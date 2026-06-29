<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		class/powerplantpvreport.class.php
 * \ingroup		powerplantpv
 * \brief		Generated intervention report snapshot object.
 */

dol_include_once('/powerplantpv/class/powerplantpvreportgeneratedbase.class.php');

/**
 * Generated intervention report snapshot object.
 */
class PowerPlantPVReport extends PowerPlantPVReportGeneratedBase
{
	public const STATUS_DRAFT = 'draft';
	public const STATUS_SAVED = 'saved';

	public $element = 'powerplantpv_report';
	public $table_element = 'powerplantpv_report';

	/**
	 * @var array<string,array<string,mixed>> Fields
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => -2),
		'fk_fichinter' => array('type' => 'integer', 'label' => 'Intervention', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1),
		'fk_soc' => array('type' => 'integer', 'label' => 'ThirdParty', 'enabled' => 1, 'position' => 20, 'notnull' => 0, 'visible' => 1),
		'fk_project' => array('type' => 'integer', 'label' => 'Project', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 1),
		'fk_intervention_nature' => array('type' => 'integer', 'label' => 'PowerPlantPVMaintenanceNature', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1),
		'intervention_nature_code' => array('type' => 'varchar(64)', 'label' => 'Code', 'enabled' => 1, 'position' => 41, 'notnull' => 0, 'visible' => 1),
		'intervention_nature_label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 42, 'notnull' => 0, 'visible' => 1),
		'intervention_nature_label_en' => array('type' => 'varchar(255)', 'label' => 'PowerPlantPVEnglishLabel', 'enabled' => 1, 'position' => 43, 'notnull' => 0, 'visible' => 1),
		'fk_report_template' => array('type' => 'integer', 'label' => 'PowerPlantPVReportTemplate', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'report_template_code' => array('type' => 'varchar(64)', 'label' => 'PowerPlantPVReportTemplateCode', 'enabled' => 1, 'position' => 51, 'notnull' => 0, 'visible' => 1),
		'report_template_label' => array('type' => 'varchar(255)', 'label' => 'PowerPlantPVReportTemplate', 'enabled' => 1, 'position' => 52, 'notnull' => 0, 'visible' => 1),
		'report_template_label_en' => array('type' => 'varchar(255)', 'label' => 'PowerPlantPVEnglishLabel', 'enabled' => 1, 'position' => 53, 'notnull' => 0, 'visible' => 1),
		'source_mode' => array('type' => 'varchar(16)', 'label' => 'PowerPlantPVReportSourceMode', 'enabled' => 1, 'position' => 60, 'notnull' => 1, 'visible' => 1, 'default' => 'contract'),
		'status' => array('type' => 'varchar(16)', 'label' => 'Status', 'enabled' => 1, 'position' => 70, 'notnull' => 1, 'visible' => 1, 'default' => self::STATUS_DRAFT),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 0, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => 0, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => 0, 'visible' => -2),
	);

	public $fk_fichinter;
	public $fk_soc;
	public $fk_project;
	public $fk_intervention_nature;
	public $intervention_nature_code;
	public $intervention_nature_label;
	public $intervention_nature_label_en;
	public $fk_report_template;
	public $report_template_code;
	public $report_template_label;
	public $report_template_label_en;
	public $source_mode;
	public $status;

	/**
	 * Fetch report by intervention.
	 *
	 * @param	int	$interventionId	Intervention id
	 * @return	int					>0 if found, 0 if not found, <0 on error
	 */
	public function fetchByIntervention($interventionId)
	{
		$interventionId = (int) $interventionId;
		if ($interventionId <= 0) {
			return 0;
		}

		$sql = "SELECT rowid";
		$sql .= " FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE fk_fichinter = ".$interventionId;
		$sql .= " AND entity IN (".$this->db->sanitize(getEntity('fichinter')).")";
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

	/**
	 * Return true when status is saved.
	 *
	 * @return	bool	True if saved
	 */
	public function isSaved()
	{
		return (string) $this->status === self::STATUS_SAVED;
	}
}
