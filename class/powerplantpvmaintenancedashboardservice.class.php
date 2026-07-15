<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

dol_include_once('/powerplantpv/class/powerplantpvmaintenancescheduler.class.php');

/**
 * Shared maintenance aggregation service for statistics and home boxes.
 */
class PowerPlantPVMaintenanceDashboardService
{
	/** @var DoliDB */
	private $db;

	/** @var array<string,array<int,array<string,mixed>>> */
	private static $rowsCache = array();

	/** @var array<string,array<string,mixed>> */
	private static $dashboardCache = array();

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Return dashboard data for a requested range.
	 *
	 * @param User $user Current user
	 * @param int $dateStart Range start
	 * @param int $dateEnd Range end
	 * @param int|null $referenceDate Reference date
	 * @return array<string,mixed>
	 */
	public function getDashboard($user, $dateStart, $dateEnd, $referenceDate = null)
	{
		global $conf;

		$referenceDate = !empty($referenceDate) ? (int) $referenceDate : dol_now();
		$key = implode(':', array((int) $conf->entity, (int) $user->id, (int) $user->socid, (int) $dateStart, (int) $dateEnd, $referenceDate));
		if (isset(self::$dashboardCache[$key])) {
			return self::$dashboardCache[$key];
		}

		$rowsKey = implode(':', array((int) $conf->entity, (int) $user->id, (int) $user->socid, $referenceDate));
		if (!isset(self::$rowsCache[$rowsKey])) {
			$scheduler = new PowerPlantPVMaintenanceScheduler($this->db);
			self::$rowsCache[$rowsKey] = $scheduler->getMaintenanceRows($user, array(), $referenceDate);
		}

		self::$dashboardCache[$key] = $this->aggregateRows(self::$rowsCache[$rowsKey], $dateStart, $dateEnd, $referenceDate);
		return self::$dashboardCache[$key];
	}

	/**
	 * Aggregate already-calculated scheduler rows. Public for deterministic tests.
	 *
	 * @param array<int,array<string,mixed>> $rows Scheduler rows
	 * @param int $dateStart Range start
	 * @param int $dateEnd Range end
	 * @param int $referenceDate Reference date
	 * @return array<string,mixed>
	 */
	public function aggregateRows(array $rows, $dateStart, $dateEnd, $referenceDate)
	{
		$counts = array('to_schedule' => 0, 'scheduled' => 0, 'overdue' => 0, 'covered' => 0, 'not_required' => 0, 'incomplete' => 0);
		$dueWindows = array('7' => 0, '30' => 0, '90' => 0);
		$overdueAge = array('1_7' => 0, '8_30' => 0, '31_90' => 0, 'over_90' => 0);
		$quality = array('incomplete' => 0, 'missing_period' => 0, 'missing_recurrence' => 0);
		$distributions = array('by_powerplant' => array(), 'by_customer' => array(), 'by_nature' => array(), 'by_service' => array(), 'by_recurrence' => array());
		$monthly = $this->initializeMonths($dateStart, $dateEnd);
		$today = dol_mktime(0, 0, 0, (int) date('m', $referenceDate), (int) date('d', $referenceDate), (int) date('Y', $referenceDate));

		foreach ($rows as $row) {
			$status = isset($row['status']) ? (string) $row['status'] : PowerPlantPVMaintenanceScheduler::STATUS_NOT_REQUIRED;
			$periodStart = !empty($row['period_start']) ? (int) $row['period_start'] : 0;
			$periodEnd = !empty($row['period_end']) ? (int) $row['period_end'] : 0;
			$isIncomplete = ($status === PowerPlantPVMaintenanceScheduler::STATUS_INCOMPLETE);
			$isNotRequired = ($status === PowerPlantPVMaintenanceScheduler::STATUS_NOT_REQUIRED);
			$inRange = $isNotRequired || $isIncomplete || ($periodStart > 0 && $periodEnd > 0 && $periodEnd >= $dateStart && $periodStart <= $dateEnd);

			if ($isIncomplete) {
				$quality['incomplete']++;
				if ($periodStart <= 0 || $periodEnd <= 0 || $periodStart > $periodEnd) {
					$quality['missing_period']++;
				}
				if (empty($row['recurrence'])) {
					$quality['missing_recurrence']++;
				}
			}
			if (!$inRange) {
				continue;
			}

			if ($status === PowerPlantPVMaintenanceScheduler::STATUS_PLANNED || $status === PowerPlantPVMaintenanceScheduler::STATUS_DUE) {
				$counts['to_schedule']++;
			} elseif ($status === PowerPlantPVMaintenanceScheduler::STATUS_SCHEDULED) {
				$counts['scheduled']++;
			} elseif ($status === PowerPlantPVMaintenanceScheduler::STATUS_OVERDUE) {
				$counts['overdue']++;
			} elseif ($status === PowerPlantPVMaintenanceScheduler::STATUS_COVERED) {
				$counts['covered']++;
			} elseif ($isNotRequired) {
				$counts['not_required']++;
			} elseif ($isIncomplete) {
				$counts['incomplete']++;
			}

			if (in_array($status, array(PowerPlantPVMaintenanceScheduler::STATUS_PLANNED, PowerPlantPVMaintenanceScheduler::STATUS_DUE, PowerPlantPVMaintenanceScheduler::STATUS_SCHEDULED), true) && $periodStart >= $today) {
				$days = (int) floor(($periodStart - $today) / 86400);
				foreach (array(7, 30, 90) as $window) {
					if ($days <= $window) {
						$dueWindows[(string) $window]++;
					}
				}
			}
			if ($status === PowerPlantPVMaintenanceScheduler::STATUS_OVERDUE && $periodEnd > 0) {
				$days = max(1, (int) floor(($today - $periodEnd) / 86400));
				if ($days <= 7) {
					$overdueAge['1_7']++;
				} elseif ($days <= 30) {
					$overdueAge['8_30']++;
				} elseif ($days <= 90) {
					$overdueAge['31_90']++;
				} else {
					$overdueAge['over_90']++;
				}
			}

			if (!$isNotRequired && !$isIncomplete) {
				$monthKey = $periodStart > 0 ? date('Y-m', $periodStart) : '';
				if (isset($monthly[$monthKey])) {
					$monthly[$monthKey]['count']++;
				}
			}
			$this->incrementDistributions($distributions, $row);
		}

		$denominator = $counts['to_schedule'] + $counts['scheduled'] + $counts['overdue'];
		$programmingRate = $denominator > 0 ? round(($counts['scheduled'] * 100) / $denominator, 2) : 0;
		foreach ($distributions as $key => $buckets) {
			uasort($buckets, static function ($a, $b) {
				$result = ((int) $b['count']) <=> ((int) $a['count']);
				return $result !== 0 ? $result : strnatcasecmp((string) $a['label'], (string) $b['label']);
			});
			$distributions[$key] = array_values($buckets);
		}

		return array(
			'counts' => $counts,
			'programming_rate' => $programmingRate,
			'due_windows' => $dueWindows,
			'overdue_age' => $overdueAge,
			'monthly_load' => array_values($monthly),
			'distributions' => $distributions,
			'configuration_quality' => $quality,
			'period_start' => $dateStart,
			'period_end' => $dateEnd,
			'reference_date' => $referenceDate,
		);
	}

	/**
	 * Initialize month buckets within a range, capped at 24 months.
	 *
	 * @param int $dateStart Start
	 * @param int $dateEnd End
	 * @return array<string,array{label:string,count:int}>
	 */
	private function initializeMonths($dateStart, $dateEnd)
	{
		$months = array();
		$cursor = dol_mktime(0, 0, 0, (int) date('m', $dateStart), 1, (int) date('Y', $dateStart));
		$last = dol_mktime(0, 0, 0, (int) date('m', $dateEnd), 1, (int) date('Y', $dateEnd));
		for ($index = 0; $cursor <= $last && $index < 24; $index++) {
			$key = date('Y-m', $cursor);
			$months[$key] = array('label' => dol_print_date($cursor, '%b %Y'), 'count' => 0);
			$month = (int) date('m', $cursor) + 1;
			$year = (int) date('Y', $cursor);
			$cursor = dol_mktime(0, 0, 0, $month, 1, $year);
		}
		return $months;
	}

	/**
	 * Increment all distribution buckets for one scheduler row.
	 *
	 * @param array<string,array<string,array<string,mixed>>> $distributions Distributions
	 * @param array<string,mixed> $row Scheduler row
	 * @return void
	 */
	private function incrementDistributions(&$distributions, array $row)
	{
		global $langs;

		$powerplantId = !empty($row['powerplant_id']) ? (int) $row['powerplant_id'] : 0;
		$powerplantLabel = !empty($row['powerplant_ref']) ? (string) $row['powerplant_ref'] : '#'.$powerplantId;
		$this->incrementBucket($distributions['by_powerplant'], 'powerplant_'.$powerplantId, $powerplantLabel, $powerplantId > 0 ? dol_buildpath('/powerplantpv/powerplant_card.php', 1).'?id='.$powerplantId : '');

		$contract = !empty($row['contract']) && is_array($row['contract']) ? $row['contract'] : array();
		$socid = !empty($row['fk_soc']) ? (int) $row['fk_soc'] : 0;
		$customerLabel = !empty($contract['thirdparty_name']) ? (string) $contract['thirdparty_name'] : ($socid > 0 ? '#'.$socid : '-');
		$this->incrementBucket($distributions['by_customer'], 'customer_'.$socid, $customerLabel, $socid > 0 ? DOL_URL_ROOT.'/societe/card.php?socid='.$socid : '');

		$intervention = !empty($row['covering_intervention']) && is_array($row['covering_intervention']) ? $row['covering_intervention'] : (!empty($row['scheduled_intervention']) && is_array($row['scheduled_intervention']) ? $row['scheduled_intervention'] : array());
		$natureLabel = !empty($intervention['nature_label']) ? (string) $intervention['nature_label'] : (!empty($row['is_eligible']) ? $langs->trans('PowerPlantPVDefaultPreventiveMaintenanceNature') : $langs->trans('NotRequired'));
		$natureId = !empty($intervention['intervention_nature_id']) ? (int) $intervention['intervention_nature_id'] : 0;
		$this->incrementBucket($distributions['by_nature'], 'nature_'.$natureId.'_'.md5($natureLabel), $natureLabel);

		$services = !empty($row['active_services']) && is_array($row['active_services']) ? $row['active_services'] : array();
		$seenServices = array();
		foreach ($services as $contractLine) {
			$maintenanceServices = !empty($contractLine['maintenance_services']) && is_array($contractLine['maintenance_services']) ? $contractLine['maintenance_services'] : array();
			foreach ($maintenanceServices as $service) {
				$serviceId = !empty($service['id']) ? (int) $service['id'] : 0;
				if ($serviceId <= 0 || isset($seenServices[$serviceId])) {
					continue;
				}
				$seenServices[$serviceId] = true;
				$serviceLabel = !empty($service['label']) ? (string) $service['label'] : '#'.$serviceId;
				$this->incrementBucket($distributions['by_service'], 'service_'.$serviceId, $serviceLabel);
			}
		}

		$recurrence = !empty($row['recurrence']) ? (string) $row['recurrence'] : '';
		$labels = PowerPlantPVMaintenanceScheduler::getRecurrenceLabelKeys();
		$recurrenceLabel = isset($labels[$recurrence]) ? $langs->trans($labels[$recurrence]) : $langs->trans('PowerPlantPVNotConfigured');
		$this->incrementBucket($distributions['by_recurrence'], 'recurrence_'.$recurrence, $recurrenceLabel);
	}

	/**
	 * Increment one bucket.
	 *
	 * @param array<string,array{label:string,count:int,url?:string}> $buckets Buckets
	 * @param string $key Key
	 * @param string $label Label
	 * @param string $url Optional URL
	 * @return void
	 */
	private function incrementBucket(&$buckets, $key, $label, $url = '')
	{
		if (!isset($buckets[$key])) {
			$buckets[$key] = array('label' => $label, 'count' => 0, 'url' => $url);
		}
		$buckets[$key]['count']++;
	}
}
