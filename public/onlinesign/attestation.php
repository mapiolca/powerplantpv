<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file		public/onlinesign/attestation.php
 * \ingroup		powerplantpv
 * \brief		Public fallback online signature page for attestations.
 */

if (!defined('NOLOGIN')) {
	define('NOLOGIN', 1);
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', 1);
}
if (!defined('NOIPCHECK')) {
	define('NOIPCHECK', '1');
}
if (!defined('NOBROWSERNOTIF')) {
	define('NOBROWSERNOTIF', '1');
}

// Must be defined before main.inc.php for Multicompany public links.
$entity = (!empty($_GET['entity']) ? (int) $_GET['entity'] : (!empty($_POST['entity']) ? (int) $_POST['entity'] : 1));
if (is_numeric($entity)) {
	define('DOLENTITY', $entity);
}

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
}
if (!$res && file_exists('../../../../main.inc.php')) {
	$res = @include '../../../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = @include '../../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
dol_include_once('/powerplantpv/lib/powerplantpv_attestation.lib.php');
dol_include_once('/powerplantpv/class/powerplantpvattestationtypes.class.php');

$langs->loadLangs(array('main', 'other', 'companies', 'errors', 'powerplantpv@powerplantpv'));

$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');
$ref = GETPOST('ref', 'alphanohtml');
$securekey = GETPOST('securekey');
$message = GETPOST('message', 'aZ09');
$entity = GETPOSTINT('entity') ?: (defined('DOLENTITY') ? (int) DOLENTITY : (int) $conf->entity);

if (!isModEnabled('powerplantpv') || !getDolGlobalInt('POWERPLANTPV_ATTESTATION_ENABLE', 1)) {
	httponly_accessforbidden($langs->trans('ErrorModuleSetupNotComplete'), 403, 1);
}

$errors = array();
$object = powerplantpvAttestationFetchForOnlineSignature($ref, $entity, $securekey, $errors);
if ($action === 'download') {
	if (!is_object($object)) {
		httponly_accessforbidden($langs->trans('AttestationSignatureLinkInvalid'), 403, 1);
	}
	$file = powerplantpvAttestationGetOnlineSignaturePdfFile($object, $langs, 1);
	if ($file === '' || !is_readable($file)) {
		httponly_accessforbidden($langs->trans('ErrorFileNotFound'), 404, 1);
	}
	$filename = basename($file);
	$mime = dol_mimetype($filename);
	header('Content-Type: '.$mime);
	header('Content-Length: '.filesize($file));
	header('Content-Disposition: inline; filename="'.$filename.'"');
	readfile($file);
	$db->close();
	exit;
}

$form = new Form($db);
$head = '';
if (getDolGlobalString('MAIN_SIGN_CSS_URL')) {
	$head = '<link rel="stylesheet" type="text/css" href="'.getDolGlobalString('MAIN_SIGN_CSS_URL').'?lang='.$langs->defaultlang.'">'."\n";
}

$conf->dol_hide_topmenu = 1;
$conf->dol_hide_leftmenu = 1;
$replacemainarea = (empty($conf->dol_hide_leftmenu) ? '<div>' : '').'<div>';
llxHeader($head, $langs->trans('OnlineSignature'), '', '', 0, 0, '', '', '', 'onlinepaymentbody', $replacemainarea, 1);

$creditor = is_object($mysoc) ? $mysoc->name : '';
$thirdpartyName = '';
if (is_object($object) && !empty($object->fk_soc)) {
	require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
	$societe = new Societe($db);
	if ($societe->fetch((int) $object->fk_soc) > 0) {
		$thirdpartyName = $societe->name;
	}
}
$typeLabel = '';
if (is_object($object)) {
	$typeLabels = PowerPlantPVAttestationTypes::getTypeLabels($langs);
	$typeLabel = !empty($typeLabels[$object->type_code]) ? $typeLabels[$object->type_code] : (string) $object->type_code;
}

print '<span id="dolpaymentspan"></span>'."\n";
print '<div class="center">'."\n";
print '<form id="dolpaymentform" class="center" name="paymentform" action="'.$_SERVER['PHP_SELF'].'" method="POST">'."\n";
print '<input type="hidden" name="token" value="'.newToken().'">'."\n";
print '<input type="hidden" name="action" value="dosign">'."\n";
print '<input type="hidden" name="securekey" value="'.dol_escape_htmltag($securekey).'">'."\n";
print '<input type="hidden" name="entity" value="'.((int) $entity).'">';
print '<input type="hidden" name="ref" value="'.dol_escape_htmltag($ref).'">';
print '<table id="dolpublictable" summary="Online signature form" class="center">'."\n";

$logosmall = is_object($mysoc) ? $mysoc->logo_small : '';
$logo = is_object($mysoc) ? $mysoc->logo : '';
if (getDolGlobalString('ONLINE_SIGN_LOGO')) {
	$logosmall = getDolGlobalString('ONLINE_SIGN_LOGO');
}
$urllogo = '';
if (!empty($logosmall) && is_readable($conf->mycompany->dir_output.'/logos/thumbs/'.$logosmall)) {
	$urllogo = DOL_URL_ROOT.'/viewimage.php?modulepart=mycompany&amp;entity='.$conf->entity.'&amp;file='.urlencode('logos/thumbs/'.$logosmall);
} elseif (!empty($logo) && is_readable($conf->mycompany->dir_output.'/logos/'.$logo)) {
	$urllogo = DOL_URL_ROOT.'/viewimage.php?modulepart=mycompany&amp;entity='.$conf->entity.'&amp;file='.urlencode('logos/'.$logo);
}
if ($urllogo) {
	print '<tr><td>';
	print '<div class="backgreypublicpayment">';
	print '<div class="logopublicpayment"><img id="dolpaymentlogo" src="'.$urllogo.'"></div>';
	if (!getDolGlobalString('MAIN_HIDE_POWERED_BY')) {
		print '<div class="poweredbypublicpayment opacitymedium right"><a class="poweredbyhref" href="https://www.dolibarr.org?utm_medium=website&utm_source=poweredby" target="dolibarr" rel="noopener">'.$langs->trans('PoweredBy').'<br><img class="poweredbyimg" src="'.DOL_URL_ROOT.'/theme/dolibarr_logo.svg" width="80px"></a></div>';
	}
	print '</div>';
	print '</td></tr>'."\n";
}

print '<tr><td class="textpublicpayment"><br><strong>'.$langs->trans('WelcomeOnOnlineSignaturePageAttestation', $creditor).'</strong></td></tr>'."\n";
print '<tr><td class="textpublicpayment opacitymedium">'.$langs->trans('ThisScreenAllowsYouToSignDocFromAttestation', $creditor).'<br><br></td></tr>'."\n";
print '<tr><td>';
print '<table class="liste tablepublicpayment centpercent">'."\n";
if (!is_object($object)) {
	foreach ($errors as $errorKey) {
		print '<tr><td class="center" colspan="2"><br><div class="warning">'.dol_escape_htmltag($langs->trans($errorKey)).'</div></td></tr>'."\n";
	}
} else {
	print '<tr class="CTableRow2"><td class="CTableRow2">'.$langs->trans('Proposer').'</td><td class="CTableRow2">'.img_picto('', 'company', 'class="pictofixedwidth"').'<b>'.dol_escape_htmltag($creditor).'</b></td></tr>'."\n";
	print '<tr class="CTableRow2"><td class="CTableRow2">'.$langs->trans('ThirdParty').'</td><td class="CTableRow2">'.img_picto('', 'company', 'class="pictofixedwidth"').'<b>'.dol_escape_htmltag($thirdpartyName !== '' ? $thirdpartyName : $langs->trans('AttestationNotProvided')).'</b></td></tr>'."\n";
	print '<tr class="CTableRow2"><td class="CTableRow2">'.$langs->trans('Designation').'</td><td class="CTableRow2"><b>'.$langs->trans('SignaturePowerplantpvAttestationRef', $object->ref).'</b><br><span class="opacitymedium">'.dol_escape_htmltag($typeLabel).'</span>';
	$downloadUrl = $_SERVER['PHP_SELF'].'?action=download&ref='.urlencode($ref).'&securekey='.urlencode($securekey).'&entity='.(int) $entity;
	print '<br><a href="'.dol_escape_htmltag($downloadUrl).'" target="_blank" rel="noopener">'.img_mime('document.pdf', '').($message === 'signed' || (int) $object->status === PowerPlantPVAttestation::STATUS_SIGNED ? $langs->trans('DownloadSignedDocument') : $langs->trans('DownloadDocument')).'</a>';
	print '</td></tr>'."\n";
}
print '</table>'."\n";
print '</td></tr>'."\n";
print '<tr><td class="center">';

$canSign = is_object($object) && in_array((int) $object->status, array(PowerPlantPVAttestation::STATUS_VALIDATED, PowerPlantPVAttestation::STATUS_PENDING_SIGNATURE), true);
if ($action === 'dosign' && empty($cancel) && $canSign) {
	print '<div class="tablepublicpayment">';
	print '<input type="text" class="paddingleftonly marginleftonly paddingrightonly marginrightonly marginbottomonly borderbottom" id="name" placeholder="'.$langs->trans('Lastname').'" autofocus>';
	print '<div id="signature" style="border:solid;"></div>';
	print '</div>';
	print '<input type="button" class="small noborderbottom cursorpointer buttonreset" id="clearsignature" value="'.$langs->trans('ClearSignature').'">';
	print '<div>';
	print '<input type="button" class="button marginleftonly marginrightonly" id="signbutton" value="'.$langs->trans('Sign').'">';
	print '<input type="submit" class="button butActionDelete marginleftonly marginrightonly" name="cancel" value="'.$langs->trans('Cancel').'">';
	print '</div>';
	print '<script language="JavaScript" type="text/javascript" src="'.DOL_URL_ROOT.'/includes/jquery/plugins/jSignature/jSignature.js"></script>
	<script type="text/javascript">
	$(document).ready(function() {
		$("#signature").jSignature({ color:"#000", lineWidth:0, '.(empty($conf->dol_optimize_smallscreen) ? '' : 'width: 280, ').'height: 180});
		$("#signature").on("change", function() {
			$("#clearsignature").css("display", "");
			$("#signbutton").attr("disabled", false);
			if (!$._data($("#signbutton")[0], "events")) {
				$("#signbutton").on("click", function() {
					document.body.style.cursor = "wait";
					var signature = $("#signature").jSignature("getData", "image");
					var name = document.getElementById("name").value;
					$.ajax({
						type: "POST",
						url: "'.dol_buildpath('/powerplantpv/ajax/attestation_online_sign.php', 1).'",
						dataType: "text",
						data: {
							"action": "importSignature",
							"token": "'.newToken().'",
							"signaturebase64": signature,
							"onlinesignname": name,
							"ref": "'.dol_escape_js($ref).'",
							"securekey": "'.dol_escape_js($securekey).'",
							"entity": "'.((int) $entity).'"
						},
						success: function(response) {
							if (response.trim() === "success") {
								window.location.replace("'.dol_escape_js($_SERVER['PHP_SELF'].'?ref='.urlencode($ref).'&message=signed&securekey='.urlencode($securekey).'&entity='.(int) $entity).'");
							} else {
								document.body.style.cursor = "auto";
								console.error(response);
								alert("'.dol_escape_js($langs->transnoentities('Error')).'");
							}
						},
						error: function(response) {
							document.body.style.cursor = "auto";
							console.error(response);
							alert("'.dol_escape_js($langs->transnoentities('Error')).' " + response.responseText);
						}
					});
				});
			}
		});
		$("#clearsignature").on("click", function() {
			$("#signature").jSignature("clear");
			$("#signbutton").attr("disabled", true);
		});
		$("#signbutton").attr("disabled", true);
	});
	</script>';
} elseif (is_object($object) && (int) $object->status === PowerPlantPVAttestation::STATUS_SIGNED) {
	print '<br>'.img_picto('', 'check', '', false, 0, 0, '', 'size2x').'<br>';
	print '<span class="ok">'.$langs->trans($message === 'signed' ? 'AttestationOnlineSignatureSigned' : 'AttestationOnlineSignatureAlreadySigned').'</span>';
} elseif ($canSign) {
	print '<input type="submit" class="butAction small wraponsmartphone marginbottomonly marginleftonly marginrightonly reposition" value="'.$langs->trans('SignAttestation').'">';
} elseif (is_object($object)) {
	print '<br><span class="warning">'.$langs->trans('AttestationSignatureNotAllowed').'</span>';
}

print '</td></tr>'."\n";
print '</table>'."\n";
print '</form>'."\n";
print '</div>'."\n";
print '<br>';

htmlPrintOnlineFooter($mysoc, $langs);

llxFooter('', 'public');
$db->close();
