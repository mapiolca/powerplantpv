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
dol_include_once('/powerplantpv/class/powerplantpvbulkproductimport.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv_producttechnicalimport.lib.php');

final class PowerPlantPVDictionaryTestResult
{
	/** @var array<int,object> */
	public $rows;

	/** @var int */
	public $index = 0;

	/** @param array<int,object> $rows Rows */
	public function __construct(array $rows)
	{
		$this->rows = $rows;
	}
}

final class PowerPlantPVDictionaryTestDb
{
	/** @var array<int,object> */
	private $rows;

	/** @param array<int,object> $rows Rows */
	public function __construct(array $rows)
	{
		$this->rows = $rows;
	}

	public function prefix(): string
	{
		return 'llx_';
	}

	/** @return PowerPlantPVDictionaryTestResult */
	public function query(string $sql)
	{
		$rows = $this->rows;
		if (strpos($sql, 'd.active = 1') !== false) {
			$rows = array_values(array_filter($rows, static function ($row) {
				return !empty($row->active);
			}));
		}
		return new PowerPlantPVDictionaryTestResult($rows);
	}

	/** @return object|false */
	public function fetch_object(PowerPlantPVDictionaryTestResult $result)
	{
		return isset($result->rows[$result->index]) ? $result->rows[$result->index++] : false;
	}

	public function free(PowerPlantPVDictionaryTestResult $result): void
	{
	}
}

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
		$this->assertArrayHasKey('noise_comparator', $fields);
	}

	public function testIntegratedStorageReusesExtendedInverterRegistry(): void
	{
		$fields = ProductInverter::getInverterFields();
		$this->assertArrayHasKey('phase_count', $fields);
		$this->assertArrayHasKey('backup_nominal_power', $fields);
		$this->assertArrayHasKey('backup_peak_power', $fields);
		$this->assertArrayHasKey('backup_transfer_time', $fields);
		$this->assertArrayHasKey('max_unbalanced_output', $fields);
		$this->assertArrayHasKey('ac_voltage_min', $fields);
		$this->assertArrayHasKey('ac_voltage_nominal', $fields);
		$this->assertArrayHasKey('ac_voltage_max', $fields);
		$this->assertArrayHasKey('power_factor_inductive', $fields);
		$this->assertArrayHasKey('thd_comparator', $fields);
		$this->assertSame('W', $fields['ac_nominal_power']['unit']);
		$this->assertSame('VA', $fields['ac_apparent_power']['unit']);
		$this->assertSame('A', $fields['backup_max_current']['unit']);
		$this->assertSame('W', $fields['night_consumption']['unit']);

		$mpptFields = ProductInverter::getMpptFields();
		$this->assertSame('V', $mpptFields['voltage_min']['unit']);
		$this->assertSame('A', $mpptFields['max_input_current']['unit']);
		$this->assertSame('W', $mpptFields['max_dc_power']['unit']);

		$inputFields = ProductInverter::getPvInputFields();
		$this->assertSame('A', $inputFields['max_input_current']['unit']);
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

	public function testBatteryImportParsesUnitHeadersAndControlledDictionaryCodes(): void
	{
		$import = new PowerPlantPVFileImport();
		$parsed = $import->buildImportRows(array(
			array('usable_energy [kWh]', 'nominal_voltage [V]', 'communication_protocol_1 [code]', 'certification_1 [code]'),
			array('9.6', '400', 'MODBUS_RTU|Modbus RTU', 'IEC_62619'),
		), 'battery');
		$this->assertSame('', $import->getLastError());
		$this->assertSame(9.6, $parsed['rows'][0]['normalized']['usable_energy']);
		$this->assertSame(
			array('communication_protocol' => array('MODBUS_RTU|Modbus RTU'), 'certification' => array('IEC_62619')),
			$parsed['rows'][0]['normalized']['_technical_dictionary_codes']
		);
	}

	public function testDocumentedHeadersAreParsedWithRepeatableDictionaryColumns(): void
	{
		$import = new PowerPlantPVFileImport();
		$parsed = $import->buildImportRows(array(
			array(
				'usable_energy [type=decimal; unit=kWh; format=SIGNED_DECIMAL]',
				'storage_type [type=select2; format=CODE; source=storage_type]',
				'communication_protocol_12 [type=multiselect2; format=CODE|Libellé; source=communication_protocol]',
			),
			array('9,6', 'BATTERY_MODULE', 'NEW_PROTO|Nouveau protocole'),
		), 'battery');
		$this->assertSame('', $import->getLastError());
		$this->assertSame(9.6, $parsed['rows'][0]['normalized']['usable_energy']);
		$this->assertSame('BATTERY_MODULE', $parsed['rows'][0]['normalized']['storage_type']);
		$this->assertSame(array('NEW_PROTO|Nouveau protocole'), $parsed['rows'][0]['normalized']['_technical_dictionary_codes']['communication_protocol']);
		$definition = PowerPlantPVProductImport::getImportFieldDefinition('battery', 'communication_protocol_12');
		$this->assertSame('multiselect2', $definition['type']);
		$this->assertSame('0..N', $definition['cardinality']);
		$this->assertSame('CODE|Libellé', $definition['format']);
	}

	public function testContradictoryDocumentedTypeIsRejectedSeparately(): void
	{
		$import = new PowerPlantPVFileImport();
		$parsed = $import->buildImportRows(array(
			array('usable_energy [type=text; unit=kWh; format=TEXT]'),
			array('9.6'),
		), 'battery');
		$this->assertSame(array(), $parsed);
		$this->assertSame('ProductTechnicalImportUnexpectedType', $import->getLastError());
	}

	public function testContradictoryDocumentedFormatIsRejectedSeparately(): void
	{
		$import = new PowerPlantPVFileImport();
		$parsed = $import->buildImportRows(array(
			array('usable_energy [type=decimal; unit=kWh; format=TEXT]'),
			array('9.6'),
		), 'battery');
		$this->assertSame(array(), $parsed);
		$this->assertSame('ProductTechnicalImportUnexpectedFormat', $import->getLastError());
	}

	public function testCsvDocumentationRowsAreNeverImportedAsProducts(): void
	{
		$import = new PowerPlantPVFileImport();
		$parsed = $import->buildImportRows(array(
			array('usable_energy [type=decimal; unit=kWh; format=SIGNED_DECIMAL]'),
			array('9.6'),
			array('#POWERPLANTPV_FIELD', 'usable_energy', 'BATTER', 'decimal', 'kWh'),
			array('#POWERPLANTPV_VALUE', 'storage_type', 'storage_type', 'BATTERY_MODULE', 'Module de batterie'),
		), 'battery');
		$this->assertSame('', $import->getLastError());
		$this->assertCount(1, $parsed['rows']);
	}

	public function testDictionaryAnalysisDistinguishesKnownUnknownInactiveAndLabels(): void
	{
		$db = new PowerPlantPVDictionaryTestDb(array(
			(object) array('rowid' => 1, 'code' => 'MODBUS_RTU', 'label' => 'Modbus RTU', 'active' => 1),
			(object) array('rowid' => 2, 'code' => 'LEGACY', 'label' => 'Ancien protocole', 'active' => 0),
		));
		$service = new PowerPlantPVProductDictionary($db);
		$analysis = $service->analyzeImportValues(
			PowerPlantPVProductDictionary::TYPE_COMMUNICATION_PROTOCOL,
			array('MODBUS_RTU|Autre libellé', 'new.code|<b>Nouveau</b> protocole', 'LEGACY|Ancien protocole'),
			2
		);
		$this->assertIsArray($analysis);
		$this->assertSame(array(1), $analysis['resolved_ids']);
		$this->assertCount(1, $analysis['warnings']);
		$unknownKey = PowerPlantPVProductDictionary::getImportIssueKey('communication_protocol', 'NEW.CODE');
		$inactiveKey = PowerPlantPVProductDictionary::getImportIssueKey('communication_protocol', 'LEGACY');
		$this->assertSame('Nouveau protocole', $analysis['issues'][$unknownKey]['label']);
		$this->assertTrue($analysis['issues'][$unknownKey]['can_create']);
		$this->assertSame('inactive', $analysis['issues'][$inactiveKey]['status']);
		$this->assertFalse($analysis['issues'][$inactiveKey]['can_create']);

		$conflicting = $service->analyzeImportValues(
			PowerPlantPVProductDictionary::TYPE_COMMUNICATION_PROTOCOL,
			array('CONFLICT|Premier libellé', 'CONFLICT|Second libellé'),
			2
		);
		$conflictingKey = PowerPlantPVProductDictionary::getImportIssueKey('communication_protocol', 'CONFLICT');
		$this->assertIsArray($conflicting);
		$this->assertFalse($conflicting['issues'][$conflictingKey]['can_create']);

		$plan = $service->buildImportResolutionPlan(
			PowerPlantPVProductDictionary::TYPE_COMMUNICATION_PROTOCOL,
			array('new.code|Nouveau protocole', 'LEGACY|Ancien protocole'),
			2,
			array($unknownKey => array('action' => 'create'), $inactiveKey => array('action' => 'ignore'))
		);
		$this->assertIsArray($plan);
		$this->assertTrue($plan['complete']);
		$this->assertSame('NEW.CODE', $plan['create'][0]['code']);
		$this->assertFalse($plan['preserve_existing']);

		$mappedPlan = $service->buildImportResolutionPlan(
			PowerPlantPVProductDictionary::TYPE_COMMUNICATION_PROTOCOL,
			array('new.code|Nouveau protocole'),
			2,
			array($unknownKey => array('action' => 'map', 'target_codes' => array('MODBUS_RTU')))
		);
		$this->assertIsArray($mappedPlan);
		$this->assertTrue($mappedPlan['complete']);
		$this->assertSame(array(1), $mappedPlan['ids']);
		$this->assertSame(array('MODBUS_RTU'), $mappedPlan['codes']);
		$this->assertSame(array(), $mappedPlan['create']);
		$this->assertFalse($mappedPlan['preserve_existing']);
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
		$this->assertSame(1234567.89, powerplantpvParseTechnicalNumber('+1 234 567,89'));
		$this->assertSame(1000.5, powerplantpvParseTechnicalNumber("1 000.5"));
		$this->assertSame(1000.5, powerplantpvParseTechnicalNumber("1 000,5"));
		$this->assertNull(powerplantpvParseTechnicalNumber('9.6 kWh'));
		$this->assertNull(powerplantpvParseTechnicalNumber('about 9.6'));
		$this->assertNull(powerplantpvParseTechnicalNumber('12 34'));
		$this->assertNull(powerplantpvParseTechnicalNumber('10-20'));
		$this->assertNull(powerplantpvParseTechnicalNumber('1e3'));
		$this->assertNull(powerplantpvParseTechnicalNumber('1,234.56'));
		$this->assertNull(powerplantpvParseTechnicalNumber('2.5', true));
	}

	public function testImportRejectsUnitInsideMeasurementValue(): void
	{
		$import = new PowerPlantPVFileImport();
		$parsed = $import->buildImportRows(array(array('usable_energy [kWh]'), array('9.6 kWh')), 'battery');
		$this->assertSame(array(), $parsed);
		$this->assertSame('ProductTechnicalImportNumericValueRequired', $import->getLastError());
	}

	public function testLegacyInverterMeasurementColumnsPopulateStructuredValues(): void
	{
		$import = new PowerPlantPVFileImport();
		$parsed = $import->buildImportRows(array(array('ac_nominal_voltage [V]'), array('230')), 'inverter');
		$this->assertSame('', $import->getLastError());
		$this->assertSame(230.0, $parsed['rows'][0]['normalized']['ac_voltage_nominal']);

		$parsed = $import->buildImportRows(array(array('ac_nominal_voltage [V]'), array('230 V')), 'inverter');
		$this->assertSame(array(), $parsed);
		$this->assertSame('ProductTechnicalImportNumericValueRequired', $import->getLastError());
	}

	public function testEveryFlatTemplateHeaderDocumentsAUnitOrFormat(): void
	{
		foreach (array('MODULE', 'ONDULE', 'BATTER') as $category) {
			foreach (powerplantpvProductTechnicalImportGetTemplateHeaders($category) as $header) {
				$this->assertMatchesRegularExpression('/\[type=[^\]]+\]$/u', $header, $category.' header '.$header);
			}
		}
		foreach (PowerPlantPVBulkProductImport::getTemplateHeaders() as $header) {
			$this->assertMatchesRegularExpression('/\[type=[^\]]+\]$/u', $header, 'MIXED header '.$header);
		}
	}
}
