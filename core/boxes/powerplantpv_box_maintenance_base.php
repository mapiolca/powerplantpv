<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/class/powerplantpvmaintenancewidget.class.php');
dol_include_once('/powerplantpv/class/powerplantpvmaintenancedashboardservice.class.php');

/**
 * Shared native home box for a maintenance catalog widget.
 */
abstract class PowerPlantPVMaintenanceBoxBase extends ModeleBoxes
{
	/** @var string */
	protected $widgetCode = '';

	public $boximg = 'tools';
	public $depends = array('powerplantpv');
	public $lang = 'powerplantpv@powerplantpv';
	public $version = 'dolibarr';

	/**
	 * @param DoliDB $db Database handler
	 * @param string $param Box parameters
	 */
	public function __construct($db, $param = '')
	{
		global $user;

		parent::__construct($db, $param);
		$this->db = $db;
		$this->param = $param;
		if (!getDolGlobalInt('POWERPLANTPV_MAINTENANCE_ENABLE', 1)
			|| !is_object($user)
			|| !powerplantpvUserHasMaintenanceRight($user, 'read')
			|| !powerplantpvUserHasRightPath($user, array('powerplantpv', 'powerplant', 'read'))
		) {
			$this->hidden = true;
		}
	}

	/**
	 * @param array<string,mixed>|null $head Head
	 * @param array<int,mixed>|null $contents Contents
	 * @param int<0,1> $nooutput No output
	 * @return string
	 */
	public function showBox($head = null, $contents = null, $nooutput = 0)
	{
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
	}

	/**
	 * Load shared widget data.
	 *
	 * @param int $max Maximum rows
	 * @return void
	 */
	public function loadBox($max = 5)
	{
		global $langs, $user;

		if (!empty($this->hidden)
			|| !is_object($user)
			|| !powerplantpvUserHasMaintenanceRight($user, 'read')
			|| !powerplantpvUserHasRightPath($user, array('powerplantpv', 'powerplant', 'read'))
		) {
			$this->hidden = true;
			return;
		}
		$langs->loadLangs(array('powerplantpv@powerplantpv'));
		$catalog = PowerPlantPVMaintenanceWidget::getCatalog();
		if (!isset($catalog[$this->widgetCode]) || !PowerPlantPVMaintenanceWidget::isAvailable($this->widgetCode, 'home')) {
			$this->hidden = true;
			return;
		}
		$range = $this->getHomeRange($catalog[$this->widgetCode]['home_period']);
		$service = new PowerPlantPVMaintenanceDashboardService($this->db);
		$data = $service->getDashboard($user, $range['start'], $range['end'], $range['reference']);
		$this->info_box_head = array(
			'text' => $langs->trans($catalog[$this->widgetCode]['label']),
			'nbcol' => 2,
			'limit' => 0,
			'subpicto' => 'help',
			'subtext' => dol_escape_htmltag($langs->trans($catalog[$this->widgetCode]['help'])),
			'subclass' => 'classfortooltip',
		);
		$this->info_box_contents = array(array(0 => array(
			'td' => 'class="nohover"',
			'asis' => 1,
			'text' => PowerPlantPVMaintenanceWidget::renderBoxContents($this->widgetCode, $data),
		)));
	}

	/**
	 * @param string $periodCode Catalog period code
	 * @return array{start:int,end:int,reference:int}
	 */
	private function getHomeRange($periodCode)
	{
		$now = dol_now();
		$year = (int) date('Y', $now);
		$reference = dol_mktime(0, 0, 0, (int) date('m', $now), (int) date('d', $now), $year);
		if ($periodCode === 'all_active_overdue') {
			return array('start' => 1, 'end' => dol_mktime(23, 59, 59, 12, 31, $year + 1), 'reference' => $reference);
		}
		if ($periodCode === 'rolling_90_days') {
			$start = $reference;
			return array('start' => $start, 'end' => $start + (90 * 86400) + 86399, 'reference' => $reference);
		}
		if ($periodCode === 'rolling_12_months') {
			$start = dol_mktime(0, 0, 0, (int) date('m', $now), 1, $year);
			$end = dol_mktime(0, 0, 0, (int) date('m', $now) + 12, 1, $year) - 1;
			return array('start' => $start, 'end' => $end, 'reference' => $reference);
		}
		return array('start' => dol_mktime(0, 0, 0, 1, 1, $year), 'end' => dol_mktime(23, 59, 59, 12, 31, $year), 'reference' => $reference);
	}
}
