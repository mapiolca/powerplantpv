<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

if (!defined('DOL_DOCUMENT_ROOT')) {
	exit;
}
$form = new Form($db);
$previousReview = array();
foreach ((array) json_decode($review, true) as $row) {
	if (is_array($row) && is_string($row['serial'] ?? null)) {
		$previousReview['sn:'.$row['serial']] = $row;
	}
}
print '<form id="ppv-atmoce-layout" method="POST" enctype="multipart/form-data" action="'.dol_escape_htmltag($url).'"';
foreach (array('reading' => 'Reading', 'failed' => 'ScanFailed', 'result' => 'ScanResult', 'corners' => 'SelectCorners', 'empty' => 'EmptyReview') as $attribute => $key) {
	print ' data-'.$attribute.'="'.dol_escape_htmltag($langs->transnoentities('PowerPlantPVLayout'.$key, '%s', '%s', '%s')).'"';
}
print '>';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="confirm">';
print '<input type="hidden" name="review" value="">';

print '<fieldset><legend>'.$langs->trans('PowerPlantPVLayoutPhoto').'</legend>';
print '<p>'.$langs->trans('PowerPlantPVLayoutScanHelp').'</p>';
print '<p><label for="ppv-layout-image">'.$langs->trans('PowerPlantPVLayoutPhoto').'</label> ';
print '<input id="ppv-layout-image" type="file" name="proof_image" accept="image/jpeg,image/png"></p>';
print '<p><label for="ppv-layout-zone">'.$langs->trans('PowerPlantPVLayoutZone').'</label> ';
print '<input id="ppv-layout-zone" class="flat maxwidth150" maxlength="72" type="text"> ';
print '<label for="ppv-layout-cols">'.$langs->trans('PowerPlantPVLayoutColumns').'</label> ';
print '<input id="ppv-layout-cols" class="flat maxwidth50" type="number" min="1" max="26" value="6"> ';
print '<label for="ppv-layout-rows">'.$langs->trans('PowerPlantPVLayoutRows').'</label> ';
print '<input id="ppv-layout-rows" class="flat maxwidth50" type="number" min="1" max="40" value="12"> ';
print '<button id="ppv-layout-scan" class="button" type="button" disabled>'.$langs->trans('PowerPlantPVLayoutReadQR').'</button></p>';
print '<canvas id="ppv-layout-preview" hidden style="max-width:100%;height:auto" aria-label="'.dol_escape_htmltag($langs->trans('PowerPlantPVLayoutPhoto')).'"></canvas>';
print '<p id="ppv-layout-status" role="status" aria-live="polite"></p>';
if (powerplantpvIndexPhotoAvailable()) {
	print '<p><label for="keep_photo">'.$langs->trans('PowerPlantPVLayoutArchivePhoto').'</label> ';
	print $form->selectarray('keep_photo', array(0 => $langs->trans('No'), 1 => $langs->trans('Yes')), 0).'</p>';
} else {
	print '<p class="opacitymedium">'.$langs->trans('PowerPlantPVLayoutProofUnavailable').'</p>';
}
print '</fieldset>';

$lineOptions = array();
foreach ($lines as $lineId => $line) {
	if (powerplantpvSerialImportLineCapacity($line, 'add') > 0) {
		$lineOptions[$lineId] = $line['product_display'];
	}
}
if (count($lineOptions) > 1) {
	print '<p><label for="ppv-layout-default-line">'.$langs->trans('PowerPlantPVLayoutDefaultLine').'</label> ';
	print $form->selectarray('ppv-layout-default-line', $lineOptions, 0, 1);
	print ' <button type="button" id="ppv-layout-apply-line" class="button">'.$langs->trans('Apply').'</button></p>';
}
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('SerialNumber').'</th><th>'.$langs->trans('Model').'</th>';
print '<th>'.$langs->trans('PowerPlantCompositionLine').'</th><th>'.$langs->trans('PowerPlantPVLayoutPositions').'</th></tr>';
foreach ($inventory['devices'] as $i => $device) {
	$serial = $device['serial'];
	$known = $existing[powerplantpvSerialImportSerialKey($serial)] ?? null;
	$before = $positions['sn:'.$serial] ?? array();
	$reviewRow = $previousReview['sn:'.$serial] ?? array();
	$labels = is_string($reviewRow['positions'] ?? null) ? $reviewRow['positions'] : implode(', ', $before);
	$selectedLine = (int) ($reviewRow['line'] ?? (count($lineOptions) === 1 ? key($lineOptions) : 0));
	print '<tr class="oddeven" data-serial="'.dol_escape_htmltag($serial).'" data-revision="'.hash('sha256', implode("\n", $before)).'"';
	print ' data-base-positions="'.dol_escape_htmltag(implode(', ', $before)).'" data-known="'.(int) ($known !== null).'">';
	print '<td>'.dol_escape_htmltag($serial).'</td><td>'.dol_escape_htmltag($device['model']).'</td><td>';
	if ($known !== null) {
		$lineId = (int) $known['fk_powerplant_line'];
		print dol_escape_htmltag($lines[$lineId]['product_display'] ?? $langs->trans('PowerPlantPVLayoutAlreadyImported'));
	} else {
		$label = dol_escape_htmltag($langs->trans('PowerPlantCompositionLine').' · '.$serial);
		print $form->selectarray('ppv-layout-line-'.$i, $lineOptions, $selectedLine, 1, 0, 0, 'data-ppv-line="1" aria-label="'.$label.'"');
	}
	$label = dol_escape_htmltag($langs->trans('PowerPlantPVLayoutPositions').' · '.$serial);
	print '</td><td><input type="text" data-ppv-position class="flat minwidth150" aria-label="'.$label.'" value="'.dol_escape_htmltag($labels).'"></td></tr>';
}
print '</table></div>';
print '<p>'.$langs->trans('PowerPlantPVLayoutConfirmHelp').'</p>';
print '<button class="button button-save" type="submit">'.$langs->trans('PowerPlantPVLayoutConfirm').'</button>';
print '</form>';
