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
 * \file       class/powerplantpvproductdatasource.class.php
 * \ingroup    powerplantpv
 * \brief      Product data source traceability helper.
 */

/**
 * Trace an external data source used to fill product technical data.
 */
class PowerPlantPVProductDataSource
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Error message
	 */
	public $error = '';

	/**
	 * @var array<int,string> Error messages
	 */
	public $errors = array();

	/**
	 * @var int Object id
	 */
	public $id = 0;

	/**
	 * @var int Object rowid
	 */
	public $rowid = 0;

	/**
	 * @var array<string,mixed> Field values
	 */
	public $data = array();

	/**
	 * @var bool|null Cached filename column availability
	 */
	protected $hasFilenameColumn = null;

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Fetch a data source trace by id.
	 *
	 * @param int $id Row id
	 * @return int 1 if found, 0 if not found, <0 on error
	 */
	public function fetch($id)
	{
		$sql = 'SELECT '.$this->getSelectFields();
		$sql .= ' FROM '.$this->db->prefix().'powerplantpv_product_datasource';
		$sql .= ' WHERE rowid = '.((int) $id);
		$sql .= ' AND entity IN ('.getEntity('product').')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		$obj = $this->db->fetch_object($resql);
		if (!$obj) {
			return 0;
		}

		$this->setFromObject($obj);
		return 1;
	}

	/**
	 * Fetch data source traces for a product.
	 *
	 * @param int $fkProduct Product id
	 * @return array<int,object> Rows
	 */
	public function fetchByProduct($fkProduct)
	{
		$rows = array();

		$sql = 'SELECT '.$this->getSelectFields();
		$sql .= ' FROM '.$this->db->prefix().'powerplantpv_product_datasource';
		$sql .= ' WHERE fk_product = '.((int) $fkProduct);
		$sql .= ' AND entity IN ('.getEntity('product').')';
		$sql .= ' ORDER BY datec DESC, rowid DESC';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return $rows;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$rows[] = $obj;
		}
		$this->db->free($resql);

		return $rows;
	}

	/**
	 * Fetch a data source trace by source key in current entity.
	 *
	 * @param string      $source    Source code
	 * @param string|null $dataset   Source dataset
	 * @param string      $sourceKey Source key
	 * @return int 1 if found, 0 if not found, <0 on error
	 */
	public function fetchBySource($source, $dataset, $sourceKey)
	{
		global $conf;

		$sql = 'SELECT '.$this->getSelectFields();
		$sql .= ' FROM '.$this->db->prefix().'powerplantpv_product_datasource';
		$sql .= " WHERE source = '".$this->db->escape($source)."'";
		$sql .= $this->sqlWhereNullableString('source_dataset', $dataset);
		$sql .= " AND source_key = '".$this->db->escape($sourceKey)."'";
		$sql .= ' AND entity = '.((int) $conf->entity);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		$obj = $this->db->fetch_object($resql);
		if (!$obj) {
			return 0;
		}

		$this->setFromObject($obj);
		return 1;
	}

	/**
	 * Insert a data source trace for a product.
	 *
	 * @param int                 $fkProduct Product id
	 * @param array<string,mixed> $data      Trace data
	 * @param User                $user      Current user
	 * @return int Row id, <0 on error
	 */
	public function saveForProduct($fkProduct, array $data, User $user)
	{
		global $conf;

		$fields = array(
			'entity',
			'fk_product',
			'source',
			'source_dataset',
			'source_key',
			'source_name',
			'source_url',
			'raw_json',
			'normalized_json',
			'import_status',
			'datec',
			'fk_user_creat',
		);
		$values = array(
			(int) $conf->entity,
			(int) $fkProduct,
			$this->sqlString($this->requiredString($data, 'source')),
			$this->sqlNullableString(isset($data['source_dataset']) ? $data['source_dataset'] : null),
			$this->sqlString($this->requiredString($data, 'source_key')),
			$this->sqlNullableString(isset($data['source_name']) ? $data['source_name'] : null),
			$this->sqlNullableString(isset($data['source_url']) ? $data['source_url'] : null),
			$this->sqlNullableString(isset($data['raw_json']) ? $data['raw_json'] : null),
			$this->sqlNullableString(isset($data['normalized_json']) ? $data['normalized_json'] : null),
			$this->sqlString(isset($data['import_status']) && $data['import_status'] !== '' ? (string) $data['import_status'] : 'imported'),
			"'".$this->db->idate(dol_now())."'",
			(int) $user->id,
		);
		if ($this->hasFilenameColumn()) {
			array_splice($fields, 7, 0, array('filename'));
			array_splice($values, 7, 0, array($this->sqlNullableString(isset($data['filename']) ? $data['filename'] : null)));
		}

		$sql = 'INSERT INTO '.$this->db->prefix().'powerplantpv_product_datasource';
		$sql .= ' ('.implode(', ', $fields).') VALUES ('.implode(', ', $values).')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		$this->id = (int) $this->db->last_insert_id($this->db->prefix().'powerplantpv_product_datasource');
		$this->rowid = $this->id;
		$this->fetch($this->id);

		return $this->id;
	}

	/**
	 * Insert or update a trace for the same product, but never move a source to another product.
	 *
	 * @param int                 $fkProduct Product id
	 * @param array<string,mixed> $data      Trace data
	 * @param User                $user      Current user
	 * @return int Row id, <0 on error
	 */
	public function upsertForProduct($fkProduct, array $data, User $user)
	{
		$source = $this->requiredString($data, 'source');
		$dataset = isset($data['source_dataset']) ? $data['source_dataset'] : null;
		$sourceKey = $this->requiredString($data, 'source_key');

		$result = $this->fetchBySource($source, $dataset, $sourceKey);
		if ($result < 0) {
			return -1;
		}
		if ($result == 0) {
			return $this->saveForProduct($fkProduct, $data, $user);
		}

		if ((int) $this->data['fk_product'] !== (int) $fkProduct) {
			$this->setError($source === 'pvfree' ? 'PVFreeDataSourceAlreadyLinkedToAnotherProduct' : 'ProductTechnicalImportDataSourceAlreadyLinkedToAnotherProduct');
			return -1;
		}

		$sets = array(
			'fk_product = '.((int) $fkProduct),
			'source_name = '.$this->sqlNullableString(isset($data['source_name']) ? $data['source_name'] : null),
			'source_url = '.$this->sqlNullableString(isset($data['source_url']) ? $data['source_url'] : null),
			'raw_json = '.$this->sqlNullableString(isset($data['raw_json']) ? $data['raw_json'] : null),
			'normalized_json = '.$this->sqlNullableString(isset($data['normalized_json']) ? $data['normalized_json'] : null),
			'import_status = '.$this->sqlString(isset($data['import_status']) && $data['import_status'] !== '' ? (string) $data['import_status'] : 'imported'),
			'fk_user_modif = '.((int) $user->id),
		);
		if ($this->hasFilenameColumn()) {
			array_splice($sets, 3, 0, array('filename = '.$this->sqlNullableString(isset($data['filename']) ? $data['filename'] : null)));
		}

		$sql = 'UPDATE '.$this->db->prefix().'powerplantpv_product_datasource';
		$sql .= ' SET '.implode(', ', $sets);
		$sql .= ' WHERE rowid = '.((int) $this->id);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		$this->fetch($this->id);
		return $this->id;
	}

	/**
	 * Build explicit select fields.
	 *
	 * @return string Field list
	 */
	protected function getSelectFields()
	{
		$fields = array('rowid', 'entity', 'fk_product', 'source', 'source_dataset', 'source_key', 'source_name', 'source_url');
		if ($this->hasFilenameColumn()) {
			$fields[] = 'filename';
		}
		$fields = array_merge($fields, array('raw_json', 'normalized_json', 'import_status', 'datec', 'tms', 'fk_user_creat', 'fk_user_modif'));

		return implode(', ', $fields);
	}

	/**
	 * Test if the filename column exists on the current database.
	 *
	 * @return bool True if the column exists
	 */
	protected function hasFilenameColumn()
	{
		if ($this->hasFilenameColumn !== null) {
			return $this->hasFilenameColumn;
		}

		$table = $this->db->prefix().'powerplantpv_product_datasource';
		$sql = "SHOW COLUMNS FROM ".$this->db->sanitize($table)." LIKE 'filename'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			if (function_exists('dol_syslog')) {
				dol_syslog(__METHOD__.' failed to check filename column: '.$this->db->lasterror(), (defined('LOG_WARNING') ? LOG_WARNING : 4));
			}
			$this->hasFilenameColumn = false;
			return false;
		}

		$this->hasFilenameColumn = ($this->db->num_rows($resql) > 0);
		$this->db->free($resql);

		return $this->hasFilenameColumn;
	}

	/**
	 * Populate properties from database row.
	 *
	 * @param object $obj Database row
	 * @return void
	 */
	protected function setFromObject($obj)
	{
		$this->id = (int) $obj->rowid;
		$this->rowid = (int) $obj->rowid;
		$this->data = array();
		foreach (explode(', ', $this->getSelectFields()) as $field) {
			$this->data[$field] = isset($obj->{$field}) ? $obj->{$field} : null;
		}
		if (!array_key_exists('filename', $this->data)) {
			$this->data['filename'] = null;
		}
	}

	/**
	 * Return a required string from data.
	 *
	 * @param array<string,mixed> $data Data
	 * @param string              $key  Key
	 * @return string Value
	 */
	protected function requiredString(array $data, $key)
	{
		return isset($data[$key]) ? (string) $data[$key] : '';
	}

	/**
	 * Escape a SQL string.
	 *
	 * @param string $value Value
	 * @return string SQL value
	 */
	protected function sqlString($value)
	{
		return "'".$this->db->escape((string) $value)."'";
	}

	/**
	 * Escape a nullable SQL string.
	 *
	 * @param mixed $value Value
	 * @return string SQL value
	 */
	protected function sqlNullableString($value)
	{
		if ($value === null || $value === '') {
			return 'null';
		}

		return $this->sqlString((string) $value);
	}

	/**
	 * Build a nullable string where clause.
	 *
	 * @param string $field Field name
	 * @param mixed  $value Value
	 * @return string SQL clause
	 */
	protected function sqlWhereNullableString($field, $value)
	{
		if ($value === null || $value === '') {
			return ' AND '.$this->db->sanitize($field).' IS NULL';
		}

		return ' AND '.$this->db->sanitize($field)." = '".$this->db->escape((string) $value)."'";
	}

	/**
	 * Register an error.
	 *
	 * @param string $error Error message
	 * @return void
	 */
	protected function setError($error)
	{
		$this->error = $error;
		$this->errors[] = $error;
	}
}
