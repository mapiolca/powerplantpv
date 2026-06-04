<?php
/* Copyright (C) 2026  Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/powerplantpv_box_installed_power_base.php';

class powerplantpv_graph_installedpower_weekly extends PowerPlantPVInstalledPowerBoxBase
{
	public $boxcode = 'powerplantpv_kwc_week';
	public $boxlabel = 'PowerPlantPVWidgetInstalledPowerWeeklyTitle';

	/**
	 * Load box data.
	 *
	 * @param	int	$max	Max lines
	 * @return	void
	 */
	public function loadBox($max = 5)
	{
		global $conf;

		$this->loadPowerPlantPVLangs();

		$y = (int) dol_print_date(dol_now(), '%Y');
		$dataCurrent = $this->fetchByWeek($y);
		$dataPrevious = $this->fetchByWeek($y - 1);

		$graphData = array();
		$total = 0.0;
		for ($w = 1; $w <= 53; $w++) {
			$v1 = isset($dataCurrent[$w]) ? (float) $dataCurrent[$w] : 0.0;
			$v0 = isset($dataPrevious[$w]) ? (float) $dataPrevious[$w] : 0.0;
			$total += $v1 + $v0;
			$graphData[] = array((string) $w, $v1, $v0);
		}

		if ($total <= 0) {
			$contentHtml = $this->buildNoDataMessageHtml(array($y, $y - 1));
		} else {
			$graph = new DolGraph();
			$graph->SetData($graphData);
			$graph->SetLegend(array((string) $y.' (kWc)', (string) ($y - 1).' (kWc)'));
			$graph->SetDataColor(array('#2e78c2', '#a3a3a3'));
			$graph->SetType(array('lines'));
			$graph->setHeight(!empty($conf->dol_optimize_smallscreen) ? '220' : '260');
			$graph->setWidth(!empty($conf->dol_optimize_smallscreen) ? '320' : '680');
			$graph->setShowLegend(1);
			$graph->setMinValue(0);
			$graph->draw('powerplantpvkwcweekly_e'.((int) $conf->entity));
			$contentHtml = '<div class="center">'.$graph->show(0).'</div>';
		}

		$this->info_box_head = $this->buildHeader('PowerPlantPVWidgetInstalledPowerWeeklyTitle');
		$this->info_box_contents = array(
			array(0 => array('td' => 'class="center"', 'asis' => 1, 'text' => $contentHtml)),
		);
	}
}
