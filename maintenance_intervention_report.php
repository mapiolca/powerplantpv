<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		maintenance_intervention_report.php
 * \ingroup		powerplantpv
 * \brief		Generated report tab for interventions.
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

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/ajax.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_maintenance.lib.php');
dol_include_once('/powerplantpv/class/powerplantpvreport.class.php');
dol_include_once('/powerplantpv/class/powerplantpvreportbuilder.class.php');
dol_include_once('/powerplantpv/class/powerplantpvreportparts.class.php');
dol_include_once('/powerplantpv/class/powerplantpvreportfield.class.php');
dol_include_once('/powerplantpv/class/powerplantpvreportfile.class.php');
require_once __DIR__.'/class/powerplantpvreportevidence.class.php';
require_once __DIR__.'/lib/powerplantpv_residential.lib.php';
require_once __DIR__.'/lib/powerplantpv_indexphoto.lib.php';
require_once __DIR__.'/lib/powerplantpv_reportsignature.lib.php';
require_once __DIR__.'/class/powerplantpvinterventionnature.class.php';
dol_include_once('/powerplantpv/class/powerplantpvreportdcmeasure.class.php');
dol_include_once('/powerplantpv/class/powerplantpvpowerplantsummary.class.php');
require_once __DIR__.'/class/powerplantpvreportpresentation.class.php';
require_once __DIR__.'/lib/powerplantpv_reportcompletion.lib.php';
dol_include_once('/fichinter/class/fichinter.class.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/fichinter.lib.php';
if (isModEnabled('project')) {
	require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
}

$langs->loadLangs(array('powerplantpv@powerplantpv', 'interventions', 'companies', 'contracts', 'other'));

$id = GETPOSTINT('id');
$ref = $id > 0 ? '' : (string) GETPOST('ref', 'alphanohtml');
$action = GETPOST('action', 'aZ09');
$token = GETPOST('token', 'alphanohtml');
$fieldId = GETPOSTINT('field_id');
$fileId = GETPOSTINT('file_id');
$dcSectionId = GETPOSTINT('add_dc_section_id');
$partToValidate = (string) GETPOST('validate_part_submit', 'aZ09', 2);
$partToReopen = (string) GETPOST('reopen_part_code', 'aZ09');
$reportRestoreScrollY = GETPOSTINT('report_scroll_y');
$residentialRowSubmit = (string) GETPOST('residential_row_submit', 'aZ09', 2);
$residentialAppend = (string) GETPOST('residential_append', 'aZ09', 1);
$legacyVoltageCards = GETPOST('adopt_legacy_voltage_submit', 'array', 2);
$legacyVoltageCard = is_array($legacyVoltageCards) && count($legacyVoltageCards) === 1 ? (string) key($legacyVoltageCards) : '';
if (!preg_match('/^[a-f0-9]{32}$/D', $legacyVoltageCard)) {
	$legacyVoltageCard = '';
}
$manualServiceIds = powerplantpvSanitizeIdArray(GETPOST('manual_services', 'array:int'));

if ($action === '') {
	if ($partToValidate !== '') {
		$action = 'validate_part';
	} elseif (GETPOSTISSET('save_draft_submit') || $legacyVoltageCard !== '' || $residentialRowSubmit !== '') {
		$action = 'save_draft';
	} elseif (GETPOSTISSET('save_report_submit')) {
		$action = 'save';
	} elseif (GETPOSTISSET('recalculate_submit')) {
		$action = 'recalculate';
	}
}

if (!isModEnabled('powerplantpv') || !getDolGlobalInt('POWERPLANTPV_MAINTENANCE_ENABLE', 1)) {
	accessforbidden();
}
if (!$user->hasRight('ficheinter', 'lire') || !($user->hasRight('powerplantpv', 'report', 'read') || !empty($user->admin))) {
	accessforbidden();
}
if ($id <= 0 && $ref === '') {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}

$intervention = new Fichinter($db);
if ($intervention->fetch($id, $ref) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
$id = (int) $intervention->id;
restrictedArea($user, 'ficheinter', $id, 'fichinter');

if (method_exists($intervention, 'fetch_optionals')) {
	$intervention->fetch_optionals();
}
if (method_exists($intervention, 'fetch_thirdparty')) {
	$intervention->fetch_thirdparty();
}

$permissiontowrite = ($user->hasRight('powerplantpv', 'report', 'write') || !empty($user->admin));
$usercancreateintervention = $user->hasRight('ficheinter', 'creer');

$builder = new PowerPlantPVReportBuilder($db);
$report = new PowerPlantPVReport($db);
$signatureState = $report->readInterventionSignatureState($intervention);
if ($signatureState === null) {
	accessforbidden();
}
$intervention->signed_status = (int) $signatureState->signed_status;
$thirdPartySigned = PowerPlantPVReport::interventionSignedByThirdParty((int) $intervention->signed_status);
$locked = powerplantpvReportIsInterventionLocked($intervention);
$caneditreport = $permissiontowrite && !$locked;
$cangenerateserialannex = $permissiontowrite && $user->hasRight('ficheinter', 'creer');
$reportFetch = $report->fetchByIntervention($id);
if ($reportFetch < 0) {
	setEventMessages($report->error, $report->errors, 'errors');
}
if ($action === 'download_scope_file') {
	$file = new PowerPlantPVReportFile($db);
	if ($reportFetch <= 0 || !$user->hasRight('powerplantpv', 'report', 'read') || $file->fetch((int) $fileId) <= 0) {
		accessforbidden();
	}
	$path = powerplantpvResidentialFilePath($report, $intervention, $file);
	$stream = $path !== '' ? fopen($path, 'rb') : false;
	if ($stream === false) {
		accessforbidden();
	}
	header('Content-Type: application/octet-stream');
	header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode((string) $file->filename));
	header('Cache-Control: private, no-store');
	header('X-Content-Type-Options: nosniff');
	fpassthru($stream);
	fclose($stream);
	exit;
}
$technicalSigned = $reportFetch > 0 && (int) $report->fk_user_technical_sign > 0;
$technicianSigned = $reportFetch > 0 && (int) $report->fk_user_technician_sign > 0;
$caneditreport = $caneditreport && !$technicalSigned && !$technicianSigned;
if ($reportFetch > 0 && $report->report_template_code === PowerPlantPVReportTemplate::CODE_RESIDENTIAL) {
	if (!$user->hasRight('powerplantpv', 'report', 'read')) {
		accessforbidden();
	}
	$caneditreport = $caneditreport && !$report->isSaved() && $user->hasRight('powerplantpv', 'report', 'write');
}

$signatureScript = '';
if (powerplantpvReportSignatureAvailable() && !empty($conf->use_javascript_ajax)) {
	foreach (array('/public/includes/jquery/plugins/jSignature/jSignature.js', '/includes/jquery/plugins/jSignature/jSignature.js') as $path) {
		if (is_readable(DOL_DOCUMENT_ROOT.$path)) {
			$signatureScript = $path;
			break;
		}
	}
}
$canSign = $reportFetch > 0 && (int) $signatureState->fk_statut === Fichinter::STATUS_VALIDATED
	&& $report->isSaved() && $signatureScript !== '';
$signingAllowed = array(
	'technician' => $canSign && !$technicianSigned && $report->isAssignedTechnician($intervention, $user),
	'technical' => $canSign && !$technicalSigned && ($user->hasRight('powerplantpv', 'report', 'sign_technical') || !empty($user->admin))
		&& $report->technicalSignatureEnabled() && $report->isTechnicalSupervisor($intervention, $user),
);
// Self-inspection reports: each part is signed on the day of its tests and only that part is frozen.
$partsHelper = new PowerPlantPVReportParts($db);
$isPartsReport = $reportFetch > 0 && PowerPlantPVReportParts::isPartsReport($report);
$partSignatures = array();
$lockedPartSectionIds = array();
if ($isPartsReport) {
	$partSignatures = $partsHelper->fetchSignatures($report);
	$lockedPartSectionIds = is_array($partSignatures) ? $partsHelper->lockedSectionIds($report) : -1;
	if (!is_array($partSignatures) || !is_array($lockedPartSectionIds)) {
		setEventMessages($partsHelper->error, $partsHelper->errors, 'errors');
		$partSignatures = array();
		$lockedPartSectionIds = array();
		$caneditreport = false;
	}
	if (PowerPlantPVReportParts::isFinalSigned($partSignatures)) {
		$caneditreport = false;
	}
	// The final validation of the parts replaces the report-wide signatures.
	$signingAllowed = array('technician' => false, 'technical' => false);
}
$partSigning = array();
$partSignatureError = '';
if (($action === 'prepare_part_signature' || $action === 'sign_part') && $isPartsReport) {
	$partCode = GETPOST('part_code', 'aZ09');
	$partRole = GETPOST('part_role', 'aZ09');
	if ($locked || $signatureScript === '' || !$partsHelper->canSign($report, $intervention, $user, $partCode, $partRole, $partSignatures)
		|| powerplantpvReportSnapshotNeedsRebuild($builder, (int) $report->id)) {
		accessforbidden();
	}
	if ($action === 'prepare_part_signature' && $partCode !== PowerPlantPVReportParts::FINAL_CODE) {
		$partTree = $builder->loadReportTree((int) $report->id);
		$sectionIds = is_array($partTree) ? powerplantpvReportPartSectionIds($partTree, $partCode) : array();
		$missing = $sectionIds ? powerplantpvReportFindMissingRequiredFields((int) $report->id, $sectionIds, true) : -1;
		if (!is_array($missing) || $missing) {
			$message = is_array($missing)
				? $langs->transnoentities('PowerPlantPVPartMissingRequiredFields', implode(', ', $missing))
				: $langs->trans('ErrorRecordNotFound');
			setEventMessages($message, null, 'errors');
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $id.'#powerplantpv-part-actions-'.$partCode);
			exit;
		}
	}
	$partSigning = array('part' => $partCode, 'role' => $partRole);
	if ($action === 'sign_part') {
		if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !powerplantpvReportSubmittedTokenValid($token)
			|| GETPOSTINT('signature_consent', 2) !== 1) {
			accessforbidden();
		}
		if ($db->begin() <= 0) {
			accessforbidden();
		}
		// Use the same row lock as report writes before reading the values being signed.
		$lockedForSignature = $report->lockUnsignedForEdit() && $report->fetch((int) $report->id) > 0;
		$currentSignatures = $lockedForSignature ? $partsHelper->fetchSignatures($report) : -1;
		if (!is_array($currentSignatures)
			|| !$partsHelper->canSign($report, $intervention, $user, $partCode, $partRole, $currentSignatures)) {
			$db->rollback();
			accessforbidden();
		}
		$signatureError = '';
		$partTree = $builder->loadReportTree((int) $report->id);
		$missingPartFields = is_array($partTree) ? powerplantpvReportFindMissingRequiredFields((int) $report->id, powerplantpvReportPartSectionIds($partTree, $partCode), true) : -1;
		if (!is_array($missingPartFields)) {
			$signatureError = $langs->trans('ErrorRecordNotFound');
		} elseif ($missingPartFields) {
			$signatureError = $langs->trans('PowerPlantPVPartMissingRequiredFields', implode(', ', $missingPartFields));
		}
		$signaturePath = '';
		if ($signatureError === '') {
			$signaturePath = powerplantpvReportSignaturePath((int) $report->entity, (int) $report->id, PowerPlantPVReportParts::imageKey($partCode, $partRole), true);
			if ($signaturePath === '' || is_file($signaturePath) || !powerplantpvReportSignatureStore((string) GETPOST('signature_image', 'none', 2), $signaturePath)) {
				$signatureError = $langs->trans('PowerPlantPVSignatureDrawingFailed');
				$signaturePath = '';
			}
		}
		if ($signatureError === '') {
			if ($partsHelper->sign($report, $user, $partCode, $partRole, $user->getFullName($langs)) > 0
				&& $report->recordLifecycleEvent($intervention, $user, 'signed', $partCode) >= 0
				&& $db->commit() > 0) {
				setEventMessages($langs->trans('PowerPlantPVSignatureRecorded'), null, 'mesgs');
				if ($partCode === PowerPlantPVReportParts::FINAL_CODE) {
					$model = (new PowerPlantPVInterventionNature($db))->configuredPdfModel($intervention);
					if ($model === null || $intervention->generateDocument($model !== '' ? $model : PowerPlantPVReportParts::PDF_MODEL, $langs) <= 0) {
						setEventMessages($langs->trans('PowerPlantPVInterventionPdfGenerationFailed'), null, 'warnings');
					}
				}
				header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $id.'#powerplantpv-part-signature-'.$partCode);
				exit;
			}
			dol_delete_file($signaturePath, 1, 1, 1, null, false, 0);
			$signatureError = $langs->trans('PowerPlantPVSignatureDrawingFailed');
		}
		$db->rollback();
		if ($partCode === PowerPlantPVReportParts::FINAL_CODE) {
			setEventMessages($signatureError, null, 'errors');
		} else {
			$partSignatureError = $signatureError;
		}
	}
}
$signatureRole = '';
if ($action === 'prepare_signature' || $action === 'save_signature') {
	$signatureRole = GETPOST('signature_role', 'aZ09');
	if (empty($signingAllowed[$signatureRole]) || powerplantpvReportSnapshotNeedsRebuild($builder, (int) $report->id)) {
		accessforbidden();
	}
	if ($action === 'save_signature') {
		if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !powerplantpvReportSubmittedTokenValid($token)
			|| GETPOSTINT('signature_consent', 2) !== 1) {
			accessforbidden();
		}
		$drawing = (string) GETPOST('signature_image', 'none', 2);
		if ($report->signDrawn($intervention, $user, $signatureRole, $user->getFullName($langs), $drawing) > 0) {
			setEventMessages($langs->trans('PowerPlantPVSignatureRecorded'), null, 'mesgs');
			$model = (new PowerPlantPVInterventionNature($db))->configuredPdfModel($intervention);
			$defaultModel = $report->report_template_code === PowerPlantPVReportTemplate::CODE_RESIDENTIAL
				? PowerPlantPVReportTemplate::PDF_MODEL_RESIDENTIAL : 'powerplantpvreport';
			$canRegenerate = !PowerPlantPVReport::interventionSignedByThirdParty((int) $intervention->signed_status)
				|| $model === '' || $model === $defaultModel;
			if ($model === null || ($canRegenerate && $intervention->generateDocument($model !== '' ? $model : $defaultModel, $langs) <= 0)) {
				setEventMessages($langs->trans('PowerPlantPVInterventionPdfGenerationFailed'), null, 'warnings');
			}
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $id.'#powerplantpv-report-signatures');
			exit;
		}
		setEventMessages($langs->trans('PowerPlantPVSignatureDrawingFailed'), null, 'errors');
	}
}

if ($action === 'view_index_photo') {
	$field = new PowerPlantPVReportField($db);
	if ($reportFetch <= 0 || $field->fetch(GETPOSTINT('field_id')) <= 0
		|| (int) $field->fk_report !== (int) $report->id || (int) $field->entity !== (int) $report->entity
		|| !powerplantpvReportIndexPhotoField($field)) {
		accessforbidden();
	}
	powerplantpvIndexPhotoDownload(powerplantpvReportIndexPhotoPath($field));
}

$completionService = new PowerPlantPVReportPlantCompletion($db);
$canwriteplant = $user->hasRight('powerplantpv', 'powerplant', 'read') && $user->hasRight('powerplantpv', 'powerplant', 'write');
$caneditcompletion = $caneditreport && $canwriteplant && !$report->isSaved() && !$isPartsReport
	&& $report->report_template_code !== PowerPlantPVReportTemplate::CODE_RESIDENTIAL;
$completionPayload = array();
$completionPayloadInvalid = false;
if (($action === 'save_draft' || $action === 'save') && GETPOSTISSET('plant_completion', 2)) {
	$completionJson = GETPOST('plant_completion', 'none', 2);
	if (is_string($completionJson) && $completionJson !== '') {
		$completionPayload = strlen($completionJson) <= 1048576 ? json_decode($completionJson, true, 32) : null;
		$completionPayloadInvalid = !is_array($completionPayload);
	}
}
if (($completionPayload || $completionPayloadInvalid) && !$caneditcompletion) {
	accessforbidden();
}
if (GETPOSTINT('retry_plant_completion', 2) > 0) {
	if (!$canwriteplant || !$permissiontowrite || $reportFetch <= 0 || !$report->isSaved() || !powerplantpvReportSubmittedTokenValid($token)) {
		accessforbidden();
	}
	try {
		$plantId = GETPOSTINT('retry_plant_completion', 2);
		$entry = $completionService->forReport((int) $report->id, (int) $report->entity)[$plantId] ?? null;
		if (!$entry || !$completionService->plantForAuthor($intervention, $user, $plantId)) {
			accessforbidden();
		}
		$completionService->transfer((int) $entry->rowid, (int) $report->entity, true);
	} catch (RuntimeException | JsonException $e) {
		setEventMessages($langs->trans(PowerPlantPVReportPlantCompletion::DATABASE), null, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $id.'#powerplantpv-plant-completion');
	exit;
}

$sensitiveActions = array('save_draft', 'save', 'validate_part', 'recalculate', 'upload_file', 'delete_file', 'add_dc_measure_line');
if ($action === 'generate_serial_annex' && (!$cangenerateserialannex || !powerplantpvReportSubmittedTokenValid($token))) { accessforbidden(); }
if (in_array($action, $sensitiveActions, true)) {
	if (!$caneditreport) {
		accessforbidden();
	}
	if (in_array($action, array('upload_file', 'delete_file'), true) && !empty($lockedPartSectionIds)
		&& powerplantpvReportFileActionTargetsLockedPart($action === 'upload_file' ? $fieldId : 0, $action === 'delete_file' ? $fileId : 0, $lockedPartSectionIds)) {
		accessforbidden($langs->trans('PowerPlantPVPartSignedLocked'));
	}
	if ($action === 'recalculate' && !empty($lockedPartSectionIds)) {
		accessforbidden($langs->trans('PowerPlantPVPartSignedRecalculateLocked'));
	}
	if (!powerplantpvReportSubmittedTokenValid($token)) {
		accessforbidden('Invalid CSRF token');
	}
}

if (in_array($action, array('setref_client', 'classin'), true)) {
	if (!$usercancreateintervention) {
		accessforbidden();
	}
	if (!powerplantpvReportSubmittedTokenValid($token)) {
		accessforbidden('Invalid CSRF token');
	}
}

if ($action === 'setref_client' && $usercancreateintervention) {
	$result = $intervention->setRefClient($user, GETPOST('ref_client', 'alpha'));
	if ($result < 0) {
		setEventMessages($intervention->error, $intervention->errors, 'errors');
	} else {
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $id);
		exit;
	}
}

if ($action === 'classin' && $usercancreateintervention) {
	$result = $intervention->setProject(GETPOSTINT('projectid'));
	if ($result < 0) {
		setEventMessages($intervention->error, $intervention->errors, 'errors');
	} else {
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $id);
		exit;
	}
}

if ($action === 'validate_part') {
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !$isPartsReport || $signatureScript === ''
		|| !isset(PowerPlantPVReportParts::definitions()[$partToValidate])
		|| !$partsHelper->canSign($report, $intervention, $user, $partToValidate, 'technician', $partSignatures)
		|| powerplantpvReportSnapshotNeedsRebuild($builder, (int) $report->id)) {
		accessforbidden();
	}
	$treeForSignature = $builder->loadReportTree((int) $report->id);
	$partSectionIds = is_array($treeForSignature) ? powerplantpvReportPartSectionIds($treeForSignature, $partToValidate) : array();
	$redirectUrl = $_SERVER['PHP_SELF'].'?id='.(int) $id.'#powerplantpv-part-actions-'.$partToValidate;
	if (!$partSectionIds) {
		setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
	} elseif ($db->begin() <= 0) {
		setEventMessages($db->lasterror(), null, 'errors');
	} else {
		$result = $builder->saveValues((int) $report->id, powerplantpvReportGetSubmittedValues(), powerplantpvReportGetSubmittedDateValues(), $user, (string) $report->status, $partSectionIds);
		if ($result > 0) {
			$result = $builder->saveDcMeasureValues((int) $report->id, powerplantpvReportGetSubmittedDcMeasures(), $user, '', $partSectionIds);
		}
		$missing = $result > 0 ? powerplantpvReportFindMissingRequiredFields((int) $report->id, $partSectionIds, true) : -1;
		if (is_array($missing) && !$missing && $report->recordLifecycleEvent($intervention, $user, 'stage_validated', $partToValidate) < 0) {
			$result = -1;
		}
		if ($result <= 0 || !is_array($missing)) {
			$db->rollback();
			setEventMessages($result <= 0 ? $langs->trans($builder->error ?: $report->error) : $langs->trans('ErrorRecordNotFound'), $builder->errors, 'errors');
		} elseif ($db->commit() <= 0) {
			$error = $db->lasterror();
			$db->rollback();
			setEventMessages($error, null, 'errors');
		} elseif ($missing) {
			setEventMessages($langs->transnoentities('PowerPlantPVPartMissingRequiredFields', implode(', ', $missing)), null, 'errors');
		} else {
			$redirectUrl = powerplantpvReportPartSignatureUrl($partToValidate, 'technician');
		}
	}
	header('Location: '.$redirectUrl);
	exit;
}

if (in_array($action, array('reopen_part', 'confirm_reopen_part'), true)) {
	$stageSigner = $partSignatures[$partToReopen]['technician'] ?? null;
	if (!$isPartsReport || $locked || !isset(PowerPlantPVReportParts::definitions()[$partToReopen])
		|| !($user->hasRight('powerplantpv', 'attestation', 'manage_signed') || !empty($user->admin)) || !is_object($stageSigner)
		|| !$partsHelper->isSignerOrSupervisor($user, (int) $stageSigner->fk_user) || !powerplantpvReportSubmittedTokenValid($token)) {
		accessforbidden();
	}
	if ($action === 'confirm_reopen_part') {
		if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || GETPOST('confirm', 'aZ09', 2) !== 'yes') {
			accessforbidden();
		}
		if ($partsHelper->reopenPart($report, $intervention, $user, $partToReopen) > 0) {
			setEventMessages($langs->trans('PowerPlantPVPartReopened'), null, 'mesgs');
		} else {
			setEventMessages($langs->trans($partsHelper->error), null, 'errors');
		}
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $id.'#powerplantpv-part-actions-'.$partToReopen);
		exit;
	}
}

$residentialForm = null;
$scopeFileAction = '';
if ($caneditreport && $reportFetch > 0 && $report->report_template_code === PowerPlantPVReportTemplate::CODE_RESIDENTIAL
	&& in_array($action, array('upload_file', 'delete_file'), true)) {
	$scopeFieldId = $fieldId;
	if ($action === 'delete_file') {
		$scopeFile = new PowerPlantPVReportFile($db);
		$scopeFieldId = $scopeFile->fetch((int) $fileId) > 0 && (int) $scopeFile->fk_report === (int) $report->id
			? (int) $scopeFile->fk_report_field : 0;
	}
	$scopeField = new PowerPlantPVReportField($db);
	if ($scopeFieldId > 0 && $scopeField->fetch($scopeFieldId) > 0
		&& (int) $scopeField->fk_report === (int) $report->id && powerplantpvIsResidentialEvidenceField($scopeField->field_code)) {
		$scopeFileAction = $action;
		$action = 'save_draft';
	}
}
if (($action === 'save_draft' || $action === 'save') && $caneditreport) {
	if ($residentialRowSubmit !== '' && (!$user->hasRight('powerplantpv', 'report', 'write')
		|| ($reportFetch > 0 && $report->report_template_code !== PowerPlantPVReportTemplate::CODE_RESIDENTIAL))) {
		accessforbidden();
	}
	if ($reportFetch <= 0 && ($residentialRowSubmit !== '' || !$user->hasRight('powerplantpv', 'report', 'write'))) {
		$preview = $builder->buildPreviewTree($intervention, $manualServiceIds);
		$isResidentialPreview = ($preview['report']->report_template_code ?? '') === PowerPlantPVReportTemplate::CODE_RESIDENTIAL;
		if ($residentialRowSubmit !== '' ? !$isResidentialPreview : $isResidentialPreview) {
			accessforbidden();
		}
	}
	$status = ($action === 'save') ? PowerPlantPVReport::STATUS_SAVED : PowerPlantPVReport::STATUS_DRAFT;
	$persistStatus = ($action === 'save') ? PowerPlantPVReport::STATUS_DRAFT : $status;
	$saveerror = (int) $completionPayloadInvalid;
	if ($saveerror) {
		setEventMessages($langs->trans(PowerPlantPVReportPlantCompletion::INVALID), null, 'errors');
	}
	if (!$saveerror && $reportFetch <= 0) {
		$createdReport = $builder->createSnapshot($intervention, $user, $manualServiceIds, $persistStatus);
		if ($createdReport instanceof PowerPlantPVReport) {
			$report = $createdReport;
			$reportFetch = 1;
		} else {
			$saveerror++;
			setEventMessages($builder->error, $builder->errors, 'errors');
		}
	}
	if (!$saveerror && $reportFetch > 0 && powerplantpvReportSnapshotNeedsRebuild($builder, (int) $report->id)) {
		$result = $builder->recalculateSnapshot($report, $intervention, $user, $manualServiceIds, $persistStatus);
		if ($result < 0) {
			$saveerror++;
			setEventMessages($builder->error, $builder->errors, 'errors');
		} else {
			$reportFetch = $report->fetchByIntervention($id);
			if ($reportFetch <= 0) {
				$saveerror++;
				if (!empty($report->error) || !empty($report->errors)) {
					setEventMessages($report->error, $report->errors, 'errors');
				} else {
					setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
				}
			}
		}
	}
	if (!$saveerror && $reportFetch > 0) {
		$values = powerplantpvReportGetSubmittedValues();
		$dateValues = powerplantpvReportGetSubmittedDateValues();
		$dcValues = powerplantpvReportGetSubmittedDcMeasures();
		$completionTransaction = false;
		$isResidential = $report->report_template_code === PowerPlantPVReportTemplate::CODE_RESIDENTIAL;
		$requiredValidationFailed = 0;
		if ($isResidential) {
			$residential = new PowerPlantPVResidentialReport($db);
			$submitted = GETPOST('residential', 'array', 2);
			$residentialTree = $builder->loadReportTree((int) $report->id);
			$options = is_array($residentialTree) ? $residential->options($builder->fetchResidentialEquipmentContext($intervention), (int) $report->entity, $user) : array();
			$residentialForm = is_array($submitted) ? $submitted : array();
			if ($action === 'save_draft') {
				$residentialForm = PowerPlantPVResidentialReport::removeSubmittedRow($residentialForm, $residentialRowSubmit);
			}
			unset($residentialForm['staff']);
			if (GETPOSTINT('ppv_staff_submitted', 2) === 1) {
				$roofTeam = GETPOST('ppv_roof_team', 'array', 2);
				$residentialForm['staff'] = array(
					'roof_user' => is_array($roofTeam) ? $roofTeam : array(),
					'electrical' => GETPOST('ppv_electrical_lead', 'alphanohtml', 2),
				);
			}
			$result = -1;
			if ($completionPayload) {
				$builder->error = $langs->trans(PowerPlantPVReportPlantCompletion::CONFLICT);
			} elseif (!$options || !is_array($residentialTree) || !PowerPlantPVResidentialReport::hasSections($residentialTree)) {
				$builder->error = $langs->trans($residential->error ?: 'PVResidentialRecalculate');
			} elseif (GETPOSTINT('residential_complete', 2) !== 1) {
				$builder->error = $langs->trans('PVResidentialIncompleteSubmission');
			} elseif ($db->begin() > 0) {
				$before = $report->lockUnsignedForEdit(true) ? $residential->load($report) : null;
				$options['savedStaff'] = $before['staff'] ?? array();
				$normalized = PowerPlantPVResidentialReport::normalize($residentialForm, $options, false);
				if ($before === null) {
					$builder->error = $langs->trans($residential->error ?: 'PowerPlantPVTechnicalSignEditLocked');
				} elseif ($normalized['errors']) {
					setEventMessages(null, array_map(array($langs, 'trans'), $normalized['errors']), 'errors');
					$requiredValidationFailed = 1;
				} else {
					$result = $builder->saveValues((int) $report->id, $values, $dateValues, $user, $persistStatus);
					if ($result >= 0 && !$residential->save($report, $normalized['data'])) {
						$result = -1;
						$builder->error = $residential->error;
					}
					if ($result >= 0 && $db->commit() <= 0) {
						$result = -1;
						$builder->error = $db->lasterror();
					}
				}
				if ($result < 0) {
					$db->rollback();
				}
			} else {
				$builder->error = $db->lasterror();
			}
		} else {
			$result = 1;
			if ($completionPayload) {
				$completionTransaction = $db->begin() > 0;
				$state = $completionTransaction ? $report->readInterventionSignatureState($intervention, true) : null;
				if (!$state || PowerPlantPVReport::interventionSignedByThirdParty((int) $state->signed_status)
					|| !$report->lockUnsignedForEdit() || $report->fetchByIntervention($id) <= 0 || $report->isSaved()
					|| PowerPlantPVReportParts::isPartsReport($report)
					|| $report->report_template_code === PowerPlantPVReportTemplate::CODE_RESIDENTIAL
					|| !$completionService->save($report, $intervention, $user, $completionPayload)) {
					$result = -1;
					$message = $completionService->error ?: PowerPlantPVReportPlantCompletion::CONFLICT;
					setEventMessages($langs->trans($message), null, 'errors');
				}
			}
			if ($result >= 0) {
				$result = $builder->saveValues((int) $report->id, $values, $dateValues, $user, $persistStatus);
			}
			if ($result >= 0) {
				$result = $builder->saveDcMeasureValues((int) $report->id, $dcValues, $user, $legacyVoltageCard);
			}
		}
		$interventionValidationResult = 0;
		$photoWarnings = array();
		$finalPdfModel = '';
		if ($result >= 0) {
			$photoErrors = powerplantpvReportSaveIndexPhotos(
				$report,
				(string) $status === PowerPlantPVReport::STATUS_SAVED,
				($user->hasRight('powerplantpv', 'report', 'finalize_missing_evidence') || !empty($user->admin)),
				$photoWarnings
			);
			if ($photoErrors) {
				$result = -1;
				$requiredValidationFailed = 1;
				setEventMessages(null, $photoErrors, 'errors');
			}
		}
		if ($completionTransaction) {
			if ($result >= 0) {
				$result = $builder->recalculateSnapshot($report, $intervention, $user, $manualServiceIds, PowerPlantPVReport::STATUS_DRAFT);
			}
			if ($result >= 0 && $db->commit() <= 0) {
				$result = -1;
			}
			if ($result < 0) {
				$db->rollback();
			}
		}
		if ($result >= 0 && (string) $status === PowerPlantPVReport::STATUS_SAVED && PowerPlantPVReportParts::isPartsReport($report)) {
			$signaturesForFinalize = $partsHelper->fetchSignatures($report);
			$unsignedParts = is_array($signaturesForFinalize) ? PowerPlantPVReportParts::unsignedParts($signaturesForFinalize) : array_keys(PowerPlantPVReportParts::definitions());
			if ($unsignedParts) {
				$result = -1;
				$requiredValidationFailed = 1;
				setEventMessages($langs->trans('PowerPlantPVPartsUnsignedForFinalize', implode(', ', array_map('powerplantpvReportPartLabel', $unsignedParts))), null, 'errors');
			}
		}
		if ($result >= 0 && (string) $status === PowerPlantPVReport::STATUS_SAVED) {
			$missingRequiredFields = powerplantpvReportFindMissingRequiredFields((int) $report->id, null, PowerPlantPVReportParts::isPartsReport($report));
			if (!is_array($missingRequiredFields)) {
				$result = -1;
				$requiredValidationFailed = 1;
				setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
			} elseif (!empty($missingRequiredFields)) {
				$result = -1;
				$requiredValidationFailed = 1;
				powerplantpvReportSetMissingRequiredFieldsError($missingRequiredFields);
			} else {
				if ($db->begin() <= 0) {
					$result = -1;
					$builder->error = $db->lasterror();
				} else {
					$proofCopies = array();
					if ($isResidential) {
						$finalTree = $report->lockUnsignedForEdit(true) ? $builder->loadReportTree((int) $report->id) : null;
						$finalOptions = is_array($finalTree) ? $residential->options($builder->fetchResidentialEquipmentContext($intervention), (int) $report->entity, $user) : array();
						$result = $finalOptions ? 0 : -1;
						if ($result >= 0) {
							$finalOptions += powerplantpvResidentialProofOptions($finalTree, $intervention);
							$finalOptions['savedStaff'] = $normalized['data']['staff'];
							$final = PowerPlantPVResidentialReport::normalize($residentialForm, $finalOptions, true);
							if ($final['errors']) {
								setEventMessages(null, array_map(array($langs, 'trans'), $final['errors']), 'errors');
								$requiredValidationFailed = 1;
								$result = -1;
							}
						}
						if ($result < 0 && !$requiredValidationFailed) {
							$builder->error = $residential->error ?: $langs->trans('ErrorRecordNotFound');
						}
					}
					if ($result >= 0) {
						$result = $builder->saveValues((int) $report->id, $values, $dateValues, $user, PowerPlantPVReport::STATUS_SAVED);
					}
					if ($result >= 0 && $isResidential && !$residential->save($report, $final['data'])) {
						$result = -1;
						$builder->error = $residential->error;
					}
					if ($result >= 0) {
						$interventionValidationResult = powerplantpvReportValidateInterventionIfNeeded($intervention, $user);
						if ($interventionValidationResult < 0) {
							$result = -1;
						}
					}
					if ($result >= 0) {
						$nature = new PowerPlantPVInterventionNature($db);
						$finalPdfModel = $nature->configuredPdfModel($intervention);
						if ($finalPdfModel === '') {
							$finalPdfModel = match ($report->report_template_code) {
								PowerPlantPVReportTemplate::CODE_AUTOCONTROL => PowerPlantPVReportParts::PDF_MODEL,
								PowerPlantPVReportTemplate::CODE_RESIDENTIAL => PowerPlantPVReportTemplate::PDF_MODEL_RESIDENTIAL,
								default => '',
							};
						}
						if ($finalPdfModel === null || $nature->applyPdfModel($intervention, $user, $finalPdfModel) < 0) {
							$result = -1;
							$builder->error = $langs->trans('PowerPlantPVInterventionPdfModelFailed');
						}
					}
					if ($result >= 0 && $isResidential
						&& !powerplantpvResidentialAttachProofs($report, $intervention, $user, $final['data']['strings'], $finalOptions, $proofCopies)) {
						$result = -1;
						$builder->error = $langs->trans('PVResidentialMicroProofAttachmentFailed');
					}
					if ($result >= 0 && !$report->isSaved() && $report->recordLifecycleEvent($intervention, $user, 'finalized') < 0) {
						$result = -1;
						$builder->error = $report->error;
					}
					if ($result >= 0 && $db->commit() <= 0) {
						$result = -1;
						$builder->error = $db->lasterror();
					}
					if ($result < 0) {
						foreach ($proofCopies as $proofCopy) {
							if (dol_delete_file($proofCopy, 1, 1, 1, null, false, 0) <= 0) {
								dol_syslog('Failed to roll back a microinverter proof attachment', LOG_ERR);
							}
						}
						$db->rollback();
					}
				}
			}
		}
		if ($result < 0) {
			if ($interventionValidationResult < 0) {
				powerplantpvReportSetInterventionValidationError($intervention);
			} elseif ($requiredValidationFailed) {
				// Message already set with the missing required fields.
			} else {
				setEventMessages($builder->error, $builder->errors, 'errors');
			}
		} else {
			if ($scopeFileAction === 'upload_file') {
				$uploaded = powerplantpvReportUploadFile($report, $intervention, $fieldId, $user) > 0;
				setEventMessages($langs->trans($uploaded ? 'FileUploaded' : 'ErrorFileNotUploaded'), null, $uploaded ? 'mesgs' : 'errors');
			} elseif ($scopeFileAction === 'delete_file') {
				$deleted = powerplantpvReportDeleteFile($report, $intervention, $fileId) > 0;
				setEventMessages($langs->trans($deleted ? 'PowerPlantPVReportFileRemoved' : 'PowerPlantPVReportFileRemoveFailed'), null, $deleted ? 'mesgs' : 'errors');
			} elseif ($interventionValidationResult > 0) {
				setEventMessages($langs->trans('PowerPlantPVReportSavedInterventionValidated'), null, 'mesgs');
			} else {
				setEventMessages($langs->trans($status === PowerPlantPVReport::STATUS_SAVED ? 'PowerPlantPVReportSaved' : 'PowerPlantPVReportDraftSaved'), null, 'mesgs');
			}
			if ($finalPdfModel !== '' && $intervention->generateDocument($finalPdfModel, $langs) <= 0) {
				setEventMessages($langs->trans('PowerPlantPVInterventionPdfGenerationFailed'), null, 'warnings');
			}
			if ($photoWarnings) {
				setEventMessages($langs->trans('PowerPlantPVReportMissingEvidenceWarning'), $photoWarnings, 'warnings');
			}
			$redirectUrl = $_SERVER['PHP_SELF'].'?id='.(int) $id;
			if ($action === 'save_draft' && $isResidential && in_array($residentialRowSubmit, array('add_roofs', 'add_strings'), true)) {
				$group = substr($residentialRowSubmit, 4);
				$redirectUrl .= '&residential_append='.$group.'#residential-'.$group;
				header('Location: '.$redirectUrl);
				exit;
			}
			if ($action === 'save_draft') {
				$savedCard = GETPOST('save_draft_submit', 'alphanohtml');
				if (preg_match('/^[a-f0-9]{32}$/D', $savedCard) && isset($dcValues[$savedCard])) {
					$redirectUrl .= '&report_saved_card='.urlencode($savedCard);
				}
				$scrollY = GETPOSTINT('powerplantpv_scroll_y');
				if ($scrollY > 0 && strpos($redirectUrl, 'report_saved_card=') === false) {
					$redirectUrl .= '&report_scroll_y='.(int) $scrollY;
				}
			}
			header('Location: '.$redirectUrl);
			exit;
		}
	}
}

if ($action === 'generate_serial_annex' && $reportFetch > 0) {
	require_once __DIR__.'/class/powerplantpvreportserialannex.class.php';
	$annex = new PowerPlantPVReportSerialAnnex();
	$annexTree = $builder->loadReportTree((int) $report->id);
	if (!is_array($annexTree) || $annex->generate($intervention, $annexTree) < 0) {
		setEventMessages($langs->trans($annex->error !== '' ? $annex->error : 'ErrorRecordNotFound'), null, 'errors');
	} else {
		setEventMessages($langs->trans('PowerPlantPVSerialAnnexGenerated'), null, 'mesgs');
		header('Location: '.DOL_URL_ROOT.'/fichinter/document.php?id='.(int) $id);
		exit;
	}
}

if ($action === 'add_dc_measure_line') { accessforbidden(); }

if ($action === 'recalculate' && $caneditreport && $reportFetch > 0) {
	$result = $builder->recalculateSnapshot($report, $intervention, $user, $manualServiceIds, PowerPlantPVReport::STATUS_DRAFT);
	if ($result < 0) {
		setEventMessages($builder->error, $builder->errors, 'errors');
	} else {
		setEventMessages($langs->trans('PowerPlantPVReportRecalculated'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $id);
		exit;
	}
}

if ($action === 'upload_file' && $caneditreport && $reportFetch > 0) {
	$result = powerplantpvReportUploadFile($report, $intervention, $fieldId, $user);
	if ($result < 0) {
		setEventMessages($langs->trans('ErrorFileNotUploaded'), null, 'errors');
	} else {
		setEventMessages($langs->trans('FileUploaded'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $id);
		exit;
	}
}

if ($action === 'delete_file' && $caneditreport && $reportFetch > 0) {
	$result = powerplantpvReportDeleteFile($report, $intervention, $fileId);
	if ($result < 0) {
		setEventMessages($langs->trans('PowerPlantPVReportFileRemoveFailed'), null, 'errors');
	} else {
		setEventMessages($langs->trans('PowerPlantPVReportFileRemoved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $id);
		exit;
	}
}

$form = new Form($db);
$title = $langs->trans('PowerPlantPVReportTab');

$morejs = array('/powerplantpv/js/powerplantpv_report.js?v='.(int) filemtime(__DIR__.'/js/powerplantpv_report.js'));
if (!empty($conf->use_javascript_ajax)) {
	$morejs[] = '/powerplantpv/js/powerplantpv_reportcompletion.js?v='.(int) filemtime(__DIR__.'/js/powerplantpv_reportcompletion.js');
	$morejs[] = '/powerplantpv/js/powerplantpv_residential.js?v='.(int) filemtime(__DIR__.'/js/powerplantpv_residential.js');
}
if ($signatureRole !== '' || !empty($partSigning)) {
	array_unshift($morejs, $signatureScript);
}
$morecss = array('/powerplantpv/css/powerplantpv_report.css?v='.(int) filemtime(__DIR__.'/css/powerplantpv_report.css'));
if (!defined('REQUIRE_JQUERY_MULTISELECT')) {
	define('REQUIRE_JQUERY_MULTISELECT', 'select2');
}
llxHeader('', $title, '', '', 0, 0, $morejs, $morecss, '', 'mod-powerplantpv page-maintenance-intervention-report');

$head = array();
if (function_exists('fichinter_prepare_head')) {
	$head = fichinter_prepare_head($intervention);
}
print dol_get_fiche_head($head, 'powerplantpv_report', $langs->trans('Intervention'), -1, 'intervention');
if ($action === 'reopen_part') {
	$confirmationUrl = $_SERVER['PHP_SELF'].'?id='.(int) $id.'&reopen_part_code='.urlencode($partToReopen);
	print $form->formconfirm($confirmationUrl, $langs->trans('PowerPlantPVPartReopen'), $langs->trans('PowerPlantPVPartReopenConfirm'), 'confirm_reopen_part');
}

$linkback = '<a href="'.DOL_URL_ROOT.'/fichinter/list.php?restore_lastsearch_values=1'
	.(!empty($intervention->socid) ? '&socid='.(int) $intervention->socid : '').'">'
	.$langs->trans('BackToList').'</a>';
$morehtmlref = '<div class="refidno">';
$morehtmlref .= $form->editfieldkey(
	'RefCustomer',
	'ref_client',
	$intervention->ref_client,
	$intervention,
	$usercancreateintervention,
	'string',
	'',
	0,
	1
);
$morehtmlref .= $form->editfieldval(
	'RefCustomer',
	'ref_client',
	$intervention->ref_client,
	$intervention,
	$usercancreateintervention,
	'string',
	'',
	null,
	null,
	'',
	1
);
if (isset($intervention->thirdparty) && is_object($intervention->thirdparty) && !empty($intervention->thirdparty->id)) {
	$morehtmlref .= '<br>'.$intervention->thirdparty->getNomUrl(1, 'customer');
}
if (isModEnabled('project')) {
	$langs->load('projects');
	$morehtmlref .= '<br>';
	if ($usercancreateintervention) {
		$morehtmlref .= img_picto($langs->trans('Project'), 'project', 'class="pictofixedwidth"');
		if ($action !== 'classify') {
			$morehtmlref .= '<a class="editfielda" href="'.$_SERVER['PHP_SELF'].'?action=classify'
				.'&token='.newToken().'&id='.(int) $intervention->id.'">'
				.img_edit($langs->transnoentitiesnoconv('SetProject')).'</a> ';
		}
		$morehtmlref .= $form->form_project(
			$_SERVER['PHP_SELF'].'?id='.(int) $intervention->id,
			$intervention->socid,
			$intervention->fk_project,
			($action === 'classify' ? 'projectid' : 'none'),
			0,
			0,
			0,
			1,
			'',
			'maxwidth300'
		);
	} elseif (!empty($intervention->fk_project)) {
		$proj = new Project($db);
		if ($proj->fetch((int) $intervention->fk_project) > 0) {
			$morehtmlref .= $proj->getNomUrl(1);
			if ($proj->title) {
				$morehtmlref .= '<span class="opacitymedium"> - '.dol_escape_htmltag($proj->title).'</span>';
			}
		}
	}
}
$morehtmlref .= '</div>';
$signatureStatuses = PowerPlantPVReport::interventionSignatureStatuses();
if ($reportFetch > 0 && (int) $intervention->signed_status === $signatureStatuses['STATUS_SIGNED_ALL']) {
	$langs->load('commercial');
	$langs->tab_translate['SignedAll'] = $langs->transnoentitiesnoconv('PowerPlantPVInterventionSignedInternallyAndByThirdParty');
}
dol_banner_tab($intervention, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
if ($locked) {
	print '<div class="warning">'.$langs->trans('PowerPlantPVReportReadonlyLocked').'</div>';
}

$tree = null;
$diagnosticTree = null;
$emptyExistingSnapshot = false;
$invalidExistingSnapshot = false;
if ($reportFetch > 0) {
	$tree = $builder->loadReportTree((int) $report->id);
	if (!is_array($tree)) {
		setEventMessages($builder->error, $builder->errors, 'errors');
	} elseif (empty($tree['sections'])) {
		$emptyExistingSnapshot = true;
		$diagnosticTree = $builder->buildPreviewTree($intervention, $manualServiceIds, (int) $report->id);
		if (is_array($diagnosticTree)) {
			$tree = $diagnosticTree;
		}
	} elseif (powerplantpvReportTreeHasNonDcSectionWithoutFields($tree)) {
		$invalidExistingSnapshot = true;
		$diagnosticTree = $builder->buildPreviewTree($intervention, $manualServiceIds, (int) $report->id);
		if (is_array($diagnosticTree)) {
			$tree = $diagnosticTree;
		}
	}
} else {
	$tree = $builder->buildPreviewTree($intervention, $manualServiceIds);
}

$messages = array();
if ($emptyExistingSnapshot) {
	$messages[] = 'PowerPlantPVReportEmptySnapshot';
}
if ($invalidExistingSnapshot) {
	$messages[] = 'PowerPlantPVReportSnapshotSectionsWithoutFields';
}
$treeIsParts = $isPartsReport || (is_array($tree) && PowerPlantPVReportParts::isPartsTemplate($tree['template']['code'] ?? ''));
if (is_array($tree) && !empty($tree['messages']) && is_array($tree['messages'])) {
	$messages = array_merge($messages, $tree['messages']);
}
if (is_array($diagnosticTree) && !empty($diagnosticTree['messages']) && is_array($diagnosticTree['messages'])) {
	$messages = array_merge($messages, $diagnosticTree['messages']);
}
$messages = array_values(array_unique($messages));
if ($treeIsParts) {
	// A self-inspection report does not depend on maintenance contract services.
	$messages = array_values(array_diff($messages, array('PowerPlantPVReportNoContractPrestations')));
}
if (!empty($messages)) {
	foreach ($messages as $messageKey) {
		print '<div class="info">'.$langs->trans((string) $messageKey).'</div>';
	}
}

if (is_array($tree) && empty($tree['can_generate'])) {
	print '<div class="opacitymedium">'.$langs->trans('PowerPlantPVReportCannotBeGenerated').'</div>';
} elseif (is_array($tree)) {
	$manualOptions = $builder->fetchManualMaintenanceServiceOptions();
	$manualContext = is_array($diagnosticTree) ? $diagnosticTree : $tree;
	$manualMessages = !empty($manualContext['messages']) && is_array($manualContext['messages']) ? $manualContext['messages'] : array();
	$noContractPrestations = in_array('PowerPlantPVReportNoContractPrestations', $manualMessages, true);
	$isResidentialTree = ($tree['report']->report_template_code ?? '') === PowerPlantPVReportTemplate::CODE_RESIDENTIAL;
	$residentialContext = array();
	$residentialUpgrade = $isResidentialTree && $caneditreport && (count(PowerPlantPVResidentialReport::proofFields($tree)) !== 2 || !PowerPlantPVResidentialReport::hasCommunication($tree));
	if ($residentialUpgrade) {
		print '<div class="info">'.$langs->trans('PVResidentialRecalculate').'</div>';
	} elseif ($isResidentialTree && PowerPlantPVResidentialReport::hasSections($tree)) {
		$residential = new PowerPlantPVResidentialReport($db);
		$options = $caneditreport ? $residential->options($builder->fetchResidentialEquipmentContext($intervention), (int) $intervention->entity, $user) : array();
		$data = $reportFetch > 0 ? $residential->load($report) : array('placements' => array(), 'roofs' => array(), 'strings' => array());
		if ($data === null || ($caneditreport && !$options)) {
			setEventMessages($langs->trans($residential->error ?: 'ErrorRecordNotFound'), null, 'errors');
		} else {
			if ($caneditreport && empty($data['initialized'])) {
				$data['strings'] = PowerPlantPVResidentialReport::legacyStrings($tree);
			}
			if ($caneditreport && $residentialForm !== null && GETPOSTINT('residential_complete', 2) === 1) {
				$options['savedStaff'] = $data['staff'] ?? array();
				$data = PowerPlantPVResidentialReport::normalize($residentialForm, $options, false)['data'];
			}
			if ($caneditreport && $action === '' && in_array($residentialAppend, array('roofs', 'strings'), true)) {
				$data[$residentialAppend][] = array();
			}
			$proofs = powerplantpvResidentialProofOptions($tree, $intervention);
			$residentialContext = array('options' => $options + $proofs, 'data' => $data + $proofs);
		}
	}
	$snapshotNeedsRebuild = ($emptyExistingSnapshot || $invalidExistingSnapshot || $residentialUpgrade);
	$showManualSelection = (($reportFetch <= 0 || $snapshotNeedsRebuild)
		&& $caneditreport
		&& $noContractPrestations
		&& !$treeIsParts
		&& !empty($manualOptions));
	$formClass = 'powerplantpv-report-form'.($caneditreport ? ' powerplantpv-report-form-editable' : '');
	$viewKey = (int) $conf->entity.':'.(int) $user->id.':'.(int) $id;
	print '<form id="powerplantpvreportform" class="'.dol_escape_htmltag($formClass).'" data-view-key="'.$viewKey.'" data-unsaved="'.dol_escape_htmltag($langs->transnoentities('PowerPlantPVUnsavedChanges')).'" data-saving="'.dol_escape_htmltag($langs->transnoentities('PowerPlantPVSavingReport')).'" data-leave="'.dol_escape_htmltag($langs->transnoentities('PowerPlantPVLeaveUnsaved')).'" method="POST" enctype="multipart/form-data" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="id" value="'.((int) $id).'">';
	print '<div class="powerplantpv-report-save-state opacitymedium" role="status" aria-live="polite"></div>';
	print '<input type="hidden" id="powerplantpv-scroll-y" name="powerplantpv_scroll_y" value="">';
	print '<input type="hidden" id="powerplantpv-restore-scroll-y" value="'.((int) $reportRestoreScrollY).'">';

	if ($showManualSelection) {
		print '<div class="powerplantpv-report-manual">';
		print '<label>'.$langs->trans('PowerPlantPVReportManualServices').'</label>';
		print $form->multiselectarray('manual_services', $manualOptions, $manualServiceIds, 0, 0, 'powerplantpv-report-control powerplantpv-report-choice', 0, 0, '', '', '', 0);
		print '<div class="opacitymedium">'.$langs->trans('PowerPlantPVReportManualServicesHelp').'</div>';
		print '</div>';
	}

	if (!$treeIsParts && !$isResidentialTree) {
		powerplantpvRenderReportCompletion($completionService, $report, $intervention, $user, $caneditcompletion, $canwriteplant && $permissiontowrite, $completionPayload ?: array(), $langs);
	}

	$renderEditableFields = $caneditreport && !$residentialUpgrade && (!$isResidentialTree || $residentialContext);
	if (!empty($conf->use_javascript_ajax)) {
		print '<div class="powerplantpv-report-navigation" data-report-contents-label="'.dol_escape_htmltag($langs->transnoentities('PowerPlantPVReportContents')).'">';
		print '<div class="right marginbottomonly" data-report-disclosure-controls hidden>';
		$disclosureActions = array('open' => array('ExpandAll', 'folder-open'), 'close' => array('UndoExpandAll', 'folder'));
		foreach ($disclosureActions as $disclosure => list($translation, $icon)) {
			$label = dol_escape_htmltag($langs->transnoentities($translation));
			print '<a href="#" role="button" title="'.$label.'" aria-label="'.$label.'" data-report-disclosure="'.$disclosure.'">';
			print img_picto('', $icon, 'class="paddingright"').'<span class="hideonsmartphone">'.$label.'</span></a>';
			if ($disclosure === 'open') {
				print ' | ';
			}
		}
		print '</div></div>';
	}
	$signableParts = array();
	$reopenableParts = array();
	if ($reportFetch > 0 && $isPartsReport && !$locked && !$snapshotNeedsRebuild && $signatureScript !== '') {
		foreach (array_keys(PowerPlantPVReportParts::definitions()) as $partCode) {
			if ($partsHelper->canSign($report, $intervention, $user, $partCode, 'technician', $partSignatures)) {
				$signableParts[$partCode] = true;
			}
		}
	}
	if ($isPartsReport && !$locked && ($user->hasRight('powerplantpv', 'attestation', 'manage_signed') || !empty($user->admin))) {
		foreach (array_keys(PowerPlantPVReportParts::definitions()) as $partCode) {
			$signature = $partSignatures[$partCode]['technician'] ?? null;
			if (is_object($signature) && $partsHelper->isSignerOrSupervisor($user, (int) $signature->fk_user)) {
				$reopenableParts[$partCode] = true;
			}
		}
	}
	$displaySections = $residentialContext ? PowerPlantPVResidentialReport::presentationSections($tree['sections'], $residentialContext['data']['staff'] ?? array()) : $tree['sections'];
	powerplantpvReportRenderSections($displaySections, $renderEditableFields, $form, isset($tree['powerplants']) && is_array($tree['powerplants']) ? $tree['powerplants'] : array(), $treeIsParts ? $lockedPartSectionIds : array(), $treeIsParts ? $partSignatures : null, $signableParts, $reopenableParts, $residentialContext);
	if ($residentialContext && $renderEditableFields) {
		print '<input type="hidden" name="residential_complete" value="1">';
		print '<input type="hidden" name="ppv_staff_submitted" value="1">';
	}

	if ($renderEditableFields) {
		print '<div class="center powerplantpv-report-actions">';
		print '<input type="submit" class="button button-save" name="save_draft_submit" value="'.dol_escape_htmltag($langs->trans('Save')).'">';
		print ' ';
		print '<input type="submit" class="button button-save" name="save_report_submit" value="'.dol_escape_htmltag($langs->trans('PowerPlantPVReportFinalize')).'">';
		print ' ';
		if ($snapshotNeedsRebuild && $reportFetch > 0 && empty($lockedPartSectionIds)) {
			print ' ';
			print '<input type="submit" class="button button-save" name="recalculate_submit" value="'.dol_escape_htmltag($langs->trans('PowerPlantPVReportRecalculate')).'">';
		}
		print '</div>';
		print '<input type="submit" class="button button-save powerplantpv-floating-save" name="save_draft_submit" value="'.dol_escape_htmltag($langs->trans('Save')).'">';
	} elseif ($residentialUpgrade) {
		print '<div class="center"><button type="submit" class="button" name="recalculate_submit" value="1">'.$langs->trans('PowerPlantPVReportRecalculate').'</button></div>';
	}
	print '</form>';

	print '<div class="center powerplantpv-report-actions" id="powerplantpv-report-signatures">';
	if ($reportFetch > 0 && !$treeIsParts && $report->isSaved() && !$snapshotNeedsRebuild && !$thirdPartySigned && $permissiontowrite) {
		$signatureUrl = powerplantpvReportGetInterventionOnlineSignatureUrl($intervention);
		if ($signatureUrl !== '') {
			print dolGetButtonAction($langs->trans('PowerPlantPVReportSignButton'), '', 'default', $signatureUrl);
		}
	}
	$signatureLabels = array('technician' => 'PowerPlantPVTechnicianSignButton', 'technical' => 'PowerPlantPVTechnicalSignButton');
	foreach ($signatureLabels as $role => $label) {
		if ($signingAllowed[$role] && !$snapshotNeedsRebuild) {
			$url = $_SERVER['PHP_SELF'].'?id='.(int) $id.'&action=prepare_signature&signature_role='.$role.'&token='.newToken().'#powerplantpv-report-signatures';
			print dolGetButtonAction($langs->trans($label), '', 'default', $url);
		}
	}
	if ($technicianSigned) {
		print '<p class="opacitymedium">'.$langs->trans('PowerPlantPVTechnicianSignSummary',
			dol_escape_htmltag((string) $report->technician_signer_name), dol_print_date($report->date_technician_sign, 'dayhour')).'</p>';
	}
	if ($technicalSigned) {
		print '<p class="opacitymedium">'.$langs->trans('PowerPlantPVTechnicalSignSummary',
			dol_escape_htmltag((string) $report->technical_signer_name), dol_print_date($report->date_technical_sign, 'dayhour')).'</p>';
	}
	if ($signatureRole !== '') {
		$consent = $signatureRole === 'technical' ? 'PowerPlantPVTechnicalSignConsent' : 'PowerPlantPVTechnicianSignConsent';
		print '<form method="POST" id="powerplantpv-signature-form" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="id" value="'.(int) $id.'">';
		print '<input type="hidden" name="action" value="save_signature">';
		print '<input type="hidden" name="signature_role" value="'.dol_escape_htmltag($signatureRole).'">';
		print '<input type="hidden" name="signature_image" value="">';
		print '<div id="powerplantpv-signature-pad"></div>';
		print '<button type="button" class="button buttonreset" id="powerplantpv-signature-clear">'.$langs->trans('ClearSignature').'</button>';
		print '<p><label><input type="checkbox" name="signature_consent" value="1" required> '.$langs->trans($consent).'</label></p>';
		print '<input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans($signatureLabels[$signatureRole])).'">';
		print '</form>';
	}
	if ($reportFetch > 0 && $isPartsReport) {
		powerplantpvReportRenderPartSignatures($snapshotNeedsRebuild, $partSignatures, $partSigning);
		if (!empty($partSigning) && $partSigning['part'] !== PowerPlantPVReportParts::FINAL_CODE) {
			$cancelUrl = $_SERVER['PHP_SELF'].'?id='.(int) $id.'#powerplantpv-part-actions-'.$partSigning['part'];
			print '<div class="center" data-part-signature-dialog data-title="'.dol_escape_htmltag(powerplantpvReportPartLabel($partSigning['part'])).'"';
			print ' data-close-label="'.dol_escape_htmltag($langs->transnoentities('Close')).'" data-cancel-url="'.dol_escape_htmltag($cancelUrl).'">';
			powerplantpvReportRenderPartSignatureForm($partSigning['part'], $partSigning['role'], $partSignatureError);
			print '</div>';
		}
	}
	print '</div>';

	$showSerialAnnex = $cangenerateserialannex && $reportFetch > 0;
	$showRecalculate = $caneditreport && $reportFetch > 0 && !$snapshotNeedsRebuild && empty($lockedPartSectionIds);
	if ($showSerialAnnex || $showRecalculate) {
		print '<div class="powerplantpv-report-document-actions">';
	}
	if ($showSerialAnnex) {
		print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" class="powerplantpv-inline-form">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="id" value="'.(int) $id.'">';
		print '<input type="hidden" name="action" value="generate_serial_annex">';
		print '<input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('PowerPlantPVSerialAnnex')).'">';
		print '</form>';
	}

	if ($showRecalculate) {
		print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" class="powerplantpv-inline-form">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="id" value="'.((int) $id).'">';
		print '<input type="hidden" name="action" value="recalculate">';
		print '<input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('PowerPlantPVReportRecalculate')).'">';
		print '</form>';
	}
	if ($showSerialAnnex || $showRecalculate) {
		print '</div>';
	}
}
print '</div>';

print dol_get_fiche_end();

// Apply the native Select2 presentation consistently to report choices.
print ajax_combobox('.powerplantpv-report-choice', array(), 0, 0, '100%');
print ajax_combobox('.powerplantpv-report-date select');

llxFooter();
$db->close();

/**
 * Return true if intervention is closed or signed.
 *
 * @param	Fichinter	$intervention	Intervention object
 * @return	bool						True if locked
 */
function powerplantpvReportIsInterventionLocked($intervention)
{
	$status = 0;
	foreach (array('statut', 'status', 'fk_statut') as $property) {
		if (isset($intervention->{$property})) {
			$status = (int) $intervention->{$property};
			break;
		}
	}
	$closedStatus = (class_exists('Fichinter') && defined('Fichinter::STATUS_CLOSED')) ? (int) constant('Fichinter::STATUS_CLOSED') : 3;
	if ($status === $closedStatus) {
		return true;
	}
	return PowerPlantPVReport::interventionSignedByThirdParty((int) ($intervention->signed_status ?? 0));
}

/**
 * Validate intervention with native Dolibarr workflow when the report is finalized.
 *
 * @param	Fichinter	$intervention	Intervention object
 * @param	User		$user			User
 * @return	int							1 if validated, 0 if already validated, <0 on error
 */
function powerplantpvReportValidateInterventionIfNeeded($intervention, $user)
{
	global $langs;

	$status = 0;
	foreach (array('statut', 'status', 'fk_statut') as $property) {
		if (isset($intervention->{$property})) {
			$status = (int) $intervention->{$property};
			break;
		}
	}
	$validatedStatus = (class_exists('Fichinter') && defined('Fichinter::STATUS_VALIDATED')) ? (int) constant('Fichinter::STATUS_VALIDATED') : 1;
	if ($status >= $validatedStatus) {
		return 0;
	}
	if (!method_exists($intervention, 'setValid')) {
		$intervention->error = $langs->trans('PowerPlantPVReportInterventionValidationFailed');
		if (empty($intervention->errors) || !is_array($intervention->errors)) {
			$intervention->errors = array();
		}
		$intervention->errors[] = $intervention->error;
		return -1;
	}

	$result = $intervention->setValid($user, 0);
	if ($result < 0) {
		return -1;
	}

	return 1;
}

/**
 * Display intervention validation error after report finalization.
 *
 * @param	Fichinter	$intervention	Intervention object
 * @return	void
 */
function powerplantpvReportSetInterventionValidationError($intervention)
{
	global $langs;

	$message = $langs->trans('PowerPlantPVReportInterventionValidationFailed');
	$details = array();
	if (!empty($intervention->error) && (string) $intervention->error !== $message) {
		$details[] = $intervention->error;
	}
	if (!empty($intervention->errors) && is_array($intervention->errors)) {
		foreach ($intervention->errors as $error) {
			if ((string) $error !== '' && (string) $error !== $message && !in_array((string) $error, $details, true)) {
				$details[] = (string) $error;
			}
		}
	}

	setEventMessages($message, $details, 'errors');
}

/**
 * Validate submitted token while keeping Dolibarr global CSRF protection active.
 *
 * @param	string	$token	Submitted token
 * @return	bool			True if valid
 */
function powerplantpvReportSubmittedTokenValid($token)
{
	if ($token === '') {
		return false;
	}
	if (function_exists('dol_verifyToken')) {
		return (bool) dol_verifyToken($token);
	}
	$valid = false;
	if (function_exists('currentToken')) {
		$valid = hash_equals((string) currentToken(), (string) $token);
	}
	if (!$valid && !empty($_SESSION['newtoken'])) {
		$valid = hash_equals((string) $_SESSION['newtoken'], (string) $token);
	}

	return $valid || !function_exists('currentToken');
}

/**
 * Return true when a stored snapshot must be rebuilt before saving submitted values.
 *
 * @param	PowerPlantPVReportBuilder	$builder	Report builder
 * @param	int							$reportId	Report id
 * @return	bool									True when snapshot is incomplete
 */
function powerplantpvReportSnapshotNeedsRebuild($builder, $reportId)
{
	$tree = $builder->loadReportTree((int) $reportId);
	if (!is_array($tree)) {
		return false;
	}
	if (empty($tree['sections']) || !is_array($tree['sections'])) {
		return true;
	}

	if (($tree['report']->report_template_code ?? '') === PowerPlantPVReportTemplate::CODE_RESIDENTIAL
		&& ($tree['report']->status ?? '') === PowerPlantPVReport::STATUS_DRAFT
		&& !PowerPlantPVResidentialReport::hasCommunication($tree)) {
		return true;
	}
	return powerplantpvReportTreeHasNonDcSectionWithoutFields($tree);
}

/**
 * Return a native Dolibarr online signature URL for the intervention.
 *
 * @param	Fichinter	$intervention	Intervention object
 * @return	string						Online signature URL, or empty string when unavailable
 */
function powerplantpvReportGetInterventionOnlineSignatureUrl($intervention)
{
	if (!is_object($intervention) || empty($intervention->ref) || !is_readable(DOL_DOCUMENT_ROOT.'/core/lib/signature.lib.php')) {
		return '';
	}
	require_once DOL_DOCUMENT_ROOT.'/core/lib/signature.lib.php';
	if (!function_exists('getOnlineSignatureUrl')) {
		return '';
	}

	$source = !empty($intervention->element) ? (string) $intervention->element : 'fichinter';

	return (string) getOnlineSignatureUrl(0, $source, (string) $intervention->ref, 1, $intervention);
}

/**
 * Return the native Dolibarr online signature block for the intervention.
 *
 * @param	Fichinter	$intervention	Intervention object
 * @return	string						Native signature HTML block, or empty string when unavailable
 */
function powerplantpvReportShowInterventionOnlineSignatureBlock($intervention)
{
	if (!is_object($intervention) || empty($intervention->ref) || !is_readable(DOL_DOCUMENT_ROOT.'/core/lib/signature.lib.php')) {
		return '';
	}
	require_once DOL_DOCUMENT_ROOT.'/core/lib/signature.lib.php';
	if (!function_exists('showOnlineSignatureUrl')) {
		return '';
	}

	$source = !empty($intervention->element) ? (string) $intervention->element : 'fichinter';

	return (string) showOnlineSignatureUrl($source, (string) $intervention->ref, $intervention, '');
}

/**
 * Return submitted scalar values.
 *
 * @return	array<string,mixed>	Values by stable key
 */
function powerplantpvReportGetSubmittedValues()
{
	$raw = GETPOST('report_values', 'array');
	$values = array();
	if (is_array($raw)) {
		foreach ($raw as $key => $value) {
			$key = (string) $key;
			if ($key === '') {
				continue;
			}
			if (is_array($value)) {
				$values[$key] = array_map('dol_string_nohtmltag', array_map('strval', $value));
			} else {
				$values[$key] = dol_string_nohtmltag((string) $value);
			}
		}
	}

	$choiceKeys = GETPOST('report_choice_keys', 'array');
	if (is_array($choiceKeys)) {
		foreach ($choiceKeys as $hash => $stableKey) {
			if (!is_string($stableKey) || (string) $hash !== hash('sha256', $stableKey)) {
				continue;
			}
			$input = 'report_choice_'.$hash;
			if (GETPOSTISSET($input.'_multiselect')) {
				$selected = GETPOST($input, 'array', 2);
				$values[$stableKey] = is_array($selected) ? array_map('dol_string_nohtmltag', array_map('strval', $selected)) : array();
			} elseif (GETPOSTISSET($input)) {
				$values[$stableKey] = GETPOST($input, 'alphanohtml', 2);
			}
		}
	}

	$textareaKeys = GETPOST('report_textarea_keys', 'array');
	if (is_array($textareaKeys)) {
		foreach ($textareaKeys as $hash => $stableKey) {
			$hash = dol_string_nohtmltag((string) $hash);
			$stableKey = dol_string_nohtmltag((string) $stableKey);
			if ($hash === '' || $stableKey === '') {
				continue;
			}
			$values[$stableKey] = GETPOST('report_textarea_value_'.$hash, 'restricthtml');
		}
	}

	return $values;
}

/**
 * Return submitted date values.
 *
 * @return	array<string,int|string>	Timestamps or an empty string for a cleared field
 */
function powerplantpvReportGetSubmittedDateValues()
{
	$keys = GETPOST('report_date_keys', 'array');
	if (!is_array($keys)) {
		return array();
	}
	$values = array();
	foreach ($keys as $hash => $stableKey) {
		$hash = dol_string_nohtmltag((string) $hash);
		$stableKey = (string) $stableKey;
		if ($hash === '' || $stableKey === '') {
			continue;
		}
		$prefix = 'report_date_'.$hash;
		$day = GETPOSTINT($prefix.'day');
		$month = GETPOSTINT($prefix.'month');
		$year = GETPOSTINT($prefix.'year');
		$hour = GETPOSTINT($prefix.'hour');
		$min = GETPOSTINT($prefix.'min');
		if ($year <= 0 || $month <= 0 || $day <= 0) {
			$values[$stableKey] = '';
			continue;
		}
		$timestamp = dol_mktime($hour, $min, 0, $month, $day, $year);
		if (is_int($timestamp)) {
			$values[$stableKey] = $timestamp;
		}
	}

	return $values;
}

/**
 * Return submitted DC measure values.
 *
 * @return	array<string,array<string,mixed>>	DC values
 */
function powerplantpvReportGetSubmittedDcMeasures()
{
	$raw = GETPOST('dc_measures', 'array');
	if (!is_array($raw)) {
		return array();
	}
	$values = array();
	foreach ($raw as $key => $row) {
		if (!is_array($row)) {
			continue;
		}
		$clean = array();
		$clean['id'] = !empty($row['id']) ? (int) $row['id'] : 0;
		$clean['stable_key'] = !empty($row['stable_key']) ? dol_string_nohtmltag((string) $row['stable_key']) : '';
		$clean['inverter_label'] = isset($row['inverter_label']) ? dol_string_nohtmltag((string) $row['inverter_label']) : '';
		$clean['mppt_number'] = isset($row['mppt_number']) ? dol_string_nohtmltag((string) $row['mppt_number']) : '';
		$clean['pv_input_number'] = isset($row['pv_input_number']) ? dol_string_nohtmltag((string) $row['pv_input_number']) : '';
		$clean['string_ref'] = isset($row['string_ref']) ? dol_string_nohtmltag((string) $row['string_ref']) : '';
		$clean['is_connected'] = isset($row['is_connected']) && in_array((string) $row['is_connected'], array('0', '1'), true) ? (int) $row['is_connected'] : '';
		$clean['open_circuit_voltage'] = isset($row['open_circuit_voltage']) ? dol_string_nohtmltag((string) $row['open_circuit_voltage']) : '';
		$clean['polarity_checked'] = isset($row['polarity_checked']) && in_array((string) $row['polarity_checked'], array('-1', '0', '1'), true) ? (int) $row['polarity_checked'] : -1;
		$clean['insulation_status'] = isset($row['insulation_status']) ? dol_string_nohtmltag((string) $row['insulation_status']) : '';
		$clean['positive_to_ground_voltage'] = isset($row['positive_to_ground_voltage']) ? dol_string_nohtmltag((string) $row['positive_to_ground_voltage']) : '';
		$clean['negative_to_ground_voltage'] = isset($row['negative_to_ground_voltage']) ? dol_string_nohtmltag((string) $row['negative_to_ground_voltage']) : '';
		$clean['observation'] = isset($row['observation']) ? dol_string_nohtmltag((string) $row['observation']) : '';
		$values[(string) $key] = $clean;
	}

	return $values;
}

/**
 * Return missing required field labels for a report.
 *
 * @param	int	$reportId	Report id
 * @param	array<int,bool>|null	$onlySectionIds	Restrict to these snapshot sections
 * @param	bool	$isPartsReport	Whether manual signer fields are obsolete
 * @return	array<int,string>|int	Labels, <0 on error
 */
function powerplantpvReportFindMissingRequiredFields($reportId, $onlySectionIds = null, bool $isPartsReport = false)
{
	global $db;

	$fieldObject = new PowerPlantPVReportField($db);
	$fields = $fieldObject->fetchAllByReport((int) $reportId, 'position', 'ASC');
	if (!is_array($fields)) {
		return -1;
	}

	$missing = array();
	$connections = $connectionConflicts = array();
	foreach ($fields as $field) {
		$sectionId = (int) $field->fk_report_section;
		if ($field->field_code === 'RES_CONNECTION') {
			$connections[$sectionId] = (string) $field->value_text;
		} elseif ($field->field_code === 'RES_CONNECTION_HISTORY' && trim((string) $field->value_text) !== '') {
			$connectionConflicts[$sectionId] = true;
		}
	}
	foreach ($fields as $field) {
		$sectionId = (int) $field->fk_report_section;
		$isRequired = !empty($field->is_required)
			|| ($field->field_code === 'RES_CONNECTION_WIFI' && ($connections[$sectionId] ?? '') === 'WIFI')
			|| ($field->field_code === 'RES_CONNECTION' && isset($connectionConflicts[$sectionId]));
		if (!$isRequired) {
			continue;
		}
		if (is_array($onlySectionIds) && empty($onlySectionIds[(int) $field->fk_report_section])) {
			continue;
		}
		if (!empty($field->readonly) || (int) $field->visible_form <= 0 || PowerPlantPVReportPresentation::isRetiredProductionField((string) $field->field_code)
			|| ($isPartsReport && ((string) $field->field_code === PowerPlantPVReportParts::LEGACY_RESERVES_FIELD
				|| PowerPlantPVReportParts::isManualSignerField((string) $field->field_code)))) {
			continue;
		}
		if (in_array((string) $field->field_type, array('computed', 'signature'), true)) {
			continue;
		}
		if (powerplantpvReportFieldIsPreviousReading($field)) {
			continue;
		}
		if (powerplantpvReportRequiredFieldIsEmpty($field) || ($field->field_code === 'RES_MONITORING'
			&& !in_array($field->value_text, array('valid', 'observation', 'not_applicable'), true))) {
			$label = powerplantpvReportLocalizedObjectLabel($field, 'field_label');
			$missing[] = $label !== '' ? $label : (string) $field->field_code;
		}
	}

	// Optional installed identities must not become required report answers.

	return $missing;
}

/**
 * Return true when a required report field has no exploitable value.
 *
 * @param	PowerPlantPVReportField	$field	Field
 * @return	bool							True if empty
 */
function powerplantpvReportRequiredFieldIsEmpty($field)
{
	global $db;

	$type = (string) $field->field_type;
	if ($type === 'file') {
		if (empty($field->id)) {
			return true;
		}
		$fileObject = new PowerPlantPVReportFile($db);
		$files = $fileObject->fetchAllByField((int) $field->id);
		return !is_array($files) || empty($files);
	}
	if (powerplantpvReportIsNumericFieldType($type)) {
		return $field->value_number === null || (string) $field->value_number === '';
	}
	if ($type === 'date' || $type === 'datetime') {
		return empty($field->value_date);
	}
	if (PowerPlantPVReportPresentation::allowsNotApplicable((string) $field->field_code, $type)) {
		return !in_array((string) $field->value_text, array('0', '1', 'not_applicable'), true);
	}
	if ($type === 'checkbox' || $type === 'yesno') {
		return !in_array((string) $field->value_text, array('0', '1'), true);
	}
	if ($type === 'multiselect') {
		return trim((string) $field->value_text) === '';
	}

	return powerplantpvReportIsHtmlTextEmpty((string) $field->value_text);
}

/**
 * Return true when a text/HTML field is empty after stripping markup.
 *
 * @param	string	$value	Value
 * @return	bool			True if empty
 */
function powerplantpvReportIsHtmlTextEmpty($value)
{
	$value = str_replace(array('&nbsp;', '&#160;', "\xc2\xa0"), ' ', (string) $value);
	$value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$value = trim(strip_tags($value));

	return $value === '';
}

/**
 * Display missing required field errors.
 *
 * @param	array<int,string>	$missing	Missing field labels
 * @return	void
 */
function powerplantpvReportSetMissingRequiredFieldsError($missing)
{
	global $langs;

	$details = array();
	$limit = 10;
	foreach (array_slice($missing, 0, $limit) as $label) {
		$details[] = (string) $label;
	}
	$remaining = count($missing) - count($details);
	if ($remaining > 0) {
		$details[] = $langs->trans('PowerPlantPVReportRequiredFieldsMore', $remaining);
	}

	setEventMessages($langs->trans('PowerPlantPVReportRequiredFieldsMissing'), $details, 'errors');
}

/**
 * Check if a loaded snapshot has non-DC sections but no non-DC field row at all.
 *
 * @param	array<string,mixed>	$tree	Loaded report tree
 * @return	bool						True when existing snapshot must be rebuilt
 */
function powerplantpvReportTreeHasNonDcSectionWithoutFields($tree)
{
	if (empty($tree['sections']) || !is_array($tree['sections'])) {
		return false;
	}

	$nonDcSectionCount = 0;
	$nonDcFieldCount = 0;
	foreach ($tree['sections'] as $row) {
		if (!is_array($row) || empty($row['section']) || !is_object($row['section'])) {
			continue;
		}
		if (powerplantpvReportSectionIsNativeCustomerSignature((string) $row['section']->section_code)) {
			continue;
		}
		if ((string) $row['section']->section_code === 'DC_ELECTRICAL_MEASURE') {
			continue;
		}
		$nonDcSectionCount++;
		$fields = isset($row['fields']) && is_array($row['fields']) ? $row['fields'] : array();
		$nonDcFieldCount += count($fields);
	}

	return ($nonDcSectionCount > 0 && $nonDcFieldCount <= 0);
}

/**
 * Render report sections.
 *
 * @param	array<int,array<string,mixed>>	$sections	Sections
 * @param	bool							$editable	Editable flag
 * @param	Form							$form		Form helper
 * @param	array<int,mixed>					$powerplants	Power plants
 * @param	array<int,bool>						$lockedSectionIds	Sections locked by a signature
 * @param	array<string,array<string,stdClass>>|null	$partSignatures	Signatures of a C&I report
 * @param	array<string,bool>					$signableParts	Parts the current user can sign
 * @param	array<string,bool>					$reopenableParts	Parts the current user can reopen
 * @return	void
 */
function powerplantpvReportRenderSections($sections, $editable, $form, $powerplants = array(), $lockedSectionIds = array(), $partSignatures = null, array $signableParts = array(), array $reopenableParts = array(), array $residential = array())
{
	global $id, $intervention, $langs;

	if (empty($sections) && empty($powerplants)) {
		print '<table class="noborder centpercent">';
		print '<tr class="oddeven"><td><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
		print '</table>';
		return;
	}

	$groups = array();
	$byPart = is_array($partSignatures);
	$partDefinitions = array();
	if ($byPart) {
		// Self-inspection reports are grouped by part, each part being filled and signed on its own test day.
		$powerplants = array();
		$partDefinitions = PowerPlantPVReportParts::definitions($sections);
		foreach (array_merge(array(''), array_keys($partDefinitions)) as $partCode) {
			$groups['part:'.$partCode] = array('part' => $partCode, 'powerplant' => null, 'sections' => array());
		}
	}
	foreach ($powerplants as $powerplant) {
		$key = 'powerplant:'.powerplantpvReportPowerplantSourceId($powerplant);
		if ($key === 'powerplant:0') {
			$key = 'powerplant:report:'.(is_object($powerplant) && !empty($powerplant->id) ? (int) $powerplant->id : count($groups));
		}
		if (!isset($groups[$key])) {
			$groups[$key] = array('powerplant' => $powerplant, 'sections' => array());
		}
	}
	$signatureRows = array();
	foreach ($sections as $row) {
		if (!is_array($row) || !isset($row['section']) || !is_object($row['section'])) {
			continue;
		}
		if (powerplantpvReportSectionIsNativeCustomerSignature((string) $row['section']->section_code)) {
			$signatureRows[] = $row;
			continue;
		}
		$key = 'general';
		if ($byPart) {
			if ((string) $row['section']->section_code === PowerPlantPVReportParts::LEGACY_RESERVES_SECTION) {
				continue;
			}
			$key = 'part:'.PowerPlantPVReportParts::partCodeForSection((string) $row['section']->section_code);
		} elseif (!empty($row['powerplant'])) {
			$key = 'powerplant:'.powerplantpvReportPowerplantSourceId($row['powerplant']);
		}
		if (!isset($groups[$key])) {
			$groups[$key] = array('powerplant' => !empty($row['powerplant']) ? $row['powerplant'] : null, 'sections' => array());
		}
		$groups[$key]['sections'][] = $row;
	}

	$dcMeasuresAnchors = array();
	$singlePlant = count($powerplants) === 1 ? powerplantpvReportPowerplantSourceId(reset($powerplants)) : 0;
	foreach ($groups as $group) {
		$hasPlant = !empty($group['powerplant']);
		$partCode = '';
		$partContentsTitle = '';
		if ($byPart) {
			if (empty($group['sections'])) {
				continue;
			}
			$partCode = (string) $group['part'];
			$plantLabel = $partCode !== '' ? powerplantpvReportPartLabel($partCode) : $langs->trans('PowerPlantPVAutocontrolCommonSections');
			if (isset($partDefinitions[$partCode])) {
				$partContentsTitle = $langs->transnoentities('PowerPlantPVAutocontrolPartNumber', $partDefinitions[$partCode]['number']);
				print '<div data-contents-group="'.dol_escape_htmltag($partContentsTitle).'">';
				$plantLabel .= ' · '.$partDefinitions[$partCode]['ref'];
			}
			$signedBy = array();
			foreach (($partSignatures[$partCode] ?? array()) as $signature) {
				$signedBy[] = (string) $signature->signer_name;
			}
			$morehtml = $signedBy ? '<span class="badge badge-status4 marginleftonlyshort">'.dol_escape_htmltag($langs->trans('PowerPlantPVPartSignedBy', implode(', ', array_unique($signedBy)))).'</span>' : '';
			print load_fiche_titre(dol_escape_htmltag($plantLabel).$morehtml, '', 'fa-clipboard-check');
		} elseif ($hasPlant) {
			$plantLabel = powerplantpvReportPowerplantLabel($group['powerplant']);
			print '<div data-contents-group="'.dol_escape_htmltag($plantLabel).'">';
			print load_fiche_titre(dol_escape_htmltag($plantLabel), '', 'fa-industry');
			powerplantpvReportRenderPowerplantTechnicalSummary($group['powerplant']);
		}
		foreach ($group['sections'] as $row) {
			$section = $row['section'];
			if (powerplantpvReportSectionIsPowerplantTechnicalSummary((string) $section->section_code)) {
				continue;
			}
			$fields = powerplantpvReportFilterRenderableFields(isset($row['fields']) && is_array($row['fields']) ? $row['fields'] : array(), $byPart);
			$sectionCode = (string) $section->section_code;
			if ($residential && $sectionCode === 'RES_STRINGS') {
				$fields = array_filter($fields, static fn ($field) => powerplantpvIsMicroProofField($field->field_code));
			}
			if ($residential && $sectionCode === 'RES_SAFETY' && !array_filter($fields, static fn ($field) => ($field->value_text ?? '') !== '')) {
				continue;
			}
			if ($sectionCode !== 'DC_ELECTRICAL_MEASURE' && empty($fields) && !($residential && $sectionCode === 'RES_STRINGS')) {
				continue;
			}
			// A signed part stays visible but can no longer be changed.
			$sectionEditable = $editable && empty($lockedSectionIds[(int) $section->id]);
			$label = powerplantpvReportLocalizedObjectLabel($section, 'section_label');
			if ($residential && $sectionCode === 'RES_WORK') {
				$label = $langs->transnoentities('PVResidentialCompletedWork');
			}
			$identity = !empty($row['equipment']) ? (object) $row['equipment'] : null;
			$identity = $identity && (string) $identity->equipment_type === 'INVERTER' ? $identity : null;
			$sectionKey = hash('sha256', (string) ($section->fk_powerplant ?? '').':'.(string) $section->section_code.':'.(string) ($section->occurrence_key ?? ''));
			$anchor = 'powerplantpv-section-'.$sectionKey;
			if ((string) $section->section_code === 'DC_ELECTRICAL_MEASURE') {
				$plantId = empty($group['powerplant']) ? $singlePlant : powerplantpvReportPowerplantSourceId($group['powerplant']);
				if ($plantId > 0 && !isset($dcMeasuresAnchors[$plantId])) {
					$anchor = 'powerplantpv-dc-measures-'.$plantId;
					$dcMeasuresAnchors[$plantId] = true;
				}
			}
			$contentsLabel = $label;
			if ($partContentsTitle !== '') {
				$sectionPrefix = $partContentsTitle.' · ';
				if (strpos($contentsLabel, $sectionPrefix) === 0) {
					$contentsLabel = substr($contentsLabel, strlen($sectionPrefix));
				}
			}
			if ($identity) {
				$marker = trim((string) $identity->equipment_marker);
				if ($marker !== '') {
					$contentsLabel .= ' · '.$marker;
				}
			} elseif (!empty($row['equipment'])) {
				$contentsLabel .= ' · '.powerplantpvReportEquipmentLabel($row['equipment']);
			}
			print '<details id="'.$anchor.'" class="powerplantpv-report-section" data-state-key="section:'.$sectionKey.'" data-contents-label="'.dol_escape_htmltag($contentsLabel).'" open>';
			$sectionSuffix = '';
			if ((string) $section->section_code === 'AC3_INSTALLATION') {
				$labelParts = explode(' · ', $label, 3);
				if (count($labelParts) === 3) {
					$label = $labelParts[0].' · '.$labelParts[1];
					$sectionSuffix = $labelParts[2];
				}
			}
			print '<summary class="liste_titre powerplantpv-section-heading"><span class="powerplantpv-section-heading-text"><span>'.dol_escape_htmltag($label).'</span><span class="powerplantpv-report-progress opacitymedium" data-progress-label="'.dol_escape_htmltag($langs->transnoentities('PowerPlantPVReportProgress')).'"></span>';
			if ($sectionSuffix !== '') {
				print ' <span>'.dol_escape_htmltag($sectionSuffix).'</span>';
			}
			if ($identity) {
				print '<span class="powerplantpv-section-identity">'.dol_escape_htmltag(PowerPlantPVReportPresentation::identity($identity, false)).'</span>';
			}
			print '</span>';
			if (!empty($section->is_required)) {
				print '<span class="badge powerplantpv-section-required">'.$langs->trans('Required').'</span>';
			}
			print '</summary>';
			if (!empty($row['equipment']) && !$identity) {
				print '<div class="refidno">'.dol_escape_htmltag(powerplantpvReportEquipmentLabel($row['equipment'])).'</div>';
			}
			if ((string) $section->section_code === 'DC_ELECTRICAL_MEASURE') {
				$dcMeasures = isset($row['dc_measures']) && is_array($row['dc_measures']) ? $row['dc_measures'] : array();
				powerplantpvReportRenderDcMeasures($section, $dcMeasures, $sectionEditable, $form);
				print '</details>';
				continue;
			}
			$isCommunication = $sectionCode === PowerPlantPVResidentialReport::COMMUNICATION_SECTION;
			$connection = '';
			if ($isCommunication) {
				foreach ($fields as $field) {
					if ($field->field_code === 'RES_CONNECTION') {
						$connection = (string) $field->value_text;
					}
				}
			}
			print '<div class="powerplantpv-report-fields"'.($isCommunication && $sectionEditable ? ' data-report-communication' : '').'>';
			foreach ($fields as $field) {
				if ($isCommunication && $field->field_code === 'RES_CONNECTION_HISTORY' && trim((string) $field->value_text) === '') {
					continue;
				}
				$condition = $isCommunication ? (PowerPlantPVResidentialReport::COMMUNICATION_CONDITIONS[$field->field_code] ?? null) : null;
				if ($condition !== null && !$sectionEditable && $condition !== $connection) {
					continue;
				}
				if ($residential && $sectionEditable && isset(PowerPlantPVResidentialReport::STAFF_FIELDS[$field->field_code])) {
					powerplantpvResidentialRenderStaffField($field, $residential, $form);
				} else {
					powerplantpvReportRenderField($field, $sectionEditable, $form, $condition);
				}
			}
			if ($residential && in_array($sectionCode, array('RES_SALES', 'RES_PROTECTION', 'RES_STRINGS'), true)) {
				powerplantpvResidentialRender($sectionCode, $residential, $sectionEditable, $form);
			}
			if ($byPart && (string) $section->section_code === $partCode.'_IDENT') {
				$technician = $partSignatures[$partCode]['technician'] ?? null;
				if (is_object($technician)) {
					print '<div class="powerplantpv-report-field"><strong>'.$langs->trans('PowerPlantPVPartSignedTechnician').'</strong> ';
					print dol_escape_htmltag((string) $technician->signer_name).'</div>';
				}
			}
			if ($section->section_code === 'PRODUCTION_READING' && empty($row['equipment'])) {
				$endDate = (int) ($intervention->datei ?? 0);
				$calculated = PowerPlantPVReportPresentation::selfConsumptionBetweenReadings($fields, $endDate);
				if ($calculated !== null) {
					print '<div class="powerplantpv-report-field"><strong>'.$langs->trans('PowerPlantPVSelfConsumptionBetweenReadings').'</strong> ';
					print dol_escape_htmltag(price($calculated, 0, $langs, 0, 0).' kWh').'</div>';
				}
			}
			print '</div>';
			print '</details>';
		}
		if (!empty($signableParts[$partCode])) {
			$label = $langs->trans('PowerPlantPVPartValidateAndSign', $partDefinitions[$partCode]['number']);
			print '<div class="center powerplantpv-report-actions" id="powerplantpv-part-actions-'.dol_escape_htmltag($partCode).'">';
			if ($editable) {
				print '<button type="submit" class="butAction" name="validate_part_submit" value="'.dol_escape_htmltag($partCode).'">'.dol_escape_htmltag($label).'</button>';
			} else {
				print powerplantpvReportPartSignButton($partCode, 'technician', $label);
			}
			print '</div>';
		}
		if (!empty($reopenableParts[$partCode])) {
			$reopenUrl = $_SERVER['PHP_SELF'].'?id='.(int) $id.'&action=reopen_part&reopen_part_code='.urlencode($partCode).'&token='.newToken();
			print '<div class="center powerplantpv-report-actions" id="powerplantpv-part-actions-'.dol_escape_htmltag($partCode).'">';
			print dolGetButtonAction($langs->trans('PowerPlantPVPartReopen'), '', 'default', $reopenUrl);
			print '</div>';
		}
		if ($partContentsTitle !== '' || (!$byPart && $hasPlant)) {
			print '</div>';
		}
	}

	if (!empty($signatureRows)) {
		powerplantpvReportRenderNativeCustomerSignatureBlock($signatureRows);
	}
}

/**
 * Return true for the customer signature section handled by native Dolibarr signature.
 *
 * @param	string	$sectionCode	Section code
 * @return	bool					True for native customer signature section
 */
function powerplantpvReportSectionIsNativeCustomerSignature($sectionCode)
{
	return (string) $sectionCode === 'CUSTOMER_SIGNATURE';
}

/**
 * Return true for legacy power plant summary sections now rendered from the frozen technical snapshot.
 *
 * @param	string	$sectionCode	Section code
 * @return	bool					True for legacy summary sections
 */
function powerplantpvReportSectionIsPowerplantTechnicalSummary($sectionCode)
{
	return in_array((string) $sectionCode, array('EQUIPMENT_SUMMARY', 'INSTALLATION_DESCRIPTION'), true);
}

/**
 * Render the frozen power plant technical summary.
 *
 * @param	mixed	$powerplant	Power plant preview row or report snapshot object
 * @return	void
 */
function powerplantpvReportRenderPowerplantTechnicalSummary($powerplant)
{
	global $langs;

	$resolved = powerplantpvReportResolvePowerplantTechnicalSummary($powerplant);
	$snapshot = $resolved['snapshot'];
	$plantId = powerplantpvReportPowerplantSourceId($powerplant);
	$title = $langs->transnoentities('PowerPlantPVReportPowerPlantSummary');
	print '<details id="powerplantpv-plant-summary-'.$plantId.'" class="powerplantpv-report-section powerplantpv-report-summary" data-state-key="plant-summary:'.$plantId.'" data-contents-label="'.dol_escape_htmltag($title).'" open>';
	print '<summary class="liste_titre"><span>'.dol_escape_htmltag($title).'</span></summary>';
	if (empty($snapshot['sections']) || !is_array($snapshot['sections'])) {
		print '<div class="powerplantpv-report-summary-content">';
		print '<table class="noborder centpercent"><tr class="oddeven"><td><span class="opacitymedium">'.$langs->trans('PowerPlantPVReportPowerPlantSummaryUnavailable').'</span></td></tr></table>';
		print '</div>';
		print '</details>';
		return;
	}

	print '<div class="powerplantpv-report-summary-content">';
	if (!empty($resolved['fallback_notice'])) {
		print '<div class="info opacitymedium powerplantpv-report-summary-fallback">'.dol_escape_htmltag($langs->trans('PowerPlantPVReportPowerPlantSummaryLiveFallback')).'</div>';
	}
	foreach ($snapshot['sections'] as $section) {
		if (!is_array($section)) {
			continue;
		}
		powerplantpvReportRenderSummarySection($section);
	}
	print '</div>';
	print '</details>';
}

/**
 * Resolve a power plant technical summary snapshot, with live fallback for old reports and previews.
 *
 * @param	mixed	$powerplant	Power plant preview row or report snapshot object
 * @return	array{snapshot:array<string,mixed>,fallback_notice:bool}	Resolved summary
 */
function powerplantpvReportResolvePowerplantTechnicalSummary($powerplant)
{
	global $db, $langs;

	$summary = new PowerPlantPVPowerPlantSummary($db);
	if (is_object($powerplant) && !empty($powerplant->technical_snapshot)) {
		$snapshot = $summary->decodeSnapshot((string) $powerplant->technical_snapshot);
		if (!$summary->isEmptySnapshot($snapshot)) {
			return array('snapshot' => $snapshot, 'fallback_notice' => false);
		}
	}

	$sourceId = powerplantpvReportPowerplantSourceId($powerplant);
	if ($sourceId <= 0) {
		return array('snapshot' => array(), 'fallback_notice' => false);
	}

	$snapshot = $summary->buildSnapshotById($sourceId, $langs);
	$isStoredReportRow = is_object($powerplant) && property_exists($powerplant, 'technical_snapshot');
	if ($isStoredReportRow) {
		dol_syslog(__METHOD__.' live fallback for report powerplant id='.(int) $powerplant->id.' source_powerplant_id='.$sourceId, LOG_WARNING);
	}

	return array('snapshot' => $snapshot, 'fallback_notice' => $isStoredReportRow);
}

/**
 * Render one summary section.
 *
 * @param	array<string,mixed>	$section	Snapshot section
 * @return	void
 */
function powerplantpvReportRenderSummarySection($section)
{
	global $langs;

	$titleKey = !empty($section['title_key']) ? (string) $section['title_key'] : '';
	$title = $titleKey !== '' ? $langs->trans($titleKey) : '';
	if ($title !== '') {
		print load_fiche_titre(dol_escape_htmltag($title), '', '');
	}

	$type = !empty($section['type']) ? (string) $section['type'] : 'key_value';
	if ($type === 'key_value') {
		powerplantpvReportRenderSummaryKeyValueRows(isset($section['rows']) && is_array($section['rows']) ? $section['rows'] : array());
		return;
	}
	if ($type === 'table') {
		powerplantpvReportRenderSummaryTable(isset($section['columns']) && is_array($section['columns']) ? $section['columns'] : array(), isset($section['rows']) && is_array($section['rows']) ? $section['rows'] : array());
		return;
	}
	if ($type === 'grouped_tables' && !empty($section['tables']) && is_array($section['tables'])) {
		foreach ($section['tables'] as $table) {
			if (!is_array($table) || empty($table['rows']) || !is_array($table['rows'])) {
				continue;
			}
			$tableTitleKey = !empty($table['title_key']) ? (string) $table['title_key'] : '';
			if ($tableTitleKey !== '') {
				print '<div class="titre marginbottomonlyshort">'.dol_escape_htmltag($langs->trans($tableTitleKey)).'</div>';
			}
			powerplantpvReportRenderSummaryTable(isset($table['columns']) && is_array($table['columns']) ? $table['columns'] : array(), $table['rows']);
		}
	}
}

/**
 * Render key/value summary rows.
 *
 * @param	array<int,array<string,mixed>>	$rows	Rows
 * @return	void
 */
function powerplantpvReportRenderSummaryKeyValueRows($rows)
{
	global $langs;

	if (empty($rows)) {
		return;
	}
	print '<div class="div-table-responsive-no-min">';
	print '<table class="border centpercent tableforfield powerplantpv-report-summary-table">';
	foreach ($rows as $row) {
		if (!is_array($row) || !array_key_exists('value', $row) || trim((string) $row['value']) === '') {
			continue;
		}
		$labelKey = !empty($row['label_key']) ? (string) $row['label_key'] : '';
		$label = $labelKey !== '' ? $langs->trans($labelKey) : '';
		print '<tr>';
		print '<td class="titlefield">'.dol_escape_htmltag($label).'</td>';
		print '<td>'.dol_htmlentitiesbr((string) $row['value']).'</td>';
		print '</tr>';
	}
	print '</table>';
	print '</div>';
}

/**
 * Render a generic summary table.
 *
 * @param	array<int,array<string,mixed>>	$columns	Columns
 * @param	array<int,array<string,mixed>>	$rows		Rows
 * @return	void
 */
function powerplantpvReportRenderSummaryTable($columns, $rows)
{
	global $langs;

	if (empty($columns) || empty($rows)) {
		return;
	}
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent powerplantpv-report-summary-table">';
	print '<tr class="liste_titre">';
	foreach ($columns as $column) {
		if (!is_array($column) || empty($column['key'])) {
			continue;
		}
		$labelKey = !empty($column['label_key']) ? (string) $column['label_key'] : (string) $column['key'];
		print '<th>'.dol_escape_htmltag($langs->trans($labelKey)).'</th>';
	}
	print '</tr>';
	foreach ($rows as $row) {
		if (!is_array($row)) {
			continue;
		}
		print '<tr class="oddeven">';
		foreach ($columns as $column) {
			if (!is_array($column) || empty($column['key'])) {
				continue;
			}
			$key = (string) $column['key'];
			$value = isset($row[$key]) ? (string) $row[$key] : '';
			if ($key === 'serial' && !empty($row['serial_powerplant_id'])) {
				$url = dol_buildpath('/powerplantpv/powerplant_composition.php', 1).'?id='.(int) $row['serial_powerplant_id'];
				print '<td><a href="'.dol_escape_htmltag($url).'">'.dol_escape_htmltag($langs->trans('PowerPlantPVConsultPowerplant')).'</a></td>';
			} else {
				print '<td>'.dol_htmlentitiesbr($value).'</td>';
			}
		}
		print '</tr>';
	}
	print '</table>';
	print '</div>';
}

/**
 * Render the native customer signature block at the end of the report page.
 *
 * @param	array<int,array<string,mixed>>	$signatureRows	Signature section rows
 * @return	void
 */
function powerplantpvReportRenderNativeCustomerSignatureBlock($signatureRows)
{
	global $intervention, $langs, $report, $reportFetch, $snapshotNeedsRebuild, $thirdPartySigned;

	$firstRow = reset($signatureRows);
	$section = is_array($firstRow) && isset($firstRow['section']) && is_object($firstRow['section']) ? $firstRow['section'] : null;
	$title = is_object($section) ? powerplantpvReportLocalizedObjectLabel($section, 'section_label') : $langs->trans('PowerPlantPVReportCustomerSignature');
	if ($title === '') {
		$title = $langs->trans('PowerPlantPVReportCustomerSignature');
	}
	$signatureUrl = powerplantpvReportGetInterventionOnlineSignatureUrl($intervention);
	$reportIsFinalized = ($reportFetch > 0 && (string) $report->status === PowerPlantPVReport::STATUS_SAVED);
	$signatureEnabled = ($reportIsFinalized && !$snapshotNeedsRebuild && $signatureUrl !== '' && !$thirdPartySigned);
	$message = $langs->trans('PowerPlantPVReportSignatureUnavailable');
	if ($thirdPartySigned) {
		$langs->load('commercial');
		$message = $langs->trans('SignedReceiver');
	} elseif (!$reportIsFinalized) {
		$message = $langs->trans('PowerPlantPVReportSignatureRequiresFinalizedReport');
	}

	print '<div id="powerplantpv-report-customer-signature" class="powerplantpv-native-signature-block" data-contents-label="'.dol_escape_htmltag($title).'" tabindex="-1">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="2">'.dol_escape_htmltag($title).'</td></tr>';
	print '<tr class="oddeven">';
	print '<td class="titlefield">'.$langs->trans('PowerPlantPVReportSignatures').'</td>';
	print '<td>';
	if ($signatureEnabled) {
		$nativeBlock = powerplantpvReportShowInterventionOnlineSignatureBlock($intervention);
		if ($nativeBlock !== '') {
			print $nativeBlock;
		} else {
			print dolGetButtonAction(
				$langs->trans('PowerPlantPVReportSignButton'),
				'',
				'default',
				$signatureUrl,
				'',
				true
			);
		}
	} else {
		print '<span class="opacitymedium">'.dol_escape_htmltag($message).'</span>';
	}
	print '</td>';
	print '</tr>';
	print '</table>';
	print '</div>';
}

/**
 * Return fields rendered in the report tab.
 *
 * @param	array<int,PowerPlantPVReportField>	$fields	Fields
 * @param	bool								$isPartsReport	Whether manual signer fields are obsolete
 * @return	array<int,PowerPlantPVReportField>			Renderable fields
 */
function powerplantpvReportFilterRenderableFields($fields, bool $isPartsReport = false)
{
	$filtered = array();
	foreach ($fields as $field) {
		if (!is_object($field) || (int) $field->visible_form <= 0 || (string) $field->field_type === 'signature'
			|| PowerPlantPVReportPresentation::isRetiredProductionField((string) $field->field_code)
			|| ($isPartsReport && PowerPlantPVReportParts::isManualSignerField((string) $field->field_code))) {
			continue;
		}
		$filtered[] = $field;
	}

	return $filtered;
}

/**
 * Render DC measure rows grouped by inverter and MPPT.
 *
 * @param	PowerPlantPVReportSection			$section	Section
 * @param	array<int,PowerPlantPVReportDcMeasure>	$measures	Measure rows
 * @param	bool								$editable	Editable flag
 * @param	Form								$form		Form helper
 * @return	void
 */
function powerplantpvReportRenderDcMeasures($section, $measures, $editable, $form)
{
	global $langs;

	$measures = PowerPlantPVReportPresentation::visibleMeasures($measures, (bool) getDolGlobalInt('POWERPLANTPV_HIDE_UNUSED_MPPT'));
	if (empty($measures)) {
		print '<div class="opacitymedium">'.$langs->trans('NoRecordFound').'</div>';
		return;
	}

	$grouped = PowerPlantPVReportPresentation::groupMeasures($measures);
	print '<div class="powerplantpv-dc-measures">';
	foreach ($grouped as $groupKey => $group) {
		print '<details class="powerplantpv-dc-inverter" data-state-key="inverter:'.dol_escape_htmltag($groupKey).'" open><summary class="liste_titre">';
		print '<span class="powerplantpv-identity-mobile">'.dol_escape_htmltag(PowerPlantPVReportPresentation::identity($group['identity'], false, $langs->trans('PowerPlantPVManualDcLine'))).'</span>';
		print '<span class="powerplantpv-identity-desktop">'.dol_escape_htmltag(PowerPlantPVReportPresentation::identity($group['identity'], true, $langs->trans('PowerPlantPVManualDcLine'))).'</span></summary>';
		foreach ($group['mppts'] as $mpptLabel => $rows) {
			print '<details class="powerplantpv-dc-mppt" data-state-key="mppt:'.dol_escape_htmltag($groupKey.':'.$mpptLabel).'" open><summary>'.dol_escape_htmltag($langs->trans('PowerPlantPVMPPT').' '.($mpptLabel !== '' ? powerplantpvReportDcDisplayIndex($mpptLabel) : $langs->trans('PowerPlantPVManual'))).'</summary>';
			print '<div class="powerplantpv-dc-measure-grid">';
			foreach ($rows as $measure) {
				powerplantpvReportRenderDcMeasureCard($measure, $editable, $form);
			}
			print '</div></details>';
		}
		print '</details>';
	}
	print '</div>';
}

/**
 * Check if a DC measure was added manually.
 *
 * @param	PowerPlantPVReportDcMeasure	$measure	Measure
 * @return	bool								True if manual
 */
function powerplantpvReportDcMeasureIsManual($measure)
{
	return strpos((string) $measure->stable_key, ':dc:manual:') !== false;
}

/**
 * Check if a DC measure input is exploited by the power plant.
 *
 * @param	PowerPlantPVReportDcMeasure	$measure	Measure
 * @return	bool								True if exploited
 */
function powerplantpvReportDcMeasureIsUsed($measure)
{
	return ((int) $measure->is_connected) > 0;
}

/**
 * Format a DC text value for read-only display.
 *
 * @param	PowerPlantPVReportDcMeasure	$measure	Measure
 * @param	mixed						$value		Value
 * @param	bool						$expected	Expected value flag
 * @return	string									Display value
 */
function powerplantpvReportDcDisplayText($measure, $value, $expected = true)
{
	global $langs;

	if (!powerplantpvReportDcMeasureIsUsed($measure)) {
		return $langs->trans('PowerPlantPVNotUsedByPowerPlant');
	}

	$value = trim((string) $value);
	if ($value === '' || $value === '-1') {
		return $langs->trans($expected ? 'PowerPlantPVDataNotFilled' : 'PowerPlantPVNotApplicableShort');
	}

	return $value;
}

/**
 * Format a DC index value for display.
 *
 * @param	mixed	$value	Value
 * @return	string			Display value
 */
function powerplantpvReportDcDisplayIndex($value)
{
	global $langs;

	$value = trim((string) $value);
	if ($value === '' || $value === '-1') {
		return $langs->trans('PowerPlantPVDataNotFilled');
	}

	return $value;
}

/**
 * Format a DC number value for read-only display.
 *
 * @param	PowerPlantPVReportDcMeasure	$measure	Measure
 * @param	mixed						$value		Value
 * @param	string						$unit		Unit
 * @param	bool						$expected	Expected value flag
 * @return	string									Display value
 */
function powerplantpvReportDcDisplayNumber($measure, $value, $unit, $expected = true)
{
	global $langs;

	if (!powerplantpvReportDcMeasureIsUsed($measure)) {
		return $langs->trans('PowerPlantPVNotUsedByPowerPlant');
	}
	if ($value === null || (string) $value === '' || (is_numeric($value) && (float) $value < 0)) {
		return $langs->trans($expected ? 'PowerPlantPVDataNotFilled' : 'PowerPlantPVNotApplicableShort');
	}

	return price($value).' '.$unit;
}

/**
 * Format a DC number value for edition.
 *
 * @param	mixed	$value	Value
 * @return	string			Input value
 */
function powerplantpvReportDcInputNumberValue($value)
{
	if ($value === null || (string) $value === '' || (is_numeric($value) && (float) $value < 0)) {
		return '';
	}

	return price($value);
}

/**
 * Format a DC tri-state boolean value for read-only display.
 *
 * @param	PowerPlantPVReportDcMeasure	$measure	Measure
 * @param	mixed						$value		Value
 * @return	string									Display value
 */
function powerplantpvReportDcDisplayBoolean($measure, $value)
{
	global $langs;

	if (!powerplantpvReportDcMeasureIsUsed($measure)) {
		return $langs->trans('PowerPlantPVNotUsedByPowerPlant');
	}

	$value = (string) $value;
	if ($value === '1') {
		return $langs->trans('Yes');
	}
	if ($value === '0') {
		return $langs->trans('No');
	}

	return $langs->trans('PowerPlantPVDataNotFilled');
}

/**
 * Format a DC insulation status for read-only display.
 *
 * @param	PowerPlantPVReportDcMeasure	$measure	Measure
 * @param	array<string,string>			$options	Status labels
 * @return	string									Display value
 */
function powerplantpvReportDcDisplayInsulationStatus($measure, $options)
{
	global $langs;

	if (!powerplantpvReportDcMeasureIsUsed($measure)) {
		return $langs->trans('PowerPlantPVNotUsedByPowerPlant');
	}

	$value = (string) $measure->insulation_status;
	if ($value === '' || $value === '-1') {
		return $langs->trans('PowerPlantPVDataNotFilled');
	}
	if ($value === 'not_applicable') {
		return $langs->trans('PowerPlantPVNotApplicableShort');
	}

	return isset($options[$value]) ? $options[$value] : $value;
}

/**
 * Render one DC measure card.
 *
 * @param	PowerPlantPVReportDcMeasure	$measure	Measure
 * @param	bool						$editable	Editable flag
 * @param	Form						$form		Form helper
 * @return	void
 */
function powerplantpvReportRenderDcMeasureCard($measure, $editable, $form)
{
	global $langs;

	$key = md5((string) $measure->stable_key);
	$name = 'dc_measures['.$key.']';
	$isManual = powerplantpvReportDcMeasureIsManual($measure);
	$isUsed = powerplantpvReportDcMeasureIsUsed($measure);
	$insulationOptions = array(
		'' => $langs->trans('PowerPlantPVDataNotFilled'),
		'valid' => $langs->trans('PowerPlantPVReportConformityValid'),
		'observation' => $langs->trans('PowerPlantPVReportConformityObservation'),
		'not_applicable' => $langs->trans('PowerPlantPVNotApplicableShort'),
	);

	print '<details class="powerplantpv-dc-card" data-card-key="'.$key.'" open>';
	print '<summary class="powerplantpv-dc-card-title"><span>';
	print dol_escape_htmltag($langs->trans('PowerPlantPVPVInput')).' '.dol_escape_htmltag(powerplantpvReportDcDisplayIndex($measure->pv_input_number));
	if ((string) $measure->string_ref !== '') {
		print ' - '.dol_escape_htmltag((string) $measure->string_ref);
	}
	print '</span>';
	if ($editable && ($isManual || $isUsed)) {
		$saveLabel = dol_escape_htmltag($langs->trans('PowerPlantPVSaveCollapse'));
		print '<button type="submit" class="button powerplantpv-save-collapse" name="save_draft_submit" value="'.$key.'" title="'.$saveLabel.'" aria-label="'.$saveLabel.'">'.img_picto('', 'fa-save').'</button>';
	}
	print '</summary><div class="powerplantpv-dc-card-body">';

	if (!$isManual && !$isUsed) {
		print '<div class="opacitymedium powerplantpv-dc-not-used">'.dol_escape_htmltag($langs->trans('PowerPlantPVNotUsedByPowerPlant')).'</div>';
		print '</div></details>';
		return;
	}

	$historicalResistance = array(
		'PowerPlantPVInsulationPositiveToGround' => $measure->insulation_positive_to_ground,
		'PowerPlantPVInsulationNegativeToGround' => $measure->insulation_negative_to_ground,
	);
	foreach ($historicalResistance as $label => $value) {
		if ($value !== null && $value !== '') {
			print '<div class="opacitymedium">'.dol_escape_htmltag($langs->trans($label).' : '.powerplantpvReportDcDisplayNumber($measure, $value, 'MOhm')).'</div>';
		}
	}

	if (!$editable) {
		print '<div class="powerplantpv-dc-readonly">';
		print '<span>'.dol_escape_htmltag($langs->trans('PowerPlantPVStringRef')).' : '.dol_escape_htmltag(powerplantpvReportDcDisplayText($measure, $measure->string_ref)).'</span>';
		print '<span>'.dol_escape_htmltag($langs->trans('PowerPlantPVPVInputConnected')).' : '.dol_escape_htmltag($isUsed ? $langs->trans('Yes') : $langs->trans('No')).'</span>';
		print '<span>'.dol_escape_htmltag($langs->trans('PowerPlantPVOpenCircuitVoltage')).' : '.dol_escape_htmltag(powerplantpvReportDcDisplayNumber($measure, $measure->open_circuit_voltage, 'V')).'</span>';
		print '<span>'.dol_escape_htmltag($langs->trans('PowerPlantPVPolarityChecked')).' : '.dol_escape_htmltag(powerplantpvReportDcDisplayBoolean($measure, $measure->polarity_checked)).'</span>';
		print '<span>'.dol_escape_htmltag($langs->trans('PowerPlantPVInsulationStatus')).' : '.dol_escape_htmltag(powerplantpvReportDcDisplayInsulationStatus($measure, $insulationOptions)).'</span>';
		print '<span>'.dol_escape_htmltag($langs->trans('PowerPlantPVPositiveToGroundVoltage')).' : '.dol_escape_htmltag(powerplantpvReportDcDisplayNumber($measure, $measure->positive_to_ground_voltage, 'V')).'</span>';
		print '<span>'.dol_escape_htmltag($langs->trans('PowerPlantPVNegativeToGroundVoltage')).' : '.dol_escape_htmltag(powerplantpvReportDcDisplayNumber($measure, $measure->negative_to_ground_voltage, 'V')).'</span>';
		print '<span>'.dol_htmlentitiesbr(powerplantpvReportDcDisplayText($measure, $measure->observation, false)).'</span>';
		print '</div></div></details>';
		return;
	}

	if ($measure instanceof PowerPlantPVReportDcMeasure && $measure->hasLegacyPoleVoltages()) {
		print '<div class="powerplantpv-dc-observation">';
		print $form->buttonsSaveCancel('', '', array(
			'name' => 'adopt_legacy_voltage_submit['.$key.']',
			'label_key' => 'PowerPlantPVAdoptLegacyVoltages',
		), true);
		print '<div class="opacitymedium">'.dol_escape_htmltag($langs->trans('PowerPlantPVAdoptLegacyVoltagesHelp')).'</div></div>';
	}
	print '<input type="hidden" name="'.$name.'[id]" value="'.((int) $measure->id).'">';
	print '<input type="hidden" name="'.$name.'[stable_key]" value="'.dol_escape_htmltag((string) $measure->stable_key).'">';
	if ($isManual) {
		print '<label>'.$langs->trans('PowerPlantPVInverter').'<input type="text" class="flat powerplantpv-report-control" name="'.$name.'[inverter_label]" value="'.dol_escape_htmltag((string) $measure->inverter_label).'"></label>';
		print '<label>'.$langs->trans('PowerPlantPVMPPT').'<input type="text" class="flat powerplantpv-report-control" name="'.$name.'[mppt_number]" value="'.dol_escape_htmltag((string) $measure->mppt_number).'"></label>';
		print '<label>'.$langs->trans('PowerPlantPVPVInput').'<input type="text" class="flat powerplantpv-report-control" name="'.$name.'[pv_input_number]" value="'.dol_escape_htmltag((string) $measure->pv_input_number).'"></label>';
		print '<span class="powerplantpv-dc-switch">'.$langs->trans('PowerPlantPVPVInputConnected');
		powerplantpvReportRenderBinarySwitch($form, $name.'[is_connected]', (string) (int) $measure->is_connected, '1', '0', $langs->transnoentities('PowerPlantPVPVInputConnected'));
		print '</span>';
	} else {
		print '<input type="hidden" name="'.$name.'[inverter_label]" value="'.dol_escape_htmltag((string) $measure->inverter_label).'">';
		print '<input type="hidden" name="'.$name.'[mppt_number]" value="'.dol_escape_htmltag((string) $measure->mppt_number).'">';
		print '<input type="hidden" name="'.$name.'[pv_input_number]" value="'.dol_escape_htmltag((string) $measure->pv_input_number).'">';
		print '<input type="hidden" name="'.$name.'[is_connected]" value="1">';
	}
	print '<label>'.$langs->trans('PowerPlantPVStringRef').'<input type="text" class="flat powerplantpv-report-control" name="'.$name.'[string_ref]" value="'.dol_escape_htmltag((string) $measure->string_ref).'"></label>';
	print '<label>'.$langs->trans('PowerPlantPVOpenCircuitVoltage').'<span class="powerplantpv-control-with-unit"><input type="text" class="flat powerplantpv-report-control right" name="'.$name.'[open_circuit_voltage]" value="'.dol_escape_htmltag(powerplantpvReportDcInputNumberValue($measure->open_circuit_voltage)).'"><span class="opacitymedium">V</span></span></label>';
	print '<span class="powerplantpv-dc-switch">'.$langs->trans('PowerPlantPVPolarityChecked');
	powerplantpvReportRenderBinarySwitch($form, $name.'[polarity_checked]', (string) (int) $measure->polarity_checked, '1', '0', $langs->transnoentities('PowerPlantPVPolarityChecked'), true);
	print '</span>';
	$insulationLabel = $langs->transnoentities('PowerPlantPVInsulationStatus');
	if (getDolGlobalInt('POWERPLANTPV_REPORT_COMPACT_CONFORMITY')) {
		print '<div>'.dol_escape_htmltag($insulationLabel);
		powerplantpvReportRenderConformityRadios($name.'[insulation_status]', (string) $measure->insulation_status, $insulationLabel, $insulationOptions);
		print '</div>';
	} else {
		print '<label>'.dol_escape_htmltag($insulationLabel).$form->selectarray($name.'[insulation_status]', $insulationOptions, (string) $measure->insulation_status, 1, 0, 0, '', 0, 0, 0, '', 'powerplantpv-report-control').'</label>';
	}
	print '<label>'.$langs->trans('PowerPlantPVPositiveToGroundVoltage').'<span class="powerplantpv-control-with-unit"><input type="text" class="flat powerplantpv-report-control right" name="'.$name.'[positive_to_ground_voltage]" value="'.dol_escape_htmltag(powerplantpvReportDcInputNumberValue($measure->positive_to_ground_voltage)).'"><span class="opacitymedium">V</span></span></label>';
	print '<label>'.$langs->trans('PowerPlantPVNegativeToGroundVoltage').'<span class="powerplantpv-control-with-unit"><input type="text" class="flat powerplantpv-report-control right" name="'.$name.'[negative_to_ground_voltage]" value="'.dol_escape_htmltag(powerplantpvReportDcInputNumberValue($measure->negative_to_ground_voltage)).'"><span class="opacitymedium">V</span></span></label>';
	print '<label class="powerplantpv-dc-observation">'.$langs->trans('PowerPlantPVReportObservation').'<textarea class="flat powerplantpv-report-control" rows="2" name="'.$name.'[observation]">'.dol_escape_htmltag((string) $measure->observation).'</textarea></label>';
	print '</div></details>';
}

/** @param array<string,string> $options Translated choice labels, indexed by stored value */
function powerplantpvReportRenderConformityRadios(string $name, string $value, string $label, array $options): void
{
	print '<div class="powerplantpv-conformity-radios" role="group" aria-label="'.dol_escape_htmltag($label).'">';
	foreach (array('valid' => 'C', 'observation' => 'NC', 'not_applicable' => 'SO') as $code => $caption) {
		$fullLabel = dol_escape_htmltag($options[$code]);
		print '<label title="'.$fullLabel.'"><input type="radio" name="'.dol_escape_htmltag($name).'" value="'.$code.'" aria-label="'.$fullLabel.'"'.($value === $code ? ' checked' : '').'> '.$caption.'</label>';
	}
	print '</div>';
}

/** Use Dolibarr's switch pictograms while keeping the existing form submission and fallback select. */
function powerplantpvReportRenderBinarySwitch(Form $form, string $name, string $value, string $on, string $off, string $label, bool $allowEmpty = false): void
{
	global $langs;

	$unanswered = $langs->transnoentities('PowerPlantPVDataNotFilled');
	$choices = array($off => $langs->transnoentities('No'), $on => $langs->transnoentities('Yes'));
	if ($allowEmpty) {
		$choices = array('-1' => $unanswered) + $choices;
		if ($value !== $on && $value !== $off) {
			$value = '-1';
		}
	}
	$escapedLabel = dol_escape_htmltag($label);
	print '<span data-report-binary data-on="'.dol_escape_htmltag($on).'" data-off="'.dol_escape_htmltag($off).'">';
	print $form->selectarray($name, $choices, $value, 0, 0, 0, 'aria-label="'.$escapedLabel.'"', 0, 0, 0, '', 'powerplantpv-report-control', 0);
	print '<button type="button" class="button" role="switch" aria-label="'.$escapedLabel.'" aria-checked="'.($value === $on ? 'true' : 'false').'" hidden>';
	print '<span data-switch-on aria-hidden="true">'.img_picto('', 'switch_on').'</span>';
	print '<span data-switch-off aria-hidden="true">'.img_picto('', 'switch_off').'</span> ';
	print '<span data-switch-label></span></button></span>';
}

/**
 * Render one field.
 *
 * @param	PowerPlantPVReportField	$field		Field
 * @param	bool					$editable	Editable flag
 * @param	Form					$form		Form helper
 * @return	void
 */
function powerplantpvReportRenderField($field, $editable, $form, ?string $condition = null)
{
	global $id, $langs;

	$fieldEditable = $editable && empty($field->readonly);
	$label = powerplantpvReportLocalizedObjectLabel($field, 'field_label');
	$name = 'report_values['.dol_escape_htmltag((string) $field->stable_key).']';
	$type = (string) $field->field_type;
	$value = powerplantpvReportFieldDisplayValue($field);
	if (PowerPlantPVReportParts::isHoursField((string) $field->field_code)) {
		$hours = PowerPlantPVReportParts::decodeHours((string) $value);
		foreach (array('start' => 'PowerPlantPVAutocontrolStartTime', 'end' => 'PowerPlantPVAutocontrolEndTime') as $key => $labelKey) {
			$inputId = 'powerplantpv-hours-'.md5((string) $field->stable_key).'-'.$key;
			print '<div class="powerplantpv-report-field powerplantpv-report-field-inline">';
			print '<label for="'.$inputId.'">'.$langs->trans($labelKey).'</label>';
			print '<div class="powerplantpv-report-input">';
			if ($fieldEditable) {
				print '<input type="time" id="'.$inputId.'" class="flat powerplantpv-report-control" name="'.$name.'['.$key.']" value="'.dol_escape_htmltag($hours[$key]).'">';
			} else {
				print '<div class="powerplantpv-report-readonly">'.dol_escape_htmltag($hours[$key]).'</div>';
			}
			if ($key === 'start' && $hours['legacy'] !== '') {
				print '<div class="opacitymedium">'.$langs->trans('PreviousValue').' '.dol_escape_htmltag($hours['legacy']).'</div>';
			}
			print '</div></div>';
		}
		return;
	}
	$allowsNotApplicable = PowerPlantPVReportPresentation::allowsNotApplicable((string) $field->field_code, $type);
	$fieldClass = 'powerplantpv-report-field';
	$fieldClass .= powerplantpvReportFieldCanInline($field) ? ' powerplantpv-report-field-inline' : ' powerplantpv-report-field-block';
	$inputClass = 'powerplantpv-report-input'.(powerplantpvReportFieldHasInlineUnit($field) ? ' powerplantpv-report-input-with-unit' : '');

	$progressRequired = $fieldEditable && !empty($field->is_required) && !in_array($type, array('computed', 'signature'), true) && !powerplantpvReportFieldIsPreviousReading($field);
	$progressFilled = !$progressRequired || !powerplantpvReportRequiredFieldIsEmpty($field);
	$communicationAttributes = $condition !== null ? ' data-connection-condition="'.dol_escape_htmltag($condition).'"' : '';
	if ($field->field_code === 'RES_CONNECTION') {
		$communicationAttributes = ' data-connection-choice';
	}
	print '<div class="'.$fieldClass.'" data-progress-type="'.dol_escape_htmltag($type).'" data-progress-required="'.($progressRequired ? '1' : '0').'" data-progress-filled="'.($progressFilled ? '1' : '0').'"'.$communicationAttributes.'>';
	print '<label>'.dol_escape_htmltag($label);
	if (!empty($field->is_required)) {
		print ' <span class="required">*</span>';
	} elseif ($fieldEditable && $field->field_code === 'RES_CONNECTION_WIFI') {
		print ' <span class="required" data-connection-required>*</span>';
	}
	print '</label>';
	if (!empty($field->help)) {
		print '<div class="opacitymedium powerplantpv-report-field-help">'.dol_escape_htmltag((string) $field->help).'</div>';
	}
	print '<div class="'.$inputClass.'">';
	if (!$fieldEditable) {
		$choiceLabel = PowerPlantPVReportPresentation::formatChoiceValue($field, $langs);
		if ($type === 'file') {
			powerplantpvReportRenderFiles($field, false);
		} elseif ($type === 'textarea') {
			print '<div class="powerplantpv-report-readonly">';
			powerplantpvReportPrintHtmlValue((string) $value);
			print '</div>';
		} elseif ($choiceLabel !== null) {
			if ($choiceLabel === '' && $allowsNotApplicable) {
				$choiceLabel = $langs->transnoentities('PowerPlantPVDataNotFilled');
			}
			print '<div class="powerplantpv-report-readonly">'.dol_escape_htmltag($choiceLabel).'</div>';
		} elseif ($value === '' && powerplantpvReportFieldIsPreviousReading($field)) {
			print '<div class="powerplantpv-report-readonly opacitymedium">'.$langs->trans('PowerPlantPVReportNoPreviousReading').'</div>';
		} else {
			print '<div class="powerplantpv-report-readonly">'.dol_htmlentitiesbr((string) $value).'</div>';
		}
		print '</div>';
		powerplantpvReportRenderIndexPhoto($field, false);
		print '</div>';
		return;
	}

	if ($type === 'textarea') {
		powerplantpvReportRenderTextarea($field, (string) $value);
	} elseif ($type === 'dynamic_table') {
		print '<textarea class="flat powerplantpv-report-control" name="'.$name.'" rows="4">'.dol_escape_htmltag((string) $value).'</textarea>';
	} elseif (powerplantpvReportIsNumericFieldType($type)) {
		print '<input type="text" class="flat powerplantpv-report-control right" name="'.$name.'" value="'.dol_escape_htmltag((string) $value).'">';
	} elseif ($type === 'date' || $type === 'datetime') {
		$hash = md5((string) $field->stable_key);
		$timestamp = is_int($field->value_date) ? $field->value_date : '';
		print '<input type="hidden" name="report_date_keys['.$hash.']" value="'.dol_escape_htmltag((string) $field->stable_key).'">';
		print '<div class="powerplantpv-report-date" data-date-format="'.dol_escape_htmltag($langs->transnoentitiesnoconv('FormatDateShortJavaInput')).'">';
		print $form->selectDate($timestamp, 'report_date_'.$hash, ($type === 'datetime' ? 1 : 0), ($type === 'datetime' ? 1 : 0), 1, 'powerplantpvreportform', 1, 1);
		print '</div>';
	} elseif (in_array($type, array('checkbox', 'yesno', 'boolean', 'select', 'conformity_so_valid_obs', 'status', 'multiselect'), true)) {
		// Select2 and Dolibarr both use the HTML id to target the open list.
		$hash = hash('sha256', (string) $field->stable_key);
		$selectName = 'report_choice_'.$hash;
		print '<input type="hidden" name="report_choice_keys['.$hash.']" value="'.dol_escape_htmltag((string) $field->stable_key).'">';
		$options = PowerPlantPVReportPresentation::choiceOptions($field, $langs);
		$controlClass = 'powerplantpv-report-control powerplantpv-report-choice';
		$binaryCodes = PowerPlantPVReportPresentation::binaryChoiceCodes($type, $options);
		if ($binaryCodes !== null) {
			powerplantpvReportRenderBinarySwitch($form, $selectName, (string) $value, $binaryCodes[0], $binaryCodes[1], $label, in_array($type, array('boolean', 'select'), true));
		} elseif (getDolGlobalInt('POWERPLANTPV_REPORT_COMPACT_CONFORMITY') && $type !== 'multiselect'
			&& count($options) === 3 && isset($options['valid'], $options['observation'], $options['not_applicable'])) {
			powerplantpvReportRenderConformityRadios($selectName, (string) $value, $label, $options);
		} elseif ($type === 'multiselect') {
			$selected = array_filter(explode("\n", (string) $value));
			print $form->multiselectarray($selectName, $options, $selected, 0, 0, $controlClass, 0, 0, '', '', '', 0);
		} else {
			$isBinary = in_array($type, array('checkbox', 'yesno'), true);
			$selected = $isBinary && !$allowsNotApplicable ? (int) $value : (string) $value;
			$showEmpty = $allowsNotApplicable || $isBinary ? 0 : 1;
			print $form->selectarray($selectName, $options, $selected, $showEmpty, 0, 0, '', 0, 0, 0, '', $controlClass, 0);
		}
	} elseif ($type === 'file') {
		powerplantpvReportRenderFiles($field, true);
		if (!empty($field->id)) {
			print '<div class="powerplantpv-file-upload">';
			$scopeFiles = powerplantpvIsResidentialEvidenceField($field->field_code);
			if ($scopeFiles && !PowerPlantPVCompatibility::isResidentialEvidenceAvailable()) {
				print '<span class="opacitymedium">'.$langs->trans('PVResidentialFileinfoRequired').'</span>';
			} else {
				$microProof = powerplantpvIsMicroProofField($field->field_code);
				$formats = $scopeFiles ? implode(',', array_map(static fn (string $extension): string => '.'.$extension, array_keys(powerplantpvResidentialEvidenceTypes($microProof)))) : '';
				if ($microProof) {
					print '<div class="opacitymedium">'.$langs->trans('PVResidentialMicroProofFormats', dol_escape_htmltag($formats)).'</div>';
				}
				print '<input type="file" aria-label="'.dol_escape_htmltag($langs->transnoentities('Upload')).'" name="report_file_'.$field->id.($scopeFiles ? '[]" multiple accept="'.$formats : '').'" class="flat powerplantpv-report-control">';
				print '<input type="submit" class="button" formaction="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.(int) $id.'&action=upload_file&field_id='.((int) $field->id).'" value="'.dol_escape_htmltag($langs->trans('Upload')).'">';
			}
			print '</div>';
		} else {
			print '<span class="opacitymedium">'.$langs->trans('PowerPlantPVReportFileAfterFirstSave').'</span>';
		}
	} elseif ($type === 'signature' || $type === 'computed') {
		print '<div class="powerplantpv-report-readonly">'.dol_htmlentitiesbr((string) $value).'</div>';
	} else {
		print '<input type="text" class="flat powerplantpv-report-control" name="'.$name.'" value="'.dol_escape_htmltag((string) $value).'">';
	}
	if (!empty($field->unit)) {
		print '<span class="opacitymedium powerplantpv-report-unit">'.dol_escape_htmltag((string) $field->unit).'</span>';
	}
	print '</div>';
	powerplantpvReportRenderIndexPhoto($field, true);
	print '</div>';
}

function powerplantpvReportIndexPhotoField(PowerPlantPVReportField $field): bool
{
	return empty($field->readonly) && (int) $field->visible_form > 0
		&& !PowerPlantPVReportPresentation::isRetiredProductionField((string) $field->field_code)
		&& powerplantpvIndexPhotoApplies((string) $field->field_code)
		&& powerplantpvReportIsNumericFieldType((string) $field->field_type);
}

/** @return list<string> Labels of uploads or required photos that need attention. */
function powerplantpvReportSaveIndexPhotos(PowerPlantPVReport $report, bool $finalize, bool $allowMissing, array &$warnings): array
{
	global $db, $langs;

	$required = $finalize && powerplantpvIndexPhotoRequired((int) $report->entity);
	$fields = (new PowerPlantPVReportField($db))->fetchAllByReport((int) $report->id, 'position', 'ASC');
	if (!is_array($fields)) {
		return array($langs->trans('ErrorRecordNotFound'));
	}
	$errors = array();
	foreach ($fields as $field) {
		if (!powerplantpvReportIndexPhotoField($field)) {
			continue;
		}
		$key = 'index_photo_'.hash('sha256', (string) $field->stable_key);
		$upload = $_FILES[$key] ?? array();
		$hasUpload = is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
		$path = powerplantpvReportIndexPhotoPath($field, $hasUpload);
		$label = powerplantpvReportLocalizedObjectLabel($field, 'field_label');
		if ($hasUpload && !powerplantpvIndexPhotoStore($upload, $path)) {
			$errors[] = $label.' — '.$langs->trans('PowerPlantPVIndexPhotoInvalid');
		} elseif ($required && is_numeric($field->value_number) && ($path === '' || !is_file($path))) {
			if ($allowMissing) {
				$warnings[] = $label;
			} else {
				$errors[] = $label.' — '.$langs->trans('PowerPlantPVIndexPhotoRequired');
			}
		}
	}
	return $errors;
}

function powerplantpvReportRenderIndexPhoto(PowerPlantPVReportField $field, bool $editable): void
{
	global $id, $langs;

	if (!powerplantpvReportIndexPhotoField($field)) {
		return;
	}
	$path = powerplantpvReportIndexPhotoPath($field);
	if ($path !== '' && is_file($path)) {
		$url = dol_buildpath('/powerplantpv/maintenance_intervention_report.php', 1).'?id='.(int) $id.'&action=view_index_photo&field_id='.(int) $field->id;
		print '<div><a href="'.dol_escape_htmltag($url).'">'.img_picto('', 'fa-camera').' '.$langs->trans('PowerPlantPVIndexPhoto').'</a></div>';
	}
	if ($editable && powerplantpvIndexPhotoAvailable()) {
		$key = 'index_photo_'.hash('sha256', (string) $field->stable_key);
		print '<div><label for="'.$key.'">'.$langs->trans('PowerPlantPVIndexPhoto').'</label>';
		print '<input type="file" id="'.$key.'" name="'.$key.'" accept="image/jpeg,image/png" class="flat"></div>';
	}
}

/**
 * Return true when the field unit must stay inline with the control.
 *
 * @param	PowerPlantPVReportField	$field	Field
 * @return	bool							True for compact input/select controls with unit
 */
function powerplantpvReportFieldHasInlineUnit($field)
{
	if (empty($field->unit)) {
		return false;
	}

	return !in_array((string) $field->field_type, array('textarea', 'dynamic_table', 'file', 'signature'), true);
}

/**
 * Render a textarea report field, with DolEditor when enabled.
 *
 * @param	PowerPlantPVReportField	$field	Field
 * @param	string					$value	Value
 * @return	void
 */
function powerplantpvReportRenderTextarea($field, $value)
{
	$hash = md5((string) $field->stable_key);
	$htmlName = 'report_textarea_value_'.$hash;
	print '<input type="hidden" name="report_textarea_keys['.$hash.']" value="'.dol_escape_htmltag((string) $field->stable_key).'">';
	if (isModEnabled('fckeditor')) {
		require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
		$doleditor = new DolEditor($htmlName, $value, '', 140, 'dolibarr_notes', '', false, true, true, 4, '100%');
		$doleditor->Create();
		return;
	}

	print '<textarea class="flat powerplantpv-report-control" name="'.$htmlName.'" rows="4">'.dol_escape_htmltag($value).'</textarea>';
}

/**
 * Print an HTML textarea value with native sanitizer when available.
 *
 * @param	string	$value	HTML value
 * @return	void
 */
function powerplantpvReportPrintHtmlValue($value)
{
	if (function_exists('dol_print_html')) {
		print dol_print_html($value, 1);
		return;
	}

	print dol_htmlentitiesbr($value);
}

/**
 * Return localized object label.
 *
 * @param	object	$object			Object
 * @param	string	$baseProperty	Base property
 * @return	string					Label
 */
function powerplantpvReportLocalizedObjectLabel($object, $baseProperty)
{
	global $langs;

	if ($baseProperty === 'field_label' && is_object($langs)) {
		$indexLabel = PowerPlantPVReportPresentation::inverterIndexLabel($object, $langs);
		if ($indexLabel !== null) { return $indexLabel; }
	}
	$label = isset($object->{$baseProperty}) ? (string) $object->{$baseProperty} : '';
	$englishProperty = $baseProperty.'_en';
	if (is_object($langs) && $langs->defaultlang == 'en_US' && !empty($object->{$englishProperty})) {
		$label = (string) $object->{$englishProperty};
	}

	return $label;
}

/**
 * Return source power plant id from a preview row or snapshot object.
 *
 * @param	mixed	$powerplant	Power plant row
 * @return	int					Power plant id
 */
function powerplantpvReportPowerplantSourceId($powerplant)
{
	if (is_array($powerplant)) {
		return !empty($powerplant['id']) ? (int) $powerplant['id'] : 0;
	}
	if (is_object($powerplant)) {
		if (!empty($powerplant->fk_powerplant)) {
			return (int) $powerplant->fk_powerplant;
		}
		if (!empty($powerplant->id)) {
			return (int) $powerplant->id;
		}
	}

	return 0;
}

/**
 * Return a readable power plant label from a preview row or snapshot object.
 *
 * @param	mixed	$powerplant	Power plant row
 * @return	string				Label
 */
function powerplantpvReportPowerplantLabel($powerplant)
{
	if (is_array($powerplant)) {
		$ref = !empty($powerplant['ref']) ? (string) $powerplant['ref'] : '';
		$label = !empty($powerplant['label']) ? (string) $powerplant['label'] : '';
		return trim($ref.($label !== '' ? ' - '.$label : ''));
	}
	if (is_object($powerplant)) {
		$ref = !empty($powerplant->powerplant_ref) ? (string) $powerplant->powerplant_ref : (!empty($powerplant->ref) ? (string) $powerplant->ref : '');
		$label = !empty($powerplant->powerplant_label) ? (string) $powerplant->powerplant_label : (!empty($powerplant->label) ? (string) $powerplant->label : '');
		return trim($ref.($label !== '' ? ' - '.$label : ''));
	}

	return '';
}

/**
 * Return a readable equipment label from a preview row or snapshot object.
 *
 * @param	mixed	$equipment	Equipment row
 * @return	string				Label
 */
function powerplantpvReportEquipmentLabel($equipment)
{
	if (is_array($equipment)) {
		$ref = !empty($equipment['equipment_ref']) ? (string) $equipment['equipment_ref'] : (!empty($equipment['product_ref']) ? (string) $equipment['product_ref'] : '');
		$label = !empty($equipment['equipment_label']) ? (string) $equipment['equipment_label'] : (!empty($equipment['product_label']) ? (string) $equipment['product_label'] : '');
		$serial = !empty($equipment['serial_number']) ? (string) $equipment['serial_number'] : '';
	} elseif (is_object($equipment)) {
		$ref = !empty($equipment->equipment_ref) ? (string) $equipment->equipment_ref : (!empty($equipment->product_ref) ? (string) $equipment->product_ref : '');
		$label = !empty($equipment->equipment_label) ? (string) $equipment->equipment_label : (!empty($equipment->product_label) ? (string) $equipment->product_label : '');
		$serial = !empty($equipment->serial_number) ? (string) $equipment->serial_number : '';
	} else {
		return '';
	}

	$output = trim($ref.($label !== '' ? ' - '.$label : ''));
	if ($serial !== '' && (is_array($equipment) ? ($equipment['equipment_type'] ?? '') : ($equipment->equipment_type ?? '')) !== 'PANEL') {
		$output .= ($output !== '' ? ' / ' : '').$serial;
	}

	return $output;
}

/**
 * Return display value for a field.
 *
 * @param	PowerPlantPVReportField	$field	Field
 * @return	string							Value
 */
function powerplantpvReportFieldDisplayValue($field)
{
	if (powerplantpvReportIsNumericFieldType((string) $field->field_type)) {
		return $field->value_number !== null ? price($field->value_number) : '';
	}
	if ((string) $field->field_type === 'date' || (string) $field->field_type === 'datetime') {
		return is_int($field->value_date) ? dol_print_date($field->value_date, ((string) $field->field_type === 'datetime' ? 'dayhour' : 'day')) : '';
	}
	if (PowerPlantPVReportPresentation::allowsNotApplicable((string) $field->field_code, (string) $field->field_type)) {
		return (string) $field->value_text;
	}
	if ((string) $field->field_type === 'checkbox' || (string) $field->field_type === 'yesno') {
		return !empty($field->value_text) ? '1' : '0';
	}

	return (string) $field->value_text;
}

/**
 * Return true for report fields showing previous production/consumption readings.
 *
 * @param	PowerPlantPVReportField	$field	Field
 * @return	bool							True for N-1 reading fields
 */
function powerplantpvReportFieldIsPreviousReading($field)
{
	return substr((string) $field->field_code, -10) === '_N_MINUS_1';
}

/**
 * Return true when a field can keep label, control and unit on one line.
 *
 * @param	PowerPlantPVReportField	$field	Field
 * @return	bool							True for compact fields
 */
function powerplantpvReportFieldCanInline($field)
{
	return !in_array((string) $field->field_type, array('textarea', 'dynamic_table', 'file', 'signature'), true);
}

/**
 * Return true for report field types stored in value_number.
 *
 * @param	string	$fieldType	Field type
 * @return	bool				True for numeric field types
 */
function powerplantpvReportIsNumericFieldType($fieldType)
{
	return in_array((string) $fieldType, array('number', 'double', 'real', 'integer', 'price'), true);
}

/**
 * Render files linked to a file field.
 *
 * @param	PowerPlantPVReportField	$field		Field
 * @param	bool					$editable	Editable flag
 * @return	void
 */
function powerplantpvReportRenderFiles($field, $editable)
{
	global $id, $langs;

	$files = isset($field->files) && is_array($field->files) ? $field->files : array();
	if (empty($files)) {
		print '<div class="opacitymedium">'.$langs->trans('NoRecordFound').'</div>';
		return;
	}
	print '<ul class="powerplantpv-report-files">';
	foreach ($files as $file) {
		$url = powerplantpvIsResidentialEvidenceField($field->field_code)
			? $_SERVER['PHP_SELF'].'?id='.(int) $id.'&action=download_scope_file&file_id='.(int) $file->id
			: DOL_URL_ROOT.'/document.php?modulepart=powerplantpv&file='.urlencode((string) $file->filepath);
		print '<li>';
		print '<a href="'.dol_escape_htmltag($url).'">'.dol_escape_htmltag((string) $file->filename).'</a>';
		if ($editable) {
			$deleteUrl = $_SERVER['PHP_SELF'].'?id='.(int) $id.'&action=delete_file&file_id='.(int) $file->id;
			$deleteLabel = $langs->transnoentities('Delete');
			print ' <button type="submit" class="powerplantpv-report-file-delete" formaction="'.dol_escape_htmltag($deleteUrl).'" aria-label="'.dol_escape_htmltag($deleteLabel).'">';
			print img_delete($deleteLabel).'</button>';
		}
		print '</li>';
	}
	print '</ul>';
}

/**
 * Return base output directory for report files.
 *
 * @param	PowerPlantPVReport	$report			Report
 * @param	Fichinter			$intervention	Intervention
 * @return	string								Base directory
 */
function powerplantpvReportOutputDir($report, $intervention)
{
	global $conf;

	$entity = (int) $report->entity;
	if ($entity <= 0 || $entity !== (int) $intervention->entity || (int) $report->fk_fichinter !== (int) $intervention->id
		|| empty($conf->powerplantpv->multidir_output[$entity])) {
		return '';
	}
	$base = $conf->powerplantpv->multidir_output[$entity];
	$ref = !empty($intervention->ref) ? dol_sanitizeFileName($intervention->ref) : 'intervention_'.((int) $report->fk_fichinter);

	return $base.'/report/'.$ref;
}

/**
 * Upload one file for a file field.
 *
 * @param	PowerPlantPVReport	$report			Report
 * @param	Fichinter			$intervention	Intervention
 * @param	int					$fieldId		Field id
 * @param	User				$user			User
 * @return	int									>0 if OK, <0 on error
 */
function powerplantpvReportUploadFile($report, $intervention, $fieldId, $user)
{
	global $db;

	$field = new PowerPlantPVReportField($db);
	if ($field->fetch((int) $fieldId) <= 0 || (int) $field->fk_report !== (int) $report->id || (string) $field->field_type !== 'file') {
		return -1;
	}
	$fileKey = 'report_file_'.$fieldId;
	if (powerplantpvIsResidentialEvidenceField($field->field_code)) {
		$batch = $_FILES[$fileKey] ?? null;
		return is_array($batch) ? powerplantpvResidentialUpload($report, $intervention, $field, $batch, $user) : -1;
	}
	if (empty($_FILES[$fileKey]) || !is_array($_FILES[$fileKey]) || !empty($_FILES[$fileKey]['error'])) {
		return -1;
	}
	$originalName = dol_sanitizeFileName(basename(str_replace('\\', '/', (string) $_FILES[$fileKey]['name'])));
	if ($originalName === '') {
		return -1;
	}
	$relativeDir = 'report/'.dol_sanitizeFileName((string) $intervention->ref).'/field_'.((int) $fieldId);
	$reportDir = powerplantpvReportOutputDir($report, $intervention);
	if ($reportDir === '') {
		return -1;
	}
	$uploadDir = $reportDir.'/field_'.((int) $fieldId);
	if (is_link($reportDir) || is_link($uploadDir) || dol_mkdir($uploadDir) < 0) {
		return -1;
	}
	$dest = $uploadDir.'/'.$originalName;
	if (dol_move_uploaded_file((string) $_FILES[$fileKey]['tmp_name'], $dest, 0) <= 0) {
		return -1;
	}

	$file = new PowerPlantPVReportFile($db);
	$file->entity = (int) $report->entity;
	$file->fk_report = (int) $report->id;
	$file->fk_report_field = (int) $field->id;
	$file->filename = $originalName;
	$file->filepath = $relativeDir.'/'.$originalName;
	$file->filemime = isset($_FILES[$fileKey]['type']) ? dol_string_nohtmltag((string) $_FILES[$fileKey]['type']) : '';
	$file->filesize = filesize($dest);
	$file->checksum = function_exists('hash_file') ? hash_file('sha256', $dest) : '';
	$file->date_upload = dol_now();
	$file->fk_user_upload = (int) $user->id;
	$file->position = 0;

	if ($file->create($user, 0) <= 0) {
		dol_delete_file($dest, 1);
		return -1;
	}
	if (PowerPlantPVReportParts::isPartsReport($report)
		&& (new PowerPlantPVReportEvidence($db))->attach($intervention, $report, $file) === '') {
		$file->delete($user, 0);
		dol_delete_file($dest, 1);
		return -1;
	}

	return 1;
}

/**
 * Delete one file metadata and physical file.
 *
 * @param	PowerPlantPVReport	$report			Report
 * @param	Fichinter			$intervention	Intervention
 * @param	int					$fileId			File id
 * @return	int									>0 if OK, <0 on error
 */
function powerplantpvReportDeleteFile($report, $intervention, $fileId)
{
	global $db;

	$file = new PowerPlantPVReportFile($db);
	if ($file->fetch((int) $fileId) <= 0 || (int) $file->fk_report !== (int) $report->id) {
		return -1;
	}
	if ((int) $file->entity !== (int) $report->entity || (int) $intervention->entity !== (int) $report->entity
		|| (int) $intervention->id !== (int) $report->fk_fichinter) {
		return -1;
	}
	$field = new PowerPlantPVReportField($db);
	if ($field->fetch((int) $file->fk_report_field) <= 0 || (int) $field->fk_report !== (int) $report->id) {
		return -1;
	}
	if (powerplantpvIsResidentialEvidenceField($field->field_code)) {
		if ($db->begin() <= 0) {
			return -1;
		}
		$physical = $report->lockUnsignedForEdit(true) ? powerplantpvResidentialFilePath($report, $intervention, $file) : '';
		$pending = $physical === '' ? '' : $physical.'.removed-'.bin2hex(random_bytes(8));
		$moved = $pending !== '' && rename($physical, $pending);
		if (!$moved || $file->delete($GLOBALS['user'], 0) <= 0 || $db->commit() <= 0) {
			$db->rollback();
			if ($moved && !rename($pending, $physical)) {
				dol_syslog('Residential evidence restoration failed for file '.((int) $fileId), LOG_ERR);
			}
			return -1;
		}
		if (dol_delete_file($pending, 1) <= 0) {
			dol_syslog('Residential evidence cleanup failed for file '.((int) $fileId), LOG_ERR);
		}
		if (powerplantpvIsMicroProofField($field->field_code) && $file->filemime === 'application/pdf') {
			dol_delete_file($physical.'.preview.png', 1, 1, 1, null, false, 0);
		}
		return 1;
	}
	$physical = PowerPlantPVReportEvidence::sourcePath($report, $file);
	if (PowerPlantPVReportParts::isPartsReport($report)) {
		$attachment = PowerPlantPVReportEvidence::attachmentPath($intervention, (int) $file->id, (string) $file->filename);
		if ($attachment !== '' && is_file($attachment) && realpath($attachment) === $attachment
			&& dol_delete_file($attachment, 1, 0, 0, $intervention) <= 0) {
			return -1;
		}
	}
	if ($physical !== '' && dol_delete_file($physical, 1) <= 0) {
		return -1;
	}

	return $file->delete($GLOBALS['user'], 0);
}

/**
 * Translated label of a self-inspection part, or of the final validation.
 *
 * @param	string	$partCode	Part code
 * @return	string				Label
 */
function powerplantpvReportPartLabel($partCode)
{
	global $langs;

	if ((string) $partCode === PowerPlantPVReportParts::FINAL_CODE) {
		return $langs->trans('PowerPlantPVAutocontrolFinalValidation');
	}
	$definitions = PowerPlantPVReportParts::definitions();
	if (!isset($definitions[$partCode])) {
		return (string) $partCode;
	}

	return $langs->trans('PowerPlantPVAutocontrolPartNumber', $definitions[$partCode]['number']).' · '.$langs->trans($definitions[$partCode]['label']);
}

/**
 * Snapshot section ids of one part. The final validation covers every section.
 *
 * @param	array<string,mixed>	$tree		Report tree
 * @param	string				$partCode	Part code
 * @return	array<int,bool>					Section id => true
 */
function powerplantpvReportPartSectionIds($tree, $partCode)
{
	$ids = array();
	foreach ((isset($tree['sections']) && is_array($tree['sections']) ? $tree['sections'] : array()) as $row) {
		if (!is_array($row) || empty($row['section']) || !is_object($row['section'])) {
			continue;
		}
		$sectionPart = PowerPlantPVReportParts::partCodeForSection((string) $row['section']->section_code);
		if ((string) $partCode === PowerPlantPVReportParts::FINAL_CODE || $sectionPart === (string) $partCode) {
			$ids[(int) $row['section']->id] = true;
		}
	}

	return $ids;
}

/**
 * Return true when a file upload or deletion targets a field of a signed part.
 *
 * @param	int				$fieldId				Uploaded field id, 0 for a deletion
 * @param	int				$fileId					Deleted file id, 0 for an upload
 * @param	array<int,bool>	$lockedPartSectionIds	Locked section ids
 * @return	bool
 */
function powerplantpvReportFileActionTargetsLockedPart($fieldId, $fileId, $lockedPartSectionIds)
{
	global $db;

	if ($fileId > 0) {
		$file = new PowerPlantPVReportFile($db);
		if ($file->fetch((int) $fileId) <= 0) {
			return true;
		}
		$fieldId = (int) $file->fk_report_field;
	}
	$field = new PowerPlantPVReportField($db);
	if ($fieldId <= 0 || $field->fetch((int) $fieldId) <= 0) {
		return true;
	}

	return !empty($lockedPartSectionIds[(int) $field->fk_report_section]);
}

/**
 * Render the signature of each self-inspection part, then the final validation of the complete report.
 * A part shows who signed it; the final validation keeps its three signers.
 *
 * @param	bool									$snapshotNeedsRebuild	True when the snapshot must be rebuilt first
 * @param	array<string,array<string,stdClass>>	$partSignatures			Part signatures
 * @param	array<string,string>					$partSigning			Part and role being signed, empty otherwise
 * @return	void
 */
function powerplantpvReportRenderPartSignatures($snapshotNeedsRebuild, $partSignatures, $partSigning)
{
	global $id, $langs, $report, $intervention, $user, $partsHelper, $locked, $signatureScript;

	$canDraw = !$locked && !$snapshotNeedsRebuild && $signatureScript !== '';
	$technicalSignatureEnabled = $report->technicalSignatureEnabled();
	$parts = array_keys(PowerPlantPVReportParts::definitions());
	$parts[] = PowerPlantPVReportParts::FINAL_CODE;
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="3">'.$langs->trans('PowerPlantPVPartSignatures').'</td></tr>';
	foreach ($parts as $partCode) {
		$isFinal = ($partCode === PowerPlantPVReportParts::FINAL_CODE);
		$roles = PowerPlantPVReportParts::roles($partCode, $technicalSignatureEnabled);
		$signable = array();
		foreach (array_keys($roles) as $role) {
			if ($canDraw && $partsHelper->canSign($report, $intervention, $user, $partCode, $role, $partSignatures)) {
				$signable[] = $role;
			}
		}
		if ($isFinal) {
			print '<tr class="liste_titre_filter" id="powerplantpv-part-signature-'.$partCode.'"><td colspan="3"><strong>'.dol_escape_htmltag(powerplantpvReportPartLabel($partCode)).'</strong>';
			print ' <span class="opacitymedium">'.$langs->trans('PowerPlantPVAutocontrolFinalValidationHelp').'</span></td></tr>';
			foreach ($roles as $role => $labelKey) {
				$signature = $partSignatures[$partCode][$role] ?? null;
				if ($role === 'works_manager' && !$technicalSignatureEnabled) {
					$labelKey = 'PowerPlantPVFinalSignerTechnical';
				}
				print '<tr class="oddeven"><td class="titlefieldmiddle">'.$langs->trans($labelKey).'</td><td>';
				if (is_object($signature)) {
					print dol_escape_htmltag((string) $signature->signer_name).' <span class="opacitymedium">'.dol_print_date($signature->date_sign, 'dayhour').'</span>';
				} elseif (PowerPlantPVReportParts::unsignedParts($partSignatures)) {
					print '<span class="opacitymedium">'.$langs->trans('PowerPlantPVFinalWaitingParts').'</span>';
				} else {
					print '<span class="opacitymedium">'.$langs->trans('PowerPlantPVAutocontrolNotSigned').'</span>';
				}
				print '</td><td class="right">';
				if (in_array($role, $signable, true)) {
					print powerplantpvReportPartSignButton($partCode, $role, $langs->trans('PowerPlantPVPartSignButton'));
				}
				print '</td></tr>';
				if (($partSigning['part'] ?? '') === $partCode && ($partSigning['role'] ?? '') === $role) {
					print '<tr class="oddeven"><td colspan="3">';
					powerplantpvReportRenderPartSignatureForm($partCode, $role);
					print '</td></tr>';
				}
			}
			continue;
		}
		// A part only shows who signed it.
		$signers = array();
		foreach ($roles as $role => $labelKey) {
			$signature = $partSignatures[$partCode][$role] ?? null;
			if (is_object($signature)) {
				$signers[] = dol_escape_htmltag((string) $signature->signer_name).' <span class="opacitymedium">'.dol_print_date($signature->date_sign, 'dayhour').'</span>';
			}
		}
		print '<tr class="oddeven" id="powerplantpv-part-signature-'.$partCode.'"><td class="titlefieldmiddle">'.dol_escape_htmltag(powerplantpvReportPartLabel($partCode)).'</td><td>';
		print '<span class="opacitymedium">'.$langs->trans('PowerPlantPVAutocontrolPartSignature').' :</span> ';
		print $signers ? implode(', ', $signers) : '<span class="opacitymedium">'.$langs->trans('PowerPlantPVAutocontrolNotSigned').'</span>';
		print '</td><td></td></tr>';
	}
	print '</table>';
	print '</div>';
}

/**
 * Action button opening the signature pad of a part.
 *
 * @param	string	$partCode	Part code
 * @param	string	$role		Signer role
 * @param	string	$label		Button label
 * @return	string				HTML
 */
function powerplantpvReportPartSignButton($partCode, $role, $label)
{
	return dolGetButtonAction($label, '', 'default', powerplantpvReportPartSignatureUrl($partCode, $role));
}

/**
 * Address of the native signature pad for a specific role and part.
 */
function powerplantpvReportPartSignatureUrl(string $partCode, string $role): string
{
	global $id;

	return $_SERVER['PHP_SELF'].'?id='.(int) $id.'&action=prepare_part_signature&part_code='.urlencode($partCode).'&part_role='.urlencode($role).'&token='.newToken().'#powerplantpv-part-signature-'.$partCode;
}

/**
 * Signature pad of the part being signed, drawn with the native Dolibarr jSignature plugin.
 *
 * @param	string	$partCode	Part code of the row
 * @param	string	$role		Signer role of the row
 * @param	string	$error		Translated error from a previous signature attempt
 * @return	void
 */
function powerplantpvReportRenderPartSignatureForm($partCode, $role, $error = '')
{
	global $id, $langs;

	$consent = $partCode === PowerPlantPVReportParts::FINAL_CODE ? 'PowerPlantPVFinalSignConsent' : 'PowerPlantPVPartSignConsent';
	if ($role === 'technical') {
		$consent = 'PowerPlantPVTechnicalSignConsent';
	}
	print '<form method="POST" id="powerplantpv-signature-form" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
	if ($error !== '') {
		print '<p class="error" role="alert">'.dol_escape_htmltag(html_entity_decode($error, ENT_QUOTES, 'UTF-8')).'</p>';
	}
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="id" value="'.(int) $id.'">';
	print '<input type="hidden" name="action" value="sign_part">';
	print '<input type="hidden" name="part_code" value="'.dol_escape_htmltag($partCode).'">';
	print '<input type="hidden" name="part_role" value="'.dol_escape_htmltag($role).'">';
	print '<input type="hidden" name="signature_image" value="">';
	print '<div id="powerplantpv-signature-pad"></div>';
	print '<button type="button" class="button buttonreset" id="powerplantpv-signature-clear">'.$langs->trans('ClearSignature').'</button>';
	print '<p><label><input type="checkbox" name="signature_consent" value="1" required> '.$langs->trans($consent).'</label></p>';
	print '<input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('PowerPlantPVPartSignButton')).'">';
	print '</form>';
}
