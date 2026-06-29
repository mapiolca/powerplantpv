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
 * \file		class/powerplantpvreportgeneratedbase.class.php
 * \ingroup		powerplantpv
 * \brief		Base class for generated intervention report snapshot objects.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Base class for generated report snapshot objects.
 */
abstract class PowerPlantPVReportGeneratedBase extends CommonObject
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
		if (empty($this->date_creation) && array_key_exists('date_creation', $this->fields)) {
			$this->date_creation = dol_now();
		}
		if (empty($this->fk_user_creat) && array_key_exists('fk_user_creat', $this->fields)) {
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
		if (array_key_exists('fk_user_modif', $this->fields)) {
			$this->fk_user_modif = (int) $user->id;
		}

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

	/**
	 * Fetch rows linked to a report.
	 *
	 * @param	int		$reportId	Report id
	 * @param	string	$sortfield	Sort field
	 * @param	string	$sortorder	Sort order
	 * @return	array<int,static>|int	Rows or <0 on error
	 */
	public function fetchAllByReport($reportId, $sortfield = 'position', $sortorder = 'ASC')
	{
		$reportId = (int) $reportId;
		if ($reportId <= 0 || !array_key_exists('fk_report', $this->fields)) {
			return array();
		}

		$allowedSorts = array_keys($this->fields);
		if (!in_array($sortfield, $allowedSorts, true)) {
			$sortfield = 'position';
		}
		if (!in_array(strtoupper($sortorder), array('ASC', 'DESC'), true)) {
			$sortorder = 'ASC';
		}

		$sql = "SELECT ".$this->getSelectFieldList();
		$sql .= " FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE t.fk_report = ".$reportId;
		$sql .= " ORDER BY t.".$this->db->sanitize($sortfield)." ".strtoupper($sortorder).", t.rowid ASC";

		return $this->fetchRowsFromSql($sql);
	}

	/**
	 * Fetch rows linked to a report section.
	 *
	 * @param	int		$sectionId	Section id
	 * @param	string	$sortfield	Sort field
	 * @param	string	$sortorder	Sort order
	 * @return	array<int,static>|int	Rows or <0 on error
	 */
	public function fetchAllBySection($sectionId, $sortfield = 'position', $sortorder = 'ASC')
	{
		$sectionId = (int) $sectionId;
		if ($sectionId <= 0 || !array_key_exists('fk_report_section', $this->fields)) {
			return array();
		}

		$allowedSorts = array_keys($this->fields);
		if (!in_array($sortfield, $allowedSorts, true)) {
			$sortfield = 'position';
		}
		if (!in_array(strtoupper($sortorder), array('ASC', 'DESC'), true)) {
			$sortorder = 'ASC';
		}

		$sql = "SELECT ".$this->getSelectFieldList();
		$sql .= " FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE t.fk_report_section = ".$sectionId;
		$sql .= " ORDER BY t.".$this->db->sanitize($sortfield)." ".strtoupper($sortorder).", t.rowid ASC";

		return $this->fetchRowsFromSql($sql);
	}

	/**
	 * Return rows from a SELECT query.
	 *
	 * @param	string	$sql	SQL query
	 * @return	array<int,static>|int	Rows or <0 on error
	 */
	protected function fetchRowsFromSql($sql)
	{
		$rows = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		while (is_object($obj = $this->db->fetch_object($resql))) {
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
}
