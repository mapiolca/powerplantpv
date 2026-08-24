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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file       product_technical_import.php
 * \ingroup    powerplantpv
 * \brief      Import product detailed characteristics from CSV/XLSX files.
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
dol_include_once('/powerplantpv/class/powerplantpvfileimport.class.php');
dol_include_once('/powerplantpv/class/powerplantpvproductimport.class.php');
dol_include_once('/powerplantpv/class/productinverter.class.php');
dol_include_once('/powerplantpv/class/productbattery.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv_producttechnicalimport.lib.php');

$langs->loadLangs(array('products', 'powerplantpv@powerplantpv', 'other'));

/**
 * Cross-version CSRF token check helper.
 *
 * @return bool
 */
function powerplantpv_technical_import_check_token()
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
 * Translate error keys.
 *
 * @param array<int,string> $errors Error keys
 * @return array<int,string> Translated errors
 */
function powerplantpv_technical_import_translate_errors(array $errors)
{
	global $langs;

	$translated = array();
	foreach (array_values(array_unique($errors)) as $error) {
		$translated[] = $langs->trans($error);
	}

	return $translated;
}

/**
 * Fetch photovoltaic category code from dictionary.
 *
 * @param DoliDB $db            Database handler
 * @param int    $categoryRowId Category rowid
 * @return string Category code
 */
function powerplantpv_technical_import_get_product_category_code($db, $categoryRowId)
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
 * Return import temp directory.
 *
 * @return string Directory
 */
function powerplantpv_technical_import_temp_dir()
{
	global $conf;

	if (!empty($conf->powerplantpv->multidir_output[$conf->entity])) {
		return $conf->powerplantpv->multidir_output[$conf->entity].'/temp/producttechnicalimport';
	}
	if (!empty($conf->powerplantpv->dir_output)) {
		return $conf->powerplantpv->dir_output.'/temp/producttechnicalimport';
	}

	return DOL_DATA_ROOT.'/powerplantpv/temp/producttechnicalimport';
}

/**
 * Generate a temporary import token.
 *
 * @return string Token
 */
function powerplantpv_technical_import_generate_token()
{
	try {
		return bin2hex(random_bytes(16));
	} catch (Throwable $e) {
		return sha1(uniqid('', true));
	}
}

/**
 * Return metadata path for a token.
 *
 * @param string $token Token
 * @return string Path
 */
function powerplantpv_technical_import_meta_path($token)
{
	if (!preg_match('/^[a-f0-9]{32,40}$/', (string) $token)) {
		return '';
	}

	return powerplantpv_technical_import_temp_dir().'/'.$token.'.json';
}

/**
 * Delete temporary import files.
 *
 * @param array<string,mixed>|null $metadata Metadata
 * @param string                   $token    Token
 * @return void
 */
function powerplantpv_technical_import_delete_temp($metadata, $token)
{
	$dir = powerplantpv_technical_import_temp_dir();
	if (is_array($metadata) && !empty($metadata['filepath']) && strpos((string) $metadata['filepath'], $dir.'/') === 0 && file_exists((string) $metadata['filepath'])) {
		dol_delete_file((string) $metadata['filepath']);
	}
	$metapath = powerplantpv_technical_import_meta_path($token);
	if ($metapath !== '' && file_exists($metapath)) {
		dol_delete_file($metapath);
	}
}

/**
 * Purge stale temporary import files.
 *
 * @return void
 */
function powerplantpv_technical_import_purge_old_temp()
{
	$dir = powerplantpv_technical_import_temp_dir();
	if (!is_dir($dir)) {
		return;
	}

	$limit = dol_now() - 86400;
	foreach ((array) glob($dir.'/*') as $path) {
		if (is_file($path) && @filemtime($path) < $limit) {
			dol_delete_file($path);
		}
	}
}

/**
 * Load metadata and check ownership.
 *
 * @param string $token     Token
 * @param int    $productId Product id
 * @return array<string,mixed>|false Metadata
 */
function powerplantpv_technical_import_load_metadata($token, $productId)
{
	global $conf, $user, $langs;

	$metapath = powerplantpv_technical_import_meta_path($token);
	if ($metapath === '' || !is_readable($metapath)) {
		setEventMessages($langs->trans('ProductTechnicalImportSessionExpired'), null, 'errors');
		return false;
	}

	$data = json_decode((string) file_get_contents($metapath), true);
	if (!is_array($data)
		|| (int) ($data['entity'] ?? 0) !== (int) $conf->entity
		|| (int) ($data['user_id'] ?? 0) !== (int) $user->id
		|| (int) ($data['product_id'] ?? 0) !== (int) $productId
	) {
		accessforbidden();
	}
	if (empty($data['filepath']) || !is_readable((string) $data['filepath']) || empty($data['file_sha256'])) {
		setEventMessages($langs->trans('ProductTechnicalImportSessionExpired'), null, 'errors');
		return false;
	}
	$currentHash = hash_file('sha256', (string) $data['filepath']);
	if (!is_string($currentHash) || !hash_equals((string) $data['file_sha256'], $currentHash)) {
		setEventMessages($langs->trans('ProductTechnicalImportFileChanged'), null, 'errors');
		return false;
	}

	return $data;
}

/**
 * Save metadata.
 *
 * @param string              $token Token
 * @param array<string,mixed> $data  Metadata
 * @return bool True if saved
 */
function powerplantpv_technical_import_save_metadata($token, array $data)
{
	$metapath = powerplantpv_technical_import_meta_path($token);
	if ($metapath === '') {
		return false;
	}

	$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	if ($json === false) {
		return false;
	}

	return (bool) file_put_contents($metapath, $json);
}

/**
 * Return a field label.
 *
 * @param string $field Field
 * @param string $type  Product PV type
 * @return string Label
 */
function powerplantpv_technical_import_field_label($field, $type)
{
	global $langs;

	if (preg_match('/^mppt_([0-9]+)\.input_([0-9]+)\.(.+)$/', (string) $field, $matches)) {
		$inputfields = ProductInverter::getPvInputFields();
		$fieldkey = (string) $matches[3];
		$fieldlabel = isset($inputfields[$fieldkey]['label']) ? $langs->trans($inputfields[$fieldkey]['label']) : dol_escape_htmltag($fieldkey);
		return $langs->trans('PVInverterMPPT').' '.((int) $matches[1]).' / '.$langs->trans('PowerPlantPVPVInput').' '.((int) $matches[2]).' - '.$fieldlabel;
	}

	if (preg_match('/^mppt_([0-9]+)\.(.+)$/', (string) $field, $matches)) {
		$mpptfields = ProductInverter::getMpptFields();
		$fieldkey = (string) $matches[2];
		$fieldlabel = isset($mpptfields[$fieldkey]['label']) ? $langs->trans($mpptfields[$fieldkey]['label']) : dol_escape_htmltag($fieldkey);
		return $langs->trans('PVInverterMPPT').' '.((int) $matches[1]).' - '.$fieldlabel;
	}

	$modulelabels = array(
		'pmax' => 'PVPanelNominalPower',
		'power_tolerance_min' => 'PVPanelPowerTolerance',
		'power_tolerance_max' => 'PVPanelPowerTolerance',
		'module_efficiency' => 'PVPanelModuleEfficiency',
		'vmp' => 'PVPanelVmp',
		'imp' => 'PVPanelImp',
		'voc' => 'PVPanelVoc',
		'isc' => 'PVPanelIsc',
		'front_glass_thickness' => 'PVPanelFrontGlassThickness',
		'back_glass_thickness' => 'PVPanelBackGlassThickness',
		'cable_section' => 'PVPanelCableSection',
		'cable_length' => 'PVPanelCableLength',
		'noct' => 'PVPanelNOCT',
		'temp_coeff_pmax' => 'PVPanelTempCoeffPmax',
		'temp_coeff_voc' => 'PVPanelTempCoeffVoc',
		'temp_coeff_isc' => 'PVPanelTempCoeffIsc',
		'max_system_voltage' => 'PVPanelMaxSystemVoltage',
		'max_series_fuse' => 'PVPanelMaxSeriesFuse',
		'operating_temperature_min' => 'PVPanelOperatingTemperature',
		'operating_temperature_max' => 'PVPanelOperatingTemperature',
		'snow_load' => 'PVPanelSnowLoad',
		'wind_load' => 'PVPanelWindLoad',
		'product_warranty' => 'PVPanelProductWarranty',
		'power_warranty' => 'PVPanelPowerWarranty',
		'first_year_degradation' => 'PVPanelFirstYearDegradation',
		'annual_degradation' => 'PVPanelAnnualDegradation',
		'modules_per_box' => 'PVPanelModulesPerBox',
		'modules_per_container40' => 'PVPanelModulesPerContainer40',
	);

	if ($type === 'MODULE' && isset($modulelabels[$field])) {
		$label = $langs->trans($modulelabels[$field]);
		if (substr($field, -4) === '_min') {
			$label .= ' - '.$langs->trans('Minimum');
		} elseif (substr($field, -4) === '_max') {
			$label .= ' - '.$langs->trans('Maximum');
		}
		return $label;
	}
	if ($type === 'BATTER') {
		$attributelabels = array(
			'battery_attribute_protocol' => 'BatteryProtocols',
			'battery_attribute_protection' => 'BatteryProtections',
			'battery_attribute_certification' => 'BatteryCertifications',
		);
		if (isset($attributelabels[$field])) {
			return $langs->trans($attributelabels[$field]);
		}
		$batteryfields = ProductBattery::getBatteryFields();
		if (isset($batteryfields[$field]['label'])) {
			if (!empty($batteryfields[$field]['group'])) {
				$batterygroups = array('voltage' => 'BatteryVoltageRange', 'operating_temperature' => 'BatteryOperatingTemperatureRange', 'storage_temperature' => 'BatteryStorageTemperatureRange', 'humidity' => 'BatteryHumidityRange', 'noise' => 'BatteryNoise');
				$group = (string) $batteryfields[$field]['group'];
				return $langs->trans(isset($batterygroups[$group]) ? $batterygroups[$group] : $group).' - '.$langs->trans($batteryfields[$field]['label']);
			}
			return $langs->trans($batteryfields[$field]['label']);
		}
	}

	$inverterfields = ProductInverter::getInverterFields();
	if (isset($inverterfields[$field]['label'])) {
		if (!empty($inverterfields[$field]['group'])) {
			$invertergroups = array('ac_voltage' => 'PVInverterACVoltageRange', 'grid_frequency' => 'PVInverterGridFrequency', 'power_factor' => 'PVInverterPowerFactor', 'thd' => 'PVInverterTHD', 'backup_voltage' => 'PVInverterBackupVoltageRange', 'backup_thd' => 'PVInverterBackupTHD', 'operating_temperature' => 'PVInverterOperatingTemperature', 'relative_humidity' => 'PVInverterRelativeHumidity', 'noise' => 'PVInverterNoise');
			$group = (string) $inverterfields[$field]['group'];
			return $langs->trans(isset($invertergroups[$group]) ? $invertergroups[$group] : $group).' - '.$langs->trans($inverterfields[$field]['label']);
		}
		return $langs->trans($inverterfields[$field]['label']);
	}

	return dol_escape_htmltag($field);
}

/**
 * Format a preview value.
 *
 * @param mixed $value Value
 * @return string HTML
 */
function powerplantpv_technical_import_format_value($value)
{
	if ($value === null || $value === '') {
		return '<span class="opacitymedium">-</span>';
	}
	if (is_numeric($value)) {
		return price((float) $value);
	}

	return dol_escape_htmltag((string) $value);
}

/**
 * Print preview changes or ignored fields.
 *
 * @param string                            $title      Title key
 * @param array<string,array<string,mixed>> $rows       Preview rows
 * @param string                            $type       Product PV type
 * @param bool                              $withReason Show reason
 * @param string                            $picto      Title picto
 * @return void
 */
function powerplantpv_technical_import_print_preview_rows($title, array $rows, $type, $withReason = false, $picto = '')
{
	global $langs;

	print load_fiche_titre($langs->trans($title), '', $picto);
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('Field').'</td>';
	print '<td>'.$langs->trans('ProductTechnicalImportCurrentValue').'</td>';
	print '<td>'.$langs->trans('ProductTechnicalImportFileValue').'</td>';
	if ($withReason) {
		print '<td>'.$langs->trans('Reason').'</td>';
	}
	print '</tr>';

	if (empty($rows)) {
		print '<tr class="oddeven"><td colspan="'.($withReason ? 4 : 3).'" class="opacitymedium">'.$langs->trans('None').'</td></tr>';
	} else {
		foreach ($rows as $field => $row) {
			print '<tr class="oddeven">';
			print '<td>'.powerplantpv_technical_import_field_label($field, $type).'</td>';
			print '<td>'.powerplantpv_technical_import_format_value(isset($row['current']) ? $row['current'] : null).'</td>';
			print '<td>'.powerplantpv_technical_import_format_value(isset($row['proposed']) ? $row['proposed'] : null).'</td>';
			if ($withReason) {
				print '<td>'.$langs->trans(isset($row['reason']) ? $row['reason'] : '').'</td>';
			}
			print '</tr>';
		}
	}

	print '</table>';
	print '</div>';
}

/**
 * Print a JSON block.
 *
 * @param string              $title Title key
 * @param array<string,mixed> $data  Data
 * @return void
 */
function powerplantpv_technical_import_print_json_block($title, array $data)
{
	global $langs;

	$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	if ($json === false) {
		$json = '';
	}

	print load_fiche_titre($langs->trans($title), '', '');
	print '<pre class="centpercent" style="max-height: 360px; overflow: auto;">'.dol_escape_htmltag($json).'</pre>';
}

/**
 * Build source trace data for file import.
 *
 * @param array<string,mixed> $metadata  Metadata
 * @param int                 $lineIndex Selected line index
 * @param string              $type      module|inverter|battery
 * @return array<string,mixed> Source data
 */
function powerplantpv_technical_import_source_data(array $metadata, $lineIndex, $type)
{
	$filehash = isset($metadata['file_sha256']) ? (string) $metadata['file_sha256'] : '';

	return array(
		'source' => isset($metadata['extension']) ? (string) $metadata['extension'] : '',
		'source_dataset' => $type,
		'source_key' => hash('sha256', $filehash.'|'.((int) $lineIndex)),
		'source_name' => isset($metadata['filename']) ? (string) $metadata['filename'] : '',
		'source_url' => '',
		'filename' => isset($metadata['filename']) ? (string) $metadata['filename'] : '',
		'import_status' => 'imported',
		'raw_data_const' => 'POWERPLANTPV_IMPORT_RAW_DATA',
		'invalid_response_error' => 'ProductTechnicalImportInvalidData',
	);
}

/**
 * Return selected import row.
 *
 * @param array<string,mixed> $metadata  Metadata
 * @param int                 $lineIndex Line index
 * @return array<string,mixed>|false Import row
 */
function powerplantpv_technical_import_selected_row(array $metadata, $lineIndex)
{
	$rows = isset($metadata['parsed']['rows']) && is_array($metadata['parsed']['rows']) ? $metadata['parsed']['rows'] : array();
	if (!isset($rows[$lineIndex]) || !is_array($rows[$lineIndex])) {
		return false;
	}

	return $rows[$lineIndex];
}

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$format = GETPOST('format', 'alpha');
$source = GETPOST('import_source', 'alpha');
$strategy = GETPOST('strategy', 'aZ09');
$separator = GETPOST('separator', 'nohtml');
$importtoken = GETPOST('import_token', 'alphanohtml');
$lineindex = GETPOSTINT('line_index');

if (!isModEnabled('powerplantpv')) {
	accessforbidden();
}

$permissiontoread = !empty($user->admin) || $user->hasRight('produit', 'lire');
$permissiontoadd = !empty($user->admin) || $user->hasRight('produit', 'creer');
if (!$permissiontoread || !$permissiontoadd) {
	accessforbidden();
}

$object = new Product($db);
if ($id > 0) {
	$object->fetch($id);
}
if (empty($object->id)) {
	accessforbidden();
}

$object->fetch_optionals($object->id, null);
$categoryRowId = !empty($object->array_options['options_categorie_photovoltaique']) ? (int) $object->array_options['options_categorie_photovoltaique'] : 0;
$categoryCode = powerplantpv_technical_import_get_product_category_code($db, $categoryRowId);
$isPVPanel = ($categoryCode === 'MODULE');
$isInverter = ($categoryCode === 'ONDULE');
$isBattery = ($categoryCode === 'BATTER');
if (!$isPVPanel && !$isInverter && !$isBattery) {
	accessforbidden($langs->trans('ProductTechnicalImportProductNotPVCompatible'));
}
if ($isBattery && powerplantpvProductHasNativeComponents($object->id) !== 0) {
	accessforbidden($langs->trans('ProductTechnicalImportBatteryKitForbidden'));
}
$technicaltype = $isInverter ? 'inverter' : ($isBattery ? 'battery' : 'module');

$csvEnabled = (bool) getDolGlobalInt('POWERPLANTPV_COMPONENT_IMPORT_CSV_ENABLED', 1);
$xlsxEnabled = (bool) getDolGlobalInt('POWERPLANTPV_COMPONENT_IMPORT_XLSX_ENABLED', 1);
$pvfreeEnabled = (bool) getDolGlobalInt('POWERPLANTPV_PVFREE_ENABLED');
if (!$csvEnabled && !$xlsxEnabled && !$pvfreeEnabled) {
	accessforbidden($langs->trans('ProductTechnicalImportNoSourceEnabled'));
}

$sourceOptions = array();
if ($csvEnabled) {
	$sourceOptions['csv'] = $langs->trans('ProductTechnicalImportCSV');
}
if ($xlsxEnabled) {
	$sourceOptions['xlsx'] = $langs->trans('ProductTechnicalImportXLSX');
}
if ($source === '' || empty($sourceOptions[$source])) {
	$source = !empty($sourceOptions) ? (string) key($sourceOptions) : '';
}

$strategyOptions = array(
	PowerPlantPVProductImport::STRATEGY_NEVER => $langs->trans('ProductTechnicalImportOverwriteNever'),
	PowerPlantPVProductImport::STRATEGY_EMPTY_ONLY => $langs->trans('ProductTechnicalImportOverwriteEmptyOnly'),
	PowerPlantPVProductImport::STRATEGY_OVERWRITE_AFTER_CONFIRM => $langs->trans('ProductTechnicalImportOverwriteAfterConfirm'),
);
if ($strategy === '' || !isset($strategyOptions[$strategy])) {
	$strategy = getDolGlobalString('POWERPLANTPV_IMPORT_OVERWRITE_EXISTING_DATA', PowerPlantPVProductImport::STRATEGY_EMPTY_ONLY);
}
if (!isset($strategyOptions[$strategy])) {
	$strategy = PowerPlantPVProductImport::STRATEGY_EMPTY_ONLY;
}

$separatorOptions = array(
	';' => $langs->trans('ProductTechnicalImportSeparatorSemicolon'),
	',' => $langs->trans('ProductTechnicalImportSeparatorComma'),
	'tab' => $langs->trans('ProductTechnicalImportSeparatorTab'),
);
if ($separator === '' || !isset($separatorOptions[$separator])) {
	$separator = getDolGlobalString('POWERPLANTPV_IMPORT_DEFAULT_SEPARATOR', ';');
}
if (!isset($separatorOptions[$separator])) {
	$separator = ';';
}
$csvSeparator = ($separator === 'tab' ? "\t" : $separator);

$form = new Form($db);
$fileimport = new PowerPlantPVFileImport();
$importer = new PowerPlantPVProductImport($db);
$metadata = false;
$selectedrow = false;
$normalizedData = null;
$rawData = null;
$preview = null;

if ($action === 'downloadtemplate') {
	if (!powerplantpv_technical_import_check_token()) {
		accessforbidden('Bad token');
	}
	$format = strtolower($format);
	if ($format === 'csv' && !$csvEnabled) {
		setEventMessages($langs->trans('ProductTechnicalImportCsvDisabled'), null, 'errors');
		$action = 'view';
	} elseif ($format === 'xlsx' && (!$xlsxEnabled || !powerplantpvProductTechnicalImportIsXlsxTemplateAvailable())) {
		setEventMessages($langs->trans('ProductTechnicalImportXlsxTemplateUnavailable'), null, 'errors');
		$action = 'view';
	} elseif (!in_array($format, array('csv', 'xlsx'), true)) {
		setEventMessages($langs->trans('ProductTechnicalImportUnsupportedFileExtension'), null, 'errors');
		$action = 'view';
	} else {
		$template = powerplantpvProductTechnicalImportBuildTemplateData($categoryCode);
		$headers = (array) $template['headers'];
		$rows = (array) $template['rows'];
		$fieldCatalog = powerplantpvProductTechnicalImportGetFieldCatalog($headers, $categoryCode);
		$allowedValues = powerplantpvProductTechnicalImportGetAllowedValues($db, (int) $conf->entity, $categoryCode);
		$filenamebase = dol_sanitizeFileName($object->ref.'-'.strtolower($categoryCode).'-technical-characteristics-template');

		if ($format === 'csv') {
			header('Content-Type: text/csv; charset=UTF-8');
			header('Content-Disposition: attachment; filename="'.$filenamebase.'.csv"');
			$out = fopen('php://output', 'wb');
			fputs($out, "\xEF\xBB\xBF");
			fputcsv($out, $headers, ';');
			foreach ($rows as $row) {
				$data = array();
				foreach ($headers as $header) {
					$data[] = isset($row[$header]) ? $row[$header] : '';
				}
				fputcsv($out, $data, ';');
			}
			foreach (powerplantpvProductTechnicalImportGetCsvDocumentationRows($fieldCatalog, $allowedValues) as $documentationRow) {
				fputcsv($out, $documentationRow, ';');
			}
			fclose($out);
			exit;
		}

		if (!powerplantpvProductTechnicalImportLoadPhpSpreadsheet()) {
			setEventMessages($langs->trans('ProductTechnicalImportXlsxTemplateUnavailable'), null, 'errors');
			$action = 'view';
		} else {
			$data = array();
			foreach ($rows as $row) {
				$datarow = array();
				foreach ($headers as $header) {
					$datarow[] = isset($row[$header]) ? $row[$header] : '';
				}
				$data[] = $datarow;
			}

			$oblevel = ob_get_level();
			try {
				$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
				$sheet = $spreadsheet->getActiveSheet();
				$sheet->fromArray($headers, null, 'A1');
				if (!empty($data)) {
					$sheet->fromArray($data, null, 'A2');
				}

				$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
				powerplantpvProductTechnicalImportAddReferenceSheets($spreadsheet, $fieldCatalog, $allowedValues);
				ob_start();
				$writer->save('php://output');
				$xlsxcontent = ob_get_clean();
				if ($xlsxcontent === false) {
					throw new Exception('Unable to capture XLSX template output');
				}
				header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
				header('Content-Disposition: attachment; filename="'.$filenamebase.'.xlsx"');
				print $xlsxcontent;
				exit;
			} catch (Throwable $e) {
				while (ob_get_level() > $oblevel) {
					ob_end_clean();
				}
				dol_syslog('product_technical_import.php downloadtemplate xlsx failed: '.$e->getMessage(), (defined('LOG_WARNING') ? LOG_WARNING : 4));
				setEventMessages($langs->trans('ProductTechnicalImportXlsxTemplateUnavailable'), null, 'errors');
				$action = 'view';
			}
		}
	}
}

powerplantpv_technical_import_purge_old_temp();

if ($action === 'cancel_import') {
	if (!powerplantpv_technical_import_check_token()) {
		accessforbidden('Bad token');
	}
	$metadata = powerplantpv_technical_import_load_metadata($importtoken, (int) $object->id);
	if (is_array($metadata)) {
		powerplantpv_technical_import_delete_temp($metadata, $importtoken);
	}
	header('Location: '.dol_buildpath('/powerplantpv/product_detailedcaracteristics.php', 1).'?id='.((int) $object->id));
	exit;
}

if ($action === 'upload_file') {
	if (!powerplantpv_technical_import_check_token()) {
		accessforbidden('Bad token');
	}
	if ($source === '' || empty($sourceOptions[$source])) {
		setEventMessages($langs->trans('ProductTechnicalImportSourceDisabled'), null, 'errors');
		$action = 'view';
	} elseif (empty($_FILES['technical_file']) || !is_array($_FILES['technical_file'])) {
		setEventMessages($langs->trans('ProductTechnicalImportFileMissing'), null, 'errors');
		$action = 'view';
	} else {
		$uploaded = $_FILES['technical_file'];
		$filemeta = $fileimport->validateUploadedFile($uploaded);
		if ($filemeta === false) {
			setEventMessages($langs->trans($fileimport->getLastError()), powerplantpv_technical_import_translate_errors($fileimport->getLastErrors()), 'errors');
			$action = 'view';
		} elseif ((string) $filemeta['extension'] !== $source) {
			setEventMessages($langs->trans('ProductTechnicalImportSourceFileMismatch'), null, 'errors');
			$action = 'view';
		} else {
			$tempdir = powerplantpv_technical_import_temp_dir();
			dol_mkdir($tempdir);
			$importtoken = powerplantpv_technical_import_generate_token();
			$targetpath = $tempdir.'/'.$importtoken.'.'.$filemeta['extension'];
			$uploadresult = dol_move_uploaded_file($uploaded['tmp_name'], $targetpath, 1, 0, (int) $uploaded['error'], 0);
			if ($uploadresult <= 0 || !is_readable($targetpath)) {
				setEventMessages($langs->trans('ProductTechnicalImportFileUnreadable'), null, 'errors');
				$action = 'view';
			} else {
				$rows = ($filemeta['extension'] === 'csv') ? $fileimport->readCsv($targetpath, $csvSeparator) : $fileimport->readXlsx($targetpath);
				if ($fileimport->getLastError()) {
					setEventMessages($langs->trans($fileimport->getLastError()), powerplantpv_technical_import_translate_errors($fileimport->getLastErrors()), 'errors');
					dol_delete_file($targetpath);
					$action = 'view';
				} else {
					$parsed = $fileimport->buildImportRows($rows, $technicaltype);
					if ($fileimport->getLastError()) {
						setEventMessages($langs->trans($fileimport->getLastError()), powerplantpv_technical_import_translate_errors($fileimport->getLastErrors()), 'errors');
						dol_delete_file($targetpath);
						$action = 'view';
					} else {
						if (!empty($parsed['field_map']['unit_warnings'])) {
							setEventMessages($langs->trans('ProductTechnicalImportMissingUnitWarning', count($parsed['field_map']['unit_warnings'])), null, 'warnings');
						}
						$legacywarningcount = 0;
						foreach ($parsed['rows'] as $parsedrow) {
							if (!empty($parsedrow['normalized']['_legacy_warnings'])) {
								$legacywarningcount += count($parsedrow['normalized']['_legacy_warnings']);
							}
						}
						if ($legacywarningcount > 0) {
							setEventMessages($langs->trans('ProductTechnicalImportLegacyValueWarning', $legacywarningcount), null, 'warnings');
						}
						$metadata = array(
							'entity' => (int) $conf->entity,
							'user_id' => (int) $user->id,
							'product_id' => (int) $object->id,
							'category_code' => $categoryCode,
							'type' => $technicaltype,
							'source' => $source,
							'extension' => (string) $filemeta['extension'],
							'filename' => (string) $filemeta['filename'],
							'filepath' => $targetpath,
							'file_sha256' => hash_file('sha256', $targetpath),
							'parsed' => $parsed,
						);
						if (!powerplantpv_technical_import_save_metadata($importtoken, $metadata)) {
							setEventMessages($langs->trans('ProductTechnicalImportInvalidData'), null, 'errors');
							dol_delete_file($targetpath);
							$action = 'view';
						} else {
							$lineindex = 0;
							$action = (count($parsed['rows']) > 1 ? 'select_line' : 'preview');
						}
					}
				}
			}
		}
	}
}

if ($action === 'select_line' || $action === 'preview' || $action === 'resolve_dictionaries' || $action === 'confirm_import') {
	$metadata = powerplantpv_technical_import_load_metadata($importtoken, (int) $object->id);
	if (!is_array($metadata)) {
		$action = 'view';
	} else {
		$selectedrow = powerplantpv_technical_import_selected_row($metadata, $lineindex);
		if ($selectedrow === false) {
			if ($action !== 'select_line') {
				setEventMessages($langs->trans('ProductTechnicalImportNoUsableLine'), null, 'errors');
				$action = 'select_line';
			}
		} elseif ($action === 'preview' || $action === 'resolve_dictionaries' || $action === 'confirm_import') {
			$normalizedData = isset($selectedrow['normalized']) && is_array($selectedrow['normalized']) ? $selectedrow['normalized'] : array();
			$rawData = array(
				'filename' => isset($metadata['filename']) ? (string) $metadata['filename'] : '',
				'extension' => isset($metadata['extension']) ? (string) $metadata['extension'] : '',
				'header_row' => isset($metadata['parsed']['header_row']) ? (int) $metadata['parsed']['header_row'] : 0,
				'line' => isset($selectedrow['line']) ? (int) $selectedrow['line'] : 0,
				'row' => isset($selectedrow['raw']) && is_array($selectedrow['raw']) ? $selectedrow['raw'] : array(),
				'field_map' => isset($metadata['parsed']['field_map']) && is_array($metadata['parsed']['field_map']) ? $metadata['parsed']['field_map'] : array(),
			);
			$dictionaryResolutions = isset($metadata['dictionary_resolutions']) && is_array($metadata['dictionary_resolutions']) ? $metadata['dictionary_resolutions'] : array();
			if ($action === 'resolve_dictionaries') {
				if (!powerplantpv_technical_import_check_token()) {
					accessforbidden('Bad token');
				}
				if ($isPVPanel) {
					$unresolvedPreview = $importer->previewModuleImport($object->id, $normalizedData, $strategy);
				} elseif ($isBattery) {
					$unresolvedPreview = $importer->previewBatteryImport($object->id, $normalizedData, $strategy);
				} else {
					$unresolvedPreview = $importer->previewInverterImport($object->id, $normalizedData, $strategy);
				}
				$trustedIssues = isset($unresolvedPreview['technical_dictionary_issues']) && is_array($unresolvedPreview['technical_dictionary_issues']) ? $unresolvedPreview['technical_dictionary_issues'] : array();
				$dictionaryResolutions = powerplantpvTechnicalImportCollectDictionaryResolutions($trustedIssues);
				$metadata['dictionary_resolutions'] = $dictionaryResolutions;
				if (!powerplantpv_technical_import_save_metadata($importtoken, $metadata)) {
					setEventMessages($langs->trans('ProductTechnicalImportInvalidData'), null, 'errors');
				}
				$action = 'preview';
			}
			if ($isPVPanel) {
				$preview = $importer->previewModuleImport($object->id, $normalizedData, $strategy, $dictionaryResolutions);
			} elseif ($isBattery) {
				$preview = $importer->previewBatteryImport($object->id, $normalizedData, $strategy, $dictionaryResolutions);
			} else {
				$preview = $importer->previewInverterImport($object->id, $normalizedData, $strategy, $dictionaryResolutions);
			}
			if ($importer->error) {
				setEventMessages($langs->trans($importer->error), powerplantpv_technical_import_translate_errors($importer->errors), 'errors');
			}
		}
	}
}

if ($action === 'confirm_import' && is_array($metadata) && is_array($selectedrow)) {
	if (!powerplantpv_technical_import_check_token()) {
		accessforbidden('Bad token');
	}

	$sourceData = powerplantpv_technical_import_source_data($metadata, $lineindex, $technicaltype);
	$dictionaryResolutions = isset($metadata['dictionary_resolutions']) && is_array($metadata['dictionary_resolutions']) ? $metadata['dictionary_resolutions'] : array();
	$sourceData['dictionary_resolutions'] = $dictionaryResolutions;
	if ($isPVPanel) {
		$result = $importer->importModuleToProduct($object->id, $normalizedData, $rawData, $user, $strategy, $sourceData, $dictionaryResolutions);
	} elseif ($isBattery) {
		$result = $importer->importBatteryToProduct($object->id, $normalizedData, $rawData, $user, $strategy, $sourceData, $dictionaryResolutions);
	} else {
		$result = $importer->importInverterToProduct($object->id, $normalizedData, $rawData, $user, $strategy, $sourceData, $dictionaryResolutions);
	}

	if ($result['result'] > 0) {
		powerplantpv_technical_import_delete_temp($metadata, $importtoken);
		setEventMessages($langs->trans('ProductTechnicalImportConfirmed'), null, 'mesgs');
		if (!empty($result['message'])) {
			setEventMessages($langs->trans($result['message']), null, 'mesgs');
		}
		if (!empty($result['warning'])) {
			setEventMessages($langs->trans($result['warning']), null, 'warnings');
		}
		header('Location: '.dol_buildpath('/powerplantpv/product_detailedcaracteristics.php', 1).'?id='.((int) $object->id));
		exit;
	}
	if ($result['result'] == 0) {
		setEventMessages($langs->trans(isset($result['message']) ? $result['message'] : 'ProductTechnicalImportNoFieldToImport'), null, 'warnings');
		$preview = isset($result['preview']) ? $result['preview'] : $preview;
	} else {
		setEventMessages($langs->trans($importer->error ? $importer->error : 'ProductTechnicalImportFailed'), powerplantpv_technical_import_translate_errors($importer->errors), 'errors');
		$preview = isset($result['preview']) ? $result['preview'] : $preview;
	}
	$action = 'preview';
}

$shortlabel = dol_trunc($object->label, 16);
$title = $langs->trans('Product').' '.$shortlabel.' - '.$langs->trans('ProductTechnicalImport');
$helpurl = 'EN:Module_Products|FR:Module_Produits|ES:M&oacute;dulo_Productos';

llxHeader('', $title, $helpurl, '', 0, 0, '', '', '', 'mod-product page-card_product_technical_import');

$head = product_prepare_head($object);
$productpicto = (method_exists($object, 'isService') && $object->isService()) ? 'service' : 'product';

print dol_get_fiche_head($head, 'pvpanel', $langs->trans('Product'), -1, $productpicto);

$linkback = '<a href="'.DOL_URL_ROOT.'/product/list.php?restore_lastsearch_values=1&type='.$object->type.'">'.$langs->trans('BackToList').'</a>';
$object->next_prev_filter = ' fk_product_type = '.((int) $object->type);
$shownav = 1;
if ($user->socid && !in_array('product', explode(',', getDolGlobalString('MAIN_MODULES_FOR_EXTERNAL')))) {
	$shownav = 0;
}
dol_banner_tab($object, 'ref', $linkback, $shownav, 'ref');
print dol_get_fiche_end();

if ($pvfreeEnabled) {
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('PVFreeConnector').'</td><td>'.dolGetButtonAction($langs->trans('PVFreeImportFromPVFree'), '', 'default', dol_buildpath('/powerplantpv/product_pvfree_import.php', 1).'?id='.((int) $object->id), '', true).'</td></tr>';
	print '</table>';
	print '</div><br>';
}

if (!empty($sourceOptions)) {
	$templatehtml = powerplantpvProductTechnicalImportTemplateLinksHtml((int) $object->id, $csvEnabled, $xlsxEnabled);
	print '<form method="POST" enctype="multipart/form-data" action="'.$_SERVER['PHP_SELF'].'?id='.((int) $object->id).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="upload_file">';
	print '<table class="noborder centpercent">';
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('ProductTechnicalImportSource').'</td><td>'.$form->selectarray('import_source', $sourceOptions, $source, 0, 0, '', 0, 0, 0, '', 'flat minwidth200').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('ProductTechnicalImportFile').'</td><td><input type="file" class="flat" name="technical_file" accept="'.($csvEnabled ? '.csv' : '').($csvEnabled && $xlsxEnabled ? ',' : '').($xlsxEnabled ? '.xlsx' : '').'"></td></tr>';
	if ($templatehtml !== '') {
		print '<tr class="oddeven"><td>'.$langs->trans('ProductTechnicalImportDownloadTemplate').'</td><td>'.$templatehtml.'</td></tr>';
	}
	print '<tr class="oddeven"><td>'.$langs->trans('ProductTechnicalImportOverwriteStrategy').'</td><td>'.$form->selectarray('strategy', $strategyOptions, $strategy, 0, 0, '', 0, 0, 0, '', 'flat minwidth300').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('ProductTechnicalImportDefaultSeparator').'</td><td>'.$form->selectarray('separator', $separatorOptions, $separator, 0, 0, '', 0, 0, 0, '', 'flat minwidth200').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('ProductTechnicalImportMaxFileSize').'</td><td>'.((int) getDolGlobalInt('POWERPLANTPV_IMPORT_MAX_FILE_SIZE', 5)).' MB</td></tr>';
	print '</table>';
	print '<div class="tabsAction">';
	print '<input type="submit" class="butAction" value="'.$langs->trans('ProductTechnicalImportUpload').'">';
	print dolGetButtonAction($langs->trans('Cancel'), '', 'default', dol_buildpath('/powerplantpv/product_detailedcaracteristics.php', 1).'?id='.((int) $object->id), '', true);
	print '</div>';
	print '</form>';
}

if ($conf->use_javascript_ajax) {
	print '<script nonce="'.getNonce().'">jQuery(function(){jQuery("#import_source,#strategy,#separator").select2({width:"resolve",minimumResultsForSearch:0});});</script>';
}

if (($action === 'select_line' || $action === 'preview') && is_array($metadata)) {
	$rows = isset($metadata['parsed']['rows']) && is_array($metadata['parsed']['rows']) ? $metadata['parsed']['rows'] : array();
	$showselection = ($action === 'select_line' || count($rows) > 1);
}
if (!empty($showselection) && is_array($metadata)) {
	$rows = isset($metadata['parsed']['rows']) && is_array($metadata['parsed']['rows']) ? $metadata['parsed']['rows'] : array();
	print load_fiche_titre($langs->trans('ProductTechnicalImportSelectLine'), '', '');
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('ProductTechnicalImportSelection').'</td>';
	print '<td>'.$langs->trans('ProductTechnicalImportLine').'</td>';
	print '<td>'.$langs->trans('ProductTechnicalImportManufacturer').'</td>';
	print '<td>'.$langs->trans('ProductTechnicalImportModel').'</td>';
	print '<td class="right">'.$langs->trans('ProductTechnicalImportPower').'</td>';
	print '<td class="right">'.$langs->trans('ProductTechnicalImportRecognizedFields').'</td>';
	print '</tr>';
	foreach ($rows as $idx => $row) {
		$previewurl = $_SERVER['PHP_SELF'].'?id='.((int) $object->id);
		$previewurl .= '&action=preview';
		$previewurl .= '&token='.newToken();
		$previewurl .= '&import_token='.urlencode($importtoken);
		$previewurl .= '&line_index='.((int) $idx);
		$previewurl .= '&strategy='.urlencode($strategy);
		$previewurl .= '&separator='.urlencode($separator);
		print '<tr class="oddeven">';
		print '<td>'.dolGetButtonAction($langs->trans('ProductTechnicalImportChoose'), '', 'default', $previewurl, '', true).'</td>';
		print '<td>'.((int) (isset($row['line']) ? $row['line'] : ($idx + 1))).'</td>';
		print '<td>'.dol_escape_htmltag(isset($row['manufacturer']) ? (string) $row['manufacturer'] : '').'</td>';
		print '<td>'.dol_escape_htmltag(isset($row['model']) ? (string) $row['model'] : '').'</td>';
		print '<td class="right">'.powerplantpv_technical_import_format_value(isset($row['power']) ? $row['power'] : '').'</td>';
		print '<td class="right">'.((int) (isset($row['recognized_count']) ? $row['recognized_count'] : 0)).'</td>';
		print '</tr>';
	}
	print '</table>';
	print '</div>';
}

if (is_array($metadata) && is_array($selectedrow) && is_array($normalizedData) && is_array($rawData) && is_array($preview)) {
	print load_fiche_titre($langs->trans('ProductTechnicalImportPreview'), '', 'fa-eye');
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('ProductTechnicalImportFile').'</td><td>'.dol_escape_htmltag(isset($metadata['filename']) ? (string) $metadata['filename'] : '').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('ProductTechnicalImportSource').'</td><td>'.dol_escape_htmltag(strtoupper((string) (isset($metadata['extension']) ? $metadata['extension'] : ''))).'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('ProductTechnicalImportLine').'</td><td>'.((int) (isset($selectedrow['line']) ? $selectedrow['line'] : 0)).'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('ProductTechnicalImportOverwriteStrategy').'</td><td>'.dol_escape_htmltag($strategyOptions[$strategy]).'</td></tr>';
	print '</table>';
	print '</div>';

	powerplantpv_technical_import_print_preview_rows('ProductTechnicalImportFieldsModified', $preview['changes'], $categoryCode, false, 'fa-check-circle');
	powerplantpv_technical_import_print_preview_rows('ProductTechnicalImportFieldsIgnored', $preview['ignored'], $categoryCode, true, 'fa-ban');
	if (!empty($preview['mppt_changes']) || !empty($preview['mppt_ignored'])) {
		powerplantpv_technical_import_print_preview_rows('ProductTechnicalImportMPPTFieldsModified', isset($preview['mppt_changes']) && is_array($preview['mppt_changes']) ? $preview['mppt_changes'] : array(), $categoryCode, false, 'fa-check-circle');
		powerplantpv_technical_import_print_preview_rows('ProductTechnicalImportMPPTFieldsIgnored', isset($preview['mppt_ignored']) && is_array($preview['mppt_ignored']) ? $preview['mppt_ignored'] : array(), $categoryCode, true, 'fa-ban');
	}
	foreach (isset($preview['technical_dictionary_warnings']) && is_array($preview['technical_dictionary_warnings']) ? $preview['technical_dictionary_warnings'] : array() as $warning) {
		if (is_array($warning)) {
			print '<div class="warning">'.$langs->trans('PowerPlantPVImportExistingDictionaryLabelKept', dol_escape_htmltag((string) $warning['code']), dol_escape_htmltag((string) $warning['stored_label'])).'</div>';
		}
	}

	$requiresResolution = !empty($preview['requires_dictionary_resolution']);
	$issues = isset($preview['technical_dictionary_issues']) && is_array($preview['technical_dictionary_issues']) ? $preview['technical_dictionary_issues'] : array();
	foreach ($issues as $issueKey => $issue) {
		if (is_array($issue)) {
			$issues[$issueKey]['occurrence_labels'] = array($langs->trans('Line').' '.((int) (isset($selectedrow['line']) ? $selectedrow['line'] : 0)).' - '.$object->ref);
		}
	}
	$dictionaryResolutions = isset($metadata['dictionary_resolutions']) && is_array($metadata['dictionary_resolutions']) ? $metadata['dictionary_resolutions'] : array();
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.((int) $object->id).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="'.($requiresResolution ? 'resolve_dictionaries' : 'confirm_import').'">';
	print '<input type="hidden" name="import_token" value="'.dol_escape_htmltag($importtoken).'">';
	print '<input type="hidden" name="line_index" value="'.((int) $lineindex).'">';
	print '<input type="hidden" name="strategy" value="'.dol_escape_htmltag($strategy).'">';
	if ($requiresResolution) {
		powerplantpvTechnicalImportPrintDictionaryResolutionFields($issues, $dictionaryResolutions, (int) $conf->entity);
	}
	print '<div class="tabsAction">';
	$haspreviewchanges = (!empty($preview['changes']) || !empty($preview['mppt_changes']));
	if ($requiresResolution) {
		print '<input type="submit" class="butAction" value="'.$langs->trans('PowerPlantPVImportApplyDictionaryResolutions').'">';
	} else {
		print '<input type="submit" class="butAction" value="'.$langs->trans('ProductTechnicalImportConfirm').'"'.($haspreviewchanges ? '' : ' disabled').'>';
	}
	print dolGetButtonAction($langs->trans('Cancel'), '', 'default', $_SERVER['PHP_SELF'].'?id='.((int) $object->id).'&action=cancel_import&import_token='.urlencode($importtoken).'&token='.newToken(), '', true);
	print '</div>';
	print '</form>';
}

llxFooter();
$db->close();
