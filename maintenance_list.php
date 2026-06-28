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
 * \file		maintenance_list.php
 * \ingroup		powerplantpv
 * \brief		Global calculated maintenance list.
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

$action = GETPOST('action', 'aZ09');
$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$page = 0;
}
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

$searchPowerplant = GETPOSTINT('search_fk_powerplant');
$searchSocid = GETPOSTINT('search_fk_soc');
$searchStatus = GETPOST('search_status', 'alphanohtml');
$searchNature = GETPOSTINT('search_intervention_nature');
$searchService = GETPOSTINT('search_maintenance_service');
$searchDateStart = dol_mktime(0, 0, 0, GETPOSTINT('search_date_startmonth'), GETPOSTINT('search_date_startday'), GETPOSTINT('search_date_startyear'));
$searchDateEnd = dol_mktime(23, 59, 59, GETPOSTINT('search_date_endmonth'), GETPOSTINT('search_date_endday'), GETPOSTINT('search_date_endyear'));

if (GETPOST('button_removefilter', 'alpha') || GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha')) {
	$searchPowerplant = 0;
	$searchSocid = 0;
	$searchStatus = '';
	$searchNature = 0;
	$searchService = 0;
	$searchDateStart = 0;
	$searchDateEnd = 0;
}
if (!empty($user->socid)) {
	$searchSocid = (int) $user->socid;
}

if (!isModEnabled('powerplantpv') || !getDolGlobalInt('POWERPLANTPV_MAINTENANCE_ENABLE', 1)) {
	accessforbidden();
}
if (!powerplantpvUserHasMaintenanceRight($user, 'read') || !powerplantpvUserHasRightPath($user, array('powerplantpv', 'powerplant', 'read'))) {
	accessforbidden();
}

$filters = array(
	'fk_powerplant' => $searchPowerplant,
	'fk_soc' => $searchSocid,
	'status' => $searchStatus,
	'date_start' => $searchDateStart,
	'date_end' => $searchDateEnd,
	'intervention_nature' => $searchNature,
	'maintenance_service' => $searchService,
);

$form = new Form($db);
$scheduler = new PowerPlantPVMaintenanceScheduler($db);
$rows = $scheduler->getMaintenanceRows($user, $filters);
$nbtotalofrecords = count($rows);
$num = ($limit > 0) ? min($limit + 1, max(0, $nbtotalofrecords - $offset)) : $nbtotalofrecords;
$pagedRows = ($limit > 0) ? array_slice($rows, $offset, $limit + 1) : $rows;

$powerplantOptions = powerplantpvGetSelectablePowerPlantOptions(null, $searchPowerplant > 0 ? array($searchPowerplant) : array());
$statusOptions = powerplantpvMaintenanceStatusOptions();
$natureOptions = powerplantpvMaintenanceInterventionNatureOptions(1);
$serviceOptions = powerplantpvMaintenanceServiceOptions();
$createAllowed = powerplantpvMaintenanceCanCreateIntervention($user);

$arrayfields = array(
	'powerplant' => array('label' => 'PowerPlant', 'checked' => 1, 'enabled' => 1, 'position' => 10),
	'thirdparty' => array('label' => 'ThirdParty', 'checked' => 1, 'enabled' => 1, 'position' => 20),
	'contract' => array('label' => 'Contract', 'checked' => 1, 'enabled' => 1, 'position' => 30),
	'active_services' => array('label' => 'PowerPlantPVMaintenanceActiveServices', 'checked' => 1, 'enabled' => 1, 'position' => 40),
	'prestations' => array('label' => 'PowerPlantPVMaintenancePrestations', 'checked' => 1, 'enabled' => 1, 'position' => 50),
	'recurrence' => array('label' => 'PowerPlantPVMaintenanceRecurrence', 'checked' => 1, 'enabled' => 1, 'position' => 60),
	'period' => array('label' => 'PowerPlantPVMaintenancePeriod', 'checked' => 1, 'enabled' => 1, 'position' => 70),
	'last_intervention' => array('label' => 'PowerPlantPVLastCoveringIntervention', 'checked' => 1, 'enabled' => 1, 'position' => 80),
	'status' => array('label' => 'MaintenanceStatus', 'checked' => 1, 'enabled' => 1, 'position' => 90),
);

$param = '';
if ($limit > 0 && $limit != $conf->liste_limit) {
	$param .= '&limit='.((int) $limit);
}
if ($searchPowerplant > 0) {
	$param .= '&search_fk_powerplant='.$searchPowerplant;
}
if ($searchSocid > 0 && empty($user->socid)) {
	$param .= '&search_fk_soc='.$searchSocid;
}
if ($searchStatus !== '') {
	$param .= '&search_status='.urlencode($searchStatus);
}
if ($searchNature > 0) {
	$param .= '&search_intervention_nature='.$searchNature;
}
if ($searchService > 0) {
	$param .= '&search_maintenance_service='.$searchService;
}
if ($searchDateStart > 0) {
	$param .= '&search_date_startmonth='.GETPOSTINT('search_date_startmonth').'&search_date_startday='.GETPOSTINT('search_date_startday').'&search_date_startyear='.GETPOSTINT('search_date_startyear');
}
if ($searchDateEnd > 0) {
	$param .= '&search_date_endmonth='.GETPOSTINT('search_date_endmonth').'&search_date_endday='.GETPOSTINT('search_date_endday').'&search_date_endyear='.GETPOSTINT('search_date_endyear');
}

$title = $langs->trans('ListMaintenances');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-powerplantpv page-maintenance-list bodyforlist');

$newcardbutton = dolGetButtonTitle($langs->trans('NewMaintenanceIntervention'), '', 'fa fa-plus-circle', dol_buildpath('/powerplantpv/maintenance_intervention_card.php', 1).'?action=create&backtopage='.urlencode($_SERVER['PHP_SELF'].($param ? '?'.ltrim($param, '&') : '')), '', $createAllowed);
print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, '', '', '', $num, $nbtotalofrecords, 'fa-tools', 0, $newcardbutton, '', $limit);

print '<form method="POST" id="searchFormList" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="page" value="'.$page.'">';

$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, 'maintenance_list');
$visibleColumnCount = 1;
foreach ($arrayfields as $field) {
	if (!empty($field['checked'])) {
		$visibleColumnCount++;
	}
}

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal noborder liste listwithfilterbefore">';
print '<tr class="liste_titre_filter">';
if (!empty($arrayfields['powerplant']['checked'])) {
	print '<td>'.$form->selectarray('search_fk_powerplant', $powerplantOptions, $searchPowerplant, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
}
if (!empty($arrayfields['thirdparty']['checked'])) {
	print '<td>';
	if (empty($user->socid)) {
		print $form->select_company($searchSocid, 'search_fk_soc', '', 1, 0, 0, array(), 0, 'maxwidth150');
	}
	print '</td>';
}
if (!empty($arrayfields['contract']['checked'])) {
	print '<td></td>';
}
if (!empty($arrayfields['active_services']['checked'])) {
	print '<td></td>';
}
if (!empty($arrayfields['prestations']['checked'])) {
	print '<td>'.$form->selectarray('search_maintenance_service', $serviceOptions, $searchService, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
}
if (!empty($arrayfields['recurrence']['checked'])) {
	print '<td></td>';
}
if (!empty($arrayfields['period']['checked'])) {
	print '<td class="center">';
	print '<div class="nowrap">'.$form->selectDate($searchDateStart > 0 ? $searchDateStart : '', 'search_date_start', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From')).'</div>';
	print '<div class="nowrap">'.$form->selectDate($searchDateEnd > 0 ? $searchDateEnd : '', 'search_date_end', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to')).'</div>';
	print '</td>';
}
if (!empty($arrayfields['last_intervention']['checked'])) {
	print '<td>'.$form->selectarray('search_intervention_nature', $natureOptions, $searchNature, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
}
if (!empty($arrayfields['status']['checked'])) {
	print '<td class="center">'.$form->selectarray('search_status', $statusOptions, $searchStatus, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth125').'</td>';
}
print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons().'</td>';
print '</tr>';

print '<tr class="liste_titre">';
foreach ($arrayfields as $field) {
	if (!empty($field['checked'])) {
		print getTitleFieldOfList($field['label'], 0, $_SERVER['PHP_SELF'], '', '', $param, '', '', '', '')."\n";
	}
}
print getTitleFieldOfList($selectedfields, 0, $_SERVER['PHP_SELF'], '', '', '', '', '', '', 'center maxwidthsearch ')."\n";
print '</tr>';

if (empty($pagedRows)) {
	print '<tr class="oddeven"><td colspan="'.$visibleColumnCount.'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
} else {
	$imax = ($limit > 0) ? min(count($pagedRows), $limit) : count($pagedRows);
	for ($i = 0; $i < $imax; $i++) {
		$row = $pagedRows[$i];
		$powerplant = $row['powerplant'];
		$contract = (!empty($row['contract']) && is_array($row['contract'])) ? $row['contract'] : array();
		$coveringIntervention = (!empty($row['covering_intervention']) && is_array($row['covering_intervention'])) ? $row['covering_intervention'] : null;
		print '<tr class="oddeven">';
		if (!empty($arrayfields['powerplant']['checked'])) {
			print '<td class="nowrap">'.powerplantpvMaintenancePowerPlantLink($powerplant).'</td>';
		}
		if (!empty($arrayfields['thirdparty']['checked'])) {
			print '<td>'.powerplantpvMaintenanceThirdPartyLink((int) $row['fk_soc']).'</td>';
		}
		if (!empty($arrayfields['contract']['checked'])) {
			print '<td class="nowrap">'.powerplantpvMaintenanceContractLink((int) $row['contract_id'], !empty($contract['ref']) ? (string) $contract['ref'] : '').'</td>';
		}
		if (!empty($arrayfields['active_services']['checked'])) {
			print '<td>'.powerplantpvMaintenanceRenderActiveServices($row['active_services']).'</td>';
		}
		if (!empty($arrayfields['prestations']['checked'])) {
			print '<td>'.powerplantpvMaintenanceRenderPrestations($row['active_services']).'</td>';
		}
		if (!empty($arrayfields['recurrence']['checked'])) {
			print '<td>'.dol_escape_htmltag(powerplantpvMaintenanceRecurrenceLabel((string) $row['recurrence'])).'</td>';
		}
		if (!empty($arrayfields['period']['checked'])) {
			print '<td>'.powerplantpvMaintenanceFormatPeriod((int) $row['period_start'], (int) $row['period_end']).'</td>';
		}
		if (!empty($arrayfields['last_intervention']['checked'])) {
			print '<td>';
			if (is_array($coveringIntervention)) {
				print powerplantpvMaintenanceInterventionLink((int) $coveringIntervention['id'], (string) $coveringIntervention['ref']);
			} else {
				print '<span class="opacitymedium">-</span>';
			}
			print '</td>';
		}
		if (!empty($arrayfields['status']['checked'])) {
			print '<td class="center">'.powerplantpvMaintenanceStatusBadge((string) $row['status']).'</td>';
		}
		print '<td class="right nowrap">';
		if (!empty($row['is_eligible'])) {
			$urlCreate = powerplantpvMaintenanceBuildCreateInterventionUrl($powerplant, $row, dol_buildpath('/powerplantpv/maintenance_list.php', 1).($param ? '?'.ltrim($param, '&') : ''));
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
print '</form>';

llxFooter();
$db->close();
