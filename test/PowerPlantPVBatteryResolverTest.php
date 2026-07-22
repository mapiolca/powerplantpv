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

dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/class/powerplantpvfileimport.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv_producttechnicalimport.lib.php');

final class PowerPlantPVBatteryResolverTest extends TestCase
{
	/** @return array<string,mixed> */
	private function fixture()
	{
		return array(
			'products' => array(
				1 => array('rowid' => 1, 'ref' => 'KIT-A', 'label' => 'Kit A', 'category_code' => ''),
				2 => array('rowid' => 2, 'ref' => 'BAT-5', 'label' => 'Battery 5', 'category_code' => 'BATTER'),
				3 => array('rowid' => 3, 'ref' => 'ACC', 'label' => 'Accessory', 'category_code' => 'BATACC'),
				4 => array('rowid' => 4, 'ref' => 'KIT-B', 'label' => 'Kit B', 'category_code' => ''),
				5 => array('rowid' => 5, 'ref' => 'BAT-7', 'label' => 'Battery 7', 'category_code' => 'BATTER'),
			),
			'children' => array(
				1 => array(array('product_id' => 4, 'qty' => 2), array('product_id' => 3, 'qty' => 1)),
				4 => array(array('product_id' => 2, 'qty' => 3), array('product_id' => 5, 'qty' => 1)),
			),
			'capacities' => array(2 => 5.0, 5 => 7.0),
		);
	}

	public function testLeafBatteryReturnsUsefulCapacity(): void
	{
		$result = powerplantpvResolveBatteryProduct(2, 2, $this->fixture());
		$this->assertSame(10.0, $result['capacity_kwh']);
		$this->assertTrue($result['complete']);
		$this->assertTrue($result['has_battery']);
	}

	public function testRegistryCoversEverySupportedStorageFamily(): void
	{
		$this->assertSame(
			array('BATTERY_MODULE', 'DC_SYSTEM', 'AC_COUPLED_ALL_IN_ONE', 'HYBRID_ALL_IN_ONE'),
			array_keys(ProductBattery::getStorageTypeOptions())
		);
		$fields = ProductBattery::getBatteryFields();
		$this->assertSame('kWh', $fields['usable_energy']['unit']);
		$this->assertArrayHasKey('roundtrip_efficiency_ac', $fields);
		$this->assertArrayHasKey('max_parallel_systems', $fields);
		$this->assertArrayHasKey('installation_location', $fields);
	}

	public function testIntegratedStorageReusesExtendedInverterRegistry(): void
	{
		$fields = ProductInverter::getInverterFields();
		$this->assertArrayHasKey('phase_count', $fields);
		$this->assertArrayHasKey('backup_nominal_power', $fields);
		$this->assertArrayHasKey('backup_peak_power', $fields);
		$this->assertArrayHasKey('backup_transfer_time', $fields);
		$this->assertArrayHasKey('max_unbalanced_output', $fields);
	}

	public function testNestedKitMultipliesEveryQuantityAndFlattensInventory(): void
	{
		$result = powerplantpvResolveBatteryProduct(1, 2, $this->fixture());
		$this->assertSame(88.0, $result['capacity_kwh']);
		$this->assertTrue($result['complete']);
		$inventory = array_column($result['inventory'], 'quantity', 'product_id');
		$this->assertSame(12.0, $inventory[2]);
		$this->assertSame(4.0, $inventory[5]);
		$this->assertSame(2.0, $inventory[3]);
	}

	public function testAccessoryAloneContributesZero(): void
	{
		$result = powerplantpvResolveBatteryProduct(3, 4, $this->fixture());
		$this->assertSame(0.0, $result['capacity_kwh']);
		$this->assertFalse($result['has_battery']);
		$this->assertTrue($result['complete']);
	}

	public function testMissingUsefulCapacityMakesResultIncomplete(): void
	{
		$fixture = $this->fixture();
		$fixture['capacities'][5] = null;
		$result = powerplantpvResolveBatteryProduct(1, 1, $fixture);
		$this->assertNull($result['capacity_kwh']);
		$this->assertFalse($result['complete']);
		$this->assertSame(array(5), $result['missing_product_ids']);
	}

	public function testCycleIsReportedWithoutRecursionOverflow(): void
	{
		$fixture = $this->fixture();
		$fixture['children'][4][] = array('product_id' => 1, 'qty' => 1);
		$result = powerplantpvResolveBatteryProduct(1, 1, $fixture);
		$this->assertNull($result['capacity_kwh']);
		$this->assertFalse($result['complete']);
		$this->assertStringStartsWith('BatteryKitCycle:', $result['errors'][0]);
		$this->assertSame($result['errors'], $result['composition_anomalies']);
	}

	public function testBatteryImportParsesUnitHeadersAndNormalizedAttributes(): void
	{
		$import = new PowerPlantPVFileImport();
		$parsed = $import->buildImportRows(array(
			array('usable_energy [kWh]', 'nominal_voltage [V]', 'protocol_1 [code]', 'certification_1 [code]'),
			array('9.6', '400', 'MODBUS|Modbus TCP', 'IEC62619'),
		), 'battery');
		$this->assertSame('', $import->getLastError());
		$this->assertSame(9.6, $parsed['rows'][0]['normalized']['usable_energy']);
		$this->assertSame('MODBUS', $parsed['rows'][0]['normalized']['_battery_attributes']['PROTOCOL'][0]['code']);
		$this->assertSame('Modbus TCP', $parsed['rows'][0]['normalized']['_battery_attributes']['PROTOCOL'][0]['label']);
	}

	public function testLegacyUnitlessHeadersAreAcceptedWithWarning(): void
	{
		$import = new PowerPlantPVFileImport();
		$parsed = $import->buildImportRows(array(array('usable_energy', 'protocol_1'), array('5', 'CAN')), 'battery');
		$this->assertSame('', $import->getLastError());
		$this->assertCount(2, $parsed['field_map']['unit_warnings']);
	}

	public function testContradictoryUnitIsRejected(): void
	{
		$import = new PowerPlantPVFileImport();
		$parsed = $import->buildImportRows(array(array('usable_energy [V]'), array('5')), 'battery');
		$this->assertSame(array(), $parsed);
		$this->assertSame('ProductTechnicalImportUnexpectedUnit', $import->getLastError());
	}

	public function testContradictoryMpptCompositionUnitIsRejected(): void
	{
		$import = new PowerPlantPVFileImport();
		$parsed = $import->buildImportRows(array(array('mppt_1_voltage_min [A]'), array('200')), 'inverter');
		$this->assertSame(array(), $parsed);
		$this->assertSame('ProductTechnicalImportUnexpectedUnit', $import->getLastError());
	}

	public function testTechnicalNumberParserRejectsUnitsAndFreeText(): void
	{
		$this->assertSame(9.6, powerplantpvParseTechnicalNumber('9,6'));
		$this->assertSame(1000.0, powerplantpvParseTechnicalNumber('1 000'));
		$this->assertSame(-20.0, powerplantpvParseTechnicalNumber('-20'));
		$this->assertNull(powerplantpvParseTechnicalNumber('9.6 kWh'));
		$this->assertNull(powerplantpvParseTechnicalNumber('about 9.6'));
		$this->assertNull(powerplantpvParseTechnicalNumber('2.5', true));
	}

	public function testImportRejectsUnitInsideMeasurementValue(): void
	{
		$import = new PowerPlantPVFileImport();
		$parsed = $import->buildImportRows(array(array('usable_energy [kWh]'), array('9.6 kWh')), 'battery');
		$this->assertSame(array(), $parsed);
		$this->assertSame('ProductTechnicalImportNumericValueRequired', $import->getLastError());
	}

	public function testLegacyInverterMeasurementColumnsAreNumeric(): void
	{
		$import = new PowerPlantPVFileImport();
		$parsed = $import->buildImportRows(array(array('ac_nominal_voltage [V]'), array('230')), 'inverter');
		$this->assertSame('', $import->getLastError());
		$this->assertSame(230.0, $parsed['rows'][0]['normalized']['ac_nominal_voltage']);

		$parsed = $import->buildImportRows(array(array('ac_nominal_voltage [V]'), array('230 V')), 'inverter');
		$this->assertSame(array(), $parsed);
		$this->assertSame('ProductTechnicalImportNumericValueRequired', $import->getLastError());
	}

	public function testEveryFlatTemplateHeaderDocumentsAUnitOrFormat(): void
	{
		foreach (array('MODULE', 'ONDULE', 'BATTER') as $category) {
			foreach (powerplantpvProductTechnicalImportGetTemplateHeaders($category) as $header) {
				$this->assertMatchesRegularExpression('/\[[^\]]+\]$/u', $header, $category.' header '.$header);
			}
		}
	}
}
