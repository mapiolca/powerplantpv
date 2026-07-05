<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		admin/maintenance_intervention_natures.php
 * \ingroup		powerplantpv
 * \brief		Maintenance intervention nature administration.
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
dol_include_once('/powerplantpv/class/powerplantpvinterventionnature.class.php');

$langs->loadLangs(array('admin', 'powerplantpv@powerplantpv', 'other'));
powerplantpvReportTemplateAdminAccess();

$form = new Form($db);
$object = new PowerPlantPVInterventionNature($db);
$template = new PowerPlantPVReportTemplate($db);

$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');
$confirm = GETPOST('confirm', 'alpha');
$search = trim(GETPOST('search', 'alphanohtml'));
$search_active = GETPOSTISSET('search_active') ? GETPOST('search_active', 'int') : '';
$search_requires_report = GETPOSTISSET('search_requires_report') ? GETPOST('search_requires_report', 'int') : '';
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
	$search_requires_report = '';
}

if (in_array($action, array('add', 'update', 'enable', 'disable', 'delete', 'moveup', 'movedown'), true)) {
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
		$object = new PowerPlantPVInterventionNature($db);
	}
	$templateId = GETPOSTINT('fk_report_template');
	$templateCode = '';
	if ($templateId > 0 && $template->fetch($templateId) > 0) {
		$templateCode = (string) $template->code;
	}
	$object->code = strtoupper(trim(GETPOST('code', 'alphanohtml')));
	$object->label = trim(GETPOST('label', 'alphanohtml'));
	$object->label_en = trim(GETPOST('label_en', 'alphanohtml'));
	$object->description = GETPOST('description', 'restricthtml');
	$object->description_en = GETPOST('description_en', 'restricthtml');
	$object->fk_report_template = $templateId;
	$object->report_template_code = $templateCode;
	$object->is_maintenance = GETPOSTINT('is_maintenance');
	$object->is_preventive = GETPOSTINT('is_preventive');
	$object->requires_report = GETPOSTINT('requires_report');
	$object->requires_signature = GETPOSTINT('requires_signature');
	$object->active = GETPOSTINT('active');
	$object->position = GETPOSTINT('position');

	$result = ($action === 'add') ? $object->create($user, 0) : $object->update($user, 0);
	if ($result < 0) {
		setEventMessages($object->error, $object->errors, 'errors');
		$action = ($action === 'add') ? 'create' : 'edit';
	} else {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}
} elseif ($action === 'disable' && $id > 0) {
	if ($object->fetch($id) <= 0) {
		accessforbidden();
	}
	$result = $object->disable($user);
	if ($result < 0) {
		setEventMessages($object->error, $object->errors, 'errors');
	} else {
		setEventMessages($langs->trans('PowerPlantPVRecordDisabled'), null, 'mesgs');
	}
	$action = '';
} elseif ($action === 'enable' && $id > 0) {
	if ($object->fetch($id) <= 0) {
		accessforbidden();
	}
	$result = $object->enable($user);
	if ($result < 0) {
		setEventMessages($object->error, $object->errors, 'errors');
	} else {
		setEventMessages($langs->trans('PowerPlantPVRecordEnabled'), null, 'mesgs');
	}
	$action = '';
} elseif ($action === 'delete' && $confirm === 'yes' && $id > 0) {
	if ($object->fetch($id) <= 0) {
		accessforbidden();
	}
	$result = $object->delete($user, 0);
	if ($result < 0) {
		setEventMessages($object->error, $object->errors, 'errors');
	} else {
		setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}
	$action = '';
} elseif (($action === 'moveup' || $action === 'movedown') && $id > 0) {
	$result = powerplantpvReportTemplateMoveRow('c_powerplantpv_intervention_nature', $id, $action === 'moveup' ? 'up' : 'down');
	if ($result < 0) {
		setEventMessages($langs->trans('Error'), null, 'errors');
	}
	$action = '';
}

if ($action === 'edit' && $id > 0) {
	if ($object->fetch($id) <= 0) {
		accessforbidden();
	}
}
$openeditdialog = in_array($action, array('create', 'edit'), true);

$param = '';
if ($search !== '') {
	$param .= '&search='.urlencode($search);
}
if ($search_active !== '') {
	$param .= '&search_active='.urlencode((string) $search_active);
}
if ($search_requires_report !== '') {
	$param .= '&search_requires_report='.urlencode((string) $search_requires_report);
}

$where = " WHERE t.entity = ".((int) $conf->entity);
if ($search !== '') {
	$safeSearch = $db->escape($search);
	$where .= " AND (t.code LIKE '%".$safeSearch."%' OR t.label LIKE '%".$safeSearch."%')";
}
if ($search_active !== '') {
	$where .= " AND t.active = ".((int) $search_active);
}
if ($search_requires_report !== '') {
	$where .= " AND t.requires_report = ".((int) $search_requires_report);
}

$sql = "SELECT COUNT(*) as nb FROM ".$db->prefix()."c_powerplantpv_intervention_nature as t".$where;
$resql = $db->query($sql);
$num = 0;
if ($resql) {
	$obj = $db->fetch_object($resql);
	$num = is_object($obj) ? (int) $obj->nb : 0;
	$db->free($resql);
}

$allowedSorts = array('code', 'label', 'fk_report_template', 'requires_report', 'active', 'position');
if (!in_array($sortfield, $allowedSorts, true)) {
	$sortfield = 'position';
}
if (!in_array(strtoupper($sortorder), array('ASC', 'DESC'), true)) {
	$sortorder = 'ASC';
}

$sql = "SELECT t.rowid, t.code, t.label, t.fk_report_template, t.report_template_code, t.is_maintenance, t.is_preventive, t.requires_report, t.requires_signature, t.active, t.position, rt.label as template_label, rt.code as template_code";
$sql .= " FROM ".$db->prefix()."c_powerplantpv_intervention_nature as t";
$sql .= " LEFT JOIN ".$db->prefix()."powerplantpv_report_template as rt ON rt.rowid = t.fk_report_template AND rt.entity = t.entity";
$sql .= $where;
$sql .= " ORDER BY t.".$db->sanitize($sortfield)." ".strtoupper($sortorder).", t.rowid ASC";
$sql .= $db->plimit($limit + 1, $offset);
$resql = $db->query($sql);

$title = $langs->trans('InterventionNatureDictionary');
powerplantpvReportTemplateAdminHeader($title, 'maintenance_intervention_natures');

if ($action === 'delete' && $id > 0) {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.(int) $id.'&token='.newToken().$param, $langs->trans('Delete'), $langs->trans('ConfirmDeleteObject'), 'delete', '', 0, 1);
}

$newurl = $_SERVER['PHP_SELF'].'?action=create'.$param;
$newbutton = dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', $newurl, 'powerplantpv-interventionnature-new-btn', 1);
print load_fiche_titre($title, $newbutton, 'fa-tags');

print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print_barre_liste('', $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $num, 'fa-tags', 0, '', '', $limit);
print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste listwithfilterbefore centpercent">';
print '<tr class="liste_titre_filter">';
print '<td><input class="flat maxwidth150" type="text" name="search" value="'.dol_escape_htmltag($search).'"></td>';
print '<td></td><td></td>';
print '<td class="center">'.$form->selectyesno('search_requires_report', $search_requires_report, 1, false, 1).'</td>';
print '<td class="center">'.$form->selectarray('search_active', powerplantpvReportTemplateTranslateOptions(powerplantpvReportTemplateActiveOptions()), $search_active, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
print '<td></td><td class="center">';
print '<input type="submit" class="button smallpaddingimp" name="button_search" value="'.$langs->trans('Search').'">';
print '<input type="submit" class="button smallpaddingimp" name="button_removefilter" value="'.$langs->trans('RemoveFilter').'">';
print '</td></tr>';
print '<tr class="liste_titre">';
print_liste_field_titre('Code', $_SERVER['PHP_SELF'], 'code', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Label', $_SERVER['PHP_SELF'], 'label', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('PowerPlantPVReportTemplate', $_SERVER['PHP_SELF'], 'fk_report_template', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('PowerPlantPVRequiresReport', $_SERVER['PHP_SELF'], 'requires_report', '', $param, 'class="center"', $sortfield, $sortorder);
print_liste_field_titre('Status', $_SERVER['PHP_SELF'], 'active', '', $param, 'class="center"', $sortfield, $sortorder);
print_liste_field_titre('Position', $_SERVER['PHP_SELF'], 'position', '', $param, 'class="center"', $sortfield, $sortorder);
print '<th class="center">'.$langs->trans('Actions').'</th>';
print '</tr>';
if ($resql) {
	$i = 0;
	while ($i < min($num, $limit) && ($obj = $db->fetch_object($resql))) {
		$templateLabel = !empty($obj->template_label) ? (string) $obj->template_label.' ['.(string) $obj->template_code.']' : (string) $obj->report_template_code;
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag((string) $obj->code).'</td>';
		print '<td>'.dol_escape_htmltag((string) $obj->label).'</td>';
		print '<td>'.dol_escape_htmltag($templateLabel).'</td>';
		print '<td class="center">'.yn((int) $obj->requires_report).'</td>';
		print '<td class="center"><span class="badge '.(!empty($obj->active) ? 'badge-status4' : 'badge-status8').'">'.$langs->trans(!empty($obj->active) ? 'Enabled' : 'Disabled').'</span></td>';
		print '<td class="center">'.((int) $obj->position).'</td>';
		print '<td class="center nowraponall">';
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=edit&id='.(int) $obj->rowid.$param.'">'.img_edit($langs->trans('Modify')).'</a>';
		print ' <a href="'.$_SERVER['PHP_SELF'].'?action=moveup&id='.(int) $obj->rowid.'&token='.newToken().$param.'">'.img_picto($langs->trans('MoveUp'), 'uparrow').'</a>';
		print ' <a href="'.$_SERVER['PHP_SELF'].'?action=movedown&id='.(int) $obj->rowid.'&token='.newToken().$param.'">'.img_picto($langs->trans('MoveDown'), 'downarrow').'</a>';
		if (!empty($obj->active)) {
			print ' <a href="'.$_SERVER['PHP_SELF'].'?action=disable&id='.(int) $obj->rowid.'&token='.newToken().$param.'">'.img_picto($langs->trans('Disable'), 'switch_on').'</a>';
		} else {
			print ' <a href="'.$_SERVER['PHP_SELF'].'?action=enable&id='.(int) $obj->rowid.'&token='.newToken().$param.'">'.img_picto($langs->trans('Enable'), 'switch_off').'</a>';
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

$dialogtitle = $langs->trans($action === 'edit' ? 'Modify' : 'New');
print '<div id="dialog-interventionnature" class="hideobject">';
print '<form method="POST" id="powerplantpv-interventionnature-form" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="'.($action === 'edit' ? 'update' : 'add').'">';
if ($action === 'edit') {
	print '<input type="hidden" name="id" value="'.((int) $object->id).'">';
}
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefieldcreate">'.$langs->trans('Code').'</td><td><input class="flat maxwidth200" type="text" name="code" value="'.dol_escape_htmltag((string) $object->code).'"></td></tr>';
print '<tr><td class="titlefieldcreate">'.$langs->trans('Label').'</td><td><input class="flat minwidth300" type="text" name="label" value="'.dol_escape_htmltag((string) $object->label).'"></td></tr>';
print '<tr><td>'.$langs->trans('PowerPlantPVEnglishLabel').'</td><td><input class="flat minwidth300" type="text" name="label_en" value="'.dol_escape_htmltag((string) $object->label_en).'"></td></tr>';
print '<tr><td>'.$langs->trans('Description').'</td><td><textarea class="flat centpercent" name="description" rows="2">'.dol_escape_htmltag((string) $object->description).'</textarea></td></tr>';
print '<tr><td>'.$langs->trans('PowerPlantPVEnglishDescription').'</td><td><textarea class="flat centpercent" name="description_en" rows="2">'.dol_escape_htmltag((string) $object->description_en).'</textarea></td></tr>';
print '<tr><td>'.$langs->trans('PowerPlantPVReportTemplate').'</td><td>'.$form->selectarray('fk_report_template', powerplantpvReportTemplateOptions(1), $object->fk_report_template, 1, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td></tr>';
print '<tr><td>'.$langs->trans('PowerPlantPVIsMaintenance').'</td><td>'.$form->selectyesno('is_maintenance', isset($object->is_maintenance) ? (int) $object->is_maintenance : 1, 1).'</td></tr>';
print '<tr><td>'.$langs->trans('PowerPlantPVIsPreventive').'</td><td>'.$form->selectyesno('is_preventive', (int) $object->is_preventive, 1).'</td></tr>';
print '<tr><td>'.$langs->trans('PowerPlantPVRequiresReport').'</td><td>'.$form->selectyesno('requires_report', (int) $object->requires_report, 1).'</td></tr>';
print '<tr><td>'.$langs->trans('PowerPlantPVRequiresSignature').'</td><td>'.$form->selectyesno('requires_signature', (int) $object->requires_signature, 1).'</td></tr>';
print '<tr><td>'.$langs->trans('Status').'</td><td>'.$form->selectarray('active', powerplantpvReportTemplateTranslateOptions(powerplantpvReportTemplateActiveOptions()), isset($object->active) ? (int) $object->active : 1, 0, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td></tr>';
print '<tr><td>'.$langs->trans('Position').'</td><td><input class="flat maxwidth75 right" type="number" name="position" value="'.((int) $object->position).'"></td></tr>';
print '</table>';
print '<div class="center">';
print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
print ' <input type="button" class="button button-cancel" id="interventionnature-cancel-btn" value="'.$langs->trans('Cancel').'">';
print '</div>';
print '</form>';
print '</div>';
print '<script nonce="'.getNonce().'">';
print 'jQuery(function(){';
print 'var dialog = jQuery("#dialog-interventionnature");';
print 'dialog.dialog({autoOpen:false,modal:true,width:"auto",height:"auto",resizable:false,title:"'.dol_escape_js($dialogtitle).'",maxHeight:Math.max(320,jQuery(window).height()-80),open:function(){';
print 'jQuery(this).parent().css({"max-width":Math.min(920,jQuery(window).width()-40)+"px"});';
print 'jQuery("#dialog-interventionnature select").select2({width:"resolve",minimumResultsForSearch:0,dropdownCssClass:"ui-dialog"});';
print '}});';
print 'jQuery("#interventionnature-cancel-btn").on("click", function(){dialog.dialog("close");});';
if ($openeditdialog) {
	print 'dialog.dialog("open");';
}
print '});';
print '</script>';

powerplantpvReportTemplateAdminFooter();
