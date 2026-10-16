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
