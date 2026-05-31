<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file        core/tpl/linkedobjectblock.tpl.php
 * \ingroup     powerplantpv
 * \brief       Template for power plants in native linked objects blocks.
 */

if (empty($conf) || !is_object($conf)) {
	print "Error, template page can't be called as URL";
	exit;
}

if (!in_array($objecttype, array('powerplantpv_powerplant', 'powerplant@powerplantpv', 'powerplant'))) {
	return 0;
}

print "<!-- BEGIN PHP TEMPLATE powerplantpv/core/tpl/linkedobjectblock.tpl.php -->\n";

global $permissiondellink, $noMoreLinkedObjectBlockAfter;

$langs = $GLOBALS['langs'];
$linkedObjectBlock = $GLOBALS['linkedObjectBlock'];
$langs->load('powerplantpv@powerplantpv');
dol_include_once('/powerplantpv/class/powerplant.class.php');

$totalpower = 0;
$haspower = false;
$nboutput = 0;
$ilink = 0;

foreach ($linkedObjectBlock as $key => $objectlink) {
	$ilink++;
	if (!is_object($objectlink) || empty($objectlink->id)) {
		continue;
	}
	if (!is_a($objectlink, 'PowerPlant')) {
		$powerplant = new PowerPlant($object->db);
		if ($powerplant->fetch((int) $objectlink->id) <= 0) {
			continue;
		}
		$objectlink = $powerplant;
	}

	$nboutput++;
	$trclass = 'oddeven linkedobjectblock linkedobjectblock-powerplant';
	if ($ilink == count($linkedObjectBlock) && empty($noMoreLinkedObjectBlockAfter) && count($linkedObjectBlock) <= 1) {
		$trclass .= ' liste_sub_total';
	}

	$installedpower = '';
	if ($objectlink->installed_power !== null && $objectlink->installed_power !== '') {
		$installedpower = price($objectlink->installed_power).' kWc';
		$totalpower += (float) $objectlink->installed_power;
		$haspower = true;
	}

	$actionhtml = '';
	if (!empty($permissiondellink)) {
		$url = $_SERVER['PHP_SELF'].'?id='.((int) $object->id).'&action=dellink&token='.newToken().'&dellinkid='.((int) $key);
		$actionhtml = '<a class="reposition" href="'.dol_escape_htmltag($url).'">'.img_picto($langs->trans('RemoveLink'), 'unlink').'</a>';
	}

	print '<tr class="'.$trclass.'" data-element="'.$objectlink->element.'" data-id="'.$objectlink->id.'">';
	print '<td class="linkedcol-element tdoverflowmax100">'.$langs->trans('PowerPlant').'</td>';
	print '<td class="linkedcol-name tdoverflowmax150">'.$objectlink->getNomUrl(1).'</td>';
	print '<td class="linkedcol-ref">'.dol_escape_htmltag((string) $objectlink->prm_pdl_number).'</td>';
	print '<td class="linkedcol-date center">'.(!empty($objectlink->commissioning_date) ? dol_print_date($objectlink->commissioning_date, 'day') : '').'</td>';
	print '<td class="linkedcol-amount right">'.$installedpower.'</td>';
	print '<td class="linkedcol-statut right">'.$objectlink->getLibStatut(3).'</td>';
	print '<td class="linkedcol-action right">'.$actionhtml.'</td>';
	print '</tr>';
}

if ($nboutput > 1 && $haspower) {
	print '<tr class="liste_total '.(empty($noMoreLinkedObjectBlockAfter) ? 'liste_sub_total' : '').'">';
	print '<td>'.$langs->trans('Total').'</td>';
	print '<td></td>';
	print '<td class="center"></td>';
	print '<td class="center"></td>';
	print '<td class="right">'.price($totalpower).' kWc</td>';
	print '<td class="right"></td>';
	print '<td class="right"></td>';
	print '</tr>';
}

print "<!-- END PHP TEMPLATE powerplantpv/core/tpl/linkedobjectblock.tpl.php -->\n";

return 1;
