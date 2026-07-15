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
 * \file		class/powerplantpvmaintenancescheduler.class.php
 * \ingroup		powerplantpv
 * \brief		Maintenance scheduler for linked power plant contracts.
 */

dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/class/powerplant.class.php');
dol_include_once('/contrat/class/contrat.class.php');
dol_include_once('/fichinter/class/fichinter.class.php');

/**
 * Calculate the maintenance status of a power plant from native links.
 */
class PowerPlantPVMaintenanceScheduler
{
	public const STATUS_NOT_REQUIRED = 'not_required';
	public const STATUS_PLANNED = 'planned';
	public const STATUS_SCHEDULED = 'scheduled';
	public const STATUS_DUE = 'due';
	public const STATUS_OVERDUE = 'overdue';
	public const STATUS_COVERED = 'covered';
	public const STATUS_INCOMPLETE = 'incomplete';

	public const RECURRENCE_MONTHLY = 'monthly';
	public const RECURRENCE_QUARTERLY = 'quarterly';
	public const RECURRENCE_HALFYEARLY = 'halfyearly';
	public const RECURRENCE_YEARLY = 'yearly';
	public const RECURRENCE_BIENNIAL = 'biennial';
	public const RECURRENCE_CUSTOM = 'custom';

	public const SCHEDULED_MODE_CREATED = 'created';
	public const SCHEDULED_MODE_VALIDATED = 'validated';

	/**
	 * @var DoliDB Database handler
	 */
	private $db;

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
	 * Return supported recurrence label keys.
	 *
	 * @return	array<string,string>	Recurrence code to translation key map
	 */
	public static function getRecurrenceLabelKeys()
	{
		return array(
			self::RECURRENCE_MONTHLY => 'PowerPlantPVMaintenanceRecurrenceMonthly',
			self::RECURRENCE_QUARTERLY => 'PowerPlantPVMaintenanceRecurrenceQuarterly',
			self::RECURRENCE_HALFYEARLY => 'PowerPlantPVMaintenanceRecurrenceHalfyearly',
			self::RECURRENCE_YEARLY => 'PowerPlantPVMaintenanceRecurrenceYearly',
			self::RECURRENCE_BIENNIAL => 'PowerPlantPVMaintenanceRecurrenceBiennial',
			self::RECURRENCE_CUSTOM => 'PowerPlantPVMaintenanceRecurrenceCustom',
		);
	}

	/**
	 * Return status label key.
	 *
	 * @param	string	$status	Scheduler status
	 * @return	string			Translation key
	 */
	public static function getStatusLabelKey($status)
	{
		$labels = array(
			self::STATUS_NOT_REQUIRED => 'PowerPlantPVMaintenanceStatusNotRequired',
			self::STATUS_PLANNED => 'PowerPlantPVMaintenanceStatusPlanned',
			self::STATUS_SCHEDULED => 'PowerPlantPVMaintenanceStatusScheduled',
			self::STATUS_DUE => 'PowerPlantPVMaintenanceStatusDue',
			self::STATUS_OVERDUE => 'PowerPlantPVMaintenanceStatusOverdue',
			self::STATUS_COVERED => 'PowerPlantPVMaintenanceStatusCovered',
			self::STATUS_INCOMPLETE => 'PowerPlantPVMaintenanceStatusIncomplete',
		);

		return isset($labels[$status]) ? $labels[$status] : 'Unknown';
	}

	/**
	 * Return native Dolibarr status type for display.
	 *
	 * @param	string	$status	Scheduler status
	 * @return	string			Dolibarr status type
	 */
	public static function getStatusType($status)
	{
		$statusTypes = array(
			self::STATUS_NOT_REQUIRED => 'status0',
			self::STATUS_PLANNED => 'status2',
			self::STATUS_SCHEDULED => 'status1',
			self::STATUS_DUE => 'status3',
			self::STATUS_OVERDUE => 'status8',
			self::STATUS_COVERED => 'status4',
			self::STATUS_INCOMPLETE => 'status5',
		);

		return isset($statusTypes[$status]) ? $statusTypes[$status] : 'status0';
	}

	/**
	 * Return the maintenance schedule for a power plant.
	 *
	 * @param	CommonObject	$powerplant		Power plant object
	 * @param	User			$user			Current user
	 * @param	int|null		$referenceDate	Reference timestamp, defaults to today
	 * @param	int<0,1>		$systemMode		1 to bypass UI permission filters during system recomputation
	 * @return	array<string,mixed>				Schedule result
	 */
	public function getScheduleForPowerPlant($powerplant, $user, $referenceDate = null, $systemMode = 0)
	{
		$contracts = $this->fetchLinkedContracts($powerplant, $user, $systemMode);
		$interventions = $this->fetchLinkedInterventions($powerplant, $user, $systemMode);
		$items = array();
		$referenceDate = !empty($referenceDate) ? (int) $referenceDate : dol_now();

		foreach ($contracts as $contract) {
			$services = $this->fetchActiveServicesWithMaintenancePrestations((int) $contract['id']);
			$maintenanceServiceCount = $this->countMaintenancePrestations($services);
			$periodStart = $this->sqlDateToTimestamp((string) $contract['period_start'], false);
			$periodEnd = $this->sqlDateToTimestamp((string) $contract['period_end'], true);
			$coveringIntervention = null;
			$scheduledIntervention = null;

			if ($maintenanceServiceCount > 0 && $periodStart > 0 && $periodEnd > 0 && $periodStart <= $periodEnd) {
				$coveringIntervention = $this->findCoveringIntervention($contract, $interventions, $periodStart, $periodEnd);
				if (!is_array($coveringIntervention)) {
					$scheduledIntervention = $this->findScheduledIntervention($contract, $interventions, $periodStart, $periodEnd);
				}
			}

			$status = $this->calculateStatus($maintenanceServiceCount, $periodStart, $periodEnd, $coveringIntervention, $scheduledIntervention, $referenceDate);
			$items[] = array(
				'contract' => $contract,
				'active_services' => $services,
				'maintenance_service_count' => $maintenanceServiceCount,
				'recurrence' => $this->normalizeRecurrence((string) $contract['recurrence']),
				'period_start' => $periodStart,
				'period_end' => $periodEnd,
				'covering_intervention' => $coveringIntervention,
				'scheduled_intervention' => $scheduledIntervention,
				'status' => $status,
				'is_eligible' => ($maintenanceServiceCount > 0),
			);
		}

		return array(
			'contracts' => $contracts,
			'interventions' => $interventions,
			'items' => $items,
			'summary' => $this->buildSummary($items, $contracts),
		);
	}

	/**
	 * Return global maintenance rows calculated from every visible power plant.
	 *
	 * @param	User				$user			Current user
	 * @param	array<string,mixed>	$filters		Filters
	 * @param	int|null			$referenceDate	Reference timestamp, defaults to today
	 * @return	array<int,array<string,mixed>>		Maintenance rows
	 */
	public function getMaintenanceRows($user, array $filters = array(), $referenceDate = null)
	{
		$powerplants = $this->fetchPowerPlantsForMaintenanceRows($user, $filters);
		$referenceDate = !empty($referenceDate) ? (int) $referenceDate : dol_now();
		$rows = array();

		foreach ($powerplants as $powerplant) {
			$schedule = $this->getScheduleForPowerPlant($powerplant, $user, $referenceDate);
			$items = (!empty($schedule['items']) && is_array($schedule['items'])) ? $schedule['items'] : array();
			if (empty($items)) {
				$emptyItem = array(
					'contract' => array(),
					'active_services' => array(),
					'maintenance_service_count' => 0,
					'recurrence' => '',
					'period_start' => 0,
					'period_end' => 0,
					'covering_intervention' => null,
					'scheduled_intervention' => null,
					'status' => self::STATUS_NOT_REQUIRED,
					'is_eligible' => false,
				);
				if ($this->maintenanceRowMatchesFilters($powerplant, $emptyItem, $filters)) {
					$rows[] = $this->buildMaintenanceRow($powerplant, $emptyItem);
				}
				continue;
			}

			foreach ($items as $item) {
				if (!is_array($item)) {
					continue;
				}
				if ($this->maintenanceRowMatchesFilters($powerplant, $item, $filters)) {
					$rows[] = $this->buildMaintenanceRow($powerplant, $item);
				}
			}
		}

		usort($rows, array($this, 'sortMaintenanceRows'));

		return $rows;
	}

	/**
	 * Fetch visible power plants used by global maintenance pages.
	 *
	 * @param	User				$user		Current user
	 * @param	array<string,mixed>	$filters	Filters
	 * @return	array<int,PowerPlant>			Power plants
	 */
	private function fetchPowerPlantsForMaintenanceRows($user, array $filters)
	{
		if (!function_exists('powerplantpvUserHasRightPath') || !powerplantpvUserHasRightPath($user, array('powerplantpv', 'powerplant', 'read'))) {
			return array();
		}

		$powerplantId = !empty($filters['fk_powerplant']) ? (int) $filters['fk_powerplant'] : 0;
		$socid = !empty($filters['fk_soc']) ? (int) $filters['fk_soc'] : 0;
		if (!empty($user->socid)) {
			$socid = (int) $user->socid;
		}

		$sql = "SELECT t.rowid, t.ref, t.label, t.fk_soc, t.fk_project, t.entity, t.status";
		$sql .= " FROM ".$this->db->prefix()."powerplantpv_powerplant AS t";
		$sql .= " WHERE t.entity IN (".$this->db->sanitize(getEntity('powerplant')).")";
		$entityFilters = $this->normalizeEntityFilters(isset($filters['entities']) ? $filters['entities'] : array());
		if (!empty($entityFilters)) {
			$sql .= " AND t.entity IN (".implode(',', $entityFilters).")";
		}
		if ($powerplantId > 0) {
			$sql .= " AND t.rowid = ".$powerplantId;
		}
		if ($socid > 0) {
			$sql .= " AND t.fk_soc = ".$socid;
		}
		$sql .= " ORDER BY t.ref ASC, t.rowid ASC";

		$powerplants = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' power plant lookup failed: '.$this->db->lasterror(), LOG_WARNING);
			return $powerplants;
		}

		while (is_object($obj = $this->db->fetch_object($resql))) {
			$powerplant = new PowerPlant($this->db);
			$powerplant->id = (int) $obj->rowid;
			$powerplant->rowid = (int) $obj->rowid;
			$powerplant->ref = (string) $obj->ref;
			$powerplant->label = (string) $obj->label;
			$powerplant->fk_soc = (int) $obj->fk_soc;
			$powerplant->fk_project = (int) $obj->fk_project;
			$powerplant->entity = (int) $obj->entity;
			$powerplant->status = (int) $obj->status;
			$powerplants[(int) $obj->rowid] = $powerplant;
		}
		$this->db->free($resql);

		return array_values($powerplants);
	}

	/**
	 * Build one global maintenance row.
	 *
	 * @param	PowerPlant			$powerplant	Power plant
	 * @param	array<string,mixed>	$item		Scheduler item
	 * @return	array<string,mixed>				Maintenance row
	 */
	private function buildMaintenanceRow($powerplant, array $item)
	{
		$contract = (!empty($item['contract']) && is_array($item['contract'])) ? $item['contract'] : array();
		$coveringIntervention = (!empty($item['covering_intervention']) && is_array($item['covering_intervention'])) ? $item['covering_intervention'] : null;
		$scheduledIntervention = (!empty($item['scheduled_intervention']) && is_array($item['scheduled_intervention'])) ? $item['scheduled_intervention'] : null;

		return array(
			'powerplant' => $powerplant,
			'powerplant_id' => (int) $powerplant->id,
			'powerplant_ref' => (string) $powerplant->ref,
			'entity' => (int) $powerplant->entity,
			'fk_soc' => !empty($contract['fk_soc']) ? (int) $contract['fk_soc'] : (int) $powerplant->fk_soc,
			'contract' => $contract,
			'contract_id' => !empty($contract['id']) ? (int) $contract['id'] : 0,
			'active_services' => (!empty($item['active_services']) && is_array($item['active_services'])) ? $item['active_services'] : array(),
			'maintenance_service_count' => !empty($item['maintenance_service_count']) ? (int) $item['maintenance_service_count'] : 0,
			'recurrence' => isset($item['recurrence']) ? (string) $item['recurrence'] : '',
			'period_start' => !empty($item['period_start']) ? (int) $item['period_start'] : 0,
			'period_end' => !empty($item['period_end']) ? (int) $item['period_end'] : 0,
			'covering_intervention' => $coveringIntervention,
			'scheduled_intervention' => $scheduledIntervention,
			'status' => isset($item['status']) ? (string) $item['status'] : self::STATUS_NOT_REQUIRED,
			'is_eligible' => !empty($item['is_eligible']),
			'item' => $item,
		);
	}

	/**
	 * Check if one maintenance item matches filters.
	 *
	 * @param	PowerPlant			$powerplant	Power plant
	 * @param	array<string,mixed>	$item		Scheduler item
	 * @param	array<string,mixed>	$filters	Filters
	 * @return	bool							True if the item must be kept
	 */
	private function maintenanceRowMatchesFilters($powerplant, array $item, array $filters)
	{
		$contract = (!empty($item['contract']) && is_array($item['contract'])) ? $item['contract'] : array();
		$status = isset($item['status']) ? (string) $item['status'] : self::STATUS_NOT_REQUIRED;
		$socid = !empty($contract['fk_soc']) ? (int) $contract['fk_soc'] : (int) $powerplant->fk_soc;
		$entityFilters = $this->normalizeEntityFilters(isset($filters['entities']) ? $filters['entities'] : array());
		if (!empty($entityFilters) && !in_array((int) $powerplant->entity, $entityFilters, true)) {
			return false;
		}

		$statusFilters = $this->normalizeStatusFilters($filters);
		if (!empty($statusFilters) && !in_array($status, $statusFilters, true)) {
			return false;
		}
		if (!empty($filters['fk_soc']) && $socid !== (int) $filters['fk_soc']) {
			return false;
		}

		$dateStart = $this->normalizeFilterTimestamp(isset($filters['date_start']) ? $filters['date_start'] : 0, false);
		$dateEnd = $this->normalizeFilterTimestamp(isset($filters['date_end']) ? $filters['date_end'] : 0, true);
		$periodStart = !empty($item['period_start']) ? (int) $item['period_start'] : 0;
		$periodEnd = !empty($item['period_end']) ? (int) $item['period_end'] : 0;
		if ($dateStart > 0 && ($periodEnd <= 0 || $periodEnd < $dateStart)) {
			return false;
		}
		if ($dateEnd > 0 && ($periodStart <= 0 || $periodStart > $dateEnd)) {
			return false;
		}

		$maintenanceServiceId = !empty($filters['maintenance_service']) ? (int) $filters['maintenance_service'] : 0;
		if ($maintenanceServiceId > 0 && !$this->itemHasMaintenanceService($item, $maintenanceServiceId)) {
			return false;
		}

		$natureId = !empty($filters['intervention_nature']) ? (int) $filters['intervention_nature'] : 0;
		if ($natureId > 0) {
			$coveringIntervention = (!empty($item['covering_intervention']) && is_array($item['covering_intervention'])) ? $item['covering_intervention'] : null;
			$scheduledIntervention = (!empty($item['scheduled_intervention']) && is_array($item['scheduled_intervention'])) ? $item['scheduled_intervention'] : null;
			$matchingIntervention = is_array($coveringIntervention) ? $coveringIntervention : $scheduledIntervention;
			if (!is_array($matchingIntervention) || (int) $matchingIntervention['intervention_nature_id'] !== $natureId) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if one item contains a maintenance service id.
	 *
	 * @param	array<string,mixed>	$item					Scheduler item
	 * @param	int					$maintenanceServiceId	Maintenance service id
	 * @return	bool										True if found
	 */
	private function itemHasMaintenanceService(array $item, $maintenanceServiceId)
	{
		$services = (!empty($item['active_services']) && is_array($item['active_services'])) ? $item['active_services'] : array();
		foreach ($services as $service) {
			$ids = (!empty($service['maintenance_service_ids']) && is_array($service['maintenance_service_ids'])) ? $service['maintenance_service_ids'] : array();
			if (in_array((int) $maintenanceServiceId, array_map('intval', $ids), true)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalize status filters.
	 *
	 * @param	array<string,mixed>	$filters	Raw filters
	 * @return	array<int,string>				Accepted status codes
	 */
	private function normalizeStatusFilters(array $filters)
	{
		$values = array();
		foreach (array('status', 'statuses') as $key) {
			if (empty($filters[$key])) {
				continue;
			}
			if (is_array($filters[$key])) {
				foreach ($filters[$key] as $status) {
					$status = trim((string) $status);
					if ($status !== '') {
						$values[$status] = $status;
					}
				}
			} else {
				foreach (explode(',', (string) $filters[$key]) as $status) {
					$status = trim($status);
					if ($status !== '') {
						$values[$status] = $status;
					}
				}
			}
		}

		return array_values($values);
	}

	/**
	 * Normalize entity filters.
	 *
	 * @param	mixed	$value	Raw entity ids
	 * @return	int[]			Positive entity ids
	 */
	private function normalizeEntityFilters($value)
	{
		$values = is_array($value) ? $value : explode(',', (string) $value);
		$entities = array();
		foreach ($values as $entityId) {
			$entityId = (int) $entityId;
			if ($entityId > 0) {
				$entities[$entityId] = $entityId;
			}
		}

		return array_values($entities);
	}

	/**
	 * Normalize a timestamp or SQL date filter.
	 *
	 * @param	mixed	$value		Filter value
	 * @param	bool	$endOfDay	Use end of day
	 * @return	int					Timestamp or 0
	 */
	private function normalizeFilterTimestamp($value, $endOfDay)
	{
		if (empty($value)) {
			return 0;
		}
		if (is_int($value) || ctype_digit((string) $value)) {
			$timestamp = (int) $value;
			return $timestamp > 0 ? $this->dayBoundary($timestamp, $endOfDay) : 0;
		}

		return $this->sqlDateToTimestamp((string) $value, $endOfDay);
	}

	/**
	 * Sort global maintenance rows by period, status priority and reference.
	 *
	 * @param	array<string,mixed>	$a	First row
	 * @param	array<string,mixed>	$b	Second row
	 * @return	int						Sort result
	 */
	private function sortMaintenanceRows(array $a, array $b)
	{
		$statusPriority = array(
			self::STATUS_OVERDUE => 10,
			self::STATUS_DUE => 20,
			self::STATUS_SCHEDULED => 30,
			self::STATUS_PLANNED => 40,
			self::STATUS_INCOMPLETE => 50,
			self::STATUS_COVERED => 60,
			self::STATUS_NOT_REQUIRED => 70,
		);
		$periodA = !empty($a['period_start']) ? (int) $a['period_start'] : PHP_INT_MAX;
		$periodB = !empty($b['period_start']) ? (int) $b['period_start'] : PHP_INT_MAX;
		if ($periodA !== $periodB) {
			return $periodA <=> $periodB;
		}
		$priorityA = isset($statusPriority[(string) $a['status']]) ? $statusPriority[(string) $a['status']] : 99;
		$priorityB = isset($statusPriority[(string) $b['status']]) ? $statusPriority[(string) $b['status']] : 99;
		if ($priorityA !== $priorityB) {
			return $priorityA <=> $priorityB;
		}

		return strnatcasecmp((string) $a['powerplant_ref'], (string) $b['powerplant_ref']);
	}

	/**
	 * Fetch linked validated contracts visible from the current entity scope.
	 *
	 * @param	CommonObject	$powerplant	Power plant object
	 * @param	User			$user		Current user
	 * @param	int<0,1>		$systemMode	1 to bypass UI permission filters during system recomputation
	 * @return	array<int,array<string,mixed>>	Linked contracts
	 */
	private function fetchLinkedContracts($powerplant, $user, $systemMode = 0)
	{
		if (empty($powerplant->id)) {
			return array();
		}
		if (empty($systemMode) && function_exists('powerplantpvUserHasRightPath') && !powerplantpvUserHasRightPath($user, array('contrat', 'lire'))) {
			return array();
		}

		$contractExtraTable = $this->db->prefix().'contrat_extrafields';
		$hasContractExtra = $this->tableExists($contractExtraTable)
			&& $this->columnExists($contractExtraTable, 'powerplantpv_maintenance_recurrence')
			&& $this->columnExists($contractExtraTable, 'powerplantpv_next_maintenance_period_start')
			&& $this->columnExists($contractExtraTable, 'powerplantpv_next_maintenance_period_end');
		$contractStatusValidated = $this->getContractValidatedStatus();
		$powerPlantTypes = $this->getSqlStringList(powerplantpvGetPowerPlantLinkTypes());

		$sql = "SELECT DISTINCT c.rowid, c.ref, c.ref_customer, c.fk_soc, c.fk_projet, c.statut, c.entity";
		$sql .= ", s.nom as thirdparty_name";
		if ($hasContractExtra) {
			$sql .= ", ce.powerplantpv_maintenance_recurrence as recurrence";
			$sql .= ", ce.powerplantpv_next_maintenance_period_start as period_start";
			$sql .= ", ce.powerplantpv_next_maintenance_period_end as period_end";
		} else {
			$sql .= ", '' as recurrence, NULL as period_start, NULL as period_end";
		}
		$sql .= " FROM ".$this->db->prefix()."element_element AS ee";
		$sql .= " INNER JOIN ".$this->db->prefix()."contrat AS c ON (";
		$sql .= "(ee.sourcetype = 'contrat' AND ee.fk_source = c.rowid AND ee.targettype IN (".$powerPlantTypes.") AND ee.fk_target = ".((int) $powerplant->id).")";
		$sql .= " OR ";
		$sql .= "(ee.targettype = 'contrat' AND ee.fk_target = c.rowid AND ee.sourcetype IN (".$powerPlantTypes.") AND ee.fk_source = ".((int) $powerplant->id).")";
		$sql .= ")";
		$sql .= " LEFT JOIN ".$this->db->prefix()."societe AS s ON s.rowid = c.fk_soc";
		if ($hasContractExtra) {
			$sql .= " LEFT JOIN ".$contractExtraTable." AS ce ON ce.fk_object = c.rowid";
		}
		$sql .= " WHERE c.entity IN (".$this->db->sanitize(getEntity('contrat')).")";
		$sql .= " AND c.statut = ".((int) $contractStatusValidated);
		if (!empty($user->socid)) {
			$sql .= " AND c.fk_soc = ".((int) $user->socid);
		}
		$sql .= " ORDER BY c.ref ASC, c.rowid ASC";

		$contracts = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' contract lookup failed: '.$this->db->lasterror(), LOG_WARNING);
			return $contracts;
		}

		while (is_object($obj = $this->db->fetch_object($resql))) {
			$contracts[(int) $obj->rowid] = array(
				'id' => (int) $obj->rowid,
				'ref' => (string) $obj->ref,
				'ref_customer' => (string) $obj->ref_customer,
				'fk_soc' => (int) $obj->fk_soc,
				'fk_project' => (int) $obj->fk_projet,
				'status' => (int) $obj->statut,
				'entity' => (int) $obj->entity,
				'thirdparty_name' => (string) $obj->thirdparty_name,
				'recurrence' => isset($obj->recurrence) ? (string) $obj->recurrence : '',
				'period_start' => isset($obj->period_start) ? (string) $obj->period_start : '',
				'period_end' => isset($obj->period_end) ? (string) $obj->period_end : '',
			);
		}
		$this->db->free($resql);

		return array_values($contracts);
	}

	/**
	 * Fetch active service lines and their active maintenance prestations.
	 *
	 * @param	int	$contractId	Contract id
	 * @return	array<int,array<string,mixed>>	Service lines
	 */
	private function fetchActiveServicesWithMaintenancePrestations($contractId)
	{
		$productExtraTable = $this->db->prefix().'product_extrafields';
		$hasProductExtra = $this->tableExists($productExtraTable)
			&& $this->columnExists($productExtraTable, 'powerplantpv_maintenance_services');
		$serviceStatusOpen = $this->getContractLineOpenStatus();

		$sql = "SELECT d.rowid, d.fk_product, d.description, d.qty, d.statut, d.rang";
		$sql .= ", p.ref as product_ref, p.label as product_label";
		if ($hasProductExtra) {
			$sql .= ", pe.powerplantpv_maintenance_services as maintenance_services";
		} else {
			$sql .= ", '' as maintenance_services";
		}
		$sql .= " FROM ".$this->db->prefix()."contratdet AS d";
		$sql .= " LEFT JOIN ".$this->db->prefix()."product AS p ON p.rowid = d.fk_product";
		if ($hasProductExtra) {
			$sql .= " LEFT JOIN ".$productExtraTable." AS pe ON pe.fk_object = d.fk_product";
		}
		$sql .= " WHERE d.fk_contrat = ".((int) $contractId);
		$sql .= " AND d.statut = ".((int) $serviceStatusOpen);
		$sql .= " AND d.product_type = 1";
		$sql .= " AND d.fk_product > 0";
		$sql .= " AND (p.rowid IS NULL OR p.entity IN (".$this->db->sanitize(getEntity('product'))."))";
		$sql .= " ORDER BY d.rang ASC, d.rowid ASC";

		$lines = array();
		$maintenanceIds = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' active service lookup failed: '.$this->db->lasterror(), LOG_WARNING);
			return $lines;
		}

		while (is_object($obj = $this->db->fetch_object($resql))) {
			$lineMaintenanceIds = $this->parseMaintenanceServiceIds(isset($obj->maintenance_services) ? $obj->maintenance_services : '');
			foreach ($lineMaintenanceIds as $maintenanceId) {
				$maintenanceIds[$maintenanceId] = $maintenanceId;
			}
			$lines[] = array(
				'id' => (int) $obj->rowid,
				'fk_product' => (int) $obj->fk_product,
				'description' => (string) $obj->description,
				'qty' => (float) $obj->qty,
				'status' => (int) $obj->statut,
				'rang' => (int) $obj->rang,
				'product_ref' => (string) $obj->product_ref,
				'product_label' => (string) $obj->product_label,
				'maintenance_service_ids' => $lineMaintenanceIds,
				'maintenance_services' => array(),
			);
		}
		$this->db->free($resql);

		if (empty($lines) || empty($maintenanceIds)) {
			return $lines;
		}

		$serviceLabels = $this->fetchMaintenanceServiceLabels(array_values($maintenanceIds));
		foreach ($lines as $lineKey => $line) {
			foreach ($line['maintenance_service_ids'] as $maintenanceId) {
				if (isset($serviceLabels[$maintenanceId])) {
					$lines[$lineKey]['maintenance_services'][] = $serviceLabels[$maintenanceId];
				}
			}
		}

		return $lines;
	}

	/**
	 * Fetch linked interventions visible from the current entity scope.
	 *
	 * @param	CommonObject	$powerplant	Power plant object
	 * @param	User			$user		Current user
	 * @param	int<0,1>		$systemMode	1 to bypass UI permission filters during system recomputation
	 * @return	array<int,array<string,mixed>>	Linked interventions
	 */
	private function fetchLinkedInterventions($powerplant, $user, $systemMode = 0)
	{
		if (empty($powerplant->id)) {
			return array();
		}
		if (empty($systemMode) && function_exists('powerplantpvUserHasRightPath') && !powerplantpvUserHasRightPath($user, array('ficheinter', 'lire'))) {
			return array();
		}

		$fichinterTable = $this->db->prefix().'fichinter';
		$fichinterExtraTable = $this->db->prefix().'fichinter_extrafields';
		$natureTable = $this->db->prefix().'c_powerplantpv_intervention_nature';
		$hasFichinterExtra = $this->tableExists($fichinterExtraTable)
			&& $this->columnExists($fichinterExtraTable, 'powerplantpv_intervention_nature')
			&& $this->tableExists($natureTable);
		$hasContractColumn = $this->columnExists($fichinterTable, 'fk_contrat');
		$hasSignedStatusColumn = $this->columnExists($fichinterTable, 'signed_status');
		$hasDateValidColumn = $this->columnExists($fichinterTable, 'date_valid');
		$closingDateColumns = $this->getExistingColumns($fichinterTable, array('date_cloture', 'date_close', 'date_closed', 'date_closing'));
		$signatureDateColumns = $this->getExistingColumns(
			$fichinterTable,
			array(
				'date_signature',
				'signature_date',
				'date_signed',
				'signed_date',
				'online_sign_date',
				'date_online_signature',
				'date_sign',
				'sign_date',
			)
		);
		$powerPlantTypes = $this->getSqlStringList(powerplantpvGetPowerPlantLinkTypes());

		$sql = "SELECT DISTINCT f.rowid, f.ref, f.ref_client, f.fk_soc, f.fk_projet, f.fk_statut, f.entity";
		$sql .= ", f.dateo, f.datee, f.datei, f.datet";
		$sql .= ($hasContractColumn ? ", f.fk_contrat" : ", 0 as fk_contrat");
		$sql .= ($hasSignedStatusColumn ? ", f.signed_status" : ", 0 as signed_status");
		$sql .= ($hasDateValidColumn ? ", f.date_valid" : ", NULL as date_valid");
		foreach ($closingDateColumns as $idx => $column) {
			$sql .= ", f.".$column." as powerplantpv_closing_date_".((int) $idx);
		}
		foreach ($signatureDateColumns as $idx => $column) {
			$sql .= ", f.".$column." as powerplantpv_signature_date_".((int) $idx);
		}
		if ($hasFichinterExtra) {
			$sql .= ", fe.powerplantpv_intervention_nature as intervention_nature_id";
			$sql .= ", n.code as nature_code, n.label as nature_label, n.label_en as nature_label_en";
			$sql .= ", n.is_maintenance as nature_is_maintenance, n.active as nature_active";
		} else {
			$sql .= ", 0 as intervention_nature_id, '' as nature_code, '' as nature_label, '' as nature_label_en";
			$sql .= ", 0 as nature_is_maintenance, 0 as nature_active";
		}
		$sql .= ", fd.line_start, fd.line_end";
		$sql .= " FROM ".$this->db->prefix()."element_element AS ee";
		$sql .= " INNER JOIN ".$fichinterTable." AS f ON (";
		$sql .= "(ee.sourcetype = 'fichinter' AND ee.fk_source = f.rowid AND ee.targettype IN (".$powerPlantTypes.") AND ee.fk_target = ".((int) $powerplant->id).")";
		$sql .= " OR ";
		$sql .= "(ee.targettype = 'fichinter' AND ee.fk_target = f.rowid AND ee.sourcetype IN (".$powerPlantTypes.") AND ee.fk_source = ".((int) $powerplant->id).")";
		$sql .= ")";
		if ($hasFichinterExtra) {
			$sql .= " LEFT JOIN ".$fichinterExtraTable." AS fe ON fe.fk_object = f.rowid";
			$sql .= " LEFT JOIN ".$natureTable." AS n ON n.rowid = fe.powerplantpv_intervention_nature";
			$sql .= " AND n.entity IN (".$this->db->sanitize(getEntity('c_powerplantpv_intervention_nature')).")";
		}
		$sql .= " LEFT JOIN (";
		$sql .= " SELECT fk_fichinter, MIN(date) as line_start, MAX(date) as line_end";
		$sql .= " FROM ".$this->db->prefix()."fichinterdet";
		$sql .= " GROUP BY fk_fichinter";
		$sql .= ") AS fd ON fd.fk_fichinter = f.rowid";
		$sql .= " WHERE f.entity IN (".$this->db->sanitize(getEntity('fichinter')).")";
		if (!empty($user->socid)) {
			$sql .= " AND f.fk_soc = ".((int) $user->socid);
		}
		$sql .= " ORDER BY COALESCE(f.datee, f.dateo, f.datei, f.datet, fd.line_end, fd.line_start) DESC, f.rowid DESC";

		$interventions = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' intervention lookup failed: '.$this->db->lasterror(), LOG_WARNING);
			return $interventions;
		}

		while (is_object($obj = $this->db->fetch_object($resql))) {
			$isClosed = ((int) $obj->fk_statut === $this->getFichinterClosedStatus());
			$isSignedCovering = in_array((int) $obj->signed_status, $this->getFichinterCoveringSignedStatuses(), true);
			$interventionStart = $this->firstDateTimestamp(array($obj->dateo, $obj->line_start, $obj->datei), false);
			$interventionEnd = $this->firstDateTimestamp(array($obj->datee, $obj->line_end, $obj->dateo, $obj->line_start, $obj->datei), true);
			if ($interventionStart <= 0 || $interventionEnd <= 0) {
				$fallbackStart = $this->getInterventionFinalizationTimestamp(
					$obj,
					$isClosed,
					$isSignedCovering,
					$closingDateColumns,
					$signatureDateColumns,
					false
				);
				$fallbackEnd = $this->getInterventionFinalizationTimestamp(
					$obj,
					$isClosed,
					$isSignedCovering,
					$closingDateColumns,
					$signatureDateColumns,
					true
				);
				if ($interventionStart <= 0 && $fallbackStart > 0) {
					$interventionStart = $fallbackStart;
				}
				if ($interventionEnd <= 0 && $fallbackEnd > 0) {
					$interventionEnd = $fallbackEnd;
				}
				if ($interventionStart > 0 && $interventionEnd > 0 && $interventionStart > $interventionEnd
					&& $fallbackStart > 0 && $fallbackEnd > 0
				) {
					$interventionStart = $fallbackStart;
					$interventionEnd = $fallbackEnd;
				}
			}
			$interventions[(int) $obj->rowid] = array(
				'id' => (int) $obj->rowid,
				'ref' => (string) $obj->ref,
				'ref_client' => (string) $obj->ref_client,
				'fk_soc' => (int) $obj->fk_soc,
				'fk_project' => (int) $obj->fk_projet,
				'fk_contrat' => (int) $obj->fk_contrat,
				'status' => (int) $obj->fk_statut,
				'signed_status' => (int) $obj->signed_status,
				'entity' => (int) $obj->entity,
				'date_start' => $interventionStart,
				'date_end' => $interventionEnd,
				'intervention_nature_id' => (int) $obj->intervention_nature_id,
				'nature_code' => (string) $obj->nature_code,
				'nature_label' => $this->localizedDictionaryLabel($obj, 'nature_label'),
				'nature_is_maintenance' => (int) $obj->nature_is_maintenance,
				'nature_active' => (int) $obj->nature_active,
				'contract_ids' => array(),
				'is_closed' => $isClosed,
				'is_signed_covering' => $isSignedCovering,
			);
		}
		$this->db->free($resql);

		$canReadContracts = !function_exists('powerplantpvUserHasRightPath') || powerplantpvUserHasRightPath($user, array('contrat', 'lire'));
		if (!empty($interventions) && $canReadContracts) {
			$contractLinks = $this->fetchInterventionContractLinks(array_keys($interventions));
			foreach ($interventions as $interventionId => $intervention) {
				$contractIds = isset($contractLinks[$interventionId]) ? $contractLinks[$interventionId] : array();
				if (!empty($intervention['fk_contrat']) && $this->isContractVisible((int) $intervention['fk_contrat'])) {
					$contractIds[(int) $intervention['fk_contrat']] = (int) $intervention['fk_contrat'];
				}
				$interventions[$interventionId]['contract_ids'] = array_values($contractIds);
			}
		}

		return array_values($interventions);
	}

	/**
	 * Find the latest covering intervention for a period.
	 *
	 * @param	array<string,mixed>			$contract		Contract row
	 * @param	array<int,array<string,mixed>>	$interventions	Linked interventions
	 * @param	int							$periodStart	Period start timestamp
	 * @param	int							$periodEnd		Period end timestamp
	 * @return	array<string,mixed>|null					Covering intervention, if any
	 */
	private function findCoveringIntervention($contract, $interventions, $periodStart, $periodEnd)
	{
		$covering = null;
		$contractId = (int) $contract['id'];

		foreach ($interventions as $intervention) {
			if (!$this->isInterventionCoveringPeriod($intervention, $contractId, $periodStart, $periodEnd)) {
				continue;
			}
			if ($covering === null || (int) $intervention['date_end'] > (int) $covering['date_end']) {
				$covering = $intervention;
			}
		}

		return $covering;
	}

	/**
	 * Find the latest scheduled intervention for a period.
	 *
	 * @param	array<string,mixed>			$contract		Contract row
	 * @param	array<int,array<string,mixed>>	$interventions	Linked interventions
	 * @param	int							$periodStart	Period start timestamp
	 * @param	int							$periodEnd		Period end timestamp
	 * @return	array<string,mixed>|null					Scheduled intervention, if any
	 */
	private function findScheduledIntervention($contract, $interventions, $periodStart, $periodEnd)
	{
		$scheduled = null;
		$contractId = (int) $contract['id'];

		foreach ($interventions as $intervention) {
			if (!$this->isInterventionScheduledForPeriod($intervention, $contractId, $periodStart, $periodEnd)) {
				continue;
			}
			if ($scheduled === null || (int) $intervention['date_end'] > (int) $scheduled['date_end']) {
				$scheduled = $intervention;
			}
		}

		return $scheduled;
	}

	/**
	 * Check whether an intervention covers a maintenance period.
	 *
	 * @param	array<string,mixed>	$intervention	Intervention row
	 * @param	int					$contractId		Contract id
	 * @param	int					$periodStart	Expected period start
	 * @param	int					$periodEnd		Expected period end
	 * @return	bool								True if the intervention covers the period
	 */
	private function isInterventionCoveringPeriod($intervention, $contractId, $periodStart, $periodEnd)
	{
		if (empty($intervention['is_closed']) && empty($intervention['is_signed_covering'])) {
			return false;
		}

		return $this->interventionMatchesMaintenancePeriod($intervention, $contractId, $periodStart, $periodEnd);
	}

	/**
	 * Check whether an intervention schedules a maintenance period.
	 *
	 * @param	array<string,mixed>	$intervention	Intervention row
	 * @param	int					$contractId		Contract id
	 * @param	int					$periodStart	Expected period start
	 * @param	int					$periodEnd		Expected period end
	 * @return	bool							True if the intervention schedules the period
	 */
	private function isInterventionScheduledForPeriod($intervention, $contractId, $periodStart, $periodEnd)
	{
		$mode = getDolGlobalString('POWERPLANTPV_MAINTENANCE_SCHEDULED_INTERVENTION_MODE', self::SCHEDULED_MODE_CREATED);
		if (!in_array($mode, array(self::SCHEDULED_MODE_CREATED, self::SCHEDULED_MODE_VALIDATED), true)) {
			$mode = self::SCHEDULED_MODE_CREATED;
		}

		return self::isScheduledInterventionMatchingPeriod($intervention, $contractId, $periodStart, $periodEnd, $mode);
	}

	/**
	 * Evaluate the scheduled-intervention policy without database access.
	 *
	 * @param	array<string,mixed>	$intervention	Intervention row
	 * @param	int					$contractId		Contract id
	 * @param	int					$periodStart	Period start
	 * @param	int					$periodEnd		Period end
	 * @param	string				$mode			created or validated
	 * @return	bool							True when scheduled
	 */
	public static function isScheduledInterventionMatchingPeriod(array $intervention, $contractId, $periodStart, $periodEnd, $mode)
	{
		if (!empty($intervention['is_closed']) || !empty($intervention['is_signed_covering'])) {
			return false;
		}
		$draft = (class_exists('Fichinter') && defined('Fichinter::STATUS_DRAFT')) ? (int) constant('Fichinter::STATUS_DRAFT') : 0;
		$validated = (class_exists('Fichinter') && defined('Fichinter::STATUS_VALIDATED')) ? (int) constant('Fichinter::STATUS_VALIDATED') : 1;
		$billed = (class_exists('Fichinter') && defined('Fichinter::STATUS_BILLED')) ? (int) constant('Fichinter::STATUS_BILLED') : 2;
		$allowedStatuses = ($mode === self::SCHEDULED_MODE_VALIDATED) ? array($validated, $billed) : array($draft, $validated, $billed);
		$interventionStatus = isset($intervention['status']) ? (int) $intervention['status'] : -1;
		if (!in_array($interventionStatus, $allowedStatuses, true)) {
			return false;
		}

		return self::isMaintenanceInterventionMatchingPeriod($intervention, $contractId, $periodStart, $periodEnd);
	}

	/**
	 * Check common maintenance nature, link, date and overlap rules.
	 *
	 * @param	array<string,mixed>	$intervention	Intervention row
	 * @param	int					$contractId		Contract id
	 * @param	int					$periodStart	Expected period start
	 * @param	int					$periodEnd		Expected period end
	 * @return	bool							True if all common rules match
	 */
	private function interventionMatchesMaintenancePeriod($intervention, $contractId, $periodStart, $periodEnd)
	{
		return self::isMaintenanceInterventionMatchingPeriod($intervention, $contractId, $periodStart, $periodEnd);
	}

	/**
	 * Evaluate common nature, linkage, dates and overlap rules without database access.
	 *
	 * @param	array<string,mixed>	$intervention	Intervention row
	 * @param	int					$contractId		Contract id
	 * @param	int					$periodStart	Period start
	 * @param	int					$periodEnd		Period end
	 * @return	bool							True when matching
	 */
	public static function isMaintenanceInterventionMatchingPeriod(array $intervention, $contractId, $periodStart, $periodEnd)
	{
		if (empty($intervention['nature_active']) || empty($intervention['nature_is_maintenance'])) {
			return false;
		}
		if (empty($intervention['date_start']) || empty($intervention['date_end'])) {
			return false;
		}
		if ((int) $intervention['date_start'] > (int) $intervention['date_end'] || $periodStart <= 0 || $periodEnd <= 0 || $periodStart > $periodEnd) {
			return false;
		}

		$contractIds = isset($intervention['contract_ids']) && is_array($intervention['contract_ids']) ? $intervention['contract_ids'] : array();
		if (empty($contractIds) || !in_array($contractId, array_map('intval', $contractIds), true)) {
			return false;
		}

		return ((int) $intervention['date_start'] <= $periodEnd && (int) $intervention['date_end'] >= $periodStart);
	}

	/**
	 * Calculate one item status.
	 *
	 * @param	int						$maintenanceServiceCount	Number of active maintenance prestations
	 * @param	int						$periodStart			Period start timestamp
	 * @param	int						$periodEnd				Period end timestamp
	 * @param	array<string,mixed>|null	$coveringIntervention	Covering intervention
	 * @param	array<string,mixed>|null	$scheduledIntervention	Scheduled intervention
	 * @param	int						$referenceDate			Reference timestamp
	 * @return	string											Scheduler status
	 */
	private function calculateStatus($maintenanceServiceCount, $periodStart, $periodEnd, $coveringIntervention, $scheduledIntervention, $referenceDate)
	{
		$leadDays = max(0, getDolGlobalInt('POWERPLANTPV_MAINTENANCE_PLANNING_LEAD_DAYS', 30));

		return self::resolveMaintenanceStatus($maintenanceServiceCount, $periodStart, $periodEnd, $coveringIntervention, $scheduledIntervention, $referenceDate, $leadDays);
	}

	/**
	 * Resolve one maintenance status without database access.
	 *
	 * @param	int						$maintenanceServiceCount	Maintenance prestation count
	 * @param	int						$periodStart			Period start
	 * @param	int						$periodEnd				Period end
	 * @param	array<string,mixed>|null	$coveringIntervention	Covering intervention
	 * @param	array<string,mixed>|null	$scheduledIntervention	Scheduled intervention
	 * @param	int						$referenceDate			Reference date
	 * @param	int						$leadDays				Planning lead time
	 * @return	string									Maintenance status
	 */
	public static function resolveMaintenanceStatus($maintenanceServiceCount, $periodStart, $periodEnd, $coveringIntervention, $scheduledIntervention, $referenceDate, $leadDays)
	{
		if ($maintenanceServiceCount <= 0) {
			return self::STATUS_NOT_REQUIRED;
		}
		if (is_array($coveringIntervention)) {
			return self::STATUS_COVERED;
		}
		if ($periodStart <= 0 || $periodEnd <= 0 || $periodStart > $periodEnd) {
			return self::STATUS_INCOMPLETE;
		}
		if (is_array($scheduledIntervention)) {
			return self::STATUS_SCHEDULED;
		}

		$todayStart = self::timestampDayBoundary($referenceDate, false);
		$todayEnd = self::timestampDayBoundary($referenceDate, true);
		if ($periodEnd < $todayStart) {
			return self::STATUS_OVERDUE;
		}
		if ($periodStart > $todayEnd) {
			$leadDays = max(0, (int) $leadDays);
			if ($leadDays <= 0) {
				return self::STATUS_COVERED;
			}
			$plannedFrom = self::timestampDayBoundary(dol_time_plus_duree($periodStart, -$leadDays, 'd'), false);

			return ($todayStart >= $plannedFrom) ? self::STATUS_PLANNED : self::STATUS_COVERED;
		}

		return self::STATUS_DUE;
	}

	/**
	 * Build schedule summary.
	 *
	 * @param	array<int,array<string,mixed>>	$items		Schedule items
	 * @param	array<int,array<string,mixed>>	$contracts	Linked contracts
	 * @return	array<string,mixed>							Summary
	 */
	private function buildSummary($items, $contracts)
	{
		$counts = array(
			self::STATUS_NOT_REQUIRED => 0,
			self::STATUS_PLANNED => 0,
			self::STATUS_SCHEDULED => 0,
			self::STATUS_DUE => 0,
			self::STATUS_OVERDUE => 0,
			self::STATUS_COVERED => 0,
			self::STATUS_INCOMPLETE => 0,
		);
		$priority = array(
			self::STATUS_OVERDUE,
			self::STATUS_DUE,
			self::STATUS_SCHEDULED,
			self::STATUS_PLANNED,
			self::STATUS_INCOMPLETE,
			self::STATUS_COVERED,
			self::STATUS_NOT_REQUIRED,
		);
		$primaryItem = null;
		$primaryStatus = self::STATUS_NOT_REQUIRED;

		foreach ($items as $item) {
			$status = isset($item['status']) ? (string) $item['status'] : self::STATUS_NOT_REQUIRED;
			if (!isset($counts[$status])) {
				$counts[$status] = 0;
			}
			$counts[$status]++;
		}

		foreach ($priority as $status) {
			foreach ($items as $item) {
				if ((string) $item['status'] === $status) {
					$primaryStatus = $status;
					$primaryItem = $item;
					break 2;
				}
			}
		}

		return array(
			'status' => empty($contracts) ? self::STATUS_NOT_REQUIRED : $primaryStatus,
			'counts' => $counts,
			'primary_item' => $primaryItem,
		);
	}

	/**
	 * Count maintenance prestations on active services.
	 *
	 * @param	array<int,array<string,mixed>>	$services	Service lines
	 * @return	int											Number of prestations
	 */
	private function countMaintenancePrestations($services)
	{
		$count = 0;
		foreach ($services as $service) {
			if (!empty($service['maintenance_services']) && is_array($service['maintenance_services'])) {
				$count += count($service['maintenance_services']);
			}
		}

		return $count;
	}

	/**
	 * Fetch active maintenance service labels.
	 *
	 * @param	int[]	$ids	Maintenance service ids
	 * @return	array<int,array<string,mixed>>	Maintenance services indexed by id
	 */
	private function fetchMaintenanceServiceLabels($ids)
	{
		$serviceTable = $this->db->prefix().'c_powerplantpv_maintenance_service';
		if (empty($ids) || !$this->tableExists($serviceTable)) {
			return array();
		}

		$cleanIds = array();
		foreach ($ids as $id) {
			if ((int) $id > 0) {
				$cleanIds[(int) $id] = (int) $id;
			}
		}
		if (empty($cleanIds)) {
			return array();
		}

		$sql = "SELECT rowid, code, label, label_en, active, position";
		$sql .= " FROM ".$serviceTable;
		$sql .= " WHERE rowid IN (".implode(',', $cleanIds).")";
		$sql .= " AND active = 1";
		$sql .= " AND entity IN (".$this->db->sanitize(getEntity('c_powerplantpv_maintenance_service')).")";
		$sql .= " ORDER BY position ASC, label ASC, rowid ASC";

		$services = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' maintenance service lookup failed: '.$this->db->lasterror(), LOG_WARNING);
			return $services;
		}

		while (is_object($obj = $this->db->fetch_object($resql))) {
			$services[(int) $obj->rowid] = array(
				'id' => (int) $obj->rowid,
				'code' => (string) $obj->code,
				'label' => $this->localizedDictionaryLabel($obj, 'label'),
				'active' => (int) $obj->active,
				'position' => (int) $obj->position,
			);
		}
		$this->db->free($resql);

		return $services;
	}

	/**
	 * Fetch native contract links attached to interventions.
	 *
	 * @param	int[]	$interventionIds	Intervention ids
	 * @return	array<int,array<int,int>>	Contract ids by intervention id
	 */
	private function fetchInterventionContractLinks($interventionIds)
	{
		if (empty($interventionIds)) {
			return array();
		}

		$cleanIds = array();
		foreach ($interventionIds as $id) {
			if ((int) $id > 0) {
				$cleanIds[(int) $id] = (int) $id;
			}
		}
		if (empty($cleanIds)) {
			return array();
		}

		$sql = "SELECT ee.sourcetype, ee.fk_source, ee.targettype, ee.fk_target";
		$sql .= " FROM ".$this->db->prefix()."element_element AS ee";
		$sql .= " INNER JOIN ".$this->db->prefix()."contrat AS c ON (";
		$sql .= "(ee.sourcetype = 'fichinter' AND ee.targettype = 'contrat' AND c.rowid = ee.fk_target)";
		$sql .= " OR ";
		$sql .= "(ee.targettype = 'fichinter' AND ee.sourcetype = 'contrat' AND c.rowid = ee.fk_source)";
		$sql .= ")";
		$sql .= " WHERE c.entity IN (".$this->db->sanitize(getEntity('contrat')).")";
		$sql .= " AND (";
		$sql .= "(ee.sourcetype = 'fichinter' AND ee.targettype = 'contrat' AND ee.fk_source IN (".implode(',', $cleanIds)."))";
		$sql .= " OR ";
		$sql .= "(ee.targettype = 'fichinter' AND ee.sourcetype = 'contrat' AND ee.fk_target IN (".implode(',', $cleanIds)."))";
		$sql .= ")";

		$links = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' intervention contract link lookup failed: '.$this->db->lasterror(), LOG_WARNING);
			return $links;
		}

		while (is_object($obj = $this->db->fetch_object($resql))) {
			if ((string) $obj->sourcetype === 'fichinter') {
				$interventionId = (int) $obj->fk_source;
				$contractId = (int) $obj->fk_target;
			} else {
				$interventionId = (int) $obj->fk_target;
				$contractId = (int) $obj->fk_source;
			}
			if ($interventionId > 0 && $contractId > 0) {
				if (!isset($links[$interventionId])) {
					$links[$interventionId] = array();
				}
				$links[$interventionId][$contractId] = $contractId;
			}
		}
		$this->db->free($resql);

		return $links;
	}

	/**
	 * Check if a contract id is visible in the current entity scope.
	 *
	 * @param	int	$contractId	Contract id
	 * @return	bool			True if visible
	 */
	private function isContractVisible($contractId)
	{
		static $cache = array();

		if ($contractId <= 0) {
			return false;
		}
		if (isset($cache[$contractId])) {
			return (bool) $cache[$contractId];
		}

		$sql = "SELECT rowid";
		$sql .= " FROM ".$this->db->prefix()."contrat";
		$sql .= " WHERE rowid = ".((int) $contractId);
		$sql .= " AND entity IN (".$this->db->sanitize(getEntity('contrat')).")";
		$sql .= " LIMIT 1";
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' contract visibility lookup failed: '.$this->db->lasterror(), LOG_WARNING);
			$cache[$contractId] = false;
			return false;
		}

		$cache[$contractId] = ($this->db->num_rows($resql) > 0);
		$this->db->free($resql);

		return (bool) $cache[$contractId];
	}

	/**
	 * Parse a Dolibarr multiselect extrafield value into ids.
	 *
	 * @param	string|array<int|string,mixed>|null	$value	Raw extrafield value
	 * @return	int[]										Id list
	 */
	private function parseMaintenanceServiceIds($value)
	{
		if (is_array($value)) {
			$parts = $value;
		} else {
			$normalized = str_replace(array(';', '|'), ',', (string) $value);
			$parts = explode(',', $normalized);
		}

		$ids = array();
		foreach ($parts as $part) {
			$id = (int) trim((string) $part);
			if ($id > 0) {
				$ids[$id] = $id;
			}
		}

		return array_values($ids);
	}

	/**
	 * Normalize recurrence to a supported code.
	 *
	 * @param	string	$recurrence	Raw recurrence
	 * @return	string				Supported recurrence or empty string
	 */
	private function normalizeRecurrence($recurrence)
	{
		$recurrence = trim((string) $recurrence);
		$labels = self::getRecurrenceLabelKeys();

		return isset($labels[$recurrence]) ? $recurrence : '';
	}

	/**
	 * Return existing columns from a fixed candidate list.
	 *
	 * @param	string	$table		Full table name
	 * @param	string[]	$columns	Candidate column names
	 * @return	string[]				Existing column names
	 */
	private function getExistingColumns($table, $columns)
	{
		$existing = array();
		foreach ($columns as $column) {
			if ($this->columnExists($table, (string) $column)) {
				$existing[] = (string) $column;
			}
		}

		return $existing;
	}

	/**
	 * Return the finalization timestamp used as fallback when an intervention has no explicit period.
	 *
	 * @param	stdClass	$obj					SQL intervention row
	 * @param	bool		$isClosed				True when intervention is closed
	 * @param	bool		$isSignedCovering		True when intervention has a covering signature status
	 * @param	string[]		$closingDateColumns		Detected closing date columns
	 * @param	string[]		$signatureDateColumns	Detected signature date columns
	 * @param	bool		$endOfDay				Use end of day
	 * @return	int									Timestamp or 0
	 */
	private function getInterventionFinalizationTimestamp(
		$obj,
		$isClosed,
		$isSignedCovering,
		$closingDateColumns,
		$signatureDateColumns,
		$endOfDay
	)
	{
		$values = array();

		if ($isSignedCovering) {
			foreach ($signatureDateColumns as $idx => $column) {
				$property = 'powerplantpv_signature_date_'.((int) $idx);
				$values[] = isset($obj->{$property}) ? $obj->{$property} : '';
			}
		}

		if ($isClosed) {
			foreach ($closingDateColumns as $idx => $column) {
				$property = 'powerplantpv_closing_date_'.((int) $idx);
				$values[] = isset($obj->{$property}) ? $obj->{$property} : '';
			}
			$values[] = isset($obj->datet) ? $obj->datet : '';
		}

		if ($isSignedCovering || $isClosed) {
			$values[] = isset($obj->date_valid) ? $obj->date_valid : '';
		}

		return $this->firstDateTimestamp($values, $endOfDay);
	}

	/**
	 * Return first valid timestamp from date values.
	 *
	 * @param	array<int,mixed>	$values		Date values
	 * @param	bool			$endOfDay	Use end of day
	 * @return	int							Timestamp or 0
	 */
	private function firstDateTimestamp($values, $endOfDay)
	{
		foreach ($values as $value) {
			$timestamp = $this->sqlDateToTimestamp((string) $value, $endOfDay);
			if ($timestamp > 0) {
				return $timestamp;
			}
		}

		return 0;
	}

	/**
	 * Convert a SQL date/datetime string to a day-boundary timestamp.
	 *
	 * @param	string	$value		SQL date or datetime
	 * @param	bool	$endOfDay	Use end of day
	 * @return	int					Timestamp or 0
	 */
	private function sqlDateToTimestamp($value, $endOfDay)
	{
		$value = trim((string) $value);
		if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
			return 0;
		}

		$timestamp = 0;
		if (is_numeric($value)) {
			$timestamp = (int) $value;
		} else {
			$timestamp = (int) $this->db->jdate($value);
		}
		if ($timestamp <= 0) {
			return 0;
		}

		return $this->dayBoundary($timestamp, $endOfDay);
	}

	/**
	 * Return a day boundary timestamp.
	 *
	 * @param	int		$timestamp	Timestamp
	 * @param	bool	$endOfDay	Use end of day
	 * @return	int					Boundary timestamp
	 */
	private function dayBoundary($timestamp, $endOfDay)
	{
		return self::timestampDayBoundary($timestamp, $endOfDay);
	}

	/**
	 * Return a day boundary timestamp without database access.
	 *
	 * @param	int		$timestamp	Timestamp
	 * @param	bool	$endOfDay	Use end of day
	 * @return	int					Boundary timestamp
	 */
	public static function timestampDayBoundary($timestamp, $endOfDay)
	{
		$hour = $endOfDay ? 23 : 0;
		$minute = $endOfDay ? 59 : 0;
		$second = $endOfDay ? 59 : 0;

		return dol_mktime($hour, $minute, $second, (int) date('m', $timestamp), (int) date('d', $timestamp), (int) date('Y', $timestamp));
	}

	/**
	 * Return a SQL string list.
	 *
	 * @param	string[]	$values	Values
	 * @return	string			Escaped SQL values
	 */
	private function getSqlStringList($values)
	{
		$sqlvalues = array();
		foreach ($values as $value) {
			$sqlvalues[] = "'".$this->db->escape((string) $value)."'";
		}

		return implode(',', $sqlvalues);
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
	 * Return a localized dictionary label from an SQL object.
	 *
	 * @param	stdClass	$obj			SQL row object
	 * @param	string		$baseProperty	Base property name
	 * @return	string						Localized label
	 */
	private function localizedDictionaryLabel($obj, $baseProperty)
	{
		global $langs;

		$label = isset($obj->{$baseProperty}) ? (string) $obj->{$baseProperty} : '';
		$englishProperty = $baseProperty.'_en';
		if (is_object($langs) && $langs->defaultlang == 'en_US' && !empty($obj->{$englishProperty})) {
			$label = (string) $obj->{$englishProperty};
		}

		return $label;
	}

	/**
	 * Return Dolibarr v20 contract validated status.
	 *
	 * @return	int	Status value
	 */
	private function getContractValidatedStatus()
	{
		return (class_exists('Contrat') && defined('Contrat::STATUS_VALIDATED')) ? (int) constant('Contrat::STATUS_VALIDATED') : 1;
	}

	/**
	 * Return Dolibarr v20 contract line open status.
	 *
	 * @return	int	Status value
	 */
	private function getContractLineOpenStatus()
	{
		return (class_exists('ContratLigne') && defined('ContratLigne::STATUS_OPEN')) ? (int) constant('ContratLigne::STATUS_OPEN') : 4;
	}

	/**
	 * Return Dolibarr v20 intervention closed status.
	 *
	 * @return	int	Status value
	 */
	private function getFichinterClosedStatus()
	{
		return (class_exists('Fichinter') && defined('Fichinter::STATUS_CLOSED')) ? (int) constant('Fichinter::STATUS_CLOSED') : 3;
	}

	/**
	 * Return intervention signed statuses that cover a maintenance period.
	 *
	 * @return	int[]	Signed statuses
	 */
	private function getFichinterCoveringSignedStatuses()
	{
		$receiver = (class_exists('Fichinter') && defined('Fichinter::STATUS_SIGNED_RECEIVER')) ? (int) constant('Fichinter::STATUS_SIGNED_RECEIVER') : 2;
		$receiverOnline = (class_exists('Fichinter') && defined('Fichinter::STATUS_SIGNED_RECEIVER_ONLINE'))
			? (int) constant('Fichinter::STATUS_SIGNED_RECEIVER_ONLINE')
			: 3;
		$all = (class_exists('Fichinter') && defined('Fichinter::STATUS_SIGNED_ALL')) ? (int) constant('Fichinter::STATUS_SIGNED_ALL') : 9;

		return array_values(array_unique(array($receiver, $receiverOnline, $all)));
	}
}
