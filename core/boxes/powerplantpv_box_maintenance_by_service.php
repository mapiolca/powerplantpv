<?php
require_once __DIR__.'/powerplantpv_box_maintenance_base.php';
class powerplantpv_box_maintenance_by_service extends PowerPlantPVMaintenanceBoxBase
{
	public $boxcode = 'powerplantpv_maint_by_service';
	public $boxlabel = 'PowerPlantPVMaintenanceStatsByService';
	protected $widgetCode = PowerPlantPVMaintenanceWidget::BY_SERVICE;
}
