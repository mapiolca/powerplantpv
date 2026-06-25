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
 * \file		lib/powerplantpv_serialnumber.lib.php
 * \ingroup		powerplantpv
 * \brief		Serial number import helpers for power plant composition.
 */

/**
 * Return allowed import extensions.
 *
 * @return	string[]	Extensions
 */
function powerplantpvSerialImportAllowedExtensions()
{
	return array('csv', 'xlsx');
}

/**
 * Load Dolibarr bundled PhpSpreadsheet when available.
 *
 * Dolibarr v20 ships PhpSpreadsheet under htdocs/includes/phpoffice/phpspreadsheet.
 *
 * @return	bool	True when IOFactory is available
 */
function powerplantpvSerialImportLoadPhpSpreadsheet()
{
	if (class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
		return true;
	}

	$candidates = array(
		DOL_DOCUMENT_ROOT.'/includes/phpoffice/phpspreadsheet/src/autoloader.php',
		DOL_DOCUMENT_ROOT.'/includes/phpoffice/phpspreadsheet/src/Bootstrap.php',
		DOL_DOCUMENT_ROOT.'/vendor/autoload.php',
	);

	foreach ($candidates as $candidate) {
		if (is_readable($candidate)) {
			try {
				require_once $candidate;
			} catch (Throwable $e) {
				if (function_exists('dol_syslog')) {
					dol_syslog(
						__FUNCTION__.' failed to load '.$candidate.': '.$e->getMessage(),
						(defined('LOG_WARNING') ? LOG_WARNING : 4)
					);
				}
				continue;
			}
			if (class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
				return true;
			}
		}
	}

	return class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory');
}

/**
 * Tell whether XLSX import reading is available.
 *
 * @return	bool	True if available
 */
function powerplantpvSerialImportIsXlsxReadAvailable()
{
	return (class_exists('ZipArchive') && function_exists('simplexml_load_string'))
		|| powerplantpvSerialImportLoadPhpSpreadsheet();
}

/**
 * Tell whether PhpSpreadsheet XLSX operations are available.
 *
 * Export still relies on PhpSpreadsheet writers.
 *
 * @return	bool	True if available
 */
function powerplantpvSerialImportIsXlsxAvailable()
{
	return powerplantpvSerialImportLoadPhpSpreadsheet();
}

/**
 * Return the upload directory for serial number imports of a power plant.
 *
 * @param	PowerPlant	$object	Power plant
 * @return	string				Absolute directory
 */
function powerplantpvSerialImportGetUploadDir($object)
{
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	dol_include_once('/powerplantpv/lib/powerplantpv_powerplant.lib.php');

	return powerplantGetDocumentUploadDir($object).'/serialimports';
}

/**
 * Return category codes ignored by default for serial-number controls.
 *
 * @return	string[]	Default category codes
 */
function powerplantpvSerialNumberDefaultIgnoredCategoryCodes()
{
	return array('COFFAC', 'COFFDC', 'SYSINT');
}

/**
 * Fetch a Dolibarr constant for a specific entity without switching context.
 *
 * @param	string	$constname	Constant name
 * @param	int		$entity		Entity id
 * @return	string|null			Constant value, null when not found
 */
function powerplantpvSerialNumberFetchEntityConstValue($constname, $entity)
{
	global $db, $conf;

	$entity = (int) ($entity > 0 ? $entity : $conf->entity);
	$sql = "SELECT value";
	$sql .= " FROM ".$db->prefix()."const";
	$sql .= " WHERE name = '".$db->escape($constname)."'";
	$sql .= " AND entity = ".$entity;
	$sql .= $db->plimit(1);

	$resql = $db->query($sql);
	if (!$resql) {
		return null;
	}

	$obj = $db->fetch_object($resql);
	$db->free($resql);
	if (!$obj) {
		return null;
	}

	return (string) $obj->value;
}

/**
 * Parse a category id list from a constant or form value.
 *
 * @param	string|string[]|int[]	$value	Raw ids
 * @return	int[]							Unique positive ids
 */
function powerplantpvSerialNumberParseCategoryIds($value)
{
	if (is_array($value)) {
		$rawvalues = $value;
	} else {
		$rawvalues = preg_split('/[,\s;]+/', (string) $value);
		if (!is_array($rawvalues)) {
			$rawvalues = array();
		}
	}

	$ids = array();
	foreach ($rawvalues as $rawvalue) {
		$id = (int) $rawvalue;
		if ($id > 0) {
			$ids[$id] = $id;
		}
	}

	return array_values($ids);
}

/**
 * Fetch photovoltaic categories.
 *
 * @param	bool	$activeonly	Only active categories
 * @return	array<int,array<string,mixed>>	Categories indexed by id
 */
function powerplantpvSerialNumberFetchPhotovoltaicCategories($activeonly = true)
{
	global $db;

	$categories = array();
	$sql = "SELECT rowid, code, label, active";
	$sql .= " FROM ".$db->prefix()."c_powerplantpv_categorypv";
	if ($activeonly) {
		$sql .= " WHERE active = 1";
	}
	$sql .= " ORDER BY label ASC";

	$resql = $db->query($sql);
	if (!$resql) {
		return $categories;
	}

	while ($obj = $db->fetch_object($resql)) {
		$categories[(int) $obj->rowid] = array(
			'id' => (int) $obj->rowid,
			'code' => (string) $obj->code,
			'label' => (string) $obj->label,
			'active' => (int) $obj->active,
		);
	}
	$db->free($resql);

	return $categories;
}

/**
 * Keep only ids that still exist in the PV category dictionary.
 *
 * @param	string|string[]|int[]	$categoryids	Raw category ids
 * @param	bool					$activeonly		Only active categories
 * @return	int[]									Existing category ids
 */
function powerplantpvSerialNumberFilterExistingCategoryIds($categoryids, $activeonly = false)
{
	$ids = powerplantpvSerialNumberParseCategoryIds($categoryids);
	if (empty($ids)) {
		return array();
	}

	$categories = powerplantpvSerialNumberFetchPhotovoltaicCategories($activeonly);
	$filtered = array();
	foreach ($ids as $id) {
		if (isset($categories[$id])) {
			$filtered[$id] = $id;
		}
	}

	return array_values($filtered);
}

/**
 * Normalize text for default category detection.
 *
 * @param	string	$value	Raw text
 * @return	string			Normalized ASCII uppercase text
 */
function powerplantpvSerialNumberNormalizeCategoryText($value)
{
	$value = trim((string) $value);
	if (function_exists('iconv')) {
		$converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
		if ($converted !== false) {
			$value = $converted;
		}
	}
	$value = strtoupper($value);
	$value = preg_replace('/[^A-Z0-9]+/', ' ', $value);

	return trim((string) $value);
}

/**
 * Tell whether a category matches the default ignored set.
 *
 * @param	array<string,mixed>	$category	Category data
 * @return	bool							True if ignored by default
 */
function powerplantpvSerialNumberCategoryMatchesDefaultIgnore($category)
{
	$code = strtoupper(trim((string) ($category['code'] ?? '')));
	if (in_array($code, powerplantpvSerialNumberDefaultIgnoredCategoryCodes(), true)) {
		return true;
	}

	$label = powerplantpvSerialNumberNormalizeCategoryText((string) ($category['label'] ?? ''));
	if ($label === '') {
		return false;
	}

	$isbox = (strpos($label, 'COFFRET') !== false || strpos($label, 'COMBINER BOX') !== false || strpos($label, 'BOX') !== false);
	if ($isbox && strpos($label, 'AC') !== false) {
		return true;
	}
	if ($isbox && strpos($label, 'DC') !== false) {
		return true;
	}
	if (strpos($label, 'INTEGRATION') !== false || strpos($label, 'MOUNTING SYSTEM') !== false) {
		return true;
	}

	return false;
}

/**
 * Return the default ignored PV category ids.
 *
 * @return	int[]	Default ignored ids
 */
function powerplantpvSerialNumberFetchDefaultIgnoredCategoryIds()
{
	$ids = array();
	$categories = powerplantpvSerialNumberFetchPhotovoltaicCategories(true);
	foreach ($categories as $categoryid => $category) {
		if (powerplantpvSerialNumberCategoryMatchesDefaultIgnore($category)) {
			$ids[(int) $categoryid] = (int) $categoryid;
		}
	}

	return array_values($ids);
}

/**
 * Tell whether the ignored-category setting has been explicitly saved.
 *
 * @param	int	$entity	Entity id
 * @return	bool		True when saved
 */
function powerplantpvSerialNumberIgnoredCategoriesAreConfigured($entity = 0)
{
	global $conf;

	$entity = (int) ($entity > 0 ? $entity : $conf->entity);
	$marker = powerplantpvSerialNumberFetchEntityConstValue('POWERPLANTPV_SERIALNUMBER_IGNORED_CATEGORY_IDS_CONFIGURED', $entity);
	if ($marker !== null) {
		return ((string) $marker === '1');
	}

	return powerplantpvSerialNumberFetchEntityConstValue('POWERPLANTPV_SERIALNUMBER_IGNORED_CATEGORY_IDS', $entity) !== null;
}

/**
 * Return configured ignored category ids for one entity.
 *
 * A missing setting applies the default categories. A saved empty setting means
 * every PV category must be counted.
 *
 * @param	int	$entity	Entity id
 * @return	int[]		Ignored category ids
 */
function powerplantpvSerialNumberGetIgnoredCategoryIds($entity = 0)
{
	global $conf;

	$entity = (int) ($entity > 0 ? $entity : $conf->entity);
	if (!powerplantpvSerialNumberIgnoredCategoriesAreConfigured($entity)) {
		return powerplantpvSerialNumberFetchDefaultIgnoredCategoryIds();
	}

	$value = powerplantpvSerialNumberFetchEntityConstValue('POWERPLANTPV_SERIALNUMBER_IGNORED_CATEGORY_IDS', $entity);

	return powerplantpvSerialNumberFilterExistingCategoryIds((string) $value, false);
}

/**
 * Tell whether a category is ignored for serial-number controls.
 *
 * @param	int	$categoryid	Category id
 * @param	int	$entity		Entity id
 * @return	bool			True if ignored
 */
function powerplantpvSerialNumberIsCategoryIgnored($categoryid, $entity = 0)
{
	$categoryid = (int) $categoryid;
	if ($categoryid <= 0) {
		return false;
	}

	return in_array($categoryid, powerplantpvSerialNumberGetIgnoredCategoryIds((int) $entity), true);
}

/**
 * Return an SQL NOT IN clause fragment for ignored categories.
 *
 * @param	string	$field	SQL field name
 * @param	int		$entity	Entity id
 * @return	string			SQL fragment
 */
function powerplantpvSerialNumberBuildIgnoredCategoryWhere($field, $entity = 0)
{
	$ids = powerplantpvSerialNumberGetIgnoredCategoryIds((int) $entity);
	if (empty($ids)) {
		return '';
	}

	return " AND ".$field." NOT IN (".implode(',', array_map('intval', $ids)).")";
}

/**
 * Return the serial-number display value for generated documents.
 *
 * @param	string|null		$serialnumber	Serial number
 * @param	int				$categoryid		PV category id
 * @param	int				$entity			Entity id
 * @param	Translate|null	$outputlangs	Output language
 * @return	string							Serial number or not-applicable label
 */
function powerplantpvSerialNumberDisplayValue($serialnumber, $categoryid, $entity = 0, $outputlangs = null)
{
	global $langs;

	$value = trim((string) $serialnumber);
	if ($value !== '') {
		return $value;
	}
	if ((int) $categoryid > 0 && powerplantpvSerialNumberIsCategoryIgnored((int) $categoryid, (int) $entity)) {
		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		return $outputlangs->transnoentities('SerialNumbersNotApplicable');
	}

	return '';
}

/**
 * Return categories actually present in the composition.
 *
 * @param	PowerPlant	$object	Power plant
 * @return	array<int,array<string,mixed>>	Categories indexed by category id
 */
function powerplantpvSerialImportFetchCompositionCategories($object)
{
	global $db, $conf;

	$categories = array();
	if (empty($object->id)) {
		return $categories;
	}

	$entity = (!empty($object->entity) ? (int) $object->entity : (int) $conf->entity);
	$ignoredwhere = powerplantpvSerialNumberBuildIgnoredCategoryWhere('cpv.rowid', $entity);

	$sql = "SELECT cpv.rowid, cpv.code, cpv.label, SUM(c.qty) as expected_qty,";
	$sql .= " SUM(CASE WHEN COALESCE(sns.stored_qty, 0) > 0 THEN COALESCE(sns.stored_qty, 0) WHEN c.serial_number IS NOT NULL AND c.serial_number <> '' THEN 1 ELSE 0 END) as stored_qty";
	$sql .= " FROM ".$db->prefix()."powerplantpv_powerplantcomp as c";
	$sql .= " INNER JOIN ".$db->prefix()."product as p ON p.rowid = c.fk_product";
	$sql .= " INNER JOIN ".$db->prefix()."product_extrafields as pe ON pe.fk_object = p.rowid";
	$sql .= " INNER JOIN ".$db->prefix()."c_powerplantpv_categorypv as cpv ON cpv.rowid = pe.categorie_photovoltaique";
	$sql .= " LEFT JOIN (SELECT fk_powerplant_line, entity, COUNT(rowid) as stored_qty FROM ".$db->prefix()."powerplantpv_serialnumber";
	$sql .= " WHERE fk_powerplant = ".((int) $object->id)." AND entity = ".$entity;
	$sql .= " GROUP BY fk_powerplant_line, entity) as sns ON sns.fk_powerplant_line = c.rowid AND sns.entity = c.entity";
	$sql .= " WHERE c.fk_powerplant = ".((int) $object->id);
	$sql .= " AND c.entity = ".$entity;
	$sql .= " AND p.entity IN (".getEntity('product').")";
	$sql .= " AND cpv.active = 1";
	$sql .= $ignoredwhere;
	$sql .= " AND (c.fk_status IS NULL OR c.fk_status <> 6)";
	$sql .= " GROUP BY cpv.rowid, cpv.code, cpv.label";
	$sql .= " ORDER BY cpv.label ASC";

	$resql = $db->query($sql);
	if (!$resql) {
		return $categories;
	}
	while ($obj = $db->fetch_object($resql)) {
		$categories[(int) $obj->rowid] = array(
			'id' => (int) $obj->rowid,
			'code' => (string) $obj->code,
			'label' => (string) $obj->label,
			'expected_qty' => (int) round((float) $obj->expected_qty),
			'stored_qty' => (int) $obj->stored_qty,
		);
	}

	return $categories;
}

/**
 * Return composition lines for one PV category.
 *
 * @param	int	$powerplantid	Power plant id
 * @param	int	$categoryid		Category id
 * @param	int	$entity			Entity id
 * @return	array<int,array<string,mixed>>	Lines indexed by line id
 */
function powerplantpvSerialImportFetchCategoryLines($powerplantid, $categoryid, $entity)
{
	global $db;

	$lines = array();
	if ($powerplantid <= 0 || $categoryid <= 0) {
		return $lines;
	}
	if (powerplantpvSerialNumberIsCategoryIgnored((int) $categoryid, (int) $entity)) {
		return $lines;
	}

	$sql = "SELECT c.rowid, c.fk_product, c.qty, c.serial_number as composition_serial_number, p.ref as product_ref, p.label as product_label,";
	$sql .= " cpv.rowid as fk_categorie, cpv.label as category_label, COALESCE(sns.stored_qty, 0) as stored_qty";
	$sql .= " FROM ".$db->prefix()."powerplantpv_powerplantcomp as c";
	$sql .= " INNER JOIN ".$db->prefix()."product as p ON p.rowid = c.fk_product";
	$sql .= " INNER JOIN ".$db->prefix()."product_extrafields as pe ON pe.fk_object = p.rowid";
	$sql .= " INNER JOIN ".$db->prefix()."c_powerplantpv_categorypv as cpv ON cpv.rowid = pe.categorie_photovoltaique";
	$sql .= " LEFT JOIN (SELECT fk_powerplant_line, entity, COUNT(rowid) as stored_qty FROM ".$db->prefix()."powerplantpv_serialnumber";
	$sql .= " WHERE fk_powerplant = ".((int) $powerplantid)." AND entity = ".((int) $entity);
	$sql .= " GROUP BY fk_powerplant_line, entity) as sns ON sns.fk_powerplant_line = c.rowid AND sns.entity = c.entity";
	$sql .= " WHERE c.fk_powerplant = ".((int) $powerplantid);
	$sql .= " AND c.entity = ".((int) $entity);
	$sql .= " AND pe.categorie_photovoltaique = ".((int) $categoryid);
	$sql .= " AND p.entity IN (".getEntity('product').")";
	$sql .= " AND (c.fk_status IS NULL OR c.fk_status <> 6)";
	$sql .= " ORDER BY p.ref ASC, c.rowid ASC";

	$resql = $db->query($sql);
	if (!$resql) {
		return $lines;
	}
	while ($obj = $db->fetch_object($resql)) {
		$productdisplay = $obj->product_ref;
		if (!empty($obj->product_label)) {
			$productdisplay .= ' - '.$obj->product_label;
		}
		$lines[(int) $obj->rowid] = array(
			'rowid' => (int) $obj->rowid,
			'fk_product' => (int) $obj->fk_product,
			'qty' => (int) round((float) $obj->qty),
			'product_ref' => (string) $obj->product_ref,
			'product_label' => (string) $obj->product_label,
			'product_display' => (string) $productdisplay,
			'fk_categorie' => (int) $obj->fk_categorie,
			'category_label' => (string) $obj->category_label,
			'composition_serial_number' => (string) $obj->composition_serial_number,
			'stored_qty' => (int) $obj->stored_qty,
		);
	}

	return $lines;
}

/**
 * Build rows for a serial number import template.
 *
 * @param	PowerPlant	$object		Power plant
 * @param	int			$categoryid	Category id
 * @return	array<string,mixed>		Template headers and rows
 */
function powerplantpvSerialImportBuildTemplateData($object, $categoryid)
{
	global $conf;

	$template = array(
		'headers' => array('product_ref', 'serial_number', 'product_label', 'comment'),
		'rows' => array(),
	);
	if (empty($object->id) || $categoryid <= 0) {
		return $template;
	}

	$entity = (!empty($object->entity) ? (int) $object->entity : (int) $conf->entity);
	$lines = powerplantpvSerialImportFetchCategoryLines((int) $object->id, (int) $categoryid, $entity);
	foreach ($lines as $line) {
		$missingqty = powerplantpvSerialImportLineCapacity($line, 'add');
		for ($i = 0; $i < $missingqty; $i++) {
			$template['rows'][] = array(
				'product_ref' => (string) $line['product_ref'],
				'serial_number' => '',
				'product_label' => (string) $line['product_label'],
				'comment' => '',
			);
		}
	}

	return $template;
}

/**
 * Print the serial number import dialog.
 *
 * @param	PowerPlant	$object				Power plant
 * @param	int			$selectedcategoryid	Selected category id
 * @param	bool		$openmodal			Open dialog on page load
 * @return	void
 */
function powerplantpvSerialImportPrintDialog($object, $selectedcategoryid = 0, $openmodal = false)
{
	global $db, $langs;

	if (empty($object->id)) {
		return;
	}

	if (!class_exists('Form')) {
		require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
	}

	$form = new Form($db);
	$serialimportcategories = powerplantpvSerialImportFetchCompositionCategories($object);
	if (empty($serialimportcategories)) {
		return;
	}

	$serialcategoryoptions = array();
	foreach ($serialimportcategories as $serialcatid => $serialcat) {
		$serialcategoryoptions[(int) $serialcatid] = $serialcat['label'].' ('.((int) $serialcat['expected_qty']).')';
	}
	$defaultserialcategory = (int) $selectedcategoryid;
	if (empty($serialcategoryoptions[$defaultserialcategory])) {
		reset($serialcategoryoptions);
		$defaultserialcategory = (int) key($serialcategoryoptions);
	}

	$serialtemplatebaseurl = dol_buildpath('/powerplantpv/serialimport.php', 1).'?id='.(int) $object->id.'&action=downloadtemplate';
	$serialtemplatecsvurl = $serialtemplatebaseurl.'&format=csv&fk_categorie='.(int) $defaultserialcategory;
	$serialtemplatexlsxurl = $serialtemplatebaseurl.'&format=xlsx&fk_categorie='.(int) $defaultserialcategory;
	$serialtemplatexlsxavailable = powerplantpvSerialImportIsXlsxAvailable();

	print '<div id="dialog-serialimport" class="hideobject">';
	print '<form method="POST" enctype="multipart/form-data" action="'.dol_buildpath('/powerplantpv/serialimport.php', 1).'?id='.(int) $object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="uploadserials">';
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefieldcreate">'.$langs->trans('SerialNumbersCategoryToImport').'</td><td>'.$form->selectarray('fk_categorie', $serialcategoryoptions, $defaultserialcategory, 0, 0, '', 0, 0, 0, '', 'flat minwidth300').'</td></tr>';
	print '<tr><td class="titlefieldcreate">'.$langs->trans('SerialNumbersFileToImport').'</td><td><input type="file" class="flat" name="serial_file" accept=".csv,.xlsx"></td></tr>';
	print '<tr><td>'.$langs->trans('SerialNumbersDownloadTemplate').'</td><td>';
	print '<a id="serialimport-template-csv" href="'.dol_escape_htmltag($serialtemplatecsvurl).'">'.img_picto('', 'fa-download', 'class="pictofixedwidth"').$langs->trans('SerialNumbersDownloadCsvTemplate').'</a>';
	if ($serialtemplatexlsxavailable) {
		print ' &nbsp; <a id="serialimport-template-xlsx" href="'.dol_escape_htmltag($serialtemplatexlsxurl).'">'.img_picto('', 'fa-download', 'class="pictofixedwidth"').$langs->trans('SerialNumbersDownloadXlsxTemplate').'</a>';
	}
	print '<br><span class="opacitymedium">'.dol_escape_htmltag($langs->transnoentities('SerialNumbersTemplateComment')).'</span>';
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('SerialNumbersFirstLineHeaders').'</td><td><input type="checkbox" class="flat" name="first_line_headers" value="1" checked></td></tr>';
	print '<tr><td>'.$langs->trans('SerialNumbersImportMode').'</td><td>'.$form->selectarray('import_mode', array('add' => $langs->trans('SerialNumbersAddOnly'), 'replace' => $langs->trans('SerialNumbersReplaceExisting')), 'add', 0, 0, '', 0, 0, 0, '', 'flat minwidth300').'</td></tr>';
	print '</table>';
	print '<div class="center">';
	print '<input type="submit" class="button button-add" value="'.$langs->trans('SerialNumbersImportSubmit').'">';
	print ' <input type="button" class="button button-cancel" id="serialimport-cancel-btn" value="'.$langs->trans('Cancel').'">';
	print '</div>';
	print '</form>';
	print '</div>';
	print '<script nonce="'.getNonce().'">';
	print 'jQuery(function(){';
	print 'jQuery("#dialog-serialimport").dialog({autoOpen:false,modal:true,width:720,title:"'.dol_escape_js($langs->transnoentitiesnoconv('SerialNumbersImport')).'"});';
	print 'jQuery("#dialog-serialimport #fk_categorie,#dialog-serialimport #import_mode").select2({width:"resolve",minimumResultsForSearch:0,dropdownCssClass:"ui-dialog"});';
	print 'var serialimportTemplateBaseUrl = "'.dol_escape_js($serialtemplatebaseurl).'";';
	print 'function powerplantpvRefreshSerialImportTemplateLinks(){';
	print 'var category = jQuery("#dialog-serialimport #fk_categorie").val() || "'.((int) $defaultserialcategory).'";';
	print 'jQuery("#serialimport-template-csv").attr("href", serialimportTemplateBaseUrl+"&format=csv&fk_categorie="+encodeURIComponent(category));';
	if ($serialtemplatexlsxavailable) {
		print 'jQuery("#serialimport-template-xlsx").attr("href", serialimportTemplateBaseUrl+"&format=xlsx&fk_categorie="+encodeURIComponent(category));';
	}
	print '}';
	print 'powerplantpvRefreshSerialImportTemplateLinks();';
	print 'jQuery("#dialog-serialimport #fk_categorie").on("change", powerplantpvRefreshSerialImportTemplateLinks);';
	print 'jQuery("a[href*=\"action=serialimport\"]").on("click", function(e){e.preventDefault();jQuery("#dialog-serialimport").dialog("open");});';
	print 'jQuery("#serialimport-cancel-btn").on("click", function(){jQuery("#dialog-serialimport").dialog("close");});';
	if ($openmodal) {
		print 'jQuery("#dialog-serialimport").dialog("open");';
	}
	print '});';
	print '</script>';
}

/**
 * Return grouped composition serial number summary.
 *
 * @param	PowerPlant	$object	Power plant
 * @return	array<int,array<string,mixed>>	Summary rows
 */
function powerplantpvSerialNumberFetchCompositionSummary($object)
{
	global $db, $conf;

	$summary = array();
	if (empty($object->id)) {
		return $summary;
	}

	$entity = (!empty($object->entity) ? (int) $object->entity : (int) $conf->entity);
	$ignoredwhere = powerplantpvSerialNumberBuildIgnoredCategoryWhere('cpv.rowid', $entity);

	$sql = "SELECT c.rowid as fk_powerplant_line, cpv.rowid as fk_categorie, cpv.label as category_label, p.rowid as fk_product, p.ref as product_ref, p.label as product_label,";
	$sql .= " c.qty as expected_qty,";
	$sql .= " CASE WHEN COALESCE(sns.stored_qty, 0) > 0 THEN COALESCE(sns.stored_qty, 0) WHEN c.serial_number IS NOT NULL AND c.serial_number <> '' THEN 1 ELSE 0 END as stored_qty";
	$sql .= " FROM ".$db->prefix()."powerplantpv_powerplantcomp as c";
	$sql .= " INNER JOIN ".$db->prefix()."product as p ON p.rowid = c.fk_product";
	$sql .= " INNER JOIN ".$db->prefix()."product_extrafields as pe ON pe.fk_object = p.rowid";
	$sql .= " INNER JOIN ".$db->prefix()."c_powerplantpv_categorypv as cpv ON cpv.rowid = pe.categorie_photovoltaique";
	$sql .= " LEFT JOIN (SELECT fk_powerplant_line, entity, COUNT(rowid) as stored_qty FROM ".$db->prefix()."powerplantpv_serialnumber";
	$sql .= " WHERE fk_powerplant = ".((int) $object->id)." AND entity = ".$entity;
	$sql .= " GROUP BY fk_powerplant_line, entity) as sns ON sns.fk_powerplant_line = c.rowid AND sns.entity = c.entity";
	$sql .= " WHERE c.fk_powerplant = ".((int) $object->id);
	$sql .= " AND c.entity = ".$entity;
	$sql .= " AND p.entity IN (".getEntity('product').")";
	$sql .= $ignoredwhere;
	$sql .= " AND (c.fk_status IS NULL OR c.fk_status <> 6)";
	$sql .= " ORDER BY cpv.label ASC, p.ref ASC, c.rowid ASC";

	$resql = $db->query($sql);
	if (!$resql) {
		return $summary;
	}
	while ($obj = $db->fetch_object($resql)) {
		$productdisplay = $obj->product_ref;
		if (!empty($obj->product_label)) {
			$productdisplay .= ' - '.$obj->product_label;
		}
		$summary[] = array(
			'fk_powerplant_line' => (int) $obj->fk_powerplant_line,
			'fk_categorie' => (int) $obj->fk_categorie,
			'category_label' => (string) $obj->category_label,
			'fk_product' => (int) $obj->fk_product,
			'product_ref' => (string) $obj->product_ref,
			'product_label' => (string) $obj->product_label,
			'product_display' => (string) $productdisplay,
			'expected_qty' => (int) round((float) $obj->expected_qty),
			'stored_qty' => (int) $obj->stored_qty,
		);
	}

	return $summary;
}

/**
 * Return compact serial number traceability counters for a power plant.
 *
 * @param	PowerPlant	$object	Power plant
 * @return	array<string,mixed>	Traceability summary
 */
function powerplantpvSerialNumberFetchTraceabilitySummary($object)
{
	global $db, $conf;

	$summary = array(
		'expected_qty' => 0,
		'stored_qty' => 0,
		'missing_qty' => 0,
		'composition_rows' => array(),
		'missing_rows' => array(),
		'first_missing_category' => 0,
		'last_import' => null,
	);
	if (empty($object->id)) {
		return $summary;
	}

	$rows = powerplantpvSerialNumberFetchCompositionSummary($object);
	$summary['composition_rows'] = $rows;
	$productgroups = array();
	foreach ($rows as $row) {
		$expectedqty = max(0, (int) $row['expected_qty']);
		$storedqty = max(0, (int) $row['stored_qty']);
		$summary['expected_qty'] += $expectedqty;
		$summary['stored_qty'] += $storedqty;

		$productkey = ((int) $row['fk_categorie']).'-'.(string) $row['product_ref'];
		if (empty($productgroups[$productkey])) {
			$productgroups[$productkey] = array(
				'fk_categorie' => (int) $row['fk_categorie'],
				'category_label' => (string) $row['category_label'],
				'fk_product' => (int) $row['fk_product'],
				'product_ref' => (string) $row['product_ref'],
				'product_label' => (string) $row['product_label'],
				'product_display' => (string) $row['product_display'],
				'expected_qty' => 0,
				'stored_qty' => 0,
				'missing_qty' => 0,
			);
		}
		$productgroups[$productkey]['expected_qty'] += $expectedqty;
		$productgroups[$productkey]['stored_qty'] += $storedqty;
	}
	$summary['missing_qty'] = max(0, (int) $summary['expected_qty'] - (int) $summary['stored_qty']);
	foreach ($productgroups as $productgroup) {
		$productgroup['missing_qty'] = max(0, (int) $productgroup['expected_qty'] - (int) $productgroup['stored_qty']);
		if ($productgroup['missing_qty'] <= 0) {
			continue;
		}
		if (empty($summary['first_missing_category'])) {
			$summary['first_missing_category'] = (int) $productgroup['fk_categorie'];
		}
		$summary['missing_rows'][] = $productgroup;
	}

	$entity = (!empty($object->entity) ? (int) $object->entity : (int) $conf->entity);
	$sql = "SELECT si.rowid, si.datec, si.filename, u.login, u.firstname, u.lastname";
	$sql .= " FROM ".$db->prefix()."powerplantpv_serialnumber_import as si";
	$sql .= " LEFT JOIN ".$db->prefix()."user as u ON u.rowid = si.fk_user";
	$sql .= " WHERE si.fk_powerplant = ".((int) $object->id);
	$sql .= " AND si.entity = ".$entity;
	$sql .= " AND si.status = 'validated'";
	$sql .= " ORDER BY si.datec DESC, si.rowid DESC";
	$sql .= $db->plimit(1);
	$resql = $db->query($sql);
	if ($resql && ($obj = $db->fetch_object($resql))) {
		$username = trim((string) $obj->firstname.' '.(string) $obj->lastname);
		if ($username === '') {
			$username = (string) $obj->login;
		}
		$summary['last_import'] = array(
			'id' => (int) $obj->rowid,
			'datec' => (string) $obj->datec,
			'filename' => (string) $obj->filename,
			'user_name' => $username,
		);
	}

	return $summary;
}

/**
 * Return detailed missing serial number summary for a power plant.
 *
 * @param	PowerPlant	$object	Power plant
 * @return	array<string,mixed>	Missing serial number summary
 */
function powerplantpvSerialNumberFetchMissingSummary($object)
{
	return powerplantpvSerialNumberFetchTraceabilitySummary($object);
}

/**
 * Normalize an import header.
 *
 * @param	string	$value	Header
 * @return	string			Normalized header
 */
function powerplantpvSerialImportNormalizeHeader($value)
{
	$value = trim((string) $value);
	$value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
	if (function_exists('iconv')) {
		$converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
		if ($converted !== false) {
			$value = $converted;
		}
	}
	$value = strtolower($value);
	$value = preg_replace('/[^a-z0-9]+/', '_', $value);
	$value = trim((string) $value, '_');

	$aliases = array(
		'serial' => 'serial_number',
		'serialnumber' => 'serial_number',
		'serial_no' => 'serial_number',
		'serial_num' => 'serial_number',
		'sn' => 'serial_number',
		's_n' => 'serial_number',
		'ns' => 'serial_number',
		'n_s' => 'serial_number',
		'numero_serie' => 'serial_number',
		'numero_de_serie' => 'serial_number',
		'num_serie' => 'serial_number',
		'n_serie' => 'serial_number',
		'no_serie' => 'serial_number',
		'n_de_serie' => 'serial_number',
		'no_de_serie' => 'serial_number',
		'product' => 'product_ref',
		'ref' => 'product_ref',
		'reference' => 'product_ref',
		'r_f_rence' => 'product_ref',
		'product_reference' => 'product_ref',
		'ref_product' => 'product_ref',
		'ref_produit' => 'product_ref',
		'reference_produit' => 'product_ref',
		'produit' => 'product_ref',
		'label' => 'product_label',
		'libelle' => 'product_label',
		'commentaire' => 'comment',
		'notes' => 'comment',
	);
	if (isset($aliases[$value])) {
		return $aliases[$value];
	}

	return $value;
}

/**
 * Normalize a cell value.
 *
 * @param	mixed	$value	Cell value
 * @return	string			Normalized value
 */
function powerplantpvSerialImportNormalizeValue($value)
{
	$value = trim((string) $value);
	$value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
	$value = preg_replace('/[\r\n\t]+/', ' ', $value);

	return trim((string) $value);
}

/**
 * Normalize a serial number for duplicate checks.
 *
 * @param	string	$serial	Serial number
 * @return	string			Comparison key
 */
function powerplantpvSerialImportSerialKey($serial)
{
	return strtoupper(powerplantpvSerialImportNormalizeValue($serial));
}

/**
 * Read an import file into normalized rows.
 *
 * @param	string	$filepath			File path
 * @param	string	$extension			File extension
 * @param	int		$firstlineheaders	1 if first line contains headers
 * @return	array<string,mixed>		Read result
 */
function powerplantpvSerialImportReadFile($filepath, $extension, $firstlineheaders)
{
	$extension = strtolower((string) $extension);
	if ($extension === 'csv') {
		return powerplantpvSerialImportReadCsv($filepath, $firstlineheaders);
	}
	if ($extension === 'xlsx') {
		return powerplantpvSerialImportReadXlsx($filepath, $firstlineheaders);
	}

	return array('rows' => array(), 'errors' => array('SerialNumbersUnsupportedFileExtension'), 'warnings' => array(), 'unknown_columns' => array());
}

/**
 * Read a CSV file.
 *
 * @param	string	$filepath			File path
 * @param	int		$firstlineheaders	1 if first line contains headers
 * @return	array<string,mixed>		Read result
 */
function powerplantpvSerialImportReadCsv($filepath, $firstlineheaders)
{
	$errors = array();
	$warnings = array();
	$matrix = array();

	$handle = @fopen($filepath, 'rb');
	if (!$handle) {
		return array('rows' => array(), 'errors' => array('SerialNumbersFileNotReadable'), 'warnings' => array(), 'unknown_columns' => array());
	}

	$firstline = fgets($handle);
	if ($firstline === false) {
		fclose($handle);
		return array('rows' => array(), 'errors' => array('SerialNumbersNoUsableLine'), 'warnings' => array(), 'unknown_columns' => array());
	}

	$delimiter = ';';
	$delimiters = array(';' => substr_count($firstline, ';'), ',' => substr_count($firstline, ','), "\t" => substr_count($firstline, "\t"));
	arsort($delimiters);
	foreach ($delimiters as $candidate => $count) {
		if ($count > 0) {
			$delimiter = $candidate;
			break;
		}
	}

	rewind($handle);
	$line = 1;
	while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
		$matrix[] = array('line' => $line, 'cells' => $data);
		$line++;
	}
	fclose($handle);

	$result = powerplantpvSerialImportRowsFromMatrix($matrix, $firstlineheaders);
	$result['errors'] = array_merge($errors, $result['errors']);
	$result['warnings'] = array_merge($warnings, $result['warnings']);

	return $result;
}

/**
 * Read an XLSX file.
 *
 * @param	string	$filepath			File path
 * @param	int		$firstlineheaders	1 if first line contains headers
 * @return	array<string,mixed>		Read result
 */
function powerplantpvSerialImportReadXlsx($filepath, $firstlineheaders)
{
	$native = powerplantpvSerialImportReadXlsxNative($filepath, $firstlineheaders);
	if (empty($native['errors'])
		|| in_array('SerialNumbersColumnSerialNotFound', $native['errors'], true)
		|| in_array('SerialNumbersNoUsableLine', $native['errors'], true)
	) {
		return $native;
	}

	if (!powerplantpvSerialImportLoadPhpSpreadsheet()) {
		return $native;
	}

	try {
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filepath);
		$worksheet = $spreadsheet->getActiveSheet();
		$array = $worksheet->toArray('', false, false, false);
	} catch (Throwable $e) {
		if (function_exists('dol_syslog')) {
			dol_syslog(
				__FUNCTION__.' failed to read '.$filepath.': '.$e->getMessage(),
				(defined('LOG_WARNING') ? LOG_WARNING : 4)
			);
		}
		return $native;
	}

	$matrix = array();
	foreach ($array as $idx => $cells) {
		$matrix[] = array('line' => $idx + 1, 'cells' => $cells);
	}

	return powerplantpvSerialImportRowsFromMatrix($matrix, $firstlineheaders);
}

/**
 * Read an XLSX file without external Composer dependencies.
 *
 * @param	string	$filepath			File path
 * @param	int		$firstlineheaders	1 if first line contains headers
 * @return	array<string,mixed>		Read result
 */
function powerplantpvSerialImportReadXlsxNative($filepath, $firstlineheaders)
{
	if (!class_exists('ZipArchive') || !function_exists('simplexml_load_string')) {
		return array(
			'rows' => array(),
			'errors' => array('SerialNumbersXlsxReaderUnavailable'),
			'warnings' => array(),
			'unknown_columns' => array(),
		);
	}

	$zip = new ZipArchive();
	if ($zip->open($filepath) !== true) {
		return array(
			'rows' => array(),
			'errors' => array('SerialNumbersFileNotReadable'),
			'warnings' => array(),
			'unknown_columns' => array(),
		);
	}

	$sheetpath = powerplantpvSerialImportXlsxGetFirstSheetPath($zip);
	if ($sheetpath === '') {
		$zip->close();
		return array(
			'rows' => array(),
			'errors' => array('SerialNumbersFileNotReadable'),
			'warnings' => array(),
			'unknown_columns' => array(),
		);
	}

	$sheetxml = $zip->getFromName($sheetpath);
	if ($sheetxml === false) {
		$zip->close();
		return array(
			'rows' => array(),
			'errors' => array('SerialNumbersFileNotReadable'),
			'warnings' => array(),
			'unknown_columns' => array(),
		);
	}

	$sharedstrings = powerplantpvSerialImportXlsxReadSharedStrings($zip);
	$zip->close();

	$matrix = powerplantpvSerialImportXlsxSheetToMatrix($sheetxml, $sharedstrings);
	if ($matrix === false) {
		return array(
			'rows' => array(),
			'errors' => array('SerialNumbersFileNotReadable'),
			'warnings' => array(),
			'unknown_columns' => array(),
		);
	}

	return powerplantpvSerialImportRowsFromMatrix($matrix, $firstlineheaders);
}

/**
 * Return the first worksheet path from an XLSX archive.
 *
 * @param	ZipArchive	$zip	XLSX archive
 * @return	string				Worksheet path inside the archive
 */
function powerplantpvSerialImportXlsxGetFirstSheetPath($zip)
{
	$fallback = 'xl/worksheets/sheet1.xml';
	$workbookxml = $zip->getFromName('xl/workbook.xml');
	$relsxml = $zip->getFromName('xl/_rels/workbook.xml.rels');
	if ($workbookxml === false || $relsxml === false) {
		return ($zip->locateName($fallback) !== false ? $fallback : '');
	}

	$mainns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
	$relsns = 'http://schemas.openxmlformats.org/package/2006/relationships';
	$officerelsns = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	$workbook = powerplantpvSerialImportXlsxLoadXml($workbookxml);
	$rels = powerplantpvSerialImportXlsxLoadXml($relsxml);
	if ($workbook === false || $rels === false) {
		return ($zip->locateName($fallback) !== false ? $fallback : '');
	}

	$targets = array();
	foreach ($rels->children($relsns)->Relationship as $relationship) {
		$attrs = $relationship->attributes();
		$id = (string) $attrs['Id'];
		if ($id !== '') {
			$targets[$id] = (string) $attrs['Target'];
		}
	}

	$activeindex = 0;
	$bookviews = $workbook->children($mainns)->bookViews;
	foreach ($bookviews->children($mainns)->workbookView as $workbookview) {
		$viewattrs = $workbookview->attributes();
		$activetab = (string) $viewattrs['activeTab'];
		if ($activetab !== '') {
			$activeindex = (int) $activetab;
			break;
		}
	}

	$sheetpaths = array();
	$sheets = $workbook->children($mainns)->sheets;
	foreach ($sheets->children($mainns)->sheet as $sheet) {
		$attrs = $sheet->attributes($officerelsns);
		$rid = (string) $attrs['id'];
		if ($rid !== '' && !empty($targets[$rid])) {
			$path = powerplantpvSerialImportXlsxNormalizeTargetPath('xl/workbook.xml', $targets[$rid]);
			if ($path !== '' && $zip->locateName($path) !== false) {
				$sheetpaths[] = $path;
			}
		}
	}
	if (!empty($sheetpaths[$activeindex])) {
		return $sheetpaths[$activeindex];
	}
	if (!empty($sheetpaths[0])) {
		return $sheetpaths[0];
	}

	return ($zip->locateName($fallback) !== false ? $fallback : '');
}

/**
 * Read shared strings from an XLSX archive.
 *
 * @param	ZipArchive	$zip	XLSX archive
 * @return	string[]			Shared strings indexed by position
 */
function powerplantpvSerialImportXlsxReadSharedStrings($zip)
{
	$strings = array();
	$xml = $zip->getFromName('xl/sharedStrings.xml');
	if ($xml === false) {
		return $strings;
	}

	$mainns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
	$sst = powerplantpvSerialImportXlsxLoadXml($xml);
	if ($sst === false) {
		return $strings;
	}

	foreach ($sst->children($mainns)->si as $si) {
		$strings[] = powerplantpvSerialImportXlsxReadStringNode($si);
	}

	return $strings;
}

/**
 * Convert worksheet XML to the import matrix.
 *
 * @param	string		$sheetxml		Worksheet XML
 * @param	string[]	$sharedstrings	Shared strings
 * @return	array<int,array<string,mixed>>|false	Matrix or false on parsing error
 */
function powerplantpvSerialImportXlsxSheetToMatrix($sheetxml, $sharedstrings)
{
	$mainns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
	$sheet = powerplantpvSerialImportXlsxLoadXml($sheetxml);
	if ($sheet === false) {
		return false;
	}

	$matrix = array();
	$rowposition = 0;
	$sheetdata = $sheet->children($mainns)->sheetData;
	foreach ($sheetdata->children($mainns)->row as $rownode) {
		$rowposition++;
		$rowattrs = $rownode->attributes();
		$rowref = (string) $rowattrs['r'];
		$linenumber = ($rowref !== '' ? (int) $rowref : $rowposition);
		$cells = array();
		$nextcol = 0;
		foreach ($rownode->children($mainns)->c as $cellnode) {
			$cellattrs = $cellnode->attributes();
			$cellref = (string) $cellattrs['r'];
			$colindex = ($cellref !== '' ? powerplantpvSerialImportXlsxColumnIndex($cellref) : $nextcol);
			$cells[$colindex] = powerplantpvSerialImportXlsxCellValue($cellnode, $sharedstrings);
			$nextcol = $colindex + 1;
		}
		if (empty($cells)) {
			continue;
		}

		ksort($cells);
		$maxcol = (int) max(array_keys($cells));
		$dense = array();
		for ($i = 0; $i <= $maxcol; $i++) {
			$dense[] = isset($cells[$i]) ? $cells[$i] : '';
		}
		$matrix[] = array('line' => $linenumber, 'cells' => $dense);
	}

	return $matrix;
}

/**
 * Safely load XML.
 *
 * @param	string	$xml	XML content
 * @return	SimpleXMLElement|false	XML object or false
 */
function powerplantpvSerialImportXlsxLoadXml($xml)
{
	$previous = libxml_use_internal_errors(true);
	$object = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	libxml_clear_errors();
	libxml_use_internal_errors($previous);

	return $object;
}

/**
 * Read an XLSX shared or inline string node.
 *
 * @param	SimpleXMLElement	$node	String node
 * @return	string					Text
 */
function powerplantpvSerialImportXlsxReadStringNode($node)
{
	$mainns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
	$children = $node->children($mainns);
	$text = '';
	if (isset($children->t)) {
		$text .= (string) $children->t;
	}
	foreach ($children->r as $run) {
		$rchildren = $run->children($mainns);
		if (isset($rchildren->t)) {
			$text .= (string) $rchildren->t;
		}
	}

	return $text;
}

/**
 * Return the display value of an XLSX cell.
 *
 * @param	SimpleXMLElement	$cellnode		Cell XML node
 * @param	string[]			$sharedstrings	Shared strings
 * @return	string								Cell value
 */
function powerplantpvSerialImportXlsxCellValue($cellnode, $sharedstrings)
{
	$mainns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
	$attrs = $cellnode->attributes();
	$type = (string) $attrs['t'];
	$children = $cellnode->children($mainns);

	if ($type === 'inlineStr') {
		return isset($children->is) ? powerplantpvSerialImportXlsxReadStringNode($children->is) : '';
	}

	$value = isset($children->v) ? (string) $children->v : '';
	if ($type === 's') {
		$index = (int) $value;
		return isset($sharedstrings[$index]) ? $sharedstrings[$index] : '';
	}
	if ($type === 'b') {
		return ($value === '1' ? '1' : '0');
	}

	return $value;
}

/**
 * Convert a cell reference to a zero-based column index.
 *
 * @param	string	$cellref	Cell reference, for example B12
 * @return	int					Zero-based column index
 */
function powerplantpvSerialImportXlsxColumnIndex($cellref)
{
	$letters = preg_replace('/[^A-Z]/', '', strtoupper($cellref));
	if ($letters === '') {
		return 0;
	}

	$index = 0;
	$length = strlen($letters);
	for ($i = 0; $i < $length; $i++) {
		$index = ($index * 26) + (ord($letters[$i]) - 64);
	}

	return max(0, $index - 1);
}

/**
 * Normalize a relationship target path.
 *
 * @param	string	$basepath	Base path
 * @param	string	$target		Relationship target
 * @return	string				Archive path
 */
function powerplantpvSerialImportXlsxNormalizeTargetPath($basepath, $target)
{
	$target = str_replace('\\', '/', (string) $target);
	if ($target === '') {
		return '';
	}
	if ($target[0] === '/') {
		return ltrim($target, '/');
	}

	$parts = explode('/', $basepath);
	array_pop($parts);
	foreach (explode('/', $target) as $part) {
		if ($part === '' || $part === '.') {
			continue;
		}
		if ($part === '..') {
			array_pop($parts);
			continue;
		}
		$parts[] = $part;
	}

	return implode('/', $parts);
}

/**
 * Convert a matrix into normalized import rows.
 *
 * @param	array<int,array<string,mixed>>	$matrix				Raw matrix
 * @param	int								$firstlineheaders	1 if first line contains headers
 * @return	array<string,mixed>									Read result
 */
function powerplantpvSerialImportRowsFromMatrix($matrix, $firstlineheaders)
{
	$errors = array();
	$warnings = array();
	$rows = array();
	$unknowncolumns = array();
	$allowed = array('serial_number', 'product_ref', 'product_label', 'manufacturer', 'model', 'comment');

	$header = array();
	$headerline = 0;
	if (!empty($firstlineheaders)) {
		foreach ($matrix as $matrixrow) {
			$cells = (array) $matrixrow['cells'];
			$hasvalue = false;
			foreach ($cells as $cell) {
				if (powerplantpvSerialImportNormalizeValue($cell) !== '') {
					$hasvalue = true;
					break;
				}
			}
			if (!$hasvalue) {
				continue;
			}
			foreach ($cells as $idx => $cell) {
				$key = powerplantpvSerialImportNormalizeHeader((string) $cell);
				$header[$idx] = $key;
				if ($key !== '' && !in_array($key, $allowed, true)) {
					$unknowncolumns[$key] = $key;
				}
			}
			$headerline = (int) $matrixrow['line'];
			break;
		}
		if (!in_array('serial_number', $header, true)) {
			$errors[] = 'SerialNumbersColumnSerialNotFound';
		}
	} else {
		$header = array(0 => 'serial_number');
	}

	foreach ($matrix as $matrixrow) {
		$linenumber = (int) $matrixrow['line'];
		if (!empty($firstlineheaders) && $linenumber <= $headerline) {
			continue;
		}
		$cells = (array) $matrixrow['cells'];
		$hasvalue = false;
		foreach ($cells as $cell) {
			if (powerplantpvSerialImportNormalizeValue($cell) !== '') {
				$hasvalue = true;
				break;
			}
		}
		if (!$hasvalue) {
			continue;
		}

		if (empty($firstlineheaders) && count($cells) > 1) {
			$header = array(0 => 'product_ref', 1 => 'serial_number');
		}

		$row = array(
			'line' => $linenumber,
			'serial_number' => '',
			'product_ref' => '',
			'product_label' => '',
			'manufacturer' => '',
			'model' => '',
			'comment' => '',
			'raw' => array(),
		);
		foreach ($cells as $idx => $cell) {
			$key = isset($header[$idx]) ? $header[$idx] : '';
			$value = powerplantpvSerialImportNormalizeValue($cell);
			if ($key !== '' && in_array($key, $allowed, true)) {
				$row[$key] = $value;
			}
			if ($key !== '') {
				$row['raw'][$key] = $value;
			}
		}
		$rows[] = $row;
	}

	if (empty($rows) && empty($errors)) {
		$errors[] = 'SerialNumbersNoUsableLine';
	}
	if (!empty($unknowncolumns)) {
		$warnings[] = 'SerialNumbersUnknownColumnsIgnored';
	}

	return array(
		'rows' => $rows,
		'errors' => $errors,
		'warnings' => $warnings,
		'unknown_columns' => array_values($unknowncolumns),
	);
}

/**
 * Fetch serial numbers already present in a power plant.
 *
 * @param	int	$powerplantid	Power plant id
 * @param	int	$entity			Entity id
 * @return	array<string,array<string,mixed>>	Existing serials indexed by comparison key
 */
function powerplantpvSerialImportFetchExistingSerials($powerplantid, $entity)
{
	global $db;

	$existing = array();

	$sql = "SELECT sn.serial_number, sn.fk_categorie, sn.fk_powerplant_line, sn.fk_product";
	$sql .= " FROM ".$db->prefix()."powerplantpv_serialnumber as sn";
	$sql .= " WHERE sn.fk_powerplant = ".((int) $powerplantid);
	$sql .= " AND sn.entity = ".((int) $entity);
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$key = powerplantpvSerialImportSerialKey($obj->serial_number);
			if ($key !== '') {
				$existing[$key] = array(
					'serial_number' => (string) $obj->serial_number,
					'fk_categorie' => (int) $obj->fk_categorie,
					'fk_powerplant_line' => (int) $obj->fk_powerplant_line,
					'fk_product' => (int) $obj->fk_product,
					'source' => 'serialnumber',
				);
			}
		}
	}

	$sql = "SELECT c.serial_number, c.rowid as fk_powerplant_line, c.fk_product, pe.categorie_photovoltaique as fk_categorie";
	$sql .= " FROM ".$db->prefix()."powerplantpv_powerplantcomp as c";
	$sql .= " INNER JOIN ".$db->prefix()."product_extrafields as pe ON pe.fk_object = c.fk_product";
	$sql .= " WHERE c.fk_powerplant = ".((int) $powerplantid);
	$sql .= " AND c.entity = ".((int) $entity);
	$sql .= " AND c.serial_number IS NOT NULL AND c.serial_number <> ''";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$key = powerplantpvSerialImportSerialKey($obj->serial_number);
			if ($key !== '' && !isset($existing[$key])) {
				$existing[$key] = array(
					'serial_number' => (string) $obj->serial_number,
					'fk_categorie' => (int) $obj->fk_categorie,
					'fk_powerplant_line' => (int) $obj->fk_powerplant_line,
					'fk_product' => (int) $obj->fk_product,
					'source' => 'composition',
				);
			}
		}
	}

	return $existing;
}

/**
 * Analyze normalized import rows.
 *
 * @param	PowerPlant					$object		Power plant
 * @param	int							$categoryid	Category id
 * @param	array<int,array<string,mixed>>	$rows	Rows
 * @param	string						$mode		Import mode add|replace
 * @return	array<string,mixed>					Analysis
 */
function powerplantpvSerialImportAnalyzeRows($object, $categoryid, $rows, $mode)
{
	global $conf, $langs;

	$entity = (!empty($object->entity) ? (int) $object->entity : (int) $conf->entity);
	$categoryid = (int) $categoryid;
	$mode = ($mode === 'replace' ? 'replace' : 'add');
	$lines = powerplantpvSerialImportFetchCategoryLines((int) $object->id, $categoryid, $entity);
	$existing = powerplantpvSerialImportFetchExistingSerials((int) $object->id, $entity);

	$productids = array();
	$linesbyref = array();
	$availablebyproduct = array();
	$replaceexistingcount = 0;
	foreach ($lines as $lineid => $line) {
		$productids[(int) $line['fk_product']] = (int) $line['fk_product'];
		$refkey = powerplantpvSerialImportSerialKey((string) $line['product_ref']);
		if ($refkey !== '') {
			if (empty($linesbyref[$refkey])) {
				$linesbyref[$refkey] = array();
			}
			$linesbyref[$refkey][] = (int) $lineid;
		}

		$recordedqty = powerplantpvSerialImportLineRecordedQty($line);
		$replaceexistingcount += $recordedqty;
		$capacity = powerplantpvSerialImportLineCapacity($line, $mode);
		if ($capacity > 0) {
			if (empty($availablebyproduct[(int) $line['fk_product']])) {
				$availablebyproduct[(int) $line['fk_product']] = array();
			}
			$availablebyproduct[(int) $line['fk_product']][(int) $lineid] = $capacity;
		}
	}

	$singleproductid = 0;
	if (count($productids) === 1) {
		$singleproductid = (int) reset($productids);
	}

	$filecounts = array();
	foreach ($rows as $row) {
		$key = powerplantpvSerialImportSerialKey((string) $row['serial_number']);
		if ($key === '') {
			continue;
		}
		if (empty($filecounts[$key])) {
			$filecounts[$key] = 0;
		}
		$filecounts[$key]++;
	}

	$analysisrows = array();
	$duplicatesfile = 0;
	$duplicatespowerplant = 0;
	$validnumbers = 0;
	$blockingerrors = array();
	$warnings = array();

	foreach ($rows as $row) {
		$messages = array();
		$status = 'ok';
		$blocking = 0;
		$serial = powerplantpvSerialImportNormalizeValue((string) $row['serial_number']);
		$serialkey = powerplantpvSerialImportSerialKey($serial);
		$productref = powerplantpvSerialImportNormalizeValue((string) $row['product_ref']);
		$lineid = 0;
		$productid = 0;
		$productdisplay = '';
		$associationrequired = 0;

		if ($serial === '') {
			$messages[] = 'SerialNumbersEmptySerialNumber';
			$status = 'error';
			$blocking = 1;
		}
		if ($serial !== '' && !preg_match('/^[A-Za-z0-9][A-Za-z0-9._\\-\\/]{2,127}$/', $serial)) {
			$messages[] = 'SerialNumbersUnusualFormat';
			if ($status === 'ok') {
				$status = 'warning';
			}
		}
		if ($serialkey !== '' && !empty($filecounts[$serialkey]) && $filecounts[$serialkey] > 1) {
			$messages[] = 'SerialNumbersDuplicateInFile';
			$status = 'error';
			$blocking = 1;
			$duplicatesfile++;
		}
		if ($serialkey !== '' && isset($existing[$serialkey])) {
			$existingrow = $existing[$serialkey];
			if ($mode === 'replace' && (int) $existingrow['fk_categorie'] === $categoryid) {
				$messages[] = 'SerialNumbersAlreadyAssociatedWillBeReplaced';
				if ($status === 'ok') {
					$status = 'warning';
				}
			} else {
				$messages[] = 'SerialNumbersDuplicateInPowerplant';
				$status = 'error';
				$blocking = 1;
				$duplicatespowerplant++;
			}
		}

		if (!$blocking) {
			if ($productref !== '') {
				$productrefkey = powerplantpvSerialImportSerialKey($productref);
				if (!empty($linesbyref[$productrefkey])) {
					$candidateid = (int) reset($linesbyref[$productrefkey]);
					$candidate = $lines[$candidateid];
					$productid = (int) $candidate['fk_product'];
					$lineid = powerplantpvSerialImportReserveLine($availablebyproduct, $productid, $candidateid);
					if ($lineid <= 0) {
						$messages[] = 'SerialNumbersQuantityExceedsExpected';
						$status = 'error';
						$blocking = 1;
					}
				} else {
					$messages[] = 'SerialNumbersUnknownProduct';
					$status = 'error';
					$blocking = 1;
				}
			} elseif ($singleproductid > 0) {
				$lineid = powerplantpvSerialImportReserveLine($availablebyproduct, $singleproductid, 0);
				if ($lineid <= 0) {
					$messages[] = 'SerialNumbersQuantityExceedsExpected';
					$status = 'error';
					$blocking = 1;
				}
			} else {
				$messages[] = 'SerialNumbersAssociationRequired';
				$status = 'warning';
				$associationrequired = 1;
			}
		}

		if ($lineid > 0 && isset($lines[$lineid])) {
			$productid = (int) $lines[$lineid]['fk_product'];
			$productdisplay = (string) $lines[$lineid]['product_display'];
		}

		if (!$blocking && $status !== 'error') {
			$validnumbers++;
		} elseif ($blocking) {
			$blockingerrors[] = array('line' => (int) $row['line'], 'messages' => $messages);
		}
		if ($status === 'warning') {
			$warnings[] = array('line' => (int) $row['line'], 'messages' => $messages);
		}

		$analysisrows[] = array(
			'line' => (int) $row['line'],
			'serial_number' => $serial,
			'product_ref' => $productref,
			'product_label' => powerplantpvSerialImportNormalizeValue((string) $row['product_label']),
			'manufacturer' => powerplantpvSerialImportNormalizeValue((string) $row['manufacturer']),
			'model' => powerplantpvSerialImportNormalizeValue((string) $row['model']),
			'comment' => powerplantpvSerialImportNormalizeValue((string) $row['comment']),
			'fk_powerplant_line' => $lineid,
			'fk_product' => $productid,
			'product_display' => $productdisplay,
			'association_required' => $associationrequired,
			'status' => $status,
			'blocking' => $blocking,
			'messages' => $messages,
		);
	}

	if (empty($lines)) {
		$blockingerrors[] = array('line' => 0, 'messages' => array('SerialNumbersCategoryAbsentFromPowerplant'));
	}
	if (empty($rows)) {
		$blockingerrors[] = array('line' => 0, 'messages' => array('SerialNumbersNoUsableLine'));
	}

	$expectedqty = 0;
	foreach ($lines as $line) {
		$expectedqty += max(0, (int) $line['qty']);
	}
	if ($validnumbers > $expectedqty) {
		$blockingerrors[] = array('line' => 0, 'messages' => array('SerialNumbersQuantityExceedsExpected'));
	}

	$categorylabel = '';
	if (!empty($lines)) {
		$firstline = reset($lines);
		$categorylabel = (string) $firstline['category_label'];
	}

	return array(
		'rows' => $analysisrows,
		'lines' => array_values($lines),
		'summary' => array(
			'powerplant_ref' => (string) $object->ref,
			'category_id' => $categoryid,
			'category_label' => $categorylabel,
			'lines_detected' => count($rows),
			'valid_numbers' => $validnumbers,
			'duplicates_file' => $duplicatesfile,
			'duplicates_powerplant' => $duplicatespowerplant,
			'expected_qty' => $expectedqty,
			'imported_qty' => $validnumbers,
			'gap' => $validnumbers - $expectedqty,
			'replace_existing_count' => ($mode === 'replace' ? $replaceexistingcount : 0),
			'mode' => $mode,
		),
		'blocking_errors' => $blockingerrors,
		'warnings' => $warnings,
		'has_blocking_errors' => (!empty($blockingerrors) ? 1 : 0),
		'manual_association_required' => powerplantpvSerialImportHasManualAssociations($analysisrows),
	);
}

/**
 * Reserve an available composition line.
 *
 * @param	array<int,array<int,int>>	$availablebyproduct	Available lines by product
 * @param	int							$productid			Product id
 * @param	int							$preferredlineid	Preferred line id
 * @return	int												Reserved line id
 */
function powerplantpvSerialImportReserveLine(&$availablebyproduct, $productid, $preferredlineid = 0)
{
	$productid = (int) $productid;
	if ($productid <= 0 || empty($availablebyproduct[$productid])) {
		return 0;
	}

	if ($preferredlineid > 0 && !empty($availablebyproduct[$productid][(int) $preferredlineid])) {
		$availablebyproduct[$productid][(int) $preferredlineid]--;
		if ($availablebyproduct[$productid][(int) $preferredlineid] <= 0) {
			unset($availablebyproduct[$productid][(int) $preferredlineid]);
		}

		return (int) $preferredlineid;
	}

	foreach ($availablebyproduct[$productid] as $lineid => $capacity) {
		if ($capacity > 0) {
			$availablebyproduct[$productid][(int) $lineid]--;
			if ($availablebyproduct[$productid][(int) $lineid] <= 0) {
				unset($availablebyproduct[$productid][(int) $lineid]);
			}

			return (int) $lineid;
		}
	}

	return 0;
}

/**
 * Return the recorded serial number count for one composition line.
 *
 * @param	array<string,mixed>	$line	Composition line data
 * @return	int							Recorded serial number count
 */
function powerplantpvSerialImportLineRecordedQty($line)
{
	$storedqty = (int) ($line['stored_qty'] ?? 0);
	$legacyqty = (!empty($line['composition_serial_number']) ? 1 : 0);

	return max($storedqty, $legacyqty);
}

/**
 * Return remaining import capacity for one composition line.
 *
 * @param	array<string,mixed>	$line	Composition line data
 * @param	string				$mode	Import mode add|replace
 * @return	int							Remaining capacity
 */
function powerplantpvSerialImportLineCapacity($line, $mode)
{
	$qty = max(0, (int) ($line['qty'] ?? 0));
	if ($mode === 'replace') {
		return $qty;
	}

	return max(0, $qty - powerplantpvSerialImportLineRecordedQty($line));
}

/**
 * Check whether at least one row needs a manual association.
 *
 * @param	array<int,array<string,mixed>>	$analysisrows	Analysis rows
 * @return	int<0,1>										1 if manual association is needed
 */
function powerplantpvSerialImportHasManualAssociations($analysisrows)
{
	foreach ($analysisrows as $row) {
		if (!empty($row['association_required'])) {
			return 1;
		}
	}

	return 0;
}

/**
 * Update a temporary import batch with parsed data.
 *
 * @param	PowerPlantPVSerialNumberImport	$import		Import batch
 * @param	string							$status		Status
 * @param	array<string,mixed>				$parsed		Parsed data
 * @param	array<string,mixed>				$errors		Error data
 * @return	int										1 if OK, <0 on error
 */
function powerplantpvSerialImportUpdateBatch($import, $status, $parsed, $errors)
{
	$jsonparsed = json_encode($parsed, JSON_UNESCAPED_SLASHES);
	$jsonerrors = json_encode($errors, JSON_UNESCAPED_SLASHES);
	if ($jsonparsed === false || $jsonerrors === false) {
		$import->error = 'ErrorFailedToEncodeJson';
		return -1;
	}

	$sql = "UPDATE ".$import->db->prefix().$import->table_element;
	$sql .= " SET status = '".$import->db->escape($status)."',";
	$sql .= " parsed_data_json = '".$import->db->escape($jsonparsed)."',";
	$sql .= " errors_json = '".$import->db->escape($jsonerrors)."'";
	$sql .= " WHERE rowid = ".((int) $import->id);
	$resql = $import->db->query($sql);
	if (!$resql) {
		$import->error = $import->db->lasterror();
		return -1;
	}

	$import->status = $status;
	$import->parsed_data_json = $jsonparsed;
	$import->errors_json = $jsonerrors;

	return 1;
}

/**
 * Validate an import batch and save serial numbers.
 *
 * @param	PowerPlant						$object			Power plant
 * @param	PowerPlantPVSerialNumberImport	$import			Import batch
 * @param	array<int,int>					$assignments	Manual assignments by source line
 * @param	User							$user			User
 * @return	array<string,mixed>|int							Result array, <0 on error
 */
function powerplantpvSerialImportValidateBatch($object, $import, $assignments, $user)
{
	global $conf, $langs, $db;

	$parsed = $import->getParsedData();
	$inputrows = !empty($parsed['input_rows']) && is_array($parsed['input_rows']) ? $parsed['input_rows'] : array();
	$analysis = powerplantpvSerialImportAnalyzeRows($object, (int) $import->fk_categorie, $inputrows, (string) $import->import_mode);
	$entity = (!empty($object->entity) ? (int) $object->entity : (int) $conf->entity);
	$lines = powerplantpvSerialImportFetchCategoryLines((int) $object->id, (int) $import->fk_categorie, $entity);
	$linecapacities = array();
	$finalrows = array();
	$blockingerrors = $analysis['blocking_errors'];
	$mode = ((string) $import->import_mode === 'replace' ? 'replace' : 'add');
	foreach ($lines as $lineid => $line) {
		$linecapacities[(int) $lineid] = powerplantpvSerialImportLineCapacity($line, $mode);
	}

	foreach ($analysis['rows'] as $row) {
		if (!empty($row['blocking'])) {
			$finalrows[] = $row;
			continue;
		}

		$lineid = (int) $row['fk_powerplant_line'];
		if (!empty($row['association_required']) || !empty($assignments[(int) $row['line']])) {
			$lineid = !empty($assignments[(int) $row['line']]) ? (int) $assignments[(int) $row['line']] : 0;
			if ($lineid <= 0 || empty($lines[$lineid])) {
				$row['status'] = 'error';
				$row['blocking'] = 1;
				$row['messages'][] = 'SerialNumbersAssociationRequired';
				$blockingerrors[] = array('line' => (int) $row['line'], 'messages' => array('SerialNumbersAssociationRequired'));
				$finalrows[] = $row;
				continue;
			}
			$row['fk_powerplant_line'] = $lineid;
			$row['fk_product'] = (int) $lines[$lineid]['fk_product'];
			$row['product_display'] = (string) $lines[$lineid]['product_display'];
			$row['association_required'] = 0;
		}

		if ($lineid <= 0 || empty($lines[$lineid])) {
			$row['status'] = 'error';
			$row['blocking'] = 1;
			$row['messages'][] = 'SerialNumbersAssociationRequired';
			$blockingerrors[] = array('line' => (int) $row['line'], 'messages' => array('SerialNumbersAssociationRequired'));
			$finalrows[] = $row;
			continue;
		}
		if (empty($linecapacities[$lineid])) {
			$row['status'] = 'error';
			$row['blocking'] = 1;
			$row['messages'][] = 'SerialNumbersQuantityExceedsExpected';
			$blockingerrors[] = array('line' => (int) $row['line'], 'messages' => array('SerialNumbersQuantityExceedsExpected'));
			$finalrows[] = $row;
			continue;
		}
		$linecapacities[$lineid]--;
		$finalrows[] = $row;
	}

	if (!empty($blockingerrors)) {
		$analysis['rows'] = $finalrows;
		$analysis['blocking_errors'] = $blockingerrors;
		$analysis['has_blocking_errors'] = 1;
		powerplantpvSerialImportUpdateBatch($import, PowerPlantPVSerialNumberImport::STATUS_CHECKED, array('input_rows' => $inputrows, 'analysis' => $analysis), $analysis);
		$import->error = 'SerialNumbersImportHasBlockingErrors';
		return -1;
	}

	$batch = dol_print_date(dol_now(), '%Y%m%d%H%M%S').'-'.((int) $import->id);
	$sourcefile = (string) $import->filename;
	$db->begin();

	if ($mode === 'replace') {
		$sqldelete = "DELETE FROM ".$db->prefix()."powerplantpv_serialnumber";
		$sqldelete .= " WHERE fk_powerplant = ".((int) $object->id);
		$sqldelete .= " AND fk_categorie = ".((int) $import->fk_categorie);
		$sqldelete .= " AND entity = ".$entity;
		if (!$db->query($sqldelete)) {
			$import->error = $db->lasterror();
			$db->rollback();
			return -1;
		}

		$sqlclear = "UPDATE ".$db->prefix()."powerplantpv_powerplantcomp as c";
		$sqlclear .= " INNER JOIN ".$db->prefix()."product_extrafields as pe ON pe.fk_object = c.fk_product";
		$sqlclear .= " SET c.serial_number = ''";
		$sqlclear .= " WHERE c.fk_powerplant = ".((int) $object->id);
		$sqlclear .= " AND c.entity = ".$entity;
		$sqlclear .= " AND pe.categorie_photovoltaique = ".((int) $import->fk_categorie);
		if (!$db->query($sqlclear)) {
			$import->error = $db->lasterror();
			$db->rollback();
			return -1;
		}
	}

	$inserted = 0;
	foreach ($finalrows as $row) {
		$serial = (string) $row['serial_number'];
		if ($serial === '') {
			continue;
		}
		$lineid = (int) $row['fk_powerplant_line'];
		$line = $lines[$lineid];
		$note = trim((string) $row['comment']);

		$sqlinsert = "INSERT INTO ".$db->prefix()."powerplantpv_serialnumber(";
		$sqlinsert .= "entity, fk_powerplant, fk_powerplant_line, fk_product, fk_categorie, serial_number, source_file, import_batch, note, import_status, datec, fk_user_creat";
		$sqlinsert .= ") VALUES (";
		$sqlinsert .= $entity.", ".((int) $object->id).", ".$lineid.", ".((int) $line['fk_product']).", ".((int) $import->fk_categorie).",";
		$sqlinsert .= " '".$db->escape($serial)."', '".$db->escape($sourcefile)."', '".$db->escape($batch)."',";
		$sqlinsert .= " ".($note !== '' ? "'".$db->escape($note)."'" : "NULL").", 'validated', '".$db->idate(dol_now())."', ".((int) $user->id);
		$sqlinsert .= ")";
		if (!$db->query($sqlinsert)) {
			$import->error = $db->lasterror();
			$db->rollback();
			return -1;
		}

		$sqlupdate = "UPDATE ".$db->prefix()."powerplantpv_powerplantcomp";
		$sqlupdate .= " SET serial_number = '".$db->escape($serial)."'";
		$sqlupdate .= " WHERE rowid = ".$lineid;
		$sqlupdate .= " AND fk_powerplant = ".((int) $object->id);
		$sqlupdate .= " AND entity = ".$entity;
		if (!$db->query($sqlupdate)) {
			$import->error = $db->lasterror();
			$db->rollback();
			return -1;
		}
		$inserted++;
	}

	$analysis['rows'] = $finalrows;
	$analysis['summary']['imported_qty'] = $inserted;
	$analysis['summary']['valid_numbers'] = $inserted;
	$analysis['summary']['gap'] = $inserted - (int) $analysis['summary']['expected_qty'];
	$analysis['summary']['import_batch'] = $batch;

	$resultupdate = powerplantpvSerialImportUpdateBatch($import, PowerPlantPVSerialNumberImport::STATUS_VALIDATED, array('input_rows' => $inputrows, 'analysis' => $analysis), $analysis);
	if ($resultupdate < 0) {
		$db->rollback();
		return -1;
	}

	$db->commit();

	$label = $langs->transnoentities('SerialNumbersImportValidated');
	$message = $langs->transnoentities('SerialNumbersImportValidatedLog', (string) $analysis['summary']['category_label'], $sourcefile, $inserted, $user->getFullName($langs));
	dol_include_once('/powerplantpv/lib/powerplantpv_powerplant.lib.php');
	powerplantTriggerAgendaEvent($object, $user, 'POWERPLANTPV_POWERPLANT_COMP_SERIAL_IMPORT', $label, $message);

	return array('inserted' => $inserted, 'analysis' => $analysis);
}

/**
 * Cancel an import batch.
 *
 * @param	PowerPlantPVSerialNumberImport	$import	Import batch
 * @return	int									1 if OK, <0 on error
 */
function powerplantpvSerialImportCancelBatch($import)
{
	$sql = "UPDATE ".$import->db->prefix().$import->table_element;
	$sql .= " SET status = '".PowerPlantPVSerialNumberImport::STATUS_CANCELLED."'";
	$sql .= " WHERE rowid = ".((int) $import->id);
	$resql = $import->db->query($sql);
	if (!$resql) {
		$import->error = $import->db->lasterror();
		return -1;
	}
	$import->status = PowerPlantPVSerialNumberImport::STATUS_CANCELLED;

	return 1;
}

/**
 * Build a WHERE clause for serial number list filters.
 *
 * @param	PowerPlant	$object		Power plant
 * @param	array<string,int>	$filters	Filters
 * @return	string							SQL WHERE clause
 */
function powerplantpvSerialNumberBuildFilterWhere($object, $filters)
{
	global $conf;

	$entity = (!empty($object->entity) ? (int) $object->entity : (int) $conf->entity);
	$where = " WHERE sn.fk_powerplant = ".((int) $object->id);
	$where .= " AND sn.entity = ".$entity;
	if (!empty($filters['lineid'])) {
		$where .= " AND sn.fk_powerplant_line = ".((int) $filters['lineid']);
	}
	if (!empty($filters['fk_product'])) {
		$where .= " AND sn.fk_product = ".((int) $filters['fk_product']);
	}
	if (!empty($filters['fk_categorie'])) {
		$where .= " AND sn.fk_categorie = ".((int) $filters['fk_categorie']);
	}

	return $where;
}

/**
 * Delete serial numbers by filter and clear composition line values.
 *
 * @param	PowerPlant			$object		Power plant
 * @param	array<string,int>	$filters	Filters
 * @return	int								Deleted count, <0 on error
 */
function powerplantpvSerialNumberDeleteByFilter($object, $filters)
{
	global $db, $conf;

	$entity = (!empty($object->entity) ? (int) $object->entity : (int) $conf->entity);
	$where = powerplantpvSerialNumberBuildFilterWhere($object, $filters);
	$sqlids = "SELECT sn.rowid, sn.fk_powerplant_line FROM ".$db->prefix()."powerplantpv_serialnumber as sn".$where;
	$resids = $db->query($sqlids);
	if (!$resids) {
		$object->error = $db->lasterror();
		return -1;
	}

	$ids = array();
	$lineids = array();
	while ($obj = $db->fetch_object($resids)) {
		$ids[] = (int) $obj->rowid;
		$lineids[] = (int) $obj->fk_powerplant_line;
	}
	if (empty($ids)) {
		return 0;
	}

	$db->begin();

	$sqldelete = "DELETE FROM ".$db->prefix()."powerplantpv_serialnumber WHERE rowid IN (".implode(',', $ids).") AND entity = ".$entity;
	if (!$db->query($sqldelete)) {
		$object->error = $db->lasterror();
		$db->rollback();
		return -1;
	}

	$sqlclear = "UPDATE ".$db->prefix()."powerplantpv_powerplantcomp";
	$sqlclear .= " SET serial_number = ''";
	$sqlclear .= " WHERE rowid IN (".implode(',', array_unique($lineids)).")";
	$sqlclear .= " AND fk_powerplant = ".((int) $object->id);
	$sqlclear .= " AND entity = ".$entity;
	if (!$db->query($sqlclear)) {
		$object->error = $db->lasterror();
		$db->rollback();
		return -1;
	}

	$db->commit();

	return count($ids);
}

/**
 * Delete selected serial numbers and resync composition line serial values.
 *
 * @param	PowerPlant	$object	Power plant
 * @param	int[]		$ids	Serial number ids
 * @return	int					Deleted count, <0 on error
 */
function powerplantpvSerialNumberDeleteByIds($object, $ids)
{
	global $db, $conf;

	$ids = array_map('intval', (array) $ids);
	$ids = array_filter($ids, function ($id) {
		return ($id > 0);
	});
	$ids = array_values(array_unique($ids));
	if (empty($ids)) {
		return 0;
	}

	$entity = (!empty($object->entity) ? (int) $object->entity : (int) $conf->entity);
	$sqlids = "SELECT rowid, fk_powerplant_line FROM ".$db->prefix()."powerplantpv_serialnumber";
	$sqlids .= " WHERE fk_powerplant = ".((int) $object->id);
	$sqlids .= " AND entity = ".$entity;
	$sqlids .= " AND rowid IN (".implode(',', $ids).")";
	$resids = $db->query($sqlids);
	if (!$resids) {
		$object->error = $db->lasterror();
		return -1;
	}

	$idsfound = array();
	$lineids = array();
	while ($obj = $db->fetch_object($resids)) {
		$idsfound[] = (int) $obj->rowid;
		$lineids[] = (int) $obj->fk_powerplant_line;
	}
	$lineids = array_values(array_unique(array_filter($lineids, function ($lineid) {
		return ($lineid > 0);
	})));
	if (empty($idsfound)) {
		return 0;
	}

	$db->begin();

	$sqldelete = "DELETE FROM ".$db->prefix()."powerplantpv_serialnumber";
	$sqldelete .= " WHERE fk_powerplant = ".((int) $object->id);
	$sqldelete .= " AND entity = ".$entity;
	$sqldelete .= " AND rowid IN (".implode(',', $idsfound).")";
	if (!$db->query($sqldelete)) {
		$object->error = $db->lasterror();
		$db->rollback();
		return -1;
	}

	foreach ($lineids as $lineid) {
		$serialnumber = '';
		$sqlremaining = "SELECT serial_number FROM ".$db->prefix()."powerplantpv_serialnumber";
		$sqlremaining .= " WHERE fk_powerplant = ".((int) $object->id);
		$sqlremaining .= " AND entity = ".$entity;
		$sqlremaining .= " AND fk_powerplant_line = ".((int) $lineid);
		$sqlremaining .= " ORDER BY rowid ASC";
		$sqlremaining .= $db->plimit(1);
		$resremaining = $db->query($sqlremaining);
		if (!$resremaining) {
			$object->error = $db->lasterror();
			$db->rollback();
			return -1;
		}
		if ($objremaining = $db->fetch_object($resremaining)) {
			$serialnumber = (string) $objremaining->serial_number;
		}

		$sqlupdate = "UPDATE ".$db->prefix()."powerplantpv_powerplantcomp";
		$sqlupdate .= " SET serial_number = '".$db->escape($serialnumber)."'";
		$sqlupdate .= " WHERE rowid = ".((int) $lineid);
		$sqlupdate .= " AND fk_powerplant = ".((int) $object->id);
		$sqlupdate .= " AND entity = ".$entity;
		if (!$db->query($sqlupdate)) {
			$object->error = $db->lasterror();
			$db->rollback();
			return -1;
		}
	}

	$db->commit();

	return count($idsfound);
}
