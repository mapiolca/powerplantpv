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
 * \file		class/powerplantpvserialnumber.class.php
 * \ingroup		powerplantpv
 * \brief		Serial number stored for a power plant composition line.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Serial number attached to a power plant composition line.
 */
class PowerPlantPVSerialNumber extends CommonObject
{
	/**
	 * @var string Module key
	 */
	public $module = 'powerplantpv';

	/**
	 * @var string Element type
	 */
	public $element = 'powerplantpv_serialnumber';

	/**
	 * @var string Table element without prefix
	 */
	public $table_element = 'powerplantpv_serialnumber';

	/**
	 * @var string Picto
	 */
	public $picto = 'fa-barcode';

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
		'fk_powerplant_line' => array('type' => 'integer', 'label' => 'PowerPlantCompositionLine', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'fk_product' => array('type' => 'integer:Product:product/class/product.class.php', 'label' => 'Product', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'fk_categorie' => array('type' => 'integer', 'label' => 'Category', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'serial_number' => array('type' => 'varchar(128)', 'label' => 'PowerPlantSerialNumber', 'enabled' => 1, 'position' => 50, 'notnull' => 1, 'visible' => 1, 'searchall' => 1, 'index' => 1),
		'source_file' => array('type' => 'varchar(255)', 'label' => 'SerialNumbersSourceFile', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 1),
		'import_batch' => array('type' => 'varchar(64)', 'label' => 'SerialNumbersImportBatch', 'enabled' => 1, 'position' => 70, 'notnull' => 0, 'visible' => 1, 'index' => 1),
		'note' => array('type' => 'text', 'label' => 'Note', 'enabled' => 1, 'position' => 80, 'notnull' => 0, 'visible' => 3),
		'import_status' => array('type' => 'varchar(32)', 'label' => 'Status', 'enabled' => 1, 'position' => 90, 'notnull' => 1, 'visible' => 1, 'default' => 'validated'),
		'datec' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => -1, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => -1, 'visible' => -2),
	);

	public $rowid;
	public $entity;
	public $fk_powerplant;
	public $fk_powerplant_line;
	public $fk_product;
	public $fk_categorie;
	public $serial_number;
	public $source_file;
	public $import_batch;
	public $note;
	public $import_status;
	public $datec;
	public $tms;
	public $fk_user_creat;
	public $fk_user_modif;
	public $import_key;

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
		if (empty($this->datec)) {
			$this->datec = dol_now();
		}
		if (empty($this->fk_user_creat)) {
			$this->fk_user_creat = (int) $user->id;
		}
		if (empty($this->import_status)) {
			$this->import_status = 'validated';
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
		$this->fk_user_modif = (int) $user->id;

		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Delete record.
	 *
	 * @param	User		$user		User
	 * @param	int<0,1>	$notrigger	No trigger flag
	 * @return	int						>0 if OK, <0 on error
	 */
	public function delete(User $user, $notrigger = 0)
	{
		return $this->deleteCommon($user, $notrigger);
	}
}
