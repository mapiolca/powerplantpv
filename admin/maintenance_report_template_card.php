<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		admin/maintenance_report_template_card.php
 * \ingroup		powerplantpv
 * \brief		Report template administration card.
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

$langs->loadLangs(array('admin', 'powerplantpv@powerplantpv', 'other'));
powerplantpvReportTemplateAdminAccess();

$form = new Form($db);
$object = new PowerPlantPVReportTemplate($db);

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

$isCreate = ($action === 'create' || $action === 'add');
if ($id > 0) {
	$result = $object->fetch($id);
	if ($result <= 0) {
		accessforbidden();
	}
} elseif (!$isCreate) {
	$action = 'create';
}

if (in_array($action, array('add', 'update', 'disable', 'delete', 'setdefault'), true)) {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
}

if ($action === 'add' || $action === 'update') {
	if ($action === 'add') {
		$object = new PowerPlantPVReportTemplate($db);
	}
	$object->code = trim(GETPOST('code', 'alphanohtml'));
	$object->label = trim(GETPOST('label', 'alphanohtml'));
	$object->label_en = trim(GETPOST('label_en', 'alphanohtml'));
	$object->description = GETPOST('description', 'restricthtml');
	$object->description_en = GETPOST('description_en', 'restricthtml');
	$object->target_element = PowerPlantPVReportTemplate::TARGET_INTERVENTION;
	$object->is_default = GETPOSTINT('is_default');
	$object->active = GETPOSTINT('active');
	$object->position = GETPOSTINT('position');

	$result = ($action === 'add') ? $object->create($user, 0) : $object->update($user, 0);
	if ($result < 0) {
		setEventMessages($object->error, $object->errors, 'errors');
		$action = ($action === 'add') ? 'create' : 'edit';
	} else {
		if (!empty($object->is_default)) {
			$object->setDefault($user);
		}
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		$id = ($action === 'add') ? (int) $result : (int) $object->id;
		$redirect = $backtopage ?: dol_buildpath('/powerplantpv/admin/maintenance_report_template_card.php', 1).'?id='.(int) $id;
		header('Location: '.$redirect);
		exit;
	}
} elseif ($action === 'disable' && $id > 0) {
	$result = $object->disable($user);
	if ($result < 0) {
		setEventMessages($object->error, $object->errors, 'errors');
	} else {
		setEventMessages($langs->trans('PowerPlantPVReportTemplateDisabled'), null, 'mesgs');
	}
	$action = '';
} elseif ($action === 'setdefault' && $id > 0) {
	$result = $object->setDefault($user);
	if ($result < 0) {
		setEventMessages($object->error, $object->errors, 'errors');
	} else {
		setEventMessages($langs->trans('PowerPlantPVReportTemplateSetDefault'), null, 'mesgs');
	}
	$action = '';
} elseif ($action === 'delete' && $confirm === 'yes' && $id > 0) {
	$result = $object->delete($user, 0);
	if ($result < 0) {
		setEventMessages($object->error, $object->errors, 'errors');
	} else {
		setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
		header('Location: '.dol_buildpath('/powerplantpv/admin/maintenance_report_templates.php', 1));
		exit;
	}
	$action = '';
}

if ($id > 0 && empty($object->id)) {
	$object->fetch($id);
}

$title = $langs->trans('PowerPlantPVReportTemplate');
powerplantpvReportTemplateAdminHeader($title, 'maintenance_report_templates');

if ($action === 'delete' && $id > 0) {
	print $form->formconfirm(
		$_SERVER['PHP_SELF'].'?id='.(int) $id.'&token='.newToken(),
		$langs->trans('Delete'),
		$langs->trans('ConfirmDeleteObject', $object->label),
		'delete',
		'',
		0,
		1
	);
}

$isEdit = in_array($action, array('create', 'edit'), true);

if ($id > 0) {
	$linkback = '<a href="'.dol_buildpath('/powerplantpv/admin/maintenance_report_templates.php', 1).'">'.$langs->trans('BackToList').'</a>';
	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield">'.$langs->trans('Ref').'</td><td>'.dol_escape_htmltag((string) $object->code).'</td></tr>';
	print '<tr><td>'.$langs->trans('Label').'</td><td>'.dol_escape_htmltag((string) $object->label).'</td></tr>';
	print '<tr><td>'.$langs->trans('Status').'</td><td><span class="badge '.(!empty($object->active) ? 'badge-status4' : 'badge-status8').'">'.$langs->trans(!empty($object->active) ? 'Enabled' : 'Disabled').'</span></td></tr>';
	print '<tr><td>'.$langs->trans('Default').'</td><td>'.yn((int) $object->is_default).'</td></tr>';
	print '</table>';
	print '</div>';
	print '<div class="tabsAction">';
	print dolGetButtonAction($langs->trans('PowerPlantPVReportSections'), '', 'default', dol_buildpath('/powerplantpv/admin/maintenance_report_template_sections.php', 1).'?fk_report_template='.(int) $object->id, '', true);
	print dolGetButtonAction($langs->trans('PowerPlantPVReportFields'), '', 'default', dol_buildpath('/powerplantpv/admin/maintenance_report_template_fields.php', 1).'?fk_report_template='.(int) $object->id, '', true);
	print dolGetButtonAction($langs->trans('PowerPlantPVMaintenanceServiceSections'), '', 'default', dol_buildpath('/powerplantpv/admin/maintenance_service_sections.php', 1).'?fk_report_template='.(int) $object->id, '', true);
	print '</div>';
}

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="'.($id > 0 ? 'update' : 'add').'">';
if ($id > 0) {
	print '<input type="hidden" name="id" value="'.((int) $id).'">';
}
print '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">';

print '<div class="div-table-responsive-no-min">';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefieldcreate">'.$langs->trans('Code').'</td><td><input class="flat maxwidth200" type="text" name="code" value="'.dol_escape_htmltag((string) $object->code).'"'.($id > 0 ? ' readonly' : '').'></td></tr>';
print '<tr><td class="titlefieldcreate">'.$langs->trans('Label').'</td><td><input class="flat minwidth300" type="text" name="label" value="'.dol_escape_htmltag((string) $object->label).'"></td></tr>';
print '<tr><td>'.$langs->trans('PowerPlantPVEnglishLabel').'</td><td><input class="flat minwidth300" type="text" name="label_en" value="'.dol_escape_htmltag((string) $object->label_en).'"></td></tr>';
print '<tr><td>'.$langs->trans('Description').'</td><td><textarea class="flat centpercent" name="description" rows="3">'.dol_escape_htmltag((string) $object->description).'</textarea></td></tr>';
print '<tr><td>'.$langs->trans('PowerPlantPVEnglishDescription').'</td><td><textarea class="flat centpercent" name="description_en" rows="3">'.dol_escape_htmltag((string) $object->description_en).'</textarea></td></tr>';
print '<tr><td>'.$langs->trans('PowerPlantPVReportTargetElement').'</td><td>'.$form->selectarray('target_element', array(PowerPlantPVReportTemplate::TARGET_INTERVENTION => 'Intervention'), PowerPlantPVReportTemplate::TARGET_INTERVENTION, 0, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
print '<tr><td>'.$langs->trans('Default').'</td><td>'.$form->selectyesno('is_default', (int) $object->is_default, 1).'</td></tr>';
print '<tr><td>'.$langs->trans('Status').'</td><td>'.$form->selectarray('active', powerplantpvReportTemplateTranslateOptions(powerplantpvReportTemplateActiveOptions()), isset($object->active) ? (int) $object->active : 1, 0, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td></tr>';
print '<tr><td>'.$langs->trans('Position').'</td><td><input class="flat maxwidth75 right" type="number" name="position" value="'.((int) $object->position).'"></td></tr>';
print '</table>';
print '</div>';

print '<div class="center">';
print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
print ' <a class="button button-cancel" href="'.dol_buildpath('/powerplantpv/admin/maintenance_report_templates.php', 1).'">'.$langs->trans('Cancel').'</a>';
print '</div>';
print '</form>';

if ($id > 0) {
	print '<div class="tabsAction">';
	print dolGetButtonAction($langs->trans('Modify'), '', 'default', $_SERVER['PHP_SELF'].'?id='.(int) $id.'&action=edit', '', true);
	if (empty($object->is_default)) {
		print dolGetButtonAction($langs->trans('SetAsDefault'), '', 'default', $_SERVER['PHP_SELF'].'?id='.(int) $id.'&action=setdefault&token='.newToken(), '', true);
	}
	if (!empty($object->active)) {
		print dolGetButtonAction($langs->trans('Disable'), '', 'default', $_SERVER['PHP_SELF'].'?id='.(int) $id.'&action=disable&token='.newToken(), '', true);
	}
	print dolGetButtonAction($langs->trans('Delete'), '', 'delete', $_SERVER['PHP_SELF'].'?id='.(int) $id.'&action=delete&token='.newToken(), '', true);
	print '</div>';
}

powerplantpvReportTemplateAdminFooter();
