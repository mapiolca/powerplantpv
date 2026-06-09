<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr> */

/**
 * \file		attestation_signature.php
 * \ingroup		powerplantpv
 * \brief		Internal attestation signature page.
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/powerplantpv/class/powerplantpvattestation.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv_attestation.lib.php');

$langs->loadLangs(array('powerplantpv@powerplantpv', 'other'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

if (!isModEnabled('powerplantpv') || !getDolGlobalInt('POWERPLANTPV_ATTESTATION_ENABLE', 1)) {
	accessforbidden();
}
if (!powerplantpvAttestationUserHasRight($user, 'sign')) {
	accessforbidden();
}

$object = new PowerPlantPVAttestation($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden();
}
restrictedArea($user, $object->module, $object, $object->table_element, $object->element, 'fk_soc', 'rowid', 0);
if (!in_array((int) $object->status, array(PowerPlantPVAttestation::STATUS_VALIDATED, PowerPlantPVAttestation::STATUS_PENDING_SIGNATURE), true)) {
	accessforbidden($langs->trans('AttestationMustBeValidatedBeforeSignature'));
}

if ($action == 'sign') {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}

	$signatureData = GETPOST('signature_data', 'restricthtml');
	$signatureFile = powerplantpvAttestationStoreSignatureImage($object, $signatureData);
	if ($signatureFile === '') {
		setEventMessages($langs->trans($object->error), null, 'errors');
	} else {
		$previousStatus = (int) $object->status;
		$object->signature_file = $signatureFile;
		$object->date_signature = dol_now();
		$object->status = PowerPlantPVAttestation::STATUS_SIGNED;
		$result = $object->generateDocument($object->model_pdf, $langs);
		$object->status = $previousStatus;
		if ($result <= 0 || empty($object->last_main_doc)) {
			setEventMessages($object->error, $object->errors, 'errors');
		} else {
			$root = powerplantpvAttestationGetDocumentRootDir($object->entity);
			$source = $root.'/'.$object->last_main_doc;
			$signedRelative = powerplantpvAttestationGetDocumentRelativePath($object).'/'.dol_sanitizeFileName($object->ref).'_signed.pdf';
			$signedFile = $root.'/'.$signedRelative;
			$result = dol_copy($source, $signedFile, 0, 0);
			if ($result < 0) {
				setEventMessages($langs->trans('ErrorFailedToSaveFile'), null, 'errors');
			} else {
				$hash = hash_file('sha256', $signedFile);
				$result = $object->sign($user, $signatureFile, $signedRelative, $hash);
				if ($result > 0) {
					setEventMessages($langs->trans('AttestationSigned'), null, 'mesgs');
					header('Location: '.dol_buildpath('/powerplantpv/attestation_card.php', 1).'?id='.(int) $object->id);
					exit;
				}
				setEventMessages($object->error, $object->errors, 'errors');
			}
		}
	}
}

$form = new Form($db);

llxHeader('', $langs->trans('Sign').' - '.$object->ref, '', '', 0, 0, '', '', '', 'mod-powerplantpv page-attestation-signature');

$head = powerplantpvAttestationPrepareHead($object);
print dol_get_fiche_head($head, 'card', $langs->trans('Attestation'), -1, $object->picto);
dol_banner_tab($object, 'ref', powerplantpvAttestationGetBackToListLink($object), 1, 'ref', 'ref', powerplantpvAttestationBuildBannerMoreHtml($object));
print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.(int) $object->id.'" id="attestation-sign-form">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="sign">';
print '<input type="hidden" name="signature_data" id="signature_data" value="">';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans('AttestationSigner').'</td><td>'.dol_escape_htmltag($user->getFullName($langs)).'</td></tr>';
print '<tr><td>'.$langs->trans('AttestationSignature').'</td><td><canvas id="signature-pad" width="620" height="180" style="border:1px solid #999; max-width:100%; touch-action:none;"></canvas></td></tr>';
print '</table>';
print '<div class="tabsAction">';
print '<button type="button" class="butAction" id="signature-clear">'.$langs->trans('Clear').'</button>';
print '<button type="submit" class="butAction" id="signature-submit">'.$langs->trans('Sign').'</button>';
print '</div>';
print '</form>';
print '</div>';
print dol_get_fiche_end();

print '<script nonce="'.getNonce().'">
jQuery(function() {
	var canvas = document.getElementById("signature-pad");
	var ctx = canvas.getContext("2d");
	var drawing = false;
	ctx.lineWidth = 2;
	ctx.lineCap = "round";
	function pos(e) {
		var rect = canvas.getBoundingClientRect();
		var point = e.touches && e.touches.length ? e.touches[0] : e;
		return {x: (point.clientX - rect.left) * (canvas.width / rect.width), y: (point.clientY - rect.top) * (canvas.height / rect.height)};
	}
	function start(e) {
		drawing = true;
		var p = pos(e);
		ctx.beginPath();
		ctx.moveTo(p.x, p.y);
		e.preventDefault();
	}
	function move(e) {
		if (!drawing) return;
		var p = pos(e);
		ctx.lineTo(p.x, p.y);
		ctx.stroke();
		e.preventDefault();
	}
	function end(e) {
		drawing = false;
		e.preventDefault();
	}
	canvas.addEventListener("mousedown", start);
	canvas.addEventListener("mousemove", move);
	canvas.addEventListener("mouseup", end);
	canvas.addEventListener("mouseleave", end);
	canvas.addEventListener("touchstart", start, {passive:false});
	canvas.addEventListener("touchmove", move, {passive:false});
	canvas.addEventListener("touchend", end, {passive:false});
	jQuery("#signature-clear").on("click", function() { ctx.clearRect(0, 0, canvas.width, canvas.height); });
	jQuery("#attestation-sign-form").on("submit", function() {
		jQuery("#signature_data").val(canvas.toDataURL("image/png"));
	});
});
</script>';

llxFooter();
$db->close();
