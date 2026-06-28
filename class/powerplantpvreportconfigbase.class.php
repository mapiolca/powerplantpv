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
 * \file		class/powerplantpvreportconfigbase.class.php
 * \ingroup		powerplantpv
 * \brief		Base class for report template configuration objects.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
dol_include_once('/powerplantpv/lib/powerplantpv_reporttemplate.lib.php');

/**
 * Base class for report template configuration objects.
 */
abstract class PowerPlantPVReportConfigBase extends CommonObject
{
	/**
	 * @var string Module key
	 */
	public $module = 'powerplantpv';

	/**
	 * @var string Picto
	 */
	public $picto = 'fa-clipboard-list';

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

	public $rowid;
	public $entity;
	public $date_creation;
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
	 * Validate object before persistence.
	 *
	 * @return	int		1 if OK, <0 if KO
	 */
	protected function validateObject()
	{
		return 1;
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

		$result = $this->validateObject();
		if ($result < 0) {
			return -1;
		}

		if (empty($this->entity)) {
			$this->entity = (int) $conf->entity;
		}
		if (empty($this->date_creation)) {
			$this->date_creation = dol_now();
		}
		if (empty($this->fk_user_creat)) {
			$this->fk_user_creat = (int) $user->id;
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
		global $conf;

		$result = $this->fetchCommon($id, $ref);
		if ($result <= 0) {
			return $result;
		}
		if ((int) $this->entity !== (int) $conf->entity) {
			return 0;
		}

		return $result;
	}

	/**
	 * Fetch record by code in current entity.
	 *
	 * @param	string	$code	Code
	 * @return	int				>0 if OK, 0 if not found, <0 on error
	 */
	public function fetchByCode($code)
	{
		global $conf;

		$sql = "SELECT rowid";
		$sql .= " FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE entity = ".((int) $conf->entity);
		$sql .= " AND code = '".$this->db->escape($code)."'";

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
	 * Update record.
	 *
	 * @param	User		$user		User
	 * @param	int<0,1>	$notrigger	No trigger flag
	 * @return	int						>0 if OK, <0 on error
	 */
	public function update(User $user, $notrigger = 0)
	{
		$result = $this->validateObject();
		if ($result < 0) {
			return -1;
		}

		$this->fk_user_modif = (int) $user->id;

		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Disable record.
	 *
	 * @param	User	$user	User
	 * @return	int			>0 if OK, <0 on error
	 */
	public function disable(User $user)
	{
		$this->active = 0;

		return $this->update($user, 0);
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

	/**
	 * Fetch all records matching filters.
	 *
	 * @param	int					$active		-1 all, 0 disabled, 1 active
	 * @param	array<string,mixed>	$filters	Filters
	 * @param	string				$sortfield	Sort field
	 * @param	string				$sortorder	Sort order
	 * @param	int					$limit		Limit
	 * @param	int					$offset		Offset
	 * @return	array<int,static>|int				Rows or <0 on error
	 */
	public function fetchAll($active = -1, $filters = array(), $sortfield = 'position', $sortorder = 'ASC', $limit = 0, $offset = 0)
	{
		global $conf;

		$rows = array();
		$sql = "SELECT ".$this->getSelectFieldList();
		$sql .= " FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE t.entity = ".((int) $conf->entity);
		if ($active >= 0 && array_key_exists('active', $this->fields)) {
			$sql .= " AND t.active = ".((int) $active);
		}
		$sql .= $this->buildFetchAllWhere($filters);

		$allowedSorts = array_keys($this->fields);
		if (!in_array($sortfield, $allowedSorts, true)) {
			$sortfield = 'position';
		}
		if (!in_array(strtoupper($sortorder), array('ASC', 'DESC'), true)) {
			$sortorder = 'ASC';
		}
		$sql .= " ORDER BY t.".$this->db->sanitize($sortfield)." ".strtoupper($sortorder).", t.rowid ASC";
		if ($limit > 0) {
			$sql .= $this->db->plimit($limit, $offset);
		}

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$class = get_class($this);
			$row = new $class($this->db);
			$row->setVarsFromFetchObj($obj);
			$row->id = (int) $row->rowid;
			$rows[] = $row;
		}
		$this->db->free($resql);

		return $rows;
	}

	/**
	 * Build additional SQL filters for fetchAll().
	 *
	 * @param	array<string,mixed>	$filters	Filters
	 * @return	string							SQL fragment
	 */
	protected function buildFetchAllWhere($filters)
	{
		return '';
	}

	/**
	 * Return SELECT field list.
	 *
	 * @return	string	SQL field list
	 */
	protected function getSelectFieldList()
	{
		$fields = array();
		foreach (array_keys($this->fields) as $field) {
			$fields[] = 't.'.$this->db->sanitize($field);
		}

		return implode(', ', $fields);
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

	/**
	 * Validate a required code field.
	 *
	 * @param	string	$field	Field name
	 * @return	int				1 if OK, <0 if KO
	 */
	protected function validateCodeField($field)
	{
		$value = isset($this->{$field}) ? (string) $this->{$field} : '';
		if ($value === '' || !powerplantpvReportTemplateIsValidCode($value)) {
			$this->setError('PowerPlantPVReportTemplateInvalidCode');
			return -1;
		}

		return 1;
	}

	/**
	 * Validate a required string field.
	 *
	 * @param	string	$field	Field name
	 * @param	string	$error	Error key
	 * @return	int				1 if OK, <0 if KO
	 */
	protected function validateRequiredString($field, $error)
	{
		$value = isset($this->{$field}) ? trim((string) $this->{$field}) : '';
		if ($value === '') {
			$this->setError($error);
			return -1;
		}

		return 1;
	}

	/**
	 * Validate a field value against an allowed list.
	 *
	 * @param	string						$field		Field name
	 * @param	array<string|int,string>		$allowed	Allowed options
	 * @param	string						$error		Error key
	 * @return	int										1 if OK, <0 if KO
	 */
	protected function validateEnum($field, $allowed, $error)
	{
		$value = isset($this->{$field}) ? (string) $this->{$field} : '';
		if (!array_key_exists($value, $allowed)) {
			$this->setError($error);
			return -1;
		}

		return 1;
	}
}
