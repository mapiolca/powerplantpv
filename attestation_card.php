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
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
dol_include_once('/powerplantpv/class/powerplantpvattestation.class.php');
dol_include_once('/powerplantpv/class/powerplantpvattestationtypes.class.php');
dol_include_once('/powerplantpv/class/powerplantpvcompatibility.class.php');
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
$extrafields = new ExtraFields($db);
$hookmanager->initHooks(array($object->element.'card', 'globalcard'));
$extrafields->fetch_name_optionals_label($object->table_element);
$permissiontoread = powerplantpvAttestationUserHasRight($user, 'read');
$permissiontoadd = powerplantpvAttestationUserHasRight($user, 'write');
$permissiontodelete = powerplantpvAttestationUserHasRight($user, 'delete');
$permissiontovalidate = powerplantpvAttestationUserHasRight($user, 'validate');
$permissiontosign = powerplantpvAttestationUserHasRight($user, 'sign');
$permissiontocancel = powerplantpvAttestationUserHasRight($user, 'cancel');
$permissiontoedit = $permissiontoadd;
$permissiontodeleteobject = $permissiontodelete;
$permissiontogeneratedocument = $permissiontoadd;
$permissiontodeletedocument = $permissiontodelete;

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
	if (!empty($attestationInstallationIssues['tables']) || !empty($attestationInstallationIssues['columns'])) {
		dol_syslog(
			'PowerPlantPV attestation card unavailable: missing tables '.implode(', ', $attestationInstallationIssues['tables']).' columns '.implode(', ', $attestationInstallationIssues['columns']).' action='.$action.' id='.$id,
			LOG_ERR
		);
		llxHeader('', $langs->trans('Attestation'), '', '', 0, 0, '', '', '', 'mod-powerplantpv page-attestation-card');
		powerplantpvAttestationPrintInstallationWarnings();
		llxFooter();
		$db->close();
		exit;
	}
}

if ($tab === 'notes' && $id > 0) {
	header('Location: '.dol_buildpath('/powerplantpv/attestation_note.php', 1).'?id='.(int) $id);
	exit;
}

if ($id > 0) {
	$result = $object->fetch($id);
	if ($result <= 0) {
		accessforbidden();
	}
	if (!empty($object->isextrafieldmanaged) && method_exists($object, 'fetch_optionals')) {
		$object->fetch_optionals();
	}
	$isdraft = ((int) $object->status === PowerPlantPVAttestation::STATUS_DRAFT ? 1 : 0);
	powerplantpvRequireSharedObjectReadAccess($user, $object, $permissiontoread, $isdraft);
	$permissiontoedit = powerplantpvAttestationCanEdit($user, $object);
	$permissiontodeleteobject = powerplantpvAttestationCanDelete($user, $object);
	$permissiontogeneratedocument = powerplantpvAttestationCanGenerateDocument($user, $object);
	$permissiontodeletedocument = powerplantpvAttestationCanDeleteDocument($user, $object);
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
 * Return a POSTed Dolibarr date/time or an empty value.
 *
 * @param	string	$prefix	Input prefix
 * @return	int|string		Timestamp or empty string
 */
function powerplantpvAttestationGetPostDateTime($prefix)
{
	$year = GETPOSTINT($prefix.'year');
	$month = GETPOSTINT($prefix.'month');
	$day = GETPOSTINT($prefix.'day');
	if (empty($year) || empty($month) || empty($day)) {
		return '';
	}

	return dol_mktime(GETPOSTINT($prefix.'hour'), GETPOSTINT($prefix.'min'), 0, $month, $day, $year);
}

/**
 * Return a POSTed decimal value while preserving empty values as null.
 *
 * @param	string	$key	Input name
 * @return	float|int|string|null	Parsed value or null when empty
 */
function powerplantpvAttestationGetPostDecimalOrNull($key)
{
	$value = trim((string) GETPOST($key, 'alphanohtml'));
	if ($value === '') {
		return null;
	}

	return price2num($value, 'MU');
}

/**
 * Set attestation fields from POST.
 *
 * @param	PowerPlantPVAttestation	$object					Attestation
 * @param	int<0,1>					$allowSignatureMetadata	Allow signature metadata edition
 * @return	void
 */
function powerplantpvAttestationSetFromPost($object, $allowSignatureMetadata = 0)
{
	$object->type_code = GETPOST('type_code', 'alphanohtml');
	$object->fk_powerplant = GETPOSTINT('fk_powerplant') ?: null;
	if (GETPOSTISSET('fk_soc')) {
		$object->fk_soc = GETPOSTINT('fk_soc') ?: null;
	}
	$object->socid = $object->fk_soc;
	$object->fk_project = GETPOSTINT('fk_project') ?: null;
	$object->date_attestation = powerplantpvAttestationGetPostDate('date_attestation');
	$object->date_setting = powerplantpvAttestationGetPostDate('date_setting');
	$object->date_completion = powerplantpvAttestationGetPostDate('date_completion');
	$object->bta_contract_number = GETPOST('bta_contract_number', 'alphanohtml');
	$object->max_export_power_kw = powerplantpvAttestationGetPostDecimalOrNull('max_export_power_kw');
	$object->max_frequency_hz = powerplantpvAttestationGetPostDecimalOrNull('max_frequency_hz');
	$object->landscape_integration_prime = GETPOSTINT('landscape_integration_prime');
	$object->note_public = GETPOST('note_public', 'restricthtml');
	$object->note_private = GETPOST('note_private', 'restricthtml');
	if (!empty($allowSignatureMetadata)) {
		$object->fk_user_sign = GETPOSTINT('fk_user_sign') ?: null;
		$object->date_signature = powerplantpvAttestationGetPostDateTime('date_signature');
		$object->signature_ip = GETPOST('signature_ip', 'nohtml');
		$object->signature_user_agent = GETPOST('signature_user_agent', 'nohtml');
		$object->online_sign_name = GETPOST('online_sign_name', 'nohtml');
		$object->signature_hash = GETPOST('signature_hash', 'alphanohtml');
		$object->signature_file = GETPOST('signature_file', 'nohtml');
		$object->signed_pdf_file = GETPOST('signed_pdf_file', 'nohtml');
	}
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

/**
 * Return source errors that block attestation creation.
 *
 * @param	string	$typeCode		Type code
 * @param	int		$fkPowerPlant	Power plant id
 * @return	string[]				Translation keys
 */
function powerplantpvAttestationGetCreateSourceErrors($typeCode, $fkPowerPlant)
{
	global $db;

	$errors = array();

	if ($typeCode === '') {
		$errors[] = 'AttestationTypeRequired';
	} elseif (!PowerPlantPVAttestationTypes::isValidType($typeCode)) {
		$errors[] = 'AttestationInvalidType';
	}
	if ($fkPowerPlant <= 0) {
		$errors[] = 'AttestationPowerPlantRequired';
	} else {
		dol_include_once('/powerplantpv/class/powerplant.class.php');
		$powerplant = new PowerPlant($db);
		if ($powerplant->fetch((int) $fkPowerPlant) <= 0) {
			$errors[] = 'AttestationPowerPlantRequired';
		} else {
			$entityScope = array_map('intval', explode(',', getEntity('powerplant')));
			if (!in_array((int) $powerplant->entity, $entityScope, true)) {
				$errors[] = 'AttestationPowerPlantRequired';
			}
		}
	}

	return $errors;
}

/*
 * Actions
 */

$parameters = array('id' => $id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (!empty($reshook)) {
	// Hook handled or replaced standard actions.
} elseif ($action == 'add' && $permissiontoadd) {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
	$object = new PowerPlantPVAttestation($db);
	$object->type_code = GETPOST('type_code', 'alphanohtml');
	$fkPowerPlant = GETPOSTINT('fk_powerplant');
	$sourceErrors = powerplantpvAttestationGetCreateSourceErrors($object->type_code, $fkPowerPlant);
	if (!empty($sourceErrors)) {
		foreach ($sourceErrors as $sourceError) {
			$object->errors[] = $langs->trans($sourceError);
		}
		$object->error = $object->errors[0];
		$result = -1;
	} else {
		$result = powerplantpvAttestationPrefillFromPowerPlant($object, $fkPowerPlant, $user);
	}
	if ($result >= 0) {
		powerplantpvAttestationSetFromPost($object);
		if ($object->type_code === PowerPlantPVAttestationTypes::TYPE_BRIDAGE_DYNAMIQUE_ONDULEUR && $object->max_export_power_kw === null) {
			$object->error = 'AttestationMaxExportPowerRequired';
			$result = -1;
		} else {
			$result = $object->create($user);
		}
	}
	if ($result > 0) {
		setEventMessages($langs->trans('RecordCreated'), null, 'mesgs');
		header('Location: '.dol_buildpath('/powerplantpv/attestation_card.php', 1).'?id='.(int) $object->id);
		exit;
	}
	setEventMessages($object->error, $object->errors, 'errors');
	$action = 'create';
} elseif ($action == 'update' && $permissiontoedit && $object->id > 0) {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
	$allowSignedAction = powerplantpvAttestationCanManageSigned($user, $object);
	powerplantpvAttestationSetFromPost($object, $allowSignedAction);
	$sourceErrors = powerplantpvAttestationGetCreateSourceErrors($object->type_code, (int) $object->fk_powerplant);
	if (!empty($sourceErrors)) {
		$object->errors = array();
		foreach ($sourceErrors as $sourceError) {
			$object->errors[] = $langs->trans($sourceError);
		}
		$object->error = $object->errors[0];
		$result = -1;
	} else {
		$result = $object->update($user, 0, $allowSignedAction);
	}
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
} elseif ($action == 'confirm_delete' && $confirm == 'yes' && $permissiontodeleteobject && $object->id > 0) {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
	$result = $object->delete($user, 0, powerplantpvAttestationCanManageSigned($user, $object));
	if ($result > 0) {
		setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
		header('Location: '.dol_buildpath('/powerplantpv/attestation_list.php', 1));
		exit;
	}
	setEventMessages($object->error, $object->errors, 'errors');
}

$form = new Form($db);
$formfile = new FormFile($db);
$formactions = new FormActions($db);
$formproject = new FormProjets($db);

if (empty($reshook) && $object->id > 0) {
	$oldpermissiontoadd = $permissiontoadd;
	$oldpermissiontodelete = $permissiontodelete;
	$oldpermission = isset($permission) ? $permission : null;
	$oldpermtoedit = isset($permtoedit) ? $permtoedit : null;
	$permissiontoadd = $permissiontogeneratedocument;
	$permissiontodelete = $permissiontodeletedocument;
	$permission = $permissiontogeneratedocument;
	$permtoedit = $permissiontogeneratedocument;
	if ((int) $object->status === PowerPlantPVAttestation::STATUS_SIGNED && $permissiontogeneratedocument) {
		if (!isset($object->context) || !is_array($object->context)) {
			$object->context = array();
		}
		$object->context['allow_signed_generation'] = 1;
	}
	$upload_dir = powerplantpvAttestationGetDocumentUploadDir($object);
	include DOL_DOCUMENT_ROOT.'/core/actions_dellink.inc.php';
	include DOL_DOCUMENT_ROOT.'/core/actions_linkedfiles.inc.php';
	$upload_dir = powerplantpvAttestationGetDocumentRootDir(!empty($object->entity) ? $object->entity : $conf->entity);
	include DOL_DOCUMENT_ROOT.'/core/actions_builddoc.inc.php';
	$permissiontoadd = $oldpermissiontoadd;
	$permissiontodelete = $oldpermissiontodelete;
	if ($oldpermission === null) {
		unset($permission);
	} else {
		$permission = $oldpermission;
	}
	if ($oldpermtoedit === null) {
		unset($permtoedit);
	} else {
		$permtoedit = $oldpermtoedit;
	}
	if (isset($object->context) && is_array($object->context)) {
		unset($object->context['allow_signed_generation']);
	}
}

/*
 * View
 */

$title = $langs->trans('Attestation');
if ($action == 'create' && empty($object->id)) {
	$title = $langs->trans('New_Attestation');
} elseif ($object->id > 0 && !empty($object->ref)) {
	$typeLabels = PowerPlantPVAttestationTypes::getTypeLabels($langs);
	$typeLabel = !empty($typeLabels[$object->type_code]) ? $typeLabels[$object->type_code] : $object->type_code;
	$title = $object->ref.' - '.$langs->trans('Attestation').($typeLabel !== '' ? ' '.$typeLabel : '');
}
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-powerplantpv page-attestation-card');

if ($action == 'delete' && $object->id > 0 && $permissiontodeleteobject) {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.(int) $object->id, $langs->trans('Delete'), $langs->trans('ConfirmDeleteObject', $object->ref), 'confirm_delete', '', 0, 1);
}
if ($action == 'validate' && $object->id > 0) {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.(int) $object->id, $langs->trans('Validate'), $langs->trans('ConfirmValidateAttestation', $object->ref), 'confirm_validate', '', 0, 1);
}
if ($action == 'cancel' && $object->id > 0) {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.(int) $object->id, $langs->trans('Cancel'), $langs->trans('ConfirmCancelAttestation', $object->ref), 'confirm_cancel', '', 0, 1);
}

if ($action == 'create' || $action == 'edit') {
	if (($action == 'create' && !$permissiontoadd) || ($action == 'edit' && !$permissiontoedit)) {
		accessforbidden();
	}
	if ($action == 'create') {
		$object = new PowerPlantPVAttestation($db);
		$object->type_code = PowerPlantPVAttestationTypes::isValidType($typeCode) ? $typeCode : '';
		if ($object->type_code !== '') {
			$result = powerplantpvAttestationPrefillFromPowerPlant($object, $fkPowerPlant, $user);
			if ($result < 0) {
				setEventMessages($object->error, $object->errors, 'errors');
			}
		} else {
			powerplantpvAttestationPrefillFromPowerPlant($object, 0, $user);
			$object->fk_powerplant = $fkPowerPlant > 0 ? $fkPowerPlant : null;
		}
	}

	$head = ($object->id > 0 ? powerplantpvAttestationPrepareHead($object) : array());
	if ($object->id > 0) {
		print dol_get_fiche_head($head, 'card', $langs->trans('Attestation'), -1, $object->picto);
	} else {
		print load_fiche_titre($langs->trans('New_Attestation'), '', 'fa-file-signature');
	}
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="'.($object->id > 0 ? 'update' : 'add').'">';
	if ($object->id > 0) {
		print '<input type="hidden" name="id" value="'.((int) $object->id).'">';
	}
	if ($object->id > 0) {
		print '<input type="hidden" name="type_code" value="'.dol_escape_htmltag($object->type_code).'">';
	}

	print '<div class="fichecenter"><div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfieldedit">';
	if ($object->id > 0) {
		print '<tr><td class="titlefieldcreate">'.$langs->trans('AttestationType').'</td><td>'.dol_escape_htmltag(PowerPlantPVAttestationTypes::getTypeLabels($langs)[$object->type_code] ?? $object->type_code).'</td></tr>';
	} else {
		print '<tr><td class="titlefieldcreate">'.$langs->trans('AttestationType').'</td><td>'.$form->selectarray('type_code', PowerPlantPVAttestationTypes::getTypeLabels($langs), $object->type_code, 1, 0, 0, '', 0, 0, 0, '', 'flat minwidth300').'</td></tr>';
	}
	print '<tr><td class="titlefieldcreate">'.$langs->trans('PowerPlant').'</td><td>'.$form->selectarray('fk_powerplant', powerplantpvAttestationPowerPlantOptions(), (int) $object->fk_powerplant, 1, 0, 0, '', 0, 0, 0, '', 'flat minwidth300').'</td></tr>';
	if ($object->id > 0) {
		print '<tr><td>'.$langs->trans('ThirdParty').'</td><td>'.$form->select_company($object->fk_soc, 'fk_soc', '', 1, 0, 0, array(), 0, 'minwidth300').'</td></tr>';
	}
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
	if ($object->id > 0 && (int) $object->status === PowerPlantPVAttestation::STATUS_SIGNED && powerplantpvAttestationCanManageSigned($user, $object)) {
		print '<tr><td>'.$langs->trans('AttestationSigner').'</td><td>'.$form->select_dolusers($object->fk_user_sign, 'fk_user_sign', 1, null, 0, '', '', '0', 0, 0, '', 0, '', 'maxwidth300').'</td></tr>';
		print '<tr><td>'.$langs->trans('AttestationSignatureDate').'</td><td>'.$form->selectDate($object->date_signature, 'date_signature', 1, 1, 1, '', 1, 1).'</td></tr>';
		print '<tr><td>'.$langs->trans('AttestationOnlineSignName').'</td><td><input type="text" class="flat minwidth300" name="online_sign_name" value="'.dol_escape_htmltag($object->online_sign_name).'"></td></tr>';
		print '<tr><td>'.$langs->trans('AttestationSignatureHash').'</td><td><input type="text" class="flat minwidth500" name="signature_hash" value="'.dol_escape_htmltag($object->signature_hash).'"></td></tr>';
		print '<tr><td>'.$langs->trans('AttestationSignatureFile').'</td><td><input type="text" class="flat minwidth500" name="signature_file" value="'.dol_escape_htmltag($object->signature_file).'"></td></tr>';
		print '<tr><td>'.$langs->trans('AttestationSignedPdfFile').'</td><td><input type="text" class="flat minwidth500" name="signed_pdf_file" value="'.dol_escape_htmltag($object->signed_pdf_file).'"></td></tr>';
		print '<tr><td>'.$langs->trans('IPAddress').'</td><td><input type="text" class="flat minwidth200" name="signature_ip" value="'.dol_escape_htmltag($object->signature_ip).'"></td></tr>';
		print '<tr><td>'.$langs->trans('UserAgent').'</td><td><input type="text" class="flat minwidth500" name="signature_user_agent" value="'.dol_escape_htmltag($object->signature_user_agent).'"></td></tr>';
	}
	print '</table></div>';
	if ($object->id > 0) {
		print dol_get_fiche_end();
	}
	print $form->buttonsSaveCancel();
	print '</form>';
	if ($action == 'create') {
		print '<script nonce="'.getNonce().'">';
		print 'jQuery(function(){';
		print 'jQuery("#type_code,#fk_powerplant").select2({width:"resolve",minimumResultsForSearch:0});';
		print 'jQuery("#type_code,#fk_powerplant").on("change",function(){';
		print 'var url="'.dol_buildpath('/powerplantpv/attestation_card.php', 1).'?action=create";';
		print 'var typeCode=jQuery("#type_code").val()||"";';
		print 'var fkPowerplant=jQuery("#fk_powerplant").val()||"";';
		print 'window.location.href=url+"&type_code="+encodeURIComponent(typeCode)+"&fk_powerplant="+encodeURIComponent(fkPowerplant);';
		print '});';
		print '});';
		print '</script>';
	}
} elseif ($object->id > 0) {
	$derivedData = powerplantpvAttestationGetDerivedData($object, $langs);
	$head = powerplantpvAttestationPrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans('Attestation'), -1, $object->picto);
	dol_banner_tab($object, 'ref', powerplantpvAttestationGetBackToListLink($object), 1, 'ref', 'ref', powerplantpvAttestationBuildBannerMoreHtml($object, $permissiontoedit, $action));
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
	$parameters = array('socid' => $object->fk_soc);
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';
	print '</table>';
	print load_fiche_titre($langs->trans('AttestationEquipment'), '', 'fa-cubes');
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	if ($object->type_code == PowerPlantPVAttestationTypes::TYPE_INSTALLATEUR_INF_100KWC) {
		print '<tr class="liste_titre"><td>'.$langs->trans('AttestationInstallerInf100EquipmentCategory').'</td><td>'.$langs->trans('AttestationInstallerInf100EquipmentBrand').'</td><td>'.$langs->trans('AttestationInstallerInf100EquipmentReference').'</td><td>'.$langs->trans('AttestationInstallerInf100EquipmentManufacturer').'</td></tr>';
		$installerEquipmentRows = powerplantpvAttestationBuildInstallerInf100EquipmentRows($object, $langs);
		if (empty($installerEquipmentRows)) {
			print '<tr class="oddeven"><td colspan="4"><span class="opacitymedium">'.$langs->trans('None').'</span></td></tr>';
		} else {
			$productCache = array();
			foreach ($installerEquipmentRows as $equipment) {
				$productRefHtml = $equipment['product_ref'] !== '' ? dol_escape_htmltag($equipment['product_ref']) : '<span class="opacitymedium">'.$langs->trans('AttestationNotProvided').'</span>';
				$fkProduct = !empty($equipment['fk_product']) ? (int) $equipment['fk_product'] : 0;
				if ($fkProduct > 0) {
					if (!array_key_exists($fkProduct, $productCache)) {
						$product = new Product($db);
						$productCache[$fkProduct] = ($product->fetch($fkProduct) > 0) ? $product : false;
					}
					if (is_object($productCache[$fkProduct])) {
						$productRefHtml = $productCache[$fkProduct]->getNomUrl(1);
					}
				}
				print '<tr class="oddeven">';
				print '<td>'.dol_escape_htmltag($equipment['category']).'</td>';
				print '<td>'.dol_escape_htmltag($equipment['brand']).'</td>';
				print '<td>'.$productRefHtml.'</td>';
				print '<td>'.dol_escape_htmltag($equipment['manufacturer']).'</td>';
				print '</tr>';
			}
		}
	} else {
		print '<tr class="liste_titre"><td>'.$langs->trans('AttestationEquipmentCategory').'</td><td>'.$langs->trans('Ref').'</td><td>'.$langs->trans('Designation').'</td><td>'.$langs->trans('PowerPlantSerialNumber').'</td></tr>';
		if (empty($object->lines)) {
			print '<tr class="oddeven"><td colspan="4"><span class="opacitymedium">'.$langs->trans('None').'</span></td></tr>';
		} else {
			$productCache = array();
			foreach ($object->lines as $line) {
				$equipment = powerplantpvAttestationResolveEquipmentLine($line, $langs);
				$productRefHtml = dol_escape_htmltag($equipment['product_ref']);
				$fkProduct = !empty($equipment['fk_product']) ? (int) $equipment['fk_product'] : 0;
				if ($fkProduct > 0) {
					if (!array_key_exists($fkProduct, $productCache)) {
						$product = new Product($db);
						$productCache[$fkProduct] = ($product->fetch($fkProduct) > 0) ? $product : false;
					}
					if (is_object($productCache[$fkProduct])) {
						$productRefHtml = $productCache[$fkProduct]->getNomUrl(1);
					}
				}
				print '<tr class="oddeven">';
				print '<td>'.dol_escape_htmltag($equipment['category']).'</td>';
				print '<td>'.$productRefHtml.'</td>';
				print '<td>'.dol_escape_htmltag($equipment['designation']).'</td>';
				print '<td>'.dol_escape_htmltag($equipment['serial_number']).'</td>';
				print '</tr>';
			}
		}
	}
	print '</table></div>';
	print '</div>';
	print dol_get_fiche_end();

	print '<div class="tabsAction">';
	if ($permissiontoedit) {
		print dolGetButtonAction($langs->trans('Modify'), '', 'default', $_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=edit&token='.newToken(), '', true);
	}
	if ($permissiontovalidate && $object->status == PowerPlantPVAttestation::STATUS_DRAFT) {
		print dolGetButtonAction($langs->trans('Validate'), '', 'default', $_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=validate&token='.newToken(), '', true);
	}
	if ($permissiontosign
		&& PowerPlantPVCompatibility::isFeatureAvailable('attestation_online_signature')
		&& in_array((int) $object->status, array(PowerPlantPVAttestation::STATUS_VALIDATED, PowerPlantPVAttestation::STATUS_PENDING_SIGNATURE), true)
	) {
		$signatureUrl = powerplantpvAttestationGetOnlineSignatureUrl(0, $object, 1);
		if ($signatureUrl !== '') {
			print dolGetButtonAction($langs->trans('AttestationSignButton'), '', 'default', $signatureUrl, '', true);
		}
	}
	if ($permissiontocancel && !in_array((int) $object->status, array(PowerPlantPVAttestation::STATUS_SIGNED, PowerPlantPVAttestation::STATUS_CANCELED), true)) {
		print dolGetButtonAction($langs->trans('Cancel'), '', 'default', $_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=cancel&token='.newToken(), '', true);
	}
	if ($permissiontodeleteobject) {
		print dolGetButtonAction($langs->trans('Delete'), '', 'delete', $_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=delete&token='.newToken(), '', true);
	}
	print '</div>';

	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	$uploadDir = powerplantpvAttestationGetDocumentUploadDir($object);
	powerplantpvAttestationNormalizeDocumentDirectory($object);
	$genallowed = $permissiontogeneratedocument;
	$delallowed = $permissiontodeletedocument;
	print $formfile->showdocuments(powerplantpvAttestationGetDocumentGenerationModulePart(), powerplantpvAttestationGetDocumentRelativePath($object), $uploadDir, $_SERVER['PHP_SELF'].'?id='.(int) $object->id, $genallowed, $delallowed, $object->model_pdf, 1, 0, 0, 28, 0, '', '', '', $langs->defaultlang, '', $object);
	if (method_exists($form, 'showLinkedObjectBlock')) {
		$form->showLinkedObjectBlock($object);
	}
	print '</div>';
	print '<div class="fichehalfright">';
	if (isModEnabled('agenda')) {
		$MAXEVENT = getDolGlobalInt('MAIN_SIZE_SHORTLIST_LIMIT', 10);
		$morehtmlcenter = dolGetButtonTitle($langs->trans('SeeAll'), '', 'fa fa-bars imgforviewmode', dol_buildpath('/powerplantpv/attestation_agenda.php', 1).'?id='.(int) $object->id);
		$formactions->showactions($object, 'attestation@powerplantpv', $object->fk_soc, 1, '', $MAXEVENT, '', $morehtmlcenter);
	}
	print '</div>';
	print '</div>';
}

llxFooter();
$db->close();
