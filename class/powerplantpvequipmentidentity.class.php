<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/** Installed identity orchestration. The caller checks permissions, access and CSRF. */
class PowerPlantPVEquipmentIdentity
{
	/** @var DoliDB */
	private $db;
	/** @var string */
	public $error = '';
	/** @var list<int> Equipment changed by the current operation */
	public $changedEquipmentIds = array();

	/** @param DoliDB $db Native database handler */
	public function __construct($db) { $this->db = $db; }

	/**
	 * Optimistic revision of all values edited together in the component dialog.
	 * @param object $line Loaded installed component
	 * @return string Non-secret revision fingerprint
	 */
	public static function revision($line)
	{
		$values = array();
		foreach (array('equipment_marker', 'equipment_identification', 'serial_number', 'fk_status', 'commissioning_date') as $field) {
			$values[] = (string) ($line->{$field} ?? '');
		}
		return hash('sha256', serialize($values));
	}

	/**
	 * Update a named physical inverter inside the caller's transaction.
	 * Lock the plant to serialize name allocation and interactive edits.
	 * @param int $plant Plant id
	 * @param int $entity Owner entity
	 * @param int $line Installed component id
	 * @param string $marker Requested marker, or empty for a new default
	 * @param string $name Identification, or empty for a new default
	 * @param bool $new Allocate defaults only for a new component
	 * @param string|null $revision Expected previous identity hash on interactive edits
	 * @param bool $completeMissing Allocate defaults for missing identity fields on an existing line
	 * @return int 1 on success, -1 on failure
	 */
	public function save($plant, $entity, $line, $marker, $name, $new = false, $revision = null, $completeMissing = false)
	{
		global $langs;
		$marker = trim($marker);
		$name = trim($name);
		$lock = $this->db->query('SELECT rowid FROM '.$this->db->prefix().'powerplantpv_powerplant WHERE rowid = '.(int) $plant.' AND entity = '.(int) $entity.' FOR UPDATE');
		if (!$lock || !$this->db->fetch_object($lock)) {
			$this->error = 'ErrorRecordNotFound';
			return -1;
		}
		$sql = 'SELECT c.rowid, c.qty, c.equipment_marker, c.equipment_identification, c.serial_number, c.fk_status, c.commissioning_date, inv.rowid as inverter_id, cat.code as category_code, p.ref as product_ref, p.label as product_label FROM '.$this->db->prefix().'powerplantpv_powerplantcomp c';
		$sql .= ' LEFT JOIN '.$this->db->prefix().'powerplantpv_product_inverter inv ON inv.fk_product = c.fk_product';
		$sql .= ' LEFT JOIN '.$this->db->prefix().'product p ON p.rowid = c.fk_product';
		$sql .= ' LEFT JOIN '.$this->db->prefix().'product_extrafields pe ON pe.fk_object = c.fk_product';
		$sql .= ' LEFT JOIN '.$this->db->prefix().'c_powerplantpv_categorypv cat ON cat.rowid = pe.categorie_photovoltaique';
		$sql .= ' WHERE c.fk_powerplant = '.(int) $plant.' AND c.entity = '.(int) $entity;
		$result = $this->db->query($sql);
		if (!$result) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$used = array();
		$current = null;
		while ($row = $this->db->fetch_object($result)) {
			if ((int) $row->rowid === (int) $line) {
				$current = $row;
			} elseif (trim((string) $row->equipment_marker) !== '') {
				$used[mb_strtolower(trim((string) $row->equipment_marker), 'UTF-8')] = true;
			}
		}
		if (!$current) {
			$this->error = 'ErrorRecordNotFound';
			return -1;
		}
		if (!$new && ($revision === null || !hash_equals(self::revision($current), $revision))) {
			$this->error = 'PowerPlantPVIdentityConcurrentChange';
			return -1;
		}
		$category = (string) ($current->category_code ?? '');
		$isInverter = $category === 'ONDULE' || ($category === '' && (!empty($current->inverter_id) || preg_match('/ONDULEUR|INVERTER/i', (string) ($current->product_ref ?? '').' '.(string) ($current->product_label ?? ''))));
		if (!$isInverter) {
			return 1;
		}
		if ($new || $completeMissing) {
			if ($marker === '') {
				require_once __DIR__.'/powerplantpvequipmentnumbering.class.php';
				$model = new PowerPlantPVEquipmentNumbering($this->db, 'inverter');
				$marker = $model->getNextValue((object) array('id' => $plant, 'entity' => $entity));
				if ($marker === '') { $this->error = $model->error; return -1; }
			}
			$name = $name !== '' ? $name : $langs->transnoentities('PowerPlantPVInverter').' '.$marker;
		}
		if ((float) $current->qty !== 1.0 || $marker === '' || $name === '' || mb_strlen($marker, 'UTF-8') > 32 || mb_strlen($name, 'UTF-8') > 255) {
			$this->error = 'PowerPlantPVIdentityRequired';
			return -1;
		}
		if (isset($used[mb_strtolower($marker, 'UTF-8')])) {
			$this->error = 'PowerPlantPVMarkerAlreadyUsed';
			return -1;
		}
		$sql = 'UPDATE '.$this->db->prefix().'powerplantpv_powerplantcomp SET equipment_marker = \''.$this->db->escape($marker).'\', equipment_identification = \''.$this->db->escape($name).'\'';
		$sql .= ' WHERE rowid = '.(int) $line.' AND fk_powerplant = '.(int) $plant.' AND entity = '.(int) $entity;
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		if ((string) $current->equipment_marker !== $marker || (string) $current->equipment_identification !== $name) { $this->changedEquipmentIds[] = (int) $line; }
		return 1;
	}
}
