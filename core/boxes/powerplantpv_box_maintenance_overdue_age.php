<?php
require_once __DIR__.'/powerplantpv_box_maintenance_base.php';
class powerplantpv_box_maintenance_overdue_age extends PowerPlantPVMaintenanceBoxBase
{
	public $boxcode = 'powerplantpv_maint_overdue_age';
	public $boxlabel = 'PowerPlantPVMaintenanceOverdueAge';
	protected $widgetCode = PowerPlantPVMaintenanceWidget::OVERDUE_AGE;
}
