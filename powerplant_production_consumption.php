<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *  \file       powerplant_production_consumption.php
 *  \ingroup    powerplantpv
 *  \brief      Production/consumption index readings tab on PowerPlant cards
 */

// Load Dolibarr environment
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
/**
 * The main.inc.php has been included so the following variable are now defined:
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */
include_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/powerplantpv/class/powerplant.class.php');
dol_include_once('/powerplantpv/class/powerplantpvindexreading.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_powerplant.lib.php');

$langs->loadLangs(array('powerplantpv@powerplantpv', 'other', 'interventions', 'users'));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');
$socid = GETPOSTINT('socid');
$token = GETPOST('token', 'alphanohtml');
$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page == -1) {
	$page = 0;
}
$offset = $limit * $page;

$object = new PowerPlant($db);
$extrafields = new ExtraFields($db);
$hookmanager->initHooks(array($object->element.'productionconsumption', 'globalcard'));
$extrafields->fetch_name_optionals_label($object->table_element);

include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

$permissiontoread = powerplantpvUserHasRightPath($user, array('powerplantpv', 'powerplant', 'read'))
	|| powerplantpvUserHasMaintenanceRight($user, 'read');
$permissiontoadd = powerplantpvUserHasRightPath($user, array('powerplantpv', 'powerplant', 'write'))
	|| powerplantpvUserHasMaintenanceRight($user, 'write');

if ($user->socid > 0) {
	$socid = $user->socid;
}
$isdraft = (($object->status == $object::STATUS_DRAFT) ? 1 : 0);
restrictedArea($user, $object->module, $object, $object->table_element, $object->element, 'fk_soc', 'rowid', $isdraft);
if (!isModEnabled('powerplantpv')) {
	accessforbidden();
}
if (!$permissiontoread || empty($object->id)) {
	accessforbidden();
}

/*
 * Actions
 */

$parameters = array('id' => $id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	powerplantHandleSetLabelAction($object, $action, $permissiontoadd, $user);
	powerplantHandleSetThirdpartyAction($object, $action, $permissiontoadd, $user);

	if ($cancel) {
		$action = '';
	}

	if ($action === 'add_reading') {
		if (!$permissiontoadd) {
			accessforbidden();
		}
		if (!powerplantpvIndexReadingSubmittedTokenValid($token)) {
			accessforbidden('Invalid CSRF token');
		}

		$typeId = GETPOSTINT('fk_index_type');
		$typeRow = powerplantpvIndexReadingFetchTypeById($typeId);
		$readingDate = powerplantpvIndexReadingGetSubmittedDate('reading_date');
		$valueRaw = trim((string) GETPOST('reading_value', 'restricthtml'));
		$unit = trim((string) GETPOST('unit', 'alphanohtml'));
		$meterRef = trim((string) GETPOST('meter_ref', 'alphanohtml'));
		$comment = trim((string) GETPOST('comment', 'restricthtml'));

		$error = 0;
		if (empty($typeRow)) {
			setEventMessages($langs->trans('PowerPlantPVIndexReadingTypeRequired'), null, 'errors');
			$error++;
		}
		if ($readingDate <= 0) {
			setEventMessages($langs->trans('PowerPlantPVIndexReadingDateRequired'), null, 'errors');
			$error++;
		}
		if ($valueRaw === '') {
			setEventMessages($langs->trans('PowerPlantPVIndexReadingValueRequired'), null, 'errors');
			$error++;
		}

		if (!$error) {
			$reading = new PowerPlantPVIndexReading($db);
			$reading->entity = !empty($object->entity) ? (int) $object->entity : (int) $conf->entity;
			$reading->fk_powerplant = (int) $object->id;
			$reading->fk_fichinter_source = null;
			$reading->fk_report = null;
			$reading->fk_report_powerplant = 0;
			$reading->fk_report_equipment = 0;
			$reading->fk_index_type = (int) $typeRow['rowid'];
			$reading->reading_type_code = (string) $typeRow['code'];
			$reading->reading_date = $db->idate($readingDate);
			$reading->value = (float) price2num($valueRaw);
			$reading->unit = $unit !== '' ? $unit : (!empty($typeRow['default_unit']) ? (string) $typeRow['default_unit'] : 'kWh');
			$reading->meter_ref = $meterRef;
			$reading->source_type = PowerPlantPVIndexReading::SOURCE_MANUAL;
			$reading->comment = $comment;
			$reading->active = 1;
			$result = $reading->create($user, 1);
			if ($result <= 0) {
				setEventMessages($reading->error, $reading->errors, 'errors');
				$action = 'create_reading';
			} else {
				setEventMessages($langs->trans('PowerPlantPVIndexReadingSaved'), null, 'mesgs');
				header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $object->id);
				exit;
			}
		} else {
			$action = 'create_reading';
		}
	}
}

$allowedSorts = array(
	'reading_date' => 't.reading_date',
	'type' => 'it.label',
	'value' => 't.value',
	'unit' => 't.unit',
	'source_type' => 't.source_type',
	'user' => 'u.lastname',
	'rowid' => 't.rowid',
);
if (empty($sortfield) || !isset($allowedSorts[$sortfield])) {
	$sortfield = 'reading_date';
}
if (!in_array(strtoupper($sortorder), array('ASC', 'DESC'), true)) {
	$sortorder = 'DESC';
}

$param = '&id='.(int) $object->id;

$where = " WHERE t.fk_powerplant = ".((int) $object->id);
$where .= " AND t.active = 1";
$where .= " AND t.entity IN (".$db->sanitize(getEntity('powerplant')).")";

$sql = "SELECT COUNT(t.rowid) as nb";
$sql .= " FROM ".$db->prefix()."powerplantpv_index_reading as t";
$sql .= $where;
$resql = $db->query($sql);
$nbtotalofrecords = 0;
if ($resql) {
	$objcount = $db->fetch_object($resql);
	$nbtotalofrecords = is_object($objcount) ? (int) $objcount->nb : 0;
	$db->free($resql);
}

$sql = "SELECT t.rowid, t.fk_powerplant, t.fk_fichinter_source, t.fk_report, t.fk_report_powerplant, t.fk_report_equipment, t.reading_type_code";
$sql .= ", t.reading_date, t.value, t.unit, t.meter_ref, t.source_type, t.comment, t.fk_user_creat";
$sql .= ", it.label as type_label, it.label_en as type_label_en";
$sql .= ", u.login, u.lastname, u.firstname";
$sql .= ", fi.ref as intervention_ref";
$sql .= " FROM ".$db->prefix()."powerplantpv_index_reading as t";
$sql .= " LEFT JOIN ".$db->prefix()."c_powerplantpv_index_type as it ON it.rowid = t.fk_index_type";
$sql .= " LEFT JOIN ".$db->prefix()."user as u ON u.rowid = t.fk_user_creat";
$sql .= " LEFT JOIN ".$db->prefix()."fichinter as fi ON fi.rowid = t.fk_fichinter_source";
$sql .= $where;
$sql .= " ORDER BY ".$allowedSorts[$sortfield]." ".strtoupper($sortorder).", t.rowid DESC";
$sql .= $db->plimit($limit + 1, $offset);
$resql = $db->query($sql);
$num = $resql ? $db->num_rows($resql) : 0;

/*
 * View
 */

$form = new Form($db);
$readingHelper = new PowerPlantPVIndexReading($db);
$typeOptions = powerplantpvIndexReadingFetchTypeOptions();
$defaultTypeId = powerplantpvIndexReadingDefaultTypeId($typeOptions);
$title = $langs->trans('PowerPlant').' - '.$langs->trans('PowerPlantPVProductionConsumption');

llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-powerplantpv page-card_production_consumption');

$head = powerplantPrepareHead($object);
print dol_get_fiche_head($head, 'production_consumption', $langs->trans('PowerPlant'), -1, $object->picto);

$linkback = powerplantGetBackToListLink($object, $socid);
$morehtmlref = powerplantBuildBannerMoreHtml($object, $permissiontoadd, $action);
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';
print '<tr>';
print '<td class="titlefield">'.$langs->trans('PowerPlantPVIndexReadings').'</td>';
print '<td>'.((int) $nbtotalofrecords).'</td>';
print '</tr>';
print '</table>';
print '</div>';

print dol_get_fiche_end();

print '<div class="tabsAction">';
print dolGetButtonAction($langs->trans('PowerPlantPVReadProductionIndex'), '', 'default', $_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=create_reading', '', $permissiontoadd);
print '</div>';

if ($action === 'create_reading' && $permissiontoadd) {
	$submittedTypeId = GETPOSTINT('fk_index_type') > 0 ? GETPOSTINT('fk_index_type') : $defaultTypeId;
	$submittedDate = powerplantpvIndexReadingGetSubmittedDate('reading_date');
	$formDate = $submittedDate > 0 ? $submittedDate : dol_now();
	$submittedUnit = GETPOST('unit', 'alphanohtml') ? GETPOST('unit', 'alphanohtml') : 'kWh';
	print load_fiche_titre($langs->trans('PowerPlantPVNewIndexReading'), '', 'fa-tachometer-alt');
	print '<form method="POST" id="powerplantpv_index_reading_form" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="id" value="'.((int) $object->id).'">';
	print '<input type="hidden" name="action" value="add_reading">';
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefieldcreate">'.$langs->trans('PowerPlantPVReadingDate').'</td><td>'.$form->selectDate($formDate, 'reading_date', 0, 0, 1, 'powerplantpv_index_reading_form', 1, 1).'</td></tr>';
	print '<tr><td class="titlefieldcreate">'.$langs->trans('PowerPlantPVReadingType').'</td><td>'.$form->selectarray('fk_index_type', $typeOptions, $submittedTypeId, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td></tr>';
	if (function_exists('ajax_combobox')) {
		print ajax_combobox('fk_index_type');
	}
	print '<tr><td class="titlefieldcreate">'.$langs->trans('PowerPlantPVReadingValue').'</td><td><input type="text" class="flat maxwidth150 right" name="reading_value" value="'.dol_escape_htmltag(GETPOST('reading_value', 'restricthtml')).'"></td></tr>';
	print '<tr><td>'.$langs->trans('PowerPlantPVReadingUnit').'</td><td><input type="text" class="flat maxwidth100" name="unit" value="'.dol_escape_htmltag($submittedUnit).'"></td></tr>';
	print '<tr><td>'.$langs->trans('PowerPlantPVMeterRef').'</td><td><input type="text" class="flat minwidth200 maxwidth500" name="meter_ref" value="'.dol_escape_htmltag(GETPOST('meter_ref', 'alphanohtml')).'"></td></tr>';
	print '<tr><td>'.$langs->trans('Comment').'</td><td><textarea class="flat minwidth500 widthcentpercentminusxx" rows="3" name="comment">'.dol_escape_htmltag(GETPOST('comment', 'restricthtml')).'</textarea></td></tr>';
	print '</table>';
	print '<div class="center">';
	print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
	print ' <input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'">';
	print '</div>';
	print '</form>';
	print '<br>';
}

$morehtmlright = '';
print_barre_liste($langs->trans('PowerPlantPVIndexReadings'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'fa-tachometer-alt', 0, $morehtmlright, '', $limit, 0, 0, 1);
print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste centpercent">';
print '<tr class="liste_titre">';
print_liste_field_titre('PowerPlantPVReadingDate', $_SERVER['PHP_SELF'], 'reading_date', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('PowerPlantPVReadingType', $_SERVER['PHP_SELF'], 'type', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('PowerPlantPVReadingValue', $_SERVER['PHP_SELF'], 'value', '', $param, 'class="right"', $sortfield, $sortorder);
print_liste_field_titre('PowerPlantPVReadingUnit', $_SERVER['PHP_SELF'], 'unit', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('PowerPlantPVReadingDelta', $_SERVER['PHP_SELF'], '', '', $param, 'class="right"', $sortfield, $sortorder);
print_liste_field_titre('UserAuthor', $_SERVER['PHP_SELF'], 'user', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('PowerPlantPVReadingSource', $_SERVER['PHP_SELF'], 'source_type', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('PowerPlantPVSourceIntervention', $_SERVER['PHP_SELF'], '', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('PowerPlantPVSourceReportLink', $_SERVER['PHP_SELF'], '', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Comment', $_SERVER['PHP_SELF'], '', '', $param, '', $sortfield, $sortorder);
print '</tr>';

if ($resql) {
	$i = 0;
	while ($i < min($num, $limit) && is_object($obj = $db->fetch_object($resql))) {
		$typeLabel = powerplantpvIndexReadingLocalizedTypeLabel($obj);
		$userLabel = powerplantpvIndexReadingUserLabel($obj);
		$sourceLabel = $langs->trans((string) $obj->source_type === PowerPlantPVIndexReading::SOURCE_REPORT ? 'PowerPlantPVSourceReport' : 'PowerPlantPVSourceManual');
		$previous = $readingHelper->fetchPreviousValue((int) $obj->fk_powerplant, (string) $obj->reading_type_code, (string) $obj->reading_date, (int) $obj->rowid);
		$delta = is_array($previous) ? (float) $obj->value - (float) $previous['value'] : null;

		print '<tr class="oddeven">';
		print '<td class="nowrap">'.dol_print_date($db->jdate((string) $obj->reading_date), 'day').'</td>';
		print '<td>'.dol_escape_htmltag($typeLabel).'</td>';
		print '<td class="right">'.price($obj->value).'</td>';
		print '<td>'.dol_escape_htmltag((string) $obj->unit).'</td>';
		print '<td class="right">'.($delta !== null ? price($delta) : '<span class="opacitymedium">-</span>').'</td>';
		print '<td>'.dol_escape_htmltag($userLabel).'</td>';
		print '<td>'.dol_escape_htmltag($sourceLabel).'</td>';
		print '<td>'.powerplantpvIndexReadingInterventionLink($obj).'</td>';
		print '<td>'.powerplantpvIndexReadingReportLink($obj).'</td>';
		print '<td>'.dol_htmlentitiesbr((string) $obj->comment).'</td>';
		print '</tr>';
		$i++;
	}
	$db->free($resql);
}

if ($nbtotalofrecords === 0) {
	print '<tr class="oddeven"><td colspan="10"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table>';
print '</div>';

llxFooter();
$db->close();

/**
 * Check submitted token.
 *
 * @param	string	$token	Submitted token
 * @return	bool			True when accepted
 */
function powerplantpvIndexReadingSubmittedTokenValid($token)
{
	if ($token === '') {
		return false;
	}
	if (function_exists('dol_verifyToken')) {
		return (bool) dol_verifyToken($token);
	}
	$valid = false;
	if (function_exists('currentToken')) {
		$valid = hash_equals((string) currentToken(), (string) $token);
	}
	if (!$valid && !empty($_SESSION['newtoken'])) {
		$valid = hash_equals((string) $_SESSION['newtoken'], (string) $token);
	}

	return $valid || !function_exists('currentToken');
}

/**
 * Return active index type options.
 *
 * @return	array<int,string>	Options indexed by dictionary rowid
 */
function powerplantpvIndexReadingFetchTypeOptions()
{
	global $db, $langs;

	$sql = "SELECT rowid, code, label, label_en, default_unit";
	$sql .= " FROM ".$db->prefix()."c_powerplantpv_index_type";
	$sql .= " WHERE active = 1";
	$sql .= " AND entity IN (".$db->sanitize(getEntity('c_powerplantpv_index_type')).")";
	$sql .= " ORDER BY position ASC, label ASC, rowid ASC";

	$options = array();
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__METHOD__.' index type lookup failed: '.$db->lasterror(), LOG_WARNING);
		return $options;
	}
	while (is_object($obj = $db->fetch_object($resql))) {
		$label = ($langs->defaultlang === 'en_US' && !empty($obj->label_en)) ? (string) $obj->label_en : (string) $obj->label;
		$options[(int) $obj->rowid] = trim((string) $obj->code.' - '.$label, " -\t\n\r\0\x0B");
	}
	$db->free($resql);

	return $options;
}

/**
 * Return the default type id.
 *
 * @param	array<int,string>	$options	Available options
 * @return	int								Default rowid
 */
function powerplantpvIndexReadingDefaultTypeId($options)
{
	foreach ($options as $id => $label) {
		if (strpos((string) $label, 'PRODUCTION_INDEX') === 0) {
			return (int) $id;
		}
	}
	foreach ($options as $id => $label) {
		return (int) $id;
	}

	return 0;
}

/**
 * Fetch one active index type row.
 *
 * @param	int	$typeId	Type rowid
 * @return	array<string,mixed>	Type row or empty array
 */
function powerplantpvIndexReadingFetchTypeById($typeId)
{
	global $db;

	$typeId = (int) $typeId;
	if ($typeId <= 0) {
		return array();
	}

	$sql = "SELECT rowid, code, label, label_en, default_unit";
	$sql .= " FROM ".$db->prefix()."c_powerplantpv_index_type";
	$sql .= " WHERE rowid = ".$typeId;
	$sql .= " AND active = 1";
	$sql .= " AND entity IN (".$db->sanitize(getEntity('c_powerplantpv_index_type')).")";

	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__METHOD__.' index type lookup failed: '.$db->lasterror(), LOG_WARNING);
		return array();
	}
	$obj = $db->fetch_object($resql);
	$db->free($resql);
	if (!is_object($obj)) {
		return array();
	}

	return array(
		'rowid' => (int) $obj->rowid,
		'code' => (string) $obj->code,
		'label' => (string) $obj->label,
		'label_en' => (string) $obj->label_en,
		'default_unit' => (string) $obj->default_unit,
	);
}

/**
 * Return a submitted date built with Dolibarr selectDate fields.
 *
 * @param	string	$prefix	Field prefix
 * @return	int				Timestamp, 0 when incomplete
 */
function powerplantpvIndexReadingGetSubmittedDate($prefix)
{
	$day = GETPOSTINT($prefix.'day');
	$month = GETPOSTINT($prefix.'month');
	$year = GETPOSTINT($prefix.'year');
	if ($day <= 0 || $month <= 0 || $year <= 0) {
		return 0;
	}

	return dol_mktime(0, 0, 0, $month, $day, $year);
}

/**
 * Return localized type label for a result row.
 *
 * @param	stdClass	$obj	Result row
 * @return	string			Label
 */
function powerplantpvIndexReadingLocalizedTypeLabel($obj)
{
	global $langs;

	$label = !empty($obj->type_label) ? (string) $obj->type_label : (string) $obj->reading_type_code;
	if ($langs->defaultlang === 'en_US' && !empty($obj->type_label_en)) {
		$label = (string) $obj->type_label_en;
	}

	return $label;
}

/**
 * Return a readable user label from a result row.
 *
 * @param	stdClass	$obj	Result row
 * @return	string			User label
 */
function powerplantpvIndexReadingUserLabel($obj)
{
	$name = trim((string) $obj->firstname.' '.(string) $obj->lastname);
	if ($name === '') {
		$name = (string) $obj->login;
	}

	return $name;
}

/**
 * Return intervention link for a result row.
 *
 * @param	stdClass	$obj	Result row
 * @return	string			HTML link
 */
function powerplantpvIndexReadingInterventionLink($obj)
{
	global $langs;

	if (empty($obj->fk_fichinter_source)) {
		return '<span class="opacitymedium">-</span>';
	}
	$label = !empty($obj->intervention_ref) ? (string) $obj->intervention_ref : $langs->trans('Intervention').' #'.((int) $obj->fk_fichinter_source);

	return '<a href="'.DOL_URL_ROOT.'/fichinter/card.php?id='.((int) $obj->fk_fichinter_source).'">'.dol_escape_htmltag($label).'</a>';
}

/**
 * Return report link for a result row.
 *
 * @param	stdClass	$obj	Result row
 * @return	string			HTML link
 */
function powerplantpvIndexReadingReportLink($obj)
{
	global $langs;

	if (empty($obj->fk_report) || empty($obj->fk_fichinter_source)) {
		return '<span class="opacitymedium">-</span>';
	}
	$label = $langs->trans('PowerPlantPVReport').' #'.((int) $obj->fk_report);

	return '<a href="'.dol_buildpath('/powerplantpv/maintenance_intervention_report.php', 1).'?id='.((int) $obj->fk_fichinter_source).'">'.dol_escape_htmltag($label).'</a>';
}
