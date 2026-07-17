<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		class/powerplantpvequipmentmppt.class.php
 * \ingroup		powerplantpv
 * \brief		Installed inverter MPPT configuration object.
 */

dol_include_once('/powerplantpv/class/powerplantpvreportgeneratedbase.class.php');

/**
 * Installed inverter MPPT configuration object.
 */
class PowerPlantPVEquipmentMppt extends PowerPlantPVReportGeneratedBase
{
	public $element = 'powerplantpv_equipment_mppt';
	public $table_element = 'powerplantpv_equipment_mppt';
	public $picto = 'fa-bolt';

	/**
	 * @var array<string,array<string,mixed>> Fields
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => -2),
		'fk_powerplant' => array('type' => 'integer', 'label' => 'PowerPlant', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1),
		'fk_inverter' => array('type' => 'integer', 'label' => 'PowerPlantPVInverter', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1),
		'mppt_number' => array('type' => 'integer', 'label' => 'PowerPlantPVMPPT', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1),
		'pv_input_count' => array('type' => 'integer', 'label' => 'PowerPlantPVPVInputCount', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => 1, 'position' => 50, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 0, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => 0, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => 0, 'visible' => -2),
	);

	public $fk_powerplant;
	public $fk_inverter;
	public $mppt_number;
	public $pv_input_count;
	public $position;

	/**
	 * Fetch MPPT rows for one installed inverter.
	 *
	 * @param	int	$fk_powerplant	Power plant id
	 * @param	int	$fk_inverter	Installed inverter line id
	 * @return	array<int,PowerPlantPVEquipmentMppt>|int	Rows or <0 on error
	 */
	public function fetchAllByInverter($fk_powerplant, $fk_inverter)
	{
		$sql = "SELECT ".$this->getSelectFieldList();
		$sql .= " FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE t.fk_powerplant = ".((int) $fk_powerplant);
		$sql .= " AND t.fk_inverter = ".((int) $fk_inverter);
		$sql .= " AND t.entity IN (".$this->db->sanitize(getEntity('powerplant')).")";
		$sql .= " ORDER BY t.position ASC, t.mppt_number ASC, t.rowid ASC";

		return $this->fetchRowsFromSql($sql);
	}
}
