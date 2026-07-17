<?php
require_once __DIR__.'/powerplantpv_box_maintenance_base.php';
class powerplantpv_box_maintenance_programming_rate extends PowerPlantPVMaintenanceBoxBase
{
	public $boxcode = 'powerplantpv_maint_program_rate';
	public $boxlabel = 'PowerPlantPVMaintenanceProgrammingRate';
	protected $widgetCode = PowerPlantPVMaintenanceWidget::PROGRAMMING_RATE;
}
