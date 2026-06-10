<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		attestation_list.php
 * \ingroup		powerplantpv
 * \brief		Native list page for attestations.
 */

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
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

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
if (isModEnabled('project')) {
	require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
}
dol_include_once('/powerplantpv/class/powerplant.class.php');
dol_include_once('/powerplantpv/class/powerplantpvattestation.class.php');
dol_include_once('/powerplantpv/class/powerplantpvattestationtypes.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_attestation.lib.php');

$langs->loadLangs(array('powerplantpv@powerplantpv', 'companies', 'projects', 'other'));

if (!isModEnabled('powerplantpv') || !getDolGlobalInt('POWERPLANTPV_ATTESTATION_ENABLE', 1)) {
	dol_syslog('PowerPlantPV attestation list forbidden: module or attestation feature disabled', LOG_WARNING);
	accessforbidden();
}

if (!class_exists('PowerPlantPVAttestation') || !class_exists('PowerPlantPVAttestationTypes') || !function_exists('powerplantpvAttestationUserHasRight')) {
	dol_syslog('PowerPlantPV attestation list unavailable: missing class or permission helper', LOG_ERR);
	llxHeader('', $langs->trans('Attestations'), '', '', 0, 0, '', '', '', 'mod-powerplantpv page-attestation-list');
	print '<div class="error">'.$langs->trans('AttestationInstallationIncomplete').'</div>';
	llxFooter();
	$db->close();
	exit;
}

$permissiontoread = powerplantpvAttestationUserHasRight($user, 'read');
$permissiontoadd = powerplantpvAttestationUserHasRight($user, 'write');
$permissiontodelete = powerplantpvAttestationUserHasRight($user, 'delete');
if (!$permissiontoread) {
	dol_syslog('PowerPlantPV attestation list forbidden: missing read right for user '.$user->id, LOG_WARNING);
	accessforbidden();
}

if (function_exists('powerplantpvAttestationGetInstallationIssues')) {
	$attestationInstallationIssues = powerplantpvAttestationGetInstallationIssues();
	if (!empty($attestationInstallationIssues['tables']) || !empty($attestationInstallationIssues['columns'])) {
		dol_syslog(
			'PowerPlantPV attestation list unavailable: missing tables '.implode(', ', $attestationInstallationIssues['tables']).' columns '.implode(', ', $attestationInstallationIssues['columns']),
			LOG_ERR
		);
		llxHeader('', $langs->trans('Attestations'), '', '', 0, 0, '', '', '', 'mod-powerplantpv page-attestation-list');
		powerplantpvAttestationPrintInstallationWarnings();
		llxFooter();
		$db->close();
		exit;
	}
}

$object = new PowerPlantPVAttestation($db);
$form = new Form($db);

$action = GETPOST('action', 'aZ09') ? GETPOST('action', 'aZ09') : 'list';
$massaction = GETPOST('massaction', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'alpha');
$toselect = GETPOST('toselect', 'array:int');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : getDolDefaultContextPage(__FILE__);
$optioncss = GETPOST('optioncss', 'aZ');

$search_all = trim(GETPOST('search_all', 'alphanohtml'));
$search_ref = trim(GETPOST('search_ref', 'alphanohtml'));
$search_type = GETPOST('search_type', 'alphanohtml');
$search_powerplant = trim(GETPOST('search_powerplant', 'alphanohtml'));
$search_soc = trim(GETPOST('search_soc', 'alphanohtml'));
$search_project = trim(GETPOST('search_project', 'alphanohtml'));
$search_status = GETPOSTISSET('search_status') ? GETPOST('search_status', 'int') : '';
$search_signed = GETPOSTISSET('search_signed') ? GETPOST('search_signed', 'int') : '';
if ($search_status == '-1') {
	$search_status = '';
}
if ($search_signed == '-1') {
	$search_signed = '';
}
$search_date_attestation_start = dol_mktime(0, 0, 0, GETPOSTINT('search_date_attestation_startmonth'), GETPOSTINT('search_date_attestation_startday'), GETPOSTINT('search_date_attestation_startyear'));
$search_date_attestation_end = dol_mktime(23, 59, 59, GETPOSTINT('search_date_attestation_endmonth'), GETPOSTINT('search_date_attestation_endday'), GETPOSTINT('search_date_attestation_endyear'));
$search_date_signature_start = dol_mktime(0, 0, 0, GETPOSTINT('search_date_signature_startmonth'), GETPOSTINT('search_date_signature_startday'), GETPOSTINT('search_date_signature_startyear'));
$search_date_signature_end = dol_mktime(23, 59, 59, GETPOSTINT('search_date_signature_endmonth'), GETPOSTINT('search_date_signature_endday'), GETPOSTINT('search_date_signature_endyear'));
$search_entity = GETPOST('search_entity', 'array:int');

$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$page = 0;
}
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

if (!$sortfield) {
	$sortfield = 't.tms';
}
if (!$sortorder) {
	$sortorder = 'DESC';
}

$entityScope = getEntity('attestation');
$availableEntities = array();
foreach (explode(',', $entityScope) as $entityId) {
	$entityId = (int) trim($entityId);
	if ($entityId > 0) {
		$availableEntities[$entityId] = (string) $entityId;
	}
}
$showEnvironment = count($availableEntities) > 1;

$arrayfields = array(
	't.ref' => array('label' => 'Ref', 'checked' => 1, 'enabled' => 1, 'position' => 10),
	't.type_code' => array('label' => 'AttestationType', 'checked' => 1, 'enabled' => 1, 'position' => 20),
	'p.ref' => array('label' => 'PowerPlant', 'checked' => 1, 'enabled' => 1, 'position' => 30),
	's.nom' => array('label' => 'ThirdParty', 'checked' => 1, 'enabled' => 1, 'position' => 40),
	'pr.ref' => array('label' => 'Project', 'checked' => (int) isModEnabled('project'), 'enabled' => (int) isModEnabled('project'), 'position' => 50),
	't.date_attestation' => array('label' => 'AttestationDate', 'checked' => 1, 'enabled' => 1, 'position' => 60, 'csslist' => 'center'),
	't.status' => array('label' => 'Status', 'checked' => 1, 'enabled' => 1, 'position' => 70, 'csslist' => 'center'),
	't.date_signature' => array('label' => 'AttestationSigned', 'checked' => 1, 'enabled' => 1, 'position' => 80, 'csslist' => 'center'),
	't.entity' => array('label' => 'Environment', 'checked' => ($showEnvironment ? 1 : 0), 'enabled' => ($showEnvironment ? 1 : 0), 'position' => 90, 'csslist' => 'center'),
	't.date_creation' => array('label' => 'DateCreation', 'checked' => 0, 'enabled' => 1, 'position' => 100, 'csslist' => 'center'),
	't.tms' => array('label' => 'DateModification', 'checked' => 0, 'enabled' => 1, 'position' => 110, 'csslist' => 'center'),
);
$arrayfields = dol_sort_array($arrayfields, 'position');
$statusFilterOptions = array();
foreach ($object->fields['status']['arrayofkeyval'] as $statusKey => $statusLabel) {
	$statusFilterOptions[$statusKey] = $langs->trans($statusLabel);
}

$hookmanager->initHooks(array($contextpage, 'attestationlist'));

if ($cancel) {
	$action = 'list';
	$massaction = '';
}
if (!GETPOST('confirmmassaction', 'alpha') && $massaction !== 'predelete' && $massaction !== 'confirm_delete' && $massaction !== 'confirm_presend') {
	$massaction = '';
}

$parameters = array('arrayfields' => &$arrayfields);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
		$search_all = '';
		$search_ref = '';
		$search_type = '';
		$search_powerplant = '';
		$search_soc = '';
		$search_project = '';
		$search_status = '';
		$search_signed = '';
		$search_date_attestation_start = '';
		$search_date_attestation_end = '';
		$search_date_signature_start = '';
		$search_date_signature_end = '';
		$search_entity = array();
		$toselect = array();
	}
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
		|| GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha')) {
		$massaction = '';
	}

	if ($massaction === 'predelete' && empty($toselect)) {
		setEventMessages($langs->trans('NoRecordSelected'), null, 'warnings');
		$massaction = '';
	}

	if (($action === 'confirm_delete' || $massaction === 'confirm_delete') && $confirm === 'yes' && $permissiontodelete) {
		if (function_exists('checkToken') && !checkToken()) {
			accessforbidden('Bad token');
		}
		$error = 0;
		foreach ((array) $toselect as $selectedId) {
			$tmp = new PowerPlantPVAttestation($db);
			if ($tmp->fetch((int) $selectedId) > 0) {
				$result = $tmp->delete($user);
				if ($result < 0) {
					$error++;
					setEventMessages($tmp->error, $tmp->errors, 'errors');
				}
			}
		}
		if (!$error) {
			setEventMessages($langs->trans('RecordsDeleted'), null, 'mesgs');
			$massaction = '';
		}
	}
}

$param = '';
if ($contextpage && $contextpage !== $_SERVER['PHP_SELF']) {
	$param .= '&contextpage='.urlencode($contextpage);
}
if ($limit > 0 && $limit != $conf->liste_limit) {
	$param .= '&limit='.((int) $limit);
}
foreach (array('search_all', 'search_ref', 'search_type', 'search_powerplant', 'search_soc', 'search_project', 'search_status', 'search_signed') as $paramkey) {
	if ($$paramkey !== '' && $$paramkey !== null) {
		$param .= '&'.$paramkey.'='.urlencode((string) $$paramkey);
	}
}
foreach (array(
	'search_date_attestation_start' => $search_date_attestation_start,
	'search_date_attestation_end' => $search_date_attestation_end,
	'search_date_signature_start' => $search_date_signature_start,
	'search_date_signature_end' => $search_date_signature_end,
) as $datekey => $datevalue) {
	if (!empty($datevalue)) {
		$param .= '&'.$datekey.'month='.GETPOSTINT($datekey.'month');
		$param .= '&'.$datekey.'day='.GETPOSTINT($datekey.'day');
		$param .= '&'.$datekey.'year='.GETPOSTINT($datekey.'year');
	}
}
if (is_array($search_entity)) {
	foreach ($search_entity as $entityId) {
		if ((int) $entityId > 0) {
			$param .= '&search_entity[]='.(int) $entityId;
		}
	}
}

$fieldstosearchall = array(
	't.ref' => 'Ref',
	'p.ref' => 'PowerPlant',
	'p.label' => 'Label',
	's.nom' => 'ThirdParty',
	'pr.ref' => 'Project',
	'pr.title' => 'Project',
);

$sqlFrom = " FROM ".$db->prefix()."powerplantpv_attestation as t";
$sqlFrom .= " LEFT JOIN ".$db->prefix()."powerplantpv_powerplant as p ON p.rowid = t.fk_powerplant";
$sqlFrom .= " LEFT JOIN ".$db->prefix()."societe as s ON s.rowid = t.fk_soc";
$sqlFrom .= " LEFT JOIN ".$db->prefix()."projet as pr ON pr.rowid = t.fk_project";
$sqlWhere = " WHERE t.entity IN (".$entityScope.")";
if (!empty($user->socid)) {
	$sqlWhere .= " AND t.fk_soc = ".((int) $user->socid);
}
if ($search_all !== '') {
	$sqlWhere .= natural_search(array_keys($fieldstosearchall), $search_all);
}
if ($search_ref !== '') {
	$sqlWhere .= natural_search('t.ref', $search_ref);
}
if ($search_type !== '') {
	$sqlWhere .= " AND t.type_code = '".$db->escape($search_type)."'";
}
if ($search_powerplant !== '') {
	$sqlWhere .= natural_search(array('p.ref', 'p.label'), $search_powerplant);
}
if ($search_soc !== '') {
	$sqlWhere .= natural_search('s.nom', $search_soc);
}
if ($search_project !== '') {
	$sqlWhere .= natural_search(array('pr.ref', 'pr.title'), $search_project);
}
if ($search_status !== '') {
	$sqlWhere .= " AND t.status = ".((int) $search_status);
}
if ($search_signed !== '') {
	$sqlWhere .= ((int) $search_signed ? " AND t.date_signature IS NOT NULL" : " AND t.date_signature IS NULL");
}
if (!empty($search_date_attestation_start)) {
	$sqlWhere .= " AND t.date_attestation >= '".$db->idate($search_date_attestation_start)."'";
}
if (!empty($search_date_attestation_end)) {
	$sqlWhere .= " AND t.date_attestation <= '".$db->idate($search_date_attestation_end)."'";
}
if (!empty($search_date_signature_start)) {
	$sqlWhere .= " AND t.date_signature >= '".$db->idate($search_date_signature_start)."'";
}
if (!empty($search_date_signature_end)) {
	$sqlWhere .= " AND t.date_signature <= '".$db->idate($search_date_signature_end)."'";
}
if (!empty($search_entity) && is_array($search_entity)) {
	$entities = array();
	foreach ($search_entity as $entityId) {
		$entityId = (int) $entityId;
		if ($entityId > 0 && isset($availableEntities[$entityId])) {
			$entities[] = $entityId;
		}
	}
	if (!empty($entities)) {
		$sqlWhere .= " AND t.entity IN (".implode(',', $entities).")";
	}
}

$sqlCount = "SELECT COUNT(t.rowid) as nb".$sqlFrom.$sqlWhere;
$resqlCount = $db->query($sqlCount);
$num = 0;
if ($resqlCount) {
	$objCount = $db->fetch_object($resqlCount);
	$num = $objCount ? (int) $objCount->nb : 0;
	$db->free($resqlCount);
}
$nbtotalofrecords = $num;

$sql = "SELECT t.rowid, t.ref, t.entity, t.type_code, t.date_attestation, t.status, t.date_signature, t.date_creation, t.tms, t.fk_powerplant, t.fk_soc, t.fk_project,";
$sql .= " p.ref as powerplant_ref, p.label as powerplant_label, s.nom as thirdparty_name, pr.ref as project_ref, pr.title as project_title";
$sql .= $sqlFrom.$sqlWhere.$db->order($sortfield, $sortorder).$db->plimit($limit + 1, $offset);
$resql = $db->query($sql);

$arrayofmassactions = array();
if (!empty($permissiontodelete)) {
	$arrayofmassactions['predelete'] = img_picto('', 'delete', 'class="pictofixedwidth"').$langs->trans('Delete');
}
if (GETPOSTINT('nomassaction') || in_array($massaction, array('predelete'), true)) {
	$arrayofmassactions = array();
}
$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

/*
 * View
 */

llxHeader('', $langs->trans('Attestations'), '', '', 0, 0, '', '', '', 'mod-powerplantpv page-list page-attestation-list bodyforlist');

if ($massaction === 'predelete') {
	$formquestion = array(
		array('type' => 'hidden', 'name' => 'massaction', 'value' => 'confirm_delete'),
		array('type' => 'hidden', 'name' => 'confirmmassaction', 'value' => '1'),
	);
	foreach ((array) $toselect as $selectedId) {
		$formquestion[] = array('type' => 'hidden', 'name' => 'toselect[]', 'value' => (int) $selectedId);
	}
	$formconfirm = $form->formconfirm(
		$_SERVER['PHP_SELF'].'?'.$param,
		$langs->trans('ConfirmMassDelete'),
		$langs->trans('ConfirmMassDeleteQuestion', count((array) $toselect)),
		'confirm_delete',
		$formquestion,
		0,
		1
	);
	print $formconfirm;
}

print '<form method="POST" id="searchFormList" action="'.$_SERVER['PHP_SELF'].'">'."\n";
if ($optioncss != '') {
	print '<input type="hidden" name="optioncss" value="'.dol_escape_htmltag($optioncss).'">';
}
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
print '<input type="hidden" name="page" value="'.((int) $page).'">';
print '<input type="hidden" name="contextpage" value="'.dol_escape_htmltag($contextpage).'">';
print '<input type="hidden" name="page_y" value="">';

$newcardbutton = dolGetButtonTitle($langs->trans('New_Attestation'), '', 'fa fa-plus-circle', dol_buildpath('/powerplantpv/attestation_card.php', 1).'?action=create', '', $permissiontoadd);
print_barre_liste($langs->trans('Attestations'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'fa-file-signature', 0, $newcardbutton, '', $limit, 0, 0, 1);

if ($search_all) {
	$setupstring = '';
	foreach ($fieldstosearchall as $key => $val) {
		$fieldstosearchall[$key] = $langs->trans($val);
		$setupstring .= $key.'='.$val.';';
	}
	print '<div class="divsearchfieldfilter">'.$langs->trans('FilterOnInto', $search_all).implode(', ', $fieldstosearchall).'</div>';
}

$varpage = empty($contextpage) ? $_SERVER['PHP_SELF'] : $contextpage;
$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage, $conf->main_checkbox_left_column);
$selectedfields .= (count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal noborder liste listwithfilterbefore">'."\n";

print '<tr class="liste_titre_filter">';
if ($conf->main_checkbox_left_column) {
	print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons('left').'</td>';
}
if (!empty($arrayfields['t.ref']['checked'])) {
	print '<td class="liste_titre"><input type="text" class="flat maxwidth100" name="search_ref" value="'.dol_escape_htmltag($search_ref).'"></td>';
}
if (!empty($arrayfields['t.type_code']['checked'])) {
	print '<td class="liste_titre">'.$form->selectarray('search_type', PowerPlantPVAttestationTypes::getTypeLabels($langs), $search_type, 1, 0, 0, '', 0, 0, 0, '', 'flat maxwidth150').'</td>';
}
if (!empty($arrayfields['p.ref']['checked'])) {
	print '<td class="liste_titre"><input type="text" class="flat maxwidth150" name="search_powerplant" value="'.dol_escape_htmltag($search_powerplant).'"></td>';
}
if (!empty($arrayfields['s.nom']['checked'])) {
	print '<td class="liste_titre"><input type="text" class="flat maxwidth150" name="search_soc" value="'.dol_escape_htmltag($search_soc).'"></td>';
}
if (!empty($arrayfields['pr.ref']['checked'])) {
	print '<td class="liste_titre"><input type="text" class="flat maxwidth150" name="search_project" value="'.dol_escape_htmltag($search_project).'"></td>';
}
if (!empty($arrayfields['t.date_attestation']['checked'])) {
	print '<td class="liste_titre center">';
	print '<div class="nowrap">'.$form->selectDate($search_date_attestation_start ? $search_date_attestation_start : '', 'search_date_attestation_start', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From')).'</div>';
	print '<div class="nowrap">'.$form->selectDate($search_date_attestation_end ? $search_date_attestation_end : '', 'search_date_attestation_end', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to')).'</div>';
	print '</td>';
}
if (!empty($arrayfields['t.status']['checked'])) {
	print '<td class="liste_titre center">'.$form->selectarray('search_status', $statusFilterOptions, $search_status, 1, 0, 0, '', 0, 0, 0, '', 'flat maxwidth125').'</td>';
}
if (!empty($arrayfields['t.date_signature']['checked'])) {
	print '<td class="liste_titre center">';
	print $form->selectyesno('search_signed', $search_signed, 1, false, 1);
	print '<div class="nowrap">'.$form->selectDate($search_date_signature_start ? $search_date_signature_start : '', 'search_date_signature_start', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From')).'</div>';
	print '<div class="nowrap">'.$form->selectDate($search_date_signature_end ? $search_date_signature_end : '', 'search_date_signature_end', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to')).'</div>';
	print '</td>';
}
if (!empty($arrayfields['t.entity']['checked'])) {
	print '<td class="liste_titre center">'.$form->multiselectarray('search_entity', $availableEntities, $search_entity, 0, 0, 'maxwidth150', 0, '100%').'</td>';
}
if (!empty($arrayfields['t.date_creation']['checked'])) {
	print '<td class="liste_titre center"></td>';
}
if (!empty($arrayfields['t.tms']['checked'])) {
	print '<td class="liste_titre center"></td>';
}
if (!$conf->main_checkbox_left_column) {
	print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons().'</td>';
}
print '</tr>'."\n";

$totalarray = array('nbfield' => 0);
print '<tr class="liste_titre">';
if ($conf->main_checkbox_left_column) {
	print getTitleFieldOfList($selectedfields, 0, $_SERVER['PHP_SELF'], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ')."\n";
	$totalarray['nbfield']++;
}
foreach ($arrayfields as $key => $field) {
	if (empty($field['checked'])) {
		continue;
	}
	$css = !empty($field['csslist']) ? $field['csslist'].' ' : '';
	print getTitleFieldOfList($field['label'], 0, $_SERVER['PHP_SELF'], $key, '', $param, ($css ? 'class="'.trim($css).'"' : ''), $sortfield, $sortorder, $css)."\n";
	$totalarray['nbfield']++;
}
if (!$conf->main_checkbox_left_column) {
	print getTitleFieldOfList($selectedfields, 0, $_SERVER['PHP_SELF'], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ')."\n";
	$totalarray['nbfield']++;
}
print '</tr>'."\n";

if ($resql) {
	$i = 0;
	while ($i < min($num, $limit) && ($obj = $db->fetch_object($resql))) {
		$i++;
		$tmp = new PowerPlantPVAttestation($db);
		$tmp->id = (int) $obj->rowid;
		$tmp->ref = (string) $obj->ref;
		$tmp->status = (int) $obj->status;

		print '<tr class="oddeven">';
		if ($conf->main_checkbox_left_column) {
			print '<td class="nowrap center"><input id="cb'.((int) $obj->rowid).'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.((int) $obj->rowid).'"></td>';
		}
		if (!empty($arrayfields['t.ref']['checked'])) {
			print '<td>'.$tmp->getNomUrl(1).'</td>';
		}
		if (!empty($arrayfields['t.type_code']['checked'])) {
			$type = PowerPlantPVAttestationTypes::getType($obj->type_code);
			print '<td>'.dol_escape_htmltag(!empty($type['label']) ? $langs->trans($type['label']) : $obj->type_code).'</td>';
		}
		if (!empty($arrayfields['p.ref']['checked'])) {
			$powerplantHtml = '';
			if (!empty($obj->fk_powerplant)) {
				$powerplant = new PowerPlant($db);
				$powerplant->id = (int) $obj->fk_powerplant;
				$powerplant->ref = (string) $obj->powerplant_ref;
				$powerplant->label = (string) $obj->powerplant_label;
				$powerplantHtml = $powerplant->getNomUrl(1);
			}
			print '<td>'.$powerplantHtml.'</td>';
		}
		if (!empty($arrayfields['s.nom']['checked'])) {
			$thirdpartyHtml = '';
			if (!empty($obj->fk_soc)) {
				$soc = new Societe($db);
				$soc->id = (int) $obj->fk_soc;
				$soc->name = (string) $obj->thirdparty_name;
				$thirdpartyHtml = $soc->getNomUrl(1);
			}
			print '<td>'.$thirdpartyHtml.'</td>';
		}
		if (!empty($arrayfields['pr.ref']['checked'])) {
			$projectHtml = '';
			if (!empty($obj->fk_project) && class_exists('Project')) {
				$project = new Project($db);
				$project->id = (int) $obj->fk_project;
				$project->ref = (string) $obj->project_ref;
				$project->title = (string) $obj->project_title;
				$projectHtml = $project->getNomUrl(1);
			}
			print '<td>'.$projectHtml.'</td>';
		}
		if (!empty($arrayfields['t.date_attestation']['checked'])) {
			print '<td class="center">'.(!empty($obj->date_attestation) ? dol_print_date($db->jdate($obj->date_attestation), 'day') : '').'</td>';
		}
		if (!empty($arrayfields['t.status']['checked'])) {
			print '<td class="center">'.$tmp->getLibStatut(5).'</td>';
		}
		if (!empty($arrayfields['t.date_signature']['checked'])) {
			print '<td class="center">'.(!empty($obj->date_signature) ? dol_print_date($db->jdate($obj->date_signature), 'dayhour') : '').'</td>';
		}
		if (!empty($arrayfields['t.entity']['checked'])) {
			print '<td class="center">'.((int) $obj->entity).'</td>';
		}
		if (!empty($arrayfields['t.date_creation']['checked'])) {
			print '<td class="center">'.(!empty($obj->date_creation) ? dol_print_date($db->jdate($obj->date_creation), 'dayhour') : '').'</td>';
		}
		if (!empty($arrayfields['t.tms']['checked'])) {
			print '<td class="center">'.(!empty($obj->tms) ? dol_print_date($db->jdate($obj->tms), 'dayhour') : '').'</td>';
		}
		if (!$conf->main_checkbox_left_column) {
			print '<td class="nowrap center"><input id="cb'.((int) $obj->rowid).'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.((int) $obj->rowid).'"></td>';
		}
		print '</tr>'."\n";
	}

	if ($i === 0) {
		print '<tr class="oddeven"><td colspan="'.((int) $totalarray['nbfield']).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}
	$db->free($resql);
} else {
	print '<tr class="oddeven"><td colspan="'.((int) $totalarray['nbfield']).'"><span class="error">'.dol_escape_htmltag($db->lasterror()).'</span></td></tr>';
}

print '</table>';
print '</div>';
print '</form>';

llxFooter();
$db->close();
