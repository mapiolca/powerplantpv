<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file ajax/thirdparty_address.php
 * \ingroup powerplantpv
 * \brief Return the address of an accessible third party for the creation form.
 */

define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOTOKENRENEWAL', 1);

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

/** @var User $user */
/** @var DoliDB $db */
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';

top_httphead('application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (!isModEnabled('powerplantpv') || !isModEnabled('societe')
	|| !$user->hasRight('powerplantpv', 'powerplant', 'read')
	|| !$user->hasRight('powerplantpv', 'powerplant', 'write')
	|| !$user->hasRight('societe', 'lire')) {
	http_response_code(403);
	exit;
}

$thirdPartyId = GETPOSTINT('fk_soc', 1);
if ($thirdPartyId <= 0) {
	http_response_code(400);
	exit;
}

$thirdParty = new Societe($db);
if ($thirdParty->fetch($thirdPartyId) <= 0
	|| !in_array((int) $thirdParty->entity, array_map('intval', explode(',', getEntity('societe'))), true)
	|| !checkUserAccessToObject($user, array('societe'), $thirdParty, 'societe')) {
	http_response_code(404);
	exit;
}

print json_encode(array(
	'address' => (string) $thirdParty->address,
	'zip' => (string) $thirdParty->zip,
	'town' => (string) $thirdParty->town,
	'fk_country' => (int) $thirdParty->country_id,
));
$db->close();
