<?php
/* Copyright (C) 2001-2005  Rodolphe Quiedeville    <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012  Regis Houssin           <regis.houssin@inodbox.com>
 * Copyright (C) 2015       Jean-François Ferry     <jfefe@aternatik.fr>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2025		Pierre Ardoin				<erp@lesmetiersdubatiment.fr>
 * Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
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
 *	\file       powerplantpv/powerplantpvindex.php
 *	\ingroup    powerplantpv
 *	\brief      Home page of powerplantpv top menu
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
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
// Try main.inc.php using relative path
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
include_once DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php';
dol_include_once('/powerplantpv/class/powerplant.class.php');
dol_include_once('/powerplantpv/class/powerplantpvattestation.class.php');
dol_include_once('/powerplantpv/class/powerplantpvattestationtypes.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_attestation.lib.php');

// Load translation files required by the page
$langs->loadLangs(array("powerplantpv@powerplantpv"));

$action = GETPOST('action', 'aZ09');

$max = 3;

// Security check - Protection if external user
$socid = GETPOSTINT('socid');
if (!empty($user->socid) && $user->socid > 0) {
	$action = '';
	$socid = $user->socid;
}

$powerplantstatic = new PowerPlant($db);
$hookmanager->initHooks(array($powerplantstatic->element.'index', 'globalindex'));
$attestationstatic = null;
$permissiontoreadattestation = 0;
$attestationtablesok = 0;

// Security check (enable the most restrictive one)
if (!isModEnabled('powerplantpv')) {
	accessforbidden('Module not enabled');
}
if ($user->socid > 0) {
	$socid = $user->socid;
}
$enablepermissioncheck = getDolGlobalInt('POWERPLANTPV_ENABLE_PERMISSION_CHECK');
$permissiontoread = ($enablepermissioncheck ? $user->hasRight('powerplantpv', 'powerplant', 'read') : 1);
if (!$permissiontoread) {
	accessforbidden();
}
restrictedArea($user, 'powerplantpv', 0, 'powerplantpv_powerplant', 'powerplant', 'fk_soc', 'rowid');

if (getDolGlobalInt('POWERPLANTPV_ATTESTATION_ENABLE', 1)
	&& class_exists('PowerPlantPVAttestation')
	&& class_exists('PowerPlantPVAttestationTypes')
	&& function_exists('powerplantpvAttestationUserHasRight')
) {
	$permissiontoreadattestation = powerplantpvAttestationUserHasRight($user, 'read');
	if ($permissiontoreadattestation && function_exists('powerplantpvAttestationGetInstallationIssues')) {
		$attestationissues = powerplantpvAttestationGetInstallationIssues();
		$attestationtablesok = empty($attestationissues['tables']);
	}
	if ($permissiontoreadattestation && $attestationtablesok) {
		$attestationstatic = new PowerPlantPVAttestation($db);
	}
}
//if (empty($user->admin)) {
//	accessforbidden('Must be admin');
//}


/*
 * Actions
 */

// None


/**
 * Build status statistics graph.
 *
 * @param	DoliDB		$db					Database handler
 * @param	Translate	$langs				Language handler
 * @param	PowerPlant	$powerplantstatic	Power plant static object
 * @param	int			$socid				Third party filter
 * @return	string							HTML graph block
 */
function powerplantpvIndexStatusGraph($db, $langs, $powerplantstatic, $socid = 0)
{
	$sql = "SELECT p.status, COUNT(p.rowid) as nb";
	$sql .= " FROM ".$db->prefix()."powerplantpv_powerplant as p";
	$sql .= " WHERE p.entity IN (".getEntity($powerplantstatic->element).")";
	if ($socid > 0) {
		$sql .= " AND p.fk_soc = ".((int) $socid);
	}
	$sql .= " GROUP BY p.status";
	$sql .= " ORDER BY p.status ASC";

	$labels = array(
		PowerPlant::STATUS_DRAFT => 'Draft',
		PowerPlant::STATUS_VALIDATED => 'Validated',
		PowerPlant::STATUS_IN_SERVICE => 'PowerPlantInService',
		PowerPlant::STATUS_OUT_OF_SERVICE => 'PowerPlantOutOfService',
		PowerPlant::STATUS_CANCELED => 'Canceled',
	);
	$dataseries = array();
	$total = 0;

	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$status = (int) $obj->status;
			$label = !empty($labels[$status]) ? $langs->transnoentitiesnoconv($labels[$status]) : $langs->transnoentitiesnoconv('Status').' '.$status;
			$dataseries[] = array($label, (int) $obj->nb);
			$total += (int) $obj->nb;
		}
		$db->free($resql);
	} else {
		dol_print_error($db);
	}

	$dolgraph = new DolGraph();
	$dolgraph->SetData($dataseries);
	$dolgraph->setShowLegend(2);
	$dolgraph->setShowPercent(1);
	$dolgraph->SetType(array('pie'));
	$dolgraph->setHeight('220');
	$dolgraph->draw('idgraphpowerplantstatus');

	$out = '<div class="div-table-responsive-no-min">';
	$out .= '<table class="noborder centpercent">';
	$out .= '<tr class="liste_titre"><th>'.$langs->trans('PowerPlantStatsByStatus').'</th></tr>';
	$out .= '<tr><td class="center nopaddingleftimp nopaddingrightimp">'.$dolgraph->show($total ? 0 : 1).'</td></tr>';
	$out .= '</table>';
	$out .= '</div><br>';

	return $out;
}

/**
 * Build category statistics graph.
 *
 * @param	DoliDB		$db		Database handler
 * @param	Translate	$langs	Language handler
 * @param	int			$socid	Third party filter
 * @return	string				HTML graph block
 */
function powerplantpvIndexCategoryGraph($db, $langs, $socid = 0)
{
	global $user;

	if (!isModEnabled('category') || !$user->hasRight('categorie', 'read')) {
		return '';
	}

	$sql = "SELECT c.label, COUNT(cp.fk_powerplant) as nb";
	$sql .= " FROM ".$db->prefix()."categorie_powerplant as cp";
	$sql .= " INNER JOIN ".$db->prefix()."categorie as c ON cp.fk_categorie = c.rowid";
	$sql .= " INNER JOIN ".$db->prefix()."powerplantpv_powerplant as p ON cp.fk_powerplant = p.rowid";
	$sql .= " WHERE c.type = 450004";
	$sql .= " AND c.entity IN (".getEntity('category').")";
	$sql .= " AND p.entity IN (".getEntity('powerplant').")";
	if ($socid > 0) {
		$sql .= " AND p.fk_soc = ".((int) $socid);
	}
	$sql .= " GROUP BY c.label";
	$sql .= " ORDER BY nb DESC, c.label ASC";

	$dataseries = array();
	$total = 0;
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$dataseries[] = array($obj->label, (int) $obj->nb);
			$total += (int) $obj->nb;
		}
		$db->free($resql);
	} else {
		dol_print_error($db);
	}

	$dolgraph = new DolGraph();
	$dolgraph->SetData($dataseries);
	$dolgraph->setShowLegend(2);
	$dolgraph->setShowPercent(1);
	$dolgraph->SetType(array('pie'));
	$dolgraph->setHeight('220');
	$dolgraph->draw('idgraphpowerplantcategories');

	$out = '<div class="div-table-responsive-no-min">';
	$out .= '<table class="noborder centpercent">';
	$out .= '<tr class="liste_titre"><th colspan="2">'.$langs->trans('PowerPlantStatsByCategories').'</th></tr>';
	$out .= '<tr><td class="center nopaddingleftimp nopaddingrightimp" colspan="2">'.$dolgraph->show($total ? 0 : 1).'</td></tr>';
	$out .= '<tr class="liste_total"><td>'.$langs->trans('Total').'</td><td class="right">'.$total.'</td></tr>';
	$out .= '</table>';
	$out .= '</div><br>';

	return $out;
}

/**
 * Build latest power plant table.
 *
 * @param	DoliDB		$db					Database handler
 * @param	Translate	$langs				Language handler
 * @param	PowerPlant	$powerplantstatic	Power plant static object
 * @param	string		$field				Date field
 * @param	string		$titlekey			Title translation key
 * @param	int			$max				Max rows
 * @param	int			$socid				Third party filter
 * @return	string							HTML table
 */
function powerplantpvIndexLatestTable($db, $langs, $powerplantstatic, $field, $titlekey, $max, $socid = 0)
{
	$field = ($field == 'date_creation' ? 'date_creation' : 'tms');

	$sql = "SELECT p.rowid, p.ref, p.label, p.status, p.".$field." as datevalue";
	$sql .= " FROM ".$db->prefix()."powerplantpv_powerplant as p";
	$sql .= " WHERE p.entity IN (".getEntity($powerplantstatic->element).")";
	if ($socid > 0) {
		$sql .= " AND p.fk_soc = ".((int) $socid);
	}
	$sql .= $db->order("p.".$field, "DESC");
	$sql .= $db->plimit($max, 0);

	$out = '<div class="div-table-responsive-no-min">';
	$out .= '<table class="noborder centpercent">';
	$out .= '<tr class="liste_titre">';
	$out .= '<th colspan="4">'.$langs->trans($titlekey, $max);
	$out .= '<a href="'.dol_buildpath('/powerplantpv/powerplant_list.php', 1).'?sortfield=t.'.$field.'&sortorder=DESC" title="'.$langs->trans('FullList').'">';
	$out .= '<span class="badge marginleftonlyshort">...</span>';
	$out .= '</a>';
	$out .= '</th>';
	$out .= '</tr>';

	$resql = $db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		if ($num > 0) {
			while ($obj = $db->fetch_object($resql)) {
				$powerplantstatic->id = $obj->rowid;
				$powerplantstatic->ref = $obj->ref;
				$powerplantstatic->label = $obj->label;
				$powerplantstatic->status = $obj->status;

				$out .= '<tr class="oddeven">';
				$out .= '<td class="nowraponall">'.$powerplantstatic->getNomUrl(1).'</td>';
				$out .= '<td class="tdoverflowmax200" title="'.dol_escape_htmltag($obj->label).'">'.dol_escape_htmltag($obj->label).'</td>';
				$out .= '<td class="nowraponall">'.dol_print_date($db->jdate($obj->datevalue), 'day', 'tzuserrel').'</td>';
				$out .= '<td class="right nowraponall">'.$powerplantstatic->getLibStatut(5).'</td>';
				$out .= '</tr>';
			}
		} else {
			$out .= '<tr class="oddeven"><td colspan="4" class="opacitymedium">'.$langs->trans('None').'</td></tr>';
		}
		$db->free($resql);
	} else {
		dol_print_error($db);
	}

	$out .= '</table>';
	$out .= '</div><br>';

	return $out;
}

/**
 * Build latest attestation table.
 *
 * @param	DoliDB						$db					Database handler
 * @param	Translate					$langs				Language handler
 * @param	PowerPlantPVAttestation		$attestationstatic	Attestation static object
 * @param	int							$max				Max rows
 * @param	int							$socid				Third party filter
 * @return	string											HTML table
 */
function powerplantpvIndexLatestAttestationTable($db, $langs, $attestationstatic, $max, $socid = 0)
{
	if (!is_object($attestationstatic)) {
		return '';
	}

	$typeLabels = PowerPlantPVAttestationTypes::getTypeLabels($langs);

	$sql = "SELECT t.rowid, t.ref, t.type_code, t.status, t.tms as datevalue";
	$sql .= " FROM ".$db->prefix()."powerplantpv_attestation as t";
	$sql .= " WHERE t.entity IN (".getEntity($attestationstatic->element).")";
	if ($socid > 0) {
		$sql .= " AND t.fk_soc = ".((int) $socid);
	}
	$sql .= $db->order("t.tms", "DESC");
	$sql .= $db->plimit($max, 0);

	$out = '<div class="div-table-responsive-no-min">';
	$out .= '<table class="noborder centpercent">';
	$out .= '<tr class="liste_titre">';
	$out .= '<th colspan="4">'.$langs->trans('AttestationLatestModified', $max);
	$out .= '<a href="'.dol_buildpath('/powerplantpv/attestation_list.php', 1).'?sortfield=t.tms&sortorder=DESC" title="'.$langs->trans('FullList').'">';
	$out .= '<span class="badge marginleftonlyshort">...</span>';
	$out .= '</a>';
	$out .= '</th>';
	$out .= '</tr>';

	$resql = $db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		if ($num > 0) {
			while ($obj = $db->fetch_object($resql)) {
				$attestationstatic->id = $obj->rowid;
				$attestationstatic->ref = $obj->ref;
				$attestationstatic->type_code = $obj->type_code;
				$attestationstatic->status = $obj->status;
				$typeLabel = !empty($typeLabels[$obj->type_code]) ? $typeLabels[$obj->type_code] : $obj->type_code;

				$out .= '<tr class="oddeven">';
				$out .= '<td class="nowraponall">'.$attestationstatic->getNomUrl(1).'</td>';
				$out .= '<td class="tdoverflowmax200" title="'.dol_escape_htmltag($typeLabel).'">'.dol_escape_htmltag($typeLabel).'</td>';
				$out .= '<td class="nowraponall">'.dol_print_date($db->jdate($obj->datevalue), 'day', 'tzuserrel').'</td>';
				$out .= '<td class="right nowraponall">'.$attestationstatic->getLibStatut(5).'</td>';
				$out .= '</tr>';
			}
		} else {
			$out .= '<tr class="oddeven"><td colspan="4" class="opacitymedium">'.$langs->trans('None').'</td></tr>';
		}
		$db->free($resql);
	} else {
		dol_print_error($db);
	}

	$out .= '</table>';
	$out .= '</div><br>';

	return $out;
}


/*
 * View
 */

llxHeader("", $langs->trans("PowerPlantPVArea"), '', '', 0, 0, '', '', '', 'mod-powerplantpv page-index');

print load_fiche_titre($langs->trans("PowerPlantPVArea"), '', 'fa-sun');

print '<div class="fichecenter"><div class="fichethirdleft">';

print powerplantpvIndexStatusGraph($db, $langs, $powerplantstatic, $socid);
print powerplantpvIndexCategoryGraph($db, $langs, $socid);

print '</div><div class="fichetwothirdright">';

print powerplantpvIndexLatestTable($db, $langs, $powerplantstatic, 'date_creation', 'PowerPlantLatestCreated', $max, $socid);
print powerplantpvIndexLatestTable($db, $langs, $powerplantstatic, 'tms', 'PowerPlantLatestModified', $max, $socid);
if (is_object($attestationstatic)) {
	print powerplantpvIndexLatestAttestationTable($db, $langs, $attestationstatic, $max, $socid);
}

print '</div></div>';

// End of page
llxFooter();
$db->close();
