<?php
require_once __DIR__.'/powerplantpv_box_maintenance_base.php';
class powerplantpv_box_maintenance_to_schedule extends PowerPlantPVMaintenanceBoxBase
{
	public $boxcode = 'powerplantpv_maint_to_schedule';
	public $boxlabel = 'PowerPlantPVMaintenancesToSchedule';
	protected $widgetCode = PowerPlantPVMaintenanceWidget::TO_SCHEDULE;
}
