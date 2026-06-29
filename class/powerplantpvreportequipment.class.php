<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		class/powerplantpvreportequipment.class.php
 * \ingroup		powerplantpv
 * \brief		Generated report equipment snapshot object.
 */

dol_include_once('/powerplantpv/class/powerplantpvreportgeneratedbase.class.php');

/**
 * Generated report equipment snapshot object.
 */
class PowerPlantPVReportEquipment extends PowerPlantPVReportGeneratedBase
{
	public $element = 'powerplantpv_report_equipment';
	public $table_element = 'powerplantpv_report_equipment';

	/**
	 * @var array<string,array<string,mixed>> Fields
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => -2),
		'fk_report' => array('type' => 'integer:PowerPlantPVReport:powerplantpv/class/powerplantpvreport.class.php', 'label' => 'PowerPlantPVReport', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1),
		'fk_report_powerplant' => array('type' => 'integer:PowerPlantPVReportPowerPlant:powerplantpv/class/powerplantpvreportpowerplant.class.php', 'label' => 'PowerPlant', 'enabled' => 1, 'position' => 20, 'notnull' => 0, 'visible' => 1),
		'fk_powerplant' => array('type' => 'integer', 'label' => 'PowerPlant', 'enabled' => 1, 'position' => 21, 'notnull' => 0, 'visible' => 1),
		'fk_powerplant_line' => array('type' => 'integer', 'label' => 'PowerPlantPVEquipment', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 1),
		'fk_product' => array('type' => 'integer', 'label' => 'Product', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1),
		'product_ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'position' => 41, 'notnull' => 0, 'visible' => 1),
		'product_label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 42, 'notnull' => 0, 'visible' => 1),
		'equipment_type' => array('type' => 'varchar(32)', 'label' => 'PowerPlantPVEquipmentType', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'equipment_ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 1),
		'equipment_label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 61, 'notnull' => 0, 'visible' => 1),
		'serial_number' => array('type' => 'varchar(128)', 'label' => 'SerialNumber', 'enabled' => 1, 'position' => 70, 'notnull' => 0, 'visible' => 1),
		'qty' => array('type' => 'double(24,8)', 'label' => 'Qty', 'enabled' => 1, 'position' => 80, 'notnull' => 0, 'visible' => 1),
		'technical_key' => array('type' => 'varchar(255)', 'label' => 'Code', 'enabled' => 1, 'position' => 90, 'notnull' => 0, 'visible' => 1),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => 1, 'position' => 100, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 0, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => 0, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => 0, 'visible' => -2),
	);

	public $fk_report;
	public $fk_report_powerplant;
	public $fk_powerplant;
	public $fk_powerplant_line;
	public $fk_product;
	public $product_ref;
	public $product_label;
	public $equipment_type;
	public $equipment_ref;
	public $equipment_label;
	public $serial_number;
	public $qty;
	public $technical_key;
	public $position;
}
