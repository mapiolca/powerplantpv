<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
$res = 0;
if (file_exists(__DIR__.'/../../main.inc.php')) { $res = require __DIR__.'/../../main.inc.php'; }
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) { $res = require __DIR__.'/../../../main.inc.php'; }
if (!$res) { http_response_code(503); exit('Dolibarr environment unavailable'); }
/** @var DoliDB $db */
/** @var Conf $conf */
/** @var User $user */
/** @var Translate $langs */
/** @var HookManager $hookmanager */
global $db, $conf, $user, $langs, $hookmanager;


require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once __DIR__.'/../lib/powerplantpv.lib.php';
$langs->loadLangs(array('admin', 'powerplantpv@powerplantpv'));
if (!isModEnabled('powerplantpv') || !$user->admin || $user->socid > 0) { accessforbidden(); }
$form = new Form($db);
$action = GETPOST('action', 'aZ09');
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] !== 'POST') { accessforbidden(); }
if ($action === 'save') {
	$url = rtrim(GETPOST('library_url', 'alphanohtml'), '/');
	if ($url !== '' && !preg_match('~^https://[a-zA-Z0-9.-]+(?::443)?(?:/[a-zA-Z0-9_/-]+)?$~D', $url)) {
		setEventMessages($langs->trans('PVLibraryInvalidURL'), null, 'errors');
	} elseif (dolibarr_set_const($db, 'POWERPLANTPV_LIBRARY_URL', $url, 'chaine', 0, '', (int) $conf->entity) > 0) {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.dol_buildpath('/powerplantpv/admin/library.php', 1)); exit;
	}
}
llxHeader('', $langs->trans('PVLibraryTitle'));
print load_fiche_titre($langs->trans('PVLibraryTitle'), '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword=powerplantpv">'.$langs->trans('BackToModuleList').'</a>', 'solar-panel');
print dol_get_fiche_head(powerplantpvAdminPrepareHead(), 'library', $langs->trans('PVLibraryTitle'), -1, 'solar-panel');
print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save">';
print '<table class="noborder centpercent"><tr class="liste_titre"><td>'.$langs->trans('Parameter').'</td><td>'.$langs->trans('Value').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVLibraryURL').'</td><td><input class="flat minwidth300" name="library_url" value="'.dol_escape_htmltag(getDolGlobalString('POWERPLANTPV_LIBRARY_URL')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVLibrarySecret').'</td><td>'.$langs->trans('PVLibrarySecretHelp', 'POWERPLANTPV_LIBRARY_API_KEY_'.((int) $conf->entity)).'</td></tr></table>';
print $form->buttonsSaveCancel('Save', '');
print '</form>'.dol_get_fiche_end();
llxFooter(); $db->close();
