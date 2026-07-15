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

final class PowerPlantPVMaintenanceReminderTest extends TestCase
{
	public function testWeeklyWindowIncludesDaySixAndExcludesDaySeven(): void
	{
		$execution = dol_mktime(12, 0, 0, 7, 15, 2026);
		$window = PowerPlantPVMaintenanceReminder::calculateReminderWindow('weekly', $execution);
		$this->assertSame(dol_mktime(0, 0, 0, 7, 15, 2026), $window['start']);
		$this->assertSame(dol_mktime(23, 59, 59, 7, 21, 2026), $window['end']);
		$this->assertLessThan(dol_mktime(0, 0, 0, 7, 22, 2026), $window['end']);
	}

	public function testMonthlyWindowIncludesDayTwentyNineAndExcludesDayThirty(): void
	{
		$execution = dol_mktime(12, 0, 0, 7, 1, 2026);
		$window = PowerPlantPVMaintenanceReminder::calculateReminderWindow('monthly', $execution);
		$this->assertSame(dol_mktime(23, 59, 59, 7, 30, 2026), $window['end']);
		$this->assertLessThan(dol_mktime(0, 0, 0, 7, 31, 2026), $window['end']);
	}

	public function testCatchUpSelectsFirstFutureOccurrence(): void
	{
		$weeklyStart = dol_mktime(8, 0, 0, 5, 4, 2026);
		$execution = dol_mktime(12, 0, 0, 7, 15, 2026);
		$nextWeekly = PowerPlantPVMaintenanceReminder::calculateNextFutureStart('weekly', $weeklyStart, $execution);
		$this->assertGreaterThan($execution, $nextWeekly);
		$this->assertLessThanOrEqual(dol_time_plus_duree($execution, 1, 'w'), $nextWeekly);

		$monthlyStart = dol_mktime(8, 0, 0, 1, 5, 2026);
		$nextMonthly = PowerPlantPVMaintenanceReminder::calculateNextFutureStart('monthly', $monthlyStart, $execution);
		$this->assertGreaterThan($execution, $nextMonthly);
		$this->assertLessThanOrEqual(dol_time_plus_duree($execution, 1, 'm', 1), $nextMonthly);
	}

	public function testLocksAreDistinctPerEntity(): void
	{
		$this->assertNotSame(
			PowerPlantPVMaintenanceReminder::buildEntityLockName(1, 'weekly', '202607150800'),
			PowerPlantPVMaintenanceReminder::buildEntityLockName(2, 'weekly', '202607150800')
		);
	}

	public function testRecipientMarkerIsStableAndRecipientSpecific(): void
	{
		$first = PowerPlantPVMaintenanceReminder::buildRecipientMarkerConstName('weekly', 10, 'first@example.invalid');
		$this->assertSame($first, PowerPlantPVMaintenanceReminder::buildRecipientMarkerConstName('weekly', 10, 'FIRST@example.invalid'));
		$this->assertNotSame($first, PowerPlantPVMaintenanceReminder::buildRecipientMarkerConstName('weekly', 11, 'first@example.invalid'));
	}

	public function testReminderRowsAreDeduplicatedByPowerPlantContractAndPeriod(): void
	{
		$row = array('powerplant_id' => 1, 'contract_id' => 2, 'period_start' => 100, 'period_end' => 200, 'status' => 'overdue');
		$other = array('powerplant_id' => 1, 'contract_id' => 2, 'period_start' => 201, 'period_end' => 300, 'status' => 'scheduled');
		$this->assertCount(2, PowerPlantPVMaintenanceReminder::deduplicateReminderRows(array($row, $row, $other)));
	}

	public function testHistoricalOverdueRowsAreKeptOutsideRollingWindow(): void
	{
		$window = array(array('powerplant_id' => 1, 'contract_id' => 2, 'period_start' => 1000, 'period_end' => 2000, 'status' => 'planned'));
		$historical = array(array('powerplant_id' => 3, 'contract_id' => 4, 'period_start' => 10, 'period_end' => 20, 'status' => 'overdue'));
		$merged = PowerPlantPVMaintenanceReminder::mergeWindowAndOverdueRows($window, $historical);
		$this->assertCount(2, $merged);
		$this->assertContains('overdue', array_column($merged, 'status'));
	}

	public function testSmtpFailureKeepsOccurrenceAndPersistenceFailuresAreCounted(): void
	{
		$this->assertFalse(PowerPlantPVMaintenanceReminder::shouldAdvanceAfterDelivery(1));
		$this->assertTrue(PowerPlantPVMaintenanceReminder::shouldAdvanceAfterDelivery(0));
		$this->assertSame(1, PowerPlantPVMaintenanceReminder::getPersistenceFailureCount(1, 1));
		$this->assertSame(1, PowerPlantPVMaintenanceReminder::getPersistenceFailureCount(0, -1));
		$this->assertSame(2, PowerPlantPVMaintenanceReminder::getPersistenceFailureCount(1, -1));
	}

	public function testDigestObjectLinksAreAbsolute(): void
	{
		$url = PowerPlantPVMaintenanceReminder::buildAbsoluteObjectUrl('/powerplantpv/powerplant_card.php', 12);
		$this->assertMatchesRegularExpression('~^https?://~', $url);
		$this->assertStringContainsString('powerplant_card.php?id=12', $url);
		$this->assertMatchesRegularExpression('~^https?://~', PowerPlantPVMaintenanceReminder::buildAbsoluteObjectUrl('/contrat/card.php', 34));
		$this->assertMatchesRegularExpression('~^https?://~', PowerPlantPVMaintenanceReminder::buildAbsoluteObjectUrl('/fichinter/card.php', 56));
	}

	public function testScheduledStatusHasFrenchAndEnglishRecipientLabels(): void
	{
		global $langs;

		$french = clone $langs;
		$french->setDefaultLang('fr_FR');
		$french->load('powerplantpv@powerplantpv');
		$this->assertSame('Programmée', $french->trans('PowerPlantPVMaintenanceStatusScheduled'));

		$english = clone $langs;
		$english->setDefaultLang('en_US');
		$english->load('powerplantpv@powerplantpv');
		$this->assertSame('Scheduled', $english->trans('PowerPlantPVMaintenanceStatusScheduled'));
	}
}
