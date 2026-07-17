<?php
require_once __DIR__.'/powerplantpv_box_maintenance_base.php';
class powerplantpv_box_maintenance_status_summary extends PowerPlantPVMaintenanceBoxBase
{
	public $boxcode = 'powerplantpv_maint_status_summary';
	public $boxlabel = 'PowerPlantPVMaintenanceSummary';
	protected $widgetCode = PowerPlantPVMaintenanceWidget::STATUS_SUMMARY;
}
