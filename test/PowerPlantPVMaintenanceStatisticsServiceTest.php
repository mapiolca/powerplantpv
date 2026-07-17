<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/bootstrap.php';

final class PowerPlantPVMaintenanceStatisticsServiceTest extends TestCase
{
	/**
	 * @param int $interventionId Intervention ID
	 * @param int $powerPlantId Power plant ID
	 * @param int $date Effective date
	 * @param bool $completed Completion state
	 * @return array<string,mixed>
	 */
	private function row($interventionId, $powerPlantId, $date, $completed)
	{
		return array(
			'intervention_id' => $interventionId,
			'effective_date' => $date,
			'completed' => $completed,
			'nature_id' => 4,
			'nature_label' => 'Preventive maintenance',
			'powerplant_id' => $powerPlantId,
			'powerplant_label' => 'PV-'.sprintf('%03d', $powerPlantId),
			'fk_soc' => 8,
			'customer_label' => 'Customer',
		);
	}

	public function testThreeYearAggregationHandlesYearBoundariesAndDuplicates(): void
	{
		global $db;

		$rows = array(
			$this->row(10, 1, dol_mktime(23, 59, 59, 12, 31, 2024), false),
			$this->row(11, 1, dol_mktime(0, 0, 0, 1, 1, 2025), true),
			$this->row(11, 1, dol_mktime(0, 0, 0, 1, 1, 2025), true),
			$this->row(12, 2, dol_mktime(12, 0, 0, 7, 15, 2026), true),
		);
		$service = new PowerPlantPVMaintenanceStatisticsService($db);
		$data = $service->aggregateHistoryRows($rows, 2026, 3);

		$this->assertSame(array(2026, 2025, 2024), $data['years']);
		$this->assertSame(1, $data['annual'][2024]['total']);
		$this->assertSame(0, $data['annual'][2024]['completed']);
		$this->assertSame(1, $data['monthly']['total'][2024][12]);
		$this->assertSame(1, $data['annual'][2025]['total']);
		$this->assertSame(1, $data['annual'][2025]['completed']);
		$this->assertSame(1, $data['monthly']['completed'][2025][1]);
		$this->assertSame(1, $data['annual'][2026]['total']);
		$this->assertSame(100.0, $data['annual'][2026]['completion_rate']);
		$this->assertSame(3, $data['distributions']['nature'][0]['total']);
		$this->assertSame(2, $data['distributions']['powerplant'][0]['total']);
		foreach ($data['years'] as $year) {
			$this->assertSame($data['annual'][$year]['total'], array_sum($data['monthly']['total'][$year]));
			$this->assertSame($data['annual'][$year]['completed'], array_sum($data['monthly']['completed'][$year]));
		}
	}

	public function testTwoYearHorizonExcludesOlderRowsAndKeepsDistinctPlantLinks(): void
	{
		global $db;

		$rows = array(
			$this->row(20, 1, dol_mktime(12, 0, 0, 6, 1, 2024), true),
			$this->row(21, 1, dol_mktime(12, 0, 0, 6, 1, 2025), false),
			$this->row(21, 2, dol_mktime(12, 0, 0, 6, 1, 2025), false),
			$this->row(22, 2, dol_mktime(12, 0, 0, 6, 1, 2026), true),
		);
		$service = new PowerPlantPVMaintenanceStatisticsService($db);
		$data = $service->aggregateHistoryRows($rows, 2026, 2);

		$this->assertSame(array(2026, 2025), $data['years']);
		$this->assertArrayNotHasKey(2024, $data['annual']);
		$this->assertSame(1, $data['annual'][2025]['total']);
		$this->assertSame(1, $data['annual'][2025]['open']);
		$this->assertSame(1, $data['annual'][2026]['completed']);
		$this->assertSame(3, array_sum(array_column($data['distributions']['powerplant'], 'total')));
	}
}
