<?php
require_once __DIR__.'/powerplantpv_box_maintenance_base.php';
class powerplantpv_box_maintenance_overdue extends PowerPlantPVMaintenanceBoxBase
{
	public $boxcode = 'powerplantpv_maint_overdue';
	public $boxlabel = 'PowerPlantPVMaintenanceWidgetOverdue';
	protected $widgetCode = PowerPlantPVMaintenanceWidget::OVERDUE;
}
