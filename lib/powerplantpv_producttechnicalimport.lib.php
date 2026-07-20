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
			PowerPlantPVProductImport::getInverterMPPTCompositionTemplateFields(4, 2)
		);
	} elseif ($categoryCode === 'BATTER') {
		$type = 'battery';
		$fields = array_merge(PowerPlantPVProductImport::getBatteryImportFields(), PowerPlantPVProductImport::getBatteryAttributeTemplateFields());
	} else {
		$fields = PowerPlantPVProductImport::getModuleImportFields();
	}

	$headers = array();
	foreach ($fields as $field) {
		if ($type === 'battery' && preg_match('/^(protocol|protection|certification)_[0-9]+$/', $field)) {
			$headers[] = $field.' [code]';
			continue;
		}
		$unitfield = $field;
		if ($type === 'inverter' && preg_match('/^mppt_[0-9]+_(?:input_[0-9]+_)?(.+)$/', $field, $matches)) {
			$unitfield = $matches[1];
			$unit = in_array($unitfield, array('voltage_min', 'voltage_max'), true) ? 'V' : (strpos($unitfield, 'current') !== false ? 'A' : ($unitfield === 'max_dc_power' ? 'W' : 'text'));
			$headers[] = $field.' ['.$unit.']';
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
	$templatebaseurl = dol_buildpath('/powerplantpv/product_technical_import.php', 1).'?id='.(int) $productId.'&action=downloadtemplate';
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
