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

	/** Move the existing sales text for display without changing its stored stable key.
	 * @param list<array<string,mixed>> $sections Report sections
	 * @return list<array<string,mixed>>
	 */
	public static function presentationSections(array $sections): array
	{
		$salesIndex = null;
		$salesField = null;
		foreach ($sections as $index => $row) {
			$code = $row['section']->section_code;
			if ($code === 'RES_SALES') {
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
	public function options(array $tree): array
	{
		$options = array('plants' => array(), 'products' => array(), 'placements' => array());
		foreach ($tree['powerplants'] ?? array() as $plant) {
			$plantId = (int) (is_object($plant) ? $plant->fk_powerplant : $plant['id']);
			$options['plants'][$plantId] = is_object($plant) ? trim($plant->powerplant_ref.' '.$plant->powerplant_label) : trim($plant['ref'].' '.$plant['label']);
		}
		if (!$options['plants']) {
			$this->error = 'NoPowerPlantLinked';
			return array();
		}
		$products = array();
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
				$options['products'][$plant][$product] = array('label' => $label, 'maximum' => (int) $row->max_microinverters_per_string ?: null);
			}
		}
		$this->db->free($result);
		return $options;
	}

	/**
	 * @return array{placements:array<string,array<string,mixed>>,roofs:list<array<string,mixed>>,strings:list<array<string,mixed>>,initialized:bool}|null
	 */
	public function load(PowerPlantPVReport $report): ?array
	{
		$data = array('placements' => array(), 'roofs' => array(), 'strings' => array());
		$table = $this->db->prefix().'powerplantpv_report_residential';
		$sql = 'SELECT rowid, row_type, position, fk_parent, placement_type, fk_component, fk_powerplant, fk_product,';
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

	/** @param array<string,mixed> $input Submitted residential rows
	 * @param array<string,array> $options Eligible equipment and products
	 * @return array{data:array<string,array>,errors:list<string>} Normalized data and translation keys
	 */
	public static function normalize(array $input, array $options, bool $finalize): array
	{
		$data = array('placements' => array(), 'roofs' => array(), 'strings' => array());
		$errors = array();
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
		if ($finalize && ($definition === null || $quantity === null || count($serials) !== $quantity || in_array('', $serials, true))) {
			$errors[] = 'PVResidentialMicroIncomplete';
		}
		if ($finalize && isset($definition['maximum']) && ($quantity > $definition['maximum'] || count($serials) > $definition['maximum'])) {
			$errors[] = 'PVResidentialMicroExceeded';
		}
		return array(
			'micro' => 1, 'fk_powerplant' => isset($options['plants'][$plant ?? 0]) ? $plant : null,
			'fk_product' => $definition === null ? null : $product, 'reference_label' => $definition['label'] ?? '',
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
