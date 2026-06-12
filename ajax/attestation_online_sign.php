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
 * \file		ajax/attestation_online_sign.php
 * \ingroup		powerplantpv
 * \brief		Ajax fallback endpoint for attestation online signatures.
 */

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOLOGIN')) {
	define('NOLOGIN', '1');
}
if (!defined('NOIPCHECK')) {
	define('NOIPCHECK', '1');
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', '1');
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
if (!$res && file_exists('../../main.inc.php')) {
	$res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = @include '../../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/powerplantpv/lib/powerplantpv_attestation.lib.php');

$langs->loadLangs(array('main', 'errors', 'powerplantpv@powerplantpv'));

$action = GETPOST('action', 'aZ09');
$signature = GETPOST('signaturebase64');
$ref = GETPOST('ref', 'alphanohtml');
$securekey = GETPOST('securekey');
$onlineSignName = GETPOST('onlinesignname', 'alphanohtml');
$entity = GETPOSTINT('entity') ?: (defined('DOLENTITY') ? (int) DOLENTITY : (int) $conf->entity);

top_httphead();

$response = 'error';
$error = 0;
$errors = array();
$object = null;
if (!isModEnabled('powerplantpv') || !getDolGlobalInt('POWERPLANTPV_ATTESTATION_ENABLE', 1)) {
	$error++;
	$response = 'error module_disabled';
} elseif ($action !== 'importSignature') {
	$error++;
	$response = 'error bad_action';
} else {
	$object = powerplantpvAttestationFetchForOnlineSignature($ref, $entity, $securekey, $errors);
	if (!is_object($object)) {
		$error++;
		$response = 'error bad_signature_link';
	}
}
if (!$error && is_object($object)) {
	$result = powerplantpvAttestationStoreAlternativeOnlineSignature($object, $signature, $onlineSignName, $user, $langs);
	if ($result > 0) {
		$response = 'success';
	} else {
		$error++;
		$response = 'error '.(!empty($object->error) ? $langs->transnoentities($object->error) : $langs->transnoentities('Error'));
	}
}

if ($error) {
	http_response_code(501);
}

print $response;
$db->close();
