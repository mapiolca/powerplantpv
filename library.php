<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
$res = 0;
if (file_exists(__DIR__.'/../main.inc.php')) { $res = require __DIR__.'/../main.inc.php'; }
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) { $res = require __DIR__.'/../../main.inc.php'; }
if (!$res) { http_response_code(503); exit('Dolibarr environment unavailable'); }
/** @var DoliDB $db */
/** @var Conf $conf */
/** @var User $user */
/** @var Translate $langs */
/** @var HookManager $hookmanager */
global $db, $conf, $user, $langs, $hookmanager;


require_once __DIR__.'/class/powerplantpvlibraryclient.class.php';
require_once __DIR__.'/class/powerplantpvlibraryimport.class.php';
$langs->loadLangs(array('products', 'powerplantpv@powerplantpv'));
if (!isModEnabled('powerplantpv') || $user->socid > 0 || !$user->hasRight('powerplantpv', 'library', 'read') || !$user->hasRight('produit', 'lire')) { accessforbidden(); }
$form = new Form($db);
$action = GETPOST('action', 'aZ09');
if ($action === 'import' && $_SERVER['REQUEST_METHOD'] !== 'POST') { accessforbidden(); }
$id = GETPOST('catalog_id', 'aZ09');
$search = GETPOST('search', 'alphanohtml');
$page = max(0, GETPOSTINT('page'));
$limit = min(100, max(1, GETPOSTINT('limit') ?: (int) $conf->liste_limit));
if (GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) { $page = 0; }
if (GETPOST('button_removefilter', 'alpha')) { $search = ''; }
$client = new PowerPlantPVLibraryClient();
$item = null; $rows = array(); $libraryId = '';
try {
	$caps = $client->get('/capabilities');
	$libraryId = isset($caps['library_id']) && is_string($caps['library_id']) ? $caps['library_id'] : '';
	if ($id !== '') {
		$item = $client->get('/items/'.$id);
		PowerPlantPVLibraryContract::validate($item);
		if ($action === 'import') {
			if (!$user->hasRight('powerplantpv', 'library', 'import') || !$user->hasRight('produit', 'creer')) { accessforbidden(); }
			// Re-fetch on submission and reject a changed revision, never trust browser-supplied technical values.
			if (GETPOSTINT('revision') !== $item['revision']) { throw new RuntimeException('PVLibraryRevisionChanged'); }
			$service = new PowerPlantPVLibraryImport($db);
			$productId = $service->import($item, $libraryId, $user);
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
			header('Location: '.DOL_URL_ROOT.'/product/card.php?id='.$productId); exit;
		}
	} else {
		$data = $client->get('/items?search='.rawurlencode($search).'&page='.$page.'&limit='.$limit);
		$rows = isset($data['items']) && is_array($data['items']) ? $data['items'] : array();
	}
} catch (Throwable $e) {
	$message = preg_match('/^PVLibrary[A-Za-z]+$/D', $e->getMessage()) ? $e->getMessage() : 'PVLibraryRemoteError';
	setEventMessages($langs->trans($message), null, 'errors');
}
llxHeader('', $langs->trans('PVLibraryTitle'));
if (is_array($item)) {
	print load_fiche_titre($langs->trans('PVLibraryTitle'), '', 'book');
	print '<table class="border centpercent tableforfield">';
	foreach (array('manufacturer' => 'PVLibraryManufacturer', 'model' => 'PVLibraryModel', 'label' => 'Label', 'revision' => 'PVLibraryRevision') as $key => $label) {
		print '<tr><td class="titlefield">'.$langs->trans($label).'</td><td>'.dol_escape_htmltag((string) $item[$key]).'</td></tr>';
	}
	print '</table><br><div class="div-table-responsive-no-min"><table class="noborder centpercent"><tr class="liste_titre">';
	foreach (array('Field', 'Value', 'Unit', 'PVLibraryEvidence') as $label) { print '<td>'.$langs->trans($label).'</td>'; }
	print '</tr>';
	foreach ($item['technical'] as $field => $value) {
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($field).'</td><td>'.dol_escape_htmltag((string) $value['value']).'</td><td>'.dol_escape_htmltag($value['unit']).'</td><td>'.dol_escape_htmltag($value['quote']).'<br>'.dol_escape_htmltag($value['source_url']).'</td></tr>';
	}
	print '</table></div><p>'.$langs->trans('PVLibraryImportHelp').'</p>';
	if ($user->hasRight('powerplantpv', 'library', 'import') && $user->hasRight('produit', 'creer')) {
		print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="import"><input type="hidden" name="catalog_id" value="'.dol_escape_htmltag($item['catalog_id']).'"><input type="hidden" name="revision" value="'.((int) $item['revision']).'">';
		print $form->buttonsSaveCancel('PVLibraryConfirmImport', ''); print '</form>';
	}
} else {
	print '<form method="GET" action="'.dol_buildpath('/powerplantpv/library.php', 1).'">';
	$param = '&search='.urlencode($search);
	print_barre_liste($langs->trans('PVLibraryTitle'), $page, $_SERVER['PHP_SELF'], $param, '', '', '', count($rows), -1, 'book', 0, '', '', $limit);
	print '<input type="search" class="flat minwidth200" name="search" value="'.dol_escape_htmltag($search).'">'.$form->showFilterButtons();
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent"><tr class="liste_titre"><td>'.$langs->trans('PVLibraryManufacturer').'</td><td>'.$langs->trans('PVLibraryModel').'</td><td>'.$langs->trans('Label').'</td><td></td></tr>';
	foreach ($rows as $row) {
		if (!is_array($row) || !preg_match('/^[a-f0-9]{32}$/D', (string) ($row['catalog_id'] ?? ''))) { continue; }
		print '<tr class="oddeven"><td>'.dol_escape_htmltag((string) ($row['manufacturer'] ?? '')).'</td><td>'.dol_escape_htmltag((string) ($row['model'] ?? '')).'</td><td>'.dol_escape_htmltag((string) ($row['label'] ?? '')).'</td><td><a href="'.dol_buildpath('/powerplantpv/library.php', 1).'?catalog_id='.$row['catalog_id'].'">'.$langs->trans('Preview').'</a></td></tr>';
	}
	if (!$rows) { print '<tr class="oddeven"><td colspan="4"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>'; }
	print '</table></div>';
	print '</form>';
}
llxFooter(); $db->close();
