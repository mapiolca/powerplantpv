<?php
require_once __DIR__.'/powerplantpv_box_maintenance_base.php';
class powerplantpv_box_maintenance_scheduled extends PowerPlantPVMaintenanceBoxBase
{
	public $boxcode = 'powerplantpv_maint_scheduled';
	public $boxlabel = 'PowerPlantPVMaintenanceStatusScheduled';
	protected $widgetCode = PowerPlantPVMaintenanceWidget::SCHEDULED;
}
