<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		class/powerplantpvmaintenanceservicesection.class.php
 * \ingroup		powerplantpv
 * \brief		Maintenance service to report section mapping object.
 */

dol_include_once('/powerplantpv/class/powerplantpvreportconfigbase.class.php');

/**
 * Maintenance service to report section mapping object.
 */
class PowerPlantPVMaintenanceServiceSection extends PowerPlantPVReportConfigBase
{
	public $element = 'powerplantpv_maintenance_service_section';
	public $table_element = 'powerplantpv_maintenance_service_section';

	/**
	 * @var array<string,array<string,mixed>> Fields
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => -2),
		'fk_report_template' => array('type' => 'integer:PowerPlantPVReportTemplate:powerplantpv/class/powerplantpvreporttemplate.class.php', 'label' => 'PowerPlantPVReportTemplate', 'enabled' => 1, 'position' => 10, 'notnull' => 0, 'visible' => 1),
		'fk_maintenance_service' => array('type' => 'integer', 'label' => 'MaintenanceServiceDictionary', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1),
		'fk_report_section' => array('type' => 'integer', 'label' => 'PowerPlantPVLegacyReportSection', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => -2),
		'fk_report_template_section' => array('type' => 'integer:PowerPlantPVReportTemplateSection:powerplantpv/class/powerplantpvreporttemplatesection.class.php', 'label' => 'PowerPlantPVReportSection', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1),
		'is_required' => array('type' => 'smallint', 'label' => 'Required', 'enabled' => 1, 'position' => 50, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'active' => array('type' => 'smallint', 'label' => 'Status', 'enabled' => 1, 'position' => 60, 'notnull' => 1, 'visible' => 1, 'default' => 1),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => 1, 'position' => 70, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 0, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => 0, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => 0, 'visible' => -2),
	);

	public $fk_report_template;
	public $fk_maintenance_service;
	public $fk_report_section;
	public $fk_report_template_section;
	public $is_required;
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
		if ((int) $this->fk_maintenance_service <= 0) {
			$this->setError('PowerPlantPVMaintenanceServiceRequired');
			return -1;
		}
		if ((int) $this->fk_report_template_section <= 0) {
			$this->setError('PowerPlantPVReportSectionRequired');
			return -1;
		}
		if (empty($this->fk_report_section)) {
			$this->fk_report_section = 0;
		}
		$this->is_required = (int) $this->is_required;
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
		if (!empty($filters['fk_maintenance_service'])) {
			$sql .= " AND t.fk_maintenance_service = ".((int) $filters['fk_maintenance_service']);
		}
		if (!empty($filters['fk_report_template_section'])) {
			$sql .= " AND t.fk_report_template_section = ".((int) $filters['fk_report_template_section']);
		}

		return $sql;
	}
}
