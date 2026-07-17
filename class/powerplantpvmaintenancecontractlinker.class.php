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
 * \file		class/powerplantpvmaintenancecontractlinker.class.php
 * \ingroup		powerplantpv
 * \brief		Automatic native maintenance contract linker for interventions.
 */

dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/contrat/class/contrat.class.php');
dol_include_once('/fichinter/class/fichinter.class.php');

/**
 * Link maintenance interventions to the active maintenance contract carried by their power plants.
 */
class PowerPlantPVMaintenanceContractLinker
{
	/**
	 * @var DoliDB Database handler
	 */
	private $db;

	/**
	 * @var string Last error
	 */
	public $error = '';

	/**
	 * @var string[] Last errors
	 */
	public $errors = array();

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
	 * Return the best maintenance contract for requested power plants and intervention nature.
	 *
	 * @param	int[]	$powerPlantIds	Power plant row ids
	 * @param	int		$natureId		Intervention nature row id
	 * @return	int						Contract id, 0 if no contract should be linked
	 */
	public function findBestMaintenanceContractIdForPowerPlants($powerPlantIds, $natureId)
	{
		$powerPlantIds = powerplantpvSanitizeIdArray($powerPlantIds);
		if (empty($powerPlantIds) || (int) $natureId <= 0) {
			return 0;
		}
		if (!$this->isMaintenanceNatureId((int) $natureId)) {
			dol_syslog(__METHOD__.' skipped: intervention nature '.((int) $natureId).' is not an active maintenance nature', LOG_DEBUG);
			return 0;
		}

		$candidates = $this->fetchMaintenanceContractCandidates($powerPlantIds);
		if (empty($candidates)) {
			dol_syslog(__METHOD__.' skipped: no active maintenance contract candidate for power plants '.implode(',', $powerPlantIds), LOG_DEBUG);
			return 0;
		}

		usort($candidates, array($this, 'sortContractCandidates'));

		return (int) $candidates[0]['id'];
	}

	/**
	 * Link an existing intervention to its best maintenance contract when no contract is already linked.
	 *
	 * @param	CommonObject	$intervention	Intervention object
	 * @param	User			$user			User applying the link
	 * @param	string			$reason			Diagnostic reason
	 * @return	int								1 if a link was added, 0 if skipped, <0 on link error
	 */
	public function linkInterventionToMaintenanceContract($intervention, $user, $reason = 'system')
	{
		$interventionId = $this->getInterventionId($intervention);
		if ($interventionId <= 0 || !$this->isInterventionObject($intervention)) {
			return 0;
		}

		$natureId = $this->fetchInterventionNatureId($intervention);
		if ($natureId <= 0 || !$this->isMaintenanceNatureId($natureId)) {
			dol_syslog(__METHOD__.' skipped intervention '.$interventionId.': no active maintenance nature', LOG_DEBUG);
			return 0;
		}

		$existingContractIds = $this->fetchExistingInterventionContractIds($interventionId);
		if (!empty($existingContractIds)) {
			dol_syslog(__METHOD__.' skipped intervention '.$interventionId.': contract already linked', LOG_DEBUG);
			return 0;
		}

		$powerPlantIds = $this->fetchInterventionPowerPlantIds($intervention);
		if (empty($powerPlantIds)) {
			dol_syslog(__METHOD__.' skipped intervention '.$interventionId.': no linked power plant', LOG_DEBUG);
			return 0;
		}

		$contractId = $this->findBestMaintenanceContractIdForPowerPlants($powerPlantIds, $natureId);
		if ($contractId <= 0) {
			return 0;
		}

		$interventionToLink = $this->ensureLoadedIntervention($intervention, $interventionId);
		if (!is_object($interventionToLink) || $this->getInterventionId($interventionToLink) <= 0) {
			$this->setError('PowerPlantPVMaintenanceContractAutoLinkInterventionNotFound');
			return -1;
		}

		$result = $interventionToLink->add_object_linked('contrat', $contractId, $user, 0);
		if ($result <= 0) {
			$this->setError(!empty($interventionToLink->error) ? $interventionToLink->error : 'ErrorFailedToAddLink');
			if (!empty($interventionToLink->errors) && is_array($interventionToLink->errors)) {
				$this->errors = array_merge($this->errors, $interventionToLink->errors);
			}
			dol_syslog(__METHOD__.' failed to link intervention '.$interventionId.' to contract '.$contractId.': '.$this->error, LOG_WARNING);
			return -1;
		}

		dol_syslog(__METHOD__.' linked intervention '.$interventionId.' to contract '.$contractId.' reason='.$reason, LOG_INFO);

		return 1;
	}

	/**
	 * Check if a requested payload already carries a contract.
	 *
	 * @return	bool	True if a contract is already requested
	 */
	public function requestAlreadyCarriesContract()
	{
		$contractId = GETPOSTINT('fk_contrat') > 0 ? GETPOSTINT('fk_contrat') : GETPOSTINT('contratid');
		$origin = powerplantpvNormalizeElementType(GETPOST('origin', 'alphanohtml'));
		$originId = GETPOSTINT('originid') > 0 ? GETPOSTINT('originid') : GETPOSTINT('origin_id');
		if (($origin == 'contrat' && $originId > 0) || $contractId > 0) {
			return true;
		}

		$otherLinkedObjects = GETPOST('other_linked_objects', 'array:int');
		if (!is_array($otherLinkedObjects)) {
			return false;
		}
		if (!empty($otherLinkedObjects['contrat'])) {
			return !empty(powerplantpvSanitizeIdArray($otherLinkedObjects['contrat']));
		}
		if (!empty($otherLinkedObjects['contract'])) {
			return !empty(powerplantpvSanitizeIdArray($otherLinkedObjects['contract']));
		}

		return false;
	}

	/**
	 * Fetch active maintenance contract candidates from linked power plants.
	 *
	 * @param	int[]	$powerPlantIds	Power plant row ids
	 * @return	array<int,array{id:int,period_start:string,period_start_ts:int}>
	 */
	private function fetchMaintenanceContractCandidates($powerPlantIds)
	{
		$powerPlantIds = powerplantpvSanitizeIdArray($powerPlantIds);
		if (empty($powerPlantIds)) {
			return array();
		}

		$productExtraTable = $this->db->prefix().'product_extrafields';
		if (!$this->tableExists($productExtraTable) || !$this->columnExists($productExtraTable, 'powerplantpv_maintenance_services')) {
			dol_syslog(__METHOD__.' skipped: product maintenance service extrafield is unavailable', LOG_DEBUG);
			return array();
		}

		$contractExtraTable = $this->db->prefix().'contrat_extrafields';
		$hasContractPeriod = $this->tableExists($contractExtraTable)
			&& $this->columnExists($contractExtraTable, 'powerplantpv_next_maintenance_period_start');
		$powerPlantIdSql = implode(',', array_map('intval', $powerPlantIds));
		$powerPlantTypeSql = $this->getSqlStringList(powerplantpvGetPowerPlantLinkTypes());
		$contractStatusValidated = $this->getContractValidatedStatus();
		$serviceStatusOpen = $this->getContractLineOpenStatus();

		$sql = "SELECT DISTINCT c.rowid, pe.powerplantpv_maintenance_services as maintenance_services";
		if ($hasContractPeriod) {
			$sql .= ", ce.powerplantpv_next_maintenance_period_start as period_start";
		} else {
			$sql .= ", NULL as period_start";
		}
		$sql .= " FROM ".$this->db->prefix()."element_element AS ee";
		$sql .= " INNER JOIN ".$this->db->prefix()."contrat AS c ON (";
		$sql .= "(ee.sourcetype = 'contrat' AND ee.fk_source = c.rowid AND ee.targettype IN (".$powerPlantTypeSql.") AND ee.fk_target IN (".$powerPlantIdSql."))";
		$sql .= " OR ";
		$sql .= "(ee.targettype = 'contrat' AND ee.fk_target = c.rowid AND ee.sourcetype IN (".$powerPlantTypeSql.") AND ee.fk_source IN (".$powerPlantIdSql."))";
		$sql .= ")";
		$sql .= " INNER JOIN ".$this->db->prefix()."contratdet AS d ON d.fk_contrat = c.rowid";
		$sql .= " INNER JOIN ".$this->db->prefix()."product AS p ON p.rowid = d.fk_product";
		$sql .= " INNER JOIN ".$productExtraTable." AS pe ON pe.fk_object = p.rowid";
		if ($hasContractPeriod) {
			$sql .= " LEFT JOIN ".$contractExtraTable." AS ce ON ce.fk_object = c.rowid";
		}
		$sql .= " WHERE c.entity IN (".$this->db->sanitize(getEntity('contrat')).")";
		$sql .= " AND c.statut = ".((int) $contractStatusValidated);
		$sql .= " AND d.statut = ".((int) $serviceStatusOpen);
		$sql .= " AND d.product_type = 1";
		$sql .= " AND d.fk_product > 0";
		$sql .= " AND p.entity IN (".$this->db->sanitize(getEntity('product')).")";
		$sql .= " AND pe.powerplantpv_maintenance_services IS NOT NULL";
		$sql .= " AND pe.powerplantpv_maintenance_services <> ''";

		$candidates = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' contract lookup failed: '.$this->db->lasterror(), LOG_WARNING);
			return $candidates;
		}

		while (is_object($obj = $this->db->fetch_object($resql))) {
			$maintenanceServiceIds = $this->parseMaintenanceServiceIds(isset($obj->maintenance_services) ? $obj->maintenance_services : '1');
			if (empty($maintenanceServiceIds)) {
				continue;
			}
			$contractId = (int) $obj->rowid;
			$periodStart = isset($obj->period_start) ? (string) $obj->period_start : '';
			$candidates[$contractId] = array(
				'id' => $contractId,
				'period_start' => $periodStart,
				'period_start_ts' => $this->sqlDateToTimestamp($periodStart),
			);
		}
		$this->db->free($resql);

		return array_values($candidates);
	}

	/**
	 * Return contract ids already attached to the intervention.
	 *
	 * @param	int	$interventionId	Intervention id
	 * @return	int[]				Contract ids
	 */
	private function fetchExistingInterventionContractIds($interventionId)
	{
		$contractIds = array();
		$fichinterTable = $this->db->prefix().'fichinter';
		if ($this->tableExists($fichinterTable) && $this->columnExists($fichinterTable, 'fk_contrat')) {
			$sql = "SELECT fk_contrat";
			$sql .= " FROM ".$fichinterTable;
			$sql .= " WHERE rowid = ".((int) $interventionId);
			$sql .= " AND entity IN (".$this->db->sanitize(getEntity('fichinter')).")";
			$resql = $this->db->query($sql);
			if ($resql) {
				if (is_object($obj = $this->db->fetch_object($resql)) && !empty($obj->fk_contrat)) {
					$contractIds[] = (int) $obj->fk_contrat;
				}
				$this->db->free($resql);
			} else {
				dol_syslog(__METHOD__.' direct intervention contract lookup failed: '.$this->db->lasterror(), LOG_WARNING);
			}
		}

		$elementTypes = $this->getSqlStringList(array('fichinter', 'intervention', 'ficheinter'));
		$sql = "SELECT c.rowid";
		$sql .= " FROM ".$this->db->prefix()."element_element AS ee";
		$sql .= " INNER JOIN ".$this->db->prefix()."contrat AS c ON (";
		$sql .= "(ee.sourcetype IN (".$elementTypes.") AND ee.fk_source = ".((int) $interventionId)." AND ee.targettype = 'contrat' AND ee.fk_target = c.rowid)";
		$sql .= " OR ";
		$sql .= "(ee.targettype IN (".$elementTypes.") AND ee.fk_target = ".((int) $interventionId)." AND ee.sourcetype = 'contrat' AND ee.fk_source = c.rowid)";
		$sql .= ")";
		$sql .= " WHERE c.entity IN (".$this->db->sanitize(getEntity('contrat')).")";
		$resql = $this->db->query($sql);
		if ($resql) {
			while (is_object($obj = $this->db->fetch_object($resql))) {
				$contractIds[] = (int) $obj->rowid;
			}
			$this->db->free($resql);
		} else {
			dol_syslog(__METHOD__.' linked intervention contract lookup failed: '.$this->db->lasterror(), LOG_WARNING);
		}

		return array_values(array_unique(array_filter(array_map('intval', $contractIds))));
	}

	/**
	 * Return linked power plant ids for an existing intervention.
	 *
	 * @param	CommonObject	$intervention	Intervention object
	 * @return	int[]							Power plant row ids
	 */
	private function fetchInterventionPowerPlantIds($intervention)
	{
		$ids = array();
		foreach (powerplantpvGetLinkedPowerPlants($intervention) as $powerPlant) {
			$ids[] = powerplantpvGetCommonObjectId($powerPlant);
		}

		return powerplantpvSanitizeIdArray($ids);
	}

	/**
	 * Read the PowerPlantPV intervention nature id from object options or persisted extrafields.
	 *
	 * @param	CommonObject	$intervention	Intervention object
	 * @return	int								Nature row id
	 */
	private function fetchInterventionNatureId($intervention)
	{
		if (is_object($intervention) && !empty($intervention->array_options) && is_array($intervention->array_options)) {
			if (!empty($intervention->array_options['options_powerplantpv_intervention_nature'])) {
				return (int) $intervention->array_options['options_powerplantpv_intervention_nature'];
			}
		}

		$interventionId = $this->getInterventionId($intervention);
		if ($interventionId <= 0) {
			return 0;
		}

		$table = $this->db->prefix().'fichinter_extrafields';
		if (!$this->tableExists($table) || !$this->columnExists($table, 'powerplantpv_intervention_nature')) {
			return 0;
		}

		$sql = "SELECT powerplantpv_intervention_nature";
		$sql .= " FROM ".$table;
		$sql .= " WHERE fk_object = ".((int) $interventionId);
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' intervention nature lookup failed: '.$this->db->lasterror(), LOG_WARNING);
			return 0;
		}

		$natureId = 0;
		if (is_object($obj = $this->db->fetch_object($resql)) && !empty($obj->powerplantpv_intervention_nature)) {
			$natureId = (int) $obj->powerplantpv_intervention_nature;
		}
		$this->db->free($resql);

		return $natureId;
	}

	/**
	 * Check if an intervention nature is active and marked as maintenance.
	 *
	 * @param	int	$natureId	Nature row id
	 * @return	bool			True if maintenance nature
	 */
	private function isMaintenanceNatureId($natureId)
	{
		$table = $this->db->prefix().'c_powerplantpv_intervention_nature';
		if ((int) $natureId <= 0 || !$this->tableExists($table) || !$this->columnExists($table, 'is_maintenance')) {
			return false;
		}

		$sql = "SELECT rowid";
		$sql .= " FROM ".$table;
		$sql .= " WHERE rowid = ".((int) $natureId);
		$sql .= " AND active = 1";
		$sql .= " AND is_maintenance = 1";
		$sql .= " AND entity IN (".$this->db->sanitize(getEntity('c_powerplantpv_intervention_nature')).")";
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' intervention nature validation failed: '.$this->db->lasterror(), LOG_WARNING);
			return false;
		}

		$isMaintenance = ($this->db->num_rows($resql) > 0);
		$this->db->free($resql);

		return $isMaintenance;
	}

	/**
	 * Ensure an intervention object is loaded enough to create native links.
	 *
	 * @param	CommonObject	$intervention	Intervention object
	 * @param	int				$interventionId	Intervention id
	 * @return	CommonObject|null				Loaded object
	 */
	private function ensureLoadedIntervention($intervention, $interventionId)
	{
		if (is_object($intervention) && method_exists($intervention, 'add_object_linked')) {
			return $intervention;
		}
		if (!class_exists('Fichinter')) {
			return null;
		}

		$loaded = new Fichinter($this->db);
		if ($loaded->fetch((int) $interventionId) <= 0) {
			$this->setError(!empty($loaded->error) ? $loaded->error : 'ErrorRecordNotFound');
			return null;
		}

		return $loaded;
	}

	/**
	 * Check if object is an intervention.
	 *
	 * @param	CommonObject	$object	Object
	 * @return	bool					True if object resolves to fichinter
	 */
	private function isInterventionObject($object)
	{
		if (!is_object($object)) {
			return false;
		}

		foreach (powerplantpvGetObjectElementTypes($object) as $elementType) {
			if (powerplantpvNormalizeElementType($elementType) == 'fichinter') {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return intervention id.
	 *
	 * @param	CommonObject	$intervention	Intervention object
	 * @return	int								Intervention id
	 */
	private function getInterventionId($intervention)
	{
		return powerplantpvGetCommonObjectId($intervention);
	}

	/**
	 * Sort candidates by latest period start, then highest contract id.
	 *
	 * @param	array{id:int,period_start:string,period_start_ts:int}	$a	First candidate
	 * @param	array{id:int,period_start:string,period_start_ts:int}	$b	Second candidate
	 * @return	int														Sort order
	 */
	private function sortContractCandidates($a, $b)
	{
		if ((int) $a['period_start_ts'] !== (int) $b['period_start_ts']) {
			return ((int) $a['period_start_ts'] > (int) $b['period_start_ts']) ? -1 : 1;
		}
		if ((int) $a['id'] === (int) $b['id']) {
			return 0;
		}

		return ((int) $a['id'] > (int) $b['id']) ? -1 : 1;
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
			$parts = explode(',', str_replace(array(';', '|'), ',', (string) $value));
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
	 * Convert a SQL date to timestamp.
	 *
	 * @param	string	$value	SQL date
	 * @return	int				Timestamp, 0 for empty values
	 */
	private function sqlDateToTimestamp($value)
	{
		if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
			return 0;
		}

		return (int) $this->db->jdate($value);
	}

	/**
	 * Return a SQL string list.
	 *
	 * @param	string[]	$values	Values
	 * @return	string			Escaped SQL values
	 */
	private function getSqlStringList($values)
	{
		$sqlValues = array();
		foreach ($values as $value) {
			$sqlValues[] = "'".$this->db->escape((string) $value)."'";
		}

		return implode(',', $sqlValues);
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
	 * Register the current error.
	 *
	 * @param	string	$error	Error key or message
	 * @return	void
	 */
	private function setError($error)
	{
		$this->error = (string) $error;
		if ($this->error !== '') {
			$this->errors[] = $this->error;
		}
	}
}
