<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Normalize and validate structured technical values.
 */
class PowerPlantPVTechnicalValue
{
	/** @return array<string,array<string,string>> PV panel field registry */
	public static function getPVPanelFields()
	{
		return array(
			'pmax' => array('label' => 'PVPanelNominalPower', 'type' => 'double', 'unit' => 'Wc'),
			'power_tolerance_min' => array('label' => 'Minimum', 'type' => 'double', 'unit' => '%', 'group' => 'power_tolerance', 'role' => 'min'),
			'power_tolerance_max' => array('label' => 'Maximum', 'type' => 'double', 'unit' => '%', 'group' => 'power_tolerance', 'role' => 'max'),
			'module_efficiency' => array('label' => 'PVPanelModuleEfficiency', 'type' => 'double', 'unit' => '%'),
			'vmp' => array('label' => 'PVPanelVmp', 'type' => 'double', 'unit' => 'V'),
			'imp' => array('label' => 'PVPanelImp', 'type' => 'double', 'unit' => 'A'),
			'voc' => array('label' => 'PVPanelVoc', 'type' => 'double', 'unit' => 'V'),
			'isc' => array('label' => 'PVPanelIsc', 'type' => 'double', 'unit' => 'A'),
			'front_glass_thickness' => array('label' => 'PVPanelFrontGlassThickness', 'type' => 'double', 'unit' => 'mm'),
			'back_glass_thickness' => array('label' => 'PVPanelBackGlassThickness', 'type' => 'double', 'unit' => 'mm'),
			'cable_section' => array('label' => 'PVPanelCableSection', 'type' => 'double', 'unit' => 'mm²'),
			'cable_length' => array('label' => 'PVPanelCableLength', 'type' => 'double', 'unit' => 'mm'),
			'operating_temperature_min' => array('label' => 'Minimum', 'type' => 'double', 'unit' => '°C', 'group' => 'operating_temperature', 'role' => 'min'),
			'operating_temperature_max' => array('label' => 'Maximum', 'type' => 'double', 'unit' => '°C', 'group' => 'operating_temperature', 'role' => 'max'),
			'max_system_voltage' => array('label' => 'PVPanelMaxSystemVoltage', 'type' => 'double', 'unit' => 'V'),
			'max_series_fuse' => array('label' => 'PVPanelMaxSeriesFuse', 'type' => 'double', 'unit' => 'A'),
			'snow_load' => array('label' => 'PVPanelSnowLoad', 'type' => 'double', 'unit' => 'Pa'),
			'wind_load' => array('label' => 'PVPanelWindLoad', 'type' => 'double', 'unit' => 'Pa'),
			'noct' => array('label' => 'PVPanelNOCT', 'type' => 'double', 'unit' => '°C'),
			'temp_coeff_pmax' => array('label' => 'PVPanelTempCoeffPmax', 'type' => 'double', 'unit' => '%/°C'),
			'temp_coeff_voc' => array('label' => 'PVPanelTempCoeffVoc', 'type' => 'double', 'unit' => '%/°C'),
			'temp_coeff_isc' => array('label' => 'PVPanelTempCoeffIsc', 'type' => 'double', 'unit' => '%/°C'),
			'first_year_degradation' => array('label' => 'PVPanelFirstYearDegradation', 'type' => 'double', 'unit' => '%'),
			'annual_degradation' => array('label' => 'PVPanelAnnualDegradation', 'type' => 'double', 'unit' => '%/year'),
			'product_warranty' => array('label' => 'PVPanelProductWarranty', 'type' => 'double', 'unit' => 'years'),
			'power_warranty' => array('label' => 'PVPanelPowerWarranty', 'type' => 'double', 'unit' => 'years'),
			'modules_per_box' => array('label' => 'PVPanelModulesPerBox', 'type' => 'int', 'unit' => 'pcs'),
			'modules_per_container40' => array('label' => 'PVPanelModulesPerContainer40', 'type' => 'int', 'unit' => 'pcs'),
		);
	}

	/** @return array<string,string> Comparator code => display symbol */
	public static function getComparatorSymbols()
	{
		return array('LT' => '<', 'LTE' => '≤', 'EQ' => '=', 'GTE' => '≥', 'GT' => '>');
	}

	/**
	 * Normalize a comparator code or symbol.
	 *
	 * @param mixed $value Raw value
	 * @return string Empty string when invalid or absent
	 */
	public static function normalizeComparator($value)
	{
		$value = strtoupper(trim((string) $value));
		$map = array('<' => 'LT', 'LT' => 'LT', '<=' => 'LTE', '≤' => 'LTE', 'LTE' => 'LTE', '=' => 'EQ', '==' => 'EQ', 'EQ' => 'EQ', '>=' => 'GTE', '≥' => 'GTE', 'GTE' => 'GTE', '>' => 'GT', 'GT' => 'GT');
		return isset($map[$value]) ? $map[$value] : '';
	}

	/**
	 * Parse a compact threshold such as "<3" or "≥ 0,95".
	 *
	 * @param mixed $raw Raw value
	 * @return array{comparator:string,value:float}|null
	 */
	public static function parseThreshold($raw)
	{
		$value = trim((string) $raw);
		if ($value === '') {
			return null;
		}
		if (!preg_match('/^(<=|>=|<|>|=|≤|≥)\s*([+-]?(?:\d+(?:[.,]\d+)?|[.,]\d+))$/u', $value, $matches)) {
			return null;
		}
		$comparator = self::normalizeComparator($matches[1]);
		return $comparator !== '' ? array('comparator' => $comparator, 'value' => self::toFloat($matches[2])) : null;
	}

	/**
	 * Parse a compact two-bound range.
	 *
	 * @param mixed $raw Raw value
	 * @return array{min:float,max:float}|null
	 */
	public static function parseRange($raw)
	{
		$value = trim((string) $raw);
		if ($value === '') {
			return null;
		}
		$number = '([+-]?(?:\d+(?:[.,]\d+)?|[.,]\d+))';
		if (preg_match('/^\s*'.$number.'\s*(?:\.\.|…|–|—|\b(?:to|a|à)\b)\s*'.$number.'\s*$/iu', $value, $matches)) {
			return self::orderedRange(self::toFloat($matches[1]), self::toFloat($matches[2]));
		}
		if (preg_match('/^\s*'.$number.'\s*(?:\/|;)\s*'.$number.'\s*$/u', $value, $matches) && (preg_match('/^[+-]/', trim($matches[1])) || preg_match('/^[+-]/', trim($matches[2])))) {
			return self::orderedRange(self::toFloat($matches[1]), self::toFloat($matches[2]));
		}
		if (preg_match('/^\s*(-(?:\d+(?:[.,]\d+)?|[.,]\d+))\s*-\s*(-(?:\d+(?:[.,]\d+)?|[.,]\d+))\s*$/u', $value, $matches)) {
			return self::orderedRange(self::toFloat($matches[1]), self::toFloat($matches[2]));
		}
		if (preg_match('/^\s*'.$number.'\s*-\s*(\+?(?:\d+(?:[.,]\d+)?|[.,]\d+))\s*$/u', $value, $matches)) {
			return self::orderedRange(self::toFloat($matches[1]), self::toFloat($matches[2]));
		}
		return null;
	}

	/**
	 * Parse signed legacy power-factor values.
	 * Positive is inductive, negative is capacitive; an unsigned third value is nominal.
	 *
	 * @param mixed $raw Raw value
	 * @return array{inductive:?float,nominal:?float,capacitive:?float}|null
	 */
	public static function parsePowerFactor($raw)
	{
		$value = trim((string) $raw);
		if ($value === '') {
			return null;
		}
		if (!preg_match_all('/[+-]?(?:\d+(?:[.,]\d+)?|[.,]\d+)/', $value, $matches) || count($matches[0]) < 2) {
			return null;
		}
		$result = array('inductive' => null, 'nominal' => null, 'capacitive' => null);
		foreach ($matches[0] as $part) {
			$number = self::toFloat($part);
			if (substr($part, 0, 1) === '+') {
				$result['inductive'] = abs($number);
			} elseif (substr($part, 0, 1) === '-') {
				$result['capacitive'] = abs($number);
			} elseif ($result['nominal'] === null) {
				$result['nominal'] = abs($number);
			} else {
				return null;
			}
		}
		return ($result['inductive'] !== null && $result['capacitive'] !== null) ? $result : null;
	}

	/**
	 * Validate nullable min/nominal/max values.
	 *
	 * @param mixed $min Minimum
	 * @param mixed $nominal Nominal value
	 * @param mixed $max Maximum
	 * @return bool
	 */
	public static function isValidRange($min, $nominal, $max)
	{
		$minset = self::isSet($min);
		$nominalset = self::isSet($nominal);
		$maxset = self::isSet($max);
		if ($minset && $maxset && (float) $min > (float) $max) {
			return false;
		}
		if ($nominalset && $minset && (float) $nominal < (float) $min) {
			return false;
		}
		if ($nominalset && $maxset && (float) $nominal > (float) $max) {
			return false;
		}
		return true;
	}

	/** @param mixed $value Value @return bool */
	private static function isSet($value)
	{
		return $value !== null && $value !== '';
	}

	/** @param string $value Number @return float */
	private static function toFloat($value)
	{
		return (float) str_replace(',', '.', trim($value));
	}

	/** @return array{min:float,max:float} */
	private static function orderedRange($first, $second)
	{
		return array('min' => min($first, $second), 'max' => max($first, $second));
	}
}
