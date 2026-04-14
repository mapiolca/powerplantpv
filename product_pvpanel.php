<?php
/* Copyright (C) 2025		Pierre Ardoin				<erp@lesmetiersdubatiment.fr>
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
 *\file       htdocs/powerplantpv/product_pvpanel.php
 *\ingroup    powerplantpv
 *\brief      Product tab for PV panel detailed characteristics
 */

// Load Dolibarr environment
$res = 0;
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

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

$langs->loadLangs(array('products', 'powerplantpv@powerplantpv'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

$object = new Product($db);
if ($id > 0) {
	$object->fetch($id);
}

if (empty($object->id)) {
	accessforbidden();
}

$permissiontoread = $user->hasRight('produit', 'lire');
$permissiontoadd = $user->hasRight('produit', 'creer');

if (!$permissiontoread) {
	accessforbidden();
}

// Security: keep your existing rule (only admin or product finished == 50)
if (!$user->admin && (int) $object->finished !== 50) {
	accessforbidden();
}

$sql = "SELECT c.code";
$sql .= " FROM ".$db->prefix()."product_extrafields as pe";
$sql .= " LEFT JOIN ".$db->prefix()."c_powerplantpv_categorypv as c ON c.rowid = pe.categorie_photovoltaique";
$sql .= " WHERE pe.fk_object = ".((int) $object->id);
$resql = $db->query($sql);
$categoryCode = '';
if ($resql) {
	$obj = $db->fetch_object($resql);
	$categoryCode = !empty($obj->code) ? $obj->code : '';
}
if (!in_array($categoryCode, array('MODULE', 'ONDULE'), true)) {
	accessforbidden();
}

if ($action === 'edit' && !$permissiontoadd) {
	accessforbidden();
}

// Load existing data
$panel = null;
$sql = 'SELECT * FROM '.$db->prefix().'powerplantpv_product_pvpanel';
$sql .= ' WHERE fk_product = '.((int) $object->id);
$resql = $db->query($sql);
if ($resql) {
	$panel = $db->fetch_object($resql);
} else {
	setEventMessages($db->lasterror(), null, 'errors');
}

// Save
if ($action === 'save' && $permissiontoadd) {
	$fields = array(
		'pmax', 'power_tolerance', 'module_efficiency', 'vmp', 'imp', 'voc', 'isc',
		'front_glass_thickness', 'back_glass_thickness', 'cable_section', 'cable_length',
		'operating_temperature', 'max_system_voltage', 'max_series_fuse', 'snow_load', 'wind_load',
		'noct', 'temp_coeff_pmax', 'temp_coeff_voc', 'temp_coeff_isc',
		'first_year_degradation', 'annual_degradation', 'product_warranty', 'power_warranty',
		'modules_per_box', 'modules_per_container40'
	);

	$data = array();
	foreach ($fields as $field) {
		$data[$field] = price2num(GETPOST($field, 'alpha'), 'MT');
	}

	$db->begin();

	if ($panel) {
		$sets = array();
		foreach ($data as $key => $val) {
			$sets[] = $key.'='.($val === '' ? 'null' : "'".$db->escape($val)."'");
		}
		$sql = 'UPDATE '.$db->prefix().'powerplantpv_product_pvpanel SET '.implode(',', $sets);
		$sql .= ' WHERE rowid = '.((int) $panel->rowid);
		$res = $db->query($sql);
	} else {
		$cols = array('fk_product');
		$vals = array((int) $object->id);
		foreach ($data as $key => $val) {
			$cols[] = $key;
			$vals[] = ($val === '' ? 'null' : "'".$db->escape($val)."'");
		}
		$sql = 'INSERT INTO '.$db->prefix().'powerplantpv_product_pvpanel('.implode(',', $cols).') VALUES('.implode(',', $vals).')';
		$res = $db->query($sql);
	}

	if ($res) {
		$db->commit();
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	}

	$db->rollback();
	setEventMessages($db->lasterror(), null, 'errors');
	$action = 'edit';
}

/**
 * Helper to print a row in view or edit mode
 *
 * @param string $label
 * @param string $name
 * @param object|null $panel
 * @param bool $edit
 */
function print_pvpanel_row($label, $name, $panel, $edit)
{
	global $langs;

	$value = ($panel && isset($panel->{$name})) ? (string) $panel->{$name} : '';

	print '<tr class="oddeven">';
	print '<td>'.$label.'</td>';
	print '<td>';
	if ($edit) {
		print '<input class="flat minwidth75" type="text" name="'.$name.'" value="'.dol_escape_htmltag($value).'">';
	} else {
		print ($value !== '' ? dol_escape_htmltag($value) : '<span class="opacitymedium">-</span>');
	}
	print '</td>';
	print '</tr>';
}

$helpurl = '';
$shortlabel = dol_trunc($object->label, 16);
$title = $langs->trans('Product').' '.$shortlabel.' - '.$langs->trans('PVPanelTabTitle');
$helpurl = 'EN:Module_Products|FR:Module_Produits|ES:M&oacute;dulo_Productos';

llxHeader('', $title, $helpurl, '', 0, 0, '', '', '', 'mod-product page-card_product_pvpanel');

$head = product_prepare_head($object, $user);

print dol_get_fiche_head($head, 'pvpanel', $langs->trans('Product'));

$linkback = '<a href="'.DOL_URL_ROOT.'/product/list.php?restore_lastsearch_values=1&type='.$object->type.'">'.$langs->trans('BackToList').'</a>';
$object->next_prev_filter = '(te.fk_product_type:=:'.((int) $object->type).')';
$shownav = 1;
if ($user->socid && !in_array('product', explode(',', getDolGlobalString('MAIN_MODULES_FOR_EXTERNAL')))) {
	$shownav = 0;
}

dol_banner_tab($object, 'ref', $linkback, $shownav, 'ref');
print dol_get_fiche_end();
$editmode = ($action === 'edit');

if ($editmode) {
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="save">';
}
print '<div class="fichehalfleft">';

print load_fiche_titre($langs->trans('PVPanelElectricalSTC'), '', '');
print '<table class="noborder centpercent">';
print_pvpanel_row($langs->trans('PVPanelNominalPower'), 'pmax', $panel, $editmode);
print_pvpanel_row($langs->trans('PVPanelPowerTolerance'), 'power_tolerance', $panel, $editmode);
print_pvpanel_row($langs->trans('PVPanelVmp'), 'vmp', $panel, $editmode);
print_pvpanel_row($langs->trans('PVPanelImp'), 'imp', $panel, $editmode);
print_pvpanel_row($langs->trans('PVPanelVoc'), 'voc', $panel, $editmode);
print_pvpanel_row($langs->trans('PVPanelIsc'), 'isc', $panel, $editmode);
print_pvpanel_row($langs->trans('PVPanelModuleEfficiency'), 'module_efficiency', $panel, $editmode);
print '</table><br>';

print load_fiche_titre($langs->trans('PVPanelElectricalNOCT'), '', '');
print '<table class="noborder centpercent">';
print_pvpanel_row($langs->trans('PVPanelNOCT'), 'noct', $panel, $editmode);
print_pvpanel_row($langs->trans('PVPanelTempCoeffPmax'), 'temp_coeff_pmax', $panel, $editmode);
print_pvpanel_row($langs->trans('PVPanelTempCoeffVoc'), 'temp_coeff_voc', $panel, $editmode);
print_pvpanel_row($langs->trans('PVPanelTempCoeffIsc'), 'temp_coeff_isc', $panel, $editmode);
print '</table><br>';

print load_fiche_titre($langs->trans('PVPanelWarranty'), '', '');
print '<table class="noborder centpercent">';
print_pvpanel_row($langs->trans('PVPanelProductWarranty'), 'product_warranty', $panel, $editmode);
print_pvpanel_row($langs->trans('PVPanelPowerWarranty'), 'power_warranty', $panel, $editmode);
print_pvpanel_row($langs->trans('PVPanelFirstYearDegradation'), 'first_year_degradation', $panel, $editmode);
print_pvpanel_row($langs->trans('PVPanelAnnualDegradation'), 'annual_degradation', $panel, $editmode);
print '</table>';

print '</div>';

print '<div class="fichehalfright">';

print load_fiche_titre($langs->trans('PVPanelMechanicalData'), '', '');
print '<table class="noborder centpercent">';
print_pvpanel_row($langs->trans('PVPanelFrontGlassThickness'), 'front_glass_thickness', $panel, $editmode);
print_pvpanel_row($langs->trans('PVPanelBackGlassThickness'), 'back_glass_thickness', $panel, $editmode);
print_pvpanel_row($langs->trans('PVPanelCableSection'), 'cable_section', $panel, $editmode);
print_pvpanel_row($langs->trans('PVPanelCableLength'), 'cable_length', $panel, $editmode);
print '</table><br>';

print load_fiche_titre($langs->trans('PVPanelMaxRatings'), '', '');
print '<table class="noborder centpercent">';
print_pvpanel_row($langs->trans('PVPanelMaxSystemVoltage'), 'max_system_voltage', $panel, $editmode);
print_pvpanel_row($langs->trans('PVPanelMaxSeriesFuse'), 'max_series_fuse', $panel, $editmode);
print_pvpanel_row($langs->trans('PVPanelOperatingTemperature'), 'operating_temperature', $panel, $editmode);
print_pvpanel_row($langs->trans('PVPanelSnowLoad'), 'snow_load', $panel, $editmode);
print_pvpanel_row($langs->trans('PVPanelWindLoad'), 'wind_load', $panel, $editmode);
print '</table><br>';

print load_fiche_titre($langs->trans('PVPanelPackaging'), '', '');
print '<table class="noborder centpercent">';
print_pvpanel_row($langs->trans('PVPanelModulesPerBox'), 'modules_per_box', $panel, $editmode);
print_pvpanel_row($langs->trans('PVPanelModulesPerContainer40'), 'modules_per_container40', $panel, $editmode);
print '</table>';

print '</div>';

print '<div class="clearboth"></div>';
// Action buttons
print '<div class="tabsAction">';

if (!$editmode) {
	if ($permissiontoadd) {
		print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=edit">'.$langs->trans('Modify').'</a>';
	}
} else {
	print '<input type="submit" class="butAction" value="'.$langs->trans('Save').'">';
	print '<a class="butActionRefused" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">'.$langs->trans('Cancel').'</a>';
}
print '</div>';

if ($editmode) {
	print '</form>';
}

llxFooter();
$db->close();
