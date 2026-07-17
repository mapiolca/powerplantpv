<?php
require_once __DIR__.'/powerplantpv_box_maintenance_base.php';
class powerplantpv_box_maintenance_by_powerplant extends PowerPlantPVMaintenanceBoxBase
{
	public $boxcode = 'powerplantpv_maint_by_plant';
	public $boxlabel = 'PowerPlantPVStatsByPowerPlant';
	protected $widgetCode = PowerPlantPVMaintenanceWidget::BY_POWERPLANT;
}
