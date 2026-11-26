<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

if (PHP_SAPI !== 'cli' || !isset($argv[1]) || !is_file($argv[1].'/core/lib/functions.lib.php')) {
	exit(1);
}
define('DOL_DOCUMENT_ROOT', realpath($argv[1]));
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once dirname(__DIR__).'/class/powerplantpvresidentialreport.class.php';

function residentialCheck(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

$options = array(
	'plants' => array(1 => 'Centrale A', 2 => 'Centrale B'),
	'products' => array(1 => array(7 => array('maximum' => 2, 'label' => 'MICRO-A')), 2 => array(8 => array('maximum' => null, 'label' => 'MICRO-B'))),
	'placements' => array('battery' => array(12 => 'BAT-A')),
);
$base = array(
	'placements' => array('battery' => array('selected' => '1', 'description' => 'Local technique', 'fk_component' => '12')),
	'strings' => array(array('voltage' => '315,5', 'module_count' => '8')),
);
$roof = array('orientation' => 'SE', 'module_count' => '8', 'row_count' => '2', 'column_count' => '4');
$micro = array('micro' => '1', 'fk_powerplant' => '1', 'quantity' => '2', 'serials' => array('SER-1', 'SER-2'));
$ok = PowerPlantPVResidentialReport::normalize($base, $options, true);
residentialCheck(!$ok['errors'] && $ok['data']['strings'][0]['voltage'] === 315.5, 'A completed conventional string and a location suffice');
residentialCheck(in_array('PVResidentialStringRequired', PowerPlantPVResidentialReport::normalize(array('strings' => array(array())), $options, true)['errors'], true), 'At least one string is required');

$panel = $base;
$panel['placements']['panel'] = array('selected' => '1', 'description' => 'Versant sud');
residentialCheck(in_array('PVResidentialRoofRequired', PowerPlantPVResidentialReport::normalize($panel, $options, true)['errors'], true), 'A module placement requires a roof');
$panel['roofs'] = array($roof, $roof);
residentialCheck(!PowerPlantPVResidentialReport::normalize($panel, $options, true)['errors'], 'Two complete roofs are accepted');
$panel['roofs'][] = array('orientation' => 'NE');
residentialCheck(in_array('PVResidentialRoofIncomplete', PowerPlantPVResidentialReport::normalize($panel, $options, true)['errors'], true), 'Every added roof must be complete');
residentialCheck(!PowerPlantPVResidentialReport::normalize($panel, $options, false)['errors'], 'A draft accepts a partial roof');

$base['strings'] = array($base['strings'][0], $micro, $micro);
$ok = PowerPlantPVResidentialReport::normalize($base, $options, true);
residentialCheck(!$ok['errors'] && $ok['data']['strings'][1]['fk_product'] === 7 && $ok['data']['strings'][1]['maximum'] === 2, 'The only reference is selected and the limit applies separately to both strings');
$base['strings'][1]['quantity'] = '3';
$base['strings'][1]['serials'][] = 'SER-3';
$exceeded = PowerPlantPVResidentialReport::normalize($base, $options, true);
residentialCheck(in_array('PVResidentialMicroExceeded', $exceeded['errors'], true), 'Limit plus one is rejected');
residentialCheck(count(PowerPlantPVResidentialReport::normalize($base, $options, false)['data']['strings'][1]['serials']) === 3, 'A draft retains over-limit serials');

$base['strings'][1]['fk_powerplant'] = '2';
$unlimited = PowerPlantPVResidentialReport::normalize($base, $options, true);
residentialCheck(!$unlimited['errors'] && $unlimited['data']['strings'][1]['maximum'] === null, 'A reference with no maximum allows all three units');
$base['strings'][1]['fk_product'] = '7';
residentialCheck(in_array('PVResidentialMicroIncomplete', PowerPlantPVResidentialReport::normalize($base, $options, true)['errors'], true), 'A reference from a different plant is rejected');
$base['strings'][1]['fk_product'] = '8';
$base['strings'][1]['serials'][1] = '';
residentialCheck(in_array('PVResidentialMicroIncomplete', PowerPlantPVResidentialReport::normalize($base, $options, true)['errors'], true), 'A blank serial must be filled or removed');
array_splice($base['strings'][1]['serials'], 1, 1);
$base['strings'][1]['quantity'] = '2';
residentialCheck(!PowerPlantPVResidentialReport::normalize($base, $options, true)['errors'], 'Removing the blank serial and decrementing the quantity is valid');

$options['products'][2][8]['maximum'] = 1;
residentialCheck(in_array('PVResidentialMicroExceeded', PowerPlantPVResidentialReport::normalize($base, $options, true)['errors'], true), 'The new catalogue limit is enforced on finalization');

$legacy = array('sections' => array(array('fields' => array(
	(object) array('field_code' => 'RES_STRING_1_PANELS', 'value_number' => 8),
	(object) array('field_code' => 'RES_STRING_1_VOLTAGE', 'value_number' => 315),
	(object) array('field_code' => 'RES_STRING_2_VOLTAGE', 'value_number' => null),
))));
residentialCheck(PowerPlantPVResidentialReport::legacyStrings($legacy) === array(array('module_count' => 8, 'voltage' => 315)), 'Legacy string measurements populate the first revised draft');

echo "Residential scope and string rules OK\n";
