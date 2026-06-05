<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file		class/powerplantpvserialnumberimport.class.php
 * \ingroup		powerplantpv
 * \brief		Temporary serial number import batch.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Temporary import batch for serial numbers.
 */
class PowerPlantPVSerialNumberImport extends CommonObject
{
	public const STATUS_DRAFT = 'draft';
	public const STATUS_CHECKED = 'checked';
	public const STATUS_VALIDATED = 'validated';
	public const STATUS_CANCELLED = 'cancelled';
	public const STATUS_ERROR = 'error';

	/**
	 * @var string Module key
	 */
	public $module = 'powerplantpv';

	/**
	 * @var string Element type
	 */
	public $element = 'powerplantpv_serialnumber_import';

	/**
	 * @var string Table element without prefix
	 */
	public $table_element = 'powerplantpv_serialnumber_import';

	/**
	 * @var string Picto
	 */
	public $picto = 'fa-file-import';

	/**
	 * @var int Multicompany support
	 */
	public $ismultientitymanaged = 1;

	/**
	 * @var array<string,array<string,mixed>> Fields
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => -2, 'default' => 1, 'index' => 1),
		'fk_powerplant' => array('type' => 'integer:PowerPlant:powerplantpv/class/powerplant.class.php', 'label' => 'PowerPlant', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'fk_categorie' => array('type' => 'integer', 'label' => 'Category', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'fk_user' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'User', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'filename' => array('type' => 'varchar(255)', 'label' => 'File', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'visible' => 1),
		'filepath' => array('type' => 'varchar(1024)', 'label' => 'File', 'enabled' => 1, 'position' => 45, 'notnull' => 0, 'visible' => -2),
		'status' => array('type' => 'varchar(16)', 'label' => 'Status', 'enabled' => 1, 'position' => 50, 'notnull' => 1, 'visible' => 1, 'default' => self::STATUS_DRAFT),
		'import_mode' => array('type' => 'varchar(16)', 'label' => 'SerialNumbersImportMode', 'enabled' => 1, 'position' => 60, 'notnull' => 1, 'visible' => 1, 'default' => 'add'),
		'first_line_headers' => array('type' => 'smallint', 'label' => 'SerialNumbersFirstLineHeaders', 'enabled' => 1, 'position' => 70, 'notnull' => 1, 'visible' => 1, 'default' => 1),
		'raw_data_json' => array('type' => 'mediumtext', 'label' => 'SerialNumbersRawData', 'enabled' => 1, 'position' => 80, 'notnull' => 0, 'visible' => -2),
		'parsed_data_json' => array('type' => 'mediumtext', 'label' => 'SerialNumbersParsedData', 'enabled' => 1, 'position' => 90, 'notnull' => 0, 'visible' => -2),
		'errors_json' => array('type' => 'mediumtext', 'label' => 'Errors', 'enabled' => 1, 'position' => 100, 'notnull' => 0, 'visible' => -2),
		'datec' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
	);

	public $rowid;
	public $entity;
	public $fk_powerplant;
	public $fk_categorie;
	public $fk_user;
	public $filename;
	public $filepath;
	public $status;
	public $import_mode;
	public $first_line_headers;
	public $raw_data_json;
	public $parsed_data_json;
	public $errors_json;
	public $datec;
	public $tms;

	/**
	 * Constructor.
	 *
	 * @param	DoliDB	$db		Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/**
	 * Create record.
	 *
	 * @param	User		$user		User
	 * @param	int<0,1>	$notrigger	No trigger flag
	 * @return	int						Record id, <0 on error
	 */
	public function create(User $user, $notrigger = 0)
	{
		global $conf;

		if (empty($this->entity)) {
			$this->entity = (int) $conf->entity;
		}
		if (empty($this->fk_user)) {
			$this->fk_user = (int) $user->id;
		}
		if (empty($this->datec)) {
			$this->datec = dol_now();
		}
		if (empty($this->status)) {
			$this->status = self::STATUS_DRAFT;
		}
		if (empty($this->import_mode)) {
			$this->import_mode = 'add';
		}

		return $this->createCommon($user, $notrigger);
	}

	/**
	 * Fetch record.
	 *
	 * @param	int		$id		Record id
	 * @param	string	$ref	Unused ref
	 * @return	int				>0 if OK, 0 not found, <0 on error
	 */
	public function fetch($id, $ref = '')
	{
		return $this->fetchCommon($id, $ref);
	}

	/**
	 * Update record.
	 *
	 * @param	User		$user		User
	 * @param	int<0,1>	$notrigger	No trigger flag
	 * @return	int						>0 if OK, <0 on error
	 */
	public function update(User $user, $notrigger = 0)
	{
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Decode parsed JSON data.
	 *
	 * @return	array<string,mixed>	Parsed data
	 */
	public function getParsedData()
	{
		$data = json_decode((string) $this->parsed_data_json, true);

		return (is_array($data) ? $data : array());
	}

	/**
	 * Decode errors JSON data.
	 *
	 * @return	array<string,mixed>	Errors data
	 */
	public function getErrorsData()
	{
		$data = json_decode((string) $this->errors_json, true);

		return (is_array($data) ? $data : array());
	}
}
