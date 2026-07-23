<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       admin/product_import.php
 * \ingroup    powerplantpv
 * \brief      Administrator bulk product and technical characteristics import.
 */

$res = 0;
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
dol_include_once('/powerplantpv/class/powerplantpvbulkproductimport.class.php');
dol_include_once('/powerplantpv/class/powerplantpvfileimport.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_producttechnicalimport.lib.php');

$langs->loadLangs(array('admin', 'products', 'powerplantpv@powerplantpv'));

if (!isModEnabled('powerplantpv') || !isModEnabled('product')) {
	accessforbidden();
}
if (empty($user->admin)) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$format = strtolower(GETPOST('format', 'aZ09'));
$separator = GETPOST('separator', 'nohtml');
$importtoken = GETPOST('import_token', 'alphanohtml');
$backtopage = GETPOST('backtopage', 'alpha');
if (!in_array($separator, array(';', ',', 'tab'), true)) {
	$separator = getDolGlobalString('POWERPLANTPV_IMPORT_DEFAULT_SEPARATOR', ';');
}
if (!in_array($separator, array(';', ',', 'tab'), true)) {
	$separator = ';';
}


/** @return string */
function powerplantpv_bulk_import_temp_dir()
{
	global $conf, $user;

	$entity = (int) $conf->entity;
	if (!empty($conf->powerplantpv->multidir_output[$entity])) {
		$root = $conf->powerplantpv->multidir_output[$entity];
	} elseif (!empty($conf->powerplantpv->dir_output)) {
		$root = $conf->powerplantpv->dir_output;
	} else {
		$root = DOL_DATA_ROOT.'/powerplantpv';
	}
	return $root.'/temp/bulkproductimport/'.$entity.'/'.((int) $user->id);
}

/** @return string */
function powerplantpv_bulk_import_generate_token()
{
	try {
		return bin2hex(random_bytes(16));
	} catch (Throwable $e) {
		return sha1(uniqid('', true));
	}
}

/** @param string $token Import token @return string */
function powerplantpv_bulk_import_meta_path($token)
{
	if (!preg_match('/^[a-f0-9]{32,40}$/D', $token)) {
		return '';
	}
	return powerplantpv_bulk_import_temp_dir().'/'.$token.'.json';
}

/** @param string $token Import token @return array<string,mixed>|false */
function powerplantpv_bulk_import_load_metadata($token)
{
	global $conf, $user;

	$path = powerplantpv_bulk_import_meta_path($token);
	if ($path === '' || !is_readable($path)) {
		return false;
	}
	$decoded = json_decode((string) file_get_contents($path), true);
	if (!is_array($decoded) || (int) ($decoded['entity'] ?? 0) !== (int) $conf->entity || (int) ($decoded['fk_user'] ?? 0) !== (int) $user->id) {
		return false;
	}
	return $decoded;
}

/** @param array<string,mixed>|false $metadata Metadata @param string $token Import token @return void */
function powerplantpv_bulk_import_delete_temp($metadata, $token)
{
	$dir = powerplantpv_bulk_import_temp_dir();
	if (is_array($metadata) && !empty($metadata['filepath'])) {
		$filepath = (string) $metadata['filepath'];
		if (strpos($filepath, $dir.'/') === 0 && is_file($filepath)) {
			dol_delete_file($filepath);
		}
	}
	$metapath = powerplantpv_bulk_import_meta_path($token);
	if ($metapath !== '' && is_file($metapath)) {
		dol_delete_file($metapath);
	}
}

/** @return void */
function powerplantpv_bulk_import_purge_old_temp()
{
	$dir = powerplantpv_bulk_import_temp_dir();
	if (!is_dir($dir)) {
		return;
	}
	$files = dol_dir_list($dir, 'files', 0, '', '', 'date', SORT_ASC, 0, 0, 0, 0, '', 0);
	$cutoff = dol_now() - 86400;
	foreach ($files as $file) {
		$path = isset($file['fullname']) ? (string) $file['fullname'] : '';
		$mtime = isset($file['date']) ? (int) $file['date'] : ($path !== '' ? (int) @filemtime($path) : 0);
		if ($path !== '' && strpos($path, $dir.'/') === 0 && $mtime > 0 && $mtime < $cutoff) {
			dol_delete_file($path);
		}
	}
}

/** @param PowerPlantPVFileImport $reader Reader @param string $filepath File path @param string $extension Extension @param string $separator CSV separator @return array<int,array<int,string>> */
function powerplantpv_bulk_import_read_rows($reader, $filepath, $extension, $separator)
{
	return $extension === 'xlsx' ? $reader->readXlsx($filepath) : $reader->readCsv($filepath, $separator === 'tab' ? "\t" : $separator);
}

/** @param array<int,string> $errors Translation keys @return array<int,string> */
function powerplantpv_bulk_import_translate_errors(array $errors)
{
	global $langs;
	$result = array();
	foreach (array_values(array_unique(array_filter($errors))) as $error) {
		$result[] = $langs->trans($error);
	}
	return $result;
}

/** @param string $status Import status @return string */
function powerplantpv_bulk_import_status_badge($status)
{
	global $langs;

	$levels = array('CREATE' => 4, 'UPDATE' => 4, 'UNCHANGED' => 0, 'ERROR' => 8);
	$level = isset($levels[$status]) ? $levels[$status] : 0;
	return dolGetStatus($langs->trans('PowerPlantPVBulkImportStatus'.$status), '', '', $level, 1);
}

powerplantpv_bulk_import_purge_old_temp();

$preview = array();
$results = array();
$metadata = false;
$reader = new PowerPlantPVFileImport();
$orchestrator = new PowerPlantPVBulkProductImport($db, (int) $conf->entity);
$maxrows = getDolGlobalInt('POWERPLANTPV_BULK_IMPORT_MAX_ROWS', 1000);
if ($maxrows <= 0) {
	$maxrows = 1000;
}

if ($action === 'download_template') {
	if (!checkToken()) {
		accessforbidden('Bad token');
	}
	$headers = PowerPlantPVBulkProductImport::getTemplateHeaders();
	$sampleRows = array(
		array('ref' => 'PV-MODULE-EXEMPLE', 'category_code' => 'MODULE', 'label' => $langs->transnoentities('PowerPlantPVBulkImportSampleModuleLabel')),
		array('ref' => 'PV-ONDULEUR-EXEMPLE', 'category_code' => 'ONDULE', 'label' => $langs->transnoentities('PowerPlantPVBulkImportSampleInverterLabel')),
		array('ref' => 'PV-BATTERIE-EXEMPLE', 'category_code' => 'BATTER', 'label' => $langs->transnoentities('PowerPlantPVBulkImportSampleBatteryLabel')),
	);
	$filenamebase = 'powerplantpv-products-technical-characteristics-template';
	if ($format === 'csv') {
		header('Content-Type: text/csv; charset=UTF-8');
		header('Content-Disposition: attachment; filename="'.$filenamebase.'.csv"');
		$out = fopen('php://output', 'wb');
		fputs($out, "\xEF\xBB\xBF");
		fputcsv($out, $headers, ';');
		foreach ($sampleRows as $sample) {
			$row = array();
			foreach ($headers as $header) {
				$key = preg_replace('/\s*\[[^\]]+\]\s*$/u', '', $header);
				$row[] = isset($sample[$key]) ? $sample[$key] : '';
			}
			fputcsv($out, $row, ';');
		}
		fclose($out);
		exit;
	}
	if ($format !== 'xlsx' || !powerplantpvProductTechnicalImportLoadPhpSpreadsheet()) {
		setEventMessages($langs->trans('ProductTechnicalImportXlsxTemplateUnavailable'), null, 'errors');
		$action = '';
	} else {
		$oblevel = ob_get_level();
		try {
			$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
			$sheet = $spreadsheet->getActiveSheet();
			$sheet->fromArray($headers, null, 'A1');
			$rows = array();
			foreach ($sampleRows as $sample) {
				$row = array();
				foreach ($headers as $header) {
					$key = preg_replace('/\s*\[[^\]]+\]\s*$/u', '', $header);
					$row[] = isset($sample[$key]) ? $sample[$key] : '';
				}
				$rows[] = $row;
			}
			$sheet->fromArray($rows, null, 'A2');
			$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
			ob_start();
			$writer->save('php://output');
			$content = ob_get_clean();
			if ($content === false) {
				throw new Exception('Unable to capture XLSX template');
			}
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment; filename="'.$filenamebase.'.xlsx"');
			print $content;
			exit;
		} catch (Throwable $e) {
			while (ob_get_level() > $oblevel) {
				ob_end_clean();
			}
			dol_syslog(__FILE__.' XLSX template: '.$e->getMessage(), LOG_WARNING);
			setEventMessages($langs->trans('ProductTechnicalImportXlsxTemplateUnavailable'), null, 'errors');
			$action = '';
		}
	}
}

if ($action === 'upload') {
	if (!checkToken()) {
		accessforbidden('Bad token');
	}
	$uploaded = isset($_FILES['bulk_product_file']) && is_array($_FILES['bulk_product_file']) ? $_FILES['bulk_product_file'] : array();
	$fileinfo = $reader->validateUploadedFile($uploaded);
	if ($fileinfo === false) {
		setEventMessages($langs->trans($reader->getLastError()), powerplantpv_bulk_import_translate_errors($reader->getLastErrors()), 'errors');
	} else {
		$dir = powerplantpv_bulk_import_temp_dir();
		if (dol_mkdir($dir) < 0) {
			setEventMessages($langs->trans('ErrorFailedToCreateDir'), null, 'errors');
		} else {
			$importtoken = powerplantpv_bulk_import_generate_token();
			$extension = (string) $fileinfo['extension'];
			$filepath = $dir.'/'.$importtoken.'.'.$extension;
			$move = dol_move_uploaded_file((string) $uploaded['tmp_name'], $filepath, 1, 0, (int) $uploaded['error'], 0);
			if ($move <= 0) {
				setEventMessages($langs->trans('ProductTechnicalImportFileUnreadable'), null, 'errors');
			} else {
				$rows = powerplantpv_bulk_import_read_rows($reader, $filepath, $extension, $separator);
				$preview = $orchestrator->previewRows($rows, $maxrows);
				if (empty($preview)) {
					setEventMessages($langs->trans($orchestrator->error !== '' ? $orchestrator->error : $reader->getLastError()), powerplantpv_bulk_import_translate_errors($orchestrator->errors), 'errors');
					powerplantpv_bulk_import_delete_temp(array('filepath' => $filepath), $importtoken);
					$importtoken = '';
				} else {
					$metadata = array(
						'entity' => (int) $conf->entity,
						'fk_user' => (int) $user->id,
						'created_at' => dol_now(),
						'filename' => (string) $fileinfo['filename'],
						'extension' => $extension,
						'separator' => $separator,
						'filepath' => $filepath,
						'sha256' => hash_file('sha256', $filepath),
						'preview' => $preview,
					);
					$json = json_encode($metadata);
					$metapath = powerplantpv_bulk_import_meta_path($importtoken);
					if ($json === false || $metapath === '' || file_put_contents($metapath, $json, LOCK_EX) === false) {
						setEventMessages($langs->trans('PowerPlantPVBulkImportTemporaryStorageError'), null, 'errors');
						powerplantpv_bulk_import_delete_temp($metadata, $importtoken);
						$preview = array();
						$importtoken = '';
					}
				}
			}
		}
	}
}

if ($action === 'confirm_import') {
	if (!checkToken()) {
		accessforbidden('Bad token');
	}
	$metadata = powerplantpv_bulk_import_load_metadata($importtoken);
	if (!is_array($metadata) || empty($metadata['filepath']) || !is_readable((string) $metadata['filepath'])) {
		setEventMessages($langs->trans('ProductTechnicalImportSessionExpired'), null, 'errors');
	} elseif (!hash_equals((string) $metadata['sha256'], (string) hash_file('sha256', (string) $metadata['filepath']))) {
		setEventMessages($langs->trans('PowerPlantPVBulkImportFileChanged'), null, 'errors');
		powerplantpv_bulk_import_delete_temp($metadata, $importtoken);
	} else {
		$rows = powerplantpv_bulk_import_read_rows($reader, (string) $metadata['filepath'], (string) $metadata['extension'], (string) $metadata['separator']);
		$preview = $orchestrator->previewRows($rows, $maxrows);
		if (empty($preview)) {
			setEventMessages($langs->trans($orchestrator->error !== '' ? $orchestrator->error : $reader->getLastError()), powerplantpv_bulk_import_translate_errors($orchestrator->errors), 'errors');
		} else {
			$results = $orchestrator->execute($preview, $user, (string) $metadata['filename']);
			setEventMessages($langs->trans('PowerPlantPVBulkImportCompleted'), null, 'mesgs');
		}
		powerplantpv_bulk_import_delete_temp($metadata, $importtoken);
		$preview = array();
		$importtoken = '';
	}
}

if ($action === 'cancel_import') {
	if (!checkToken()) {
		accessforbidden('Bad token');
	}
	$metadata = powerplantpv_bulk_import_load_metadata($importtoken);
	powerplantpv_bulk_import_delete_temp($metadata, $importtoken);
	setEventMessages($langs->trans('ProductTechnicalImportCancelled'), null, 'mesgs');
	$preview = array();
	$importtoken = '';
}

$form = new Form($db);
$title = $langs->trans('PowerPlantPVBulkImport');
llxHeader('', $title, '', '', 0, 0, array(), array(), '', 'mod-powerplantpv page-admin');

$linkback = '<a href="'.($backtopage ? dol_escape_htmltag($backtopage) : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.img_picto($langs->trans('BackToModuleList'), 'back', 'class="pictofixedwidth"').'<span class="hideonsmartphone">'.$langs->trans('BackToModuleList').'</span></a>';
print load_fiche_titre($title, $linkback, 'fa-file-import');
$head = powerplantpvAdminPrepareHead();
print dol_get_fiche_head($head, 'product_import', $title, -1, 'fa-sun');

print '<span class="opacitymedium">'.$langs->trans('PowerPlantPVBulkImportHelp', $maxrows).'</span><br><br>';

if (!empty($results)) {
	$counts = array('CREATE' => 0, 'UPDATE' => 0, 'UNCHANGED' => 0, 'ERROR' => 0);
	foreach ($results as $result) {
		$status = isset($result['result_status']) ? (string) $result['result_status'] : 'ERROR';
		$counts[$status] = isset($counts[$status]) ? $counts[$status] + 1 : 1;
	}
	print load_fiche_titre($langs->trans('PowerPlantPVBulkImportReport'), '', 'fa-list');
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Status').'</td><td>'.$langs->trans('NbOfLines').'</td></tr>';
	foreach ($counts as $status => $count) {
		print '<tr class="oddeven"><td>'.powerplantpv_bulk_import_status_badge($status).'</td><td>'.((int) $count).'</td></tr>';
	}
	print '</table></div><br>';
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Line').'</td><td>'.$langs->trans('Ref').'</td><td>'.$langs->trans('ProductPhotovoltaicCategory').'</td><td>'.$langs->trans('Status').'</td><td>'.$langs->trans('Errors').'</td></tr>';
	foreach ($results as $result) {
		$status = isset($result['result_status']) ? (string) $result['result_status'] : 'ERROR';
		$productlink = dol_escape_htmltag((string) $result['ref']);
		if (!empty($result['product_id'])) {
			$product = new Product($db);
			if ($product->fetch((int) $result['product_id']) > 0 && (int) $product->entity === (int) $conf->entity) {
				$productlink = $product->getNomUrl(1);
			}
		}
		$errors = isset($result['errors']) && is_array($result['errors']) ? powerplantpv_bulk_import_translate_errors($result['errors']) : array();
		print '<tr class="oddeven"><td>'.((int) $result['line']).'</td><td>'.$productlink.'</td><td>'.dol_escape_htmltag((string) $result['category_code']).'</td><td>'.powerplantpv_bulk_import_status_badge($status).'</td><td>'.dol_escape_htmltag(implode(' - ', $errors)).'</td></tr>';
	}
	print '</table></div><br>';
}

if (!empty($preview) && $importtoken !== '') {
	print load_fiche_titre($langs->trans('ProductTechnicalImportPreview'), '', 'fa-eye');
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Line').'</td><td>'.$langs->trans('Ref').'</td><td>'.$langs->trans('ProductPhotovoltaicCategory').'</td><td>'.$langs->trans('Status').'</td><td>'.$langs->trans('Errors').'</td></tr>';
	foreach ($preview as $entry) {
		$errors = isset($entry['errors']) && is_array($entry['errors']) ? powerplantpv_bulk_import_translate_errors($entry['errors']) : array();
		print '<tr class="oddeven"><td>'.((int) $entry['line']).'</td><td>'.dol_escape_htmltag((string) $entry['ref']).'</td><td>'.dol_escape_htmltag((string) $entry['category_code']).'</td><td>'.powerplantpv_bulk_import_status_badge((string) $entry['status']).'</td><td>'.dol_escape_htmltag(implode(' - ', $errors)).'</td></tr>';
	}
	print '</table></div>';
	print '<div class="tabsAction">';
	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" class="inline-block">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="confirm_import">';
	print '<input type="hidden" name="import_token" value="'.dol_escape_htmltag($importtoken).'">';
	print '<input type="submit" class="butAction" value="'.$langs->trans('ProductTechnicalImportConfirm').'">';
	print '</form> ';
	$cancelurl = $_SERVER['PHP_SELF'].'?action=cancel_import&import_token='.urlencode($importtoken).'&token='.newToken();
	print dolGetButtonAction($langs->trans('Cancel'), '', 'cancel', $cancelurl, '', true);
	print '</div>';
} else {
	$templatebase = $_SERVER['PHP_SELF'].'?action=download_template&token='.newToken().'&format=';
	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" enctype="multipart/form-data">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="upload">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Name').'</td><td>'.$langs->trans('Value').'</td></tr>';
	print '<tr class="oddeven"><td class="titlefieldcreate">'.$langs->trans('ProductTechnicalImportFile').'</td><td><input type="file" class="flat" name="bulk_product_file" accept=".csv,.xlsx" required></td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('ProductTechnicalImportDefaultSeparator').'</td><td>'.$form->selectarray('separator', array(';' => $langs->trans('ProductTechnicalImportSeparatorSemicolon'), ',' => $langs->trans('ProductTechnicalImportSeparatorComma'), 'tab' => $langs->trans('ProductTechnicalImportSeparatorTab')), $separator, 0, 0, '', 0, 0, 0, '', 'flat minwidth200').'</td></tr>';
	print '<script nonce="'.getNonce().'">jQuery(function(){jQuery("#separator").select2({width:"resolve",minimumResultsForSearch:0});});</script>';
	print '<tr class="oddeven"><td>'.$langs->trans('ProductTechnicalImportDownloadTemplate').'</td><td><a href="'.dol_escape_htmltag($templatebase.'csv').'">'.img_picto('', 'fa-download', 'class="pictofixedwidth"').$langs->trans('ProductTechnicalImportDownloadCsvTemplate').'</a>';
	if (powerplantpvProductTechnicalImportIsXlsxTemplateAvailable()) {
		print ' &nbsp; <a href="'.dol_escape_htmltag($templatebase.'xlsx').'">'.img_picto('', 'fa-download', 'class="pictofixedwidth"').$langs->trans('ProductTechnicalImportDownloadXlsxTemplate').'</a>';
	}
	print '</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('PowerPlantPVBulkImportMaximumRows').'</td><td>'.((int) $maxrows).'</td></tr>';
	print '</table>';
	print '<div class="tabsAction"><input type="submit" class="butAction" value="'.$langs->trans('ProductTechnicalImportUpload').'"></div>';
	print '</form>';
}

print dol_get_fiche_end();
llxFooter();
$db->close();

