<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

// Run with a Dolibarr source tree: php -d extension=mysqli test/report_plant_completion.php CORE_HTDOCS
if (PHP_SAPI !== 'cli' || !is_file(($argv[1] ?? '').'/core/lib/functions.lib.php')) {
	exit(1);
}

define('DOL_DOCUMENT_ROOT', realpath($argv[1]));
define('MAIN_DB_PREFIX', 'completion_test_');
define('DOL_URL_ROOT', '');
if (is_file(DOL_DOCUMENT_ROOT.'/version.inc.php')) {
	require_once DOL_DOCUMENT_ROOT.'/version.inc.php';
} else {
	$source = file_get_contents(DOL_DOCUMENT_ROOT.'/filefunc.inc.php');
	if (!preg_match('/define\([\'"]DOL_VERSION[\'"],\s*[\'"]([^\'"]+)/', $source, $matches)) {
		throw new RuntimeException('Unknown Dolibarr revision.');
	}
	define('DOL_VERSION', $matches[1]);
}
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/db/Database.interface.php';
require_once DOL_DOCUMENT_ROOT.'/core/db/DoliDB.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/db/mysqli.class.php';
require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
$conf = (object) array('file' => (object) array('dol_document_root' => array('main' => DOL_DOCUMENT_ROOT)));
require_once __DIR__.'/../class/powerplantpvreportgeneratedbase.class.php';
require_once __DIR__.'/../class/powerplantpvreport.class.php';
require_once __DIR__.'/../class/powerplantpvreportplantcompletion.class.php';
require_once __DIR__.'/../class/powerplantpvreportbuilder.class.php';

$db = new class extends DoliDBMysqli {
	public function __construct() {}
};
$plant = (new ReflectionClass(PowerPlant::class))->newInstanceWithoutConstructor();
$plant->prm_pdl_number = null;
$plant->connection_type = '';
$service = new PowerPlantPVReportPlantCompletion($db);
$catalog = array(
	10 => (object) array('code' => 'MODULE', 'ref' => 'PV10', 'label' => 'Module 10', 'pmax' => 430),
	11 => (object) array('code' => 'MODULE', 'ref' => 'PV11', 'label' => 'Module 11', 'pmax' => 500),
	20 => (object) array('code' => 'ONDULE', 'ref' => 'INV20', 'label' => 'Inverter 20'),
);
$composition = array('modules' => array(10 => array('qty' => 10.0)), 'used' => array('total' => 4, 'products' => array(10 => 4)),
	'inverters' => array(), 'mppts' => array(), 'strings' => array());
$proposal = array(
	'prm' => '00123456789012',
	'modules' => array(array('product' => '11', 'count' => '12')),
	'inverters' => array(array(
		'key' => 'new-0000000000002001', 'product' => 20, 'equipment_marker' => 'INV-02', 'serial_number' => 'SN-02',
		'mppts' => array(array('number' => 1, 'inputs' => 3, 'strings' => array(
			array('input' => 1, 'product' => 10, 'count' => 6),
			array('input' => 2, 'product' => 11, 'count' => 12),
		))),
	)),
);

$checks = 0;
function completionCheck(bool $ok, string $message): void
{
	$GLOBALS['checks']++;
	if (!$ok) {
		throw new RuntimeException($message);
	}
}

function completionRejects(PowerPlantPVReportPlantCompletion $service, PowerPlant $plant, array $proposal, array $catalog, array $composition, string $expected): void
{
	try {
		$service->validate($plant, $proposal, $catalog, $composition);
	} catch (DomainException $e) {
		completionCheck($e->getMessage() === $expected, $e->getMessage().' is not '.$expected);
		return;
	}
	throw new RuntimeException('The invalid proposal was accepted.');
}

$normalized = $service->validate($plant, $proposal, $catalog, $composition);
completionCheck($normalized['prm'] === '00123456789012', 'PRM loses a leading zero.');
completionCheck($normalized['inverters']['new-0000000000002001']['mppts'][1]['strings'][2]['count'] === 12, 'New module stock is unavailable to the string.');
completionCheck($plant->prm_pdl_number === null, 'Validation modified the central record.');

$overflow = $proposal;
$overflow['inverters'][0]['mppts'][0]['strings'][0]['count'] = 7;
completionRejects($service, $plant, $overflow, $catalog, $composition, 'PowerPlantPVModuleAllocationExceeded');
$outside = $proposal;
$outside['inverters'][0]['mppts'][0]['strings'][0]['input'] = 4;
completionRejects($service, $plant, $outside, $catalog, $composition, PowerPlantPVReportPlantCompletion::INVALID);
$malformed = $proposal;
$malformed['modules'] = "11";
completionRejects($service, $plant, $malformed, $catalog, $composition, PowerPlantPVReportPlantCompletion::INVALID);
$unavailable = $proposal;
$unavailable['inverters'][0]['product'] = 199;
completionRejects($service, $plant, $unavailable, $catalog, $composition, PowerPlantPVReportPlantCompletion::INVALID);
$plant->prm_pdl_number = '00123456789013';
completionRejects($service, $plant, $proposal, $catalog, $composition, PowerPlantPVReportPlantCompletion::CONFLICT);

$plant->prm_pdl_number = $proposal['prm'];
$composition['inverters'][42] = (object) array(
	'fk_product' => 20, 'equipment_marker' => 'INV-02', 'equipment_identification' => '', 'serial_number' => 'SN-02',
);
$composition['mppts'][42] = array(1 => 3);
$composition['modules'][11] = array('qty' => 12.0);
$composition['strings'][42][1][1] = (object) array('fk_product' => 10, 'module_count' => 6, 'is_connected' => 1, 'module_power' => null);
$composition['used'] = array('total' => 10, 'products' => array(10 => 10));
$replayed = $service->validate($plant, $proposal, $catalog, $composition);
completionCheck($replayed['inverters']['new-0000000000002001']['resolved'] === 42, 'The existing matching inverter was not reused.');
$composition['strings'][42][1][1]->module_count = 5;
completionRejects($service, $plant, $proposal, $catalog, $composition, PowerPlantPVReportPlantCompletion::CONFLICT);

$builder = new PowerPlantPVReportBuilder($db);
$overlay = new ReflectionMethod($builder, 'overlayPlantCompletion');
$overlay->setAccessible(true);
$equipment = array();
$inputs = array();
$overlay->invokeArgs($builder, array(&$equipment, &$inputs, array(3 => (object) array('data' => $normalized)), $catalog));
completionCheck(count($equipment) === 1 && $equipment[0]['serial_number'] === 'SN-02', 'The draft inverter is missing from the report equipment.');
completionCheck(count($inputs) === 3 && $inputs[0]['is_connected'] === 1 && $inputs[2]['is_connected'] === 0, 'DC topology does not reflect the occupied and open inputs.');
completionCheck($inputs[0]['completion_key'] === 'new-0000000000002001' && $inputs[1]['module_count'] === 12, 'The stable measurement identity or module count is missing.');

$newMeasures = array();
foreach (array('new-0000000000002001', 'new-0000000000002002') as $key) {
	$newMeasures[] = (object) array(
		'fk_powerplant' => 3, 'fk_inverter' => 0, 'mppt_number' => 1, 'pv_input_number' => 1,
		'position' => 0, 'inverter_label' => 'Inverter 20', 'stable_key' => 'plant:3:dc:inverter:'.$key.':mppt:1:pvinput:1',
	);
}
completionCheck(count(PowerPlantPVReportPresentation::groupMeasures($newMeasures)) === 2, 'Identical inverter products are merged into a single group.');
$recorded = new ReflectionMethod($builder, 'hasRecordedDcMeasure');
$recorded->setAccessible(true);
completionCheck($recorded->invoke($builder, array('open_circuit_voltage' => 0.0)), 'A zero observation would be lost when the topology changes.');
completionCheck($recorded->invoke($builder, array('observation' => 'Observed on site')), 'A comment would be lost when the topology changes.');
completionCheck(!$recorded->invoke($builder, array('polarity_checked' => -1)), 'Empty generated measurement would be kept as manual.');

$statuses = PowerPlantPVReport::interventionSignatureStatuses();
foreach (array(false, true) as $technicalRequired) {
	$receiver = $statuses['STATUS_SIGNED_RECEIVER'];
	$sender = $statuses['STATUS_SIGNED_SENDER'];
	$all = $statuses['STATUS_SIGNED_ALL'];
	completionCheck(PowerPlantPVReport::resolveInterventionSignatureStatus($statuses, $receiver, true, $technicalRequired, true) === $all, 'Client-first signature never queues the proposal.');
	$internalFirst = PowerPlantPVReport::resolveInterventionSignatureStatus($statuses, $statuses['STATUS_NO_SIGNATURE'], true, $technicalRequired, true);
	completionCheck($internalFirst === $sender, 'Internal signatures alone queued the proposal.');
	completionCheck(PowerPlantPVReport::resolveInterventionSignatureStatus($statuses, $receiver, $internalFirst === $sender, $technicalRequired, true) === $all, 'Technician-first signature never queues the proposal.');
	completionCheck(PowerPlantPVReport::resolveInterventionSignatureStatus($statuses, $receiver, true, $technicalRequired, false) === ($technicalRequired ? $receiver : $all), 'Required technical signature was not enforced.');
}
printf("Dolibarr %s: %d checks passed.\n", DOL_VERSION, $checks);
