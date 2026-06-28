<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		class/powerplantpvinterventionnature.class.php
 * \ingroup		powerplantpv
 * \brief		Intervention nature configuration object.
 */

dol_include_once('/powerplantpv/class/powerplantpvreportconfigbase.class.php');

/**
 * Intervention nature configuration object.
 */
class PowerPlantPVInterventionNature extends PowerPlantPVReportConfigBase
{
	public $element = 'powerplantpv_intervention_nature';
	public $table_element = 'c_powerplantpv_intervention_nature';

	/**
	 * @var array<string,array<string,mixed>> Fields
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => -2),
		'code' => array('type' => 'varchar(64)', 'label' => 'Code', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1, 'searchall' => 1),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1, 'searchall' => 1),
		'label_en' => array('type' => 'varchar(255)', 'label' => 'PowerPlantPVEnglishLabel', 'enabled' => 1, 'position' => 21, 'notnull' => 0, 'visible' => 1),
		'description' => array('type' => 'text', 'label' => 'Description', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 1),
		'description_en' => array('type' => 'text', 'label' => 'PowerPlantPVEnglishDescription', 'enabled' => 1, 'position' => 31, 'notnull' => 0, 'visible' => 1),
		'fk_report_template' => array('type' => 'integer:PowerPlantPVReportTemplate:powerplantpv/class/powerplantpvreporttemplate.class.php', 'label' => 'PowerPlantPVReportTemplate', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1),
		'report_template_code' => array('type' => 'varchar(64)', 'label' => 'PowerPlantPVReportTemplateCode', 'enabled' => 1, 'position' => 41, 'notnull' => 0, 'visible' => -2),
		'is_maintenance' => array('type' => 'smallint', 'label' => 'PowerPlantPVIsMaintenance', 'enabled' => 1, 'position' => 50, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'is_preventive' => array('type' => 'smallint', 'label' => 'PowerPlantPVIsPreventive', 'enabled' => 1, 'position' => 60, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'requires_report' => array('type' => 'smallint', 'label' => 'PowerPlantPVRequiresReport', 'enabled' => 1, 'position' => 70, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'requires_signature' => array('type' => 'smallint', 'label' => 'PowerPlantPVRequiresSignature', 'enabled' => 1, 'position' => 80, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'active' => array('type' => 'smallint', 'label' => 'Status', 'enabled' => 1, 'position' => 90, 'notnull' => 1, 'visible' => 1, 'default' => 1),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => 1, 'position' => 100, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => 0, 'visible' => -2),
	);

	public $code;
	public $label;
	public $label_en;
	public $description;
	public $description_en;
	public $fk_report_template;
	public $report_template_code;
	public $is_maintenance;
	public $is_preventive;
	public $requires_report;
	public $requires_signature;
	public $active;
	public $position;

	/**
	 * Validate object before persistence.
	 *
	 * @return	int		1 if OK, <0 if KO
	 */
	protected function validateObject()
	{
		if ($this->validateCodeField('code') < 0) {
			return -1;
		}
		if ($this->validateRequiredString('label', 'PowerPlantPVInterventionNatureLabelRequired') < 0) {
			return -1;
		}
		$this->is_maintenance = (int) $this->is_maintenance;
		$this->is_preventive = (int) $this->is_preventive;
		$this->requires_report = (int) $this->requires_report;
		$this->requires_signature = (int) $this->requires_signature;
		$this->active = (int) $this->active;
		$this->position = (int) $this->position;

		return 1;
	}

	/**
	 * Build additional SQL filters for fetchAll().
	 *
	 * @param	array<string,mixed>	$filters	Filters
	 * @return	string							SQL fragment
	 */
	protected function buildFetchAllWhere($filters)
	{
		$sql = '';
		if (!empty($filters['search'])) {
			$search = $this->db->escape((string) $filters['search']);
			$sql .= " AND (t.code LIKE '%".$search."%' OR t.label LIKE '%".$search."%')";
		}
		if (isset($filters['requires_report']) && $filters['requires_report'] !== '') {
			$sql .= " AND t.requires_report = ".((int) $filters['requires_report']);
		}

		return $sql;
	}
}
