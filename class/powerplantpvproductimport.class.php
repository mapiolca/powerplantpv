<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file       class/powerplantpvproductimport.class.php
 * \ingroup    powerplantpv
 * \brief      Product technical data importer.
 */

dol_include_once('/powerplantpv/class/productinverter.class.php');
dol_include_once('/powerplantpv/class/productbattery.class.php');
dol_include_once('/powerplantpv/class/powerplantpvproductdatasource.class.php');
dol_include_once('/powerplantpv/class/powerplantpvproductdictionary.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_powerplant.lib.php');

/**
 * Normalize and import external data into existing PowerPlantPV product technical tables.
 */
class PowerPlantPVProductImport
{
	public const STRATEGY_NEVER = 'never';
	public const STRATEGY_EMPTY_ONLY = 'empty_only';
	public const STRATEGY_OVERWRITE_AFTER_CONFIRM = 'overwrite_after_confirm';

	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Error message
	 */
	public $error = '';

	/**
	 * @var array<int,string> Error messages
	 */
	public $errors = array();

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Return PV panel fields imported from generic CSV/XLSX files.
	 *
	 * @return array<int,string> Fields
	 */
	public static function getModuleImportFields()
	{
		return array(
			'pmax',
			'power_tolerance',
			'module_efficiency',
			'vmp',
			'imp',
			'voc',
			'isc',
			'front_glass_thickness',
			'back_glass_thickness',
			'cable_section',
			'cable_length',
			'noct',
			'temp_coeff_pmax',
			'temp_coeff_voc',
			'temp_coeff_isc',
			'max_system_voltage',
			'max_series_fuse',
			'operating_temperature',
			'snow_load',
			'wind_load',
			'product_warranty',
			'power_warranty',
			'first_year_degradation',
			'annual_degradation',
			'modules_per_box',
			'modules_per_container40',
		);
	}

	/**
	 * Return inverter fields imported from generic CSV/XLSX files.
	 *
	 * @return array<int,string> Fields
	 */
	public static function getInverterImportFields()
	{
		$fields = array_keys(ProductInverter::getInverterFields());
		$legacy = array('communication_interfaces', 'certifications', 'dc_spd', 'ac_spd');
		return array_values(array_diff($fields, $legacy));
	}

	/** @return array<int,string> Battery scalar fields */
	public static function getBatteryImportFields()
	{
		return array_keys(ProductBattery::getBatteryFields());
	}

	/** @return array<int,string> Repeated normalized technical dictionary fields */
	public static function getTechnicalDictionaryTemplateFields()
	{
		$fields = array();
		foreach (array('communication_protocol', 'certification', 'protection') as $prefix) {
			for ($i = 1; $i <= 8; $i++) {
				$fields[] = $prefix.'_'.$i;
			}
		}
		return $fields;
	}

	/** @return array<int,string> Backward-compatible alias */
	public static function getBatteryAttributeTemplateFields()
	{
		return self::getTechnicalDictionaryTemplateFields();
	}

	/**
	 * Return the expected unit/format for one canonical import field.
	 *
	 * @param string $type module|inverter|battery
	 * @param string $field Canonical field
	 * @return string Unit or format
	 */
	public static function getImportFieldUnit($type, $field)
	{
		if ($type === 'battery') {
			$fields = ProductBattery::getBatteryFields();
			return isset($fields[$field]['unit']) ? (string) $fields[$field]['unit'] : 'text';
		}
		$moduleunits = array(
			'pmax' => 'Wc', 'power_tolerance' => '%', 'module_efficiency' => '%', 'vmp' => 'V', 'imp' => 'A', 'voc' => 'V', 'isc' => 'A',
			'front_glass_thickness' => 'mm', 'back_glass_thickness' => 'mm', 'cable_section' => 'mm²', 'cable_length' => 'mm', 'noct' => '°C',
			'temp_coeff_pmax' => '%/°C', 'temp_coeff_voc' => '%/°C', 'temp_coeff_isc' => '%/°C', 'max_system_voltage' => 'V', 'max_series_fuse' => 'A',
			'operating_temperature' => '°C', 'snow_load' => 'Pa', 'wind_load' => 'Pa', 'product_warranty' => 'years', 'power_warranty' => 'years',
			'first_year_degradation' => '%', 'annual_degradation' => '%/year', 'modules_per_box' => 'pcs', 'modules_per_container40' => 'pcs',
		);
		if ($type === 'module') {
			return isset($moduleunits[$field]) ? $moduleunits[$field] : 'text';
		}
		$inverterunits = array(
			'pv_max_power' => 'W', 'dc_max_voltage' => 'V', 'startup_voltage' => 'V', 'mppt_voltage_min' => 'V', 'mppt_voltage_max' => 'V', 'nominal_dc_voltage' => 'V',
			'ac_nominal_power' => 'W', 'ac_max_power' => 'W', 'ac_apparent_power' => 'VA', 'ac_nominal_voltage' => 'V', 'grid_frequency' => 'Hz', 'ac_max_output_current' => 'A',
			'phase_count' => 'pcs', 'power_factor' => 'ratio', 'thd' => '%', 'backup_nominal_power' => 'W', 'backup_peak_power' => 'W', 'backup_peak_duration' => 's',
			'backup_transfer_time' => 'ms', 'backup_nominal_voltage' => 'V', 'backup_max_current' => 'A', 'backup_thd' => '%', 'max_unbalanced_output' => '%',
			'max_efficiency' => '%', 'european_efficiency' => '%', 'dc_switch' => '0/1', 'afci' => '0/1', 'pid_recovery' => '0/1', 'anti_islanding' => '0/1',
			'dc_reverse_polarity_protection' => '0/1', 'insulation_monitoring' => '0/1', 'residual_current_monitoring' => '0/1', 'max_altitude' => 'm',
			'operating_temperature' => '°C', 'relative_humidity' => '%', 'noise' => 'dB(A)', 'night_consumption' => 'W',
		);
		return isset($inverterunits[$field]) ? $inverterunits[$field] : 'text';
	}

	/**
	 * Return the canonical import metadata for one technical field.
	 *
	 * @return array<string,mixed>
	 */
	public static function getImportFieldDefinition($type, $field)
	{
		if (preg_match('/^(communication_protocol|certification|protection)_[0-9]+$/D', $field, $matches)) {
			return array(
				'field' => $field,
				'family' => $type,
				'type' => 'multiselect2',
				'unit' => '',
				'cardinality' => '0..N',
				'format' => 'CODE|Libellé',
				'source' => (string) $matches[1],
				'options' => array(),
			);
		}

		$rawtype = 'double';
		$options = array();
		if ($type === 'module' && in_array($field, array('modules_per_box', 'modules_per_container40'), true)) {
			$rawtype = 'int';
		}
		if ($type === 'battery') {
			$fields = ProductBattery::getBatteryFields();
			$spec = isset($fields[$field]) ? $fields[$field] : array();
			$rawtype = isset($spec['type']) ? (string) $spec['type'] : 'varchar';
			$options = isset($spec['options']) && is_array($spec['options']) ? $spec['options'] : array();
		} elseif ($type === 'inverter') {
			$fields = ProductInverter::getInverterFields();
			$spec = isset($fields[$field]) ? $fields[$field] : array();
			$rawtype = isset($spec['type']) ? (string) $spec['type'] : 'varchar';
			if (!empty($spec['numeric']) && !in_array($rawtype, array('double', 'int'), true)) {
				$rawtype = 'double';
			}
		}

		$datatype = 'text';
		if ($rawtype === 'double') {
			$datatype = 'decimal';
		} elseif ($rawtype === 'int') {
			$datatype = 'integer';
		} elseif ($rawtype === 'bool') {
			$datatype = 'boolean';
		} elseif ($rawtype === 'select') {
			$datatype = 'select2';
		}

		$unit = self::getImportFieldUnit($type, $field);
		if (in_array($datatype, array('text', 'select2', 'boolean'), true) || $unit === 'text' || $unit === '0/1') {
			$unit = '';
		}
		$formats = array('decimal' => 'SIGNED_DECIMAL', 'integer' => 'SIGNED_INTEGER', 'boolean' => '0|1', 'text' => 'TEXT', 'select2' => 'CODE');

		return array(
			'field' => $field,
			'family' => $type,
			'type' => $datatype,
			'unit' => $unit,
			'cardinality' => '0..1',
			'format' => isset($formats[$datatype]) ? $formats[$datatype] : '',
			'source' => $datatype === 'select2' ? $field : '',
			'options' => $options,
		);
	}

	/**
	 * Return the canonical import metadata for one native product field.
	 *
	 * @param string $field Native import field
	 * @return array<string,string>
	 */
	public static function getNativeProductImportFieldDefinition($field)
	{
		$selects = array('category_code' => 'category_code', 'price_base_type' => 'price_base_type', 'barcode_type_code' => 'barcode_type_code', 'weight_unit' => 'weight_unit', 'size_unit' => 'size_unit');
		$booleans = array('status_sell', 'status_buy');
		$decimals = array('price', 'vat_rate', 'weight', 'length', 'width', 'height');
		$cardinality = in_array($field, array('ref', 'category_code'), true) ? '1' : '0..1';
		if (isset($selects[$field])) {
			return array('type' => 'select2', 'cardinality' => $cardinality, 'format' => 'CODE', 'source' => $selects[$field]);
		}
		if (in_array($field, $booleans, true)) {
			return array('type' => 'boolean', 'cardinality' => $cardinality, 'format' => '0|1', 'source' => 'boolean');
		}
		if (in_array($field, $decimals, true)) {
			return array('type' => 'decimal', 'unit' => $field === 'vat_rate' ? '%' : '', 'cardinality' => $cardinality, 'format' => 'SIGNED_DECIMAL', 'source' => 'product');
		}
		return array(
			'type' => 'text',
			'cardinality' => $cardinality,
			'format' => $field === 'ref' ? 'REFERENCE' : 'TEXT',
			'source' => 'product',
		);
	}

	/**
	 * Format a canonical field as a self-documenting template header.
	 *
	 * @param string $type Import type
	 * @param string $field Canonical field
	 * @return string Header
	 */
	public static function getTemplateHeader($type, $field)
	{
		$definition = self::getImportFieldDefinition($type, $field);
		$parts = array('type='.$definition['type']);
		if ((string) $definition['unit'] !== '') {
			$parts[] = 'unit='.$definition['unit'];
		}
		if ((string) $definition['format'] !== '') {
			$parts[] = 'format='.$definition['format'];
		}
		if ((string) $definition['source'] !== '') {
			$parts[] = 'source='.$definition['source'];
		}
		return $field.' ['.implode('; ', $parts).']';
	}

	/**
	 * Return sample MPPT composition fields for generic inverter CSV/XLSX templates.
	 *
	 * @param int $mpptCount  Number of MPPT groups
	 * @param int $inputCount Number of PV inputs per MPPT group
	 * @return array<int,string> Fields
	 */
	public static function getInverterMPPTCompositionTemplateFields($mpptCount = 4, $inputCount = 2)
	{
		$fields = array();
		for ($mppt = 1; $mppt <= $mpptCount; $mppt++) {
			$prefix = 'mppt_'.$mppt.'_';
			$fields[] = $prefix.'label';
			$fields[] = $prefix.'voltage_min';
			$fields[] = $prefix.'voltage_max';
			$fields[] = $prefix.'max_input_current';
			$fields[] = $prefix.'max_short_circuit_current';
			$fields[] = $prefix.'max_dc_power';
			for ($input = 1; $input <= $inputCount; $input++) {
				$inputprefix = $prefix.'input_'.$input.'_';
				$fields[] = $inputprefix.'label';
				$fields[] = $inputprefix.'max_input_current';
				$fields[] = $inputprefix.'max_short_circuit_current';
				$fields[] = $inputprefix.'connector_type';
			}
		}

		return $fields;
	}

	/**
	 * Return PV Free module fields from connector V1.
	 *
	 * @return array<int,string> Fields
	 */
	protected static function getPVFreeModuleImportFields()
	{
		return array('pmax', 'vmp', 'imp', 'voc', 'isc', 'module_efficiency', 'noct', 'temp_coeff_pmax', 'temp_coeff_voc', 'temp_coeff_isc');
	}

	/**
	 * Return PV Free inverter fields from connector V1.
	 *
	 * @return array<int,string> Fields
	 */
	protected static function getPVFreeInverterImportFields()
	{
		return array('pv_max_power', 'dc_max_voltage', 'startup_voltage', 'mppt_voltage_min', 'mppt_voltage_max', 'nominal_dc_voltage', 'ac_nominal_power', 'ac_max_power', 'ac_apparent_power', 'ac_nominal_voltage', 'grid_frequency', 'ac_max_output_current', 'max_efficiency', 'european_efficiency');
	}

	/**
	 * Normalize a PV Free module object.
	 *
	 * @param array<string,mixed> $rawData Raw API data
	 * @param string              $dataset Dataset
	 * @return array<string,mixed> Normalized data
	 */
	public function normalizeModule(array $rawData, $dataset)
	{
		if ($dataset === 'pvmodule') {
			$vmp = $this->toFloat($rawData, 'Vmpo');
			$imp = $this->toFloat($rawData, 'Impo');
			$pmax = ($vmp !== null && $imp !== null) ? $vmp * $imp : null;
			$area = $this->toFloat($rawData, 'Area');

			return array(
				'_dataset' => $dataset,
				'pmax' => $pmax,
				'vmp' => $vmp,
				'imp' => $imp,
				'voc' => $this->toFloat($rawData, 'Voco'),
				'isc' => $this->toFloat($rawData, 'Isco'),
				'module_efficiency' => ($pmax !== null && $area !== null && $area > 0) ? ($pmax / ($area * 1000) * 100) : null,
				'noct' => null,
				'temp_coeff_pmax' => null,
				'temp_coeff_voc' => null,
				'temp_coeff_isc' => null,
			);
		}

		$stc = $this->toFloat($rawData, 'STC');
		$area = $this->toFloat($rawData, 'A_c');
		$voc = $this->toFloat($rawData, 'V_oc_ref');
		$isc = $this->toFloat($rawData, 'I_sc_ref');
		$betaoc = $this->toFloat($rawData, 'beta_oc');
		$alphasc = $this->toFloat($rawData, 'alpha_sc');

		return array(
			'_dataset' => $dataset,
			'pmax' => $stc,
			'vmp' => $this->toFloat($rawData, 'V_mp_ref'),
			'imp' => $this->toFloat($rawData, 'I_mp_ref'),
			'voc' => $voc,
			'isc' => $isc,
			'module_efficiency' => ($stc !== null && $area !== null && $area > 0) ? ($stc / ($area * 1000) * 100) : null,
			'noct' => $this->toFloat($rawData, 'T_NOCT'),
			'temp_coeff_pmax' => $this->toFloat($rawData, 'gamma_r'),
			'temp_coeff_voc' => ($betaoc !== null && $voc !== null && abs($voc) > 0.000000001) ? ($betaoc / $voc * 100) : null,
			'temp_coeff_isc' => ($alphasc !== null && $isc !== null && abs($isc) > 0.000000001) ? ($alphasc / $isc * 100) : null,
		);
	}

	/**
	 * Normalize a PV Free inverter object.
	 *
	 * @param array<string,mixed> $rawData Raw API data
	 * @param string              $dataset Dataset
	 * @return array<string,mixed> Normalized data
	 */
	public function normalizeInverter(array $rawData, $dataset)
	{
		return array(
			'_dataset' => $dataset,
			'pv_max_power' => $this->toFloat($rawData, 'Pdco'),
			'dc_max_voltage' => $this->toFloat($rawData, 'Vdcmax'),
			'startup_voltage' => null,
			'mppt_voltage_min' => $this->toFloat($rawData, 'Mppt_low'),
			'mppt_voltage_max' => $this->toFloat($rawData, 'Mppt_high'),
			'nominal_dc_voltage' => $this->toFloat($rawData, 'Vdco'),
			'ac_nominal_power' => $this->toFloat($rawData, 'Paco'),
			'ac_max_power' => $this->toFloat($rawData, 'Paco'),
			'ac_apparent_power' => null,
			'ac_nominal_voltage' => $this->toScalarString($rawData, 'Vac'),
			'grid_frequency' => null,
			'ac_max_output_current' => $this->toFloat($rawData, 'Idcmax'),
			'max_efficiency' => null,
			'european_efficiency' => null,
		);
	}

	/**
	 * Preview a module import.
	 *
	 * @param int                 $fkProduct      Product id
	 * @param array<string,mixed> $normalizedData Normalized data
	 * @param string              $strategy       Import strategy
	 * @param array<string,array<string,mixed>> $dictionaryResolutions Trusted dictionary decisions
	 * @return array<string,mixed> Preview data
	 */
	public function previewModuleImport($fkProduct, array $normalizedData, $strategy, array $dictionaryResolutions = array())
	{
		$this->resetErrors();

		$current = $this->fetchPvPanel($fkProduct);
		$fields = (isset($normalizedData['_dataset']) && in_array($normalizedData['_dataset'], array('cecmodule', 'pvmodule'), true)) ? self::getPVFreeModuleImportFields() : self::getModuleImportFields();
		$preview = $this->buildPreview($fields, $current, $normalizedData, $strategy);
		return $this->appendTechnicalDictionaryPreview($preview, $fkProduct, $normalizedData, $strategy, $dictionaryResolutions);
	}

	/**
	 * Preview an inverter import.
	 *
	 * @param int                 $fkProduct      Product id
	 * @param array<string,mixed> $normalizedData Normalized data
	 * @param string              $strategy       Import strategy
	 * @param array<string,array<string,mixed>> $dictionaryResolutions Trusted dictionary decisions
	 * @return array<string,mixed> Preview data
	 */
	public function previewInverterImport($fkProduct, array $normalizedData, $strategy, array $dictionaryResolutions = array())
	{
		$this->resetErrors();

		$inverter = new ProductInverter($this->db);
		$current = null;
		$result = $inverter->fetchByProduct($fkProduct);
		if ($result < 0) {
			$this->setError($inverter->error);
		} elseif ($result > 0) {
			$current = (object) $inverter->data;
			$current->rowid = $inverter->id;
		}

		$fields = (isset($normalizedData['_dataset']) && $normalizedData['_dataset'] === 'pvinverter') ? self::getPVFreeInverterImportFields() : self::getInverterImportFields();
		$preview = $this->buildPreview($fields, $current, $normalizedData, $strategy);
		$preview = array_merge($preview, $this->buildMpptCompositionPreview($fkProduct, $normalizedData, $strategy));
		$preview = $this->appendTechnicalDictionaryPreview($preview, $fkProduct, $normalizedData, $strategy, $dictionaryResolutions);

		return $preview;
	}

	/**
	 * Preview a battery import.
	 *
	 * @param int $fkProduct Product id
	 * @param array<string,mixed> $normalizedData Normalized data
	 * @param string $strategy Import strategy
	 * @param array<string,array<string,mixed>> $dictionaryResolutions Trusted dictionary decisions
	 * @return array<string,mixed> Preview data
	 */
	public function previewBatteryImport($fkProduct, array $normalizedData, $strategy, array $dictionaryResolutions = array())
	{
		$this->resetErrors();
		$battery = new ProductBattery($this->db);
		$current = null;
		$result = $battery->fetchByProduct($fkProduct);
		if ($result < 0) {
			$this->setError($battery->error);
		} elseif ($result > 0) {
			$current = (object) $battery->data;
			$current->rowid = $battery->id;
		}
		$preview = $this->buildPreview(self::getBatteryImportFields(), $current, $normalizedData, $strategy);
		return $this->appendTechnicalDictionaryPreview($preview, $fkProduct, $normalizedData, $strategy, $dictionaryResolutions);
	}

	/**
	 * Validate and append normalized dictionary selections to a preview.
	 *
	 * @param array<string,mixed> $preview Existing preview
	 * @param int $fkProduct Product identifier
	 * @param array<string,mixed> $normalizedData Normalized import data
	 * @param string $strategy Overwrite strategy
	 * @param array<string,array<string,mixed>> $dictionaryResolutions Trusted dictionary decisions
	 * @return array<string,mixed>
	 */
	protected function appendTechnicalDictionaryPreview(array $preview, $fkProduct, array $normalizedData, $strategy, array $dictionaryResolutions = array())
	{
		$groups = isset($normalizedData['_technical_dictionary_codes']) && is_array($normalizedData['_technical_dictionary_codes']) ? $normalizedData['_technical_dictionary_codes'] : array();
		$preview['technical_dictionary_apply'] = array();
		$preview['technical_dictionary_issues'] = array();
		$preview['technical_dictionary_warnings'] = array();
		$preview['requires_dictionary_resolution'] = false;
		if (empty($groups)) {
			return $preview;
		}

		$entity = $this->fetchProductEntity($fkProduct);
		if ($entity <= 0) {
			return $preview;
		}
		$service = new PowerPlantPVProductDictionary($this->db);
		$strategy = $this->sanitizeStrategy($strategy);
		foreach ($groups as $type => $values) {
			if (!is_array($values) || !isset(PowerPlantPVProductDictionary::getDefinitions()[$type])) {
				continue;
			}
			$plan = $service->buildImportResolutionPlan($type, $values, $entity, $dictionaryResolutions);
			if ($plan === false) {
				$this->error = $service->error;
				$this->errors = array_merge($this->errors, $service->errors);
				return $preview;
			}
			$preview['technical_dictionary_issues'] = array_replace($preview['technical_dictionary_issues'], (array) $plan['issues']);
			$preview['technical_dictionary_warnings'] = array_merge($preview['technical_dictionary_warnings'], (array) $plan['warnings']);
			if (empty($plan['complete'])) {
				$preview['requires_dictionary_resolution'] = true;
				continue;
			}

			$currentIds = $service->fetchSelectedIds($fkProduct, $type, $entity);
			$codeMap = $service->fetchCodeMap($type, $entity, true);
			if ($service->error !== '') {
				$this->error = $service->error;
				$this->errors = array_merge($this->errors, $service->errors);
				return $preview;
			}
			$idToCode = array();
			foreach ($codeMap as $dictionaryCode => $dictionaryEntry) {
				$idToCode[(int) $dictionaryEntry['id']] = (string) $dictionaryCode;
			}
			$proposedIds = array_map('intval', (array) $plan['ids']);
			sort($currentIds);
			sort($proposedIds);
			$key = 'technical_dictionary_'.$type;
			$currentDisplay = implode(', ', array_map(static function ($id) use ($idToCode) {
				return isset($idToCode[(int) $id]) ? $idToCode[(int) $id] : '#'.((int) $id);
			}, $currentIds));
			$proposedDisplay = implode(', ', (array) $plan['codes']);
			if (!empty($plan['preserve_existing'])) {
				$preview['ignored'][$key] = array('current' => $currentDisplay, 'proposed' => '', 'reason' => 'PowerPlantPVTechnicalDictionaryIgnoredPreserveExisting');
				continue;
			}
			if (($strategy === self::STRATEGY_NEVER && !empty($currentIds)) || ($strategy === self::STRATEGY_EMPTY_ONLY && !empty($currentIds))) {
				$preview['ignored'][$key] = array('current' => $currentDisplay, 'proposed' => $proposedDisplay, 'reason' => ($strategy === self::STRATEGY_NEVER ? 'PVFreeOverwriteNever' : 'PVFreeExistingValueKept'));
				continue;
			}
			if ($currentIds === $proposedIds && empty($plan['create'])) {
				$preview['ignored'][$key] = array('current' => $currentDisplay, 'proposed' => $proposedDisplay, 'reason' => 'PVFreeSameValue');
				continue;
			}
			$preview['changes'][$key] = array('current' => $currentDisplay, 'proposed' => $proposedDisplay);
			$preview['technical_dictionary_apply'][$type] = $plan;
		}

		return $preview;
	}

	/**
	 * Apply dictionary selections included in a validated preview.
	 *
	 * @param int $fkProduct Product identifier
	 * @param array<string,mixed> $preview Validated preview
	 * @param User $user Acting user
	 * @return int
	 */
	protected function applyTechnicalDictionaryPreview($fkProduct, array $preview, User $user)
	{
		$apply = isset($preview['technical_dictionary_apply']) && is_array($preview['technical_dictionary_apply']) ? $preview['technical_dictionary_apply'] : array();
		if (empty($apply)) {
			return 0;
		}
		$entity = $this->fetchProductEntity($fkProduct);
		if ($entity <= 0) {
			return -1;
		}
		$service = new PowerPlantPVProductDictionary($this->db);
		$selections = array();
		foreach ($apply as $type => $plan) {
			if (!is_array($plan)) {
				continue;
			}
			$ids = array_map('intval', isset($plan['ids']) && is_array($plan['ids']) ? $plan['ids'] : array());
			foreach (isset($plan['create']) && is_array($plan['create']) ? $plan['create'] : array() as $creation) {
				if (!is_array($creation)) {
					continue;
				}
				$id = $service->createOrFetchImportedValue($type, isset($creation['code']) ? $creation['code'] : '', isset($creation['label']) ? $creation['label'] : '', $entity, $user);
				if ($id <= 0) {
					$this->error = $service->error;
					$this->errors = array_merge($this->errors, $service->errors);
					return -1;
				}
				$ids[] = $id;
			}
			$selections[$type] = array_values(array_unique(array_filter($ids)));
		}
		$result = $service->replaceSelections($fkProduct, $entity, $selections, $user, false);
		if ($result < 0) {
			$this->error = $service->error;
			$this->errors = array_merge($this->errors, $service->errors);
			return -1;
		}
		return 1;
	}

	/** @return int */
	protected function fetchProductEntity($fkProduct)
	{
		$sql = 'SELECT entity FROM '.$this->db->prefix().'product WHERE rowid = '.((int) $fkProduct).' AND fk_product_type = 0';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!$obj) {
			$this->setError('PowerPlantPVTechnicalDictionaryInvalidProduct');
			return -1;
		}
		return (int) $obj->entity;
	}

	/**
	 * Build the preview for normalized protocol, protection and certification rows.
	 *
	 * @param ProductBattery $battery Battery object
	 * @param array<string,mixed> $normalizedData Normalized data
	 * @param string $strategy Import strategy
	 * @param bool $hasBattery Current battery row exists
	 * @return array<string,mixed> Preview additions
	 */
	protected function buildBatteryAttributePreview(ProductBattery $battery, array $normalizedData, $strategy, $hasBattery)
	{
		$changes = array();
		$ignored = array();
		$apply = array();
		$proposedgroups = isset($normalizedData['_battery_attributes']) && is_array($normalizedData['_battery_attributes']) ? $normalizedData['_battery_attributes'] : array();
		$currentgroups = $hasBattery ? $battery->fetchAttributes($battery->id) : array();
		if ($battery->error !== '') {
			$this->setError($battery->error);
			return array('changes' => $changes, 'ignored' => $ignored, 'apply' => $apply);
		}
		$strategy = $this->sanitizeStrategy($strategy);
		foreach (ProductBattery::getAttributeTypeOptions() as $type => $label) {
			if (!isset($proposedgroups[$type]) || !is_array($proposedgroups[$type])) {
				continue;
			}
			$proposed = $this->normalizeBatteryAttributeRows($proposedgroups[$type]);
			$current = $this->normalizeBatteryAttributeObjects(isset($currentgroups[$type]) && is_array($currentgroups[$type]) ? $currentgroups[$type] : array());
			$key = 'battery_attribute_'.strtolower($type);
			$currentdisplay = $this->formatBatteryAttributeRows($current);
			$proposeddisplay = $this->formatBatteryAttributeRows($proposed);
			if (($strategy === self::STRATEGY_NEVER && !empty($current)) || ($strategy === self::STRATEGY_EMPTY_ONLY && !empty($current))) {
				$ignored[$key] = array('current' => $currentdisplay, 'proposed' => $proposeddisplay, 'reason' => ($strategy === self::STRATEGY_NEVER ? 'PVFreeOverwriteNever' : 'PVFreeExistingValueKept'));
				continue;
			}
			if ($current === $proposed) {
				$ignored[$key] = array('current' => $currentdisplay, 'proposed' => $proposeddisplay, 'reason' => 'PVFreeSameValue');
				continue;
			}
			$changes[$key] = array('current' => $currentdisplay, 'proposed' => $proposeddisplay);
			$apply[$type] = $proposed;
		}
		return array('changes' => $changes, 'ignored' => $ignored, 'apply' => $apply);
	}

	/** @param array<int,mixed> $rows @return array<int,array<string,string>> */
	protected function normalizeBatteryAttributeRows(array $rows)
	{
		$normalized = array();
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			$code = strtoupper(trim((string) (isset($row['code']) ? $row['code'] : '')));
			if ($code !== '') {
				$normalized[] = array('code' => $code, 'label' => trim((string) (isset($row['label']) ? $row['label'] : '')));
			}
		}
		return $normalized;
	}

	/** @param array<int,object> $rows @return array<int,array<string,string>> */
	protected function normalizeBatteryAttributeObjects(array $rows)
	{
		$normalized = array();
		foreach ($rows as $row) {
			if (is_object($row)) {
				$normalized[] = array('code' => strtoupper(trim((string) $row->attribute_code)), 'label' => trim((string) $row->attribute_label));
			}
		}
		return $normalized;
	}

	/** @param array<int,array<string,string>> $rows @return string */
	protected function formatBatteryAttributeRows(array $rows)
	{
		$values = array();
		foreach ($rows as $row) {
			$values[] = $row['code'].($row['label'] !== '' ? '|'.$row['label'] : '');
		}
		return implode(', ', $values);
	}

	/**
	 * Import module data to product.
	 *
	 * @param int                 $fkProduct      Product id
	 * @param array<string,mixed> $normalizedData Normalized data
	 * @param array<string,mixed> $rawData        Raw data
	 * @param User                $user           Current user
	 * @param string              $strategy       Import strategy
	 * @param array<string,mixed> $sourceData     Optional source trace data
	 * @param array<string,array<string,mixed>> $dictionaryResolutions Trusted dictionary decisions
	 * @param bool $manageTransaction Let this importer own the transaction
	 * @return array<string,mixed> Result data
	 */
	public function importModuleToProduct($fkProduct, array $normalizedData, array $rawData, User $user, $strategy, array $sourceData = array(), array $dictionaryResolutions = array(), $manageTransaction = true)
	{
		$isgenericimport = !empty($sourceData) && (!isset($sourceData['source']) || $sourceData['source'] !== 'pvfree');
		$preview = $this->previewModuleImport($fkProduct, $normalizedData, $strategy, $dictionaryResolutions);
		if (!empty($preview['requires_dictionary_resolution'])) {
			$this->setError('PowerPlantPVTechnicalDictionaryResolutionRequired');
			return array('result' => -1, 'preview' => $preview);
		}
		if ($this->error) {
			return array('result' => -1, 'preview' => $preview);
		}
		if (empty($preview['changes'])) {
			return array('result' => 0, 'preview' => $preview, 'message' => ($isgenericimport ? 'ProductTechnicalImportNoFieldToImport' : 'PVFreeNoFieldToImport'));
		}

		if ($manageTransaction) {
			$this->db->begin();
		}

		$result = $this->savePvPanelChanges($fkProduct, $preview['changes']);
		if ($result < 0) {
			if ($manageTransaction) { $this->db->rollback(); }
			return array('result' => -1, 'preview' => $preview);
		}
		$result = $this->applyTechnicalDictionaryPreview($fkProduct, $preview, $user);
		if ($result < 0) {
			if ($manageTransaction) { $this->db->rollback(); }
			return array('result' => -1, 'preview' => $preview);
		}

		if (empty($sourceData)) {
			$sourceData = $this->buildSourceData('pvfree', $rawData, $preview['dataset']);
		}
		$result = $this->saveDataSource($fkProduct, $sourceData, $rawData, $normalizedData, $user);
		if ($result < 0) {
			if ($manageTransaction) { $this->db->rollback(); }
			return array('result' => -1, 'preview' => $preview);
		}

		if (!empty($preview['changes']['pmax'])) {
			$resultrecalculate = powerplantRecalculateInstalledPowerForProduct($fkProduct);
			$resultcommercialrecalculate = powerplantpvRecalculateCommercialDocumentPeakPowerForProduct($fkProduct);
			if ($resultrecalculate < 0 || $resultcommercialrecalculate < 0) {
				if ($resultrecalculate < 0) {
					$this->errors[] = 'PowerPlantInstalledPowerRecalculationError';
				}
				if ($resultcommercialrecalculate < 0) {
					$this->errors[] = 'PowerPlantPVPeakPowerRecalculationFailed';
				}
				$this->error = ($isgenericimport ? 'ProductTechnicalImportPartial' : 'PVFreeImportPartial');
				if ($manageTransaction) { $this->db->rollback(); }
				return array('result' => -1, 'preview' => $preview);
			}
		}

		if ($manageTransaction) { $this->db->commit(); }

		return array('result' => 1, 'preview' => $preview);
	}

	/**
	 * Import inverter data to product.
	 *
	 * @param int                 $fkProduct      Product id
	 * @param array<string,mixed> $normalizedData Normalized data
	 * @param array<string,mixed> $rawData        Raw data
	 * @param User                $user           Current user
	 * @param string              $strategy       Import strategy
	 * @param array<string,mixed> $sourceData     Optional source trace data
	 * @param array<string,array<string,mixed>> $dictionaryResolutions Trusted dictionary decisions
	 * @param bool $manageTransaction Let this importer own the transaction
	 * @return array<string,mixed> Result data
	 */
	public function importInverterToProduct($fkProduct, array $normalizedData, array $rawData, User $user, $strategy, array $sourceData = array(), array $dictionaryResolutions = array(), $manageTransaction = true)
	{
		$isgenericimport = !empty($sourceData) && (!isset($sourceData['source']) || $sourceData['source'] !== 'pvfree');
		$preview = $this->previewInverterImport($fkProduct, $normalizedData, $strategy, $dictionaryResolutions);
		if (!empty($preview['requires_dictionary_resolution'])) {
			$this->setError('PowerPlantPVTechnicalDictionaryResolutionRequired');
			return array('result' => -1, 'preview' => $preview);
		}
		if ($this->error) {
			return array('result' => -1, 'preview' => $preview);
		}
		$hasmpptchanges = !empty($preview['mppt_changes']);
		if (empty($preview['changes']) && !$hasmpptchanges) {
			return array('result' => 0, 'preview' => $preview, 'message' => ($isgenericimport ? 'ProductTechnicalImportNoFieldToImport' : 'PVFreeNoFieldToImport'));
		}

		if ($manageTransaction) {
			$this->db->begin();
		}

		if (!empty($preview['changes'])) {
			$result = $this->saveInverterChanges($fkProduct, $preview['changes'], $user);
			if ($result < 0) {
				if ($manageTransaction) { $this->db->rollback(); }
				return array('result' => -1, 'preview' => $preview);
			}
		}

		if ($hasmpptchanges) {
			$result = $this->saveMpptCompositionChanges($fkProduct, $normalizedData, $user, $strategy);
			if ($result < 0) {
				if ($manageTransaction) { $this->db->rollback(); }
				return array('result' => -1, 'preview' => $preview);
			}
		}
		$result = $this->applyTechnicalDictionaryPreview($fkProduct, $preview, $user);
		if ($result < 0) {
			if ($manageTransaction) { $this->db->rollback(); }
			return array('result' => -1, 'preview' => $preview);
		}

		if (empty($sourceData)) {
			$sourceData = $this->buildSourceData('pvfree', $rawData, $preview['dataset']);
		}
		$result = $this->saveDataSource($fkProduct, $sourceData, $rawData, $normalizedData, $user);
		if ($result < 0) {
			if ($manageTransaction) { $this->db->rollback(); }
			return array('result' => -1, 'preview' => $preview);
		}

		if ($manageTransaction) { $this->db->commit(); }

		$resultdata = array('result' => 1, 'preview' => $preview);
		if (empty($normalizedData['_mppt_composition'])) {
			$resultdata['warning'] = ($isgenericimport ? 'ProductTechnicalImportMPPTManualCheckRequired' : 'PVFreeMPPTManualCheckRequired');
		} elseif ($hasmpptchanges) {
			$resultdata['message'] = 'ProductTechnicalImportMPPTCompositionImported';
		}

		return $resultdata;
	}

	/**
	 * Import battery scalar data to product.
	 *
	 * @param int $fkProduct Product id
	 * @param array<string,mixed> $normalizedData Normalized data
	 * @param array<string,mixed> $rawData Raw data
	 * @param User $user Current user
	 * @param string $strategy Import strategy
	 * @param array<string,mixed> $sourceData Source trace
	 * @param array<string,array<string,mixed>> $dictionaryResolutions Trusted dictionary decisions
	 * @param bool $manageTransaction Let this importer own the transaction
	 * @return array<string,mixed> Result data
	 */
	public function importBatteryToProduct($fkProduct, array $normalizedData, array $rawData, User $user, $strategy, array $sourceData = array(), array $dictionaryResolutions = array(), $manageTransaction = true)
	{
		$preview = $this->previewBatteryImport($fkProduct, $normalizedData, $strategy, $dictionaryResolutions);
		if (!empty($preview['requires_dictionary_resolution'])) {
			$this->setError('PowerPlantPVTechnicalDictionaryResolutionRequired');
			return array('result' => -1, 'preview' => $preview);
		}
		if ($this->error) {
			return array('result' => -1, 'preview' => $preview);
		}
		if (empty($preview['changes'])) {
			return array('result' => 0, 'preview' => $preview, 'message' => 'ProductTechnicalImportNoFieldToImport');
		}
		$battery = new ProductBattery($this->db);
		$currentresult = $battery->fetchByProduct($fkProduct);
		if ($currentresult < 0) {
			$this->setError($battery->error);
			return array('result' => -1, 'preview' => $preview);
		}
		$data = array();
		foreach (ProductBattery::getBatteryFields() as $field => $spec) {
			$data[$field] = $currentresult > 0 && array_key_exists($field, $battery->data) ? $battery->data[$field] : null;
		}
		foreach (ProductBattery::getBatteryFields() as $field => $spec) {
			if (isset($preview['changes'][$field])) {
				$data[$field] = $preview['changes'][$field]['proposed'];
			}
		}

		if ($manageTransaction) {
			$this->db->begin();
		}
		$result = $battery->saveForProduct($fkProduct, $data, $user);
		if ($result < 0) {
			if ($manageTransaction) { $this->db->rollback(); }
			$this->setError($battery->error);
			return array('result' => -1, 'preview' => $preview);
		}
		$result = $this->applyTechnicalDictionaryPreview($fkProduct, $preview, $user);
		if ($result < 0) {
			if ($manageTransaction) { $this->db->rollback(); }
			return array('result' => -1, 'preview' => $preview);
		}
		$result = $this->saveDataSource($fkProduct, $sourceData, $rawData, $normalizedData, $user);
		if ($result < 0 || powerplantpvRecalculateCommercialDocumentStorageCapacityForProduct($fkProduct) < 0) {
			if ($manageTransaction) { $this->db->rollback(); }
			$this->setError('PowerPlantPVStorageCapacityRecalculationFailed');
			return array('result' => -1, 'preview' => $preview);
		}
		if ($manageTransaction) { $this->db->commit(); }
		return array('result' => 1, 'preview' => $preview);
	}

	/**
	 * Save data source trace.
	 *
	 * @param int                 $fkProduct      Product id
	 * @param array<string,mixed> $sourceData     Source data
	 * @param array<string,mixed> $rawData        Raw data
	 * @param array<string,mixed> $normalizedData Normalized data
	 * @param User                $user           Current user
	 * @return int Row id, <0 on error
	 */
	public function saveDataSource($fkProduct, array $sourceData, array $rawData, array $normalizedData, User $user)
	{
		$rawjson = null;
		$rawdataconst = isset($sourceData['raw_data_const']) ? (string) $sourceData['raw_data_const'] : 'POWERPLANTPV_PVFREE_IMPORT_RAW_JSON';
		$invalidresponseerror = isset($sourceData['invalid_response_error']) ? (string) $sourceData['invalid_response_error'] : 'PVFreeInvalidResponse';
		if (getDolGlobalInt($rawdataconst, 1)) {
			$rawjson = json_encode($rawData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			if ($rawjson === false) {
				$this->setError($invalidresponseerror);
				return -1;
			}
		}

		$normalizedjson = json_encode($normalizedData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($normalizedjson === false) {
			$this->setError($invalidresponseerror);
			return -1;
		}

		$datasource = new PowerPlantPVProductDataSource($this->db);
		$result = $datasource->upsertForProduct($fkProduct, array(
			'source' => isset($sourceData['source']) ? $sourceData['source'] : 'pvfree',
			'source_dataset' => isset($sourceData['source_dataset']) ? $sourceData['source_dataset'] : '',
			'source_key' => isset($sourceData['source_key']) ? $sourceData['source_key'] : '',
			'source_name' => isset($sourceData['source_name']) ? $sourceData['source_name'] : '',
			'source_url' => isset($sourceData['source_url']) ? $sourceData['source_url'] : '',
			'filename' => isset($sourceData['filename']) ? $sourceData['filename'] : '',
			'raw_json' => $rawjson,
			'normalized_json' => $normalizedjson,
			'import_status' => isset($sourceData['import_status']) ? $sourceData['import_status'] : 'imported',
		), $user);

		if ($result < 0) {
			$this->error = $datasource->error;
			$this->errors = array_merge($this->errors, $datasource->errors);
			return -1;
		}

		return $result;
	}

	/**
	 * Fetch PV panel row for a product.
	 *
	 * @param int $fkProduct Product id
	 * @return object|null Row
	 */
	public function fetchPvPanel($fkProduct)
	{
		$fields = array_merge(array('rowid', 'fk_product', 'entity'), self::getModuleImportFields());

		$sql = 'SELECT '.implode(', ', $fields);
		$sql .= ' FROM '.$this->db->prefix().'powerplantpv_product_pvpanel';
		$sql .= ' WHERE fk_product = '.((int) $fkProduct);
		$sql .= ' AND entity IN ('.getEntity('product').')';
		$sql .= ' ORDER BY entity DESC, rowid ASC';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return null;
		}

		return $this->db->fetch_object($resql);
	}

	/**
	 * Build import preview from current and proposed values.
	 *
	 * @param array<int,string>   $fields         Fields
	 * @param object|null         $current        Current row
	 * @param array<string,mixed> $normalizedData Normalized data
	 * @param string              $strategy       Strategy
	 * @return array<string,mixed> Preview
	 */
	protected function buildPreview(array $fields, $current, array $normalizedData, $strategy)
	{
		$strategy = $this->sanitizeStrategy($strategy);
		$changes = array();
		$ignored = array();

		foreach ($fields as $field) {
			$currentvalue = $current && property_exists($current, $field) ? $current->{$field} : null;
			$proposedvalue = array_key_exists($field, $normalizedData) ? $normalizedData[$field] : null;

			if ($proposedvalue === null || $proposedvalue === '') {
				$ignored[$field] = array(
					'current' => $currentvalue,
					'proposed' => $proposedvalue,
					'reason' => 'PVFreeNoImportedValue',
				);
				continue;
			}

			if ($strategy === self::STRATEGY_NEVER && $current && !empty($current->rowid)) {
				$ignored[$field] = array(
					'current' => $currentvalue,
					'proposed' => $proposedvalue,
					'reason' => 'PVFreeOverwriteNever',
				);
				continue;
			}

			if ($strategy === self::STRATEGY_EMPTY_ONLY && !$this->isEmptyValue($currentvalue)) {
				$ignored[$field] = array(
					'current' => $currentvalue,
					'proposed' => $proposedvalue,
					'reason' => 'PVFreeExistingValueKept',
				);
				continue;
			}

			if ($this->valuesEqual($currentvalue, $proposedvalue)) {
				$ignored[$field] = array(
					'current' => $currentvalue,
					'proposed' => $proposedvalue,
					'reason' => 'PVFreeSameValue',
				);
				continue;
			}

			$changes[$field] = array(
				'current' => $currentvalue,
				'proposed' => $proposedvalue,
			);
		}

		return array(
			'strategy' => $strategy,
			'dataset' => isset($normalizedData['_dataset']) ? $normalizedData['_dataset'] : '',
			'has_current_row' => ($current && !empty($current->rowid)),
			'changes' => $changes,
			'ignored' => $ignored,
		);
	}

	/**
	 * Build MPPT composition import preview.
	 *
	 * @param int                 $fkProduct      Product id
	 * @param array<string,mixed> $normalizedData Normalized data
	 * @param string              $strategy       Strategy
	 * @return array<string,mixed> Preview data
	 */
	protected function buildMpptCompositionPreview($fkProduct, array $normalizedData, $strategy)
	{
		$composition = $this->getNormalizedMpptComposition($normalizedData);
		$changes = array();
		$ignored = array();
		if (empty($composition)) {
			return array('mppt_changes' => $changes, 'mppt_ignored' => $ignored);
		}

		$inverter = new ProductInverter($this->db);
		$result = $inverter->fetchByProduct($fkProduct);
		if ($result < 0) {
			$this->error = $inverter->error;
			$this->errors = array_merge($this->errors, $inverter->errors);
			return array('mppt_changes' => $changes, 'mppt_ignored' => $ignored);
		}

		$current = ($result > 0) ? $this->fetchProductInverterComposition($inverter) : array('mppts' => array(), 'inputs' => array());
		if ($this->error) {
			return array('mppt_changes' => $changes, 'mppt_ignored' => $ignored);
		}

		$strategy = $this->sanitizeStrategy($strategy);
		foreach ($composition as $mpptnumber => $mpptdata) {
			$mpptnumber = (int) $mpptnumber;
			if ($mpptnumber <= 0 || !is_array($mpptdata)) {
				continue;
			}
			$currentmppt = isset($current['mppts'][$mpptnumber]) ? $current['mppts'][$mpptnumber] : null;
			$mpptdata['position'] = $mpptnumber;
			foreach (ProductInverter::getMpptFields() as $field => $spec) {
				if (!array_key_exists($field, $mpptdata)) {
					continue;
				}
				$this->appendImportedFieldPreview($changes, $ignored, 'mppt_'.$mpptnumber.'.'.$field, $currentmppt, $field, $mpptdata[$field], $strategy);
			}

			$inputs = isset($mpptdata['inputs']) && is_array($mpptdata['inputs']) ? $mpptdata['inputs'] : array();
			foreach ($inputs as $inputnumber => $inputdata) {
				$inputnumber = (int) $inputnumber;
				if ($inputnumber <= 0 || !is_array($inputdata)) {
					continue;
				}
				$currentinput = isset($current['inputs'][$mpptnumber][$inputnumber]) ? $current['inputs'][$mpptnumber][$inputnumber] : null;
				$inputdata['position'] = $inputnumber;
				foreach (ProductInverter::getPvInputFields() as $field => $spec) {
					if (!array_key_exists($field, $inputdata)) {
						continue;
					}
					$this->appendImportedFieldPreview($changes, $ignored, 'mppt_'.$mpptnumber.'.input_'.$inputnumber.'.'.$field, $currentinput, $field, $inputdata[$field], $strategy);
				}
			}
		}

		return array('mppt_changes' => $changes, 'mppt_ignored' => $ignored);
	}

	/**
	 * Append one field preview.
	 *
	 * @param array<string,array<string,mixed>> $changes Changes
	 * @param array<string,array<string,mixed>> $ignored Ignored values
	 * @param string                           $key Preview key
	 * @param object|null                      $current Current row
	 * @param string                           $field Stored field
	 * @param mixed                            $proposedvalue Proposed value
	 * @param string                           $strategy Strategy
	 * @return void
	 */
	protected function appendImportedFieldPreview(array &$changes, array &$ignored, $key, $current, $field, $proposedvalue, $strategy)
	{
		if ($proposedvalue === null || $proposedvalue === '') {
			return;
		}

		$hascurrent = ($current && !empty($current->rowid));
		$currentvalue = ($current && property_exists($current, $field)) ? $current->{$field} : null;

		if ($strategy === self::STRATEGY_NEVER && $hascurrent) {
			$ignored[$key] = array(
				'current' => $currentvalue,
				'proposed' => $proposedvalue,
				'reason' => 'PVFreeOverwriteNever',
			);
			return;
		}

		if ($strategy === self::STRATEGY_EMPTY_ONLY && !$this->isEmptyValue($currentvalue)) {
			$ignored[$key] = array(
				'current' => $currentvalue,
				'proposed' => $proposedvalue,
				'reason' => 'PVFreeExistingValueKept',
			);
			return;
		}

		if ($this->valuesEqual($currentvalue, $proposedvalue)) {
			$ignored[$key] = array(
				'current' => $currentvalue,
				'proposed' => $proposedvalue,
				'reason' => 'PVFreeSameValue',
			);
			return;
		}

		$changes[$key] = array(
			'current' => $currentvalue,
			'proposed' => $proposedvalue,
		);
	}

	/**
	 * Save PV panel changes.
	 *
	 * @param int                 $fkProduct Product id
	 * @param array<string,array<string,mixed>> $changes Changes
	 * @return int >0 on success, <0 on error
	 */
	protected function savePvPanelChanges($fkProduct, array $changes)
	{
		global $conf;

		$current = $this->fetchPvPanel($fkProduct);
		if ($this->error) {
			return -1;
		}

		if ($current && !empty($current->rowid)) {
			$sets = array();
			$allowedFields = array_flip(self::getModuleImportFields());
			foreach ($changes as $field => $change) {
				if (!isset($allowedFields[$field])) {
					continue;
				}
				$sets[] = $this->db->sanitize($field).' = '.$this->sqlFloatValue($change['proposed']);
			}
			if (empty($sets)) {
				return 0;
			}
			$sql = 'UPDATE '.$this->db->prefix().'powerplantpv_product_pvpanel';
			$sql .= ' SET '.implode(', ', $sets);
			$sql .= ' WHERE rowid = '.((int) $current->rowid);
			$sql .= ' AND entity IN ('.getEntity('product').')';
		} else {
			$cols = array('fk_product', 'entity');
			$vals = array((int) $fkProduct, (int) $conf->entity);
			$allowedFields = array_flip(self::getModuleImportFields());
			foreach ($changes as $field => $change) {
				if (!isset($allowedFields[$field])) {
					continue;
				}
				$cols[] = $this->db->sanitize($field);
				$vals[] = $this->sqlFloatValue($change['proposed']);
			}
			if (count($cols) === 2) {
				return 0;
			}
			$sql = 'INSERT INTO '.$this->db->prefix().'powerplantpv_product_pvpanel';
			$sql .= ' ('.implode(', ', $cols).') VALUES ('.implode(', ', $vals).')';
		}

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		return 1;
	}

	/**
	 * Save inverter changes.
	 *
	 * @param int                 $fkProduct Product id
	 * @param array<string,array<string,mixed>> $changes Changes
	 * @param User                $user      Current user
	 * @return int >0 on success, <0 on error
	 */
	protected function saveInverterChanges($fkProduct, array $changes, User $user)
	{
		$inverter = new ProductInverter($this->db);
		$result = $inverter->fetchByProduct($fkProduct);
		if ($result < 0) {
			$this->error = $inverter->error;
			$this->errors = array_merge($this->errors, $inverter->errors);
			return -1;
		}

		$data = array();
		foreach (ProductInverter::getInverterFields() as $field => $spec) {
			$data[$field] = ($result > 0 && array_key_exists($field, $inverter->data)) ? $inverter->data[$field] : null;
		}
		foreach ($changes as $field => $change) {
			if (!isset(ProductInverter::getInverterFields()[$field])) {
				continue;
			}
			$data[$field] = $change['proposed'];
		}

		$result = $inverter->saveForProduct($fkProduct, $data, $user);
		if ($result < 0) {
			$this->error = $inverter->error;
			$this->errors = array_merge($this->errors, $inverter->errors);
			return -1;
		}

		return 1;
	}

	/**
	 * Save imported MPPT composition changes.
	 *
	 * @param int                 $fkProduct      Product id
	 * @param array<string,mixed> $normalizedData Normalized data
	 * @param User                $user           Current user
	 * @param string              $strategy       Import strategy
	 * @return int >0 on success, 0 when no composition, <0 on error
	 */
	protected function saveMpptCompositionChanges($fkProduct, array $normalizedData, User $user, $strategy)
	{
		$composition = $this->getNormalizedMpptComposition($normalizedData);
		if (empty($composition)) {
			return 0;
		}

		$strategy = $this->sanitizeStrategy($strategy);
		$inverter = new ProductInverter($this->db);
		$inverterid = $inverter->ensureForProduct($fkProduct, $user);
		if ($inverterid < 0) {
			$this->error = $inverter->error;
			$this->errors = array_merge($this->errors, $inverter->errors);
			return -1;
		}

		$current = $this->fetchProductInverterComposition($inverter);
		if ($this->error) {
			return -1;
		}

		foreach ($composition as $mpptnumber => $mpptdata) {
			$mpptnumber = (int) $mpptnumber;
			if ($mpptnumber <= 0 || !is_array($mpptdata)) {
				continue;
			}

			$currentmppt = isset($current['mppts'][$mpptnumber]) ? $current['mppts'][$mpptnumber] : null;
			$mpptid = ($currentmppt && !empty($currentmppt->rowid)) ? (int) $currentmppt->rowid : 0;
			$data = $this->buildCurrentFieldData(ProductInverter::getMpptFields(), $currentmppt);
			$data['position'] = $mpptnumber;
			$changed = ($mpptid <= 0);
			foreach (ProductInverter::getMpptFields() as $field => $spec) {
				if ($field === 'position' || !array_key_exists($field, $mpptdata)) {
					continue;
				}
				$proposed = $mpptdata[$field];
				$currentvalue = $this->getCurrentFieldValue($currentmppt, $field);
				if ($this->shouldApplyImportedValue($currentvalue, $proposed, $strategy, $mpptid > 0)) {
					$data[$field] = $proposed;
					$changed = true;
				}
			}

			if ($changed || $mpptid <= 0) {
				$mpptid = $inverter->saveMppt($inverterid, $mpptid, $data);
				if ($mpptid < 0) {
					$this->error = $inverter->error;
					$this->errors = array_merge($this->errors, $inverter->errors);
					return -1;
				}
			}

			$inputs = isset($mpptdata['inputs']) && is_array($mpptdata['inputs']) ? $mpptdata['inputs'] : array();
			foreach ($inputs as $inputnumber => $inputdata) {
				$inputnumber = (int) $inputnumber;
				if ($inputnumber <= 0 || !is_array($inputdata)) {
					continue;
				}

				$currentinput = isset($current['inputs'][$mpptnumber][$inputnumber]) ? $current['inputs'][$mpptnumber][$inputnumber] : null;
				$inputid = ($currentinput && !empty($currentinput->rowid)) ? (int) $currentinput->rowid : 0;
				$inputsave = $this->buildCurrentFieldData(ProductInverter::getPvInputFields(), $currentinput);
				$inputsave['position'] = $inputnumber;
				$inputchanged = ($inputid <= 0);
				foreach (ProductInverter::getPvInputFields() as $field => $spec) {
					if ($field === 'position' || !array_key_exists($field, $inputdata)) {
						continue;
					}
					$proposed = $inputdata[$field];
					$currentvalue = $this->getCurrentFieldValue($currentinput, $field);
					if ($this->shouldApplyImportedValue($currentvalue, $proposed, $strategy, $inputid > 0)) {
						$inputsave[$field] = $proposed;
						$inputchanged = true;
					}
				}

				if ($inputchanged || $inputid <= 0) {
					$result = $inverter->saveInput($mpptid, $inputid, $inputsave);
					if ($result < 0) {
						$this->error = $inverter->error;
						$this->errors = array_merge($this->errors, $inverter->errors);
						return -1;
					}
				}
			}
		}

		return 1;
	}

	/**
	 * Return normalized MPPT composition from imported data.
	 *
	 * @param array<string,mixed> $normalizedData Normalized data
	 * @return array<int,array<string,mixed>> Composition
	 */
	protected function getNormalizedMpptComposition(array $normalizedData)
	{
		return isset($normalizedData['_mppt_composition']) && is_array($normalizedData['_mppt_composition']) ? $normalizedData['_mppt_composition'] : array();
	}

	/**
	 * Fetch current inverter MPPT composition indexed by positions.
	 *
	 * @param ProductInverter $inverter Inverter helper
	 * @return array<string,mixed> Current rows
	 */
	protected function fetchProductInverterComposition(ProductInverter $inverter)
	{
		$mppts = array();
		$inputs = array();
		if (empty($inverter->id)) {
			return array('mppts' => $mppts, 'inputs' => $inputs);
		}

		$mpptrows = $inverter->fetchMppts($inverter->id);
		if ($inverter->error) {
			$this->error = $inverter->error;
			$this->errors = array_merge($this->errors, $inverter->errors);
			return array('mppts' => $mppts, 'inputs' => $inputs);
		}

		$mpptids = array();
		$mpptpositionsbyid = array();
		foreach ($mpptrows as $mppt) {
			$position = (int) $mppt->position;
			if ($position <= 0) {
				continue;
			}
			$mppts[$position] = $mppt;
			$mpptids[] = (int) $mppt->rowid;
			$mpptpositionsbyid[(int) $mppt->rowid] = $position;
		}

		$inputrows = $inverter->fetchInputsByMppts($mpptids);
		if ($inverter->error) {
			$this->error = $inverter->error;
			$this->errors = array_merge($this->errors, $inverter->errors);
			return array('mppts' => $mppts, 'inputs' => $inputs);
		}

		foreach ($inputrows as $mpptid => $rows) {
			$mpptposition = isset($mpptpositionsbyid[(int) $mpptid]) ? (int) $mpptpositionsbyid[(int) $mpptid] : 0;
			if ($mpptposition <= 0 || !is_array($rows)) {
				continue;
			}
			if (empty($inputs[$mpptposition])) {
				$inputs[$mpptposition] = array();
			}
			foreach ($rows as $input) {
				$position = (int) $input->position;
				if ($position > 0) {
					$inputs[$mpptposition][$position] = $input;
				}
			}
		}

		return array('mppts' => $mppts, 'inputs' => $inputs);
	}

	/**
	 * Build current field data for an update without resetting omitted fields.
	 *
	 * @param array<string,array<string,string>> $fields  Field specs
	 * @param object|null                        $current Current row
	 * @return array<string,mixed> Data
	 */
	protected function buildCurrentFieldData(array $fields, $current)
	{
		$data = array();
		foreach ($fields as $field => $spec) {
			$data[$field] = $this->getCurrentFieldValue($current, $field);
		}

		return $data;
	}

	/**
	 * Return a current object field value.
	 *
	 * @param object|null $current Current row
	 * @param string      $field   Field name
	 * @return mixed Value
	 */
	protected function getCurrentFieldValue($current, $field)
	{
		return ($current && property_exists($current, $field)) ? $current->{$field} : null;
	}

	/**
	 * Tell whether an imported value must be applied.
	 *
	 * @param mixed  $current    Current value
	 * @param mixed  $proposed   Proposed value
	 * @param string $strategy   Import strategy
	 * @param bool   $hasCurrent Current row exists
	 * @return bool True when the value must be applied
	 */
	protected function shouldApplyImportedValue($current, $proposed, $strategy, $hasCurrent)
	{
		if ($proposed === null || $proposed === '') {
			return false;
		}
		if ($strategy === self::STRATEGY_NEVER && $hasCurrent) {
			return false;
		}
		if ($strategy === self::STRATEGY_EMPTY_ONLY && !$this->isEmptyValue($current)) {
			return false;
		}

		return !$this->valuesEqual($current, $proposed);
	}

	/**
	 * Build source data from raw PV Free object.
	 *
	 * @param string              $source  Source code
	 * @param array<string,mixed> $rawData Raw data
	 * @param string              $dataset Dataset
	 * @return array<string,mixed> Source data
	 */
	protected function buildSourceData($source, array $rawData, $dataset)
	{
		$baseurl = rtrim(getDolGlobalString('POWERPLANTPV_PVFREE_API_URL', 'https://pvfree.azurewebsites.net'), '/');
		$resourceuri = isset($rawData['resource_uri']) ? (string) $rawData['resource_uri'] : '';

		return array(
			'source' => $source,
			'source_dataset' => $dataset,
			'source_key' => isset($rawData['id']) ? (string) $rawData['id'] : $resourceuri,
			'source_name' => isset($rawData['Name']) ? (string) $rawData['Name'] : '',
			'source_url' => ($resourceuri !== '' ? $baseurl.$resourceuri : ''),
			'import_status' => 'imported',
		);
	}

	/**
	 * Sanitize strategy.
	 *
	 * @param string $strategy Strategy
	 * @return string Strategy
	 */
	protected function sanitizeStrategy($strategy)
	{
		if (in_array($strategy, array(self::STRATEGY_NEVER, self::STRATEGY_EMPTY_ONLY, self::STRATEGY_OVERWRITE_AFTER_CONFIRM), true)) {
			return $strategy;
		}

		return self::STRATEGY_EMPTY_ONLY;
	}

	/**
	 * Convert raw API value to float.
	 *
	 * @param array<string,mixed> $data Data
	 * @param string              $key  Key
	 * @return float|null Value
	 */
	protected function toFloat(array $data, $key)
	{
		if (!array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
			return null;
		}

		return is_numeric($data[$key]) ? (float) $data[$key] : null;
	}

	/**
	 * Convert raw API value to scalar string.
	 *
	 * @param array<string,mixed> $data Data
	 * @param string              $key  Key
	 * @return string|null Value
	 */
	protected function toScalarString(array $data, $key)
	{
		if (!array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
			return null;
		}

		return is_scalar($data[$key]) ? (string) $data[$key] : null;
	}

	/**
	 * Check if a stored value is empty.
	 *
	 * @param mixed $value Value
	 * @return bool True if empty
	 */
	protected function isEmptyValue($value)
	{
		return $value === null || $value === '';
	}

	/**
	 * Compare current and proposed values.
	 *
	 * @param mixed $current  Current value
	 * @param mixed $proposed Proposed value
	 * @return bool True if equal
	 */
	protected function valuesEqual($current, $proposed)
	{
		if ($this->isEmptyValue($current) && $this->isEmptyValue($proposed)) {
			return true;
		}
		if (is_numeric($current) && is_numeric($proposed)) {
			return abs(((float) $current) - ((float) $proposed)) < 0.000000001;
		}

		return (string) $current === (string) $proposed;
	}

	/**
	 * Convert a float to SQL.
	 *
	 * @param mixed $value Value
	 * @return string SQL value
	 */
	protected function sqlFloatValue($value)
	{
		if ($value === null || $value === '') {
			return 'null';
		}

		return (string) ((float) $value);
	}

	/**
	 * Reset errors.
	 *
	 * @return void
	 */
	protected function resetErrors()
	{
		$this->error = '';
		$this->errors = array();
	}

	/**
	 * Register an error.
	 *
	 * @param string $error Error
	 * @return void
	 */
	protected function setError($error)
	{
		$this->error = $error;
		$this->errors[] = $error;
	}
}
