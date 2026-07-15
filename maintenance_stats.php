<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file		maintenance_stats.php
 * \ingroup		powerplantpv
 * \brief		Maintenance statistics.
 */

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}
/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_maintenance.lib.php');
dol_include_once('/powerplantpv/class/powerplantpvmaintenancescheduler.class.php');

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

/**
 * Increment a distribution bucket.
 *
 * @param	array<string,array{label:string,count:int}>	$buckets	Buckets
 * @param	string										$key		Bucket key
 * @param	string										$label		Bucket label
 * @return	void
 */
function powerplantpvMaintenanceStatsIncrement(&$buckets, $key, $label)
{
	if (!isset($buckets[$key])) {
		$buckets[$key] = array('label' => $label, 'count' => 0);
	}
	$buckets[$key]['count']++;
}

/**
 * Render one distribution table.
 *
 * @param	string										$title		Title
 * @param	array<string,array{label:string,count:int}>	$buckets	Buckets
 * @return	void
 */
function powerplantpvMaintenanceStatsRenderDistribution($title, $buckets)
{
	global $langs;

	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent liste">';
	print '<tr class="liste_titre"><td>'.$title.'</td><td class="right">'.$langs->trans('Number').'</td></tr>';
	if (empty($buckets)) {
		print '<tr class="oddeven"><td colspan="2"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	} else {
		foreach ($buckets as $bucket) {
			print '<tr class="oddeven">';
			print '<td>'.$bucket['label'].'</td>';
			print '<td class="right">'.((int) $bucket['count']).'</td>';
			print '</tr>';
		}
	}
	print '</table>';
	print '</div>';
}

$scheduler = new PowerPlantPVMaintenanceScheduler($db);
$rows = $scheduler->getMaintenanceRows($user);

$counts = array(
	'to_schedule' => 0,
	'scheduled' => 0,
	'overdue' => 0,
	'covered' => 0,
	'not_required' => 0,
);
$byNature = array();
$byPowerplant = array();
$byClient = array();

foreach ($rows as $row) {
	$status = (string) $row['status'];
	$periodStart = (int) $row['period_start'];
	$periodEnd = (int) $row['period_end'];
	if ($status !== PowerPlantPVMaintenanceScheduler::STATUS_NOT_REQUIRED) {
		if ($periodStart <= 0 || $periodEnd <= 0 || $periodEnd < $dateStart || $periodStart > $dateEnd) {
			continue;
		}
	}

	if ($status === PowerPlantPVMaintenanceScheduler::STATUS_PLANNED || $status === PowerPlantPVMaintenanceScheduler::STATUS_DUE) {
		$counts['to_schedule']++;
	} elseif ($status === PowerPlantPVMaintenanceScheduler::STATUS_SCHEDULED) {
		$counts['scheduled']++;
	} elseif ($status === PowerPlantPVMaintenanceScheduler::STATUS_OVERDUE) {
		$counts['overdue']++;
	} elseif ($status === PowerPlantPVMaintenanceScheduler::STATUS_COVERED) {
		$counts['covered']++;
	} elseif ($status === PowerPlantPVMaintenanceScheduler::STATUS_NOT_REQUIRED) {
		$counts['not_required']++;
	}

	$powerplant = $row['powerplant'];
	powerplantpvMaintenanceStatsIncrement($byPowerplant, 'powerplant_'.((int) $row['powerplant_id']), powerplantpvMaintenancePowerPlantLink($powerplant));
	powerplantpvMaintenanceStatsIncrement($byClient, 'soc_'.((int) $row['fk_soc']), powerplantpvMaintenanceThirdPartyLink((int) $row['fk_soc']));

	$natureLabel = '';
	$displayedIntervention = (!empty($row['covering_intervention']) && is_array($row['covering_intervention'])) ? $row['covering_intervention'] : ((!empty($row['scheduled_intervention']) && is_array($row['scheduled_intervention'])) ? $row['scheduled_intervention'] : null);
	if (is_array($displayedIntervention) && !empty($displayedIntervention['nature_label'])) {
		$natureLabel = dol_escape_htmltag((string) $displayedIntervention['nature_label']);
	} elseif (!empty($row['is_eligible'])) {
		$natureLabel = $langs->trans('PowerPlantPVDefaultPreventiveMaintenanceNature');
	} else {
		$natureLabel = $langs->trans('NotRequired');
	}
	powerplantpvMaintenanceStatsIncrement($byNature, md5($natureLabel), $natureLabel);
}

$form = new Form($db);
$title = $langs->trans('MaintenanceStatistics');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-powerplantpv page-maintenance-stats');

print load_fiche_titre($title, '', 'fa-tools');

print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<div class="liste_titre liste_titre_bydiv centpercent">';
print '<div class="divsearchfield">';
print $langs->trans('Period').' ';
print $form->selectDate($dateStart, 'date_start', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From'));
print ' ';
print $form->selectDate($dateEnd, 'date_end', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to'));
print ' <input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Search')).'">';
print '</div>';
print '</div>';
print '</form>';

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent liste">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('PowerPlantPVMaintenanceSummary').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PowerPlantPVMaintenancesToSchedule').'</td><td class="right">'.((int) $counts['to_schedule']).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PowerPlantPVMaintenanceStatusScheduled').'</td><td class="right">'.((int) $counts['scheduled']).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Overdue').'</td><td class="right">'.((int) $counts['overdue']).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Covered').'</td><td class="right">'.((int) $counts['covered']).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('NotRequired').'</td><td class="right">'.((int) $counts['not_required']).'</td></tr>';
print '</table>';
print '</div>';
print '</div>';
print '<div class="fichehalfright">';
powerplantpvMaintenanceStatsRenderDistribution($langs->trans('PowerPlantPVStatsByNature'), $byNature);
print '</div>';
print '</div>';

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
powerplantpvMaintenanceStatsRenderDistribution($langs->trans('PowerPlantPVStatsByPowerPlant'), $byPowerplant);
print '</div>';
print '<div class="fichehalfright">';
powerplantpvMaintenanceStatsRenderDistribution($langs->trans('PowerPlantPVStatsByCustomer'), $byClient);
print '</div>';
print '</div>';

llxFooter();
$db->close();
