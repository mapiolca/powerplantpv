<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		class/powerplantpvreportsection.class.php
 * \ingroup		powerplantpv
 * \brief		Generated report section snapshot object.
 */

dol_include_once('/powerplantpv/class/powerplantpvreportgeneratedbase.class.php');

/**
 * Generated report section snapshot object.
 */
class PowerPlantPVReportSection extends PowerPlantPVReportGeneratedBase
{
	public $element = 'powerplantpv_report_section';
	public $table_element = 'powerplantpv_report_section';

	/**
	 * @var array<string,array<string,mixed>> Fields
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => -2),
		'fk_report' => array('type' => 'integer:PowerPlantPVReport:powerplantpv/class/powerplantpvreport.class.php', 'label' => 'PowerPlantPVReport', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1),
		'fk_report_powerplant' => array('type' => 'integer:PowerPlantPVReportPowerPlant:powerplantpv/class/powerplantpvreportpowerplant.class.php', 'label' => 'PowerPlant', 'enabled' => 1, 'position' => 20, 'notnull' => 0, 'visible' => 1),
		'fk_report_equipment' => array('type' => 'integer:PowerPlantPVReportEquipment:powerplantpv/class/powerplantpvreportequipment.class.php', 'label' => 'PowerPlantPVEquipment', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 1),
		'fk_report_template_section' => array('type' => 'integer', 'label' => 'PowerPlantPVReportSection', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1),
		'section_code' => array('type' => 'varchar(64)', 'label' => 'Code', 'enabled' => 1, 'position' => 50, 'notnull' => 1, 'visible' => 1),
		'section_label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 60, 'notnull' => 1, 'visible' => 1),
		'section_label_en' => array('type' => 'varchar(255)', 'label' => 'PowerPlantPVEnglishLabel', 'enabled' => 1, 'position' => 61, 'notnull' => 0, 'visible' => 1),
		'section_description' => array('type' => 'text', 'label' => 'Description', 'enabled' => 1, 'position' => 70, 'notnull' => 0, 'visible' => 1),
		'section_description_en' => array('type' => 'text', 'label' => 'PowerPlantPVEnglishDescription', 'enabled' => 1, 'position' => 71, 'notnull' => 0, 'visible' => 1),
		'scope_type' => array('type' => 'varchar(32)', 'label' => 'PowerPlantPVReportScope', 'enabled' => 1, 'position' => 80, 'notnull' => 1, 'visible' => 1),
		'equipment_type' => array('type' => 'varchar(32)', 'label' => 'PowerPlantPVEquipmentType', 'enabled' => 1, 'position' => 90, 'notnull' => 0, 'visible' => 1),
		'repeat_mode' => array('type' => 'varchar(32)', 'label' => 'PowerPlantPVRepeatMode', 'enabled' => 1, 'position' => 100, 'notnull' => 1, 'visible' => 1),
		'occurrence_key' => array('type' => 'varchar(255)', 'label' => 'Code', 'enabled' => 1, 'position' => 110, 'notnull' => 1, 'visible' => 1),
		'is_required' => array('type' => 'smallint', 'label' => 'Required', 'enabled' => 1, 'position' => 120, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'visible_form' => array('type' => 'smallint', 'label' => 'PowerPlantPVVisibleForm', 'enabled' => 1, 'position' => 130, 'notnull' => 1, 'visible' => 1, 'default' => 1),
		'visible_pdf' => array('type' => 'smallint', 'label' => 'PowerPlantPVVisiblePdf', 'enabled' => 1, 'position' => 140, 'notnull' => 1, 'visible' => 1, 'default' => 1),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => 1, 'position' => 150, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 0, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => 0, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => 0, 'visible' => -2),
	);

	public $fk_report;
	public $fk_report_powerplant;
	public $fk_report_equipment;
	public $fk_report_template_section;
	public $section_code;
	public $section_label;
	public $section_label_en;
	public $section_description;
	public $section_description_en;
	public $scope_type;
	public $equipment_type;
	public $repeat_mode;
	public $occurrence_key;
	public $is_required;
	public $visible_form;
	public $visible_pdf;
	public $position;
}
