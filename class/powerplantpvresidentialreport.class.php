<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/** Residential report rows, separate from the C&I DC test measurements. */
class PowerPlantPVResidentialReport
{
	private DoliDB $db;
	public string $error = '';

	public const PLACEMENTS = array(
		'inverter' => array('PVResidentialInverterLocation', array('INVERTER')),
		'battery' => array('PVResidentialBatteryLocation', array('BATTERY')),
		'enclosure' => array('PVResidentialEnclosureLocation', array('AC_BOX', 'DC_BOX')),
		'charger' => array('PVResidentialChargerLocation', array('EV_CHARGER')),
		'panel' => array('PVResidentialPanelLocation', array('PANEL')),
	);

	public const STAFF_FIELDS = array('RES_ROOF_TEAM' => 'roof_user', 'RES_ELECTRICAL_TEAM' => 'electrical');

	public const COMMUNICATION_SECTION = 'RES_COMMUNICATION';
	public const COMMUNICATION_CONDITIONS = array(
		'RES_CONNECTION_WIFI' => 'WIFI',
		'RES_CONNECTION_SUBSCRIBER' => 'CELLULAR',
		'RES_CONNECTION_SIM' => 'CELLULAR',
	);

	/**
	 * Consolidate old per-inverter values by source plant. Existing plant choices take precedence.
	 * @param array<string,array<string,mixed>> $values Stored fields indexed by stable key
	 * @return array<string,array<string,mixed>>
	 */
	public static function migrateConnections(array $values): array
	{
		$plants = array();
		foreach ($values as $key => $row) {
			if (preg_match('/^section:RES_INVERTER:equipment:powerplant:([1-9][0-9]*):(.+):field:(RES_NETWORK|RES_WIFI_REASON)$/D', $key, $parts)) {
				$plants[(int) $parts[1]][$parts[2]][$parts[3]] = trim((string) ($row['value_text'] ?? ''));
				$plants[(int) $parts[1]][$parts[2]]['label'] = $row['inverter_label'] ?? $parts[2];
			}
		}
		foreach ($plants as $id => $inverters) {
			$prefix = 'section:'.self::COMMUNICATION_SECTION.':powerplant:'.$id.':field:';
			if (isset($values[$prefix.'RES_CONNECTION'])) {
				continue;
			}
			$connections = $reasons = $history = array();
			foreach ($inverters as $inverter) {
				$connection = $inverter['RES_NETWORK'] ?? '';
				$reason = $inverter['RES_WIFI_REASON'] ?? '';
				if ($connection !== '') {
					$connections[$connection] = true;
				}
				if ($reason !== '') {
					$reasons[$reason] = true;
				}
				if ($connection !== '' || $reason !== '') {
					$history[] = $inverter['label'].' · '.implode(' · ', array_filter(array($connection, $reason), 'strlen'));
				}
			}
			$connection = (string) array_key_first($connections);
			if (count($connections) > 1 || count($reasons) > 1 || ($reasons && $connection !== 'WIFI')
				|| ($connection !== '' && !in_array($connection, array('WIFI', 'RJ45', 'OTHER'), true))) {
				$values[$prefix.'RES_CONNECTION_HISTORY'] = array('value_text' => implode("\n", $history));
			} elseif ($connection !== '') {
				$values[$prefix.'RES_CONNECTION'] = array('value_text' => $connection);
				if ($reasons) {
					$values[$prefix.'RES_CONNECTION_WIFI'] = array('value_text' => (string) array_key_first($reasons));
				}
			}
		}
		return $values;
	}

	public static function hasCommunication(array $tree): bool
	{
		foreach ($tree['sections'] ?? array() as $row) {
			if ($row['section']->section_code === self::COMMUNICATION_SECTION) {
				return true;
			}
		}
		return false;
	}

	public const COMMON_PROOF = 'RES_MICRO_PROOF_COMMON';
	public const CHAIN_PROOF = 'RES_MICRO_PROOF_CHAIN';

	public const ORIENTATIONS = array('N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW');

	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/** @param array<string,mixed> $tree Report tree
	 * @return bool
	 */
	public static function hasSections(array $tree): bool
	{
		foreach ($tree['sections'] ?? array() as $row) {
			if ($row['section']->section_code === 'RES_SALES') {
				return true;
			}
		}
		return false;
	}

	/** @return array<string,PowerPlantPVReportField> Proof fields indexed by code. */
	public static function proofFields(array $tree): array
	{
		$fields = array();
		foreach ($tree['sections'] ?? array() as $row) {
			if ($row['section']->section_code !== 'RES_STRINGS') {
				continue;
			}
			foreach ($row['fields'] as $field) {
				if (in_array($field->field_code, array(self::COMMON_PROOF, self::CHAIN_PROOF), true)) {
					$fields[$field->field_code] = $field;
				}
			}
		}
		return $fields;
	}

	/** Move the existing sales text for display without changing its stored stable key.
	 * @param list<array<string,mixed>> $sections Report sections
	 * @return list<array<string,mixed>>
	 */
	public static function presentationSections(array $sections, array $staff = array()): array
	{
		$salesIndex = null;
		$salesField = null;
		foreach ($sections as $index => $row) {
			$code = $row['section']->section_code;
			if ($code === 'RES_SITE') {
				foreach ($row['fields'] as $key => $field) {
					$role = self::STAFF_FIELDS[$field->field_code] ?? '';
					if (isset($staff[$role])) {
						$sections[$index]['fields'][$key] = clone $field;
						$sections[$index]['fields'][$key]->value_text = implode(', ', $staff[$role]);
					}
				}
			} elseif ($code === 'RES_SALES') {
				$salesIndex = $index;
			} elseif ($code === 'RES_WORK') {
				foreach ($row['fields'] as $key => $field) {
					if ($field->field_code === 'RES_SOLD_SCOPE') {
						$salesField = $field;
						unset($sections[$index]['fields'][$key]);
					} elseif (in_array($field->field_code, array('RES_MODULE_LAYOUT', 'RES_INVERTER_LOCATION'), true)) {
						if (trim((string) $field->value_text) === '') {
							unset($sections[$index]['fields'][$key]);
						} else {
							$sections[$index]['fields'][$key] = clone $field;
							$sections[$index]['fields'][$key]->readonly = 1;
						}
					}
				}
			}
		}
		if ($salesIndex !== null && $salesField !== null) {
			array_unshift($sections[$salesIndex]['fields'], $salesField);
		}
		return $sections;
	}

	/** @param array<string,mixed> $tree Report tree
	 * @return array<string,array> Options keyed by source IDs, or an empty array on lookup failure
	 */
	public function options(array $tree, int $entity, User $actor): array
	{
		$staff = $this->staffOptions($entity, $actor);
		if ($staff === null) {
			return array();
		}
		$options = array('plants' => array(), 'products' => array(), 'placements' => array(), 'staff' => $staff);
		foreach ($tree['powerplants'] ?? array() as $plant) {
			$plantId = (int) (is_object($plant) ? $plant->fk_powerplant : $plant['id']);
			$options['plants'][$plantId] = is_object($plant) ? trim($plant->powerplant_ref.' '.$plant->powerplant_label) : trim($plant['ref'].' '.$plant['label']);
		}
		if (!$options['plants']) {
			$this->error = 'NoPowerPlantLinked';
			return array();
		}
		$products = array();
		$productLabels = array();
		foreach ($tree['equipment'] ?? array() as $row) {
			$row = (object) $row;
			$plant = (int) $row->fk_powerplant;
			$product = (int) $row->fk_product;
			$sourceLine = (int) $row->fk_powerplant_line;
			if (!isset($options['plants'][$plant]) || $sourceLine < 1) {
				continue;
			}
			$label = trim($row->product_ref.' '.$row->product_label);
			foreach (self::PLACEMENTS as $key => $definition) {
				if (in_array($row->equipment_type, $definition[1], true)) {
					$options['placements'][$key][$sourceLine] = $options['plants'][$plant].' · '.$label;
				}
			}
			if ($row->equipment_type === 'INVERTER' && $product > 0) {
				$productLabels[$product] = $label;
				$products[$product][$plant] = count($options['plants']) > 1 ? $options['plants'][$plant].' · '.$label : $label;
			}
		}
		if (!$products) {
			return $options;
		}
		$sql = 'SELECT fk_product, max_microinverters_per_string FROM '.$this->db->prefix().'powerplantpv_product_inverter';
		$sql .= ' WHERE is_microinverter = 1 AND entity IN ('.$this->db->sanitize(getEntity('product')).')';
		$sql .= ' AND fk_product IN ('.$this->db->sanitize(implode(',', array_keys($products))).')';
		$result = $this->db->query($sql);
		if (!$result) {
			$this->error = $this->db->lasterror();
			return array();
		}
		while ($row = $this->db->fetch_object($result)) {
			$product = (int) $row->fk_product;
			foreach ($products[$product] as $plant => $label) {
				$options['products'][$plant][$product] = array(
					'label' => $label, 'product_label' => $productLabels[$product], 'maximum' => (int) $row->max_microinverters_per_string ?: null,
				);
			}
		}
		$this->db->free($result);
		return $options;
	}

	/** @return array<int,string>|null Active users with access to the report's owning entity. */
	private function staffOptions(int $entity, User $actor): ?array
	{
		global $langs;

		if ($entity <= 0) {
			$this->error = 'ErrorRecordNotFound';
			return null;
		}
		$prefix = $this->db->prefix();
		$sql = 'SELECT u.rowid, u.firstname, u.lastname, u.login FROM '.$prefix.'user AS u WHERE u.statut = 1';
		if (isModEnabled('multicompany') && getDolGlobalInt('MULTICOMPANY_TRANSVERSE_MODE')) {
			// Multicompany grants transverse access through membership, with exceptions for entity administrators.
			$sql .= ' AND (u.entity = 0 OR (u.entity = '.$entity.' AND u.admin = 1)';
			$sql .= ' OR EXISTS (SELECT ug.rowid FROM '.$prefix.'usergroup_user AS ug WHERE ug.fk_user = u.rowid AND ug.entity = '.$entity.'))';
		} else {
			$sql .= ' AND u.entity IN ('.(isModEnabled('multicompany') ? '0, ' : '').$entity.')';
		}
		if (!empty($actor->socid)) {
			$sql .= ' AND u.fk_soc = '.((int) $actor->socid);
		}
		$sql .= ' ORDER BY u.lastname, u.firstname, u.rowid';
		$result = $this->db->query($sql);
		if (!$result) {
			$this->error = $this->db->lasterror();
			return null;
		}
		$users = array();
		$member = new User($this->db);
		while ($row = $this->db->fetch_object($result)) {
			$member->firstname = $row->firstname;
			$member->lastname = $row->lastname;
			$member->login = $row->login;
			$users[(int) $row->rowid] = $member->getFullName($langs) ?: $row->login;
		}
		$this->db->free($result);
		return $users;
	}

	/**
	 * @return array{staff:array<string,array<int,string>>,placements:array<string,array<string,mixed>>,roofs:list<array<string,mixed>>,strings:list<array<string,mixed>>,initialized:bool}|null
	 */
	public function load(PowerPlantPVReport $report): ?array
	{
		$data = array('placements' => array(), 'roofs' => array(), 'strings' => array(), 'staff' => array());
		$table = $this->db->prefix().'powerplantpv_report_residential';
		$sql = 'SELECT rowid, row_type, position, fk_parent, placement_type, fk_component, fk_powerplant, fk_product, fk_micro_proof, fk_user,';
		$sql .= ' reference_label, description, orientation, module_count, row_count, column_count, voltage, micro, quantity, maximum, serial_number';
		$sql .= ' FROM '.$table.' WHERE '.$this->reportFilter($report).' ORDER BY position, rowid';
		$result = $this->db->query($sql);
		if (!$result) {
			$this->error = $this->db->lasterror();
			return null;
		}
		$data['initialized'] = false;
		$serials = array();
		while ($row = $this->db->fetch_object($result)) {
			$record = get_object_vars($row);
			switch ($row->row_type) {
				case 'state':
					$data['initialized'] = true;
					break;
				case 'roof_user':
				case 'electrical':
					$data['staff'][$row->row_type] ??= array();
					if ((int) $row->fk_user > 0) {
						$data['staff'][$row->row_type][(int) $row->fk_user] = (string) $row->reference_label;
					}
					break;
				case 'placement':
					$data['placements'][$row->placement_type] = $record;
					break;
				case 'roof':
					$data['roofs'][] = $record;
					break;
				case 'string':
					$data['strings'][(int) $row->rowid] = $record;
					break;
				case 'serial':
					$serials[(int) $row->fk_parent][] = (string) $row->serial_number;
					break;
			}
		}
		$this->db->free($result);
		foreach ($data['strings'] as $id => &$chain) {
			$chain['serials'] = $serials[$id] ?? array();
		}
		unset($chain);
		$data['strings'] = array_values($data['strings']);
		return $data;
	}

	/** @param array<string,mixed> $tree Report tree
	 * @return list<array<string,mixed>>
	 */
	public static function legacyStrings(array $tree): array
	{
		$strings = array();
		foreach ($tree['sections'] ?? array() as $row) {
			foreach ($row['fields'] as $field) {
				if ($field->value_number === null || $field->value_number === '' || !preg_match('/^RES_STRING_([1-3])_(VOLTAGE|PANELS)$/D', $field->field_code, $match)) {
					continue;
				}
				$key = $match[2] === 'VOLTAGE' ? 'voltage' : 'module_count';
				$strings[(int) $match[1]][$key] = $field->value_number;
			}
		}
		ksort($strings);
		return array_values($strings);
	}

	/** Apply a native form row removal before validating and saving the draft.
	 * @param array<string,mixed> $input Submitted residential fields
	 * @return array<string,mixed>
	 */
	public static function removeSubmittedRow(array $input, string $command): array
	{
		if (preg_match('/^remove_(roofs|strings)_([0-9]+)$/D', $command, $matches)) {
			if (is_array($input[$matches[1]] ?? null)) {
				unset($input[$matches[1]][(int) $matches[2]]);
			}
		} elseif (preg_match('/^remove_serial_([0-9]+)_([0-9]+)$/D', $command, $matches)) {
			$chain = (int) $matches[1];
			$position = (int) $matches[2];
			$row = $input['strings'][$chain] ?? null;
			if (is_array($row) && is_array($row['serials'] ?? null) && array_key_exists($position, $row['serials'])) {
				unset($row['serials'][$position]);
				$row['quantity'] = count($row['serials']) ?: '';
				$input['strings'][$chain] = $row;
			}
		}
		return $input;
	}

	/** @param array<string,mixed> $input Submitted residential rows
	 * @param array<string,array> $options Eligible equipment and products
	 * @return array{data:array<string,array>,errors:list<string>} Normalized data and translation keys
	 */
	public static function normalize(array $input, array $options, bool $finalize): array
	{
		$data = array('placements' => array(), 'roofs' => array(), 'strings' => array());
		$errors = array();
		$data['staff'] = self::normalizeStaff($input['staff'] ?? null, $options['staff'] ?? array(), $options['savedStaff'] ?? array(), $errors);
		$hasLocation = false;
		foreach (self::PLACEMENTS as $type => $definition) {
			$row = $input['placements'][$type] ?? null;
			if (!is_array($row) || ($row['selected'] ?? '') !== '1') {
				continue;
			}
			$description = self::shortText($row['description'] ?? '', 255);
			$component = self::positiveInt($row['fk_component'] ?? null);
			$label = $options['placements'][$type][$component ?? 0] ?? '';
			if ($component !== null && $label === '') {
				$errors[] = 'PVResidentialEquipmentInvalid';
			}
			$data['placements'][$type] = array('description' => $description, 'fk_component' => $component, 'reference_label' => $label);
			$hasLocation = $hasLocation || $description !== '';
		}
		foreach ((is_array($input['roofs'] ?? null) ? $input['roofs'] : array()) as $roof) {
			if (!is_array($roof)) {
				continue;
			}
			$orientation = self::shortText($roof['orientation'] ?? '', 2);
			$row = array(
				'orientation' => in_array($orientation, self::ORIENTATIONS, true) ? $orientation : null,
				'module_count' => self::positiveInt($roof['module_count'] ?? null),
				'row_count' => self::positiveInt($roof['row_count'] ?? null),
				'column_count' => self::positiveInt($roof['column_count'] ?? null),
			);
			if ($finalize && in_array(null, $row, true)) {
				$errors[] = 'PVResidentialRoofIncomplete';
			}
			$data['roofs'][] = $row;
		}
		if ($finalize && !$data['roofs'] && isset($data['placements']['panel'])) {
			$errors[] = 'PVResidentialRoofRequired';
		}
		if ($finalize && !$hasLocation && !$data['roofs']) {
			$errors[] = 'PVResidentialScopeRequired';
		}

		foreach ((is_array($input['strings'] ?? null) ? $input['strings'] : array()) as $chain) {
			if (!is_array($chain)) {
				continue;
			}
			$row = self::normalizeString($chain, $options, $finalize, $errors);
			if ($row !== null) {
				$data['strings'][] = $row;
			}
		}
		if ($finalize && !$data['strings']) {
			$errors[] = 'PVResidentialStringRequired';
		}
		return array('data' => $data, 'errors' => array_values(array_unique($errors)));
	}

	/**
	 * @param mixed $submitted Submitted staff selections
	 * @param array<int,string> $eligible User names by id
	 * @param array<string,array<int,string>> $previous Stored staff assignments
	 * @param list<string> $errors Validation errors
	 * @return array<string,array<int,string>>
	 */
	private static function normalizeStaff($submitted, array $eligible, array $previous, array &$errors): array
	{
		if (!is_array($submitted)) {
			return $previous;
		}
		foreach (self::STAFF_FIELDS as $role) {
			$raw = $submitted[$role] ?? ($role === 'roof_user' ? array() : '');
			$multiple = $role === 'roof_user';
			if (($multiple && !is_array($raw)) || (!$multiple && !is_scalar($raw))) {
				$errors[] = 'PVResidentialStaffInvalid';
				continue;
			}
			$selected = array();
			foreach ($multiple ? $raw : array($raw) as $value) {
				if ($value === '' || (!$multiple && ((string) $value === '-1'))) {
					continue;
				}
				$id = self::positiveInt($value);
				if ($id === null || !isset($eligible[$id])) {
					$errors[] = 'PVResidentialStaffInvalid';
					continue;
				}
				$selected[$id] = $eligible[$id];
			}
			// An ordinary edit must retain previous selections that are no longer offered.
			if ($multiple || !$selected) {
				$selected += array_diff_key($previous[$role] ?? array(), $eligible);
			}
			if ($selected || isset($previous[$role])) {
				$previous[$role] = $selected;
			}
		}
		return $previous;
	}

	/** @param array<string,mixed> $chain Submitted string
	 * @param array<string,array> $options Eligible plants and products
	 * @param list<string> $errors Validation errors
	 * @return array<string,mixed>|null Null for a completely empty conventional string
	 */
	private static function normalizeString(array $chain, array $options, bool $finalize, array &$errors): ?array
	{
		if (($chain['micro'] ?? '') !== '1') {
			$voltage = self::shortText($chain['voltage'] ?? '', 32);
			$modules = self::shortText($chain['module_count'] ?? '', 16);
			if ($voltage === '' && $modules === '') {
				return null;
			}
			$voltage = str_replace(',', '.', $voltage);
			$volts = is_numeric($voltage) && is_finite((float) $voltage) ? (float) $voltage : null;
			$count = self::positiveInt($modules);
			if ($finalize && ($volts === null || $count === null)) {
				$errors[] = 'PVResidentialStringIncomplete';
			}
			return array('micro' => 0, 'voltage' => $volts, 'module_count' => $count);
		}
		$plant = self::positiveInt($chain['fk_powerplant'] ?? null);
		if ($plant === null && count($options['plants']) === 1) {
			$plant = (int) array_key_first($options['plants']);
		}
		$products = $options['products'][$plant ?? 0] ?? array();
		$product = self::positiveInt($chain['fk_product'] ?? null);
		if ($product === null && count($products) === 1) {
			$product = (int) array_key_first($products);
		}
		$definition = $products[$product ?? 0] ?? null;
		$quantity = self::positiveInt($chain['quantity'] ?? null);
		$serials = array();
		foreach ((is_array($chain['serials'] ?? null) ? $chain['serials'] : array()) as $serial) {
			$serials[] = self::shortText($serial, 128);
		}
		$proof = self::positiveInt($chain['fk_micro_proof'] ?? null);
		$hasProof = !empty($options['commonProof']) || isset($options['chainProof'][$proof ?? 0]);
		if ($finalize && $proof !== null && !isset($options['chainProof'][$proof])) {
			$errors[] = 'PVResidentialMicroProofUnavailable';
		}
		if ($finalize && $hasProof) {
			$serials = array_values(array_filter($serials, static fn (string $serial): bool => $serial !== ''));
		}
		if ($finalize && ($definition === null || $quantity === null || count($serials) > $quantity
			|| (!$hasProof && (count($serials) !== $quantity || in_array('', $serials, true))))) {
			$errors[] = 'PVResidentialMicroIncomplete';
		}
		if ($finalize && isset($definition['maximum']) && ($quantity > $definition['maximum'] || count($serials) > $definition['maximum'])) {
			$errors[] = 'PVResidentialMicroExceeded';
		}
		return array(
			'micro' => 1, 'fk_powerplant' => isset($options['plants'][$plant ?? 0]) ? $plant : null,
			'fk_product' => $definition === null ? null : $product, 'reference_label' => $definition['label'] ?? '',
			'fk_micro_proof' => $proof,
			'quantity' => $quantity, 'maximum' => $definition['maximum'] ?? null, 'serials' => $serials,
		);
	}

	/** @param mixed $value Submitted value */
	private static function positiveInt($value): ?int
	{
		if (!is_string($value) && !is_int($value)) {
			return null;
		}
		$number = filter_var($value, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 2147483647)));
		return $number === false ? null : $number;
	}

	/** @param mixed $value Submitted value */
	private static function shortText($value, int $length): string
	{
		return is_scalar($value) ? dol_substr(trim(dol_string_nohtmltag((string) $value)), 0, $length) : '';
	}

	/** Replace report rows inside the caller's locked transaction.
	 * @param array<string,array> $data Normalized report rows
	 */
	public function save(PowerPlantPVReport $report, array $data): bool
	{
		$table = $this->db->prefix().'powerplantpv_report_residential';
		if (!$this->db->query('DELETE FROM '.$table.' WHERE '.$this->reportFilter($report))) {
			$this->error = $this->db->lasterror();
			return false;
		}
		if (!$this->insert($report, 'state', 0, array())) {
			return false;
		}
		foreach ($data['staff'] ?? array() as $role => $members) {
			if (!$members && !$this->insert($report, $role, 0, array())) {
				return false;
			}
			$position = 0;
			foreach ($members as $id => $label) {
				// Freeze the displayed names for the historical report and regenerated PDF.
				if (!$this->insert($report, $role, ++$position, array('fk_user' => (int) $id, 'reference_label' => $label))) {
					return false;
				}
			}
		}
		foreach ($data['placements'] as $key => $row) {
			if (!$this->insert($report, 'placement', 0, $row + array('placement_type' => $key))) {
				return false;
			}
		}
		foreach ($data['roofs'] as $key => $row) {
			if (!$this->insert($report, 'roof', $key + 1, $row)) {
				return false;
			}
		}
		foreach ($data['strings'] as $key => $row) {
			$serials = $row['serials'] ?? array();
			unset($row['serials']);
			if (!$this->insert($report, 'string', $key + 1, $row)) {
				return false;
			}
			$parent = (int) $this->db->last_insert_id($table);
			if ($parent <= 0) {
				$this->error = 'ErrorRecordNotFound';
				return false;
			}
			foreach ($serials as $position => $serial) {
				if (!$this->insert($report, 'serial', $position + 1, array('fk_parent' => $parent, 'serial_number' => $serial))) {
					return false;
				}
			}
		}
		return true;
	}

	private function reportFilter(PowerPlantPVReport $report): string
	{
		return 'entity = '.((int) $report->entity).' AND fk_report = '.((int) $report->id);
	}

	/** @param array<string,int|float|string|null> $data Normalized report row columns */
	private function insert(PowerPlantPVReport $report, string $type, int $position, array $data): bool
	{
		$data += array('row_type' => $type, 'position' => $position, 'entity' => (int) $report->entity, 'fk_report' => (int) $report->id);
		$values = array();
		foreach ($data as $value) {
			$values[] = is_int($value) || is_float($value) ? (string) $value : ($value === null ? 'NULL' : "'".$this->db->escape($value)."'");
		}
		$sql = 'INSERT INTO '.$this->db->prefix().'powerplantpv_report_residential';
		$sql .= ' ('.implode(', ', array_keys($data)).') VALUES ('.implode(', ', $values).')';
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return false;
		}
		return true;
	}
}
