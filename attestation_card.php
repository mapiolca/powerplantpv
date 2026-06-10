<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		attestation_card.php
 * \ingroup		powerplantpv
 * \brief		Attestation card.
 */

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
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

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/powerplantpv/class/powerplantpvattestation.class.php');
dol_include_once('/powerplantpv/class/powerplantpvattestationtypes.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_attestation.lib.php');

$langs->loadLangs(array('powerplantpv@powerplantpv', 'companies', 'projects', 'other'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$tab = GETPOST('tab', 'aZ09');
$typeCode = GETPOST('type_code', 'alphanohtml');
$fkPowerPlant = GETPOSTINT('fk_powerplant');
$backtopage = GETPOST('backtopage', 'alpha');

if (!isModEnabled('powerplantpv') || !getDolGlobalInt('POWERPLANTPV_ATTESTATION_ENABLE', 1)) {
	dol_syslog(
		'PowerPlantPV attestation card forbidden: module or attestation feature disabled action='.$action.' id='.$id,
		LOG_WARNING
	);
	accessforbidden();
}

if (!class_exists('PowerPlantPVAttestation') || !class_exists('PowerPlantPVAttestationTypes') || !function_exists('powerplantpvAttestationUserHasRight')) {
	dol_syslog(
		'PowerPlantPV attestation card unavailable: missing class or permission helper action='.$action.' id='.$id,
		LOG_ERR
	);
	llxHeader('', $langs->trans('Attestation'), '', '', 0, 0, '', '', '', 'mod-powerplantpv page-attestation-card');
	print '<div class="error">'.$langs->trans('AttestationInstallationIncomplete').'</div>';
	llxFooter();
	$db->close();
	exit;
}

$object = new PowerPlantPVAttestation($db);
$permissiontoread = powerplantpvAttestationUserHasRight($user, 'read');
$permissiontoadd = powerplantpvAttestationUserHasRight($user, 'write');
$permissiontodelete = powerplantpvAttestationUserHasRight($user, 'delete');
$permissiontovalidate = powerplantpvAttestationUserHasRight($user, 'validate');
$permissiontosign = powerplantpvAttestationUserHasRight($user, 'sign');
$permissiontocancel = powerplantpvAttestationUserHasRight($user, 'cancel');

$isCreateAccess = ($id <= 0 && in_array($action, array('create', 'add'), true));
if (($isCreateAccess && !$permissiontoadd) || (!$isCreateAccess && !$permissiontoread)) {
	dol_syslog(
		'PowerPlantPV attestation card forbidden: missing '.($isCreateAccess ? 'write' : 'read').' right for user '.$user->id.' action='.$action.' id='.$id,
		LOG_WARNING
	);
	accessforbidden();
}

if (function_exists('powerplantpvAttestationGetInstallationIssues')) {
	$attestationInstallationIssues = powerplantpvAttestationGetInstallationIssues();
	if (!empty($attestationInstallationIssues['tables'])) {
		dol_syslog(
			'PowerPlantPV attestation card unavailable: missing tables '.implode(', ', $attestationInstallationIssues['tables']).' action='.$action.' id='.$id,
			LOG_ERR
		);
		llxHeader('', $langs->trans('Attestation'), '', '', 0, 0, '', '', '', 'mod-powerplantpv page-attestation-card');
		powerplantpvAttestationPrintInstallationWarnings();
		llxFooter();
		$db->close();
		exit;
	}
}

if ($id > 0) {
	$result = $object->fetch($id);
	if ($result <= 0) {
		accessforbidden();
	}
	$isdraft = ((int) $object->status === PowerPlantPVAttestation::STATUS_DRAFT ? 1 : 0);
	restrictedArea($user, $object->module, $object, $object->table_element, $object->element, 'fk_soc', 'rowid', $isdraft);
}

/**
 * Return a POSTed Dolibarr date or an empty value.
 *
 * @param	string	$prefix	Input prefix
 * @return	int|string		Timestamp or empty string
 */
function powerplantpvAttestationGetPostDate($prefix)
{
	$year = GETPOSTINT($prefix.'year');
	$month = GETPOSTINT($prefix.'month');
	$day = GETPOSTINT($prefix.'day');
	if (empty($year) || empty($month) || empty($day)) {
		return '';
	}

	return dol_mktime(0, 0, 0, $month, $day, $year);
}

/**
 * Set attestation fields from POST.
 *
 * @param	PowerPlantPVAttestation	$object	Attestation
 * @return	void
 */
function powerplantpvAttestationSetFromPost($object)
{
	$object->type_code = GETPOST('type_code', 'alphanohtml');
	$object->fk_powerplant = GETPOSTINT('fk_powerplant') ?: null;
	$object->fk_soc = GETPOSTINT('fk_soc') ?: null;
	$object->socid = $object->fk_soc;
	$object->fk_project = GETPOSTINT('fk_project') ?: null;
	$object->date_attestation = powerplantpvAttestationGetPostDate('date_attestation');
	$object->date_setting = powerplantpvAttestationGetPostDate('date_setting');
	$object->date_completion = powerplantpvAttestationGetPostDate('date_completion');
	$object->bta_contract_number = GETPOST('bta_contract_number', 'alphanohtml');
	$object->max_export_power_kw = price2num(GETPOST('max_export_power_kw', 'alphanohtml'), 'MU');
	$object->max_frequency_hz = price2num(GETPOST('max_frequency_hz', 'alphanohtml'), 'MU');
	$object->landscape_integration_prime = GETPOSTINT('landscape_integration_prime');
	$object->note_public = GETPOST('note_public', 'restricthtml');
	$object->note_private = GETPOST('note_private', 'restricthtml');
}

/**
 * Return power plant select options.
 *
 * @return	array<int,string>	Options
 */
function powerplantpvAttestationPowerPlantOptions()
{
	global $db;

	$options = array();
	$sql = "SELECT rowid, ref, label FROM ".$db->prefix()."powerplantpv_powerplant";
	$sql .= " WHERE entity IN (".getEntity('powerplant').")";
	$sql .= " ORDER BY ref ASC";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$options[(int) $obj->rowid] = trim($obj->ref.' - '.$obj->label);
		}
		$db->free($resql);
	}

	return $options;
}

/*
 * Actions
 */

if ($action == 'add' && $permissiontoadd) {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
	$object = new PowerPlantPVAttestation($db);
	$object->type_code = GETPOST('type_code', 'alphanohtml');
	$fkPowerPlant = GETPOSTINT('fk_powerplant');
	$result = powerplantpvAttestationPrefillFromPowerPlant($object, $fkPowerPlant, $user);
	if ($result >= 0) {
		powerplantpvAttestationSetFromPost($object);
		$result = $object->create($user);
	}
	if ($result > 0) {
		setEventMessages($langs->trans('RecordCreated'), null, 'mesgs');
		header('Location: '.dol_buildpath('/powerplantpv/attestation_card.php', 1).'?id='.(int) $object->id);
		exit;
	}
	setEventMessages($object->error, $object->errors, 'errors');
	$action = 'create';
} elseif ($action == 'update' && $permissiontoadd && $object->id > 0) {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
	powerplantpvAttestationSetFromPost($object);
	$result = $object->update($user);
	if ($result > 0) {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $object->id);
		exit;
	}
	setEventMessages($object->error, $object->errors, 'errors');
	$action = 'edit';
} elseif ($action == 'confirm_validate' && $confirm == 'yes' && $permissiontovalidate && $object->id > 0) {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
	$result = $object->validate($user);
	if ($result > 0) {
		setEventMessages($langs->trans('AttestationValidated'), null, 'mesgs');
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $object->id);
	exit;
} elseif ($action == 'confirm_cancel' && $confirm == 'yes' && $permissiontocancel && $object->id > 0) {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
	$result = $object->cancel($user);
	if ($result > 0) {
		setEventMessages($langs->trans('AttestationCanceled'), null, 'mesgs');
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $object->id);
	exit;
} elseif ($action == 'confirm_delete' && $confirm == 'yes' && $permissiontodelete && $object->id > 0) {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
	$result = $object->delete($user);
	if ($result > 0) {
		setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
		header('Location: '.dol_buildpath('/powerplantpv/attestation_list.php', 1));
		exit;
	}
	setEventMessages($object->error, $object->errors, 'errors');
} elseif ($action == 'sendsign' && $permissiontosign && $object->id > 0) {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
	$result = $object->sendToSignature($user);
	if ($result > 0) {
		setEventMessages($langs->trans('AttestationSentToSignature'), null, 'mesgs');
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $object->id);
	exit;
}

$form = new Form($db);
$formfile = new FormFile($db);
$formactions = new FormActions($db);
$formproject = new FormProjets($db);

if ($object->id > 0) {
	$upload_dir = powerplantpvAttestationGetDocumentUploadDir($object);
	include DOL_DOCUMENT_ROOT.'/core/actions_dellink.inc.php';
	include DOL_DOCUMENT_ROOT.'/core/actions_linkedfiles.inc.php';
	$upload_dir = powerplantpvAttestationGetDocumentRootDir(!empty($object->entity) ? $object->entity : $conf->entity);
	include DOL_DOCUMENT_ROOT.'/core/actions_builddoc.inc.php';
}

/*
 * View
 */

$title = $langs->trans('Attestation');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-powerplantpv page-attestation-card');

if ($action == 'delete' && $object->id > 0) {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.(int) $object->id, $langs->trans('Delete'), $langs->trans('ConfirmDeleteObject', $object->ref), 'confirm_delete', '', 0, 1);
}
if ($action == 'validate' && $object->id > 0) {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.(int) $object->id, $langs->trans('Validate'), $langs->trans('ConfirmValidateAttestation', $object->ref), 'confirm_validate', '', 0, 1);
}
if ($action == 'cancel' && $object->id > 0) {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.(int) $object->id, $langs->trans('Cancel'), $langs->trans('ConfirmCancelAttestation', $object->ref), 'confirm_cancel', '', 0, 1);
}

if ($action == 'create' && empty($typeCode)) {
	print load_fiche_titre($langs->trans('New_Attestation'), '', 'fa-file-signature');
	print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="action" value="create">';
	print '<table class="border centpercent tableforfieldedit">';
	print '<tr><td class="titlefieldcreate">'.$langs->trans('AttestationType').'</td><td>'.$form->selectarray('type_code', PowerPlantPVAttestationTypes::getTypeLabels($langs), '', 1, 0, 0, '', 0, 0, 0, '', 'flat minwidth300').'</td></tr>';
	print '<tr><td>'.$langs->trans('PowerPlant').'</td><td>'.$form->selectarray('fk_powerplant', powerplantpvAttestationPowerPlantOptions(), $fkPowerPlant, 1, 0, 0, '', 0, 0, 0, '', 'flat minwidth300').'</td></tr>';
	print '</table>';
	print '<div class="center"><input type="submit" class="button" value="'.$langs->trans('Next').'"></div>';
	print '</form>';
} elseif ($action == 'create' || $action == 'edit') {
	if (!$permissiontoadd) {
		accessforbidden();
	}
	if ($action == 'create') {
		$object = new PowerPlantPVAttestation($db);
		$object->type_code = $typeCode;
		powerplantpvAttestationPrefillFromPowerPlant($object, $fkPowerPlant, $user);
	}

	$head = ($object->id > 0 ? powerplantpvAttestationPrepareHead($object) : array());
	if ($object->id > 0) {
		print dol_get_fiche_head($head, 'card', $langs->trans('Attestation'), -1, $object->picto);
	}
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="'.($object->id > 0 ? 'update' : 'add').'">';
	if ($object->id > 0) {
		print '<input type="hidden" name="id" value="'.((int) $object->id).'">';
	}
	print '<input type="hidden" name="type_code" value="'.dol_escape_htmltag($object->type_code).'">';

	print '<div class="fichecenter"><div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfieldedit">';
	print '<tr><td class="titlefieldcreate">'.$langs->trans('AttestationType').'</td><td>'.dol_escape_htmltag(PowerPlantPVAttestationTypes::getTypeLabels($langs)[$object->type_code] ?? $object->type_code).'</td></tr>';
	print '<tr><td>'.$langs->trans('PowerPlant').'</td><td>'.$form->selectarray('fk_powerplant', powerplantpvAttestationPowerPlantOptions(), (int) $object->fk_powerplant, 1, 0, 0, '', 0, 0, 0, '', 'flat minwidth300').'</td></tr>';
	print '<tr><td>'.$langs->trans('ThirdParty').'</td><td>'.$form->select_company($object->fk_soc, 'fk_soc', '', 1, 0, 0, array(), 0, 'minwidth300').'</td></tr>';
	if (isModEnabled('project')) {
		print '<tr><td>'.$langs->trans('Project').'</td><td>'.$formproject->select_projects($object->fk_soc, $object->fk_project, 'fk_project', 0, 0, 1, 1, 0, 0, 0, '', 1, 0, 'maxwidth500').'</td></tr>';
	}
	print '<tr><td>'.$langs->trans('AttestationDate').'</td><td>'.$form->selectDate($object->date_attestation, 'date_attestation', 0, 0, 0, '', 1, 1).'</td></tr>';
	if (in_array($object->type_code, array(PowerPlantPVAttestationTypes::TYPE_BRIDAGE_DYNAMIQUE_ONDULEUR, PowerPlantPVAttestationTypes::TYPE_BRIDAGE_STATIQUE_ONDULEUR), true)) {
		print '<tr><td>'.$langs->trans('AttestationMaxExportPowerKw').'</td><td><input type="text" class="flat maxwidth100 right" name="max_export_power_kw" value="'.dol_escape_htmltag($object->max_export_power_kw).'"></td></tr>';
	}
	if ($object->type_code == PowerPlantPVAttestationTypes::TYPE_REGLAGE_MAX_FREQ_51_5HZ) {
		print '<tr><td>'.$langs->trans('AttestationMaxFrequencyHz').'</td><td><input type="text" class="flat maxwidth100 right" name="max_frequency_hz" value="'.dol_escape_htmltag($object->max_frequency_hz).'"></td></tr>';
		print '<tr><td>'.$langs->trans('AttestationSettingDate').'</td><td>'.$form->selectDate($object->date_setting, 'date_setting', 0, 0, 1, '', 1, 1).'</td></tr>';
	}
	if ($object->type_code == PowerPlantPVAttestationTypes::TYPE_INSTALLATEUR_INF_100KWC) {
		print '<tr><td>'.$langs->trans('AttestationBtaContractNumber').'</td><td><input type="text" class="flat minwidth200" name="bta_contract_number" value="'.dol_escape_htmltag($object->bta_contract_number).'"></td></tr>';
		print '<tr><td>'.$langs->trans('AttestationCompletionDate').'</td><td>'.$form->selectDate($object->date_completion, 'date_completion', 0, 0, 1, '', 1, 1).'</td></tr>';
		print '<tr><td>'.$langs->trans('AttestationLandscapeIntegrationPrime').'</td><td>'.$form->selectyesno('landscape_integration_prime', (int) $object->landscape_integration_prime, 1).'</td></tr>';
	}
	print '<tr><td>'.$langs->trans('NotePublic').'</td><td><textarea class="flat centpercent" rows="3" name="note_public">'.dol_escape_htmltag($object->note_public).'</textarea></td></tr>';
	if (empty($user->socid)) {
		print '<tr><td>'.$langs->trans('NotePrivate').'</td><td><textarea class="flat centpercent" rows="3" name="note_private">'.dol_escape_htmltag($object->note_private).'</textarea></td></tr>';
	}
	print '</table></div>';
	if ($object->id > 0) {
		print dol_get_fiche_end();
	}
	print $form->buttonsSaveCancel();
	print '</form>';
} elseif ($object->id > 0) {
	$derivedData = powerplantpvAttestationGetDerivedData($object, $langs);
	$head = powerplantpvAttestationPrepareHead($object);
	print dol_get_fiche_head($head, ($tab == 'notes' ? 'notes' : 'card'), $langs->trans('Attestation'), -1, $object->picto);
	dol_banner_tab($object, 'ref', powerplantpvAttestationGetBackToListLink($object), 1, 'ref', 'ref', powerplantpvAttestationBuildBannerMoreHtml($object));
	print '<div class="fichecenter"><div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield">'.$langs->trans('AttestationType').'</td><td>'.dol_escape_htmltag(PowerPlantPVAttestationTypes::getTypeLabels($langs)[$object->type_code] ?? $object->type_code).'</td></tr>';
	print '<tr><td>'.$langs->trans('AttestationDate').'</td><td>'.dol_print_date($object->date_attestation, 'day').'</td></tr>';
	if (in_array($object->type_code, array(PowerPlantPVAttestationTypes::TYPE_BRIDAGE_DYNAMIQUE_ONDULEUR, PowerPlantPVAttestationTypes::TYPE_BRIDAGE_STATIQUE_ONDULEUR), true)) {
		print '<tr><td>'.$langs->trans('AttestationMaxExportPowerKw').'</td><td>'.($object->max_export_power_kw !== null && $object->max_export_power_kw !== '' ? price($object->max_export_power_kw) : '').'</td></tr>';
	}
	if ($object->type_code == PowerPlantPVAttestationTypes::TYPE_REGLAGE_MAX_FREQ_51_5HZ) {
		print '<tr><td>'.$langs->trans('AttestationMaxFrequencyHz').'</td><td>'.($object->max_frequency_hz !== null && $object->max_frequency_hz !== '' ? price($object->max_frequency_hz) : '').'</td></tr>';
		print '<tr><td>'.$langs->trans('AttestationSettingDate').'</td><td>'.(!empty($object->date_setting) ? dol_print_date($object->date_setting, 'day') : '').'</td></tr>';
	}
	if ($object->type_code == PowerPlantPVAttestationTypes::TYPE_INSTALLATEUR_INF_100KWC) {
		print '<tr><td>'.$langs->trans('AttestationBtaContractNumber').'</td><td>'.dol_escape_htmltag($object->bta_contract_number).'</td></tr>';
		print '<tr><td>'.$langs->trans('AttestationCompletionDate').'</td><td>'.(!empty($object->date_completion) ? dol_print_date($object->date_completion, 'day') : '').'</td></tr>';
		print '<tr><td>'.$langs->trans('AttestationLandscapeIntegrationPrime').'</td><td>'.yn((int) $object->landscape_integration_prime).'</td></tr>';
	}
	$writerHtml = dol_escape_htmltag($derivedData['writer_name']);
	if (!empty($derivedData['writer_id'])) {
		require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
		$writer = new User($db);
		if ($writer->fetch((int) $derivedData['writer_id']) > 0) {
			$writerHtml = $writer->getNomUrl(1);
		}
	}
	print '<tr><td>'.$langs->trans('AttestationWriterName').'</td><td>'.$writerHtml.'</td></tr>';
	if (!empty($object->date_signature)) {
		print '<tr><td>'.$langs->trans('AttestationSignatureDate').'</td><td>'.dol_print_date($object->date_signature, 'dayhour').'</td></tr>';
	}
	print '</table>';
	print load_fiche_titre($langs->trans('AttestationEquipment'), '', 'fa-cubes');
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Type').'</td><td>'.$langs->trans('Designation').'</td><td>'.$langs->trans('Model').'</td><td>'.$langs->trans('SerialNumber').'</td><td>'.$langs->trans('AttestationBridage').'</td></tr>';
	if (empty($object->lines)) {
		print '<tr class="oddeven"><td colspan="5"><span class="opacitymedium">'.$langs->trans('None').'</span></td></tr>';
	} else {
		foreach ($object->lines as $line) {
			print '<tr class="oddeven"><td>'.dol_escape_htmltag($line->equipment_type).'</td><td>'.dol_escape_htmltag($line->designation).'</td><td>'.dol_escape_htmltag($line->model).'</td><td>'.dol_escape_htmltag($line->serial_number).'</td><td>'.yn($line->bridage_enabled).'</td></tr>';
		}
	}
	print '</table></div>';
	print '</div>';
	print dol_get_fiche_end();

	print '<div class="tabsAction">';
	if ($permissiontoadd && $object->status != PowerPlantPVAttestation::STATUS_SIGNED) {
		print dolGetButtonAction($langs->trans('Modify'), '', 'default', $_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=edit&token='.newToken(), '', true);
	}
	if ($permissiontovalidate && $object->status == PowerPlantPVAttestation::STATUS_DRAFT) {
		print dolGetButtonAction($langs->trans('Validate'), '', 'default', $_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=validate&token='.newToken(), '', true);
	}
	if ($permissiontosign && in_array((int) $object->status, array(PowerPlantPVAttestation::STATUS_VALIDATED, PowerPlantPVAttestation::STATUS_PENDING_SIGNATURE), true)) {
		print dolGetButtonAction($langs->trans('AttestationSendToSignature'), '', 'default', $_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=sendsign&token='.newToken(), '', true);
		print dolGetButtonAction($langs->trans('Sign'), '', 'default', dol_buildpath('/powerplantpv/attestation_signature.php', 1).'?id='.(int) $object->id, '', true);
	}
	if ($permissiontocancel && !in_array((int) $object->status, array(PowerPlantPVAttestation::STATUS_SIGNED, PowerPlantPVAttestation::STATUS_CANCELED), true)) {
		print dolGetButtonAction($langs->trans('Cancel'), '', 'default', $_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=cancel&token='.newToken(), '', true);
	}
	if ($permissiontodelete && in_array((int) $object->status, array(PowerPlantPVAttestation::STATUS_DRAFT, PowerPlantPVAttestation::STATUS_CANCELED), true)) {
		print dolGetButtonAction($langs->trans('Delete'), '', 'delete', $_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=delete&token='.newToken(), '', true);
	}
	print '</div>';

	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	$uploadDir = powerplantpvAttestationGetDocumentUploadDir($object);
	powerplantpvAttestationPrintDocumentGenerationForm($object, $_SERVER['PHP_SELF'].'?id='.(int) $object->id, $permissiontoadd, $object->model_pdf, 0, '', $langs->defaultlang);
	$formfile->showdocuments(powerplantpvAttestationGetDocumentModulePart(), powerplantpvAttestationGetDocumentRelativePath($object), $uploadDir, $_SERVER['PHP_SELF'].'?id='.(int) $object->id, 0, $permissiontodelete, $object->model_pdf, 1, 0, 0, 28, 0, '', 'none', '', $langs->defaultlang, '', $object);
	if (method_exists($form, 'showLinkedObjectBlock')) {
		$form->showLinkedObjectBlock($object);
	}
	print '</div>';
	print '<div class="fichehalfright">';
	if (isModEnabled('agenda')) {
		$formactions->showactions($object, 'attestation@powerplantpv', $object->fk_soc, 1);
	}
	print '</div>';
	print '</div>';
}

llxFooter();
$db->close();
