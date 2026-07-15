<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

if (!defined('DOL_DOCUMENT_ROOT')) {
	$mainIncCandidates = array(
		dirname(__DIR__, 3).'/main.inc.php',
		dirname(__DIR__, 2).'/dolibarr/htdocs/main.inc.php',
	);
	$mainInc = '';
	foreach ($mainIncCandidates as $mainIncCandidate) {
		if (is_readable($mainIncCandidate)) {
			$mainInc = $mainIncCandidate;
			break;
		}
	}
	if ($mainInc === '') {
		throw new RuntimeException('Dolibarr parent main.inc.php is required to run PowerPlantPV tests.');
	}
	require_once $mainInc;
}

// Make a sibling module checkout discoverable by dol_include_once().
$moduleParent = dirname(__DIR__, 2);
if (!is_readable(dol_buildpath('/powerplantpv/class/powerplantpvmaintenancescheduler.class.php', 0, 1))
	&& is_readable($moduleParent.'/powerplantpv/class/powerplantpvmaintenancescheduler.class.php')
) {
	$conf->file->dol_document_root['powerplantpv_test'] = $moduleParent;
	$conf->file->dol_url_root['powerplantpv_test'] = '/custom';
}

dol_include_once('/powerplantpv/class/powerplantpvmaintenancescheduler.class.php');
dol_include_once('/powerplantpv/class/powerplantpvmaintenancereminder.class.php');
dol_include_once('/powerplantpv/class/powerplantpvmaintenancewidget.class.php');
dol_include_once('/powerplantpv/class/powerplantpvmaintenancedashboardservice.class.php');
dol_include_once('/powerplantpv/class/powerplantpvmaintenancestatisticsservice.class.php');
dol_include_once('/powerplantpv/class/powerplantpvmaintenancewidgetmanager.class.php');
