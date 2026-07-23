<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once dirname(__DIR__).'/lib/powerplantpv.lib.php';

/**
 * Technical battery data attached to a native Dolibarr product.
 */
class ProductBattery
{
	/** @var DoliDB */
	public $db;
	/** @var string */
	public $error = '';
	/** @var array<int,string> */
	public $errors = array();
	/** @var int */
	public $id = 0;
	/** @var int */
	public $rowid = 0;
	/** @var int */
	public $fk_product = 0;
	/** @var int */
	public $entity = 1;
	/** @var array<string,mixed> */
	public $data = array();

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Return the single source of truth for scalar battery fields.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function getBatteryFields()
	{
		return array(
			'storage_type' => array('label' => 'BatteryStorageType', 'type' => 'select', 'unit' => 'code', 'section' => 'BatteryClassification', 'options' => self::getStorageTypeOptions()),
			'battery_family' => array('label' => 'BatteryFamily', 'type' => 'varchar', 'unit' => 'text', 'section' => 'BatteryClassification'),
			'chemistry' => array('label' => 'BatteryChemistry', 'type' => 'select', 'unit' => 'code', 'section' => 'BatteryClassification', 'options' => self::getChemistryOptions()),
			'nominal_energy' => array('label' => 'BatteryNominalEnergy', 'type' => 'double', 'unit' => 'kWh', 'section' => 'BatteryEnergy'),
			'usable_energy' => array('label' => 'BatteryUsableEnergy', 'type' => 'double', 'unit' => 'kWh', 'section' => 'BatteryEnergy'),
			'capacity_ah' => array('label' => 'BatteryCapacityAh', 'type' => 'double', 'unit' => 'Ah', 'section' => 'BatteryEnergy'),
			'nominal_voltage' => array('label' => 'BatteryNominalVoltage', 'type' => 'double', 'unit' => 'V', 'section' => 'BatteryEnergy'),
			'voltage_min' => array('label' => 'BatteryVoltageMin', 'type' => 'double', 'unit' => 'V', 'section' => 'BatteryEnergy'),
			'voltage_max' => array('label' => 'BatteryVoltageMax', 'type' => 'double', 'unit' => 'V', 'section' => 'BatteryEnergy'),
			'dod' => array('label' => 'BatteryDepthOfDischarge', 'type' => 'double', 'unit' => '%', 'section' => 'BatteryEnergy'),
			'max_charge_power' => array('label' => 'BatteryMaxChargePower', 'type' => 'double', 'unit' => 'kW', 'section' => 'BatteryPower'),
			'max_discharge_power' => array('label' => 'BatteryMaxDischargePower', 'type' => 'double', 'unit' => 'kW', 'section' => 'BatteryPower'),
			'peak_charge_power' => array('label' => 'BatteryPeakChargePower', 'type' => 'double', 'unit' => 'kW', 'section' => 'BatteryPower'),
			'peak_discharge_power' => array('label' => 'BatteryPeakDischargePower', 'type' => 'double', 'unit' => 'kW', 'section' => 'BatteryPower'),
			'peak_duration' => array('label' => 'BatteryPeakDuration', 'type' => 'double', 'unit' => 's', 'section' => 'BatteryPower'),
			'max_charge_current' => array('label' => 'BatteryMaxChargeCurrent', 'type' => 'double', 'unit' => 'A', 'section' => 'BatteryPower'),
			'max_discharge_current' => array('label' => 'BatteryMaxDischargeCurrent', 'type' => 'double', 'unit' => 'A', 'section' => 'BatteryPower'),
			'roundtrip_efficiency_dc' => array('label' => 'BatteryRoundtripEfficiencyDC', 'type' => 'double', 'unit' => '%', 'section' => 'BatteryPerformance'),
			'roundtrip_efficiency_ac' => array('label' => 'BatteryRoundtripEfficiencyAC', 'type' => 'double', 'unit' => '%', 'section' => 'BatteryPerformance'),
			'cycle_life' => array('label' => 'BatteryCycleLife', 'type' => 'int', 'unit' => 'cycles', 'section' => 'BatteryPerformance'),
			'cycle_end_capacity' => array('label' => 'BatteryCycleEndCapacity', 'type' => 'double', 'unit' => '%', 'section' => 'BatteryPerformance'),
			'warranty_years' => array('label' => 'BatteryWarrantyYears', 'type' => 'int', 'unit' => 'years', 'section' => 'BatteryPerformance'),
			'warranty_cycles' => array('label' => 'BatteryWarrantyCycles', 'type' => 'int', 'unit' => 'cycles', 'section' => 'BatteryPerformance'),
			'warranty_throughput' => array('label' => 'BatteryWarrantyThroughput', 'type' => 'double', 'unit' => 'MWh', 'section' => 'BatteryPerformance'),
			'min_modules' => array('label' => 'BatteryMinModules', 'type' => 'int', 'unit' => 'pcs', 'section' => 'BatteryScalability'),
			'max_modules' => array('label' => 'BatteryMaxModules', 'type' => 'int', 'unit' => 'pcs', 'section' => 'BatteryScalability'),
			'max_parallel_systems' => array('label' => 'BatteryMaxParallelSystems', 'type' => 'int', 'unit' => 'pcs', 'section' => 'BatteryScalability'),
			'operating_temperature_min' => array('label' => 'BatteryOperatingTemperatureMin', 'type' => 'double', 'unit' => '°C', 'section' => 'BatteryEnvironment'),
			'operating_temperature_max' => array('label' => 'BatteryOperatingTemperatureMax', 'type' => 'double', 'unit' => '°C', 'section' => 'BatteryEnvironment'),
			'storage_temperature_min' => array('label' => 'BatteryStorageTemperatureMin', 'type' => 'double', 'unit' => '°C', 'section' => 'BatteryEnvironment'),
			'storage_temperature_max' => array('label' => 'BatteryStorageTemperatureMax', 'type' => 'double', 'unit' => '°C', 'section' => 'BatteryEnvironment'),
			'humidity_min' => array('label' => 'BatteryHumidityMin', 'type' => 'double', 'unit' => '%', 'section' => 'BatteryEnvironment'),
			'humidity_max' => array('label' => 'BatteryHumidityMax', 'type' => 'double', 'unit' => '%', 'section' => 'BatteryEnvironment'),
			'max_altitude' => array('label' => 'BatteryMaxAltitude', 'type' => 'int', 'unit' => 'm', 'section' => 'BatteryEnvironment'),
			'ip_rating' => array('label' => 'BatteryIPRating', 'type' => 'varchar', 'unit' => 'code', 'section' => 'BatteryEnvironment'),
			'corrosion_class' => array('label' => 'BatteryCorrosionClass', 'type' => 'varchar', 'unit' => 'code', 'section' => 'BatteryEnvironment'),
			'cooling' => array('label' => 'BatteryCooling', 'type' => 'varchar', 'unit' => 'text', 'section' => 'BatteryEnvironment'),
			'noise' => array('label' => 'BatteryNoise', 'type' => 'double', 'unit' => 'dB(A)', 'section' => 'BatteryEnvironment'),
			'installation_location' => array('label' => 'BatteryInstallationLocation', 'type' => 'select', 'unit' => 'code', 'section' => 'BatteryEnvironment', 'options' => self::getInstallationLocationOptions()),
			'mounting' => array('label' => 'BatteryMounting', 'type' => 'varchar', 'unit' => 'text', 'section' => 'BatteryEnvironment'),
		);
	}

	/** @return array<string,string> */
	public static function getStorageTypeOptions()
	{
		return array('BATTERY_MODULE' => 'BatteryTypeModule', 'DC_SYSTEM' => 'BatteryTypeDCSystem', 'AC_COUPLED_ALL_IN_ONE' => 'BatteryTypeACAllInOne', 'HYBRID_ALL_IN_ONE' => 'BatteryTypeHybridAllInOne');
	}

	/** @return array<string,string> */
	public static function getChemistryOptions()
	{
		return array('LFP' => 'BatteryChemistryLFP', 'NMC' => 'BatteryChemistryNMC', 'LTO' => 'BatteryChemistryLTO', 'LEAD_ACID' => 'BatteryChemistryLeadAcid', 'OTHER' => 'Other');
	}

	/** @return array<string,string> */
	public static function getInstallationLocationOptions()
	{
		return array('INDOOR' => 'BatteryLocationIndoor', 'OUTDOOR' => 'BatteryLocationOutdoor', 'INDOOR_OUTDOOR' => 'BatteryLocationIndoorOutdoor');
	}

	/** @return array<string,string> */
	public static function getAttributeTypeOptions()
	{
		return array('PROTOCOL' => 'BatteryProtocols', 'PROTECTION' => 'BatteryProtections', 'CERTIFICATION' => 'BatteryCertifications');
	}

	/** @return array<string,string> */
	public static function getAccessoryRoleOptions()
	{
		return array('BMS' => 'BatteryAccessoryRoleBMS', 'CONTROLLER' => 'BatteryAccessoryRoleController', 'BASE' => 'BatteryAccessoryRoleBase', 'POWER_MODULE' => 'BatteryAccessoryRolePowerModule', 'CABLE' => 'BatteryAccessoryRoleCable', 'COMMUNICATION' => 'BatteryAccessoryRoleCommunication', 'MOUNTING' => 'BatteryAccessoryRoleMounting', 'HEATER' => 'BatteryAccessoryRoleHeater', 'SAFETY' => 'BatteryAccessoryRoleSafety', 'OTHER' => 'Other');
	}

	/** @return array<string,string> */
	public static function getRuleEffectOptions()
	{
		return array('COMPATIBLE' => 'BatteryRuleCompatible', 'REQUIRED' => 'BatteryRuleRequired', 'INCOMPATIBLE' => 'BatteryRuleIncompatible');
	}

	/** @return array<string,string> */
	public static function getCriterionTypeOptions()
	{
		return array('PRODUCT' => 'Product', 'FAMILY' => 'BatteryFamily', 'BRAND' => 'ProductPhotovoltaicBrand', 'STORAGE_TYPE' => 'BatteryStorageType', 'CHEMISTRY' => 'BatteryChemistry', 'PROTOCOL' => 'BatteryProtocols', 'CAPACITY' => 'BatteryUsableEnergy', 'MODULE_COUNT' => 'BatteryMaxModules');
	}

	/** @return array<string,string> */
	public static function getRuleUnitOptions()
	{
		return array('kWh' => 'kWh', 'kW' => 'kW', 'V' => 'V', 'A' => 'A', 'pcs' => 'pcs');
	}

	/**
	 * @param int $fkProduct Product id
	 * @return int 1 if found, 0 if absent, <0 on error
	 */
	public function fetchByProduct($fkProduct)
	{
		$fields = array_keys(self::getBatteryFields());
		$sql = 'SELECT b.rowid, b.fk_product, b.entity, b.'.implode(', b.', $fields);
		$sql .= ' FROM '.$this->db->prefix().'powerplantpv_product_battery as b';
		$sql .= ' INNER JOIN '.$this->db->prefix().'product as p ON p.rowid = b.fk_product AND p.entity = b.entity';
		$sql .= ' WHERE b.fk_product = '.((int) $fkProduct).' AND p.entity IN ('.getEntity('product').')';
		$resql = $this->db->query($sql);
		if (!$resql) {
			return $this->setError($this->db->lasterror());
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!$obj) {
			return 0;
		}
		$this->id = (int) $obj->rowid;
		$this->rowid = $this->id;
		$this->fk_product = (int) $obj->fk_product;
		$this->entity = (int) $obj->entity;
		foreach ($fields as $field) {
			$this->data[$field] = $obj->{$field};
		}
		return 1;
	}

	/**
	 * @param int $fkProduct Product id
	 * @param array<string,mixed> $data Field values
	 * @param User $user Current user
	 * @return int Battery id or <0
	 */
	public function saveForProduct($fkProduct, array $data, User $user)
	{
		foreach (self::getBatteryFields() as $field => $spec) {
			$type = isset($spec['type']) ? (string) $spec['type'] : 'varchar';
			$value = array_key_exists($field, $data) ? $data[$field] : null;
			if (!in_array($type, array('double', 'int'), true) || $value === null || $value === '') {
				continue;
			}
			$normalized = powerplantpvParseTechnicalNumber($value, $type === 'int');
			if ($normalized === null) {
				return $this->setError('ProductTechnicalNumericValueRequired');
			}
			$data[$field] = $normalized;
		}

		if (empty($data['storage_type'])) {
			$data['storage_type'] = 'BATTERY_MODULE';
		}
		if (!isset(self::getStorageTypeOptions()[$data['storage_type']])) {
			return $this->setError('BatteryInvalidStorageType');
		}
		if (!empty($data['chemistry']) && !isset(self::getChemistryOptions()[$data['chemistry']])) {
			return $this->setError('BatteryInvalidChemistry');
		}
		if (!empty($data['installation_location']) && !isset(self::getInstallationLocationOptions()[$data['installation_location']])) {
			return $this->setError('BatteryInvalidInstallationLocation');
		}
		foreach (array('dod', 'roundtrip_efficiency_dc', 'roundtrip_efficiency_ac', 'cycle_end_capacity', 'humidity_min', 'humidity_max') as $percentfield) {
			if (isset($data[$percentfield]) && $data[$percentfield] !== '' && $data[$percentfield] !== null && ((float) $data[$percentfield] < 0 || (float) $data[$percentfield] > 100)) {
				return $this->setError('BatteryInvalidPercentage');
			}
		}
		foreach (array('nominal_energy', 'usable_energy', 'capacity_ah', 'nominal_voltage', 'max_charge_power', 'max_discharge_power', 'peak_charge_power', 'peak_discharge_power', 'peak_duration', 'max_charge_current', 'max_discharge_current', 'cycle_life', 'warranty_years', 'warranty_cycles', 'warranty_throughput', 'min_modules', 'max_modules', 'max_parallel_systems', 'max_altitude', 'noise') as $positivefield) {
			if (isset($data[$positivefield]) && $data[$positivefield] !== '' && $data[$positivefield] !== null && (float) $data[$positivefield] < 0) {
				return $this->setError('BatteryInvalidPositiveValue');
			}
		}
		foreach (array(
			array('voltage_min', 'voltage_max'),
			array('operating_temperature_min', 'operating_temperature_max'),
			array('storage_temperature_min', 'storage_temperature_max'),
			array('humidity_min', 'humidity_max'),
			array('min_modules', 'max_modules'),
		) as $rangefields) {
			if (isset($data[$rangefields[0]], $data[$rangefields[1]]) && $data[$rangefields[0]] !== '' && $data[$rangefields[1]] !== '' && $data[$rangefields[0]] !== null && $data[$rangefields[1]] !== null && (float) $data[$rangefields[0]] > (float) $data[$rangefields[1]]) {
				return $this->setError('BatteryInvalidRange');
			}
		}
		if (isset($data['nominal_energy'], $data['usable_energy']) && $data['nominal_energy'] !== '' && $data['usable_energy'] !== '' && $data['nominal_energy'] !== null && $data['usable_energy'] !== null && (float) $data['usable_energy'] > (float) $data['nominal_energy']) {
			return $this->setError('BatteryInvalidRange');
		}

		$productentity = $this->fetchProductEntity($fkProduct);
		if ($productentity <= 0) {
			return $this->setError('ErrorRecordNotFound');
		}
		$result = $this->fetchByProduct($fkProduct);
		if ($result < 0) {
			return -1;
		}
		$sets = $this->buildSetSql(self::getBatteryFields(), $data);
		$sets[] = 'fk_user_modif = '.((int) $user->id);
		if ($result > 0) {
			$sql = 'UPDATE '.$this->db->prefix().'powerplantpv_product_battery SET '.implode(', ', $sets);
			$sql .= ' WHERE rowid = '.((int) $this->id).' AND entity = '.$productentity;
		} else {
			$sql = 'INSERT INTO '.$this->db->prefix().'powerplantpv_product_battery';
			$sql .= ' (fk_product, entity, datec, fk_user_creat, fk_user_modif) VALUES (';
			$sql .= ((int) $fkProduct).', '.$productentity.", '".$this->db->idate(dol_now())."', ".((int) $user->id).', '.((int) $user->id).')';
		}
		if (!$this->db->query($sql)) {
			return $this->setError($this->db->lasterror());
		}
		if ($result === 0) {
			$this->id = (int) $this->db->last_insert_id($this->db->prefix().'powerplantpv_product_battery');
			$sql = 'UPDATE '.$this->db->prefix().'powerplantpv_product_battery SET '.implode(', ', $sets).' WHERE rowid = '.((int) $this->id).' AND entity = '.$productentity;
			if (!$this->db->query($sql)) {
				return $this->setError($this->db->lasterror());
			}
		}
		$this->fetchByProduct($fkProduct);
		return $this->id;
	}

	/**
	 * @param int $fkBattery Battery rowid
	 * @return array<string,array<int,object>> Attributes grouped by type
	 */
	public function fetchAttributes($fkBattery)
	{
		$attributes = array('PROTOCOL' => array(), 'PROTECTION' => array(), 'CERTIFICATION' => array());
		$batteryentity = $this->fetchBatteryEntity($fkBattery);
		if ($batteryentity <= 0) {
			return $attributes;
		}
		$sql = 'SELECT rowid, attribute_type, attribute_code, attribute_label, position';
		$sql .= ' FROM '.$this->db->prefix().'powerplantpv_product_battery_attribute';
		$sql .= ' WHERE fk_battery = '.((int) $fkBattery).' AND entity = '.$batteryentity;
		$sql .= ' ORDER BY attribute_type, position, rowid';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return $attributes;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			if (isset($attributes[$obj->attribute_type])) {
				$attributes[$obj->attribute_type][] = $obj;
			}
		}
		$this->db->free($resql);
		return $attributes;
	}

	/**
	 * Replace normalized attributes.
	 *
	 * @param int $fkBattery Battery id
	 * @param array<string,array<int,array<string,string>>> $attributes Attributes by type
	 * @return int 1 or <0
	 */
	public function replaceAttributes($fkBattery, array $attributes)
	{
		$batteryentity = $this->fetchBatteryEntity($fkBattery);
		if ($batteryentity <= 0) {
			return $this->setError('ErrorRecordNotFound');
		}
		$allowed = array_keys(self::getAttributeTypeOptions());
		$sql = 'DELETE FROM '.$this->db->prefix().'powerplantpv_product_battery_attribute';
		$sql .= ' WHERE fk_battery = '.((int) $fkBattery).' AND entity = '.$batteryentity;
		if (!$this->db->query($sql)) {
			return $this->setError($this->db->lasterror());
		}
		foreach ($attributes as $type => $rows) {
			if (!in_array($type, $allowed, true)) {
				continue;
			}
			$position = 0;
			$seen = array();
			foreach ($rows as $row) {
				$code = strtoupper(trim((string) (isset($row['code']) ? $row['code'] : '')));
				if ($code === '' || isset($seen[$code])) {
					continue;
				}
				$seen[$code] = 1;
				$position++;
				$label = isset($row['label']) ? trim((string) $row['label']) : '';
				$sql = 'INSERT INTO '.$this->db->prefix().'powerplantpv_product_battery_attribute';
				$sql .= ' (fk_battery, entity, attribute_type, attribute_code, attribute_label, position) VALUES (';
				$sql .= ((int) $fkBattery).', '.$batteryentity.", '".$this->db->escape($type)."', '".$this->db->escape($code)."', ".($label === '' ? 'NULL' : "'".$this->db->escape($label)."'").', '.((int) $position).')';
				if (!$this->db->query($sql)) {
					return $this->setError($this->db->lasterror());
				}
			}
		}
		return 1;
	}

	/**
	 * @param int $fkProduct Product id
	 * @return object|null Accessory row
	 */
	public function fetchAccessoryByProduct($fkProduct)
	{
		$sql = 'SELECT a.rowid, a.fk_product, a.entity, a.role_code, a.note_private';
		$sql .= ' FROM '.$this->db->prefix().'powerplantpv_product_battery_accessory as a';
		$sql .= ' INNER JOIN '.$this->db->prefix().'product as p ON p.rowid = a.fk_product AND p.entity = a.entity';
		$sql .= ' WHERE a.fk_product = '.((int) $fkProduct).' AND p.entity IN ('.getEntity('product').')';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return null;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		return $obj ?: null;
	}

	/**
	 * @param int $fkProduct Product id
	 * @param string $roleCode Controlled role code
	 * @param string $notePrivate Private note
	 * @param User $user Current user
	 * @return int Accessory id or <0
	 */
	public function saveAccessoryForProduct($fkProduct, $roleCode, $notePrivate, User $user)
	{
		if (!isset(self::getAccessoryRoleOptions()[$roleCode])) {
			return $this->setError('BatteryInvalidAccessoryRole');
		}
		$productentity = $this->fetchProductEntity($fkProduct);
		if ($productentity <= 0) {
			return $this->setError('ErrorRecordNotFound');
		}
		$existing = $this->fetchAccessoryByProduct($fkProduct);
		if ($existing) {
			$sql = 'UPDATE '.$this->db->prefix().'powerplantpv_product_battery_accessory SET';
			$sql .= " role_code = '".$this->db->escape($roleCode)."', note_private = '".$this->db->escape($notePrivate)."', fk_user_modif = ".((int) $user->id);
			$sql .= ' WHERE rowid = '.((int) $existing->rowid).' AND entity = '.$productentity;
		} else {
			$sql = 'INSERT INTO '.$this->db->prefix().'powerplantpv_product_battery_accessory';
			$sql .= ' (fk_product, entity, role_code, note_private, datec, fk_user_creat, fk_user_modif) VALUES (';
			$sql .= ((int) $fkProduct).', '.$productentity.", '".$this->db->escape($roleCode)."', '".$this->db->escape($notePrivate)."', '".$this->db->idate(dol_now())."', ".((int) $user->id).', '.((int) $user->id).')';
		}
		if (!$this->db->query($sql)) {
			return $this->setError($this->db->lasterror());
		}
		$stored = $this->fetchAccessoryByProduct($fkProduct);
		return $stored ? (int) $stored->rowid : -1;
	}

	/**
	 * @param int $fkAccessory Accessory rowid
	 * @return array<int,object> Rules
	 */
	public function fetchAccessoryRules($fkAccessory)
	{
		$rules = array();
		$accessoryentity = $this->fetchAccessoryEntity($fkAccessory);
		if ($accessoryentity <= 0) {
			return $rules;
		}
		$sql = 'SELECT rowid, rule_effect, criterion_type, fk_target_product, value_code, min_value, max_value, unit_code, min_quantity, max_quantity, position, note_private';
		$sql .= ' FROM '.$this->db->prefix().'powerplantpv_product_battery_accessory_rule';
		$sql .= ' WHERE fk_accessory = '.((int) $fkAccessory).' AND entity = '.$accessoryentity.' ORDER BY position, rowid';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return $rules;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$rules[] = $obj;
		}
		$this->db->free($resql);
		return $rules;
	}

	/**
	 * Replace normalized accessory compatibility rules.
	 *
	 * @param int $fkAccessory Accessory rowid
	 * @param array<int,array<string,mixed>> $rules Rules
	 * @return int 1 or <0
	 */
	public function replaceAccessoryRules($fkAccessory, array $rules)
	{
		$accessoryentity = $this->fetchAccessoryEntity($fkAccessory);
		if ($accessoryentity <= 0) {
			return $this->setError('ErrorRecordNotFound');
		}
		$sql = 'DELETE FROM '.$this->db->prefix().'powerplantpv_product_battery_accessory_rule';
		$sql .= ' WHERE fk_accessory = '.((int) $fkAccessory).' AND entity = '.$accessoryentity;
		if (!$this->db->query($sql)) {
			return $this->setError($this->db->lasterror());
		}
		$effects = self::getRuleEffectOptions();
		$criteria = self::getCriterionTypeOptions();
		$position = 0;
		foreach ($rules as $rule) {
			$effect = isset($rule['rule_effect']) ? (string) $rule['rule_effect'] : '';
			$criterion = isset($rule['criterion_type']) ? (string) $rule['criterion_type'] : '';
			if (!isset($effects[$effect]) || !isset($criteria[$criterion])) {
				return $this->setError('BatteryInvalidCompatibilityRule');
			}
			$position++;
			$targetproduct = !empty($rule['fk_target_product']) ? (int) $rule['fk_target_product'] : 0;
			if ($criterion !== 'PRODUCT') {
				$targetproduct = 0;
			}
			if ($targetproduct > 0 && $this->fetchProductEntity($targetproduct) <= 0) {
				return $this->setError('BatteryInvalidTargetProduct');
			}
			$valuecode = isset($rule['value_code']) ? trim((string) $rule['value_code']) : '';
			if ($criterion === 'PRODUCT' && $targetproduct <= 0) {
				return $this->setError('BatteryInvalidRuleValue');
			}
			if (in_array($criterion, array('FAMILY', 'BRAND', 'STORAGE_TYPE', 'CHEMISTRY', 'PROTOCOL'), true) && $valuecode === '') {
				return $this->setError('BatteryInvalidRuleValue');
			}
			if ($criterion === 'STORAGE_TYPE' && !isset(self::getStorageTypeOptions()[$valuecode])) {
				return $this->setError('BatteryInvalidRuleValue');
			}
			if ($criterion === 'CHEMISTRY' && !isset(self::getChemistryOptions()[$valuecode])) {
				return $this->setError('BatteryInvalidRuleValue');
			}
			$unitcode = isset($rule['unit_code']) ? trim((string) $rule['unit_code']) : '';
			if ($criterion === 'CAPACITY') {
				$unitcode = 'kWh';
			} elseif ($criterion === 'MODULE_COUNT') {
				$unitcode = 'pcs';
			} elseif ($unitcode !== '' && !isset(self::getRuleUnitOptions()[$unitcode])) {
				return $this->setError('BatteryInvalidRuleUnit');
			}
			$note = isset($rule['note_private']) ? trim((string) $rule['note_private']) : '';
			$minvalue = isset($rule['min_value']) && $rule['min_value'] !== '' ? (float) $rule['min_value'] : null;
			$maxvalue = isset($rule['max_value']) && $rule['max_value'] !== '' ? (float) $rule['max_value'] : null;
			$minquantity = isset($rule['min_quantity']) && $rule['min_quantity'] !== '' ? (float) $rule['min_quantity'] : null;
			$maxquantity = isset($rule['max_quantity']) && $rule['max_quantity'] !== '' ? (float) $rule['max_quantity'] : null;
			if (!in_array($criterion, array('CAPACITY', 'MODULE_COUNT'), true)) {
				$minvalue = null;
				$maxvalue = null;
			} else {
				$valuecode = '';
			}
			if ($criterion === 'PRODUCT') {
				$valuecode = '';
			}
			if (in_array($criterion, array('CAPACITY', 'MODULE_COUNT'), true) && $minvalue === null && $maxvalue === null) {
				return $this->setError('BatteryInvalidRuleValue');
			}
			if (($minquantity !== null && $minquantity < 0) || ($maxquantity !== null && $maxquantity < 0) || (in_array($criterion, array('CAPACITY', 'MODULE_COUNT'), true) && (($minvalue !== null && $minvalue < 0) || ($maxvalue !== null && $maxvalue < 0)))) {
				return $this->setError('BatteryInvalidPositiveValue');
			}
			if (($minvalue !== null && $maxvalue !== null && $minvalue > $maxvalue) || ($minquantity !== null && $maxquantity !== null && $minquantity > $maxquantity)) {
				return $this->setError('BatteryInvalidRuleRange');
			}
			$sql = 'INSERT INTO '.$this->db->prefix().'powerplantpv_product_battery_accessory_rule';
			$sql .= ' (fk_accessory, entity, rule_effect, criterion_type, fk_target_product, value_code, min_value, max_value, unit_code, min_quantity, max_quantity, position, note_private) VALUES (';
			$sql .= ((int) $fkAccessory).', '.$accessoryentity.", '".$this->db->escape($effect)."', '".$this->db->escape($criterion)."', ";
			$sql .= ($targetproduct > 0 ? (string) $targetproduct : 'NULL').', '.($valuecode !== '' ? "'".$this->db->escape($valuecode)."'" : 'NULL').', ';
			$sql .= ($minvalue !== null ? (string) $minvalue : 'NULL').', '.($maxvalue !== null ? (string) $maxvalue : 'NULL').', ';
			$sql .= ($unitcode !== '' ? "'".$this->db->escape($unitcode)."'" : 'NULL').', ';
			$sql .= ($minquantity !== null ? (string) $minquantity : 'NULL').', '.($maxquantity !== null ? (string) $maxquantity : 'NULL').', ';
			$sql .= ((int) $position).', '.($note !== '' ? "'".$this->db->escape($note)."'" : 'NULL').')';
			if (!$this->db->query($sql)) {
				return $this->setError($this->db->lasterror());
			}
		}
		return 1;
	}

	/**
	 * @param array<string,array<string,mixed>> $definitions Field definitions
	 * @param array<string,mixed> $data Values
	 * @return array<int,string> SQL assignments
	 */
	private function buildSetSql(array $definitions, array $data)
	{
		$sets = array();
		foreach ($definitions as $field => $spec) {
			$value = array_key_exists($field, $data) ? $data[$field] : null;
			if ($value === '' || $value === null) {
				$sets[] = $field.' = NULL';
			} elseif ($spec['type'] === 'double') {
				$sets[] = $field.' = '.((float) $value);
			} elseif ($spec['type'] === 'int') {
				$sets[] = $field.' = '.((int) $value);
			} else {
				$sets[] = $field." = '".$this->db->escape((string) $value)."'";
			}
		}
		return $sets;
	}

	/** @param int $fkProduct Product id @return int Product entity or 0 */
	private function fetchProductEntity($fkProduct)
	{
		$sql = 'SELECT entity FROM '.$this->db->prefix().'product';
		$sql .= ' WHERE rowid = '.((int) $fkProduct).' AND entity IN ('.getEntity('product').')';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return 0;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		return $obj ? (int) $obj->entity : 0;
	}

	/** @param int $fkBattery Battery id @return int Battery entity or 0 */
	private function fetchBatteryEntity($fkBattery)
	{
		$sql = 'SELECT b.entity FROM '.$this->db->prefix().'powerplantpv_product_battery as b';
		$sql .= ' INNER JOIN '.$this->db->prefix().'product as p ON p.rowid = b.fk_product AND p.entity = b.entity';
		$sql .= ' WHERE b.rowid = '.((int) $fkBattery).' AND p.entity IN ('.getEntity('product').')';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return 0;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		return $obj ? (int) $obj->entity : 0;
	}

	/** @param int $fkAccessory Accessory id @return int Accessory entity or 0 */
	private function fetchAccessoryEntity($fkAccessory)
	{
		$sql = 'SELECT a.entity FROM '.$this->db->prefix().'powerplantpv_product_battery_accessory as a';
		$sql .= ' INNER JOIN '.$this->db->prefix().'product as p ON p.rowid = a.fk_product AND p.entity = a.entity';
		$sql .= ' WHERE a.rowid = '.((int) $fkAccessory).' AND p.entity IN ('.getEntity('product').')';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return 0;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		return $obj ? (int) $obj->entity : 0;
	}

	/**
	 * @param string $message Error message
	 * @return int Always -1
	 */
	private function setError($message)
	{
		$this->error = $message;
		$this->errors[] = $message;
		return -1;
	}
}
