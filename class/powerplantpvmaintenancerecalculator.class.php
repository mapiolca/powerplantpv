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
 * \file		class/powerplantpvmaintenancerecalculator.class.php
 * \ingroup		powerplantpv
 * \brief		Targeted maintenance recalculation orchestrator.
 */

dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/class/powerplant.class.php');
dol_include_once('/powerplantpv/class/powerplantpvmaintenancescheduler.class.php');

/**
 * Recompute maintenance schedules from Dolibarr core triggers without storing a cache.
 */
class PowerPlantPVMaintenanceRecalculator
{
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
	 * Constructor.
	 *
	 * @param	DoliDB	$db	Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Handle a Dolibarr trigger and recompute only linked power plants.
	 *
	 * @param	string			$action	Trigger action
	 * @param	CommonObject	$object	Triggered object
	 * @param	User			$user	User
	 * @return	int						0. Recalculation is best-effort and never blocks the source mutation.
	 */
	public function handleTrigger($action, $object, User $user)
	{
		if (!isModEnabled('powerplantpv') || !getDolGlobalInt('POWERPLANTPV_MAINTENANCE_ENABLE', 1)) {
			return 0;
		}

		dol_syslog(__METHOD__.' received action='.$action.' object_id='.$this->getObjectId($object).' object_class='.(is_object($object) ? get_class($object) : 'none'), LOG_DEBUG);

		if (in_array($action, $this->getContractActions(), true)) {
			$this->recomputeByContract($this->getObjectId($object), $user, $action);
			return 0;
		}

		if (in_array($action, $this->getContractLineActions(), true)) {
			$this->recomputeByContractLine($object, $user, $action);
			return 0;
		}

		if (in_array($action, $this->getInterventionActions(), true)) {
			$this->recomputeByIntervention($this->getObjectId($object), $user, $action);
			return 0;
		}

		if (in_array($action, $this->getInterventionLineActions(), true)) {
			$this->recomputeByInterventionLine($object, $user, $action);
			return 0;
		}

		if (in_array($action, $this->getObjectLinkActions(), true)) {
			$this->recomputeByObjectLink($action, $object, $user);
		}

		return 0;
	}

	/**
	 * Return supported contract trigger codes.
	 *
	 * @return	array<int,string> Trigger codes
	 */
	private function getContractActions()
	{
		return array('CONTRACT_CREATE', 'CONTRACT_MODIFY', 'CONTRACT_VALIDATE', 'CONTRACT_REOPEN', 'CONTRACT_DELETE');
	}

	/**
	 * Return supported contract line trigger codes.
	 *
	 * @return	array<int,string> Trigger codes
	 */
	private function getContractLineActions()
	{
		return array('LINECONTRACT_INSERT', 'LINECONTRACT_MODIFY', 'LINECONTRACT_DELETE', 'LINECONTRACT_ACTIVATE', 'LINECONTRACT_CLOSE');
	}

	/**
	 * Return supported intervention trigger codes.
	 *
	 * @return	array<int,string> Trigger codes
	 */
	private function getInterventionActions()
	{
		return array('FICHINTER_CREATE', 'FICHINTER_MODIFY', 'FICHINTER_VALIDATE', 'FICHINTER_UNVALIDATE', 'FICHINTER_CLOSE', 'FICHINTER_DELETE');
	}

	/**
	 * Return supported intervention line trigger codes.
	 *
	 * @return	array<int,string> Trigger codes
	 */
	private function getInterventionLineActions()
	{
		return array('LINEFICHINTER_CREATE', 'LINEFICHINTER_MODIFY', 'LINEFICHINTER_DELETE');
	}

	/**
	 * Return supported native object link trigger codes.
	 *
	 * @return	array<int,string> Trigger codes
	 */
	private function getObjectLinkActions()
	{
		return array('OBJECT_LINK_INSERT', 'OBJECT_LINK_MODIFY', 'OBJECT_LINK_DELETE');
	}

	/**
	 * Recompute power plants linked to a contract.
	 *
	 * @param	int		$contractId	Contract id
	 * @param	User	$user		User
	 * @param	string	$reason		Trigger action
	 * @return	void
	 */
	private function recomputeByContract($contractId, User $user, $reason)
	{
		$contractId = (int) $contractId;
		if ($contractId <= 0) {
			$this->logIgnored($reason, 'missing_contract_id');
			return;
		}

		$powerPlantIds = $this->fetchPowerPlantIdsLinkedToObject('contrat', $contractId);
		$this->recomputeByPowerPlantIds($powerPlantIds, $user, $reason, array('contract_id' => $contractId));
	}

	/**
	 * Recompute power plants linked to a contract line parent.
	 *
	 * @param	CommonObject	$line	Contract line object
	 * @param	User			$user	User
	 * @param	string			$reason	Trigger action
	 * @return	void
	 */
	private function recomputeByContractLine($line, User $user, $reason)
	{
		$contractId = $this->getObjectIntProperty($line, array('fk_contrat', 'fk_contract', 'fk_parent', 'fk_object'));
		if ($contractId <= 0) {
			$contractId = $this->fetchContractIdFromLine($this->getObjectId($line));
		}
		$this->recomputeByContract($contractId, $user, $reason);
	}

	/**
	 * Recompute power plants linked to an intervention and its linked contracts.
	 *
	 * @param	int		$interventionId	Intervention id
	 * @param	User	$user			User
	 * @param	string	$reason			Trigger action
	 * @return	void
	 */
	private function recomputeByIntervention($interventionId, User $user, $reason)
	{
		$interventionId = (int) $interventionId;
		if ($interventionId <= 0) {
			$this->logIgnored($reason, 'missing_intervention_id');
			return;
		}

		$powerPlantIds = $this->fetchPowerPlantIdsLinkedToObject('fichinter', $interventionId);
		$contractIds = $this->fetchContractIdsLinkedToIntervention($interventionId);
		foreach ($contractIds as $contractId) {
			$powerPlantIds += $this->fetchPowerPlantIdsLinkedToObject('contrat', (int) $contractId);
		}

		$this->recomputeByPowerPlantIds($powerPlantIds, $user, $reason, array('intervention_id' => $interventionId, 'contract_ids' => array_values($contractIds)));
	}

	/**
	 * Recompute power plants linked to an intervention line parent.
	 *
	 * @param	CommonObject	$line	Intervention line object
	 * @param	User			$user	User
	 * @param	string			$reason	Trigger action
	 * @return	void
	 */
	private function recomputeByInterventionLine($line, User $user, $reason)
	{
		$interventionId = $this->getObjectIntProperty($line, array('fk_fichinter', 'fk_intervention', 'fk_parent', 'fk_object'));
		if ($interventionId <= 0) {
			$interventionId = $this->fetchInterventionIdFromLine($this->getObjectId($line));
		}
		$this->recomputeByIntervention($interventionId, $user, $reason);
	}

	/**
	 * Recompute power plants impacted by a native object link mutation.
	 *
	 * @param	string			$action	Trigger action
	 * @param	CommonObject	$object	Triggered object
	 * @param	User			$user	User
	 * @return	void
	 */
	private function recomputeByObjectLink($action, $object, User $user)
	{
		$links = $this->extractObjectLinkPairs($action, $object);
		if (empty($links)) {
			$this->logIgnored($action, 'missing_link_context');
			return;
		}

		$powerPlantIds = array();
		$contractIds = array();
		$interventionIds = array();
		foreach ($links as $link) {
			foreach (array('source', 'target') as $side) {
				$id = (int) $link[$side.'_id'];
				$type = $this->normalizeElementType((string) $link[$side.'_type']);
				if ($id <= 0 || $type === '') {
					continue;
				}
				if (powerplantpvIsPowerPlantLinkType($type)) {
					$powerPlantIds[$id] = $id;
				} elseif ($type === 'contrat') {
					$contractIds[$id] = $id;
				} elseif ($type === 'fichinter') {
					$interventionIds[$id] = $id;
				}
			}
		}

		foreach ($interventionIds as $interventionId) {
			$powerPlantIds += $this->fetchPowerPlantIdsLinkedToObject('fichinter', (int) $interventionId);
			$contractIds += $this->fetchContractIdsLinkedToIntervention((int) $interventionId);
		}
		foreach ($contractIds as $contractId) {
			$powerPlantIds += $this->fetchPowerPlantIdsLinkedToObject('contrat', (int) $contractId);
		}

		$this->recomputeByPowerPlantIds($powerPlantIds, $user, $action, array(
			'link_count' => count($links),
			'contract_ids' => array_values($contractIds),
			'intervention_ids' => array_values($interventionIds),
		));
	}

	/**
	 * Extract native object link source/target pairs from trigger context.
	 *
	 * @param	string			$action	Trigger action
	 * @param	CommonObject	$object	Triggered object
	 * @return	array<int,array{source_id:int,source_type:string,target_id:int,target_type:string}> Link pairs
	 */
	private function extractObjectLinkPairs($action, $object)
	{
		$context = (is_object($object) && !empty($object->context) && is_array($object->context)) ? $object->context : array();
		$pairs = array();

		if ($action === 'OBJECT_LINK_DELETE' && !empty($context['link_id'])) {
			$link = $this->fetchObjectLinkById((int) $context['link_id']);
			if (!empty($link)) {
				$pairs[] = $link;
				return $pairs;
			}
		}

		if ($action === 'OBJECT_LINK_INSERT' && !empty($context['link_origin']) && !empty($context['link_origin_id']) && is_object($object)) {
			$pairs[] = array(
				'source_id' => (int) $context['link_origin_id'],
				'source_type' => $this->normalizeElementType((string) $context['link_origin']),
				'target_id' => $this->getObjectId($object),
				'target_type' => $this->normalizeElementType(!empty($object->element) ? (string) $object->element : ''),
			);
			return $pairs;
		}

		if (!empty($context['link_source_id']) && !empty($context['link_source_type']) && !empty($context['link_target_id']) && !empty($context['link_target_type'])) {
			$pairs[] = array(
				'source_id' => (int) $context['link_source_id'],
				'source_type' => $this->normalizeElementType((string) $context['link_source_type']),
				'target_id' => (int) $context['link_target_id'],
				'target_type' => $this->normalizeElementType((string) $context['link_target_type']),
			);
		}

		return $pairs;
	}

	/**
	 * Fetch an object link row before Dolibarr deletes it.
	 *
	 * @param	int	$linkId	element_element row id
	 * @return	array{source_id:int,source_type:string,target_id:int,target_type:string}|array<string,mixed>	Link pair or empty array
	 */
	private function fetchObjectLinkById($linkId)
	{
		$linkId = (int) $linkId;
		if ($linkId <= 0) {
			return array();
		}

		$sql = "SELECT fk_source, sourcetype, fk_target, targettype";
		$sql .= " FROM ".$this->db->prefix()."element_element";
		$sql .= " WHERE rowid = ".$linkId;
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->registerError(__METHOD__.' link lookup failed for rowid='.$linkId.': '.$this->db->lasterror(), LOG_WARNING);
			return array();
		}

		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($obj)) {
			return array();
		}

		return array(
			'source_id' => (int) $obj->fk_source,
			'source_type' => $this->normalizeElementType((string) $obj->sourcetype),
			'target_id' => (int) $obj->fk_target,
			'target_type' => $this->normalizeElementType((string) $obj->targettype),
		);
	}

	/**
	 * Recompute and log a set of power plants.
	 *
	 * @param	array<int,int>		$powerPlantIds	Power plant ids indexed by id
	 * @param	User				$user			User
	 * @param	string				$reason			Trigger action
	 * @param	array<string,mixed>	$context		Log context
	 * @return	void
	 */
	private function recomputeByPowerPlantIds(array $powerPlantIds, User $user, $reason, array $context = array())
	{
		$powerPlantIds = array_filter(array_map('intval', array_values($powerPlantIds)));
		$powerPlantIds = array_values(array_unique($powerPlantIds));
		if (empty($powerPlantIds)) {
			$this->logIgnored($reason, 'no_powerplant_target', $context);
			return;
		}

		$scheduler = new PowerPlantPVMaintenanceScheduler($this->db);
		$done = 0;
		foreach ($powerPlantIds as $powerPlantId) {
			$powerPlant = $this->fetchPowerPlant((int) $powerPlantId);
			if (!$powerPlant instanceof PowerPlant) {
				continue;
			}

			$schedule = $scheduler->getScheduleForPowerPlant($powerPlant, $user, null, 1);
			$summary = isset($schedule['summary']) && is_array($schedule['summary']) ? $schedule['summary'] : array();
			dol_syslog(__METHOD__.' action='.$reason.' recomputed powerplant_id='.(int) $powerPlant->id.' ref='.(string) $powerPlant->ref.' summary='.json_encode($summary), LOG_DEBUG);
			$done++;
		}

		dol_syslog(__METHOD__.' action='.$reason.' targeted_powerplants='.implode(',', $powerPlantIds).' recomputed='.$done.' context='.json_encode($this->sanitizeLogContext($context)), LOG_DEBUG);
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
			$this->registerError(__METHOD__.' power plant lookup failed for id='.(int) $powerPlantId.': '.$this->db->lasterror(), LOG_WARNING);
			return null;
		}

		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($obj)) {
			dol_syslog(__METHOD__.' skipped inaccessible powerplant_id='.(int) $powerPlantId, LOG_DEBUG);
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
	 * Fetch power plant ids linked to a Dolibarr object.
	 *
	 * @param	string	$objectType	Object type
	 * @param	int		$objectId	Object id
	 * @return	array<int,int>		Power plant ids indexed by id
	 */
	private function fetchPowerPlantIdsLinkedToObject($objectType, $objectId)
	{
		$objectType = $this->normalizeElementType((string) $objectType);
		$objectId = (int) $objectId;
		if ($objectType === '' || $objectId <= 0) {
			return array();
		}
		if (($objectType === 'contrat' || $objectType === 'fichinter') && !$this->isLinkedObjectVisible($objectType, $objectId)) {
			dol_syslog(__METHOD__.' skipped inaccessible '.$objectType.'#'.$objectId, LOG_DEBUG);
			return array();
		}

		$powerPlantTypes = $this->getSqlStringList(powerplantpvGetPowerPlantLinkTypes());
		$sql = "SELECT DISTINCT CASE";
		$sql .= " WHEN ee.sourcetype = '".$this->db->escape($objectType)."' AND ee.fk_source = ".$objectId." THEN ee.fk_target";
		$sql .= " ELSE ee.fk_source END AS powerplant_id";
		$sql .= " FROM ".$this->db->prefix()."element_element AS ee";
		$sql .= " WHERE (ee.sourcetype = '".$this->db->escape($objectType)."' AND ee.fk_source = ".$objectId." AND ee.targettype IN (".$powerPlantTypes."))";
		$sql .= " OR (ee.targettype = '".$this->db->escape($objectType)."' AND ee.fk_target = ".$objectId." AND ee.sourcetype IN (".$powerPlantTypes."))";

		$ids = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->registerError(__METHOD__.' power plant link lookup failed for '.$objectType.'#'.$objectId.': '.$this->db->lasterror(), LOG_WARNING);
			return $ids;
		}
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$id = (int) $obj->powerplant_id;
			if ($id > 0) {
				$ids[$id] = $id;
			}
		}
		$this->db->free($resql);

		return $ids;
	}

	/**
	 * Check that a linked core object is visible in the current entity scope.
	 *
	 * @param	string	$objectType	Object type
	 * @param	int		$objectId	Object id
	 * @return	bool				True if visible
	 */
	private function isLinkedObjectVisible($objectType, $objectId)
	{
		$table = '';
		$entityKey = '';
		if ($objectType === 'contrat') {
			$table = 'contrat';
			$entityKey = 'contrat';
		} elseif ($objectType === 'fichinter') {
			$table = 'fichinter';
			$entityKey = 'fichinter';
		}
		if ($table === '' || $entityKey === '') {
			return true;
		}

		$sql = "SELECT rowid";
		$sql .= " FROM ".$this->db->prefix().$table;
		$sql .= " WHERE rowid = ".((int) $objectId);
		$sql .= " AND entity IN (".$this->db->sanitize(getEntity($entityKey)).")";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->registerError(__METHOD__.' '.$objectType.' lookup failed for id='.(int) $objectId.': '.$this->db->lasterror(), LOG_WARNING);
			return false;
		}
		$visible = ($this->db->num_rows($resql) > 0);
		$this->db->free($resql);

		return $visible;
	}

	/**
	 * Fetch contracts linked to an intervention.
	 *
	 * @param	int	$interventionId	Intervention id
	 * @return	array<int,int>		Contract ids indexed by id
	 */
	private function fetchContractIdsLinkedToIntervention($interventionId)
	{
		$interventionId = (int) $interventionId;
		if ($interventionId <= 0) {
			return array();
		}

		$ids = array();
		$directId = $this->fetchDirectInterventionContractId($interventionId);
		if ($directId > 0) {
			$ids[$directId] = $directId;
		}

		$sql = "SELECT DISTINCT CASE";
		$sql .= " WHEN ee.sourcetype = 'fichinter' AND ee.fk_source = ".$interventionId." THEN ee.fk_target";
		$sql .= " ELSE ee.fk_source END AS contract_id";
		$sql .= " FROM ".$this->db->prefix()."element_element AS ee";
		$sql .= " WHERE (ee.sourcetype = 'fichinter' AND ee.fk_source = ".$interventionId." AND ee.targettype = 'contrat')";
		$sql .= " OR (ee.targettype = 'fichinter' AND ee.fk_target = ".$interventionId." AND ee.sourcetype = 'contrat')";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->registerError(__METHOD__.' intervention-contract link lookup failed for fichinter#'.$interventionId.': '.$this->db->lasterror(), LOG_WARNING);
			return $ids;
		}
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$id = (int) $obj->contract_id;
			if ($id > 0) {
				$ids[$id] = $id;
			}
		}
		$this->db->free($resql);

		return $ids;
	}

	/**
	 * Fetch direct contract id stored on an intervention.
	 *
	 * @param	int	$interventionId	Intervention id
	 * @return	int					Contract id or 0
	 */
	private function fetchDirectInterventionContractId($interventionId)
	{
		$sql = "SELECT fk_contrat";
		$sql .= " FROM ".$this->db->prefix()."fichinter";
		$sql .= " WHERE rowid = ".((int) $interventionId);
		$sql .= " AND entity IN (".$this->db->sanitize(getEntity('fichinter')).")";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->registerError(__METHOD__.' direct contract lookup failed for fichinter#'.(int) $interventionId.': '.$this->db->lasterror(), LOG_WARNING);
			return 0;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);

		return is_object($obj) ? (int) $obj->fk_contrat : 0;
	}

	/**
	 * Fetch contract id from a contract line.
	 *
	 * @param	int	$lineId	Line id
	 * @return	int			Contract id or 0
	 */
	private function fetchContractIdFromLine($lineId)
	{
		$sql = "SELECT d.fk_contrat";
		$sql .= " FROM ".$this->db->prefix()."contratdet AS d";
		$sql .= " INNER JOIN ".$this->db->prefix()."contrat AS c ON c.rowid = d.fk_contrat";
		$sql .= " WHERE d.rowid = ".((int) $lineId);
		$sql .= " AND c.entity IN (".$this->db->sanitize(getEntity('contrat')).")";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->registerError(__METHOD__.' contract line lookup failed for line#'.(int) $lineId.': '.$this->db->lasterror(), LOG_WARNING);
			return 0;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);

		return is_object($obj) ? (int) $obj->fk_contrat : 0;
	}

	/**
	 * Fetch intervention id from an intervention line.
	 *
	 * @param	int	$lineId	Line id
	 * @return	int			Intervention id or 0
	 */
	private function fetchInterventionIdFromLine($lineId)
	{
		$sql = "SELECT d.fk_fichinter";
		$sql .= " FROM ".$this->db->prefix()."fichinterdet AS d";
		$sql .= " INNER JOIN ".$this->db->prefix()."fichinter AS f ON f.rowid = d.fk_fichinter";
		$sql .= " WHERE d.rowid = ".((int) $lineId);
		$sql .= " AND f.entity IN (".$this->db->sanitize(getEntity('fichinter')).")";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->registerError(__METHOD__.' intervention line lookup failed for line#'.(int) $lineId.': '.$this->db->lasterror(), LOG_WARNING);
			return 0;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);

		return is_object($obj) ? (int) $obj->fk_fichinter : 0;
	}

	/**
	 * Return an integer object property from a list of candidate names.
	 *
	 * @param	mixed				$object		Object
	 * @param	array<int,string>	$properties	Property names
	 * @return	int								First positive value
	 */
	private function getObjectIntProperty($object, array $properties)
	{
		if (!is_object($object)) {
			return 0;
		}
		foreach ($properties as $property) {
			if (isset($object->{$property}) && (int) $object->{$property} > 0) {
				return (int) $object->{$property};
			}
		}

		return 0;
	}

	/**
	 * Return the object id.
	 *
	 * @param	mixed	$object	Object
	 * @return	int				Object id
	 */
	private function getObjectId($object)
	{
		if (!is_object($object)) {
			return 0;
		}
		if (!empty($object->id)) {
			return (int) $object->id;
		}
		if (!empty($object->rowid)) {
			return (int) $object->rowid;
		}

		return 0;
	}

	/**
	 * Normalize element type and power plant legacy aliases.
	 *
	 * @param	string	$elementType	Element type
	 * @return	string					Normalized element type
	 */
	private function normalizeElementType($elementType)
	{
		$elementType = powerplantpvNormalizeElementType((string) $elementType);
		if (powerplantpvIsPowerPlantLinkType($elementType)) {
			return $elementType;
		}

		return $elementType;
	}

	/**
	 * Build a SQL string list.
	 *
	 * @param	array<int,string>	$values	Values
	 * @return	string						Escaped SQL list
	 */
	private function getSqlStringList(array $values)
	{
		$escaped = array();
		foreach ($values as $value) {
			$escaped[] = "'".$this->db->escape((string) $value)."'";
		}

		return empty($escaped) ? "''" : implode(',', $escaped);
	}

	/**
	 * Log an ignored trigger.
	 *
	 * @param	string				$reason		Trigger action
	 * @param	string				$cause		Cause
	 * @param	array<string,mixed>	$context	Log context
	 * @return	void
	 */
	private function logIgnored($reason, $cause, array $context = array())
	{
		dol_syslog(__METHOD__.' action='.$reason.' ignored='.$cause.' context='.json_encode($this->sanitizeLogContext($context)), LOG_DEBUG);
	}

	/**
	 * Register and log an error without blocking the source object mutation.
	 *
	 * @param	string	$message	Error message
	 * @param	int		$level		Log level
	 * @return	void
	 */
	private function registerError($message, $level = LOG_WARNING)
	{
		$this->error = (string) $message;
		$this->errors[] = $this->error;
		dol_syslog($this->error, $level);
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
