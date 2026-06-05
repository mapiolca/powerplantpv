<?php
/* Copyright (C) 2026		Pierre Ardoin			<developpeur@lesmetiersdubatiment.fr>
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
 * \file		serialnumber_list.php
 * \ingroup		powerplantpv
 * \brief		Serial number list and export for power plant composition.
 */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)).'/main.inc.php')) {
	$res = @include substr($tmp, 0, ($i + 1)).'/main.inc.php';
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php')) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php';
}
if (!$res && file_exists('../main.inc.php')) {
	$res = @include '../main.inc.php';
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

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/powerplantpv/class/powerplant.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv_powerplant.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_serialnumber.lib.php');

$langs->loadLangs(array('powerplantpv@powerplantpv', 'products', 'other'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$lineid = GETPOSTINT('lineid');
$fk_product = GETPOSTINT('fk_product');
$fk_categorie = GETPOSTINT('fk_categorie');
$export = GETPOST('export', 'alpha');

$object = new PowerPlant($db);
$form = new Form($db);

$enablepermissioncheck = getDolGlobalInt('POWERPLANTPV_ENABLE_PERMISSION_CHECK');
if ($enablepermissioncheck) {
	$permissiontoread = $user->hasRight('powerplantpv', 'powerplant', 'read');
	$permissiontoserialread = $user->hasRight('powerplantpv', 'serialnumber', 'read');
	$permissiontoserialdelete = $user->hasRight('powerplantpv', 'serialnumber', 'delete');
	$permissiontoserialexport = $user->hasRight('powerplantpv', 'serialnumber', 'export');
} else {
	$permissiontoread = 1;
	$permissiontoserialread = 1;
	$permissiontoserialdelete = 1;
	$permissiontoserialexport = 1;
}

if (!isModEnabled('powerplantpv') || !$permissiontoread || !$permissiontoserialread) {
	accessforbidden();
}

include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';
if (empty($object->id)) {
	accessforbidden();
}

$powerplantentity = (!empty($object->entity) ? (int) $object->entity : (int) $conf->entity);
$isdraft = (isset($object->status) && ($object->status == $object::STATUS_DRAFT) ? 1 : 0);
restrictedArea($user, $object->module, $object, $object->table_element, $object->element, 'fk_soc', 'rowid', $isdraft);

$filters = array(
	'lineid' => $lineid,
	'fk_product' => $fk_product,
	'fk_categorie' => $fk_categorie,
);

if ($action === 'confirm_deletefilter' && $confirm === 'yes' && $permissiontoserialdelete) {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
	$resultdelete = powerplantpvSerialNumberDeleteByFilter($object, $filters);
	if ($resultdelete >= 0) {
		setEventMessages($langs->trans('SerialNumbersDeletedCount', $resultdelete), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $object->id);
		exit;
	}
	setEventMessages($object->error, null, 'errors');
}

$where = powerplantpvSerialNumberBuildFilterWhere($object, $filters);
$sql = "SELECT sn.rowid, sn.serial_number, sn.source_file, sn.import_batch, sn.datec, p.ref as product_ref, p.label as product_label,";
$sql .= " cpv.label as category_label, u.rowid as user_id, u.login as user_login, u.lastname, u.firstname";
$sql .= " FROM ".$db->prefix()."powerplantpv_serialnumber as sn";
$sql .= " INNER JOIN ".$db->prefix()."product as p ON p.rowid = sn.fk_product";
$sql .= " INNER JOIN ".$db->prefix()."c_powerplantpv_categorypv as cpv ON cpv.rowid = sn.fk_categorie";
$sql .= " LEFT JOIN ".$db->prefix()."user as u ON u.rowid = sn.fk_user_creat";
$sql .= $where;
$sql .= " ORDER BY cpv.label ASC, p.ref ASC, sn.serial_number ASC";

$resql = $db->query($sql);

if ($export === 'csv' && $permissiontoserialexport) {
	$filename = dol_sanitizeFileName($object->ref.'-serialnumbers.csv');
	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	$out = fopen('php://output', 'wb');
	fputs($out, "\xEF\xBB\xBF");
	fputcsv($out, array(
		$langs->transnoentitiesnoconv('PowerPlant'),
		$langs->transnoentitiesnoconv('Category'),
		$langs->transnoentitiesnoconv('Ref'),
		$langs->transnoentitiesnoconv('Label'),
		$langs->transnoentitiesnoconv('PowerPlantSerialNumber'),
		$langs->transnoentitiesnoconv('DateCreation'),
		$langs->transnoentitiesnoconv('User'),
		$langs->transnoentitiesnoconv('SerialNumbersSourceFile'),
	), ';');
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$username = trim((string) $obj->firstname.' '.(string) $obj->lastname);
			if ($username === '') {
				$username = (string) $obj->user_login;
			}
			fputcsv($out, array(
				$object->ref,
				$obj->category_label,
				$obj->product_ref,
				$obj->product_label,
				$obj->serial_number,
				(!empty($obj->datec) ? dol_print_date($db->jdate($obj->datec), 'dayhour') : ''),
				$username,
				$obj->source_file,
			), ';');
		}
	}
	fclose($out);
	exit;
}

if ($export === 'xlsx' && $permissiontoserialexport) {
	if (!powerplantpvSerialImportLoadPhpSpreadsheet()) {
		setEventMessages($langs->trans('SerialNumbersXlsxReaderUnavailable'), null, 'errors');
	} elseif ($resql) {
		$filename = dol_sanitizeFileName($object->ref.'-serialnumbers.xlsx');
		$headers = array(
			$langs->transnoentitiesnoconv('PowerPlant'),
			$langs->transnoentitiesnoconv('Category'),
			$langs->transnoentitiesnoconv('Ref'),
			$langs->transnoentitiesnoconv('Label'),
			$langs->transnoentitiesnoconv('PowerPlantSerialNumber'),
			$langs->transnoentitiesnoconv('DateCreation'),
			$langs->transnoentitiesnoconv('User'),
			$langs->transnoentitiesnoconv('SerialNumbersSourceFile'),
		);
		$data = array();
		while ($obj = $db->fetch_object($resql)) {
			$username = trim((string) $obj->firstname.' '.(string) $obj->lastname);
			if ($username === '') {
				$username = (string) $obj->user_login;
			}
			$data[] = array(
				$object->ref,
				$obj->category_label,
				$obj->product_ref,
				$obj->product_label,
				$obj->serial_number,
				(!empty($obj->datec) ? dol_print_date($db->jdate($obj->datec), 'dayhour') : ''),
				$username,
				$obj->source_file,
			);
		}

		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->fromArray($headers, null, 'A1');
		if (!empty($data)) {
			$sheet->fromArray($data, null, 'A2');
		}

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->save('php://output');
		exit;
	}
}

if (!$resql) {
	dol_print_error($db);
}

$title = $langs->trans('SerialNumbers');
llxHeader('', $title, '');

$object->fetch_thirdparty();
$head = powerplantPrepareHead($object);
print dol_get_fiche_head($head, 'composition', $langs->trans('PowerPlant'), -1, $object->picto);

$linkback = powerplantGetBackToListLink($object);
$morehtmlref = powerplantBuildBannerMoreHtml($object, false, $action);
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

if ($action === 'deletefilter' && $permissiontoserialdelete) {
	$formconfirm = $form->formconfirm(
		$_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&lineid='.(int) $lineid.'&fk_product='.(int) $fk_product.'&fk_categorie='.(int) $fk_categorie,
		$langs->trans('Delete'),
		$langs->trans('SerialNumbersConfirmDeleteSelection'),
		'confirm_deletefilter',
		'',
		0,
		1
	);
	print $formconfirm;
}

$param = 'id='.(int) $object->id;
if ($lineid > 0) {
	$param .= '&lineid='.(int) $lineid;
}
if ($fk_product > 0) {
	$param .= '&fk_product='.(int) $fk_product;
}
if ($fk_categorie > 0) {
	$param .= '&fk_categorie='.(int) $fk_categorie;
}

$buttons = '';
if ($permissiontoserialexport) {
	$buttons .= dolGetButtonTitle($langs->trans('Export').' CSV', '', 'fa fa-download', $_SERVER['PHP_SELF'].'?'.$param.'&export=csv', '', 1);
	if (powerplantpvSerialImportIsXlsxAvailable()) {
		$buttons .= dolGetButtonTitle($langs->trans('Export').' XLSX', '', 'fa fa-download', $_SERVER['PHP_SELF'].'?'.$param.'&export=xlsx', '', 1);
	}
}
if ($permissiontoserialdelete) {
	$buttons .= dolGetButtonTitle($langs->trans('Delete'), '', 'fa fa-trash', $_SERVER['PHP_SELF'].'?'.$param.'&action=deletefilter&token='.newToken(), '', 1);
}

print_barre_liste($langs->trans('SerialNumbers'), 0, $_SERVER['PHP_SELF'], $param, '', '', '', 0, 0, 'barcode', 0, $buttons, '', $conf->liste_limit, 0, 0, 1);

print '<div class="div-table-responsive">';
print '<table class="tagtable liste centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Category').'</td>';
print '<td>'.$langs->trans('Ref').'</td>';
print '<td>'.$langs->trans('Label').'</td>';
print '<td>'.$langs->trans('PowerPlantSerialNumber').'</td>';
print '<td>'.$langs->trans('DateCreation').'</td>';
print '<td>'.$langs->trans('User').'</td>';
print '<td>'.$langs->trans('SerialNumbersSourceFile').'</td>';
print '</tr>';

if ($resql && $db->num_rows($resql) > 0) {
	while ($obj = $db->fetch_object($resql)) {
		$username = trim((string) $obj->firstname.' '.(string) $obj->lastname);
		if ($username === '') {
			$username = (string) $obj->user_login;
		}
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($obj->category_label).'</td>';
		print '<td>'.dol_escape_htmltag($obj->product_ref).'</td>';
		print '<td>'.dol_escape_htmltag($obj->product_label).'</td>';
		print '<td>'.dol_escape_htmltag($obj->serial_number).'</td>';
		print '<td>'.(!empty($obj->datec) ? dol_print_date($db->jdate($obj->datec), 'dayhour') : '').'</td>';
		print '<td>'.dol_escape_htmltag($username).'</td>';
		print '<td>'.dol_escape_htmltag($obj->source_file).'</td>';
		print '</tr>';
	}
} else {
	print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('None').'</span></td></tr>';
}
print '</table>';
print '</div>';
print '</div>';
print dol_get_fiche_end();

llxFooter();
$db->close();
