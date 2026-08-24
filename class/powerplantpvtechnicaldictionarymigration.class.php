<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once __DIR__.'/powerplantpvproductdictionary.class.php';

/**
 * One-time migration of legacy technical free-text values.
 */
class PowerPlantPVTechnicalDictionaryMigration
{
	public const VERSION = 1;
	public const MARKER = 'POWERPLANTPV_TECHNICAL_DICTIONARY_MIGRATION_VERSION';

	/** @var DoliDB */
	private $db;

	/** @var string */
	public $error = '';

	/** @var array<int,string> */
	public $errors = array();

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * @param int $entity Current entity
	 * @param int $userId Acting administrator
	 * @return int
	 */
	public function migrateOnce($entity, $userId)
	{
		if (getDolGlobalInt(self::MARKER, 0) >= self::VERSION) {
			return 1;
		}

		$this->db->begin();
		if ($this->migrateBatteryAttributes($entity, $userId) < 0 || $this->migrateInverterFields($entity, $userId) < 0) {
			$this->db->rollback();
			return -1;
		}
		if (dolibarr_set_const($this->db, self::MARKER, (string) self::VERSION, 'chaine', 0, '', (int) $entity) <= 0) {
			$this->error = 'PowerPlantPVTechnicalDictionaryMigrationMarkerError';
			$this->errors[] = $this->error;
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		return 1;
	}

	/** @return int */
	private function migrateBatteryAttributes($entity, $userId)
	{
		$typeMap = array(
			'PROTOCOL' => PowerPlantPVProductDictionary::TYPE_COMMUNICATION_PROTOCOL,
			'CERTIFICATION' => PowerPlantPVProductDictionary::TYPE_CERTIFICATION,
			'PROTECTION' => PowerPlantPVProductDictionary::TYPE_PROTECTION,
		);
		$sql = 'SELECT b.fk_product, a.attribute_type, a.attribute_code, a.attribute_label';
		$sql .= ' FROM '.$this->db->prefix().'powerplantpv_product_battery as b';
		$sql .= ' INNER JOIN '.$this->db->prefix().'powerplantpv_product_battery_attribute as a ON a.fk_battery = b.rowid';
		$sql .= ' INNER JOIN '.$this->db->prefix().'product as p ON p.rowid = b.fk_product AND p.entity = b.entity AND p.fk_product_type = 0';
		$sql .= ' WHERE b.entity = '.((int) $entity);
		$resql = $this->db->query($sql);
		if (!$resql) {
			return $this->setDatabaseError();
		}
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$legacyType = strtoupper(trim((string) $obj->attribute_type));
			if (!isset($typeMap[$legacyType])) {
				continue;
			}
			$label = trim((string) $obj->attribute_label);
			$preferredCode = strtoupper(trim((string) $obj->attribute_code));
			$id = $this->findOrCreateEntry($typeMap[$legacyType], $preferredCode, $label, $entity);
			if ($id <= 0 || $this->insertLink((int) $obj->fk_product, $typeMap[$legacyType], $id, $entity, $userId) < 0) {
				$this->db->free($resql);
				return -1;
			}
		}
		$this->db->free($resql);
		return 1;
	}

	/** @return int */
	private function migrateInverterFields($entity, $userId)
	{
		$sql = 'SELECT i.fk_product, i.communication_interfaces, i.certifications, i.dc_spd, i.ac_spd';
		$sql .= ' FROM '.$this->db->prefix().'powerplantpv_product_inverter as i';
		$sql .= ' INNER JOIN '.$this->db->prefix().'product as p ON p.rowid = i.fk_product AND p.entity = i.entity AND p.fk_product_type = 0';
		$sql .= ' WHERE i.entity = '.((int) $entity);
		$resql = $this->db->query($sql);
		if (!$resql) {
			return $this->setDatabaseError();
		}
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$groups = array(
				array(PowerPlantPVProductDictionary::TYPE_COMMUNICATION_PROTOCOL, (string) $obj->communication_interfaces, ''),
				array(PowerPlantPVProductDictionary::TYPE_CERTIFICATION, (string) $obj->certifications, ''),
				array(PowerPlantPVProductDictionary::TYPE_PROTECTION, (string) $obj->dc_spd, 'DC'),
				array(PowerPlantPVProductDictionary::TYPE_PROTECTION, (string) $obj->ac_spd, 'AC'),
			);
			foreach ($groups as $group) {
				foreach ($this->splitValues($group[1]) as $label) {
					$preferredCode = $this->normalizeKnownCode($group[0], $label, $group[2]);
					$id = $this->findOrCreateEntry($group[0], $preferredCode, $label, $entity);
					if ($id <= 0 || $this->insertLink((int) $obj->fk_product, $group[0], $id, $entity, $userId) < 0) {
						$this->db->free($resql);
						return -1;
					}
				}
			}
		}
		$this->db->free($resql);
		return 1;
	}

	/** @return array<int,string> */
	private function splitValues($value)
	{
		$values = preg_split('/[\r\n,;]+/u', trim((string) $value));
		if (!is_array($values)) {
			return array();
		}
		return array_values(array_filter(array_map('trim', $values), static function ($item) {
			return $item !== '' && $item !== '-';
		}));
	}

	/** @return string */
	private function normalizeKnownCode($type, $label, $current)
	{
		$ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $label);
		$normalized = strtoupper((string) preg_replace('/[^A-Z0-9]+/', '_', is_string($ascii) ? $ascii : (string) $label));
		$normalized = trim($normalized, '_');
		if ($type === PowerPlantPVProductDictionary::TYPE_COMMUNICATION_PROTOCOL) {
			$aliases = array(
				'MODBUS' => 'MODBUS_RTU', 'MODBUS_RTU' => 'MODBUS_RTU', 'MODBUS_TCP' => 'MODBUS_TCP',
				'SUNSPEC' => 'SUNSPEC_MODBUS', 'SUNSPEC_MODBUS' => 'SUNSPEC_MODBUS', 'RS_485' => 'RS485',
				'WI_FI' => 'WIFI', 'REST' => 'REST_API', 'API_REST' => 'REST_API',
			);
			return isset($aliases[$normalized]) ? $aliases[$normalized] : $normalized;
		}
		if ($type === PowerPlantPVProductDictionary::TYPE_PROTECTION && $current !== '') {
			if (strpos($normalized, 'TYPE_1') !== false || preg_match('/(^|_)1($|_)/', $normalized)) {
				return 'SPD_'.$current.'_TYPE_1';
			}
			if (strpos($normalized, 'TYPE_2') !== false || preg_match('/(^|_)2($|_)/', $normalized)) {
				return 'SPD_'.$current.'_TYPE_2';
			}
		}
		return $normalized;
	}

	/** @return int */
	private function findOrCreateEntry($type, $preferredCode, $label, $entity)
	{
		$definitions = PowerPlantPVProductDictionary::getDefinitions();
		if (!isset($definitions[$type])) {
			return -1;
		}
		$definition = $definitions[$type];
		$code = strtoupper(trim((string) $preferredCode));
		$code = trim((string) preg_replace('/[^A-Z0-9_]+/', '_', $code), '_');
		$known = (new PowerPlantPVProductDictionary($this->db))->fetchCodeMap($type, $entity, true);
		$legacyLabel = trim((string) $label) !== '' ? trim((string) $label) : trim((string) $preferredCode);
		if ($code === '' || !isset($known[$code])) {
			$code = 'LEGACY_'.strtoupper(substr(sha1($type.'|'.trim((string) $preferredCode).'|'.trim((string) $label)), 0, 16));
		}
		if (isset($known[$code])) {
			return (int) $known[$code]['id'];
		}

		$displayLabel = $legacyLabel !== '' ? $legacyLabel : str_replace('_', ' ', $code);
		$sql = 'INSERT INTO '.$this->db->prefix().$definition['dictionary'];
		$sql .= ' (entity, code, label, description, active, position) VALUES (';
		$sql .= ((int) $entity).", '".$this->db->escape($code)."', '".$this->db->escape($displayLabel)."', NULL, 1, 9000)";
		if (!$this->db->query($sql)) {
			return $this->setDatabaseError();
		}
		return (int) $this->db->last_insert_id($this->db->prefix().$definition['dictionary']);
	}

	/** @return int */
	private function insertLink($productId, $type, $dictionaryId, $entity, $userId)
	{
		$definitions = PowerPlantPVProductDictionary::getDefinitions();
		if (!isset($definitions[$type])) {
			return -1;
		}
		$definition = $definitions[$type];
		$sql = 'INSERT INTO '.$this->db->prefix().$definition['link'].' (entity, fk_product, '.$definition['fk'].', date_creation, fk_user_creat)';
		$sql .= ' SELECT '.((int) $entity).', '.((int) $productId).', '.((int) $dictionaryId).", '".$this->db->idate(dol_now())."', ".((int) $userId);
		$sql .= ' WHERE NOT EXISTS (SELECT 1 FROM '.$this->db->prefix().$definition['link'];
		$sql .= ' WHERE entity = '.((int) $entity).' AND fk_product = '.((int) $productId);
		$sql .= ' AND '.$definition['fk'].' = '.((int) $dictionaryId).')';
		if (!$this->db->query($sql)) {
			return $this->setDatabaseError();
		}
		return 1;
	}

	/** @return int */
	private function setDatabaseError()
	{
		$this->error = $this->db->lasterror();
		$this->errors[] = $this->error;
		return -1;
	}
}
