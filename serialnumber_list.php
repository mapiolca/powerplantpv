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
$lineid = GETPOSTINT('lineid');
$fk_product = GETPOSTINT('fk_product');
$fk_categorie = GETPOSTINT('fk_categorie');
$export = GETPOST('export', 'alpha');
$massaction = GETPOST('massaction', 'alpha');
$toselect = GETPOST('toselect', 'array:int');

$limit = GETPOSTINT('limit');
if (empty($limit) || $limit < 1) {
	$limit = $conf->liste_limit;
}
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page < 0) {
	$page = 0;
}
$offset = $limit * $page;

$sortfieldlist = array(
	'cpv.label',
	'p.ref',
	'p.label',
	'sn.serial_number',
	'sn.datec',
	'u.lastname',
	'sn.source_file',
	'sn.rowid',
);
$sortfields = array_filter(array_map('trim', explode(',', $sortfield)));
if (empty($sortfields) || count(array_diff($sortfields, $sortfieldlist)) > 0) {
	$sortfield = 'cpv.label,p.ref,sn.serial_number';
	$sortorder = '';
} else {
	$sortfield = implode(',', $sortfields);
}
$sortorders = array_filter(array_map('trim', explode(',', $sortorder)));
foreach ($sortorders as $key => $val) {
	$sortorders[$key] = strtoupper($val);
}
if (empty($sortorders) || count(array_diff($sortorders, array('ASC', 'DESC'))) > 0) {
	$sortorder = 'ASC,ASC,ASC';
} else {
	$sortorder = implode(',', $sortorders);
}

$object = new PowerPlant($db);
$form = new Form($db);

$enablepermissioncheck = getDolGlobalInt('POWERPLANTPV_ENABLE_PERMISSION_CHECK');
if ($enablepermissioncheck) {
	$permissiontoread = $user->hasRight('powerplantpv', 'powerplant', 'read');
	$permissiontoadd = $user->hasRight('powerplantpv', 'powerplant', 'write');
	$permissiontoserialread = $user->hasRight('powerplantpv', 'serialnumber', 'read');
	$permissiontoserialimport = $user->hasRight('powerplantpv', 'serialnumber', 'import');
	$permissiontoserialdelete = $user->hasRight('powerplantpv', 'serialnumber', 'delete');
	$permissiontoserialexport = $user->hasRight('powerplantpv', 'serialnumber', 'export');
} else {
	$permissiontoread = 1;
	$permissiontoadd = 1;
	$permissiontoserialread = 1;
	$permissiontoserialimport = 1;
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
$serialimportcategories = powerplantpvSerialImportFetchCompositionCategories($object);
$canimportserialnumbers = ($permissiontoadd && $permissiontoserialimport && (int) $object->status !== (int) $object::STATUS_CANCELED && !empty($serialimportcategories));
if ($action === 'serialimport') {
	if (!$canimportserialnumbers) {
		accessforbidden();
	}
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
}

$filters = array(
	'lineid' => $lineid,
	'fk_product' => $fk_product,
	'fk_categorie' => $fk_categorie,
);

$selectedids = array_map('intval', (array) $toselect);
$selectedids = array_filter($selectedids, function ($id) {
	return ($id > 0);
});
$selectedids = array_values(array_unique($selectedids));
if (GETPOST('cancel', 'alpha')) {
	$action = 'list';
	$massaction = '';
	$selectedids = array();
}

$redirectparam = 'id='.(int) $object->id;
if ($lineid > 0) {
	$redirectparam .= '&lineid='.(int) $lineid;
}
if ($fk_product > 0) {
	$redirectparam .= '&fk_product='.(int) $fk_product;
}
if ($fk_categorie > 0) {
	$redirectparam .= '&fk_categorie='.(int) $fk_categorie;
}
if ($limit > 0) {
	$redirectparam .= '&limit='.(int) $limit;
}

if (GETPOSTINT('confirmmassaction') && GETPOSTINT('massaction_confirmed') && $massaction === 'massdelete' && $permissiontoserialdelete && !GETPOST('cancel', 'alpha')) {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
	$resultdelete = powerplantpvSerialNumberDeleteByIds($object, $selectedids);
	if ($resultdelete >= 0) {
		setEventMessages($langs->trans('SerialNumbersDeletedCount', $resultdelete), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?'.$redirectparam);
		exit;
	}
	setEventMessages($object->error, null, 'errors');
}

if (($action === '' || $action === 'view' || $action === 'list') && $massaction === 'massdelete' && !empty($selectedids) && $permissiontoserialdelete) {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
	$action = 'massdelete';
}

$where = powerplantpvSerialNumberBuildFilterWhere($object, $filters);
$sqlselect = "SELECT sn.rowid, sn.serial_number, sn.source_file, sn.import_batch, sn.datec, p.ref as product_ref, p.label as product_label,";
$sqlselect .= " cpv.label as category_label, u.rowid as user_id, u.login as user_login, u.lastname, u.firstname";
$sqlfrom = " FROM ".$db->prefix()."powerplantpv_serialnumber as sn";
$sqlfrom .= " INNER JOIN ".$db->prefix()."product as p ON p.rowid = sn.fk_product";
$sqlfrom .= " INNER JOIN ".$db->prefix()."c_powerplantpv_categorypv as cpv ON cpv.rowid = sn.fk_categorie";
$sqlfrom .= " LEFT JOIN ".$db->prefix()."user as u ON u.rowid = sn.fk_user_creat";
$sqlbase = $sqlselect.$sqlfrom.$where;
$sqlordered = $sqlbase.$db->order($sortfield, $sortorder);

if ($export === 'csv' && $permissiontoserialexport) {
	$resqlexport = $db->query($sqlordered);
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
	if ($resqlexport) {
		while ($obj = $db->fetch_object($resqlexport)) {
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
	} else {
		$resqlexport = $db->query($sqlordered);
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
		if ($resqlexport) {
			while ($obj = $db->fetch_object($resqlexport)) {
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

$sqlcount = "SELECT COUNT(sn.rowid) as nb FROM ".$db->prefix()."powerplantpv_serialnumber as sn".$where;
$rescount = $db->query($sqlcount);
$nbtotalofrecords = 0;
if ($rescount) {
	$objcount = $db->fetch_object($rescount);
	$nbtotalofrecords = (int) $objcount->nb;
}

$sql = $sqlordered.$db->plimit($limit + 1, $offset);
$resql = $db->query($sql);

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

$param = $redirectparam;

if ($action === 'massdelete' && $permissiontoserialdelete && !empty($selectedids)) {
	print '<div id="dialog-massdelete-serialnumbers" class="hideobject">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="id" value="'.((int) $object->id).'">';
	print '<input type="hidden" name="lineid" value="'.((int) $lineid).'">';
	print '<input type="hidden" name="fk_product" value="'.((int) $fk_product).'">';
	print '<input type="hidden" name="fk_categorie" value="'.((int) $fk_categorie).'">';
	print '<input type="hidden" name="limit" value="'.((int) $limit).'">';
	print '<input type="hidden" name="action" value="list">';
	print '<input type="hidden" name="massaction" value="massdelete">';
	print '<input type="hidden" name="confirmmassaction" value="1">';
	print '<input type="hidden" name="massaction_confirmed" value="1">';
	foreach ($selectedids as $selectedid) {
		print '<input type="hidden" name="toselect[]" value="'.((int) $selectedid).'">';
	}
	print '<p>'.$langs->trans('SerialNumbersConfirmDeleteSelected', count($selectedids)).'</p>';
	print '<div class="center">';
	print '<input type="submit" class="button button-delete" value="'.$langs->trans('Delete').'">';
	print ' <input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'">';
	print '</div>';
	print '</form>';
	print '</div>';
	print '<script nonce="'.getNonce().'">jQuery(function(){jQuery("#dialog-massdelete-serialnumbers").dialog({autoOpen:true,modal:true,width:550,title:"'.dol_escape_js($langs->transnoentitiesnoconv('SerialNumbersDeleteSelected')).'"});});</script>';
}

$arrayofmassactions = array();
if ($permissiontoserialdelete) {
	$arrayofmassactions['massdelete'] = img_picto('', 'delete', 'class="pictofixedwidth"').$langs->trans('SerialNumbersDeleteSelected');
}
$massactionbutton = '';
if (!empty($arrayofmassactions)) {
	$massactionbutton = $form->selectMassAction('', $arrayofmassactions);
}
$showmassactions = !empty($arrayofmassactions);

$buttons = '';
if ($permissiontoserialexport) {
	$buttons .= dolGetButtonTitle($langs->trans('Export').' CSV', '', 'fa fa-download', $_SERVER['PHP_SELF'].'?'.$param.'&export=csv', '', 1);
	if (powerplantpvSerialImportIsXlsxAvailable()) {
		$buttons .= dolGetButtonTitle($langs->trans('Export').' XLSX', '', 'fa fa-download', $_SERVER['PHP_SELF'].'?'.$param.'&export=xlsx', '', 1);
	}
}

print '<form method="POST" id="serialNumberListForm" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="id" value="'.((int) $object->id).'">';
print '<input type="hidden" name="lineid" value="'.((int) $lineid).'">';
print '<input type="hidden" name="fk_product" value="'.((int) $fk_product).'">';
print '<input type="hidden" name="fk_categorie" value="'.((int) $fk_categorie).'">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
print '<input type="hidden" name="page" value="'.((int) $page).'">';
print '<input type="hidden" name="limit" value="'.((int) $limit).'">';

$missingsummary = powerplantpvSerialNumberFetchMissingSummary($object);
$missingrows = (!empty($missingsummary['missing_rows']) && is_array($missingsummary['missing_rows']) ? $missingsummary['missing_rows'] : array());
$firstmissingcategory = (!empty($missingsummary['first_missing_category']) ? (int) $missingsummary['first_missing_category'] : 0);
$missingtitlebuttons = dolGetButtonTitle($langs->trans('SerialNumbersBackToComposition'), '', 'fa fa-arrow-left', dol_buildpath('/powerplantpv/powerplant_composition.php', 1).'?id='.(int) $object->id, '', 1);
print load_fiche_titre($langs->trans('SerialNumbersMissingSummary'), $missingtitlebuttons, 'fa-exclamation-triangle');

if ((int) $missingsummary['expected_qty'] <= 0) {
	print '<div class="opacitymedium">'.dol_escape_htmltag($langs->transnoentities('SerialNumbersNoSerializableEquipment')).'</div>';
} elseif ((int) $missingsummary['missing_qty'] <= 0) {
	print '<div class="opacitymedium">'.dol_escape_htmltag($langs->transnoentities('SerialNumbersAllRecorded')).'</div>';
} else {
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('Category').'</td>';
	print '<td>'.$langs->trans('Product').'</td>';
	print '<td class="right">'.$langs->trans('SerialNumbersMissingQty').'</td>';
	print '</tr>';
	foreach ($missingrows as $missingrow) {
		$expectedqty = max(0, (int) $missingrow['expected_qty']);
		$missingqty = max(0, (int) $missingrow['missing_qty']);
		$missingratio = ($expectedqty > 0 ? ($missingqty / $expectedqty) : 0);
		$badgeclass = ($missingratio >= 0.5 ? 'badge-status8' : 'badge-status1');
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($missingrow['category_label']).'</td>';
		print '<td>'.dol_escape_htmltag($missingrow['product_display']).'</td>';
		print '<td class="right"><span class="badge '.$badgeclass.'">'.$missingqty.' / '.$expectedqty.'</span></td>';
		print '</tr>';
	}
	print '</table>';
	print '</div>';
}
if ((int) $missingsummary['missing_qty'] > 0 && $firstmissingcategory > 0 && $canimportserialnumbers) {
	print '<div class="tabsAction">';
	print dolGetButtonAction(
		$langs->trans('SerialNumbersImportMissing'),
		'',
		'default',
		$_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=serialimport&token='.newToken().'&fk_categorie='.$firstmissingcategory,
		'',
		true
	);
	print '</div>';
}
print '<br>';

print_barre_liste($langs->trans('SerialNumbers'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, $massactionbutton, $nbtotalofrecords, $nbtotalofrecords, 'barcode', 0, $buttons, '', $limit, 0, 0, 1);

print '<div class="div-table-responsive">';
print '<table class="tagtable liste centpercent">';
print '<tr class="liste_titre">';
if ($showmassactions) {
	print_liste_field_titre($form->showCheckAddButtons('checkforselect', 1), $_SERVER['PHP_SELF'], '', '', $param, 'class="center"', $sortfield, $sortorder);
}
print_liste_field_titre($langs->trans('Category'), $_SERVER['PHP_SELF'], 'cpv.label', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Ref'), $_SERVER['PHP_SELF'], 'p.ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Label'), $_SERVER['PHP_SELF'], 'p.label', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('PowerPlantSerialNumber'), $_SERVER['PHP_SELF'], 'sn.serial_number', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('DateCreation'), $_SERVER['PHP_SELF'], 'sn.datec', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('User'), $_SERVER['PHP_SELF'], 'u.lastname', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('SerialNumbersSourceFile'), $_SERVER['PHP_SELF'], 'sn.source_file', '', $param, '', $sortfield, $sortorder);
print '</tr>';

if ($resql && $db->num_rows($resql) > 0) {
	$num = min($db->num_rows($resql), $limit);
	$i = 0;
	while ($i < $num) {
		$obj = $db->fetch_object($resql);
		$username = trim((string) $obj->firstname.' '.(string) $obj->lastname);
		if ($username === '') {
			$username = (string) $obj->user_login;
		}
		print '<tr class="oddeven">';
		if ($showmassactions) {
			print '<td class="center">';
			print '<input class="flat checkforselect" type="checkbox" name="toselect[]" value="'.((int) $obj->rowid).'">';
			print '</td>';
		}
		print '<td>'.dol_escape_htmltag($obj->category_label).'</td>';
		print '<td>'.dol_escape_htmltag($obj->product_ref).'</td>';
		print '<td>'.dol_escape_htmltag($obj->product_label).'</td>';
		print '<td>'.dol_escape_htmltag($obj->serial_number).'</td>';
		print '<td>'.(!empty($obj->datec) ? dol_print_date($db->jdate($obj->datec), 'dayhour') : '').'</td>';
		print '<td>'.dol_escape_htmltag($username).'</td>';
		print '<td>'.dol_escape_htmltag($obj->source_file).'</td>';
		print '</tr>';
		$i++;
	}
} else {
	print '<tr class="oddeven"><td colspan="'.($showmassactions ? 8 : 7).'"><span class="opacitymedium">'.$langs->trans('None').'</span></td></tr>';
}
print '</table>';
print '</div>';
print '</form>';
if ($canimportserialnumbers) {
	powerplantpvSerialImportPrintDialog($object, $firstmissingcategory, ($action === 'serialimport'));
}
print '</div>';
print dol_get_fiche_end();

llxFooter();
$db->close();
