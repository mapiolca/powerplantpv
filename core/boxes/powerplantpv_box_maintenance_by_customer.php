<?php
require_once __DIR__.'/powerplantpv_box_maintenance_base.php';
class powerplantpv_box_maintenance_by_customer extends PowerPlantPVMaintenanceBoxBase
{
	public $boxcode = 'powerplantpv_maint_by_customer';
	public $boxlabel = 'PowerPlantPVStatsByCustomer';
	protected $widgetCode = PowerPlantPVMaintenanceWidget::BY_CUSTOMER;
}
