<?php
/* Copyright (C) 2026  Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
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
 */

/**
 * \file       htdocs/custom/powerplantpv/attestation_signature.php
 * \ingroup    powerplantpv
 * \brief      Public online signature page for PowerPlantPV attestations
 */

$ispublicsignature = (!empty($_GET['securekey']) || !empty($_POST['securekey']) || !empty($_GET['message']));
if ($ispublicsignature) {
	if (!defined('NOLOGIN')) {
		define('NOLOGIN', 1);
	}
	if (!defined('NOCSRFCHECK')) {
		define('NOCSRFCHECK', 1);
	}
	if (!defined('NOIPCHECK')) {
		define('NOIPCHECK', 1);
	}
	if (!defined('NOBROWSERNOTIF')) {
		define('NOBROWSERNOTIF', 1);
	}
	if (!defined('NOREQUIREMENU')) {
		define('NOREQUIREMENU', 1);
	}
	$entityforpublic = isset($_GET['entity']) ? $_GET['entity'] : (isset($_POST['entity']) ? $_POST['entity'] : 1);
	if (is_numeric($entityforpublic) && !defined('DOLENTITY')) {
		define('DOLENTITY', (int) $entityforpublic);
	}
}

$res = 0;
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

dol_include_once('/powerplantpv/class/powerplantpvattestation.class.php');
dol_include_once('/powerplantpv/class/powerplantpvattestationtypes.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_attestation.lib.php');

global $conf, $db, $langs, $mysoc, $user;

$langs->loadLangs(array('main', 'companies', 'other', 'bills', 'orders', 'propal', 'errors', 'powerplantpv@powerplantpv'));

$action = GETPOST('action', 'aZ09');
$source = GETPOST('source', 'alpha');
$ref = GETPOST('ref', 'alphanohtml');
$securekey = GETPOST('securekey', 'restricthtml');
$message = GETPOST('message', 'aZ09');
$signaturedata = GETPOST('signature_data', 'restricthtml');
$signername = GETPOST('signer_name', 'nohtml');
$entity = GETPOSTINT('entity');
if (empty($entity)) {
	$entity = (int) $conf->entity;
}

/**
 * Return a forbidden response suitable for the public online signature page.
 *
 * @param string $message Error message
 * @return void
 */
function powerplantpvAttestationOnlineAccessForbidden($message = '')
{
	if (function_exists('httponly_accessforbidden')) {
		httponly_accessforbidden($message);
		return;
	}
	accessforbidden($message);
}

/**
 * Fetch an attestation by reference and entity for public signature.
 *
 * @param DoliDB $db     Database handler
 * @param string $ref    Attestation reference
 * @param int    $entity Entity id
 * @return PowerPlantPVAttestation|null
 */
function powerplantpvAttestationFetchByRefForOnlineSignature($db, $ref, $entity)
{
	$sql = "SELECT rowid";
	$sql .= " FROM ".MAIN_DB_PREFIX."powerplantpv_attestation";
	$sql .= " WHERE ref = '".$db->escape($ref)."'";
	$sql .= " AND entity = ".((int) $entity);

	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__METHOD__.' sql error '.$db->lasterror(), LOG_ERR);
		return null;
	}

	$obj = $db->fetch_object($resql);
	$db->free($resql);
	if (!$obj) {
		return null;
	}

	$attestation = new PowerPlantPVAttestation($db);
	if ($attestation->fetch((int) $obj->rowid) <= 0) {
		return null;
	}

	return $attestation;
}

/**
 * Build the current signature URL parameters.
 *
 * @param string $source    Online signature source
 * @param string $ref       Object reference
 * @param string $securekey Secure key
 * @param int    $entity    Entity id
 * @param string $message   Optional message
 * @return string
 */
function powerplantpvAttestationOnlineSignatureParam($source, $ref, $securekey, $entity, $message = '')
{
	$param = 'source='.urlencode($source);
	$param .= '&ref='.urlencode($ref);
	$param .= '&securekey='.urlencode($securekey);
	$param .= '&entity='.((int) $entity);
	if ($message !== '') {
		$param .= '&message='.urlencode($message);
	}
	return $param;
}

if (!isModEnabled('powerplantpv')) {
	powerplantpvAttestationOnlineAccessForbidden();
}
if (function_exists('powerplantpvIsAttestationEnabled') && !powerplantpvIsAttestationEnabled()) {
	powerplantpvAttestationOnlineAccessForbidden();
}
if (!getDolGlobalInt('POWERPLANTPV_ATTESTATION_ALLOW_ONLINESIGN', 1)) {
	powerplantpvAttestationOnlineAccessForbidden();
}
if ($source !== powerplantpvAttestationGetOnlineSignatureSource() || $ref === '' || $securekey === '') {
	powerplantpvAttestationOnlineAccessForbidden();
}

$object = powerplantpvAttestationFetchByRefForOnlineSignature($db, $ref, $entity);
if (!is_object($object)) {
	powerplantpvAttestationOnlineAccessForbidden($langs->trans('ErrorRecordNotFound'));
}

if (!powerplantpvAttestationVerifyOnlineSignatureSecureKey($object, $securekey)) {
	powerplantpvAttestationOnlineAccessForbidden();
}

if (!in_array((int) $object->status, array(PowerPlantPVAttestation::STATUS_VALIDATED, PowerPlantPVAttestation::STATUS_PENDING_SIGNATURE, PowerPlantPVAttestation::STATUS_SIGNED), true)) {
	powerplantpvAttestationOnlineAccessForbidden($langs->trans('AttestationSignatureNotAllowed'));
}

if (method_exists($object, 'fetch_thirdparty')) {
	$object->fetch_thirdparty();
}

$form = new Form($db);
$error = 0;

if ($action === 'dosign' && (int) $object->status !== PowerPlantPVAttestation::STATUS_SIGNED) {
	$signaturefile = powerplantpvAttestationStoreSignatureImage($object, $signaturedata);
	if (empty($signaturefile)) {
		$error++;
		setEventMessages($langs->trans('AttestationSignatureRequired'), null, 'errors');
	}

	$signuser = new User($db);
	$signuser->id = 0;

	if (!$error) {
		$oldstatus = $object->status;
		$object->status = PowerPlantPVAttestation::STATUS_SIGNED;
		$object->date_signature = dol_now();
		$object->signature_file = $signaturefile;
		if ($signername !== '') {
			$object->online_sign_name = $signername;
		}

		$model = !empty($object->model_pdf) ? $object->model_pdf : '';
		$result = $object->generateDocument($model, $langs);
		$object->status = $oldstatus;
		if ($result < 0) {
			$error++;
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}

	if (!$error) {
		$rootdir = powerplantpvAttestationGetDocumentRootDir((int) $object->entity);
		$sourcefile = '';
		if (!empty($object->last_main_doc)) {
			$sourcefile = $rootdir.'/'.$object->last_main_doc;
		}
		if (empty($sourcefile) || !dol_is_file($sourcefile)) {
			$sourcefile = powerplantpvAttestationGetDocumentUploadDir($object).'/'.dol_sanitizeFileName($object->ref).'.pdf';
		}
		if (!dol_is_file($sourcefile)) {
			$error++;
			setEventMessages($langs->trans('FileNotFound'), null, 'errors');
		}
	}

	if (!$error) {
		$datekey = dol_print_date(dol_now(), '%Y%m%d%H%M%S');
		$relativepath = powerplantpvAttestationGetDocumentRelativePath($object);
		$signedrelative = $relativepath.'/'.dol_sanitizeFileName($object->ref).'_signed-'.$datekey.'.pdf';
		$signedfile = $rootdir.'/'.$signedrelative;
		dol_mkdir(dirname($signedfile));

		if (!dol_copy($sourcefile, $signedfile, 0, 0)) {
			$error++;
			setEventMessages($langs->trans('ErrorFailToCopyFile'), null, 'errors');
		}
	}

	if (!$error) {
		$signaturehash = hash_file('sha256', $signedfile);
		if (method_exists($object, 'indexFile')) {
			$object->indexFile($signedfile, 1);
		}
		$result = $object->sign($signuser, $signaturefile, $signedrelative, $signaturehash, $signername);
		if ($result <= 0) {
			$error++;
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}

	if (!$error) {
		$url = $_SERVER['PHP_SELF'].'?'.powerplantpvAttestationOnlineSignatureParam($source, $ref, $securekey, $entity, 'signed');
		header('Location: '.$url);
		exit;
	}
}

$conf->dol_hide_topmenu = 1;
$conf->dol_hide_leftmenu = 1;

$head = '';
if (getDolGlobalString('MAIN_SIGN_CSS_URL')) {
	$head .= '<link rel="stylesheet" type="text/css" href="'.getDolGlobalString('MAIN_SIGN_CSS_URL').'">'."\n";
}
$head .= '<script src="'.DOL_URL_ROOT.'/includes/jquery/plugins/jSignature/jSignature.min.js"></script>'."\n";

llxHeader($head, $langs->trans('OnlineSignature'), '', '', 0, 0, array(), array(), '', 'onlinepaymentbody');

if (function_exists('htmlPrintOnlineHeader')) {
	htmlPrintOnlineHeader($mysoc, $langs, 1, '', 'ONLINE_SIGN_IMAGE_PUBLIC_INTERFACE', 'ONLINE_SIGN_LOGO', 'ONLINE_SIGN_LOGO');
}

print '<div class="center">';
print '<div class="inline-block login_vertical_align_top onlinepaymentbody">';
print '<div class="tagtable centpercent">';

print '<div class="tagtr">';
print '<div class="tagtd minwidth150 right">'.$langs->trans('Creditor').'</div>';
print '<div class="tagtd left"><strong>'.dol_escape_htmltag($mysoc->name).'</strong></div>';
print '</div>';

if (!empty($object->thirdparty) && is_object($object->thirdparty)) {
	print '<div class="tagtr">';
	print '<div class="tagtd minwidth150 right">'.$langs->trans('ThirdParty').'</div>';
	print '<div class="tagtd left"><strong>'.dol_escape_htmltag($object->thirdparty->name).'</strong></div>';
	print '</div>';
}

print '<div class="tagtr">';
print '<div class="tagtd minwidth150 right">'.$langs->trans('Reference').'</div>';
print '<div class="tagtd left"><strong>'.dol_escape_htmltag($object->ref).'</strong></div>';
print '</div>';

print '<div class="tagtr">';
print '<div class="tagtd minwidth150 right">'.$langs->trans('Designation').'</div>';
print '<div class="tagtd left">'.$langs->trans('SignaturePowerplantpvAttestationRef', dol_escape_htmltag($object->ref)).'</div>';
print '</div>';

if (method_exists($object, 'getLastMainDocLink')) {
	$doclink = $object->getLastMainDocLink(powerplantpvAttestationGetDocumentModulePart());
	if (!empty($doclink)) {
		print '<div class="tagtr">';
		print '<div class="tagtd minwidth150 right">'.$langs->trans('Document').'</div>';
		print '<div class="tagtd left">'.$doclink.'</div>';
		print '</div>';
	}
}

print '</div>';

if ($message === 'signed' || (int) $object->status === PowerPlantPVAttestation::STATUS_SIGNED) {
	print '<br><div class="ok maxwidth750 center">'.$langs->trans('AttestationOnlineSignatureDone').'</div>';
} else {
	print '<br><form method="POST" id="attestation-sign-form" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="action" value="dosign">';
	print '<input type="hidden" name="source" value="'.dol_escape_htmltag($source).'">';
	print '<input type="hidden" name="ref" value="'.dol_escape_htmltag($ref).'">';
	print '<input type="hidden" name="securekey" value="'.dol_escape_htmltag($securekey).'">';
	print '<input type="hidden" name="entity" value="'.((int) $entity).'">';
	print '<input type="hidden" name="signature_data" id="signature_data" value="">';

	print '<div class="tagtable centpercent">';
	print '<div class="tagtr">';
	print '<div class="tagtd minwidth150 right">'.$langs->trans('Name').'</div>';
	print '<div class="tagtd left"><input type="text" class="flat minwidth300" name="signer_name" value="'.dol_escape_htmltag($signername).'"></div>';
	print '</div>';
	print '</div>';

	print '<br><div class="center">';
	print '<div id="signature" class="signature-zone" style="background:#fff;border:1px solid #bbb;height:180px;max-width:650px;margin:0 auto;"></div>';
	print '</div>';
	print '<br><div class="center">';
	print '<input type="button" class="button button-cancel" id="signature-clear" value="'.$langs->trans('Clear').'">';
	print ' ';
	print '<input type="submit" class="button button-save" value="'.$langs->trans('Sign').'">';
	print '</div>';
	print '</form>';

	print '<script>
	jQuery(function() {
		var signature = jQuery("#signature");
		if (typeof signature.jSignature === "function") {
			signature.jSignature({height: 180, width: "100%"});
		}
		jQuery("#signature-clear").on("click", function() {
			if (typeof signature.jSignature === "function") {
				signature.jSignature("reset");
			}
		});
		jQuery("#attestation-sign-form").on("submit", function() {
			if (typeof signature.jSignature !== "function") {
				return false;
			}
			var data = signature.jSignature("getData", "image");
			jQuery("#signature_data").val(data[0] + "," + data[1]);
			return true;
		});
	});
	</script>';
}

print '</div>';
print '</div>';

if (function_exists('htmlPrintOnlineFooter')) {
	htmlPrintOnlineFooter($mysoc, $langs);
}

llxFooter('', 'public');
$db->close();
