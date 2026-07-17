<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		class/powerplantpvreportfile.class.php
 * \ingroup		powerplantpv
 * \brief		Generated report field file object.
 */

dol_include_once('/powerplantpv/class/powerplantpvreportgeneratedbase.class.php');

/**
 * Generated report field file object.
 */
class PowerPlantPVReportFile extends PowerPlantPVReportGeneratedBase
{
	public $element = 'powerplantpv_report_file';
	public $table_element = 'powerplantpv_report_file';

	/**
	 * @var array<string,array<string,mixed>> Fields
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => -2),
		'fk_report' => array('type' => 'integer:PowerPlantPVReport:powerplantpv/class/powerplantpvreport.class.php', 'label' => 'PowerPlantPVReport', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1),
		'fk_report_field' => array('type' => 'integer:PowerPlantPVReportField:powerplantpv/class/powerplantpvreportfield.class.php', 'label' => 'PowerPlantPVReportField', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1),
		'filename' => array('type' => 'varchar(255)', 'label' => 'File', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1),
		'filepath' => array('type' => 'varchar(255)', 'label' => 'RelativePath', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'visible' => 1),
		'filemime' => array('type' => 'varchar(128)', 'label' => 'MimeType', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'filesize' => array('type' => 'integer', 'label' => 'Size', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 1),
		'checksum' => array('type' => 'varchar(128)', 'label' => 'Checksum', 'enabled' => 1, 'position' => 70, 'notnull' => 0, 'visible' => 1),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => 1, 'position' => 80, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'date_upload' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 0, 'visible' => -2),
		'fk_user_upload' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 511, 'notnull' => 0, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => 0, 'visible' => -2),
	);

	public $fk_report;
	public $fk_report_field;
	public $filename;
	public $filepath;
	public $filemime;
	public $filesize;
	public $checksum;
	public $position;
	public $date_upload;
	public $fk_user_upload;

	/**
	 * Fetch files linked to a field.
	 *
	 * @param	int	$fieldId	Field id
	 * @return	array<int,PowerPlantPVReportFile>|int	Rows or <0 on error
	 */
	public function fetchAllByField($fieldId)
	{
		$fieldId = (int) $fieldId;
		if ($fieldId <= 0) {
			return array();
		}

		$sql = "SELECT ".$this->getSelectFieldList();
		$sql .= " FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE t.fk_report_field = ".$fieldId;
		$sql .= " ORDER BY t.position ASC, t.rowid ASC";

		return $this->fetchRowsFromSql($sql);
	}
}
