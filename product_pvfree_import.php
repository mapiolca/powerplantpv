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
 * \file       product_pvfree_import.php
 * \ingroup    powerplantpv
 * \brief      Import product detailed characteristics from PV Free.
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
dol_include_once('/powerplantpv/class/powerplantpvpvfreeclient.class.php');
dol_include_once('/powerplantpv/class/powerplantpvproductimport.class.php');
dol_include_once('/powerplantpv/class/productinverter.class.php');

$langs->loadLangs(array('products', 'powerplantpv@powerplantpv', 'other'));

/**
 * Translate error keys for event messages.
 *
 * @param array<int,string> $errors Error keys
 * @return array<int,string> Translated errors
 */
function powerplantpv_pvfree_translate_errors(array $errors)
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
function powerplantpv_pvfree_get_product_category_code($db, $categoryRowId)
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
 * Return the translated label for an imported field.
 *
 * @param string $field Field
 * @param string $type  Product PV type
 * @return string Translated label
 */
function powerplantpv_pvfree_field_label($field, $type)
{
	global $langs;

	$modulelabels = array(
		'pmax' => 'PVPanelNominalPower',
		'vmp' => 'PVPanelVmp',
		'imp' => 'PVPanelImp',
		'voc' => 'PVPanelVoc',
		'isc' => 'PVPanelIsc',
		'module_efficiency' => 'PVPanelModuleEfficiency',
		'noct' => 'PVPanelNOCT',
		'temp_coeff_pmax' => 'PVPanelTempCoeffPmax',
		'temp_coeff_voc' => 'PVPanelTempCoeffVoc',
		'temp_coeff_isc' => 'PVPanelTempCoeffIsc',
	);

	if ($type === 'MODULE' && isset($modulelabels[$field])) {
		return $langs->trans($modulelabels[$field]);
	}

	$inverterfields = ProductInverter::getInverterFields();
	if (isset($inverterfields[$field]['label'])) {
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
function powerplantpv_pvfree_format_value($value)
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
 * Print a JSON block.
 *
 * @param string              $title Title
 * @param array<string,mixed> $data  Data
 * @return void
 */
function powerplantpv_pvfree_print_json_block($title, array $data)
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
 * Print preview changes or ignored fields.
 *
 * @param string              $title Title
 * @param array<string,array<string,mixed>> $rows Rows
 * @param string              $type  Product PV type
 * @param bool                $withReason Show reason
 * @return void
 */
function powerplantpv_pvfree_print_preview_rows($title, array $rows, $type, $withReason = false)
{
	global $langs;

	print load_fiche_titre($langs->trans($title), '', '');
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('Field').'</td>';
	print '<td>'.$langs->trans('PVFreeCurrentValue').'</td>';
	print '<td>'.$langs->trans('PVFreeImportedValue').'</td>';
	if ($withReason) {
		print '<td>'.$langs->trans('Reason').'</td>';
	}
	print '</tr>';

	if (empty($rows)) {
		print '<tr class="oddeven"><td colspan="'.($withReason ? 4 : 3).'" class="opacitymedium">'.$langs->trans('None').'</td></tr>';
	} else {
		foreach ($rows as $field => $row) {
			print '<tr class="oddeven">';
			print '<td>'.powerplantpv_pvfree_field_label($field, $type).'</td>';
			print '<td>'.powerplantpv_pvfree_format_value(isset($row['current']) ? $row['current'] : null).'</td>';
			print '<td>'.powerplantpv_pvfree_format_value(isset($row['proposed']) ? $row['proposed'] : null).'</td>';
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
 * Check that a PV Free resource URI matches the selected dataset.
 *
 * @param string $resourceUri Resource URI
 * @param string $dataset     Dataset
 * @return bool True if the URI is consistent with the dataset
 */
function powerplantpv_pvfree_resource_matches_dataset($resourceUri, $dataset)
{
	return (bool) preg_match('#^/api/v1/'.preg_quote($dataset, '#').'/[0-9]+/$#', (string) $resourceUri);
}

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$query = trim(GETPOST('query', 'nohtml'));
$dataset = GETPOST('dataset', 'aZ09');
$strategy = GETPOST('strategy', 'aZ09');
$resourceUri = GETPOST('resource_uri', 'nohtml');

if (!isModEnabled('powerplantpv')) {
	accessforbidden();
}
if (!getDolGlobalInt('POWERPLANTPV_PVFREE_ENABLED')) {
	accessforbidden($langs->trans('PVFreeConnectorDisabled'));
}

$permissiontoread = $user->hasRight('produit', 'lire');
$permissiontoadd = $user->hasRight('produit', 'creer');
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
$categoryCode = powerplantpv_pvfree_get_product_category_code($db, $categoryRowId);
$isPVPanel = ($categoryCode === 'MODULE');
$isInverter = ($categoryCode === 'ONDULE');
if (!$isPVPanel && !$isInverter) {
	accessforbidden($langs->trans('PVFreeProductNotPVCompatible'));
}

if ($query === '') {
	$query = trim($object->ref.' '.$object->label);
}

$moduleDatasetOptions = array(
	'cecmodule' => $langs->trans('PVFreeDatasetCECModule'),
	'pvmodule' => $langs->trans('PVFreeDatasetSandiaModule'),
);
$inverterDatasetOptions = array(
	'pvinverter' => $langs->trans('PVFreeDatasetPVInverter'),
);
$datasetOptions = $isPVPanel ? $moduleDatasetOptions : $inverterDatasetOptions;
$defaultDataset = $isPVPanel ? getDolGlobalString('POWERPLANTPV_PVFREE_DEFAULT_MODULE_DATASET', 'cecmodule') : getDolGlobalString('POWERPLANTPV_PVFREE_DEFAULT_INVERTER_DATASET', 'pvinverter');
if (empty($dataset) || !isset($datasetOptions[$dataset])) {
	$dataset = isset($datasetOptions[$defaultDataset]) ? $defaultDataset : key($datasetOptions);
}

$strategyOptions = array(
	PowerPlantPVProductImport::STRATEGY_NEVER => $langs->trans('PVFreeOverwriteNever'),
	PowerPlantPVProductImport::STRATEGY_EMPTY_ONLY => $langs->trans('PVFreeOverwriteEmptyOnly'),
	PowerPlantPVProductImport::STRATEGY_OVERWRITE_AFTER_CONFIRM => $langs->trans('PVFreeOverwriteAfterConfirm'),
);
if (empty($strategy) || !isset($strategyOptions[$strategy])) {
	$strategy = getDolGlobalString('POWERPLANTPV_PVFREE_OVERWRITE_EXISTING_DATA', PowerPlantPVProductImport::STRATEGY_EMPTY_ONLY);
}
if (!isset($strategyOptions[$strategy])) {
	$strategy = PowerPlantPVProductImport::STRATEGY_EMPTY_ONLY;
}

$form = new Form($db);
$client = new PowerPlantPVPVFreeClient();
$importer = new PowerPlantPVProductImport($db);

$results = null;
$rawData = null;
$normalizedData = null;
$preview = null;

if ($action === 'search') {
	$results = $isPVPanel ? $client->searchModules($query, $dataset, 20) : $client->searchInverters($query, $dataset, 20);
	if ($results === null) {
		setEventMessages($langs->trans($client->getLastError()), powerplantpv_pvfree_translate_errors($client->getLastErrors()), 'errors');
	} elseif (empty($results['objects']) || !is_array($results['objects'])) {
		setEventMessages($langs->trans('PVFreeNoResult'), null, 'warnings');
	}
} elseif ($action === 'preview') {
	if (!powerplantpv_pvfree_resource_matches_dataset($resourceUri, $dataset)) {
		setEventMessages($langs->trans('PVFreeInvalidResourceUri'), null, 'errors');
	} else {
		$rawData = $client->fetchDetail($resourceUri);
		if ($rawData === null) {
			setEventMessages($langs->trans($client->getLastError()), powerplantpv_pvfree_translate_errors($client->getLastErrors()), 'errors');
		} else {
			$normalizedData = $isPVPanel ? $importer->normalizeModule($rawData, $dataset) : $importer->normalizeInverter($rawData, $dataset);
			$preview = $isPVPanel ? $importer->previewModuleImport($object->id, $normalizedData, $strategy) : $importer->previewInverterImport($object->id, $normalizedData, $strategy);
			if ($importer->error) {
				setEventMessages($langs->trans($importer->error), powerplantpv_pvfree_translate_errors($importer->errors), 'errors');
			}
		}
	}
} elseif ($action === 'confirm_import') {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}

	if (!powerplantpv_pvfree_resource_matches_dataset($resourceUri, $dataset)) {
		setEventMessages($langs->trans('PVFreeInvalidResourceUri'), null, 'errors');
		$action = 'preview';
	} else {
		$rawData = $client->fetchDetail($resourceUri);
		if ($rawData === null) {
			setEventMessages($langs->trans($client->getLastError()), powerplantpv_pvfree_translate_errors($client->getLastErrors()), 'errors');
			$action = 'preview';
		} else {
			$normalizedData = $isPVPanel ? $importer->normalizeModule($rawData, $dataset) : $importer->normalizeInverter($rawData, $dataset);
			$result = $isPVPanel ? $importer->importModuleToProduct($object->id, $normalizedData, $rawData, $user, $strategy) : $importer->importInverterToProduct($object->id, $normalizedData, $rawData, $user, $strategy);
			if ($result['result'] > 0) {
				setEventMessages($langs->trans('PVFreeImportConfirmed'), null, 'mesgs');
				if (!empty($result['warning'])) {
					setEventMessages($langs->trans($result['warning']), null, 'warnings');
				}
				header('Location: '.dol_buildpath('/powerplantpv/product_detailedcaracteristics.php', 1).'?id='.((int) $object->id));
				exit;
			}
			if ($result['result'] == 0) {
				setEventMessages($langs->trans(isset($result['message']) ? $result['message'] : 'PVFreeNoFieldToImport'), null, 'warnings');
				$preview = isset($result['preview']) ? $result['preview'] : null;
			} else {
				setEventMessages($langs->trans($importer->error ? $importer->error : 'PVFreeImportFailed'), powerplantpv_pvfree_translate_errors($importer->errors), 'errors');
				$preview = isset($result['preview']) ? $result['preview'] : null;
			}
			$action = 'preview';
		}
	}
}

$shortlabel = dol_trunc($object->label, 16);
$title = $langs->trans('Product').' '.$shortlabel.' - '.$langs->trans('PVFreeImport');
$helpurl = 'EN:Module_Products|FR:Module_Produits|ES:M&oacute;dulo_Productos';

llxHeader('', $title, $helpurl, '', 0, 0, '', '', '', 'mod-product page-card_product_pvfree_import');

$head = product_prepare_head($object);
$productpicto = (method_exists($object, 'isService') && $object->isService()) ? 'service' : 'product';

print dol_get_fiche_head($head, 'pvpanel', $langs->trans('Product'), -1, $productpicto);

$linkback = '<a href="'.DOL_URL_ROOT.'/product/list.php?restore_lastsearch_values=1&type='.$object->type.'">'.$langs->trans('BackToList').'</a>';
$object->next_prev_filter = '(te.fk_product_type:=:'.((int) $object->type).')';
$shownav = 1;
if ($user->socid && !in_array('product', explode(',', getDolGlobalString('MAIN_MODULES_FOR_EXTERNAL')))) {
	$shownav = 0;
}
dol_banner_tab($object, 'ref', $linkback, $shownav, 'ref');
print dol_get_fiche_end();

print load_fiche_titre($langs->trans('PVFreeImport'), '', 'fa-cloud-download-alt');
print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="id" value="'.((int) $object->id).'">';
print '<input type="hidden" name="action" value="search">';
print '<table class="noborder centpercent">';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('PVFreeSearch').'</td><td><input type="text" class="flat minwidth400" name="query" value="'.dol_escape_htmltag($query).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVFreeDataset').'</td><td>'.$form->selectarray('dataset', $datasetOptions, $dataset, 0, 0, '', 0, 0, 0, '', 'flat minwidth200').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVFreeOverwriteStrategy').'</td><td>'.$form->selectarray('strategy', $strategyOptions, $strategy, 0, 0, '', 0, 0, 0, '', 'flat minwidth300').'</td></tr>';
print '</table>';
print '<div class="tabsAction">';
print '<input type="submit" class="butAction" value="'.$langs->trans('PVFreeSearch').'">';
print dolGetButtonAction($langs->trans('Cancel'), '', 'default', dol_buildpath('/powerplantpv/product_detailedcaracteristics.php', 1).'?id='.((int) $object->id), '', true);
print '</div>';
print '</form>';

if ($conf->use_javascript_ajax) {
	print '<script nonce="'.getNonce().'">jQuery(function(){jQuery("#dataset,#strategy").select2({width:"resolve",minimumResultsForSearch:0});});</script>';
}

if ($results !== null && !empty($results['objects']) && is_array($results['objects'])) {
	print load_fiche_titre($langs->trans('PVFreeSearchResults'), '', '');
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('Ref').'</td>';
	print '<td>'.$langs->trans('Name').'</td>';
	print '<td>'.$langs->trans('PVFreeDataset').'</td>';
	print '<td class="right"></td>';
	print '</tr>';
	foreach ($results['objects'] as $result) {
		if (!is_array($result) || empty($result['resource_uri'])) {
			continue;
		}
		$previewurl = $_SERVER['PHP_SELF'].'?id='.((int) $object->id);
		$previewurl .= '&action=preview';
		$previewurl .= '&dataset='.urlencode($dataset);
		$previewurl .= '&strategy='.urlencode($strategy);
		$previewurl .= '&query='.urlencode($query);
		$previewurl .= '&resource_uri='.urlencode((string) $result['resource_uri']);
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag(isset($result['id']) ? (string) $result['id'] : '').'</td>';
		print '<td>'.dol_escape_htmltag(isset($result['Name']) ? (string) $result['Name'] : '').'</td>';
		print '<td>'.dol_escape_htmltag($datasetOptions[$dataset]).'</td>';
		print '<td class="right">'.dolGetButtonAction($langs->trans('PVFreePreviewImport'), '', 'default', $previewurl, '', true).'</td>';
		print '</tr>';
	}
	print '</table>';
	print '</div>';
}

if (is_array($rawData) && is_array($normalizedData) && is_array($preview)) {
	print load_fiche_titre($langs->trans('PVFreePreviewImport'), '', '');
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Name').'</td><td>'.dol_escape_htmltag(isset($rawData['Name']) ? (string) $rawData['Name'] : '').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('PVFreeDataset').'</td><td>'.dol_escape_htmltag($datasetOptions[$dataset]).'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('PVFreeOverwriteStrategy').'</td><td>'.dol_escape_htmltag($strategyOptions[$strategy]).'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('PVFreeDataSource').'</td><td>'.dol_escape_htmltag(isset($rawData['resource_uri']) ? (string) $rawData['resource_uri'] : '').'</td></tr>';
	print '</table>';
	print '</div>';

	powerplantpv_pvfree_print_preview_rows('PVFreeFieldsModified', $preview['changes'], $categoryCode, false);
	powerplantpv_pvfree_print_preview_rows('PVFreeFieldsIgnored', $preview['ignored'], $categoryCode, true);
	powerplantpv_pvfree_print_json_block('PVFreeNormalizedData', $normalizedData);
	powerplantpv_pvfree_print_json_block('PVFreeRawData', $rawData);

	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.((int) $object->id).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="confirm_import">';
	print '<input type="hidden" name="dataset" value="'.dol_escape_htmltag($dataset).'">';
	print '<input type="hidden" name="strategy" value="'.dol_escape_htmltag($strategy).'">';
	print '<input type="hidden" name="resource_uri" value="'.dol_escape_htmltag((string) $resourceUri).'">';
	print '<div class="tabsAction">';
	print '<input type="submit" class="butAction" value="'.$langs->trans('PVFreeImportAction').'"'.(empty($preview['changes']) ? ' disabled' : '').'>';
	print dolGetButtonAction($langs->trans('Cancel'), '', 'default', dol_buildpath('/powerplantpv/product_detailedcaracteristics.php', 1).'?id='.((int) $object->id), '', true);
	print '</div>';
	print '</form>';
}

llxFooter();
$db->close();
