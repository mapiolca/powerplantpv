<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/bootstrap.php';

dol_include_once('/powerplantpv/class/powerplantpvtechnicalvalue.class.php');
dol_include_once('/powerplantpv/class/powerplantpvfileimport.class.php');

final class PowerPlantPVTechnicalValueTest extends TestCase
{
	/** @dataProvider rangeProvider */
	public function testCompactRangesAreParsed($raw, $min, $max): void
	{
		$this->assertSame(array('min' => $min, 'max' => $max), PowerPlantPVTechnicalValue::parseRange($raw));
	}

	/** @return array<string,array{0:string,1:float,2:float}> */
	public static function rangeProvider(): array
	{
		return array(
			'hyphen' => array('184-276', 184.0, 276.0),
			'negative slash' => array('-40/+65', -40.0, 65.0),
			'negative bounds' => array('-40 - -10', -40.0, -10.0),
			'unicode dash' => array('45 – 65', 45.0, 65.0),
		);
	}

	/** @dataProvider thresholdProvider */
	public function testThresholdComparatorsAreNormalized($raw, $comparator, $value): void
	{
		$this->assertSame(array('comparator' => $comparator, 'value' => $value), PowerPlantPVTechnicalValue::parseThreshold($raw));
	}

	/** @return array<string,array{0:string,1:string,2:float}> */
	public static function thresholdProvider(): array
	{
		return array(
			'less' => array('<3', 'LT', 3.0),
			'less equal' => array('≤ 3', 'LTE', 3.0),
			'greater' => array('>0,95', 'GT', 0.95),
			'greater equal' => array('>= 4', 'GTE', 4.0),
			'equal' => array('=25', 'EQ', 25.0),
		);
	}

	public function testSignedPowerFactorIsDirectional(): void
	{
		$this->assertSame(
			array('inductive' => 0.8, 'nominal' => null, 'capacitive' => 0.8),
			PowerPlantPVTechnicalValue::parsePowerFactor('+0.8;-0.8')
		);
	}

	public function testUnsignedSlashValuesRemainAmbiguous(): void
	{
		$this->assertNull(PowerPlantPVTechnicalValue::parseRange('50/60'));
	}

	public function testRangesValidatePartialAndNominalValues(): void
	{
		$this->assertTrue(PowerPlantPVTechnicalValue::isValidRange(null, null, 65));
		$this->assertTrue(PowerPlantPVTechnicalValue::isValidRange(184, 230, 276));
		$this->assertFalse(PowerPlantPVTechnicalValue::isValidRange(184, 300, 276));
		$this->assertFalse(PowerPlantPVTechnicalValue::isValidRange(65, null, 45));
	}

	public function testPvPanelRegistryCarriesDisplayUnits(): void
	{
		$fields = PowerPlantPVTechnicalValue::getPVPanelFields();
		$this->assertSame('Wc', $fields['pmax']['unit']);
		$this->assertSame('V', $fields['vmp']['unit']);
		$this->assertSame('A', $fields['isc']['unit']);
		$this->assertSame('mm²', $fields['cable_section']['unit']);
		$this->assertSame('years', $fields['product_warranty']['unit']);
		$this->assertSame('pcs', $fields['modules_per_box']['unit']);
	}

	public function testStructuredImportHeadersAndLegacyRows(): void
	{
		$this->assertSame('pmax [type=decimal; unit=Wc; format=SIGNED_DECIMAL]', PowerPlantPVProductImport::getTemplateHeader('module', 'pmax'));
		$this->assertSame('ac_voltage_min [type=decimal; unit=V; format=SIGNED_DECIMAL]', PowerPlantPVProductImport::getTemplateHeader('inverter', 'ac_voltage_min'));
		$this->assertSame('thd_comparator [type=select2; format=CODE; source=thd_comparator]', PowerPlantPVProductImport::getTemplateHeader('inverter', 'thd_comparator'));

		$import = new PowerPlantPVFileImport();
		$parsed = $import->buildImportRows(array(
			array('ac_nominal_voltage [V]', 'cos_phi [ratio]', 'thd [%]'),
			array('184-276', '+0.8;-0.8', '<3'),
		), 'inverter');
		$this->assertSame(184.0, $parsed['rows'][0]['normalized']['ac_voltage_min']);
		$this->assertSame(276.0, $parsed['rows'][0]['normalized']['ac_voltage_max']);
		$this->assertSame(0.8, $parsed['rows'][0]['normalized']['power_factor_inductive']);
		$this->assertSame(0.8, $parsed['rows'][0]['normalized']['power_factor_capacitive']);
		$this->assertSame('LT', $parsed['rows'][0]['normalized']['thd_comparator']);
		$this->assertSame(3.0, $parsed['rows'][0]['normalized']['thd_value']);
	}

	public function testAmbiguousLegacyModuleScalarIsReported(): void
	{
		$import = new PowerPlantPVFileImport();
		$parsed = $import->buildImportRows(array(array('power_tolerance [%]'), array('5')), 'module');
		$this->assertSame('5', $parsed['rows'][0]['normalized']['_legacy_warnings']['_legacy_power_tolerance']);
		$this->assertArrayNotHasKey('power_tolerance_min', $parsed['rows'][0]['normalized']);
	}
}
