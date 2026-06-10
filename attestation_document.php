<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr> */

/**
 * \file		attestation_document.php
 * \ingroup		powerplantpv
 * \brief		Documents tab for attestations.
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/powerplantpv/class/powerplantpvattestation.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv_attestation.lib.php');

$langs->loadLangs(array('powerplantpv@powerplantpv', 'mails', 'other'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
if (!$sortfield) {
	$sortfield = 'name';
}
if (!$sortorder) {
	$sortorder = 'ASC';
}

if (!isModEnabled('powerplantpv') || !getDolGlobalInt('POWERPLANTPV_ATTESTATION_ENABLE', 1)) {
	accessforbidden();
}
if (!powerplantpvAttestationUserHasRight($user, 'read')) {
	accessforbidden();
}

$object = new PowerPlantPVAttestation($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden();
}

$permissiontoadd = powerplantpvAttestationUserHasRight($user, 'write') && (int) $object->status !== PowerPlantPVAttestation::STATUS_SIGNED;
$permissiontodelete = powerplantpvAttestationUserHasRight($user, 'delete') && (int) $object->status !== PowerPlantPVAttestation::STATUS_SIGNED;
restrictedArea($user, $object->module, $object, $object->table_element, $object->element, 'fk_soc', 'rowid', 0);

$upload_dir = powerplantpvAttestationGetDocumentUploadDir($object);
include DOL_DOCUMENT_ROOT.'/core/actions_linkedfiles.inc.php';
$upload_dir = powerplantpvAttestationGetDocumentRootDir(!empty($object->entity) ? $object->entity : $conf->entity);
include DOL_DOCUMENT_ROOT.'/core/actions_builddoc.inc.php';

$form = new Form($db);
$formfile = new FormFile($db);

llxHeader('', $langs->trans('Attestation').' - '.$langs->trans('Documents'), '', '', 0, 0, '', '', '', 'mod-powerplantpv page-attestation-document');

$head = powerplantpvAttestationPrepareHead($object);
print dol_get_fiche_head($head, 'document', $langs->trans('Attestation'), -1, $object->picto);
dol_banner_tab($object, 'ref', powerplantpvAttestationGetBackToListLink($object), 1, 'ref', 'ref', powerplantpvAttestationBuildBannerMoreHtml($object));

$upload_dir = powerplantpvAttestationGetDocumentUploadDir($object);
$filearray = dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$', $sortfield, (strtolower($sortorder) == 'desc' ? SORT_DESC : SORT_ASC), 1);
$totalsize = 0;
foreach ($filearray as $file) {
	$totalsize += $file['size'];
}

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans('NbOfAttachedFiles').'</td><td>'.count($filearray).'</td></tr>';
print '<tr><td>'.$langs->trans('TotalSizeOfAttachedFiles').'</td><td>'.dol_print_size($totalsize).'</td></tr>';
print '</table>';
print '</div>';
print dol_get_fiche_end();

powerplantpvAttestationPrintDocumentGenerationForm($object, $_SERVER['PHP_SELF'].'?id='.(int) $object->id, $permissiontoadd, $object->model_pdf, 0, '', $langs->defaultlang);

$modulepart = powerplantpvAttestationGetDocumentModulePart();
$relativepathwithnofile = powerplantpvAttestationGetDocumentRelativePath($object);
$param = '&id='.(int) $object->id;
$permission = $permissiontoadd;
$permtoedit = $permissiontoadd;

include DOL_DOCUMENT_ROOT.'/core/tpl/document_actions_post_headers.tpl.php';

llxFooter();
$db->close();
