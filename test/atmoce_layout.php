<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

if (PHP_SAPI !== 'cli' || !isset($argv[1])) {
	exit(1);
}
define('DOL_DOCUMENT_ROOT', $argv[1]);
require_once __DIR__.'/../class/powerplantpvatmoceclient.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
// The isolated test uses no other Dolibarr modules or application bootstrap.
function dol_include_once($path)
{
	return false;
}
require_once __DIR__.'/../class/powerplantpvatmocelayout.class.php';

function checkLayout(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

$client = new PowerPlantPVAtmoceClient('key', 'secret', static function ($method, $url) {
	checkLayout($method === 'GET' && str_ends_with($url, '/device/getDevicesBySite?siteIds=07'), 'Read equipment for the linked site');
	return array('status' => 200, 'body' => json_encode(array('success' => true, 'data' => array(
		array('siteId' => '07', 'deviceSN' => '00123', 'deviceType' => 'micro_inverter', 'deviceMode' => 'MI-400'),
		array('siteId' => '07', 'deviceSN' => '00123', 'deviceType' => 'micro_inverter', 'deviceMode' => 'MI-400'),
		array('siteId' => '07', 'deviceSN' => '00456', 'deviceType' => 'gateway', 'deviceMode' => 'MG'),
	)), JSON_THROW_ON_ERROR));
});
$client->reuseSession('fake-token', time() + 300);
checkLayout($client->microinverters('07') === array(array('serial' => '00123', 'model' => 'MI-400')), 'Keep only distinct microinverters with their exact serials');

$client = new PowerPlantPVAtmoceClient('key', 'secret', static fn () => array(
	'status' => 200,
	'body' => '{"success":true,"data":[{"siteId":"08","deviceSN":"00123","deviceType":"micro_inverter"}]}',
));
$client->reuseSession('fake-token', time() + 300);
try {
	$client->microinverters('07');
	throw new LogicException('Accepted an unlinked site');
} catch (RuntimeException $e) {
	checkLayout($e->getMessage() === 'InvalidAtmoceResponse', 'Reject equipment reported for another site');
}

checkLayout(PowerPlantPVAtmoceLayout::parsePositions('Toiture Est B4, toiture sud c7') === array('TOITURE EST B4', 'TOITURE SUD C7'), 'Normalize positions without losing multi-panel placements');
foreach (array('a1, A1' => 'PowerPlantPVLayoutPositionConflict', '../A1' => 'PowerPlantPVLayoutInvalidPosition') as $positions => $error) {
	try {
		PowerPlantPVAtmoceLayout::parsePositions($positions);
		throw new LogicException('Accepted invalid positions');
	} catch (RuntimeException $e) {
		checkLayout($e->getMessage() === $error, 'Return the expected position validation error');
	}
}
print "ATMOCE inventory and layout validation checks passed\n";
