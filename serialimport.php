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
 * \file		serialimport.php
 * \ingroup		powerplantpv
 * \brief		Serial number import page for power plant composition.
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
dol_include_once('/powerplantpv/class/powerplant.class.php');
dol_include_once('/powerplantpv/class/powerplantpvserialnumberimport.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv_powerplant.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_serialnumber.lib.php');

$langs->loadLangs(array('powerplantpv@powerplantpv', 'products', 'other'));

if (!function_exists('powerplantpv_serialimport_check_token')) {
	/**
	 * Cross-version CSRF token check helper.
	 *
	 * @return bool
	 */
	function powerplantpv_serialimport_check_token()
	{
		$token = GETPOST('token', 'alphanohtml');
		if (function_exists('checkToken')) {
			return checkToken();
		}
		if (function_exists('dol_verifyToken')) {
			return dol_verifyToken($token);
		}
		return (!empty($token) && !empty($_SESSION['newtoken']) && $token === $_SESSION['newtoken']);
	}
}

/**
 * Translate row messages.
 *
 * @param	string[]	$messages	Message keys
 * @return	string				Translated HTML
 */
function powerplantpv_serialimport_messages_html($messages)
{
	global $langs;

	$out = array();
	foreach ((array) $messages as $message) {
		$out[] = dol_escape_htmltag($langs->trans($message));
	}

	return (!empty($out) ? implode('<br>', $out) : '&mdash;');
}

$id = GETPOSTINT('id');
$importid = GETPOSTINT('importid');
$action = GETPOST('action', 'aZ09');
$format = GETPOST('format', 'alpha');
$categoryid = GETPOSTINT('fk_categorie');
$mode = GETPOST('import_mode', 'alpha');
$mode = ($mode === 'replace' ? 'replace' : 'add');
$firstlineheaders = ($action === 'uploadserials' ? (GETPOSTISSET('first_line_headers') ? 1 : 0) : (GETPOSTISSET('first_line_headers') ? GETPOSTINT('first_line_headers') : 1));

$object = new PowerPlant($db);
$form = new Form($db);

$enablepermissioncheck = getDolGlobalInt('POWERPLANTPV_ENABLE_PERMISSION_CHECK');
if ($enablepermissioncheck) {
	$permissiontoread = $user->hasRight('powerplantpv', 'powerplant', 'read');
	$permissiontoadd = $user->hasRight('powerplantpv', 'powerplant', 'write');
	$permissiontoserialread = $user->hasRight('powerplantpv', 'serialnumber', 'read');
	$permissiontoserialimport = $user->hasRight('powerplantpv', 'serialnumber', 'import');
} else {
	$permissiontoread = 1;
	$permissiontoadd = 1;
	$permissiontoserialread = 1;
	$permissiontoserialimport = 1;
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

$canimport = ($permissiontoadd && $permissiontoserialimport && (int) $object->status !== (int) $object::STATUS_CANCELED);
$categories = powerplantpvSerialImportFetchCompositionCategories($object);

if ($action === 'downloadtemplate') {
	if (!$canimport) {
		accessforbidden();
	}
	if (!powerplantpv_serialimport_check_token()) {
		accessforbidden('Bad token');
	}
	if (empty($categories[$categoryid])) {
		setEventMessages($langs->trans('SerialNumbersCategoryAbsentFromPowerplant'), null, 'errors');
	} else {
		$template = powerplantpvSerialImportBuildTemplateData($object, $categoryid);
		$headers = (array) $template['headers'];
		$rows = (array) $template['rows'];
		$filenamebase = dol_sanitizeFileName($object->ref.'-'.$categories[$categoryid]['label'].'-serialnumbers-template');

		if ($format === 'csv') {
			header('Content-Type: text/csv; charset=UTF-8');
			header('Content-Disposition: attachment; filename="'.$filenamebase.'.csv"');
			$out = fopen('php://output', 'wb');
			fputs($out, "\xEF\xBB\xBF");
			fputcsv($out, $headers, ';');
			foreach ($rows as $row) {
				$data = array();
				foreach ($headers as $header) {
					$data[] = isset($row[$header]) ? $row[$header] : '';
				}
				fputcsv($out, $data, ';');
			}
			fclose($out);
			exit;
		} elseif ($format === 'xlsx') {
			if (!powerplantpvSerialImportLoadPhpSpreadsheet()) {
				setEventMessages($langs->trans('SerialNumbersXlsxReaderUnavailable'), null, 'errors');
			} else {
				$data = array();
				foreach ($rows as $row) {
					$datarow = array();
					foreach ($headers as $header) {
						$datarow[] = isset($row[$header]) ? $row[$header] : '';
					}
					$data[] = $datarow;
				}

				$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
				$sheet = $spreadsheet->getActiveSheet();
				$sheet->fromArray($headers, null, 'A1');
				if (!empty($data)) {
					$sheet->fromArray($data, null, 'A2');
				}

				header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
				header('Content-Disposition: attachment; filename="'.$filenamebase.'.xlsx"');
				$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
				$writer->save('php://output');
				exit;
			}
		} else {
			setEventMessages($langs->trans('SerialNumbersUnsupportedFileExtension'), null, 'errors');
		}
	}
}

if ($action === 'uploadserials' && $canimport) {
	if (!powerplantpv_serialimport_check_token()) {
		accessforbidden();
	}
	if (empty($categories[$categoryid])) {
		setEventMessages($langs->trans('SerialNumbersCategoryAbsentFromPowerplant'), null, 'errors');
	} elseif (empty($_FILES['serial_file']) || !is_array($_FILES['serial_file'])) {
		setEventMessages($langs->trans('SerialNumbersFileToImport'), null, 'errors');
	} else {
		$uploaded = $_FILES['serial_file'];
		$originalname = dol_sanitizeFileName((string) $uploaded['name']);
		$extension = strtolower(pathinfo($originalname, PATHINFO_EXTENSION));
		if (!in_array($extension, powerplantpvSerialImportAllowedExtensions(), true)) {
			setEventMessages($langs->trans('SerialNumbersUnsupportedFileExtension'), null, 'errors');
		} else {
			$upload_dir = powerplantpvSerialImportGetUploadDir($object);
			dol_mkdir($upload_dir);
			$targetname = dol_print_date(dol_now(), '%Y%m%d%H%M%S').'-'.$originalname;
			$targetpath = $upload_dir.'/'.$targetname;
			$uploadresult = dol_move_uploaded_file($uploaded['tmp_name'], $targetpath, 1, 0, (int) $uploaded['error'], 0);
			if ($uploadresult <= 0 || !is_readable($targetpath)) {
				setEventMessages($langs->trans('SerialNumbersFileNotReadable'), null, 'errors');
			} else {
				$import = new PowerPlantPVSerialNumberImport($db);
				$import->entity = $powerplantentity;
				$import->fk_powerplant = (int) $object->id;
				$import->fk_categorie = $categoryid;
				$import->fk_user = (int) $user->id;
				$import->filename = $originalname;
				$import->filepath = $targetpath;
				$import->status = PowerPlantPVSerialNumberImport::STATUS_DRAFT;
				$import->import_mode = $mode;
				$import->first_line_headers = (!empty($firstlineheaders) ? 1 : 0);
				$import->raw_data_json = json_encode(array('filename' => $originalname, 'extension' => $extension), JSON_UNESCAPED_SLASHES);
				$resultcreate = $import->create($user, 1);
				if ($resultcreate <= 0) {
					setEventMessages($import->error, $import->errors, 'errors');
				} else {
					$read = powerplantpvSerialImportReadFile($targetpath, $extension, (int) $import->first_line_headers);
					$analysis = powerplantpvSerialImportAnalyzeRows($object, $categoryid, $read['rows'], $mode);
					if (!empty($read['errors'])) {
						$analysis['blocking_errors'][] = array('line' => 0, 'messages' => $read['errors']);
						$analysis['has_blocking_errors'] = 1;
					}
					if (!empty($read['warnings'])) {
						$analysis['file_warnings'] = $read['warnings'];
					}
					if (!empty($read['unknown_columns'])) {
						$analysis['unknown_columns'] = $read['unknown_columns'];
					}
					$parsed = array('input_rows' => $read['rows'], 'read' => $read, 'analysis' => $analysis);
					$status = (!empty($read['errors']) ? PowerPlantPVSerialNumberImport::STATUS_ERROR : PowerPlantPVSerialNumberImport::STATUS_CHECKED);
					$resultupdate = powerplantpvSerialImportUpdateBatch($import, $status, $parsed, $analysis);
					if ($resultupdate < 0) {
						setEventMessages($import->error, $import->errors, 'errors');
					} else {
						header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&importid='.(int) $import->id);
						exit;
					}
				}
			}
		}
	}
}

if ($action === 'validateimport' && $canimport && $importid > 0) {
	if (!powerplantpv_serialimport_check_token()) {
		accessforbidden();
	}
	$import = new PowerPlantPVSerialNumberImport($db);
	$resultfetch = $import->fetch($importid);
	if ($resultfetch <= 0 || (int) $import->fk_powerplant !== (int) $object->id || (int) $import->entity !== $powerplantentity) {
		accessforbidden();
	}
	$assignments = GETPOST('line_assignment', 'array:int');
	if (!is_array($assignments)) {
		$assignments = array();
	}
	$resultvalidate = powerplantpvSerialImportValidateBatch($object, $import, $assignments, $user);
	if (is_array($resultvalidate)) {
		setEventMessages($langs->trans('SerialNumbersImportedForCategory', (int) $resultvalidate['inserted'], $resultvalidate['analysis']['summary']['category_label']), null, 'mesgs');
		header('Location: '.dol_buildpath('/powerplantpv/powerplant_composition.php', 1).'?id='.(int) $object->id);
		exit;
	}
	setEventMessages($import->error, $import->errors, 'errors');
}

if ($action === 'cancelimport' && $canimport && $importid > 0) {
	if (!powerplantpv_serialimport_check_token()) {
		accessforbidden();
	}
	$import = new PowerPlantPVSerialNumberImport($db);
	$resultfetch = $import->fetch($importid);
	if ($resultfetch <= 0 || (int) $import->fk_powerplant !== (int) $object->id || (int) $import->entity !== $powerplantentity) {
		accessforbidden();
	}
	$resultcancel = powerplantpvSerialImportCancelBatch($import);
	if ($resultcancel > 0) {
		setEventMessages($langs->trans('SerialNumbersImportCancelled'), null, 'mesgs');
		header('Location: '.dol_buildpath('/powerplantpv/powerplant_composition.php', 1).'?id='.(int) $object->id);
		exit;
	}
	setEventMessages($import->error, $import->errors, 'errors');
}

$title = $langs->trans('SerialNumbersImport');
llxHeader('', $title, '');

$object->fetch_thirdparty();
$head = powerplantPrepareHead($object);
print dol_get_fiche_head($head, 'composition', $langs->trans('PowerPlant'), -1, $object->picto);

$linkback = powerplantGetBackToListLink($object);
$morehtmlref = powerplantBuildBannerMoreHtml($object, $permissiontoadd, $action);
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

if ($importid > 0) {
	$import = new PowerPlantPVSerialNumberImport($db);
	$resultfetch = $import->fetch($importid);
	if ($resultfetch <= 0 || (int) $import->fk_powerplant !== (int) $object->id || (int) $import->entity !== $powerplantentity) {
		print '<div class="error">'.$langs->trans('ErrorRecordNotFound').'</div>';
	} else {
		$parsed = $import->getParsedData();
		$analysis = !empty($parsed['analysis']) && is_array($parsed['analysis']) ? $parsed['analysis'] : array();
		$summary = !empty($analysis['summary']) && is_array($analysis['summary']) ? $analysis['summary'] : array();
		$rows = !empty($analysis['rows']) && is_array($analysis['rows']) ? $analysis['rows'] : array();
		$lines = powerplantpvSerialImportFetchCategoryLines((int) $object->id, (int) $import->fk_categorie, $powerplantentity);
		$lineoptions = array();
		foreach ($lines as $lineid => $line) {
			$label = $line['product_display'].' #'.$lineid;
			$recordedqty = powerplantpvSerialImportLineRecordedQty($line);
			if ($recordedqty > 0) {
				$label .= ' - '.$langs->trans('SerialNumbersRecordedQty').': '.$recordedqty.' / '.((int) $line['qty']);
			}
			$lineoptions[(int) $lineid] = $label;
		}

		print load_fiche_titre($langs->trans('SerialNumbersImportSummary'), '', 'fa-file-import');
		print '<div class="div-table-responsive-no-min">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('SerialNumbersImportSummary').'</td></tr>';
		print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('PowerPlant').'</td><td>'.$object->getNomUrl(1).'</td></tr>';
		print '<tr class="oddeven"><td>'.$langs->trans('SerialNumbersCategoryToImport').'</td><td>'.dol_escape_htmltag((string) ($summary['category_label'] ?? '')).'</td></tr>';
		print '<tr class="oddeven"><td>'.$langs->trans('SerialNumbersFileToImport').'</td><td>'.dol_escape_htmltag($import->filename).'</td></tr>';
		print '<tr class="oddeven"><td>'.$langs->trans('SerialNumbersDetectedRows').'</td><td>'.((int) ($summary['lines_detected'] ?? 0)).'</td></tr>';
		print '<tr class="oddeven"><td>'.$langs->trans('SerialNumbersValidQty').'</td><td>'.((int) ($summary['valid_numbers'] ?? 0)).'</td></tr>';
		print '<tr class="oddeven"><td>'.$langs->trans('SerialNumbersDuplicateInFile').'</td><td>'.((int) ($summary['duplicates_file'] ?? 0)).'</td></tr>';
		print '<tr class="oddeven"><td>'.$langs->trans('SerialNumbersDuplicateInPowerplant').'</td><td>'.((int) ($summary['duplicates_powerplant'] ?? 0)).'</td></tr>';
		print '<tr class="oddeven"><td>'.$langs->trans('SerialNumbersExpectedQty').'</td><td>'.((int) ($summary['expected_qty'] ?? 0)).'</td></tr>';
		print '<tr class="oddeven"><td>'.$langs->trans('SerialNumbersGap').'</td><td>'.((int) ($summary['gap'] ?? 0)).'</td></tr>';
		if (($summary['mode'] ?? '') === 'replace') {
			print '<tr class="oddeven"><td>'.$langs->trans('SerialNumbersReplaceExisting').'</td><td>'.$langs->trans('SerialNumbersExistingDeletedCount', (int) ($summary['replace_existing_count'] ?? 0)).'</td></tr>';
		}
		print '</table>';
		print '</div>';
		print '<br>';

		if (!empty($analysis['file_warnings'])) {
			print '<div class="warning">';
			foreach ($analysis['file_warnings'] as $warningkey) {
				print dol_escape_htmltag($langs->trans($warningkey)).'<br>';
			}
			print '</div>';
			print '<br>';
		}

		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&importid='.(int) $import->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="validateimport">';
		print '<div class="div-table-responsive">';
		print '<table class="tagtable liste centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans('Status').'</td>';
		print '<td class="right">'.$langs->trans('Line').'</td>';
		print '<td>'.$langs->trans('SerialNumbersDetectedProduct').'</td>';
		print '<td>'.$langs->trans('PowerPlantSerialNumber').'</td>';
		print '<td>'.$langs->trans('SerialNumbersProposedAssociation').'</td>';
		print '<td>'.$langs->trans('Message').'</td>';
		print '</tr>';
		if (!empty($rows)) {
			foreach ($rows as $row) {
				$status = (string) $row['status'];
				$badgeclass = ($status === 'error' ? 'badge-status8' : ($status === 'warning' ? 'badge-status1' : 'badge-status4'));
				print '<tr class="oddeven">';
				print '<td><span class="badge '.$badgeclass.'">'.$langs->trans('SerialNumbersStatus'.ucfirst($status)).'</span></td>';
				print '<td class="right">'.((int) $row['line']).'</td>';
				print '<td>'.dol_escape_htmltag((string) $row['product_ref']).'</td>';
				print '<td>'.dol_escape_htmltag((string) $row['serial_number']).'</td>';
				print '<td>';
				if (!empty($row['association_required']) && empty($row['blocking'])) {
					print $form->selectarray('line_assignment['.((int) $row['line']).']', array('' => '') + $lineoptions, 0, 0, 0, '', 0, 0, 0, '', 'flat minwidth300 serial-line-assignment');
				} elseif (!empty($row['product_display'])) {
					print dol_escape_htmltag((string) $row['product_display']);
				} else {
					print '&mdash;';
				}
				print '</td>';
				print '<td>'.powerplantpv_serialimport_messages_html($row['messages']).'</td>';
				print '</tr>';
			}
		} else {
			print '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans('None').'</span></td></tr>';
		}
		print '</table>';
		print '</div>';

		print '<div class="tabsAction">';
		$canvalidate = ($canimport && empty($analysis['has_blocking_errors']) && $import->status !== PowerPlantPVSerialNumberImport::STATUS_VALIDATED && $import->status !== PowerPlantPVSerialNumberImport::STATUS_CANCELLED);
		print dolGetButtonAction($langs->trans('SerialNumbersValidateAssociation'), '', 'default', '#', 'serialimport-submit', $canvalidate);
		print dolGetButtonAction($langs->trans('SerialNumbersCancelImport'), '', 'delete', $_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&importid='.(int) $import->id.'&action=cancelimport&token='.newToken(), '', ($canimport && $import->status !== PowerPlantPVSerialNumberImport::STATUS_VALIDATED && $import->status !== PowerPlantPVSerialNumberImport::STATUS_CANCELLED));
		print dolGetButtonAction($langs->trans('BackToList'), '', 'default', dol_buildpath('/powerplantpv/powerplant_composition.php', 1).'?id='.(int) $object->id, '', true);
		print '</div>';
		print '</form>';

		print '<script nonce="'.getNonce().'">';
		print 'jQuery(function(){';
		print 'jQuery(".serial-line-assignment").select2({width:"resolve",minimumInputLength:0});';
		print 'jQuery("#serialimport-submit").on("click", function(e){e.preventDefault(); if (!jQuery(this).hasClass("butActionRefused")) { jQuery(this).closest("form").submit(); }});';
		print '});';
		print '</script>';
	}
} else {
	print load_fiche_titre($langs->trans('SerialNumbersImport'), '', 'fa-file-import');
	if (empty($categories)) {
		print '<div class="opacitymedium">'.$langs->trans('SerialNumbersNoCategoryAvailable').'</div>';
	} else {
		$categoryoptions = array();
		foreach ($categories as $catid => $cat) {
			$categoryoptions[(int) $catid] = $cat['label'].' ('.((int) $cat['expected_qty']).')';
		}

		print '<form method="POST" enctype="multipart/form-data" action="'.$_SERVER['PHP_SELF'].'?id='.(int) $object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="uploadserials">';
		print '<table class="border centpercent tableforfield">';
		print '<tr><td class="titlefieldcreate">'.$langs->trans('SerialNumbersCategoryToImport').'</td><td>'.$form->selectarray('fk_categorie', $categoryoptions, ($categoryid > 0 ? $categoryid : 0), 0, 0, '', 0, 0, 0, '', 'flat minwidth300').'</td></tr>';
		print '<tr><td class="titlefieldcreate">'.$langs->trans('SerialNumbersFileToImport').'</td><td><input type="file" class="flat" name="serial_file" accept=".csv,.xlsx"></td></tr>';
		print '<tr><td>'.$langs->trans('SerialNumbersFirstLineHeaders').'</td><td><input type="checkbox" class="flat" name="first_line_headers" value="1" checked></td></tr>';
		print '<tr><td>'.$langs->trans('SerialNumbersImportMode').'</td><td>'.$form->selectarray('import_mode', array('add' => $langs->trans('SerialNumbersAddOnly'), 'replace' => $langs->trans('SerialNumbersReplaceExisting')), $mode, 0, 0, '', 0, 0, 0, '', 'flat minwidth300').'</td></tr>';
		print '</table>';
		print '<div class="tabsAction">';
		print '<input type="submit" class="button button-add" value="'.$langs->trans('Send').'">';
		print dolGetButtonAction($langs->trans('BackToList'), '', 'default', dol_buildpath('/powerplantpv/powerplant_composition.php', 1).'?id='.(int) $object->id, '', true);
		print '</div>';
		print '</form>';
		print '<script nonce="'.getNonce().'">jQuery(function(){jQuery("#fk_categorie,#import_mode").select2({width:"resolve",minimumResultsForSearch:0});});</script>';
	}
}

print '</div>';
print dol_get_fiche_end();

llxFooter();
$db->close();
