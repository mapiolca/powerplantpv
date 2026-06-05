<?php
/* Copyright (C) 2025		Pierre Ardoin				<erp@lesmetiersdubatiment.fr>
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
 *\file       htdocs/powerplantpv/product_detailedcaracteristics.php
 *\ingroup    powerplantpv
 *\brief      Product tab for PV detailed characteristics
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
dol_include_once('/powerplantpv/class/productinverter.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_powerplant.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_producttechnicalimport.lib.php');

$langs->loadLangs(array('products', 'powerplantpv@powerplantpv', 'other'));

/**
 * Cross-version CSRF token check helper.
 *
 * @return bool
 */
function powerplantpv_product_check_token()
{
	$token = GETPOST('token', 'alphanohtml');
	if (function_exists('checkToken')) {
		return checkToken();
	}
	if (function_exists('dol_verifyToken')) {
		return dol_verifyToken($token);
	}
	return (!empty($token) && !empty($_SESSION['newtoken']) && $token === $_SESSION['newtoken']);
}

/**
 * PV panel fields.
 *
 * @return array<int,string>
 */
function powerplantpv_get_pvpanel_fields()
{
	return array(
		'pmax', 'power_tolerance', 'module_efficiency', 'vmp', 'imp', 'voc', 'isc',
		'front_glass_thickness', 'back_glass_thickness', 'cable_section', 'cable_length',
		'operating_temperature', 'max_system_voltage', 'max_series_fuse', 'snow_load', 'wind_load',
		'noct', 'temp_coeff_pmax', 'temp_coeff_voc', 'temp_coeff_isc',
		'first_year_degradation', 'annual_degradation', 'product_warranty', 'power_warranty',
		'modules_per_box', 'modules_per_container40'
	);
}

/**
 * Fetch photovoltaic category code from dictionary.
 *
 * @param DoliDB $db            Database handler
 * @param int    $categoryRowId Category rowid
 * @return string
 */
function powerplantpv_get_product_category_code($db, $categoryRowId)
{
	if ($categoryRowId <= 0) {
		return '';
	}

	$sql = 'SELECT code';
	$sql .= ' FROM '.$db->prefix().'c_powerplantpv_categorypv';
	$sql .= ' WHERE rowid = '.((int) $categoryRowId);

	$resql = $db->query($sql);
	if (!$resql) {
		setEventMessages($db->lasterror(), null, 'errors');
		return '';
	}

	$obj = $db->fetch_object($resql);
	return $obj ? (string) $obj->code : '';
}

/**
 * Fetch PV panel data.
 *
 * @param DoliDB $db        Database handler
 * @param int    $productId Product id
 * @return object|null
 */
function powerplantpv_fetch_pvpanel($db, $productId)
{
	$fields = array_merge(array('rowid', 'fk_product', 'entity'), powerplantpv_get_pvpanel_fields());

	$sql = 'SELECT '.implode(', ', $fields);
	$sql .= ' FROM '.$db->prefix().'powerplantpv_product_pvpanel';
	$sql .= ' WHERE fk_product = '.((int) $productId);
	$sql .= ' AND entity IN ('.getEntity('product').')';
	$sql .= ' ORDER BY entity DESC';

	$resql = $db->query($sql);
	if (!$resql) {
		setEventMessages($db->lasterror(), null, 'errors');
		return null;
	}

	return $db->fetch_object($resql);
}

/**
 * Get a numeric POST value while preserving empty fields.
 *
 * @param string $field Field name
 * @return string|float Empty string or normalized number
 */
function powerplantpv_get_numeric_post_value($field)
{
	$value = GETPOST($field, 'alpha');
	if (trim((string) $value) === '') {
		return '';
	}

	return price2num($value, 'MT');
}

/**
 * Save PV panel data.
 *
 * @param DoliDB      $db        Database handler
 * @param Product     $product   Product object
 * @param object|null $panel     Existing panel row
 * @return int >0 if ok, <0 if error
 */
function powerplantpv_save_pvpanel($db, Product $product, $panel)
{
	global $conf;

	$data = array();
	foreach (powerplantpv_get_pvpanel_fields() as $field) {
		$data[$field] = powerplantpv_get_numeric_post_value($field);
	}

	if ($panel && !empty($panel->rowid)) {
		$sets = array();
		foreach ($data as $key => $val) {
			$sets[] = $key.' = '.($val === '' ? 'null' : price2num($val, 'MT'));
		}
		$sql = 'UPDATE '.$db->prefix().'powerplantpv_product_pvpanel SET '.implode(', ', $sets);
		$sql .= ' WHERE rowid = '.((int) $panel->rowid);
		$sql .= ' AND entity IN ('.getEntity('product').')';
	} else {
		$cols = array('fk_product', 'entity');
		$vals = array((int) $product->id, (int) $conf->entity);
		foreach ($data as $key => $val) {
			$cols[] = $key;
			$vals[] = ($val === '' ? 'null' : price2num($val, 'MT'));
		}
		$sql = 'INSERT INTO '.$db->prefix().'powerplantpv_product_pvpanel';
		$sql .= ' ('.implode(', ', $cols).') VALUES ('.implode(', ', $vals).')';
	}

	$resql = $db->query($sql);
	if (!$resql) {
		setEventMessages($db->lasterror(), null, 'errors');
		return -1;
	}

	return 1;
}

/**
 * Collect POST data according to field specs.
 *
 * @param array<string,array<string,string>> $fields Field specs
 * @return array<string,mixed>
 */
function powerplantpv_collect_post_data(array $fields)
{
	$data = array();
	foreach ($fields as $key => $spec) {
		if ($spec['type'] === 'bool') {
			$data[$key] = GETPOSTISSET($key) ? 1 : 0;
		} elseif ($spec['type'] === 'double') {
			$data[$key] = price2num(GETPOST($key, 'alpha'), 'MT');
		} elseif ($spec['type'] === 'int') {
			$value = GETPOST($key, 'alpha');
			$data[$key] = ($value === '' ? '' : (int) price2num($value, 'MT'));
		} elseif ($spec['type'] === 'text') {
			$data[$key] = GETPOST($key, 'restricthtml');
		} else {
			$data[$key] = GETPOST($key, 'nohtml');
		}
	}

	return $data;
}

/**
 * Redirect to the tab.
 *
 * @param int    $productId Product id
 * @param string $anchor    Optional anchor
 * @return void
 */
function powerplantpv_redirect_product($productId, $anchor = '')
{
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.((int) $productId).$anchor);
	exit;
}

/**
 * Get a value from an object or ProductInverter data array.
 *
 * @param object $source Source object
 * @param string $key    Field key
 * @return mixed|null
 */
function powerplantpv_get_field_value($source, $key)
{
	if ($source instanceof ProductInverter && array_key_exists($key, $source->data)) {
		return $source->data[$key];
	}
	if (is_object($source) && property_exists($source, $key)) {
		return $source->{$key};
	}
	return null;
}

/**
 * Print a standard field row.
 *
 * @param string                   $label  Translated label
 * @param string                   $name   Field name
 * @param array<string,string>     $spec   Field spec
 * @param object|null              $source Source object
 * @param bool                     $edit   Edit mode
 * @return void
 */
function powerplantpv_print_field_row($label, $name, array $spec, $source, $edit)
{
	$value = $source ? powerplantpv_get_field_value($source, $name) : null;

	print '<tr class="oddeven">';
	print '<td class="titlefield">'.$label.'</td>';
	print '<td>';
	if ($edit) {
		if ($spec['type'] === 'bool') {
			print '<input type="checkbox" class="flat" name="'.$name.'" value="1"'.((int) $value ? ' checked' : '').'>';
		} elseif ($spec['type'] === 'text') {
			print '<textarea class="flat centpercent" name="'.$name.'" rows="3">'.dol_escape_htmltag((string) $value).'</textarea>';
		} else {
			$css = ($spec['type'] === 'double' || $spec['type'] === 'int') ? 'flat maxwidth100 right' : 'flat minwidth300';
			print '<input class="'.$css.'" type="text" name="'.$name.'" value="'.dol_escape_htmltag((string) $value).'">';
		}
	} else {
		if ($value === null || $value === '') {
			print '<span class="opacitymedium">-</span>';
		} elseif ($spec['type'] === 'bool') {
			print yn((int) $value);
		} elseif ($spec['type'] === 'text') {
			print dol_htmlentitiesbr((string) $value);
		} elseif ($spec['type'] === 'double') {
			print price((float) $value);
		} else {
			print dol_escape_htmltag((string) $value);
		}
	}
	print '</td>';
	print '</tr>';
}

/**
 * Print a PV panel row.
 *
 * @param string      $label Label
 * @param string      $name  Field name
 * @param object|null $panel Panel data
 * @param bool        $edit  Edit mode
 * @return void
 */
function powerplantpv_print_pvpanel_row($label, $name, $panel, $edit)
{
	$spec = array('type' => 'double');
	powerplantpv_print_field_row($label, $name, $spec, $panel, $edit);
}

/**
 * Format a native product measure without storing a duplicate value.
 *
 * @param Product $product    Product object
 * @param string  $valueField Value field
 * @param string  $unitField  Unit field
 * @param string  $unitType   Unit type
 * @return string
 */
function powerplantpv_format_product_measure(Product $product, $valueField, $unitField, $unitType)
{
	if (!property_exists($product, $valueField) || $product->{$valueField} === '' || $product->{$valueField} === null) {
		return '';
	}

	$out = price((float) $product->{$valueField});
	if (property_exists($product, $unitField) && $product->{$unitField} !== '' && $product->{$unitField} !== null && function_exists('measuring_units_string')) {
		$unit = measuring_units_string($product->{$unitField}, $unitType, 0, 1);
		if ($unit !== '') {
			$out .= ' '.$unit;
		}
	}

	return $out;
}

/**
 * Print a read-only native product row.
 *
 * @param string $label Label
 * @param string $value Value
 * @return void
 */
function powerplantpv_print_native_product_row($label, $value)
{
	print '<tr class="oddeven">';
	print '<td class="titlefield">'.$label.'</td>';
	print '<td>'.($value !== '' ? dol_escape_htmltag($value) : '<span class="opacitymedium">-</span>').'</td>';
	print '</tr>';
}

/**
 * Print inverter general form rows.
 *
 * @param ProductInverter $inverter Inverter object
 * @param bool            $editMode Edit mode
 * @return void
 */
function powerplantpv_print_inverter_general_rows(ProductInverter $inverter, $editMode)
{
	global $langs;

	$fields = ProductInverter::getInverterFields();
	$sections = array(
		'PVInverterDCData' => array('pv_max_power', 'dc_max_voltage', 'startup_voltage', 'mppt_voltage_min', 'mppt_voltage_max', 'nominal_dc_voltage'),
		'PVInverterACData' => array('ac_nominal_power', 'ac_max_power', 'ac_apparent_power', 'ac_nominal_voltage', 'grid_frequency', 'ac_max_output_current', 'power_factor', 'thd'),
		'PVInverterEfficiency' => array('max_efficiency', 'european_efficiency'),
		'PVInverterProtections' => array('dc_switch', 'dc_spd', 'ac_spd', 'afci', 'pid_recovery', 'anti_islanding', 'dc_reverse_polarity_protection', 'insulation_monitoring', 'residual_current_monitoring'),
		'PVInverterEnvironmentCommunication' => array('ip_rating', 'operating_temperature', 'relative_humidity', 'cooling', 'max_altitude', 'noise', 'topology', 'night_consumption', 'display_type', 'communication_interfaces', 'dc_connector', 'ac_connector', 'mounting', 'warranty', 'certifications'),
	);

	$half = 0;
	foreach ($sections as $sectionLabel => $sectionFields) {
		if ($half === 0) {
			print '<div class="fichehalfleft">';
		} elseif ($half === 3) {
			print '</div><div class="fichehalfright">';
		}

		print load_fiche_titre($langs->trans($sectionLabel), '', '');
		print '<table class="noborder centpercent">';
		foreach ($sectionFields as $field) {
			powerplantpv_print_field_row($langs->trans($fields[$field]['label']), $field, $fields[$field], $inverter, $editMode);
		}
		print '</table><br>';
		$half++;
	}
	print '</div><div class="clearboth"></div>';
}

/**
 * Print MPPT or PV input edit form.
 *
 * @param string      $saveAction      Save action
 * @param int         $productId       Product id
 * @param int         $mpptId          MPPT id
 * @param int         $inputId         PV input id
 * @param object|null $row             Existing row
 * @param array<string,array<string,string>> $fields Field specs
 * @param int         $defaultPosition Default position
 * @return void
 */
function powerplantpv_print_composition_form($saveAction, $productId, $mpptId, $inputId, $row, array $fields, $defaultPosition)
{
	global $langs;

	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.((int) $productId).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="'.$saveAction.'">';
	if ($mpptId > 0) {
		print '<input type="hidden" name="mpptid" value="'.((int) $mpptId).'">';
	}
	if ($inputId > 0) {
		print '<input type="hidden" name="inputid" value="'.((int) $inputId).'">';
	}
	print '<table class="noborder centpercent">';
	foreach ($fields as $key => $spec) {
		if (!$row && $key === 'position') {
			$row = new stdClass();
			$row->position = $defaultPosition;
		}
		powerplantpv_print_field_row($langs->trans($spec['label']), $key, $spec, $row, true);
	}
	print '</table>';
	print '<div class="tabsAction">';
	print '<input type="submit" class="butAction" value="'.$langs->trans('Save').'">';
	print dolGetButtonAction($langs->trans('Cancel'), '', 'default', $_SERVER['PHP_SELF'].'?id='.((int) $productId), '', true);
	print '</div>';
	print '</form>';
}

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$mpptid = GETPOSTINT('mpptid');
$inputid = GETPOSTINT('inputid');

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

$mutatingActions = array('save', 'save_panel', 'save_inverter', 'save_mppt', 'confirm_delete_mppt', 'save_input', 'confirm_delete_input');
if (in_array($action, $mutatingActions) && !$permissiontoadd) {
	accessforbidden();
}
if (in_array($action, $mutatingActions) && !powerplantpv_product_check_token()) {
	accessforbidden('Bad token');
}

$object->fetch_optionals($object->id, null);
$categoryRowId = !empty($object->array_options['options_categorie_photovoltaique']) ? (int) $object->array_options['options_categorie_photovoltaique'] : 0;
$categoryCode = powerplantpv_get_product_category_code($db, $categoryRowId);
$isPVPanel = ($categoryCode === 'MODULE');
$isInverter = ($categoryCode === 'ONDULE');
$hasDetailedCharacteristics = ($isPVPanel || $isInverter);
$hasTechnicalImportSource = (
	getDolGlobalInt('POWERPLANTPV_PVFREE_ENABLED')
	|| getDolGlobalInt('POWERPLANTPV_COMPONENT_IMPORT_CSV_ENABLED', 1)
	|| getDolGlobalInt('POWERPLANTPV_COMPONENT_IMPORT_XLSX_ENABLED', 1)
);
$showTechnicalImportButton = ($permissiontoadd && $hasDetailedCharacteristics && $hasTechnicalImportSource);

$form = new Form($db);
$panel = null;
$inverter = new ProductInverter($db);

if ($isPVPanel) {
	$panel = powerplantpv_fetch_pvpanel($db, $object->id);
}
if ($isInverter) {
	$inverter->fetchByProduct($object->id);
}

if ($isPVPanel && ($action === 'save' || $action === 'save_panel')) {
	$result = powerplantpv_save_pvpanel($db, $object, $panel);
	if ($result > 0) {
		$resultrecalculate = powerplantRecalculateInstalledPowerForProduct($object->id);
		$resultcommercialrecalculate = powerplantpvRecalculateCommercialDocumentPeakPowerForProduct($object->id);
		if ($resultrecalculate < 0 || $resultcommercialrecalculate < 0) {
			$panel = powerplantpv_fetch_pvpanel($db, $object->id);
			$errors = array();
			if ($resultrecalculate < 0) {
				$errors[] = $langs->trans('PowerPlantInstalledPowerRecalculationError');
			}
			if ($resultcommercialrecalculate < 0) {
				$errors[] = powerplantpvBuildPeakPowerRecalculationErrorMessage(!empty($user->admin));
			}
			setEventMessages('', $errors, 'errors');
			$action = 'edit_panel';
		} else {
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
			powerplantpv_redirect_product($object->id);
		}
	}
	$action = 'edit_panel';
}

if ($isInverter && $action === 'save_inverter') {
	$data = powerplantpv_collect_post_data(ProductInverter::getInverterFields());
	$result = $inverter->saveForProduct($object->id, $data, $user);
	if ($result > 0) {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		powerplantpv_redirect_product($object->id);
	}
	setEventMessages($inverter->error, $inverter->errors, 'errors');
	$action = 'edit_inverter';
}

if ($isInverter && $action === 'save_mppt') {
	$inverterId = $inverter->ensureForProduct($object->id, $user);
	if ($inverterId < 0) {
		setEventMessages($inverter->error, $inverter->errors, 'errors');
		$action = 'create_mppt';
	} else {
		if ($mpptid > 0 && !$inverter->fetchMppt($mpptid, $inverterId)) {
			accessforbidden();
		}
		$data = powerplantpv_collect_post_data(ProductInverter::getMpptFields());
		$result = $inverter->saveMppt($inverterId, $mpptid, $data);
		if ($result > 0) {
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
			powerplantpv_redirect_product($object->id, '#mppt');
		}
		setEventMessages($inverter->error, $inverter->errors, 'errors');
		$action = ($mpptid > 0 ? 'edit_mppt' : 'create_mppt');
	}
}

if ($isInverter && $action === 'confirm_delete_mppt') {
	if (empty($inverter->id) || !$inverter->fetchMppt($mpptid, $inverter->id)) {
		accessforbidden();
	}

	$db->begin();
	$result = $inverter->deleteMppt($mpptid, $inverter->id);
	if ($result > 0) {
		$db->commit();
		setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
		powerplantpv_redirect_product($object->id, '#mppt');
	}
	$db->rollback();
	setEventMessages($inverter->error, $inverter->errors, 'errors');
}

if ($isInverter && $action === 'save_input') {
	if (empty($inverter->id)) {
		accessforbidden();
	}
	$mppt = $inverter->fetchMppt($mpptid, $inverter->id);
	if (!$mppt) {
		accessforbidden();
	}
	if ($inputid > 0 && !$inverter->fetchInput($inputid, $mpptid)) {
		accessforbidden();
	}

	$data = powerplantpv_collect_post_data(ProductInverter::getPvInputFields());
	$result = $inverter->saveInput($mpptid, $inputid, $data);
	if ($result > 0) {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		powerplantpv_redirect_product($object->id, '#mppt_'.$mpptid);
	}
	setEventMessages($inverter->error, $inverter->errors, 'errors');
	$action = ($inputid > 0 ? 'edit_input' : 'create_input');
}

if ($isInverter && $action === 'confirm_delete_input') {
	if (empty($inverter->id)) {
		accessforbidden();
	}
	$mppt = $inverter->fetchMppt($mpptid, $inverter->id);
	$input = $inverter->fetchInput($inputid, $mpptid);
	if (!$mppt || !$input) {
		accessforbidden();
	}

	$db->begin();
	$result = $inverter->deleteInput($inputid, $mpptid);
	if ($result > 0) {
		$db->commit();
		setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
		powerplantpv_redirect_product($object->id, '#mppt_'.$mpptid);
	}
	$db->rollback();
	setEventMessages($inverter->error, $inverter->errors, 'errors');
}

if ($isPVPanel) {
	$panel = powerplantpv_fetch_pvpanel($db, $object->id);
}
if ($isInverter) {
	$inverter->fetchByProduct($object->id);
}

$helpurl = '';
$shortlabel = dol_trunc($object->label, 16);
$title = $langs->trans('Product').' '.$shortlabel.' - '.$langs->trans('PVPanelTabTitle');
$helpurl = 'EN:Module_Products|FR:Module_Produits|ES:M&oacute;dulo_Productos';

llxHeader('', $title, $helpurl, '', 0, 0, '', '', '', 'mod-product page-card_product_detailedcaracteristics');

$head = product_prepare_head($object);
$productpicto = (method_exists($object, 'isService') && $object->isService()) ? 'service' : 'product';

print dol_get_fiche_head($head, 'pvpanel', $langs->trans('Product'), -1, $productpicto);

if ($isInverter && $action === 'delete_mppt' && $mpptid > 0) {
	$mpptToDelete = (!empty($inverter->id) ? $inverter->fetchMppt($mpptid, $inverter->id) : null);
	if ($mpptToDelete) {
		$confirmUrl = $_SERVER['PHP_SELF'].'?id='.$object->id.'&mpptid='.$mpptid.'&token='.newToken();
		print $form->formconfirm($confirmUrl, $langs->trans('PVInverterDeleteMPPT'), $langs->trans('PVInverterConfirmDeleteMPPT'), 'confirm_delete_mppt', '', 0, 1);
	}
}

if ($isInverter && $action === 'delete_input' && $inputid > 0 && $mpptid > 0) {
	$inputToDelete = $inverter->fetchInput($inputid, $mpptid);
	if ($inputToDelete) {
		$confirmUrl = $_SERVER['PHP_SELF'].'?id='.$object->id.'&mpptid='.$mpptid.'&inputid='.$inputid.'&token='.newToken();
		print $form->formconfirm($confirmUrl, $langs->trans('PVInverterDeletePVInput'), $langs->trans('PVInverterConfirmDeletePVInput'), 'confirm_delete_input', '', 0, 1);
	}
}

$linkback = '<a href="'.DOL_URL_ROOT.'/product/list.php?restore_lastsearch_values=1&type='.$object->type.'">'.$langs->trans('BackToList').'</a>';
$object->next_prev_filter = ' fk_product_type = '.((int) $object->type);
$shownav = 1;
if ($user->socid && !in_array('product', explode(',', getDolGlobalString('MAIN_MODULES_FOR_EXTERNAL')))) {
	$shownav = 0;
}

dol_banner_tab($object, 'ref', $linkback, $shownav, 'ref');
print dol_get_fiche_end();

if (!$hasDetailedCharacteristics) {
	print '<div class="info">'.$langs->trans('NoDetailedCharacteristicsForProductType').'</div>';
	llxFooter();
	$db->close();
	exit;
}

if ($isPVPanel) {
	$editmode = ($action === 'edit' || $action === 'edit_panel');

	if ($editmode) {
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="save_panel">';
	}

	print '<div class="fichehalfleft">';
	print load_fiche_titre($langs->trans('PVPanelElectricalSTC'), '', '');
	print '<table class="noborder centpercent">';
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelNominalPower'), 'pmax', $panel, $editmode);
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelPowerTolerance'), 'power_tolerance', $panel, $editmode);
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelVmp'), 'vmp', $panel, $editmode);
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelImp'), 'imp', $panel, $editmode);
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelVoc'), 'voc', $panel, $editmode);
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelIsc'), 'isc', $panel, $editmode);
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelModuleEfficiency'), 'module_efficiency', $panel, $editmode);
	print '</table><br>';

	print load_fiche_titre($langs->trans('PVPanelElectricalNOCT'), '', '');
	print '<table class="noborder centpercent">';
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelNOCT'), 'noct', $panel, $editmode);
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelTempCoeffPmax'), 'temp_coeff_pmax', $panel, $editmode);
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelTempCoeffVoc'), 'temp_coeff_voc', $panel, $editmode);
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelTempCoeffIsc'), 'temp_coeff_isc', $panel, $editmode);
	print '</table><br>';

	print load_fiche_titre($langs->trans('PVPanelWarranty'), '', '');
	print '<table class="noborder centpercent">';
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelProductWarranty'), 'product_warranty', $panel, $editmode);
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelPowerWarranty'), 'power_warranty', $panel, $editmode);
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelFirstYearDegradation'), 'first_year_degradation', $panel, $editmode);
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelAnnualDegradation'), 'annual_degradation', $panel, $editmode);
	print '</table>';
	print '</div>';

	print '<div class="fichehalfright">';
	print load_fiche_titre($langs->trans('PVPanelMechanicalData'), '', '');
	print '<table class="noborder centpercent">';
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelFrontGlassThickness'), 'front_glass_thickness', $panel, $editmode);
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelBackGlassThickness'), 'back_glass_thickness', $panel, $editmode);
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelCableSection'), 'cable_section', $panel, $editmode);
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelCableLength'), 'cable_length', $panel, $editmode);
	print '</table><br>';

	print load_fiche_titre($langs->trans('PVPanelMaxRatings'), '', '');
	print '<table class="noborder centpercent">';
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelMaxSystemVoltage'), 'max_system_voltage', $panel, $editmode);
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelMaxSeriesFuse'), 'max_series_fuse', $panel, $editmode);
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelOperatingTemperature'), 'operating_temperature', $panel, $editmode);
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelSnowLoad'), 'snow_load', $panel, $editmode);
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelWindLoad'), 'wind_load', $panel, $editmode);
	print '</table><br>';

	print load_fiche_titre($langs->trans('PVPanelPackaging'), '', '');
	print '<table class="noborder centpercent">';
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelModulesPerBox'), 'modules_per_box', $panel, $editmode);
	powerplantpv_print_pvpanel_row($langs->trans('PVPanelModulesPerContainer40'), 'modules_per_container40', $panel, $editmode);
	print '</table>';
	print '</div>';

	print '<div class="clearboth"></div>';
	print '<div class="tabsAction">';
	if (!$editmode) {
		if ($permissiontoadd) {
			print dolGetButtonAction($langs->trans('Modify'), '', 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=edit_panel', '', true);
		}
		if ($showTechnicalImportButton) {
			print dolGetButtonAction($langs->trans('ProductTechnicalImportButton'), '', 'default', dol_buildpath('/powerplantpv/product_technical_import.php', 1).'?id='.$object->id, 'producttechnicalimport-btn-panel', true);
		}
	} else {
		print '<input type="submit" class="butAction" value="'.$langs->trans('Save').'">';
		print dolGetButtonAction($langs->trans('Cancel'), '', 'default', $_SERVER['PHP_SELF'].'?id='.$object->id, '', true);
	}
	print '</div>';

	if ($editmode) {
		print '</form>';
	}
}

if ($isInverter) {
	$editmode = ($action === 'edit_inverter');

	print load_fiche_titre($langs->trans('PVInverterDolibarrProductData'), '', '');
	print '<table class="noborder centpercent">';
	powerplantpv_print_native_product_row($langs->trans('Weight'), powerplantpv_format_product_measure($object, 'weight', 'weight_units', 'weight'));
	powerplantpv_print_native_product_row($langs->trans('Length'), powerplantpv_format_product_measure($object, 'length', 'length_units', 'size'));
	powerplantpv_print_native_product_row($langs->trans('Width'), powerplantpv_format_product_measure($object, 'width', 'width_units', 'size'));
	powerplantpv_print_native_product_row($langs->trans('Height'), powerplantpv_format_product_measure($object, 'height', 'height_units', 'size'));
	powerplantpv_print_native_product_row($langs->trans('Surface'), powerplantpv_format_product_measure($object, 'surface', 'surface_units', 'surface'));
	powerplantpv_print_native_product_row($langs->trans('Volume'), powerplantpv_format_product_measure($object, 'volume', 'volume_units', 'volume'));
	print '</table><br>';

	if ($editmode) {
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="save_inverter">';
	}
	powerplantpv_print_inverter_general_rows($inverter, $editmode);
	print '<div class="tabsAction">';
	if (!$editmode) {
		if ($permissiontoadd) {
			print dolGetButtonAction($langs->trans('Modify'), '', 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=edit_inverter', '', true);
		}
		if ($showTechnicalImportButton) {
			print dolGetButtonAction($langs->trans('ProductTechnicalImportButton'), '', 'default', dol_buildpath('/powerplantpv/product_technical_import.php', 1).'?id='.$object->id, 'producttechnicalimport-btn-inverter', true);
		}
	} else {
		print '<input type="submit" class="butAction" value="'.$langs->trans('Save').'">';
		print dolGetButtonAction($langs->trans('Cancel'), '', 'default', $_SERVER['PHP_SELF'].'?id='.$object->id, '', true);
	}
	print '</div>';
	if ($editmode) {
		print '</form>';
	}

	$mppts = !empty($inverter->id) ? $inverter->fetchMppts($inverter->id) : array();
	$mpptIds = array();
	foreach ($mppts as $mppt) {
		$mpptIds[] = (int) $mppt->rowid;
	}
	$inputsByMppt = $inverter->fetchInputsByMppts($mpptIds);

	$newMpptButton = '';
	if ($permissiontoadd) {
		$newMpptButton = dolGetButtonTitle($langs->trans('PVInverterAddMPPT'), '', 'fa fa-plus-circle', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=create_mppt', '', 1);
	}
	print '<a id="mppt"></a>';
	print load_fiche_titre($langs->trans('PVInverterMPPTComposition'), $newMpptButton, '');

	if ($permissiontoadd && $action === 'create_mppt') {
		powerplantpv_print_composition_form('save_mppt', $object->id, 0, 0, null, ProductInverter::getMpptFields(), count($mppts) + 1);
	}

	$editMppt = null;
	if ($permissiontoadd && $action === 'edit_mppt' && $mpptid > 0 && !empty($inverter->id)) {
		$editMppt = $inverter->fetchMppt($mpptid, $inverter->id);
		if ($editMppt) {
			powerplantpv_print_composition_form('save_mppt', $object->id, $mpptid, 0, $editMppt, ProductInverter::getMpptFields(), (int) $editMppt->position);
		}
	}

	if (empty($mppts)) {
		print '<div class="opacitymedium">'.$langs->trans('PVInverterNoMPPT').'</div>';
	} else {
		foreach ($mppts as $mppt) {
			$mpptTitle = $mppt->label ? $mppt->label : $langs->trans('PVInverterMPPT').' '.((int) $mppt->position);
			$mpptActions = '';
			if ($permissiontoadd) {
				$mpptActions .= '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=edit_mppt&mpptid='.$mppt->rowid.'">'.img_edit($langs->trans('Modify'), 0).'</a>';
				$mpptActions .= ' <a class="reposition marginleftonly" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=delete_mppt&mpptid='.$mppt->rowid.'">'.img_delete($langs->trans('Delete'), 0).'</a>';
			}

			print '<a id="mppt_'.$mppt->rowid.'"></a>';
			print load_fiche_titre(dol_escape_htmltag($mpptTitle), $mpptActions, '');
			print '<table class="noborder centpercent">';
			foreach (ProductInverter::getMpptFields() as $key => $spec) {
				powerplantpv_print_field_row($langs->trans($spec['label']), $key, $spec, $mppt, false);
			}
			print '</table>';

			$addInputButton = '';
			if ($permissiontoadd) {
				$addInputButton = dolGetButtonTitle($langs->trans('PVInverterAddPVInput'), '', 'fa fa-plus-circle', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=create_input&mpptid='.$mppt->rowid, '', 1);
			}
			print load_fiche_titre($langs->trans('PVInverterPVInputs'), $addInputButton, '');

			if ($permissiontoadd && $action === 'create_input' && $mpptid === (int) $mppt->rowid) {
				$currentInputs = !empty($inputsByMppt[(int) $mppt->rowid]) ? $inputsByMppt[(int) $mppt->rowid] : array();
				powerplantpv_print_composition_form('save_input', $object->id, (int) $mppt->rowid, 0, null, ProductInverter::getPvInputFields(), count($currentInputs) + 1);
			}

			if ($permissiontoadd && $action === 'edit_input' && $mpptid === (int) $mppt->rowid && $inputid > 0) {
				$editInput = $inverter->fetchInput($inputid, (int) $mppt->rowid);
				if ($editInput) {
					powerplantpv_print_composition_form('save_input', $object->id, (int) $mppt->rowid, $inputid, $editInput, ProductInverter::getPvInputFields(), (int) $editInput->position);
				}
			}

			print '<div class="div-table-responsive-no-min">';
			print '<table class="noborder centpercent">';
			print '<tr class="liste_titre">';
			print '<td>'.$langs->trans('PVInverterPVInputPosition').'</td>';
			print '<td>'.$langs->trans('PVInverterPVInputLabel').'</td>';
			print '<td class="right">'.$langs->trans('PVInverterPVInputMaxInputCurrent').'</td>';
			print '<td class="right">'.$langs->trans('PVInverterPVInputMaxShortCircuitCurrent').'</td>';
			print '<td>'.$langs->trans('PVInverterPVInputConnectorType').'</td>';
			print '<td>'.$langs->trans('PVInverterNotes').'</td>';
			print '<td class="right"></td>';
			print '</tr>';

			$inputs = !empty($inputsByMppt[(int) $mppt->rowid]) ? $inputsByMppt[(int) $mppt->rowid] : array();
			if (empty($inputs)) {
				print '<tr class="oddeven"><td colspan="7" class="opacitymedium">'.$langs->trans('PVInverterNoPVInput').'</td></tr>';
			} else {
				foreach ($inputs as $input) {
					$inputLabel = ($input->label !== null && $input->label !== '') ? dol_escape_htmltag($input->label) : '<span class="opacitymedium">-</span>';
					$connectorType = ($input->connector_type !== null && $input->connector_type !== '') ? dol_escape_htmltag($input->connector_type) : '<span class="opacitymedium">-</span>';
					$inputNote = ($input->note_private !== null && $input->note_private !== '') ? dol_htmlentitiesbr($input->note_private) : '<span class="opacitymedium">-</span>';
					print '<tr class="oddeven">';
					print '<td>'.((int) $input->position).'</td>';
					print '<td>'.$inputLabel.'</td>';
					print '<td class="right">'.($input->max_input_current !== null && $input->max_input_current !== '' ? price((float) $input->max_input_current) : '<span class="opacitymedium">-</span>').'</td>';
					print '<td class="right">'.($input->max_short_circuit_current !== null && $input->max_short_circuit_current !== '' ? price((float) $input->max_short_circuit_current) : '<span class="opacitymedium">-</span>').'</td>';
					print '<td>'.$connectorType.'</td>';
					print '<td>'.$inputNote.'</td>';
					print '<td class="right nowrap">';
					if ($permissiontoadd) {
						print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=edit_input&mpptid='.$mppt->rowid.'&inputid='.$input->rowid.'">'.img_edit($langs->trans('Modify'), 0).'</a>';
						print ' <a class="reposition marginleftonly" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=delete_input&mpptid='.$mppt->rowid.'&inputid='.$input->rowid.'">'.img_delete($langs->trans('Delete'), 0).'</a>';
					}
					print '</td>';
					print '</tr>';
				}
			}
			print '</table>';
			print '</div><br>';
		}
	}
}

if ($showTechnicalImportButton) {
	powerplantpvProductTechnicalImportPrintDialog($object, $categoryCode, false);
}

llxFooter();
$db->close();
