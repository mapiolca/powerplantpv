<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		class/powerplantpvreportfield.class.php
 * \ingroup		powerplantpv
 * \brief		Generated report field snapshot object.
 */

dol_include_once('/powerplantpv/class/powerplantpvreportgeneratedbase.class.php');

/**
 * Generated report field snapshot object.
 */
class PowerPlantPVReportField extends PowerPlantPVReportGeneratedBase
{
	public $element = 'powerplantpv_report_field';
	public $table_element = 'powerplantpv_report_field';

	/**
	 * @var array<string,array<string,mixed>> Fields
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => -2),
		'fk_report' => array('type' => 'integer:PowerPlantPVReport:powerplantpv/class/powerplantpvreport.class.php', 'label' => 'PowerPlantPVReport', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1),
		'fk_report_section' => array('type' => 'integer:PowerPlantPVReportSection:powerplantpv/class/powerplantpvreportsection.class.php', 'label' => 'PowerPlantPVReportSection', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1),
		'fk_report_powerplant' => array('type' => 'integer:PowerPlantPVReportPowerPlant:powerplantpv/class/powerplantpvreportpowerplant.class.php', 'label' => 'PowerPlant', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 1),
		'fk_report_equipment' => array('type' => 'integer:PowerPlantPVReportEquipment:powerplantpv/class/powerplantpvreportequipment.class.php', 'label' => 'PowerPlantPVEquipment', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1),
		'fk_report_template_field' => array('type' => 'integer', 'label' => 'PowerPlantPVReportField', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'stable_key' => array('type' => 'varchar(255)', 'label' => 'Code', 'enabled' => 1, 'position' => 60, 'notnull' => 1, 'visible' => 1),
		'field_code' => array('type' => 'varchar(64)', 'label' => 'Code', 'enabled' => 1, 'position' => 70, 'notnull' => 1, 'visible' => 1),
		'field_label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 80, 'notnull' => 1, 'visible' => 1),
		'field_label_en' => array('type' => 'varchar(255)', 'label' => 'PowerPlantPVEnglishLabel', 'enabled' => 1, 'position' => 81, 'notnull' => 0, 'visible' => 1),
		'field_description' => array('type' => 'text', 'label' => 'Description', 'enabled' => 1, 'position' => 90, 'notnull' => 0, 'visible' => 1),
		'field_description_en' => array('type' => 'text', 'label' => 'PowerPlantPVEnglishDescription', 'enabled' => 1, 'position' => 91, 'notnull' => 0, 'visible' => 1),
		'field_type' => array('type' => 'varchar(32)', 'label' => 'Type', 'enabled' => 1, 'position' => 100, 'notnull' => 1, 'visible' => 1),
		'scope_type' => array('type' => 'varchar(32)', 'label' => 'PowerPlantPVReportScope', 'enabled' => 1, 'position' => 110, 'notnull' => 0, 'visible' => 1),
		'unit' => array('type' => 'varchar(32)', 'label' => 'Unit', 'enabled' => 1, 'position' => 120, 'notnull' => 0, 'visible' => 1),
		'default_value' => array('type' => 'text', 'label' => 'DefaultValue', 'enabled' => 1, 'position' => 130, 'notnull' => 0, 'visible' => 1),
		'placeholder' => array('type' => 'varchar(255)', 'label' => 'PowerPlantPVPlaceholder', 'enabled' => 1, 'position' => 140, 'notnull' => 0, 'visible' => 1),
		'help' => array('type' => 'text', 'label' => 'Help', 'enabled' => 1, 'position' => 150, 'notnull' => 0, 'visible' => 1),
		'options_snapshot' => array('type' => 'mediumtext', 'label' => 'PowerPlantPVReportOptionsSnapshot', 'enabled' => 1, 'position' => 160, 'notnull' => 0, 'visible' => 1),
		'value_text' => array('type' => 'mediumtext', 'label' => 'Value', 'enabled' => 1, 'position' => 170, 'notnull' => 0, 'visible' => 1),
		'value_number' => array('type' => 'double(24,8)', 'label' => 'Value', 'enabled' => 1, 'position' => 171, 'notnull' => 0, 'visible' => 1),
		'value_date' => array('type' => 'datetime', 'label' => 'Value', 'enabled' => 1, 'position' => 172, 'notnull' => 0, 'visible' => 1),
		'is_required' => array('type' => 'smallint', 'label' => 'Required', 'enabled' => 1, 'position' => 180, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'visible_form' => array('type' => 'smallint', 'label' => 'PowerPlantPVVisibleForm', 'enabled' => 1, 'position' => 190, 'notnull' => 1, 'visible' => 1, 'default' => 1),
		'visible_pdf' => array('type' => 'smallint', 'label' => 'PowerPlantPVVisiblePdf', 'enabled' => 1, 'position' => 200, 'notnull' => 1, 'visible' => 1, 'default' => 1),
		'readonly' => array('type' => 'smallint', 'label' => 'PowerPlantPVReadonly', 'enabled' => 1, 'position' => 210, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => 1, 'position' => 220, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 0, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => 0, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => 0, 'visible' => -2),
	);

	public $fk_report;
	public $fk_report_section;
	public $fk_report_powerplant;
	public $fk_report_equipment;
	public $fk_report_template_field;
	public $stable_key;
	public $field_code;
	public $field_label;
	public $field_label_en;
	public $field_description;
	public $field_description_en;
	public $field_type;
	public $scope_type;
	public $unit;
	public $default_value;
	public $placeholder;
	public $help;
	public $options_snapshot;
	public $value_text;
	public $value_number;
	public $value_date;
	public $is_required;
	public $visible_form;
	public $visible_pdf;
	public $readonly;
	public $position;

	/**
	 * Fetch field by stable key.
	 *
	 * @param	int		$reportId	Report id
	 * @param	string	$stableKey	Stable key
	 * @return	int					>0 if found, 0 if not found, <0 on error
	 */
	public function fetchByStableKey($reportId, $stableKey)
	{
		$reportId = (int) $reportId;
		$stableKey = (string) $stableKey;
		if ($reportId <= 0 || $stableKey === '') {
			return 0;
		}

		$sql = "SELECT rowid";
		$sql .= " FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE fk_report = ".$reportId;
		$sql .= " AND stable_key = '".$this->db->escape($stableKey)."'";
		$sql .= " ORDER BY rowid DESC";
		$sql .= " LIMIT 1";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($obj)) {
			return 0;
		}

		return $this->fetch((int) $obj->rowid);
	}
}
