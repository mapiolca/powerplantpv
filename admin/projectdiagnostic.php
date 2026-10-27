<?php
/* Copyright (C) 2025-2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file		PowerPlantPV/admin/about.php
 * \ingroup	PowerPlantPV
 * \brief	About page of PowerPlantPV module.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)).'/main.inc.php')) {
	$res = @include substr($tmp, 0, ($i + 1)).'/main.inc.php';
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php')) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php';
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

require_once __DIR__.'/../class/actions_powerplantpv.class.php';
require_once __DIR__.'/../class/powerplantpvprojectlink.class.php';
if (!$user->admin || !isModEnabled('powerplantpv') || !$user->hasRight('powerplantpv', 'powerplant', 'read') || !$user->hasRight('projet', 'lire')) {
	accessforbidden();
}
$diagnostic = array('php' => PHP_VERSION, 'entity' => $conf->entity, 'hook_debug' => getDolGlobalInt('MAIN_HOOK_DEBUG'));
foreach (array('opcache.enable', 'opcache.validate_timestamps', 'opcache.revalidate_freq', 'opcache.file_update_protection') as $key) {
	$diagnostic[$key] = ini_get($key);
}
foreach (array('llxHeader', 'formDolBanner') as $method) {
	$reflection = new ReflectionMethod('ActionsPowerplantpv', $method);
	$diagnostic[$method] = array('file' => $reflection->getFileName(), 'start' => $reflection->getStartLine(), 'end' => $reflection->getEndLine());
}
$diagnostic['hooks'] = $conf->modules_parts['hooks']['powerplantpv'] ?? null;
$service = new PowerPlantPVProjectLink($db);
$diagnostic['projects'] = array_keys($service->projects($user));
$diagnostic['plants'] = array_keys($service->powerplants($user));
$diagnostic['service_error'] = $service->error;
header('Content-Type: text/plain; charset=utf-8');
print json_encode($diagnostic, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$db->close();