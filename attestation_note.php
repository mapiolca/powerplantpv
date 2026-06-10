<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		attestation_note.php
 * \ingroup		powerplantpv
 * \brief		Notes tab for attestations.
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
dol_include_once('/powerplantpv/class/powerplantpvattestation.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_attestation.lib.php');

$langs->loadLangs(array('powerplantpv@powerplantpv', 'companies'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');
$socid = GETPOSTINT('socid');

if (!isModEnabled('powerplantpv') || !getDolGlobalInt('POWERPLANTPV_ATTESTATION_ENABLE', 1)) {
	accessforbidden();
}
if (!powerplantpvAttestationUserHasRight($user, 'read')) {
	accessforbidden();
}
if (function_exists('powerplantpvAttestationGetInstallationIssues')) {
	$attestationInstallationIssues = powerplantpvAttestationGetInstallationIssues();
	if (!empty($attestationInstallationIssues['tables']) || !empty($attestationInstallationIssues['columns'])) {
		accessforbidden($langs->trans('AttestationInstallationIncomplete'));
	}
}

$object = new PowerPlantPVAttestation($db);
$hookmanager->initHooks(array($object->element.'note', 'globalcard'));
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden();
}

$permissiontoadd = powerplantpvAttestationUserHasRight($user, 'write');
$permissionnote = ($permissiontoadd && (int) $object->status !== PowerPlantPVAttestation::STATUS_SIGNED);

if ($user->socid > 0) {
	$socid = $user->socid;
}
$isdraft = ((int) $object->status === PowerPlantPVAttestation::STATUS_DRAFT ? 1 : 0);
restrictedArea($user, $object->module, $object, $object->table_element, $object->element, 'fk_soc', 'rowid', $isdraft);

/*
 * Actions
 */

$parameters = array('id' => $id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}
if (empty($reshook)) {
	if (GETPOST('cancel', 'alpha') && !empty($backtopage)) {
		header('Location: '.$backtopage);
		exit;
	}
	include DOL_DOCUMENT_ROOT.'/core/actions_setnotes.inc.php';
}

/*
 * View
 */

$form = new Form($db);

llxHeader('', $langs->trans('Attestation').' - '.$langs->trans('Notes'), '', '', 0, 0, '', '', '', 'mod-powerplantpv page-attestation-note');

$head = powerplantpvAttestationPrepareHead($object);
print dol_get_fiche_head($head, 'note', $langs->trans('Attestation'), -1, $object->picto);
dol_banner_tab($object, 'ref', powerplantpvAttestationGetBackToListLink($object), 1, 'ref', 'ref', powerplantpvAttestationBuildBannerMoreHtml($object));

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

$cssclass = 'titlefield';
include DOL_DOCUMENT_ROOT.'/core/tpl/notes.tpl.php';

print '</div>';
print dol_get_fiche_end();

llxFooter();
$db->close();
