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
 *	\file       htdocs/powerplantpv/product_pvpanel.php
 *	\ingroup    powerplantpv
 *	\brief      Product tab for PV panel detailed characteristics
 */

// Load Dolibarr environment
$res = 0;
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

require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

$langs->loadLangs(array("products", "powerplantpv@powerplantpv"));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

$object = new Product($db);
if ($id > 0) {
	$object->fetch($id);
}

if (empty($object->id) || $object->fk_product_nature != '50') {
	accessforbidden();
}

$permissiontoadd = $user->hasRight('produit', 'creer');

// Load existing data
$sql = "SELECT * FROM ".$db->prefix()."powerplantpv_product_pvpanel WHERE fk_product = ".((int) $object->id);
$resql = $db->query($sql);
$panel = $db->fetch_object($resql);

if ($action == 'save' && $permissiontoadd) {
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

	if ($panel) {
		$sets = array();
		foreach ($data as $key => $val) {
			$sets[] = $key."=".($val === '' ? "null" : "'".$db->escape($val)."'");
		}
		$sql = "UPDATE ".$db->prefix()."powerplantpv_product_pvpanel SET ".implode(',', $sets);
		$sql .= " WHERE rowid = ".((int) $panel->rowid);
		$db->query($sql);
	} else {
		$cols = array('fk_product');
		$vals = array((int) $object->id);
		foreach ($data as $key => $val) {
			$cols[] = $key;
			$vals[] = ($val === '' ? "null" : "'".$db->escape($val)."'");
		}
		$sql = "INSERT INTO ".$db->prefix()."powerplantpv_product_pvpanel(".implode(',', $cols).") VALUES(".implode(',', $vals).")";
		$db->query($sql);
	}

	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

$head = product_prepare_head($object, $user);
$head[] = array(
	dol_buildpath('/powerplantpv/product_pvpanel.php', 1).'?id='.$object->id,
	$langs->trans('PVPanelTabTitle'),
	'pvpanel'
);

llxHeader('', $langs->trans('PVPanelTabTitle'));

print dol_get_fiche_head($head, 'pvpanel', $langs->trans("Product"));

print '<table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans("Ref").'</td><td>'.$object->ref.'</td></tr>';
print '<tr><td>'.$langs->trans("Label").'</td><td>'.$object->label.'</td></tr>';
print '</table><br>';

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

print '<div class="fichehalfleft">';
print load_fiche_titre($langs->trans('PVPanelElectricalSTC'), '', '');
print '<table class="noborder centpercent">';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelNominalPower').'</td><td><input class="flat minwidth75" type="text" name="pmax" value="'.dol_escape_htmltag($panel ? $panel->pmax : '').'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelPowerTolerance').'</td><td><input class="flat minwidth75" type="text" name="power_tolerance" value="'.dol_escape_htmltag($panel ? $panel->power_tolerance : '').'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelVmp').'</td><td><input class="flat minwidth75" type="text" name="vmp" value="'.dol_escape_htmltag($panel ? $panel->vmp : '').'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelImp').'</td><td><input class="flat minwidth75" type="text" name="imp" value="'.dol_escape_htmltag($panel ? $panel->imp : '').'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelVoc').'</td><td><input class="flat minwidth75" type="text" name="voc" value="'.dol_escape_htmltag($panel ? $panel->voc : '').'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelIsc').'</td><td><input class="flat minwidth75" type="text" name="isc" value="'.dol_escape_htmltag($panel ? $panel->isc : '').'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelModuleEfficiency').'</td><td><input class="flat minwidth75" type="text" name="module_efficiency" value="'.dol_escape_htmltag($panel ? $panel->module_efficiency : '').'"></td></tr>';
print '</table><br>';

print load_fiche_titre($langs->trans('PVPanelElectricalNOCT'), '', '');
print '<table class="noborder centpercent">';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelNOCT').'</td><td><input class="flat minwidth75" type="text" name="noct" value="'.dol_escape_htmltag($panel ? $panel->noct : '').'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelTempCoeffPmax').'</td><td><input class="flat minwidth75" type="text" name="temp_coeff_pmax" value="'.dol_escape_htmltag($panel ? $panel->temp_coeff_pmax : '').'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelTempCoeffVoc').'</td><td><input class="flat minwidth75" type="text" name="temp_coeff_voc" value="'.dol_escape_htmltag($panel ? $panel->temp_coeff_voc : '').'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelTempCoeffIsc').'</td><td><input class="flat minwidth75" type="text" name="temp_coeff_isc" value="'.dol_escape_htmltag($panel ? $panel->temp_coeff_isc : '').'"></td></tr>';
print '</table><br>';

print load_fiche_titre($langs->trans('PVPanelWarranty'), '', '');
print '<table class="noborder centpercent">';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelProductWarranty').'</td><td><input class="flat minwidth75" type="text" name="product_warranty" value="'.dol_escape_htmltag($panel ? $panel->product_warranty : '').'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelPowerWarranty').'</td><td><input class="flat minwidth75" type="text" name="power_warranty" value="'.dol_escape_htmltag($panel ? $panel->power_warranty : '').'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelFirstYearDegradation').'</td><td><input class="flat minwidth75" type="text" name="first_year_degradation" value="'.dol_escape_htmltag($panel ? $panel->first_year_degradation : '').'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelAnnualDegradation').'</td><td><input class="flat minwidth75" type="text" name="annual_degradation" value="'.dol_escape_htmltag($panel ? $panel->annual_degradation : '').'"></td></tr>';
print '</table>';
print '</div>';

print '<div class="fichehalfright">';
print load_fiche_titre($langs->trans('PVPanelMechanicalData'), '', '');
print '<table class="noborder centpercent">';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelFrontGlassThickness').'</td><td><input class="flat minwidth75" type="text" name="front_glass_thickness" value="'.dol_escape_htmltag($panel ? $panel->front_glass_thickness : '').'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelBackGlassThickness').'</td><td><input class="flat minwidth75" type="text" name="back_glass_thickness" value="'.dol_escape_htmltag($panel ? $panel->back_glass_thickness : '').'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelCableSection').'</td><td><input class="flat minwidth75" type="text" name="cable_section" value="'.dol_escape_htmltag($panel ? $panel->cable_section : '').'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelCableLength').'</td><td><input class="flat minwidth75" type="text" name="cable_length" value="'.dol_escape_htmltag($panel ? $panel->cable_length : '').'"></td></tr>';
print '</table><br>';

print load_fiche_titre($langs->trans('PVPanelMaxRatings'), '', '');
print '<table class="noborder centpercent">';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelMaxSystemVoltage').'</td><td><input class="flat minwidth75" type="text" name="max_system_voltage" value="'.dol_escape_htmltag($panel ? $panel->max_system_voltage : '').'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelMaxSeriesFuse').'</td><td><input class="flat minwidth75" type="text" name="max_series_fuse" value="'.dol_escape_htmltag($panel ? $panel->max_series_fuse : '').'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelOperatingTemperature').'</td><td><input class="flat minwidth75" type="text" name="operating_temperature" value="'.dol_escape_htmltag($panel ? $panel->operating_temperature : '').'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelSnowLoad').'</td><td><input class="flat minwidth75" type="text" name="snow_load" value="'.dol_escape_htmltag($panel ? $panel->snow_load : '').'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelWindLoad').'</td><td><input class="flat minwidth75" type="text" name="wind_load" value="'.dol_escape_htmltag($panel ? $panel->wind_load : '').'"></td></tr>';
print '</table><br>';

print load_fiche_titre($langs->trans('PVPanelPackaging'), '', '');
print '<table class="noborder centpercent">';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelModulesPerBox').'</td><td><input class="flat minwidth75" type="text" name="modules_per_box" value="'.dol_escape_htmltag($panel ? $panel->modules_per_box : '').'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVPanelModulesPerContainer40').'</td><td><input class="flat minwidth75" type="text" name="modules_per_container40" value="'.dol_escape_htmltag($panel ? $panel->modules_per_container40 : '').'"></td></tr>';
print '</table>';

print '</div>';

print '<div class="clearboth"></div>';
if ($permissiontoadd) {
	print '<div class="center">';
	print '<input type="submit" class="button button-save" value="'.$langs->trans("Save").'">';
	print '</div>';
}

print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();
