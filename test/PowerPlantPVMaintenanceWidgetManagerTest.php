<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/bootstrap.php';

final class PowerPlantPVMaintenanceWidgetManagerTest extends TestCase
{
	public function testNormalizeLayoutPreservesColumnsAndRemovesDuplicates(): void
	{
		$layout = PowerPlantPVMaintenanceWidgetManager::normalizeLayout(array(
			array('code' => PowerPlantPVMaintenanceWidget::OVERDUE, 'column' => 1, 'position' => 999),
			array('code' => PowerPlantPVMaintenanceWidget::STATUS_SUMMARY, 'column' => 0, 'position' => 1),
			array('code' => PowerPlantPVMaintenanceWidget::OVERDUE, 'column' => 0, 'position' => 1),
			array('code' => 'unknown_widget', 'column' => 0, 'position' => 1),
		));

		$this->assertCount(2, $layout);
		$this->assertSame(array('code' => PowerPlantPVMaintenanceWidget::OVERDUE, 'column' => 1, 'position' => 10), $layout[0]);
		$this->assertSame(array('code' => PowerPlantPVMaintenanceWidget::STATUS_SUMMARY, 'column' => 0, 'position' => 10), $layout[1]);
	}

	public function testDefaultLayoutMatchesLegacyStatistics(): void
	{
		$codes = array_column(PowerPlantPVMaintenanceWidget::getDefaultStatsLayout(), 'code');
		$this->assertSame(array(
			PowerPlantPVMaintenanceWidget::STATUS_SUMMARY,
			PowerPlantPVMaintenanceWidget::BY_NATURE,
			PowerPlantPVMaintenanceWidget::BY_POWERPLANT,
			PowerPlantPVMaintenanceWidget::BY_CUSTOMER,
		), $codes);
	}
}
