<?php
/* Copyright (C) 2026  Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/powerplantpv_box_installed_power_base.php';

class powerplantpv_graph_installedpower_monthly extends PowerPlantPVInstalledPowerBoxBase
{
	public $boxcode = 'powerplantpv_kwc_month';
	public $boxlabel = 'PowerPlantPVWidgetInstalledPowerMonthlyTitle';

	/**
	 * Load box data.
	 *
	 * @param	int	$max	Max lines
	 * @return	void
	 */
	public function loadBox($max = 5)
	{
		global $conf, $langs;

		$this->loadPowerPlantPVLangs();

		$y = (int) dol_print_date(dol_now(), '%Y');
		$dataCurrent = $this->fetchByMonth($y);
		$dataPrevious = $this->fetchByMonth($y - 1);

		$graphData = array();
		$total = 0.0;
		for ($m = 1; $m <= 12; $m++) {
			$v1 = isset($dataCurrent[$m]) ? (float) $dataCurrent[$m] : 0.0;
			$v0 = isset($dataPrevious[$m]) ? (float) $dataPrevious[$m] : 0.0;
			$total += $v1 + $v0;
			$monthLabel = dol_print_date(dol_mktime(0, 0, 0, $m, 1, 2000), '%b');
			$graphData[] = array($monthLabel, $v1, $v0);
		}

		if ($total <= 0) {
			$contentHtml = '<div class="center opacitymedium">'.$langs->trans('PowerPlantPVWidgetNoData').'</div>';
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
			$graph->draw('powerplantpvkwcmonthly_e'.((int) $conf->entity));
			$contentHtml = '<div class="center">'.$graph->show(0).'</div>';
		}

		$this->info_box_head = $this->buildHeader('PowerPlantPVWidgetInstalledPowerMonthlyTitle');
		$this->info_box_contents = array(
			array(0 => array('td' => 'class="center"', 'asis' => 1, 'text' => $contentHtml)),
		);
	}
}
