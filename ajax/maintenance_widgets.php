<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
	$res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = @include '../../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var User $user */
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/class/powerplantpvmaintenancewidgetmanager.class.php');

header('Content-Type: application/json; charset=UTF-8');

if (!isModEnabled('powerplantpv') || !getDolGlobalInt('POWERPLANTPV_MAINTENANCE_ENABLE', 1)
	|| !powerplantpvUserHasMaintenanceRight($user, 'read')
	|| !powerplantpvUserHasRightPath($user, array('powerplantpv', 'powerplant', 'read'))
) {
	http_response_code(403);
	print json_encode(array('success' => false, 'error' => 'Forbidden'));
	exit;
}
if (!GETPOST('token', 'alpha') || (function_exists('checkToken') && !checkToken())) {
	http_response_code(403);
	print json_encode(array('success' => false, 'error' => 'InvalidToken'));
	exit;
}

$action = GETPOST('action', 'aZ09');
$manager = new PowerPlantPVMaintenanceWidgetManager($db);
$result = -1;
if ($action === 'save_layout') {
	$codes = GETPOST('widget_code', 'array:aZ09');
	$columns = GETPOST('widget_column', 'array:int');
	if (!is_array($columns)) {
		$columns = array();
	}
	$layout = array();
	if (is_array($codes)) {
		foreach (array_values($codes) as $index => $code) {
			$layout[] = array('code' => (string) $code, 'column' => isset($columns[$index]) ? (int) $columns[$index] : 0);
		}
	}
	$result = $manager->saveLayout((int) $user->id, (int) $conf->entity, $layout, (int) $user->id);
} elseif ($action === 'reset_layout') {
	$result = $manager->resetLayout((int) $user->id, (int) $conf->entity);
} else {
	http_response_code(400);
	print json_encode(array('success' => false, 'error' => 'UnknownAction'));
	exit;
}

if ($result < 0) {
	http_response_code(500);
	print json_encode(array('success' => false, 'error' => $manager->error));
	exit;
}
print json_encode(array('success' => true));
