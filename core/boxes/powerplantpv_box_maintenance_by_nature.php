<?php
require_once __DIR__.'/powerplantpv_box_maintenance_base.php';
class powerplantpv_box_maintenance_by_nature extends PowerPlantPVMaintenanceBoxBase
{
	public $boxcode = 'powerplantpv_maint_by_nature';
	public $boxlabel = 'PowerPlantPVStatsByNature';
	protected $widgetCode = PowerPlantPVMaintenanceWidget::BY_NATURE;
}
