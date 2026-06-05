<?php
/* Copyright (C) 2017       Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2024-2025  Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2025		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 * Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
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
dol_include_once('/product/class/html.formproduct.class.php');
dol_include_once('/powerplantpv/class/powerplant.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_powerplant.lib.php');

// Load translation files required by the page
$langs->loadLangs(array("powerplantpv@powerplantpv", "products", "other", "agenda"));

// Get parameters
$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$lineid   = GETPOSTINT('lineid');
$socid = GETPOSTINT('socid');
$origin = GETPOST('origin', 'alphanohtml');
$originid = GETPOSTINT('originid') ? GETPOSTINT('originid') : GETPOSTINT('origin_id');
$create_material_from_origin = GETPOSTINT('create_material_from_origin');
$fk_soc = GETPOSTINT('fk_soc');
$origin = powerplantpvNormalizeOriginType($origin);

$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : getDolDefaultContextPage(__FILE__); // To manage different context of search
$backtopage = GETPOST('backtopage', 'restricthtml');					// if not set, a default page will be used
$backtopageforcancel = GETPOST('backtopageforcancel', 'restricthtml');	// if not set, $backtopage will be used
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
if (!empty($origin) && $originid > 0) {
	$object->origin = $origin;
	$object->origin_id = $originid;
}

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
	$permissiontosetinservice = $user->hasRight('powerplantpv', 'powerplant', 'inservice');
	$permissiontosetoutofservice = $user->hasRight('powerplantpv', 'powerplant', 'outofservice');
	$permissionnote = $user->hasRight('powerplantpv', 'powerplant', 'write'); // Used by the include of actions_setnotes.inc.php
	$permissiondellink = $user->hasRight('powerplantpv', 'powerplant', 'write'); // Used by the include of actions_dellink.inc.php
} else {
	$permissiontoread = 1;
	$permissiontoadd = 1; // Used by the include of actions_addupdatedelete.inc.php and actions_lineupdown.inc.php
	$permissiontodelete = 1;
	$permissiontosetinservice = 1;
	$permissiontosetoutofservice = 1;
	$permissionnote = 1;
	$permissiondellink = 1;
}

$upload_dir = null;
if (!empty($object->id)) {
	$upload_dir = powerplantGetDocumentUploadDir($object);
} else {
	$diroutput = powerplantGetDocumentRootDir($conf->entity);
	$upload_dir = $diroutput.'/powerplant';
}
$modulepart = powerplantGetDocumentModulePart();

// Security check (enable at least one, the most restrictive one)
if ($user->socid > 0) {
	$socid = $user->socid;
	$fk_soc = $user->socid;
	if (empty($object->fk_soc)) {
		$object->fk_soc = $user->socid;
		$object->socid = $user->socid;
	}
}
$powerplantentity = (!empty($object->entity) ? (int) $object->entity : (int) $conf->entity);
$isdraft = (isset($object->status) && ($object->status == $object::STATUS_DRAFT) ? 1 : 0);
restrictedArea($user, $object->module, $object, $object->table_element, $object->element, 'fk_soc', 'rowid', $isdraft);
if (!isModEnabled($object->module)) {
	accessforbidden("Module ".$object->module." not enabled");
}
if (!$permissiontoread) {
	accessforbidden();
}

$error = 0;
if ($action == 'add' && !empty($origin) && $originid > 0) {
	$object->origin = $origin;
	$object->origin_id = $originid;
	if (!empty($create_material_from_origin)) {
		$object->create_material_from_origin = 1;
	}
}


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
	$fk_product = GETPOSTINT('fk_product');
	$qty = price2num(GETPOST('qty', 'alpha'), 'MT');

	if ($fk_product > 0 && $qty > 0) {
		$sql = "INSERT INTO ".$db->prefix()."powerplantpv_powerplantcomp(fk_powerplant, fk_product, qty, entity)";
		$sql .= " VALUES(".((int) $object->id).", ".((int) $fk_product).", ".((float) $qty).", ".$powerplantentity.")";
		if ($db->query($sql)) {
			powerplantRecalculateInstalledPower($object);
		}
	}
}
if ($action == 'delcomposition' && $permissiontoadd) {
	$lineid = GETPOSTINT('lineid');
	if ($lineid > 0) {
		$sql = "DELETE FROM ".$db->prefix()."powerplantpv_powerplantcomp WHERE rowid = ".((int) $lineid)." AND fk_powerplant = ".((int) $object->id)." AND entity = ".$powerplantentity;
		if ($db->query($sql)) {
			powerplantRecalculateInstalledPower($object);
		}
	}
}

	$triggermodname = $object->TRIGGER_PREFIX.'_MODIFY'; // Name of trigger action code to execute when we modify record. Used in actions_addupdatedelete.inc.php

	powerplantHandleSetLabelAction($object, $action, $permissiontoadd, $user);

	// Inline update of a single field (row-level edition)
	if ($action == 'updatefield' && $permissiontoadd) {
		// CSRF protection - compatible across Dolibarr versions
		$token = GETPOST('token', 'alphanohtml');
		if (function_exists('checkToken')) {
			if (!checkToken()) {
				accessforbidden();
			}
		} elseif (function_exists('dol_verifyToken')) {
			if (!dol_verifyToken($token)) {
				accessforbidden();
			}
		} else {
			// Fallback for older versions
			if (empty($token) || empty($_SESSION['newtoken']) || $token != $_SESSION['newtoken']) {
				accessforbidden();
			}
		}

		$field = preg_replace('/[^a-zA-Z0-9_]/', '', GETPOST('field', 'nohtml'));

		// Security: allow only known fields from $object->fields.
		if (empty($field) || empty($object->fields[$field])) {
			setEventMessages($langs->trans("ErrorBadParameter"), null, 'errors');
		} elseif ($field == 'installed_power') {
			setEventMessages($langs->trans("ErrorForbidden"), null, 'errors');
		} else {
			$res = 0;

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
			if ($field === 'connection_type' && class_exists('PowerPlant')) {
				$newvalue = PowerPlant::normalizeConnectionTypeValue($newvalue);
			}

			$res = $object->setValueFrom($field, $newvalue, '', $object->id, $format, '', $user, $triggermodname);

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

	// Actions to upload, rename or delete files linked to the object.
	include DOL_DOCUMENT_ROOT.'/core/actions_linkedfiles.inc.php';

	// Actions when printing a doc from card
	include DOL_DOCUMENT_ROOT.'/core/actions_printing.inc.php';

	// Action to move up and down lines of object
	//include DOL_DOCUMENT_ROOT.'/core/actions_lineupdown.inc.php';

	// Action to build doc
	// actions_builddoc.inc.php expects the module document root for remove_file.
	$upload_dir = powerplantGetDocumentRootDir(!empty($object->entity) ? $object->entity : $conf->entity);
	include DOL_DOCUMENT_ROOT.'/core/actions_builddoc.inc.php';

	if ($action == 'set_thirdparty') {
		powerplantHandleSetThirdpartyAction($object, $action, $permissiontoadd, $user);
	}
	if ($action == 'setcategories' && $permissiontoadd) {
		if (function_exists('checkToken') && !checkToken()) {
			accessforbidden();
		}
		$categories = GETPOST('categories', 'array:int');
		if (!is_array($categories)) {
			$categories = array();
		}
		$result = $object->setCategories($categories);
		if ($result > 0) {
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
			header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}
	if ($action == 'setinservice' && empty($permissiontosetinservice)) {
		accessforbidden();
	}
	if ($action == 'setoutofservice' && empty($permissiontosetoutofservice)) {
		accessforbidden();
	}
	if ($action == 'confirm_setinservice' && $confirm == 'yes') {
		if (empty($permissiontosetinservice)) {
			accessforbidden();
		}
		if (function_exists('checkToken') && !checkToken()) {
			accessforbidden();
		}
		$compositiondatemode = GETPOST('composition_date_mode', 'alpha');
		if (!in_array($compositiondatemode, array('overwrite', 'keep'), true)) {
			$compositiondatemode = 'keep';
		}
		$result = $object->setInService($user);
		if ($result >= 0) {
			$resultcompositionstatus = powerplantSetCompositionServiceStatus($object, $user, 4);
			$resultcompositiondate = ($resultcompositionstatus >= 0 ? powerplantApplyCompositionCommissioningDate($object, $user, ($compositiondatemode === 'overwrite' ? 1 : 0)) : -1);
			$resultinstalledpower = ($resultcompositiondate >= 0 ? powerplantRecalculateInstalledPower($object) : -1);
			if ($resultcompositionstatus < 0 || $resultcompositiondate < 0 || $resultinstalledpower < 0) {
				setEventMessages($object->error, $object->errors, 'errors');
			} else {
				setEventMessages($langs->trans('PowerPlantSetInServiceDone'), null, 'mesgs');
				header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
				exit;
			}
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}
	if ($action == 'confirm_setoutofservice' && $confirm == 'yes') {
		if (empty($permissiontosetoutofservice)) {
			accessforbidden();
		}
		if (function_exists('checkToken') && !checkToken()) {
			accessforbidden();
		}
		$result = $object->setOutOfService($user);
		if ($result >= 0) {
			$resultcompositionstatus = powerplantSetCompositionServiceStatus($object, $user, 8);
			$resultinstalledpower = ($resultcompositionstatus >= 0 ? powerplantRecalculateInstalledPower($object) : -1);
			if ($resultcompositionstatus < 0 || $resultinstalledpower < 0) {
				setEventMessages($object->error, $object->errors, 'errors');
			} else {
				setEventMessages($langs->trans('PowerPlantSetOutOfServiceDone'), null, 'mesgs');
				header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
				exit;
			}
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}

	// Actions to send emails
	$triggersendname = 'POWERPLANTPV_POWERPLANT_SENTBYMAIL';
	$autocopy = 'MAIN_MAIL_AUTOCOPY_POWERPLANT_TO';
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
	if (!empty($origin) && $originid > 0) {
		powerplantpvApplyOriginDefaults($object, $origin, $originid);
	}
	if ($fk_soc > 0) {
		$object->fk_soc = $fk_soc;
	}

	$automaticmaterialsummary = array();
	if (!empty($create_material_from_origin) && !empty($origin) && $originid > 0) {
		$automaticmaterialsummary = powerplantpvGetAutomaticMaterialSummary($origin, $originid, 1);
	}

	print load_fiche_titre($title, '', $object->picto);

	print '<form method="POST" action="'.dolBuildUrl($_SERVER["PHP_SELF"]).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	if (!empty($origin) && $originid > 0) {
		print '<input type="hidden" name="origin" value="'.dol_escape_htmltag($origin).'">';
		print '<input type="hidden" name="originid" value="'.((int) $originid).'">';
	}
	if (!empty($create_material_from_origin)) {
		print '<input type="hidden" name="create_material_from_origin" value="1">';
	}
	if ($backtopage) {
		print '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">';
	}
	if ($backtopageforcancel) {
		print '<input type="hidden" name="backtopageforcancel" value="'.dol_escape_htmltag($backtopageforcancel).'">';
	}
	if ($dol_openinpopup) {
		print '<input type="hidden" name="dol_openinpopup" value="'.dol_escape_htmltag($dol_openinpopup).'">';
	}

	print dol_get_fiche_head(array(), '');


	print '<table class="border centpercent tableforfieldcreate">'."\n";

	// Common attributes
	// EN: Render common fields with Dolibarr forms
	if (empty($object->ref)) {
		$object->ref = $object->getProvisionalRefPreview();
	}
	powerplantApplyPowerPlantRuntimeFields($object, $langs);
	$object->fields['ref']['disabled'] = 1;
	$object->fields['ref']['noteditable'] = 1;
	$object->fields['ref']['visible'] = 0;
	$object->fields['ref']['default'] = $object->ref;
	$object->fields['fk_country']['type'] = 'sellist:c_country:label:rowid::active=1';
	$object->fields['installed_power']['noteditable'] = 1;
	$object->fields['installed_power']['visible'] = 0;
	$object->fields = dol_sort_array($object->fields, 'position');
	foreach ($object->fields as $key => $val) {
		$visible = (int) $val['visible'];
		if (abs($visible) != 1 && abs($visible) != 3 && abs($visible) != 6) {
			continue;
		}
		if (array_key_exists('enabled', $val) && isset($val['enabled']) && !verifCond($val['enabled'])) {
			continue;
		}

		$nativeval = powerplantGetNativeFieldDefinition($val);
		$type = (string) $nativeval['type'];
		if (in_array($type, array('int', 'integer'))) {
			$value = GETPOST($key);
		} elseif (preg_match('/^double/', $type)) {
			$value = price2num(GETPOST($key, 'alphanohtml'));
		} elseif (preg_match('/^text/', $type)) {
			$tmparray = explode(':', $type);
			$value = GETPOST($key, (!empty($tmparray[1]) ? $tmparray[1] : 'nohtml'));
		} elseif (preg_match('/^html/', $type)) {
			$tmparray = explode(':', $type);
			$value = GETPOST($key, (!empty($tmparray[1]) ? $tmparray[1] : 'restricthtml'));
		} elseif ($type == 'date') {
			$value = dol_mktime(12, 0, 0, GETPOSTINT($key.'month'), GETPOSTINT($key.'day'), GETPOSTINT($key.'year'));
		} elseif ($type == 'datetime') {
			$value = dol_mktime(GETPOSTINT($key.'hour'), GETPOSTINT($key.'min'), 0, GETPOSTINT($key.'month'), GETPOSTINT($key.'day'), GETPOSTINT($key.'year'));
		} elseif ($type == 'boolean') {
			$value = (GETPOST($key) == 'on' ? 1 : 0);
		} elseif ($type == 'price') {
			$value = price2num(GETPOST($key));
		} elseif ($key == 'lang') {
			$value = GETPOST($key, 'aZ09');
		} else {
			$value = GETPOST($key, 'alphanohtml');
		}

		print '<tr class="field_'.$key.'">';
		print '<td class="titlefieldcreate'.((isset($val['notnull']) && $val['notnull'] > 0) ? ' fieldrequired' : '').(($type == 'text' || $type == 'html') ? ' tdtop' : '').'">';
		print '<label for="'.$key.'" class="block">';
		if (!empty($val['help'])) {
			print $form->textwithpicto($langs->trans($val['label']), $langs->trans($val['help']));
		} else {
			print $langs->trans($val['label']);
		}
		print '</label>';
		print '</td>';
		print '<td class="valuefieldcreate">';
		if (!empty($val['picto'])) {
			print img_picto('', $val['picto'], '', 0, 0, 0, '', 'pictofixedwidth');
		}
		if (!empty($val['noteditable'])) {
			print powerplantRenderPowerPlantOutputField($object, $val, $key, $value);
		} elseif ($key == 'lang') {
			require_once DOL_DOCUMENT_ROOT.'/core/class/html.formadmin.class.php';
			$formadmin = new FormAdmin($db);
			print img_picto('', 'language', 'class="pictofixedwidth"');
			print $formadmin->select_language($value, $key, 0, array(), 1, 0, 0, 'minwidth300', 2);
		} else {
			print powerplantRenderPowerPlantInputField($object, $val, $key, $value);
		}
		print '</td>';
		print '</tr>';
	}

	// Other attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_add.tpl.php';

	print '</table>'."\n";

	print dol_get_fiche_end();

	if (!empty($create_material_from_origin) && !empty($automaticmaterialsummary)) {
		print '<div class="fichecenter">';
		print load_fiche_titre($langs->trans('PowerPlantAutomaticMaterialSummary'), '', '');

		if (!empty($automaticmaterialsummary['source_object']) && is_object($automaticmaterialsummary['source_object'])) {
			$sourceobject = $automaticmaterialsummary['source_object'];
			$sourcehtml = (!empty($sourceobject->ref) ? dol_escape_htmltag($sourceobject->ref) : '');
			if (method_exists($sourceobject, 'getNomUrl')) {
				$sourcehtml = $sourceobject->getNomUrl(1);
			}
			print '<div class="underbanner clearboth"></div>';
			print '<table class="border centpercent tableforfield">';
			print '<tr>';
			print '<td class="titlefield">'.$langs->trans('Source').'</td>';
			print '<td>'.$sourcehtml.'</td>';
			print '</tr>';
			print '</table>';
		}

		print '<table class="border centpercent tableforfield">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans('Category').'</td>';
		print '<td class="right">'.$langs->trans('PVQuantity').'</td>';
		print '<td class="right">'.$langs->trans('PowerPlantComponentsToCreate').'</td>';
		print '<td class="right">'.$langs->trans('PowerPlantIgnoredFractionalQty').'</td>';
		print '</tr>';
		if (empty($automaticmaterialsummary['categories'])) {
			print '<tr class="oddeven"><td colspan="4" class="opacitymedium">'.$langs->trans('PVSummaryNone').'</td></tr>';
		} else {
			foreach ($automaticmaterialsummary['categories'] as $categoryline) {
				print '<tr class="oddeven">';
				print '<td>'.dol_escape_htmltag($categoryline['category_label']).'</td>';
				print '<td class="right">'.price($categoryline['total_qty']).'</td>';
				print '<td class="right">'.((int) $categoryline['total_components']).'</td>';
				print '<td class="right">'.($categoryline['total_ignored_qty'] > 0 ? price($categoryline['total_ignored_qty']) : '').'</td>';
				print '</tr>';
			}
		}
		print '</table>';

		print '<br>';
		print '<div class="div-table-responsive-no-min">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans('Product').'</td>';
		print '<td>'.$langs->trans('Category').'</td>';
		print '<td class="right">'.$langs->trans('PVQuantity').'</td>';
		print '<td class="right">'.$langs->trans('PowerPlantComponentsToCreate').'</td>';
		print '<td class="right">'.$langs->trans('PowerPlantIgnoredFractionalQty').'</td>';
		print '</tr>';
		if (empty($automaticmaterialsummary['lines'])) {
			print '<tr class="oddeven"><td colspan="5" class="opacitymedium">'.$langs->trans('PowerPlantNoAutomaticMaterial').'</td></tr>';
		} else {
			foreach ($automaticmaterialsummary['lines'] as $summaryline) {
				print '<tr class="oddeven">';
				print '<td>';
				print dol_escape_htmltag($summaryline['product_ref']);
				if (!empty($summaryline['product_label'])) {
					print ' - '.dol_escape_htmltag($summaryline['product_label']);
				}
				print '</td>';
				print '<td>'.dol_escape_htmltag($summaryline['category_label']).'</td>';
				print '<td class="right">'.price($summaryline['source_qty']).'</td>';
				print '<td class="right">'.((int) $summaryline['components_to_create']).'</td>';
				print '<td class="right">'.($summaryline['ignored_qty'] > 0 ? price($summaryline['ignored_qty']) : '').'</td>';
				print '</tr>';
			}
		}
		print '</table>';
		print '</div>';

		if (!empty($automaticmaterialsummary['warnings'])) {
			foreach ($automaticmaterialsummary['warnings'] as $warning) {
				print '<div class="warning">'.dol_escape_htmltag($warning).'</div>';
			}
		}

		print '</div>';
	}

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
	powerplantApplyPowerPlantRuntimeFields($object, $langs);

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
$k_access_instructions = 'access_instructions';

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
			if ($key === 'connection_type') {
				$value = powerplantGetConnectionTypeFormValue($value);
			}

			print '<tr class="field_'.$key.'">';
			print '<td class="titlefieldcreate">'.$label.'</td>';
			if (!empty($def['noteditable']) || !empty($def['disabled'])) {
				print '<td class="valuefieldcreate">'.powerplantRenderPowerPlantOutputField($object, $def, $key, $value).'</td>';
			} else {
				print '<td class="valuefieldcreate">'.powerplantRenderPowerPlantInputField($object, $def, $key, $value, $morecss).'</td>';
			}
			print '</tr>';
		};

		// Left column
		print '<div class="fichehalfleft">';

		print load_fiche_titre(img_picto('', 'setup', 'class="pictofixedwidth"').' '.$langs->trans("General"), '', '');
		print '<table class="border centpercent tableforfieldedit">'."\n";
		$printRowEdit($k_description);
		print '</table>';

		print load_fiche_titre($langs->trans("Réseau"), '', 'fa-plug');
		print '<table class="border centpercent tableforfieldedit">'."\n";
		$printRowEdit($k_prm_pdl);
		$printRowEdit($k_connection_type);
		$printRowEdit($k_commissioning_date);
		$printRowEdit($k_enedis_commissioning_date);
		$printRowEdit($k_connection_request_number);
		$printRowEdit($k_connection_request_no);
		$printRowEdit($k_t0_date);
		$printRowEdit($k_connection_contract_power);
		print '</table>';

		print '</div>'; // fichehalfleft

		// Right column
		print '<div class="fichehalfright">';

		print load_fiche_titre($langs->trans("Contrat de rachat"), '', 'currency');
		print '<table class="border centpercent tableforfieldedit">'."\n";
		$printRowEdit($k_installed_power);
		$printRowEdit($k_purchase_contract_no);
		$printRowEdit($k_purchase_tariff);
		print '</table>';

		print '</div>'; // fichehalfright

		// Remaining fields + extrafields (full width, no duplicates)
		print '<div class="clearboth"></div>';

		$exclude = array();
		foreach (array(
			$k_description, $k_address, $k_zip, $k_town, $k_country, $k_access_instructions,
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
					print '<table class="border centpercent tableforfield">'."\n";

				foreach ($allfields as $key => $def) {
					if (!empty($exclude[$key])) continue;
					$vis = isset($def['visible']) ? (int) $def['visible'] : 0;
					if ($vis <= 0 || $vis == 2) continue;

					$printRowEdit($key);
				}

				print '</table>';
			}

				if ($hasextra) {
					print '<table class="border centpercent tableforfield">'."\n";
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
	// Confirmation to delete a linked file or external link.
	if ($action == 'deletefile' || $action == 'deletelink') {
		$langs->load('companies');
		$urlfile = GETPOST('urlfile', 'alpha', 0, null, null, 1);
		$formconfirm = $form->formconfirm(
			$_SERVER["PHP_SELF"].'?id='.$object->id.'&urlfile='.urlencode($urlfile).'&linkid='.GETPOSTINT('linkid'),
			$langs->trans('DeleteFile'),
			$langs->trans('ConfirmDeleteFile'),
			'confirm_deletefile',
			'',
			'',
			1
		);
	}

	// Clone confirmation
	if ($action == 'clone') {
		// Create an array for form
		$formquestion = array();
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('ToClone'), $langs->trans('ConfirmCloneAsk', $object->ref), 'confirm_clone', $formquestion, 'yes', 1);
	}

	// Confirmation for service status changes.
	if ($action == 'setinservice') {
		if (empty($permissiontosetinservice)) {
			accessforbidden();
		}
		$formquestion = array();
		$compositiondateconflicts = powerplantCountCompositionCommissioningDateConflicts($object);
		if ($compositiondateconflicts > 0) {
			$compositiondate = powerplantGetCompositionCommissioningDate($object);
			$formquestion[] = array(
				'type' => 'onecolumn',
				'value' => $langs->trans(
					'PowerPlantCompositionCommissioningDateConflictQuestion',
					$compositiondateconflicts,
					dol_print_date($db->jdate($compositiondate.' 00:00:00'), 'day')
				),
			);
			$formquestion[] = array(
				'type' => 'radio',
				'name' => 'composition_date_mode',
				'label' => $langs->trans('PowerPlantCompositionDateMode'),
				'values' => array(
					'keep' => $langs->trans('PowerPlantCompositionKeepExistingDates'),
					'overwrite' => $langs->trans('PowerPlantCompositionOverwriteExistingDates'),
				),
				'default' => 'keep',
			);
		}
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('PowerPlantSetInService'), $langs->trans('PowerPlantConfirmSetInService', $object->ref), 'confirm_setinservice', $formquestion, 0, 1);
	}
	if ($action == 'setoutofservice') {
		if (empty($permissiontosetoutofservice)) {
			accessforbidden();
		}
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('PowerPlantSetOutOfService'), $langs->trans('PowerPlantConfirmSetOutOfService', $object->ref), 'confirm_setoutofservice', '', 0, 1);
	}

	// Confirmation of action xxxx (You can use it for xxx = 'close', xxx = 'reopen', ...)
	// if ($action == 'xxx') {
	// 	$text = $langs->trans('ConfirmActionXxx', $object->ref);
	// 	if (isModEnabled('notification')) {
	// 		require_once DOL_DOCUMENT_ROOT . '/core/class/notify.class.php';
	// 		$notify = new Notify($db);
	// 		$text .= '<br>';
	// 		$text .= $notify->confirmMessage('POWERPLANT_CLOSE', $object->socid, $object);
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
	$linkback = powerplantGetBackToListLink($object, $socid);
	$morehtmlref = powerplantBuildBannerMoreHtml($object, $permissiontoadd, $action);


	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

	$isdraft = (isset($object->status) && ((int) $object->status === (int) $object::STATUS_DRAFT));


	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';

	// Prepare field types
	$object->fields['fk_country']['type'] = 'sellist:c_country:label:rowid::active=1';
	powerplantApplyPowerPlantRuntimeFields($object, $langs);

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
$k_access_instructions = 'access_instructions';

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
		if (!empty($fieldtoedit) && empty($object->fields[$fieldtoedit])) $fieldtoedit = '';

		$printRowView = function($key, $labelOverride = '', $valueOverride = null) use ($object, $langs, $permissiontoadd, $fieldtoedit) {
			if (empty($key) || empty($object->fields[$key])) return;

			$def = $object->fields[$key];
			$label = $labelOverride ?: $langs->trans(!empty($def['label']) ? $def['label'] : $key);
			$value = ($valueOverride !== null ? $valueOverride : (isset($object->$key) ? $object->$key : ''));
			if ($key === 'connection_type') {
				$value = powerplantGetConnectionTypeFormValue($value);
			}

			$canedit = (!empty($permissiontoadd) && empty($def['noteditable']) && empty($def['disabled']));
			$isedit = ($canedit && $fieldtoedit === $key);

			$urlcard = $_SERVER["PHP_SELF"].'?id='.$object->id;
			$urledit = $urlcard.'&action=editfield&field='.$key.'&token='.newToken();

			print '<tr class="field_'.$key.'" id="field_'.$key.'">';
			print '<td class="titlefieldmiddle">'.$label.'</td>';

			if ($isedit) {
				$formid = 'form_'.$key;
				print '<td class="valuefield">';
				print '<form id="'.$formid.'" method="POST" action="'.$urlcard.'">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="updatefield">';
				print '<input type="hidden" name="field" value="'.$key.'">';
				print powerplantRenderPowerPlantInputField($object, $def, $key, $value);
				print '</form>';
				print '</td>';
				print '<td class="right nowraponall">';
				print '<button type="submit" form="'.$formid.'" class="reposition">'.img_picto($langs->trans("Save"), 'tick').'</button>';
				print ' <a class="reposition" href="'.$urlcard.'">'.img_picto($langs->trans("Cancel"), 'cancel').'</a>';
				print '</td>';
			} else {
				print '<td class="valuefield">'.powerplantRenderPowerPlantOutputField($object, $def, $key, $value).'</td>';
				print '<td class="right nowraponall">';
				if ($canedit) {
					print '<a class="editfielda reposition" href="'.$urledit.'">'.img_edit().'</a>';
				} else {
					print '&nbsp;';
				}
				print '</td>';
			}

			print '</tr>';
		};

		// Left column
		print '<div class="fichehalfleft">';

		print load_fiche_titre(img_picto('', 'setup', 'class="pictofixedwidth"').' '.$langs->trans("General"), '', '');
		print '<table class="border centpercent tableforfield">'."\n";
		$printRowView($k_description);
		print '</table>';

		// Réseau
		print load_fiche_titre($langs->trans("Réseau"), '', 'fa-plug');
		print '<table class="border centpercent tableforfield">'."\n";
		$printRowView($k_prm_pdl);
		$printRowView($k_connection_type);
		$printRowView($k_commissioning_date);
		$printRowView($k_enedis_commissioning_date);
		$printRowView($k_connection_request_number);
		$printRowView($k_connection_request_no);
		$printRowView($k_t0_date);
		$printRowView($k_connection_contract_power);
		print '</table>';

		print '</div>'; // fichehalfleft

		// Right column
		print '<div class="fichehalfright">';

		print load_fiche_titre($langs->trans("Contrat de rachat"), '', 'currency');
		print '<table class="border centpercent tableforfield">'."\n";
		$printRowView($k_installed_power);
		$printRowView($k_purchase_contract_no);
		$printRowView($k_purchase_tariff);
		print '</table>';

		print '</div>'; // fichehalfright

		// Remaining fields + extrafields (full width, no duplicates)
		print '<div class="clearboth"></div>';

		$exclude = array();
		foreach (array(
			$k_description, $k_address, $k_zip, $k_town, $k_country, $k_access_instructions,
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
					print '<td class="valuefield">'.powerplantRenderPowerPlantOutputField($object, $def, $key, $value).'</td>';
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

	if (isModEnabled('category')) {
		print '<div class="clearboth"></div>';
		print '<div class="fichecenter">';
		print load_fiche_titre($langs->trans('Categories'), '', 'category');
		print '<table class="border centpercent tableforfield">';
		print '<tr>';
		print '<td class="titlefield">'.$langs->trans('Categories').'</td>';
		if ($action == 'editcategories' && $permissiontoadd) {
			print '<td>';
			print '<form method="POST" action="'.dolBuildUrl($_SERVER["PHP_SELF"]).'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="setcategories">';
			print '<input type="hidden" name="id" value="'.((int) $object->id).'">';
			print powerplantSelectCategories($form, 'powerplant', 'categories', $object);
			print ' <input type="submit" class="button valignmiddle" value="'.$langs->trans('Save').'">';
			print ' <a class="button button-cancel" href="'.dolBuildUrl($_SERVER["PHP_SELF"], array('id' => $object->id)).'">'.$langs->trans('Cancel').'</a>';
			print '</form>';
			print '</td>';
			print '<td class="right nowraponall">&nbsp;</td>';
		} else {
			$categorieshtml = $form->showCategories($object->id, 'powerplant', 1);
			print '<td>'.($categorieshtml !== '' ? $categorieshtml : '<span class="opacitymedium">'.$langs->trans('None').'</span>').'</td>';
			print '<td class="right nowraponall">';
			if ($permissiontoadd) {
				print '<a class="editfielda reposition" href="'.dolBuildUrl($_SERVER["PHP_SELF"], array('id' => $object->id, 'action' => 'editcategories')).'">'.img_edit($langs->transnoentitiesnoconv('Modify'), 0).'</a>';
			} else {
				print '&nbsp;';
			}
			print '</td>';
		}
		print '</tr>';
		print '</table>';
		print '</div>';
	}

	$compositionstatuslist = array(
		4 => 'PowerPlantCompStatusActivePlural',
		0 => 'PowerPlantCompStatusInactivePlural',
		8 => 'PowerPlantCompStatusOutOfServicePlural',
		6 => 'PowerPlantCompStatusReplacedPlural',
	);
	$compositionsummary = array();

	$sqlcomp = "SELECT cpv.rowid as category_id, cpv.label as category_label, cpv.code as category_code, c.fk_status, COUNT(c.rowid) as nb_products";
	$sqlcomp .= " FROM ".$db->prefix()."powerplantpv_powerplantcomp as c";
	$sqlcomp .= " LEFT JOIN ".$db->prefix()."product_extrafields as pe ON pe.fk_object = c.fk_product";
	$sqlcomp .= " LEFT JOIN ".$db->prefix()."c_powerplantpv_categorypv as cpv ON cpv.rowid = pe.categorie_photovoltaique";
	$sqlcomp .= " WHERE c.fk_powerplant = ".((int) $object->id);
	$sqlcomp .= " AND c.entity = ".$powerplantentity;
	$sqlcomp .= " GROUP BY cpv.rowid, cpv.label, cpv.code, c.fk_status";
	$sqlcomp .= " ORDER BY cpv.label ASC, cpv.rowid ASC, c.fk_status ASC";
	$rescomp = $db->query($sqlcomp);
	if ($rescomp) {
		while ($line = $db->fetch_object($rescomp)) {
			$categoryid = (int) $line->category_id;
			$categorykey = ($categoryid > 0 ? (string) $categoryid : '0');
			if (!isset($compositionsummary[$categorykey])) {
				$compositionsummary[$categorykey] = array(
					'category_id' => $categoryid,
					'label' => ($categoryid > 0 ? $line->category_label : $langs->trans('PVSummaryOtherElementsList')),
					'total' => 0,
					'statuses' => array_fill_keys(array_keys($compositionstatuslist), 0),
				);
			}
			$nbproducts = (int) $line->nb_products;
			$compositionsummary[$categorykey]['total'] += $nbproducts;
			if ($line->fk_status !== null && $line->fk_status !== '') {
				$statuskey = (int) $line->fk_status;
			} else {
				$statuskey = null;
			}
			if ($statuskey !== null && isset($compositionstatuslist[$statuskey])) {
				$compositionsummary[$categorykey]['statuses'][$statuskey] += $nbproducts;
			}
		}
	}

	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';
	print load_fiche_titre($langs->trans('PowerPlantComposition'), '', 'products');
	print '<table class="border centpercent tableforfield">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('Category').'</td>';
	print '<td class="right">'.$langs->trans('Total').'</td>';
	foreach ($compositionstatuslist as $statuslabelkey) {
		print '<td class="right">'.$langs->trans($statuslabelkey).'</td>';
	}
	print '</tr>';
	if (empty($compositionsummary)) {
		print '<tr class="oddeven"><td colspan="6" class="opacitymedium">'.$langs->trans('PVSummaryNone').'</td></tr>';
	} else {
		$compositionbaseurl = dol_buildpath('/powerplantpv/powerplant_composition.php', 1).'?id='.((int) $object->id);
		foreach ($compositionsummary as $summaryline) {
			$categoryid = (int) $summaryline['category_id'];
			$categoryurl = $compositionbaseurl.($categoryid > 0 ? '&search_nature='.$categoryid : '');
			print '<tr class="oddeven">';
			print '<td class="titlefield">'.dol_escape_htmltag($summaryline['label']).'</td>';
			print '<td class="right">';
			if ($categoryid > 0) {
				print '<a href="'.dol_escape_htmltag($categoryurl).'">'.((int) $summaryline['total']).'</a>';
			} else {
				print (int) $summaryline['total'];
			}
			print '</td>';
			foreach ($compositionstatuslist as $statuskey => $statuslabelkey) {
				$nbstatus = (int) $summaryline['statuses'][$statuskey];
				$statusurl = $categoryurl.($categoryid > 0 ? '&search_status='.((int) $statuskey) : '');
				print '<td class="right">';
				if ($categoryid > 0) {
					print '<a href="'.dol_escape_htmltag($statusurl).'">'.$nbstatus.'</a>';
				} else {
					print $nbstatus;
				}
				print '</td>';
			}
			print '</tr>';
		}
	}
	print '</table>';
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
			if ($object->status > $object::STATUS_DRAFT && $object->status != $object::STATUS_CANCELED) {
				print dolGetButtonAction('', $langs->trans('SetToDraft'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=confirm_setdraft&confirm=yes&token='.newToken(), '', $permissiontoadd);
			}

			// Validate
			if ($object->status == $object::STATUS_DRAFT) {
				if (empty($object->table_element_line) || (is_array($object->lines) && count($object->lines) > 0)) {
					print dolGetButtonAction('', $langs->trans('Validate'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_validate&confirm=yes&token='.newToken(), '', $permissiontoadd);
				} else {
					$langs->load("errors");
					print dolGetButtonAction($langs->trans("ErrorAddAtLeastOneLineFirst"), $langs->trans("Validate"), 'default', '#', '', 0);
				}
			}

			if (in_array($object->status, array($object::STATUS_VALIDATED, $object::STATUS_OUT_OF_SERVICE))) {
				print dolGetButtonAction('', $langs->trans('PowerPlantSetInService'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=setinservice&token='.newToken(), '', $permissiontosetinservice);
			}
			if (in_array($object->status, array($object::STATUS_VALIDATED, $object::STATUS_IN_SERVICE))) {
				print dolGetButtonAction('', $langs->trans('PowerPlantSetOutOfService'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=setoutofservice&token='.newToken(), '', $permissiontosetoutofservice);
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
			$filedir = powerplantGetDocumentUploadDir($object);
			$relativepathwithnofile = powerplantGetDocumentRelativePath($object);
			$urlsource = $_SERVER["PHP_SELF"]."?id=".$object->id;
			$genallowed = $permissiontoread; // If you can read, you can build the PDF to read content
			$delallowed = $permissiontoadd; // If you can create/edit, you can remove a file on card
			print $formfile->showdocuments($modulepart.':PowerPlant', $relativepathwithnofile, $filedir, $urlsource, $genallowed, $delallowed, $object->model_pdf, 1, 0, 0, 28, 0, '', '', '', $langs->defaultlang, '', $object);
		}

		// Show links to link elements
		$tmparray = $form->showLinkToObjectBlock(
			$object,
			array(),
			array('powerplant', 'powerplantpv_powerplant', 'powerplant@powerplantpv'),
			1
		);
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

		$MAXEVENT = getDolGlobalInt('MAIN_SIZE_SHORTLIST_LIMIT', 10);

		$morehtmlcenter = dolGetButtonTitle($langs->trans('SeeAll'), '', 'fa fa-bars imgforviewmode', dol_buildpath('/powerplantpv/powerplant_agenda.php', 1).'?id='.$object->id);

		$includeeventlist = (isModEnabled('agenda') && ($user->hasRight('agenda', 'myactions', 'read') || $user->hasRight('agenda', 'allactions', 'read')));

		// List of actions on element
		if ($includeeventlist) {
			include_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
			$formactions = new FormActions($db);
			$actionssocid = (!empty($object->fk_soc) ? (int) $object->fk_soc : (is_object($object->thirdparty) ? (int) $object->thirdparty->id : 0));
			$somethingshown = $formactions->showactions($object, powerplantGetAgendaElementType(), $actionssocid, 1, '', $MAXEVENT, '', $morehtmlcenter);
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
