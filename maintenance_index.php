<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file maintenance_index.php
 * \ingroup powerplantpv
 * \brief Personalized maintenance operational dashboard.
 */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] === $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, $i + 1).'/main.inc.php')) {
	$res = @include substr($tmp, 0, $i + 1).'/main.inc.php';
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, $i + 1)).'/main.inc.php')) {
	$res = @include dirname(substr($tmp, 0, $i + 1)).'/main.inc.php';
}
if (!$res && file_exists('../main.inc.php')) {
	$res = @include '../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
	$res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = @include '../../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/class/powerplantpvmaintenancewidget.class.php');
dol_include_once('/powerplantpv/class/powerplantpvmaintenancedashboardservice.class.php');
dol_include_once('/powerplantpv/class/powerplantpvmaintenancewidgetmanager.class.php');

$langs->loadLangs(array('powerplantpv@powerplantpv', 'other', 'companies', 'contracts', 'interventions'));

if (!isModEnabled('powerplantpv') || !getDolGlobalInt('POWERPLANTPV_MAINTENANCE_ENABLE', 1)) {
	accessforbidden();
}
if (!powerplantpvUserHasMaintenanceRight($user, 'read') || !powerplantpvUserHasRightPath($user, array('powerplantpv', 'powerplant', 'read'))) {
	accessforbidden();
}

$defaultStart = dol_mktime(0, 0, 0, 1, 1, (int) date('Y'));
$defaultEnd = dol_mktime(23, 59, 59, 12, 31, (int) date('Y'));
$dateStart = dol_mktime(0, 0, 0, GETPOSTINT('date_startmonth'), GETPOSTINT('date_startday'), GETPOSTINT('date_startyear'));
$dateEnd = dol_mktime(23, 59, 59, GETPOSTINT('date_endmonth'), GETPOSTINT('date_endday'), GETPOSTINT('date_endyear'));
if ($dateStart <= 0) {
	$dateStart = $defaultStart;
}
if ($dateEnd <= 0) {
	$dateEnd = $defaultEnd;
}
if ($dateEnd < $dateStart) {
	$tmpDate = $dateStart;
	$dateStart = $dateEnd;
	$dateEnd = $tmpDate;
}

$form = new Form($db);
$service = new PowerPlantPVMaintenanceDashboardService($db);
$manager = new PowerPlantPVMaintenanceWidgetManager($db);
$dashboard = $service->getDashboard($user, $dateStart, $dateEnd);
$layout = $manager->getLayout((int) $user->id, (int) $conf->entity);
$catalog = PowerPlantPVMaintenanceWidget::getCatalog();
$visibleCodes = array();
foreach ($layout as $item) {
	$visibleCodes[$item['code']] = true;
}
$addOptions = array();
foreach ($catalog as $code => $definition) {
	if (!empty($definition['stats']) && !isset($visibleCodes[$code])) {
		$addOptions[$code] = $langs->trans($definition['label']);
	}
}

$titleKey = isset($powerplantpvMaintenanceDashboardTitleKey) ? (string) $powerplantpvMaintenanceDashboardTitleKey : 'PowerPlantPVMaintenanceDashboard';
$title = $langs->trans($titleKey);
$js = array('/powerplantpv/js/powerplantpv_maintenance_dashboard.js');
llxHeader('', $title, '', '', 0, 0, $js, array(), '', 'mod-powerplantpv page-maintenance-dashboard');

print load_fiche_titre($title, '', 'fa-tools');

print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<div class="divsearchfield marginbottomonly">';
print $langs->trans('Period').' ';
print $form->selectDate($dateStart, 'date_start', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From'));
print ' ';
print $form->selectDate($dateEnd, 'date_end', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to'));
print ' <input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Search')).'">';
print '</div>';
print '</form>';

print '<div id="powerplantpv-maintenance-dashboard" data-save-url="'.dol_buildpath('/powerplantpv/ajax/maintenance_widgets.php', 1).'" data-token="'.newToken().'">';
print '<div class="powerplantpv-maintenance-widget-toolbar marginbottomonly">';
print '<span class="opacitymedium">'.$langs->trans('PowerPlantPVMaintenanceCustomizeDashboard').'</span> ';
print $form->selectarray('powerplantpv_maintenance_widget_select', $addOptions, '', 1, 0, 0, '', 0, 0, 0, '', 'minwidth300');
print ' '.dolGetButtonTitle($langs->trans('PowerPlantPVMaintenanceAddWidget'), '', 'fa fa-plus-circle', '#', 'powerplantpv-maintenance-widget-add', 1);
print ' '.dolGetButtonTitle($langs->trans('PowerPlantPVMaintenanceResetLayout'), '', 'fa fa-undo', '#', 'powerplantpv-maintenance-widget-reset', 1);
print '<span id="powerplantpv-maintenance-widget-message" class="marginleftonly"></span>';
print '</div>';

$periodHelp = $langs->trans('PowerPlantPVMaintenancePeriodHelp', dol_print_date($dateStart, 'day'), dol_print_date($dateEnd, 'day'));
print '<div class="powerplantpv-maintenance-widget-columns">';
for ($column = 0; $column <= 1; $column++) {
	print '<div class="powerplantpv-maintenance-widget-column" data-column="'.$column.'">';
	foreach ($layout as $item) {
		if ((int) $item['column'] !== $column || !isset($catalog[$item['code']])) {
			continue;
		}
		$definition = $catalog[$item['code']];
		print '<section class="powerplantpv-maintenance-widget-card" draggable="true" data-widget-code="'.dol_escape_htmltag($item['code']).'">';
		print '<div class="liste_titre powerplantpv-maintenance-widget-title">';
		print '<span class="fa fa-grip-vertical powerplantpv-maintenance-widget-handle" aria-hidden="true"></span> ';
		print '<span class="classfortooltip" title="'.dol_escape_htmltag($periodHelp).'">'.$langs->trans($definition['label']).'</span>';
		print '<a href="#" class="powerplantpv-maintenance-widget-remove" title="'.dol_escape_htmltag($langs->trans('Remove')).'">'.img_delete().'</a>';
		print '</div>';
		print '<div class="powerplantpv-maintenance-widget-content">'.PowerPlantPVMaintenanceWidget::renderContent($item['code'], $dashboard).'</div>';
		print '</section>';
	}
	print '</div>';
}
print '</div>';

print '<div id="powerplantpv-maintenance-widget-templates" class="hidden">';
foreach ($catalog as $code => $definition) {
	print '<div class="powerplantpv-maintenance-widget-template" data-widget-code="'.dol_escape_htmltag($code).'">';
	print '<section class="powerplantpv-maintenance-widget-card" draggable="true" data-widget-code="'.dol_escape_htmltag($code).'">';
	print '<div class="liste_titre powerplantpv-maintenance-widget-title"><span class="fa fa-grip-vertical powerplantpv-maintenance-widget-handle"></span> ';
	print '<span class="classfortooltip" title="'.dol_escape_htmltag($periodHelp).'">'.$langs->trans($definition['label']).'</span>';
	print '<a href="#" class="powerplantpv-maintenance-widget-remove" title="'.dol_escape_htmltag($langs->trans('Remove')).'">'.img_delete().'</a></div>';
	print '<div class="powerplantpv-maintenance-widget-content">'.PowerPlantPVMaintenanceWidget::renderContent($code, $dashboard).'</div>';
	print '</section></div>';
}
print '</div>';
print '</div>';

llxFooter();
$db->close();
