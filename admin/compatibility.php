<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
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
 * \file		admin/compatibility.php
 * \ingroup		powerplantpv
 * \brief		Compatibility settings page.
 */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
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

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once '../lib/powerplantpv.lib.php';
require_once '../class/powerplantpvcompatibility.class.php';

$langs->loadLangs(array('admin', 'powerplantpv@powerplantpv'));

if (empty($user->admin)) {
	accessforbidden();
}

$title = $langs->trans('Compatibility');
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword='.urlencode('powerplantpv').'">'.img_picto($langs->trans('BackToModuleList'), 'back', 'class="pictofixedwidth"').'<span class="hideonsmartphone">'.$langs->trans('BackToModuleList').'</span></a>';

llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-powerplantpv page-admin');

print load_fiche_titre($title, $linkback, 'title_setup');
$head = powerplantpvAdminPrepareHead();
print dol_get_fiche_head($head, 'compatibility', $title, -1, 'fa-sun');

$features = PowerPlantPVCompatibility::getFeatures();

print '<div class="fichecenter">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('PowerPlantPVCompatibilityEnvironment').'</th></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('PowerPlantPVDetectedPhpVersion').'</td><td>'.dol_escape_htmltag(PHP_VERSION).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PowerPlantPVDetectedDolibarrVersion').'</td><td>'.dol_escape_htmltag(defined('DOL_VERSION') ? DOL_VERSION : '').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PowerPlantPVMinPhpVersion').'</td><td>'.PowerPlantPVCompatibility::MIN_PHP_VERSION.'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PowerPlantPVMinDolibarrVersion').'</td><td>'.PowerPlantPVCompatibility::MIN_DOLIBARR_VERSION.'</td></tr>';
print '</table>';
print '</div>';

print '<br>';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Code').'</th>';
print '<th>'.$langs->trans('Label').'</th>';
print '<th>'.$langs->trans('Description').'</th>';
print '<th>'.$langs->trans('PowerPlantPVMinDolibarrVersion').'</th>';
print '<th>'.$langs->trans('PowerPlantPVMinPhpVersion').'</th>';
print '<th class="center">'.$langs->trans('Status').'</th>';
print '<th>'.$langs->trans('Reason').'</th>';
print '</tr>';
foreach ($features as $code => $feature) {
	$available = !empty($feature['available']);
	print '<tr class="oddeven">';
	print '<td><code>'.dol_escape_htmltag($code).'</code></td>';
	print '<td>'.dol_escape_htmltag($langs->trans($feature['label'])).'</td>';
	print '<td>'.dol_escape_htmltag($langs->trans($feature['description'])).'</td>';
	print '<td>'.dol_escape_htmltag($feature['min_dolibarr']).'</td>';
	print '<td>'.dol_escape_htmltag($feature['min_php']).'</td>';
	print '<td class="center"><span class="badge '.($available ? 'badge-status4' : 'badge-status8').'">'.$langs->trans($available ? 'Available' : 'Unavailable').'</span></td>';
	print '<td>'.(!empty($feature['reason']) ? dol_escape_htmltag($langs->trans($feature['reason'])) : '&mdash;').'</td>';
	print '</tr>';
}
print '</table>';
print '</div>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
