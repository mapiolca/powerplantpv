<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		admin/maintenance_service_sections.php
 * \ingroup		powerplantpv
 * \brief		Maintenance service to report section mapping administration.
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
dol_include_once('/powerplantpv/class/powerplantpvmaintenanceservicesection.class.php');

$langs->loadLangs(array('admin', 'powerplantpv@powerplantpv', 'other'));
powerplantpvReportTemplateAdminAccess();

$form = new Form($db);
$template = new PowerPlantPVReportTemplate($db);
$object = new PowerPlantPVMaintenanceServiceSection($db);

$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');
$confirm = GETPOST('confirm', 'alpha');
$templateId = GETPOSTINT('fk_report_template') ?: powerplantpvDefaultReportTemplateId();
$serviceFilter = GETPOSTINT('fk_maintenance_service');
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
	$serviceFilter = 0;
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
		if ($id <= 0 || $object->fetch($id) <= 0 || (int) $object->fk_report_template !== (int) $templateId) {
			accessforbidden();
		}
	} else {
		$object = new PowerPlantPVMaintenanceServiceSection($db);
	}
	$object->fk_report_template = $templateId;
	$object->fk_maintenance_service = GETPOSTINT('fk_maintenance_service_form');
	$object->fk_report_section = 0;
	$object->fk_report_template_section = GETPOSTINT('fk_report_template_section');
	$object->is_required = GETPOSTINT('is_required');
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
	$result = powerplantpvReportTemplateMoveRow('powerplantpv_maintenance_service_section', $id, $action === 'moveup' ? 'up' : 'down', $whereExtra);
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
if ($serviceFilter > 0) {
	$param .= '&fk_maintenance_service='.((int) $serviceFilter);
}
if ($search_active !== '') {
	$param .= '&search_active='.urlencode((string) $search_active);
}

$where = " WHERE t.entity = ".((int) $conf->entity);
$where .= " AND t.fk_report_template = ".((int) $templateId);
if ($serviceFilter > 0) {
	$where .= " AND t.fk_maintenance_service = ".((int) $serviceFilter);
}
if ($search_active !== '') {
	$where .= " AND t.active = ".((int) $search_active);
}

$sql = "SELECT COUNT(*) as nb FROM ".$db->prefix()."powerplantpv_maintenance_service_section as t".$where;
$resql = $db->query($sql);
$num = 0;
if ($resql) {
	$obj = $db->fetch_object($resql);
	$num = is_object($obj) ? (int) $obj->nb : 0;
	$db->free($resql);
}

$allowedSorts = array('fk_maintenance_service', 'fk_report_template_section', 'is_required', 'active', 'position');
if (!in_array($sortfield, $allowedSorts, true)) {
	$sortfield = 'position';
}
if (!in_array(strtoupper($sortorder), array('ASC', 'DESC'), true)) {
	$sortorder = 'ASC';
}

$sql = "SELECT t.rowid, t.fk_maintenance_service, t.fk_report_template_section, t.is_required, t.active, t.position, ms.label as service_label, s.label as section_label";
$sql .= " FROM ".$db->prefix()."powerplantpv_maintenance_service_section as t";
$sql .= " LEFT JOIN ".$db->prefix()."c_powerplantpv_maintenance_service as ms ON ms.rowid = t.fk_maintenance_service AND ms.entity = t.entity";
$sql .= " LEFT JOIN ".$db->prefix()."powerplantpv_report_template_section as s ON s.rowid = t.fk_report_template_section AND s.entity = t.entity";
$sql .= $where;
$sql .= " ORDER BY t.".$db->sanitize($sortfield)." ".strtoupper($sortorder).", t.rowid ASC";
$sql .= $db->plimit($limit + 1, $offset);
$resql = $db->query($sql);

$title = $langs->trans('PowerPlantPVMaintenanceServiceSections');
powerplantpvReportTemplateAdminHeader($title, 'maintenance_service_sections');

if ($action === 'delete' && $id > 0) {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.(int) $id.$param.'&token='.newToken(), $langs->trans('Delete'), $langs->trans('ConfirmDeleteObject'), 'delete', '', 0, 1);
}

print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $num, 'fa-link', 0, '', '', $limit);
print '<div class="inline-block valignmiddle marginrightonly">'.$langs->trans('PowerPlantPVReportTemplate').' '.$form->selectarray('fk_report_template', powerplantpvReportTemplateOptions(0), $templateId, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</div>';
print '<div class="inline-block valignmiddle marginrightonly">'.$langs->trans('MaintenanceServiceDictionary').' '.$form->selectarray('fk_maintenance_service', powerplantpvMaintenanceServiceOptions(1), $serviceFilter, 1, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</div>';
print '<input type="submit" class="button smallpaddingimp" value="'.$langs->trans('Refresh').'">';

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste listwithfilterbefore centpercent">';
print '<tr class="liste_titre_filter">';
print '<td></td><td></td><td></td>';
print '<td class="center">'.$form->selectarray('search_active', powerplantpvReportTemplateTranslateOptions(powerplantpvReportTemplateActiveOptions()), $search_active, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
print '<td></td><td class="center">';
print '<input type="submit" class="button smallpaddingimp" name="button_search" value="'.$langs->trans('Search').'">';
print '<input type="submit" class="button smallpaddingimp" name="button_removefilter" value="'.$langs->trans('RemoveFilter').'">';
print '</td></tr>';
print '<tr class="liste_titre">';
print_liste_field_titre('MaintenanceServiceDictionary', $_SERVER['PHP_SELF'], 'fk_maintenance_service', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('PowerPlantPVReportSection', $_SERVER['PHP_SELF'], 'fk_report_template_section', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Required', $_SERVER['PHP_SELF'], 'is_required', '', $param, 'class="center"', $sortfield, $sortorder);
print_liste_field_titre('Status', $_SERVER['PHP_SELF'], 'active', '', $param, 'class="center"', $sortfield, $sortorder);
print_liste_field_titre('Position', $_SERVER['PHP_SELF'], 'position', '', $param, 'class="center"', $sortfield, $sortorder);
print '<th class="center">'.$langs->trans('Actions').'</th>';
print '</tr>';
if ($resql) {
	$i = 0;
	while ($i < min($num, $limit) && ($obj = $db->fetch_object($resql))) {
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag((string) $obj->service_label).'</td>';
		print '<td>'.dol_escape_htmltag((string) $obj->section_label).'</td>';
		print '<td class="center">'.yn((int) $obj->is_required).'</td>';
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
		print '</td></tr>';
		$i++;
	}
	$db->free($resql);
}
if (empty($num)) {
	powerplantpvPrintNoRecordFound(6);
}
print '</table></div></form>';

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
print '<tr><td class="titlefieldcreate">'.$langs->trans('MaintenanceServiceDictionary').'</td><td>'.$form->selectarray('fk_maintenance_service_form', powerplantpvMaintenanceServiceOptions(0), $object->fk_maintenance_service ?: $serviceFilter, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td></tr>';
print '<tr><td class="titlefieldcreate">'.$langs->trans('PowerPlantPVReportSection').'</td><td>'.$form->selectarray('fk_report_template_section', powerplantpvReportTemplateSectionOptions($templateId, 0), $object->fk_report_template_section, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td></tr>';
print '<tr><td>'.$langs->trans('Required').'</td><td>'.$form->selectyesno('is_required', (int) $object->is_required, 1).'</td></tr>';
print '<tr><td>'.$langs->trans('Status').'</td><td>'.$form->selectarray('active', powerplantpvReportTemplateTranslateOptions(powerplantpvReportTemplateActiveOptions()), isset($object->active) ? (int) $object->active : 1, 0, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td></tr>';
print '<tr><td>'.$langs->trans('Position').'</td><td><input class="flat maxwidth75 right" type="number" name="position" value="'.((int) $object->position).'"></td></tr>';
print '</table>';
print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';

powerplantpvReportTemplateAdminFooter();
