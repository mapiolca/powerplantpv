<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);

// Select the public page's configuration. The grant must independently match this entity.
$requestedEntity = filter_input(INPUT_GET, 'entity', FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
if (is_int($requestedEntity)) {
	define('DOLENTITY', $requestedEntity);
}
$res = 0;
if (is_file(__DIR__.'/../../../main.inc.php')) {
	$res = require __DIR__.'/../../../main.inc.php';
}
if (!$res && is_file(__DIR__.'/../../main.inc.php')) {
	$res = require __DIR__.'/../../main.inc.php';
}
if (!$res) {
	http_response_code(503);
	exit;
}

require_once __DIR__.'/../class/powerplantpvreportevidence.class.php';
$langs->loadLangs(array('main', 'errors', 'powerplantpv@powerplantpv'));
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

if (!isModEnabled('powerplantpv') || !isModEnabled('ficheinter') || !is_int($requestedEntity)) {
	httponly_accessforbidden($langs->trans('PowerPlantPVReportEvidenceLinkUnavailable'), 404, 1);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
	$attachment = (new PowerPlantPVReportEvidence($db))->resolveDownload((string) GETPOST('access_key', 'aZ09', 2), $requestedEntity);
	if ($attachment === null) {
		httponly_accessforbidden($langs->trans('PowerPlantPVReportEvidenceLinkUnavailable'), 404, 1);
	}
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename="preuve"; filename*=UTF-8\'\''.rawurlencode($attachment['name']));
	header('Content-Length: '.$attachment['size']);
	fpassthru($attachment['stream']);
	fclose($attachment['stream']);
	$db->close();
	exit;
}

$title = $langs->trans('Download');
$nonce = getNonce();
header('Content-Type: text/html; charset=UTF-8');
header("Content-Security-Policy: default-src 'none'; script-src 'nonce-".$nonce."'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
print '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
print '<title>'.dol_escape_htmltag($title).'</title></head><body>';
print '<form method="post" id="evidence-download">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="access_key" value="">';
print '<button type="submit" disabled>'.dol_escape_htmltag($title).'</button>';
print '<noscript>'.dol_escape_htmltag($langs->trans('PowerPlantPVReportEvidenceLinkUnavailable')).'</noscript></form>';
print '<script nonce="'.dol_escape_htmltag($nonce).'">
const form = document.getElementById("evidence-download");
const key = location.hash.slice(1);
if (/^[0-9a-f]{64}$/.test(key)) {
	form.elements.namedItem("access_key").value = key;
	form.querySelector("button").disabled = false;
}
</script></body></html>';
$db->close();
