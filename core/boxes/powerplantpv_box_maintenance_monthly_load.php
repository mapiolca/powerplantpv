<?php
require_once __DIR__.'/powerplantpv_box_maintenance_base.php';
class powerplantpv_box_maintenance_monthly_load extends PowerPlantPVMaintenanceBoxBase
{
	public $boxcode = 'powerplantpv_maint_monthly_load';
	public $boxlabel = 'PowerPlantPVMaintenanceMonthlyLoad';
	protected $widgetCode = PowerPlantPVMaintenanceWidget::MONTHLY_LOAD;
}
