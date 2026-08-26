<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/bootstrap.php';

final class PowerPlantPVMaintenanceDashboardServiceTest extends TestCase
{
	/**
	 * @param string $status Status
	 * @param int $start Start
	 * @param int $end End
	 * @return array<string,mixed>
	 */
	private function row($status, $start, $end)
	{
		return array(
			'entity' => 1,
			'status' => $status,
			'period_start' => $start,
			'period_end' => $end,
			'powerplant_id' => 1,
			'powerplant_ref' => 'PV-001',
			'fk_soc' => 2,
			'contract' => array('thirdparty_name' => 'Customer'),
			'active_services' => array(array('maintenance_services' => array(array('id' => 3, 'label' => 'Preventive')))),
			'recurrence' => PowerPlantPVMaintenanceScheduler::RECURRENCE_YEARLY,
			'is_eligible' => true,
			'covering_intervention' => null,
			'scheduled_intervention' => array('intervention_nature_id' => 4, 'nature_label' => 'Maintenance'),
		);
	}

	public function testCountsRateWindowsAndOverdueBucketsUseOneAggregation(): void
	{
		global $db;

		$reference = dol_mktime(0, 0, 0, 7, 15, 2026);
		$rows = array(
			$this->row(PowerPlantPVMaintenanceScheduler::STATUS_DUE, $reference + (5 * 86400), $reference + (10 * 86400)),
			$this->row(PowerPlantPVMaintenanceScheduler::STATUS_SCHEDULED, $reference + (20 * 86400), $reference + (25 * 86400)),
			$this->row(PowerPlantPVMaintenanceScheduler::STATUS_OVERDUE, $reference - (20 * 86400), $reference - (10 * 86400)),
		);
		$service = new PowerPlantPVMaintenanceDashboardService($db);
		$data = $service->aggregateRows($rows, $reference - (30 * 86400), $reference + (90 * 86400), $reference);

		$this->assertTrue($data['has_data']);
		$this->assertSame(1, $data['counts']['to_schedule']);
		$this->assertSame(1, $data['counts']['scheduled']);
		$this->assertSame(1, $data['counts']['overdue']);
		$this->assertEqualsWithDelta(33.33, (float) $data['programming_rate'], 0.02);
		$this->assertSame(1, $data['due_windows']['7']);
		$this->assertSame(2, $data['due_windows']['30']);
		$this->assertSame(1, $data['overdue_age']['8_30']);
	}

	public function testCoveredNotRequiredAndIncompleteAreExcludedFromRate(): void
	{
		global $db;

		$reference = dol_mktime(0, 0, 0, 7, 15, 2026);
		$rows = array(
			$this->row(PowerPlantPVMaintenanceScheduler::STATUS_SCHEDULED, $reference, $reference + 86400),
			$this->row(PowerPlantPVMaintenanceScheduler::STATUS_COVERED, $reference, $reference + 86400),
			$this->row(PowerPlantPVMaintenanceScheduler::STATUS_NOT_REQUIRED, 0, 0),
			$this->row(PowerPlantPVMaintenanceScheduler::STATUS_INCOMPLETE, 0, 0),
		);
		$rows[3]['recurrence'] = '';
		$service = new PowerPlantPVMaintenanceDashboardService($db);
		$data = $service->aggregateRows($rows, $reference - 86400, $reference + (2 * 86400), $reference);

		$this->assertEquals(100.0, (float) $data['programming_rate']);
		$this->assertSame(1, $data['configuration_quality']['incomplete']);
		$this->assertSame(1, $data['configuration_quality']['missing_period']);
		$this->assertSame(1, $data['configuration_quality']['missing_recurrence']);
	}

	public function testMonthlyBucketsCrossDecemberWithoutInvalidTimestamp(): void
	{
		global $db;

		$dateStart = dol_mktime(0, 0, 0, 1, 1, 2026);
		$dateEnd = dol_mktime(23, 59, 59, 12, 31, 2026);
		$service = new PowerPlantPVMaintenanceDashboardService($db);
		$data = $service->aggregateRows(array(), $dateStart, $dateEnd, $dateStart);

		$this->assertFalse($data['has_data']);
		$this->assertCount(12, $data['monthly_load']);
		$this->assertSame(0, array_sum(array_column($data['monthly_load'], 'count')));
	}

	public function testSharedEntityRowsAreAggregatedLikeLocalRows(): void
	{
		global $db;

		$reference = dol_mktime(0, 0, 0, 7, 15, 2026);
		$row = $this->row(PowerPlantPVMaintenanceScheduler::STATUS_SCHEDULED, $reference, $reference + 86400);
		$row['entity'] = 2;

		$service = new PowerPlantPVMaintenanceDashboardService($db);
		$data = $service->aggregateRows(array($row), $reference - 86400, $reference + (2 * 86400), $reference);

		$this->assertTrue($data['has_data']);
		$this->assertSame(1, $data['counts']['scheduled']);
		$this->assertSame('PV-001', $data['distributions']['by_powerplant'][0]['label']);
	}

	public function testCustomerDistributionUsesPowerPlantThirdPartyLabelWithoutContract(): void
	{
		global $db;

		$reference = dol_mktime(0, 0, 0, 7, 15, 2026);
		$row = $this->row(PowerPlantPVMaintenanceScheduler::STATUS_NOT_REQUIRED, 0, 0);
		$row['contract'] = array();
		$row['customer_label'] = 'Customer without contract';

		$service = new PowerPlantPVMaintenanceDashboardService($db);
		$data = $service->aggregateRows(array($row), $reference, $reference, $reference);

		$this->assertSame('Customer without contract', $data['distributions']['by_customer'][0]['label']);
		$this->assertSame(DOL_URL_ROOT.'/societe/card.php?socid=2', $data['distributions']['by_customer'][0]['url']);
	}
}
