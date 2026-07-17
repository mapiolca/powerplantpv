<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		class/powerplantpvequipmentstring.class.php
 * \ingroup		powerplantpv
 * \brief		Installed inverter string configuration object.
 */

dol_include_once('/powerplantpv/class/powerplantpvreportgeneratedbase.class.php');

/**
 * Installed inverter string configuration object.
 */
class PowerPlantPVEquipmentString extends PowerPlantPVReportGeneratedBase
{
	public $element = 'powerplantpv_equipment_string';
	public $table_element = 'powerplantpv_equipment_string';
	public $picto = 'fa-plug';

	/**
	 * @var array<string,array<string,mixed>> Fields
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => -2),
		'fk_powerplant' => array('type' => 'integer', 'label' => 'PowerPlant', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1),
		'fk_inverter' => array('type' => 'integer', 'label' => 'PowerPlantPVInverter', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1),
		'mppt_number' => array('type' => 'integer', 'label' => 'PowerPlantPVMPPT', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1),
		'pv_input_number' => array('type' => 'integer', 'label' => 'PowerPlantPVPVInput', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'visible' => 1),
		'string_ref' => array('type' => 'varchar(128)', 'label' => 'PowerPlantPVStringRef', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'module_count' => array('type' => 'integer', 'label' => 'PowerPlantPVModuleCount', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 1),
		'module_power' => array('type' => 'double(24,8)', 'label' => 'PowerPlantPVModulePower', 'enabled' => 1, 'position' => 70, 'notnull' => 0, 'visible' => 1),
		'orientation' => array('type' => 'varchar(64)', 'label' => 'PowerPlantPVOrientation', 'enabled' => 1, 'position' => 80, 'notnull' => 0, 'visible' => 1),
		'tilt' => array('type' => 'double(24,8)', 'label' => 'PowerPlantPVTilt', 'enabled' => 1, 'position' => 90, 'notnull' => 0, 'visible' => 1),
		'is_connected' => array('type' => 'smallint', 'label' => 'PowerPlantPVPVInputConnected', 'enabled' => 1, 'position' => 100, 'notnull' => 1, 'visible' => 1, 'default' => 1),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => 1, 'position' => 110, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 0, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => 0, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => 0, 'visible' => -2),
	);

	public $fk_powerplant;
	public $fk_inverter;
	public $mppt_number;
	public $pv_input_number;
	public $string_ref;
	public $module_count;
	public $module_power;
	public $orientation;
	public $tilt;
	public $is_connected;
	public $position;

	/**
	 * Fetch string rows for one installed inverter.
	 *
	 * @param	int	$fk_powerplant	Power plant id
	 * @param	int	$fk_inverter	Installed inverter line id
	 * @return	array<int,PowerPlantPVEquipmentString>|int	Rows or <0 on error
	 */
	public function fetchAllByInverter($fk_powerplant, $fk_inverter)
	{
		$sql = "SELECT ".$this->getSelectFieldList();
		$sql .= " FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE t.fk_powerplant = ".((int) $fk_powerplant);
		$sql .= " AND t.fk_inverter = ".((int) $fk_inverter);
		$sql .= " AND t.entity IN (".$this->db->sanitize(getEntity('powerplant')).")";
		$sql .= " ORDER BY t.position ASC, t.mppt_number ASC, t.pv_input_number ASC, t.rowid ASC";

		return $this->fetchRowsFromSql($sql);
	}
}
