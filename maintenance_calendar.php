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
 * \file		maintenance_calendar.php
 * \ingroup		powerplantpv
 * \brief		Simple monthly maintenance calendar.
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_maintenance.lib.php');
dol_include_once('/powerplantpv/class/powerplantpvmaintenancescheduler.class.php');

$langs->loadLangs(array('powerplantpv@powerplantpv', 'other', 'contracts', 'interventions'));

if (!isModEnabled('powerplantpv') || !getDolGlobalInt('POWERPLANTPV_MAINTENANCE_ENABLE', 1)) {
	accessforbidden();
}
if (!powerplantpvUserHasMaintenanceRight($user, 'read') || !powerplantpvUserHasRightPath($user, array('powerplantpv', 'powerplant', 'read'))) {
	accessforbidden();
}

$year = GETPOSTINT('year');
$month = GETPOSTINT('month');
if ($year <= 0) {
	$year = (int) date('Y');
}
if ($month <= 0 || $month > 12) {
	$month = (int) date('m');
}

$prevYear = $year;
$prevMonth = $month - 1;
if ($prevMonth < 1) {
	$prevMonth = 12;
	$prevYear--;
}
$nextYear = $year;
$nextMonth = $month + 1;
if ($nextMonth > 12) {
	$nextMonth = 1;
	$nextYear++;
}
$lastDayOfMonth = (int) date('t', mktime(12, 0, 0, $month, 1, $year));

$monthStart = dol_mktime(0, 0, 0, $month, 1, $year);
$monthEnd = dol_mktime(23, 59, 59, $month, $lastDayOfMonth, $year);

$scheduler = new PowerPlantPVMaintenanceScheduler($db);
$allRows = $scheduler->getMaintenanceRows($user);
$calendarRows = array();
foreach ($allRows as $row) {
	$status = (string) $row['status'];
	if (!in_array($status, array(PowerPlantPVMaintenanceScheduler::STATUS_PLANNED, PowerPlantPVMaintenanceScheduler::STATUS_SCHEDULED, PowerPlantPVMaintenanceScheduler::STATUS_DUE, PowerPlantPVMaintenanceScheduler::STATUS_OVERDUE), true)) {
		continue;
	}
	$periodStart = (int) $row['period_start'];
	$periodEnd = (int) $row['period_end'];
	if ($periodStart <= 0 || $periodEnd <= 0) {
		continue;
	}
	if ($status === PowerPlantPVMaintenanceScheduler::STATUS_OVERDUE) {
		if ($periodEnd > $monthEnd) {
			continue;
		}
	} elseif ($periodEnd < $monthStart || $periodStart > $monthEnd) {
		continue;
	}
	$calendarRows[] = $row;
}

$createAllowed = powerplantpvMaintenanceCanCreateIntervention($user);
$title = $langs->trans('MaintenanceCalendar');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-powerplantpv page-maintenance-calendar');

$nav = dolGetButtonTitle($langs->trans('Previous'), '', 'fa fa-chevron-left', $_SERVER['PHP_SELF'].'?year='.(int) $prevYear.'&month='.(int) $prevMonth, '', 1);
$nav .= dolGetButtonTitle($langs->trans('CurrentMonth'), '', 'fa fa-calendar', $_SERVER['PHP_SELF'], '', 1);
$nav .= dolGetButtonTitle($langs->trans('Next'), '', 'fa fa-chevron-right', $_SERVER['PHP_SELF'].'?year='.(int) $nextYear.'&month='.(int) $nextMonth, '', 1);
print_barre_liste($title.' - '.dol_print_date($monthStart, '%B %Y'), 0, $_SERVER['PHP_SELF'], '&year='.$year.'&month='.$month, '', '', '', count($calendarRows), count($calendarRows), 'fa-tools', 0, $nav, '', 0);

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent liste">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Date').'</td>';
print '<td>'.$langs->trans('PowerPlant').'</td>';
print '<td>'.$langs->trans('Contract').'</td>';
print '<td>'.$langs->trans('PowerPlantPVMaintenancePeriod').'</td>';
print '<td class="center">'.$langs->trans('MaintenanceStatus').'</td>';
print '<td class="right">'.$langs->trans('Action').'</td>';
print '</tr>';

if (empty($calendarRows)) {
	print '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
} else {
	foreach ($calendarRows as $row) {
		$powerplant = $row['powerplant'];
		$contract = (!empty($row['contract']) && is_array($row['contract'])) ? $row['contract'] : array();
		$dateReference = ((string) $row['status'] === PowerPlantPVMaintenanceScheduler::STATUS_OVERDUE && (int) $row['period_end'] < $monthStart) ? $monthStart : (int) $row['period_start'];

		print '<tr class="oddeven">';
		print '<td class="nowrap">'.dol_print_date($dateReference, 'day').'</td>';
		print '<td class="nowrap">'.powerplantpvMaintenancePowerPlantLink($powerplant).'</td>';
		print '<td class="nowrap">'.powerplantpvMaintenanceContractLink((int) $row['contract_id'], !empty($contract['ref']) ? (string) $contract['ref'] : '').'</td>';
		print '<td>'.powerplantpvMaintenanceFormatPeriod((int) $row['period_start'], (int) $row['period_end']).'</td>';
		print '<td class="center">'.powerplantpvMaintenanceStatusBadge((string) $row['status']).'</td>';
		print '<td class="right nowrap">';
		if (!empty($row['is_eligible']) && powerplantpvMaintenanceStatusAllowsCreation((string) $row['status'])) {
			$urlCreate = powerplantpvMaintenanceBuildCreateInterventionUrl($powerplant, $row, dol_buildpath('/powerplantpv/maintenance_calendar.php', 1).'?year='.$year.'&month='.$month);
			print dolGetButtonAction($langs->trans('PowerPlantPVCreateMaintenanceInterventionTooltip'), $langs->trans('Create'), 'default', $urlCreate, '', ($createAllowed && !empty($row['fk_soc'])));
		} else {
			print '<span class="opacitymedium">-</span>';
		}
		print '</td>';
		print '</tr>';
	}
}

print '</table>';
print '</div>';

llxFooter();
$db->close();
