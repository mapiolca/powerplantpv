<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/bootstrap.php';

final class PowerPlantPVMaintenanceSchedulerTest extends TestCase
{
	/**
	 * @return array<string,mixed>
	 */
	private function intervention($status)
	{
		return array(
			'status' => $status,
			'is_closed' => false,
			'is_signed_covering' => false,
			'nature_active' => 1,
			'nature_is_maintenance' => 1,
			'date_start' => 100,
			'date_end' => 200,
			'contract_ids' => array(42),
		);
	}

	public function testCreatedModeAcceptsDraftValidatedAndBilled(): void
	{
		foreach (array(0, 1, 2) as $status) {
			$this->assertTrue(PowerPlantPVMaintenanceScheduler::isScheduledInterventionMatchingPeriod($this->intervention($status), 42, 100, 200, 'created'));
		}
	}

	public function testValidatedModeRejectsDraftAndAcceptsValidatedAndBilled(): void
	{
		$this->assertFalse(PowerPlantPVMaintenanceScheduler::isScheduledInterventionMatchingPeriod($this->intervention(0), 42, 100, 200, 'validated'));
		$this->assertTrue(PowerPlantPVMaintenanceScheduler::isScheduledInterventionMatchingPeriod($this->intervention(1), 42, 100, 200, 'validated'));
		$this->assertTrue(PowerPlantPVMaintenanceScheduler::isScheduledInterventionMatchingPeriod($this->intervention(2), 42, 100, 200, 'validated'));
	}

	public function testClosedOrSignedInterventionIsNotOnlyScheduled(): void
	{
		$closed = $this->intervention(3);
		$closed['is_closed'] = true;
		$this->assertFalse(PowerPlantPVMaintenanceScheduler::isScheduledInterventionMatchingPeriod($closed, 42, 100, 200, 'created'));

		$status = PowerPlantPVMaintenanceScheduler::resolveMaintenanceStatus(1, 100, 200, $closed, $this->intervention(1), 150, 30);
		$this->assertSame(PowerPlantPVMaintenanceScheduler::STATUS_COVERED, $status);
	}

	public function testPeriodOverlapIncludesExactBoundaries(): void
	{
		$intervention = $this->intervention(1);
		$intervention['date_start'] = 200;
		$intervention['date_end'] = 300;
		$this->assertTrue(PowerPlantPVMaintenanceScheduler::isMaintenanceInterventionMatchingPeriod($intervention, 42, 100, 200));
		$intervention['date_start'] = 201;
		$this->assertFalse(PowerPlantPVMaintenanceScheduler::isMaintenanceInterventionMatchingPeriod($intervention, 42, 100, 200));
	}

	public function testCommonRulesRequireActiveNatureContractAndValidDates(): void
	{
		$intervention = $this->intervention(1);
		$intervention['nature_active'] = 0;
		$this->assertFalse(PowerPlantPVMaintenanceScheduler::isMaintenanceInterventionMatchingPeriod($intervention, 42, 100, 200));
		$intervention = $this->intervention(1);
		$intervention['contract_ids'] = array();
		$this->assertFalse(PowerPlantPVMaintenanceScheduler::isMaintenanceInterventionMatchingPeriod($intervention, 42, 100, 200));
		$intervention = $this->intervention(1);
		$intervention['date_start'] = 300;
		$intervention['date_end'] = 200;
		$this->assertFalse(PowerPlantPVMaintenanceScheduler::isMaintenanceInterventionMatchingPeriod($intervention, 42, 100, 200));
	}
}

