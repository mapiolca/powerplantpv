<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		admin/attestation.php
 * \ingroup		powerplantpv
 * \brief		Attestation settings.
 */

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/ajax.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once '../lib/powerplantpv.lib.php';
require_once '../lib/powerplantpv_attestation.lib.php';

$langs->loadLangs(array('admin', 'powerplantpv@powerplantpv'));

$action = GETPOST('action', 'aZ09');
$error = 0;

$permissiontosetup = $user->admin || powerplantpvAttestationUserHasRight($user, 'setup');
if (!$permissiontosetup) {
	accessforbidden();
}

if (!isModEnabled('powerplantpv')) {
	accessforbidden();
}

$attestationDocumentType = 'attestation';
$attestationTypeModelConstants = array(
	'POWERPLANTPV_ATTESTATION_BRIDAGE_DYNAMIQUE_MODEL' => array('label' => 'AttestationTypeBridageDynamiqueOnduleur', 'default' => 'attestation_bridage_dynamique'),
	'POWERPLANTPV_ATTESTATION_BRIDAGE_STATIQUE_MODEL' => array('label' => 'AttestationTypeBridageStatiqueOnduleur', 'default' => 'attestation_bridage_statique'),
	'POWERPLANTPV_ATTESTATION_REGLAGE_FREQ_MODEL' => array('label' => 'AttestationTypeReglageMaxFreq515Hz', 'default' => 'attestation_reglage_max_freq'),
	'POWERPLANTPV_ATTESTATION_INSTALLATEUR_INF100KWC_MODEL' => array('label' => 'AttestationTypeInstallateurInf100kwc', 'default' => 'attestation_installateur_inf100kwc'),
);

/**
 * Return attestation model directories.
 *
 * @param	int<0,1>	$withDocSubdir	Append doc subdir
 * @return	array<int,string>			Directories
 */
function powerplantpvAttestationAdminGetModelDirs($withDocSubdir = 0)
{
	global $conf;

	$reldirs = array('/powerplantpv/');
	$externalModelDirs = !empty($conf->modules_parts['models']) ? (array) $conf->modules_parts['models'] : array();
	foreach ($externalModelDirs as $reldir) {
		$reldirs[] = $reldir;
	}

	$dirs = array();
	foreach ($reldirs as $reldir) {
		$dir = dol_buildpath($reldir.'core/modules/attestation'.($withDocSubdir ? '/doc' : ''), 0);
		if (is_dir($dir)) {
			$key = str_replace('\\', '/', realpath($dir));
			$dirs[$key] = $dir;
		}
	}

	return array_values($dirs);
}

/**
 * Return available attestation PDF model modules.
 *
 * @return	array<string,array<string,mixed>>	Models indexed by model name
 */
function powerplantpvAttestationAdminGetPdfModels()
{
	global $db;

	$models = array();
	foreach (powerplantpvAttestationAdminGetModelDirs(1) as $dir) {
		$filelist = array();
		$handle = opendir($dir);
		if (!is_resource($handle)) {
			continue;
		}
		while (($file = readdir($handle)) !== false) {
			$filelist[] = $file;
		}
		closedir($handle);
		arsort($filelist);

		foreach ($filelist as $file) {
			if (!preg_match('/^pdf_attestation_.*\.modules\.php$/i', $file) || $file === 'pdf_attestation_base.modules.php') {
				continue;
			}
			$name = substr($file, 4, dol_strlen($file) - 16);
			$className = substr($file, 0, dol_strlen($file) - 12);
			if (isset($models[$name])) {
				continue;
			}

			require_once $dir.'/'.$file;
			if (!class_exists($className)) {
				continue;
			}
			$module = new $className($db);
			$models[$name] = array(
				'name' => $name,
				'class' => $className,
				'file' => $file,
				'dir' => $dir,
				'realpath' => '/powerplantpv/core/modules/attestation/doc',
				'module' => $module,
			);
		}
	}

	return $models;
}

/**
 * Return active document models for attestation.
 *
 * @param	DoliDB	$db	Database handler
 * @return	array<string,int>	Active models
 */
function powerplantpvAttestationAdminGetActiveDocumentModels($db)
{
	global $conf;

	$active = array();
	$sql = "SELECT nom";
	$sql .= " FROM ".$db->prefix()."document_model";
	$sql .= " WHERE type = 'attestation'";
	$sql .= " AND entity = ".((int) $conf->entity);
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$active[(string) $obj->nom] = 1;
		}
		$db->free($resql);
	}

	return $active;
}

/**
 * Return select options for attestation PDF models.
 *
 * @param	array<string,array<string,mixed>>	$pdfModels	PDF models
 * @param	array<string,int>					$active		Active models
 * @return	array<string,string>							Options
 */
function powerplantpvAttestationAdminGetModelOptions($pdfModels, $active)
{
	$options = array();
	foreach ($pdfModels as $name => $data) {
		if (!empty($active) && empty($active[$name])) {
			continue;
		}
		$module = $data['module'];
		$options[$name] = !empty($module->name) ? (string) $module->name : $name;
	}
	if (empty($options)) {
		foreach ($pdfModels as $name => $data) {
			$module = $data['module'];
			$options[$name] = !empty($module->name) ? (string) $module->name : $name;
		}
	}
	asort($options);

	return $options;
}

/**
 * Return attestation type code for a PDF model.
 *
 * @param	string	$model	Model name
 * @return	string			Type code
 */
function powerplantpvAttestationAdminGetTypeForModel($model)
{
	if ($model === 'attestation_bridage_statique') {
		return PowerPlantPVAttestationTypes::TYPE_BRIDAGE_STATIQUE_ONDULEUR;
	}
	if ($model === 'attestation_reglage_max_freq') {
		return PowerPlantPVAttestationTypes::TYPE_REGLAGE_MAX_FREQ_51_5HZ;
	}
	if ($model === 'attestation_installateur_inf100kwc') {
		return PowerPlantPVAttestationTypes::TYPE_INSTALLATEUR_INF_100KWC;
	}

	return PowerPlantPVAttestationTypes::TYPE_BRIDAGE_DYNAMIQUE_ONDULEUR;
}

$value = GETPOST('value', 'aZ09');
$label = GETPOST('label', 'alphanohtml');
$scanDir = GETPOST('scan_dir', 'nohtml');
$modele = GETPOST('module', 'alpha');

if ($action == 'save') {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}

	$settings = array(
		'POWERPLANTPV_ATTESTATION_DEFAULT_MAX_FREQUENCY_HZ' => array('type' => 'chaine', 'value' => price2num(GETPOST('POWERPLANTPV_ATTESTATION_DEFAULT_MAX_FREQUENCY_HZ', 'alphanohtml'), 'MU')),
		'POWERPLANTPV_ATTESTATION_DEFAULT_BRIDAGE_POWER' => array('type' => 'chaine', 'value' => price2num(GETPOST('POWERPLANTPV_ATTESTATION_DEFAULT_BRIDAGE_POWER', 'alphanohtml'), 'MU')),
		'POWERPLANTPV_ATTESTATION_BRIDAGE_DYNAMIQUE_MODEL' => array('type' => 'chaine', 'value' => GETPOST('POWERPLANTPV_ATTESTATION_BRIDAGE_DYNAMIQUE_MODEL', 'aZ09')),
		'POWERPLANTPV_ATTESTATION_BRIDAGE_STATIQUE_MODEL' => array('type' => 'chaine', 'value' => GETPOST('POWERPLANTPV_ATTESTATION_BRIDAGE_STATIQUE_MODEL', 'aZ09')),
		'POWERPLANTPV_ATTESTATION_REGLAGE_FREQ_MODEL' => array('type' => 'chaine', 'value' => GETPOST('POWERPLANTPV_ATTESTATION_REGLAGE_FREQ_MODEL', 'aZ09')),
		'POWERPLANTPV_ATTESTATION_INSTALLATEUR_INF100KWC_MODEL' => array('type' => 'chaine', 'value' => GETPOST('POWERPLANTPV_ATTESTATION_INSTALLATEUR_INF100KWC_MODEL', 'aZ09')),
	);
	foreach ($settings as $key => $setting) {
		$result = dolibarr_set_const($db, $key, (string) $setting['value'], $setting['type'], 0, '', (int) $conf->entity);
		if ($result <= 0) {
			$error++;
		}
	}

	if (!empty($_FILES['company_stamp']['name'])) {
		$name = (string) $_FILES['company_stamp']['name'];
		$tmpname = (string) $_FILES['company_stamp']['tmp_name'];
		$size = (int) $_FILES['company_stamp']['size'];
		$mime = function_exists('dol_mimetype') ? dol_mimetype($name, '', 0, $tmpname) : '';
		if ($size > 1048576 || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'png' || ($mime !== '' && $mime !== 'image/png')) {
			$error++;
			setEventMessages($langs->trans('AttestationStampMustBePng'), null, 'errors');
		} else {
			$target = powerplantpvAttestationGetCompanyStampFile($conf->entity);
			dol_mkdir(dirname($target));
			if (!move_uploaded_file($tmpname, $target)) {
				$error++;
				setEventMessages($langs->trans('ErrorFailedToSaveFile'), null, 'errors');
			}
		}
	}

	if (!$error) {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}
} elseif ($action == 'setmod') {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
	$found = 0;
	foreach (powerplantpvAttestationAdminGetModelDirs(0) as $dir) {
		if ($value !== '' && file_exists($dir.'/'.$value.'.php') && preg_match('/^mod_attestation_/', $value)) {
			$found = 1;
			break;
		}
	}
	if ($found) {
		dolibarr_set_const($db, 'POWERPLANTPV_ATTESTATION_ADDON', $value, 'chaine', 0, '', (int) $conf->entity);
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	} else {
		setEventMessages($langs->trans('ErrorModuleNotFound'), null, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
} elseif ($action == 'set') {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
	$ret = addDocumentModel($value, $attestationDocumentType, $label, $scanDir);
	if ($ret > 0) {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
} elseif ($action == 'del') {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
	$ret = delDocumentModel($value, $attestationDocumentType);
	if ($ret > 0) {
		if (getDolGlobalString('POWERPLANTPV_ATTESTATION_ADDON_PDF') == $value) {
			dolibarr_del_const($db, 'POWERPLANTPV_ATTESTATION_ADDON_PDF', (int) $conf->entity);
		}
		foreach (array_keys($attestationTypeModelConstants) as $constname) {
			if (getDolGlobalString($constname) == $value) {
				dolibarr_del_const($db, $constname, (int) $conf->entity);
			}
		}
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
} elseif ($action == 'setdoc') {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
	if (dolibarr_set_const($db, 'POWERPLANTPV_ATTESTATION_ADDON_PDF', $value, 'chaine', 0, '', (int) $conf->entity)) {
		$conf->global->POWERPLANTPV_ATTESTATION_ADDON_PDF = $value;
	}
	$ret = delDocumentModel($value, $attestationDocumentType);
	if ($ret > 0) {
		$ret = addDocumentModel($value, $attestationDocumentType, $label, $scanDir);
	}
	if ($ret > 0) {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
} elseif ($action == 'unsetdoc') {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
	dolibarr_del_const($db, 'POWERPLANTPV_ATTESTATION_ADDON_PDF', (int) $conf->entity);
	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
} elseif ($action == 'specimen') {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
	$pdfModels = powerplantpvAttestationAdminGetPdfModels();
	if (!isset($pdfModels[$modele])) {
		setEventMessages($langs->trans('ErrorModuleNotFound'), null, 'errors');
	} else {
		$data = $pdfModels[$modele];
		$className = $data['class'];
		$module = new $className($db);
		$tmpobject = new PowerPlantPVAttestation($db);
		$tmpobject->initAsSpecimen();
		$tmpobject->entity = (int) $conf->entity;
		$tmpobject->type_code = powerplantpvAttestationAdminGetTypeForModel($modele);
		$tmpobject->model_pdf = $modele;
		if ($module->write_file($tmpobject, $langs) > 0) {
			$documentfile = powerplantpvAttestationGetDocumentRelativePath($tmpobject).'/'.dol_sanitizeFileName($tmpobject->ref).'.pdf';
			if (!empty($module->result['fullpath'])) {
				$documentroot = str_replace('\\', '/', powerplantpvAttestationGetDocumentRootDir($tmpobject->entity));
				$generatedfile = str_replace('\\', '/', $module->result['fullpath']);
				if (strpos($generatedfile, $documentroot.'/') === 0) {
					$documentfile = substr($generatedfile, dol_strlen($documentroot) + 1);
				}
			}
			header('Location: '.DOL_URL_ROOT.'/document.php?modulepart=powerplantpv&entity='.(int) $tmpobject->entity.'&file='.urlencode($documentfile));
			exit;
		}
		setEventMessages($module->error, null, 'errors');
		dol_syslog($module->error, LOG_ERR);
	}
} elseif ($action == 'delete_stamp') {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
	$stamp = powerplantpvAttestationGetCompanyStampFile($conf->entity);
	if (file_exists($stamp) && !dol_delete_file($stamp)) {
		setEventMessages($langs->trans('ErrorFailToDeleteFile', $stamp), null, 'errors');
	} else {
		setEventMessages($langs->trans('FileWasRemoved'), null, 'mesgs');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

$form = new Form($db);
$pdfModels = powerplantpvAttestationAdminGetPdfModels();
$activePdfModels = powerplantpvAttestationAdminGetActiveDocumentModels($db);
$modelOptions = powerplantpvAttestationAdminGetModelOptions($pdfModels, $activePdfModels);

llxHeader('', $langs->trans('AttestationSettings'), '', '', 0, 0, '', '', '', 'mod-powerplantpv page-admin-attestation');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword='.urlencode('powerplantpv').'">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('AttestationSettings'), $linkback, 'title_setup');
$head = powerplantpvAdminPrepareHead();
print dol_get_fiche_head($head, 'attestation', $langs->trans('ModulePowerPlantPVName'), -1, 'powerplantpv@powerplantpv');

if (getDolGlobalInt('POWERPLANTPV_ATTESTATION_ENABLE', 1)) {
	powerplantpvAttestationPrintInstallationWarnings();
}

/*
 * Numbering modules
 */

print load_fiche_titre($langs->trans('NumberingModules', $langs->trans('Attestation')), '', '');
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Name').'</td>';
print '<td>'.$langs->trans('Description').'</td>';
print '<td class="nowrap">'.$langs->trans('Example').'</td>';
print '<td class="center" width="60">'.$langs->trans('Status').'</td>';
print '<td class="center" width="16">'.$langs->trans('ShortInfo').'</td>';
print '</tr>'."\n";

$attestationInstallationIssues = powerplantpvAttestationGetInstallationIssues();
clearstatcache();
foreach (powerplantpvAttestationAdminGetModelDirs(0) as $dir) {
	$handle = opendir($dir);
	if (!is_resource($handle)) {
		continue;
	}
	while (($file = readdir($handle)) !== false) {
		if (!preg_match('/^mod_attestation_.*\.php$/i', $file)) {
			continue;
		}
		$file = substr($file, 0, dol_strlen($file) - 4);
		require_once $dir.'/'.$file.'.php';
		if (!class_exists($file)) {
			continue;
		}

		$module = new $file($db);
		if ($module->version == 'development' && getDolGlobalInt('MAIN_FEATURES_LEVEL') < 2) {
			continue;
		}
		if ($module->version == 'experimental' && getDolGlobalInt('MAIN_FEATURES_LEVEL') < 1) {
			continue;
		}
		if (method_exists($module, 'isEnabled') && !$module->isEnabled()) {
			continue;
		}

		print '<tr class="oddeven"><td>';
		print method_exists($module, 'getName') ? $module->getName($langs) : (!empty($module->name) ? dol_escape_htmltag($module->name) : dol_escape_htmltag($file));
		print '</td><td>';
		print method_exists($module, 'info') ? $module->info($langs) : '';
		print '</td>';

		print '<td class="nowrap">';
		$tmp = method_exists($module, 'getExample') ? $module->getExample() : '';
		if (preg_match('/^Error/', (string) $tmp)) {
			$langs->load('errors');
			print '<div class="error">'.$langs->trans($tmp).'</div>';
		} elseif ($tmp == 'NotConfigured') {
			print '<span class="opacitymedium">'.$langs->trans($tmp).'</span>';
		} else {
			print dol_escape_htmltag((string) $tmp);
		}
		print '</td>'."\n";

		print '<td class="center">';
		if (getDolGlobalString('POWERPLANTPV_ATTESTATION_ADDON', 'mod_attestation_standard') == $file) {
			print img_picto($langs->trans('Activated'), 'switch_on');
		} else {
			print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?action=setmod&token='.newToken().'&value='.urlencode($file).'">';
			print img_picto($langs->trans('Disabled'), 'switch_off');
			print '</a>';
		}
		print '</td>';

		$htmltooltip = $langs->trans('Version').': <b>'.(method_exists($module, 'getVersion') ? $module->getVersion() : dol_escape_htmltag($module->version)).'</b><br>';
		if (empty($attestationInstallationIssues['tables'])) {
			$tmpobject = new PowerPlantPVAttestation($db);
			$tmpobject->initAsSpecimen();
			$tmpobject->entity = (int) $conf->entity;
			$nextval = method_exists($module, 'getNextValue') ? $module->getNextValue($tmpobject) : '';
			if ((string) $nextval != $langs->trans('NotAvailable')) {
				$htmltooltip .= $langs->trans('NextValue').': ';
				if ($nextval) {
					if (preg_match('/^Error/', (string) $nextval) || $nextval == 'NotConfigured') {
						$nextval = $langs->trans($nextval);
					}
					$htmltooltip .= dol_escape_htmltag((string) $nextval).'<br>';
				} elseif (!empty($module->error)) {
					$htmltooltip .= $langs->trans($module->error).'<br>';
				}
			}
		}
		print '<td class="center">'.$form->textwithpicto('', $htmltooltip, 1, 'info').'</td>';
		print '</tr>'."\n";
	}
	closedir($handle);
}
print '</table>';
print '</div>';

/*
 * Document templates generators
 */

print '<br>';
print load_fiche_titre($langs->trans('DocumentModules', $langs->trans('Attestation')), '', '');
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Name').'</td>';
print '<td>'.$langs->trans('Description').'</td>';
print '<td class="center" width="60">'.$langs->trans('Status').'</td>';
print '<td class="center" width="60">'.$langs->trans('Default').'</td>';
print '<td class="center" width="38">'.$langs->trans('ShortInfo').'</td>';
print '<td class="center" width="38">'.$langs->trans('Preview').'</td>';
print '</tr>'."\n";

foreach ($pdfModels as $name => $data) {
	$module = $data['module'];
	if ($module->version == 'development' && getDolGlobalInt('MAIN_FEATURES_LEVEL') < 2) {
		continue;
	}
	if ($module->version == 'experimental' && getDolGlobalInt('MAIN_FEATURES_LEVEL') < 1) {
		continue;
	}

	$moduleName = !empty($module->name) ? (string) $module->name : $name;
	$moduleDescription = method_exists($module, 'info') ? $module->info($langs) : (!empty($module->description) ? $module->description : '');
	$scandir = !empty($module->scandir) ? (string) $module->scandir : '';

	print '<tr class="oddeven"><td width="100">'.dol_escape_htmltag($moduleName).'</td><td>'.$moduleDescription.'</td>';

	print '<td class="center">';
	if (!empty($activePdfModels[$name])) {
		print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?action=del&token='.newToken().'&value='.urlencode($name).'">';
		print img_picto($langs->trans('Enabled'), 'switch_on');
		print '</a>';
	} else {
		print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?action=set&token='.newToken().'&value='.urlencode($name).'&scan_dir='.urlencode($scandir).'&label='.urlencode($moduleName).'">';
		print img_picto($langs->trans('Disabled'), 'switch_off');
		print '</a>';
	}
	print '</td>';

	print '<td class="center">';
	if (getDolGlobalString('POWERPLANTPV_ATTESTATION_ADDON_PDF') == $name) {
		print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?action=unsetdoc&token='.newToken().'&value='.urlencode($name).'" alt="'.$langs->trans('Disable').'">'.img_picto($langs->trans('Enabled'), 'on').'</a>';
	} else {
		print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?action=setdoc&token='.newToken().'&value='.urlencode($name).'&scan_dir='.urlencode($scandir).'&label='.urlencode($moduleName).'" alt="'.$langs->trans('Default').'">'.img_picto($langs->trans('Disabled'), 'off').'</a>';
	}
	print '</td>';

	$htmltooltip = $langs->trans('Name').': '.dol_escape_htmltag($moduleName);
	$htmltooltip .= '<br>'.$langs->trans('Type').': '.(!empty($module->type) ? dol_escape_htmltag($module->type) : $langs->trans('Unknown'));
	if (!empty($module->type) && $module->type == 'pdf') {
		$htmltooltip .= '<br>'.$langs->trans('Width').'/'.$langs->trans('Height').': '.((float) $module->page_largeur).'/'.((float) $module->page_hauteur);
	}
	$htmltooltip .= '<br>'.$langs->trans('Path').': core/modules/attestation/doc/'.$data['file'];
	$htmltooltip .= '<br><br><u>'.$langs->trans('FeaturesSupported').':</u>';
	$htmltooltip .= '<br>'.$langs->trans('Logo').': '.yn(!empty($module->option_logo), 1, 1);
	$htmltooltip .= '<br>'.$langs->trans('MultiLanguage').': '.yn(!empty($module->option_multilang), 1, 1);

	print '<td class="center">'.$form->textwithpicto('', $htmltooltip, 1, 'info').'</td>';

	print '<td class="center">';
	if (!empty($module->type) && $module->type == 'pdf') {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=specimen&token='.newToken().'&module='.urlencode($name).'">'.img_object($langs->trans('Preview'), 'pdf').'</a>';
	} else {
		print img_object($langs->transnoentitiesnoconv('PreviewNotAvailable'), 'generic');
	}
	print '</td>';
	print '</tr>'."\n";
}
print '</table>';
print '</div>';
print '<br>';

print '<form method="POST" enctype="multipart/form-data" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('AttestationSettings').'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('AttestationEnable').'</td><td>'.ajax_constantonoff('POWERPLANTPV_ATTESTATION_ENABLE', array(), (int) $conf->entity, 0, 0, 0, 2, 0, 1).'</td></tr>';
$fields = array(
	'POWERPLANTPV_ATTESTATION_DEFAULT_MAX_FREQUENCY_HZ' => 'AttestationDefaultMaxFrequencyHz',
	'POWERPLANTPV_ATTESTATION_DEFAULT_BRIDAGE_POWER' => 'AttestationDefaultBridagePower',
);
foreach ($fields as $key => $label) {
	print '<tr class="oddeven"><td>'.$langs->trans($label).'</td><td><input type="text" class="flat minwidth300" name="'.$key.'" value="'.dol_escape_htmltag(getDolGlobalString($key)).'"></td></tr>';
}
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('AttestationPdfModelsByType').'</td></tr>';
foreach ($attestationTypeModelConstants as $constname => $typeModel) {
	print '<tr class="oddeven"><td>'.$langs->trans($typeModel['label']).'</td><td>'.$form->selectarray($constname, $modelOptions, getDolGlobalString($constname, $typeModel['default']), 0, 0, 0, '', 0, 0, 0, '', 'flat minwidth300').'</td></tr>';
	if ($conf->use_javascript_ajax) {
		print ajax_combobox($constname);
	}
}
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('AttestationCompanyStamp').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('AttestationCompanyStampPng').'</td><td><input type="file" class="flat" name="company_stamp" accept="image/png"></td></tr>';
$stamp = powerplantpvAttestationGetCompanyStampFile($conf->entity);
if (file_exists($stamp)) {
	$stampUrl = DOL_URL_ROOT.'/viewimage.php?modulepart=powerplantpv&entity='.(int) $conf->entity.'&file='.urlencode(powerplantpvAttestationGetCompanyStampRelativeFile());
	print '<tr class="oddeven"><td>'.$langs->trans('Preview').'</td><td><img src="'.$stampUrl.'" style="max-height:80px; max-width:220px" alt=""> ';
	print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?action=delete_stamp&token='.newToken().'">'.img_delete().' '.$langs->trans('Delete').'</a></td></tr>';
}
print '</table>';
print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';

print dol_get_fiche_end();
llxFooter();
$db->close();
