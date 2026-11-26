<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

define('CSRFCHECK_WITH_TOKEN', 1);
$res = @include '../main.inc.php';
if (!$res) {
	$res = @include '../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once __DIR__.'/class/powerplant.class.php';
require_once __DIR__.'/class/powerplantpvsupervision.class.php';
require_once __DIR__.'/class/powerplantpvatmocelayout.class.php';
require_once __DIR__.'/lib/powerplantpv.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

if (!isModEnabled('powerplantpv') || !$user->hasRight('powerplantpv', 'powerplant', 'read')
	|| !$user->hasRight('powerplantpv', 'serialnumber', 'read')) {
	accessforbidden();
}
$plant = new PowerPlant($db);
$id = GETPOSTINT('id');
if ($id <= 0 || $plant->fetch($id) <= 0) {
	accessforbidden();
}
powerplantpvRequireSharedObjectReadAccess($user, $plant, true, $plant->status == PowerPlant::STATUS_DRAFT ? 1 : 0);
if ($user->socid > 0 && (int) $user->socid !== (int) $plant->fk_soc) {
	accessforbidden();
}

$entity = (int) $plant->entity;
$canImport = $entity === (int) $conf->entity && $user->socid <= 0 && $plant->status != PowerPlant::STATUS_CANCELED
	&& $user->hasRight('powerplantpv', 'powerplant', 'write') && $user->hasRight('powerplantpv', 'serialnumber', 'import');
$layout = new PowerPlantPVAtmoceLayout($db);
$proofId = GETPOSTINT('proof');
if ($proofId > 0) {
	$path = '';
	try {
		$batch = $layout->evidence($plant, $proofId)[0] ?? null;
		if ($batch !== null) {
			$path = powerplantpvIndexPhotoPath($entity, 'layout', $id, $batch->proof_ref);
		}
	} catch (RuntimeException $e) {
		dol_syslog(__FILE__.' layout proof lookup failed', LOG_WARNING);
	}
	powerplantpvIndexPhotoDownload($path);
}

$langs->loadLangs(array('powerplantpv@powerplantpv', 'other'));
$url = dol_buildpath('/powerplantpv/powerplant_atmoce_layout.php', 1).'?id='.$id;
$action = GETPOST('action', 'aZ09', 2);
$service = new PowerPlantPVSupervision($db);
$inventory = null;
$evidence = array();
$positions = array();
$positionsWithProof = array();
$lines = array();
$existing = array();
$canScan = !empty($conf->use_javascript_ajax);
$review = '';
$link = null;
try {
	$link = $service->linkForPlant($entity, $id);
	$evidence = $layout->evidence($plant);
	$positionsWithProof = $layout->positionRows($plant);
	foreach ($positionsWithProof as $row) {
		$positions['sn:'.$row->serial_number][] = $row->position;
	}
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, array('load', 'confirm'), true)) {
		if (!$canImport) {
			accessforbidden();
		}
		$inventory = $service->microinverterInventory($entity, $id);
		$lines = $layout->compositionLines($plant);
		$existing = powerplantpvSerialImportFetchExistingSerials($id, $entity);
		if ($action === 'confirm') {
			$review = GETPOST('review', 'none', 2);
			$upload = GETPOSTINT('keep_photo', 2) ? ($_FILES['proof_image'] ?? array()) : null;
			if (!is_string($review) || strlen($review) > 2000000 || ($upload !== null && !is_array($upload))) {
				throw new RuntimeException('PowerPlantPVLayoutInvalidReview');
			}
			$layout->save($plant, $user, $inventory, $review, $upload);
			setEventMessages($langs->trans('PowerPlantPVLayoutSaved'), null, 'mesgs');
			header('Location: '.$url);
			exit;
		}
	}
} catch (RuntimeException $e) {
	setEventMessages($langs->trans($e->getMessage()), null, 'errors');
}

$title = $langs->trans('PowerPlantPVLayoutTitle');
$scripts = $canScan && $inventory !== null && $inventory['devices']
	? array('/powerplantpv/js/vendor/jsqr-1.4.0.js', '/powerplantpv/js/powerplantpv_atmoce_layout.js') : array();
llxHeader('', $title, '', '', 0, 0, $scripts);
print dol_get_fiche_head(powerplantPrepareHead($plant), 'composition', $langs->trans('PowerPlant'), -1, $plant->picto);
$back = dol_buildpath('/powerplantpv/powerplant_composition.php', 1).'?id='.$id;
dol_banner_tab($plant, 'ref', '<a href="'.dol_escape_htmltag($back).'">'.$langs->trans('PowerPlantMaterialComposition').'</a>', 1, 'ref', 'ref');
print '<div class="fichecenter"><div class="underbanner clearboth"></div>';
print load_fiche_titre($title, '', 'fa-qrcode');

if ($positions) {
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('SerialNumber').'</th><th>'.$langs->trans('PowerPlantPVLayoutPositions').'</th>';
	print '<th>'.$langs->trans('PowerPlantPVLayoutEvidence').'</th></tr>';
	foreach ($positionsWithProof as $row) {
		$proofLink = $row->proof_ref === null ? '—' : '<a href="'.dol_escape_htmltag($url.'&proof='.(int) $row->fk_layout).'">'.$langs->trans('View').'</a>';
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($row->serial_number).'</td><td>'.dol_escape_htmltag($row->position).'</td><td>'.$proofLink.'</td></tr>';
	}
	print '</table></div>';
}

if ($evidence) {
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('PowerPlantPVLayoutEvidence').'</th><th>'.$langs->trans('Date').'</th></tr>';
	foreach ($evidence as $batch) {
		$proofUrl = $url.'&proof='.(int) $batch->rowid;
		print '<tr class="oddeven"><td><a href="'.dol_escape_htmltag($proofUrl).'">'
			.$langs->trans('PowerPlantPVLayoutEvidence').' #'.((int) $batch->rowid).'</a></td><td>'
			.dol_print_date($db->jdate($batch->date_creation), 'dayhour', 'tzuser').'</td></tr>';
	}
	print '</table></div>';
}

if ($canImport && $inventory === null && !empty($link->enabled)) {
	print '<form method="POST" action="'.dol_escape_htmltag($url).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<button type="submit" class="button" name="action" value="load">'.$langs->trans('PowerPlantPVLayoutLoad').'</button></form>';
} elseif ($canImport && $inventory === null) {
	print '<p class="opacitymedium">'.$langs->trans('PowerPlantPVLayoutRequiresSite').'</p>';
} elseif ($inventory !== null && !$inventory['devices']) {
	print '<p class="opacitymedium">'.$langs->trans('PowerPlantPVLayoutNoMicroinverters').'</p>';
} elseif ($inventory !== null && !$canScan) {
	print '<p class="warning">'.$langs->trans('PowerPlantPVLayoutJavascriptRequired').'</p>';
} elseif ($inventory !== null) {
	require __DIR__.'/tpl/atmoce_layout_review.tpl.php';
}

print '</div>';
print dol_get_fiche_end();
llxFooter();
$db->close();
