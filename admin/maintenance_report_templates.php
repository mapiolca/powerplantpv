<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		admin/maintenance_report_templates.php
 * \ingroup		powerplantpv
 * \brief		Report template administration list.
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

$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');
$confirm = GETPOST('confirm', 'alpha');
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

if (in_array($action, array('disable', 'setdefault', 'duplicate', 'moveup', 'movedown'), true)) {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
	if ($id <= 0 || $object->fetch($id) <= 0) {
		accessforbidden();
	}
	if ($action === 'disable') {
		$result = $object->disable($user);
		if ($result < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		} else {
			setEventMessages($langs->trans('PowerPlantPVReportTemplateDisabled'), null, 'mesgs');
		}
	} elseif ($action === 'setdefault') {
		$result = $object->setDefault($user);
		if ($result < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		} else {
			setEventMessages($langs->trans('PowerPlantPVReportTemplateSetDefault'), null, 'mesgs');
		}
	} elseif ($action === 'duplicate') {
		$baseCode = strtoupper((string) $object->code).'_COPY';
		$newCode = $baseCode;
		$suffix = 1;
		$probe = new PowerPlantPVReportTemplate($db);
		while ($probe->fetchByCode($newCode) > 0) {
			$suffix++;
			$newCode = $baseCode.'_'.$suffix;
		}
		$result = $object->duplicateTemplate($user, $newCode, $object->label.' - '.$langs->trans('Copy'));
		if ($result < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		} else {
			setEventMessages($langs->trans('PowerPlantPVReportTemplateDuplicated'), null, 'mesgs');
		}
	} elseif ($action === 'moveup' || $action === 'movedown') {
		$result = powerplantpvReportTemplateMoveRow('powerplantpv_report_template', $id, $action === 'moveup' ? 'up' : 'down');
		if ($result < 0) {
			setEventMessages($langs->trans('Error'), null, 'errors');
		}
	}
}

$param = '';
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
if ($search !== '') {
	$safeSearch = $db->escape($search);
	$where .= " AND (t.code LIKE '%".$safeSearch."%' OR t.label LIKE '%".$safeSearch."%')";
}
if ($search_active !== '') {
	$where .= " AND t.active = ".((int) $search_active);
}

$sql = "SELECT COUNT(*) as nb FROM ".$db->prefix()."powerplantpv_report_template as t".$where;
$resql = $db->query($sql);
$num = 0;
if ($resql) {
	$obj = $db->fetch_object($resql);
	$num = is_object($obj) ? (int) $obj->nb : 0;
	$db->free($resql);
}

$allowedSorts = array('code', 'label', 'target_element', 'is_default', 'active', 'position');
if (!in_array($sortfield, $allowedSorts, true)) {
	$sortfield = 'position';
}
if (!in_array(strtoupper($sortorder), array('ASC', 'DESC'), true)) {
	$sortorder = 'ASC';
}

$sql = "SELECT t.rowid, t.code, t.label, t.description, t.target_element, t.is_default, t.active, t.position";
$sql .= " FROM ".$db->prefix()."powerplantpv_report_template as t";
$sql .= $where;
$sql .= " ORDER BY t.".$db->sanitize($sortfield)." ".strtoupper($sortorder).", t.rowid ASC";
$sql .= $db->plimit($limit + 1, $offset);
$resql = $db->query($sql);

$title = $langs->trans('PowerPlantPVReportTemplates');
powerplantpvReportTemplateAdminHeader($title, 'maintenance_report_templates');

$newUrl = dol_buildpath('/powerplantpv/admin/maintenance_report_template_card.php', 1).'?action=create';
print '<div class="tabsAction">';
print dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', $newUrl, '', 1);
print '</div>';

print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $num, 'object_powerplantpv@powerplantpv', 0, '', '', $limit);

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste listwithfilterbefore centpercent">';
print '<tr class="liste_titre_filter">';
print '<td><input class="flat maxwidth150" type="text" name="search" value="'.dol_escape_htmltag($search).'"></td>';
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
print_liste_field_titre('PowerPlantPVReportTargetElement', $_SERVER['PHP_SELF'], 'target_element', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Status', $_SERVER['PHP_SELF'], 'active', '', $param, 'class="center"', $sortfield, $sortorder);
print_liste_field_titre('Position', $_SERVER['PHP_SELF'], 'position', '', $param, 'class="center"', $sortfield, $sortorder);
print '<th class="center">'.$langs->trans('Actions').'</th>';
print '</tr>';

if ($resql) {
	$i = 0;
	while ($i < min($num, $limit) && ($obj = $db->fetch_object($resql))) {
		$cardUrl = dol_buildpath('/powerplantpv/admin/maintenance_report_template_card.php', 1).'?id='.(int) $obj->rowid;
		print '<tr class="oddeven">';
		print '<td><a href="'.$cardUrl.'">'.dol_escape_htmltag((string) $obj->code).'</a>';
		if (!empty($obj->is_default)) {
			print ' <span class="badge badge-status4">'.$langs->trans('Default').'</span>';
		}
		print '</td>';
		print '<td>'.dol_escape_htmltag((string) $obj->label).'</td>';
		print '<td>'.dol_escape_htmltag((string) $obj->target_element).'</td>';
		print '<td class="center"><span class="badge '.(!empty($obj->active) ? 'badge-status4' : 'badge-status8').'">'.$langs->trans(!empty($obj->active) ? 'Enabled' : 'Disabled').'</span></td>';
		print '<td class="center">'.((int) $obj->position).'</td>';
		print '<td class="center nowraponall">';
		print '<a href="'.$cardUrl.'">'.img_edit($langs->trans('Modify')).'</a>';
		print ' <a href="'.$_SERVER['PHP_SELF'].'?action=moveup&id='.(int) $obj->rowid.'&token='.newToken().$param.'">'.img_picto($langs->trans('MoveUp'), 'uparrow').'</a>';
		print ' <a href="'.$_SERVER['PHP_SELF'].'?action=movedown&id='.(int) $obj->rowid.'&token='.newToken().$param.'">'.img_picto($langs->trans('MoveDown'), 'downarrow').'</a>';
		print ' <a href="'.$_SERVER['PHP_SELF'].'?action=duplicate&id='.(int) $obj->rowid.'&token='.newToken().$param.'">'.img_picto($langs->trans('Duplicate'), 'copy').'</a>';
		if (empty($obj->is_default)) {
			print ' <a href="'.$_SERVER['PHP_SELF'].'?action=setdefault&id='.(int) $obj->rowid.'&token='.newToken().$param.'">'.img_picto($langs->trans('SetAsDefault'), 'check').'</a>';
		}
		if (!empty($obj->active)) {
			print ' <a href="'.$_SERVER['PHP_SELF'].'?action=disable&id='.(int) $obj->rowid.'&token='.newToken().$param.'">'.img_picto($langs->trans('Disable'), 'switch_off').'</a>';
		}
		print '</td>';
		print '</tr>';
		$i++;
	}
	$db->free($resql);
}
if (empty($num)) {
	powerplantpvPrintNoRecordFound(6);
}

print '</table>';
print '</div>';
print '</form>';

powerplantpvReportTemplateAdminFooter();
