<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		class/powerplantpvreportdcmeasure.class.php
 * \ingroup		powerplantpv
 * \brief		Generated report DC measure snapshot object.
 */

dol_include_once('/powerplantpv/class/powerplantpvreportgeneratedbase.class.php');

/**
 * Generated report DC measure snapshot object.
 */
class PowerPlantPVReportDcMeasure extends PowerPlantPVReportGeneratedBase
{
	public $element = 'powerplantpv_report_dc_measure';
	public $table_element = 'powerplantpv_report_dc_measure';
	public $picto = 'fa-bolt';

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
		'fk_powerplant' => array('type' => 'integer', 'label' => 'PowerPlant', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'fk_inverter' => array('type' => 'integer', 'label' => 'PowerPlantPVInverter', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 1),
		'inverter_ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'position' => 70, 'notnull' => 0, 'visible' => 1),
		'inverter_label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 80, 'notnull' => 0, 'visible' => 1),
		'inverter_serial' => array('type' => 'varchar(128)', 'label' => 'SerialNumber', 'enabled' => 1, 'position' => 90, 'notnull' => 0, 'visible' => 1),
		'mppt_number' => array('type' => 'integer', 'label' => 'PowerPlantPVMPPT', 'enabled' => 1, 'position' => 100, 'notnull' => 0, 'visible' => 1),
		'pv_input_number' => array('type' => 'integer', 'label' => 'PowerPlantPVPVInput', 'enabled' => 1, 'position' => 110, 'notnull' => 0, 'visible' => 1),
		'string_ref' => array('type' => 'varchar(128)', 'label' => 'PowerPlantPVStringRef', 'enabled' => 1, 'position' => 120, 'notnull' => 0, 'visible' => 1),
		'is_connected' => array('type' => 'smallint', 'label' => 'PowerPlantPVPVInputConnected', 'enabled' => 1, 'position' => 130, 'notnull' => 1, 'visible' => 1, 'default' => 1),
		'open_circuit_voltage' => array('type' => 'double(24,8)', 'label' => 'PowerPlantPVOpenCircuitVoltage', 'enabled' => 1, 'position' => 140, 'notnull' => 0, 'visible' => 1),
		'polarity_checked' => array('type' => 'smallint', 'label' => 'PowerPlantPVPolarityChecked', 'enabled' => 1, 'position' => 150, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'insulation_status' => array('type' => 'varchar(32)', 'label' => 'PowerPlantPVInsulationStatus', 'enabled' => 1, 'position' => 160, 'notnull' => 0, 'visible' => 1),
		'insulation_positive_to_ground' => array('type' => 'double(24,8)', 'label' => 'PowerPlantPVInsulationPositiveToGround', 'enabled' => 1, 'position' => 170, 'notnull' => 0, 'visible' => 1),
		'insulation_negative_to_ground' => array('type' => 'double(24,8)', 'label' => 'PowerPlantPVInsulationNegativeToGround', 'enabled' => 1, 'position' => 180, 'notnull' => 0, 'visible' => 1),
		'observation' => array('type' => 'text', 'label' => 'Observation', 'enabled' => 1, 'position' => 190, 'notnull' => 0, 'visible' => 1),
		'stable_key' => array('type' => 'varchar(255)', 'label' => 'Code', 'enabled' => 1, 'position' => 200, 'notnull' => 1, 'visible' => 1),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => 1, 'position' => 210, 'notnull' => 1, 'visible' => 1, 'default' => 0),
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
	public $fk_powerplant;
	public $fk_inverter;
	public $inverter_ref;
	public $inverter_label;
	public $inverter_serial;
	public $mppt_number;
	public $pv_input_number;
	public $string_ref;
	public $is_connected;
	public $open_circuit_voltage;
	public $polarity_checked;
	public $insulation_status;
	public $insulation_positive_to_ground;
	public $insulation_negative_to_ground;
	public $observation;
	public $stable_key;
	public $position;

	/**
	 * Fetch one measure by report and stable key.
	 *
	 * @param	int		$reportId	Report id
	 * @param	string	$stableKey	Stable key
	 * @return	int					>0 if OK, 0 if not found, <0 on error
	 */
	public function fetchByStableKey($reportId, $stableKey)
	{
		$sql = "SELECT ".$this->getSelectFieldList();
		$sql .= " FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE t.fk_report = ".((int) $reportId);
		$sql .= " AND t.stable_key = '".$this->db->escape((string) $stableKey)."'";

		$rows = $this->fetchRowsFromSql($sql);
		if (!is_array($rows)) {
			return -1;
		}
		if (empty($rows[0])) {
			return 0;
		}
		foreach (array_keys($this->fields) as $field) {
			$this->{$field} = $rows[0]->{$field};
		}
		$this->id = (int) $this->rowid;

		return 1;
	}
}
