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
 * \file       lib/powerplantpv_producttechnicalimport.lib.php
 * \ingroup    powerplantpv
 * \brief      Product technical import UI helpers.
 */

dol_include_once('/powerplantpv/class/powerplantpvproductimport.class.php');
dol_include_once('/powerplantpv/class/powerplantpvproductdictionary.class.php');
dol_include_once('/powerplantpv/class/powerplantpvfileimport.class.php');

/**
 * Return product technical import template headers.
 *
 * @param string $categoryCode Product PV category code
 * @return array<int,string> Headers
 */
function powerplantpvProductTechnicalImportGetTemplateHeaders($categoryCode)
{
	$type = 'module';
	$fields = array();
	if ($categoryCode === 'ONDULE') {
		$type = 'inverter';
		$fields = array_merge(
			PowerPlantPVProductImport::getInverterImportFields(),
			PowerPlantPVProductImport::getInverterMPPTCompositionTemplateFields(4, 2),
			PowerPlantPVProductImport::getTechnicalDictionaryTemplateFields()
		);
	} elseif ($categoryCode === 'BATTER') {
		$type = 'battery';
		$fields = array_merge(PowerPlantPVProductImport::getBatteryImportFields(), PowerPlantPVProductImport::getTechnicalDictionaryTemplateFields());
	} else {
		$fields = array_merge(PowerPlantPVProductImport::getModuleImportFields(), PowerPlantPVProductImport::getTechnicalDictionaryTemplateFields());
	}

	$headers = array();
	foreach ($fields as $field) {
		$unitfield = $field;
		if ($type === 'inverter' && preg_match('/^mppt_[0-9]+_(?:input_[0-9]+_)?(.+)$/', $field, $matches)) {
			$unitfield = $matches[1];
			$unit = in_array($unitfield, array('voltage_min', 'voltage_max'), true) ? 'V' : (strpos($unitfield, 'current') !== false ? 'A' : ($unitfield === 'max_dc_power' ? 'W' : 'text'));
			$datatype = $unit === 'text' ? 'text' : 'decimal';
			$parts = array('type='.$datatype);
			if ($unit !== 'text') {
				$parts[] = 'unit='.$unit;
			}
			$parts[] = 'format='.($unit === 'text' ? 'TEXT' : 'SIGNED_DECIMAL');
			$headers[] = $field.' ['.implode('; ', $parts).']';
			continue;
		}
		$headers[] = PowerPlantPVProductImport::getTemplateHeader($type, $unitfield);
	}
	return $headers;
}

/**
 * Build template data with one empty row.
 *
 * @param string $categoryCode Product PV category code
 * @return array<string,mixed> Template data
 */
function powerplantpvProductTechnicalImportBuildTemplateData($categoryCode)
{
	$headers = powerplantpvProductTechnicalImportGetTemplateHeaders($categoryCode);
	$row = array();
	foreach ($headers as $header) {
		$row[$header] = '';
	}

	return array(
		'headers' => $headers,
		'rows' => array($row),
	);
}

/**
 * Return the documented contract for every exported column.
 *
 * @param array<int,string> $headers Exported headers
 * @param string $categoryCode Product PV category or MIXED
 * @return array<int,array<string,string>>
 */
function powerplantpvProductTechnicalImportGetFieldCatalog(array $headers, $categoryCode)
{
	$catalog = array();
	$fileimport = new PowerPlantPVFileImport();
	$technicalType = $categoryCode === 'ONDULE' ? 'inverter' : ($categoryCode === 'BATTER' ? 'battery' : 'module');
	$typeFields = array(
		'module' => array_fill_keys(PowerPlantPVProductImport::getModuleImportFields(), true),
		'inverter' => array_fill_keys(PowerPlantPVProductImport::getInverterImportFields(), true),
		'battery' => array_fill_keys(PowerPlantPVProductImport::getBatteryImportFields(), true),
	);
	foreach ($headers as $header) {
		$field = trim((string) preg_replace('/\s*\[[^\]]+\]\s*$/u', '', (string) $header));
		$family = $categoryCode;
		$fieldTechnicalType = $technicalType;
		$definition = array();
		if ($categoryCode === 'MIXED' && in_array($field, PowerPlantPVBulkProductImport::getNativeHeaders(), true)) {
			$definition = powerplantpvProductTechnicalImportGetNativeFieldDefinition($field);
			$family = 'PRODUCT';
		} elseif (preg_match('/^mppt_[0-9]+_(?:input_[0-9]+_)?/', $field)) {
			$definition = $fileimport->getMpptCompositionFieldDefinition($field);
			$family = 'ONDULE';
		} else {
			if ($categoryCode === 'MIXED') {
				$families = array();
				foreach ($typeFields as $candidateType => $candidateFields) {
					if (isset($candidateFields[$field])) {
						if (empty($families)) {
							$fieldTechnicalType = $candidateType;
						}
						$families[] = $candidateType === 'module' ? 'MODULE' : ($candidateType === 'inverter' ? 'ONDULE' : 'BATTER');
					}
				}
				if (preg_match('/^(communication_protocol|certification|protection)_[0-9]+$/D', $field)) {
					$families = array('MODULE', 'ONDULE', 'BATTER');
				}
				if (!empty($families)) {
					$family = implode('/', $families);
				}
			}
			$definition = PowerPlantPVProductImport::getImportFieldDefinition($fieldTechnicalType, $field);
		}
		$type = isset($definition['type']) ? (string) $definition['type'] : 'text';
		$catalog[] = array(
			'field' => $field,
			'family' => $family,
			'type' => $type,
			'unit' => isset($definition['unit']) ? (string) $definition['unit'] : '',
			'cardinality' => isset($definition['cardinality']) ? (string) $definition['cardinality'] : '0..1',
			'format' => isset($definition['format']) ? (string) $definition['format'] : '',
			'source' => isset($definition['source']) ? (string) $definition['source'] : '',
			'rule' => 'PowerPlantPVImportRule'.ucfirst($type),
		);
	}
	return $catalog;
}

/** @return array<string,string> */
function powerplantpvProductTechnicalImportGetNativeFieldDefinition($field)
{
	return PowerPlantPVProductImport::getNativeProductImportFieldDefinition($field);
}

/**
 * Return active allowed values for template documentation.
 *
 * @param DoliDB $db Database handler
 * @param int $entity Current entity
 * @param string $categoryCode Product category or MIXED
 * @return array<int,array<string,string>>
 */
function powerplantpvProductTechnicalImportGetAllowedValues($db, $entity, $categoryCode)
{
	global $langs;
	$values = array();
	$service = new PowerPlantPVProductDictionary($db);
	foreach (PowerPlantPVProductDictionary::getDefinitions() as $type => $definition) {
		$map = $service->fetchCodeMap($type, $entity, false);
		foreach ($map as $entry) {
			$values[] = array('source' => $type, 'field' => $type, 'code' => (string) $entry['code'], 'label' => (string) $entry['label']);
		}
	}

	if ($categoryCode === 'BATTER' || $categoryCode === 'MIXED') {
		foreach (ProductBattery::getBatteryFields() as $field => $spec) {
			if (empty($spec['options']) || !is_array($spec['options'])) {
				continue;
			}
			foreach ($spec['options'] as $code => $labelKey) {
				$values[] = array('source' => $field, 'field' => $field, 'code' => (string) $code, 'label' => $langs->trans((string) $labelKey));
			}
		}
	}

	if ($categoryCode === 'MIXED') {
		$sql = "SELECT code, label FROM ".$db->prefix()."c_powerplantpv_categorypv WHERE active = 1 AND code IN ('MODULE', 'ONDULE', 'BATTER') ORDER BY label ASC";
		$resql = $db->query($sql);
		if ($resql) {
			while (is_object($obj = $db->fetch_object($resql))) {
				$values[] = array('source' => 'category_code', 'field' => 'category_code', 'code' => (string) $obj->code, 'label' => (string) $obj->label);
			}
			$db->free($resql);
		}
		foreach (array('status_sell', 'status_buy') as $field) {
			$values[] = array('source' => 'boolean', 'field' => $field, 'code' => '0', 'label' => $langs->trans('No'));
			$values[] = array('source' => 'boolean', 'field' => $field, 'code' => '1', 'label' => $langs->trans('Yes'));
		}
		$values[] = array('source' => 'price_base_type', 'field' => 'price_base_type', 'code' => 'HT', 'label' => $langs->trans('HT'));
		$values[] = array('source' => 'price_base_type', 'field' => 'price_base_type', 'code' => 'TTC', 'label' => $langs->trans('TTC'));

		$sql = 'SELECT code, short_label, label, unit_type FROM '.$db->prefix().'c_units WHERE active = 1 AND unit_type IN (\'weight\', \'size\') ORDER BY unit_type, scale ASC';
		$resql = $db->query($sql);
		if ($resql) {
			while (is_object($obj = $db->fetch_object($resql))) {
				$field = (string) $obj->unit_type === 'weight' ? 'weight_unit' : 'size_unit';
				$code = trim((string) $obj->code) !== '' ? (string) $obj->code : (string) $obj->short_label;
				$label = trim((string) $obj->label) !== '' ? $langs->trans((string) $obj->label) : (string) $obj->short_label;
				$values[] = array('source' => $field, 'field' => $field, 'code' => $code, 'label' => $label);
			}
			$db->free($resql);
		}

		$sql = 'SELECT code, libelle FROM '.$db->prefix().'c_barcode_type WHERE entity = '.((int) $entity)." AND coder <> '0' ORDER BY libelle ASC";
		$resql = $db->query($sql);
		if ($resql) {
			while (is_object($obj = $db->fetch_object($resql))) {
				$values[] = array('source' => 'barcode_type_code', 'field' => 'barcode_type_code', 'code' => (string) $obj->code, 'label' => (string) $obj->libelle);
			}
			$db->free($resql);
		}
	}
	return $values;
}

/**
 * Return CSV documentation rows ignored by the parser.
 *
 * @param array<int,array<string,string>> $fields Field catalog
 * @param array<int,array<string,string>> $values Allowed values
 * @return array<int,array<int,string>>
 */
function powerplantpvProductTechnicalImportGetCsvDocumentationRows(array $fields, array $values)
{
	global $langs;

	$rows = array();
	foreach ($fields as $field) {
		$rows[] = array('#POWERPLANTPV_FIELD', $field['field'], $field['family'], $field['type'], $field['unit'], $field['cardinality'], $field['format'], $field['source'], $langs->transnoentitiesnoconv($field['rule']));
	}
	foreach ($values as $value) {
		$rows[] = array('#POWERPLANTPV_VALUE', $value['source'], $value['field'], $value['code'], $value['label']);
	}
	return $rows;
}

/**
 * Add reference sheets while keeping Import active and first.
 *
 * @param object $spreadsheet PhpSpreadsheet workbook
 * @param array<int,array<string,string>> $fields Field catalog
 * @param array<int,array<string,string>> $values Allowed values
 * @return void
 */
function powerplantpvProductTechnicalImportAddReferenceSheets($spreadsheet, array $fields, array $values)
{
	global $langs;
	$importSheet = $spreadsheet->getSheet(0);
	$importSheet->setTitle($langs->transnoentitiesnoconv('PowerPlantPVImportSheetImport'));
	$fieldSheet = $spreadsheet->createSheet();
	$fieldSheet->setTitle($langs->transnoentitiesnoconv('PowerPlantPVImportSheetFields'));
	$fieldSheet->fromArray(array(
		$langs->transnoentitiesnoconv('Field'), $langs->transnoentitiesnoconv('PowerPlantPVImportFamily'),
		$langs->transnoentitiesnoconv('PowerPlantPVImportDataType'), $langs->transnoentitiesnoconv('Unit'),
		$langs->transnoentitiesnoconv('PowerPlantPVImportCardinality'), $langs->transnoentitiesnoconv('PowerPlantPVImportFormat'),
		$langs->transnoentitiesnoconv('PowerPlantPVImportOptionSource'), $langs->transnoentitiesnoconv('PowerPlantPVImportRules'),
	), null, 'A1');
	$row = 2;
	foreach ($fields as $field) {
		$fieldSheet->fromArray(array($field['field'], $field['family'], $field['type'], $field['unit'], $field['cardinality'], $field['format'], $field['source'], $langs->transnoentitiesnoconv($field['rule'])), null, 'A'.$row++);
	}
	$valueSheet = $spreadsheet->createSheet();
	$valueSheet->setTitle($langs->transnoentitiesnoconv('PowerPlantPVImportSheetAllowedValues'));
	$valueSheet->fromArray(array(
		$langs->transnoentitiesnoconv('PowerPlantPVImportOptionSource'), $langs->transnoentitiesnoconv('Field'),
		$langs->transnoentitiesnoconv('Code'), $langs->transnoentitiesnoconv('Label'),
	), null, 'A1');
	$row = 2;
	foreach ($values as $value) {
		$valueSheet->fromArray(array($value['source'], $value['field'], $value['code'], $value['label']), null, 'A'.$row++);
	}
	$spreadsheet->setActiveSheetIndex(0);
}

/**
 * Read dictionary decisions only for issues regenerated from the trusted file.
 *
 * @param array<string,array<string,mixed>> $issues Trusted preview issues
 * @return array<string,array<string,mixed>>
 */
function powerplantpvTechnicalImportCollectDictionaryResolutions(array $issues)
{
	$resolutions = array();
	foreach ($issues as $key => $issue) {
		if (!is_array($issue) || !preg_match('/^[a-f0-9]{20}$/D', (string) $key)) {
			continue;
		}
		$isError = GETPOST('dictionary_is_error_'.$key, 'alpha');
		if ($isError === 'no' && !empty($issue['can_create'])) {
			$resolutions[$key] = array('action' => 'create');
			continue;
		}
		if ($isError !== 'yes') {
			continue;
		}
		$postedTargets = GETPOST('dictionary_targets_'.$key, 'array');
		$targetCodes = array();
		foreach (is_array($postedTargets) ? $postedTargets : array() as $targetCode) {
			$targetCode = PowerPlantPVProductDictionary::normalizeImportCode($targetCode);
			if (preg_match('/^[A-Z0-9][A-Z0-9_.-]{0,63}$/D', $targetCode)) {
				$targetCodes[$targetCode] = $targetCode;
			}
		}
		$resolutions[$key] = empty($targetCodes)
			? array('action' => 'ignore')
			: array('action' => 'map', 'target_codes' => array_values($targetCodes));
	}
	return $resolutions;
}

/**
 * Print native Select2 controls resolving dictionary anomalies.
 *
 * @param array<string,array<string,mixed>> $issues Trusted preview issues
 * @param array<string,array<string,mixed>> $resolutions Current decisions
 * @param int $entity Current entity
 * @return void
 */
function powerplantpvTechnicalImportPrintDictionaryResolutionFields(array $issues, array $resolutions, $entity)
{
	global $db, $langs;
	if (empty($issues)) {
		return;
	}
	$form = new Form($db);
	$service = new PowerPlantPVProductDictionary($db);
	$definitions = PowerPlantPVProductDictionary::getDefinitions();
	$optionsByType = array();
	foreach ($definitions as $type => $definition) {
		$optionsByType[$type] = array();
		foreach ($service->fetchCodeMap($type, $entity, false) as $entry) {
			$optionsByType[$type][(string) $entry['code']] = (string) $entry['code'].' - '.(string) $entry['label'];
		}
	}

	print load_fiche_titre($langs->trans('PowerPlantPVImportDictionaryResolutionTitle'), '', 'fa-code-branch');
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('PowerPlantPVImportDictionary').'</td><td>'.$langs->trans('Code').'</td><td>'.$langs->trans('Label').'</td><td>'.$langs->trans('PowerPlantPVImportOccurrences').'</td><td>'.$langs->trans('PowerPlantPVImportIsCodeError').'</td><td>'.$langs->trans('PowerPlantPVImportReplacement').'</td></tr>';
	foreach ($issues as $key => $issue) {
		if (!is_array($issue)) {
			continue;
		}
		$type = isset($issue['type']) ? (string) $issue['type'] : '';
		$definition = isset($definitions[$type]) ? $definitions[$type] : array('label' => 'PowerPlantPVImportDictionary');
		$current = isset($resolutions[$key]) && is_array($resolutions[$key]) ? $resolutions[$key] : array();
		$answer = '';
		if (isset($current['action'])) {
			$answer = $current['action'] === 'create' ? 'no' : 'yes';
		}
		$questionOptions = array('yes' => $langs->trans('Yes'));
		if (!empty($issue['can_create'])) {
			$questionOptions['no'] = $langs->trans('No');
		}
		$selectedTargets = isset($current['target_codes']) && is_array($current['target_codes']) ? $current['target_codes'] : array();
		$occurrences = array();
		if (!empty($issue['occurrence_labels']) && is_array($issue['occurrence_labels'])) {
			$occurrences = $issue['occurrence_labels'];
		} elseif (!empty($issue['occurrences'])) {
			$occurrences[] = (string) ((int) $issue['occurrences']);
		}
		$labels = isset($issue['labels']) && is_array($issue['labels']) ? $issue['labels'] : array();
		print '<tr class="oddeven">';
		print '<td>'.$langs->trans((string) $definition['label']).'</td>';
		print '<td><strong>'.dol_escape_htmltag(isset($issue['code']) ? (string) $issue['code'] : '').'</strong><br><span class="opacitymedium">'.$langs->trans('PowerPlantPVImportDictionaryStatus'.ucfirst((string) (isset($issue['status']) ? $issue['status'] : 'unknown'))).'</span></td>';
		print '<td>'.dol_escape_htmltag(implode(' / ', $labels)).'</td>';
		print '<td>'.dol_escape_htmltag(implode(', ', $occurrences)).'</td>';
		print '<td>'.$form->selectarray('dictionary_is_error_'.$key, $questionOptions, $answer, 1, 0, 0, 'required', 0, 0, 0, '', 'minwidth200 dictionary-error-question', 1).'</td>';
		print '<td class="dictionary-target-cell" data-key="'.dol_escape_htmltag((string) $key).'">'.$form->multiselectarray('dictionary_targets_'.$key, isset($optionsByType[$type]) ? $optionsByType[$type] : array(), $selectedTargets, 0, 0, 'minwidth300').'<br><span class="opacitymedium">'.$langs->trans('PowerPlantPVImportEmptyReplacementKeepsExisting').'</span></td>';
		print '</tr>';
	}
	print '</table></div>';
	print '<script nonce="'.getNonce().'">jQuery(function(){';
	print 'function ppvToggleDictionaryTarget(select){var key=jQuery(select).attr("name").replace("dictionary_is_error_","");jQuery(".dictionary-target-cell[data-key=\""+key+"\"]").toggle(jQuery(select).val()==="yes");}';
	print 'jQuery(".dictionary-error-question").each(function(){ppvToggleDictionaryTarget(this);}).on("change",function(){ppvToggleDictionaryTarget(this);});';
	print '});</script>';
}

/**
 * Merge row-level issues and attach line/reference occurrences.
 *
 * @param array<int,array<string,mixed>> $previews Bulk or single row previews
 * @return array<string,array<string,mixed>>
 */
function powerplantpvTechnicalImportAggregateDictionaryIssues(array $previews)
{
	$issues = array();
	foreach ($previews as $preview) {
		$rowIssues = isset($preview['technical_dictionary_issues']) && is_array($preview['technical_dictionary_issues']) ? $preview['technical_dictionary_issues'] : array();
		foreach ($rowIssues as $key => $issue) {
			if (!is_array($issue)) {
				continue;
			}
			if (!isset($issues[$key])) {
				$issues[$key] = $issue;
				$issues[$key]['occurrence_labels'] = array();
			}
			$line = isset($preview['line']) ? (int) $preview['line'] : 0;
			$ref = isset($preview['ref']) ? (string) $preview['ref'] : '';
			$label = ($line > 0 ? '#'.$line : '').($ref !== '' ? ($line > 0 ? ' - ' : '').$ref : '');
			if ($label !== '') {
				$issues[$key]['occurrence_labels'][$label] = $label;
			}
			$mergedLabels = array_merge(isset($issues[$key]['labels']) && is_array($issues[$key]['labels']) ? $issues[$key]['labels'] : array(), isset($issue['labels']) && is_array($issue['labels']) ? $issue['labels'] : array());
			$issues[$key]['labels'] = array_values(array_unique($mergedLabels));
			$issues[$key]['label'] = count($issues[$key]['labels']) === 1 ? (string) $issues[$key]['labels'][0] : '';
			$labelLength = function_exists('mb_strlen') ? mb_strlen((string) $issues[$key]['label'], 'UTF-8') : strlen((string) $issues[$key]['label']);
			$issues[$key]['can_create'] = (isset($issues[$key]['status']) && $issues[$key]['status'] === 'unknown' && $issues[$key]['label'] !== '' && $labelLength <= 255);
		}
	}
	foreach ($issues as $key => $issue) {
		$issues[$key]['occurrence_labels'] = array_values((array) $issue['occurrence_labels']);
	}
	return $issues;
}

/**
 * Tell whether both PhpSpreadsheet export classes are available.
 *
 * @return bool True when Spreadsheet and IOFactory are available
 */
function powerplantpvProductTechnicalImportHasPhpSpreadsheetExportClasses()
{
	try {
		return class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet') && class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory');
	} catch (Throwable $e) {
		if (function_exists('dol_syslog')) {
			dol_syslog(
				__FUNCTION__.' failed while checking PhpSpreadsheet classes: '.$e->getMessage(),
				(defined('LOG_WARNING') ? LOG_WARNING : 4)
			);
		}
		return false;
	}
}

/**
 * Tell whether XLSX template export is available.
 *
 * @return bool True when PhpSpreadsheet can be loaded
 */
function powerplantpvProductTechnicalImportIsXlsxTemplateAvailable()
{
	return powerplantpvProductTechnicalImportLoadPhpSpreadsheet();
}

/**
 * Load PhpSpreadsheet for XLSX template export.
 *
 * @return bool True when loaded
 */
function powerplantpvProductTechnicalImportLoadPhpSpreadsheet()
{
	if (powerplantpvProductTechnicalImportHasPhpSpreadsheetExportClasses()) {
		return true;
	}

	dol_include_once('/powerplantpv/lib/powerplantpv_serialnumber.lib.php');

	if (function_exists('powerplantpvSerialImportLoadPhpSpreadsheet')) {
		powerplantpvSerialImportLoadPhpSpreadsheet();
	}

	return powerplantpvProductTechnicalImportHasPhpSpreadsheetExportClasses();
}

/**
 * Return template download links HTML.
 *
 * @param int  $productId   Product id
 * @param bool $csvEnabled  CSV import enabled
 * @param bool $xlsxEnabled XLSX import enabled
 * @return string HTML
 */
function powerplantpvProductTechnicalImportTemplateLinksHtml($productId, $csvEnabled, $xlsxEnabled)
{
	global $langs;

	$links = array();
	$templatebaseurl = dol_buildpath('/powerplantpv/product_technical_import.php', 1).'?id='.(int) $productId.'&action=downloadtemplate&token='.newToken();
	if ($csvEnabled) {
		$links[] = '<a id="producttechnicalimport-template-csv" href="'.dol_escape_htmltag($templatebaseurl.'&format=csv').'">'.img_picto('', 'fa-download', 'class="pictofixedwidth"').$langs->trans('ProductTechnicalImportDownloadCsvTemplate').'</a>';
	}
	if ($xlsxEnabled && powerplantpvProductTechnicalImportIsXlsxTemplateAvailable()) {
		$links[] = '<a id="producttechnicalimport-template-xlsx" href="'.dol_escape_htmltag($templatebaseurl.'&format=xlsx').'">'.img_picto('', 'fa-download', 'class="pictofixedwidth"').$langs->trans('ProductTechnicalImportDownloadXlsxTemplate').'</a>';
	}

	if (empty($links)) {
		return '';
	}

	return implode(' &nbsp; ', $links).'<br><span class="opacitymedium">'.dol_escape_htmltag($langs->transnoentities('ProductTechnicalImportTemplateComment')).'</span>';
}

/**
 * Print the product technical import dialog.
 *
 * @param Product $object       Product
 * @param string  $categoryCode Product PV category code
 * @param bool    $openmodal    Open dialog on page load
 * @return void
 */
function powerplantpvProductTechnicalImportPrintDialog($object, $categoryCode, $openmodal = false)
{
	global $db, $langs;

	if (empty($object->id) || !in_array($categoryCode, array('MODULE', 'ONDULE', 'BATTER'), true)) {
		return;
	}

	if (!class_exists('Form')) {
		require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
	}

	$form = new Form($db);
	$csvenabled = (bool) getDolGlobalInt('POWERPLANTPV_COMPONENT_IMPORT_CSV_ENABLED', 1);
	$xlsxenabled = (bool) getDolGlobalInt('POWERPLANTPV_COMPONENT_IMPORT_XLSX_ENABLED', 1);
	$pvfreeenabled = (bool) getDolGlobalInt('POWERPLANTPV_PVFREE_ENABLED');

	$sourceoptions = array();
	if ($csvenabled) {
		$sourceoptions['csv'] = $langs->trans('ProductTechnicalImportCSV');
	}
	if ($xlsxenabled) {
		$sourceoptions['xlsx'] = $langs->trans('ProductTechnicalImportXLSX');
	}
	if (empty($sourceoptions) && !$pvfreeenabled) {
		return;
	}
	$defaultsource = '';
	if (!empty($sourceoptions)) {
		reset($sourceoptions);
		$defaultsource = (string) key($sourceoptions);
	}

	$strategyoptions = array(
		'never' => $langs->trans('ProductTechnicalImportOverwriteNever'),
		'empty_only' => $langs->trans('ProductTechnicalImportOverwriteEmptyOnly'),
		'overwrite_after_confirm' => $langs->trans('ProductTechnicalImportOverwriteAfterConfirm'),
	);
	$separatoroptions = array(
		';' => $langs->trans('ProductTechnicalImportSeparatorSemicolon'),
		',' => $langs->trans('ProductTechnicalImportSeparatorComma'),
		'tab' => $langs->trans('ProductTechnicalImportSeparatorTab'),
	);

	$templatehtml = powerplantpvProductTechnicalImportTemplateLinksHtml((int) $object->id, $csvenabled, $xlsxenabled);
	$accept = ($csvenabled ? '.csv' : '').($csvenabled && $xlsxenabled ? ',' : '').($xlsxenabled ? '.xlsx' : '');

	print '<div id="dialog-producttechnicalimport" class="hideobject">';
	print '<form method="POST" enctype="multipart/form-data" action="'.dol_buildpath('/powerplantpv/product_technical_import.php', 1).'?id='.(int) $object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="upload_file">';
	print '<table class="border centpercent tableforfield">';
	if ($pvfreeenabled) {
		print '<tr><td class="titlefield">'.$langs->trans('PVFreeConnector').'</td><td><a class="button" href="'.dol_buildpath('/powerplantpv/product_pvfree_import.php', 1).'?id='.(int) $object->id.'">'.$langs->trans('PVFreeImportFromPVFree').'</a></td></tr>';
	}
	if (!empty($sourceoptions)) {
		print '<tr><td class="titlefieldcreate">'.$langs->trans('ProductTechnicalImportSource').'</td><td>'.$form->selectarray('import_source', $sourceoptions, $defaultsource, 0, 0, '', 0, 0, 0, '', 'flat minwidth200').'</td></tr>';
		print '<tr><td class="titlefieldcreate">'.$langs->trans('ProductTechnicalImportFile').'</td><td><input type="file" class="flat" name="technical_file" accept="'.$accept.'"></td></tr>';
		if ($templatehtml !== '') {
			print '<tr><td>'.$langs->trans('ProductTechnicalImportDownloadTemplate').'</td><td>'.$templatehtml.'</td></tr>';
		}
		print '<tr><td>'.$langs->trans('ProductTechnicalImportOverwriteStrategy').'</td><td>'.$form->selectarray('strategy', $strategyoptions, getDolGlobalString('POWERPLANTPV_IMPORT_OVERWRITE_EXISTING_DATA', 'empty_only'), 0, 0, '', 0, 0, 0, '', 'flat minwidth300').'</td></tr>';
		print '<tr><td>'.$langs->trans('ProductTechnicalImportDefaultSeparator').'</td><td>'.$form->selectarray('separator', $separatoroptions, getDolGlobalString('POWERPLANTPV_IMPORT_DEFAULT_SEPARATOR', ';'), 0, 0, '', 0, 0, 0, '', 'flat minwidth200').'</td></tr>';
		print '<tr><td>'.$langs->trans('ProductTechnicalImportMaxFileSize').'</td><td>'.((int) getDolGlobalInt('POWERPLANTPV_IMPORT_MAX_FILE_SIZE', 5)).' MB</td></tr>';
	}
	print '</table>';
	print '<div class="center">';
	if (!empty($sourceoptions)) {
		print '<input type="submit" class="button button-add" value="'.$langs->trans('ProductTechnicalImportUpload').'">';
	}
	print ' <input type="button" class="button button-cancel" id="producttechnicalimport-cancel-btn" value="'.$langs->trans('Cancel').'">';
	print '</div>';
	print '</form>';
	print '</div>';
	print '<script nonce="'.getNonce().'">';
	print 'jQuery(function(){';
	print 'jQuery("#dialog-producttechnicalimport").dialog({autoOpen:false,modal:true,width:760,title:"'.dol_escape_js($langs->transnoentitiesnoconv('ProductTechnicalImport')).'"});';
	print 'jQuery("#dialog-producttechnicalimport #import_source,#dialog-producttechnicalimport #strategy,#dialog-producttechnicalimport #separator").select2({width:"resolve",minimumResultsForSearch:0,dropdownCssClass:"ui-dialog"});';
	print 'jQuery("a[id^=\"producttechnicalimport-btn-\"],a[href*=\"product_technical_import.php\"]").on("click", function(e){if (jQuery(this).closest("#dialog-producttechnicalimport").length) { return; } e.preventDefault();jQuery("#dialog-producttechnicalimport").dialog("open");});';
	print 'jQuery("#producttechnicalimport-cancel-btn").on("click", function(){jQuery("#dialog-producttechnicalimport").dialog("close");});';
	if ($openmodal) {
		print 'jQuery("#dialog-producttechnicalimport").dialog("open");';
	}
	print '});';
	print '</script>';
}
