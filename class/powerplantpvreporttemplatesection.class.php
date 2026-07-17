<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		class/powerplantpvreporttemplatesection.class.php
 * \ingroup		powerplantpv
 * \brief		Report template section configuration object.
 */

dol_include_once('/powerplantpv/class/powerplantpvreportconfigbase.class.php');

/**
 * Report template section configuration object.
 */
class PowerPlantPVReportTemplateSection extends PowerPlantPVReportConfigBase
{
	public $element = 'powerplantpv_report_template_section';
	public $table_element = 'powerplantpv_report_template_section';

	/**
	 * @var array<string,array<string,mixed>> Fields
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => -2),
		'fk_report_template' => array('type' => 'integer:PowerPlantPVReportTemplate:powerplantpv/class/powerplantpvreporttemplate.class.php', 'label' => 'PowerPlantPVReportTemplate', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1),
		'code' => array('type' => 'varchar(64)', 'label' => 'Code', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1, 'searchall' => 1),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1, 'searchall' => 1),
		'label_en' => array('type' => 'varchar(255)', 'label' => 'PowerPlantPVEnglishLabel', 'enabled' => 1, 'position' => 31, 'notnull' => 0, 'visible' => 1),
		'description' => array('type' => 'text', 'label' => 'Description', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1),
		'description_en' => array('type' => 'text', 'label' => 'PowerPlantPVEnglishDescription', 'enabled' => 1, 'position' => 41, 'notnull' => 0, 'visible' => 1),
		'scope_type' => array('type' => 'varchar(32)', 'label' => 'PowerPlantPVReportScope', 'enabled' => 1, 'position' => 50, 'notnull' => 1, 'visible' => 1),
		'equipment_type' => array('type' => 'varchar(32)', 'label' => 'PowerPlantPVEquipmentType', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 1),
		'repeat_mode' => array('type' => 'varchar(32)', 'label' => 'PowerPlantPVRepeatMode', 'enabled' => 1, 'position' => 70, 'notnull' => 1, 'visible' => 1),
		'is_required' => array('type' => 'smallint', 'label' => 'Required', 'enabled' => 1, 'position' => 80, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'visible_form' => array('type' => 'smallint', 'label' => 'PowerPlantPVVisibleForm', 'enabled' => 1, 'position' => 90, 'notnull' => 1, 'visible' => 1, 'default' => 1),
		'visible_pdf' => array('type' => 'smallint', 'label' => 'PowerPlantPVVisiblePdf', 'enabled' => 1, 'position' => 100, 'notnull' => 1, 'visible' => 1, 'default' => 1),
		'active' => array('type' => 'smallint', 'label' => 'Status', 'enabled' => 1, 'position' => 110, 'notnull' => 1, 'visible' => 1, 'default' => 1),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => 1, 'position' => 120, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 0, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => 0, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => 0, 'visible' => -2),
	);

	public $fk_report_template;
	public $code;
	public $label;
	public $label_en;
	public $description;
	public $description_en;
	public $scope_type;
	public $equipment_type;
	public $repeat_mode;
	public $is_required;
	public $visible_form;
	public $visible_pdf;
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
		if ($this->validateCodeField('code') < 0) {
			return -1;
		}
		if ($this->validateRequiredString('label', 'PowerPlantPVReportSectionLabelRequired') < 0) {
			return -1;
		}
		if ($this->validateEnum('scope_type', powerplantpvReportTemplateScopeTypes(), 'PowerPlantPVReportScopeInvalid') < 0) {
			return -1;
		}
		if (!empty($this->equipment_type) && $this->validateEnum('equipment_type', powerplantpvReportTemplateEquipmentTypes(), 'PowerPlantPVEquipmentTypeInvalid') < 0) {
			return -1;
		}
		if ($this->validateEnum('repeat_mode', powerplantpvReportTemplateRepeatModes(), 'PowerPlantPVRepeatModeInvalid') < 0) {
			return -1;
		}
		$this->is_required = (int) $this->is_required;
		$this->visible_form = (int) $this->visible_form;
		$this->visible_pdf = (int) $this->visible_pdf;
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
		if (!empty($filters['search'])) {
			$search = $this->db->escape((string) $filters['search']);
			$sql .= " AND (t.code LIKE '%".$search."%' OR t.label LIKE '%".$search."%')";
		}

		return $sql;
	}
}
