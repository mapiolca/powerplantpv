<?php
/* Copyright (C) 2007-2017  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2024-2025  Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2026       Pierre Ardoin           <developpeur@lesmetiersdubatiment.fr>
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
 *  \file       powerplant_location.php
 *  \ingroup    powerplantpv
 *  \brief      Tab for location and access instructions on PowerPlant
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

/**
 * The main.inc.php has been included so the following variables are now defined:
 *
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */
include_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/powerplantpv/class/powerplant.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv_powerplant.lib.php');

// Load translation files required by the page
$langs->loadLangs(array('powerplantpv@powerplantpv', 'companies'));

// Get parameters
$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');
$backtopage = GETPOST('backtopage', 'restricthtml');
$socid = GETPOSTINT('socid');

// Initialize technical objects
$object = new PowerPlant($db);
$form = new Form($db);
$hookmanager->initHooks(array($object->element.'location', 'globalcard'));

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

// Permissions
$enablepermissioncheck = getDolGlobalInt('POWERPLANTPV_ENABLE_PERMISSION_CHECK');
if ($enablepermissioncheck) {
	$permissiontoread = $user->hasRight('powerplantpv', 'powerplant', 'read');
	$permissiontoadd = $user->hasRight('powerplantpv', 'powerplant', 'write');
} else {
	$permissiontoread = 1;
	$permissiontoadd = 1;
}

// Security check
if ($user->socid > 0) {
	$socid = $user->socid;
}
$isdraft = (($object->status == $object::STATUS_DRAFT) ? 1 : 0);
restrictedArea($user, $object->module, $object, $object->table_element, $object->element, 'fk_soc', 'rowid', $isdraft);
if (!isModEnabled('powerplantpv')) {
	accessforbidden();
}
if (!$permissiontoread) {
	accessforbidden();
}

/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}
if (empty($reshook)) {
	powerplantHandleSetLabelAction($object, $action, $permissiontoadd, $user);
	powerplantHandleSetThirdpartyAction($object, $action, $permissiontoadd, $user);

	if ($action == 'update_location') {
		if ($cancel) {
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $object->id);
			exit;
		}
		if (empty($permissiontoadd)) {
			accessforbidden();
		}
		if (function_exists('checkToken') && !checkToken()) {
			accessforbidden();
		}

		$object->address = GETPOST('address', 'restricthtml');
		$object->zip = GETPOST('zip', 'restricthtml');
		$object->town = GETPOST('town', 'restricthtml');
		$object->fk_country = GETPOSTINT('fk_country');
		$object->access_instructions = GETPOST('access_instructions', 'restricthtml');

		$result = $object->update($user);
		if ($result > 0) {
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $object->id);
			exit;
		}
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

/*
 * View
 */

$object->fields['fk_country']['type'] = 'sellist:c_country:label:rowid::active=1';

$title = $langs->trans('PowerPlant').' - '.$langs->trans('PowerPlantLocationAccess');
$help_url = '';

llxHeader('', $title, $help_url, '', 0, 0, '', '', '', 'mod-powerplantpv page-card_location');

if ($id > 0 || !empty($ref)) {
	$object->fetch_thirdparty();

	$head = powerplantPrepareHead($object);

	print dol_get_fiche_head($head, 'location', $langs->trans('PowerPlant'), -1, $object->picto, 0, '', '', 0, '', 1);

	$linkback = powerplantGetBackToListLink($object, $socid);
	$morehtmlref = powerplantBuildBannerMoreHtml($object, $permissiontoadd, $action);
	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';

	if ($action == 'edit') {
		if (empty($permissiontoadd)) {
			accessforbidden();
		}

		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.(int) $object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="update_location">';
		if ($backtopage) {
			print '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">';
		}

		print load_fiche_titre($langs->trans('Localisation'), '', 'country');
		print '<table class="border centpercent tableforfieldedit">'."\n";
		foreach (array('address', 'zip', 'town', 'fk_country') as $field) {
			$def = $object->fields[$field];
			$value = isset($object->$field) ? $object->$field : '';
			print '<tr class="field_'.$field.'">';
			print '<td class="titlefieldcreate">'.$langs->trans($def['label']).'</td>';
			print '<td class="valuefieldcreate">'.$object->showInputField($def, $field, $value, '', '', '', '').'</td>';
			print '</tr>';
		}
		print '</table>';

		print load_fiche_titre($langs->trans('PowerPlantAccess'), '', 'lock');
		print '<table class="border centpercent tableforfieldedit">'."\n";
		$def = $object->fields['access_instructions'];
		print '<tr class="field_access_instructions">';
		print '<td class="titlefieldcreate">'.$langs->trans($def['label']).'</td>';
		print '<td class="valuefieldcreate">'.$object->showInputField($def, 'access_instructions', $object->access_instructions, '', '', '', '').'</td>';
		print '</tr>';
		print '</table>';

		print '</div>';
		print dol_get_fiche_end();
		print $form->buttonsSaveCancel();
		print '</form>';
	} else {
		print load_fiche_titre($langs->trans('Localisation'), '', 'country');
		print '<table class="border centpercent tableforfield">'."\n";
		foreach (array('address', 'zip', 'town', 'fk_country') as $field) {
			$def = $object->fields[$field];
			$value = isset($object->$field) ? $object->$field : '';
			print '<tr class="field_'.$field.'">';
			print '<td class="titlefieldmiddle">'.$langs->trans($def['label']).'</td>';
			print '<td class="valuefield">'.$object->showOutputField($def, $field, $value).'</td>';
			print '</tr>';
		}
		print '</table>';

		print load_fiche_titre($langs->trans('PowerPlantAccess'), '', 'lock');
		print '<table class="border centpercent tableforfield">'."\n";
		$def = $object->fields['access_instructions'];
		print '<tr class="field_access_instructions">';
		print '<td class="titlefieldmiddle">'.$langs->trans($def['label']).'</td>';
		print '<td class="valuefield">'.$object->showOutputField($def, 'access_instructions', $object->access_instructions).'</td>';
		print '</tr>';
		print '</table>';

		print '</div>';
		print dol_get_fiche_end();

		print '<div class="tabsAction">'."\n";
		print dolGetButtonAction($langs->trans('Modify'), '', 'default', $_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=edit', '', $permissiontoadd);
		print '</div>'."\n";
	}
}

llxFooter();
$db->close();
