<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

if (PHP_SAPI !== 'cli' || !isset($argv[1])) {
	exit(1);
}

define('DOL_DOCUMENT_ROOT', $argv[1]);
require_once __DIR__.'/../class/powerplantpvsupervision.class.php';

function checkSupervision(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

$reportedAt = 1767272400;
$calls = array();
$transport = static function (string $method, string $url, array $headers, string $body) use (&$calls, $reportedAt): array {
	$calls[] = array($method, $url, $headers, $body);
	if (str_ends_with($url, '/auth/token')) {
		checkSupervision($method === 'POST', 'Session creation posts the credential payload');
		checkSupervision(json_decode($body, true) === array('grant_type' => 'system', 'app_key' => 'test-key', 'app_secret' => 'test-secret'), 'Correct grant payload');
		return array('status' => 200, 'body' => '{"success":true,"data":{"access_token":"fake-bearer","expires_in":500}}');
	}

	checkSupervision(in_array('Authorization: Bearer fake-bearer', $headers, true), 'Reuse the issued bearer token');
	if (str_contains($url, '/sites/getSites?')) {
		return array('status' => 200, 'body' => '{"success":true,"pageEnd":0,"data":[{"siteId":"007","name":"Plant","timeZone":"UTC+01:00"}]}');
	}

	checkSupervision(str_ends_with($url, 'siteIds=007%2C002'), 'Keep leading zeros in packed site IDs');
	return array('status' => 200, 'body' => json_encode(array('success' => true, 'data' => array(
		array('siteId' => '007', 'lastReportedTime' => $reportedAt * 1000, 'status' => 2, 'solarGenerationPower' => 0, 'dailySolarGeneration' => 0),
		array('siteId' => '002', 'lastReportedTime' => $reportedAt * 1000, 'status' => 0),
	)), JSON_THROW_ON_ERROR));
};

$client = new PowerPlantPVAtmoceClient('test-key', 'test-secret', $transport);
$sites = $client->listSites(2);
checkSupervision($sites === array('sites' => array(array('id' => '007', 'name' => 'Plant', 'timezone' => 'UTC+01:00')), 'complete' => false), 'Read a partial page');
checkSupervision(str_ends_with($calls[1][1], '?page=2'), 'Send the requested page');
$readings = $client->latest(array('007', '002'));
checkSupervision(count($calls) === 3, 'One login across discovery and latest reads');
checkSupervision($readings[0]['reported_at'] === $reportedAt, 'Convert milliseconds to seconds');
checkSupervision($readings[0]['power_w'] === 0.0 && $readings[0]['energy_today_kwh'] === 0.0, 'Preserve zero readings');
checkSupervision($readings[1]['power_w'] === null && $readings[1]['energy_today_kwh'] === null, 'Keep missing readings unknown');
checkSupervision(PowerPlantPVSupervision::displayState(null, 15, $reportedAt) === 'PowerPlantPVSupervisionAwaiting', 'Recognise awaiting data');
checkSupervision(PowerPlantPVSupervision::displayState((object) array('reported_at' => $reportedAt, 'device_status' => 2), 15, $reportedAt + 1800) === 'PowerPlantPVSupervisionFault', 'Retain fresh faults');
checkSupervision(PowerPlantPVSupervision::displayState((object) array('reported_at' => $reportedAt, 'device_status' => 2), 15, $reportedAt + 1801) === 'PowerPlantPVSupervisionStale', 'Mark old faults as stale');
checkSupervision(PowerPlantPVSupervision::estimatedCalls(300, 15) === 8928 && PowerPlantPVSupervision::estimatedCalls(301, 15) === 11904, 'Round the number of API batches up');

try {
	$client->latest(array_fill(0, 101, '001'));
	throw new LogicException('Accepted oversized batch');
} catch (InvalidArgumentException $e) {
	checkSupervision($e->getMessage() === 'InvalidAtmoceSiteBatch', 'Reject oversized batch before the request');
}
checkSupervision(count($calls) === 3, 'Validation performed before transport');

foreach (array(401 => 'AtmoceAuthenticationFailed', 429 => 'AtmoceQuotaExceeded', 503 => 'AtmoceRequestFailed') as $status => $error) {
	$client = new PowerPlantPVAtmoceClient('test-key', 'test-secret', static fn () => array(
		'status' => $status,
		'body' => '{"success":false,"reason":"provider text must not be reused"}',
	));
	$client->reuseSession('fake-bearer', time() + 300);
	try {
		$client->listSites();
		throw new LogicException('Accepted an HTTP error');
	} catch (RuntimeException $e) {
		checkSupervision($e->getMessage() === $error, 'Return the expected technical error without provider text');
	}
}

print "ATMOCE transport, normalization, budget and status checks passed\n";
