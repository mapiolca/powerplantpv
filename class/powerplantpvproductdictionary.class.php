<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Entity-aware service for product technical dictionaries.
 */
class PowerPlantPVProductDictionary
{
	public const TYPE_COMMUNICATION_PROTOCOL = 'communication_protocol';
	public const TYPE_CERTIFICATION = 'certification';
	public const TYPE_PROTECTION = 'protection';

	/** @var DoliDB */
	private $db;

	/** @var string */
	public $error = '';

	/** @var array<int,string> */
	public $errors = array();

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * @return array<string,array{dictionary:string,link:string,fk:string,label:string}>
	 */
	public static function getDefinitions()
	{
		return array(
			self::TYPE_COMMUNICATION_PROTOCOL => array(
				'dictionary' => 'c_powerplantpv_communication_protocol',
				'link' => 'powerplantpv_product_communication_protocol',
				'fk' => 'fk_communication_protocol',
				'label' => 'PVTechnicalCommunicationProtocols',
			),
			self::TYPE_CERTIFICATION => array(
				'dictionary' => 'c_powerplantpv_certification',
				'link' => 'powerplantpv_product_certification',
				'fk' => 'fk_certification',
				'label' => 'PVTechnicalCertifications',
			),
			self::TYPE_PROTECTION => array(
				'dictionary' => 'c_powerplantpv_protection',
				'link' => 'powerplantpv_product_protection',
				'fk' => 'fk_protection',
				'label' => 'PVTechnicalProtections',
			),
		);
	}

	/**
	 * @param string $type Dictionary type
	 * @return array{dictionary:string,link:string,fk:string,label:string}|null
	 */
	private function getDefinition($type)
	{
		$definitions = self::getDefinitions();
		return isset($definitions[$type]) ? $definitions[$type] : null;
	}

	/**
	 * Return the selected dictionary identifiers.
	 *
	 * @param int $productId Product identifier
	 * @param string $type Dictionary type
	 * @param int $entity Product owner entity
	 * @return array<int,int>
	 */
	public function fetchSelectedIds($productId, $type, $entity)
	{
		$definition = $this->getDefinition($type);
		if ($definition === null || $productId <= 0 || $entity <= 0) {
			return array();
		}

		$sql = 'SELECT l.'.$definition['fk'].' as dictionary_id';
		$sql .= ' FROM '.$this->db->prefix().$definition['link'].' as l';
		$sql .= ' INNER JOIN '.$this->db->prefix().$definition['dictionary'].' as d ON d.rowid = l.'.$definition['fk'];
		$sql .= ' WHERE l.entity = '.((int) $entity);
		$sql .= ' AND d.entity = '.((int) $entity);
		$sql .= ' AND l.fk_product = '.((int) $productId);
		$sql .= ' ORDER BY d.position ASC, d.label ASC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setDatabaseError();
			return array();
		}

		$ids = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$ids[] = (int) $obj->dictionary_id;
		}
		$this->db->free($resql);
		return $ids;
	}

	/**
	 * Return Select2 options. Inactive selected values remain visible.
	 *
	 * @param string $type Dictionary type
	 * @param int $entity Product owner entity
	 * @param array<int,int> $selectedIds Selected identifiers
	 * @return array<int,string>
	 */
	public function fetchOptions($type, $entity, array $selectedIds = array())
	{
		global $langs;

		$definition = $this->getDefinition($type);
		if ($definition === null || $entity <= 0) {
			return array();
		}

		$sql = 'SELECT d.rowid, d.code, d.label, d.active';
		$sql .= ' FROM '.$this->db->prefix().$definition['dictionary'].' as d';
		$sql .= ' WHERE d.entity = '.((int) $entity);
		$sql .= ' AND (d.active = 1';
		if (!empty($selectedIds)) {
			$sql .= ' OR d.rowid IN ('.implode(',', array_map('intval', $selectedIds)).')';
		}
		$sql .= ') ORDER BY d.position ASC, d.label ASC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setDatabaseError();
			return array();
		}

		$options = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$label = trim((string) $obj->label) !== '' ? (string) $obj->label : (string) $obj->code;
			if (empty($obj->active)) {
				$label .= ' ['.(string) $obj->code.' - '.$langs->trans('Disabled').']';
			}
			$options[(int) $obj->rowid] = $label;
		}
		$this->db->free($resql);
		return $options;
	}

	/**
	 * Return active dictionary rows keyed by normalized code.
	 *
	 * @param string $type Dictionary type
	 * @param int $entity Entity identifier
	 * @param bool $includeInactive Include inactive rows
	 * @return array<string,array{id:int,code:string,label:string,active:int}>
	 */
	public function fetchCodeMap($type, $entity, $includeInactive = false)
	{
		$definition = $this->getDefinition($type);
		if ($definition === null || $entity <= 0) {
			return array();
		}

		$sql = 'SELECT d.rowid, d.code, d.label, d.active FROM '.$this->db->prefix().$definition['dictionary'].' as d';
		$sql .= ' WHERE d.entity = '.((int) $entity);
		if (!$includeInactive) {
			$sql .= ' AND d.active = 1';
		}
		$sql .= ' ORDER BY d.position ASC, d.label ASC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setDatabaseError();
			return array();
		}

		$map = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$code = strtoupper(trim((string) $obj->code));
			$map[$code] = array(
				'id' => (int) $obj->rowid,
				'code' => (string) $obj->code,
				'label' => (string) $obj->label,
				'active' => (int) $obj->active,
			);
		}
		$this->db->free($resql);
		return $map;
	}

	/**
	 * Resolve import values to active dictionary identifiers.
	 * The legacy CODE|Label format is accepted only when CODE exists.
	 *
	 * @param string $type Dictionary type
	 * @param array<int,string> $values Imported values
	 * @param int $entity Entity identifier
	 * @return array<int,int>|false
	 */
	public function resolveCodes($type, array $values, $entity)
	{
		$map = $this->fetchCodeMap($type, $entity, false);
		if ($this->error !== '') {
			return false;
		}

		$ids = array();
		foreach ($values as $value) {
			$parts = explode('|', trim((string) $value), 2);
			$code = strtoupper(trim($parts[0]));
			if ($code === '') {
				continue;
			}
			if (!isset($map[$code])) {
				$this->error = 'PowerPlantPVTechnicalDictionaryUnknownCode';
				$this->errors[] = $type.': '.$code;
				return false;
			}
			$ids[(int) $map[$code]['id']] = (int) $map[$code]['id'];
		}
		return array_values($ids);
	}

	/**
	 * Replace all selections for one product in one transaction.
	 *
	 * @param int $productId Product identifier
	 * @param int $entity Product owner entity
	 * @param array<string,array<int,int>> $selections Selections keyed by dictionary type
	 * @param User $user Acting user
	 * @return int 1 on success, -1 on error
	 */
	public function replaceSelections($productId, $entity, array $selections, $user)
	{
		$this->error = '';
		$this->errors = array();
		if (!$this->productBelongsToEntity($productId, $entity)) {
			$this->error = 'PowerPlantPVTechnicalDictionaryInvalidProduct';
			return -1;
		}

		$this->db->begin();
		foreach ($selections as $type => $ids) {
			if (!is_array($ids) || $this->replaceOne($productId, $entity, $type, $ids, $user) < 0) {
				$this->db->rollback();
				return -1;
			}
		}
		$this->db->commit();
		return 1;
	}

	/**
	 * Delete every technical dictionary link for a product.
	 *
	 * @param int $productId Product identifier
	 * @param int $entity Product owner entity
	 * @return int
	 */
	public function deleteForProduct($productId, $entity)
	{
		foreach (self::getDefinitions() as $definition) {
			$sql = 'DELETE FROM '.$this->db->prefix().$definition['link'];
			$sql .= ' WHERE entity = '.((int) $entity).' AND fk_product = '.((int) $productId);
			if (!$this->db->query($sql)) {
				$this->setDatabaseError();
				return -1;
			}
		}
		return 1;
	}

	/**
	 * Seed the built-in values without overwriting administrator changes.
	 *
	 * @param Translate $langs Language handler
	 * @param int $entity Entity identifier
	 * @return int
	 */
	public function seedDefaults($langs, $entity)
	{
		$rows = array(
			self::TYPE_COMMUNICATION_PROTOCOL => array('MODBUS_RTU', 'MODBUS_TCP', 'SUNSPEC_MODBUS', 'CAN', 'CANOPEN', 'RS485', 'ETHERNET', 'WIFI', 'BLUETOOTH', 'ZIGBEE', 'MQTT', 'REST_API'),
			self::TYPE_CERTIFICATION => array('CE', 'IEC_61215', 'IEC_61730', 'IEC_62109_1', 'IEC_62109_2', 'IEC_62619', 'IEC_63056', 'UN_38_3', 'EN_50549_1', 'VDE_AR_N_4105', 'UL_1741', 'UL_9540', 'UL_9540A'),
			self::TYPE_PROTECTION => array('SPD_DC_TYPE_1', 'SPD_DC_TYPE_2', 'SPD_AC_TYPE_1', 'SPD_AC_TYPE_2', 'OVERVOLTAGE', 'UNDERVOLTAGE', 'OVERCURRENT', 'SHORT_CIRCUIT', 'OVERTEMPERATURE', 'OVERLOAD', 'OVERCHARGE', 'DEEP_DISCHARGE', 'GROUND_FAULT', 'EMERGENCY_SHUTDOWN'),
		);

		foreach ($rows as $type => $codes) {
			$definition = $this->getDefinition($type);
			if ($definition === null) {
				continue;
			}
			$position = 10;
			foreach ($codes as $code) {
				$labelKey = 'PVTechnicalDictionaryValue'.str_replace('_', '', ucwords(strtolower($code), '_'));
				$label = $langs->transnoentitiesnoconv($labelKey);
				if ($label === $labelKey) {
					$label = str_replace('_', ' ', $code);
				}
				$sql = 'INSERT INTO '.$this->db->prefix().$definition['dictionary'].' (entity, code, label, description, active, position)';
				$sql .= ' SELECT '.((int) $entity).", '".$this->db->escape($code)."', '".$this->db->escape($label)."', NULL, 1, ".((int) $position);
				$sql .= ' WHERE NOT EXISTS (SELECT 1 FROM '.$this->db->prefix().$definition['dictionary'];
				$sql .= ' WHERE entity = '.((int) $entity)." AND code = '".$this->db->escape($code)."')";
				if (!$this->db->query($sql)) {
					$this->setDatabaseError();
					return -1;
				}
				$position += 10;
			}
		}
		return 1;
	}

	/**
	 * @param int $productId Product identifier
	 * @param int $entity Entity identifier
	 * @param string $type Dictionary type
	 * @param array<int,int> $ids Dictionary identifiers
	 * @param User $user Acting user
	 * @return int
	 */
	private function replaceOne($productId, $entity, $type, array $ids, $user)
	{
		$definition = $this->getDefinition($type);
		if ($definition === null) {
			$this->error = 'PowerPlantPVTechnicalDictionaryInvalidType';
			return -1;
		}

		$currentIds = $this->fetchSelectedIds($productId, $type, $entity);
		if ($this->error !== '') {
			return -1;
		}
		$cleanIds = array_values(array_unique(array_filter(array_map('intval', $ids))));
		if (!empty($cleanIds)) {
			$sql = 'SELECT rowid, active FROM '.$this->db->prefix().$definition['dictionary'];
			$sql .= ' WHERE entity = '.((int) $entity).' AND rowid IN ('.implode(',', $cleanIds).')';
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->setDatabaseError();
				return -1;
			}
			$validIds = array();
			while (is_object($obj = $this->db->fetch_object($resql))) {
				$id = (int) $obj->rowid;
				if (!empty($obj->active) || in_array($id, $currentIds, true)) {
					$validIds[$id] = $id;
				}
			}
			$this->db->free($resql);
			if (count($validIds) !== count($cleanIds)) {
				$this->error = 'PowerPlantPVTechnicalDictionaryInvalidSelection';
				return -1;
			}
		}

		$sql = 'DELETE FROM '.$this->db->prefix().$definition['link'];
		$sql .= ' WHERE entity = '.((int) $entity).' AND fk_product = '.((int) $productId);
		if (!$this->db->query($sql)) {
			$this->setDatabaseError();
			return -1;
		}

		foreach ($cleanIds as $id) {
			$sql = 'INSERT INTO '.$this->db->prefix().$definition['link'].' (';
			$sql .= 'entity, fk_product, '.$definition['fk'].', date_creation, fk_user_creat';
			$sql .= ') VALUES (';
			$sql .= ((int) $entity).', '.((int) $productId).', '.((int) $id).', ';
			$sql .= "'".$this->db->idate(dol_now())."', ".((int) $user->id).')';
			if (!$this->db->query($sql)) {
				$this->setDatabaseError();
				return -1;
			}
		}
		return 1;
	}

	/**
	 * @param int $productId Product identifier
	 * @param int $entity Entity identifier
	 * @return bool
	 */
	private function productBelongsToEntity($productId, $entity)
	{
		$sql = 'SELECT rowid FROM '.$this->db->prefix().'product';
		$sql .= ' WHERE rowid = '.((int) $productId).' AND entity = '.((int) $entity).' AND fk_product_type = 0';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setDatabaseError();
			return false;
		}
		$found = $this->db->num_rows($resql) === 1;
		$this->db->free($resql);
		return $found;
	}

	/**
	 * Store the current database error.
	 *
	 * @return void
	 */
	private function setDatabaseError()
	{
		$this->error = $this->db->lasterror();
		$this->errors[] = $this->error;
	}
}
