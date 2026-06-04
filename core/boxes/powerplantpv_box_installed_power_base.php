<?php
/* Copyright (C) 2026  Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

require_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php';
dol_include_once('/commande/class/commande.class.php');

/**
 * Shared helpers for installed peak power widgets.
 */
abstract class PowerPlantPVInstalledPowerBoxBase extends ModeleBoxes
{
	public $boximg = 'chart';
	public $depends = array('powerplantpv', 'commande');
	public $lang = 'powerplantpv@powerplantpv';
	public $version = 'dolibarr';

	/**
	 * Constructor.
	 *
	 * @param	DoliDB	$db		Database handler
	 * @param	string	$param	Box parameters
	 */
	public function __construct($db, $param = '')
	{
		global $user;

		parent::__construct($db, $param);
		$this->db = $db;
		$this->param = $param;
		if (!isModEnabled('order')) {
			$this->hidden = true;
		}
		if (is_object($user) && !$user->hasRight('commande', 'lire')) {
			$this->hidden = true;
		}
		if (
			is_object($user)
			&& getDolGlobalInt('POWERPLANTPV_ENABLE_PERMISSION_CHECK')
			&& !$user->hasRight('powerplantpv', 'powerplant', 'read')
		) {
			$this->hidden = true;
		}
	}

	/**
	 * Display box.
	 *
	 * @param	array<string,mixed>|null	$head		Head
	 * @param	array<int,mixed>|null		$contents	Contents
	 * @param	int<0,1>					$nooutput	No output
	 * @return	string
	 */
	public function showBox($head = null, $contents = null, $nooutput = 0)
	{
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
	}

	/**
	 * Load module translations.
	 *
	 * @return	void
	 */
	protected function loadPowerPlantPVLangs()
	{
		global $langs;

		$langs->loadLangs(array('powerplantpv@powerplantpv'));
	}

	/**
	 * Build the standard widget header.
	 *
	 * @param	string	$titleKey	Title translation key
	 * @return	array<string,mixed>
	 */
	protected function buildHeader($titleKey)
	{
		global $langs;

		return array(
			'text' => $langs->trans($titleKey),
			'limit' => 0,
			'subpicto' => 'help',
			'subtext' => dol_escape_htmltag($langs->transnoentitiesnoconv('PowerPlantPVWidgetInstalledPowerInfo')),
			'subclass' => 'classfortooltip',
		);
	}

	/**
	 * Return the minimum order status used for delivered power statistics.
	 *
	 * @return	int	Order status
	 */
	protected function getDeliveredOrderStatus()
	{
		if (class_exists('Commande')) {
			return (int) Commande::STATUS_CLOSED;
		}

		return 3;
	}

	/**
	 * Check that expected table columns are available.
	 *
	 * @return	bool
	 */
	protected function hasRequiredColumns()
	{
		static $hasColumns = null;

		if ($hasColumns !== null) {
			return $hasColumns;
		}

		$required = array(
			MAIN_DB_PREFIX.'commande' => array('rowid', 'entity', 'fk_statut', 'date_cloture'),
			MAIN_DB_PREFIX.'commande_extrafields' => array('fk_object', 'powerplantpv_peak_power'),
		);
		foreach ($required as $table => $columns) {
			foreach ($columns as $column) {
				$sql = "SHOW COLUMNS FROM ".$table." LIKE '".$this->db->escape($column)."'";
				$resql = $this->db->query($sql);
				if (!$resql || $this->db->num_rows($resql) <= 0) {
					$hasColumns = false;
					return $hasColumns;
				}
			}
		}

		$hasColumns = true;
		return $hasColumns;
	}

	/**
	 * Add the yearly date range filter.
	 *
	 * @param	string	$field	SQL date field
	 * @param	int		$year	Year
	 * @return	string			SQL filter
	 */
	private function getYearDateRangeSql($field, $year)
	{
		$start = $this->db->idate(dol_get_first_day($year, 1, false));
		$nextstart = $this->db->idate(dol_get_first_day($year + 1, 1, false));

		return " AND ".$field." >= '".$start."' AND ".$field." < '".$nextstart."'";
	}

	/**
	 * Return the native thirdparty access join used by customer order statistics.
	 *
	 * @return	string	SQL join
	 */
	private function getOrderAccessJoinSql()
	{
		global $user;

		if (is_object($user) && empty($user->socid) && !$user->hasRight('societe', 'client', 'voir')) {
			return " INNER JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON c.fk_soc = sc.fk_soc AND sc.fk_user = ".((int) $user->id);
		}

		return '';
	}

	/**
	 * Return the native thirdparty access filter used by customer order statistics.
	 *
	 * @return	string	SQL where
	 */
	private function getOrderAccessWhereSql()
	{
		global $user;

		if (is_object($user) && !empty($user->socid)) {
			return " AND c.fk_soc = ".((int) $user->socid);
		}

		return '';
	}

	/**
	 * Fetch total installed peak power for a year.
	 *
	 * @param	int	$year	Year
	 * @return	float		Total kWc
	 */
	protected function fetchTotalYear($year)
	{
		if (!$this->hasRequiredColumns()) {
			return 0.0;
		}

		$sql = "SELECT SUM(COALESCE(ef.powerplantpv_peak_power, 0)) as total";
		$sql .= " FROM ".MAIN_DB_PREFIX."commande as c";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."commande_extrafields as ef ON ef.fk_object = c.rowid";
		$sql .= $this->getOrderAccessJoinSql();
		$sql .= " WHERE c.fk_statut >= ".$this->getDeliveredOrderStatus();
		$sql .= " AND c.entity IN (".getEntity('commande').")";
		$sql .= $this->getOrderAccessWhereSql();
		$sql .= " AND c.date_cloture IS NOT NULL";
		$sql .= $this->getYearDateRangeSql('c.date_cloture', $year);

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			return (float) ($obj->total ?? 0);
		}

		dol_syslog(__METHOD__.' SQL error: '.$this->db->lasterror(), LOG_WARNING);
		return 0.0;
	}

	/**
	 * Fetch installed peak power grouped by month.
	 *
	 * @param	int	$year	Year
	 * @return	array<int,float>
	 */
	protected function fetchByMonth($year)
	{
		return $this->fetchByPeriod('month', $year);
	}

	/**
	 * Fetch installed peak power grouped by ISO week.
	 *
	 * @param	int	$year	Year
	 * @return	array<int,float>
	 */
	protected function fetchByWeek($year)
	{
		return $this->fetchByPeriod('week', $year);
	}

	/**
	 * Fetch installed peak power grouped by period.
	 *
	 * @param	'month'|'week'|string	$period	Period
	 * @param	int						$year	Year
	 * @return	array<int,float>
	 */
	private function fetchByPeriod($period, $year)
	{
		$isMonth = ($period === 'month');
		$maxIndex = ($isMonth ? 12 : 53);
		$result = array_fill(1, $maxIndex, 0.0);

		if (!$this->hasRequiredColumns()) {
			return $result;
		}

		$indexExpression = ($isMonth ? "MONTH(c.date_cloture)" : "WEEK(c.date_cloture, 3)");
		$sql = "SELECT ".$indexExpression." as idx, SUM(COALESCE(ef.powerplantpv_peak_power, 0)) as total";
		$sql .= " FROM ".MAIN_DB_PREFIX."commande as c";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."commande_extrafields as ef ON ef.fk_object = c.rowid";
		$sql .= $this->getOrderAccessJoinSql();
		$sql .= " WHERE c.fk_statut >= ".$this->getDeliveredOrderStatus();
		$sql .= " AND c.entity IN (".getEntity('commande').")";
		$sql .= $this->getOrderAccessWhereSql();
		$sql .= " AND c.date_cloture IS NOT NULL";
		$sql .= $this->getYearDateRangeSql('c.date_cloture', $year);
		$sql .= " GROUP BY idx";

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$idx = (int) $obj->idx;
				if (!$isMonth && $idx === 0) {
					$idx = 53;
				}
				if ($idx >= 1 && $idx <= $maxIndex) {
					$result[$idx] = (float) $obj->total;
				}
			}
		} else {
			dol_syslog(__METHOD__.' SQL error: '.$this->db->lasterror(), LOG_WARNING);
		}

		return $result;
	}
}
