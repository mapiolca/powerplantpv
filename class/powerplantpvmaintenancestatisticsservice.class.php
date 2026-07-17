<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/class/powerplantpvmaintenancescheduler.class.php');

/**
 * Historical maintenance statistics built from dated intervention records.
 */
class PowerPlantPVMaintenanceStatisticsService
{
	/** @var DoliDB */
	private $db;

	/** @var array<string,array<int,array<string,mixed>>> */
	private static $historyCache = array();

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Return a two- or three-year comparison ending with the base year.
	 *
	 * @param User $user Current user
	 * @param int $baseYear Most recent compared year
	 * @param int $yearCount Number of compared years
	 * @return array<string,mixed>
	 */
	public function getComparison($user, $baseYear, $yearCount)
	{
		global $conf;

		$baseYear = (int) $baseYear;
		$yearCount = ((int) $yearCount === 2) ? 2 : 3;
		$firstYear = $baseYear - $yearCount + 1;
		$cacheKey = implode(':', array((int) $conf->entity, (int) $user->id, (int) $user->socid, $firstYear, $baseYear));
		if (!isset(self::$historyCache[$cacheKey])) {
			self::$historyCache[$cacheKey] = $this->fetchHistoryRows($user, $firstYear, $baseYear);
		}

		return $this->aggregateHistoryRows(self::$historyCache[$cacheKey], $baseYear, $yearCount);
	}

	/**
	 * Aggregate historical rows. Public to keep year-boundary tests deterministic.
	 *
	 * A maintenance intervention contributes once to global, nature and customer
	 * totals. It contributes once per distinct linked power plant to the power
	 * plant distribution.
	 *
	 * @param array<int,array<string,mixed>> $rows Historical rows
	 * @param int $baseYear Most recent year
	 * @param int $yearCount Number of years
	 * @return array<string,mixed>
	 */
	public function aggregateHistoryRows(array $rows, $baseYear, $yearCount)
	{
		$yearCount = ((int) $yearCount === 2) ? 2 : 3;
		$years = array();
		for ($offset = 0; $offset < $yearCount; $offset++) {
			$years[] = (int) $baseYear - $offset;
		}
		$annual = array();
		$monthly = array('total' => array(), 'completed' => array());
		foreach ($years as $year) {
			$annual[$year] = array('total' => 0, 'completed' => 0, 'open' => 0, 'completion_rate' => 0.0);
			for ($month = 1; $month <= 12; $month++) {
				$monthly['total'][$year][$month] = 0;
				$monthly['completed'][$year][$month] = 0;
			}
		}
		$distributions = array('nature' => array(), 'powerplant' => array(), 'customer' => array());
		$seenInterventions = array();
		$seenPowerPlants = array();

		foreach ($rows as $row) {
			$interventionId = !empty($row['intervention_id']) ? (int) $row['intervention_id'] : 0;
			$timestamp = !empty($row['effective_date']) ? (int) $row['effective_date'] : 0;
			if ($interventionId <= 0 || $timestamp <= 0) {
				continue;
			}
			$year = (int) dol_print_date($timestamp, '%Y');
			$month = (int) dol_print_date($timestamp, '%m');
			if (!isset($annual[$year])) {
				continue;
			}
			$completed = !empty($row['completed']);
			if (!isset($seenInterventions[$interventionId])) {
				$seenInterventions[$interventionId] = true;
				$annual[$year]['total']++;
				$monthly['total'][$year][$month]++;
				if ($completed) {
					$annual[$year]['completed']++;
					$monthly['completed'][$year][$month]++;
				}
				$this->incrementDistribution($distributions['nature'], 'nature_'.((int) $row['nature_id']), (string) $row['nature_label'], $year);
				$customerId = !empty($row['fk_soc']) ? (int) $row['fk_soc'] : 0;
				$this->incrementDistribution(
					$distributions['customer'],
					'customer_'.$customerId,
					(string) $row['customer_label'],
					$year,
					$customerId > 0 ? DOL_URL_ROOT.'/societe/card.php?socid='.$customerId : ''
				);
			}

			$powerplantId = !empty($row['powerplant_id']) ? (int) $row['powerplant_id'] : 0;
			$powerplantKey = $interventionId.':'.$powerplantId;
			if ($powerplantId > 0 && !isset($seenPowerPlants[$powerplantKey])) {
				$seenPowerPlants[$powerplantKey] = true;
				$this->incrementDistribution(
					$distributions['powerplant'],
					'powerplant_'.$powerplantId,
					(string) $row['powerplant_label'],
					$year,
					dol_buildpath('/powerplantpv/powerplant_card.php', 1).'?id='.$powerplantId
				);
			}
		}

		foreach ($years as $year) {
			$annual[$year]['open'] = $annual[$year]['total'] - $annual[$year]['completed'];
			$annual[$year]['completion_rate'] = $annual[$year]['total'] > 0
				? round(($annual[$year]['completed'] * 100) / $annual[$year]['total'], 2)
				: 0.0;
		}
		foreach ($distributions as $type => $buckets) {
			uasort($buckets, static function ($a, $b) {
				$result = ((int) $b['total']) <=> ((int) $a['total']);
				return $result !== 0 ? $result : strnatcasecmp((string) $a['label'], (string) $b['label']);
			});
			$distributions[$type] = array_values($buckets);
		}

		return array(
			'years' => $years,
			'annual' => $annual,
			'monthly' => $monthly,
			'distributions' => $distributions,
		);
	}

	/**
	 * Fetch dated maintenance interventions visible to the current user.
	 *
	 * @param User $user Current user
	 * @param int $firstYear First included year
	 * @param int $lastYear Last included year
	 * @return array<int,array<string,mixed>>
	 */
	private function fetchHistoryRows($user, $firstYear, $lastYear)
	{
		global $langs;

		$linkTypes = array();
		foreach (powerplantpvGetPowerPlantLinkTypes() as $linkType) {
			$linkTypes[] = "'".$this->db->escape($linkType)."'";
		}
		$linkTypesSql = implode(',', $linkTypes);
		$effectiveDateSql = 'COALESCE(f.dateo, fd.line_start, f.datei, f.datee, fd.line_end, f.datet, f.datec)';
		$dateStart = dol_mktime(0, 0, 0, 1, 1, (int) $firstYear);
		$dateEnd = dol_mktime(23, 59, 59, 12, 31, (int) $lastYear);

		$sql = 'SELECT DISTINCT f.rowid as intervention_id, f.fk_statut, f.signed_status, f.fk_soc';
		$sql .= ', p.rowid as powerplant_id, p.ref as powerplant_ref, p.label as powerplant_name';
		$sql .= ', s.nom as customer_name, n.rowid as nature_id, n.label as nature_label, n.label_en as nature_label_en';
		$sql .= ', '.$effectiveDateSql.' as effective_date';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'element_element AS ee';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'powerplantpv_powerplant AS p ON (';
		$sql .= '(ee.sourcetype IN ('.$linkTypesSql.') AND ee.fk_source = p.rowid)';
		$sql .= ' OR (ee.targettype IN ('.$linkTypesSql.') AND ee.fk_target = p.rowid))';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'fichinter AS f ON (';
		$sql .= "(ee.sourcetype = 'fichinter' AND ee.fk_source = f.rowid)";
		$sql .= " OR (ee.targettype = 'fichinter' AND ee.fk_target = f.rowid))";
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'fichinter_extrafields AS fe ON fe.fk_object = f.rowid';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'c_powerplantpv_intervention_nature AS n ON n.rowid = fe.powerplantpv_intervention_nature AND n.is_maintenance = 1';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe AS s ON s.rowid = f.fk_soc';
		$sql .= ' LEFT JOIN (SELECT fk_fichinter, MIN(date) as line_start, MAX(date) as line_end';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'fichinterdet GROUP BY fk_fichinter) AS fd ON fd.fk_fichinter = f.rowid';
		$sql .= " WHERE ((ee.sourcetype = 'fichinter' AND ee.targettype IN (".$linkTypesSql."))";
		$sql .= " OR (ee.targettype = 'fichinter' AND ee.sourcetype IN (".$linkTypesSql.")))";
		$sql .= ' AND p.entity IN ('.$this->db->sanitize(getEntity('powerplant')).')';
		$sql .= ' AND f.entity IN ('.$this->db->sanitize(getEntity('fichinter')).')';
		$sql .= ' AND n.entity IN ('.$this->db->sanitize(getEntity('c_powerplantpv_intervention_nature')).')';
		$sql .= " AND ".$effectiveDateSql." >= '".$this->db->idate($dateStart)."'";
		$sql .= " AND ".$effectiveDateSql." <= '".$this->db->idate($dateEnd)."'";
		if (!empty($user->socid)) {
			$sql .= ' AND p.fk_soc = '.((int) $user->socid);
			$sql .= ' AND f.fk_soc = '.((int) $user->socid);
		}
		$sql .= ' ORDER BY effective_date ASC, f.rowid ASC, p.rowid ASC';

		$rows = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' historical maintenance query failed: '.$this->db->lasterror(), LOG_WARNING);
			return $rows;
		}
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$natureLabel = (!empty($langs->defaultlang) && strpos((string) $langs->defaultlang, 'en_') === 0 && !empty($obj->nature_label_en))
				? (string) $obj->nature_label_en
				: (string) $obj->nature_label;
			$powerplantLabel = trim((string) $obj->powerplant_ref.' - '.(string) $obj->powerplant_name, ' -');
			$rows[] = array(
				'intervention_id' => (int) $obj->intervention_id,
				'effective_date' => $this->db->jdate($obj->effective_date),
				'completed' => PowerPlantPVMaintenanceScheduler::isInterventionCompleted((int) $obj->fk_statut, (int) $obj->signed_status),
				'nature_id' => (int) $obj->nature_id,
				'nature_label' => $natureLabel !== '' ? $natureLabel : (string) $obj->nature_label,
				'powerplant_id' => (int) $obj->powerplant_id,
				'powerplant_label' => $powerplantLabel !== '' ? $powerplantLabel : '#'.((int) $obj->powerplant_id),
				'fk_soc' => (int) $obj->fk_soc,
				'customer_label' => !empty($obj->customer_name) ? (string) $obj->customer_name : '#'.((int) $obj->fk_soc),
			);
		}
		$this->db->free($resql);

		return $rows;
	}

	/**
	 * Increment a comparative distribution bucket.
	 *
	 * @param array<string,array<string,mixed>> $buckets Buckets
	 * @param string $key Bucket key
	 * @param string $label Label
	 * @param int $year Year
	 * @param string $url Optional URL
	 * @return void
	 */
	private function incrementDistribution(&$buckets, $key, $label, $year, $url = '')
	{
		if (!isset($buckets[$key])) {
			$buckets[$key] = array('label' => $label, 'years' => array(), 'total' => 0, 'url' => $url);
		}
		if (!isset($buckets[$key]['years'][$year])) {
			$buckets[$key]['years'][$year] = 0;
		}
		$buckets[$key]['years'][$year]++;
		$buckets[$key]['total']++;
	}
}
