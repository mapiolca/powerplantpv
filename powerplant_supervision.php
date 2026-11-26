<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * Licensed under the GNU General Public License, version 3 or later.
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
require_once __DIR__.'/lib/powerplantpv.lib.php';
require_once __DIR__.'/lib/powerplantpv_powerplant.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php';

if (!isModEnabled('powerplantpv') || !$user->hasRight('powerplantpv', 'powerplant', 'read')) {
	accessforbidden();
}
$object = new PowerPlant($db);
$id = GETPOSTINT('id');
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden();
}
powerplantpvRequireSharedObjectReadAccess($user, $object, true, $object->status == PowerPlant::STATUS_DRAFT ? 1 : 0);
if ($user->socid > 0 && (int) $user->socid !== (int) $object->fk_soc) {
	accessforbidden();
}

$langs->loadLangs(array('other', 'powerplantpv@powerplantpv'));
$service = new PowerPlantPVSupervision($db);
$entity = (int) $object->entity;
$canLink = (int) $conf->entity === $entity && $user->socid <= 0 && $user->hasRight('powerplantpv', 'powerplant', 'write');
$url = dol_buildpath('/powerplantpv/powerplant_supervision.php', 1).'?id='.$id;
$page = max(1, GETPOSTINT('sitepage'));
$sites = null;
$link = null;
$readings = array();
$action = GETPOST('action', 'aZ09');
$period = GETPOST('period', 'aZ09');
if (!in_array($period, array('today', 'week', 'month'), true)) {
	$period = 'today';
}
$timezone = new DateTimeZone('UTC');
try {
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, array('sites', 'link'), true)) {
		if (!$canLink) {
			accessforbidden();
		}
		if ($action === 'link') {
			$service->link($entity, $id, GETPOST('site_id', 'alphanohtml'), $page);
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
			header('Location: '.$url);
			exit;
		}
		$sites = $service->sites($entity, $page);
	}
	$link = $service->linkForPlant($entity, $id);
	if ($link !== null) {
		try {
			$timezone = new DateTimeZone($link->site_timezone !== '' ? preg_replace('/^UTC(?=[+-])/', '', $link->site_timezone) : 'UTC');
		} catch (Exception $e) {
			$timezone = new DateTimeZone('UTC');
		}
		$today = new DateTimeImmutable('today', $timezone);
		$days = array('today' => 1, 'week' => 7, 'month' => 30)[$period];
		$since = $today->modify('-'.($days - 1).' days');
		$readings = $service->readings($entity, $link, $since->getTimestamp());
		$latest = $readings[0] ?? ($service->readings($entity, $link, 0, 1)[0] ?? null);
	}
} catch (RuntimeException | InvalidArgumentException $e) {
	setEventMessages($langs->trans($e->getMessage()), null, 'errors');
}

$title = $langs->trans('PowerPlantPVSupervision');
llxHeader('', $title);
print dol_get_fiche_head(powerplantPrepareHead($object), 'supervision', $langs->trans('PowerPlant'), -1, $object->picto);
$linkback = '<a href="'.dol_buildpath('/powerplantpv/powerplant_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');
print '<div class="underbanner clearboth"></div>';

if ($link !== null) {
	$reading = $latest ?? null;
	$state = empty($link->enabled) ? 'Disabled' : PowerPlantPVSupervision::displayState($reading, (int) $link->cadence, time());
	$badgeTypes = array(
		'PowerPlantPVSupervisionNormal' => 'success',
		'PowerPlantPVSupervisionOffline' => 'danger',
		'PowerPlantPVSupervisionFault' => 'danger',
		'PowerPlantPVSupervisionStale' => 'warning',
	);
	print '<div class="div-table-responsive-no-min"><table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield">'.$langs->trans('PowerPlantPVSupervisionSite').'</td><td>ATMOCE Cloud · '.dol_escape_htmltag($link->site_name).'</td></tr>';
	print '<tr><td>'.$langs->trans('Status').'</td><td>'.dolGetBadge($langs->trans($state), '', $badgeTypes[$state] ?? 'neutral').'</td></tr>';
	print '<tr><td>'.$langs->trans('PowerPlantPVSupervisionLastMeasure').'</td><td>'
		.($reading !== null ? dol_print_date((int) $reading->reported_at, 'dayhour', 'tzuser') : '—').'</td></tr>';
	print '<tr><td>'.$langs->trans('PowerPlantPVSupervisionLastPower').'</td><td>'
		.(isset($reading->power_w) ? price((float) $reading->power_w / 1000).' kW' : '—').'</td></tr>';
	print '<tr><td>'.$langs->trans('PowerPlantPVSupervisionDailyEnergy').'</td><td>'
		.(isset($reading->energy_today_kwh) && (int) $reading->reported_at >= $today->getTimestamp() ? price($reading->energy_today_kwh).' kWh' : '—').'</td></tr>';
	if ($state === 'PowerPlantPVSupervisionStale' && $reading !== null) {
		$lastKnown = PowerPlantPVSupervision::displayState($reading, (int) $link->cadence, (int) $reading->reported_at);
		print '<tr><td>'.$langs->trans('PowerPlantPVSupervisionLastKnownState').'</td><td>'.$langs->trans($lastKnown).'</td></tr>';
	}
	if ($canLink && !empty($link->last_error)) {
		print '<tr><td>'.$langs->trans('PowerPlantPVSupervisionLastCollectionError').'</td><td>'.dol_escape_htmltag($langs->trans($link->last_error)).'</td></tr>';
	}
	print '</table></div>';

	$periods = array('today' => 'Today', 'week' => 'PowerPlantPVSupervisionSevenDays', 'month' => 'PowerPlantPVSupervisionThirtyDays');
	print '<div class="tabsAction">';
	foreach ($periods as $key => $label) {
		print dolGetButtonAction($langs->trans($label), '', 'default', $url.'&period='.$key, '', $period !== $key);
	}
	print '</div>';

	$data = array();
	if (isset($today, $since) && $period === 'today') {
		$slots = array();
		foreach (array_reverse($readings) as $row) {
			$slot = (int) (floor(((int) $row->reported_at - $since->getTimestamp()) / 900) * 900) + $since->getTimestamp();
			$slots[$slot] = $row->power_w === null ? null : ((float) $row->power_w / 1000);
		}
		$lastSlot = $slots ? max(array_keys($slots)) : 0;
		for ($slot = $since->getTimestamp(); $slot <= $lastSlot; $slot += 900) {
			$label = (new DateTimeImmutable('@'.$slot))->setTimezone($timezone)->format('H:i');
			$data[] = array($label, $slots[$slot] ?? null);
		}
	} elseif (isset($today, $since)) {
		$daily = array();
		foreach ($readings as $row) {
			$date = (new DateTimeImmutable('@'.$row->reported_at))->setTimezone($timezone)->format('Y-m-d');
			if (!isset($daily[$date]) && $row->energy_today_kwh !== null) {
				$daily[$date] = (float) $row->energy_today_kwh;
			}
		}
		for ($day = $since; $day <= $today; $day = $day->modify('+1 day')) {
			$data[] = array($day->format('d/m'), $daily[$day->format('Y-m-d')] ?? null);
		}
	}
	$hasNumeric = count(array_filter(array_column($data, 1), 'is_numeric')) > 0;
	if ($hasNumeric && !empty($conf->use_javascript_ajax)) {
		$graph = new DolGraph();
		$graph->SetData($data);
		$graph->SetLegend(array($period === 'today' ? 'kW' : 'kWh'));
		$graph->SetType(array($period === 'today' ? 'lines' : 'bars'));
		$graph->setHeight('240');
		$graph->setWidth(!empty($conf->dol_optimize_smallscreen) ? '320' : '680');
		$graph->setMinValue(0);
		$graph->draw('ppv-monitor-production');
		print '<div class="div-table-responsive-no-min">'.$graph->show(0).'</div>';
	} elseif ($hasNumeric) {
		print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
		foreach ($data as [$label, $value]) {
			print '<tr class="oddeven"><td>'.dol_escape_htmltag($label).'</td><td>'.($value === null ? '—' : price($value)).'</td></tr>';
		}
		print '</table></div>';
	} else {
		print '<table class="noborder centpercent"><tr class="oddeven"><td class="opacitymedium">'.$langs->trans('NoRecordFound').'</td></tr></table>';
	}
} else {
	print '<div class="opacitymedium">'.$langs->trans('PowerPlantPVSupervisionNoLink').'</div>';
}

if ($canLink) {
	$form = new Form($db);
	print '<form method="POST" action="'.dol_escape_htmltag($url).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	if ($sites === null) {
		print '<button class="button" name="action" value="sites">'.$langs->trans('PowerPlantPVSupervisionFindSites').'</button>';
	} else {
		$options = array();
		foreach ($sites['sites'] as $site) {
			$options[$site['id']] = $site['name'].' ('.$site['id'].')';
		}
		if (!$options) {
			print '<p>'.$langs->trans('NoRecordFound').'</p>';
		} else {
			print '<label for="site_id">'.$langs->trans('PowerPlantPVSupervisionSite').'</label> ';
			print $form->selectarray('site_id', $options, $link->site_id ?? '', 1);
			print '<input type="hidden" name="sitepage" value="'.$page.'">';
			print '<button class="button button-save" name="action" value="link">'.$langs->trans('Save').'</button>';
		}
	}
	print '</form>';
	if ($sites !== null && ($page > 1 || !$sites['complete'])) {
		print '<form method="POST" action="'.dol_escape_htmltag($url).'"><input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="sites">';
		if ($page > 1) {
			print '<button class="button" name="sitepage" value="'.($page - 1).'">'.$langs->trans('Previous').'</button>';
		}
		if (!$sites['complete']) {
			print '<button class="button" name="sitepage" value="'.($page + 1).'">'.$langs->trans('Next').'</button>';
		}
		print '</form>';
	}
}

print dol_get_fiche_end();
llxFooter();
$db->close();
