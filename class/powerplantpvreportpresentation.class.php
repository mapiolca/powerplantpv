<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/** Shared presentation of installed equipment and frozen report data. */
class PowerPlantPVReportPresentation
{
	/** Allow a third answer on the standard cabinet cleaning check, including legacy snapshots. */
	public static function allowsNotApplicable(string $code, string $type): bool
	{
		return $code === 'BOX_JOINTS_HINGES_CLEANING' && in_array($type, array('yesno', 'boolean'), true);
	}

	/**
	 * Return the labels of stored choices, including legacy report field types.
	 * @param object $field Report field
	 * @param Translate $langs Output language
	 * @return array<string|int,string>|null Null for fields without predefined choices
	 */
	public static function choiceOptions($field, $langs)
	{
		$type = (string) $field->field_type;
		if (in_array($type, array('checkbox', 'yesno', 'boolean'), true)) {
			$options = array('0' => $langs->transnoentities('No'), '1' => $langs->transnoentities('Yes'));
			if (self::allowsNotApplicable((string) $field->field_code, $type)) {
				$options = array('' => $langs->transnoentities('PowerPlantPVDataNotFilled')) + $options;
				$options['not_applicable'] = $langs->transnoentities('PowerPlantPVReportConformityNotApplicable');
			}
			return $options;
		}
		if ($type === 'conformity_so_valid_obs' || $type === 'status') {
			return array(
				'valid' => $langs->transnoentities('PowerPlantPVReportConformityValid'),
				'observation' => $langs->transnoentities($field->field_code === 'RES_MONITORING' ? 'PVResidentialMonitoringNotCompliant' : 'PowerPlantPVReportConformityObservation'),
				'not_applicable' => $langs->transnoentities('PowerPlantPVReportConformityNotApplicable'),
			);
		}
		if ($type !== 'select' && $type !== 'multiselect') {
			return null;
		}
		$options = array();
		$snapshot = json_decode((string) ($field->options_snapshot ?? ''), true);
		if (is_array($snapshot)) {
			foreach ($snapshot as $option) {
				if (!is_array($option) || !isset($option['code']) || (string) $option['code'] === '') {
					continue;
				}
				$label = $langs->defaultlang === 'en_US' ? ($option['label_en'] ?? '') : '';
				$options[(string) $option['code']] = (string) ($label !== '' ? $label : ($option['label'] ?? ''));
			}
		}

		return $options;
	}

	/**
	 * @param array<string|int,string>|null $choices Available options
	 * @return array{string,string}|null Yes and no codes for strictly binary fields
	 */
	public static function binaryChoiceCodes(string $type, ?array $choices): ?array
	{
		if ($type === 'multiselect' || $choices === null || count($choices) !== 2) {
			return null;
		}
		if (isset($choices['YES'], $choices['NO'])) {
			return array('YES', 'NO');
		}
		if (isset($choices[1], $choices[0])) {
			return array('1', '0');
		}
		return null;
	}

	/**
	 * Format stored choices for read-only HTML and PDF output.
	 * @param object $field Report field
	 * @param Translate $langs Output language
	 * @return string|null Null for fields without predefined choices
	 */
	public static function formatChoiceValue($field, $langs)
	{
		$options = self::choiceOptions($field, $langs);
		if ($options === null) {
			return null;
		}
		$type = (string) $field->field_type;
		$value = (string) $field->value_text;
		if ($type === 'conformity_so_valid_obs' || $type === 'status') {
			return $options[$value] ?? $langs->transnoentities('PowerPlantPVDataNotFilled');
		}
		if ($value === '') {
			return '';
		}
		$codes = $type === 'multiselect' ? preg_split('/\R/', $value) : array($value);
		$labels = array();
		foreach ($codes ?: array() as $code) {
			$code = trim($code);
			if ($code !== '') {
				$labels[] = ($options[$code] ?? '') !== '' ? $options[$code] : $langs->transnoentities('Unknown');
			}
		}
		return implode(', ', $labels);
	}

	/** @param string $code @return bool */
	public static function isRetiredProductionField($code)
	{
		return self::isInverterInjectionField($code)
			|| in_array($code, array('ANNUAL_PRODUCTION', 'ANNUAL_PRODUCTION_N_MINUS_1', 'SELF_CONSUMPTION', 'SELF_CONSUMPTION_N_MINUS_1'), true);
	}

	/** Identify the retired inverter fields without matching the common injection meter. */
	public static function isInverterInjectionField(string $code): bool
	{
		return in_array($code, array('INVERTER_INJECTION', 'INVERTER_INJECTION_N_MINUS_1'), true);
	}

	/** @param object $field @param Translate $langs @return string|null */
	public static function inverterIndexLabel($field, $langs)
	{
		$code = (string) ($field->field_code ?? '');
		if ($code === 'INVERTER_PRODUCTION') { return $langs->transnoentities('PowerPlantPVInverterProductionIndexCurrent'); }
		if ($code === 'INVERTER_PRODUCTION_N_MINUS_1') { return $langs->transnoentities('PowerPlantPVInverterProductionIndexPrevious'); }
		return null;
	}

	/**
	 * Energy used on site between comparable production and injection meter readings.
	 * Previous index dates are frozen with their numeric values. No estimate if an
	 * index or date is absent, the start dates differ, or a counter has decreased.
	 * @param array<int,object> $fields Visible fields of the common meters section
	 * @param int $endDate Timestamp of the current intervention reading
	 * @return float|null Energy in kWh (zero is a valid result)
	 */
	public static function selfConsumptionBetweenReadings($fields, $endDate)
	{
		$required = array('PRODUCTION_INDEX', 'PRODUCTION_INDEX_N_MINUS_1', 'INJECTION_INDEX', 'INJECTION_INDEX_N_MINUS_1');
		$values = array();
		$previousDates = array();
		foreach ($fields as $field) {
			$code = (string) ($field->field_code ?? '');
			if (!in_array($code, $required, true)) { continue; }
			if (($field->unit ?? '') !== 'kWh' || !is_numeric($field->value_number ?? null) || isset($values[$code])) { return null; }
			$value = (float) $field->value_number;
			if (!is_finite($value) || $value < 0) { return null; }
			$values[$code] = $value;
			if (substr($code, -10) === '_N_MINUS_1') {
				$rawDate = $field->value_date ?? null;
				$previousDates[] = is_numeric($rawDate) ? (int) $rawDate : (is_string($rawDate) ? (int) strtotime($rawDate) : 0);
			}
		}
		if (count($values) !== 4 || count($previousDates) !== 2 || $previousDates[0] <= 0
			|| $previousDates[0] !== $previousDates[1] || $previousDates[0] >= $endDate) {
			return null;
		}
		$result = self::calculateSelfConsumption($values['PRODUCTION_INDEX_N_MINUS_1'], $values['PRODUCTION_INDEX'], $values['INJECTION_INDEX_N_MINUS_1'], $values['INJECTION_INDEX']);
		return $result['energy'] ?? null;
	}

	/**
	 * Calculate self-consumption from cumulative kWh readings covering the same period.
	 * @return array{energy:float,rate:float|null}|null Rate is undefined when production is zero.
	 */
	public static function calculateSelfConsumption(float $previousProduction, float $currentProduction, float $previousInjection, float $currentInjection): ?array
	{
		foreach (array($previousProduction, $currentProduction, $previousInjection, $currentInjection) as $index) {
			if (!is_finite($index) || $index < 0) {
				return null;
			}
		}
		$production = $currentProduction - $previousProduction;
		$injection = $currentInjection - $previousInjection;
		if ($injection < 0 || $production < $injection) {
			return null;
		}
		$energy = $production - $injection;
		return array('energy' => $energy, 'rate' => $production > 0 ? ($energy / $production) * 100 : null);
	}

	/**
	 * Consolidate raw composition rows before locale-dependent formatting.
	 * @param array<int,array<string,mixed>> $components Raw rows from one plant
	 * @return array<int,array<string,mixed>> Consolidated rows
	 */
	public static function consolidate($components)
	{
		$groups = array();
		foreach ($components as $row) {
			$key = (int) ($row['fk_powerplant'] ?? 0).':'.(int) ($row['fk_product'] ?? 0);
			if ((int) ($row['fk_product'] ?? 0) <= 0) {
				$key .= ':line:'.(int) ($row['id'] ?? count($groups));
			}
			if (!isset($groups[$key])) {
				$groups[$key] = $row;
				$groups[$key]['qty'] = 0;
				$groups[$key]['serial_number'] = '';
				$groups[$key]['multiple_statuses'] = false;
			}
			$groups[$key]['qty'] += (float) ($row['qty'] ?? 0);
			if ((string) ($groups[$key]['status'] ?? '') !== (string) ($row['status'] ?? '')) {
				$groups[$key]['multiple_statuses'] = true;
			}
		}
		return array_values($groups);
	}

	/**
	 * Omit empty legacy manual placeholders; optionally hide disconnected installed inputs.
	 * @param array<int,object> $measures Frozen DC rows
	 * @param bool $hideUnused Screen preference; PDF always passes false
	 * @return array<int,object> Visible rows, with stored data untouched
	 */
	public static function visibleMeasures($measures, $hideUnused = false)
	{
		return array_values(array_filter($measures, static function ($row) use ($hideUnused) {
			if ($hideUnused && (int) ($row->is_connected ?? 1) === 0) { return false; }
			if (strpos((string) ($row->stable_key ?? ''), ':dc:manual:') === false) { return true; }
			foreach (array('inverter_ref', 'inverter_label', 'string_ref', 'open_circuit_voltage', 'polarity_checked', 'insulation_status', 'insulation_positive_to_ground', 'insulation_negative_to_ground', 'positive_to_ground_voltage', 'negative_to_ground_voltage', 'observation') as $field) {
				$value = $row->{$field} ?? null;
				if ($value !== null && trim((string) $value) !== '' && (string) $value !== '-1') { return true; }
			}
			return false;
		}));
	}

	/**
	 * Count only explicit conformity answers and installed DC inputs for a plant summary.
	 * @param array<int,array<string,mixed>> $sections Frozen section rows
	 * @return array{checks:int,completed:int,observations:int,connected:int,inputs:int}
	 */
	public static function indicators($sections)
	{
		$stats = array('checks' => 0, 'completed' => 0, 'observations' => 0, 'connected' => 0, 'inputs' => 0);
		foreach ($sections as $row) {
			foreach (($row['fields'] ?? array()) as $field) {
				if (empty($field->visible_pdf) || (string) $field->field_type !== 'conformity_so_valid_obs') { continue; }
				$stats['checks']++;
				if (in_array((string) $field->value_text, array('valid', 'observation', 'not_applicable'), true)) { $stats['completed']++; }
				if ((string) $field->value_text === 'observation') { $stats['observations']++; }
			}
			foreach (self::visibleMeasures($row['dc_measures'] ?? array(), false) as $measure) {
				$stats['inputs']++;
				if ((int) $measure->is_connected === 1) { $stats['connected']++; }
			}
		}
		return $stats;
	}

	/**
	 * Format an identity from frozen equipment or DC measure properties.
	 * @param object $row Equipment or measure
	 * @param bool $serial Include the serial number
	 * @param string $emptyLabel Localized fallback for an unattached manual line
	 * @return string Plain text, to escape at the destination
	 */
	public static function identity($row, $serial = true, $emptyLabel = '')
	{
		$marker = trim((string) ($row->equipment_marker ?? $row->inverter_marker ?? ''));
		$name = trim((string) ($row->equipment_identification ?? $row->inverter_identification ?? ''));
		if ($marker === '' && $name === '') {
			$name = trim((string) ($row->equipment_ref ?? $row->inverter_ref ?? '').' '.(string) ($row->equipment_label ?? $row->inverter_label ?? ''));
			// The source line distinguishes identical legacy equipment without inventing a physical marker.
			$id = (int) ($row->fk_powerplant_line ?? $row->fk_inverter ?? 0);
			if ($id > 0) {
				$name .= ' #'.$id;
			}
		}
		$parts = array_filter(array($marker, $name), static function ($part) { return $part !== ''; });
		$number = trim((string) ($row->serial_number ?? $row->inverter_serial ?? ''));
		if ($serial && $number !== '') {
			$parts[] = $number;
		}
		return $parts ? implode(' | ', $parts) : $emptyLabel;
	}

	/**
	 * Group DC measures by installed line, draft inverter, or legacy manual label.
	 * @param array<int,object> $measures Frozen DC measures
	 * @return array<string,array{identity:object,mppts:array<string,array<int,object>>}>
	 */
	public static function groupMeasures($measures)
	{
		$groups = array();
		foreach ($measures as $row) {
			$source = (int) $row->fk_inverter;
			$label = trim((string) ($row->inverter_label ?? ''));
			$key = (int) $row->fk_powerplant.':line:'.$source;
			if ($source <= 0 && preg_match('/:dc:inverter:(new-[a-f0-9]{16}):mppt:/D', (string) $row->stable_key, $match)) {
				$key = (int) $row->fk_powerplant.':completion:'.$match[1];
			} elseif ($source <= 0) {
				$key = (int) $row->fk_powerplant.':manual:'.($label !== '' ? 'label:'.$label : 'row:'.(string) $row->stable_key);
			}
			$mppt = $row->mppt_number === null ? '' : (string) $row->mppt_number;
			if (!isset($groups[$key])) {
				$groups[$key] = array('identity' => $row, 'mppts' => array());
			}
			$groups[$key]['mppts'][$mppt][] = $row;
		}
		uasort($groups, static function ($a, $b) {
			$left = $a['identity'];
			$right = $b['identity'];
			return ((int) $left->fk_powerplant <=> (int) $right->fk_powerplant)
				?: strnatcasecmp(self::identity($left, false), self::identity($right, false))
				?: ((int) $left->fk_inverter <=> (int) $right->fk_inverter);
		});
		foreach ($groups as &$group) {
			uksort($group['mppts'], static function ($a, $b) {
				return (($a === '') <=> ($b === '')) ?: ((int) $a <=> (int) $b);
			});
			foreach ($group['mppts'] as &$rows) {
				usort($rows, static function ($a, $b) {
					return ((int) $a->pv_input_number <=> (int) $b->pv_input_number)
						?: ((int) $a->position <=> (int) $b->position);
				});
			}
			unset($rows);
		}
		unset($group);
		return $groups;
	}

	/**
	 * Prevent spreadsheet formula interpretation without losing serial leading zeroes.
	 * @param string $value Original cell
	 * @return string CSV text cell
	 */
	public static function csvText($value)
	{
		return preg_match('/^[\s\x00-\x20]*[=+@-]/u', $value) || preg_match('/^\d/', $value) ? "'".$value : $value;
	}
}
