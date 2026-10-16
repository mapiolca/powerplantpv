<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once __DIR__.'/powerplant.class.php';

/** Native many-to-many project / power plant links, with independent access scopes. */
class PowerPlantPVProjectLink
{
	/** @var DoliDB */
	private $db;
	/** @var string */
	public $error = '';

	/** @param DoliDB $db Native database handler */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Read available projects using the native public / assigned / all-projects policy.
	 * @param User $user Actor
	 * @return array<int,Project> Projects indexed by id
	 */
	public function projects(User $user)
	{
		if (!isModEnabled('project') || (!$user->hasRight('projet', 'lire') && !$user->hasRight('projet', 'all', 'lire'))) {
			return array();
		}
		$project = new Project($this->db);
		$ids = $project->getProjectsAuthorizedForUser($user, $user->hasRight('projet', 'all', 'lire') ? 2 : 0, 1, (int) $user->socid);
		$ids = is_string($ids) && $ids !== '' ? $ids : '0';
		$sql = 'SELECT p.rowid, p.ref, p.title, p.entity, p.fk_soc, p.public, p.fk_statut';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'projet as p';
		$sql .= ' WHERE p.entity IN ('.$this->db->sanitize(getEntity('project')).')';
		$sql .= ' AND p.rowid IN ('.$this->db->sanitize((string) $ids).') ORDER BY p.ref, p.rowid';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return array();
		}
		$projects = array();
		while ($row = $this->db->fetch_object($resql)) {
			$item = new Project($this->db);
			$item->id = (int) $row->rowid;
			$item->ref = $row->ref;
			$item->title = $row->title;
			$item->entity = (int) $row->entity;
			$item->socid = (int) $row->fk_soc;
			$item->public = (int) $row->public;
			$item->status = (int) $row->fk_statut;
			$projects[$item->id] = $item;
		}
		$this->db->free($resql);
		return $projects;
	}

	/**
	 * Read available power plants, including the native sales representative restriction.
	 * @param User $user Actor
	 * @return array<int,PowerPlant> Power plants indexed by id
	 */
	public function powerplants(User $user)
	{
		if (!isModEnabled('powerplantpv') || !$user->hasRight('powerplantpv', 'powerplant', 'read')) {
			return array();
		}
		$sql = 'SELECT p.rowid, p.ref, p.label, p.entity, p.fk_soc, p.status';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'powerplantpv_powerplant as p';
		$sql .= ' WHERE p.entity IN ('.$this->db->sanitize(getEntity('powerplant')).')';
		if ($user->socid > 0) {
			$sql .= ' AND p.fk_soc = '.((int) $user->socid);
		} elseif (isModEnabled('societe') && !$user->hasRight('societe', 'client', 'voir')) {
			$userids = array((int) $user->id);
			if (getDolGlobalInt('MAIN_SEE_SUBORDINATES')) {
				$userids = array_merge($userids, $user->getAllChildIds());
			}
			$sql .= ' AND (p.fk_soc IS NULL OR p.fk_soc = 0 OR EXISTS (SELECT sc.fk_soc';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'societe_commerciaux as sc WHERE sc.fk_soc = p.fk_soc';
			$sql .= ' AND sc.fk_user IN ('.$this->db->sanitize(implode(',', array_map('intval', $userids))).')))';
		}
		$sql .= ' ORDER BY p.ref, p.rowid';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return array();
		}
		$plants = array();
		while ($row = $this->db->fetch_object($resql)) {
			$item = new PowerPlant($this->db);
			$item->id = (int) $row->rowid;
			$item->ref = $row->ref;
			$item->label = $row->label;
			$item->entity = (int) $row->entity;
			$item->fk_soc = $item->socid = (int) $row->fk_soc;
			$item->status = (int) $row->status;
			$plants[$item->id] = $item;
		}
		$this->db->free($resql);
		return $plants;
	}

	/**
	 * Read both historical directions without making invisible links editable.
	 * @param CommonObject $object Project or power plant
	 * @param bool $lock Use a native locking read during synchronization
	 * @return list<array{rowid:int,project:int,powerplant:int}> Native link rows
	 */
	public function rows($object, $lock = false)
	{
		$isproject = $object instanceof Project;
		$id = (int) $object->id;
		if ($id <= 0) {
			return array();
		}
		$types = "('powerplant@powerplantpv','powerplant','powerplantpv_powerplant')";
		$projects = "('project','projet')";
		$sql = 'SELECT ee.rowid, ee.fk_source, ee.sourcetype, ee.fk_target, ee.targettype';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'element_element as ee WHERE (';
		$sql .= '(ee.sourcetype IN '.$projects.' AND ee.targettype IN '.$types;
		$sql .= ' AND ee.'.($isproject ? 'fk_source' : 'fk_target').' = '.$id.')';
		$sql .= ' OR (ee.targettype IN '.$projects.' AND ee.sourcetype IN '.$types;
		$sql .= ' AND ee.'.($isproject ? 'fk_target' : 'fk_source').' = '.$id.')) ORDER BY ee.rowid';
		if ($lock) {
			$sql .= ' FOR UPDATE';
		}
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return array();
		}
		$rows = array();
		while ($row = $this->db->fetch_object($resql)) {
			$forward = in_array($row->sourcetype, array('project', 'projet'), true);
			$rows[] = array('rowid' => (int) $row->rowid,
				'project' => (int) ($forward ? $row->fk_source : $row->fk_target),
				'powerplant' => (int) ($forward ? $row->fk_target : $row->fk_source));
		}
		$this->db->free($resql);
		return $rows;
	}

	/**
	 * Visible counterparts, sharing the exact same scope as the selectors.
	 * @param CommonObject $object Project or power plant
	 * @param User $user Actor
	 * @return array<int,CommonObject> Linked objects
	 */
	public function linked($object, User $user)
	{
		$owners = $object instanceof Project ? $this->projects($user) : $this->powerplants($user);
		if (!isset($owners[(int) $object->id])) {
			return array();
		}
		$options = $object instanceof Project ? $this->powerplants($user) : $this->projects($user);
		$linked = array();
		$key = $object instanceof Project ? 'powerplant' : 'project';
		foreach ($this->rows($object) as $row) {
			if (isset($options[$row[$key]])) {
				$linked[$row[$key]] = $options[$row[$key]];
			}
		}
		return $linked;
	}

	/**
	 * Atomically replace the visible selection; preserve hidden links and reject forged ids.
	 * The edited owner must be writable; counterparts must be readable and accessible.
	 * @param CommonObject $object Loaded project or power plant
	 * @param list<int> $selectedids Submitted counterpart ids
	 * @param User $user Actor
	 * @param int $notrigger Suppress the owner event when called inside create/update
	 * @param string|null $expectedRevision Revision from the edit form, or null for an internal operation
	 * @return int 1 on success, -1 on refusal or database failure
	 */
	public function sync($object, array $selectedids, User $user, $notrigger = 0, $expectedRevision = null)
	{
		$this->error = '';
		$isproject = $object instanceof Project;
		if (!isModEnabled('powerplantpv') || !isModEnabled('project') || $user->socid > 0
			|| !$user->hasRight('powerplantpv', 'powerplant', 'read')
			|| ($isproject && !$user->hasRight('projet', 'creer') && !$user->hasRight('projet', 'all', 'creer'))
			|| (!$isproject && (!($object instanceof PowerPlant) || !$user->hasRight('powerplantpv', 'powerplant', 'write')))) {
			$this->error = 'ErrorForbidden';
			return -1;
		}
		$projects = $this->projects($user);
		$plants = $this->powerplants($user);
		$id = (int) $object->id;
		if ($this->error !== '' || ($isproject ? !isset($projects[$id]) : !isset($plants[$id]))) {
			$this->error = $this->error ?: 'ErrorForbidden';
			return -1;
		}
		$options = $isproject ? $plants : $projects;
		$selected = array();
		foreach ($selectedids as $selectedid) {
			if (!is_int($selectedid) || $selectedid <= 0 || !isset($options[$selectedid])) {
				$this->error = 'ErrorForbidden';
				return -1;
			}
			$selected[$selectedid] = true;
		}
		$rows = $this->rows($object);
		if ($this->error !== '') {
			return -1;
		}
		$revision = hash('sha256', json_encode($rows));
		if ($expectedRevision !== null && (!is_string($expectedRevision) || !hash_equals($revision, $expectedRevision))) {
			$this->error = 'PowerPlantPVProjectLinksConflict';
			return -1;
		}
		$projectids = $isproject ? array($id) : array_keys($selected);
		foreach ($rows as $row) {
			if (isset($projects[$row['project']], $plants[$row['powerplant']])) {
				$projectids[] = $row['project'];
			}
		}
		if ($isproject && $projects[$id]->restrictedProjectArea($user, 'write') <= 0) {
			$this->error = 'ErrorForbidden';
			return -1;
		}
		if (!$this->db->begin()) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		sort($projectids, SORT_NUMERIC);
		$lock = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'projet WHERE rowid IN (';
		$lock .= $this->db->sanitize(implode(',', array_map('intval', array_unique($projectids)))).')';
		$lock .= ' AND entity IN ('.$this->db->sanitize(getEntity('project')).') ORDER BY rowid FOR UPDATE';
		if ($projectids && !$this->db->query($lock)) {
			$this->error = $this->db->lasterror();
		}
		// Serialize edits from both directions using the owning rows in a stable order.
		$plantids = $isproject ? array_keys($selected) : array($id);
		foreach ($rows as $row) {
			$plantids[] = $row['powerplant'];
		}
		sort($plantids, SORT_NUMERIC);
		if ($plantids) {
			$lock = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'powerplantpv_powerplant WHERE rowid IN (';
			$lock .= $this->db->sanitize(implode(',', array_map('intval', array_unique($plantids)))).')';
			$lock .= ' AND entity IN ('.$this->db->sanitize(getEntity('powerplant')).') ORDER BY rowid FOR UPDATE';
			if (!$this->db->query($lock)) {
				$this->error = $this->db->lasterror();
			}
		}
		$latest = $this->rows($object, true);
		if ($this->error === '' && hash('sha256', json_encode($latest)) !== $revision) {
			$this->error = 'PowerPlantPVProjectLinksConflict';
		}
		$kept = array();
		$changed = false;
		$key = $isproject ? 'powerplant' : 'project';
		foreach ($rows as $row) {
			$otherid = $row[$key];
			if (!isset($options[$otherid])) {
				continue;
			}
			if (isset($selected[$otherid]) && !isset($kept[$otherid])) {
				$kept[$otherid] = true;
				continue;
			}
			if ($this->error === '' && $object->deleteObjectLinked(null, '', null, '', $row['rowid'], $user, 1) <= 0) {
				$this->error = $object->error ?: $this->db->lasterror();
			}
			$changed = true;
		}
		foreach (array_keys($selected) as $otherid) {
			if (isset($kept[$otherid]) || $this->error !== '') {
				continue;
			}
			$plant = $isproject ? $plants[$otherid] : $object;
			if ($plant->add_object_linked('project', $isproject ? $id : $otherid, $user, 1) <= 0) {
				$this->error = $plant->error ?: $this->db->lasterror();
			}
			$changed = true;
		}
		if ($this->error === '' && $changed && !$notrigger) {
			$object->context['trigger_reason'] = 'project_powerplant_links';
			$object->context['changed_fields'] = array('linked_projects');
			if ($object->call_trigger($isproject ? 'PROJECT_MODIFY' : 'POWERPLANTPV_POWERPLANT_UPDATE', $user) < 0) {
				$this->error = $object->error ?: 'ErrorTriggerFailed';
			}
		}
		if ($this->error !== '') {
			$this->db->rollback();
			return -1;
		}
		if (!$this->db->commit()) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		return 1;
	}
}
