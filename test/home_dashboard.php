<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

// Isolated hook test: no database writes, session or real ERP bootstrap.
if (PHP_SAPI !== 'cli') {
	exit(1);
}
define('DOL_DOCUMENT_ROOT', $argv[1] ?? dirname(__DIR__, 2).'/dolibarr/htdocs');
define('MAIN_DB_PREFIX', 'test_');
function isModEnabled($module) { return $module === 'powerplantpv'; }
function getDolGlobalInt($key, $default = 0) { return $key === 'POWERPLANTPV_MAINTENANCE_ENABLE' ? $GLOBALS['maintenanceEnabled'] : $default; }
function dol_syslog($message, $level = 0) {}
function getEntity($element) { return '2,3'; }
function dol_buildpath($path, $absolute = 0) { return '/custom'.$path; }
function dol_include_once($path) { return true; }

class PowerPlantPVMaintenanceScheduler
{
	public function __construct($db) {}
	public function getMaintenanceRows($user, array $filters = array())
	{
		return array_map(static function ($status) { return array('status' => $status); },
			array('planned', 'due', 'scheduled', 'overdue', 'covered', 'incomplete'));
	}
}
class DashboardTestUser
{
	public $socid = 0;
	public $read = true;
	public $maintenance = true;
	public function hasRight($module, $object, $action) { return $object === 'maintenance' ? $this->maintenance : $this->read; }
}
class DashboardTestDb
{
	public $sql = '';
	public $rows = array();
	public $fail = false;
	public function sanitize($value) { return $value; }
	public function query($sql) {
		$this->sql = $sql;
		$this->rows = array((object) array('status' => 0, 'nb' => 2), (object) array('status' => 2, 'nb' => 4), (object) array('status' => 9, 'nb' => 8));
		return !$this->fail;
	}
	public function fetch_object($result) { return array_shift($this->rows); }
	public function free($result) {}
	public function lasterror() { return 'Expected database failure'; }
}
function checkDashboard($condition, $message) {
	if (!$condition) { throw new RuntimeException($message); }
}
require_once dirname(__DIR__).'/class/actions_powerplantpv.class.php';
$db = new DashboardTestDb();
$user = new DashboardTestUser();
$langs = new class {
	public function load($catalog) {}
	public function trans($key) { return $key; }
};
$maintenanceEnabled = 1;
$hook = new ActionsPowerplantpv();
$object = new stdClass();
$action = '';
$manager = new stdClass();
$parameters = array('context' => 'index');
checkDashboard($hook->addOpenElementsDashboardLine($parameters, $object, $action, $manager) === 0, 'Hook succeeds');
checkDashboard(count($hook->results) === 7, 'Four plant and three maintenance counters');
checkDashboard($hook->results['powerplantpv_status_0']->nbtodo === 2, 'Drafts counted');
checkDashboard($hook->results['powerplantpv_status_1']->nbtodo === 0, 'Missing status remains zero');
checkDashboard($hook->results['powerplantpv_status_2']->nbtodo === 4, 'In service counted');
checkDashboard($hook->results['powerplantpv_maintenance_total']->nbtodo === 6, 'Total periods');
checkDashboard($hook->results['powerplantpv_maintenance_planned']->nbtodo === 2, 'Planned and due grouped');
checkDashboard($hook->results['powerplantpv_maintenance_overdue']->nbtodo === 1, 'Overdue counted');
checkDashboard(strpos($hook->results['powerplantpv_status_0']->url, 'search_status=0') !== false, 'Draft zero filter preserved');
checkDashboard(strpos($hook->results['powerplantpv_maintenance_planned']->url, 'planned%2Cdue') !== false, 'Link uses both states');
checkDashboard(strpos($db->sql, 't.entity IN (2,3)') !== false, 'Shared entities filtered');
$user->socid = 12;
$user->maintenance = false;
$hook->addOpenElementsDashboardLine($parameters, $object, $action, $manager);
checkDashboard(count($hook->results) === 4 && strpos($db->sql, 't.fk_soc = 12') !== false, 'Partial rights and external scope');
$hook->addOpenElementsDashboardGroup($parameters, $object, $action, $manager);
checkDashboard(count($hook->results) === 1, 'Only allowed group');
$user->maintenance = true;
$maintenanceEnabled = 0;
$hook->addOpenElementsDashboardLine($parameters, $object, $action, $manager);
checkDashboard(count($hook->results) === 4, 'Disabled maintenance omitted');
$user->read = false;
$db->sql = '';
$hook->addOpenElementsDashboardLine($parameters, $object, $action, $manager);
checkDashboard($hook->results === array() && $db->sql === '', 'No right: no query or stale result');
$user->read = true;
$hook->addOpenElementsDashboardLine(array('context' => 'projectcard'), $object, $action, $manager);
checkDashboard($hook->results === array() && $db->sql === '', 'Other contexts ignored');
$db->fail = true;
checkDashboard($hook->addOpenElementsDashboardLine($parameters, $object, $action, $manager) === -1 && $hook->results === array(), 'SQL error does not become zero');

// Dispatch through the real native manager, including its cleanup between calls.
require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
$manager = new HookManager($db);
$manager->contextarray = array('index');
$manager->hooks = array('index' => array('powerplantpv' => $hook));
$manager->hooksSorted = array('index' => array('50:powerplantpv' => $hook));
$db->fail = false;
$maintenanceEnabled = 1;
checkDashboard($manager->executeHooks('addOpenElementsDashboardLine', array(), $object, $action) === 0, 'Native line dispatch');
checkDashboard(count($manager->resArray) === 7 && $hook->results === array(), 'Native manager captures and clears results');
checkDashboard($manager->executeHooks('addOpenElementsDashboardGroup', array(), $object, $action) === 0, 'Native group dispatch');
checkDashboard(count($manager->resArray) === 2 && !isset($manager->resArray['powerplantpv_status_0']), 'No line leakage into groups');
$user->read = false;
$manager->executeHooks('addOpenElementsDashboardLine', array(), $object, $action);
checkDashboard($manager->resArray === array(), 'Native subsequent denied dispatch is empty');
$user->read = true;
$db->fail = true;
checkDashboard($manager->executeHooks('addOpenElementsDashboardLine', array(), $object, $action) === -1, 'Native failure dispatch');
$db->fail = false;
checkDashboard($manager->executeHooks('addOpenElementsDashboardLine', array(), $object, $action) === 0, 'Native recovery clears prior error');
echo "Home dashboard hook scenarios passed (native manager/responses, simulated database and scheduler).\n";
