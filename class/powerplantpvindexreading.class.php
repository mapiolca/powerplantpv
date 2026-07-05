<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		class/powerplantpvindexreading.class.php
 * \ingroup		powerplantpv
 * \brief		Archived production and consumption index readings.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Archived production and consumption index reading.
 */
class PowerPlantPVIndexReading extends CommonObject
{
	public const SOURCE_MANUAL = 'manual';
	public const SOURCE_REPORT = 'report';

	/**
	 * @var string Module key
	 */
	public $module = 'powerplantpv';

	/**
	 * @var string Object element
	 */
	public $element = 'powerplantpv_index_reading';

	/**
	 * @var string Table element
	 */
	public $table_element = 'powerplantpv_index_reading';

	/**
	 * @var string Picto
	 */
	public $picto = 'fa-tachometer-alt';

	/**
	 * @var int Multicompany support
	 */
	public $ismultientitymanaged = 1;

	/**
	 * @var string Error message
	 */
	public $error = '';

	/**
	 * @var array<int,string> Error messages
	 */
	public $errors = array();

	/**
	 * @var array<string,array<string,mixed>> Fields
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => -2, 'default' => 1),
		'fk_powerplant' => array('type' => 'integer:PowerPlant:powerplantpv/class/powerplant.class.php', 'label' => 'PowerPlant', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1),
		'fk_fichinter_source' => array('type' => 'integer:Fichinter:fichinter/class/fichinter.class.php', 'label' => 'Intervention', 'enabled' => 1, 'position' => 20, 'notnull' => 0, 'visible' => 1),
		'fk_report' => array('type' => 'integer:PowerPlantPVReport:powerplantpv/class/powerplantpvreport.class.php', 'label' => 'PowerPlantPVReport', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 1),
		'fk_report_powerplant' => array('type' => 'integer:PowerPlantPVReportPowerPlant:powerplantpv/class/powerplantpvreportpowerplant.class.php', 'label' => 'PowerPlant', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'fk_report_equipment' => array('type' => 'integer:PowerPlantPVReportEquipment:powerplantpv/class/powerplantpvreportequipment.class.php', 'label' => 'PowerPlantPVEquipment', 'enabled' => 1, 'position' => 50, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'fk_index_type' => array('type' => 'integer', 'label' => 'PowerPlantPVReadingType', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 1),
		'reading_type_code' => array('type' => 'varchar(64)', 'label' => 'PowerPlantPVReadingType', 'enabled' => 1, 'position' => 70, 'notnull' => 1, 'visible' => 1),
		'reading_date' => array('type' => 'datetime', 'label' => 'PowerPlantPVReadingDate', 'enabled' => 1, 'position' => 80, 'notnull' => 1, 'visible' => 1),
		'value' => array('type' => 'double(24,8)', 'label' => 'PowerPlantPVReadingValue', 'enabled' => 1, 'position' => 90, 'notnull' => 1, 'visible' => 1),
		'unit' => array('type' => 'varchar(32)', 'label' => 'PowerPlantPVReadingUnit', 'enabled' => 1, 'position' => 100, 'notnull' => 1, 'visible' => 1, 'default' => 'kWh'),
		'meter_ref' => array('type' => 'varchar(128)', 'label' => 'PowerPlantPVMeterRef', 'enabled' => 1, 'position' => 110, 'notnull' => 1, 'visible' => 1, 'default' => ''),
		'source_type' => array('type' => 'varchar(32)', 'label' => 'PowerPlantPVReadingSource', 'enabled' => 1, 'position' => 120, 'notnull' => 1, 'visible' => 1, 'default' => self::SOURCE_MANUAL),
		'comment' => array('type' => 'text', 'label' => 'Comment', 'enabled' => 1, 'position' => 130, 'notnull' => 0, 'visible' => 1),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 0, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => 0, 'visible' => -2),
		'active' => array('type' => 'smallint', 'label' => 'Active', 'enabled' => 1, 'position' => 520, 'notnull' => 1, 'visible' => 1, 'default' => 1),
	);

	public $rowid;
	public $entity;
	public $fk_powerplant;
	public $fk_fichinter_source;
	public $fk_report;
	public $fk_report_powerplant;
	public $fk_report_equipment;
	public $fk_index_type;
	public $reading_type_code;
	public $reading_date;
	public $value;
	public $unit;
	public $meter_ref;
	public $source_type;
	public $comment;
	public $date_creation;
	public $tms;
	public $fk_user_creat;
	public $fk_user_modif;
	public $active;

	/**
	 * Constructor.
	 *
	 * @param	DoliDB	$db	Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/**
	 * Create record.
	 *
	 * @param	User	$user		User
	 * @param	int		$notrigger	No trigger flag
	 * @return	int					Record id, <0 on error
	 */
	public function create(User $user, $notrigger = 1)
	{
		global $conf;

		if (empty($this->entity)) {
			$this->entity = (int) $conf->entity;
		}
		if (empty($this->date_creation)) {
			$this->date_creation = dol_now();
		}
		if (empty($this->fk_user_creat)) {
			$this->fk_user_creat = (int) $user->id;
		}
		$this->normalizeDefaults();
		if ($this->validateForSave() < 0) {
			return -1;
		}

		return $this->createCommon($user, $notrigger);
	}

	/**
	 * Fetch record by id.
	 *
	 * @param	int		$id		Record id
	 * @param	string	$ref	Unused reference
	 * @return	int				>0 if OK, 0 if not found, <0 on error
	 */
	public function fetch($id, $ref = '')
	{
		return $this->fetchCommon($id, $ref);
	}

	/**
	 * Update record.
	 *
	 * @param	User	$user		User
	 * @param	int		$notrigger	No trigger flag
	 * @return	int					>0 if OK, <0 on error
	 */
	public function update(User $user, $notrigger = 1)
	{
		$this->fk_user_modif = (int) $user->id;
		$this->normalizeDefaults();
		if ($this->validateForSave() < 0) {
			return -1;
		}

		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Fetch the report-sourced reading matching the anti-duplicate key.
	 *
	 * @param	int		$fkPowerplant		Power plant id
	 * @param	int		$fkFichinter		Source intervention id
	 * @param	int		$fkReport			Source report id
	 * @param	string	$readingTypeCode	Reading type code
	 * @param	string	$meterRef			Meter/inverter reference
	 * @param	int		$fkReportEquipment	Report equipment id
	 * @return	int							>0 if found, 0 if not found, <0 on error
	 */
	public function fetchByReportSource($fkPowerplant, $fkFichinter, $fkReport, $readingTypeCode, $meterRef = '', $fkReportEquipment = 0)
	{
		$fkPowerplant = (int) $fkPowerplant;
		$fkFichinter = (int) $fkFichinter;
		$fkReport = (int) $fkReport;
		$readingTypeCode = trim((string) $readingTypeCode);
		$meterRef = trim((string) $meterRef);
		$fkReportEquipment = (int) $fkReportEquipment;
		if ($fkPowerplant <= 0 || $fkFichinter <= 0 || $fkReport <= 0 || $readingTypeCode === '') {
			return 0;
		}

		$sql = "SELECT rowid";
		$sql .= " FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE fk_powerplant = ".$fkPowerplant;
		$sql .= " AND fk_fichinter_source = ".$fkFichinter;
		$sql .= " AND fk_report = ".$fkReport;
		$sql .= " AND reading_type_code = '".$this->db->escape($readingTypeCode)."'";
		$sql .= " AND meter_ref = '".$this->db->escape($meterRef)."'";
		$sql .= " AND fk_report_equipment = ".$fkReportEquipment;
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
	 * Fetch the latest archived active reading for a power plant and type.
	 *
	 * @param	int		$fkPowerplant		Power plant id
	 * @param	string	$readingTypeCode	Reading type code
	 * @param	string	$meterRef			Optional meter/inverter reference
	 * @param	int		$excludeReportId	Optional report id to exclude
	 * @return	array{rowid:int,value:float,reading_date:string}|null	Latest reading
	 */
	public function fetchLatestValue($fkPowerplant, $readingTypeCode, $meterRef = '', $excludeReportId = 0)
	{
		$fkPowerplant = (int) $fkPowerplant;
		$readingTypeCode = trim((string) $readingTypeCode);
		$excludeReportId = (int) $excludeReportId;
		if ($fkPowerplant <= 0 || $readingTypeCode === '') {
			return null;
		}

		$sql = "SELECT rowid, value, reading_date";
		$sql .= " FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE fk_powerplant = ".$fkPowerplant;
		$sql .= " AND active = 1";
		$sql .= " AND reading_type_code = '".$this->db->escape($readingTypeCode)."'";
		$sql .= " AND entity IN (".$this->db->sanitize(getEntity('powerplant')).")";
		if (trim((string) $meterRef) !== '') {
			$sql .= " AND meter_ref = '".$this->db->escape(trim((string) $meterRef))."'";
		}
		if ($excludeReportId > 0) {
			$sql .= " AND (fk_report IS NULL OR fk_report <> ".$excludeReportId.")";
		}
		$sql .= " ORDER BY reading_date DESC, rowid DESC";
		$sql .= " LIMIT 1";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return null;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($obj)) {
			return null;
		}

		return array(
			'rowid' => (int) $obj->rowid,
			'value' => (float) $obj->value,
			'reading_date' => (string) $obj->reading_date,
		);
	}

	/**
	 * Fetch the previous active reading for delta calculation.
	 *
	 * @param	int		$fkPowerplant		Power plant id
	 * @param	string	$readingTypeCode	Reading type code
	 * @param	string	$readingDate		Current reading SQL date
	 * @param	int		$rowid				Current row id
	 * @return	array{rowid:int,value:float,reading_date:string}|null	Previous reading
	 */
	public function fetchPreviousValue($fkPowerplant, $readingTypeCode, $readingDate, $rowid)
	{
		$fkPowerplant = (int) $fkPowerplant;
		$readingTypeCode = trim((string) $readingTypeCode);
		$readingDate = (string) $readingDate;
		$rowid = (int) $rowid;
		if ($fkPowerplant <= 0 || $readingTypeCode === '' || $readingDate === '' || $rowid <= 0) {
			return null;
		}

		$sql = "SELECT rowid, value, reading_date";
		$sql .= " FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE fk_powerplant = ".$fkPowerplant;
		$sql .= " AND active = 1";
		$sql .= " AND reading_type_code = '".$this->db->escape($readingTypeCode)."'";
		$sql .= " AND entity IN (".$this->db->sanitize(getEntity('powerplant')).")";
		$sql .= " AND (reading_date < '".$this->db->escape($readingDate)."' OR (reading_date = '".$this->db->escape($readingDate)."' AND rowid < ".$rowid."))";
		$sql .= " ORDER BY reading_date DESC, rowid DESC";
		$sql .= " LIMIT 1";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return null;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($obj)) {
			return null;
		}

		return array(
			'rowid' => (int) $obj->rowid,
			'value' => (float) $obj->value,
			'reading_date' => (string) $obj->reading_date,
		);
	}

	/**
	 * Deactivate archived readings generated from a report.
	 *
	 * @param	int		$reportId	Report id
	 * @param	User	$user		User
	 * @return	int					>0 if OK, <0 on error
	 */
	public function deactivateByReport($reportId, User $user)
	{
		$reportId = (int) $reportId;
		if ($reportId <= 0) {
			return 1;
		}

		$sql = "UPDATE ".$this->db->prefix().$this->table_element;
		$sql .= " SET active = 0, fk_user_modif = ".((int) $user->id);
		$sql .= " WHERE fk_report = ".$reportId;
		$sql .= " AND source_type = '".$this->db->escape(self::SOURCE_REPORT)."'";
		if (!$this->db->query($sql)) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		return 1;
	}

	/**
	 * Normalize nullable/default values before persistence.
	 *
	 * @return	void
	 */
	private function normalizeDefaults()
	{
		$this->fk_report_powerplant = !empty($this->fk_report_powerplant) ? (int) $this->fk_report_powerplant : 0;
		$this->fk_report_equipment = !empty($this->fk_report_equipment) ? (int) $this->fk_report_equipment : 0;
		$this->meter_ref = trim((string) $this->meter_ref);
		$this->unit = trim((string) $this->unit) !== '' ? trim((string) $this->unit) : 'kWh';
		$this->source_type = trim((string) $this->source_type) !== '' ? trim((string) $this->source_type) : self::SOURCE_MANUAL;
		$this->active = isset($this->active) ? (int) $this->active : 1;
		if (empty($this->fk_fichinter_source)) {
			$this->fk_fichinter_source = null;
		}
		if (empty($this->fk_report)) {
			$this->fk_report = null;
		}
		if (empty($this->fk_index_type)) {
			$this->fk_index_type = null;
		}
	}

	/**
	 * Validate required values before persistence.
	 *
	 * @return	int	1 if OK, <0 on error
	 */
	private function validateForSave()
	{
		if ((int) $this->fk_powerplant <= 0) {
			$this->setError('PowerPlantPVIndexReadingPowerPlantRequired');
			return -1;
		}
		if (trim((string) $this->reading_type_code) === '') {
			$this->setError('PowerPlantPVIndexReadingTypeRequired');
			return -1;
		}
		if (empty($this->reading_date)) {
			$this->setError('PowerPlantPVIndexReadingDateRequired');
			return -1;
		}
		if ($this->value === null || $this->value === '') {
			$this->setError('PowerPlantPVIndexReadingValueRequired');
			return -1;
		}
		$this->value = (float) $this->value;

		return 1;
	}

	/**
	 * Set an error.
	 *
	 * @param	string	$error	Error message
	 * @return	void
	 */
	protected function setError($error)
	{
		$this->error = $error;
		$this->errors[] = $error;
	}
}
