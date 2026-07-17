<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		admin/maintenance_report_template_sections.php
 * \ingroup		powerplantpv
 * \brief		Report template section administration.
 */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
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

require_once __DIR__.'/maintenance_report_common.php';
dol_include_once('/powerplantpv/class/powerplantpvreporttemplate.class.php');
dol_include_once('/powerplantpv/class/powerplantpvreporttemplatesection.class.php');

$langs->loadLangs(array('admin', 'powerplantpv@powerplantpv', 'other'));
powerplantpvReportTemplateAdminAccess();

$form = new Form($db);
$object = new PowerPlantPVReportTemplateSection($db);
$template = new PowerPlantPVReportTemplate($db);

$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');
$confirm = GETPOST('confirm', 'alpha');
$templateId = GETPOSTINT('fk_report_template') ?: powerplantpvDefaultReportTemplateId();
$search = trim(GETPOST('search', 'alphanohtml'));
$search_active = GETPOSTISSET('search_active') ? GETPOST('search_active', 'int') : '';
$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 'position';
$sortorder = GETPOST('sortorder', 'aZ09comma') ?: 'ASC';
$page = GETPOSTISSET('pageplusone') ? GETPOSTINT('pageplusone') - 1 : GETPOSTINT('page');
if ($page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$page = 0;
}
$limit = GETPOSTINT('limit') > 0 ? GETPOSTINT('limit') : $conf->liste_limit;
$offset = $limit * $page;

if (GETPOST('button_removefilter', 'alpha') || GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha')) {
	$search = '';
	$search_active = '';
}

if ($templateId > 0 && $template->fetch($templateId) <= 0) {
	$templateId = 0;
}

if (in_array($action, array('add', 'update', 'disable', 'delete', 'moveup', 'movedown'), true)) {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
}

if ($action === 'add' || $action === 'update') {
	if ($action === 'update') {
		if ($id <= 0 || $object->fetch($id) <= 0) {
			accessforbidden();
		}
	} else {
		$object = new PowerPlantPVReportTemplateSection($db);
	}
	$object->fk_report_template = $templateId;
	$object->code = strtoupper(trim(GETPOST('code', 'alphanohtml')));
	$object->label = trim(GETPOST('label', 'alphanohtml'));
	$object->label_en = trim(GETPOST('label_en', 'alphanohtml'));
	$object->description = GETPOST('description', 'restricthtml');
	$object->description_en = GETPOST('description_en', 'restricthtml');
	$object->scope_type = GETPOST('scope_type', 'alphanohtml');
	$object->equipment_type = GETPOST('equipment_type', 'alphanohtml');
	$object->repeat_mode = GETPOST('repeat_mode', 'alphanohtml');
	$object->is_required = GETPOSTINT('is_required');
	$object->visible_form = GETPOSTINT('visible_form');
	$object->visible_pdf = GETPOSTINT('visible_pdf');
	$object->active = GETPOSTINT('active');
	$object->position = GETPOSTINT('position');

	$result = ($action === 'add') ? $object->create($user, 0) : $object->update($user, 0);
	if ($result < 0) {
		setEventMessages($object->error, $object->errors, 'errors');
		$action = ($action === 'add') ? 'create' : 'edit';
	} else {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?fk_report_template='.(int) $templateId);
		exit;
	}
} elseif ($action === 'disable' && $id > 0) {
	if ($object->fetch($id) <= 0 || (int) $object->fk_report_template !== (int) $templateId) {
		accessforbidden();
	}
	$result = $object->disable($user);
	if ($result < 0) {
		setEventMessages($object->error, $object->errors, 'errors');
	} else {
		setEventMessages($langs->trans('PowerPlantPVRecordDisabled'), null, 'mesgs');
	}
	$action = '';
} elseif ($action === 'delete' && $confirm === 'yes' && $id > 0) {
	if ($object->fetch($id) <= 0 || (int) $object->fk_report_template !== (int) $templateId) {
		accessforbidden();
	}
	$result = $object->delete($user, 0);
	if ($result < 0) {
		setEventMessages($object->error, $object->errors, 'errors');
	} else {
		setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?fk_report_template='.(int) $templateId);
		exit;
	}
	$action = '';
} elseif (($action === 'moveup' || $action === 'movedown') && $id > 0) {
	$whereExtra = " AND fk_report_template = ".((int) $templateId);
	$result = powerplantpvReportTemplateMoveRow('powerplantpv_report_template_section', $id, $action === 'moveup' ? 'up' : 'down', $whereExtra);
	if ($result < 0) {
		setEventMessages($langs->trans('Error'), null, 'errors');
	}
	$action = '';
}

if ($action === 'edit' && $id > 0) {
	if ($object->fetch($id) <= 0 || (int) $object->fk_report_template !== (int) $templateId) {
		accessforbidden();
	}
}

$param = '&fk_report_template='.((int) $templateId);
if ($search !== '') {
	$param .= '&search='.urlencode($search);
}
if ($search_active !== '') {
	$param .= '&search_active='.urlencode((string) $search_active);
}
if ($limit != $conf->liste_limit) {
	$param .= '&limit='.((int) $limit);
}

$where = " WHERE t.entity = ".((int) $conf->entity);
$where .= " AND t.fk_report_template = ".((int) $templateId);
if ($search !== '') {
	$safeSearch = $db->escape($search);
	$where .= " AND (t.code LIKE '%".$safeSearch."%' OR t.label LIKE '%".$safeSearch."%')";
}
if ($search_active !== '') {
	$where .= " AND t.active = ".((int) $search_active);
}

$sql = "SELECT COUNT(*) as nb FROM ".$db->prefix()."powerplantpv_report_template_section as t".$where;
$resql = $db->query($sql);
$num = 0;
if ($resql) {
	$obj = $db->fetch_object($resql);
	$num = is_object($obj) ? (int) $obj->nb : 0;
	$db->free($resql);
}

$allowedSorts = array('code', 'label', 'scope_type', 'repeat_mode', 'active', 'position');
if (!in_array($sortfield, $allowedSorts, true)) {
	$sortfield = 'position';
}
if (!in_array(strtoupper($sortorder), array('ASC', 'DESC'), true)) {
	$sortorder = 'ASC';
}

$sql = "SELECT t.rowid, t.code, t.label, t.scope_type, t.equipment_type, t.repeat_mode, t.is_required, t.visible_form, t.visible_pdf, t.active, t.position";
$sql .= " FROM ".$db->prefix()."powerplantpv_report_template_section as t";
$sql .= $where;
$sql .= " ORDER BY t.".$db->sanitize($sortfield)." ".strtoupper($sortorder).", t.rowid ASC";
$sql .= $db->plimit($limit + 1, $offset);
$resql = $db->query($sql);

$title = $langs->trans('PowerPlantPVReportSections');
powerplantpvReportTemplateAdminHeader($title, 'maintenance_report_templates');

if ($action === 'delete' && $id > 0) {
	print $form->formconfirm(
		$_SERVER['PHP_SELF'].'?id='.(int) $id.'&fk_report_template='.(int) $templateId.'&token='.newToken(),
		$langs->trans('Delete'),
		$langs->trans('ConfirmDeleteObject'),
		'delete',
		'',
		0,
		1
	);
}

print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $num, 'fa-clipboard-list', 0, '', '', $limit);

print '<div class="inline-block valignmiddle marginrightonly">';
print $langs->trans('PowerPlantPVReportTemplate').' ';
print $form->selectarray('fk_report_template', powerplantpvReportTemplateOptions(0), $templateId, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300');
print '</div>';
print '<input type="submit" class="button smallpaddingimp" value="'.$langs->trans('Refresh').'">';

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste listwithfilterbefore centpercent">';
print '<tr class="liste_titre_filter">';
print '<td><input class="flat maxwidth150" type="text" name="search" value="'.dol_escape_htmltag($search).'"></td>';
print '<td></td>';
print '<td></td>';
print '<td></td>';
print '<td class="center">'.$form->selectarray('search_active', powerplantpvReportTemplateTranslateOptions(powerplantpvReportTemplateActiveOptions()), $search_active, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
print '<td></td>';
print '<td class="center">';
print '<input type="submit" class="button smallpaddingimp" name="button_search" value="'.$langs->trans('Search').'">';
print '<input type="submit" class="button smallpaddingimp" name="button_removefilter" value="'.$langs->trans('RemoveFilter').'">';
print '</td>';
print '</tr>';

print '<tr class="liste_titre">';
print_liste_field_titre('Code', $_SERVER['PHP_SELF'], 'code', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Label', $_SERVER['PHP_SELF'], 'label', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('PowerPlantPVReportScope', $_SERVER['PHP_SELF'], 'scope_type', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('PowerPlantPVRepeatMode', $_SERVER['PHP_SELF'], 'repeat_mode', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Status', $_SERVER['PHP_SELF'], 'active', '', $param, 'class="center"', $sortfield, $sortorder);
print_liste_field_titre('Position', $_SERVER['PHP_SELF'], 'position', '', $param, 'class="center"', $sortfield, $sortorder);
print '<th class="center">'.$langs->trans('Actions').'</th>';
print '</tr>';

$scopeOptions = powerplantpvReportTemplateTranslateOptions(powerplantpvReportTemplateScopeTypes());
$repeatOptions = powerplantpvReportTemplateTranslateOptions(powerplantpvReportTemplateRepeatModes());
if ($resql) {
	$i = 0;
	while ($i < min($num, $limit) && ($obj = $db->fetch_object($resql))) {
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag((string) $obj->code).'</td>';
		print '<td>'.dol_escape_htmltag((string) $obj->label).'</td>';
		print '<td>'.dol_escape_htmltag(isset($scopeOptions[$obj->scope_type]) ? $scopeOptions[$obj->scope_type] : (string) $obj->scope_type).'</td>';
		print '<td>'.dol_escape_htmltag(isset($repeatOptions[$obj->repeat_mode]) ? $repeatOptions[$obj->repeat_mode] : (string) $obj->repeat_mode).'</td>';
		print '<td class="center"><span class="badge '.(!empty($obj->active) ? 'badge-status4' : 'badge-status8').'">'.$langs->trans(!empty($obj->active) ? 'Enabled' : 'Disabled').'</span></td>';
		print '<td class="center">'.((int) $obj->position).'</td>';
		print '<td class="center nowraponall">';
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=edit&id='.(int) $obj->rowid.$param.'">'.img_edit($langs->trans('Modify')).'</a>';
		print ' <a href="'.$_SERVER['PHP_SELF'].'?action=moveup&id='.(int) $obj->rowid.'&token='.newToken().$param.'">'.img_picto($langs->trans('MoveUp'), 'uparrow').'</a>';
		print ' <a href="'.$_SERVER['PHP_SELF'].'?action=movedown&id='.(int) $obj->rowid.'&token='.newToken().$param.'">'.img_picto($langs->trans('MoveDown'), 'downarrow').'</a>';
		if (!empty($obj->active)) {
			print ' <a href="'.$_SERVER['PHP_SELF'].'?action=disable&id='.(int) $obj->rowid.'&token='.newToken().$param.'">'.img_picto($langs->trans('Disable'), 'switch_off').'</a>';
		}
		print ' <a href="'.$_SERVER['PHP_SELF'].'?action=delete&id='.(int) $obj->rowid.'&token='.newToken().$param.'">'.img_delete($langs->trans('Delete')).'</a>';
		print '</td>';
		print '</tr>';
		$i++;
	}
	$db->free($resql);
}
if (empty($num)) {
	powerplantpvPrintNoRecordFound(7);
}
print '</table>';
print '</div>';
print '</form>';

print '<br>';
print load_fiche_titre($langs->trans($action === 'edit' ? 'Modify' : 'New'), '', '');
print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="'.($action === 'edit' ? 'update' : 'add').'">';
print '<input type="hidden" name="fk_report_template" value="'.((int) $templateId).'">';
if ($action === 'edit') {
	print '<input type="hidden" name="id" value="'.((int) $object->id).'">';
}

print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefieldcreate">'.$langs->trans('Code').'</td><td><input class="flat maxwidth200" type="text" name="code" value="'.dol_escape_htmltag((string) $object->code).'"></td></tr>';
print '<tr><td class="titlefieldcreate">'.$langs->trans('Label').'</td><td><input class="flat minwidth300" type="text" name="label" value="'.dol_escape_htmltag((string) $object->label).'"></td></tr>';
print '<tr><td>'.$langs->trans('PowerPlantPVEnglishLabel').'</td><td><input class="flat minwidth300" type="text" name="label_en" value="'.dol_escape_htmltag((string) $object->label_en).'"></td></tr>';
print '<tr><td>'.$langs->trans('Description').'</td><td><textarea class="flat centpercent" name="description" rows="2">'.dol_escape_htmltag((string) $object->description).'</textarea></td></tr>';
print '<tr><td>'.$langs->trans('PowerPlantPVEnglishDescription').'</td><td><textarea class="flat centpercent" name="description_en" rows="2">'.dol_escape_htmltag((string) $object->description_en).'</textarea></td></tr>';
print '<tr><td class="titlefieldcreate">'.$langs->trans('PowerPlantPVReportScope').'</td><td>'.$form->selectarray('scope_type', $scopeOptions, $object->scope_type ?: 'intervention', 0, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
print '<tr><td>'.$langs->trans('PowerPlantPVEquipmentType').'</td><td>'.$form->selectarray('equipment_type', powerplantpvReportTemplateTranslateOptions(powerplantpvReportTemplateEquipmentTypes()), $object->equipment_type, 0, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
print '<tr><td class="titlefieldcreate">'.$langs->trans('PowerPlantPVRepeatMode').'</td><td>'.$form->selectarray('repeat_mode', $repeatOptions, $object->repeat_mode ?: 'once', 0, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
print '<tr><td>'.$langs->trans('Required').'</td><td>'.$form->selectyesno('is_required', (int) $object->is_required, 1).'</td></tr>';
print '<tr><td>'.$langs->trans('PowerPlantPVVisibleForm').'</td><td>'.$form->selectyesno('visible_form', isset($object->visible_form) ? (int) $object->visible_form : 1, 1).'</td></tr>';
print '<tr><td>'.$langs->trans('PowerPlantPVVisiblePdf').'</td><td>'.$form->selectyesno('visible_pdf', isset($object->visible_pdf) ? (int) $object->visible_pdf : 1, 1).'</td></tr>';
print '<tr><td>'.$langs->trans('Status').'</td><td>'.$form->selectarray('active', powerplantpvReportTemplateTranslateOptions(powerplantpvReportTemplateActiveOptions()), isset($object->active) ? (int) $object->active : 1, 0, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td></tr>';
print '<tr><td>'.$langs->trans('Position').'</td><td><input class="flat maxwidth75 right" type="number" name="position" value="'.((int) $object->position).'"></td></tr>';
print '</table>';
print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';

powerplantpvReportTemplateAdminFooter();
