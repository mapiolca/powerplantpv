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
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_maintenance.lib.php');
dol_include_once('/powerplantpv/class/powerplantpvreport.class.php');
dol_include_once('/powerplantpv/class/powerplantpvreportbuilder.class.php');
dol_include_once('/powerplantpv/class/powerplantpvreportfield.class.php');
dol_include_once('/powerplantpv/class/powerplantpvreportfile.class.php');
dol_include_once('/powerplantpv/class/powerplantpvreportdcmeasure.class.php');
dol_include_once('/powerplantpv/class/powerplantpvpowerplantsummary.class.php');
dol_include_once('/fichinter/class/fichinter.class.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/fichinter.lib.php';
if (isModEnabled('project')) {
	require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
}

$langs->loadLangs(array('powerplantpv@powerplantpv', 'interventions', 'companies', 'contracts', 'other'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$token = GETPOST('token', 'alphanohtml');
$fieldId = GETPOSTINT('field_id');
$fileId = GETPOSTINT('file_id');
$dcSectionId = GETPOSTINT('add_dc_section_id');
$reportRestoreScrollY = GETPOSTINT('report_scroll_y');
$manualServiceIds = powerplantpvSanitizeIdArray(GETPOST('manual_services', 'array:int'));

if ($action === '') {
	if (GETPOSTISSET('save_draft_submit')) {
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
if ($id <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}

$intervention = new Fichinter($db);
if ($intervention->fetch($id) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
if (method_exists($intervention, 'fetch_optionals')) {
	$intervention->fetch_optionals();
}
if (method_exists($intervention, 'fetch_thirdparty')) {
	$intervention->fetch_thirdparty();
}

$permissiontoread = powerplantpvUserHasRightPath($user, array('ficheinter', 'lire')) && powerplantpvUserHasMaintenanceRight($user, 'read');
$permissiontowrite = powerplantpvUserHasMaintenanceRight($user, 'report') || powerplantpvUserHasMaintenanceRight($user, 'write');
$usercancreateintervention = $user->hasRight('ficheinter', 'creer');
if (!$permissiontoread) {
	accessforbidden();
}

$locked = powerplantpvReportIsInterventionLocked($intervention);
$caneditreport = $permissiontowrite && !$locked;

$builder = new PowerPlantPVReportBuilder($db);
$report = new PowerPlantPVReport($db);
$reportFetch = $report->fetchByIntervention($id);
if ($reportFetch < 0) {
	setEventMessages($report->error, $report->errors, 'errors');
}

$sensitiveActions = array('save_draft', 'save', 'recalculate', 'upload_file', 'delete_file', 'add_dc_measure_line');
if (in_array($action, $sensitiveActions, true)) {
	if (!$caneditreport) {
		accessforbidden();
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

if (($action === 'save_draft' || $action === 'save') && $caneditreport) {
	$status = ($action === 'save') ? PowerPlantPVReport::STATUS_SAVED : PowerPlantPVReport::STATUS_DRAFT;
	$saveerror = 0;
	if ($reportFetch <= 0) {
		$createdReport = $builder->createSnapshot($intervention, $user, $manualServiceIds, $status);
		if ($createdReport instanceof PowerPlantPVReport) {
			$report = $createdReport;
			$reportFetch = 1;
		} else {
			$saveerror++;
			setEventMessages($builder->error, $builder->errors, 'errors');
		}
	}
	if (!$saveerror && $reportFetch > 0 && powerplantpvReportSnapshotNeedsRebuild($builder, (int) $report->id)) {
		$result = $builder->recalculateSnapshot($report, $intervention, $user, $manualServiceIds, $status);
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
		$result = $builder->saveValues((int) $report->id, $values, $dateValues, $user, $status);
		if ($result >= 0) {
			$result = $builder->saveDcMeasureValues((int) $report->id, $dcValues, $user);
		}
		$interventionValidationResult = 0;
		if ($result >= 0 && (string) $status === PowerPlantPVReport::STATUS_SAVED) {
			$interventionValidationResult = powerplantpvReportValidateInterventionIfNeeded($intervention, $user);
			if ($interventionValidationResult < 0) {
				$result = -1;
			}
		}
		if ($result < 0) {
			if ($interventionValidationResult < 0) {
				powerplantpvReportSetInterventionValidationError($intervention);
			} else {
				setEventMessages($builder->error, $builder->errors, 'errors');
			}
		} else {
			if ($interventionValidationResult > 0) {
				setEventMessages($langs->trans('PowerPlantPVReportSavedInterventionValidated'), null, 'mesgs');
			} else {
				setEventMessages($langs->trans($status === PowerPlantPVReport::STATUS_SAVED ? 'PowerPlantPVReportSaved' : 'PowerPlantPVReportDraftSaved'), null, 'mesgs');
			}
			$redirectUrl = $_SERVER['PHP_SELF'].'?id='.(int) $id;
			if ($action === 'save_draft') {
				$scrollY = GETPOSTINT('powerplantpv_scroll_y');
				if ($scrollY > 0) {
					$redirectUrl .= '&report_scroll_y='.(int) $scrollY;
				}
			}
			header('Location: '.$redirectUrl);
			exit;
		}
	}
}

if ($action === 'add_dc_measure_line' && $caneditreport && $reportFetch > 0) {
	$values = powerplantpvReportGetSubmittedValues();
	$dateValues = powerplantpvReportGetSubmittedDateValues();
	$dcValues = powerplantpvReportGetSubmittedDcMeasures();
	$result = $builder->saveValues((int) $report->id, $values, $dateValues, $user, PowerPlantPVReport::STATUS_DRAFT);
	if ($result >= 0) {
		$result = $builder->saveDcMeasureValues((int) $report->id, $dcValues, $user);
	}
	if ($result >= 0) {
		$result = $builder->addManualDcMeasureLine((int) $report->id, $dcSectionId, $user);
	}
	if ($result < 0) {
		setEventMessages($builder->error, $builder->errors, 'errors');
	} else {
		setEventMessages($langs->trans('PowerPlantPVDcMeasureLineAdded'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $id);
		exit;
	}
}

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
		setEventMessages($langs->trans('FileWasUploaded'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $id);
		exit;
	}
}

if ($action === 'delete_file' && $caneditreport && $reportFetch > 0) {
	$result = powerplantpvReportDeleteFile($report, $intervention, $fileId);
	if ($result < 0) {
		setEventMessages($langs->trans('ErrorFailedToDeleteFile'), null, 'errors');
	} else {
		setEventMessages($langs->trans('FileWasRemoved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $id);
		exit;
	}
}

$form = new Form($db);
$title = $langs->trans('PowerPlantPVReportTab');

$morejs = array('/powerplantpv/js/powerplantpv_report.js');
$morecss = array('/powerplantpv/css/powerplantpv_report.css');
llxHeader('', $title, '', '', 0, 0, $morejs, $morecss, '', 'mod-powerplantpv page-maintenance-intervention-report');

$head = array();
if (function_exists('fichinter_prepare_head')) {
	$head = fichinter_prepare_head($intervention);
}
print dol_get_fiche_head($head, 'powerplantpv_report', $langs->trans('Intervention'), -1, 'intervention');

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
		$diagnosticTree = $builder->buildPreviewTree($intervention, $manualServiceIds);
		if (is_array($diagnosticTree)) {
			$tree = $diagnosticTree;
		}
	} elseif (powerplantpvReportTreeHasNonDcSectionWithoutFields($tree)) {
		$invalidExistingSnapshot = true;
		$diagnosticTree = $builder->buildPreviewTree($intervention, $manualServiceIds);
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
if (is_array($tree) && !empty($tree['messages']) && is_array($tree['messages'])) {
	$messages = array_merge($messages, $tree['messages']);
}
if (is_array($diagnosticTree) && !empty($diagnosticTree['messages']) && is_array($diagnosticTree['messages'])) {
	$messages = array_merge($messages, $diagnosticTree['messages']);
}
$messages = array_values(array_unique($messages));
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
	$snapshotNeedsRebuild = ($emptyExistingSnapshot || $invalidExistingSnapshot);
	$showManualSelection = (($reportFetch <= 0 || $snapshotNeedsRebuild)
		&& $caneditreport
		&& $noContractPrestations
		&& !empty($manualOptions));
	$formClass = 'powerplantpv-report-form'.($caneditreport ? ' powerplantpv-report-form-editable' : '');
	print '<form id="powerplantpvreportform" class="'.dol_escape_htmltag($formClass).'" method="POST" enctype="multipart/form-data" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="id" value="'.((int) $id).'">';
	print '<input type="hidden" id="powerplantpv-scroll-y" name="powerplantpv_scroll_y" value="">';
	print '<input type="hidden" id="powerplantpv-restore-scroll-y" value="'.((int) $reportRestoreScrollY).'">';

	if ($showManualSelection) {
		print '<div class="powerplantpv-report-manual">';
		print '<label>'.$langs->trans('PowerPlantPVReportManualServices').'</label>';
		print $form->multiselectarray('manual_services', $manualOptions, $manualServiceIds, 0, 0, 'powerplantpv-report-control', 0, 0);
		print '<div class="opacitymedium">'.$langs->trans('PowerPlantPVReportManualServicesHelp').'</div>';
		print '</div>';
	}

	$renderEditableFields = $caneditreport;
	powerplantpvReportRenderSections($tree['sections'], $renderEditableFields, $form, isset($tree['powerplants']) && is_array($tree['powerplants']) ? $tree['powerplants'] : array());

	if ($caneditreport) {
		print '<div class="center powerplantpv-report-actions">';
		print '<input type="submit" class="button button-save" name="save_draft_submit" value="'.dol_escape_htmltag($langs->trans('Save')).'">';
		print ' ';
		print '<input type="submit" class="button button-save" name="save_report_submit" value="'.dol_escape_htmltag($langs->trans('PowerPlantPVReportFinalize')).'">';
		print ' ';
		$signatureUrl = powerplantpvReportGetInterventionOnlineSignatureUrl($intervention);
		$reportIsFinalized = ($reportFetch > 0 && (string) $report->status === PowerPlantPVReport::STATUS_SAVED);
		$signatureEnabled = ($reportIsFinalized && !$snapshotNeedsRebuild && $signatureUrl !== '');
		$signatureTooltip = $langs->trans('PowerPlantPVReportSignatureUnavailable');
		if (!$reportIsFinalized) {
			$signatureTooltip = $langs->trans('PowerPlantPVReportSignatureRequiresFinalizedReport');
		}
		print dolGetButtonAction(
			$langs->trans('PowerPlantPVReportSignButton'),
			$signatureEnabled ? '' : $signatureTooltip,
			'default',
			$signatureEnabled ? $signatureUrl : '#',
			'',
			$signatureEnabled
		);
		if ($snapshotNeedsRebuild && $reportFetch > 0) {
			print ' ';
			print '<input type="submit" class="button button-save" name="recalculate_submit" value="'.dol_escape_htmltag($langs->trans('PowerPlantPVReportRecalculate')).'">';
		}
		print '</div>';
		print '<input type="submit" class="button button-save powerplantpv-floating-save" name="save_draft_submit" value="'.dol_escape_htmltag($langs->trans('Save')).'">';
	}
	print '</form>';

	if ($caneditreport && $reportFetch > 0 && !$snapshotNeedsRebuild) {
		print '<div class="tabsAction">';
		print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" class="powerplantpv-inline-form">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="id" value="'.((int) $id).'">';
		print '<input type="hidden" name="action" value="recalculate">';
		print '<input type="submit" class="butAction" value="'.dol_escape_htmltag($langs->trans('PowerPlantPVReportRecalculate')).'">';
		print '</form>';
		print '</div>';
	}
}
print '</div>';

print dol_get_fiche_end();

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
	$signedStatus = isset($intervention->signed_status) ? (int) $intervention->signed_status : 0;
	$receiver = (class_exists('Fichinter') && defined('Fichinter::STATUS_SIGNED_RECEIVER')) ? (int) constant('Fichinter::STATUS_SIGNED_RECEIVER') : 2;
	$all = (class_exists('Fichinter') && defined('Fichinter::STATUS_SIGNED_ALL')) ? (int) constant('Fichinter::STATUS_SIGNED_ALL') : 9;

	return in_array($signedStatus, array($receiver, $all), true);
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
 * @return	array<string,string>	Date values by stable key
 */
function powerplantpvReportGetSubmittedDateValues()
{
	global $db;

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
		$values[$stableKey] = $db->idate($timestamp);
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
		$clean['is_connected'] = !empty($row['is_connected']) ? 1 : 0;
		$clean['open_circuit_voltage'] = isset($row['open_circuit_voltage']) ? dol_string_nohtmltag((string) $row['open_circuit_voltage']) : '';
		$clean['polarity_checked'] = !empty($row['polarity_checked']) ? 1 : 0;
		$clean['insulation_status'] = isset($row['insulation_status']) ? dol_string_nohtmltag((string) $row['insulation_status']) : '';
		$clean['insulation_positive_to_ground'] = isset($row['insulation_positive_to_ground']) ? dol_string_nohtmltag((string) $row['insulation_positive_to_ground']) : '';
		$clean['insulation_negative_to_ground'] = isset($row['insulation_negative_to_ground']) ? dol_string_nohtmltag((string) $row['insulation_negative_to_ground']) : '';
		$clean['observation'] = isset($row['observation']) ? dol_string_nohtmltag((string) $row['observation']) : '';
		$values[(string) $key] = $clean;
	}

	return $values;
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
 * @return	void
 */
function powerplantpvReportRenderSections($sections, $editable, $form, $powerplants = array())
{
	global $langs;

	if (empty($sections) && empty($powerplants)) {
		print '<table class="noborder centpercent">';
		print '<tr class="oddeven"><td><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
		print '</table>';
		return;
	}

	$groups = array();
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
		if (!empty($row['powerplant'])) {
			$key = 'powerplant:'.powerplantpvReportPowerplantSourceId($row['powerplant']);
		}
		if (!isset($groups[$key])) {
			$groups[$key] = array('powerplant' => !empty($row['powerplant']) ? $row['powerplant'] : null, 'sections' => array());
		}
		$groups[$key]['sections'][] = $row;
	}

	foreach ($groups as $group) {
		if (!empty($group['powerplant'])) {
			print load_fiche_titre(dol_escape_htmltag(powerplantpvReportPowerplantLabel($group['powerplant'])), '', 'fa-industry');
			powerplantpvReportRenderPowerplantTechnicalSummary($group['powerplant']);
		}
		foreach ($group['sections'] as $row) {
			$section = $row['section'];
			if (powerplantpvReportSectionIsPowerplantTechnicalSummary((string) $section->section_code)) {
				continue;
			}
			$fields = powerplantpvReportFilterRenderableFields(isset($row['fields']) && is_array($row['fields']) ? $row['fields'] : array());
			if ((string) $section->section_code !== 'DC_ELECTRICAL_MEASURE' && empty($fields)) {
				continue;
			}
			$label = powerplantpvReportLocalizedObjectLabel($section, 'section_label');
			print '<details class="powerplantpv-report-section" open>';
			print '<summary class="liste_titre"><span>'.dol_escape_htmltag($label).'</span>';
			if (!empty($section->is_required)) {
				print '<span class="badge marginleftonlyshort">'.$langs->trans('Required').'</span>';
			}
			print '</summary>';
			if (!empty($row['equipment'])) {
				print '<div class="refidno">'.dol_escape_htmltag(powerplantpvReportEquipmentLabel($row['equipment'])).'</div>';
			}
			if ((string) $section->section_code === 'DC_ELECTRICAL_MEASURE') {
				$dcMeasures = isset($row['dc_measures']) && is_array($row['dc_measures']) ? $row['dc_measures'] : array();
				powerplantpvReportRenderDcMeasures($section, $dcMeasures, $editable, $form);
				print '</details>';
				continue;
			}
			print '<div class="powerplantpv-report-fields">';
			foreach ($fields as $field) {
				powerplantpvReportRenderField($field, $editable, $form);
			}
			print '</div>';
			print '</details>';
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
	if (empty($snapshot['sections']) || !is_array($snapshot['sections'])) {
		print '<details class="powerplantpv-report-section powerplantpv-report-summary" open>';
		print '<summary class="liste_titre"><span>'.dol_escape_htmltag($langs->trans('PowerPlantPVReportPowerPlantSummary')).'</span></summary>';
		print '<div class="powerplantpv-report-summary-content">';
		print '<table class="noborder centpercent"><tr class="oddeven"><td><span class="opacitymedium">'.$langs->trans('PowerPlantPVReportPowerPlantSummaryUnavailable').'</span></td></tr></table>';
		print '</div>';
		print '</details>';
		return;
	}

	print '<details class="powerplantpv-report-section powerplantpv-report-summary" open>';
	print '<summary class="liste_titre"><span>'.dol_escape_htmltag($langs->trans('PowerPlantPVReportPowerPlantSummary')).'</span></summary>';
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
			print '<td>'.dol_htmlentitiesbr($value).'</td>';
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
	global $intervention, $langs, $report, $reportFetch, $snapshotNeedsRebuild;

	$firstRow = reset($signatureRows);
	$section = is_array($firstRow) && isset($firstRow['section']) && is_object($firstRow['section']) ? $firstRow['section'] : null;
	$title = is_object($section) ? powerplantpvReportLocalizedObjectLabel($section, 'section_label') : $langs->trans('PowerPlantPVReportCustomerSignature');
	if ($title === '') {
		$title = $langs->trans('PowerPlantPVReportCustomerSignature');
	}
	$signatureUrl = powerplantpvReportGetInterventionOnlineSignatureUrl($intervention);
	$reportIsFinalized = ($reportFetch > 0 && (string) $report->status === PowerPlantPVReport::STATUS_SAVED);
	$signatureEnabled = ($reportIsFinalized && !$snapshotNeedsRebuild && $signatureUrl !== '');
	$message = $langs->trans('PowerPlantPVReportSignatureUnavailable');
	if (!$reportIsFinalized) {
		$message = $langs->trans('PowerPlantPVReportSignatureRequiresFinalizedReport');
	}

	print '<div class="powerplantpv-native-signature-block">';
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
 * @return	array<int,PowerPlantPVReportField>			Renderable fields
 */
function powerplantpvReportFilterRenderableFields($fields)
{
	$filtered = array();
	foreach ($fields as $field) {
		if (!is_object($field) || (string) $field->field_type === 'signature') {
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

	if (empty($measures)) {
		print '<div class="opacitymedium powerplantpv-report-manual">'.$langs->trans('PowerPlantPVDcMeasureNoConfigurationManualMode').'</div>';
		if ($editable && !empty($section->id)) {
			print '<input type="submit" class="button" formaction="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.GETPOSTINT('id').'&action=add_dc_measure_line&add_dc_section_id='.((int) $section->id).'" value="'.dol_escape_htmltag($langs->trans('PowerPlantPVAddDcMeasureLine')).'">';
		} elseif ($editable) {
			print '<div class="opacitymedium">'.$langs->trans('PowerPlantPVReportManualLineAfterFirstSave').'</div>';
		}
		return;
	}

	$grouped = array();
	foreach ($measures as $measure) {
		$inverterKey = trim((string) $measure->inverter_label) !== '' ? (string) $measure->inverter_label : (string) $measure->inverter_ref;
		if ($inverterKey === '') {
			$inverterKey = $langs->trans('PowerPlantPVManualDcLine');
		}
		$mpptKey = $measure->mppt_number !== null && (string) $measure->mppt_number !== '' ? (string) $measure->mppt_number : $langs->trans('PowerPlantPVManual');
		if (!isset($grouped[$inverterKey])) {
			$grouped[$inverterKey] = array();
		}
		if (!isset($grouped[$inverterKey][$mpptKey])) {
			$grouped[$inverterKey][$mpptKey] = array();
		}
		$grouped[$inverterKey][$mpptKey][] = $measure;
	}

	print '<div class="powerplantpv-dc-measures">';
	foreach ($grouped as $inverterLabel => $mppts) {
		print '<div class="powerplantpv-dc-inverter">';
		print '<div class="liste_titre">'.dol_escape_htmltag($langs->trans('PowerPlantPVInverter')).' : '.dol_escape_htmltag((string) $inverterLabel).'</div>';
		foreach ($mppts as $mpptLabel => $rows) {
			print '<div class="powerplantpv-dc-mppt">';
			print '<div class="opacitymedium">'.dol_escape_htmltag($langs->trans('PowerPlantPVMPPT')).' '.dol_escape_htmltag((string) $mpptLabel).'</div>';
			print '<div class="powerplantpv-dc-measure-grid">';
			foreach ($rows as $measure) {
				powerplantpvReportRenderDcMeasureCard($measure, $editable, $form);
			}
			print '</div>';
			print '</div>';
		}
		print '</div>';
	}
	if ($editable && !empty($section->id)) {
		print '<div class="powerplantpv-report-actions">';
		print '<input type="submit" class="button" formaction="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.GETPOSTINT('id').'&action=add_dc_measure_line&add_dc_section_id='.((int) $section->id).'" value="'.dol_escape_htmltag($langs->trans('PowerPlantPVAddDcMeasureLine')).'">';
		print '</div>';
	}
	print '</div>';
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
	$isManual = (strpos((string) $measure->stable_key, ':dc:manual:') !== false);
	$insulationOptions = array(
		'' => '',
		'valid' => $langs->trans('PowerPlantPVReportConformityValid'),
		'observation' => $langs->trans('PowerPlantPVReportConformityObservation'),
		'not_applicable' => $langs->trans('PowerPlantPVReportConformityNotApplicable'),
	);

	print '<div class="powerplantpv-dc-card">';
	print '<input type="hidden" name="'.$name.'[id]" value="'.((int) $measure->id).'">';
	print '<input type="hidden" name="'.$name.'[stable_key]" value="'.dol_escape_htmltag((string) $measure->stable_key).'">';
	print '<div class="powerplantpv-dc-card-title">';
	print dol_escape_htmltag($langs->trans('PowerPlantPVPVInput')).' '.dol_escape_htmltag((string) $measure->pv_input_number);
	if ((string) $measure->string_ref !== '') {
		print ' - '.dol_escape_htmltag((string) $measure->string_ref);
	}
	print '</div>';
	if (!$editable) {
		$insulationLabel = isset($insulationOptions[(string) $measure->insulation_status]) ? $insulationOptions[(string) $measure->insulation_status] : (string) $measure->insulation_status;
		print '<div class="powerplantpv-dc-readonly">';
		print '<span>'.dol_escape_htmltag($langs->trans('PowerPlantPVStringRef')).' : '.dol_escape_htmltag((string) $measure->string_ref).'</span>';
		print '<span>'.dol_escape_htmltag($langs->trans('PowerPlantPVPVInputConnected')).' : '.$langs->trans(!empty($measure->is_connected) ? 'Yes' : 'No').'</span>';
		print '<span>'.dol_escape_htmltag($langs->trans('PowerPlantPVOpenCircuitVoltage')).' : '.($measure->open_circuit_voltage !== null ? price($measure->open_circuit_voltage).' V' : '').'</span>';
		print '<span>'.dol_escape_htmltag($langs->trans('PowerPlantPVPolarityChecked')).' : '.$langs->trans(!empty($measure->polarity_checked) ? 'Yes' : 'No').'</span>';
		print '<span>'.dol_escape_htmltag($langs->trans('PowerPlantPVInsulationStatus')).' : '.dol_escape_htmltag($insulationLabel).'</span>';
		print '<span>'.dol_escape_htmltag($langs->trans('PowerPlantPVInsulationPositiveToGround')).' : '.($measure->insulation_positive_to_ground !== null ? price($measure->insulation_positive_to_ground).' MOhm' : '').'</span>';
		print '<span>'.dol_escape_htmltag($langs->trans('PowerPlantPVInsulationNegativeToGround')).' : '.($measure->insulation_negative_to_ground !== null ? price($measure->insulation_negative_to_ground).' MOhm' : '').'</span>';
		print '<span>'.dol_htmlentitiesbr((string) $measure->observation).'</span>';
		print '</div></div>';
		return;
	}

	if ($isManual) {
		print '<label>'.$langs->trans('PowerPlantPVInverter').'<input type="text" class="flat powerplantpv-report-control" name="'.$name.'[inverter_label]" value="'.dol_escape_htmltag((string) $measure->inverter_label).'"></label>';
		print '<label>'.$langs->trans('PowerPlantPVMPPT').'<input type="text" class="flat powerplantpv-report-control" name="'.$name.'[mppt_number]" value="'.dol_escape_htmltag((string) $measure->mppt_number).'"></label>';
		print '<label>'.$langs->trans('PowerPlantPVPVInput').'<input type="text" class="flat powerplantpv-report-control" name="'.$name.'[pv_input_number]" value="'.dol_escape_htmltag((string) $measure->pv_input_number).'"></label>';
	} else {
		print '<input type="hidden" name="'.$name.'[inverter_label]" value="'.dol_escape_htmltag((string) $measure->inverter_label).'">';
		print '<input type="hidden" name="'.$name.'[mppt_number]" value="'.dol_escape_htmltag((string) $measure->mppt_number).'">';
		print '<input type="hidden" name="'.$name.'[pv_input_number]" value="'.dol_escape_htmltag((string) $measure->pv_input_number).'">';
	}
	print '<label>'.$langs->trans('PowerPlantPVStringRef').'<input type="text" class="flat powerplantpv-report-control" name="'.$name.'[string_ref]" value="'.dol_escape_htmltag((string) $measure->string_ref).'"></label>';
	print '<label>'.$langs->trans('PowerPlantPVPVInputConnected').$form->selectarray($name.'[is_connected]', array(0 => $langs->trans('No'), 1 => $langs->trans('Yes')), (int) $measure->is_connected, 0, 0, 0, '', 0, 0, 0, '', 'powerplantpv-report-control').'</label>';
	print '<label>'.$langs->trans('PowerPlantPVOpenCircuitVoltage').'<span class="powerplantpv-control-with-unit"><input type="text" class="flat powerplantpv-report-control right" name="'.$name.'[open_circuit_voltage]" value="'.dol_escape_htmltag($measure->open_circuit_voltage !== null ? price($measure->open_circuit_voltage) : '').'"><span class="opacitymedium">V</span></span></label>';
	print '<label>'.$langs->trans('PowerPlantPVPolarityChecked').$form->selectarray($name.'[polarity_checked]', array(0 => $langs->trans('No'), 1 => $langs->trans('Yes')), (int) $measure->polarity_checked, 0, 0, 0, '', 0, 0, 0, '', 'powerplantpv-report-control').'</label>';
	print '<label>'.$langs->trans('PowerPlantPVInsulationStatus').$form->selectarray($name.'[insulation_status]', $insulationOptions, (string) $measure->insulation_status, 1, 0, 0, '', 0, 0, 0, '', 'powerplantpv-report-control').'</label>';
	print '<label>'.$langs->trans('PowerPlantPVInsulationPositiveToGround').'<span class="powerplantpv-control-with-unit"><input type="text" class="flat powerplantpv-report-control right" name="'.$name.'[insulation_positive_to_ground]" value="'.dol_escape_htmltag($measure->insulation_positive_to_ground !== null ? price($measure->insulation_positive_to_ground) : '').'"><span class="opacitymedium">MOhm</span></span></label>';
	print '<label>'.$langs->trans('PowerPlantPVInsulationNegativeToGround').'<span class="powerplantpv-control-with-unit"><input type="text" class="flat powerplantpv-report-control right" name="'.$name.'[insulation_negative_to_ground]" value="'.dol_escape_htmltag($measure->insulation_negative_to_ground !== null ? price($measure->insulation_negative_to_ground) : '').'"><span class="opacitymedium">MOhm</span></span></label>';
	print '<label class="powerplantpv-dc-observation">'.$langs->trans('Observation').'<textarea class="flat powerplantpv-report-control" rows="2" name="'.$name.'[observation]">'.dol_escape_htmltag((string) $measure->observation).'</textarea></label>';
	print '</div>';
}

/**
 * Render one field.
 *
 * @param	PowerPlantPVReportField	$field		Field
 * @param	bool					$editable	Editable flag
 * @param	Form					$form		Form helper
 * @return	void
 */
function powerplantpvReportRenderField($field, $editable, $form)
{
	global $langs;

	$fieldEditable = $editable && empty($field->readonly);
	$label = powerplantpvReportLocalizedObjectLabel($field, 'field_label');
	$name = 'report_values['.dol_escape_htmltag((string) $field->stable_key).']';
	$type = (string) $field->field_type;
	$value = powerplantpvReportFieldDisplayValue($field);
	$fieldClass = 'powerplantpv-report-field';
	$fieldClass .= powerplantpvReportFieldCanInline($field) ? ' powerplantpv-report-field-inline' : ' powerplantpv-report-field-block';
	$inputClass = 'powerplantpv-report-input'.(powerplantpvReportFieldHasInlineUnit($field) ? ' powerplantpv-report-input-with-unit' : '');

	print '<div class="'.$fieldClass.'">';
	print '<label>'.dol_escape_htmltag($label);
	if (!empty($field->is_required)) {
		print ' <span class="required">*</span>';
	}
	print '</label>';
	if (!empty($field->help)) {
		print '<div class="opacitymedium powerplantpv-report-field-help">'.dol_escape_htmltag((string) $field->help).'</div>';
	}
	print '<div class="'.$inputClass.'">';
	if (!$fieldEditable) {
		if ($type === 'file') {
			powerplantpvReportRenderFiles($field, false);
		} elseif ($type === 'textarea') {
			print '<div class="powerplantpv-report-readonly">';
			powerplantpvReportPrintHtmlValue((string) $value);
			print '</div>';
		} elseif ($value === '' && powerplantpvReportFieldIsPreviousReading($field)) {
			print '<div class="powerplantpv-report-readonly opacitymedium">'.$langs->trans('PowerPlantPVReportNoPreviousReading').'</div>';
		} else {
			print '<div class="powerplantpv-report-readonly">'.dol_htmlentitiesbr((string) $value).'</div>';
		}
		print '</div></div>';
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
		$timestamp = !empty($field->value_date) ? strtotime((string) $field->value_date) : 0;
		print '<input type="hidden" name="report_date_keys['.$hash.']" value="'.dol_escape_htmltag((string) $field->stable_key).'">';
		print '<div class="powerplantpv-report-date">';
		print $form->selectDate($timestamp, 'report_date_'.$hash, ($type === 'datetime' ? 1 : 0), ($type === 'datetime' ? 1 : 0), 1, 'powerplantpvreportform', 1, 1);
		print '</div>';
	} elseif ($type === 'checkbox' || $type === 'yesno') {
		print $form->selectarray($name, array(0 => $langs->trans('No'), 1 => $langs->trans('Yes')), (int) $value, 0, 0, 0, '', 0, 0, 0, '', 'powerplantpv-report-control');
	} elseif ($type === 'select' || $type === 'conformity_so_valid_obs') {
		$options = powerplantpvReportFieldOptions($field);
		print $form->selectarray($name, $options, (string) $value, 1, 0, 0, '', 0, 0, 0, '', 'powerplantpv-report-control');
	} elseif ($type === 'multiselect') {
		$options = powerplantpvReportFieldOptions($field);
		$selected = array_filter(explode("\n", (string) $value));
		print $form->multiselectarray($name, $options, $selected, 0, 0, 'powerplantpv-report-control', 0, 0);
	} elseif ($type === 'file') {
		powerplantpvReportRenderFiles($field, true);
		if (!empty($field->id)) {
			print '<div class="powerplantpv-file-upload">';
			print '<input type="file" name="report_file_'.$field->id.'" class="flat powerplantpv-report-control">';
			print '<input type="submit" class="button" formaction="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.GETPOSTINT('id').'&action=upload_file&field_id='.((int) $field->id).'" value="'.dol_escape_htmltag($langs->trans('Upload')).'">';
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
	print '</div>';
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
	if ($serial !== '') {
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
		return !empty($field->value_date) ? dol_print_date(strtotime((string) $field->value_date), ((string) $field->field_type === 'datetime' ? 'dayhour' : 'day')) : '';
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
 * Return field options.
 *
 * @param	PowerPlantPVReportField	$field	Field
 * @return	array<string,string>			Options
 */
function powerplantpvReportFieldOptions($field)
{
	global $langs;

	if ((string) $field->field_type === 'conformity_so_valid_obs') {
		return array(
			'valid' => $langs->trans('PowerPlantPVReportConformityValid'),
			'observation' => $langs->trans('PowerPlantPVReportConformityObservation'),
			'not_applicable' => $langs->trans('PowerPlantPVReportConformityNotApplicable'),
		);
	}
	$options = array();
	$decoded = !empty($field->options_snapshot) ? json_decode((string) $field->options_snapshot, true) : array();
	if (is_array($decoded)) {
		foreach ($decoded as $option) {
			if (!is_array($option) || empty($option['code'])) {
				continue;
			}
			$label = isset($option['label']) ? (string) $option['label'] : (string) $option['code'];
			if ($langs->defaultlang == 'en_US' && !empty($option['label_en'])) {
				$label = (string) $option['label_en'];
			}
			$options[(string) $option['code']] = $label;
		}
	}

	return $options;
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
	global $langs;

	$files = isset($field->files) && is_array($field->files) ? $field->files : array();
	if (empty($files)) {
		print '<div class="opacitymedium">'.$langs->trans('NoRecordFound').'</div>';
		return;
	}
	print '<ul class="powerplantpv-report-files">';
	foreach ($files as $file) {
		$url = DOL_URL_ROOT.'/document.php?modulepart=powerplantpv&file='.urlencode((string) $file->filepath);
		print '<li>';
		print '<a href="'.$url.'">'.dol_escape_htmltag((string) $file->filename).'</a>';
		if ($editable) {
			print ' <input type="submit" class="button small" formaction="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.GETPOSTINT('id').'&action=delete_file&file_id='.(int) $file->id.'" value="'.dol_escape_htmltag($langs->trans('Delete')).'">';
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

	$entity = !empty($report->entity) ? (int) $report->entity : (int) $conf->entity;
	$base = '';
	if (!empty($conf->powerplantpv->multidir_output[$entity])) {
		$base = $conf->powerplantpv->multidir_output[$entity];
	} elseif (!empty($conf->powerplantpv->dir_output)) {
		$base = $conf->powerplantpv->dir_output;
	}
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
	if (empty($_FILES[$fileKey]) || !is_array($_FILES[$fileKey]) || !empty($_FILES[$fileKey]['error'])) {
		return -1;
	}
	$originalName = dol_sanitizeFileName((string) $_FILES[$fileKey]['name']);
	if ($originalName === '') {
		return -1;
	}
	$relativeDir = 'report/'.dol_sanitizeFileName((string) $intervention->ref).'/field_'.((int) $fieldId);
	$uploadDir = powerplantpvReportOutputDir($report, $intervention).'/field_'.((int) $fieldId);
	if (dol_mkdir($uploadDir) < 0) {
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

	return $file->create($user, 0) > 0 ? 1 : -1;
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
	$base = powerplantpvReportOutputDir($report, $intervention);
	$relative = (string) $file->filepath;
	$prefix = 'report/'.dol_sanitizeFileName((string) $intervention->ref).'/';
	if (strpos($relative, $prefix) !== 0) {
		return -1;
	}
	$physical = dirname($base).'/'.substr($relative, strlen('report/'.dol_sanitizeFileName((string) $intervention->ref).'/') + strlen(''));
	$physical = $base.'/'.substr($relative, strlen($prefix));
	if (is_file($physical)) {
		dol_delete_file($physical);
	}

	return $file->delete($GLOBALS['user'], 0);
}
