<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr> */

/**
 * \file		attestation_signature.php
 * \ingroup		powerplantpv
 * \brief		Internal and public-token attestation signature page.
 */

$publicSignatureBoot = !empty($_GET['signature_token']) || !empty($_POST['signature_token']) || !empty($_GET['signature_done']);
if ($publicSignatureBoot && !defined('NOREQUIREUSER')) {
	define('NOREQUIREUSER', '1');
}
if ($publicSignatureBoot && !defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
$nologin = $publicSignatureBoot ? 1 : 0;

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
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
dol_include_once('/powerplantpv/class/powerplantpvattestation.class.php');
dol_include_once('/powerplantpv/class/powerplantpvattestationtypes.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_attestation.lib.php');

$langs->loadLangs(array('powerplantpv@powerplantpv', 'other'));

if (empty($user) || !is_object($user)) {
	$user = new User($db);
	$user->id = 0;
}

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$signatureToken = GETPOST('signature_token', 'alphanohtml');
$publicMode = ($signatureToken !== '');

if (!isModEnabled('powerplantpv') || !getDolGlobalInt('POWERPLANTPV_ATTESTATION_ENABLE', 1)) {
	accessforbidden();
}

if (function_exists('powerplantpvAttestationGetInstallationIssues')) {
	$attestationInstallationIssues = powerplantpvAttestationGetInstallationIssues();
	if (!empty($attestationInstallationIssues['tables']) || !empty($attestationInstallationIssues['columns'])) {
		dol_syslog(
			'PowerPlantPV attestation signature unavailable: missing tables '.implode(', ', $attestationInstallationIssues['tables']).' columns '.implode(', ', $attestationInstallationIssues['columns']).' id='.$id,
			LOG_ERR
		);
		accessforbidden($langs->trans('AttestationInstallationIncomplete'));
	}
}

if (GETPOSTINT('signature_done')) {
	llxHeader('', $langs->trans('AttestationOnlineSignature'), '', '', 0, 0, '', '', '', 'mod-powerplantpv page-attestation-signature page-attestation-public-signature');
	print load_fiche_titre($langs->trans('AttestationOnlineSignature'), '', 'fa-file-signature');
	print '<div class="ok">'.$langs->trans('AttestationSignaturePublicSuccess').'</div>';
	llxFooter();
	$db->close();
	exit;
}

$object = new PowerPlantPVAttestation($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden();
}

if ($publicMode) {
	if ($object->validateSignatureToken($signatureToken) < 0) {
		accessforbidden($langs->trans($object->error));
	}
} else {
	if (empty($user->id) || !powerplantpvAttestationUserHasRight($user, 'sign')) {
		accessforbidden();
	}
	restrictedArea($user, $object->module, $object, $object->table_element, $object->element, 'fk_soc', 'rowid', 0);
}

if (!in_array((int) $object->status, array(PowerPlantPVAttestation::STATUS_VALIDATED, PowerPlantPVAttestation::STATUS_PENDING_SIGNATURE), true)) {
	accessforbidden($langs->trans('AttestationMustBeValidatedBeforeSignature'));
}

if ($action == 'sign') {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
	if ($publicMode && $object->validateSignatureToken($signatureToken) < 0) {
		accessforbidden($langs->trans($object->error));
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
					if ($publicMode) {
						header('Location: '.dol_buildpath('/powerplantpv/attestation_signature.php', 1).'?signature_done=1');
						exit;
					}
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

llxHeader('', $langs->trans('Sign').' - '.$object->ref, '', '', 0, 0, '', '', '', 'mod-powerplantpv page-attestation-signature'.($publicMode ? ' page-attestation-public-signature' : ''));

if ($publicMode) {
	print load_fiche_titre($langs->trans('AttestationOnlineSignature'), '', 'fa-file-signature');
	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield">'.$langs->trans('Ref').'</td><td>'.dol_escape_htmltag($object->ref).'</td></tr>';
	print '<tr><td>'.$langs->trans('AttestationType').'</td><td>'.dol_escape_htmltag(PowerPlantPVAttestationTypes::getTypeLabels($langs)[$object->type_code] ?? $object->type_code).'</td></tr>';
	if (!empty($object->signature_token_expiry)) {
		print '<tr><td>'.$langs->trans('AttestationSignatureLinkExpiresOn').'</td><td>'.dol_print_date($object->signature_token_expiry, 'dayhour').'</td></tr>';
	}
	print '</table>';
} else {
	$head = powerplantpvAttestationPrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans('Attestation'), -1, $object->picto);
	dol_banner_tab($object, 'ref', powerplantpvAttestationGetBackToListLink($object), 1, 'ref', 'ref', powerplantpvAttestationBuildBannerMoreHtml($object));
	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';
}

$formAction = $_SERVER['PHP_SELF'].'?id='.(int) $object->id;
$signerName = $publicMode ? $langs->trans('AttestationPublicSigner') : $user->getFullName($langs);
print '<form method="POST" action="'.$formAction.'" id="attestation-sign-form">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="sign">';
if ($publicMode) {
	print '<input type="hidden" name="signature_token" value="'.dol_escape_htmltag($signatureToken).'">';
}
print '<input type="hidden" name="signature_data" id="signature_data" value="">';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans('AttestationSigner').'</td><td>'.dol_escape_htmltag($signerName).'</td></tr>';
print '<tr><td>'.$langs->trans('AttestationSignature').'</td><td><canvas id="signature-pad" width="620" height="180" style="border:1px solid #999; max-width:100%; touch-action:none;"></canvas></td></tr>';
print '</table>';
print '<div class="tabsAction">';
print '<button type="button" class="butAction" id="signature-clear">'.$langs->trans('Clear').'</button>';
print '<button type="submit" class="butAction" id="signature-submit">'.$langs->trans('Sign').'</button>';
print '</div>';
print '</form>';
print '</div>';
if (!$publicMode) {
	print dol_get_fiche_end();
}

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
