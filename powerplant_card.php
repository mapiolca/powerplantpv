<?php
/* Copyright (C) 2017       Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2024-2025  Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2025		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
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
 *    \file       powerplant_card.php
 *    \ingroup    powerplantpv
 *    \brief      Page to create/edit/view powerplant
 */


// General defined Options
//if (! defined('CSRFCHECK_WITH_TOKEN'))     define('CSRFCHECK_WITH_TOKEN', '1');					// Force use of CSRF protection with tokens even for GET
//if (! defined('MAIN_AUTHENTICATION_MODE')) define('MAIN_AUTHENTICATION_MODE', 'aloginmodule');	// Force authentication handler
//if (! defined('MAIN_LANG_DEFAULT'))        define('MAIN_LANG_DEFAULT', 'auto');					// Force LANG (language) to a particular value
//if (! defined('MAIN_SECURITY_FORCECSP'))   define('MAIN_SECURITY_FORCECSP', 'none');				// Disable all Content Security Policies
//if (! defined('NOBROWSERNOTIF'))     		 define('NOBROWSERNOTIF', '1');					// Disable browser notification
//if (! defined('NOIPCHECK'))                define('NOIPCHECK', '1');						// Do not check IP defined into conf $dolibarr_main_restrict_ip
//if (! defined('NOLOGIN'))                  define('NOLOGIN', '1');						// Do not use login - if this page is public (can be called outside logged session). This includes the NOIPCHECK too.
//if (! defined('NOREQUIREAJAX'))            define('NOREQUIREAJAX', '1');       	  		// Do not load ajax.lib.php library
//if (! defined('NOREQUIREDB'))              define('NOREQUIREDB', '1');					// Do not create database handler $db
//if (! defined('NOREQUIREHTML'))            define('NOREQUIREHTML', '1');					// Do not load html.form.class.php
//if (! defined('NOREQUIREMENU'))            define('NOREQUIREMENU', '1');					// Do not load and show top and left menu
//if (! defined('NOREQUIRESOC'))             define('NOREQUIRESOC', '1');					// Do not load object $mysoc
//if (! defined('NOREQUIRETRAN'))            define('NOREQUIRETRAN', '1');					// Do not load object $langs
//if (! defined('NOREQUIREUSER'))            define('NOREQUIREUSER', '1');					// Do not load object $user
//if (! defined('NOSCANGETFORINJECTION'))    define('NOSCANGETFORINJECTION', '1');			// Do not check injection attack on GET parameters
//if (! defined('NOSCANPOSTFORINJECTION'))   define('NOSCANPOSTFORINJECTION', '1');			// Do not check injection attack on POST parameters
//if (! defined('NOSESSION'))                define('NOSESSION', '1');						// On CLI mode, no need to use web sessions
//if (! defined('NOSTYLECHECK'))             define('NOSTYLECHECK', '1');					// Do not check style html tag into posted data
//if (! defined('NOTOKENRENEWAL'))           define('NOTOKENRENEWAL', '1');					// Do not roll the Anti CSRF token (used if MAIN_SECURITY_CSRF_WITH_TOKEN is on)


// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
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
// Try main.inc.php using relative path
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
 * The main.inc.php has been included so the following variable are now defined:
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 * @var Societe $mysoc
 */
include_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
dol_include_once('/product/class/html.formproduct.class.php');
dol_include_once('/powerplantpv/class/powerplant.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv_powerplant.lib.php');

// Load translation files required by the page
$langs->loadLangs(array("powerplantpv@powerplantpv", "other"));

// Get parameters
$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$lineid   = GETPOSTINT('lineid');
//$socid = GETPOSTINT('socid');

$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : getDolDefaultContextPage(__FILE__); // To manage different context of search
$backtopage = GETPOST('backtopage', 'alpha');					// if not set, a default page will be used
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');	// if not set, $backtopage will be used
$optioncss = GETPOST('optioncss', 'aZ'); // Option for the css output (always '' except when 'print')
$dol_openinpopup = GETPOST('dol_openinpopup', 'aZ09');

// Initialize a technical objects
$object = new PowerPlant($db);
$extrafields = new ExtraFields($db);
$formcompany = new FormCompany($db);
$formproduct = new FormProduct($db);
$diroutputmassaction = $conf->powerplantpv->dir_output.'/temp/massgeneration/'.$user->id;
$hookmanager->initHooks(array($object->element.'card', 'globalcard')); // Note that conf->hooks_modules contains array
$soc = null;

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);


$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Initialize array of search criteria
$search_all = trim(GETPOST("search_all", 'alpha'));
$search = array();
foreach ($object->fields as $key => $val) {
	if (GETPOST('search_'.$key, 'alpha')) {
		$search[$key] = GETPOST('search_'.$key, 'alpha');
	}
}

if (empty($action) && empty($id) && empty($ref)) {
	$action = 'view';
}

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; // Must be 'include', not 'include_once'.

// There is several ways to check permission.
// Set $enablepermissioncheck to 1 to enable a minimum low level of checks
$enablepermissioncheck = getDolGlobalInt('POWERPLANTPV_ENABLE_PERMISSION_CHECK');
if ($enablepermissioncheck) {
	$permissiontoread = $user->hasRight('powerplantpv', 'powerplant', 'read');
	$permissiontoadd = $user->hasRight('powerplantpv', 'powerplant', 'write'); // Used by the include of actions_addupdatedelete.inc.php and actions_lineupdown.inc.php
	$permissiontodelete = $user->hasRight('powerplantpv', 'powerplant', 'delete') || ($permissiontoadd && isset($object->status) && $object->status == $object::STATUS_DRAFT);
	$permissionnote = $user->hasRight('powerplantpv', 'powerplant', 'write'); // Used by the include of actions_setnotes.inc.php
	$permissiondellink = $user->hasRight('powerplantpv', 'powerplant', 'write'); // Used by the include of actions_dellink.inc.php
} else {
	$permissiontoread = 1;
	$permissiontoadd = 1; // Used by the include of actions_addupdatedelete.inc.php and actions_lineupdown.inc.php
	$permissiontodelete = 1;
	$permissionnote = 1;
	$permissiondellink = 1;
}

$upload_dir = $conf->powerplantpv->multidir_output[isset($object->entity) ? $object->entity : 1].'/powerplant';

// Security check (enable at least one, the most restrictive one)
//if ($user->socid > 0) accessforbidden();
//if ($user->socid > 0) $socid = $user->socid;
//$isdraft = (isset($object->status) && ($object->status == $object::STATUS_DRAFT) ? 1 : 0);
//restrictedArea($user, $object->module, $object, $object->table_element, $object->element, 'fk_soc', 'rowid', $isdraft);
if (!isModEnabled($object->module)) {
	accessforbidden("Module ".$object->module." not enabled");
}
if (!$permissiontoread) {
	accessforbidden();
}

$error = 0;


/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	$backurlforlist = dol_buildpath('/powerplantpv/powerplant_list.php', 1);

	if (empty($backtopage) || ($cancel && empty($id))) {
		if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
			if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) {
				$backtopage = $backurlforlist;
			} else {
				$backtopage = dol_buildpath('/powerplantpv/powerplant_card.php', 1).'?id='.((!empty($id) && $id > 0) ? $id : '__ID__');
			}
	}
}

// Composition actions
if ($action == 'addcomposition' && $permissiontoadd) {
	$naturecode = GETPOSTINT('naturecode');
	$fk_product = GETPOSTINT('fk_product');
	$qty = price2num(GETPOST('qty', 'alpha'), 'MT');

	if ($fk_product > 0 && $qty > 0 && in_array($naturecode, array(50, 51, 52, 53, 54, 55))) {
		$sql = "INSERT INTO ".$db->prefix()."powerplantpv_powerplantcomp(fk_powerplant, fk_product, nature_code, qty, entity)";
		$sql .= " VALUES(".((int) $object->id).", ".((int) $fk_product).", ".((int) $naturecode).", ".((float) $qty).", ".((int) $conf->entity).")";
		$db->query($sql);
	}
}
if ($action == 'delcomposition' && $permissiontoadd) {
	$lineid = GETPOSTINT('lineid');
	if ($lineid > 0) {
		$sql = "DELETE FROM ".$db->prefix()."powerplantpv_powerplantcomp WHERE rowid = ".((int) $lineid)." AND fk_powerplant = ".((int) $object->id);
		$db->query($sql);
	}
}

	$triggermodname = $object->TRIGGER_PREFIX.'_MODIFY'; // Name of trigger action code to execute when we modify record. Used in actions_addupdatedelete.inc.php

	// Inline update of a single field (row-level edition)
	if ($action == 'updatefield' && $permissiontoadd) {
		if (!checkToken()) {
			accessforbidden();
		}

		$field = preg_replace('/[^a-zA-Z0-9_]/', '', GETPOST('field', 'nohtml'));

		// Security: allow only known fields from $object->fields (or our synthetic field zip_town)
		if (empty($field) || ($field != 'zip_town' && empty($object->fields[$field]))) {
			setEventMessages($langs->trans("ErrorBadParameter"), null, 'errors');
		} else {
			$res = 0;

			// Special case: one line edits both zip + town
			if ($field == 'zip_town') {
				$zip = GETPOST('zip', 'restricthtml');
				$town = GETPOST('town', 'restricthtml');

				$res1 = $object->setValueFrom('zip', $zip, '', $object->id, 'text', '', $user, $triggermodname);
				$res2 = $object->setValueFrom('town', $town, '', $object->id, 'text', '', $user, $triggermodname);

				$res = ($res1 > 0 && $res2 > 0) ? 1 : -1;
			} else {
				$type = isset($object->fields[$field]['type']) ? $object->fields[$field]['type'] : '';
				$format = 'text';
				$newvalue = '';

				// Date fields are posted as <field>day / <field>month / <field>year (and optionally hour/min)
				if (strpos($type, 'date') !== false) {
					$format = 'date';
					$day = GETPOSTINT($field.'day');
					$month = GETPOSTINT($field.'month');
					$year = GETPOSTINT($field.'year');
					$hour = GETPOSTINT($field.'hour');
					$min = GETPOSTINT($field.'min');

					if ($day && $month && $year) {
						$newvalue = dol_mktime((strpos($type, 'datetime') !== false ? $hour : 0), (strpos($type, 'datetime') !== false ? $min : 0), 0, $month, $day, $year);
					} else {
						$newvalue = '';
					}
				} elseif (strpos($type, 'sellist:') === 0 || strpos($type, 'link:') === 0 || preg_match('/(^|[: ])(int|integer)/', $type)) {
					$format = 'int';
					$newvalue = GETPOSTINT($field);
				} elseif (preg_match('/double|real|price|amount/', $type)) {
					$format = 'text';
					$newvalue = price2num(GETPOST($field, 'alpha'), 'MT');
				} else {
					$format = 'text';
					$newvalue = GETPOST($field, 'restricthtml');
				}

				$res = $object->setValueFrom($field, $newvalue, '', $object->id, $format, '', $user, $triggermodname);
			}

			if ($res > 0) {
				header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
				exit;
			} else {
				setEventMessages($object->error, $object->errors, 'errors');
			}
		}
	}

	// Actions cancel, add, update, update_extras, confirm_validate, confirm_delete, confirm_deleteline, confirm_clone, confirm_close, confirm_setdraft, confirm_reopen
	include DOL_DOCUMENT_ROOT.'/core/actions_addupdatedelete.inc.php';

	// Actions when linking object each other
	include DOL_DOCUMENT_ROOT.'/core/actions_dellink.inc.php';

	// Actions when printing a doc from card
	include DOL_DOCUMENT_ROOT.'/core/actions_printing.inc.php';

	// Action to move up and down lines of object
	//include DOL_DOCUMENT_ROOT.'/core/actions_lineupdown.inc.php';

	// Action to build doc
	include DOL_DOCUMENT_ROOT.'/core/actions_builddoc.inc.php';

	// Other special actions
	/*
	if ($action == 'set_thirdparty' && $permissiontoadd) {
		$object->setValueFrom('fk_soc', GETPOSTINT('fk_soc'), '', null, 'date', '', $user, $triggermodname);
	}
	if ($action == 'classin' && $permissiontoadd) {
		$object->setProject(GETPOSTINT('projectid'));
	}
	*/

	// Actions to send emails
	$triggersendname = 'POWERPLANTPV_MYOBJECT_SENTBYMAIL';
	$autocopy = 'MAIN_MAIL_AUTOCOPY_MYOBJECT_TO';
	$trackid = 'powerplant'.$object->id;
	include DOL_DOCUMENT_ROOT.'/core/actions_sendmails.inc.php';
}



/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

$title = $langs->trans("PowerPlant")." - ".$langs->trans('Card');
//$title = $object->ref." - ".$langs->trans('Card');
if ($action == 'create') {
	$title = $langs->trans("NewObject", $langs->transnoentitiesnoconv("PowerPlant"));
}
$help_url = '';

llxHeader('', $title, $help_url, '', 0, 0, '', '', '', 'mod-powerplantpv page-card');

// Example : Adding jquery code
// print '<script type="text/javascript">
// jQuery(document).ready(function() {
// 	function init_myfunc()
// 	{
// 		jQuery("#myid").removeAttr(\'disabled\');
// 		jQuery("#myid").attr(\'disabled\',\'disabled\');
// 	}
// 	init_myfunc();
// 	jQuery("#mybutton").click(function() {
// 		init_myfunc();
// 	});
// });
// </script>';


// Part to create
if ($action == 'create') {
	if (empty($permissiontoadd)) {
		accessforbidden('NotEnoughPermissions', 0, 1);
	}

	print load_fiche_titre($title, '', $object->picto);

	print '<form method="POST" action="'.dolBuildUrl($_SERVER["PHP_SELF"]).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	if ($backtopage) {
		print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
	}
	if ($backtopageforcancel) {
		print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';
	}
	if ($dol_openinpopup) {
		print '<input type="hidden" name="dol_openinpopup" value="'.$dol_openinpopup.'">';
	}

	print dol_get_fiche_head(array(), '');


	print '<table class="border centpercent tableforfieldcreate">'."\n";

	// Common attributes
	// EN: Render common fields with Dolibarr forms
	if (empty($object->ref)) {
		$object->ref = $object->getProvisionalRefPreview();
	}
	$object->fields['ref']['disabled'] = 1;
	$object->fields['ref']['noteditable'] = 1;
	$object->fields['ref']['default'] = $object->ref;
	$object->fields['fk_country']['type'] = 'sellist:c_country:label:rowid::active=1';
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_add.tpl.php';

	// Other attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_add.tpl.php';

	print '</table>'."\n";

	print dol_get_fiche_end();

	print $form->buttonsSaveCancel("Create");

	print '</form>';

	//dol_set_focus('input[name="ref"]');
}

// Part to edit record
if (($id || $ref) && $action == 'edit') {
	print load_fiche_titre($langs->trans("PowerPlant"), '', $object->picto);

	print '<form method="POST" action="'.dolBuildUrl($_SERVER["PHP_SELF"]).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="'.$object->id.'">';
	if ($backtopage) {
		print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
	}
	if ($backtopageforcancel) {
		print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';
	}

	print dol_get_fiche_head();

	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';

	// Common attributes
	// EN: Render common fields with Dolibarr forms
	$object->fields['ref']['type'] = 'string';
	$object->fields['ref']['noteditable'] = 1;
	$object->fields['ref']['visible'] = 5;
	unset($object->fields['status']);
	$object->fields['fk_country']['type'] = 'sellist:c_country:label:rowid::active=1';
	$object->fields['installed_power']['type'] = 'double(24,8):kWc';
	$object->fields['connection_contract_power']['type'] = 'double(24,8):kWc';

	$allfields = $object->fields;

	$findKey = function($regexes) use ($allfields, $langs) {
		foreach ($allfields as $k => $def) {
			$lab = !empty($def['label']) ? $def['label'] : '';
			$labt = $lab ? $langs->transnoentitiesnoconv($lab) : '';
			$hay = $k.' '.$lab.' '.$labt;
			foreach ($regexes as $re) {
				if (@preg_match($re, $hay) && preg_match($re, $hay)) {
					return $k;
				}
			}
		}
		return '';
	};


// Field keys (explicit)
$k_description = 'description';
$k_address = 'address';
$k_zip = 'zip';
$k_town = 'town';
$k_country = 'fk_country';

// Réseau (2 colonnes)
$k_enedis_commissioning_date = 'enedis_commissioning_date'; // Date de mise en service ENEDIS
$k_connection_request_number = 'connection_request_number'; // N° de demande de raccordement
$k_prm_pdl = isset($allfields['prm_pdl']) ? 'prm_pdl' : (isset($allfields['prm_pdl_number']) ? 'prm_pdl_number' : 'prm_pdl');
$k_connection_type = 'connection_type';
$k_commissioning_date = 'commissioning_date'; // Date de mise en service
$k_connection_request_no = 'connection_request_no';

// Contrats
$k_t0_date = 't0_obtention_date';
$k_connection_contract_power = 'connection_contract_power';
$k_installed_power = 'installed_power';
$k_purchase_contract_no = 'buyback_contract_number';
$k_purchase_tariff = 'buyback_tariff';

	// Helpers for structured rendering (no commonfields_* tpl includes)
		$printRowEdit = function($key, $labelOverride = '', $morecss = '') use ($object, $langs) {
			if (empty($key) || empty($object->fields[$key])) return;

			$def = $object->fields[$key];
			$label = $labelOverride ?: $langs->trans(!empty($def['label']) ? $def['label'] : $key);
			$value = (isset($object->$key) ? $object->$key : '');

			print '<tr class="field_'.$key.'">';
			print '<td class="titlefieldcreate">'.$label.'</td>';
			print '<td class="valuefieldcreate">'.$object->showInputField($def, $key, $value, '', '', '', $morecss).'</td>';
			print '</tr>';
		};

		$printRowZipTownEdit = function($zipKey, $townKey) use ($object, $langs) {
			if (empty($zipKey) && empty($townKey)) return;

			$zipval = (!empty($zipKey) && isset($object->$zipKey) ? $object->$zipKey : '');
			$townval = (!empty($townKey) && isset($object->$townKey) ? $object->$townKey : '');

			print '<tr class="field_zip_town">';
			print '<td class="titlefieldcreate">'.$langs->trans("Zip").' | '.$langs->trans("Town").'</td>';
			print '<td class="valuefieldcreate">';
			if (!empty($zipKey)) {
				print '<input class="flat maxwidth100" type="text" name="'.$zipKey.'" value="'.dol_escape_htmltag($zipval).'" />';
			}
			if (!empty($townKey)) {
				print ' <input class="flat maxwidth200" type="text" name="'.$townKey.'" value="'.dol_escape_htmltag($townval).'" />';
			}
			print '</td>';
			print '</tr>';
		};

		// Left column
		print '<div class="fichehalfleft">';

		print load_fiche_titre($langs->trans("Localisation"), '', '');
		print '<table class="border centpercent tableforfieldedit">'."\n";
		$printRowEdit($k_description);
		$printRowEdit($k_address);
		$printRowZipTownEdit($k_zip, $k_town);
		$printRowEdit($k_country);
		print '</table>';

		print load_fiche_titre($langs->trans("Réseau"), '', '');
		print '<table class="border centpercent tableforfieldedit">'."\n";
		$printRowEdit($k_prm_pdl);
		$printRowEdit($k_connection_type);
		$printRowEdit($k_commissioning_date);
				print '</table>';

		print '</div>'; // fichehalfleft

		// Right column
		print '<div class="fichehalfright">';

		print load_fiche_titre($langs->trans("Contrat de rachat"), '', '');
		print '<table class="border centpercent tableforfieldedit">'."\n";
		$printRowEdit($k_t0_date);
		$printRowEdit($k_installed_power);
		$printRowEdit($k_purchase_contract_no);
		$printRowEdit($k_purchase_tariff);
		print '</table>';

		print load_fiche_titre($langs->trans("Réseau"), '', '');
		print '<table class="border centpercent tableforfieldedit">'."\n";
		$printRowEdit($k_enedis_commissioning_date);
		$printRowEdit($k_connection_request_number);
		$printRowEdit($k_connection_request_no);
		$printRowEdit($k_connection_contract_power);
		print '</table>';

		print '</div>'; // fichehalfright

		// Remaining fields + extrafields (full width, no duplicates)
		print '<div class="clearboth"></div>';

		$exclude = array();
		foreach (array(
			$k_description, $k_address, $k_zip, $k_town, $k_country,
			$k_prm_pdl, $k_connection_type, $k_commissioning_date,
			$k_enedis_commissioning_date, $k_connection_request_number, $k_connection_request_no,
			$k_t0_date, $k_connection_contract_power, $k_installed_power,
			$k_purchase_contract_no, $k_purchase_tariff,
			'ref', 'label', 'fk_soc', 'socid', 'fk_project', 'status'
		) as $k) {



			if (!empty($k)) $exclude[$k] = 1;
		}

		$hasextra = (!empty($extrafields->attributes[$object->element]['label']));

		$hasremaining = 0;
		foreach ($allfields as $key => $def) {
			if (!empty($exclude[$key])) continue;
			$vis = isset($def['visible']) ? (int) $def['visible'] : 0;
			if ($vis <= 0 || $vis == 2) continue;
			$hasremaining = 1;
			break;
		}

		if ($hasremaining || $hasextra) {
			print load_fiche_titre($langs->trans("Other"), '', '');

			if ($hasremaining) {
				print '<table class="border centpercent tableforfield">'."
";

				foreach ($allfields as $key => $def) {
					if (!empty($exclude[$key])) continue;
					$vis = isset($def['visible']) ? (int) $def['visible'] : 0;
					if ($vis <= 0 || $vis == 2) continue;

					$printRowView($key);
				}

				print '</table>';
			}

			if ($hasextra) {
				print '<table class="border centpercent tableforfield">'."
";
				// Other attributes
				include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';
				print '</table>';
			}
		}
	// Restore
	$object->fields = $allfields;

	print '</div>';

	print dol_get_fiche_end();

	print $form->buttonsSaveCancel();

	print '</form>';
}

// Part to show record
if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create'))) {
	$head = powerplantPrepareHead($object);

	print dol_get_fiche_head($head, 'card', $langs->trans("PowerPlant"), -1, $object->picto, 0, '', '', 0, '', 1);

	$formconfirm = '';

	// Confirmation to delete (using preloaded confirm popup)
	if ($action == 'delete' || ($conf->use_javascript_ajax && empty($conf->dol_use_jmobile))) {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('DeletePowerPlant'), $langs->trans('ConfirmDeleteObject'), 'confirm_delete', '', 0, 'action-delete');
	}
	// Confirmation to delete line
	if ($action == 'deleteline') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id.'&lineid='.$lineid, $langs->trans('DeleteLine'), $langs->trans('ConfirmDeleteLine'), 'confirm_deleteline', '', 0, 1);
	}

	// Clone confirmation
	if ($action == 'clone') {
		// Create an array for form
		$formquestion = array();
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('ToClone'), $langs->trans('ConfirmCloneAsk', $object->ref), 'confirm_clone', $formquestion, 'yes', 1);
	}

	// Confirmation of action xxxx (You can use it for xxx = 'close', xxx = 'reopen', ...)
	// if ($action == 'xxx') {
	// 	$text = $langs->trans('ConfirmActionXxx', $object->ref);
	// 	if (isModEnabled('notification')) {
	// 		require_once DOL_DOCUMENT_ROOT . '/core/class/notify.class.php';
	// 		$notify = new Notify($db);
	// 		$text .= '<br>';
	// 		$text .= $notify->confirmMessage('MYOBJECT_CLOSE', $object->socid, $object);
	// 	}

	// 	$formquestion = array();

	// 	$forcecombo=0;
	// 	if ($conf->browser->name == 'ie') $forcecombo = 1;	// There is a bug in IE10 that make combo inside popup crazy
	// 	$formquestion = array(
	// 		// 'text' => $langs->trans("ConfirmClone"),
	// 		// array('type' => 'checkbox', 'name' => 'clone_content', 'label' => $langs->trans("CloneMainAttributes"), 'value' => 1),
	// 		// array('type' => 'checkbox', 'name' => 'update_prices', 'label' => $langs->trans("PuttingPricesUpToDate"), 'value' => 1),
	// 		// array('type' => 'other',    'name' => 'idwarehouse',   'label' => $langs->trans("SelectWarehouseForStockDecrease"), 'value' => $formproduct->selectWarehouses(GETPOST('idwarehouse')?GETPOST('idwarehouse'):'ifone', 'idwarehouse', '', 1, 0, 0, '', 0, $forcecombo))
	// 	);
	// 	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('XXX'), $text, 'confirm_xxx', $formquestion, 0, 1, 220);
	// }

	// Call Hook formConfirm
	$parameters = array('formConfirm' => $formconfirm, 'lineid' => $lineid);
	$reshook = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
	if (empty($reshook)) {
		$formconfirm .= $hookmanager->resPrint;
	} elseif ($reshook > 0) {
		$formconfirm = $hookmanager->resPrint;
	}

	// Print form confirm
	print $formconfirm;


	// Object card
	// ------------------------------------------------------------
	$linkback = '<a href="'.dol_buildpath('/powerplantpv/powerplant_list.php', 1).'?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';

	$morehtmlref = '<div class="refidno">';
	/*
		// Ref customer
		$morehtmlref .= $form->editfieldkey("RefCustomer", 'ref_client', $object->ref_client, $object, $usercancreate, 'string', '', 0, 1);
		$morehtmlref .= $form->editfieldval("RefCustomer", 'ref_client', $object->ref_client, $object, $usercancreate, 'string', '', null, null, '', 1);
	*/
	// Label (under reference)
	if (!empty($object->label)) {
		$morehtmlref .= '<br><span class="opacitymedium">'.$langs->trans("Label").'</span>: '.dol_escape_htmltag($object->label);
	}

	// Thirdparty (under reference)
	if (!empty($object->socid)) {
		$object->fetch_thirdparty();
		if (!empty($object->thirdparty) && !empty($object->thirdparty->id)) {
			$morehtmlref .= '<br><span class="opacitymedium">'.$langs->trans("ThirdParty").'</span>: '.$object->thirdparty->getNomUrl(1, 'customer');
		}
	}

	// Project (under reference)
	if (isModEnabled('project') && !empty($object->fk_project)) {
		require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
		$langs->load("projects");
		$proj = new Project($db);
		if ($proj->fetch($object->fk_project) > 0) {
			$morehtmlref .= '<br><span class="opacitymedium">'.$langs->trans("Project").'</span>: '.$proj->getNomUrl(1);
			if (!empty($proj->title)) {
				$morehtmlref .= '<span class="opacitymedium"> - '.dol_escape_htmltag($proj->title).'</span>';
			}
		}
	}

	$morehtmlref .= '</div>';


	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);


	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';

	// Prepare field types
	$object->fields['fk_country']['type'] = 'sellist:c_country:label:rowid::active=1';
	$object->fields['installed_power']['type'] = 'double(24,8):kWc';
	$object->fields['connection_contract_power']['type'] = 'double(24,8):kWc';

	$allfields = $object->fields;

	$findKey = function($regexes) use ($allfields, $langs) {
		foreach ($allfields as $k => $def) {
			$lab = !empty($def['label']) ? $def['label'] : '';
			$labt = $lab ? $langs->transnoentitiesnoconv($lab) : '';
			$hay = $k.' '.$lab.' '.$labt;
			foreach ($regexes as $re) {
				if (@preg_match($re, $hay) && preg_match($re, $hay)) {
					return $k;
				}
			}
		}
		return '';
	};

	// Field keys (robust: try standard keys, fallback on label matching)

// Field keys (explicit)
$k_description = 'description';
$k_address = 'address';
$k_zip = 'zip';
$k_town = 'town';
$k_country = 'fk_country';

// Réseau (2 colonnes)
$k_enedis_commissioning_date = 'enedis_commissioning_date'; // Date de mise en service ENEDIS
$k_connection_request_number = 'connection_request_number'; // N° de demande de raccordement
$k_prm_pdl = isset($allfields['prm_pdl']) ? 'prm_pdl' : (isset($allfields['prm_pdl_number']) ? 'prm_pdl_number' : 'prm_pdl');
$k_connection_type = 'connection_type';
$k_commissioning_date = 'commissioning_date'; // Date de mise en service
$k_connection_request_no = 'connection_request_no';

// Contrats
$k_t0_date = 't0_obtention_date';
$k_connection_contract_power = 'connection_contract_power';
$k_installed_power = 'installed_power';
$k_purchase_contract_no = 'buyback_contract_number';
$k_purchase_tariff = 'buyback_tariff';

	// Helpers for structured rendering (no commonfields_* tpl includes)
		$fieldtoedit = ($action == 'editfield' ? GETPOST('field', 'nohtml') : '');
		$fieldtoedit = preg_replace('/[^a-zA-Z0-9_]/', '', $fieldtoedit);
		if (!empty($fieldtoedit) && $fieldtoedit !== 'zip_town' && empty($object->fields[$fieldtoedit])) $fieldtoedit = '';

		$printRowView = function($key, $labelOverride = '', $valueOverride = null) use ($object, $langs, $permissiontoadd, $fieldtoedit) {
			if (empty($key) || empty($object->fields[$key])) return;

			$def = $object->fields[$key];
			$label = $labelOverride ?: $langs->trans(!empty($def['label']) ? $def['label'] : $key);
			$value = ($valueOverride !== null ? $valueOverride : (isset($object->$key) ? $object->$key : ''));

			$canedit = (!empty($permissiontoadd) && empty($def['noteditable']) && empty($def['disabled']));
			$isedit = ($canedit && $fieldtoedit === $key);

			$urlcard = $_SERVER["PHP_SELF"].'?id='.$object->id;
			$urledit = $urlcard.'&action=editfield&field='.$key;

			print '<tr class="field_'.$key.'" id="field_'.$key.'">';
			print '<td class="titlefieldmiddle">'.$label.'</td>';

			if ($isedit) {
				$formid = 'form_'.$key;
				print '<td class="valuefield">';
				print '<form id="'.$formid.'" method="POST" action="'.$urlcard.'">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="updatefield">';
				print '<input type="hidden" name="field" value="'.$key.'">';
				print $object->showInputField($def, $key, $value, '', '', '', '');
				print '</form>';
				print '</td>';
				print '<td class="right nowraponall">';
				print '<button type="submit" form="'.$formid.'" class="reposition">'.img_picto($langs->trans("Save"), 'tick').'</button>';
				print ' <a class="reposition" href="'.$urlcard.'">'.img_picto($langs->trans("Cancel"), 'cancel').'</a>';
				print '</td>';
			} else {
				print '<td class="valuefield">'.$object->showOutputField($def, $key, $value).'</td>';
				print '<td class="right nowraponall">';
				if ($canedit) {
					print '<a class="reposition" href="'.$urledit.'">'.img_edit().'</a>';
				} else {
					print '&nbsp;';
				}
				print '</td>';
			}

			print '</tr>';
		};

		$printRowZipTownView = function($zipKey, $townKey) use ($object, $langs, $permissiontoadd, $fieldtoedit) {
			if (empty($zipKey) && empty($townKey)) return;

			$zipval = (!empty($zipKey) && isset($object->$zipKey) ? $object->$zipKey : '');
			$townval = (!empty($townKey) && isset($object->$townKey) ? $object->$townKey : '');

			$canedit = (!empty($permissiontoadd));
			$isedit = ($canedit && $fieldtoedit === 'zip_town');

			$urlcard = $_SERVER["PHP_SELF"].'?id='.$object->id;
			$urledit = $urlcard.'&action=editfield&field=zip_town';

			print '<tr class="field_zip_town" id="field_zip_town">';
			print '<td class="titlefieldmiddle">'.$langs->trans("Zip").' | '.$langs->trans("Town").'</td>';

			if ($isedit) {
				$formid = 'form_zip_town';
				print '<td class="valuefield">';
				print '<form id="'.$formid.'" method="POST" action="'.$urlcard.'">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="updatefield">';
				print '<input type="hidden" name="field" value="zip_town">';
				if (!empty($zipKey)) {
					print '<input class="flat maxwidth100" type="text" name="'.$zipKey.'" value="'.dol_escape_htmltag($zipval).'" />';
				}
				if (!empty($townKey)) {
					print ' <input class="flat maxwidth200" type="text" name="'.$townKey.'" value="'.dol_escape_htmltag($townval).'" />';
				}
				print '</form>';
				print '</td>';
				print '<td class="right nowraponall">';
				print '<button type="submit" form="'.$formid.'" class="reposition">'.img_picto($langs->trans("Save"), 'tick').'</button>';
				print ' <a class="reposition" href="'.$urlcard.'">'.img_picto($langs->trans("Cancel"), 'cancel').'</a>';
				print '</td>';
			} else {
				print '<td class="valuefield">'.dol_escape_htmltag($zipval).' '.dol_escape_htmltag($townval).'</td>';
				print '<td class="right nowraponall">';
				if ($canedit) {
					print '<a class="reposition" href="'.$urledit.'">'.img_edit().'</a>';
				} else {
					print '&nbsp;';
				}
				print '</td>';
			}

			print '</tr>';
		};

		// Left column
		print '<div class="fichehalfleft">';

		// Localisation
		print load_fiche_titre($langs->trans("Localisation"), '', '');
		print '<table class="border centpercent tableforfield">'."\n";
		$printRowView($k_description);
		$printRowView($k_address);
		$printRowZipTownView($k_zip, $k_town);
		$printRowView($k_country);
		print '</table>';

		// Réseau
		print load_fiche_titre($langs->trans("Réseau"), '', '');
		print '<table class="border centpercent tableforfield">'."\n";
		$printRowView($k_prm_pdl);
		$printRowView($k_connection_type);
		$printRowView($k_commissioning_date);
				print '</table>';

		print '</div>'; // fichehalfleft

		// Right column
		print '<div class="fichehalfright">';

		print load_fiche_titre($langs->trans("Contrat de rachat"), '', '');
		print '<table class="border centpercent tableforfield">'."\n";
		$printRowView($k_t0_date);
		$printRowView($k_installed_power);
		$printRowView($k_purchase_contract_no);
		$printRowView($k_purchase_tariff);
		print '</table>';

		print load_fiche_titre($langs->trans("Réseau"), '', '');
		print '<table class="border centpercent tableforfield">'."\n";
		$printRowView($k_enedis_commissioning_date);
		$printRowView($k_connection_request_number);
		$printRowView($k_connection_request_no);
		$printRowView($k_connection_contract_power);
		print '</table>';

		print '</div>'; // fichehalfright

		// Remaining fields + extrafields (full width, no duplicates)
		print '<div class="clearboth"></div>';

		$exclude = array();
		foreach (array(
			$k_description, $k_address, $k_zip, $k_town, $k_country,
			$k_prm_pdl, $k_connection_type, $k_commissioning_date,
			$k_enedis_commissioning_date, $k_connection_request_number, $k_connection_request_no,
			$k_t0_date, $k_connection_contract_power, $k_installed_power,
			$k_purchase_contract_no, $k_purchase_tariff,
			'ref', 'label', 'fk_soc', 'socid', 'fk_project', 'status'
		) as $k) {

			if (!empty($k)) $exclude[$k] = 1;
		}

		$hasextra = (!empty($extrafields->attributes[$object->element]['label']));

		$hasremaining = 0;
		foreach ($allfields as $key => $def) {
			if (!empty($exclude[$key])) continue;
			$vis = isset($def['visible']) ? (int) $def['visible'] : 0;
			if ($vis <= 0 || $vis == 2) continue;
			$hasremaining = 1;
			break;
		}

		if ($hasremaining || $hasextra) {
			print load_fiche_titre($langs->trans("Other"), '', '');
			print '<table class="border centpercent tableforfield">'."\n";

			if ($hasremaining) {
				foreach ($allfields as $key => $def) {
					if (!empty($exclude[$key])) continue;
					$vis = isset($def['visible']) ? (int) $def['visible'] : 0;
					if ($vis <= 0 || $vis == 2) continue;

					$label = $langs->trans(!empty($def['label']) ? $def['label'] : $key);
					$value = (isset($object->$key) ? $object->$key : '');

					print '<tr class="field_'.$key.'">';
					print '<td class="titlefieldmiddle">'.$label.'</td>';
					print '<td class="valuefield">'.$object->showOutputField($def, $key, $value).'</td>';
					print '</tr>';
				}
			}

			// Other attributes
			include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';

			print '</table>';
		}
	// Restore
	$object->fields = $allfields;

	print '</div>';

	// EN: Composition sections
	$sections = array(
		50 => array('label' => $langs->trans('PVModules')),
		51 => array('label' => $langs->trans('PVInverters')),
		52 => array('label' => $langs->trans('PVIntegration')),
		53 => array('label' => $langs->trans('PVMonitoring')),
		54 => array('label' => $langs->trans('PVACBox')),
		55 => array('label' => $langs->trans('PVDCBox')),
	);

	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';
	print load_fiche_titre($langs->trans('PowerPlantComposition'), '', '');

	foreach ($sections as $code => $info) {
		$showaddform = ($object->status == $object::STATUS_DRAFT && $permissiontoadd);

		print '<div class="ficheaddleft">';
		print '<h3 class="marginbottomonly">'.$info['label'].'</h3>';
		if ($showaddform) {
			print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="addcomposition">';
			print '<input type="hidden" name="naturecode" value="'.$code.'">';
		}

		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre"><td>'.$langs->trans("Product").'</td><td class="right">'.$langs->trans("PVQuantity").'</td><td class="center"></td></tr>';

			if ($showaddform) {
				print '<tr class="oddeven">';
				print '<td>';
				print $form->select_produits(0, 'fk_product', '', 0, 0, -1, 2, '', 0, array(), '', 1, 1, '', '1', 0, 'finished', " AND p.fk_product_nature = ".((int) $code));
				print '</td>';
				print '<td class="right"><input type="text" class="flat width50" name="qty" value="1"></td>';
				print '<td class="center"><input type="submit" class="button small" value="'.$langs->trans("Add").'"></td>';
				print '</tr>';
			}

		$sqlcomp = "SELECT c.rowid, c.qty, c.nature_code, p.label as product_label, p.ref as product_ref";
		$sqlcomp .= " FROM ".$db->prefix()."powerplantpv_powerplantcomp as c";
		$sqlcomp .= " JOIN ".$db->prefix()."product as p ON p.rowid = c.fk_product";
		$sqlcomp .= " WHERE c.fk_powerplant = ".((int) $object->id)." AND c.nature_code = ".((int) $code);
		$sqlcomp .= " AND c.entity = ".((int) $conf->entity);
		$rescomp = $db->query($sqlcomp);
		if ($rescomp) {
			while ($line = $db->fetch_object($rescomp)) {
				print '<tr class="oddeven">';
				print '<td>'.dol_escape_htmltag($line->product_ref).' - '.dol_escape_htmltag($line->product_label).'</td>';
				print '<td class="right">'.price($line->qty).'</td>';
				print '<td class="center">'.($showaddform ? '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delcomposition&lineid='.$line->rowid.'&token='.newToken().'">'.img_delete().'</a>' : '').'</td>';
				print '</tr>';
			}
		}

		if ($showaddform) {
			print '</table>';
			print '</form>';
		} else {
			print '</table>';
		}

		print '</div>';
	}

	print '</div>';

	print '<div class="clearboth"></div>';

	print dol_get_fiche_end();


	/*
	 * Lines
	 */

	if (!empty($object->table_element_line)) {
		// Show object lines
		$result = $object->getLinesArray();

		print '	<form name="addproduct" id="addproduct" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.(($action != 'editline') ? '' : '#line_'.GETPOSTINT('lineid')).'" method="POST">
		<input type="hidden" name="token" value="' . newToken().'">
		<input type="hidden" name="action" value="' . (($action != 'editline') ? 'addline' : 'updateline').'">
		<input type="hidden" name="mode" value="">
		<input type="hidden" name="page_y" value="">
		<input type="hidden" name="id" value="' . $object->id.'">
		';

		if (!empty($conf->use_javascript_ajax) && $object->status == 0) {
			include DOL_DOCUMENT_ROOT.'/core/tpl/ajaxrow.tpl.php';
		}

		print '<div class="div-table-responsive-no-min">';
		if (!empty($object->lines) || ($object->status == $object::STATUS_DRAFT && $permissiontoadd && $action != 'selectlines' && $action != 'editline')) {
			print '<table id="tablelines" class="noborder noshadow" width="100%">';
		}

		if (!empty($object->lines)) {
			$object->printObjectLines($action, $mysoc, null, GETPOSTINT('lineid'), 1);
		}

		// Form to add new line
		if ($object->status == 0 && $permissiontoadd && $action != 'selectlines') {
			if ($action != 'editline') {
				// Add products/services form

				$parameters = array();
				$reshook = $hookmanager->executeHooks('formAddObjectLine', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
				if ($reshook < 0) {
					setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
				}
				if (empty($reshook)) {
					$object->formAddObjectLine(1, $mysoc, $soc);
				}
			}
		}

		if (!empty($object->lines) || ($object->status == $object::STATUS_DRAFT && $permissiontoadd && $action != 'selectlines' && $action != 'editline')) {
			print '</table>';
		}
		print '</div>';

		print "</form>\n";
	}


	// Buttons for actions

	if ($action != 'presend' && $action != 'editline') {
		print '<div class="tabsAction">'."\n";
		$parameters = array();
		$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
		if ($reshook < 0) {
			setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
		}

		if (empty($reshook)) {
			// Send
			if (empty($user->socid)) {
				print dolGetButtonAction('', $langs->trans('SendMail'), 'email', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=presend&token='.newToken().'&mode=init#formmailbeforetitle');
			}

			// Back to draft
			if ($object->status == $object::STATUS_VALIDATED) {
				print dolGetButtonAction('', $langs->trans('SetToDraft'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=confirm_setdraft&confirm=yes&token='.newToken(), '', $permissiontoadd);
			}

			// Modify
						// Validate
			if ($object->status == $object::STATUS_DRAFT) {
				if (empty($object->table_element_line) || (is_array($object->lines) && count($object->lines) > 0)) {
					print dolGetButtonAction('', $langs->trans('Validate'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_validate&confirm=yes&token='.newToken(), '', $permissiontoadd);
				} else {
					$langs->load("errors");
					print dolGetButtonAction($langs->trans("ErrorAddAtLeastOneLineFirst"), $langs->trans("Validate"), 'default', '#', '', 0);
				}
			}

			// Clone
			if ($permissiontoadd) {
				print dolGetButtonAction('', $langs->trans('ToClone'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.(!empty($object->socid) ? '&socid='.$object->socid : '').'&action=clone&token='.newToken(), '', $permissiontoadd);
			}

			/*
			// Disable / Enable
			if ($permissiontoadd) {
				if ($object->status == $object::STATUS_ENABLED) {
					print dolGetButtonAction('', $langs->trans('Disable'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=disable&token='.newToken(), '', $permissiontoadd);
				} else {
					print dolGetButtonAction('', $langs->trans('Enable'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=enable&token='.newToken(), '', $permissiontoadd);
				}
			}
			if ($permissiontoadd) {
				if ($object->status == $object::STATUS_VALIDATED) {
					print dolGetButtonAction('', $langs->trans('Cancel'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=close&token='.newToken(), '', $permissiontoadd);
				} else {
					print dolGetButtonAction('', $langs->trans('Re-Open'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=reopen&token='.newToken(), '', $permissiontoadd);
				}
			}
			*/

			// Delete (with preloaded confirm popup)
			$deleteUrl = $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete&token='.newToken();
			$buttonId = 'action-delete-no-ajax';
			if ($conf->use_javascript_ajax && empty($conf->dol_use_jmobile)) {	// We can use preloaded confirm if not jmobile
				$deleteUrl = '';
				$buttonId = 'action-delete';
			}
			$params = array();
			print dolGetButtonAction('', $langs->trans("Delete"), 'delete', $deleteUrl, $buttonId, $permissiontodelete, $params);
		}
		print '</div>'."\n";
	}


	// Select mail models is same action as presend
	if (GETPOST('modelselected')) {
		$action = 'presend';
	}

	if ($action != 'presend') {
		print '<div class="fichecenter"><div class="fichehalfleft">';
		print '<a name="builddoc"></a>'; // ancre

		$includedocgeneration = 1;

		// Documents
		if ($includedocgeneration) {
			$objref = dol_sanitizeFileName($object->ref);
			$relativepath = $objref.'/'.$objref.'.pdf';
			$filedir = $conf->powerplantpv->dir_output.'/'.$object->element.'/'.$objref;
			$urlsource = $_SERVER["PHP_SELF"]."?id=".$object->id;
			$genallowed = $permissiontoread; // If you can read, you can build the PDF to read content
			$delallowed = $permissiontoadd; // If you can create/edit, you can remove a file on card
			print $formfile->showdocuments('powerplantpv:PowerPlant', $object->element.'/'.$objref, $filedir, $urlsource, $genallowed, $delallowed, $object->model_pdf, 1, 0, 0, 28, 0, '', '', '', $langs->defaultlang);
		}

		// Show links to link elements
		$tmparray = $form->showLinkToObjectBlock($object, array(), array('powerplant'), 1);
		if (is_array($tmparray)) {
			$linktoelem = $tmparray['linktoelem'];
			$htmltoenteralink = $tmparray['htmltoenteralink'];
			print $htmltoenteralink;
			$somethingshown = $form->showLinkedObjectBlock($object, $linktoelem);
		} else {
			// backward compatibility
			$somethingshown = $form->showLinkedObjectBlock($object, $tmparray);
		}

		print '</div><div class="fichehalfright">';

		$MAXEVENT = 10;

		$morehtmlcenter = dolGetButtonTitle($langs->trans('SeeAll'), '', 'fa fa-bars imgforviewmode', dol_buildpath('/powerplantpv/powerplant_agenda.php', 1).'?id='.$object->id);

		$includeeventlist = 0;

		// List of actions on element
		if ($includeeventlist) {
			include_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
			$formactions = new FormActions($db);
			$somethingshown = $formactions->showactions($object, $object->element.'@'.$object->module, (is_object($object->thirdparty) ? $object->thirdparty->id : 0), 1, '', $MAXEVENT, '', $morehtmlcenter);
		}

		print '</div></div>';
	}

	//Select mail models is same action as presend
	if (GETPOST('modelselected')) {
		$action = 'presend';
	}

	// Presend form
	$modelmail = 'powerplant';
	$defaulttopic = 'InformationMessage';
	$diroutput = $conf->powerplantpv->dir_output;
	$trackid = 'powerplant'.$object->id;

	include DOL_DOCUMENT_ROOT.'/core/tpl/card_presend.tpl.php';
}

// End of page
llxFooter();
$db->close();
