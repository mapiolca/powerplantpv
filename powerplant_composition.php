<?php
/* Copyright (C) 2026		Pierre Ardoin			<developpeur@lesmetiersdubatiment.fr>
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
 * \file		powerplant_composition.php
 * \ingroup	powerplantpv
 * \brief	Tab for material composition on PowerPlant
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

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/powerplantpv/class/powerplant.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv_powerplant.lib.php');

$langs->loadLangs(array('powerplantpv@powerplantpv', 'products'));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$lineid = GETPOSTINT('lineid');

$object = new PowerPlant($db);
$form = new Form($db);
$hookmanager->initHooks(array($object->element.'composition', 'globalcard'));

$enablepermissioncheck = getDolGlobalInt('POWERPLANTPV_ENABLE_PERMISSION_CHECK');
if ($enablepermissioncheck) {
	$permissiontoread = $user->hasRight('powerplantpv', 'powerplant', 'read');
	$permissiontoadd = $user->hasRight('powerplantpv', 'powerplant', 'write');
} else {
	$permissiontoread = 1;
	$permissiontoadd = 1;
}

if (!isModEnabled($object->module) || !$permissiontoread) {
	accessforbidden();
}

include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

$categories = array(
	50 => $langs->trans('PVModules'),
	51 => $langs->trans('PVInverters'),
	52 => $langs->trans('PVIntegration'),
	53 => $langs->trans('PVMonitoring'),
	54 => $langs->trans('PVACBox'),
	55 => $langs->trans('PVDCBox'),
);

if ($action == 'addcomposition' && $permissiontoadd) {
	if (!checkToken()) {
		accessforbidden();
	}

	$fk_product = GETPOSTINT('fk_product');
	$naturecode = GETPOSTINT('naturecode');
	$serial_number = GETPOST('serial_number', 'alphanohtml');
	$commissioning_date = GETPOST('commissioning_date', 'alphanohtml');

	if ((int) $object->status === (int) $object::STATUS_DRAFT && $fk_product > 0 && array_key_exists($naturecode, $categories)) {
		$sql = 'INSERT INTO '.$db->prefix()."powerplantpv_powerplantcomp(fk_powerplant, fk_product, nature_code, qty, serial_number, commissioning_date, entity)";
		$sql .= ' VALUES('.((int) $object->id).', '.((int) $fk_product).', '.((int) $naturecode).", 1, '".$db->escape($serial_number)."', ";
		if (!empty($commissioning_date)) {
			$sql .= "'".$db->idate(dol_stringtotime($commissioning_date))."'";
		} else {
			$sql .= 'NULL';
		}
		$sql .= ', '.((int) $conf->entity).')';

		if (!$db->query($sql)) {
			setEventMessages($db->lasterror(), null, 'errors');
		}
	}
}

if ($action == 'delcomposition' && $permissiontoadd && $lineid > 0) {
	if (!checkToken()) {
		accessforbidden();
	}

	if ((int) $object->status !== (int) $object::STATUS_DRAFT) {
		accessforbidden();
	}

	$sql = 'DELETE FROM '.$db->prefix()."powerplantpv_powerplantcomp";
	$sql .= ' WHERE rowid = '.((int) $lineid).' AND fk_powerplant = '.((int) $object->id).' AND entity = '.((int) $conf->entity);
	if (!$db->query($sql)) {
		setEventMessages($db->lasterror(), null, 'errors');
	}
}

$title = $langs->trans('PowerPlant')." - ".$langs->trans('PowerPlantMaterialComposition');
llxHeader('', $title, '');

if ($id > 0 || !empty($ref)) {
	$object->fetch_thirdparty();

	$head = powerplantPrepareHead($object);
	print dol_get_fiche_head($head, 'composition', $langs->trans('PowerPlant'), -1, $object->picto);

	$linkback = '<a href="'.dol_buildpath('/powerplantpv/powerplant_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';
	print load_fiche_titre($langs->trans('PowerPlantMaterialComposition'));

	$canedit = ($permissiontoadd && (int) $object->status === (int) $object::STATUS_DRAFT);
	if ($canedit) {
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="addcomposition">';
		print '<div class="div-table-responsive">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans('Ref').'</td>';
		print '<td>'.$langs->trans('Label').'</td>';
		print '<td>'.$langs->trans('Category').'</td>';
		print '<td>'.$langs->trans('PowerPlantSerialNumber').'</td>';
		print '<td>'.$langs->trans('PowerPlantCommissioningDate').'</td>';
		print '<td class="center"></td>';
		print '</tr>';
		print '<tr class="oddeven">';
		print '<td colspan="2">'.$form->select_produits(0, 'fk_product', '', 0, 0, -1, 2, '', 0, array(), '', 1, 0).'</td>';
		print '<td>'.$form->selectarray('naturecode', $categories, GETPOSTINT('naturecode'), 0).'</td>';
		print '<td><input type="text" class="flat minwidth150" name="serial_number" value="'.dol_escape_htmltag(GETPOST('serial_number', 'alphanohtml')).'"></td>';
		print '<td>'.$form->selectDate('', 'commissioning_date', 0, 0, 0, '', 1, 0).'</td>';
		print '<td class="center"><input type="submit" class="button small" value="'.$langs->trans('Add').'"></td>';
		print '</tr>';
		print '</table>';
		print '</div>';
		print '</form>';
		print '<br>';
	}

	$sql = 'SELECT c.rowid, c.nature_code, c.serial_number, c.commissioning_date, p.ref as product_ref, p.label as product_label';
	$sql .= ' FROM '.$db->prefix().'powerplantpv_powerplantcomp as c';
	$sql .= ' JOIN '.$db->prefix().'product as p ON p.rowid = c.fk_product';
	$sql .= ' WHERE c.fk_powerplant = '.((int) $object->id);
	$sql .= ' AND c.entity = '.((int) $conf->entity);
	$sql .= ' ORDER BY p.ref ASC, c.rowid ASC';

	$resql = $db->query($sql);
	print '<div class="div-table-responsive">';
	print '<table class="tagtable liste centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('Ref').'</td>';
	print '<td>'.$langs->trans('Label').'</td>';
	print '<td>'.$langs->trans('Category').'</td>';
	print '<td>'.$langs->trans('PowerPlantSerialNumber').'</td>';
	print '<td>'.$langs->trans('PowerPlantCommissioningDate').'</td>';
	print '<td class="center"></td>';
	print '</tr>';

	if ($resql) {
		$num = $db->num_rows($resql);
		if ($num > 0) {
			while ($objline = $db->fetch_object($resql)) {
				print '<tr class="oddeven">';
				print '<td>'.dol_escape_htmltag($objline->product_ref).'</td>';
				print '<td>'.dol_escape_htmltag($objline->product_label).'</td>';
				print '<td>'.(isset($categories[(int) $objline->nature_code]) ? $categories[(int) $objline->nature_code] : '').'</td>';
				print '<td>'.dol_escape_htmltag($objline->serial_number).'</td>';
				print '<td>'.(!empty($objline->commissioning_date) ? dol_print_date($db->jdate($objline->commissioning_date), 'day') : '').'</td>';
				print '<td class="center">';
				if ($canedit) {
					print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=delcomposition&lineid='.(int) $objline->rowid.'&token='.newToken().'">'.img_delete().'</a>';
				}
				print '</td>';
				print '</tr>';
			}
		} else {
			print '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans('None').'</span></td></tr>';
		}
	} else {
		print '<tr class="oddeven"><td colspan="6">'.dol_escape_htmltag($db->lasterror()).'</td></tr>';
	}

	print '</table>';
	print '</div>';
	print '</div>';
	print dol_get_fiche_end();
}

llxFooter();
$db->close();
