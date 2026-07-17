<?php
require_once __DIR__.'/powerplantpv_box_maintenance_base.php';
class powerplantpv_box_maintenance_due_windows extends PowerPlantPVMaintenanceBoxBase
{
	public $boxcode = 'powerplantpv_maint_due_windows';
	public $boxlabel = 'PowerPlantPVMaintenanceDueWindows';
	protected $widgetCode = PowerPlantPVMaintenanceWidget::DUE_WINDOWS;
}
