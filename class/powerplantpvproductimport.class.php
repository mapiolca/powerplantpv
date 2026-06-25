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
dol_include_once('/powerplantpv/class/powerplantpvproductdatasource.class.php');
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
		return array_keys(ProductInverter::getInverterFields());
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
	 * @return array<string,mixed> Preview data
	 */
	public function previewModuleImport($fkProduct, array $normalizedData, $strategy)
	{
		$this->resetErrors();

		$current = $this->fetchPvPanel($fkProduct);
		$fields = (isset($normalizedData['_dataset']) && in_array($normalizedData['_dataset'], array('cecmodule', 'pvmodule'), true)) ? self::getPVFreeModuleImportFields() : self::getModuleImportFields();
		return $this->buildPreview($fields, $current, $normalizedData, $strategy);
	}

	/**
	 * Preview an inverter import.
	 *
	 * @param int                 $fkProduct      Product id
	 * @param array<string,mixed> $normalizedData Normalized data
	 * @param string              $strategy       Import strategy
	 * @return array<string,mixed> Preview data
	 */
	public function previewInverterImport($fkProduct, array $normalizedData, $strategy)
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
		return $this->buildPreview($fields, $current, $normalizedData, $strategy);
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
	 * @return array<string,mixed> Result data
	 */
	public function importModuleToProduct($fkProduct, array $normalizedData, array $rawData, User $user, $strategy, array $sourceData = array())
	{
		$isgenericimport = !empty($sourceData) && (!isset($sourceData['source']) || $sourceData['source'] !== 'pvfree');
		$preview = $this->previewModuleImport($fkProduct, $normalizedData, $strategy);
		if ($this->error) {
			return array('result' => -1, 'preview' => $preview);
		}
		if (empty($preview['changes'])) {
			return array('result' => 0, 'preview' => $preview, 'message' => ($isgenericimport ? 'ProductTechnicalImportNoFieldToImport' : 'PVFreeNoFieldToImport'));
		}

		$this->db->begin();

		$result = $this->savePvPanelChanges($fkProduct, $preview['changes']);
		if ($result < 0) {
			$this->db->rollback();
			return array('result' => -1, 'preview' => $preview);
		}

		if (empty($sourceData)) {
			$sourceData = $this->buildSourceData('pvfree', $rawData, $preview['dataset']);
		}
		$result = $this->saveDataSource($fkProduct, $sourceData, $rawData, $normalizedData, $user);
		if ($result < 0) {
			$this->db->rollback();
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
				$this->db->rollback();
				return array('result' => -1, 'preview' => $preview);
			}
		}

		$this->db->commit();

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
	 * @return array<string,mixed> Result data
	 */
	public function importInverterToProduct($fkProduct, array $normalizedData, array $rawData, User $user, $strategy, array $sourceData = array())
	{
		$isgenericimport = !empty($sourceData) && (!isset($sourceData['source']) || $sourceData['source'] !== 'pvfree');
		$preview = $this->previewInverterImport($fkProduct, $normalizedData, $strategy);
		if ($this->error) {
			return array('result' => -1, 'preview' => $preview);
		}
		if (empty($preview['changes'])) {
			return array('result' => 0, 'preview' => $preview, 'message' => ($isgenericimport ? 'ProductTechnicalImportNoFieldToImport' : 'PVFreeNoFieldToImport'));
		}

		$this->db->begin();

		$result = $this->saveInverterChanges($fkProduct, $preview['changes'], $user);
		if ($result < 0) {
			$this->db->rollback();
			return array('result' => -1, 'preview' => $preview);
		}

		if (empty($sourceData)) {
			$sourceData = $this->buildSourceData('pvfree', $rawData, $preview['dataset']);
		}
		$result = $this->saveDataSource($fkProduct, $sourceData, $rawData, $normalizedData, $user);
		if ($result < 0) {
			$this->db->rollback();
			return array('result' => -1, 'preview' => $preview);
		}

		$this->db->commit();

		return array('result' => 1, 'preview' => $preview, 'warning' => ($isgenericimport ? 'ProductTechnicalImportMPPTManualCheckRequired' : 'PVFreeMPPTManualCheckRequired'));
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
			foreach ($changes as $field => $change) {
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
			foreach ($changes as $field => $change) {
				$cols[] = $this->db->sanitize($field);
				$vals[] = $this->sqlFloatValue($change['proposed']);
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

		return (string) price2num($value, 'MT');
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
