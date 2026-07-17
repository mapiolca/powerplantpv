<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		class/powerplantpvreporttemplatefieldoption.class.php
 * \ingroup		powerplantpv
 * \brief		Report template field option configuration object.
 */

dol_include_once('/powerplantpv/class/powerplantpvreportconfigbase.class.php');

/**
 * Report template field option configuration object.
 */
class PowerPlantPVReportTemplateFieldOption extends PowerPlantPVReportConfigBase
{
	public $element = 'powerplantpv_report_template_field_option';
	public $table_element = 'powerplantpv_report_template_field_option';

	/**
	 * @var array<string,array<string,mixed>> Fields
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => -2),
		'fk_report_template_field' => array('type' => 'integer:PowerPlantPVReportTemplateField:powerplantpv/class/powerplantpvreporttemplatefield.class.php', 'label' => 'PowerPlantPVReportField', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1),
		'code' => array('type' => 'varchar(64)', 'label' => 'Code', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1),
		'label_en' => array('type' => 'varchar(255)', 'label' => 'PowerPlantPVEnglishLabel', 'enabled' => 1, 'position' => 31, 'notnull' => 0, 'visible' => 1),
		'active' => array('type' => 'smallint', 'label' => 'Status', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'visible' => 1, 'default' => 1),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => 1, 'position' => 50, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 0, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => 0, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => 0, 'visible' => -2),
	);

	public $fk_report_template_field;
	public $code;
	public $label;
	public $label_en;
	public $active;
	public $position;

	/**
	 * Validate object before persistence.
	 *
	 * @return	int		1 if OK, <0 if KO
	 */
	protected function validateObject()
	{
		if ((int) $this->fk_report_template_field <= 0) {
			$this->setError('PowerPlantPVReportFieldRequired');
			return -1;
		}
		if ($this->validateCodeField('code') < 0) {
			return -1;
		}
		if ($this->validateRequiredString('label', 'PowerPlantPVReportFieldOptionLabelRequired') < 0) {
			return -1;
		}
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
		if (!empty($filters['fk_report_template_field'])) {
			$sql .= " AND t.fk_report_template_field = ".((int) $filters['fk_report_template_field']);
		}

		return $sql;
	}
}
