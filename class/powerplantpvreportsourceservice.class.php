<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		class/powerplantpvreportsourceservice.class.php
 * \ingroup		powerplantpv
 * \brief		Generated report source service snapshot object.
 */

dol_include_once('/powerplantpv/class/powerplantpvreportgeneratedbase.class.php');

/**
 * Generated report source service snapshot object.
 */
class PowerPlantPVReportSourceService extends PowerPlantPVReportGeneratedBase
{
	public $element = 'powerplantpv_report_source_service';
	public $table_element = 'powerplantpv_report_source_service';

	/**
	 * @var array<string,array<string,mixed>> Fields
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => -2),
		'fk_report' => array('type' => 'integer:PowerPlantPVReport:powerplantpv/class/powerplantpvreport.class.php', 'label' => 'PowerPlantPVReport', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1),
		'fk_report_powerplant' => array('type' => 'integer:PowerPlantPVReportPowerPlant:powerplantpv/class/powerplantpvreportpowerplant.class.php', 'label' => 'PowerPlant', 'enabled' => 1, 'position' => 20, 'notnull' => 0, 'visible' => 1),
		'fk_powerplant' => array('type' => 'integer', 'label' => 'PowerPlant', 'enabled' => 1, 'position' => 21, 'notnull' => 0, 'visible' => 1),
		'fk_contract' => array('type' => 'integer', 'label' => 'Contract', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 1),
		'contract_ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'position' => 31, 'notnull' => 0, 'visible' => 1),
		'fk_contract_line' => array('type' => 'integer', 'label' => 'Service', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1),
		'fk_product' => array('type' => 'integer', 'label' => 'Product', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'product_ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'position' => 51, 'notnull' => 0, 'visible' => 1),
		'product_label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 52, 'notnull' => 0, 'visible' => 1),
		'fk_maintenance_service' => array('type' => 'integer', 'label' => 'MaintenanceServiceDictionary', 'enabled' => 1, 'position' => 60, 'notnull' => 1, 'visible' => 1),
		'maintenance_service_code' => array('type' => 'varchar(64)', 'label' => 'Code', 'enabled' => 1, 'position' => 61, 'notnull' => 0, 'visible' => 1),
		'maintenance_service_label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 62, 'notnull' => 0, 'visible' => 1),
		'maintenance_service_label_en' => array('type' => 'varchar(255)', 'label' => 'PowerPlantPVEnglishLabel', 'enabled' => 1, 'position' => 63, 'notnull' => 0, 'visible' => 1),
		'source_mode' => array('type' => 'varchar(16)', 'label' => 'PowerPlantPVReportSourceMode', 'enabled' => 1, 'position' => 70, 'notnull' => 1, 'visible' => 1, 'default' => 'contract'),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => 1, 'position' => 80, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 0, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => 0, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => 0, 'visible' => -2),
	);

	public $fk_report;
	public $fk_report_powerplant;
	public $fk_powerplant;
	public $fk_contract;
	public $contract_ref;
	public $fk_contract_line;
	public $fk_product;
	public $product_ref;
	public $product_label;
	public $fk_maintenance_service;
	public $maintenance_service_code;
	public $maintenance_service_label;
	public $maintenance_service_label_en;
	public $source_mode;
	public $position;
}
