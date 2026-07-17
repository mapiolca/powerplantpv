<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		admin/maintenance_report_template_fields.php
 * \ingroup		powerplantpv
 * \brief		Report template field and option administration.
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
dol_include_once('/powerplantpv/class/powerplantpvreporttemplatefield.class.php');
dol_include_once('/powerplantpv/class/powerplantpvreporttemplatefieldoption.class.php');

$langs->loadLangs(array('admin', 'powerplantpv@powerplantpv', 'other'));
powerplantpvReportTemplateAdminAccess();

$form = new Form($db);
$template = new PowerPlantPVReportTemplate($db);
$field = new PowerPlantPVReportTemplateField($db);
$option = new PowerPlantPVReportTemplateFieldOption($db);

$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');
$optionId = GETPOSTINT('option_id');
$confirm = GETPOST('confirm', 'alpha');
$templateId = GETPOSTINT('fk_report_template') ?: powerplantpvDefaultReportTemplateId();
$sectionFilter = GETPOSTINT('fk_report_template_section');
$selectedFieldId = GETPOSTINT('fk_report_template_field');
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
	$sectionFilter = 0;
}

if ($templateId > 0 && $template->fetch($templateId) <= 0) {
	$templateId = 0;
}

$mutatingActions = array('add', 'update', 'disable', 'delete', 'moveup', 'movedown', 'addoption', 'updateoption', 'disableoption', 'deleteoption', 'moveoptionup', 'moveoptiondown');
if (in_array($action, $mutatingActions, true)) {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
}

if ($action === 'add' || $action === 'update') {
	if ($action === 'update') {
		if ($id <= 0 || $field->fetch($id) <= 0 || (int) $field->fk_report_template !== (int) $templateId) {
			accessforbidden();
		}
	} else {
		$field = new PowerPlantPVReportTemplateField($db);
	}
	$field->fk_report_template = $templateId;
	$field->fk_report_template_section = GETPOSTINT('fk_report_template_section_form');
	$field->report_template_code = !empty($template->code) ? (string) $template->code : 'manual';
	$field->fk_report_section = 0;
	$field->fk_maintenance_service = GETPOSTINT('fk_maintenance_service');
	$field->code = strtoupper(trim(GETPOST('code', 'alphanohtml')));
	$field->label = trim(GETPOST('label', 'alphanohtml'));
	$field->label_en = trim(GETPOST('label_en', 'alphanohtml'));
	$field->description = GETPOST('description', 'restricthtml');
	$field->description_en = GETPOST('description_en', 'restricthtml');
	$field->field_type = GETPOST('field_type', 'alphanohtml');
	$field->scope_type = GETPOST('scope_type', 'alphanohtml');
	$field->unit = trim(GETPOST('unit', 'alphanohtml'));
	$field->default_value = GETPOST('default_value', 'restricthtml');
	$field->placeholder = trim(GETPOST('placeholder', 'alphanohtml'));
	$field->help = GETPOST('help', 'restricthtml');
	$field->is_required = GETPOSTINT('is_required');
	$field->visible_form = GETPOSTINT('visible_form');
	$field->visible_pdf = GETPOSTINT('visible_pdf');
	$field->readonly = GETPOSTINT('readonly');
	$field->active = GETPOSTINT('active');
	$field->position = GETPOSTINT('position');

	$result = ($action === 'add') ? $field->create($user, 0) : $field->update($user, 0);
	if ($result < 0) {
		setEventMessages($field->error, $field->errors, 'errors');
		$action = ($action === 'add') ? 'create' : 'edit';
	} else {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?fk_report_template='.(int) $templateId);
		exit;
	}
} elseif ($action === 'disable' && $id > 0) {
	if ($field->fetch($id) <= 0 || (int) $field->fk_report_template !== (int) $templateId) {
		accessforbidden();
	}
	$result = $field->disable($user);
	if ($result < 0) {
		setEventMessages($field->error, $field->errors, 'errors');
	} else {
		setEventMessages($langs->trans('PowerPlantPVRecordDisabled'), null, 'mesgs');
	}
	$action = '';
} elseif ($action === 'delete' && $confirm === 'yes' && $id > 0) {
	if ($field->fetch($id) <= 0 || (int) $field->fk_report_template !== (int) $templateId) {
		accessforbidden();
	}
	$result = $field->delete($user, 0);
	if ($result < 0) {
		setEventMessages($field->error, $field->errors, 'errors');
	} else {
		setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?fk_report_template='.(int) $templateId);
		exit;
	}
	$action = '';
} elseif (($action === 'moveup' || $action === 'movedown') && $id > 0) {
	$whereExtra = " AND fk_report_template = ".((int) $templateId);
	$result = powerplantpvReportTemplateMoveRow('powerplantpv_report_template_field', $id, $action === 'moveup' ? 'up' : 'down', $whereExtra);
	if ($result < 0) {
		setEventMessages($langs->trans('Error'), null, 'errors');
	}
	$action = '';
} elseif ($action === 'addoption' || $action === 'updateoption') {
	if ($selectedFieldId <= 0 || $field->fetch($selectedFieldId) <= 0 || (int) $field->fk_report_template !== (int) $templateId) {
		accessforbidden();
	}
	if ($action === 'updateoption') {
		if ($optionId <= 0 || $option->fetch($optionId) <= 0 || (int) $option->fk_report_template_field !== (int) $selectedFieldId) {
			accessforbidden();
		}
	} else {
		$option = new PowerPlantPVReportTemplateFieldOption($db);
	}
	$option->fk_report_template_field = $selectedFieldId;
	$option->code = strtoupper(trim(GETPOST('option_code', 'alphanohtml')));
	$option->label = trim(GETPOST('option_label', 'alphanohtml'));
	$option->label_en = trim(GETPOST('option_label_en', 'alphanohtml'));
	$option->active = GETPOSTINT('option_active');
	$option->position = GETPOSTINT('option_position');
	$result = ($action === 'addoption') ? $option->create($user, 0) : $option->update($user, 0);
	if ($result < 0) {
		setEventMessages($option->error, $option->errors, 'errors');
	} else {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?fk_report_template='.(int) $templateId.'&fk_report_template_field='.(int) $selectedFieldId);
		exit;
	}
} elseif ($action === 'disableoption' && $optionId > 0) {
	if ($option->fetch($optionId) <= 0) {
		accessforbidden();
	}
	$selectedFieldId = (int) $option->fk_report_template_field;
	if ($field->fetch($selectedFieldId) <= 0 || (int) $field->fk_report_template !== (int) $templateId) {
		accessforbidden();
	}
	$result = $option->disable($user);
	if ($result < 0) {
		setEventMessages($option->error, $option->errors, 'errors');
	} else {
		setEventMessages($langs->trans('PowerPlantPVRecordDisabled'), null, 'mesgs');
	}
	$action = '';
} elseif ($action === 'deleteoption' && $confirm === 'yes' && $optionId > 0) {
	if ($option->fetch($optionId) <= 0) {
		accessforbidden();
	}
	$selectedFieldId = (int) $option->fk_report_template_field;
	if ($field->fetch($selectedFieldId) <= 0 || (int) $field->fk_report_template !== (int) $templateId) {
		accessforbidden();
	}
	$result = $option->delete($user, 0);
	if ($result < 0) {
		setEventMessages($option->error, $option->errors, 'errors');
	} else {
		setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?fk_report_template='.(int) $templateId.'&fk_report_template_field='.(int) $selectedFieldId);
		exit;
	}
	$action = '';
} elseif (($action === 'moveoptionup' || $action === 'moveoptiondown') && $optionId > 0) {
	if ($option->fetch($optionId) <= 0) {
		accessforbidden();
	}
	$selectedFieldId = (int) $option->fk_report_template_field;
	if ($field->fetch($selectedFieldId) <= 0 || (int) $field->fk_report_template !== (int) $templateId) {
		accessforbidden();
	}
	$whereExtra = " AND fk_report_template_field = ".((int) $selectedFieldId);
	$result = powerplantpvReportTemplateMoveRow('powerplantpv_report_template_field_option', $optionId, $action === 'moveoptionup' ? 'up' : 'down', $whereExtra);
	if ($result < 0) {
		setEventMessages($langs->trans('Error'), null, 'errors');
	}
	$action = '';
}

if ($action === 'edit' && $id > 0) {
	if ($field->fetch($id) <= 0 || (int) $field->fk_report_template !== (int) $templateId) {
		accessforbidden();
	}
}
if ($action === 'editoption' && $optionId > 0) {
	if ($option->fetch($optionId) <= 0) {
		accessforbidden();
	}
	$selectedFieldId = (int) $option->fk_report_template_field;
	if ($field->fetch($selectedFieldId) <= 0 || (int) $field->fk_report_template !== (int) $templateId) {
		accessforbidden();
	}
}

$param = '&fk_report_template='.((int) $templateId);
if ($sectionFilter > 0) {
	$param .= '&fk_report_template_section='.((int) $sectionFilter);
}
if ($selectedFieldId > 0) {
	$param .= '&fk_report_template_field='.((int) $selectedFieldId);
}
if ($search !== '') {
	$param .= '&search='.urlencode($search);
}
if ($search_active !== '') {
	$param .= '&search_active='.urlencode((string) $search_active);
}
$paramNoField = '&fk_report_template='.((int) $templateId);
if ($sectionFilter > 0) {
	$paramNoField .= '&fk_report_template_section='.((int) $sectionFilter);
}
if ($search !== '') {
	$paramNoField .= '&search='.urlencode($search);
}
if ($search_active !== '') {
	$paramNoField .= '&search_active='.urlencode((string) $search_active);
}

$where = " WHERE t.entity = ".((int) $conf->entity);
$where .= " AND t.fk_report_template = ".((int) $templateId);
if ($sectionFilter > 0) {
	$where .= " AND t.fk_report_template_section = ".((int) $sectionFilter);
}
if ($search !== '') {
	$safeSearch = $db->escape($search);
	$where .= " AND (t.code LIKE '%".$safeSearch."%' OR t.label LIKE '%".$safeSearch."%')";
}
if ($search_active !== '') {
	$where .= " AND t.active = ".((int) $search_active);
}

$sql = "SELECT COUNT(*) as nb FROM ".$db->prefix()."powerplantpv_report_template_field as t".$where;
$resql = $db->query($sql);
$num = 0;
if ($resql) {
	$obj = $db->fetch_object($resql);
	$num = is_object($obj) ? (int) $obj->nb : 0;
	$db->free($resql);
}

$allowedSorts = array('code', 'label', 'field_type', 'fk_report_template_section', 'active', 'position');
if (!in_array($sortfield, $allowedSorts, true)) {
	$sortfield = 'position';
}
if (!in_array(strtoupper($sortorder), array('ASC', 'DESC'), true)) {
	$sortorder = 'ASC';
}

$sql = "SELECT t.rowid, t.code, t.label, t.field_type, t.fk_report_template_section, t.is_required, t.visible_form, t.visible_pdf, t.readonly, t.active, t.position, s.label as section_label";
$sql .= " FROM ".$db->prefix()."powerplantpv_report_template_field as t";
$sql .= " LEFT JOIN ".$db->prefix()."powerplantpv_report_template_section as s ON s.rowid = t.fk_report_template_section AND s.entity = t.entity";
$sql .= $where;
$sql .= " ORDER BY t.".$db->sanitize($sortfield)." ".strtoupper($sortorder).", t.rowid ASC";
$sql .= $db->plimit($limit + 1, $offset);
$resql = $db->query($sql);

$title = $langs->trans('PowerPlantPVReportFields');
powerplantpvReportTemplateAdminHeader($title, 'maintenance_report_templates');

if ($action === 'delete' && $id > 0) {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.(int) $id.$param.'&token='.newToken(), $langs->trans('Delete'), $langs->trans('ConfirmDeleteObject'), 'delete', '', 0, 1);
}
if ($action === 'deleteoption' && $optionId > 0) {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?option_id='.(int) $optionId.$param.'&token='.newToken(), $langs->trans('Delete'), $langs->trans('ConfirmDeleteObject'), 'deleteoption', '', 0, 1);
}

print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $num, 'fa-list', 0, '', '', $limit);
print '<div class="inline-block valignmiddle marginrightonly">'.$langs->trans('PowerPlantPVReportTemplate').' '.$form->selectarray('fk_report_template', powerplantpvReportTemplateOptions(0), $templateId, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</div>';
print '<div class="inline-block valignmiddle marginrightonly">'.$langs->trans('PowerPlantPVReportSection').' '.$form->selectarray('fk_report_template_section', powerplantpvReportTemplateSectionOptions($templateId, 1), $sectionFilter, 1, 0, 0, '', 0, 0, 0, '', 'minwidth250').'</div>';
print '<input type="submit" class="button smallpaddingimp" value="'.$langs->trans('Refresh').'">';

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste listwithfilterbefore centpercent">';
print '<tr class="liste_titre_filter">';
print '<td><input class="flat maxwidth150" type="text" name="search" value="'.dol_escape_htmltag($search).'"></td>';
print '<td></td><td></td><td></td>';
print '<td class="center">'.$form->selectarray('search_active', powerplantpvReportTemplateTranslateOptions(powerplantpvReportTemplateActiveOptions()), $search_active, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
print '<td></td><td class="center">';
print '<input type="submit" class="button smallpaddingimp" name="button_search" value="'.$langs->trans('Search').'">';
print '<input type="submit" class="button smallpaddingimp" name="button_removefilter" value="'.$langs->trans('RemoveFilter').'">';
print '</td></tr>';
print '<tr class="liste_titre">';
print_liste_field_titre('Code', $_SERVER['PHP_SELF'], 'code', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Label', $_SERVER['PHP_SELF'], 'label', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('PowerPlantPVReportSection', $_SERVER['PHP_SELF'], 'fk_report_template_section', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Type', $_SERVER['PHP_SELF'], 'field_type', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Status', $_SERVER['PHP_SELF'], 'active', '', $param, 'class="center"', $sortfield, $sortorder);
print_liste_field_titre('Position', $_SERVER['PHP_SELF'], 'position', '', $param, 'class="center"', $sortfield, $sortorder);
print '<th class="center">'.$langs->trans('Actions').'</th>';
print '</tr>';
$fieldTypes = powerplantpvReportTemplateTranslateOptions(powerplantpvReportTemplateFieldTypes());
if ($resql) {
	$i = 0;
	while ($i < min($num, $limit) && ($obj = $db->fetch_object($resql))) {
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag((string) $obj->code).'</td>';
		print '<td>'.dol_escape_htmltag((string) $obj->label).'</td>';
		print '<td>'.dol_escape_htmltag((string) $obj->section_label).'</td>';
		print '<td>'.dol_escape_htmltag(isset($fieldTypes[$obj->field_type]) ? $fieldTypes[$obj->field_type] : (string) $obj->field_type).'</td>';
		print '<td class="center"><span class="badge '.(!empty($obj->active) ? 'badge-status4' : 'badge-status8').'">'.$langs->trans(!empty($obj->active) ? 'Enabled' : 'Disabled').'</span></td>';
		print '<td class="center">'.((int) $obj->position).'</td>';
		print '<td class="center nowraponall">';
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=edit&id='.(int) $obj->rowid.$param.'">'.img_edit($langs->trans('Modify')).'</a>';
		print ' <a href="'.$_SERVER['PHP_SELF'].'?fk_report_template_field='.(int) $obj->rowid.$paramNoField.'">'.img_picto($langs->trans('PowerPlantPVReportFieldOptions'), 'list').'</a>';
		print ' <a href="'.$_SERVER['PHP_SELF'].'?action=moveup&id='.(int) $obj->rowid.'&token='.newToken().$param.'">'.img_picto($langs->trans('MoveUp'), 'uparrow').'</a>';
		print ' <a href="'.$_SERVER['PHP_SELF'].'?action=movedown&id='.(int) $obj->rowid.'&token='.newToken().$param.'">'.img_picto($langs->trans('MoveDown'), 'downarrow').'</a>';
		if (!empty($obj->active)) {
			print ' <a href="'.$_SERVER['PHP_SELF'].'?action=disable&id='.(int) $obj->rowid.'&token='.newToken().$param.'">'.img_picto($langs->trans('Disable'), 'switch_off').'</a>';
		}
		print ' <a href="'.$_SERVER['PHP_SELF'].'?action=delete&id='.(int) $obj->rowid.'&token='.newToken().$param.'">'.img_delete($langs->trans('Delete')).'</a>';
		print '</td></tr>';
		$i++;
	}
	$db->free($resql);
}
if (empty($num)) {
	powerplantpvPrintNoRecordFound(7);
}
print '</table></div></form>';

print '<br>';
print load_fiche_titre($langs->trans($action === 'edit' ? 'Modify' : 'New'), '', '');
print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="'.($action === 'edit' ? 'update' : 'add').'">';
print '<input type="hidden" name="fk_report_template" value="'.((int) $templateId).'">';
if ($action === 'edit') {
	print '<input type="hidden" name="id" value="'.((int) $field->id).'">';
}
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefieldcreate">'.$langs->trans('PowerPlantPVReportSection').'</td><td>'.$form->selectarray('fk_report_template_section_form', powerplantpvReportTemplateSectionOptions($templateId, 0), $field->fk_report_template_section ?: $sectionFilter, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td></tr>';
print '<tr><td class="titlefieldcreate">'.$langs->trans('Code').'</td><td><input class="flat maxwidth200" type="text" name="code" value="'.dol_escape_htmltag((string) $field->code).'"></td></tr>';
print '<tr><td class="titlefieldcreate">'.$langs->trans('Label').'</td><td><input class="flat minwidth300" type="text" name="label" value="'.dol_escape_htmltag((string) $field->label).'"></td></tr>';
print '<tr><td>'.$langs->trans('PowerPlantPVEnglishLabel').'</td><td><input class="flat minwidth300" type="text" name="label_en" value="'.dol_escape_htmltag((string) $field->label_en).'"></td></tr>';
print '<tr><td>'.$langs->trans('Description').'</td><td><textarea class="flat centpercent" name="description" rows="2">'.dol_escape_htmltag((string) $field->description).'</textarea></td></tr>';
print '<tr><td>'.$langs->trans('PowerPlantPVEnglishDescription').'</td><td><textarea class="flat centpercent" name="description_en" rows="2">'.dol_escape_htmltag((string) $field->description_en).'</textarea></td></tr>';
print '<tr><td class="titlefieldcreate">'.$langs->trans('Type').'</td><td>'.$form->selectarray('field_type', $fieldTypes, $field->field_type ?: 'text', 0, 0, 0, '', 0, 0, 0, '', 'minwidth250').'</td></tr>';
print '<tr><td>'.$langs->trans('PowerPlantPVReportScope').'</td><td>'.$form->selectarray('scope_type', array('' => '') + powerplantpvReportTemplateTranslateOptions(powerplantpvReportTemplateScopeTypes()), $field->scope_type, 0, 0, 0, '', 0, 0, 0, '', 'minwidth250').'</td></tr>';
print '<tr><td>'.$langs->trans('MaintenanceServiceDictionary').'</td><td>'.$form->selectarray('fk_maintenance_service', powerplantpvMaintenanceServiceOptions(1), $field->fk_maintenance_service, 1, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td></tr>';
print '<tr><td>'.$langs->trans('Unit').'</td><td><input class="flat maxwidth100" type="text" name="unit" value="'.dol_escape_htmltag((string) $field->unit).'"></td></tr>';
print '<tr><td>'.$langs->trans('DefaultValue').'</td><td><textarea class="flat centpercent" name="default_value" rows="2">'.dol_escape_htmltag((string) $field->default_value).'</textarea></td></tr>';
print '<tr><td>'.$langs->trans('PowerPlantPVPlaceholder').'</td><td><input class="flat minwidth300" type="text" name="placeholder" value="'.dol_escape_htmltag((string) $field->placeholder).'"></td></tr>';
print '<tr><td>'.$langs->trans('Help').'</td><td><textarea class="flat centpercent" name="help" rows="2">'.dol_escape_htmltag((string) $field->help).'</textarea></td></tr>';
print '<tr><td>'.$langs->trans('Required').'</td><td>'.$form->selectyesno('is_required', (int) $field->is_required, 1).'</td></tr>';
print '<tr><td>'.$langs->trans('PowerPlantPVVisibleForm').'</td><td>'.$form->selectyesno('visible_form', isset($field->visible_form) ? (int) $field->visible_form : 1, 1).'</td></tr>';
print '<tr><td>'.$langs->trans('PowerPlantPVVisiblePdf').'</td><td>'.$form->selectyesno('visible_pdf', isset($field->visible_pdf) ? (int) $field->visible_pdf : 1, 1).'</td></tr>';
print '<tr><td>'.$langs->trans('PowerPlantPVReadonly').'</td><td>'.$form->selectyesno('readonly', (int) $field->readonly, 1).'</td></tr>';
print '<tr><td>'.$langs->trans('Status').'</td><td>'.$form->selectarray('active', powerplantpvReportTemplateTranslateOptions(powerplantpvReportTemplateActiveOptions()), isset($field->active) ? (int) $field->active : 1, 0, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td></tr>';
print '<tr><td>'.$langs->trans('Position').'</td><td><input class="flat maxwidth75 right" type="number" name="position" value="'.((int) $field->position).'"></td></tr>';
print '</table>';
print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';

if ($selectedFieldId > 0 && $field->fetch($selectedFieldId) > 0 && (int) $field->fk_report_template === (int) $templateId) {
	print '<br>';
	print load_fiche_titre($langs->trans('PowerPlantPVReportFieldOptions').' - '.dol_escape_htmltag((string) $field->label), '', '');
	$sql = "SELECT rowid, code, label, label_en, active, position FROM ".$db->prefix()."powerplantpv_report_template_field_option WHERE entity = ".((int) $conf->entity)." AND fk_report_template_field = ".((int) $selectedFieldId)." ORDER BY position ASC, rowid ASC";
	$resopt = $db->query($sql);
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Code').'</td><td>'.$langs->trans('Label').'</td><td class="center">'.$langs->trans('Status').'</td><td class="center">'.$langs->trans('Position').'</td><td class="center">'.$langs->trans('Actions').'</td></tr>';
	$optCount = 0;
	if ($resopt) {
		while ($obj = $db->fetch_object($resopt)) {
			$optCount++;
			print '<tr class="oddeven"><td>'.dol_escape_htmltag((string) $obj->code).'</td><td>'.dol_escape_htmltag((string) $obj->label).'</td>';
			print '<td class="center"><span class="badge '.(!empty($obj->active) ? 'badge-status4' : 'badge-status8').'">'.$langs->trans(!empty($obj->active) ? 'Enabled' : 'Disabled').'</span></td>';
			print '<td class="center">'.((int) $obj->position).'</td><td class="center nowraponall">';
			print '<a href="'.$_SERVER['PHP_SELF'].'?action=editoption&option_id='.(int) $obj->rowid.$param.'">'.img_edit($langs->trans('Modify')).'</a>';
			print ' <a href="'.$_SERVER['PHP_SELF'].'?action=moveoptionup&option_id='.(int) $obj->rowid.'&token='.newToken().$param.'">'.img_picto($langs->trans('MoveUp'), 'uparrow').'</a>';
			print ' <a href="'.$_SERVER['PHP_SELF'].'?action=moveoptiondown&option_id='.(int) $obj->rowid.'&token='.newToken().$param.'">'.img_picto($langs->trans('MoveDown'), 'downarrow').'</a>';
			if (!empty($obj->active)) {
				print ' <a href="'.$_SERVER['PHP_SELF'].'?action=disableoption&option_id='.(int) $obj->rowid.'&token='.newToken().$param.'">'.img_picto($langs->trans('Disable'), 'switch_off').'</a>';
			}
			print ' <a href="'.$_SERVER['PHP_SELF'].'?action=deleteoption&option_id='.(int) $obj->rowid.'&token='.newToken().$param.'">'.img_delete($langs->trans('Delete')).'</a>';
			print '</td></tr>';
		}
		$db->free($resopt);
	}
	if ($optCount === 0) {
		powerplantpvPrintNoRecordFound(5);
	}
	print '</table>';

	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="'.($action === 'editoption' ? 'updateoption' : 'addoption').'">';
	print '<input type="hidden" name="fk_report_template" value="'.((int) $templateId).'">';
	print '<input type="hidden" name="fk_report_template_field" value="'.((int) $selectedFieldId).'">';
	if ($action === 'editoption') {
		print '<input type="hidden" name="option_id" value="'.((int) $option->id).'">';
	}
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefieldcreate">'.$langs->trans('Code').'</td><td><input class="flat maxwidth200" type="text" name="option_code" value="'.dol_escape_htmltag((string) $option->code).'"></td></tr>';
	print '<tr><td class="titlefieldcreate">'.$langs->trans('Label').'</td><td><input class="flat minwidth300" type="text" name="option_label" value="'.dol_escape_htmltag((string) $option->label).'"></td></tr>';
	print '<tr><td>'.$langs->trans('PowerPlantPVEnglishLabel').'</td><td><input class="flat minwidth300" type="text" name="option_label_en" value="'.dol_escape_htmltag((string) $option->label_en).'"></td></tr>';
	print '<tr><td>'.$langs->trans('Status').'</td><td>'.$form->selectarray('option_active', powerplantpvReportTemplateTranslateOptions(powerplantpvReportTemplateActiveOptions()), isset($option->active) ? (int) $option->active : 1, 0, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td></tr>';
	print '<tr><td>'.$langs->trans('Position').'</td><td><input class="flat maxwidth75 right" type="number" name="option_position" value="'.((int) $option->position).'"></td></tr>';
	print '</table><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div></form>';
}

powerplantpvReportTemplateAdminFooter();
