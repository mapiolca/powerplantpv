<?php
/* Copyright (C) 2025		Pierre Ardoin				<erp@lesmetiersdubatiment.fr>
 * Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 * Copyright (C) 2025       Frédéric France         <frederic.france@free.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    powerplantpv/lib/powerplantpv.lib.php
 * \ingroup powerplantpv
 * \brief   Library files with common functions for PowerPlantPV
 */

/**
 * Return the native sharing element used by maintenance service metadata.
 *
 * Maintenance services are selected on products/services, so their visibility
 * must follow the product sharing scope when a shared contract line is read.
 *
 * @return string Native Dolibarr element name
 */
function powerplantpvMaintenanceServiceEntityElement()
{
	return 'product';
}

/**
 * Remove inactive values from maintenance list filters.
 *
 * Native Dolibarr selects submit -1 for their empty option. These values must
 * not reach the scheduler as effective filters.
 *
 * @param array<string,mixed> $filters Raw list filters
 * @return array<string,mixed> Active list filters
 */
function powerplantpvMaintenanceActiveListFilters(array $filters)
{
	return array_filter($filters, static function ($value) {
		if (is_array($value)) {
			return !empty($value);
		}

		return !in_array($value, array(null, '', 0, '0', -1, '-1'), true);
	});
}

/**
 * Prepare admin pages header
 *
 * @return array<array{string,string,string}>
 */
function powerplantpvAdminPrepareHead()
{
	global $langs, $conf;

	// global $db;
	// $extrafields = new ExtraFields($db);
	// $extrafields->fetch_name_optionals_label('myobject');

	$langs->load("powerplantpv@powerplantpv");

	$h = 0;
	$head = array();

	$head[$h][0] = dolBuildUrl(dol_buildpath("/powerplantpv/admin/setup.php", 1));
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dolBuildUrl(dol_buildpath("/powerplantpv/admin/attestation.php", 1));
	$head[$h][1] = $langs->trans("Attestations");
	$head[$h][2] = 'attestation';
	$h++;

	$head[$h][0] = dolBuildUrl(dol_buildpath("/powerplantpv/admin/maintenance_report_templates.php", 1));
	$head[$h][1] = $langs->trans("PowerPlantPVReportTemplates");
	$head[$h][2] = 'maintenance_report_templates';
	$h++;

	$head[$h][0] = dolBuildUrl(dol_buildpath("/powerplantpv/admin/maintenance_service_sections.php", 1));
	$head[$h][1] = $langs->trans("PowerPlantPVMaintenanceServiceSections");
	$head[$h][2] = 'maintenance_service_sections';
	$h++;

	$head[$h][0] = dolBuildUrl(dol_buildpath("/powerplantpv/admin/maintenance_intervention_natures.php", 1));
	$head[$h][1] = $langs->trans("InterventionNatureDictionary");
	$head[$h][2] = 'maintenance_intervention_natures';
	$h++;

	$head[$h][0] = dolBuildUrl(dol_buildpath("/powerplantpv/admin/compatibility.php", 1));
	$head[$h][1] = $langs->trans("Compatibility");
	$head[$h][2] = 'compatibility';
	$h++;

	/*
	$head[$h][0] = dolBuildUrl(dol_buildpath("/powerplantpv/admin/myobject_extrafields.php", 1));
	$head[$h][1] = $langs->trans("ExtraFields");
	$nbExtrafields = (isset($extrafields->attributes['myobject']['label']) && is_countable($extrafields->attributes['myobject']['label'])) ? count($extrafields->attributes['myobject']['label']) : 0;
	if ($nbExtrafields > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">' . $nbExtrafields . '</span>';
	}
	$head[$h][2] = 'myobject_extrafields';
	$h++;

	$head[$h][0] = dolBuildUrl(dol_buildpath("/powerplantpv/admin/myobjectline_extrafields.php", 1));
	$head[$h][1] = $langs->trans("ExtraFieldsLines");
	$nbExtrafields = (isset($extrafields->attributes['myobjectline']['label']) && is_countable($extrafields->attributes['myobjectline']['label'])) ? count($extrafields->attributes['myobject']['label']) : 0;
	if ($nbExtrafields > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">' . $nbExtrafields . '</span>';
	}
	$head[$h][2] = 'myobject_extrafieldsline';
	$h++;
	*/

	$head[$h][0] = dolBuildUrl(dol_buildpath("/powerplantpv/admin/about.php", 1));
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@powerplantpv:/powerplantpv/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@powerplantpv:/powerplantpv/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, null, $head, $h, 'powerplantpv@powerplantpv');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'powerplantpv@powerplantpv', 'remove');

	return $head;
}

/**
 * Return missing attestation installation pieces for the current entity.
 *
 * @return	array{tables:array<int,string>,columns:array<int,string>,rights:array<int,string>}	Missing tables, columns and rights
 */
function powerplantpvAttestationGetInstallationIssues()
{
	global $db, $conf;

	$issues = array('tables' => array(), 'columns' => array(), 'rights' => array());
	$tables = array('powerplantpv_attestation', 'powerplantpv_attestation_equipment');
	foreach ($tables as $table) {
		$fullTable = $db->prefix().$table;
		if (!powerplantpvDatabaseTableExists($fullTable)) {
			$issues['tables'][] = $fullTable;
		}
	}

	$attestationTable = $db->prefix().'powerplantpv_attestation';
	if (empty($issues['tables']) && powerplantpvDatabaseTableExists($attestationTable)) {
		$expectedColumns = array('online_sign_name');
		foreach ($expectedColumns as $column) {
			if (!powerplantpvDatabaseTableColumnExists($attestationTable, $column)) {
				$issues['columns'][] = $attestationTable.'.'.$column;
			}
		}
	}
	$equipmentTable = $db->prefix().'powerplantpv_attestation_equipment';
	if (empty($issues['tables']) && powerplantpvDatabaseTableExists($equipmentTable)) {
		$expectedColumns = array('fk_powerplant_line', 'fk_powerplant_serialnumber', 'fk_product', 'fk_categorie');
		foreach ($expectedColumns as $column) {
			if (!powerplantpvDatabaseTableColumnExists($equipmentTable, $column)) {
				$issues['columns'][] = $equipmentTable.'.'.$column;
			}
		}
	}

	$expectedRights = array('read', 'write', 'delete', 'validate', 'sign', 'cancel', 'setup', 'manage_signed');
	$foundRights = array();
	$sql = "SELECT subperms";
	$sql .= " FROM ".$db->prefix()."rights_def";
	$sql .= " WHERE module = 'powerplantpv'";
	$sql .= " AND perms = 'attestation'";
	$sql .= " AND entity IN (0, ".((int) $conf->entity).")";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$foundRights[(string) $obj->subperms] = 1;
		}
		$db->free($resql);
	}
	foreach ($expectedRights as $right) {
		if (empty($foundRights[$right])) {
			$issues['rights'][] = $right;
		}
	}

	return $issues;
}

/**
 * Check if a database table column exists.
 *
 * @param	string	$table		Full table name with prefix
 * @param	string	$column		Column name
 * @return	bool				True if column exists
 */
function powerplantpvDatabaseTableColumnExists($table, $column)
{
	global $db;

	$safeTable = preg_replace('/[^a-z0-9_]/i', '', (string) $table);
	$safeColumn = preg_replace('/[^a-z0-9_]/i', '', (string) $column);
	if ($safeTable === '' || $safeColumn === '') {
		return false;
	}

	$sql = "SHOW COLUMNS FROM ".$db->sanitize($safeTable)." LIKE '".$db->escape($safeColumn)."'";
	$resql = $db->query($sql);
	if ($resql) {
		$exists = ($db->num_rows($resql) > 0);
		$db->free($resql);

		return $exists;
	}

	dol_syslog(__METHOD__.' column lookup failed for '.$safeTable.'.'.$safeColumn.': '.$db->lasterror(), LOG_WARNING);

	return false;
}

/**
 * Check if a database table exists.
 *
 * @param	string	$table	Full table name with prefix
 * @return	bool			True if table exists
 */
function powerplantpvDatabaseTableExists($table)
{
	global $db;

	$safeTable = preg_replace('/[^a-z0-9_]/i', '', (string) $table);
	if ($safeTable === '') {
		return false;
	}

	$sql = "SHOW TABLES LIKE '".$db->escape($safeTable)."'";
	$resql = $db->query($sql);
	if ($resql) {
		$exists = ($db->num_rows($resql) > 0);
		$db->free($resql);

		return $exists;
	}

	dol_syslog(__METHOD__.' table lookup failed for '.$safeTable.': '.$db->lasterror(), LOG_WARNING);

	$columns = $db->DDLInfoTable($safeTable);

	return is_array($columns) && count($columns) > 0;
}

/**
 * Return translated attestation installation warnings for admins.
 *
 * @return	array<int,string>	Warnings
 */
function powerplantpvAttestationGetInstallationWarnings()
{
	global $langs;

	$issues = powerplantpvAttestationGetInstallationIssues();
	$warnings = array();
	if (!empty($issues['tables'])) {
		$warnings[] = $langs->trans('AttestationInstallMissingTables', implode(', ', $issues['tables']));
	}
	if (!empty($issues['columns'])) {
		$warnings[] = $langs->trans('AttestationInstallMissingColumns', implode(', ', $issues['columns']));
	}
	if (!empty($issues['rights'])) {
		$warnings[] = $langs->trans('AttestationInstallMissingRights', implode(', ', $issues['rights']));
	}

	return $warnings;
}

/**
 * Print attestation installation warnings.
 *
 * @return	void
 */
function powerplantpvAttestationPrintInstallationWarnings()
{
	global $langs;

	$warnings = powerplantpvAttestationGetInstallationWarnings();
	if (empty($warnings)) {
		return;
	}

	print '<div class="warning">';
	print img_warning().' '.$langs->trans('AttestationInstallationIncomplete');
	print '<ul class="marginbottomonly">';
	foreach ($warnings as $warning) {
		print '<li>'.dol_escape_htmltag($warning).'</li>';
	}
	print '</ul>';
	print '</div>';
}

/**
 * Normalize a Dolibarr element type to the value stored in llx_element_element.
 *
 * @param	string	$elementtype	Element type
 * @return	string					Normalized element type
 */
function powerplantpvNormalizeElementType($elementtype)
{
	if ($elementtype == 'order') {
		return 'commande';
	}
	if ($elementtype == 'contract') {
		return 'contrat';
	}
	if ($elementtype == 'intervention' || $elementtype == 'ficheinter') {
		return 'fichinter';
	}
	if ($elementtype == 'propale') {
		return 'propal';
	}
	if ($elementtype == 'invoice') {
		return 'facture';
	}

	return (string) $elementtype;
}

/**
 * Reset the last commercial peak-power recalculation error.
 *
 * @return	void
 */
function powerplantpvResetPeakPowerRecalculationError()
{
	global $powerplantpv_last_peak_power_recalculation_error;

	$powerplantpv_last_peak_power_recalculation_error = array();
}

/**
 * Store the last commercial peak-power recalculation error.
 *
 * @param	string	$messagekey		Translation key for the business cause
 * @param	string	$elementtype	Document element type, or product for product-triggered recalculation
 * @param	int		$objectid		Document or product id
 * @param	string	$technicalerror	Technical error, usually SQL error
 * @param	string	$step			Technical step
 * @param	int		$productid		Product id when the failed operation was product-driven
 * @return	array<string,mixed>		Stored error
 */
function powerplantpvSetPeakPowerRecalculationError($messagekey, $elementtype = '', $objectid = 0, $technicalerror = '', $step = '', $productid = 0)
{
	global $powerplantpv_last_peak_power_recalculation_error;

	$error = array(
		'messagekey' => (string) $messagekey,
		'elementtype' => powerplantpvNormalizeElementType((string) $elementtype),
		'objectid' => (int) $objectid,
		'technicalerror' => trim(str_replace(array("\r", "\n"), ' ', (string) $technicalerror)),
		'step' => (string) $step,
		'productid' => (int) $productid,
	);

	$powerplantpv_last_peak_power_recalculation_error = $error;

	return $error;
}

/**
 * Return the last commercial peak-power recalculation error.
 *
 * @return	array<string,mixed>	Last error, empty if none
 */
function powerplantpvGetLastPeakPowerRecalculationError()
{
	global $powerplantpv_last_peak_power_recalculation_error;

	return (is_array($powerplantpv_last_peak_power_recalculation_error) ? $powerplantpv_last_peak_power_recalculation_error : array());
}

/**
 * Return a translated object label for peak-power recalculation errors.
 *
 * @param	string	$elementtype	Element type
 * @return	string					Translated object label
 */
function powerplantpvGetPeakPowerRecalculationObjectLabel($elementtype)
{
	global $langs;

	$elementtype = powerplantpvNormalizeElementType($elementtype);
	$key = '';
	if ($elementtype == 'propal') {
		$key = 'PowerPlantPVPeakPowerObjectPropal';
	} elseif ($elementtype == 'commande') {
		$key = 'PowerPlantPVPeakPowerObjectCommande';
	} elseif ($elementtype == 'facture') {
		$key = 'PowerPlantPVPeakPowerObjectFacture';
	} elseif ($elementtype == 'product') {
		$key = 'PowerPlantPVPeakPowerObjectProduct';
	}

	if ($key !== '') {
		$translated = $langs->trans($key);
		if ($translated != $key) {
			return $translated;
		}
	}

	return ($elementtype !== '' ? $elementtype : $langs->trans('PowerPlantPVPeakPowerObjectUnknown'));
}

/**
 * Build a user-facing message for the last commercial peak-power recalculation error.
 *
 * @param	bool				$admin		Show technical detail
 * @param	array<string,mixed>	$error		Error data, defaults to the last error
 * @return	string							Translated message
 */
function powerplantpvBuildPeakPowerRecalculationErrorMessage($admin = false, $error = null)
{
	global $langs;

	$langs->load('powerplantpv@powerplantpv');

	if (!is_array($error)) {
		$error = powerplantpvGetLastPeakPowerRecalculationError();
	}
	if (empty($error)) {
		return $langs->trans('ErrorFailedToRecalculatePeakPower');
	}

	$messagekey = !empty($error['messagekey']) ? (string) $error['messagekey'] : 'PowerPlantPVPeakPowerErrorUnknown';
	$cause = $langs->trans($messagekey);
	if ($cause == $messagekey) {
		$cause = $langs->trans('ErrorFailedToRecalculatePeakPower');
	}

	$objectlabel = powerplantpvGetPeakPowerRecalculationObjectLabel(!empty($error['elementtype']) ? (string) $error['elementtype'] : '');
	$objectref = ((int) (!empty($error['objectid']) ? $error['objectid'] : 0) > 0 ? '#'.((int) $error['objectid']) : $langs->trans('PowerPlantPVPeakPowerObjectAll'));
	$technicalerror = !empty($error['technicalerror']) ? (string) $error['technicalerror'] : '';
	$step = !empty($error['step']) ? (string) $error['step'] : '-';

	if ($admin && $technicalerror !== '') {
		return $langs->trans('PowerPlantPVPeakPowerErrorAdminDetail', $objectlabel, $objectref, $cause, $step, $technicalerror);
	}

	return $langs->trans('PowerPlantPVPeakPowerErrorUserDetail', $objectlabel, $objectref, $cause);
}

/**
 * Build a complete log message for the last commercial peak-power recalculation error.
 *
 * @param	array<string,mixed>	$error	Error data, defaults to the last error
 * @return	string						Log message
 */
function powerplantpvBuildPeakPowerRecalculationErrorLog($error = null)
{
	if (!is_array($error)) {
		$error = powerplantpvGetLastPeakPowerRecalculationError();
	}
	if (empty($error)) {
		return 'no detailed error available';
	}

	$parts = array(
		'messagekey='.(string) $error['messagekey'],
		'elementtype='.(string) $error['elementtype'],
		'objectid='.((int) $error['objectid']),
		'step='.(string) $error['step'],
	);
	if (!empty($error['productid'])) {
		$parts[] = 'productid='.((int) $error['productid']);
	}
	if (!empty($error['technicalerror'])) {
		$parts[] = 'technicalerror='.(string) $error['technicalerror'];
	}

	return implode(' ', $parts);
}

/**
 * Normalize a Dolibarr origin type to the value stored in llx_element_element.
 *
 * @param	string	$origin		Origin type
 * @return	string				Normalized origin type, empty if unsupported
 */
function powerplantpvNormalizeOriginType($origin)
{
	$origin = powerplantpvNormalizeElementType($origin);
	if (in_array($origin, array('commande', 'propal', 'contrat', 'facture'))) {
		return $origin;
	}

	return '';
}

/**
 * Fetch an object that can be linked to a power plant.
 *
 * @param	string	$origin		Origin type
 * @param	int		$originid	Origin object id
 * @return	CommonObject|null	Fetched object, null if unsupported or not found
 */
function powerplantpvFetchOriginObject($origin, $originid)
{
	global $db;

	$origin = powerplantpvNormalizeOriginType($origin);
	$originid = (int) $originid;
	if (empty($origin) || $originid <= 0) {
		return null;
	}

	if ($origin == 'commande') {
		dol_include_once('/commande/class/commande.class.php');
		$classname = 'Commande';
	} elseif ($origin == 'propal') {
		dol_include_once('/comm/propal/class/propal.class.php');
		$classname = 'Propal';
	} elseif ($origin == 'facture') {
		dol_include_once('/compta/facture/class/facture.class.php');
		$classname = 'Facture';
	} elseif ($origin == 'contrat') {
		dol_include_once('/contrat/class/contrat.class.php');
		$classname = 'Contrat';
	} else {
		return null;
	}

	if (!class_exists($classname)) {
		return null;
	}

	$sourceobject = new $classname($db);
	$result = $sourceobject->fetch($originid);
	if ($result <= 0) {
		return null;
	}

	return $sourceobject;
}

/**
 * Apply default third party values from a source object to a power plant.
 *
 * @param	PowerPlant	$powerplant	Power plant object to initialize
 * @param	string		$origin		Origin type
 * @param	int			$originid	Origin object id
 * @return	int						Return integer <0 if KO, 0 if no origin, >0 if defaults were applied
 */
function powerplantpvApplyOriginDefaults(&$powerplant, $origin, $originid)
{
	global $langs;

	$sourceobject = powerplantpvFetchOriginObject($origin, $originid);
	if (!is_object($sourceobject)) {
		return 0;
	}

	if (empty($powerplant->fk_soc)) {
		$originsocid = 0;
		if (!empty($sourceobject->socid)) {
			$originsocid = (int) $sourceobject->socid;
		} elseif (!empty($sourceobject->fk_soc)) {
			$originsocid = (int) $sourceobject->fk_soc;
		}
		if ($originsocid > 0) {
			$powerplant->fk_soc = $originsocid;
			$powerplant->socid = $originsocid;
		}
	}
	if (!empty($sourceobject->fk_project)) {
		$powerplant->context['powerplantpv_origin_fk_project'] = (int) $sourceobject->fk_project;
	}
	if (empty($powerplant->label) && !empty($sourceobject->ref)) {
		$powerplant->label = (is_object($langs) ? $langs->trans('PowerPlantCreatedFrom', $sourceobject->ref) : $sourceobject->ref);
	}

	return 1;
}

/**
 * Fetch an origin object and load its commercial lines when available.
 *
 * @param	string	$origin		Origin type
 * @param	int		$originid	Origin object id
 * @return	CommonObject|null	Fetched object, null if unsupported or not found
 */
function powerplantpvFetchOriginObjectWithLines($origin, $originid)
{
	$sourceobject = powerplantpvFetchOriginObject($origin, $originid);
	if (!is_object($sourceobject)) {
		return null;
	}

	if (method_exists($sourceobject, 'fetch_lines')) {
		$sourceobject->fetch_lines();
	} elseif (method_exists($sourceobject, 'fetchLines')) {
		$sourceobject->fetchLines();
	}

	return $sourceobject;
}

/**
 * Return the numeric status of a Dolibarr object.
 *
 * @param	CommonObject	$object	Source object
 * @return	int					Status, -9999 if no status is available
 */
function powerplantpvGetObjectStatus($object)
{
	if (is_object($object) && isset($object->statut)) {
		return (int) $object->statut;
	}
	if (is_object($object) && isset($object->status)) {
		return (int) $object->status;
	}

	return -9999;
}

/**
 * Check if the source object status allows automatic material creation.
 *
 * @param	string			$origin			Normalized origin type
 * @param	CommonObject	$sourceobject	Source object
 * @return	bool							True if source status is eligible
 */
function powerplantpvIsAutomaticMaterialSourceEligible($origin, $sourceobject)
{
	$status = powerplantpvGetObjectStatus($sourceobject);

	if ($origin == 'propal') {
		if (!class_exists('Propal')) {
			return false;
		}
		$statussigned = defined('Propal::STATUS_SIGNED') ? constant('Propal::STATUS_SIGNED') : 2;
		return $status == $statussigned;
	}

	if ($origin == 'commande') {
		if (!class_exists('Commande')) {
			return false;
		}
		$statusdraft = defined('Commande::STATUS_DRAFT') ? constant('Commande::STATUS_DRAFT') : 0;
		$statusclosed = defined('Commande::STATUS_CLOSED') ? constant('Commande::STATUS_CLOSED') : 3;
		$statuscanceled = defined('Commande::STATUS_CANCELED') ? constant('Commande::STATUS_CANCELED') : -1;
		return $status > $statusdraft && $status < $statusclosed && $status != $statuscanceled;
	}

	return false;
}

/**
 * Return a product id from a commercial line.
 *
 * @param	object	$line	Commercial line
 * @return	int				Product id
 */
function powerplantpvGetLineProductId($line)
{
	if (!empty($line->fk_product)) {
		return (int) $line->fk_product;
	}
	if (!empty($line->fk_product_or_service)) {
		return (int) $line->fk_product_or_service;
	}

	return 0;
}

/**
 * Return a commercial line id.
 *
 * @param	object	$line	Commercial line
 * @return	int				Line id
 */
function powerplantpvGetLineId($line)
{
	if (!empty($line->id)) {
		return (int) $line->id;
	}
	if (!empty($line->rowid)) {
		return (int) $line->rowid;
	}

	return 0;
}

/**
 * Return a commercial line quantity.
 *
 * @param	object	$line	Commercial line
 * @return	float			Quantity
 */
function powerplantpvGetLineQty($line)
{
	if (isset($line->qty)) {
		return (float) $line->qty;
	}
	if (isset($line->quantity)) {
		return (float) $line->quantity;
	}

	return 0.0;
}

/**
 * Return product PV category data indexed by product id.
 *
 * @param	int[]	$productids	Product ids
 * @return	array<int,array<string,mixed>>	Product category data
 */
function powerplantpvGetProductPhotovoltaicCategories($productids)
{
	global $db, $langs;

	$productids = array_values(array_unique(array_filter(array_map('intval', $productids))));
	if (empty($productids)) {
		return array();
	}

	$sql = "SELECT p.rowid, p.ref, p.label, pe.categorie_photovoltaique as category_id, cpv.code as category_code, cpv.label as category_label";
	$sql .= " FROM ".$db->prefix()."product as p";
	$sql .= " INNER JOIN ".$db->prefix()."product_extrafields as pe ON pe.fk_object = p.rowid";
	$sql .= " LEFT JOIN ".$db->prefix()."c_powerplantpv_categorypv as cpv ON cpv.rowid = pe.categorie_photovoltaique";
	$sql .= " WHERE p.rowid IN (".implode(',', $productids).")";
	$sql .= " AND pe.categorie_photovoltaique IS NOT NULL AND pe.categorie_photovoltaique <> ''";
	$sql .= " AND p.entity IN (".getEntity('product').")";

	$products = array();
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$categoryid = (int) $obj->category_id;
			$categorylabel = '';
			if (!empty($obj->category_label)) {
				$categorylabel = $obj->category_label;
			} elseif (!empty($obj->category_code)) {
				$categorylabel = $obj->category_code;
			} else {
				$categorylabel = (is_object($langs) ? $langs->trans('Category') : 'Category').' '.$categoryid;
			}

			$products[(int) $obj->rowid] = array(
				'product_id' => (int) $obj->rowid,
				'product_ref' => $obj->ref,
				'product_label' => $obj->label,
				'category_id' => $categoryid,
				'category_code' => $obj->category_code,
				'category_label' => $categorylabel,
			);
		}
		$db->free($resql);
	}

	return $products;
}

/**
 * Return the configuration used to calculate peak power on commercial documents.
 *
 * @param	string	$elementtype	Element type
 * @return	array<string,string>	Configuration, empty if unsupported
 */
function powerplantpvGetCommercialDocumentPeakPowerConfig($elementtype)
{
	$elementtype = powerplantpvNormalizeElementType($elementtype);
	if ($elementtype == 'propal') {
		return array(
			'elementtype' => 'propal',
			'parent_table' => 'propal',
			'parent_pk' => 'rowid',
			'line_table' => 'propaldet',
			'line_fk' => 'fk_propal',
			'extra_table' => 'propal_extrafields',
		);
	}
	if ($elementtype == 'commande') {
		return array(
			'elementtype' => 'commande',
			'parent_table' => 'commande',
			'parent_pk' => 'rowid',
			'line_table' => 'commandedet',
			'line_fk' => 'fk_commande',
			'extra_table' => 'commande_extrafields',
		);
	}
	if ($elementtype == 'facture') {
		return array(
			'elementtype' => 'facture',
			'parent_table' => 'facture',
			'parent_pk' => 'rowid',
			'line_table' => 'facturedet',
			'line_fk' => 'fk_facture',
			'extra_table' => 'facture_extrafields',
		);
	}

	return array();
}

/**
 * Tell whether a product has at least one native Dolibarr kit component.
 *
 * @param int $productid Product id
 * @return int 1 if it is a kit, 0 if not, <0 on SQL error
 */
function powerplantpvProductHasNativeComponents($productid)
{
	global $db;

	$sql = 'SELECT fk_product_fils FROM '.$db->prefix().'product_association';
	$sql .= ' WHERE fk_product_pere = '.((int) $productid);
	$sql .= ' LIMIT 1';
	$resql = $db->query($sql);
	if (!$resql) {
		return -1;
	}
	$haselement = $db->num_rows($resql) > 0 ? 1 : 0;
	$db->free($resql);
	return $haselement;
}

/**
 * Resolve a native Dolibarr product/kit into terminal components and useful storage capacity.
 *
 * @param int $productid Product or kit id
 * @param float $quantity Quantity of the root product
 * @param array<string,mixed>|null $resolverdata Optional normalized data source for unit tests
 * @return array{result:int,capacity_kwh:float|null,complete:bool,has_battery:bool,missing_product_ids:array<int,int>,inventory:array<int,array<string,mixed>>,composition_anomalies:array<int,string>,errors:array<int,string>}
 */
function powerplantpvResolveBatteryProduct($productid, $quantity = 1.0, $resolverdata = null)
{
	$context = array(
		'product_cache' => array(),
		'children_cache' => array(),
		'battery_cache' => array(),
		'inventory' => array(),
		'missing' => array(),
		'composition_anomalies' => array(),
		'errors' => array(),
		'capacity' => 0.0,
		'has_battery' => false,
		'technical_error' => false,
		'resolver_data' => is_array($resolverdata) ? $resolverdata : null,
	);
	powerplantpvResolveBatteryProductNode((int) $productid, (float) $quantity, array(), $context);

	return array(
		'result' => $context['technical_error'] ? -1 : 1,
		'capacity_kwh' => (!empty($context['missing']) || !empty($context['errors'])) ? null : (float) $context['capacity'],
		'complete' => empty($context['missing']) && empty($context['errors']),
		'has_battery' => (bool) $context['has_battery'],
		'missing_product_ids' => array_values(array_map('intval', array_keys($context['missing']))),
		'inventory' => array_values($context['inventory']),
		'composition_anomalies' => array_values($context['composition_anomalies']),
		'errors' => array_values($context['errors']),
	);
}

/**
 * Recursive implementation for powerplantpvResolveBatteryProduct().
 *
 * @param int $productid Product id
 * @param float $quantity Accumulated quantity
 * @param array<int,int> $path Current product path
 * @param array<string,mixed> $context Shared resolver context
 * @return void
 */
function powerplantpvResolveBatteryProductNode($productid, $quantity, array $path, array &$context)
{
	global $db;

	if ($productid <= 0 || $quantity == 0.0) {
		return;
	}
	if (in_array($productid, $path, true)) {
		$anomaly = 'BatteryKitCycle:'.implode('>', array_merge($path, array($productid)));
		$context['composition_anomalies'][] = $anomaly;
		$context['errors'][] = $anomaly;
		return;
	}
	$path[] = $productid;

	if (!isset($context['product_cache'][$productid])) {
		if (is_array($context['resolver_data'])) {
			$productdata = isset($context['resolver_data']['products'][$productid]) ? $context['resolver_data']['products'][$productid] : null;
			$context['product_cache'][$productid] = is_array($productdata) ? (object) $productdata : $productdata;
		} else {
			$sql = 'SELECT p.rowid, p.ref, p.label, cpv.code as category_code';
			$sql .= ' FROM '.$db->prefix().'product as p';
			$sql .= ' LEFT JOIN '.$db->prefix().'product_extrafields as pe ON pe.fk_object = p.rowid';
			$sql .= ' LEFT JOIN '.$db->prefix().'c_powerplantpv_categorypv as cpv ON cpv.rowid = pe.categorie_photovoltaique';
			$sql .= ' WHERE p.rowid = '.$productid.' AND p.entity IN ('.getEntity('product').')';
			$resql = $db->query($sql);
			if (!$resql) {
				$context['technical_error'] = true;
				$context['errors'][] = $db->lasterror();
				return;
			}
			$context['product_cache'][$productid] = $db->fetch_object($resql);
			$db->free($resql);
		}
	}
	$product = $context['product_cache'][$productid];
	if (!is_object($product)) {
		$anomaly = 'BatteryKitProductNotFound:'.$productid;
		$context['composition_anomalies'][] = $anomaly;
		$context['errors'][] = $anomaly;
		return;
	}

	if (!isset($context['children_cache'][$productid])) {
		$children = array();
		if (is_array($context['resolver_data'])) {
			$fixturechildren = isset($context['resolver_data']['children'][$productid]) && is_array($context['resolver_data']['children'][$productid]) ? $context['resolver_data']['children'][$productid] : array();
			foreach ($fixturechildren as $child) {
				if (is_array($child)) {
					$children[] = array('product_id' => (int) $child['product_id'], 'qty' => (float) $child['qty']);
				}
			}
		} else {
			$sql = 'SELECT pa.fk_product_fils, pa.qty, pa.rang';
			$sql .= ' FROM '.$db->prefix().'product_association as pa';
			$sql .= ' INNER JOIN '.$db->prefix().'product as p ON p.rowid = pa.fk_product_fils';
			$sql .= ' WHERE pa.fk_product_pere = '.$productid.' AND p.entity IN ('.getEntity('product').')';
			$sql .= ' ORDER BY pa.rang, pa.rowid';
			$resql = $db->query($sql);
			if (!$resql) {
				$context['technical_error'] = true;
				$context['errors'][] = $db->lasterror();
				return;
			}
			while ($child = $db->fetch_object($resql)) {
				$children[] = array('product_id' => (int) $child->fk_product_fils, 'qty' => (float) $child->qty);
			}
			$db->free($resql);
		}
		$context['children_cache'][$productid] = $children;
	}
	$children = $context['children_cache'][$productid];
	if (!empty($children)) {
		foreach ($children as $child) {
			powerplantpvResolveBatteryProductNode((int) $child['product_id'], $quantity * (float) $child['qty'], $path, $context);
		}
		return;
	}

	if (!isset($context['inventory'][$productid])) {
		$context['inventory'][$productid] = array(
			'product_id' => $productid,
			'ref' => (string) $product->ref,
			'label' => (string) $product->label,
			'category_code' => (string) $product->category_code,
			'quantity' => 0.0,
		);
	}
	$context['inventory'][$productid]['quantity'] += $quantity;

	if ((string) $product->category_code !== 'BATTER') {
		return;
	}
	$context['has_battery'] = true;
	if (!array_key_exists($productid, $context['battery_cache'])) {
		if (is_array($context['resolver_data'])) {
			$fixturecapacities = isset($context['resolver_data']['capacities']) && is_array($context['resolver_data']['capacities']) ? $context['resolver_data']['capacities'] : array();
			$context['battery_cache'][$productid] = array_key_exists($productid, $fixturecapacities) ? $fixturecapacities[$productid] : null;
		} else {
			$sql = 'SELECT b.usable_energy';
			$sql .= ' FROM '.$db->prefix().'powerplantpv_product_battery as b';
			$sql .= ' INNER JOIN '.$db->prefix().'product as p ON p.rowid = b.fk_product AND p.entity = b.entity';
			$sql .= ' WHERE b.fk_product = '.$productid.' AND p.entity IN ('.getEntity('product').')';
			$resql = $db->query($sql);
			if (!$resql) {
				$context['technical_error'] = true;
				$context['errors'][] = $db->lasterror();
				return;
			}
			$row = $db->fetch_object($resql);
			$db->free($resql);
			$context['battery_cache'][$productid] = ($row && $row->usable_energy !== null && $row->usable_energy !== '') ? (float) $row->usable_energy : null;
		}
	}
	if ($context['battery_cache'][$productid] === null) {
		$context['missing'][$productid] = 1;
		return;
	}
	$context['capacity'] += $quantity * (float) $context['battery_cache'][$productid];
}

/**
 * Calculate useful storage capacity for a commercial document.
 *
 * @param string $elementtype Document type
 * @param int $objectid Document id
 * @param int $excludelineid Line ignored before a delete trigger
 * @return array{result:int,capacity_kwh:float|null,complete:bool,has_battery:bool,missing_product_ids:array<int,int>,composition_anomalies:array<int,string>,errors:array<int,string>}
 */
function powerplantpvCalculateCommercialDocumentStorageCapacity($elementtype, $objectid, $excludelineid = 0)
{
	global $db;

	$config = powerplantpvGetCommercialDocumentPeakPowerConfig($elementtype);
	$objectid = (int) $objectid;
	if (empty($config) || $objectid <= 0) {
		return array('result' => 0, 'capacity_kwh' => 0.0, 'complete' => true, 'has_battery' => false, 'missing_product_ids' => array(), 'composition_anomalies' => array(), 'errors' => array());
	}
	$sql = 'SELECT l.rowid, l.fk_product, l.qty';
	$sql .= ' FROM '.$db->prefix().$config['line_table'].' as l';
	$sql .= ' INNER JOIN '.$db->prefix().$config['parent_table'].' as d ON d.'.$config['parent_pk'].' = l.'.$config['line_fk'];
	$sql .= ' WHERE l.'.$config['line_fk'].' = '.$objectid;
	$sql .= ' AND d.entity IN ('.getEntity($config['elementtype']).')';
	$sql .= ' AND l.fk_product IS NOT NULL AND l.fk_product > 0';
	if ((int) $excludelineid > 0) {
		$sql .= ' AND l.rowid <> '.((int) $excludelineid);
	}
	$resql = $db->query($sql);
	if (!$resql) {
		return array('result' => -1, 'capacity_kwh' => null, 'complete' => false, 'has_battery' => false, 'missing_product_ids' => array(), 'composition_anomalies' => array(), 'errors' => array($db->lasterror()));
	}
	$capacity = 0.0;
	$complete = true;
	$hasbattery = false;
	$missing = array();
	$compositionanomalies = array();
	$errors = array();
	$resultcode = 1;
	while ($line = $db->fetch_object($resql)) {
		$resolved = powerplantpvResolveBatteryProduct((int) $line->fk_product, (float) $line->qty);
		if ($resolved['result'] < 0) {
			$resultcode = -1;
		}
		$hasbattery = $hasbattery || $resolved['has_battery'];
		$complete = $complete && $resolved['complete'];
		if ($resolved['capacity_kwh'] !== null) {
			$capacity += (float) $resolved['capacity_kwh'];
		}
		foreach ($resolved['missing_product_ids'] as $productid) {
			$missing[(int) $productid] = 1;
		}
		$compositionanomalies = array_merge($compositionanomalies, $resolved['composition_anomalies']);
		$errors = array_merge($errors, $resolved['errors']);
	}
	$db->free($resql);

	$compositionanomalies = array_values(array_unique($compositionanomalies));
	$errors = array_values(array_unique($errors));
	return array(
		'result' => $resultcode,
		'capacity_kwh' => $complete ? $capacity : null,
		'complete' => $complete,
		'has_battery' => $hasbattery,
		'missing_product_ids' => array_values(array_map('intval', array_keys($missing))),
		'composition_anomalies' => $compositionanomalies,
		'errors' => $errors,
	);
}

/**
 * Save calculated useful storage capacity into a commercial document extrafield.
 *
 * @param string $elementtype Document type
 * @param int $objectid Document id
 * @param float|null $capacitykwh Capacity or null when incomplete
 * @return int 1 if written, 0 if ignored, <0 on error
 */
function powerplantpvSaveCommercialDocumentStorageCapacity($elementtype, $objectid, $capacitykwh)
{
	global $db;

	$config = powerplantpvGetCommercialDocumentPeakPowerConfig($elementtype);
	$objectid = (int) $objectid;
	if (empty($config) || $objectid <= 0) {
		return 0;
	}
	$sql = 'SELECT rowid FROM '.$db->prefix().$config['extra_table'].' WHERE fk_object = '.$objectid;
	$resql = $db->query($sql);
	if (!$resql) {
		return -1;
	}
	$row = $db->fetch_object($resql);
	$db->free($resql);
	$value = ($capacitykwh === null) ? 'NULL' : (string) ((float) $capacitykwh);
	if ($row) {
		$sql = 'UPDATE '.$db->prefix().$config['extra_table'].' SET powerplantpv_storage_capacity = '.$value.' WHERE rowid = '.((int) $row->rowid);
	} else {
		$insertparts = powerplantpvGetCommercialDocumentPeakPowerExtraFieldsInsertParts($config['elementtype'], 'powerplantpv_storage_capacity');
		if ($insertparts['result'] <= 0) {
			return 0;
		}
		$sql = 'INSERT INTO '.$db->prefix().$config['extra_table'].' (fk_object, powerplantpv_storage_capacity'.$insertparts['columns'].')';
		$sql .= ' VALUES ('.$objectid.', '.$value.$insertparts['values'].')';
	}
	return $db->query($sql) ? 1 : -1;
}

/**
 * Recalculate and save useful storage capacity for one document.
 *
 * @param string $elementtype Document type
 * @param int $objectid Document id
 * @param int $excludelineid Line ignored before deletion
 * @return array{result:int,complete:bool,capacity_kwh:float|null,missing_product_ids:array<int,int>,composition_anomalies:array<int,string>,errors:array<int,string>}
 */
function powerplantpvRecalculateCommercialDocumentStorageCapacity($elementtype, $objectid, $excludelineid = 0)
{
	$calculation = powerplantpvCalculateCommercialDocumentStorageCapacity($elementtype, $objectid, $excludelineid);
	if ($calculation['result'] < 0) {
		return array('result' => -1, 'complete' => false, 'capacity_kwh' => null, 'missing_product_ids' => $calculation['missing_product_ids'], 'composition_anomalies' => $calculation['composition_anomalies'], 'errors' => $calculation['errors']);
	}
	$result = powerplantpvSaveCommercialDocumentStorageCapacity($elementtype, $objectid, $calculation['capacity_kwh']);
	return array('result' => $result, 'complete' => $calculation['complete'], 'capacity_kwh' => $calculation['capacity_kwh'], 'missing_product_ids' => $calculation['missing_product_ids'], 'composition_anomalies' => $calculation['composition_anomalies'], 'errors' => $calculation['errors']);
}

/**
 * Return a product and every native parent kit containing it.
 *
 * @param int $productid Product id
 * @return array<int,int> Product ids
 */
function powerplantpvGetProductAndParentKitIds($productid)
{
	global $db;

	$found = array((int) $productid => 1);
	$frontier = array((int) $productid);
	while (!empty($frontier)) {
		$sql = 'SELECT DISTINCT fk_product_pere FROM '.$db->prefix().'product_association';
		$sql .= ' WHERE fk_product_fils IN ('.implode(',', array_map('intval', $frontier)).')';
		$resql = $db->query($sql);
		if (!$resql) {
			return array_values(array_map('intval', array_keys($found)));
		}
		$next = array();
		while ($obj = $db->fetch_object($resql)) {
			$parentid = (int) $obj->fk_product_pere;
			if ($parentid > 0 && !isset($found[$parentid])) {
				$found[$parentid] = 1;
				$next[] = $parentid;
			}
		}
		$db->free($resql);
		$frontier = $next;
	}
	return array_values(array_map('intval', array_keys($found)));
}

/**
 * Return readable product references indexed by product id.
 *
 * @param array<int,int> $productids Product ids
 * @return array<int,string> References
 */
function powerplantpvGetProductReferences(array $productids)
{
	global $db;

	$productids = array_values(array_unique(array_filter(array_map('intval', $productids))));
	if (empty($productids)) {
		return array();
	}
	$references = array();
	$sql = 'SELECT rowid, ref FROM '.$db->prefix().'product';
	$sql .= ' WHERE rowid IN ('.implode(',', $productids).') AND entity IN ('.getEntity('product').')';
	$resql = $db->query($sql);
	if (!$resql) {
		return $references;
	}
	while ($obj = $db->fetch_object($resql)) {
		$references[(int) $obj->rowid] = (string) $obj->ref;
	}
	$db->free($resql);
	return $references;
}

/**
 * Recalculate every commercial document that directly or indirectly uses a product.
 *
 * @param int $productid Product id
 * @return int Number of updated documents, <0 on error
 */
function powerplantpvRecalculateCommercialDocumentStorageCapacityForProduct($productid)
{
	global $db;

	$productids = powerplantpvGetProductAndParentKitIds((int) $productid);
	if (empty($productids)) {
		return 0;
	}
	$updated = 0;
	foreach (array('propal', 'commande', 'facture') as $elementtype) {
		$config = powerplantpvGetCommercialDocumentPeakPowerConfig($elementtype);
		$sql = 'SELECT DISTINCT l.'.$config['line_fk'].' as fk_object';
		$sql .= ' FROM '.$db->prefix().$config['line_table'].' as l';
		$sql .= ' INNER JOIN '.$db->prefix().$config['parent_table'].' as d ON d.'.$config['parent_pk'].' = l.'.$config['line_fk'];
		$sql .= ' WHERE l.fk_product IN ('.implode(',', array_map('intval', $productids)).')';
		$sql .= ' AND d.entity IN ('.getEntity($config['elementtype']).')';
		$resql = $db->query($sql);
		if (!$resql) {
			return -1;
		}
		$objectids = array();
		while ($obj = $db->fetch_object($resql)) {
			$objectids[(int) $obj->fk_object] = 1;
		}
		$db->free($resql);
		foreach (array_keys($objectids) as $objectid) {
			$result = powerplantpvRecalculateCommercialDocumentStorageCapacity($elementtype, (int) $objectid);
			if ($result['result'] < 0) {
				return -1;
			}
			if ($result['result'] > 0) {
				$updated++;
			}
		}
	}
	return $updated;
}

/**
 * Recalculate useful storage capacity for every supported commercial document.
 *
 * @return array{result:int,updated:int,incomplete:int,errors:int,incomplete_documents:array<int,string>,error:string}
 */
function powerplantpvRecalculateAllCommercialDocumentStorageCapacity()
{
	global $db;

	$updated = 0;
	$incomplete = 0;
	$errors = 0;
	$incompletedocuments = array();
	$db->begin();
	foreach (array('propal', 'commande', 'facture') as $elementtype) {
		$config = powerplantpvGetCommercialDocumentPeakPowerConfig($elementtype);
		$sql = 'SELECT d.'.$config['parent_pk'].' as rowid, d.ref';
		$sql .= ' FROM '.$db->prefix().$config['parent_table'].' as d';
		$sql .= ' WHERE d.entity IN ('.getEntity($config['elementtype']).')';
		$resql = $db->query($sql);
		if (!$resql) {
			$db->rollback();
			return array('result' => -1, 'updated' => $updated, 'incomplete' => $incomplete, 'errors' => $errors + 1, 'incomplete_documents' => $incompletedocuments, 'error' => $db->lasterror());
		}
		$documents = array();
		while ($obj = $db->fetch_object($resql)) {
			$documents[] = array('id' => (int) $obj->rowid, 'ref' => (string) $obj->ref);
		}
		$db->free($resql);
		foreach ($documents as $document) {
			$result = powerplantpvRecalculateCommercialDocumentStorageCapacity($elementtype, $document['id']);
			if ($result['result'] < 0) {
				$errors++;
				$db->rollback();
				return array('result' => -1, 'updated' => $updated, 'incomplete' => $incomplete, 'errors' => $errors, 'incomplete_documents' => $incompletedocuments, 'error' => implode('; ', $result['errors']));
			}
			if ($result['result'] > 0) {
				$updated++;
			}
			if (!$result['complete']) {
				$incomplete++;
				$incompletedocuments[] = $elementtype.':'.$document['ref'];
			}
		}
	}
	$db->commit();
	return array('result' => 1, 'updated' => $updated, 'incomplete' => $incomplete, 'errors' => $errors, 'incomplete_documents' => $incompletedocuments, 'error' => '');
}

/**
 * Return the dictionary ids for photovoltaic modules.
 *
 * @return	array<string,mixed>	Result with ids or error
 */
function powerplantpvGetPhotovoltaicModuleCategoryIds()
{
	global $db;

	static $cache = null;
	if (is_array($cache)) {
		return $cache;
	}

	$cache = array('result' => 1, 'ids' => array(), 'error' => '');
	$sql = "SELECT cpv.rowid";
	$sql .= " FROM ".$db->prefix()."c_powerplantpv_categorypv as cpv";
	$sql .= " WHERE cpv.code = '".$db->escape('MODULE')."'";

	$resql = $db->query($sql);
	if (!$resql) {
		$cache['result'] = -1;
		$cache['error'] = $db->lasterror();
		return $cache;
	}

	while ($obj = $db->fetch_object($resql)) {
		$cache['ids'][] = (int) $obj->rowid;
	}
	$db->free($resql);

	return $cache;
}

/**
 * Calculate total peak power for a commercial document.
 *
 * The stored value is kWc. Product pmax values are stored in W.
 *
 * @param	string	$elementtype		Element type
 * @param	int		$objectid			Document id
 * @param	int		$excludelineid		Line id to ignore, useful for pre-delete line triggers
 * @return	array<string,mixed>			Result with peak power in kWc or error
 */
function powerplantpvCalculateCommercialDocumentPeakPowerKwc($elementtype, $objectid, $excludelineid = 0)
{
	global $db;

	$config = powerplantpvGetCommercialDocumentPeakPowerConfig($elementtype);
	$objectid = (int) $objectid;
	$excludelineid = (int) $excludelineid;
	if (empty($config) || $objectid <= 0) {
		return array('result' => 0, 'peak_power_kwc' => 0.0, 'peak_power_wc' => 0.0, 'error' => '');
	}

	$modulecategories = powerplantpvGetPhotovoltaicModuleCategoryIds();
	if ($modulecategories['result'] < 0) {
		powerplantpvSetPeakPowerRecalculationError('PowerPlantPVPeakPowerErrorReadCategories', $elementtype, $objectid, $modulecategories['error'], 'read_categories');
		return array('result' => -1, 'peak_power_kwc' => 0.0, 'peak_power_wc' => 0.0, 'error' => $modulecategories['error']);
	}
	if (empty($modulecategories['ids'])) {
		return array('result' => 1, 'peak_power_kwc' => 0.0, 'peak_power_wc' => 0.0, 'error' => '');
	}

	$escapedcategoryids = array();
	foreach ($modulecategories['ids'] as $categoryid) {
		$escapedcategoryids[] = "'".$db->escape((string) $categoryid)."'";
	}

	$sql = "SELECT l.rowid, l.fk_product, l.qty";
	$sql .= " FROM ".$db->prefix().$config['line_table']." as l";
	$sql .= " INNER JOIN ".$db->prefix().$config['parent_table']." as d ON d.".$config['parent_pk']." = l.".$config['line_fk'];
	$sql .= " INNER JOIN ".$db->prefix()."product_extrafields as pe ON pe.fk_object = l.fk_product";
	$sql .= " WHERE l.".$config['line_fk']." = ".$objectid;
	$sql .= " AND d.entity IN (".getEntity($config['elementtype']).")";
	$sql .= " AND l.fk_product IS NOT NULL AND l.fk_product > 0";
	$sql .= " AND pe.categorie_photovoltaique IN (".implode(',', $escapedcategoryids).")";
	if ($excludelineid > 0) {
		$sql .= " AND l.rowid <> ".$excludelineid;
	}

	$quantitiesbyproduct = array();
	$resql = $db->query($sql);
	if (!$resql) {
		$error = $db->lasterror();
		powerplantpvSetPeakPowerRecalculationError('PowerPlantPVPeakPowerErrorReadLines', $elementtype, $objectid, $error, 'read_lines');
		return array('result' => -1, 'peak_power_kwc' => 0.0, 'peak_power_wc' => 0.0, 'error' => $error);
	}

	while ($obj = $db->fetch_object($resql)) {
		$productid = (int) $obj->fk_product;
		if ($productid <= 0) {
			continue;
		}
		if (!isset($quantitiesbyproduct[$productid])) {
			$quantitiesbyproduct[$productid] = 0.0;
		}
		$quantitiesbyproduct[$productid] += (float) $obj->qty;
	}
	$db->free($resql);

	if (empty($quantitiesbyproduct)) {
		return array('result' => 1, 'peak_power_kwc' => 0.0, 'peak_power_wc' => 0.0, 'error' => '');
	}

	$productids = array_keys($quantitiesbyproduct);
	$sql = "SELECT pv.fk_product, pv.pmax, pv.entity";
	$sql .= " FROM ".$db->prefix()."powerplantpv_product_pvpanel as pv";
	$sql .= " WHERE pv.fk_product IN (".implode(',', array_map('intval', $productids)).")";
	$sql .= " AND pv.entity IN (".getEntity('product').")";
	$sql .= " ORDER BY pv.fk_product ASC, pv.entity DESC";

	$pmaxbyproduct = array();
	$resql = $db->query($sql);
	if (!$resql) {
		$error = $db->lasterror();
		powerplantpvSetPeakPowerRecalculationError('PowerPlantPVPeakPowerErrorReadProductPower', $elementtype, $objectid, $error, 'read_product_power');
		return array('result' => -1, 'peak_power_kwc' => 0.0, 'peak_power_wc' => 0.0, 'error' => $error);
	}

	while ($obj = $db->fetch_object($resql)) {
		$productid = (int) $obj->fk_product;
		if (!isset($pmaxbyproduct[$productid])) {
			$pmaxbyproduct[$productid] = (float) $obj->pmax;
		}
	}
	$db->free($resql);

	$peakpowerwc = 0.0;
	foreach ($quantitiesbyproduct as $productid => $qty) {
		if (!isset($pmaxbyproduct[$productid])) {
			continue;
		}
		$peakpowerwc += ((float) $qty * (float) $pmaxbyproduct[$productid]);
	}

	return array(
		'result' => 1,
		'peak_power_kwc' => round($peakpowerwc / 1000, 8),
		'peak_power_wc' => $peakpowerwc,
		'error' => '',
	);
}

/**
 * Save the calculated peak power into the document extrafields table.
 *
 * @param	string	$elementtype		Element type
 * @param	int		$objectid			Document id
 * @param	float	$peakpowerkwc		Peak power in kWc
 * @return	int							1 if written, 0 if ignored, <0 if KO
 */
function powerplantpvSaveCommercialDocumentPeakPowerKwc($elementtype, $objectid, $peakpowerkwc)
{
	global $db;

	$config = powerplantpvGetCommercialDocumentPeakPowerConfig($elementtype);
	$objectid = (int) $objectid;
	if (empty($config) || $objectid <= 0) {
		return 0;
	}

	$sql = "SELECT d.".$config['parent_pk']." as rowid";
	$sql .= " FROM ".$db->prefix().$config['parent_table']." as d";
	$sql .= " WHERE d.".$config['parent_pk']." = ".$objectid;
	$sql .= " AND d.entity IN (".getEntity($config['elementtype']).")";
	$resql = $db->query($sql);
	if (!$resql) {
		$error = $db->lasterror();
		powerplantpvSetPeakPowerRecalculationError('PowerPlantPVPeakPowerErrorReadDocuments', $elementtype, $objectid, $error, 'read_document');
		dol_syslog(__FUNCTION__.' failed to read parent document: '.powerplantpvBuildPeakPowerRecalculationErrorLog(), LOG_ERR);
		return -1;
	}
	if ($db->num_rows($resql) <= 0) {
		$db->free($resql);
		return 0;
	}
	$db->free($resql);

	$value = sprintf('%.8F', round((float) $peakpowerkwc, 8));

	$sql = "SELECT ef.rowid";
	$sql .= " FROM ".$db->prefix().$config['extra_table']." as ef";
	$sql .= " WHERE ef.fk_object = ".$objectid;
	$resql = $db->query($sql);
	if (!$resql) {
		$error = $db->lasterror();
		powerplantpvSetPeakPowerRecalculationError('PowerPlantPVPeakPowerErrorReadExtraFields', $elementtype, $objectid, $error, 'read_extrafields');
		dol_syslog(__FUNCTION__.' failed to read extrafield row: '.powerplantpvBuildPeakPowerRecalculationErrorLog(), LOG_ERR);
		return -1;
	}

	$extrafieldrowid = 0;
	if ($obj = $db->fetch_object($resql)) {
		$extrafieldrowid = (int) $obj->rowid;
	}
	$db->free($resql);

	if ($extrafieldrowid > 0) {
		$sql = "UPDATE ".$db->prefix().$config['extra_table'];
		$sql .= " SET powerplantpv_peak_power = ".$value;
		$sql .= " WHERE rowid = ".$extrafieldrowid;
	} else {
		$insertparts = powerplantpvGetCommercialDocumentPeakPowerExtraFieldsInsertParts($config['elementtype'], 'powerplantpv_peak_power');
		if ($insertparts['result'] <= 0) {
			dol_syslog(__FUNCTION__.' skipped peak power insert for '.$config['elementtype'].' id='.$objectid.': '.$insertparts['reason'], LOG_WARNING);
			return 0;
		}

		$sql = "INSERT INTO ".$db->prefix().$config['extra_table']." (fk_object, powerplantpv_peak_power".$insertparts['columns'].")";
		$sql .= " VALUES (".$objectid.", ".$value.$insertparts['values'].")";
	}

	$resql = $db->query($sql);
	if (!$resql) {
		if ($extrafieldrowid <= 0) {
			dol_syslog(__FUNCTION__.' skipped peak power insert for '.$config['elementtype'].' id='.$objectid.' after SQL error: '.$db->lasterror(), LOG_WARNING);
			return 0;
		}
		dol_syslog(__FUNCTION__.' failed to write peak power: '.$db->lasterror(), LOG_ERR);
		return -1;
	}

	return 1;
}

/**
 * Build safe extra columns for creating a commercial document extrafields row.
 *
 * The commercial document extrafields tables are shared by every module. When another
 * module owns a mandatory column without default value, PowerPlantPV must not create
 * a partial row that bypasses Dolibarr's normal extrafield validation.
 *
 * @param	string	$elementtype	Element type
 * @param	string	$managedfield	Field written by PowerPlantPV
 * @return	array{result:int,columns:string,values:string,reason:string}	Insert parts or reason to skip
 */
function powerplantpvGetCommercialDocumentPeakPowerExtraFieldsInsertParts($elementtype, $managedfield)
{
	global $db;

	if (!class_exists('ExtraFields')) {
		require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
	}

	$extrafields = new ExtraFields($db);
	$extrafields->fetch_name_optionals_label($elementtype);

	$attributes = isset($extrafields->attributes[$elementtype]) && is_array($extrafields->attributes[$elementtype])
		? $extrafields->attributes[$elementtype]
		: array();
	$labels = isset($attributes['label']) && is_array($attributes['label']) ? $attributes['label'] : array();
	$columns = array();
	$values = array();
	$addedfields = array($managedfield => 1);

	foreach ($labels as $key => $label) {
		$key = (string) $key;
		if ($key === $managedfield) {
			continue;
		}

		$type = isset($attributes['type'][$key]) ? (string) $attributes['type'][$key] : '';
		if (in_array($type, array('separate', 'point', 'multipts', 'linestrg', 'polygon'), true)) {
			if (!empty($attributes['required'][$key])) {
				return array('result' => 0, 'columns' => '', 'values' => '', 'reason' => 'required unsupported extrafield '.$key);
			}
			continue;
		}

		$required = !empty($attributes['required'][$key]);
		$hasdefault = isset($attributes['default'])
			&& array_key_exists($key, $attributes['default'])
			&& $attributes['default'][$key] !== null
			&& $attributes['default'][$key] !== '';
		if ($required && !$hasdefault) {
			return array('result' => 0, 'columns' => '', 'values' => '', 'reason' => 'required extrafield '.$key.' has no default value');
		}
		if (!$hasdefault) {
			continue;
		}

		$columns[] = $key;
		$values[] = "'".$db->escape((string) $attributes['default'][$key])."'";
		$addedfields[$key] = 1;
	}

	if (!empty($attributes['mandatoryfieldsofotherentities']) && is_array($attributes['mandatoryfieldsofotherentities'])) {
		foreach ($attributes['mandatoryfieldsofotherentities'] as $key => $type) {
			$key = (string) $key;
			if (isset($addedfields[$key]) || (isset($attributes['type'][$key]) && $attributes['type'][$key] !== '')) {
				continue;
			}

			$columns[] = $key;
			if (in_array($type, array('int', 'double', 'price'), true)) {
				$values[] = '0';
			} else {
				$values[] = "''";
			}
			$addedfields[$key] = 1;
		}
	}

	return array(
		'result' => 1,
		'columns' => empty($columns) ? '' : ', '.implode(', ', $columns),
		'values' => empty($values) ? '' : ', '.implode(', ', $values),
		'reason' => '',
	);
}

/**
 * Recalculate and store total peak power for a commercial document.
 *
 * @param	string	$elementtype		Element type
 * @param	int		$objectid			Document id
 * @param	int		$excludelineid		Line id to ignore, useful for pre-delete line triggers
 * @return	int							1 if recalculated, 0 if ignored, <0 if KO
 */
function powerplantpvRecalculateCommercialDocumentPeakPower($elementtype, $objectid, $excludelineid = 0)
{
	powerplantpvResetPeakPowerRecalculationError();

	$calculation = powerplantpvCalculateCommercialDocumentPeakPowerKwc($elementtype, $objectid, $excludelineid);
	if ($calculation['result'] < 0) {
		dol_syslog(__FUNCTION__.' failed to calculate peak power: '.powerplantpvBuildPeakPowerRecalculationErrorLog(), LOG_ERR);
		return -1;
	}
	if ($calculation['result'] == 0) {
		return 0;
	}

	$result = powerplantpvSaveCommercialDocumentPeakPowerKwc($elementtype, $objectid, $calculation['peak_power_kwc']);
	if ($result < 0) {
		dol_syslog(__FUNCTION__.' failed to save peak power: '.powerplantpvBuildPeakPowerRecalculationErrorLog(), LOG_ERR);
		return -1;
	}
	if ($result == 0) {
		return 0;
	}

	return 1;
}

/**
 * Recalculate peak power for all commercial documents using a product.
 *
 * @param	int	$productid	Product id
 * @return	int				Number of recalculated documents, <0 on error
 */
function powerplantpvRecalculateCommercialDocumentPeakPowerForProduct($productid)
{
	global $db;

	$productid = (int) $productid;
	powerplantpvResetPeakPowerRecalculationError();
	if ($productid <= 0) {
		return 0;
	}

	$updated = 0;
	foreach (array('propal', 'commande', 'facture') as $elementtype) {
		$config = powerplantpvGetCommercialDocumentPeakPowerConfig($elementtype);
		if (empty($config)) {
			continue;
		}

		$sql = "SELECT DISTINCT l.".$config['line_fk']." as fk_object";
		$sql .= " FROM ".$db->prefix().$config['line_table']." as l";
		$sql .= " INNER JOIN ".$db->prefix().$config['parent_table']." as d ON d.".$config['parent_pk']." = l.".$config['line_fk'];
		$sql .= " WHERE l.fk_product = ".$productid;
		$sql .= " AND d.entity IN (".getEntity($config['elementtype']).")";

		$resql = $db->query($sql);
		if (!$resql) {
			$error = $db->lasterror();
			powerplantpvSetPeakPowerRecalculationError('PowerPlantPVPeakPowerErrorReadDocuments', 'product', $productid, $error, 'read_documents_'.$elementtype, $productid);
			dol_syslog(__FUNCTION__.' failed to list '.$elementtype.' documents for product '.$productid.': '.powerplantpvBuildPeakPowerRecalculationErrorLog(), LOG_ERR);
			return -1;
		}

		$objectids = array();
		while ($obj = $db->fetch_object($resql)) {
			if ((int) $obj->fk_object > 0) {
				$objectids[] = (int) $obj->fk_object;
			}
		}
		$db->free($resql);

		foreach (array_values(array_unique($objectids)) as $objectid) {
			$result = powerplantpvRecalculateCommercialDocumentPeakPower($elementtype, $objectid);
			if ($result < 0) {
				return -1;
			}
			if ($result > 0) {
				$updated++;
			}
		}
	}

	return $updated;
}

/**
 * Recalculate peak power for all supported commercial documents.
 *
 * @return	array{result:int,updated:int,error:string,errorinfo:array<string,mixed>}	Result data
 */
function powerplantpvRecalculateAllCommercialDocumentPeakPower()
{
	global $db;

	$updated = 0;
	$error = '';
	$errorinfo = array();
	powerplantpvResetPeakPowerRecalculationError();

	$db->begin();

	foreach (array('propal', 'commande', 'facture') as $elementtype) {
		$config = powerplantpvGetCommercialDocumentPeakPowerConfig($elementtype);
		if (empty($config)) {
			continue;
		}

		$sql = "SELECT DISTINCT l.".$config['line_fk']." as fk_object";
		$sql .= " FROM ".$db->prefix().$config['line_table']." as l";
		$sql .= " INNER JOIN ".$db->prefix().$config['parent_table']." as d ON d.".$config['parent_pk']." = l.".$config['line_fk'];
		$sql .= " WHERE d.entity IN (".getEntity($config['elementtype']).")";

		$resql = $db->query($sql);
		if (!$resql) {
			$error = $db->lasterror();
			$errorinfo = powerplantpvSetPeakPowerRecalculationError('PowerPlantPVPeakPowerErrorReadDocuments', $elementtype, 0, $error, 'read_documents');
			$db->rollback();
			dol_syslog(__FUNCTION__.' failed to list '.$elementtype.' documents: '.powerplantpvBuildPeakPowerRecalculationErrorLog($errorinfo), LOG_ERR);
			return array('result' => -1, 'updated' => $updated, 'error' => $error, 'errorinfo' => $errorinfo);
		}

		$objectids = array();
		while ($obj = $db->fetch_object($resql)) {
			if ((int) $obj->fk_object > 0) {
				$objectids[] = (int) $obj->fk_object;
			}
		}
		$db->free($resql);

		foreach (array_values(array_unique($objectids)) as $objectid) {
			$result = powerplantpvRecalculateCommercialDocumentPeakPower($elementtype, $objectid);
			if ($result < 0) {
				$errorinfo = powerplantpvGetLastPeakPowerRecalculationError();
				$error = powerplantpvBuildPeakPowerRecalculationErrorMessage(true, $errorinfo);
				$db->rollback();
				return array('result' => -1, 'updated' => $updated, 'error' => $error, 'errorinfo' => $errorinfo);
			}
			if ($result > 0) {
				$updated++;
			}
		}
	}

	$db->commit();

	return array('result' => 1, 'updated' => $updated, 'error' => $error, 'errorinfo' => $errorinfo);
}

/**
 * Return the stored peak power for a commercial document object.
 *
 * @param	CommonObject	$object	Commercial document object
 * @return	float					Peak power in kWc
 */
function powerplantpvGetObjectPeakPowerKwc($object)
{
	global $db;

	if (!is_object($object)) {
		return 0.0;
	}

	if (isset($object->array_options['options_powerplantpv_peak_power'])
		&& $object->array_options['options_powerplantpv_peak_power'] !== '') {
		return (float) $object->array_options['options_powerplantpv_peak_power'];
	}

	$objectid = 0;
	if (!empty($object->id)) {
		$objectid = (int) $object->id;
	} elseif (!empty($object->rowid)) {
		$objectid = (int) $object->rowid;
	}
	if ($objectid <= 0) {
		return 0.0;
	}

	$config = array();
	$elementtypes = array();
	if (!empty($object->table_element)) {
		$elementtypes[] = $object->table_element;
	}
	if (!empty($object->element)) {
		$elementtypes[] = $object->element;
	}
	foreach (array_values(array_unique($elementtypes)) as $elementtype) {
		$config = powerplantpvGetCommercialDocumentPeakPowerConfig($elementtype);
		if (!empty($config)) {
			break;
		}
	}
	if (empty($config)) {
		return 0.0;
	}

	$sql = "SELECT ef.powerplantpv_peak_power";
	$sql .= " FROM ".$db->prefix().$config['extra_table']." as ef";
	$sql .= " WHERE ef.fk_object = ".$objectid;
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__FUNCTION__.' failed to read peak power: '.$db->lasterror(), LOG_WARNING);
		return 0.0;
	}

	$peakpowerkwc = 0.0;
	if ($obj = $db->fetch_object($resql)) {
		$peakpowerkwc = (float) $obj->powerplantpv_peak_power;
	}
	$db->free($resql);

	return $peakpowerkwc;
}

/**
 * Build the automatic material summary from a proposal or customer order.
 *
 * @param	string	$origin					Origin type
 * @param	int		$originid				Origin object id
 * @param	int<0,1>	$checksourceeligible	1=ignore lines when the source status is not eligible
 * @return	array<string,mixed>				Summary data
 */
function powerplantpvGetAutomaticMaterialSummary($origin, $originid, $checksourceeligible = 1)
{
	global $langs;

	$origin = powerplantpvNormalizeOriginType($origin);
	$summary = array(
		'origin' => $origin,
		'origin_id' => (int) $originid,
		'source_object' => null,
		'eligible' => false,
		'lines' => array(),
		'categories' => array(),
		'total_components' => 0,
		'total_ignored_qty' => 0.0,
		'warnings' => array(),
		'errors' => array(),
	);

	if (!in_array($origin, array('propal', 'commande'), true) || (int) $originid <= 0) {
		return $summary;
	}

	$sourceobject = powerplantpvFetchOriginObjectWithLines($origin, $originid);
	if (!is_object($sourceobject)) {
		$summary['errors'][] = 'ErrorFailedToFetchOrigin';
		return $summary;
	}
	$summary['source_object'] = $sourceobject;
	$summary['eligible'] = powerplantpvIsAutomaticMaterialSourceEligible($origin, $sourceobject);
	if (!empty($checksourceeligible) && empty($summary['eligible'])) {
		return $summary;
	}

	$productids = array();
	if (!empty($sourceobject->lines) && is_array($sourceobject->lines)) {
		foreach ($sourceobject->lines as $line) {
			$productid = powerplantpvGetLineProductId($line);
			if ($productid > 0) {
				$productids[] = $productid;
			}
		}
	}

	$products = powerplantpvGetProductPhotovoltaicCategories($productids);
	if (empty($products) || empty($sourceobject->lines) || !is_array($sourceobject->lines)) {
		return $summary;
	}

	foreach ($sourceobject->lines as $line) {
		$productid = powerplantpvGetLineProductId($line);
		if ($productid <= 0 || empty($products[$productid])) {
			continue;
		}

		$qty = round(powerplantpvGetLineQty($line), 8);
		if ($qty <= 0) {
			continue;
		}

		$components = (int) floor($qty);
		$ignoredqty = $qty - $components;
		if ($ignoredqty < 0.00000001) {
			$ignoredqty = 0.0;
		}
		if ($components <= 0) {
			if ($ignoredqty > 0) {
				$summary['total_ignored_qty'] += $ignoredqty;
				if (is_object($langs)) {
					$summary['warnings'][] = $langs->trans('PowerPlantFractionalQuantityIgnored', $products[$productid]['product_ref'], price($ignoredqty));
				}
			}
			continue;
		}

		$product = $products[$productid];
		$categorykey = (string) $product['category_id'];
		if (empty($summary['categories'][$categorykey])) {
			$summary['categories'][$categorykey] = array(
				'category_id' => (int) $product['category_id'],
				'category_code' => $product['category_code'],
				'category_label' => $product['category_label'],
				'total_components' => 0,
				'total_qty' => 0.0,
				'total_ignored_qty' => 0.0,
			);
		}

		$summaryline = array(
			'source_line_id' => powerplantpvGetLineId($line),
			'fk_product' => $productid,
			'product_ref' => $product['product_ref'],
			'product_label' => $product['product_label'],
			'category_id' => (int) $product['category_id'],
			'category_code' => $product['category_code'],
			'category_label' => $product['category_label'],
			'source_qty' => $qty,
			'components_to_create' => $components,
			'ignored_qty' => $ignoredqty,
		);

		$summary['lines'][] = $summaryline;
		$summary['total_components'] += $components;
		$summary['total_ignored_qty'] += $ignoredqty;
		$summary['categories'][$categorykey]['total_components'] += $components;
		$summary['categories'][$categorykey]['total_qty'] += $qty;
		$summary['categories'][$categorykey]['total_ignored_qty'] += $ignoredqty;

		if ($ignoredqty > 0 && is_object($langs)) {
			$summary['warnings'][] = $langs->trans('PowerPlantFractionalQuantityIgnored', $product['product_ref'], price($ignoredqty));
		}
	}

	return $summary;
}

/**
 * Create the material composition from the source object's photovoltaic lines.
 *
 * @param	PowerPlant	$powerplant	Power plant object
 * @param	string		$origin		Origin type
 * @param	int			$originid	Origin object id
 * @param	User		$user		User creating the object
 * @return	int						Number of created component lines, <0 if KO
 */
function powerplantpvCreateComponentsFromOrigin($powerplant, $origin, $originid, $user)
{
	global $conf;

	$db = $powerplant->db;
	$summary = powerplantpvGetAutomaticMaterialSummary($origin, $originid, 1);
	if (empty($summary['total_components']) || empty($summary['lines'])) {
		$powerplant->context['powerplantpv_material_summary'] = $summary;
		return 0;
	}

	$entity = (!empty($powerplant->entity) ? (int) $powerplant->entity : (int) $conf->entity);
	$created = 0;
	foreach ($summary['lines'] as $line) {
		$i = 0;
		while ($i < (int) $line['components_to_create']) {
			$sql = "INSERT INTO ".$db->prefix()."powerplantpv_powerplantcomp";
			$sql .= "(fk_powerplant, fk_product, fk_status, qty, serial_number, commissioning_date, entity)";
			$sql .= " VALUES (".((int) $powerplant->id).", ".((int) $line['fk_product']).", 4, 1, '', NULL, ".$entity.")";
			$resql = $db->query($sql);
			if (!$resql) {
				$powerplant->error = $db->lasterror();
				$powerplant->errors[] = $powerplant->error;
				return -1;
			}
			$created++;
			$i++;
		}
	}

	$summary['created_components'] = $created;
	$powerplant->context['powerplantpv_material_summary'] = $summary;
	if ($created > 0 && function_exists('powerplantRecalculateInstalledPower')) {
		$resultrecalculate = powerplantRecalculateInstalledPower($powerplant);
		if ($resultrecalculate < 0) {
			return -1;
		}
	}

	return $created;
}

/**
 * Return the linked-object element types for an object.
 *
 * @param	CommonObject	$object	Object
 * @return	string[]				Element types
 */
function powerplantpvGetObjectElementTypes($object)
{
	$elementtypes = array();
	if (is_object($object) && method_exists($object, 'getElementType')) {
		$elementtypes[] = $object->getElementType();
	}
	if (is_object($object) && !empty($object->element)) {
		$elementtypes[] = $object->element;
		$elementtypes[] = powerplantpvNormalizeElementType($object->element);
	}

	return array_values(array_unique(array_filter($elementtypes)));
}

/**
 * Check a permission path with Dolibarr administrator bypass.
 *
 * @param	User		$user		Current user
 * @param	string[]	$rightpath	Path accepted by User::hasRight()
 * @return	int<0,1>				1 if allowed, 0 otherwise
 */
function powerplantpvUserHasRightPath($user, $rightpath)
{
	if (!is_object($user) || empty($rightpath) || !is_array($rightpath)) {
		return 0;
	}
	if (!empty($user->admin)) {
		return 1;
	}
	if (method_exists($user, 'hasRight')) {
		return (int) (bool) call_user_func_array(array($user, 'hasRight'), $rightpath);
	}
	if (empty($user->rights)) {
		return 0;
	}

	$cursor = $user->rights;
	foreach ($rightpath as $pathpart) {
		if (!is_object($cursor) || !isset($cursor->{$pathpart})) {
			return 0;
		}
		$cursor = $cursor->{$pathpart};
	}

	return !empty($cursor) ? 1 : 0;
}

/**
 * Check a maintenance permission with administrator bypass.
 *
 * @param	User	$user	Current user
 * @param	string	$right	Permission subkey
 * @return	int<0,1>		1 if allowed, 0 otherwise
 */
function powerplantpvUserHasMaintenanceRight($user, $right = 'read')
{
	return powerplantpvUserHasRightPath($user, array('powerplantpv', 'maintenance', $right));
}

/**
 * Return the canonical element_element type for power plants.
 *
 * @return	string	Canonical linked-object type
 */
function powerplantpvGetCanonicalPowerPlantLinkType()
{
	return 'powerplantpv_powerplant';
}

/**
 * Return every supported power plant element type found in legacy/native links.
 *
 * @return	string[]	Power plant link types
 */
function powerplantpvGetPowerPlantLinkTypes()
{
	return array(powerplantpvGetCanonicalPowerPlantLinkType(), 'powerplant@powerplantpv', 'powerplant');
}

/**
 * Check if an element type is a supported power plant type.
 *
 * @param	string	$elementtype	Element type
 * @return	bool					True if this is a power plant link type
 */
function powerplantpvIsPowerPlantLinkType($elementtype)
{
	return in_array((string) $elementtype, powerplantpvGetPowerPlantLinkTypes(), true);
}

/**
 * Return the canonical object type used for quick power plant links.
 *
 * @param	CommonObject	$object	Object
 * @return	string					Canonical linked-object type, empty if unsupported
 */
function powerplantpvGetCanonicalNativePowerPlantLinkedObjectType($object)
{
	if (!is_object($object)) {
		return '';
	}

	$elementtypes = powerplantpvGetObjectElementTypes($object);
	foreach ($elementtypes as $elementtype) {
		$normalized = powerplantpvNormalizeElementType($elementtype);
		if ($normalized == 'contrat') {
			return 'contrat';
		}
		if ($normalized == 'fichinter') {
			return 'fichinter';
		}
	}

	return '';
}

/**
 * Check if an object supports native quick power plant links.
 *
 * @param	CommonObject	$object	Object
 * @return	bool					True if supported
 */
function powerplantpvSupportsNativePowerPlantLinks($object)
{
	return powerplantpvGetCanonicalNativePowerPlantLinkedObjectType($object) !== '';
}

/**
 * Return the object id in a CommonObject-compatible way.
 *
 * @param	CommonObject	$object	Object
 * @return	int					Object id
 */
function powerplantpvGetCommonObjectId($object)
{
	if (!is_object($object)) {
		return 0;
	}
	if (!empty($object->id)) {
		return (int) $object->id;
	}
	if (!empty($object->rowid)) {
		return (int) $object->rowid;
	}

	return 0;
}

/**
 * Return the third party id carried by a Dolibarr object.
 *
 * @param	CommonObject|null	$object	Object
 * @return	int							Third party id
 */
function powerplantpvGetObjectSocId($object)
{
	if (!is_object($object)) {
		return 0;
	}
	if (!empty($object->socid)) {
		return (int) $object->socid;
	}
	if (!empty($object->fk_soc)) {
		return (int) $object->fk_soc;
	}

	return 0;
}

/**
 * Normalize a list of ids.
 *
 * @param	mixed	$values	Values from GETPOST() or internal code
 * @return	int[]			Unique positive ids
 */
function powerplantpvSanitizeIdArray($values)
{
	if (!is_array($values)) {
		$values = array($values);
	}

	$ids = array();
	foreach ($values as $value) {
		if (is_array($value)) {
			$ids = array_merge($ids, powerplantpvSanitizeIdArray($value));
			continue;
		}
		foreach (explode(',', (string) $value) as $part) {
			$id = (int) trim($part);
			if ($id > 0) {
				$ids[] = $id;
			}
		}
	}

	return array_values(array_unique($ids));
}

/**
 * Return entity ids currently allowed for power plants.
 *
 * @return	int[]	Entity ids
 */
function powerplantpvGetAccessiblePowerPlantEntityIds()
{
	$entityids = array();
	foreach (explode(',', (string) getEntity('powerplant')) as $entityid) {
		$entityid = (int) trim($entityid);
		if ($entityid > 0) {
			$entityids[] = $entityid;
		}
	}

	return array_values(array_unique($entityids));
}

/**
 * Return linked power plant rows in llx_element_element.
 *
 * @param	CommonObject	$object	Object with links
 * @return	array<int,array{rowid:int,powerplant_id:int,powerplant_type:string,object_type:string,direction:string,is_canonical:int}>	Link rows
 */
function powerplantpvGetLinkedPowerPlantRows($object)
{
	if (!is_object($object) || !isModEnabled('powerplantpv')) {
		return array();
	}

	$objectid = powerplantpvGetCommonObjectId($object);
	if ($objectid <= 0) {
		return array();
	}

	$db = (!empty($object->db) ? $object->db : (isset($GLOBALS['db']) ? $GLOBALS['db'] : null));
	$objecttypes = powerplantpvGetObjectElementTypes($object);
	$canonicalobjecttype = powerplantpvGetCanonicalNativePowerPlantLinkedObjectType($object);
	if ($canonicalobjecttype !== '') {
		$objecttypes[] = $canonicalobjecttype;
	}
	$objecttypes = array_values(array_unique(array_filter($objecttypes)));
	if (empty($db) || empty($objecttypes)) {
		return array();
	}

	$escapedobjecttypes = array();
	foreach ($objecttypes as $objecttype) {
		$escapedobjecttypes[] = "'".$db->escape($objecttype)."'";
	}

	$powerplanttypes = powerplantpvGetPowerPlantLinkTypes();
	$escapedpowerplanttypes = array();
	foreach ($powerplanttypes as $powerplanttype) {
		$escapedpowerplanttypes[] = "'".$db->escape($powerplanttype)."'";
	}

	$sql = "SELECT ee.rowid, ee.fk_source, ee.sourcetype, ee.fk_target, ee.targettype";
	$sql .= " FROM ".$db->prefix()."element_element as ee";
	$sql .= " WHERE ((ee.fk_source = ".$objectid;
	$sql .= " AND ee.sourcetype IN (".implode(',', $escapedobjecttypes).")";
	$sql .= " AND ee.targettype IN (".implode(',', $escapedpowerplanttypes)."))";
	$sql .= " OR (ee.fk_target = ".$objectid;
	$sql .= " AND ee.targettype IN (".implode(',', $escapedobjecttypes).")";
	$sql .= " AND ee.sourcetype IN (".implode(',', $escapedpowerplanttypes).")))";
	$sql .= " ORDER BY ee.rowid ASC";

	$rows = array();
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$powerplantid = 0;
			$powerplanttype = '';
			$objecttype = '';
			$direction = '';
			if (powerplantpvIsPowerPlantLinkType((string) $obj->targettype)) {
				$powerplantid = (int) $obj->fk_target;
				$powerplanttype = (string) $obj->targettype;
				$objecttype = (string) $obj->sourcetype;
				$direction = 'object_source';
			} elseif (powerplantpvIsPowerPlantLinkType((string) $obj->sourcetype)) {
				$powerplantid = (int) $obj->fk_source;
				$powerplanttype = (string) $obj->sourcetype;
				$objecttype = (string) $obj->targettype;
				$direction = 'object_target';
			}
			if ($powerplantid <= 0) {
				continue;
			}

			$rows[] = array(
				'rowid' => (int) $obj->rowid,
				'powerplant_id' => $powerplantid,
				'powerplant_type' => $powerplanttype,
				'object_type' => $objecttype,
				'direction' => $direction,
				'is_canonical' => (
					$powerplanttype === powerplantpvGetCanonicalPowerPlantLinkType()
					&& ($canonicalobjecttype === '' || powerplantpvNormalizeElementType($objecttype) === $canonicalobjecttype)
					&& $direction === 'object_source'
				) ? 1 : 0,
			);
		}
		$db->free($resql);
	}

	return $rows;
}

/**
 * Return power plants linked to a Dolibarr object.
 *
 * @param	CommonObject	$object		Object with links
 * @return	PowerPlant[]				Linked power plants indexed by id
 */
function powerplantpvGetLinkedPowerPlants($object)
{
	global $user;

	if (!is_object($object) || powerplantpvGetCommonObjectId($object) <= 0 || !isModEnabled('powerplantpv')) {
		return array();
	}

	$db = (!empty($object->db) ? $object->db : (isset($GLOBALS['db']) ? $GLOBALS['db'] : null));
	if (empty($db)) {
		return array();
	}
	dol_include_once('/powerplantpv/class/powerplant.class.php');

	$entityids = powerplantpvGetAccessiblePowerPlantEntityIds();
	$linkedrows = powerplantpvGetLinkedPowerPlantRows($object);

	$powerplants = array();
	foreach ($linkedrows as $linkedrow) {
		$powerplantid = (int) $linkedrow['powerplant_id'];
		if ($powerplantid <= 0 || isset($powerplants[$powerplantid])) {
			continue;
		}

		$powerplant = new PowerPlant($db);
		if ($powerplant->fetch($powerplantid) > 0) {
			if (!empty($entityids) && !in_array((int) $powerplant->entity, $entityids, true)) {
				continue;
			}
			if (is_object($user) && !empty($user->socid) && (int) $powerplant->fk_soc !== (int) $user->socid) {
				continue;
			}
			$powerplants[$powerplantid] = $powerplant;
		}
	}

	return $powerplants;
}

/**
 * Return selectable power plants for a native object quick link.
 *
 * @param	CommonObject|null	$object		Object used to restrict third party
 * @param	int[]				$includeids	Power plant ids to keep in the options when already linked
 * @return	array<int,string>				Options indexed by power plant id
 */
function powerplantpvGetSelectablePowerPlantOptions($object = null, $includeids = array())
{
	global $db, $user;

	if (!isModEnabled('powerplantpv') || !powerplantpvUserHasRightPath($user, array('powerplantpv', 'powerplant', 'read'))) {
		return array();
	}

	$includeids = powerplantpvSanitizeIdArray($includeids);
	$includeidssql = !empty($includeids) ? implode(',', array_map('intval', $includeids)) : '';
	$socid = powerplantpvGetObjectSocId($object);
	if (!empty($user->socid)) {
		$socid = (int) $user->socid;
	}

	$sql = "SELECT t.rowid, t.ref, t.label, t.fk_soc, s.nom as socname";
	$sql .= " FROM ".$db->prefix()."powerplantpv_powerplant as t";
	$sql .= " LEFT JOIN ".$db->prefix()."societe as s ON s.rowid = t.fk_soc";
	$sql .= " WHERE t.entity IN (".getEntity('powerplant').")";
	if ($socid > 0) {
		$sql .= " AND (t.fk_soc = ".$socid;
		if ($includeidssql !== '' && empty($user->socid)) {
			$sql .= " OR t.rowid IN (".$includeidssql.")";
		}
		$sql .= ")";
	}
	$sql .= " ORDER BY t.ref ASC, t.rowid ASC";

	$options = array();
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$label = (string) $obj->ref;
			if (!empty($obj->label)) {
				$label .= ' - '.(string) $obj->label;
			}
			if (!empty($obj->socname)) {
				$label .= ' ('.(string) $obj->socname.')';
			}
			$options[(int) $obj->rowid] = $label;
		}
		$db->free($resql);
	}

	return $options;
}

/**
 * Keep only selected power plants allowed for the current object and user.
 *
 * @param	int[]				$selectedids		Posted ids
 * @param	CommonObject|null	$object				Object
 * @param	int[]				$currentlinkedids	Current linked ids
 * @return	int[]									Allowed ids
 */
function powerplantpvFilterSelectablePowerPlantIds($selectedids, $object = null, $currentlinkedids = array())
{
	$selectedids = powerplantpvSanitizeIdArray($selectedids);
	if (empty($selectedids)) {
		return array();
	}

	$options = powerplantpvGetSelectablePowerPlantOptions($object, array_merge($selectedids, $currentlinkedids));
	$allowed = array();
	foreach ($selectedids as $selectedid) {
		if (isset($options[$selectedid])) {
			$allowed[] = $selectedid;
		}
	}

	return array_values(array_unique($allowed));
}

/**
 * Return power plant ids passed by URL/POST or inherited from linked objects.
 *
 * @param	CommonObject|null	$object				Current object
 * @param	int<0,1>			$fallbacklinked		1 to use current object links when no request value exists
 * @return	int[]									Power plant ids
 */
function powerplantpvGetRequestedPowerPlantIds($object = null, $fallbacklinked = 0)
{
	$ids = powerplantpvSanitizeIdArray(GETPOST('powerplantpv_powerplants', 'array:int'));
	$singlepowerplantid = GETPOSTINT('fk_powerplant');
	if ($singlepowerplantid > 0) {
		$ids[] = $singlepowerplantid;
	}

	$origin = powerplantpvNormalizeElementType(GETPOST('origin', 'alphanohtml'));
	$originid = GETPOSTINT('originid') > 0 ? GETPOSTINT('originid') : GETPOSTINT('origin_id');
	if ($originid > 0 && powerplantpvIsPowerPlantLinkType($origin)) {
		$ids[] = $originid;
	}

	$contractid = GETPOSTINT('fk_contrat') > 0 ? GETPOSTINT('fk_contrat') : GETPOSTINT('contratid');
	if ($origin == 'contrat' && $originid > 0) {
		$contractid = $originid;
	}
	if ($contractid > 0) {
		$contract = powerplantpvFetchOriginObject('contrat', $contractid);
		if (is_object($contract)) {
			foreach (powerplantpvGetLinkedPowerPlants($contract) as $powerplant) {
				$ids[] = powerplantpvGetCommonObjectId($powerplant);
			}
		}
	}

	if (empty($ids) && $fallbacklinked && is_object($object) && powerplantpvGetCommonObjectId($object) > 0) {
		foreach (powerplantpvGetLinkedPowerPlants($object) as $powerplant) {
			$ids[] = powerplantpvGetCommonObjectId($powerplant);
		}
	}

	return array_values(array_unique(array_filter(array_map('intval', $ids))));
}

/**
 * Synchronize native llx_element_element links between an object and power plants.
 *
 * @param	CommonObject	$object		Contract or intervention object
 * @param	int[]			$selectedids	Selected power plant ids
 * @param	User			$user		User applying the change
 * @return	int							>0 if OK, <0 if KO
 */
function powerplantpvSyncNativePowerPlantLinks($object, $selectedids, $user)
{
	if (!is_object($object) || powerplantpvGetCommonObjectId($object) <= 0 || !powerplantpvSupportsNativePowerPlantLinks($object)) {
		return 0;
	}

	$db = (!empty($object->db) ? $object->db : (isset($GLOBALS['db']) ? $GLOBALS['db'] : null));
	if (empty($db)) {
		$object->error = 'ErrorNoDatabaseHandler';
		$object->errors[] = $object->error;
		return -1;
	}

	dol_include_once('/powerplantpv/class/powerplant.class.php');

	$objectid = powerplantpvGetCommonObjectId($object);
	$objecttype = powerplantpvGetCanonicalNativePowerPlantLinkedObjectType($object);
	$currentrows = powerplantpvGetLinkedPowerPlantRows($object);
	$currentlinkedids = array();
	foreach ($currentrows as $currentrow) {
		$currentlinkedids[] = (int) $currentrow['powerplant_id'];
	}

	$manageableoptions = powerplantpvGetSelectablePowerPlantOptions($object, $currentlinkedids);
	$manageableids = array_fill_keys(array_keys($manageableoptions), 1);
	$selectedids = powerplantpvFilterSelectablePowerPlantIds($selectedids, $object, $currentlinkedids);
	$selectedindex = array_fill_keys($selectedids, 1);
	$currentbyid = array();
	foreach ($currentrows as $currentrow) {
		$powerplantid = (int) $currentrow['powerplant_id'];
		if (!isset($currentbyid[$powerplantid])) {
			$currentbyid[$powerplantid] = array();
		}
		$currentbyid[$powerplantid][] = $currentrow;
	}

	foreach ($currentbyid as $powerplantid => $rows) {
		if (empty($manageableids[$powerplantid])) {
			continue;
		}
		$canonicalkept = false;
		foreach ($rows as $row) {
			$mustdelete = empty($selectedindex[$powerplantid]);
			if (!$mustdelete && !empty($row['is_canonical']) && !$canonicalkept) {
				$canonicalkept = true;
				continue;
			}
			if (!$mustdelete && (empty($row['is_canonical']) || $canonicalkept)) {
				$mustdelete = true;
			}
			if ($mustdelete) {
				$resultdelete = $object->deleteObjectLinked(null, '', null, '', (int) $row['rowid'], $user, 0);
				if ($resultdelete <= 0) {
					$object->error = !empty($object->error) ? $object->error : 'ErrorFailedToDeleteLink';
					$object->errors[] = $object->error;
					return -1;
				}
			}
		}
	}

	$currentrowsafterdelete = powerplantpvGetLinkedPowerPlantRows($object);
	$canonicalids = array();
	foreach ($currentrowsafterdelete as $row) {
		if (!empty($row['is_canonical'])) {
			$canonicalids[(int) $row['powerplant_id']] = 1;
		}
	}

	foreach ($selectedids as $powerplantid) {
		if (!empty($canonicalids[$powerplantid])) {
			continue;
		}

		$powerplant = new PowerPlant($db);
		$resultfetch = $powerplant->fetch((int) $powerplantid);
		if ($resultfetch <= 0) {
			continue;
		}

		$resultadd = $powerplant->add_object_linked($objecttype, $objectid, $user, 0);
		if ($resultadd <= 0) {
			$object->error = !empty($powerplant->error) ? $powerplant->error : 'ErrorFailedToAddLink';
			if (!empty($powerplant->errors) && is_array($powerplant->errors)) {
				$object->errors = array_merge($object->errors, $powerplant->errors);
			} else {
				$object->errors[] = $object->error;
			}
			return -1;
		}
	}

	return 1;
}
