<?php
/* Copyright (C) 2026  Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/powerplantpv_box_installed_power_base.php';

class powerplantpv_graph_installedpower_totalyear extends PowerPlantPVInstalledPowerBoxBase
{
	public $boxcode = 'powerplantpv_kwc_year';
	public $boxlabel = 'PowerPlantPVWidgetInstalledPowerTotalTitle';

	/**
	 * Load box data.
	 *
	 * @param	int	$max	Max lines
	 * @return	void
	 */
	public function loadBox($max = 5)
	{
		$this->loadPowerPlantPVLangs();

		$yearCurrent = (int) dol_print_date(dol_now(), '%Y');
		$totalCurrentYear = $this->fetchTotalYear($yearCurrent);
		$valueText = dol_escape_htmltag(rtrim(rtrim(sprintf('%.2f', $totalCurrentYear), '0'), '.'));
		$diagnosticHtml = '';
		if ($totalCurrentYear <= 0) {
			$diagnosticHtml = $this->buildNoDataMessageHtml(array($yearCurrent), 'font-size:12px;font-weight:400;margin-top:6px;max-width:90%;');
		}

		$this->info_box_head = $this->buildHeader('PowerPlantPVWidgetInstalledPowerTotalTitle');
		$this->info_box_contents = array(
			array(
				0 => array(
					'td' => 'class="center"',
					'asis' => 1,
					'text' => '<div style="height:100px;display:flex;flex-direction:column;align-items:center;justify-content:center;"><div style="font-size:42px;font-weight:700;">'.$valueText.' kWc</div>'.$diagnosticHtml.'</div>',
				),
			),
		);
	}
}
