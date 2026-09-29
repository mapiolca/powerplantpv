<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Run with: php test/attestation_power_units.php /path/to/dolibarr/htdocs
 * Uses native core classes/helpers with an in-memory SQL fixture, without main.inc.php,
 * a database connection, PDF file generation or changes to an installed instance.
 */
if (PHP_SAPI !== 'cli') {
	exit(1);
}
$coreRoot = $argv[1] ?? dirname(__DIR__, 2).'/dolibarr/htdocs';
if (!is_file($coreRoot.'/core/lib/functions.lib.php')) {
	fwrite(STDERR, "Pass the Dolibarr htdocs directory as the first argument.\n");
	exit(1);
}
define('DOL_DOCUMENT_ROOT', $coreRoot);
define('DOL_URL_ROOT', '');
define('MAIN_DB_PREFIX', 'unit_test_');
if (is_file(DOL_DOCUMENT_ROOT.'/version.inc.php')) {
	require_once DOL_DOCUMENT_ROOT.'/version.inc.php';
} else {
	// Older core versions define their version in filefunc.inc.php; do not bootstrap an instance.
	$versionSource = file_get_contents(DOL_DOCUMENT_ROOT.'/filefunc.inc.php');
	if (!is_string($versionSource) || !preg_match('/define\([\'"]DOL_VERSION[\'"],\s*[\'"]([^\'"]+)/', $versionSource, $versionMatch)) {
		throw new RuntimeException('Cannot identify the Dolibarr core version.');
	}
	define('DOL_VERSION', $versionMatch[1]);
}
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';

$conf = (object) array(
	'entity' => 1,
	'global' => (object) array(
		'MAIN_MAX_DECIMALS_UNIT' => 8,
		'MAIN_MAX_DECIMALS_TOT' => 8,
		'MAIN_MAX_DECIMALS_SHOWN' => 8,
	),
	'file' => (object) array('dol_document_root' => array('main' => DOL_DOCUMENT_ROOT, 'module' => dirname(__DIR__, 2))),
);
$langs = new Translate('', $conf);
$langs->defaultlang = 'en_US';
$langs->tab_translate = array('SeparatorDecimal' => '.', 'SeparatorThousand' => 'None');

/** SQL fixture: only explicitly supported queries can run; no real database is opened. */
final class AttestationPowerUnitsDatabase
{
	/** @var array<int,object> */
	public array $products = array();
	public bool $failProductQuery = false;
	public ?float $savedInstalledPower = null;

	public function prefix(): string { return MAIN_DB_PREFIX; }
	public function sanitize(string $value): string { return $value; }
	public function escape(string $value): string { return addslashes($value); }

	/** @return ArrayIterator<int,object>|false */
	public function query(string $sql)
	{
		if (strpos($sql, 'SHOW COLUMNS') === 0) {
			return new ArrayIterator(array());
		}
		if (strpos($sql, 'SELECT p.rowid as fk_product') === 0) {
			if ($this->failProductQuery) {
				return false;
			}
			if (!preg_match('/AND p.rowid = (\d+)/', $sql, $matches)) {
				throw new RuntimeException('Expected product lookup: '.$sql);
			}
			$row = $this->products[(int) $matches[1]] ?? null;
			if ($row === null) {
				return new ArrayIterator(array());
			}
			$row = clone $row;
			// A fixture must not return a column omitted by the actual SELECT.
			if (strpos($sql, 'inv.ac_apparent_power') === false) {
				unset($row->ac_apparent_power);
			}
			return new ArrayIterator(array($row));
		}
		if (strpos($sql, 'SELECT c.fk_product, c.qty') === 0) {
			return new ArrayIterator(array((object) array('fk_product' => 450, 'qty' => 20)));
		}
		if (strpos($sql, 'SELECT pmax') === 0) {
			return new ArrayIterator(array((object) array('pmax' => '450.00000000')));
		}
		if (preg_match('/SET installed_power = ([0-9.]+)/', $sql, $matches)) {
			$this->savedInstalledPower = (float) $matches[1];
			return new ArrayIterator(array());
		}
		throw new RuntimeException('Unexpected SQL: '.$sql);
	}

	/** @param ArrayIterator<int,object> $result */
	public function num_rows(ArrayIterator $result): int { return $result->count(); }
	/** @param ArrayIterator<int,object> $result */
	public function free(ArrayIterator $result): void {}
	/** @param ArrayIterator<int,object> $result @return object|false */
	public function fetch_object(ArrayIterator $result)
	{
		if (!$result->valid()) {
			return false;
		}
		$row = $result->current();
		$result->next();
		return $row;
	}
}

$db = new AttestationPowerUnitsDatabase();
require_once dirname(__DIR__).'/lib/powerplantpv_attestation.lib.php';
require_once dirname(__DIR__).'/lib/powerplantpv_powerplant.lib.php';
foreach (array('bridage_dynamique', 'bridage_statique', 'reglage_max_freq') as $model) {
	require_once dirname(__DIR__).'/core/modules/attestation/doc/pdf_attestation_'.$model.'.modules.php';
}

/** Build unique product references so resolver caching remains representative. */
function equipmentFixture(?string $maximum, ?string $nominal, ?string $apparent, string $category = 'ONDULE'): PowerPlantPVAttestationEquipmentLine
{
	global $db;
	$id = count($db->products) + 1;
	$db->products[$id] = (object) array(
		'fk_product' => $id, 'product_ref' => 'PRODUCT-'.$id, 'product_label' => $category,
		'category_code' => $category, 'category_label' => $category === 'ONDULE' ? 'Inverter' : 'PV module',
		'categorie_photovoltaique' => 0, 'composition_serial_number' => 'SERIAL-'.$id,
		'imported_serial_number' => null, 'ac_max_power' => $maximum,
		'ac_nominal_power' => $nominal, 'ac_apparent_power' => $apparent,
	);
	return PowerPlantPVAttestationEquipmentLine::fromArray(array('entity' => 1, 'fk_product' => $id));
}

$checks = 0;
$failures = array();
function checkValue($expected, $actual, string $label): void
{
	global $checks, $failures;
	$checks++;
	$equal = is_float($expected) && is_numeric($actual) ? abs($expected - (float) $actual) < 1.0e-10 : $expected === $actual;
	if (!$equal) {
		$failures[] = $label.': expected '.var_export($expected, true).', got '.var_export($actual, true);
	}
}

$first = equipmentFixture('30000', '25000', '33000');
$second = equipmentFixture('500.125', '450', '550.5');
$missing = equipmentFixture('30000', '25000', null);
$zero = equipmentFixture('0', '25000', '0');
$module = equipmentFixture(null, null, null, 'MODULE');
$legacy = PowerPlantPVAttestationEquipmentLine::fromArray(array('equipment_type' => 'INVERTER', 'max_power_kw' => 36));
$specimen = (new ReflectionClass(PowerPlantPVAttestation::class))->newInstanceWithoutConstructor();
$specimen->initAsSpecimen();

foreach (array(
	'maximum' => array($first, 30.0, 33.0),
	'fractional sub-kilo' => array($second, 0.500125, 0.5505),
	'nominal fallback' => array(equipmentFixture(null, '6000', '6600'), 6.0, 6.6),
	'empty maximum' => array(equipmentFixture('', '6000', ''), 6.0, null),
	'zero is valid' => array($zero, 0.0, 0.0),
	'no active to apparent conversion' => array($missing, 30.0, null),
	'no apparent to active conversion' => array(equipmentFixture(null, null, '33000'), null, 33.0),
	'all absent' => array(equipmentFixture(null, null, null), null, null),
	'legacy already in kW' => array($legacy, 36.0, null),
) as $label => $case) {
	$resolved = powerplantpvAttestationResolveEquipmentLine($case[0]);
	checkValue($case[1], $resolved['max_power_kw'], $label.' (kW)');
	checkValue($case[2], $resolved['apparent_power_kva'] ?? null, $label.' (kVA)');
}

$fallbackLine = equipmentFixture(null, '', null);
$fallbackLine->max_power_kw = 36;
checkValue(36.0, powerplantpvAttestationResolveEquipmentLine($fallbackLine)['max_power_kw'], 'linked legacy kW stays in kW');
checkValue(30.0, powerplantpvAttestationResolveEquipmentLine($first)['max_power_kw'], 'cached result is not converted twice');
$db->failProductQuery = true;
$failedLine = equipmentFixture('30000', '25000', '33000');
checkValue(null, powerplantpvAttestationResolveEquipmentLine($failedLine)['apparent_power_kva'] ?? null, 'query failure has no apparent power');
$db->failProductQuery = false;
$absentLine = PowerPlantPVAttestationEquipmentLine::fromArray(array('entity' => 1, 'fk_product' => 9999));
checkValue(null, powerplantpvAttestationResolveEquipmentLine($absentLine)['apparent_power_kva'] ?? null, 'missing product has no apparent power');

// The existing PV calculation already converts Wc to kWc before attestation rendering.
$plant = (object) array('id' => 1, 'entity' => 1, 'installed_power' => null);
checkValue(1, powerplantRecalculateInstalledPower($plant), 'PV calculation succeeds');
checkValue(9.0, $plant->installed_power, '20 modules of 450 Wc give 9 kWc');
checkValue(9.0, $db->savedInstalledPower, 'stored plant power is already in kWc');

foreach (array('bridage_dynamique', 'bridage_statique', 'reglage_max_freq') as $model) {
	$reflection = new ReflectionClass('pdf_attestation_'.$model);
	$pdfModel = $reflection->newInstanceWithoutConstructor();
	$sum = $reflection->getMethod('getInverterPower');
	$sum->setAccessible(true);
	$format = $reflection->getMethod('formatPower');
	$format->setAccessible(true);
	$getPlantValue = $reflection->getMethod('getPowerPlantValue');
	$getPlantValue->setAccessible(true);
	foreach (array(
		'multiple inverters' => array(array($first, $second, $module), 33.5505),
		'repeated product units' => array(array($first, $first), 66.0),
		'zero' => array(array($zero), 0.0),
		'no equipment' => array(array(), ''),
		'no inverter' => array(array($module), ''),
		'missing apparent power' => array(array($missing), ''),
		'incomplete total' => array(array($first, $missing), ''),
		'legacy kW cannot mean kVA' => array(array($legacy), ''),
		'query failure prevents partial total' => array(array($first, $failedLine), ''),
		'missing product prevents partial total' => array(array($first, $absentLine), ''),
		'specimen already in kVA' => array($specimen->lines, 36.0),
	) as $label => $case) {
		$attestation = (object) array('lines' => $case[0]);
		checkValue($case[1], $sum->invoke($pdfModel, $attestation), $model.' '.$label);
	}
	checkValue(price(33.5505).' kVA', $format->invoke($pdfModel, $sum->invoke($pdfModel, (object) array('lines' => array($first, $second))), 'kVA'), $model.' formatted apparent power');
	checkValue(price(9).' kWc', $format->invoke($pdfModel, $getPlantValue->invoke($pdfModel, $plant, 'installed_power'), 'kWc'), $model.' no double conversion of installed power');
	checkValue(price(36).' kW', $format->invoke($pdfModel, 36, 'kW'), $model.' manual export limit is already in kW');
}

foreach ($failures as $failure) {
	fwrite(STDERR, 'FAIL '.$failure."\n");
}
echo $checks.' checks, '.count($failures).' failures; PHP '.PHP_VERSION.', Dolibarr '.DOL_VERSION." (simulated SQL, native helpers).\n";
exit(count($failures) ? 1 : 0);
