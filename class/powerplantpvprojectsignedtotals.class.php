<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';

/** Derived project totals; invoked inside an already authorized proposal transaction. */
class PowerPlantPVProjectSignedTotals
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
	 * Overview mutations use native methods without proposal triggers. Keep the relation
	 * and its derived totals atomic, including the former project when moving a proposal.
	 * The overview requires project write and proposal read, not proposal creation rights.
	 * @param Project $project Loaded destination/current project
	 * @param int $proposalid Proposal selected by the native overview
	 * @param bool $unlink Remove only this project's relation
	 * @param User $user Native operation actor
	 * @return int 1 changed, 0 already applied, -1 refused or failed
	 */
	public function setProposalProject(Project $project, $proposalid, $unlink, User $user)
	{
		$this->error = '';
		if (!isModEnabled('powerplantpv') || !isModEnabled('project') || !isModEnabled('propal')
			|| $user->socid > 0 || !$user->hasRight('projet', 'creer') || !$user->hasRight('propal', 'lire')
			|| (int) $proposalid <= 0 || (int) $project->id <= 0
			|| (!$unlink && getDolGlobalString('PROJECT_LINK_ON_OVERWIEW_DISABLED'))
			|| ($unlink && getDolGlobalString('PROJECT_DISABLE_UNLINK_FROM_OVERVIEW') && !$user->admin)) {
			$this->error = 'ErrorForbidden';
			return -1;
		}
		require_once __DIR__.'/powerplantpvprojectlink.class.php';
		$links = new PowerPlantPVProjectLink($this->db);
		$projects = $links->projects($user);
		if ($links->error !== '' || !isset($projects[(int) $project->id])
			|| $project->restrictedProjectArea($user, 'write') <= 0) {
			$this->error = $links->error ?: 'ErrorForbidden';
			return -1;
		}
		$sql = 'SELECT d.fk_projet FROM '.MAIN_DB_PREFIX.'propal as d';
		$sql .= ' WHERE d.rowid = '.((int) $proposalid).' AND d.entity IN ('.$this->db->sanitize(getEntity('propal')).')';
		if (!$user->hasRight('societe', 'client', 'voir')) {
			$userids = array((int) $user->id);
			if (getDolGlobalInt('MAIN_SEE_SUBORDINATES')) {
				$userids = array_merge($userids, $user->getAllChildIds());
			}
			$sql .= ' AND EXISTS (SELECT sc.fk_soc FROM '.MAIN_DB_PREFIX.'societe_commerciaux as sc';
			$sql .= ' WHERE sc.fk_soc = d.fk_soc AND sc.fk_user IN ('.$this->db->sanitize(implode(',', array_map('intval', $userids))).'))';
		}
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$row = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!$row) {
			$this->error = 'ErrorForbidden';
			return -1;
		}
		$oldproject = (int) $row->fk_projet;
		if (($unlink && $oldproject !== (int) $project->id) || (!$unlink && $oldproject === (int) $project->id)) {
			return 0;
		}
		if ($oldproject > 0 && (!isset($projects[$oldproject]) || $projects[$oldproject]->restrictedProjectArea($user, 'write') <= 0)) {
			$this->error = 'ErrorForbidden';
			return -1;
		}
		$ids = array_values(array_unique(array_filter(array($oldproject, (int) $project->id))));
		sort($ids, SORT_NUMERIC);
		if (!$this->db->begin()) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		// Same parent-first lock order as refresh(); reject concurrent reassignment.
		$resql = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'projet WHERE rowid IN ('
			.$this->db->sanitize(implode(',', $ids)).') AND entity IN ('.$this->db->sanitize(getEntity('project')).') ORDER BY rowid FOR UPDATE');
		if ($resql) {
			$parentsfound = $this->db->num_rows($resql) === count($ids);
			$this->db->free($resql);
			if (!$parentsfound) {
				$this->error = 'PowerPlantPVProjectLinksConflict';
				$this->db->rollback();
				return -1;
			}
			$resql = $this->db->query($sql.' FOR UPDATE');
		}
		$row = $resql ? $this->db->fetch_object($resql) : false;
		if ($resql) {
			$this->db->free($resql);
		}
		if (!$row || (int) $row->fk_projet !== $oldproject) {
			$this->error = $resql ? 'PowerPlantPVProjectLinksConflict' : $this->db->lasterror();
		} else {
			$result = $unlink ? $project->remove_element('propal', (int) $proposalid, 'fk_projet')
				: $project->update_element('propal', (int) $proposalid);
			if ($result < 0) {
				$this->error = $project->error ?: ($this->db->lasterror() ?: 'PowerPlantPVProjectTotalsFailed');
			} elseif ($this->refresh($user, 0, $ids) < 0 && $this->error === '') {
				$this->error = 'PowerPlantPVProjectTotalsFailed';
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

	/**
	 * Rebuild from signed/billed proposals, never increment: retries cannot double totals.
	 * Rebuilding the scope also clears a project's total after a proposal is moved away.
	 * No project-edit right is required for this derived invariant of an authorized signing.
	 * @param User $user Native operation actor
	 * @param int $excludeProposal Proposal being deleted (native trigger runs before DELETE)
	 * @param list<int> $projectids Limit a single operation to its projects; empty rebuilds the accessible scope
	 * @return int Number of projects refreshed, -1 on error
	 */
	public function refresh(User $user, $excludeProposal = 0, array $projectids = array())
	{
		if (!isModEnabled('powerplantpv') || !isModEnabled('project')) {
			return 0;
		}
		$this->error = '';
		require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
		$fields = new ExtraFields($this->db);
		if ($fields->fetch_name_optionals_label('projet') < 0
			|| !isset($fields->attributes['projet']['label']['powerplantpv_signed_peak_power'],
				$fields->attributes['projet']['label']['powerplantpv_signed_storage_capacity'])) {
			$this->error = 'PowerPlantPVProjectTotalsFieldsMissing';
			return -1;
		}
		if (!$this->db->begin()) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$projectfilter = $projectids ? ' AND p.rowid IN ('.$this->db->sanitize(implode(',', array_map('intval', $projectids))).')' : '';
		// Lock parents before reading sums: concurrent signatures must observe each other's commits.
		$sql = 'SELECT p.rowid FROM '.MAIN_DB_PREFIX.'projet as p';
		$sql .= ' WHERE p.entity IN ('.$this->db->sanitize(getEntity('project')).')'.$projectfilter.' ORDER BY p.rowid FOR UPDATE';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		$this->db->free($resql);
		$scope = $this->db->sanitize(getEntity('propal'));
		$sql = 'SELECT p.rowid, COUNT(d.rowid) as proposal_count,';
		$sql .= ' SUM(CASE WHEN d.rowid IS NOT NULL AND (d.entity NOT IN ('.$scope.') OR ef.powerplantpv_peak_power IS NULL) THEN 1 ELSE 0 END) as missing_power,';
		$sql .= ' SUM(CASE WHEN d.rowid IS NOT NULL AND (d.entity NOT IN ('.$scope.') OR ef.powerplantpv_storage_capacity IS NULL) THEN 1 ELSE 0 END) as missing_capacity,';
		$sql .= ' SUM(CASE WHEN d.entity IN ('.$scope.') THEN ef.powerplantpv_peak_power ELSE NULL END) as peak_power,';
		$sql .= ' SUM(CASE WHEN d.entity IN ('.$scope.') THEN ef.powerplantpv_storage_capacity ELSE NULL END) as capacity';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'projet as p';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'propal as d ON d.fk_projet = p.rowid AND d.fk_statut IN (2,4)';
		if ((int) $excludeProposal > 0) {
			$sql .= ' AND d.rowid <> '.((int) $excludeProposal);
		}
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'propal_extrafields as ef ON ef.fk_object = d.rowid AND d.entity IN ('.$scope.')';
		$sql .= ' WHERE p.entity IN ('.$this->db->sanitize(getEntity('project')).')'.$projectfilter.' GROUP BY p.rowid ORDER BY p.rowid FOR UPDATE';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		$totals = array();
		while ($row = $this->db->fetch_object($resql)) {
			$totals[] = $row;
		}
		$this->db->free($resql);
		$count = 0;
		foreach ($totals as $row) {
			$project = new Project($this->db);
			$project->id = (int) $row->rowid;
			// updateExtraField preserves unrelated columns even on v20's delete/insert implementation.
			// An empty numeric string is normalized to SQL NULL by this native method.
			$project->array_options = array(
				'options_powerplantpv_signed_peak_power' => (int) $row->missing_power > 0 ? '' : (float) $row->peak_power,
				'options_powerplantpv_signed_storage_capacity' => (int) $row->missing_capacity > 0 ? '' : (float) $row->capacity,
			);
			foreach (array('powerplantpv_signed_peak_power', 'powerplantpv_signed_storage_capacity') as $key) {
				if ($project->updateExtraField($key, null, $user) <= 0) {
					$this->error = $project->error ?: ($this->db->lasterror() ?: 'PowerPlantPVProjectTotalsFieldsMissing');
					break;
				}
			}
			if ($this->error !== '') {
				break;
			}
			$count++;
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
		return $count;
	}
}
