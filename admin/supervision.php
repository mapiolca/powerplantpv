<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * Licensed under the GNU General Public License, version 3 or later.
 */

define('CSRFCHECK_WITH_TOKEN', 1);

$res = @include '../../main.inc.php';
if (!$res) {
	$res = @include '../../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once __DIR__.'/../lib/powerplantpv.lib.php';
require_once __DIR__.'/../class/powerplantpvsupervision.class.php';

if (!$user->admin || !isModEnabled('powerplantpv')) {
	accessforbidden();
}

$langs->loadLangs(array('admin', 'powerplantpv@powerplantpv'));
$service = new PowerPlantPVSupervision($db);
$entity = (int) $conf->entity;
$url = dol_buildpath('/powerplantpv/admin/supervision.php', 1);
$action = GETPOST('action', 'aZ09');
$open = '';
$config = null;
$siteCount = 0;
try {
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$open = ' open';
		if ($action === 'save') {
			$service->saveSettings($entity, GETPOSTINT('cadence'), GETPOST('app_key', 'none'), GETPOST('app_secret', 'none'));
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
			header('Location: '.$url.'?settings=1');
			exit;
		}
		if ($action === 'enable' || $action === 'disable') {
			$service->setEnabled($entity, $action === 'enable');
			header('Location: '.$url.'?settings=1');
			exit;
		}
		if ($action === 'test') {
			$service->testConnection($entity);
			setEventMessages($langs->trans('PowerPlantPVSupervisionConnectionSucceeded'), null, 'mesgs');
			header('Location: '.$url.'?settings=1');
			exit;
		}
	}
	$config = $service->configuration($entity);
	$siteCount = $service->countLinks($entity);
} catch (RuntimeException | InvalidArgumentException $e) {
	setEventMessages($langs->trans($e->getMessage()), null, 'errors');
	try {
		$config = $service->configuration($entity);
		$siteCount = $service->countLinks($entity);
	} catch (RuntimeException $readError) {
		setEventMessages($langs->trans($readError->getMessage()), null, 'errors');
	}
}

$form = new Form($db);
$title = $langs->trans('PowerPlantPVSupervision');
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword=powerplantpv">'.$langs->trans('BackToModuleList').'</a>';
llxHeader('', $title);
print load_fiche_titre($title, $linkback, 'title_setup');
print dol_get_fiche_head(powerplantpvAdminPrepareHead(), 'supervision', $title, -1, 'fa-sun');

$cadence = (int) ($config->cadence ?? PowerPlantPVAtmoceClient::MEASUREMENT_INTERVAL_MINUTES);
$estimate = PowerPlantPVSupervision::estimatedCalls($siteCount, $cadence);
$enabled = !empty($config->enabled);
$open = GETPOSTINT('settings') ? ' open' : $open;
print '<details'.$open.'><summary class="liste_titre">ATMOCE Cloud / ATMOZEN '
	.dolGetBadge($langs->trans($enabled ? 'Enabled' : 'Disabled'), '', $enabled ? 'success' : 'neutral').'</summary>';
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('PowerPlantPVSupervisionApiCharacteristics').'</th></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PowerPlantPVSupervisionCapabilities').'</td><td>'.$langs->trans('PowerPlantPVSupervisionAtmoceCapabilities').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PowerPlantPVSupervisionApiAccess').'</td><td>'.$langs->trans('PowerPlantPVSupervisionAtmoceAccess').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PowerPlantPVSupervisionQuota').'</td><td>'
	.$langs->trans('PowerPlantPVSupervisionAtmoceQuota', PowerPlantPVAtmoceClient::MONTHLY_CALL_LIMIT, PowerPlantPVAtmoceClient::CONCURRENT_CALL_LIMIT, PowerPlantPVAtmoceClient::MAX_SITES_PER_REQUEST).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PowerPlantPVSupervisionEstimatedCalls').'</td><td>'
	.$langs->trans('PowerPlantPVSupervisionEstimatedCallsValue', $siteCount, $estimate).'</td></tr>';
if ($estimate >= PowerPlantPVAtmoceClient::MONTHLY_CALL_LIMIT) {
	print '<tr class="oddeven"><td colspan="2">'.dolGetBadge($langs->trans('PowerPlantPVSupervisionQuotaWarning'), '', 'warning').'</td></tr>';
}
print '<tr class="oddeven"><td>'.$langs->trans('PowerPlantPVSupervisionLastSuccess').'</td><td>'
	.(!empty($config->last_success) ? dol_print_date((int) $config->last_success, 'dayhour', 'tzuser') : '—').'</td></tr>';
if (!empty($config->last_error)) {
	print '<tr class="oddeven"><td>'.$langs->trans('Status').'</td><td>'.dol_escape_htmltag($langs->trans($config->last_error)).'</td></tr>';
}
print '</table></div>';

if (!isModEnabled('cron')) {
	print '<div class="warning">'.$langs->trans('PowerPlantPVSupervisionCronRequired').'</div>';
}
if (!function_exists('curl_init')) {
	print '<div class="warning">'.$langs->trans('AtmoceCurlUnavailable').'</div>';
}
print '<form method="POST" action="'.dol_escape_htmltag($url).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('Settings').'</th></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Status').'</td><td>';
$toggleLabel = $langs->trans($enabled ? 'Disable' : 'Enable');
print '<button type="submit" class="button bordertransp" name="action" value="'.($enabled ? 'disable' : 'enable').'"'
	.' aria-label="'.dol_escape_htmltag($toggleLabel).'" title="'.dol_escape_htmltag($toggleLabel).'">'
	.img_picto('', $enabled ? 'switch_on' : 'switch_off').'</button>';
print '</td></tr>';
$cadences = array();
foreach (PowerPlantPVSupervision::CADENCES as $minutes) {
	$cadences[$minutes] = $langs->trans('PowerPlantPVSupervisionMinutes', $minutes);
}
print '<tr class="oddeven"><td><label for="cadence">'.$langs->trans('PowerPlantPVSupervisionCadence').'</label></td><td>'.$form->selectarray('cadence', $cadences, $cadence).'</td></tr>';
print '<tr class="oddeven"><td><label for="app_key">App key</label></td><td><input class="flat minwidth200" type="password" name="app_key" id="app_key" autocomplete="new-password"></td></tr>';
print '<tr class="oddeven"><td><label for="app_secret">App secret</label></td><td><input class="flat minwidth200" type="password" name="app_secret" id="app_secret" autocomplete="new-password">';
if (!empty($config->credentials)) {
	print '<br><span class="opacitymedium">'.$langs->trans('PowerPlantPVSupervisionKeepCredentials').'</span>';
}
print '</td></tr></table></div>';
print '<div class="center"><button class="button button-save" name="action" value="save">'.$langs->trans('Save').'</button>';
print '<button class="button" name="action" value="test" formnovalidate>'.$langs->trans('PowerPlantPVSupervisionTestConnection').'</button></div></form>';
print '</details>';
print dol_get_fiche_end();
llxFooter();
$db->close();
