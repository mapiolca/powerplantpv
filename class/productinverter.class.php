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
 * \file       htdocs/powerplantpv/class/productinverter.class.php
 * \ingroup    powerplantpv
 * \brief      Product inverter detailed characteristics storage helper
 */

/**
 * Product inverter technical data attached to a native Dolibarr product.
 */
class ProductInverter
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
	 * @var int Product rowid
	 */
	public $fk_product = 0;

	/**
	 * @var int Entity
	 */
	public $entity = 1;

	/**
	 * @var array<string,mixed> Field values
	 */
	public $data = array();

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Inverter fields stored outside native product data.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function getInverterFields()
	{
		return array(
			'pv_max_power' => array('label' => 'PVInverterPVMaxPower', 'type' => 'double'),
			'dc_max_voltage' => array('label' => 'PVInverterDCMaxVoltage', 'type' => 'double'),
			'startup_voltage' => array('label' => 'PVInverterStartupVoltage', 'type' => 'double'),
			'mppt_voltage_min' => array('label' => 'PVInverterMPPTVoltageMin', 'type' => 'double'),
			'mppt_voltage_max' => array('label' => 'PVInverterMPPTVoltageMax', 'type' => 'double'),
			'nominal_dc_voltage' => array('label' => 'PVInverterNominalDCVoltage', 'type' => 'double'),
			'ac_nominal_power' => array('label' => 'PVInverterACNominalPower', 'type' => 'double'),
			'ac_max_power' => array('label' => 'PVInverterACMaxPower', 'type' => 'double'),
			'ac_apparent_power' => array('label' => 'PVInverterACApparentPower', 'type' => 'double'),
			'ac_nominal_voltage' => array('label' => 'PVInverterACNominalVoltage', 'type' => 'varchar', 'numeric' => 1),
			'grid_frequency' => array('label' => 'PVInverterGridFrequency', 'type' => 'varchar', 'numeric' => 1),
			'ac_max_output_current' => array('label' => 'PVInverterACMaxOutputCurrent', 'type' => 'double'),
			'phase_count' => array('label' => 'PVInverterPhaseCount', 'type' => 'int'),
			'power_factor' => array('label' => 'PVInverterPowerFactor', 'type' => 'varchar', 'numeric' => 1),
			'thd' => array('label' => 'PVInverterTHD', 'type' => 'varchar', 'numeric' => 1),
			'backup_nominal_power' => array('label' => 'PVInverterBackupNominalPower', 'type' => 'double'),
			'backup_peak_power' => array('label' => 'PVInverterBackupPeakPower', 'type' => 'double'),
			'backup_peak_duration' => array('label' => 'PVInverterBackupPeakDuration', 'type' => 'double'),
			'backup_transfer_time' => array('label' => 'PVInverterBackupTransferTime', 'type' => 'double'),
			'backup_nominal_voltage' => array('label' => 'PVInverterBackupNominalVoltage', 'type' => 'varchar', 'numeric' => 1),
			'backup_max_current' => array('label' => 'PVInverterBackupMaxCurrent', 'type' => 'double'),
			'backup_thd' => array('label' => 'PVInverterBackupTHD', 'type' => 'varchar', 'numeric' => 1),
			'max_unbalanced_output' => array('label' => 'PVInverterMaxUnbalancedOutput', 'type' => 'double'),
			'max_efficiency' => array('label' => 'PVInverterMaxEfficiency', 'type' => 'double'),
			'european_efficiency' => array('label' => 'PVInverterEuropeanEfficiency', 'type' => 'double'),
			'dc_switch' => array('label' => 'PVInverterDCSwitch', 'type' => 'bool'),
			'dc_spd' => array('label' => 'PVInverterDCSPD', 'type' => 'varchar'),
			'ac_spd' => array('label' => 'PVInverterACSPD', 'type' => 'varchar'),
			'afci' => array('label' => 'PVInverterAFCI', 'type' => 'bool'),
			'pid_recovery' => array('label' => 'PVInverterPIDRecovery', 'type' => 'bool'),
			'anti_islanding' => array('label' => 'PVInverterAntiIslanding', 'type' => 'bool'),
			'dc_reverse_polarity_protection' => array('label' => 'PVInverterDCReversePolarity', 'type' => 'bool'),
			'insulation_monitoring' => array('label' => 'PVInverterInsulationMonitoring', 'type' => 'bool'),
			'residual_current_monitoring' => array('label' => 'PVInverterResidualCurrentMonitoring', 'type' => 'bool'),
			'ip_rating' => array('label' => 'PVInverterIPRating', 'type' => 'varchar'),
			'operating_temperature' => array('label' => 'PVInverterOperatingTemperature', 'type' => 'varchar', 'numeric' => 1),
			'relative_humidity' => array('label' => 'PVInverterRelativeHumidity', 'type' => 'varchar', 'numeric' => 1),
			'cooling' => array('label' => 'PVInverterCooling', 'type' => 'varchar'),
			'max_altitude' => array('label' => 'PVInverterMaxAltitude', 'type' => 'int'),
			'noise' => array('label' => 'PVInverterNoise', 'type' => 'varchar', 'numeric' => 1),
			'topology' => array('label' => 'PVInverterTopology', 'type' => 'varchar'),
			'night_consumption' => array('label' => 'PVInverterNightConsumption', 'type' => 'varchar', 'numeric' => 1),
			'display_type' => array('label' => 'PVInverterDisplayType', 'type' => 'varchar'),
			'communication_interfaces' => array('label' => 'PVInverterCommunicationInterfaces', 'type' => 'varchar'),
			'dc_connector' => array('label' => 'PVInverterDCConnector', 'type' => 'varchar'),
			'ac_connector' => array('label' => 'PVInverterACConnector', 'type' => 'varchar'),
			'mounting' => array('label' => 'PVInverterMounting', 'type' => 'varchar'),
			'warranty' => array('label' => 'PVInverterWarranty', 'type' => 'varchar'),
			'certifications' => array('label' => 'PVInverterCertifications', 'type' => 'text'),
		);
	}

	/**
	 * MPPT fields.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function getMpptFields()
	{
		return array(
			'position' => array('label' => 'PVInverterMPPTPosition', 'type' => 'int'),
			'label' => array('label' => 'PVInverterMPPTLabel', 'type' => 'varchar'),
			'voltage_min' => array('label' => 'PVInverterMPPTVoltageMin', 'type' => 'double'),
			'voltage_max' => array('label' => 'PVInverterMPPTVoltageMax', 'type' => 'double'),
			'max_input_current' => array('label' => 'PVInverterMPPTMaxInputCurrent', 'type' => 'double'),
			'max_short_circuit_current' => array('label' => 'PVInverterMPPTMaxShortCircuitCurrent', 'type' => 'double'),
			'max_dc_power' => array('label' => 'PVInverterMPPTMaxDCPower', 'type' => 'double'),
			'note_private' => array('label' => 'PVInverterNotes', 'type' => 'text'),
		);
	}

	/**
	 * PV input fields.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function getPvInputFields()
	{
		return array(
			'position' => array('label' => 'PVInverterPVInputPosition', 'type' => 'int'),
			'label' => array('label' => 'PVInverterPVInputLabel', 'type' => 'varchar'),
			'max_input_current' => array('label' => 'PVInverterPVInputMaxInputCurrent', 'type' => 'double'),
			'max_short_circuit_current' => array('label' => 'PVInverterPVInputMaxShortCircuitCurrent', 'type' => 'double'),
			'connector_type' => array('label' => 'PVInverterPVInputConnectorType', 'type' => 'varchar'),
			'note_private' => array('label' => 'PVInverterNotes', 'type' => 'text'),
		);
	}

	/**
	 * Fetch inverter by product.
	 *
	 * @param int $fkProduct Product id
	 * @return int 1 if found, 0 if not found, <0 if error
	 */
	public function fetchByProduct($fkProduct)
	{
		$sql = 'SELECT '.$this->getInverterSelectFields();
		$sql .= ' FROM '.$this->db->prefix().'powerplantpv_product_inverter';
		$sql .= ' WHERE fk_product = '.((int) $fkProduct);
		$sql .= ' AND entity IN ('.getEntity('product').')';
		$sql .= ' ORDER BY entity DESC';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		$obj = $this->db->fetch_object($resql);
		if (!$obj) {
			return 0;
		}

		$this->setInverterFromObject($obj);
		return 1;
	}

	/**
	 * Ensure an inverter parent row exists for a product.
	 *
	 * @param int  $fkProduct Product id
	 * @param User $user      Current user
	 * @return int Inverter id, <0 if error
	 */
	public function ensureForProduct($fkProduct, User $user)
	{
		global $conf;

		$result = $this->fetchByProduct($fkProduct);
		if ($result < 0) {
			return -1;
		}
		if ($result > 0) {
			return $this->id;
		}

		$sql = 'INSERT INTO '.$this->db->prefix().'powerplantpv_product_inverter';
		$sql .= ' (fk_product, entity, datec, fk_user_creat)';
		$sql .= ' VALUES ('.((int) $fkProduct).', '.((int) $conf->entity).", '".$this->db->idate(dol_now())."', ".((int) $user->id).')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		$this->id = (int) $this->db->last_insert_id($this->db->prefix().'powerplantpv_product_inverter');
		$this->rowid = $this->id;
		$this->fk_product = (int) $fkProduct;
		$this->entity = (int) $conf->entity;

		return $this->id;
	}

	/**
	 * Save inverter data for a product.
	 *
	 * @param int          $fkProduct Product id
	 * @param array<string,mixed> $data Field values
	 * @param User         $user      Current user
	 * @return int Inverter id, <0 if error
	 */
	public function saveForProduct($fkProduct, array $data, User $user)
	{
		if (!$this->validateNumericData(self::getInverterFields(), $data)) {
			return -1;
		}

		$id = $this->ensureForProduct($fkProduct, $user);
		if ($id < 0) {
			return -1;
		}

		$sets = $this->buildSetSql(self::getInverterFields(), $data);
		$sets[] = 'fk_user_modif = '.((int) $user->id);

		$sql = 'UPDATE '.$this->db->prefix().'powerplantpv_product_inverter';
		$sql .= ' SET '.implode(', ', $sets);
		$sql .= ' WHERE rowid = '.((int) $id);
		$sql .= ' AND entity IN ('.getEntity('product').')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		$this->fetchByProduct($fkProduct);
		return $this->id;
	}

	/**
	 * Fetch MPPT rows.
	 *
	 * @param int $fkInverter Inverter id
	 * @return array<int,object>
	 */
	public function fetchMppts($fkInverter)
	{
		$rows = array();

		$sql = 'SELECT '.$this->getMpptSelectFields();
		$sql .= ' FROM '.$this->db->prefix().'powerplantpv_product_inverter_mppt';
		$sql .= ' WHERE fk_inverter = '.((int) $fkInverter);
		$sql .= ' AND entity IN ('.getEntity('product').')';
		$sql .= ' ORDER BY position ASC, rowid ASC';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return $rows;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$rows[] = $obj;
		}

		return $rows;
	}

	/**
	 * Fetch one MPPT row.
	 *
	 * @param int $mpptId     MPPT id
	 * @param int $fkInverter Optional inverter id
	 * @return object|null
	 */
	public function fetchMppt($mpptId, $fkInverter = 0)
	{
		$sql = 'SELECT '.$this->getMpptSelectFields();
		$sql .= ' FROM '.$this->db->prefix().'powerplantpv_product_inverter_mppt';
		$sql .= ' WHERE rowid = '.((int) $mpptId);
		if ($fkInverter > 0) {
			$sql .= ' AND fk_inverter = '.((int) $fkInverter);
		}
		$sql .= ' AND entity IN ('.getEntity('product').')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return null;
		}

		return $this->db->fetch_object($resql);
	}

	/**
	 * Save a MPPT row.
	 *
	 * @param int          $fkInverter Inverter id
	 * @param int          $mpptId     MPPT id, 0 for create
	 * @param array<string,mixed> $data Field values
	 * @return int MPPT id, <0 if error
	 */
	public function saveMppt($fkInverter, $mpptId, array $data)
	{
		global $conf;

		if (!$this->validateNumericData(self::getMpptFields(), $data)) {
			return -1;
		}

		if ($mpptId > 0) {
			$sets = $this->buildSetSql(self::getMpptFields(), $data);
			$sql = 'UPDATE '.$this->db->prefix().'powerplantpv_product_inverter_mppt';
			$sql .= ' SET '.implode(', ', $sets);
			$sql .= ' WHERE rowid = '.((int) $mpptId);
			$sql .= ' AND fk_inverter = '.((int) $fkInverter);
			$sql .= ' AND entity IN ('.getEntity('product').')';

			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->setError($this->db->lasterror());
				return -1;
			}

			return $mpptId;
		}

		$fields = array('fk_inverter', 'entity');
		$values = array((int) $fkInverter, (int) $conf->entity);
		foreach (self::getMpptFields() as $key => $spec) {
			$fields[] = $key;
			$values[] = $this->sqlValue($spec['type'], isset($data[$key]) ? $data[$key] : null);
		}

		$sql = 'INSERT INTO '.$this->db->prefix().'powerplantpv_product_inverter_mppt';
		$sql .= ' ('.implode(', ', $fields).') VALUES ('.implode(', ', $values).')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		return (int) $this->db->last_insert_id($this->db->prefix().'powerplantpv_product_inverter_mppt');
	}

	/**
	 * Delete a MPPT and its PV inputs.
	 *
	 * @param int $mpptId     MPPT id
	 * @param int $fkInverter Inverter id
	 * @return int >0 if ok, <0 if error
	 */
	public function deleteMppt($mpptId, $fkInverter)
	{
		$sql = 'DELETE FROM '.$this->db->prefix().'powerplantpv_product_inverter_pvinput';
		$sql .= ' WHERE fk_mppt = '.((int) $mpptId);
		$sql .= ' AND entity IN ('.getEntity('product').')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		$sql = 'DELETE FROM '.$this->db->prefix().'powerplantpv_product_inverter_mppt';
		$sql .= ' WHERE rowid = '.((int) $mpptId);
		$sql .= ' AND fk_inverter = '.((int) $fkInverter);
		$sql .= ' AND entity IN ('.getEntity('product').')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		return 1;
	}

	/**
	 * Fetch PV inputs grouped by MPPT id.
	 *
	 * @param array<int,int> $mpptIds MPPT ids
	 * @return array<int,array<int,object>>
	 */
	public function fetchInputsByMppts(array $mpptIds)
	{
		$rows = array();
		$ids = array();
		foreach ($mpptIds as $id) {
			if ((int) $id > 0) {
				$ids[] = (int) $id;
			}
		}
		if (empty($ids)) {
			return $rows;
		}

		$sql = 'SELECT '.$this->getPvInputSelectFields();
		$sql .= ' FROM '.$this->db->prefix().'powerplantpv_product_inverter_pvinput';
		$sql .= ' WHERE fk_mppt IN ('.implode(',', $ids).')';
		$sql .= ' AND entity IN ('.getEntity('product').')';
		$sql .= ' ORDER BY fk_mppt ASC, position ASC, rowid ASC';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return $rows;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$fkMppt = (int) $obj->fk_mppt;
			if (empty($rows[$fkMppt])) {
				$rows[$fkMppt] = array();
			}
			$rows[$fkMppt][] = $obj;
		}

		return $rows;
	}

	/**
	 * Fetch one PV input.
	 *
	 * @param int $inputId PV input id
	 * @param int $fkMppt  Optional MPPT id
	 * @return object|null
	 */
	public function fetchInput($inputId, $fkMppt = 0)
	{
		$sql = 'SELECT '.$this->getPvInputSelectFields();
		$sql .= ' FROM '.$this->db->prefix().'powerplantpv_product_inverter_pvinput';
		$sql .= ' WHERE rowid = '.((int) $inputId);
		if ($fkMppt > 0) {
			$sql .= ' AND fk_mppt = '.((int) $fkMppt);
		}
		$sql .= ' AND entity IN ('.getEntity('product').')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return null;
		}

		return $this->db->fetch_object($resql);
	}

	/**
	 * Save a PV input row.
	 *
	 * @param int          $fkMppt  MPPT id
	 * @param int          $inputId PV input id, 0 for create
	 * @param array<string,mixed> $data Field values
	 * @return int PV input id, <0 if error
	 */
	public function saveInput($fkMppt, $inputId, array $data)
	{
		global $conf;

		if (!$this->validateNumericData(self::getPvInputFields(), $data)) {
			return -1;
		}

		if ($inputId > 0) {
			$sets = $this->buildSetSql(self::getPvInputFields(), $data);
			$sql = 'UPDATE '.$this->db->prefix().'powerplantpv_product_inverter_pvinput';
			$sql .= ' SET '.implode(', ', $sets);
			$sql .= ' WHERE rowid = '.((int) $inputId);
			$sql .= ' AND fk_mppt = '.((int) $fkMppt);
			$sql .= ' AND entity IN ('.getEntity('product').')';

			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->setError($this->db->lasterror());
				return -1;
			}

			return $inputId;
		}

		$fields = array('fk_mppt', 'entity');
		$values = array((int) $fkMppt, (int) $conf->entity);
		foreach (self::getPvInputFields() as $key => $spec) {
			$fields[] = $key;
			$values[] = $this->sqlValue($spec['type'], isset($data[$key]) ? $data[$key] : null);
		}

		$sql = 'INSERT INTO '.$this->db->prefix().'powerplantpv_product_inverter_pvinput';
		$sql .= ' ('.implode(', ', $fields).') VALUES ('.implode(', ', $values).')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		return (int) $this->db->last_insert_id($this->db->prefix().'powerplantpv_product_inverter_pvinput');
	}

	/**
	 * Delete a PV input.
	 *
	 * @param int $inputId PV input id
	 * @param int $fkMppt  MPPT id
	 * @return int >0 if ok, <0 if error
	 */
	public function deleteInput($inputId, $fkMppt)
	{
		$sql = 'DELETE FROM '.$this->db->prefix().'powerplantpv_product_inverter_pvinput';
		$sql .= ' WHERE rowid = '.((int) $inputId);
		$sql .= ' AND fk_mppt = '.((int) $fkMppt);
		$sql .= ' AND entity IN ('.getEntity('product').')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		return 1;
	}

	/**
	 * Reject non-numeric values for technical measurements.
	 *
	 * Values are normalized at the UI/import boundary. This guard also protects
	 * direct class consumers from silently casting text to zero.
	 *
	 * @param array<string,array<string,mixed>> $fields Field specs
	 * @param array<string,mixed>                $data   Field values
	 * @return bool True when all numeric values are valid
	 */
	protected function validateNumericData(array $fields, array $data)
	{
		foreach ($fields as $key => $spec) {
			$type = isset($spec['type']) ? (string) $spec['type'] : 'varchar';
			$isnumeric = ($type === 'double' || $type === 'int' || !empty($spec['numeric']));
			$value = array_key_exists($key, $data) ? $data[$key] : null;
			if (!$isnumeric || $value === null || $value === '') {
				continue;
			}
			if ((!is_int($value) && !is_float($value) && !is_numeric($value))
				|| ($type === 'int' && (float) ((int) $value) !== (float) $value)
			) {
				$this->setError('ProductTechnicalNumericValueRequired');
				return false;
			}
		}

		return true;
	}

	/**
	 * Build SQL SET parts.
	 *
	 * @param array<string,array<string,mixed>> $fields Field specs
	 * @param array<string,mixed>                $data   Field values
	 * @return array<int,string>
	 */
	protected function buildSetSql(array $fields, array $data)
	{
		$sets = array();
		foreach ($fields as $key => $spec) {
			$sets[] = $key.' = '.$this->sqlValue($spec['type'], isset($data[$key]) ? $data[$key] : null);
		}
		return $sets;
	}

	/**
	 * Convert a value to SQL.
	 *
	 * @param string $type Field type
	 * @param mixed  $value Field value
	 * @return string
	 */
	protected function sqlValue($type, $value)
	{
		if ($value === null || $value === '') {
			return 'null';
		}

		if ($type === 'int' || $type === 'bool') {
			return (string) ((int) $value);
		}
		if ($type === 'double') {
			return (string) price2num($value, 'MT');
		}

		return "'".$this->db->escape((string) $value)."'";
	}

	/**
	 * Build explicit inverter select field list.
	 *
	 * @return string
	 */
	protected function getInverterSelectFields()
	{
		$fields = array('rowid', 'fk_product', 'entity');
		$fields = array_merge($fields, array_keys(self::getInverterFields()));
		$fields = array_merge($fields, array('datec', 'tms', 'fk_user_creat', 'fk_user_modif'));
		return implode(', ', $fields);
	}

	/**
	 * Build explicit MPPT select field list.
	 *
	 * @return string
	 */
	protected function getMpptSelectFields()
	{
		$fields = array('rowid', 'fk_inverter', 'entity');
		$fields = array_merge($fields, array_keys(self::getMpptFields()));
		return implode(', ', $fields);
	}

	/**
	 * Build explicit PV input select field list.
	 *
	 * @return string
	 */
	protected function getPvInputSelectFields()
	{
		$fields = array('rowid', 'fk_mppt', 'entity');
		$fields = array_merge($fields, array_keys(self::getPvInputFields()));
		return implode(', ', $fields);
	}

	/**
	 * Populate inverter properties from a database row.
	 *
	 * @param object $obj Database row
	 * @return void
	 */
	protected function setInverterFromObject($obj)
	{
		$this->id = (int) $obj->rowid;
		$this->rowid = (int) $obj->rowid;
		$this->fk_product = (int) $obj->fk_product;
		$this->entity = (int) $obj->entity;
		$this->data = array();

		foreach (self::getInverterFields() as $key => $spec) {
			$this->data[$key] = isset($obj->{$key}) ? $obj->{$key} : null;
		}
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
