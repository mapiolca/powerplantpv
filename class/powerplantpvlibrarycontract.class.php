<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
require_once __DIR__.'/powerplantpvproductimport.class.php';

/** Versioned interchange contract. No rowids, prices or private fields cross this boundary. */
class PowerPlantPVLibraryContract
{
	/** @return array<string,array<string,array<string,mixed>>> */
	public static function fields(): array
	{
		$result = array();
		foreach (array('module', 'inverter') as $type) {
			$names = $type === 'module' ? PowerPlantPVProductImport::getModuleImportFields() : PowerPlantPVProductImport::getInverterImportFields();
			foreach ($names as $name) {
				$result[$type][$name] = PowerPlantPVProductImport::getImportFieldDefinition($type, $name);
			}
		}
		return $result;
	}

	/** Validate a revision snapshot, including evidence and exact units. @param array<string,mixed> $item */
	public static function validate(array $item): void
	{
		if (($item['schema_version'] ?? null) !== 1 || !is_string($item['catalog_id'] ?? null)
			|| !preg_match('/^[a-f0-9]{32}$/D', $item['catalog_id']) || !is_int($item['revision'] ?? null) || $item['revision'] < 1) {
			throw new InvalidArgumentException('PVLibraryInvalidIdentity');
		}
		foreach (array('manufacturer', 'model', 'label') as $key) {
			if (!is_string($item[$key] ?? null) || trim($item[$key]) === '' || mb_strlen($item[$key]) > 128 || strip_tags($item[$key]) !== $item[$key]) {
				throw new InvalidArgumentException('PVLibraryInvalidIdentity');
			}
		}
		$types = self::fields();
		$type = $item['type'] ?? '';
		if (!is_string($type) || !isset($types[$type]) || !is_array($item['technical'] ?? null) || !$item['technical']) {
			throw new InvalidArgumentException('PVLibraryInvalidTechnicalData');
		}
		foreach ($item['technical'] as $field => $data) {
			if (!isset($types[$type][$field]) || !is_array($data) || !array_key_exists('value', $data)
				|| ($data['unit'] ?? null) !== $types[$type][$field]['unit'] || !is_string($data['quote'] ?? null)
				|| trim($data['quote']) === '' || mb_strlen($data['quote']) > 1000
				|| !is_string($data['source_url'] ?? null) || !filter_var($data['source_url'], FILTER_VALIDATE_URL)
				|| parse_url($data['source_url'], PHP_URL_SCHEME) !== 'https' || preg_match('/[\r\n]/', $data['source_url'])
				|| parse_url($data['source_url'], PHP_URL_USER) !== null || parse_url($data['source_url'], PHP_URL_FRAGMENT) !== null) {
				throw new InvalidArgumentException('PVLibraryInvalidEvidence');
			}
			$definition = $types[$type][$field];
			$value = $data['value'];
			$datatype = $definition['type'];
			// The registry exposes canonical datatype, never infer units from magnitude.
			if (in_array($datatype, array('decimal', 'integer', 'boolean'), true)) {
				if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)
					|| ($datatype === 'integer' && floor($value) != $value)
					|| ($datatype === 'boolean' && !in_array($value, array(0, 1), true))) {
					throw new InvalidArgumentException('PVLibraryInvalidTechnicalData');
				}
			} elseif (!is_string($value) || mb_strlen($value) > 255 || strip_tags($value) !== $value) {
				throw new InvalidArgumentException('PVLibraryInvalidTechnicalData');
			}
		}
		$required = $type === 'module' ? array('pmax', 'voc', 'vmp', 'isc', 'imp') : array('ac_nominal_power', 'dc_max_voltage');
		foreach ($required as $field) {
			if (!isset($item['technical'][$field]['value']) || !is_numeric($item['technical'][$field]['value']) || $item['technical'][$field]['value'] <= 0) {
				throw new InvalidArgumentException('PVLibraryMissingRequiredData');
			}
		}
		if ($type === 'module' && ($item['technical']['vmp']['value'] >= $item['technical']['voc']['value'] || $item['technical']['imp']['value'] > $item['technical']['isc']['value'])) {
			throw new InvalidArgumentException('PVLibraryInconsistentData');
		}
	}

	/** @param array<string,mixed> $item @return array<string,int|float|string> */
	public static function normalized(array $item): array
	{
		self::validate($item);
		$result = array();
		foreach ($item['technical'] as $field => $data) {
			$result[$field] = $data['value'];
		}
		return $result;
	}
}
