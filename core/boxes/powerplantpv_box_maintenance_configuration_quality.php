<?php
require_once __DIR__.'/powerplantpv_box_maintenance_base.php';
class powerplantpv_box_maintenance_configuration_quality extends PowerPlantPVMaintenanceBoxBase
{
	public $boxcode = 'powerplantpv_maint_quality';
	public $boxlabel = 'PowerPlantPVMaintenanceConfigurationQuality';
	protected $widgetCode = PowerPlantPVMaintenanceWidget::CONFIGURATION_QUALITY;
}
