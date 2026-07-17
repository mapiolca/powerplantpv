<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file ajax/maintenance_widget_tooltip.php
 * \ingroup powerplantpv
 * \brief Return translated maintenance widget help.
 */

if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('NOHEADERNOFOOTER')) {
	define('NOHEADERNOFOOTER', '1');
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

/** @var User $user */
/** @var Translate $langs */
/** @var DoliDB $db */
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/class/powerplantpvmaintenancewidget.class.php');

$langs->loadLangs(array('powerplantpv@powerplantpv', 'errors'));
top_httphead('text/html; charset=UTF-8');

if (!isModEnabled('powerplantpv') || !getDolGlobalInt('POWERPLANTPV_MAINTENANCE_ENABLE', 1)
	|| !powerplantpvUserHasMaintenanceRight($user, 'read')
	|| !powerplantpvUserHasRightPath($user, array('powerplantpv', 'powerplant', 'read'))
) {
	http_response_code(403);
	print dol_escape_htmltag($langs->trans('ReadPermissionNotAllowed'));
	exit;
}

if (!GETPOST('token', 'alpha') || (function_exists('checkToken') && !checkToken())) {
	http_response_code(403);
	print dol_escape_htmltag($langs->trans('ErrorForbidden'));
	exit;
}

$widgetCode = (string) GETPOST('widget_code', 'aZ09');
$catalog = PowerPlantPVMaintenanceWidget::getCatalog();
if (!isset($catalog[$widgetCode]) || empty($catalog[$widgetCode]['stats'])) {
	http_response_code(400);
	print dol_escape_htmltag($langs->trans('ErrorBadParameters'));
	exit;
}

print PowerPlantPVMaintenanceWidget::getTooltipContent($widgetCode);
$db->close();
