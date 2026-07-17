<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file		class/powerplantpvmaintenanceperiodadvancer.class.php
 * \ingroup		powerplantpv
 * \brief		Automatic maintenance period advancement.
 */

dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/class/powerplant.class.php');
dol_include_once('/powerplantpv/class/powerplantpvmaintenancescheduler.class.php');
dol_include_once('/user/class/user.class.php');

/**
 * Advance contract maintenance periods when the current period is covered.
 */
class PowerPlantPVMaintenancePeriodAdvancer
{
	private const EXTRAFIELD_PERIOD_START = 'powerplantpv_next_maintenance_period_start';
	private const EXTRAFIELD_PERIOD_END = 'powerplantpv_next_maintenance_period_end';

	/**
	 * @var DoliDB Database handler
	 */
	private $db;

	/**
	 * @var string Error message
	 */
	public $error = '';

	/**
	 * @var array<int,string> Error messages
	 */
	public $errors = array();

	/**
	 * @var string Cron output
	 */
	public $output = '';

	/**
	 * Constructor.
	 *
	 * @param	DoliDB	$db	Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Cron entry point.
	 *
	 * @param	string	$parameters	Cron parameters
	 * @return	int					0 if OK, <0 if KO
	 */
	public function runScheduledAdvancement($parameters = '')
	{
		global $user;

		$this->output = '';
		$this->error = '';
		$this->errors = array();

		if (!isModEnabled('powerplantpv') || !getDolGlobalInt('POWERPLANTPV_MAINTENANCE_ENABLE', 1)) {
			$this->output = 'PowerPlantPV maintenance period advancement skipped: module or maintenance disabled.';
			dol_syslog(__METHOD__.' skipped disabled module or maintenance', LOG_DEBUG);
			return 0;
		}

		$runUser = $this->buildSystemUser($user);
		$stats = $this->advanceAllCoveredPeriods($runUser, 'POWERPLANTPV_MAINTENANCE_PERIOD_CRON', array('parameters' => (string) $parameters));
		$this->output = $this->formatStats($stats);

		dol_syslog(__METHOD__.' '.$this->output, !empty($stats['errors']) ? LOG_WARNING : LOG_INFO);

		return !empty($stats['errors']) ? -1 : 0;
	}

	/**
	 * Advance covered periods for every visible power plant in the current entity scope.
	 *
	 * @param	User				$user		User used for scheduler context
	 * @param	string				$reason		Source reason
	 * @param	array<string,mixed>	$context	Log context
	 * @return	array<string,mixed>				Statistics
	 */
	public function advanceAllCoveredPeriods(User $user, $reason = 'manual', array $context = array())
	{
		$ids = $this->fetchAllPowerPlantIds();

		return $this->advanceCoveredPeriodsForPowerPlants($ids, $user, $reason, $context);
	}

	/**
	 * Advance covered periods for selected power plants.
	 *
	 * @param	array<int,int>		$powerPlantIds	Power plant ids
	 * @param	User				$user			User used for scheduler context
	 * @param	string				$reason			Source reason
	 * @param	array<string,mixed>	$context		Log context
	 * @return	array<string,mixed>					Statistics
	 */
	public function advanceCoveredPeriodsForPowerPlants(array $powerPlantIds, User $user, $reason = 'trigger', array $context = array())
	{
		$stats = $this->emptyStats();
		$powerPlantIds = array_filter(array_map('intval', array_values($powerPlantIds)));
		$powerPlantIds = array_values(array_unique($powerPlantIds));
		if (empty($powerPlantIds)) {
			return $stats;
		}

		$scheduler = new PowerPlantPVMaintenanceScheduler($this->db);
		$processedContractIds = array();
		foreach ($powerPlantIds as $powerPlantId) {
			$powerPlant = $this->fetchPowerPlant((int) $powerPlantId);
			if (!$powerPlant instanceof PowerPlant) {
				continue;
			}

			$stats['checked_powerplants']++;
			$schedule = $scheduler->getScheduleForPowerPlant($powerPlant, $user, null, 1);
			$itemStats = $this->advanceCoveredSchedule(
				$schedule,
				$reason,
				array_merge($context, array('powerplant_id' => (int) $powerPlant->id)),
				$processedContractIds
			);
			$stats = $this->mergeStats($stats, $itemStats);
		}

		return $stats;
	}

	/**
	 * Advance covered periods from an already calculated schedule.
	 *
	 * @param	array<string,mixed>	$schedule				Scheduler result
	 * @param	string				$reason					Source reason
	 * @param	array<string,mixed>	$context				Log context
	 * @param	array<int,int>|null	$processedContractIds	Already advanced contract ids
	 * @return	array<string,mixed>							Statistics
	 */
	public function advanceCoveredSchedule(array $schedule, $reason = 'trigger', array $context = array(), &$processedContractIds = null)
	{
		$stats = $this->emptyStats();
		if (!is_array($processedContractIds)) {
			$processedContractIds = array();
		}

		$items = (!empty($schedule['items']) && is_array($schedule['items'])) ? $schedule['items'] : array();
		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}
			$this->advanceCoveredItem($item, $reason, $context, $stats, $processedContractIds);
		}

		return $stats;
	}

	/**
	 * Advance one scheduler item if it is covered and recurrent.
	 *
	 * @param	array<string,mixed>	$item					Scheduler item
	 * @param	string				$reason					Source reason
	 * @param	array<string,mixed>	$context				Log context
	 * @param	array<string,mixed>	$stats					Statistics
	 * @param	array<int,int>		$processedContractIds	Already advanced contract ids
	 * @return	void
	 */
	private function advanceCoveredItem(array $item, $reason, array $context, array &$stats, array &$processedContractIds)
	{
		$stats['checked_items']++;
		if ((string) ($item['status'] ?? '') !== PowerPlantPVMaintenanceScheduler::STATUS_COVERED) {
			$stats['skipped_not_covered']++;
			return;
		}
		if (empty($item['covering_intervention']) || !is_array($item['covering_intervention'])) {
			$stats['skipped_not_covered']++;
			return;
		}
		if (empty($item['maintenance_service_count'])) {
			$stats['skipped_not_required']++;
			return;
		}

		$contract = (!empty($item['contract']) && is_array($item['contract'])) ? $item['contract'] : array();
		$contractId = !empty($contract['id']) ? (int) $contract['id'] : 0;
		if ($contractId <= 0) {
			$stats['skipped_incomplete']++;
			return;
		}
		if (isset($processedContractIds[$contractId])) {
			$stats['skipped_already_processed']++;
			return;
		}

		$periodStart = !empty($item['period_start']) ? (int) $item['period_start'] : 0;
		$periodEnd = !empty($item['period_end']) ? (int) $item['period_end'] : 0;
		if ($periodStart <= 0 || $periodEnd <= 0 || $periodStart > $periodEnd) {
			$stats['skipped_incomplete']++;
			return;
		}

		$recurrence = isset($item['recurrence']) ? (string) $item['recurrence'] : '';
		if ($recurrence === PowerPlantPVMaintenanceScheduler::RECURRENCE_CUSTOM) {
			$stats['skipped_custom']++;
			return;
		}
		$months = $this->getRecurrenceMonths($recurrence);
		if ($months <= 0) {
			$stats['skipped_incomplete']++;
			return;
		}

		$newStart = $this->addMonthsClamped($periodStart, $months, false);
		$newEnd = $this->addMonthsClamped($periodEnd, $months, true);
		if ($newStart <= 0 || $newEnd <= 0 || $newStart > $newEnd) {
			$stats['errors']++;
			$this->registerError(__METHOD__.' invalid next period for contract_id='.$contractId.' recurrence='.$recurrence);
			return;
		}

		$oldStartDate = $this->timestampToSqlDate($periodStart);
		$oldEndDate = $this->timestampToSqlDate($periodEnd);
		$newStartDate = $this->timestampToSqlDate($newStart);
		$newEndDate = $this->timestampToSqlDate($newEnd);
		if ($newStartDate === '' || $newEndDate === '' || ($newStartDate === $oldStartDate && $newEndDate === $oldEndDate)) {
			$stats['skipped_incomplete']++;
			return;
		}

		if (!$this->updateContractPeriod($contractId, $newStartDate, $newEndDate)) {
			$stats['errors']++;
			return;
		}

		$processedContractIds[$contractId] = $contractId;
		$stats['advanced']++;
		$stats['advanced_contract_ids'][$contractId] = $contractId;

		$coveringIntervention = $item['covering_intervention'];
		dol_syslog(
			__METHOD__.' reason='.$reason
			.' contract_id='.$contractId
			.' intervention_id='.(int) ($coveringIntervention['id'] ?? 0)
			.' recurrence='.$recurrence
			.' old_period='.$oldStartDate.'..'.$oldEndDate
			.' new_period='.$newStartDate.'..'.$newEndDate
			.' context='.json_encode($this->sanitizeLogContext($context)),
			LOG_INFO
		);
	}

	/**
	 * Return all power plant ids visible in the current entity scope.
	 *
	 * @return	array<int,int>	Power plant ids
	 */
	private function fetchAllPowerPlantIds()
	{
		$sql = "SELECT t.rowid";
		$sql .= " FROM ".$this->db->prefix()."powerplantpv_powerplant AS t";
		$sql .= " WHERE t.entity IN (".$this->db->sanitize(getEntity('powerplant')).")";
		$sql .= " ORDER BY t.ref ASC, t.rowid ASC";

		$ids = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->registerError(__METHOD__.' power plant lookup failed: '.$this->db->lasterror());
			return $ids;
		}

		while (is_object($obj = $this->db->fetch_object($resql))) {
			$id = (int) $obj->rowid;
			if ($id > 0) {
				$ids[$id] = $id;
			}
		}
		$this->db->free($resql);

		return array_values($ids);
	}

	/**
	 * Fetch a power plant with entity filtering.
	 *
	 * @param	int	$powerPlantId	Power plant id
	 * @return	PowerPlant|null		Power plant or null
	 */
	private function fetchPowerPlant($powerPlantId)
	{
		$sql = "SELECT rowid, ref, label, fk_soc, fk_project, entity, status";
		$sql .= " FROM ".$this->db->prefix()."powerplantpv_powerplant";
		$sql .= " WHERE rowid = ".((int) $powerPlantId);
		$sql .= " AND entity IN (".$this->db->sanitize(getEntity('powerplant')).")";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->registerError(__METHOD__.' power plant lookup failed for id='.(int) $powerPlantId.': '.$this->db->lasterror());
			return null;
		}

		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($obj)) {
			return null;
		}

		$powerPlant = new PowerPlant($this->db);
		$powerPlant->id = (int) $obj->rowid;
		$powerPlant->rowid = (int) $obj->rowid;
		$powerPlant->ref = (string) $obj->ref;
		$powerPlant->label = (string) $obj->label;
		$powerPlant->fk_soc = (int) $obj->fk_soc;
		$powerPlant->fk_project = (int) $obj->fk_project;
		$powerPlant->entity = (int) $obj->entity;
		$powerPlant->status = (int) $obj->status;

		return $powerPlant;
	}

	/**
	 * Update or create the contract extrafields row carrying the next period.
	 *
	 * @param	int		$contractId		Contract id
	 * @param	string	$startDate		SQL date YYYY-MM-DD
	 * @param	string	$endDate		SQL date YYYY-MM-DD
	 * @return	bool					True if OK
	 */
	private function updateContractPeriod($contractId, $startDate, $endDate)
	{
		$contractId = (int) $contractId;
		$table = $this->db->prefix().'contrat_extrafields';
		if (!$this->tableExists($table)
			|| !$this->columnExists($table, 'fk_object')
			|| !$this->columnExists($table, self::EXTRAFIELD_PERIOD_START)
			|| !$this->columnExists($table, self::EXTRAFIELD_PERIOD_END)
		) {
			$this->registerError(__METHOD__.' contract extrafields table or maintenance period columns are missing');
			return false;
		}

		$sql = "SELECT fk_object";
		$sql .= " FROM ".$table;
		$sql .= " WHERE fk_object = ".$contractId;
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->registerError(__METHOD__.' contract extrafields lookup failed for contract_id='.$contractId.': '.$this->db->lasterror());
			return false;
		}
		$exists = ($this->db->num_rows($resql) > 0);
		$this->db->free($resql);

		if ($exists) {
			$sql = "UPDATE ".$table;
			$sql .= " SET ".self::EXTRAFIELD_PERIOD_START." = '".$this->db->escape($startDate)."'";
			$sql .= ", ".self::EXTRAFIELD_PERIOD_END." = '".$this->db->escape($endDate)."'";
			$sql .= " WHERE fk_object = ".$contractId;
		} else {
			$sql = "INSERT INTO ".$table;
			$sql .= " (fk_object, ".self::EXTRAFIELD_PERIOD_START.", ".self::EXTRAFIELD_PERIOD_END.")";
			$sql .= " VALUES (".$contractId.", '".$this->db->escape($startDate)."', '".$this->db->escape($endDate)."')";
		}

		if (!$this->db->query($sql)) {
			$this->registerError(__METHOD__.' contract extrafields update failed for contract_id='.$contractId.': '.$this->db->lasterror());
			return false;
		}

		return true;
	}

	/**
	 * Return the recurrence interval in months.
	 *
	 * @param	string	$recurrence	Recurrence code
	 * @return	int					Month interval or 0
	 */
	private function getRecurrenceMonths($recurrence)
	{
		$intervals = array(
			PowerPlantPVMaintenanceScheduler::RECURRENCE_MONTHLY => 1,
			PowerPlantPVMaintenanceScheduler::RECURRENCE_QUARTERLY => 3,
			PowerPlantPVMaintenanceScheduler::RECURRENCE_HALFYEARLY => 6,
			PowerPlantPVMaintenanceScheduler::RECURRENCE_YEARLY => 12,
			PowerPlantPVMaintenanceScheduler::RECURRENCE_BIENNIAL => 24,
		);

		return isset($intervals[$recurrence]) ? (int) $intervals[$recurrence] : 0;
	}

	/**
	 * Add months while clamping end-of-month dates.
	 *
	 * @param	int		$timestamp	Timestamp
	 * @param	int		$months		Months to add
	 * @param	bool	$endOfDay	Use end of day
	 * @return	int					New timestamp or 0
	 */
	private function addMonthsClamped($timestamp, $months, $endOfDay)
	{
		$timestamp = (int) $timestamp;
		$months = (int) $months;
		if ($timestamp <= 0 || $months <= 0) {
			return 0;
		}

		$year = (int) date('Y', $timestamp);
		$month = (int) date('n', $timestamp);
		$day = (int) date('j', $timestamp);
		$totalMonths = ($year * 12) + ($month - 1) + $months;
		$targetYear = (int) floor($totalMonths / 12);
		$targetMonth = ($totalMonths % 12) + 1;
		$lastDay = (int) date('t', mktime(12, 0, 0, $targetMonth, 1, $targetYear));
		$targetDay = min($day, $lastDay);

		return dol_mktime(
			$endOfDay ? 23 : 0,
			$endOfDay ? 59 : 0,
			$endOfDay ? 59 : 0,
			$targetMonth,
			$targetDay,
			$targetYear
		);
	}

	/**
	 * Format a timestamp as SQL date.
	 *
	 * @param	int	$timestamp	Timestamp
	 * @return	string			SQL date or empty string
	 */
	private function timestampToSqlDate($timestamp)
	{
		$timestamp = (int) $timestamp;
		if ($timestamp <= 0) {
			return '';
		}

		return date('Y-m-d', $timestamp);
	}

	/**
	 * Build a system user for cron scans.
	 *
	 * @param	mixed	$currentUser	Current global user
	 * @return	User					User object
	 */
	private function buildSystemUser($currentUser)
	{
		if ($currentUser instanceof User && empty($currentUser->socid)) {
			return $currentUser;
		}

		$runUser = new User($this->db);
		$runUser->id = 0;
		$runUser->admin = 1;
		$runUser->socid = 0;

		return $runUser;
	}

	/**
	 * Return empty statistics.
	 *
	 * @return	array<string,mixed>	Statistics
	 */
	private function emptyStats()
	{
		return array(
			'checked_powerplants' => 0,
			'checked_items' => 0,
			'advanced' => 0,
			'advanced_contract_ids' => array(),
			'skipped_not_covered' => 0,
			'skipped_not_required' => 0,
			'skipped_incomplete' => 0,
			'skipped_custom' => 0,
			'skipped_already_processed' => 0,
			'errors' => 0,
		);
	}

	/**
	 * Merge statistics.
	 *
	 * @param	array<string,mixed>	$left	Left statistics
	 * @param	array<string,mixed>	$right	Right statistics
	 * @return	array<string,mixed>			Merged statistics
	 */
	private function mergeStats(array $left, array $right)
	{
		foreach ($right as $key => $value) {
			if ($key === 'advanced_contract_ids') {
				$ids = is_array($value) ? $value : array();
				foreach ($ids as $contractId) {
					$left['advanced_contract_ids'][(int) $contractId] = (int) $contractId;
				}
				continue;
			}
			if (isset($left[$key]) && is_numeric($left[$key]) && is_numeric($value)) {
				$left[$key] = (int) $left[$key] + (int) $value;
			}
		}

		return $left;
	}

	/**
	 * Format statistics for cron output.
	 *
	 * @param	array<string,mixed>	$stats	Statistics
	 * @return	string						Output
	 */
	private function formatStats(array $stats)
	{
		$advancedContractIds = !empty($stats['advanced_contract_ids']) && is_array($stats['advanced_contract_ids'])
			? array_values(array_map('intval', $stats['advanced_contract_ids']))
			: array();

		return 'PowerPlantPV maintenance period advancement: checked_powerplants='.(int) $stats['checked_powerplants']
			.' checked_items='.(int) $stats['checked_items']
			.' advanced='.(int) $stats['advanced']
			.' errors='.(int) $stats['errors']
			.' advanced_contract_ids='.implode(',', $advancedContractIds);
	}

	/**
	 * Check table existence.
	 *
	 * @param	string	$table	Full table name
	 * @return	bool			True if table exists
	 */
	private function tableExists($table)
	{
		if (function_exists('powerplantpvDatabaseTableExists')) {
			return powerplantpvDatabaseTableExists($table);
		}

		return true;
	}

	/**
	 * Check column existence.
	 *
	 * @param	string	$table	Full table name
	 * @param	string	$column	Column name
	 * @return	bool			True if column exists
	 */
	private function columnExists($table, $column)
	{
		if (function_exists('powerplantpvDatabaseTableColumnExists')) {
			return powerplantpvDatabaseTableColumnExists($table, $column);
		}

		return true;
	}

	/**
	 * Register and log an error.
	 *
	 * @param	string	$message	Error message
	 * @return	void
	 */
	private function registerError($message)
	{
		$this->error = (string) $message;
		$this->errors[] = $this->error;
		dol_syslog($this->error, LOG_WARNING);
	}

	/**
	 * Keep log context compact and non-sensitive.
	 *
	 * @param	array<string,mixed>	$context	Raw context
	 * @return	array<string,mixed>				Sanitized context
	 */
	private function sanitizeLogContext(array $context)
	{
		$sanitized = array();
		foreach ($context as $key => $value) {
			if (is_array($value)) {
				$sanitized[(string) $key] = array_values(array_map('intval', $value));
			} elseif (is_numeric($value)) {
				$sanitized[(string) $key] = (int) $value;
			} else {
				$sanitized[(string) $key] = (string) $value;
			}
		}

		return $sanitized;
	}
}
