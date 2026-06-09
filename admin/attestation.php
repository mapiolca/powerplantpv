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

$permissiontosetup = $user->admin || $user->hasRight('powerplantpv', 'attestation', 'setup');
if (!$permissiontosetup) {
	accessforbidden();
}

if (!isModEnabled('powerplantpv')) {
	accessforbidden();
}

if ($action == 'save') {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}

	$settings = array(
		'POWERPLANTPV_ATTESTATION_DEFAULT_PLACE' => array('type' => 'chaine', 'value' => GETPOST('POWERPLANTPV_ATTESTATION_DEFAULT_PLACE', 'alphanohtml')),
		'POWERPLANTPV_ATTESTATION_INSTALLER_NAME' => array('type' => 'chaine', 'value' => GETPOST('POWERPLANTPV_ATTESTATION_INSTALLER_NAME', 'alphanohtml')),
		'POWERPLANTPV_ATTESTATION_INSTALLER_ADDRESS' => array('type' => 'chaine', 'value' => GETPOST('POWERPLANTPV_ATTESTATION_INSTALLER_ADDRESS', 'alphanohtml')),
		'POWERPLANTPV_ATTESTATION_INSTALLER_ZIP' => array('type' => 'chaine', 'value' => GETPOST('POWERPLANTPV_ATTESTATION_INSTALLER_ZIP', 'alphanohtml')),
		'POWERPLANTPV_ATTESTATION_INSTALLER_TOWN' => array('type' => 'chaine', 'value' => GETPOST('POWERPLANTPV_ATTESTATION_INSTALLER_TOWN', 'alphanohtml')),
		'POWERPLANTPV_ATTESTATION_INSTALLER_SIRET' => array('type' => 'chaine', 'value' => GETPOST('POWERPLANTPV_ATTESTATION_INSTALLER_SIRET', 'alphanohtml')),
		'POWERPLANTPV_ATTESTATION_INSTALLER_VAT' => array('type' => 'chaine', 'value' => GETPOST('POWERPLANTPV_ATTESTATION_INSTALLER_VAT', 'alphanohtml')),
		'POWERPLANTPV_ATTESTATION_WRITER_FUNCTION' => array('type' => 'chaine', 'value' => GETPOST('POWERPLANTPV_ATTESTATION_WRITER_FUNCTION', 'alphanohtml')),
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
$models = array(
	'attestation_bridage_dynamique' => 'AttestationTypeBridageDynamiqueOnduleur',
	'attestation_bridage_statique' => 'AttestationTypeBridageStatiqueOnduleur',
	'attestation_reglage_max_freq' => 'AttestationTypeReglageMaxFreq515Hz',
	'attestation_installateur_inf100kwc' => 'AttestationTypeInstallateurInf100kwc',
);
$modelOptions = array();
foreach ($models as $model => $label) {
	$modelOptions[$model] = $langs->trans($label);
}

llxHeader('', $langs->trans('AttestationSettings'), '', '', 0, 0, '', '', '', 'mod-powerplantpv page-admin-attestation');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword='.urlencode('powerplantpv').'">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('AttestationSettings'), $linkback, 'title_setup');
$head = powerplantpvAdminPrepareHead();
print dol_get_fiche_head($head, 'attestation', $langs->trans('ModulePowerPlantPVName'), -1, 'powerplantpv@powerplantpv');

print '<form method="POST" enctype="multipart/form-data" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('AttestationSettings').'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('AttestationEnable').'</td><td>'.ajax_constantonoff('POWERPLANTPV_ATTESTATION_ENABLE', array(), (int) $conf->entity, 0, 0, 0, 2, 0, 1).'</td></tr>';
$fields = array(
	'POWERPLANTPV_ATTESTATION_DEFAULT_PLACE' => 'AttestationDefaultPlace',
	'POWERPLANTPV_ATTESTATION_INSTALLER_NAME' => 'AttestationInstallerName',
	'POWERPLANTPV_ATTESTATION_INSTALLER_ADDRESS' => 'AttestationInstallerAddress',
	'POWERPLANTPV_ATTESTATION_INSTALLER_ZIP' => 'AttestationInstallerZip',
	'POWERPLANTPV_ATTESTATION_INSTALLER_TOWN' => 'AttestationInstallerTown',
	'POWERPLANTPV_ATTESTATION_INSTALLER_SIRET' => 'SIRET',
	'POWERPLANTPV_ATTESTATION_INSTALLER_VAT' => 'VATIntra',
	'POWERPLANTPV_ATTESTATION_WRITER_FUNCTION' => 'AttestationWriterFunction',
	'POWERPLANTPV_ATTESTATION_DEFAULT_MAX_FREQUENCY_HZ' => 'AttestationDefaultMaxFrequencyHz',
	'POWERPLANTPV_ATTESTATION_DEFAULT_BRIDAGE_POWER' => 'AttestationDefaultBridagePower',
);
foreach ($fields as $key => $label) {
	print '<tr class="oddeven"><td>'.$langs->trans($label).'</td><td><input type="text" class="flat minwidth300" name="'.$key.'" value="'.dol_escape_htmltag(getDolGlobalString($key)).'"></td></tr>';
}
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('AttestationPdfModels').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('AttestationTypeBridageDynamiqueOnduleur').'</td><td>'.$form->selectarray('POWERPLANTPV_ATTESTATION_BRIDAGE_DYNAMIQUE_MODEL', $modelOptions, getDolGlobalString('POWERPLANTPV_ATTESTATION_BRIDAGE_DYNAMIQUE_MODEL', 'attestation_bridage_dynamique'), 0, 0, 0, '', 0, 0, 0, '', 'flat minwidth300').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('AttestationTypeBridageStatiqueOnduleur').'</td><td>'.$form->selectarray('POWERPLANTPV_ATTESTATION_BRIDAGE_STATIQUE_MODEL', $modelOptions, getDolGlobalString('POWERPLANTPV_ATTESTATION_BRIDAGE_STATIQUE_MODEL', 'attestation_bridage_statique'), 0, 0, 0, '', 0, 0, 0, '', 'flat minwidth300').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('AttestationTypeReglageMaxFreq515Hz').'</td><td>'.$form->selectarray('POWERPLANTPV_ATTESTATION_REGLAGE_FREQ_MODEL', $modelOptions, getDolGlobalString('POWERPLANTPV_ATTESTATION_REGLAGE_FREQ_MODEL', 'attestation_reglage_max_freq'), 0, 0, 0, '', 0, 0, 0, '', 'flat minwidth300').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('AttestationTypeInstallateurInf100kwc').'</td><td>'.$form->selectarray('POWERPLANTPV_ATTESTATION_INSTALLATEUR_INF100KWC_MODEL', $modelOptions, getDolGlobalString('POWERPLANTPV_ATTESTATION_INSTALLATEUR_INF100KWC_MODEL', 'attestation_installateur_inf100kwc'), 0, 0, 0, '', 0, 0, 0, '', 'flat minwidth300').'</td></tr>';
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
