<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr> */

/**
 * \file		attestation_agenda.php
 * \ingroup		powerplantpv
 * \brief		Agenda tab for attestations.
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

require_once DOL_DOCUMENT_ROOT.'/comm/action/class/html.formactions.class.php';
dol_include_once('/powerplantpv/class/powerplantpvattestation.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv_attestation.lib.php');

$langs->loadLangs(array('powerplantpv@powerplantpv', 'agenda'));

$id = GETPOSTINT('id');
if (!isModEnabled('powerplantpv') || !isModEnabled('agenda') || !getDolGlobalInt('POWERPLANTPV_ATTESTATION_ENABLE', 1)) {
	accessforbidden();
}
if (!$user->hasRight('powerplantpv', 'attestation', 'read')) {
	accessforbidden();
}

$object = new PowerPlantPVAttestation($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden();
}
restrictedArea($user, $object->module, $object, $object->table_element, $object->element, 'fk_soc', 'rowid', 0);

$formactions = new FormActions($db);

llxHeader('', $langs->trans('EventsAgenda').' - '.$object->ref, '', '', 0, 0, '', '', '', 'mod-powerplantpv page-attestation-agenda');
$head = powerplantpvAttestationPrepareHead($object);
print dol_get_fiche_head($head, 'agenda', $langs->trans('Attestation'), -1, $object->picto);
dol_banner_tab($object, 'ref', powerplantpvAttestationGetBackToListLink($object), 1, 'ref', 'ref', powerplantpvAttestationBuildBannerMoreHtml($object));
print '<div class="fichecenter"><div class="underbanner clearboth"></div>';
$formactions->showactions($object, 'attestation@powerplantpv', $object->fk_soc, 0);
print '</div>';
print dol_get_fiche_end();
llxFooter();
$db->close();
