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
 *  \file       powerplant_maintenance.php
 *  \ingroup    powerplantpv
 *  \brief      Maintenance tab on PowerPlant cards
 */

// Load Dolibarr environment
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
 * The main.inc.php has been included so the following variable are now defined:
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */
include_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/contrat/class/contrat.class.php');
dol_include_once('/fichinter/class/fichinter.class.php');
dol_include_once('/powerplantpv/class/powerplant.class.php');
dol_include_once('/powerplantpv/class/powerplantpvmaintenancescheduler.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_maintenance.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_powerplant.lib.php');

// Load translation files required by the page
$langs->loadLangs(array('powerplantpv@powerplantpv', 'other', 'contracts', 'interventions'));

// Get parameters
$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');
$socid = GETPOSTINT('socid');
$backtopage = GETPOST('backtopage', 'alpha');

// Initialize technical objects
$object = new PowerPlant($db);
$extrafields = new ExtraFields($db);
$hookmanager->initHooks(array($object->element.'maintenance', 'globalcard'));
$extrafields->fetch_name_optionals_label($object->table_element);

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

// Permissions
$permissiontoread = powerplantpvUserHasRightPath($user, array('powerplantpv', 'powerplant', 'read'))
	&& powerplantpvUserHasMaintenanceRight($user, 'read');
$permissiontoadd = powerplantpvUserHasRightPath($user, array('powerplantpv', 'powerplant', 'write'))
	&& powerplantpvUserHasMaintenanceRight($user, 'write');
$permissiontocreateintervention = $permissiontoadd
	&& isModEnabled('ficheinter')
	&& powerplantpvUserHasRightPath($user, array('ficheinter', 'creer'));

// Security check
if ($user->socid > 0) {
	$socid = $user->socid;
}
$isdraft = (($object->status == $object::STATUS_DRAFT) ? 1 : 0);
restrictedArea($user, $object->module, $object, $object->table_element, $object->element, 'fk_soc', 'rowid', $isdraft);
if (!isModEnabled('powerplantpv')) {
	accessforbidden();
}
if (!getDolGlobalInt('POWERPLANTPV_MAINTENANCE_ENABLE', 1)) {
	accessforbidden();
}
if (!$permissiontoread || empty($object->id)) {
	accessforbidden();
}

/*
 * Actions
 */

$parameters = array('id' => $id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	powerplantHandleSetLabelAction($object, $action, $permissiontoadd, $user);
	powerplantHandleSetThirdpartyAction($object, $action, $permissiontoadd, $user);

	if ($cancel && !empty($backtopage)) {
		header("Location: ".$backtopage);
		exit;
	}
}

/*
 * View
 */

$form = new Form($db);
$scheduler = new PowerPlantPVMaintenanceScheduler($db);
$schedule = $scheduler->getScheduleForPowerPlant($object, $user);
$items = (isset($schedule['items']) && is_array($schedule['items'])) ? $schedule['items'] : array();
$interventions = (isset($schedule['interventions']) && is_array($schedule['interventions'])) ? $schedule['interventions'] : array();
$summary = (isset($schedule['summary']) && is_array($schedule['summary'])) ? $schedule['summary'] : array();
$summaryStatus = isset($summary['status']) ? (string) $summary['status'] : PowerPlantPVMaintenanceScheduler::STATUS_NOT_REQUIRED;
$summaryPrimaryItem = (!empty($summary['primary_item']) && is_array($summary['primary_item'])) ? $summary['primary_item'] : array();
$coveringInterventionIds = array();
foreach ($items as $item) {
	if (!empty($item['covering_intervention']) && is_array($item['covering_intervention'])) {
		$coveringInterventionIds[(int) $item['covering_intervention']['id']] = 1;
	}
}

$title = $langs->trans('PowerPlant').' - '.$langs->trans('PowerPlantPVMaintenance');
$help_url = '';
llxHeader('', $title, $help_url, '', 0, 0, '', '', '', 'mod-powerplantpv page-card_maintenance');

$head = powerplantPrepareHead($object);

print dol_get_fiche_head($head, 'maintenance', $langs->trans('PowerPlant'), -1, $object->picto);

$linkback = powerplantGetBackToListLink($object, $socid);
$morehtmlref = powerplantBuildBannerMoreHtml($object, $permissiontoadd, $action);

dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';
print '<tr>';
print '<td class="titlefield">'.$langs->trans('PowerPlantPVMaintenanceStatus').'</td>';
print '<td>'.powerplantpvMaintenanceStatusBadge($summaryStatus).'</td>';
print '</tr>';
print '<tr>';
print '<td>'.$langs->trans('PowerPlantPVNextMaintenance').'</td>';
print '<td>';
if (!empty($summaryPrimaryItem)) {
	$primaryContract = $summaryPrimaryItem['contract'];
	print powerplantpvMaintenanceContractLink((int) $primaryContract['id'], (string) $primaryContract['ref']);
	print ' - '.powerplantpvMaintenanceFormatPeriod((int) $summaryPrimaryItem['period_start'], (int) $summaryPrimaryItem['period_end']);
} else {
	print '<span class="opacitymedium">'.$langs->trans('PowerPlantPVNoMaintenanceToPlan').'</span>';
}
print '</td>';
print '</tr>';
print '</table>';
print '</div>';

print dol_get_fiche_end();

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Contract').'</td>';
print '<td>'.$langs->trans('PowerPlantPVMaintenanceActiveServices').'</td>';
print '<td>'.$langs->trans('PowerPlantPVMaintenancePrestations').'</td>';
print '<td>'.$langs->trans('PowerPlantPVMaintenanceRecurrence').'</td>';
print '<td>'.$langs->trans('PowerPlantPVMaintenancePeriod').'</td>';
print '<td>'.$langs->trans('PowerPlantPVLastCoveringIntervention').'</td>';
print '<td class="center">'.$langs->trans('PowerPlantPVMaintenanceStatus').'</td>';
print '<td class="right">'.$langs->trans('Action').'</td>';
print '</tr>';

if (empty($items)) {
	print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('PowerPlantPVNoLinkedMaintenanceContract').'</span></td></tr>';
} else {
	foreach ($items as $item) {
		$contract = $item['contract'];
		$coveringIntervention = (!empty($item['covering_intervention']) && is_array($item['covering_intervention'])) ? $item['covering_intervention'] : null;
		$recurrenceLabels = PowerPlantPVMaintenanceScheduler::getRecurrenceLabelKeys();
		$recurrence = (string) $item['recurrence'];
		$recurrenceLabel = isset($recurrenceLabels[$recurrence]) ? $langs->trans($recurrenceLabels[$recurrence]) : $langs->trans('PowerPlantPVNotConfigured');
		$createAllowed = $permissiontocreateintervention && !empty($item['is_eligible']) && !empty($object->fk_soc);

		print '<tr class="oddeven">';
		print '<td class="nowrap">'.powerplantpvMaintenanceContractLink((int) $contract['id'], (string) $contract['ref']).'</td>';
		print '<td>'.powerplantpvMaintenanceRenderActiveServices($item['active_services']).'</td>';
		print '<td>'.powerplantpvMaintenanceRenderPrestations($item['active_services']).'</td>';
		print '<td>'.dol_escape_htmltag($recurrenceLabel).'</td>';
		print '<td>'.powerplantpvMaintenanceFormatPeriod((int) $item['period_start'], (int) $item['period_end']).'</td>';
		print '<td>';
		if (is_array($coveringIntervention)) {
			print powerplantpvMaintenanceInterventionLink((int) $coveringIntervention['id'], (string) $coveringIntervention['ref']);
		} else {
			print '<span class="opacitymedium">-</span>';
		}
		print '</td>';
		print '<td class="center">'.powerplantpvMaintenanceStatusBadge((string) $item['status']).'</td>';
		print '<td class="right nowrap">';
		if (!empty($item['is_eligible'])) {
			$urlCreate = powerplantpvMaintenanceBuildCreateInterventionUrl($object, $item);
			print dolGetButtonAction($langs->trans('PowerPlantPVCreateMaintenanceInterventionTooltip'), $langs->trans('PowerPlantPVCreateMaintenanceIntervention'), 'default', $urlCreate, '', $createAllowed);
		} else {
			print '<span class="opacitymedium">-</span>';
		}
		print '</td>';
		print '</tr>';
	}
}
print '</table>';
print '</div>';

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<br>';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="6">'.$langs->trans('PowerPlantPVMaintenanceInterventions').'</td></tr>';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Intervention').'</td>';
print '<td>'.$langs->trans('Contract').'</td>';
print '<td>'.$langs->trans('PowerPlantPVMaintenanceNature').'</td>';
print '<td>'.$langs->trans('Date').'</td>';
print '<td class="center">'.$langs->trans('Status').'</td>';
print '<td class="center">'.$langs->trans('PowerPlantPVMaintenanceCoverage').'</td>';
print '</tr>';

if (empty($interventions)) {
	print '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans('PowerPlantPVNoLinkedMaintenanceIntervention').'</span></td></tr>';
} else {
	foreach ($interventions as $intervention) {
		$contractLabels = array();
		if (!empty($intervention['contract_ids']) && is_array($intervention['contract_ids'])) {
			foreach ($intervention['contract_ids'] as $contractId) {
				$contractLabels[] = powerplantpvMaintenanceContractLink((int) $contractId, '#'.((int) $contractId));
			}
		}
		$dateLabel = powerplantpvMaintenanceFormatPeriod((int) $intervention['date_start'], (int) $intervention['date_end']);
		print '<tr class="oddeven">';
		print '<td class="nowrap">'.powerplantpvMaintenanceInterventionLink((int) $intervention['id'], (string) $intervention['ref']).'</td>';
		print '<td>'.(!empty($contractLabels) ? implode('<br>', $contractLabels) : '<span class="opacitymedium">-</span>').'</td>';
		print '<td>'.(!empty($intervention['nature_label']) ? dol_escape_htmltag((string) $intervention['nature_label']) : '<span class="opacitymedium">-</span>').'</td>';
		print '<td>'.$dateLabel.'</td>';
		print '<td class="center">'.powerplantpvMaintenanceInterventionStatus((int) $intervention['id'], (int) $intervention['status']).'</td>';
		print '<td class="center">';
		if (!empty($coveringInterventionIds[(int) $intervention['id']])) {
			print powerplantpvMaintenanceStatusBadge(PowerPlantPVMaintenanceScheduler::STATUS_COVERED);
		} else {
			print '<span class="opacitymedium">-</span>';
		}
		print '</td>';
		print '</tr>';
	}
}

print '</table>';
print '</div>';
print '</div>';
print '<div class="fichehalfright">';
print '<br>';
print '<div class="info">'.$langs->trans('PowerPlantPVMaintenanceKnownLimitNoRolling').'</div>';
print '</div>';
print '</div>';

// End of page
llxFooter();
$db->close();
