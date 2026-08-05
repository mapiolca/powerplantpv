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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file       class/powerplantpvfileimport.class.php
 * \ingroup    powerplantpv
 * \brief      CSV/XLSX reader and normalizer for product technical imports.
 */

dol_include_once('/powerplantpv/class/powerplantpvproductimport.class.php');
dol_include_once('/powerplantpv/class/powerplantpvtechnicalvalue.class.php');

/**
 * Read and normalize CSV/XLSX product technical characteristics.
 */
class PowerPlantPVFileImport
{
	/**
	 * @var string Last error
	 */
	protected $error = '';

	/**
	 * @var array<int,string> Error keys
	 */
	protected $errors = array();

	/**
	 * Validate an uploaded CSV/XLSX file.
	 *
	 * @param array<string,mixed> $file Uploaded file entry
	 * @return array<string,mixed>|false File metadata, false on error
	 */
	public function validateUploadedFile(array $file)
	{
		$this->resetErrors();

		if (empty($file) || empty($file['name'])) {
			$this->setError('ProductTechnicalImportFileMissing');
			return false;
		}
		if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
			$this->setError('ProductTechnicalImportUploadError');
			return false;
		}
		if (empty($file['tmp_name']) || !is_readable((string) $file['tmp_name'])) {
			$this->setError('ProductTechnicalImportFileUnreadable');
			return false;
		}

		$filename = function_exists('dol_sanitizeFileName') ? dol_sanitizeFileName((string) $file['name']) : basename((string) $file['name']);
		$extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
		if (!in_array($extension, array('csv', 'xlsx'), true)) {
			$this->setError('ProductTechnicalImportUnsupportedFileExtension');
			return false;
		}
		if ($extension === 'csv' && !getDolGlobalInt('POWERPLANTPV_COMPONENT_IMPORT_CSV_ENABLED', 1)) {
			$this->setError('ProductTechnicalImportCsvDisabled');
			return false;
		}
		if ($extension === 'xlsx' && !getDolGlobalInt('POWERPLANTPV_COMPONENT_IMPORT_XLSX_ENABLED', 1)) {
			$this->setError('ProductTechnicalImportXlsxDisabled');
			return false;
		}

		$size = isset($file['size']) ? (int) $file['size'] : (int) @filesize((string) $file['tmp_name']);
		$maxfilesizemb = (int) getDolGlobalInt('POWERPLANTPV_IMPORT_MAX_FILE_SIZE', 5);
		if ($maxfilesizemb <= 0) {
			$maxfilesizemb = 5;
		}
		if ($size > ($maxfilesizemb * 1024 * 1024)) {
			$this->setError('ProductTechnicalImportFileTooLarge');
			return false;
		}

		if (function_exists('mime_content_type')) {
			$mime = (string) @mime_content_type((string) $file['tmp_name']);
			if (preg_match('/php|script|executable/i', $mime)) {
				$this->setError('ProductTechnicalImportInvalidMimeType');
				return false;
			}
		}

		return array(
			'filename' => $filename,
			'extension' => $extension,
			'size' => $size,
		);
	}

	/**
	 * Read a CSV file as a raw matrix.
	 *
	 * @param string $filepath  File path
	 * @param string $separator Preferred separator
	 * @return array<int,array<int,string>> Rows
	 */
	public function readCsv($filepath, $separator = ';')
	{
		$this->resetErrors();

		$handle = @fopen($filepath, 'rb');
		if (!$handle) {
			$this->setError('ProductTechnicalImportFileUnreadable');
			return array();
		}

		$firstline = fgets($handle);
		if ($firstline === false) {
			fclose($handle);
			$this->setError('ProductTechnicalImportNoUsableLine');
			return array();
		}

		$delimiter = $this->detectCsvDelimiter($firstline, $separator);
		rewind($handle);

		$rows = array();
		while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
			$cells = array();
			foreach ($data as $cell) {
				$cells[] = $this->cleanCell((string) $cell);
			}
			if (!empty($rows) || !$this->isEmptyRow($cells)) {
				$rows[] = $cells;
			}
		}
		fclose($handle);

		if (empty($rows)) {
			$this->setError('ProductTechnicalImportNoUsableLine');
		} elseif (isset($rows[0][0])) {
			$rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rows[0][0]);
		}

		return $rows;
	}

	/**
	 * Read an XLSX file as a raw matrix.
	 *
	 * @param string $filepath File path
	 * @return array<int,array<int,string>> Rows
	 */
	public function readXlsx($filepath)
	{
		$this->resetErrors();

		$native = $this->readXlsxNative($filepath);
		if (is_array($native)) {
			return $native;
		}

		if (!$this->loadPhpSpreadsheet()) {
			$this->setError('ProductTechnicalImportXlsxReaderUnavailable');
			return array();
		}

		try {
			$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filepath);
			if (method_exists($reader, 'setReadDataOnly')) {
				$reader->setReadDataOnly(true);
			}
			$spreadsheet = $reader->load($filepath);
			$worksheet = $spreadsheet->getActiveSheet();
			$array = $worksheet->toArray('', false, false, false);
		} catch (Throwable $e) {
			if (function_exists('dol_syslog')) {
				dol_syslog(__METHOD__.' failed to read '.$filepath.': '.$e->getMessage(), (defined('LOG_WARNING') ? LOG_WARNING : 4));
			}
			$this->setError('ProductTechnicalImportFileUnreadable');
			return array();
		}

		$rows = array();
		foreach ($array as $cells) {
			$row = array();
			foreach ((array) $cells as $cell) {
				$row[] = $this->cleanCell((string) $cell);
			}
			if (!$this->isEmptyRow($row)) {
				$rows[] = $row;
			}
		}

		if (empty($rows)) {
			$this->setError('ProductTechnicalImportNoUsableLine');
		}

		return $rows;
	}

	/**
	 * Detect the header row index.
	 *
	 * @param array<int,array<int,string>> $rows Rows
	 * @return int Header row index, -1 if not found
	 */
	public function detectHeaderRow(array $rows)
	{
		$aliases = $this->getCombinedAliases();
		$bestindex = -1;
		$bestscore = 0;
		$limit = min(10, count($rows));

		for ($i = 0; $i < $limit; $i++) {
			$score = 0;
			foreach ($this->normalizeHeaders((array) $rows[$i]) as $header) {
				if ($header !== '' && (isset($aliases[$header]) || !empty($this->parseMpptCompositionHeader($header)) || !empty($this->parseBatteryAttributeHeader($header)))) {
					$score++;
				}
			}
			if ($score > $bestscore) {
				$bestscore = $score;
				$bestindex = $i;
			}
		}

		return ($bestscore > 0 ? $bestindex : -1);
	}

	/**
	 * Normalize header labels.
	 *
	 * @param array<int,string> $headers Headers
	 * @return array<int,string> Normalized headers
	 */
	public function normalizeHeaders(array $headers)
	{
		$normalized = array();
		foreach ($headers as $header) {
			$normalized[] = $this->normalizeHeader((string) $header);
		}

		return $normalized;
	}

	/**
	 * Parse the optional self-documenting contract appended to a header.
	 *
	 * @param string $header Raw header
	 * @return array<string,mixed>
	 */
	public function parseHeaderMetadata($header)
	{
		$result = array('present' => false, 'legacy' => false, 'legacy_value' => '', 'values' => array());
		if (!preg_match('/\[([^\]]+)\]\s*$/u', trim((string) $header), $matches)) {
			return $result;
		}
		$content = trim((string) $matches[1]);
		$result['present'] = true;
		if (strpos($content, '=') === false) {
			$result['legacy'] = true;
			$result['legacy_value'] = $content;
			return $result;
		}
		foreach (explode(';', $content) as $part) {
			$pair = explode('=', trim($part), 2);
			if (count($pair) === 2 && trim($pair[0]) !== '') {
				$result['values'][strtolower(trim($pair[0]))] = trim($pair[1]);
			}
		}
		return $result;
	}

	/**
	 * Tell whether a CSV row documents the template instead of carrying data.
	 *
	 * @param array<int,mixed> $row Raw row
	 * @return bool
	 */
	public function isTemplateMetadataRow(array $row)
	{
		$first = isset($row[0]) ? trim((string) $row[0]) : '';
		return strpos($first, '#POWERPLANTPV_FIELD') === 0 || strpos($first, '#POWERPLANTPV_VALUE') === 0;
	}

	/**
	 * Return metadata for one MPPT composition column.
	 *
	 * @param string $header Normalized header
	 * @return array<string,string>
	 */
	public function getMpptCompositionFieldDefinition($header)
	{
		$unit = $this->getMpptCompositionFieldUnit($header);
		return array(
			'type' => $unit === 'text' ? 'text' : 'decimal',
			'unit' => $unit === 'text' ? '' : $unit,
			'cardinality' => '0..1',
			'format' => $unit === 'text' ? 'TEXT' : 'SIGNED_DECIMAL',
		);
	}

	/**
	 * Extract non-empty data rows after the detected header.
	 *
	 * @param array<int,array<int,string>> $rows Rows
	 * @return array<int,array<int,string>> Data rows
	 */
	public function extractRows(array $rows)
	{
		$headerrow = $this->detectHeaderRow($rows);
		if ($headerrow < 0) {
			$this->setError('ProductTechnicalImportNoRecognizedColumn');
			return array();
		}

		$extracted = array();
		for ($i = $headerrow + 1; $i < count($rows); $i++) {
			if (!$this->isEmptyRow((array) $rows[$i]) && !$this->isTemplateMetadataRow((array) $rows[$i])) {
				$extracted[] = (array) $rows[$i];
			}
		}

		if (empty($extracted)) {
			$this->setError('ProductTechnicalImportNoUsableLine');
		}

		return $extracted;
	}

	/**
	 * Build normalized import row descriptors.
	 *
	 * @param array<int,array<int,string>> $rows Raw rows
	 * @param string                       $type module|inverter|battery
	 * @return array<string,mixed> Parsed import data
	 */
	public function buildImportRows(array $rows, $type)
	{
		$this->resetErrors();

		$headerrow = $this->detectHeaderRow($rows);
		if ($headerrow < 0) {
			$this->setError('ProductTechnicalImportNoRecognizedColumn');
			return array();
		}

		$headers = (array) $rows[$headerrow];
		$normalizedheaders = $this->normalizeHeaders($headers);
		$fieldmap = $this->buildFieldMap($normalizedheaders, $type);
		if (empty($fieldmap['fields']) && empty($fieldmap['composition_fields']) && empty($fieldmap['attribute_fields'])) {
			$this->setError('ProductTechnicalImportNoRecognizedColumn');
			return array();
		}
		$unitwarnings = array();
		foreach ($fieldmap['fields'] as $field => $columnindex) {
			$rawheader = isset($headers[$columnindex]) ? trim((string) $headers[$columnindex]) : '';
			$definition = PowerPlantPVProductImport::getImportFieldDefinition($type, $field);
			if (!$this->validateHeaderContract($rawheader, $definition, $field, $unitwarnings)) {
				return array();
			}
		}
		foreach ($fieldmap['attribute_fields'] as $header => $columnindex) {
			$rawheader = isset($headers[$columnindex]) ? trim((string) $headers[$columnindex]) : '';
			$descriptor = $this->parseBatteryAttributeHeader($header);
			$definition = array('type' => 'multiselect2', 'unit' => '', 'cardinality' => '0..N', 'format' => 'CODE|Libellé', 'source' => isset($descriptor['dictionary_type']) ? $descriptor['dictionary_type'] : '');
			if (!$this->validateHeaderContract($rawheader, $definition, $header, $unitwarnings)) {
				return array();
			}
		}
		foreach ($fieldmap['composition_fields'] as $header => $columnindex) {
			$rawheader = isset($headers[$columnindex]) ? trim((string) $headers[$columnindex]) : '';
			$definition = $this->getMpptCompositionFieldDefinition($header);
			if (!$this->validateHeaderContract($rawheader, $definition, $header, $unitwarnings)) {
				return array();
			}
		}
		$fieldmap['unit_warnings'] = $unitwarnings;

		$importrows = array();
		for ($i = $headerrow + 1; $i < count($rows); $i++) {
			$cells = (array) $rows[$i];
			if ($this->isEmptyRow($cells) || $this->isTemplateMetadataRow($cells)) {
				continue;
			}
			if (!$this->validateNumericImportRow($headers, $cells, $fieldmap, $type)) {
				$this->setError('ProductTechnicalImportNumericValueRequired');
				return array();
			}
			$raw = $this->rowToAssoc($headers, $cells);
			if ($type === 'inverter') {
				$normalized = $this->normalizeInverterRow($raw);
			} elseif ($type === 'battery') {
				$normalized = $this->normalizeBatteryRow($raw);
			} else {
				$normalized = $this->normalizeModuleRow($raw);
			}
			if ($this->getLastError() !== '') {
				return array();
			}
			$recognizedcount = $this->countRecognizedValues($normalized);

			$importrows[] = array(
				'index' => count($importrows),
				'line' => $i + 1,
				'manufacturer' => $this->firstRawValue($raw, array('manufacturer', 'fabricant', 'maker', 'brand', 'marque')),
				'model' => $this->firstRawValue($raw, array('model', 'modele', 'modèle', 'name', 'nom', 'ref', 'reference', 'référence')),
				'power' => $this->firstNormalizedValue($normalized, array('pmax', 'pv_max_power', 'ac_nominal_power', 'ac_max_power', 'usable_energy')),
				'recognized_count' => $recognizedcount,
				'raw' => $raw,
				'normalized' => $normalized,
			);
		}

		if (empty($importrows)) {
			$this->setError('ProductTechnicalImportNoUsableLine');
			return array();
		}

		return array(
			'header_row' => $headerrow + 1,
			'headers' => $headers,
			'normalized_headers' => $normalizedheaders,
			'field_map' => $fieldmap,
			'rows' => $importrows,
		);
	}

	/**
	 * Normalize an import unit for strict header comparison.
	 *
	 * @param string $unit Unit
	 * @return string Normalized unit
	 */
	protected function normalizeUnit($unit)
	{
		$unit = str_replace(array('％', '²', '°', ' '), array('%', '2', '', ''), trim((string) $unit));
		return strtolower($unit);
	}

	/**
	 * Public contract validator used by the mixed product importer.
	 *
	 * @param string $rawheader Raw header
	 * @param array<string,mixed> $definition Expected definition
	 * @param string $field Canonical field
	 * @param array<int,string> $warnings Missing-contract warnings
	 * @return bool
	 */
	public function validateDocumentedHeader($rawheader, array $definition, $field, array &$warnings)
	{
		return $this->validateHeaderContract($rawheader, $definition, $field, $warnings);
	}

	/**
	 * Validate a legacy or self-documenting header contract.
	 *
	 * @param string $rawheader Raw header
	 * @param array<string,mixed> $definition Expected definition
	 * @param string $field Canonical field
	 * @param array<int,string> $warnings Missing-contract warnings
	 * @return bool
	 */
	protected function validateHeaderContract($rawheader, array $definition, $field, array &$warnings)
	{
		$metadata = $this->parseHeaderMetadata($rawheader);
		if (empty($metadata['present'])) {
			$warnings[] = $field;
			return true;
		}

		$expectedType = strtolower((string) (isset($definition['type']) ? $definition['type'] : ''));
		$expectedUnit = (string) (isset($definition['unit']) ? $definition['unit'] : '');
		$expectedFormat = (string) (isset($definition['format']) ? $definition['format'] : '');
		if (!empty($metadata['legacy'])) {
			$legacy = $this->normalizeContractValue((string) $metadata['legacy_value']);
			$normalizedExpectedFormat = $this->normalizeContractValue($expectedFormat);
			if ($expectedType === 'multiselect2' && ($legacy === 'code' || $legacy === $normalizedExpectedFormat || in_array($legacy, array('codelabel', 'codelibelle'), true))) {
				return true;
			}
			$expectedLegacy = $expectedUnit !== '' ? $expectedUnit : $expectedFormat;
			if ($expectedLegacy === '' || $this->normalizeContractValue($expectedLegacy) === $legacy) {
				return true;
			}
			$this->logHeaderContractError($field, (string) $metadata['legacy_value'], $expectedLegacy);
			$this->setError('ProductTechnicalImportUnexpectedUnit');
			return false;
		}

		$values = isset($metadata['values']) && is_array($metadata['values']) ? $metadata['values'] : array();
		$declaredType = isset($values['type']) ? strtolower(trim((string) $values['type'])) : '';
		if ($declaredType === '' || $declaredType !== $expectedType) {
			$this->logHeaderContractError($field, $declaredType, $expectedType);
			$this->setError('ProductTechnicalImportUnexpectedType');
			return false;
		}

		$declaredUnit = isset($values['unit']) ? (string) $values['unit'] : '';
		if (($expectedUnit !== '' && $this->normalizeUnit($declaredUnit) !== $this->normalizeUnit($expectedUnit)) || ($expectedUnit === '' && $declaredUnit !== '')) {
			$this->logHeaderContractError($field, $declaredUnit, $expectedUnit);
			$this->setError('ProductTechnicalImportUnexpectedUnit');
			return false;
		}

		if (isset($values['format']) && $expectedFormat !== '' && $this->normalizeContractValue((string) $values['format']) !== $this->normalizeContractValue($expectedFormat)) {
			$this->logHeaderContractError($field, (string) $values['format'], $expectedFormat);
			$this->setError('ProductTechnicalImportUnexpectedFormat');
			return false;
		}
		return true;
	}

	/** @return string */
	protected function normalizeContractValue($value)
	{
		$value = strtolower(trim((string) $value));
		if (function_exists('iconv')) {
			$converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
			$value = $converted === false ? $value : $converted;
		}
		return (string) preg_replace('/[^a-z0-9]+/', '', $value);
	}

	/** @return void */
	protected function logHeaderContractError($field, $declared, $expected)
	{
		if (function_exists('dol_syslog')) {
			dol_syslog(__METHOD__.' unexpected contract for '.$field.': '.$declared.' expected '.$expected, defined('LOG_WARNING') ? LOG_WARNING : 4);
		}
	}

	/**
	 * Normalize a module row.
	 *
	 * @param array<string,mixed> $row Raw row indexed by file headers
	 * @return array<string,mixed> Normalized data
	 */
	public function normalizeModuleRow(array $row)
	{
		$normalized = $this->normalizeRowWithAliases($row, $this->getModuleAliases(), $this->getModuleFieldTypes(), 'module');
		$this->expandLegacyRange($normalized, '_legacy_power_tolerance', 'power_tolerance_min', 'power_tolerance_max');
		$this->expandLegacyRange($normalized, '_legacy_operating_temperature', 'operating_temperature_min', 'operating_temperature_max');
		$this->appendTechnicalDictionaryCodes($normalized, $row);
		return $normalized;
	}

	/**
	 * Normalize an inverter row.
	 *
	 * @param array<string,mixed> $row Raw row indexed by file headers
	 * @return array<string,mixed> Normalized data
	 */
	public function normalizeInverterRow(array $row)
	{
		$normalized = $this->normalizeRowWithAliases($row, $this->getInverterAliases(), $this->getInverterFieldTypes(), 'inverter');
		foreach (array(
			array('_legacy_ac_voltage', 'ac_voltage_min', 'ac_voltage_max', 'ac_voltage_nominal'),
			array('_legacy_grid_frequency', 'grid_frequency_min', 'grid_frequency_max', 'grid_frequency_nominal'),
			array('_legacy_backup_voltage', 'backup_voltage_min', 'backup_voltage_max', 'backup_voltage_nominal'),
			array('_legacy_operating_temperature', 'operating_temperature_min', 'operating_temperature_max', null),
			array('_legacy_relative_humidity', 'relative_humidity_min', 'relative_humidity_max', null),
		) as $range) {
			$this->expandLegacyRange($normalized, $range[0], $range[1], $range[2], $range[3]);
		}
		foreach (array(
			array('_legacy_thd', 'thd_comparator', 'thd_value'),
			array('_legacy_backup_thd', 'backup_thd_comparator', 'backup_thd_value'),
			array('_legacy_noise', 'noise_comparator', 'noise_value'),
		) as $threshold) {
			$this->expandLegacyThreshold($normalized, $threshold[0], $threshold[1], $threshold[2]);
		}
		$this->normalizeComparatorFields($normalized, array('thd_comparator', 'backup_thd_comparator', 'noise_comparator'));
		if (isset($normalized['_legacy_power_factor']) && $normalized['_legacy_power_factor'] !== '') {
			$raw = (string) $normalized['_legacy_power_factor'];
			$parsed = PowerPlantPVTechnicalValue::parsePowerFactor($raw);
			if ($parsed !== null) {
				$normalized['power_factor_inductive'] = $parsed['inductive'];
				$normalized['power_factor_nominal'] = $parsed['nominal'];
				$normalized['power_factor_capacitive'] = $parsed['capacitive'];
			} elseif ($this->containsTechnicalUnit($raw)) {
				$this->setError('ProductTechnicalImportNumericValueRequired');
			} else {
				$normalized['_legacy_warnings']['power_factor'] = $raw;
			}
			unset($normalized['_legacy_power_factor']);
		}
		$this->appendTechnicalDictionaryCodes($normalized, $row);
		$composition = $this->normalizeMpptCompositionRow($row);
		if (!empty($composition)) {
			$normalized['_mppt_composition'] = $composition;
		}

		return $normalized;
	}

	/**
	 * Expand one deprecated compact range field.
	 *
	 * @param array<string,mixed> $normalized Normalized row
	 * @param string $legacy Legacy key
	 * @param string $min Minimum key
	 * @param string $max Maximum key
	 * @param string|null $nominal Optional nominal key
	 * @return void
	 */
	protected function expandLegacyRange(array &$normalized, $legacy, $min, $max, $nominal = null)
	{
		if (!isset($normalized[$legacy]) || $normalized[$legacy] === '') {
			return;
		}
		$raw = (string) $normalized[$legacy];
		$parsed = PowerPlantPVTechnicalValue::parseRange($raw);
		if ($parsed !== null) {
			$normalized[$min] = $parsed['min'];
			$normalized[$max] = $parsed['max'];
		} elseif ($nominal !== null && is_numeric(str_replace(',', '.', $raw))) {
			$normalized[$nominal] = (float) str_replace(',', '.', $raw);
		} elseif ($this->containsTechnicalUnit($raw)) {
			$this->setError('ProductTechnicalImportNumericValueRequired');
		} else {
			$normalized['_legacy_warnings'][$legacy] = $raw;
		}
		unset($normalized[$legacy]);
	}

	/** @param array<string,mixed> $normalized Row @return void */
	protected function expandLegacyThreshold(array &$normalized, $legacy, $comparator, $value)
	{
		if (!isset($normalized[$legacy]) || $normalized[$legacy] === '') {
			return;
		}
		$raw = (string) $normalized[$legacy];
		$parsed = PowerPlantPVTechnicalValue::parseThreshold($raw);
		if ($parsed !== null) {
			$normalized[$comparator] = $parsed['comparator'];
			$normalized[$value] = $parsed['value'];
		} elseif ($this->containsTechnicalUnit($raw)) {
			$this->setError('ProductTechnicalImportNumericValueRequired');
		} else {
			$normalized['_legacy_warnings'][$legacy] = $raw;
		}
		unset($normalized[$legacy]);
	}

	/**
	 * Normalize a battery row.
	 *
	 * @param array<string,mixed> $row Raw row
	 * @return array<string,mixed> Normalized data
	 */
	public function normalizeBatteryRow(array $row)
	{
		$normalized = $this->normalizeRowWithAliases($row, $this->getBatteryAliases(), $this->getBatteryFieldTypes(), 'battery');
		$this->normalizeComparatorFields($normalized, array('noise_comparator'));
		$this->appendTechnicalDictionaryCodes($normalized, $row);
		$attributes = array();
		foreach ($row as $rawheader => $rawvalue) {
			$descriptor = $this->parseBatteryAttributeHeader($this->normalizeHeader((string) $rawheader));
			$value = trim((string) $rawvalue);
			if (empty($descriptor) || $value === '') {
				continue;
			}
			$parts = explode('|', $value, 2);
			$type = (string) $descriptor['type'];
			if (!isset($attributes[$type])) {
				$attributes[$type] = array();
			}
			$attributes[$type][] = array(
				'code' => strtoupper(trim($parts[0])),
				'label' => isset($parts[1]) ? trim($parts[1]) : '',
			);
		}
		if (!empty($attributes)) {
			$normalized['_battery_attributes'] = $attributes;
		}
		return $normalized;
	}

	/**
	 * Normalize explicit comparator columns and reject unknown codes.
	 *
	 * @param array<string,mixed> $normalized Row
	 * @param array<int,string> $fields Comparator fields
	 * @return void
	 */
	protected function normalizeComparatorFields(array &$normalized, array $fields)
	{
		foreach ($fields as $field) {
			if (!isset($normalized[$field]) || trim((string) $normalized[$field]) === '') {
				continue;
			}
			$comparator = PowerPlantPVTechnicalValue::normalizeComparator($normalized[$field]);
			if ($comparator === '') {
				$this->setError('TechnicalValueInvalidComparator');
				return;
			}
			$normalized[$field] = $comparator;
		}
	}

	/**
	 * Detect a unit embedded in a deprecated compact value.
	 *
	 * @param string $value Raw compact value
	 * @return bool True when a known unit token is present
	 */
	protected function containsTechnicalUnit($value)
	{
		return preg_match('/(?:%|°[CF]?|dB(?:\(A\))?|k?W(?:h)?|VA|V|Hz|A|ratio)\s*$/iu', trim((string) $value)) === 1;
	}

	/**
	 * Return last error.
	 *
	 * @return string Error key
	 */
	public function getLastError()
	{
		return $this->error;
	}

	/**
	 * Return all errors.
	 *
	 * @return array<int,string> Error keys
	 */
	public function getLastErrors()
	{
		return $this->errors;
	}

	/**
	 * Detect CSV delimiter.
	 *
	 * @param string $line      First line
	 * @param string $preferred Preferred separator
	 * @return string Delimiter
	 */
	protected function detectCsvDelimiter($line, $preferred)
	{
		$allowed = array(';' => ';', ',' => ',', "\t" => "\t");
		$preferred = isset($allowed[$preferred]) ? $preferred : ';';
		$counts = array(';' => substr_count($line, ';'), ',' => substr_count($line, ','), "\t" => substr_count($line, "\t"));
		if (!empty($counts[$preferred])) {
			return $preferred;
		}
		arsort($counts);
		foreach ($counts as $delimiter => $count) {
			if ($count > 0) {
				return $delimiter;
			}
		}

		return $preferred;
	}

	/**
	 * Load Dolibarr bundled PhpSpreadsheet when available.
	 *
	 * @return bool True if loaded
	 */
	protected function loadPhpSpreadsheet()
	{
		if (class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
			return true;
		}

		$candidates = array(
			DOL_DOCUMENT_ROOT.'/includes/phpoffice/phpspreadsheet/src/autoloader.php',
			DOL_DOCUMENT_ROOT.'/includes/phpoffice/phpspreadsheet/src/Bootstrap.php',
			DOL_DOCUMENT_ROOT.'/vendor/autoload.php',
		);

		foreach ($candidates as $candidate) {
			if (is_readable($candidate)) {
				try {
					require_once $candidate;
				} catch (Throwable $e) {
					if (function_exists('dol_syslog')) {
						dol_syslog(__METHOD__.' failed to load '.$candidate.': '.$e->getMessage(), (defined('LOG_WARNING') ? LOG_WARNING : 4));
					}
					continue;
				}
				if (class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
					return true;
				}
			}
		}

		return class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory');
	}

	/**
	 * Read XLSX without Composer dependency.
	 *
	 * @param string $filepath File path
	 * @return array<int,array<int,string>>|false Rows or false
	 */
	protected function readXlsxNative($filepath)
	{
		if (!class_exists('ZipArchive') || !function_exists('simplexml_load_string')) {
			return false;
		}

		$zip = new ZipArchive();
		if ($zip->open($filepath) !== true) {
			return false;
		}

		$sheetpath = $this->xlsxGetFirstSheetPath($zip);
		if ($sheetpath === '') {
			$zip->close();
			return false;
		}

		$sheetxml = $zip->getFromName($sheetpath);
		if ($sheetxml === false) {
			$zip->close();
			return false;
		}
		$sharedstrings = $this->xlsxReadSharedStrings($zip);
		$zip->close();

		$rows = $this->xlsxSheetToMatrix($sheetxml, $sharedstrings);
		if ($rows === false || empty($rows)) {
			return false;
		}

		return $rows;
	}

	/**
	 * Return first sheet path in XLSX archive.
	 *
	 * @param ZipArchive $zip XLSX archive
	 * @return string Path
	 */
	protected function xlsxGetFirstSheetPath($zip)
	{
		$fallback = 'xl/worksheets/sheet1.xml';
		$workbookxml = $zip->getFromName('xl/workbook.xml');
		$relsxml = $zip->getFromName('xl/_rels/workbook.xml.rels');
		if ($workbookxml === false || $relsxml === false) {
			return ($zip->locateName($fallback) !== false ? $fallback : '');
		}

		$mainns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
		$relsns = 'http://schemas.openxmlformats.org/package/2006/relationships';
		$officerelsns = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
		$workbook = $this->xlsxLoadXml($workbookxml);
		$rels = $this->xlsxLoadXml($relsxml);
		if ($workbook === false || $rels === false) {
			return ($zip->locateName($fallback) !== false ? $fallback : '');
		}

		$targets = array();
		foreach ($rels->children($relsns)->Relationship as $relationship) {
			$attrs = $relationship->attributes();
			$id = (string) $attrs['Id'];
			if ($id !== '') {
				$targets[$id] = (string) $attrs['Target'];
			}
		}

		$sheetpaths = array();
		$sheets = $workbook->children($mainns)->sheets;
		foreach ($sheets->children($mainns)->sheet as $sheet) {
			$attrs = $sheet->attributes($officerelsns);
			$rid = (string) $attrs['id'];
			if ($rid !== '' && !empty($targets[$rid])) {
				$path = $this->xlsxNormalizeTargetPath('xl/workbook.xml', $targets[$rid]);
				if ($path !== '' && $zip->locateName($path) !== false) {
					$sheetpaths[] = $path;
				}
			}
		}
		if (!empty($sheetpaths[0])) {
			return $sheetpaths[0];
		}

		return ($zip->locateName($fallback) !== false ? $fallback : '');
	}

	/**
	 * Read shared strings.
	 *
	 * @param ZipArchive $zip XLSX archive
	 * @return array<int,string> Shared strings
	 */
	protected function xlsxReadSharedStrings($zip)
	{
		$strings = array();
		$xml = $zip->getFromName('xl/sharedStrings.xml');
		if ($xml === false) {
			return $strings;
		}

		$mainns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
		$sst = $this->xlsxLoadXml($xml);
		if ($sst === false) {
			return $strings;
		}

		foreach ($sst->children($mainns)->si as $si) {
			$strings[] = $this->xlsxReadStringNode($si);
		}

		return $strings;
	}

	/**
	 * Convert worksheet XML to rows.
	 *
	 * @param string            $sheetxml      Sheet XML
	 * @param array<int,string> $sharedstrings Shared strings
	 * @return array<int,array<int,string>>|false Rows or false
	 */
	protected function xlsxSheetToMatrix($sheetxml, array $sharedstrings)
	{
		$mainns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
		$sheet = $this->xlsxLoadXml($sheetxml);
		if ($sheet === false) {
			return false;
		}

		$rows = array();
		$sheetdata = $sheet->children($mainns)->sheetData;
		foreach ($sheetdata->children($mainns)->row as $rownode) {
			$cells = array();
			$nextcol = 0;
			foreach ($rownode->children($mainns)->c as $cellnode) {
				$cellattrs = $cellnode->attributes();
				$cellref = (string) $cellattrs['r'];
				$colindex = ($cellref !== '' ? $this->xlsxColumnIndex($cellref) : $nextcol);
				$cells[$colindex] = $this->cleanCell($this->xlsxCellValue($cellnode, $sharedstrings));
				$nextcol = $colindex + 1;
			}
			if (empty($cells)) {
				continue;
			}

			ksort($cells);
			$maxcol = (int) max(array_keys($cells));
			$dense = array();
			for ($i = 0; $i <= $maxcol; $i++) {
				$dense[] = isset($cells[$i]) ? $cells[$i] : '';
			}
			if (!$this->isEmptyRow($dense)) {
				$rows[] = $dense;
			}
		}

		return $rows;
	}

	/**
	 * Load XML safely.
	 *
	 * @param string $xml XML
	 * @return SimpleXMLElement|false XML object
	 */
	protected function xlsxLoadXml($xml)
	{
		$previous = libxml_use_internal_errors(true);
		$object = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		return $object;
	}

	/**
	 * Read XLSX string node.
	 *
	 * @param SimpleXMLElement $node String node
	 * @return string Text
	 */
	protected function xlsxReadStringNode($node)
	{
		$mainns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
		$children = $node->children($mainns);
		$text = '';
		if (isset($children->t)) {
			$text .= (string) $children->t;
		}
		foreach ($children->r as $run) {
			$rchildren = $run->children($mainns);
			if (isset($rchildren->t)) {
				$text .= (string) $rchildren->t;
			}
		}

		return $text;
	}

	/**
	 * Read XLSX cell value without formula calculation.
	 *
	 * @param SimpleXMLElement  $cellnode      Cell node
	 * @param array<int,string> $sharedstrings Shared strings
	 * @return string Cell value
	 */
	protected function xlsxCellValue($cellnode, array $sharedstrings)
	{
		$mainns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
		$attrs = $cellnode->attributes();
		$type = (string) $attrs['t'];
		$children = $cellnode->children($mainns);

		if ($type === 'inlineStr') {
			return isset($children->is) ? $this->xlsxReadStringNode($children->is) : '';
		}

		$value = isset($children->v) ? (string) $children->v : '';
		if ($type === 's') {
			$index = (int) $value;
			return isset($sharedstrings[$index]) ? $sharedstrings[$index] : '';
		}
		if ($type === 'b') {
			return ($value === '1' ? '1' : '0');
		}

		return $value;
	}

	/**
	 * Convert XLSX cell reference to column index.
	 *
	 * @param string $cellref Cell reference
	 * @return int Index
	 */
	protected function xlsxColumnIndex($cellref)
	{
		$letters = preg_replace('/[^A-Z]/', '', strtoupper($cellref));
		if ($letters === '') {
			return 0;
		}

		$index = 0;
		$length = strlen($letters);
		for ($i = 0; $i < $length; $i++) {
			$index = ($index * 26) + (ord($letters[$i]) - 64);
		}

		return max(0, $index - 1);
	}

	/**
	 * Normalize relationship target path.
	 *
	 * @param string $basepath Base path
	 * @param string $target   Target
	 * @return string Archive path
	 */
	protected function xlsxNormalizeTargetPath($basepath, $target)
	{
		$target = str_replace('\\', '/', (string) $target);
		if ($target === '') {
			return '';
		}
		if ($target[0] === '/') {
			return ltrim($target, '/');
		}

		$parts = explode('/', $basepath);
		array_pop($parts);
		foreach (explode('/', $target) as $part) {
			if ($part === '' || $part === '.') {
				continue;
			}
			if ($part === '..') {
				array_pop($parts);
				continue;
			}
			$parts[] = $part;
		}

		return implode('/', $parts);
	}

	/**
	 * Normalize a row using aliases.
	 *
	 * @param array<string,mixed>  $row        Raw row
	 * @param array<string,string> $aliases    Alias map
	 * @param array<string,string> $fieldTypes Field types by field name
	 * @param string               $dataset    Dataset label
	 * @return array<string,mixed> Normalized row
	 */
	protected function normalizeRowWithAliases(array $row, array $aliases, array $fieldTypes, $dataset)
	{
		$normalized = array('_dataset' => $dataset);
		foreach ($row as $header => $value) {
			$normalizedheader = $this->normalizeHeader((string) $header);
			if ($normalizedheader === '' || !isset($aliases[$normalizedheader])) {
				continue;
			}
			$field = $aliases[$normalizedheader];
			if (isset($normalized[$field]) && $normalized[$field] !== null && $normalized[$field] !== '') {
				continue;
			}
			$type = isset($fieldTypes[$field]) ? $fieldTypes[$field] : 'varchar';
			$normalized[$field] = $this->parseFieldValue($value, $type);
		}

		return $normalized;
	}

	/**
	 * Build field map from headers.
	 *
	 * @param array<int,string> $normalizedheaders Headers
	 * @param string            $type              module|inverter|battery
	 * @return array<string,mixed> Field map
	 */
	protected function buildFieldMap(array $normalizedheaders, $type)
	{
		$aliases = ($type === 'inverter') ? $this->getInverterAliases() : ($type === 'battery' ? $this->getBatteryAliases() : $this->getModuleAliases());
		$fields = array();
		$compositionfields = array();
		$attributefields = array();
		$recognized = array();
		$ignored = array();
		foreach ($normalizedheaders as $idx => $header) {
			if ($header === '') {
				continue;
			}
			if (isset($aliases[$header])) {
				$field = $aliases[$header];
				if (!isset($fields[$field])) {
					$fields[$field] = $idx;
				}
				$recognized[$header] = $field;
			} elseif ($type === 'inverter' && !empty($this->parseMpptCompositionHeader($header))) {
				$compositionfields[$header] = $idx;
				$recognized[$header] = '_mppt_composition';
			} elseif (!empty($this->parseBatteryAttributeHeader($header))) {
				$attributefields[$header] = $idx;
				$recognized[$header] = '_technical_dictionary_codes';
			} else {
				$ignored[] = $header;
			}
		}

		return array('fields' => $fields, 'composition_fields' => $compositionfields, 'attribute_fields' => $attributefields, 'recognized_headers' => $recognized, 'ignored_headers' => array_values(array_unique($ignored)));
	}

	/**
	 * Validate every imported measurement before normalization.
	 *
	 * @param array<int,mixed>    $headers  Original headers
	 * @param array<int,mixed>    $cells    Raw row cells
	 * @param array<string,mixed> $fieldmap Parsed field map
	 * @param string              $type     module|inverter|battery
	 * @return bool True when all unit-bearing values are numeric
	 */
	protected function validateNumericImportRow(array $headers, array $cells, array $fieldmap, $type)
	{
		$fieldtypes = $this->getImportFieldTypes($type);
		$mappedfields = isset($fieldmap['fields']) && is_array($fieldmap['fields']) ? $fieldmap['fields'] : array();
		foreach ($mappedfields as $field => $columnindex) {
			$fieldtype = isset($fieldtypes[$field]) ? (string) $fieldtypes[$field] : 'varchar';
			if (!in_array($fieldtype, array('double', 'int'), true)) {
				continue;
			}
			$value = isset($cells[$columnindex]) ? trim((string) $cells[$columnindex]) : '';
			if ($value !== '' && powerplantpvParseTechnicalNumber($value, $fieldtype === 'int') === null) {
				dol_syslog(__METHOD__.' invalid numeric value on '.$type.'.'.$field, LOG_WARNING);
				return false;
			}
		}

		$compositionfields = isset($fieldmap['composition_fields']) && is_array($fieldmap['composition_fields']) ? $fieldmap['composition_fields'] : array();
		foreach ($compositionfields as $header => $columnindex) {
			if ($this->getMpptCompositionFieldUnit((string) $header) === 'text') {
				continue;
			}
			$value = isset($cells[$columnindex]) ? trim((string) $cells[$columnindex]) : '';
			if ($value !== '' && powerplantpvParseTechnicalNumber($value) === null) {
				dol_syslog(__METHOD__.' invalid numeric MPPT value on '.$header, LOG_WARNING);
				return false;
			}
		}

		return true;
	}

	/**
	 * Convert raw row to associative row.
	 *
	 * @param array<int,string> $headers Headers
	 * @param array<int,string> $cells   Cells
	 * @return array<string,string> Row
	 */
	protected function rowToAssoc(array $headers, array $cells)
	{
		$row = array();
		$count = max(count($headers), count($cells));
		for ($i = 0; $i < $count; $i++) {
			$header = isset($headers[$i]) && trim((string) $headers[$i]) !== '' ? (string) $headers[$i] : 'column_'.($i + 1);
			$row[$header] = isset($cells[$i]) ? (string) $cells[$i] : '';
		}

		return $row;
	}

	/**
	 * Count recognized values.
	 *
	 * @param array<string,mixed> $normalized Normalized row
	 * @return int Count
	 */
	protected function countRecognizedValues(array $normalized)
	{
		$count = 0;
		foreach ($normalized as $key => $value) {
			if ($key === '_dataset' || $key === '_legacy_warnings') {
				continue;
			}
			if ($key === '_mppt_composition' && is_array($value)) {
				$count += $this->countMpptCompositionValues($value);
				continue;
			}
			if (($key === '_battery_attributes' || $key === '_technical_dictionary_codes') && is_array($value)) {
				foreach ($value as $rows) {
					$count += is_array($rows) ? count($rows) : 0;
				}
				continue;
			}
			if ($value !== null && $value !== '') {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Count imported MPPT composition values.
	 *
	 * @param array<int,array<string,mixed>> $composition MPPT composition
	 * @return int Count
	 */
	protected function countMpptCompositionValues(array $composition)
	{
		$count = 0;
		foreach ($composition as $mppt) {
			if (!is_array($mppt)) {
				continue;
			}
			foreach ($mppt as $key => $value) {
				if ($key === 'position' || $key === 'inputs') {
					continue;
				}
				if ($value !== null && $value !== '') {
					$count++;
				}
			}
			$inputs = isset($mppt['inputs']) && is_array($mppt['inputs']) ? $mppt['inputs'] : array();
			foreach ($inputs as $input) {
				if (!is_array($input)) {
					continue;
				}
				foreach ($input as $key => $value) {
					if ($key === 'position') {
						continue;
					}
					if ($value !== null && $value !== '') {
						$count++;
					}
				}
			}
		}

		return $count;
	}

	/**
	 * Return first raw value by aliases.
	 *
	 * @param array<string,mixed> $raw     Raw row
	 * @param array<int,string>   $aliases Aliases
	 * @return string Value
	 */
	protected function firstRawValue(array $raw, array $aliases)
	{
		$lookup = array();
		foreach ($aliases as $alias) {
			$lookup[$this->normalizeHeader($alias)] = 1;
		}
		foreach ($raw as $header => $value) {
			if (isset($lookup[$this->normalizeHeader((string) $header)]) && trim((string) $value) !== '') {
				return trim((string) $value);
			}
		}

		return '';
	}

	/**
	 * Return first normalized value.
	 *
	 * @param array<string,mixed> $normalized Normalized row
	 * @param array<int,string>   $fields     Fields
	 * @return mixed Value
	 */
	protected function firstNormalizedValue(array $normalized, array $fields)
	{
		foreach ($fields as $field) {
			if (isset($normalized[$field]) && $normalized[$field] !== null && $normalized[$field] !== '') {
				return $normalized[$field];
			}
		}

		return '';
	}

	/**
	 * Normalize MPPT and PV input composition columns from an inverter row.
	 *
	 * @param array<string,mixed> $row Raw row indexed by file headers
	 * @return array<int,array<string,mixed>> Composition indexed by MPPT number
	 */
	protected function normalizeMpptCompositionRow(array $row)
	{
		$composition = array();
		foreach ($row as $header => $value) {
			$descriptor = $this->parseMpptCompositionHeader($this->normalizeHeader((string) $header));
			if (empty($descriptor)) {
				continue;
			}

			$type = $this->getMpptCompositionFieldType((string) $descriptor['field'], (string) $descriptor['kind']);
			$parsed = $this->parseFieldValue($value, $type);
			if ($parsed === null || $parsed === '') {
				continue;
			}

			$mpptnumber = (int) $descriptor['mppt_number'];
			if (empty($composition[$mpptnumber])) {
				$composition[$mpptnumber] = array(
					'position' => $mpptnumber,
					'inputs' => array(),
				);
			}

			if ((string) $descriptor['kind'] === 'mppt') {
				$composition[$mpptnumber][(string) $descriptor['field']] = $parsed;
				continue;
			}

			$inputnumber = isset($descriptor['input_number']) ? (int) $descriptor['input_number'] : 0;
			if ($inputnumber <= 0) {
				continue;
			}
			if (empty($composition[$mpptnumber]['inputs'][$inputnumber])) {
				$composition[$mpptnumber]['inputs'][$inputnumber] = array('position' => $inputnumber);
			}
			$composition[$mpptnumber]['inputs'][$inputnumber][(string) $descriptor['field']] = $parsed;
		}

		if (!empty($composition)) {
			ksort($composition);
			foreach ($composition as $mpptnumber => $mppt) {
				if (isset($mppt['inputs']) && is_array($mppt['inputs'])) {
					ksort($mppt['inputs']);
					$composition[$mpptnumber]['inputs'] = $mppt['inputs'];
				}
			}
		}

		return $composition;
	}

	/**
	 * Parse a normalized MPPT composition header.
	 *
	 * @param string $header Normalized header
	 * @return array<string,mixed> Descriptor, empty array when not a composition header
	 */
	protected function parseMpptCompositionHeader($header)
	{
		if (!preg_match('/^mppt_([0-9]+)_(.+)$/', (string) $header, $matches)) {
			return array();
		}

		$mpptnumber = (int) $matches[1];
		if ($mpptnumber <= 0) {
			return array();
		}

		$tail = (string) $matches[2];
		if (preg_match('/^(input|inputs|pv_input|pv_inputs|pvinput|pvinputs|entree|entrees|entree_pv|entrees_pv|chaine|chaines|chaine_pv|chaines_pv|string|strings)_([0-9]+)_(.+)$/', $tail, $inputmatches)) {
			$inputnumber = (int) $inputmatches[2];
			$field = $this->mapMpptCompositionFieldAlias((string) $inputmatches[3], 'input');
			if ($inputnumber <= 0 || $field === '') {
				return array();
			}

			return array(
				'kind' => 'input',
				'mppt_number' => $mpptnumber,
				'input_number' => $inputnumber,
				'field' => $field,
			);
		}

		$field = $this->mapMpptCompositionFieldAlias($tail, 'mppt');
		if ($field === '') {
			return array();
		}

		return array(
			'kind' => 'mppt',
			'mppt_number' => $mpptnumber,
			'field' => $field,
		);
	}

	/**
	 * Append repeatable protocol, certification and protection codes.
	 *
	 * @param array<string,mixed> $normalized Normalized row
	 * @param array<string,mixed> $row Raw row
	 * @return void
	 */
	protected function appendTechnicalDictionaryCodes(array &$normalized, array $row)
	{
		$groups = array();
		foreach ($row as $rawheader => $rawvalue) {
			$descriptor = $this->parseBatteryAttributeHeader($this->normalizeHeader((string) $rawheader));
			$value = trim((string) $rawvalue);
			if (empty($descriptor) || $value === '') {
				continue;
			}
			$type = (string) $descriptor['dictionary_type'];
			if (!isset($groups[$type])) {
				$groups[$type] = array();
			}
			$groups[$type][] = $value;
		}
		if (!empty($groups)) {
			$normalized['_technical_dictionary_codes'] = $groups;
		}
	}

	/**
	 * Parse a repeated normalized technical dictionary header.
	 *
	 * @param string $header Normalized header
	 * @return array<string,mixed> Descriptor or empty array
	 */
	protected function parseBatteryAttributeHeader($header)
	{
		if (!preg_match('/^(communication_protocol|protocol|protection|certification)_([0-9]+)$/', (string) $header, $matches)) {
			return array();
		}
		$position = (int) $matches[2];
		if ($position <= 0) {
			return array();
		}
		$typeMap = array(
			'communication_protocol' => 'communication_protocol',
			'protocol' => 'communication_protocol',
			'certification' => 'certification',
			'protection' => 'protection',
		);
		return array(
			'type' => strtoupper((string) $matches[1]),
			'dictionary_type' => $typeMap[(string) $matches[1]],
			'position' => $position,
		);
	}

	/**
	 * Return the expected unit for a normalized MPPT composition header.
	 *
	 * @param string $header Normalized header
	 * @return string Unit or format
	 */
	protected function getMpptCompositionFieldUnit($header)
	{
		$descriptor = $this->parseMpptCompositionHeader($header);
		$field = isset($descriptor['field']) ? (string) $descriptor['field'] : '';
		if (in_array($field, array('voltage_min', 'voltage_max'), true)) {
			return 'V';
		}
		if (strpos($field, 'current') !== false) {
			return 'A';
		}
		if ($field === 'max_dc_power') {
			return 'W';
		}
		return 'text';
	}

	/**
	 * Map a MPPT composition field alias to a stored field.
	 *
	 * @param string $field Field alias
	 * @param string $kind  mppt|input
	 * @return string Stored field or empty string
	 */
	protected function mapMpptCompositionFieldAlias($field, $kind)
	{
		$mpptaliases = array(
			'label' => 'label',
			'libelle' => 'label',
			'name' => 'label',
			'nom' => 'label',
			'voltage_min' => 'voltage_min',
			'mppt_voltage_min' => 'voltage_min',
			'vmppt_min' => 'voltage_min',
			'tension_min' => 'voltage_min',
			'tension_mppt_min' => 'voltage_min',
			'voltage_max' => 'voltage_max',
			'mppt_voltage_max' => 'voltage_max',
			'vmppt_max' => 'voltage_max',
			'tension_max' => 'voltage_max',
			'tension_mppt_max' => 'voltage_max',
			'max_input_current' => 'max_input_current',
			'input_current_max' => 'max_input_current',
			'courant_entree_max' => 'max_input_current',
			'courant_max_entree' => 'max_input_current',
			'max_short_circuit_current' => 'max_short_circuit_current',
			'short_circuit_current_max' => 'max_short_circuit_current',
			'courant_court_circuit_max' => 'max_short_circuit_current',
			'courant_cc_max' => 'max_short_circuit_current',
			'max_dc_power' => 'max_dc_power',
			'dc_power_max' => 'max_dc_power',
			'puissance_dc_max' => 'max_dc_power',
			'note_private' => 'note_private',
			'note' => 'note_private',
			'notes' => 'note_private',
		);

		$inputaliases = array(
			'label' => 'label',
			'libelle' => 'label',
			'name' => 'label',
			'nom' => 'label',
			'max_input_current' => 'max_input_current',
			'input_current_max' => 'max_input_current',
			'courant_entree_max' => 'max_input_current',
			'courant_max_entree' => 'max_input_current',
			'max_short_circuit_current' => 'max_short_circuit_current',
			'short_circuit_current_max' => 'max_short_circuit_current',
			'courant_court_circuit_max' => 'max_short_circuit_current',
			'courant_cc_max' => 'max_short_circuit_current',
			'connector_type' => 'connector_type',
			'connector' => 'connector_type',
			'connecteur' => 'connector_type',
			'type_connecteur' => 'connector_type',
			'note_private' => 'note_private',
			'note' => 'note_private',
			'notes' => 'note_private',
		);

		$aliases = ($kind === 'input') ? $inputaliases : $mpptaliases;
		$field = (string) $field;

		return isset($aliases[$field]) ? $aliases[$field] : '';
	}

	/**
	 * Return the field type for a composition field.
	 *
	 * @param string $field Field name
	 * @param string $kind  mppt|input
	 * @return string Field type
	 */
	protected function getMpptCompositionFieldType($field, $kind)
	{
		$fields = ($kind === 'input') ? ProductInverter::getPvInputFields() : ProductInverter::getMpptFields();

		return isset($fields[$field]['type']) ? (string) $fields[$field]['type'] : 'varchar';
	}

	/**
	 * Return module aliases.
	 *
	 * @return array<string,string> Alias => field
	 */
	protected function getModuleAliases()
	{
		$aliases = array(
			'pmax' => 'pmax',
			'pmpp' => 'pmax',
			'stc' => 'pmax',
			'power' => 'pmax',
			'puissance' => 'pmax',
			'puissance_stc' => 'pmax',
			'power_tolerance' => '_legacy_power_tolerance',
			'tolerance' => '_legacy_power_tolerance',
			'tolerance_puissance' => '_legacy_power_tolerance',
			'module_efficiency' => 'module_efficiency',
			'efficiency' => 'module_efficiency',
			'rendement' => 'module_efficiency',
			'vmp' => 'vmp',
			'vmpp' => 'vmp',
			'v_mpp' => 'vmp',
			'tension_mpp' => 'vmp',
			'imp' => 'imp',
			'impp' => 'imp',
			'i_mpp' => 'imp',
			'courant_mpp' => 'imp',
			'voc' => 'voc',
			'uoc' => 'voc',
			'v_oc' => 'voc',
			'tension_vide' => 'voc',
			'isc' => 'isc',
			'i_sc' => 'isc',
			'courant_cc' => 'isc',
			'front_glass_thickness' => 'front_glass_thickness',
			'front_glass_thickness_mm' => 'front_glass_thickness',
			'front_glass' => 'front_glass_thickness',
			'front_glass_mm' => 'front_glass_thickness',
			'glass_front' => 'front_glass_thickness',
			'glass_front_mm' => 'front_glass_thickness',
			'epaisseur_verre_avant' => 'front_glass_thickness',
			'epaisseur_verre_avant_mm' => 'front_glass_thickness',
			'verre_avant' => 'front_glass_thickness',
			'back_glass_thickness' => 'back_glass_thickness',
			'back_glass_thickness_mm' => 'back_glass_thickness',
			'back_glass' => 'back_glass_thickness',
			'back_glass_mm' => 'back_glass_thickness',
			'glass_back' => 'back_glass_thickness',
			'glass_back_mm' => 'back_glass_thickness',
			'epaisseur_verre_arriere' => 'back_glass_thickness',
			'epaisseur_verre_arriere_mm' => 'back_glass_thickness',
			'verre_arriere' => 'back_glass_thickness',
			'cable_section' => 'cable_section',
			'cable_section_mm' => 'cable_section',
			'cable_section_mm2' => 'cable_section',
			'cable_section_mm_2' => 'cable_section',
			'section_cable' => 'cable_section',
			'section_cable_mm' => 'cable_section',
			'section_cable_mm2' => 'cable_section',
			'section_cable_mm_2' => 'cable_section',
			'cable_length' => 'cable_length',
			'cable_length_mm' => 'cable_length',
			'longueur_cable' => 'cable_length',
			'longueur_cable_mm' => 'cable_length',
			'noct' => 'noct',
			'nmot' => 'noct',
			'gamma_pmax' => 'temp_coeff_pmax',
			'temp_coeff_pmax' => 'temp_coeff_pmax',
			'coeff_pmax' => 'temp_coeff_pmax',
			'beta_voc' => 'temp_coeff_voc',
			'temp_coeff_voc' => 'temp_coeff_voc',
			'coeff_voc' => 'temp_coeff_voc',
			'alpha_isc' => 'temp_coeff_isc',
			'temp_coeff_isc' => 'temp_coeff_isc',
			'coeff_isc' => 'temp_coeff_isc',
			'max_system_voltage' => 'max_system_voltage',
			'tension_systeme_max' => 'max_system_voltage',
			'max_series_fuse' => 'max_series_fuse',
			'fuse' => 'max_series_fuse',
			'fusible_max' => 'max_series_fuse',
			'operating_temperature' => '_legacy_operating_temperature',
			'operating_temperature_c' => '_legacy_operating_temperature',
			'temperature_fonctionnement' => '_legacy_operating_temperature',
			'temperature_fonctionnement_c' => '_legacy_operating_temperature',
			'temperature_de_fonctionnement' => '_legacy_operating_temperature',
			'temperature_de_fonctionnement_c' => '_legacy_operating_temperature',
			'temperature_service' => '_legacy_operating_temperature',
			'temperature_service_c' => '_legacy_operating_temperature',
			'snow_load' => 'snow_load',
			'snow_load_pa' => 'snow_load',
			'charge_neige' => 'snow_load',
			'charge_neige_pa' => 'snow_load',
			'wind_load' => 'wind_load',
			'wind_load_pa' => 'wind_load',
			'charge_vent' => 'wind_load',
			'charge_vent_pa' => 'wind_load',
			'product_warranty' => 'product_warranty',
			'warranty_product' => 'product_warranty',
			'garantie_produit' => 'product_warranty',
			'power_warranty' => 'power_warranty',
			'warranty_power' => 'power_warranty',
			'garantie_puissance' => 'power_warranty',
			'first_year_degradation' => 'first_year_degradation',
			'degradation_first_year' => 'first_year_degradation',
			'degradation_1ere_annee' => 'first_year_degradation',
			'degradation_premiere_annee' => 'first_year_degradation',
			'annual_degradation' => 'annual_degradation',
			'degradation_annuelle' => 'annual_degradation',
			'degradation_an' => 'annual_degradation',
			'modules_per_box' => 'modules_per_box',
			'modules_per_box_pcs' => 'modules_per_box',
			'modules_box' => 'modules_per_box',
			'modules_box_pcs' => 'modules_per_box',
			'modules_par_boite' => 'modules_per_box',
			'modules_par_boite_pcs' => 'modules_per_box',
			'modules_boite' => 'modules_per_box',
			'modules_boite_pcs' => 'modules_per_box',
			'modules_per_container40' => 'modules_per_container40',
			'modules_per_container40_pcs' => 'modules_per_container40',
			'modules_per_container_40' => 'modules_per_container40',
			'modules_per_container_40_pcs' => 'modules_per_container40',
			'modules_per_container_40_ft' => 'modules_per_container40',
			'modules_per_container_40_ft_pcs' => 'modules_per_container40',
			'modules_per_40_ft_container' => 'modules_per_container40',
			'modules_per_40_ft_container_pcs' => 'modules_per_container40',
			'modules_per_40_container' => 'modules_per_container40',
			'modules_per_40_container_pcs' => 'modules_per_container40',
			'modules_container40' => 'modules_per_container40',
			'modules_container40_pcs' => 'modules_per_container40',
			'modules_container_40' => 'modules_per_container40',
			'modules_container_40_pcs' => 'modules_per_container40',
			'modules_par_conteneur40' => 'modules_per_container40',
			'modules_par_conteneur40_pcs' => 'modules_per_container40',
			'modules_par_conteneur_40' => 'modules_per_container40',
			'modules_par_conteneur_40_pcs' => 'modules_per_container40',
		);

		return $this->addCanonicalAliases($aliases, PowerPlantPVProductImport::getModuleImportFields());
	}

	/**
	 * Return inverter aliases.
	 *
	 * @return array<string,string> Alias => field
	 */
	protected function getInverterAliases()
	{
		$aliases = array(
			'pv_max_power' => 'pv_max_power',
			'dc_power_max' => 'pv_max_power',
			'puissance_dc_max' => 'pv_max_power',
			'dc_max_voltage' => 'dc_max_voltage',
			'vdc_max' => 'dc_max_voltage',
			'tension_dc_max' => 'dc_max_voltage',
			'startup_voltage' => 'startup_voltage',
			'start_voltage' => 'startup_voltage',
			'tension_demarrage' => 'startup_voltage',
			'mppt_voltage_min' => 'mppt_voltage_min',
			'vmppt_min' => 'mppt_voltage_min',
			'mppt_voltage_max' => 'mppt_voltage_max',
			'vmppt_max' => 'mppt_voltage_max',
			'nominal_dc_voltage' => 'nominal_dc_voltage',
			'vdc_nominal' => 'nominal_dc_voltage',
			'ac_nominal_power' => 'ac_nominal_power',
			'pac_nom' => 'ac_nominal_power',
			'puissance_ac_nominale' => 'ac_nominal_power',
			'ac_max_power' => 'ac_max_power',
			'pac_max' => 'ac_max_power',
			'puissance_ac_max' => 'ac_max_power',
			'ac_apparent_power' => 'ac_apparent_power',
			'puissance_apparente' => 'ac_apparent_power',
			'kva' => 'ac_apparent_power',
			'ac_nominal_voltage' => '_legacy_ac_voltage',
			'vac_nominal' => '_legacy_ac_voltage',
			'grid_frequency' => '_legacy_grid_frequency',
			'frequency' => '_legacy_grid_frequency',
			'frequence' => '_legacy_grid_frequency',
			'ac_max_output_current' => 'ac_max_output_current',
			'iac_max' => 'ac_max_output_current',
			'courant_ac_max' => 'ac_max_output_current',
			'power_factor' => '_legacy_power_factor',
			'cos_phi' => '_legacy_power_factor',
			'cosphi' => '_legacy_power_factor',
			'facteur_puissance' => '_legacy_power_factor',
			'facteur_de_puissance' => '_legacy_power_factor',
			'thd' => '_legacy_thd',
			'taux_distorsion_harmonique' => '_legacy_thd',
			'distorsion_harmonique' => '_legacy_thd',
			'backup_nominal_voltage' => '_legacy_backup_voltage',
			'tension_secours_nominale' => '_legacy_backup_voltage',
			'backup_thd' => '_legacy_backup_thd',
			'thd_secours' => '_legacy_backup_thd',
			'max_efficiency' => 'max_efficiency',
			'efficiency_max' => 'max_efficiency',
			'rendement_max' => 'max_efficiency',
			'european_efficiency' => 'european_efficiency',
			'euro_efficiency' => 'european_efficiency',
			'rendement_europeen' => 'european_efficiency',
			'dc_switch' => 'dc_switch',
			'switch_dc' => 'dc_switch',
			'interrupteur_dc' => 'dc_switch',
			'sectionneur_dc' => 'dc_switch',
			'dc_spd' => 'dc_spd',
			'spd_dc' => 'dc_spd',
			'surge_protection_dc' => 'dc_spd',
			'protection_surtension_dc' => 'dc_spd',
			'parafoudre_dc' => 'dc_spd',
			'ac_spd' => 'ac_spd',
			'spd_ac' => 'ac_spd',
			'surge_protection_ac' => 'ac_spd',
			'protection_surtension_ac' => 'ac_spd',
			'parafoudre_ac' => 'ac_spd',
			'afci' => 'afci',
			'arc_fault_circuit_interrupter' => 'afci',
			'pid_recovery' => 'pid_recovery',
			'anti_pid' => 'pid_recovery',
			'recuperation_pid' => 'pid_recovery',
			'anti_islanding' => 'anti_islanding',
			'anti_islanding_protection' => 'anti_islanding',
			'anti_ilotage' => 'anti_islanding',
			'protection_anti_ilotage' => 'anti_islanding',
			'dc_reverse_polarity_protection' => 'dc_reverse_polarity_protection',
			'reverse_polarity_protection' => 'dc_reverse_polarity_protection',
			'polarite_inverse_dc' => 'dc_reverse_polarity_protection',
			'protection_polarite_inverse_dc' => 'dc_reverse_polarity_protection',
			'insulation_monitoring' => 'insulation_monitoring',
			'surveillance_isolement' => 'insulation_monitoring',
			'controle_isolement' => 'insulation_monitoring',
			'residual_current_monitoring' => 'residual_current_monitoring',
			'rcmu' => 'residual_current_monitoring',
			'courant_residuel' => 'residual_current_monitoring',
			'surveillance_courant_residuel' => 'residual_current_monitoring',
			'ip_rating' => 'ip_rating',
			'indice_ip' => 'ip_rating',
			'operating_temperature' => '_legacy_operating_temperature',
			'temperature_fonctionnement' => '_legacy_operating_temperature',
			'relative_humidity' => '_legacy_relative_humidity',
			'humidity' => '_legacy_relative_humidity',
			'humidite_relative' => '_legacy_relative_humidity',
			'humidite' => '_legacy_relative_humidity',
			'cooling' => 'cooling',
			'refroidissement' => 'cooling',
			'max_altitude' => 'max_altitude',
			'altitude_max' => 'max_altitude',
			'altitude_maximum' => 'max_altitude',
			'noise' => '_legacy_noise',
			'noise_db' => '_legacy_noise',
			'bruit' => '_legacy_noise',
			'bruit_db' => '_legacy_noise',
			'topology' => 'topology',
			'topologie' => 'topology',
			'night_consumption' => 'night_consumption',
			'nighttime_consumption' => 'night_consumption',
			'standby_consumption' => 'night_consumption',
			'consommation_nocturne' => 'night_consumption',
			'display_type' => 'display_type',
			'display' => 'display_type',
			'afficheur' => 'display_type',
			'ecran' => 'display_type',
			'communication_interfaces' => 'communication_interfaces',
			'communication' => 'communication_interfaces',
			'interfaces_communication' => 'communication_interfaces',
			'dc_connector' => 'dc_connector',
			'connector_dc' => 'dc_connector',
			'connecteur_dc' => 'dc_connector',
			'ac_connector' => 'ac_connector',
			'connector_ac' => 'ac_connector',
			'connecteur_ac' => 'ac_connector',
			'mounting' => 'mounting',
			'montage' => 'mounting',
			'warranty' => 'warranty',
			'garantie' => 'warranty',
			'certifications' => 'certifications',
			'certification' => 'certifications',
		);

		return $this->addCanonicalAliases($aliases, PowerPlantPVProductImport::getInverterImportFields());
	}

	/**
	 * Add canonical technical field names as aliases.
	 *
	 * @param array<string,string> $aliases Existing aliases
	 * @param array<int,string>    $fields  Canonical fields
	 * @return array<string,string> Aliases
	 */
	protected function addCanonicalAliases(array $aliases, array $fields)
	{
		foreach ($fields as $field) {
			$aliases[$field] = $field;
		}

		return $aliases;
	}

	/**
	 * Return combined aliases.
	 *
	 * @return array<string,string> Aliases
	 */
	protected function getCombinedAliases()
	{
		return array_merge($this->getModuleAliases(), $this->getInverterAliases(), $this->getBatteryAliases());
	}

	/**
	 * Return module field types.
	 *
	 * @return array<string,string> Type by field
	 */
	protected function getModuleFieldTypes()
	{
		$types = array();
		foreach (PowerPlantPVProductImport::getModuleImportFields() as $field) {
			$types[$field] = 'double';
		}
		$types['_legacy_power_tolerance'] = 'varchar';
		$types['_legacy_operating_temperature'] = 'varchar';

		return $types;
	}

	/**
	 * Return field types for an import dataset.
	 *
	 * @param string $type module|inverter|battery
	 * @return array<string,string> Type by field
	 */
	protected function getImportFieldTypes($type)
	{
		if ($type === 'inverter') {
			return $this->getInverterFieldTypes();
		}
		if ($type === 'battery') {
			return $this->getBatteryFieldTypes();
		}
		return $this->getModuleFieldTypes();
	}

	/**
	 * Return inverter field types.
	 *
	 * @return array<string,string> Type by field
	 */
	protected function getInverterFieldTypes()
	{
		$types = array();
		foreach (ProductInverter::getInverterFields() as $field => $spec) {
			$type = isset($spec['type']) ? (string) $spec['type'] : 'varchar';
			if (!empty($spec['numeric']) && !in_array($type, array('double', 'int'), true)) {
				$type = 'double';
			}
			$types[$field] = $type === 'select' ? 'varchar' : $type;
		}
		foreach (array('_legacy_ac_voltage', '_legacy_grid_frequency', '_legacy_power_factor', '_legacy_thd', '_legacy_backup_voltage', '_legacy_backup_thd', '_legacy_operating_temperature', '_legacy_relative_humidity', '_legacy_noise') as $field) {
			$types[$field] = 'varchar';
		}

		return $types;
	}

	/** @return array<string,string> Battery aliases */
	protected function getBatteryAliases()
	{
		$aliases = array();
		foreach (PowerPlantPVProductImport::getBatteryImportFields() as $field) {
			$aliases[$field] = $field;
		}
		$aliases['capacite_nominale'] = 'nominal_energy';
		$aliases['capacite_utile'] = 'usable_energy';
		$aliases['energie_nominale'] = 'nominal_energy';
		$aliases['energie_utile'] = 'usable_energy';
		$aliases['profondeur_decharge'] = 'dod';
		$aliases['cycles'] = 'cycle_life';
		return $aliases;
	}

	/** @return array<string,string> Battery field types */
	protected function getBatteryFieldTypes()
	{
		$types = array();
		foreach (ProductBattery::getBatteryFields() as $field => $spec) {
			$type = isset($spec['type']) ? (string) $spec['type'] : 'varchar';
			$types[$field] = $type === 'select' ? 'varchar' : $type;
		}
		return $types;
	}

	/**
	 * Normalize a header string.
	 *
	 * @param string $header Header
	 * @return string Normalized header
	 */
	protected function normalizeHeader($header)
	{
		$header = preg_replace('/\s*\[[^\]]+\]\s*$/u', '', trim((string) $header));
		$header = trim(strtolower((string) $header));
		if (function_exists('iconv')) {
			$converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $header);
			if ($converted !== false) {
				$header = $converted;
			}
		}
		$header = preg_replace('/[^a-z0-9]+/', '_', $header);
		$header = preg_replace('/_+/', '_', (string) $header);

		return trim((string) $header, '_');
	}

	/**
	 * Parse a value according to the target field type.
	 *
	 * @param mixed  $value Value
	 * @param string $type  Field type
	 * @return int|float|string|null Parsed value
	 */
	protected function parseFieldValue($value, $type)
	{
		if ($type === 'double') {
			return $this->parseNumericValue($value);
		}
		if ($type === 'int') {
			return $this->parseIntegerValue($value);
		}
		if ($type === 'bool') {
			return $this->parseBooleanValue($value);
		}

		return $this->parseStringValue($value);
	}

	/**
	 * Parse a numeric value without unit conversion.
	 *
	 * @param mixed $value Value
	 * @return float|null Number
	 */
	protected function parseNumericValue($value)
	{
		return powerplantpvParseTechnicalNumber($value);
	}

	/**
	 * Parse an integer value without unit conversion.
	 *
	 * @param mixed $value Value
	 * @return int|null Number
	 */
	protected function parseIntegerValue($value)
	{
		return powerplantpvParseTechnicalNumber($value, true);
	}

	/**
	 * Parse a boolean value.
	 *
	 * Unknown non-empty values are ignored instead of being cast to false.
	 *
	 * @param mixed $value Value
	 * @return int|null 1, 0 or null
	 */
	protected function parseBooleanValue($value)
	{
		$value = trim((string) $value);
		if ($value === '') {
			return null;
		}

		$value = str_replace("\xc2\xa0", ' ', $value);
		$normalized = strtolower($value);
		if (function_exists('iconv')) {
			$converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
			if ($converted !== false) {
				$normalized = $converted;
			}
		}
		$normalized = trim((string) preg_replace('/[^a-z0-9]+/', '_', $normalized), '_');

		if (in_array($normalized, array('1', 'yes', 'true', 'oui', 'vrai', 'on'), true)) {
			return 1;
		}
		if (in_array($normalized, array('0', 'no', 'false', 'non', 'faux', 'off'), true)) {
			return 0;
		}

		return null;
	}

	/**
	 * Parse a string value.
	 *
	 * @param mixed $value Value
	 * @return string|null String
	 */
	protected function parseStringValue($value)
	{
		$value = trim((string) $value);

		return ($value === '' ? null : $value);
	}

	/**
	 * Clean a cell value.
	 *
	 * @param string $value Value
	 * @return string Clean value
	 */
	protected function cleanCell($value)
	{
		return trim(str_replace("\xc2\xa0", ' ', (string) $value));
	}

	/**
	 * Check if a row is empty.
	 *
	 * @param array<int,string> $row Row
	 * @return bool True if empty
	 */
	protected function isEmptyRow(array $row)
	{
		foreach ($row as $cell) {
			if (trim((string) $cell) !== '') {
				return false;
			}
		}

		return true;
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
	 * @param string $error Error key
	 * @return void
	 */
	protected function setError($error)
	{
		$this->error = $error;
		$this->errors[] = $error;
	}
}
