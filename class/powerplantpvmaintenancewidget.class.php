<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Central registry and renderer for maintenance dashboard widgets.
 */
class PowerPlantPVMaintenanceWidget
{
	public const STATUS_SUMMARY = 'status_summary';
	public const TO_SCHEDULE = 'to_schedule';
	public const SCHEDULED = 'scheduled';
	public const OVERDUE = 'overdue';
	public const PROGRAMMING_RATE = 'programming_rate';
	public const DUE_WINDOWS = 'due_windows';
	public const OVERDUE_AGE = 'overdue_age';
	public const MONTHLY_LOAD = 'monthly_load';
	public const BY_POWERPLANT = 'by_powerplant';
	public const BY_CUSTOMER = 'by_customer';
	public const BY_NATURE = 'by_nature';
	public const BY_SERVICE = 'by_service';
	public const BY_RECURRENCE = 'by_recurrence';
	public const CONFIGURATION_QUALITY = 'configuration_quality';

	/**
	 * Return the complete widget catalog.
	 *
	 * @return array<string,array{label:string,help:string,type:string,right:string,position:int,stats:bool,home:bool,home_period:string}>
	 */
	public static function getCatalog()
	{
		return array(
			self::STATUS_SUMMARY => self::definition('PowerPlantPVMaintenanceSummary', 'PowerPlantPVMaintenanceStatusSummaryWidgetHelp', 'table', 10, 'calendar_year'),
			self::TO_SCHEDULE => self::definition('PowerPlantPVMaintenancesToSchedule', 'PowerPlantPVMaintenanceToScheduleWidgetHelp', 'indicator', 20, 'calendar_year'),
			self::SCHEDULED => self::definition('PowerPlantPVMaintenanceStatusScheduled', 'PowerPlantPVMaintenanceScheduledWidgetHelp', 'indicator', 30, 'calendar_year'),
			self::OVERDUE => self::definition('PowerPlantPVMaintenanceWidgetOverdue', 'PowerPlantPVMaintenanceOverdueWidgetHelp', 'indicator', 40, 'all_active_overdue'),
			self::PROGRAMMING_RATE => self::definition('PowerPlantPVMaintenanceProgrammingRate', 'PowerPlantPVMaintenanceProgrammingRateWidgetHelp', 'indicator', 50, 'calendar_year'),
			self::DUE_WINDOWS => self::definition('PowerPlantPVMaintenanceDueWindows', 'PowerPlantPVMaintenanceDueWindowsWidgetHelp', 'table', 60, 'rolling_90_days'),
			self::OVERDUE_AGE => self::definition('PowerPlantPVMaintenanceOverdueAge', 'PowerPlantPVMaintenanceOverdueAgeWidgetHelp', 'table', 70, 'all_active_overdue'),
			self::MONTHLY_LOAD => self::definition('PowerPlantPVMaintenanceMonthlyLoad', 'PowerPlantPVMaintenanceMonthlyLoadWidgetHelp', 'chart', 80, 'rolling_12_months'),
			self::BY_POWERPLANT => self::definition('PowerPlantPVStatsByPowerPlant', 'PowerPlantPVMaintenanceByPowerPlantWidgetHelp', 'table', 90, 'calendar_year'),
			self::BY_CUSTOMER => self::definition('PowerPlantPVStatsByCustomer', 'PowerPlantPVMaintenanceByCustomerWidgetHelp', 'table', 100, 'calendar_year'),
			self::BY_NATURE => self::definition('PowerPlantPVStatsByNature', 'PowerPlantPVMaintenanceByNatureWidgetHelp', 'table', 110, 'calendar_year'),
			self::BY_SERVICE => self::definition('PowerPlantPVMaintenanceStatsByService', 'PowerPlantPVMaintenanceByServiceWidgetHelp', 'table', 120, 'calendar_year'),
			self::BY_RECURRENCE => self::definition('PowerPlantPVMaintenanceStatsByRecurrence', 'PowerPlantPVMaintenanceByRecurrenceWidgetHelp', 'table', 130, 'calendar_year'),
			self::CONFIGURATION_QUALITY => self::definition('PowerPlantPVMaintenanceConfigurationQuality', 'PowerPlantPVMaintenanceConfigurationQualityWidgetHelp', 'table', 140, 'calendar_year'),
		);
	}

	/**
	 * Build a catalog definition.
	 *
	 * @param string $label Label translation key
	 * @param string $help Help translation key
	 * @param string $type Widget type
	 * @param int $position Default position
	 * @param string $homePeriod Default home period
	 * @return array{label:string,help:string,type:string,right:string,position:int,stats:bool,home:bool,home_period:string}
	 */
	private static function definition($label, $help, $type, $position, $homePeriod)
	{
		return array(
			'label' => $label,
			'help' => $help,
			'type' => $type,
			'right' => 'read',
			'position' => $position,
			'stats' => true,
			'home' => true,
			'home_period' => $homePeriod,
		);
	}

	/**
	 * Return the initial statistics layout.
	 *
	 * @return array<int,array{code:string,column:int,position:int}>
	 */
	public static function getDefaultStatsLayout()
	{
		return array(
			array('code' => self::STATUS_SUMMARY, 'column' => 0, 'position' => 10),
			array('code' => self::BY_NATURE, 'column' => 1, 'position' => 10),
			array('code' => self::BY_POWERPLANT, 'column' => 0, 'position' => 20),
			array('code' => self::BY_CUSTOMER, 'column' => 1, 'position' => 20),
		);
	}

	/**
	 * Return whether a widget exists and is available in a context.
	 *
	 * @param string $code Widget code
	 * @param string $context stats or home
	 * @return bool
	 */
	public static function isAvailable($code, $context)
	{
		$catalog = self::getCatalog();
		return isset($catalog[$code]) && !empty($catalog[$code][$context]);
	}

	/**
	 * Build one widget body using the native ModeleBoxes content structure.
	 *
	 * @param string $code Widget code
	 * @param array<string,mixed> $data Dashboard data
	 * @return array<int,array<int,array<string,mixed>>>
	 */
	public static function getBoxContents($code, array $data)
	{
		$counts = isset($data['counts']) && is_array($data['counts']) ? $data['counts'] : array();
		if (in_array($code, array(self::TO_SCHEDULE, self::SCHEDULED, self::OVERDUE), true)) {
			$value = isset($counts[$code]) ? (int) $counts[$code] : 0;
			return self::indicatorContents((string) $value);
		}
		if ($code === self::PROGRAMMING_RATE) {
			$rate = isset($data['programming_rate']) ? (float) $data['programming_rate'] : 0.0;
			return self::indicatorContents(vatrate($rate, true));
		}
		if ($code === self::STATUS_SUMMARY) {
			$rows = array(
				'PowerPlantPVMaintenancesToSchedule' => isset($counts['to_schedule']) ? (int) $counts['to_schedule'] : 0,
				'PowerPlantPVMaintenanceStatusScheduled' => isset($counts['scheduled']) ? (int) $counts['scheduled'] : 0,
				'Overdue' => isset($counts['overdue']) ? (int) $counts['overdue'] : 0,
				'Covered' => isset($counts['covered']) ? (int) $counts['covered'] : 0,
				'NotRequired' => isset($counts['not_required']) ? (int) $counts['not_required'] : 0,
				'PowerPlantPVMaintenanceStatusIncomplete' => isset($counts['incomplete']) ? (int) $counts['incomplete'] : 0,
			);
			return self::keyValueContents($rows, true);
		}
		if ($code === self::DUE_WINDOWS) {
			$windows = isset($data['due_windows']) && is_array($data['due_windows']) ? $data['due_windows'] : array();
			return self::keyValueContents(array(
				'PowerPlantPVMaintenanceWithin7Days' => isset($windows['7']) ? (int) $windows['7'] : 0,
				'PowerPlantPVMaintenanceWithin30Days' => isset($windows['30']) ? (int) $windows['30'] : 0,
				'PowerPlantPVMaintenanceWithin90Days' => isset($windows['90']) ? (int) $windows['90'] : 0,
			), true);
		}
		if ($code === self::OVERDUE_AGE) {
			$buckets = isset($data['overdue_age']) && is_array($data['overdue_age']) ? $data['overdue_age'] : array();
			return self::keyValueContents(array(
				'PowerPlantPVMaintenanceAge1To7' => isset($buckets['1_7']) ? (int) $buckets['1_7'] : 0,
				'PowerPlantPVMaintenanceAge8To30' => isset($buckets['8_30']) ? (int) $buckets['8_30'] : 0,
				'PowerPlantPVMaintenanceAge31To90' => isset($buckets['31_90']) ? (int) $buckets['31_90'] : 0,
				'PowerPlantPVMaintenanceAgeOver90' => isset($buckets['over_90']) ? (int) $buckets['over_90'] : 0,
			), true);
		}
		if ($code === self::MONTHLY_LOAD) {
			$months = isset($data['monthly_load']) && is_array($data['monthly_load']) ? $data['monthly_load'] : array();
			if (empty($months)) {
				return self::emptyContents();
			}
			return array(array(0 => array(
				'tr' => 'class="oddeven nohover"',
				'td' => 'colspan="2" class="nohover"',
				'asis' => 1,
				'text' => self::renderBars($months),
			)));
		}
		if ($code === self::CONFIGURATION_QUALITY) {
			$quality = isset($data['configuration_quality']) && is_array($data['configuration_quality']) ? $data['configuration_quality'] : array();
			return self::keyValueContents(array(
				'PowerPlantPVMaintenanceIncompleteConfiguration' => isset($quality['incomplete']) ? (int) $quality['incomplete'] : 0,
				'PowerPlantPVMaintenanceMissingPeriod' => isset($quality['missing_period']) ? (int) $quality['missing_period'] : 0,
				'PowerPlantPVMaintenanceMissingRecurrence' => isset($quality['missing_recurrence']) ? (int) $quality['missing_recurrence'] : 0,
			), true);
		}

		$distributionMap = array(
			self::BY_POWERPLANT => 'by_powerplant',
			self::BY_CUSTOMER => 'by_customer',
			self::BY_NATURE => 'by_nature',
			self::BY_SERVICE => 'by_service',
			self::BY_RECURRENCE => 'by_recurrence',
		);
		if (isset($distributionMap[$code])) {
			$distributions = isset($data['distributions']) && is_array($data['distributions']) ? $data['distributions'] : array();
			$rows = isset($distributions[$distributionMap[$code]]) && is_array($distributions[$distributionMap[$code]]) ? $distributions[$distributionMap[$code]] : array();
			return self::distributionContents($code, $rows);
		}

		return self::emptyContents();
	}

	/**
	 * Render a widget table using the native content structure of Dolibarr boxes.
	 *
	 * @param string $code Widget code
	 * @param array<string,mixed> $data Dashboard data
	 * @return string
	 */
	public static function renderBoxContents($code, array $data)
	{
		$html = '<div class="div-table-responsive-no-min">';
		$html .= '<table class="noborder centpercent liste">';
		foreach (self::getBoxContents($code, $data) as $row) {
			$firstCell = reset($row);
			$tr = is_array($firstCell) && !empty($firstCell['tr']) ? ' '.$firstCell['tr'] : ' class="oddeven"';
			$html .= '<tr'.$tr.'>';
			foreach ($row as $cell) {
				$td = !empty($cell['td']) ? ' '.$cell['td'] : '';
				$html .= '<td'.$td.'>'.(isset($cell['text']) ? (string) $cell['text'] : '').'</td>';
			}
			$html .= '</tr>';
		}
		$html .= '</table>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * Return translated Ajax tooltip content.
	 *
	 * @param string $code Widget code
	 * @return string
	 */
	public static function getTooltipContent($code)
	{
		global $langs;

		$catalog = self::getCatalog();
		if (!isset($catalog[$code])) {
			return '';
		}
		$definition = $catalog[$code];

		return '<strong>'.dol_escape_htmltag($langs->trans($definition['label'])).'</strong>'
			.'<br>'.dol_escape_htmltag($langs->trans($definition['help']));
	}

	/**
	 * Build a native indicator row.
	 *
	 * @param string $value Display value
	 * @return array<int,array<int,array<string,mixed>>>
	 */
	private static function indicatorContents($value)
	{
		return array(array(0 => array(
			'tr' => 'class="oddeven nohover"',
			'td' => 'colspan="2" class="center nohover"',
			'asis' => 1,
			'text' => '<span class="boxstatsindicator">'.dol_escape_htmltag($value).'</span>',
		)));
	}

	/**
	 * Build translated key/value rows.
	 *
	 * @param array<string,int> $rows Rows
	 * @param bool $translateKeys Translate keys
	 * @return array<int,array<int,array<string,mixed>>>
	 */
	private static function keyValueContents(array $rows, $translateKeys)
	{
		global $langs;

		$contents = array();
		foreach ($rows as $label => $count) {
			$displayLabel = $translateKeys ? $langs->trans($label) : $label;
			$contents[] = array(
				array('asis' => 1, 'text' => dol_escape_htmltag($displayLabel)),
				array('td' => 'class="right nowraponall"', 'asis' => 1, 'text' => (string) ((int) $count)),
			);
		}
		return $contents;
	}

	/**
	 * Build distribution rows.
	 *
	 * @param string $code Widget code
	 * @param array<int,array<string,mixed>> $rows Rows
	 * @return array<int,array<int,array<string,mixed>>>
	 */
	private static function distributionContents($code, array $rows)
	{
		if (empty($rows)) {
			return self::emptyContents();
		}

		$contents = array();
		foreach ($rows as $row) {
			$label = isset($row['label']) ? (string) $row['label'] : '-';
			$labelHtml = dol_escape_htmltag($label);
			if ($code === self::BY_POWERPLANT) {
				$labelHtml = img_picto('', 'fa-sun', 'class="paddingright"').$labelHtml;
			} elseif ($code === self::BY_CUSTOMER) {
				$labelHtml = img_picto('', 'company', 'class="paddingright"').$labelHtml;
			}
			if (!empty($row['url'])) {
				$labelHtml = '<a href="'.dol_escape_htmltag((string) $row['url']).'">'.$labelHtml.'</a>';
			}
			$contents[] = array(
				array('td' => 'class="tdoverflowmax250"', 'asis' => 1, 'text' => $labelHtml),
				array('td' => 'class="right nowraponall"', 'asis' => 1, 'text' => (string) (isset($row['count']) ? (int) $row['count'] : 0)),
			);
		}
		return $contents;
	}

	/**
	 * Build the native no-record row.
	 *
	 * @return array<int,array<int,array<string,mixed>>>
	 */
	private static function emptyContents()
	{
		global $langs;

		return array(array(0 => array(
			'td' => 'colspan="2" class="center"',
			'asis' => 1,
			'text' => '<span class="opacitymedium">'.dol_escape_htmltag($langs->trans('NoRecordFound')).'</span>',
		)));
	}

	/**
	 * Render monthly values as lightweight bars.
	 *
	 * @param array<int,array{label:string,count:int}> $rows Rows
	 * @return string
	 */
	private static function renderBars(array $rows)
	{
		global $langs;

		if (empty($rows)) {
			return '<span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span>';
		}
		$max = 1;
		foreach ($rows as $row) {
			$max = max($max, (int) $row['count']);
		}
		$html = '<div class="powerplantpv-maintenance-bars">';
		foreach ($rows as $row) {
			$count = (int) $row['count'];
			$width = (int) round(($count / $max) * 100);
			$html .= '<div class="powerplantpv-maintenance-bar-row"><span class="powerplantpv-maintenance-bar-label">'.dol_escape_htmltag($row['label']).'</span>';
			$html .= '<span class="powerplantpv-maintenance-bar-track"><span class="powerplantpv-maintenance-bar-value" style="width:'.$width.'%"></span></span>';
			$html .= '<span class="powerplantpv-maintenance-bar-count">'.$count.'</span></div>';
		}
		$html .= '</div>';
		return $html;
	}
}
