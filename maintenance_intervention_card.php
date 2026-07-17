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
 * \file		maintenance_intervention_card.php
 * \ingroup		powerplantpv
 * \brief		Guided creation page for maintenance interventions.
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
dol_include_once('/powerplantpv/class/powerplant.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_maintenance.lib.php');
dol_include_once('/contrat/class/contrat.class.php');

$langs->loadLangs(array('powerplantpv@powerplantpv', 'other', 'contracts', 'interventions'));

$action = GETPOST('action', 'aZ09');
if ($action === '') {
	$action = 'create';
}
$backtopage = GETPOST('backtopage', 'alpha');
$contractid = GETPOSTINT('fk_contract') > 0 ? GETPOSTINT('fk_contract') : GETPOSTINT('fk_contrat');
if ($contractid <= 0) {
	$contractid = GETPOSTINT('contratid');
}
$natureid = GETPOSTINT('powerplantpv_intervention_nature');
$selectedPowerplantIds = powerplantpvGetRequestedPowerPlantIds(null, 0);
if (empty($selectedPowerplantIds) && $contractid > 0 && class_exists('Contrat')) {
	$prefillContract = new Contrat($db);
	if ($prefillContract->fetch($contractid) > 0) {
		foreach (powerplantpvGetLinkedPowerPlants($prefillContract) as $linkedPowerplant) {
			$linkedPowerplantId = powerplantpvGetCommonObjectId($linkedPowerplant);
			if ($linkedPowerplantId > 0) {
				$selectedPowerplantIds[] = $linkedPowerplantId;
			}
		}
		$selectedPowerplantIds = array_values(array_unique(array_map('intval', $selectedPowerplantIds)));
	}
}

if (!isModEnabled('powerplantpv') || !getDolGlobalInt('POWERPLANTPV_MAINTENANCE_ENABLE', 1)) {
	accessforbidden();
}
if (!powerplantpvUserHasMaintenanceRight($user, 'read')) {
	accessforbidden();
}
$permissiontocreate = powerplantpvMaintenanceCanCreateIntervention($user);
if (!$permissiontocreate && $action === 'create_intervention') {
	accessforbidden();
}

if ($user->socid > 0) {
	$selectedPowerplantIds = powerplantpvFilterSelectablePowerPlantIds($selectedPowerplantIds, null, array());
}

if ($action === 'create_intervention') {
	if (empty($selectedPowerplantIds)) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('PowerPlantPVCentrals')), null, 'errors');
		$action = 'create';
	} else {
		$firstPowerplantId = (int) reset($selectedPowerplantIds);
		$powerplant = new PowerPlant($db);
		$result = $powerplant->fetch($firstPowerplantId);
		if ($result <= 0) {
			setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
			$action = 'create';
		} else {
			$socid = !empty($powerplant->fk_soc) ? (int) $powerplant->fk_soc : 0;
			$projectid = !empty($powerplant->fk_project) ? (int) $powerplant->fk_project : 0;
			if ($contractid > 0 && class_exists('Contrat')) {
				$contract = new Contrat($db);
				if ($contract->fetch($contractid) > 0) {
					$socid = !empty($contract->socid) ? (int) $contract->socid : (int) $contract->fk_soc;
					$projectid = !empty($contract->fk_project) ? (int) $contract->fk_project : (int) $contract->fk_projet;
				}
			}

			$url = dol_buildpath('/fichinter/card.php', 1).'?action=create';
			$url .= '&origin='.urlencode(powerplantpvGetCanonicalPowerPlantLinkType());
			$url .= '&originid='.$firstPowerplantId;
			$url .= '&fk_powerplant='.$firstPowerplantId;
			foreach ($selectedPowerplantIds as $powerplantId) {
				$url .= '&powerplantpv_powerplants[]='.((int) $powerplantId);
			}
			if ($contractid > 0) {
				$url .= '&fk_contrat='.$contractid.'&contratid='.$contractid;
			}
			if ($socid > 0) {
				$url .= '&socid='.$socid;
			}
			if ($projectid > 0) {
				$url .= '&projectid='.$projectid;
			}
			if ($natureid > 0) {
				$url .= '&powerplantpv_intervention_nature='.$natureid;
			} else {
				$url .= '&powerplantpv_intervention_nature_code=PREVENTIVE_MAINTENANCE';
			}
			$url .= '&backtopage='.urlencode($backtopage !== '' ? $backtopage : dol_buildpath('/powerplantpv/maintenance_list.php', 1));

			header('Location: '.$url);
			exit;
		}
	}
}

$form = new Form($db);
$powerplantOptions = powerplantpvGetSelectablePowerPlantOptions(null, $selectedPowerplantIds);
$contractOptions = powerplantpvMaintenanceContractOptions($selectedPowerplantIds);
if ($contractid > 0 && !isset($contractOptions[$contractid])) {
	$allContractOptions = powerplantpvMaintenanceContractOptions(array());
	if (isset($allContractOptions[$contractid])) {
		$contractOptions[$contractid] = $allContractOptions[$contractid];
	}
}
$natureOptions = powerplantpvMaintenanceInterventionNatureOptions(1);

$title = $langs->trans('NewMaintenanceIntervention');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-powerplantpv page-maintenance-intervention-create');

$linkback = '<a href="'.dol_buildpath('/powerplantpv/maintenance_list.php', 1).'">'.$langs->trans('BackToList').'</a>';
print load_fiche_titre($title, $linkback, 'fa-tools');

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="create_intervention">';
if ($backtopage !== '') {
	print '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">';
}

print '<div class="div-table-responsive-no-min">';
print '<table class="border centpercent tableforfield">';
print '<tr>';
print '<td class="titlefieldcreate">'.$langs->trans('PowerPlantPVCentrals').'</td>';
print '<td>'.$form->multiselectarray('powerplantpv_powerplants', $powerplantOptions, $selectedPowerplantIds, 0, 0, 'minwidth300 maxwidth500', 0, 0).'</td>';
print '</tr>';
print '<tr>';
print '<td>'.$langs->trans('Contract').'</td>';
print '<td>'.$form->selectarray('fk_contrat', $contractOptions, $contractid, 1, 0, 0, '', 0, 0, 0, '', 'minwidth300 maxwidth500').'</td>';
print '</tr>';
print '<tr>';
print '<td>'.$langs->trans('PowerPlantPVMaintenanceNature').'</td>';
print '<td>'.$form->selectarray('powerplantpv_intervention_nature', $natureOptions, $natureid, 1, 0, 0, '', 0, 0, 0, '', 'minwidth300 maxwidth500').'</td>';
print '</tr>';
print '</table>';
print '</div>';

print '<div class="center">';
print '<input type="submit" class="button button-save" value="'.dol_escape_htmltag($langs->trans('Create')).'"'.($permissiontocreate ? '' : ' disabled="disabled"').'>';
print ' ';
print '<a class="button button-cancel" href="'.dol_buildpath('/powerplantpv/maintenance_list.php', 1).'">'.$langs->trans('Cancel').'</a>';
print '</div>';
print '</form>';

llxFooter();
$db->close();
