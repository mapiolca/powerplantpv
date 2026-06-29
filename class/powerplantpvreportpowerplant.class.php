<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		class/powerplantpvreportpowerplant.class.php
 * \ingroup		powerplantpv
 * \brief		Generated report power plant snapshot object.
 */

dol_include_once('/powerplantpv/class/powerplantpvreportgeneratedbase.class.php');

/**
 * Generated report power plant snapshot object.
 */
class PowerPlantPVReportPowerPlant extends PowerPlantPVReportGeneratedBase
{
	public $element = 'powerplantpv_report_powerplant';
	public $table_element = 'powerplantpv_report_powerplant';

	/**
	 * @var array<string,array<string,mixed>> Fields
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => -2),
		'fk_report' => array('type' => 'integer:PowerPlantPVReport:powerplantpv/class/powerplantpvreport.class.php', 'label' => 'PowerPlantPVReport', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1),
		'fk_powerplant' => array('type' => 'integer:PowerPlant:powerplantpv/class/powerplant.class.php', 'label' => 'PowerPlant', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1),
		'powerplant_ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 1),
		'powerplant_label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1),
		'fk_soc' => array('type' => 'integer', 'label' => 'ThirdParty', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'fk_project' => array('type' => 'integer', 'label' => 'Project', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 1),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => 1, 'position' => 70, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 0, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => 0, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => 0, 'visible' => -2),
	);

	public $fk_report;
	public $fk_powerplant;
	public $powerplant_ref;
	public $powerplant_label;
	public $fk_soc;
	public $fk_project;
	public $position;
}
