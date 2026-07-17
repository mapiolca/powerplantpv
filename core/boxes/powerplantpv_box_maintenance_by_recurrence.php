<?php
require_once __DIR__.'/powerplantpv_box_maintenance_base.php';
class powerplantpv_box_maintenance_by_recurrence extends PowerPlantPVMaintenanceBoxBase
{
	public $boxcode = 'powerplantpv_maint_by_recurrence';
	public $boxlabel = 'PowerPlantPVMaintenanceStatsByRecurrence';
	protected $widgetCode = PowerPlantPVMaintenanceWidget::BY_RECURRENCE;
}
