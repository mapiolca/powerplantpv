<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once __DIR__.'/../class/powerplantpvreportplantcompletion.class.php';

/** Render saved status and supply each authorized plant's composition to its draft editor. */
function powerplantpvRenderReportCompletion(PowerPlantPVReportPlantCompletion $service, PowerPlantPVReport $report, Fichinter $intervention, User $user, bool $editable, bool $retry, array $submitted, Translate $langs): void
{
	global $conf;
	if (!$user->hasRight('powerplantpv', 'powerplant', 'read')) {
		return;
	}
	try {
		$stored = $service->forReport((int) $report->id, (int) $intervention->entity);
		$catalog = $editable ? $service->catalog() : array();
		$connectionTypes = array();
		if ($editable) {
			$langs->load('products');
			foreach (PowerPlant::getConnectionTypeOptions() as $code => $label) {
				$connectionTypes[$code] = $langs->transnoentities($label);
			}
		}
		$plants = powerplantpvGetLinkedPowerPlants($intervention);
		$options = array('inverters' => array(), 'modules' => array());
		foreach ($catalog as $id => $product) {
			$options[$product->code === 'MODULE' ? 'modules' : 'inverters'][$id] = $product->ref.' — '.$product->label;
		}
		$labels = array();
		foreach (array('PowerPlantPrmPdlNumber', 'PowerPlantConnectionType', 'PowerPlantPVInverter', 'PowerPlantPVMPPT', 'PowerPlantPVPVInputCount',
			'PowerPlantPVCompletionInput', 'PowerPlantPVCompletionMarker', 'PowerPlantPVCompletionIdentification',
			'PowerPlantPDFSerialNumber', 'PowerPlantPDFModules', 'Qty', 'Add', 'Delete', 'Product', 'PowerPlantPVCompletionAddInverter',
			'PowerPlantPVCompletionAddModules', 'PowerPlantPVCompletionAddMppt', 'PowerPlantPVCompletionAddString', 'PowerPlantPVCompletionInstalled') as $key) {
			$labels[$key] = $langs->transnoentities($key);
		}
		$editors = array();
		$states = array();
		foreach ($plants as $id => $linked) {
			$plant = $service->plantForAuthor($intervention, $user, (int) $id);
			if (!$plant) {
				continue;
			}
			$entry = $stored[$id] ?? null;
			if ($entry) {
				$states[$id] = array($plant, $entry);
			}
			if (!$editable || empty($conf->use_javascript_ajax)) {
				continue;
			}
			$composition = $service->composition($plant, $catalog);
			unset($composition['used']);
			$editors[] = array(
				'id' => (int) $id,
				'label' => $plant->ref.' — '.$plant->label,
				'current' => $composition + array('prm' => (string) $plant->prm_pdl_number, 'type' => (string) $plant->connection_type,
					'typeLabel' => PowerPlant::getConnectionTypeLabel($plant->connection_type, $langs)),
				'revision' => is_string($submitted[$id]['revision'] ?? null) ? $submitted[$id]['revision'] : hash('sha256', $entry->proposal ?? ''),
				'proposal' => (object) (is_array($submitted[$id]['proposal'] ?? null) ? $submitted[$id]['proposal'] : ($entry->data ?? array())),
				'dirty' => isset($submitted[$id]),
			);
		}
	} catch (RuntimeException | JsonException $e) {
		print '<div class="error">'.dol_escape_htmltag($langs->trans(PowerPlantPVReportPlantCompletion::DATABASE)).'</div>';
		return;
	}

	if (!$editors && !$states && (!$editable || !$plants)) {
		return;
	}
	print '<section id="powerplantpv-plant-completion">';
	print load_fiche_titre($langs->trans('PowerPlantPVCompletion'), '', 'fa-solar-panel');
	if ($editable && empty($conf->use_javascript_ajax)) {
		print '<p class="opacitymedium">'.dol_escape_htmltag($langs->trans('PowerPlantPVCompletionJavascript')).'</p>';
	}
	$statusKeys = array('PowerPlantPVCompletionPending', 'PowerPlantPVCompletionApplied', 'PowerPlantPVCompletionReview', 'PowerPlantPVCompletionFailed');
	foreach ($states as $id => [$plant, $entry]) {
		$status = (int) $entry->status;
		$label = $status === PowerPlantPVReportPlantCompletion::PENDING
			&& (int) $intervention->signed_status === PowerPlantPVReport::interventionSignatureStatuses()['STATUS_SIGNED_ALL']
			? 'PowerPlantPVCompletionQueued' : $statusKeys[$status];
		print '<p>'.dol_escape_htmltag($plant->ref).' '.dolGetBadge($langs->trans($label), '', 'status'.(array(4, 1, 8, 9)[$status]));
		if ($entry->error_code) {
			print ' '.dol_escape_htmltag($langs->trans($entry->error_code));
		}
		if ($retry && $status === PowerPlantPVReportPlantCompletion::FAILED) {
			print ' <button class="button" type="submit" name="retry_plant_completion" value="'.(int) $id.'">'.dol_escape_htmltag($langs->trans('PowerPlantPVCompletionRetry')).'</button>';
		}
		print '</p>';
	}
	if (!isModEnabled('cron') && ($editors || $states)) {
		print '<p class="warning">'.dol_escape_htmltag($langs->trans('PowerPlantPVCompletionCronDisabled')).'</p>';
	}
	if ($editors) {
		$data = json_encode(compact('editors', 'options', 'connectionTypes', 'labels'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
		print '<input type="hidden" name="plant_completion" data-completion-payload>';
		print '<script type="application/json" data-completion-config>'.$data.'</script>';
	}
	print '</section>';
}
