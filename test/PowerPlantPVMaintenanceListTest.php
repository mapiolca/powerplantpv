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
dol_include_once('/powerplantpv/lib/powerplantpv_maintenance.lib.php');

final class PowerPlantPVMaintenanceListTest extends TestCase
{
	public function testEmptyDolibarrSelectValuesAreDiscarded(): void
	{
		$filters = powerplantpvMaintenanceActiveListFilters(array(
			'fk_powerplant' => 0,
			'fk_soc' => -1,
			'status' => '-1',
			'date_start' => '',
			'date_end' => 0,
			'intervention_nature' => 0,
			'maintenance_service' => -1,
			'entities' => array(),
		));

		$this->assertSame(array(), $filters);
	}

	public function testActiveFiltersAreKept(): void
	{
		$filters = powerplantpvMaintenanceActiveListFilters(array(
			'fk_soc' => 12,
			'status' => PowerPlantPVMaintenanceScheduler::STATUS_DUE,
			'entities' => array(2, 3),
		));

		$this->assertSame(array(
			'fk_soc' => 12,
			'status' => PowerPlantPVMaintenanceScheduler::STATUS_DUE,
			'entities' => array(2, 3),
		), $filters);
	}

	public function testIneligiblePowerPlantsRemainAtTheEndForEverySortDirection(): void
	{
		$rows = array(
			array('powerplant_ref' => 'MIDDLE', 'is_eligible' => false),
			array('powerplant_ref' => 'ALPHA', 'is_eligible' => true),
			array('powerplant_ref' => 'ZULU', 'is_eligible' => true),
		);

		$ascending = powerplantpvMaintenanceSortRows($rows, 'powerplant', 'ASC');
		$this->assertSame(array('ALPHA', 'ZULU', 'MIDDLE'), array_column($ascending, 'powerplant_ref'));

		$descending = powerplantpvMaintenanceSortRows($rows, 'powerplant', 'DESC');
		$this->assertSame(array('ZULU', 'ALPHA', 'MIDDLE'), array_column($descending, 'powerplant_ref'));
	}
}
