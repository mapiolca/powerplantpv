<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		class/powerplantpvreporttemplatefield.class.php
 * \ingroup		powerplantpv
 * \brief		Report template field configuration object.
 */

dol_include_once('/powerplantpv/class/powerplantpvreportconfigbase.class.php');

/**
 * Report template field configuration object.
 */
class PowerPlantPVReportTemplateField extends PowerPlantPVReportConfigBase
{
	public $element = 'powerplantpv_report_template_field';
	public $table_element = 'powerplantpv_report_template_field';

	/**
	 * @var array<string,array<string,mixed>> Fields
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => -2),
		'fk_report_template' => array('type' => 'integer:PowerPlantPVReportTemplate:powerplantpv/class/powerplantpvreporttemplate.class.php', 'label' => 'PowerPlantPVReportTemplate', 'enabled' => 1, 'position' => 10, 'notnull' => 0, 'visible' => 1),
		'fk_report_template_section' => array('type' => 'integer:PowerPlantPVReportTemplateSection:powerplantpv/class/powerplantpvreporttemplatesection.class.php', 'label' => 'PowerPlantPVReportSection', 'enabled' => 1, 'position' => 20, 'notnull' => 0, 'visible' => 1),
		'report_template_code' => array('type' => 'varchar(64)', 'label' => 'PowerPlantPVReportTemplateCode', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => -2),
		'fk_report_section' => array('type' => 'integer', 'label' => 'PowerPlantPVLegacyReportSection', 'enabled' => 1, 'position' => 31, 'notnull' => 0, 'visible' => -2),
		'fk_maintenance_service' => array('type' => 'integer', 'label' => 'MaintenanceServiceDictionary', 'enabled' => 1, 'position' => 32, 'notnull' => 0, 'visible' => 1),
		'code' => array('type' => 'varchar(64)', 'label' => 'Code', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'visible' => 1, 'searchall' => 1),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 50, 'notnull' => 1, 'visible' => 1, 'searchall' => 1),
		'label_en' => array('type' => 'varchar(255)', 'label' => 'PowerPlantPVEnglishLabel', 'enabled' => 1, 'position' => 51, 'notnull' => 0, 'visible' => 1),
		'description' => array('type' => 'text', 'label' => 'Description', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 1),
		'description_en' => array('type' => 'text', 'label' => 'PowerPlantPVEnglishDescription', 'enabled' => 1, 'position' => 61, 'notnull' => 0, 'visible' => 1),
		'field_type' => array('type' => 'varchar(32)', 'label' => 'Type', 'enabled' => 1, 'position' => 70, 'notnull' => 1, 'visible' => 1),
		'scope_type' => array('type' => 'varchar(32)', 'label' => 'PowerPlantPVReportScope', 'enabled' => 1, 'position' => 80, 'notnull' => 0, 'visible' => 1),
		'unit' => array('type' => 'varchar(32)', 'label' => 'Unit', 'enabled' => 1, 'position' => 90, 'notnull' => 0, 'visible' => 1),
		'default_value' => array('type' => 'text', 'label' => 'DefaultValue', 'enabled' => 1, 'position' => 100, 'notnull' => 0, 'visible' => 1),
		'placeholder' => array('type' => 'varchar(255)', 'label' => 'PowerPlantPVPlaceholder', 'enabled' => 1, 'position' => 110, 'notnull' => 0, 'visible' => 1),
		'help' => array('type' => 'text', 'label' => 'Help', 'enabled' => 1, 'position' => 120, 'notnull' => 0, 'visible' => 1),
		'is_required' => array('type' => 'smallint', 'label' => 'Required', 'enabled' => 1, 'position' => 130, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'visible_form' => array('type' => 'smallint', 'label' => 'PowerPlantPVVisibleForm', 'enabled' => 1, 'position' => 140, 'notnull' => 1, 'visible' => 1, 'default' => 1),
		'visible_pdf' => array('type' => 'smallint', 'label' => 'PowerPlantPVVisiblePdf', 'enabled' => 1, 'position' => 150, 'notnull' => 1, 'visible' => 1, 'default' => 1),
		'readonly' => array('type' => 'smallint', 'label' => 'PowerPlantPVReadonly', 'enabled' => 1, 'position' => 160, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'active' => array('type' => 'smallint', 'label' => 'Status', 'enabled' => 1, 'position' => 170, 'notnull' => 1, 'visible' => 1, 'default' => 1),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => 1, 'position' => 180, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 0, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => 0, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => 0, 'visible' => -2),
	);

	public $fk_report_template;
	public $fk_report_template_section;
	public $report_template_code;
	public $fk_report_section;
	public $fk_maintenance_service;
	public $code;
	public $label;
	public $label_en;
	public $description;
	public $description_en;
	public $field_type;
	public $scope_type;
	public $unit;
	public $default_value;
	public $placeholder;
	public $help;
	public $is_required;
	public $visible_form;
	public $visible_pdf;
	public $readonly;
	public $active;
	public $position;

	/**
	 * Validate object before persistence.
	 *
	 * @return	int		1 if OK, <0 if KO
	 */
	protected function validateObject()
	{
		if ((int) $this->fk_report_template <= 0) {
			$this->setError('PowerPlantPVReportTemplateRequired');
			return -1;
		}
		if ((int) $this->fk_report_template_section <= 0) {
			$this->setError('PowerPlantPVReportSectionRequired');
			return -1;
		}
		if ($this->validateCodeField('code') < 0) {
			return -1;
		}
		if ($this->validateRequiredString('label', 'PowerPlantPVReportFieldLabelRequired') < 0) {
			return -1;
		}
		if ($this->validateEnum('field_type', powerplantpvReportTemplateFieldTypes(), 'PowerPlantPVFieldTypeInvalid') < 0) {
			return -1;
		}
		if (!empty($this->scope_type) && $this->validateEnum('scope_type', powerplantpvReportTemplateScopeTypes(), 'PowerPlantPVReportScopeInvalid') < 0) {
			return -1;
		}
		if (empty($this->report_template_code)) {
			$this->report_template_code = 'manual';
		}
		if (empty($this->fk_report_section)) {
			$this->fk_report_section = 0;
		}
		$this->is_required = (int) $this->is_required;
		$this->visible_form = (int) $this->visible_form;
		$this->visible_pdf = (int) $this->visible_pdf;
		$this->readonly = (int) $this->readonly;
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
		if (!empty($filters['fk_report_template'])) {
			$sql .= " AND t.fk_report_template = ".((int) $filters['fk_report_template']);
		}
		if (!empty($filters['fk_report_template_section'])) {
			$sql .= " AND t.fk_report_template_section = ".((int) $filters['fk_report_template_section']);
		}
		if (!empty($filters['search'])) {
			$search = $this->db->escape((string) $filters['search']);
			$sql .= " AND (t.code LIKE '%".$search."%' OR t.label LIKE '%".$search."%')";
		}

		return $sql;
	}
}
