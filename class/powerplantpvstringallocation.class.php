<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/** Installed module stock and string allocation. Caller authorizes and locks the plant. */
class PowerPlantPVStringAllocation
{
	/** @var DoliDB */
	private $db;
	/** @var int */
	private $plant;
	/** @var int */
	private $entity;
	/** @var string */
	public $error = '';

	/** @param DoliDB $db Database @param int $plant Plant @param int $entity Owner */
	public function __construct($db, $plant, $entity) { $this->db = $db; $this->plant = (int) $plant; $this->entity = (int) $entity; }

	/** @return array<int,array{label:string,qty:float,power:?float}>|null Null on database error */
	public function modules(bool $lock = false)
	{
		$sql = 'SELECT c.fk_product, SUM(c.qty) as qty, p.ref, p.label, panel.pmax';
		$sql .= ' FROM '.$this->db->prefix().'powerplantpv_powerplantcomp c';
		$sql .= ' INNER JOIN '.$this->db->prefix().'product p ON p.rowid = c.fk_product AND p.entity IN ('.$this->db->sanitize(getEntity('product')).')';
		$sql .= ' INNER JOIN '.$this->db->prefix().'product_extrafields pe ON pe.fk_object = p.rowid';
		$sql .= ' INNER JOIN '.$this->db->prefix().'c_powerplantpv_categorypv cat ON cat.rowid = pe.categorie_photovoltaique AND cat.code = \'MODULE\'';
		$sql .= ' LEFT JOIN '.$this->db->prefix().'powerplantpv_product_pvpanel panel ON panel.fk_product = p.rowid AND panel.entity = p.entity';
		$sql .= ' WHERE c.fk_powerplant = '.$this->plant.' AND c.entity = '.$this->entity.' AND c.fk_status = 4';
		$sql .= ' GROUP BY c.fk_product, p.ref, p.label, panel.pmax ORDER BY p.ref';
		if ($lock) {
			$sql .= ' FOR UPDATE';
		}
		$result = $this->db->query($sql);
		if (!$result) { $this->error = $this->db->lasterror(); return null; }
		$modules = array();
		while ($row = $this->db->fetch_object($result)) {
			$modules[(int) $row->fk_product] = array('label' => $row->ref.' — '.$row->label, 'qty' => (float) $row->qty, 'power' => $row->pmax === null ? null : (float) $row->pmax);
		}
		return $modules;
	}

	/**
	 * @param int $inverter Inverter whose current input is excluded
	 * @param int $mppt Current MPPT (zero excludes nothing)
	 * @param int $input Current input
	 * @return array{total:int,inverter:int,products:array<int,int>}|null
	 */
	public function connected($inverter, $mppt = 0, $input = 0, bool $lock = false)
	{
		$sql = 'SELECT fk_inverter, fk_product, module_count FROM '.$this->db->prefix().'powerplantpv_equipment_string WHERE fk_powerplant = '.$this->plant.' AND entity = '.$this->entity.' AND is_connected = 1';
		if ($mppt > 0 && $input > 0) { $sql .= ' AND NOT (fk_inverter = '.(int) $inverter.' AND mppt_number = '.(int) $mppt.' AND pv_input_number = '.(int) $input.')'; }
		if ($lock) {
			$sql .= ' FOR UPDATE';
		}
		$result = $this->db->query($sql);
		if (!$result) { $this->error = $this->db->lasterror(); return null; }
		$counts = array('total' => 0, 'inverter' => 0, 'products' => array());
		while ($row = $this->db->fetch_object($result)) {
			$n = max(0, (int) $row->module_count);
			$counts['total'] += $n;
			if ((int) $row->fk_inverter === (int) $inverter) { $counts['inverter'] += $n; }
			$id = (int) $row->fk_product;
			$counts['products'][$id] = ($counts['products'][$id] ?? 0) + $n;
		}
		return $counts;
	}

	/**
	 * @param int $product Selected composition product, zero for historical manual power
	 * @param int $count Module count
	 * @param int $connected Whether the input is connected
	 * @param int $inverter Installed inverter
	 * @param int $mppt MPPT
	 * @param int $input Input
	 * @return bool Valid allocation; call inside parent-lock transaction
	 */
	public function validate($product, $count, $connected, $inverter, $mppt, $input)
	{
		$modules = $this->modules();
		$used = $this->connected($inverter, $mppt, $input);
		if ($modules === null || $used === null) { return false; }
		if ($count < 0 || ($product > 0 && !isset($modules[$product]))) { $this->error = 'PowerPlantPVInvalidModuleSelection'; return false; }
		if ($connected && ($used['total'] + $count > array_sum(array_column($modules, 'qty')) || ($product > 0 && ($used['products'][$product] ?? 0) + $count > $modules[$product]['qty']))) {
			$this->error = 'PowerPlantPVModuleAllocationExceeded'; return false;
		}
		return true;
	}
}
