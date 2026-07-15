<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file maintenance_stats.php
 * \ingroup powerplantpv
 * \brief Multi-year maintenance statistics comparison.
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
require_once DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php';
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/class/powerplantpvmaintenancestatisticsservice.class.php');

$langs->loadLangs(array('powerplantpv@powerplantpv', 'other', 'companies', 'interventions'));

if (!isModEnabled('powerplantpv') || !getDolGlobalInt('POWERPLANTPV_MAINTENANCE_ENABLE', 1)) {
	accessforbidden();
}
if (!powerplantpvUserHasMaintenanceRight($user, 'read') || !powerplantpvUserHasRightPath($user, array('powerplantpv', 'powerplant', 'read'))) {
	accessforbidden();
}

$currentYear = (int) dol_print_date(dol_now(), '%Y');
$baseYear = GETPOSTINT('year');
if ($baseYear < 2000 || $baseYear > $currentYear) {
	$baseYear = $currentYear;
}
$yearCount = GETPOSTINT('year_count');
if ($yearCount !== 2 && $yearCount !== 3) {
	$yearCount = 3;
}

$form = new Form($db);
$statisticsService = new PowerPlantPVMaintenanceStatisticsService($db);
$comparison = $statisticsService->getComparison($user, $baseYear, $yearCount);
$years = is_array($comparison['years']) ? $comparison['years'] : array();
$annual = is_array($comparison['annual']) ? $comparison['annual'] : array();
$monthly = is_array($comparison['monthly']) ? $comparison['monthly'] : array();
$distributions = is_array($comparison['distributions']) ? $comparison['distributions'] : array();

/**
 * Render a native annual comparison table.
 *
 * @param array<int,array<string,int|float>> $annual Annual values
 * @param array<int,int> $years Compared years
 * @param Translate $langs Language handler
 * @return string
 */
function powerplantpvMaintenanceStatsAnnualTable(array $annual, array $years, $langs)
{
	$rows = array(
		'total' => 'PowerPlantPVMaintenanceStatisticsTotalInterventions',
		'completed' => 'PowerPlantPVMaintenanceStatisticsCompletedInterventions',
		'open' => 'PowerPlantPVMaintenanceStatisticsOpenInterventions',
		'completion_rate' => 'PowerPlantPVMaintenanceStatisticsCompletionRate',
	);
	$out = '<div class="div-table-responsive-no-min">';
	$out .= '<table class="noborder centpercent">';
	$out .= '<tr class="liste_titre"><th>'.$langs->trans('PowerPlantPVMaintenanceStatisticsAnnualComparison').'</th>';
	foreach ($years as $year) {
		$out .= '<th class="right">'.((int) $year).'</th>';
	}
	$out .= '</tr>';
	foreach ($rows as $key => $labelKey) {
		$out .= '<tr class="oddeven"><td>'.$langs->trans($labelKey).'</td>';
		foreach ($years as $year) {
			$value = isset($annual[$year][$key]) ? $annual[$year][$key] : 0;
			$out .= '<td class="right">'.($key === 'completion_rate' ? vatrate((float) $value).' %' : ((int) $value)).'</td>';
		}
		$out .= '</tr>';
	}
	$out .= '</table></div>';

	return $out;
}

/**
 * Render a DolGraph in a native table block.
 *
 * @param string $title Graph title
 * @param array<int,array<int,int|string>> $data Graph data
 * @param array<int,string> $legend Series legend
 * @param string $type Graph type
 * @param string $graphId Graph identifier
 * @param Conf $conf Configuration
 * @param Translate $langs Language handler
 * @param string $help Translated graph explanation
 * @return string
 */
function powerplantpvMaintenanceStatsGraph($title, array $data, array $legend, $type, $graphId, $conf, $langs, $help = '')
{
	$total = 0;
	foreach ($data as $line) {
		foreach (array_slice($line, 1) as $value) {
			$total += (int) $value;
		}
	}
	$out = '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	$out .= '<tr class="liste_titre"><th>'.$title;
	if ($help !== '') {
		$out .= '<span class="floatright">'.img_picto($help, 'help', 'class="classfortooltip opacitymedium"').'</span>';
	}
	$out .= '</th></tr><tr><td class="center">';
	if ($total <= 0) {
		$out .= '<span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span>';
	} else {
		$graph = new DolGraph();
		$graph->SetData($data);
		$graph->SetLegend($legend);
		$graph->SetDataColor(array('#2e78c2', '#7b61a8', '#a3a3a3'));
		$graph->SetType(array($type));
		$graph->setHeight(!empty($conf->dol_optimize_smallscreen) ? '230' : '300');
		$graph->setWidth(!empty($conf->dol_optimize_smallscreen) ? '320' : '680');
		$graph->setShowLegend(1);
		$graph->setMinValue(0);
		$graph->draw($graphId);
		$out .= $graph->show(0);
	}
	$out .= '</td></tr></table></div>';

	return $out;
}

/**
 * Render a comparative native distribution table.
 *
 * @param string $title Table title
 * @param array<int,array<string,mixed>> $rows Distribution rows
 * @param array<int,int> $years Compared years
 * @param Translate $langs Language handler
 * @return string
 */
function powerplantpvMaintenanceStatsDistributionTable($title, array $rows, array $years, $langs)
{
	$out = '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	$out .= '<tr class="liste_titre"><th>'.$title.'</th>';
	foreach ($years as $year) {
		$out .= '<th class="right">'.((int) $year).'</th>';
	}
	$out .= '<th class="right">'.$langs->trans('Total').'</th></tr>';
	if (empty($rows)) {
		$out .= '<tr class="oddeven"><td colspan="'.(count($years) + 2).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	} else {
		foreach ($rows as $row) {
			$label = dol_escape_htmltag((string) $row['label']);
			if (!empty($row['url'])) {
				$label = '<a href="'.dol_escape_htmltag((string) $row['url']).'">'.$label.'</a>';
			}
			$out .= '<tr class="oddeven"><td>'.$label.'</td>';
			foreach ($years as $year) {
				$out .= '<td class="right">'.(!empty($row['years'][$year]) ? ((int) $row['years'][$year]) : 0).'</td>';
			}
			$out .= '<td class="right">'.((int) $row['total']).'</td></tr>';
		}
	}
	$out .= '</table></div>';

	return $out;
}

$yearOptions = array();
for ($year = $currentYear; $year >= $currentYear - 10; $year--) {
	$yearOptions[$year] = (string) $year;
}
if (!isset($yearOptions[$baseYear])) {
	$yearOptions[$baseYear] = (string) $baseYear;
	krsort($yearOptions, SORT_NUMERIC);
}
$yearCountOptions = array(
	2 => $langs->trans('PowerPlantPVMaintenanceStatisticsTwoYears'),
	3 => $langs->trans('PowerPlantPVMaintenanceStatisticsThreeYears'),
);

$legend = array_map('strval', $years);
$monthlyTotalData = array();
$monthlyCompletedData = array();
for ($month = 1; $month <= 12; $month++) {
	$monthLabel = dol_print_date(dol_mktime(0, 0, 0, $month, 1, 2000), '%b');
	$totalLine = array($monthLabel);
	$completedLine = array($monthLabel);
	foreach ($years as $year) {
		$totalLine[] = !empty($monthly['total'][$year][$month]) ? (int) $monthly['total'][$year][$month] : 0;
		$completedLine[] = !empty($monthly['completed'][$year][$month]) ? (int) $monthly['completed'][$year][$month] : 0;
	}
	$monthlyTotalData[] = $totalLine;
	$monthlyCompletedData[] = $completedLine;
}

$natureRows = !empty($distributions['nature']) && is_array($distributions['nature']) ? $distributions['nature'] : array();
$natureTopRows = array_slice($natureRows, 0, 10);
if (count($natureRows) > 10) {
	$otherRow = array('label' => $langs->trans('PowerPlantPVMaintenanceStatisticsOther'), 'years' => array(), 'total' => 0, 'url' => '');
	foreach (array_slice($natureRows, 10) as $natureRow) {
		foreach ($years as $year) {
			if (!isset($otherRow['years'][$year])) {
				$otherRow['years'][$year] = 0;
			}
			$otherRow['years'][$year] += !empty($natureRow['years'][$year]) ? (int) $natureRow['years'][$year] : 0;
		}
		$otherRow['total'] += (int) $natureRow['total'];
	}
	$natureTopRows[] = $otherRow;
}
$natureGraphData = array();
foreach ($natureTopRows as $natureRow) {
	$line = array((string) $natureRow['label']);
	foreach ($years as $year) {
		$line[] = !empty($natureRow['years'][$year]) ? (int) $natureRow['years'][$year] : 0;
	}
	$natureGraphData[] = $line;
}

$graphSuffix = '_e'.((int) $conf->entity).'_u'.((int) $user->id).'_y'.$baseYear.'_n'.$yearCount;
$title = $langs->trans('MaintenanceStatistics');
llxHeader('', $title, '', '', 0, 0, array(), array(), '', 'mod-powerplantpv page-maintenance-statistics');

print load_fiche_titre($title, '', 'fa-chart-line');

print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<div class="divsearchfield marginbottomonly">';
print '<span class="nowrap">'.$langs->trans('PowerPlantPVMaintenanceStatisticsBaseYear').' ';
print $form->selectarray('year', $yearOptions, $baseYear, 0, 0, 0, '', 0, 0, 0, '', 'minwidth100');
print '</span> ';
print '<span class="nowrap">'.$langs->trans('PowerPlantPVMaintenanceStatisticsYears').' ';
print $form->selectarray('year_count', $yearCountOptions, $yearCount, 0, 0, 0, '', 0, 0, 0, '', 'minwidth150');
print '</span> ';
if (!empty($conf->use_javascript_ajax)) {
	print ajax_combobox('year');
	print ajax_combobox('year_count');
}
print '<input type="submit" class="button" value="'.$langs->trans('Search').'">';
print '</div>';
print '</form>';

print '<div class="opacitymedium marginbottomonly">';
print $langs->trans('PowerPlantPVMaintenanceStatisticsDateHelp').' ';
print $langs->trans('PowerPlantPVMaintenanceStatisticsCurrentStatusHelp');
print '</div>';

print powerplantpvMaintenanceStatsAnnualTable($annual, $years, $langs);
print '<br>';

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print powerplantpvMaintenanceStatsGraph(
	$langs->trans('PowerPlantPVMaintenanceStatisticsMonthlyVolume'),
	$monthlyTotalData,
	$legend,
	'lines',
	'powerplantpv_maintenance_monthly_total'.$graphSuffix,
	$conf,
	$langs,
	$langs->trans('PowerPlantPVMaintenanceStatisticsMonthlyVolumeHelp')
);
print '</div>';
print '<div class="fichehalfright">';
print powerplantpvMaintenanceStatsGraph(
	$langs->trans('PowerPlantPVMaintenanceStatisticsMonthlyCompleted'),
	$monthlyCompletedData,
	$legend,
	'lines',
	'powerplantpv_maintenance_monthly_completed'.$graphSuffix,
	$conf,
	$langs,
	$langs->trans('PowerPlantPVMaintenanceStatisticsMonthlyCompletedHelp')
);
print '</div>';
print '</div>';
print '<div class="clearboth"></div><br>';

print powerplantpvMaintenanceStatsGraph(
	$langs->trans('PowerPlantPVMaintenanceStatisticsNatureComparison'),
	$natureGraphData,
	$legend,
	'bars',
	'powerplantpv_maintenance_nature'.$graphSuffix,
	$conf,
	$langs
);
print '<br>';

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print powerplantpvMaintenanceStatsDistributionTable(
	$langs->trans('PowerPlantPVStatsByPowerPlant'),
	!empty($distributions['powerplant']) && is_array($distributions['powerplant']) ? $distributions['powerplant'] : array(),
	$years,
	$langs
);
print '</div>';
print '<div class="fichehalfright">';
print powerplantpvMaintenanceStatsDistributionTable(
	$langs->trans('PowerPlantPVStatsByCustomer'),
	!empty($distributions['customer']) && is_array($distributions['customer']) ? $distributions['customer'] : array(),
	$years,
	$langs
);
print '</div>';
print '</div>';
print '<div class="clearboth"></div>';

llxFooter();
$db->close();
