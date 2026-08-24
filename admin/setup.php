<?php
/* Copyright (C) 2004-2017  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2025		Pierre Ardoin				<erp@lesmetiersdubatiment.fr>
 * Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    powerplantpv/admin/setup.php
 * \ingroup powerplantpv
 * \brief   PowerPlantPV setup page.
 */

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
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/ajax.lib.php";
require_once '../lib/powerplantpv.lib.php';
require_once '../lib/powerplantpv_powerplant.lib.php';
require_once '../lib/powerplantpv_serialnumber.lib.php';
dol_include_once('/powerplantpv/class/powerplantpvmaintenancereminder.class.php');
//require_once "../class/myclass.class.php";

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Translations
$langs->loadLangs(array("admin", "powerplantpv@powerplantpv"));

// Initialize a technical object to manage hooks of page. Note that conf->hooks_modules contains an array of hook context
/** @var HookManager $hookmanager */
$hookmanager->initHooks(array('powerplantpvsetup', 'globalsetup'));

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$modulepart = GETPOST('modulepart', 'aZ09');	// Used by actions_setmoduleoptions.inc.php

$value = GETPOST('value', 'alpha');
$label = GETPOST('label', 'alpha');
$scandir = GETPOST('scan_dir', 'alpha');
$type = 'powerplant';

$error = 0;
$setupnotempty = 0;

// Access control
if (!$user->admin) {
	accessforbidden();
}


// Set this to 1 to use the factory to manage constants. Warning, the generated module will be compatible with version v15+ only
$useFormSetup = 1;

if (!class_exists('FormSetup')) {
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formsetup.class.php';
}
$formSetup = new FormSetup($db);

// Access control
if (!$user->admin) {
	accessforbidden();
}

// Enter here all parameters in your setup page

// End of definition of parameters


$setupnotempty += count($formSetup->items);


$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);

$moduledir = 'powerplantpv';
$myTmpObjects = array();
$myTmpObjects['powerplant'] = array('label' => 'PowerPlant', 'includerefgeneration' => 1, 'includedocgeneration' => 1, 'class' => 'PowerPlant');

$tmpobjectkey = GETPOST('object', 'aZ09');
if ($tmpobjectkey && !array_key_exists($tmpobjectkey, $myTmpObjects)) {
	accessforbidden('Bad value for object. Hack attempt ?');
}

/**
 * Print native numbering and document model settings.
 *
 * @param	array<string,array<string,mixed>>	$myTmpObjects	Objects handled by setup
 * @param	array<int,string>					$dirmodels		Model directories
 * @param	string								$moduledir		Module directory
 * @return	int												Number of printed setup sections
 */
function powerplantpvPrintPowerPlantModelSettings($myTmpObjects, $dirmodels, $moduledir)
{
	global $conf, $db, $form, $langs;

	$printedsections = 0;

	foreach ($myTmpObjects as $myTmpObjectKey => $myTmpObjectArray) {
		if (!empty($myTmpObjectArray['includerefgeneration'])) {
			$printedsections++;

			print load_fiche_titre($langs->trans("NumberingModules", $myTmpObjectArray['label']), '', '');

			print '<table class="noborder centpercent">';
			print '<tr class="liste_titre">';
			print '<td>'.$langs->trans("Name").'</td>';
			print '<td>'.$langs->trans("Description").'</td>';
			print '<td class="nowrap">'.$langs->trans("Example").'</td>';
			print '<td class="center" width="60">'.$langs->trans("Status").'</td>';
			print '<td class="center" width="16">'.$langs->trans("ShortInfo").'</td>';
			print '</tr>'."\n";

			clearstatcache();

			foreach ($dirmodels as $reldir) {
				$dir = dol_buildpath($reldir."core/modules/".$moduledir);

				if (is_dir($dir)) {
					$handle = opendir($dir);
					if (is_resource($handle)) {
						while (($file = readdir($handle)) !== false) {
							if (strpos($file, 'mod_'.strtolower($myTmpObjectKey).'_') === 0 && substr($file, dol_strlen($file) - 3, 3) == 'php') {
								$file = substr($file, 0, dol_strlen($file) - 4);

								require_once $dir.'/'.$file.'.php';

								$module = new $file($db);
								'@phan-var-force ModeleNumRefMyObject $module';

								if ($module->version == 'development' && getDolGlobalInt('MAIN_FEATURES_LEVEL') < 2) {
									continue;
								}
								if ($module->version == 'experimental' && getDolGlobalInt('MAIN_FEATURES_LEVEL') < 1) {
									continue;
								}

								if ($module->isEnabled()) {
									dol_include_once('/'.$moduledir.'/class/'.strtolower($myTmpObjectKey).'.class.php');

									print '<tr class="oddeven"><td>'.$module->getName($langs)."</td><td>\n";
									print $module->info($langs);
									print '</td>';

									print '<td class="nowrap">';
									$tmp = $module->getExample();
									if (preg_match('/^Error/', $tmp)) {
										$langs->load("errors");
										print '<div class="error">'.$langs->trans($tmp).'</div>';
									} elseif ($tmp == 'NotConfigured') {
										print $langs->trans($tmp);
									} else {
										print $tmp;
									}
									print '</td>'."\n";

									print '<td class="center">';
									$constforvar = 'POWERPLANTPV_'.strtoupper($myTmpObjectKey).'_ADDON';
									$defaultifnotset = 'mod_powerplant_standard';
									$activenumberingmodel = getDolGlobalString($constforvar, $defaultifnotset);
									if ($activenumberingmodel == $file) {
										print img_picto($langs->trans("Activated"), 'switch_on');
									} else {
										print '<a href="'.$_SERVER["PHP_SELF"].'?action=setmod&token='.newToken().'&object='.strtolower($myTmpObjectKey).'&value='.urlencode($file).'">';
										print img_picto($langs->trans("Disabled"), 'switch_off');
										print '</a>';
									}
									print '</td>';

									$className = $myTmpObjectArray['class'];
									$mytmpinstance = new $className($db);
									'@phan-var-force MyObject $mytmpinstance';
									$mytmpinstance->initAsSpecimen();

									$htmltooltip = '';
									$htmltooltip .= ''.$langs->trans("Version").': <b>'.$module->getVersion().'</b><br>';

									$nextval = $module->getNextValue($mytmpinstance);
									if ("$nextval" != $langs->trans("NotAvailable")) {
										$htmltooltip .= ''.$langs->trans("NextValue").': ';
										if ($nextval) {
											if (preg_match('/^Error/', $nextval) || $nextval == 'NotConfigured') {
												$nextval = $langs->trans($nextval);
											}
											$htmltooltip .= $nextval.'<br>';
										} else {
											$htmltooltip .= $langs->trans($module->error).'<br>';
										}
									}

									print '<td class="center">';
									print $form->textwithpicto('', $htmltooltip, 1, 'info');
									print '</td>';

									print "</tr>\n";
								}
							}
						}
						closedir($handle);
					}
				}
			}
			print "</table><br>\n";
		}

		if (!empty($myTmpObjectArray['includedocgeneration'])) {
			$printedsections++;
			$type = strtolower($myTmpObjectKey);

			print load_fiche_titre($langs->trans("DocumentModules", $myTmpObjectKey), '', '');

			$def = array();
			$sql = "SELECT nom";
			$sql .= " FROM ".$db->prefix()."document_model";
			$sql .= " WHERE type = '".$db->escape($type)."'";
			$sql .= " AND entity = ".((int) $conf->entity);
			$resql = $db->query($sql);
			if ($resql) {
				$i = 0;
				$num_rows = $db->num_rows($resql);
				while ($i < $num_rows) {
					$array = $db->fetch_array($resql);
					$def[] = $array[0];
					$i++;
				}
			} else {
				dol_print_error($db);
			}

			print '<table class="noborder centpercent">'."\n";
			print '<tr class="liste_titre">'."\n";
			print '<td>'.$langs->trans("Name").'</td>';
			print '<td>'.$langs->trans("Description").'</td>';
			print '<td class="center" width="60">'.$langs->trans("Status")."</td>\n";
			print '<td class="center" width="60">'.$langs->trans("Default")."</td>\n";
			print '<td class="center" width="38">'.$langs->trans("ShortInfo").'</td>';
			print '<td class="center" width="38">'.$langs->trans("Preview").'</td>';
			print "</tr>\n";

			clearstatcache();

			foreach ($dirmodels as $reldir) {
				foreach (array('', '/doc') as $valdir) {
					$realpath = $reldir."core/modules/".$moduledir.$valdir;
					$dir = dol_buildpath($realpath);

					if (is_dir($dir)) {
						$handle = opendir($dir);
						if (is_resource($handle)) {
							$filelist = array();
							while (($file = readdir($handle)) !== false) {
								$filelist[] = $file;
							}
							closedir($handle);
							arsort($filelist);

							foreach ($filelist as $file) {
								if (preg_match('/\.modules\.php$/i', $file) && preg_match('/^(pdf_|doc_)/', $file)) {
									if (file_exists($dir.'/'.$file)) {
										$name = substr($file, 4, dol_strlen($file) - 16);
										$className = substr($file, 0, dol_strlen($file) - 12);

										require_once $dir.'/'.$file;
										$module = new $className($db);
										'@phan-var-force ModelePDFMyObject $module';

										$modulequalified = 1;
										if ($module->version == 'development' && getDolGlobalInt('MAIN_FEATURES_LEVEL') < 2) {
											$modulequalified = 0;
										}
										if ($module->version == 'experimental' && getDolGlobalInt('MAIN_FEATURES_LEVEL') < 1) {
											$modulequalified = 0;
										}

										if ($modulequalified) {
											$nameforurl = (string) $name;
											$scandirforurl = (string) (isset($module->scandir) ? $module->scandir : '');
											$labelforurl = (string) (isset($module->name) ? $module->name : '');
											$objectforurl = strtolower((string) $myTmpObjectKey);

											print '<tr class="oddeven"><td width="100">';
											print(empty($module->name) ? $name : $module->name);
											print "</td><td>\n";
											if (method_exists($module, 'info')) {
												print $module->info($langs);
											} else {
												print $module->description;
											}
											print '</td>';

											if (in_array($name, $def)) {
												print '<td class="center">'."\n";
												print '<a href="'.$_SERVER["PHP_SELF"].'?action=del&token='.newToken().'&value='.urlencode($nameforurl).'">';
												print img_picto($langs->trans("Enabled"), 'switch_on');
												print '</a>';
												print '</td>';
											} else {
												print '<td class="center">'."\n";
												print '<a href="'.$_SERVER["PHP_SELF"].'?action=set&token='.newToken().'&value='.urlencode($nameforurl).'&scan_dir='.urlencode($scandirforurl).'&label='.urlencode($labelforurl).'">'.img_picto($langs->trans("Disabled"), 'switch_off').'</a>';
												print "</td>";
											}

											print '<td class="center">';
											$constforvar = 'POWERPLANTPV_'.strtoupper($myTmpObjectKey).'_ADDON_PDF';
											if (getDolGlobalString($constforvar) == $name) {
												print '<a href="'.$_SERVER["PHP_SELF"].'?action=unsetdoc&token='.newToken().'&object='.urlencode($objectforurl).'&value='.urlencode($nameforurl).'&scan_dir='.urlencode($scandirforurl).'&label='.urlencode($labelforurl).'&amp;type='.urlencode((string) $type).'" alt="'.$langs->trans("Disable").'">'.img_picto($langs->trans("Enabled"), 'on').'</a>';
											} else {
												print '<a href="'.$_SERVER["PHP_SELF"].'?action=setdoc&token='.newToken().'&object='.urlencode($objectforurl).'&value='.urlencode($nameforurl).'&scan_dir='.urlencode($scandirforurl).'&label='.urlencode($labelforurl).'" alt="'.$langs->trans("Default").'">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
											}
											print '</td>';

											$htmltooltip = ''.$langs->trans("Name").': '.$module->name;
											$htmltooltip .= '<br>'.$langs->trans("Type").': '.($module->type ? $module->type : $langs->trans("Unknown"));
											if ($module->type == 'pdf') {
												$htmltooltip .= '<br>'.$langs->trans("Width").'/'.$langs->trans("Height").': '.$module->page_largeur.'/'.$module->page_hauteur;
											}
											$htmltooltip .= '<br>'.$langs->trans("Path").': '.preg_replace('/^\//', '', $realpath).'/'.$file;

											$htmltooltip .= '<br><br><u>'.$langs->trans("FeaturesSupported").':</u>';
											$htmltooltip .= '<br>'.$langs->trans("Logo").': '.yn($module->option_logo, 1, 1);
											$htmltooltip .= '<br>'.$langs->trans("MultiLanguage").': '.yn($module->option_multilang, 1, 1);

											print '<td class="center">';
											print $form->textwithpicto('', $htmltooltip, 1, 'info');
											print '</td>';

											print '<td class="center">';
											if ($module->type == 'pdf') {
												$newname = preg_replace('/_'.preg_quote(strtolower($myTmpObjectKey), '/').'/', '', $name);
												print '<a href="'.$_SERVER["PHP_SELF"].'?action=specimen&token='.newToken().'&module='.urlencode($newname).'&object='.urlencode($myTmpObjectKey).'">'.img_object($langs->trans("Preview"), 'pdf').'</a>';
											} else {
												print img_object($langs->transnoentitiesnoconv("PreviewNotAvailable"), 'generic');
											}
											print '</td>';

											print "</tr>\n";
										}
									}
								}
							}
						}
					}
				}
			}

			print '</table><br>';
		}
	}

	return $printedsections;
}

/**
 * Read a date/time selected with Form::selectDate().
 *
 * @param	string	$prefix	Field prefix
 * @return	int				Timestamp or 0
 */
function powerplantpvSetupReadDateTimeFromPost($prefix)
{
	$year = GETPOSTINT($prefix.'year');
	$month = GETPOSTINT($prefix.'month');
	$day = GETPOSTINT($prefix.'day');
	$hour = GETPOSTINT($prefix.'hour');
	$minute = GETPOSTINT($prefix.'min');
	if ($year <= 0 || $month <= 0 || $day <= 0) {
		return 0;
	}

	return dol_mktime($hour, $minute, 0, $month, $day, $year);
}

/**
 * Return configured timestamp or a default value.
 *
 * @param	string	$constname	Constant name
 * @return	int					Timestamp
 */
function powerplantpvSetupGetTimestampConst($constname)
{
	$value = getDolGlobalString($constname, '');
	if ($value !== '') {
		if (is_numeric($value)) {
			return (int) $value;
		}
		$timestamp = dol_stringtotime($value, 0);
		if ($timestamp > 0) {
			return $timestamp;
		}
	}

	return dol_now();
}

/**
 * Return active email template options.
 *
 * @return	array<int,string>	Template options
 */
function powerplantpvSetupGetEmailTemplateOptions()
{
	global $conf, $db, $langs;

	$options = array(0 => $langs->trans('PowerPlantPVMaintenanceReminderDefaultTemplate'));
	$sql = 'SELECT rowid, label';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'c_email_templates';
	$sql .= " WHERE enabled = '1'";
	$sql .= ' AND entity IN (0, '.((int) $conf->entity).')';
	$sql .= " AND type_template = 'actioncomm_send'";
	$sql .= ' ORDER BY label ASC, rowid ASC';
	$resql = $db->query($sql);
	if (!$resql) {
		return $options;
	}
	while (is_object($obj = $db->fetch_object($resql))) {
		$options[(int) $obj->rowid] = (string) $obj->label;
	}
	$db->free($resql);

	return $options;
}

/**
 * Return active internal users with an email address.
 *
 * @return	array<int,string>	User options
 */
function powerplantpvSetupGetMaintenanceReminderUserOptions()
{
	global $db;

	$options = array();
	$sql = 'SELECT rowid, lastname, firstname, login, email';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'user';
	$sql .= ' WHERE statut = 1';
	$sql .= " AND email IS NOT NULL AND email <> ''";
	$sql .= ' AND entity IN ('.$db->sanitize(getEntity('user')).')';
	$sql .= ' ORDER BY lastname ASC, firstname ASC, login ASC, rowid ASC';
	$resql = $db->query($sql);
	if (!$resql) {
		return $options;
	}
	while (is_object($obj = $db->fetch_object($resql))) {
		$label = trim(dolGetFirstLastname($obj->firstname, $obj->lastname));
		if ($label === '') {
			$label = (string) $obj->login;
		}
		$label .= ' <'.(string) $obj->email.'>';
		$options[(int) $obj->rowid] = $label;
	}
	$db->free($resql);

	return $options;
}


/*
 * Actions
 */

// For retrocompatibility Dolibarr < 15.0
if (versioncompare(explode('.', DOL_VERSION), array(15)) < 0 && $action == 'update' && !empty($user->admin)) {
	$formSetup->saveConfFromPost();
}

include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

if ($action == 'updateMask') {
	$maskconst = GETPOST('maskconst', 'aZ09');
	$maskvalue = GETPOST('maskvalue', 'alpha');

	if ($maskconst && preg_match('/_MASK$/', $maskconst)) {
		$res = dolibarr_set_const($db, $maskconst, $maskvalue, 'chaine', 0, '', $conf->entity);
		if (!($res > 0)) {
			$error++;
		}
	}

	if (!$error) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
} elseif ($action == 'recalculate_commercial_peak_power') {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}

	$result = powerplantpvRecalculateAllCommercialDocumentPeakPower();
	if ($result['result'] > 0) {
		setEventMessages($langs->trans('PowerPlantPVPeakPowerRecalculationDone', $result['updated']), null, 'mesgs');
	} else {
		$errorinfo = (!empty($result['errorinfo']) && is_array($result['errorinfo']) ? $result['errorinfo'] : null);
		$errormessage = powerplantpvBuildPeakPowerRecalculationErrorMessage(!empty($user->admin), $errorinfo);
		setEventMessages($langs->trans('PowerPlantPVPeakPowerRecalculationFailed'), array($errormessage), 'errors');
	}
} elseif ($action == 'recalculate_commercial_storage_capacity') {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}

	$result = powerplantpvRecalculateAllCommercialDocumentStorageCapacity();
	if ($result['result'] > 0) {
		setEventMessages($langs->trans('PowerPlantPVStorageCapacityRecalculationDone', $result['updated'], $result['incomplete']), $result['incomplete_documents'], $result['incomplete'] > 0 ? 'warnings' : 'mesgs');
	} else {
		setEventMessages($langs->trans('PowerPlantPVStorageCapacityRecalculationFailed'), array($result['error']), 'errors');
	}
} elseif ($action == 'specimen' && $tmpobjectkey) {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
	$modele = GETPOST('module', 'alpha');

	$className = $myTmpObjects[$tmpobjectkey]['class'];
	$tmpobject = new $className($db);
	'@phan-var-force MyObject $tmpobject';
	$tmpobject->initAsSpecimen();

	// Search template files
	$file = '';
	$className = '';
	$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);
	foreach ($dirmodels as $reldir) {
		$file = dol_buildpath($reldir."core/modules/powerplantpv/doc/pdf_".$modele."_".strtolower($tmpobjectkey).".modules.php", 0);
		if (file_exists($file)) {
			$className = "pdf_".$modele."_".strtolower($tmpobjectkey);
			break;
		}
	}

	if ($className !== '') {
		require_once $file;

		$module = new $className($db);
		'@phan-var-force ModelePDFMyObject $module';

		'@phan-var-force ModelePDFMyObject $module';

		if ($module->write_file($tmpobject, $langs) > 0) {
			$documentfile = 'SPECIMEN.pdf';
			if (!empty($module->result['fullpath'])) {
				$documentroot = str_replace('\\', '/', powerplantGetDocumentRootDir($tmpobject->entity ?? $conf->entity).'/'.strtolower($tmpobjectkey));
				$generatedfile = str_replace('\\', '/', $module->result['fullpath']);
				if (strpos($generatedfile, $documentroot.'/') === 0) {
					$documentfile = substr($generatedfile, dol_strlen($documentroot) + 1);
				} else {
					$documentfile = basename($generatedfile);
				}
			}
			header("Location: ".DOL_URL_ROOT."/document.php?modulepart=powerplantpv-".strtolower($tmpobjectkey)."&file=".urlencode($documentfile));
			return;
		} else {
			setEventMessages($module->error, null, 'errors');
			dol_syslog($module->error, LOG_ERR);
		}
	} else {
		setEventMessages($langs->trans("ErrorModuleNotFound"), null, 'errors');
		dol_syslog($langs->trans("ErrorModuleNotFound"), LOG_ERR);
	}
} elseif ($action == 'setmod') {
	// TODO Check if numbering module chosen can be activated by calling method canBeActivated
	if (!empty($tmpobjectkey)) {
		$constforval = 'POWERPLANTPV_'.strtoupper($tmpobjectkey)."_ADDON";
		dolibarr_set_const($db, $constforval, $value, 'chaine', 0, '', $conf->entity);
	}
} elseif ($action == 'set') {
	// Activate a model
	$ret = addDocumentModel($value, $type, $label, $scandir);
} elseif ($action == 'del') {
	$ret = delDocumentModel($value, $type);
	if ($ret > 0) {
		if (!empty($tmpobjectkey)) {
			$constforval = 'POWERPLANTPV_'.strtoupper($tmpobjectkey).'_ADDON_PDF';
			if (getDolGlobalString($constforval) == "$value") {
				dolibarr_del_const($db, $constforval, $conf->entity);
			}
		}
	}
} elseif ($action == 'setdoc') {
	// Set or unset default model
	if (!empty($tmpobjectkey)) {
		$constforval = 'POWERPLANTPV_'.strtoupper($tmpobjectkey).'_ADDON_PDF';
		if (dolibarr_set_const($db, $constforval, $value, 'chaine', 0, '', $conf->entity)) {
			// The constant that was read before the new set
			// We therefore requires a variable to have a coherent view
			$conf->global->{$constforval} = $value;
		}

		// We disable/enable the document template (into llx_document_model table)
		$ret = delDocumentModel($value, $type);
		if ($ret > 0) {
			$ret = addDocumentModel($value, $type, $label, $scandir);
		}
	}
} elseif ($action == 'unsetdoc') {
	if (!empty($tmpobjectkey)) {
		$constforval = 'POWERPLANTPV_'.strtoupper($tmpobjectkey).'_ADDON_PDF';
		dolibarr_del_const($db, $constforval, $conf->entity);
	}
} elseif ($action == 'save_pvfree') {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}

	$pvfreeapiurl = trim(GETPOST('pvfree_api_url', 'nohtml'));
	$pvfreetimeout = GETPOSTINT('pvfree_timeout');
	$pvfreeoverwritestrategy = GETPOST('pvfree_overwrite_strategy', 'aZ09');
	$pvfreemoduledataset = GETPOST('pvfree_default_module_dataset', 'aZ09');
	$pvfreeinverterdataset = GETPOST('pvfree_default_inverter_dataset', 'aZ09');

	if ($pvfreeapiurl === '') {
		$pvfreeapiurl = 'https://pvfree.azurewebsites.net';
	}
	if ($pvfreetimeout <= 0) {
		$pvfreetimeout = 10;
	}
	if (!in_array($pvfreeoverwritestrategy, array('never', 'empty_only', 'overwrite_after_confirm'), true)) {
		$pvfreeoverwritestrategy = 'empty_only';
	}
	if (!in_array($pvfreemoduledataset, array('cecmodule', 'pvmodule'), true)) {
		$pvfreemoduledataset = 'cecmodule';
	}
	if ($pvfreeinverterdataset !== 'pvinverter') {
		$pvfreeinverterdataset = 'pvinverter';
	}

	$res = dolibarr_set_const($db, 'POWERPLANTPV_PVFREE_API_URL', $pvfreeapiurl, 'chaine', 0, '', $conf->entity);
	$res = $res && dolibarr_set_const($db, 'POWERPLANTPV_PVFREE_TIMEOUT', $pvfreetimeout, 'chaine', 0, '', $conf->entity);
	$res = $res && dolibarr_set_const($db, 'POWERPLANTPV_PVFREE_OVERWRITE_EXISTING_DATA', $pvfreeoverwritestrategy, 'chaine', 0, '', $conf->entity);
	$res = $res && dolibarr_set_const($db, 'POWERPLANTPV_PVFREE_DEFAULT_MODULE_DATASET', $pvfreemoduledataset, 'chaine', 0, '', $conf->entity);
	$res = $res && dolibarr_set_const($db, 'POWERPLANTPV_PVFREE_DEFAULT_INVERTER_DATASET', $pvfreeinverterdataset, 'chaine', 0, '', $conf->entity);

	if ($res) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
} elseif ($action == 'save_file_import') {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}

	$importmaxfilesize = GETPOSTINT('import_max_file_size');
	$importmaxrows = GETPOSTINT('bulk_import_max_rows');
	$importoverwritestrategy = GETPOST('import_overwrite_strategy', 'aZ09');
	$importseparator = GETPOST('import_default_separator', 'nohtml');

	if ($importmaxfilesize <= 0) {
		$importmaxfilesize = 5;
	}
	if ($importmaxrows <= 0) {
		$importmaxrows = 1000;
	}
	if (!in_array($importoverwritestrategy, array('never', 'empty_only', 'overwrite_after_confirm'), true)) {
		$importoverwritestrategy = 'empty_only';
	}
	if (!in_array($importseparator, array(';', ',', 'tab'), true)) {
		$importseparator = ';';
	}

	$res = dolibarr_set_const($db, 'POWERPLANTPV_IMPORT_MAX_FILE_SIZE', $importmaxfilesize, 'chaine', 0, '', $conf->entity);
	$res = $res && dolibarr_set_const($db, 'POWERPLANTPV_BULK_IMPORT_MAX_ROWS', $importmaxrows, 'chaine', 0, '', $conf->entity);
	$res = $res && dolibarr_set_const($db, 'POWERPLANTPV_IMPORT_OVERWRITE_EXISTING_DATA', $importoverwritestrategy, 'chaine', 0, '', $conf->entity);
	$res = $res && dolibarr_set_const($db, 'POWERPLANTPV_IMPORT_DEFAULT_SEPARATOR', $importseparator, 'chaine', 0, '', $conf->entity);

	if ($res) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
} elseif ($action == 'save_serialnumber_settings') {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}

	$ignoredcategoryids = GETPOST('serialnumber_ignored_category_ids', 'array:int');
	if (!is_array($ignoredcategoryids)) {
		$ignoredcategoryids = array();
	}
	$ignoredcategoryids = powerplantpvSerialNumberFilterExistingCategoryIds($ignoredcategoryids, true);
	$ignoredcategoryvalue = implode(',', array_map('intval', $ignoredcategoryids));

	$res = dolibarr_set_const($db, 'POWERPLANTPV_SERIALNUMBER_IGNORED_CATEGORY_IDS', $ignoredcategoryvalue, 'chaine', 0, '', (int) $conf->entity);
	if ($res > 0) {
		$res = dolibarr_set_const($db, 'POWERPLANTPV_SERIALNUMBER_IGNORED_CATEGORY_IDS_CONFIGURED', '1', 'chaine', 0, '', (int) $conf->entity);
	}

	if ($res > 0) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
} elseif ($action == 'save_maintenance_reminder_settings') {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}

	$leadDays = GETPOSTINT('maintenance_planning_lead_days');
	if ($leadDays < 0) {
		$leadDays = 0;
	}
	$scheduledInterventionMode = GETPOST('maintenance_scheduled_intervention_mode', 'alpha');
	if (!in_array($scheduledInterventionMode, array(PowerPlantPVMaintenanceScheduler::SCHEDULED_MODE_CREATED, PowerPlantPVMaintenanceScheduler::SCHEDULED_MODE_VALIDATED), true)) {
		$scheduledInterventionMode = PowerPlantPVMaintenanceScheduler::SCHEDULED_MODE_CREATED;
	}
	$weeklyStart = powerplantpvSetupReadDateTimeFromPost('maintenance_weekly_reminder_start');
	$monthlyStart = powerplantpvSetupReadDateTimeFromPost('maintenance_monthly_reminder_start');
	$templateId = GETPOSTINT('maintenance_reminder_email_template');
	$templateOptions = powerplantpvSetupGetEmailTemplateOptions();
	if (!isset($templateOptions[$templateId])) {
		$templateId = 0;
	}
	$userIds = GETPOST('maintenance_reminder_user_ids', 'array:int');
	if (!is_array($userIds)) {
		$userIds = array();
	}

	$userOptions = powerplantpvSetupGetMaintenanceReminderUserOptions();
	$userOptionIds = array_flip(array_keys($userOptions));
	$selectedUserIds = array();
	foreach ($userIds as $userId) {
		$userId = (int) $userId;
		if ($userId > 0 && isset($userOptionIds[$userId])) {
			$selectedUserIds[$userId] = $userId;
		}
	}

	$error = 0;
	if ($weeklyStart <= 0 || $monthlyStart <= 0) {
		setEventMessages($langs->trans('PowerPlantPVMaintenanceReminderStartTimeInvalid'), null, 'errors');
		$error++;
	}

	if (!$error) {
		$res = dolibarr_set_const($db, 'POWERPLANTPV_MAINTENANCE_PLANNING_LEAD_DAYS', (string) $leadDays, 'chaine', 0, '', (int) $conf->entity);
		$res = $res && dolibarr_set_const($db, 'POWERPLANTPV_MAINTENANCE_SCHEDULED_INTERVENTION_MODE', $scheduledInterventionMode, 'chaine', 0, '', (int) $conf->entity);
		$res = $res && dolibarr_set_const($db, 'POWERPLANTPV_MAINTENANCE_WEEKLY_REMINDER_STARTTIME', (string) $weeklyStart, 'chaine', 0, '', (int) $conf->entity);
		$res = $res && dolibarr_set_const($db, 'POWERPLANTPV_MAINTENANCE_MONTHLY_REMINDER_STARTTIME', (string) $monthlyStart, 'chaine', 0, '', (int) $conf->entity);
		$res = $res && dolibarr_set_const($db, 'POWERPLANTPV_MAINTENANCE_REMINDER_USER_IDS', implode(',', array_values($selectedUserIds)), 'chaine', 0, '', (int) $conf->entity);
		$res = $res && dolibarr_set_const($db, 'POWERPLANTPV_MAINTENANCE_REMINDER_EMAIL_TEMPLATE', (string) $templateId, 'chaine', 0, '', (int) $conf->entity);

		if ($res > 0) {
			$warnings = array();
			$weeklyCronUpdate = PowerPlantPVMaintenanceReminder::updateCronStartTime($db, 'weekly', $weeklyStart, $user);
			$monthlyCronUpdate = PowerPlantPVMaintenanceReminder::updateCronStartTime($db, 'monthly', $monthlyStart, $user);
			if ($weeklyCronUpdate < 0 || $monthlyCronUpdate < 0) {
				setEventMessages($db->lasterror(), null, 'errors');
			} else {
				if ($weeklyCronUpdate === 0) {
					$warnings[] = $langs->trans('PowerPlantPVMaintenanceWeeklyReminderCronMissing');
				}
				if ($monthlyCronUpdate === 0) {
					$warnings[] = $langs->trans('PowerPlantPVMaintenanceMonthlyReminderCronMissing');
				}
				setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
				if (!empty($warnings)) {
					setEventMessages($langs->trans('Warning'), $warnings, 'warnings');
				}
			}
		} else {
			setEventMessages($db->lasterror(), null, 'errors');
		}
	}
} elseif ($action == 'save_report_pdf_settings') {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}

	$legalnotice = trim(GETPOST('report_pdf_legal_notice', 'restricthtml'));
	$res = dolibarr_set_const($db, 'POWERPLANTPV_REPORT_PDF_LEGAL_NOTICE', $legalnotice, 'chaine', 0, '', (int) $conf->entity);
	if ($res > 0) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
}

$action = 'edit';


/*
 * View
 */

$form = new Form($db);

$help_url = '';
$title = "PowerPlantPVSetup";

llxHeader('', $langs->trans($title), $help_url, '', 0, 0, '', '', '', 'mod-powerplantpv page-admin');

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.img_picto($langs->trans("BackToModuleList"), 'back', 'class="pictofixedwidth"').'<span class="hideonsmartphone">'.$langs->trans("BackToModuleList").'</span></a>';

print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

// Configuration header
$head = powerplantpvAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($title), -1, 'fa-sun');

// Setup page goes here
echo '<span class="opacitymedium">'.$langs->trans("PowerPlantPVSetupPage").'</span><br><br>';

if (getDolGlobalInt('POWERPLANTPV_ATTESTATION_ENABLE', 1)) {
	powerplantpvAttestationPrintInstallationWarnings();
}

$setupnotempty += powerplantpvPrintPowerPlantModelSettings($myTmpObjects, $dirmodels, $moduledir);


/*if ($action == 'edit') {
 print $formSetup->generateOutput(true);
 print '<br>';
 } elseif (!empty($formSetup->items)) {
 print $formSetup->generateOutput();
 print '<div class="tabsAction">';
 print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit&token='.newToken().'">'.$langs->trans("Modify").'</a>';
 print '</div>';
 }
 */
if (!empty($formSetup->items)) {
	print $formSetup->generateOutput(true);
	print '<br>';
}

$conf->global->POWERPLANTPV_MAINTENANCE_WEEKLY_REMINDER_ENABLE = getDolGlobalInt('POWERPLANTPV_MAINTENANCE_WEEKLY_REMINDER_ENABLE', 0);
$conf->global->POWERPLANTPV_MAINTENANCE_MONTHLY_REMINDER_ENABLE = getDolGlobalInt('POWERPLANTPV_MAINTENANCE_MONTHLY_REMINDER_ENABLE', 0);
$maintenanceleadtime = getDolGlobalInt('POWERPLANTPV_MAINTENANCE_PLANNING_LEAD_DAYS', 30);
$maintenancescheduledmode = getDolGlobalString('POWERPLANTPV_MAINTENANCE_SCHEDULED_INTERVENTION_MODE', PowerPlantPVMaintenanceScheduler::SCHEDULED_MODE_CREATED);
if (!in_array($maintenancescheduledmode, array(PowerPlantPVMaintenanceScheduler::SCHEDULED_MODE_CREATED, PowerPlantPVMaintenanceScheduler::SCHEDULED_MODE_VALIDATED), true)) {
	$maintenancescheduledmode = PowerPlantPVMaintenanceScheduler::SCHEDULED_MODE_CREATED;
}
$maintenanceweeklystart = powerplantpvSetupGetTimestampConst('POWERPLANTPV_MAINTENANCE_WEEKLY_REMINDER_STARTTIME');
$maintenancemonthlystart = powerplantpvSetupGetTimestampConst('POWERPLANTPV_MAINTENANCE_MONTHLY_REMINDER_STARTTIME');
$maintenancereminderusers = array_filter(array_map('intval', explode(',', getDolGlobalString('POWERPLANTPV_MAINTENANCE_REMINDER_USER_IDS', ''))));
$maintenancereminderuseroptions = powerplantpvSetupGetMaintenanceReminderUserOptions();
$maintenancereminderusers = array_values(array_intersect($maintenancereminderusers, array_keys($maintenancereminderuseroptions)));
$maintenanceremindertemplateoptions = powerplantpvSetupGetEmailTemplateOptions();
$maintenanceremindertemplate = getDolGlobalInt('POWERPLANTPV_MAINTENANCE_REMINDER_EMAIL_TEMPLATE', 0);

print load_fiche_titre($langs->trans('PowerPlantPVMaintenanceReminderSettings'), '', 'email');
print '<span class="opacitymedium">'.$langs->trans('PowerPlantPVMaintenanceReminderSettingsHelp').'</span>';
print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save_maintenance_reminder_settings">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Name').'</td><td>'.$langs->trans('Value').'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('PowerPlantPVMaintenancePlanningLeadDays').'</td><td><input type="number" min="0" class="flat maxwidth100 right" name="maintenance_planning_lead_days" value="'.((int) $maintenanceleadtime).'"> '.$langs->trans('PowerPlantPVDays').'<br><span class="opacitymedium">'.$langs->trans('PowerPlantPVMaintenancePlanningLeadDaysHelp').'</span></td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('PowerPlantPVMaintenanceScheduledInterventionMode').'</td><td>';
print $form->selectarray('maintenance_scheduled_intervention_mode', array(
	PowerPlantPVMaintenanceScheduler::SCHEDULED_MODE_CREATED => $langs->trans('PowerPlantPVMaintenanceScheduledInterventionModeCreated'),
	PowerPlantPVMaintenanceScheduler::SCHEDULED_MODE_VALIDATED => $langs->trans('PowerPlantPVMaintenanceScheduledInterventionModeValidated'),
), $maintenancescheduledmode, 0, 0, 0, '', 0, 0, 0, '', 'flat minwidth300');
print '<br><span class="opacitymedium">'.$langs->trans('PowerPlantPVMaintenanceScheduledInterventionModeHelp').'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PowerPlantPVMaintenanceWeeklyReminderEnable').'</td><td>'.ajax_constantonoff('POWERPLANTPV_MAINTENANCE_WEEKLY_REMINDER_ENABLE', array(), (int) $conf->entity, 0, 0, 0, 2, 0, 1).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PowerPlantPVMaintenanceWeeklyReminderStartTime').'</td><td>'.$form->selectDate($maintenanceweeklystart, 'maintenance_weekly_reminder_start', 1, 1, 1, '', 1, 1).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PowerPlantPVMaintenanceMonthlyReminderEnable').'</td><td>'.ajax_constantonoff('POWERPLANTPV_MAINTENANCE_MONTHLY_REMINDER_ENABLE', array(), (int) $conf->entity, 0, 0, 0, 2, 0, 1).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PowerPlantPVMaintenanceMonthlyReminderStartTime').'</td><td>'.$form->selectDate($maintenancemonthlystart, 'maintenance_monthly_reminder_start', 1, 1, 1, '', 1, 1).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PowerPlantPVMaintenanceReminderRecipients').'</td><td>';
if (empty($maintenancereminderuseroptions)) {
	print '<span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span>';
} else {
	print '<select class="flat minwidth500" id="maintenance_reminder_user_ids" name="maintenance_reminder_user_ids[]" multiple>';
	foreach ($maintenancereminderuseroptions as $userId => $userLabel) {
		$selected = in_array((int) $userId, $maintenancereminderusers, true) ? ' selected' : '';
		print '<option value="'.((int) $userId).'"'.$selected.'>'.dol_escape_htmltag($userLabel).'</option>';
	}
	print '</select>';
}
print '<br><span class="opacitymedium">'.$langs->trans('PowerPlantPVMaintenanceReminderRecipientsHelp').'</span>';
print '</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PowerPlantPVMaintenanceReminderEmailTemplate').'</td><td>'.$form->selectarray('maintenance_reminder_email_template', $maintenanceremindertemplateoptions, $maintenanceremindertemplate, 0, 0, 0, '', 0, 0, 0, '', 'flat minwidth300').'<br><span class="opacitymedium">'.$langs->trans('PowerPlantPVMaintenanceReminderEmailTemplateHelp').'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PowerPlantPVMaintenanceReminderSubstitutions').'</td><td><span class="opacitymedium">__POWERPLANTPV_MAINTENANCE_REMINDER_FREQUENCY__, __POWERPLANTPV_MAINTENANCE_REMINDER_COUNT__, __POWERPLANTPV_MAINTENANCE_REMINDER_HTML__, __POWERPLANTPV_MAINTENANCE_REMINDER_TEXT__</span></td></tr>';
print '</table>';
print '<div class="tabsAction">';
print '<input type="submit" class="butAction" value="'.$langs->trans('Save').'">';
print '</div>';
print '</form>';
if ($conf->use_javascript_ajax) {
	print '<script nonce="'.getNonce().'">jQuery(function(){jQuery("#maintenance_reminder_user_ids,#maintenance_reminder_email_template,#maintenance_scheduled_intervention_mode").select2({width:"resolve",minimumResultsForSearch:0});});</script>';
}
print '<br>';

print load_fiche_titre($langs->trans('PowerPlantPVReportPdfSettings'), '', 'fa-file-pdf');
print '<span class="opacitymedium">'.$langs->trans('PowerPlantPVReportPdfSettingsHelp').'</span>';
print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save_report_pdf_settings">';
print '<table class="noborder centpercent">';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('PowerPlantPVReportPdfLegalNotice').'</td><td>';
$reportpdflegalnotice = getDolGlobalString('POWERPLANTPV_REPORT_PDF_LEGAL_NOTICE');
if (isModEnabled('fckeditor')) {
	require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
	$doleditor = new DolEditor('report_pdf_legal_notice', $reportpdflegalnotice, '', 140, 'dolibarr_notes', '', false, true, true, 4, '90%');
	$doleditor->Create();
} else {
	print '<textarea class="flat centpercent" rows="4" name="report_pdf_legal_notice">'.dol_escape_htmltag($reportpdflegalnotice).'</textarea>';
}
print '<br><span class="opacitymedium">'.$langs->trans('PowerPlantPVReportPdfLegalNoticeHelp').'</span>';
print '</td></tr>';
print '</table>';
print '<div class="tabsAction">';
print '<input type="submit" class="butAction" value="'.$langs->trans('Save').'">';
print '</div>';
print '</form>';
print '<br>';

$serialnumbercategories = powerplantpvSerialNumberFetchPhotovoltaicCategories(true);
$serialnumberignoredids = powerplantpvSerialNumberGetIgnoredCategoryIds((int) $conf->entity);
$serialnumberignoredconfigured = powerplantpvSerialNumberIgnoredCategoriesAreConfigured((int) $conf->entity);

print load_fiche_titre($langs->trans('SerialNumbersSettings'), '', 'fa-barcode');
print '<span class="opacitymedium">'.$langs->trans('SerialNumbersIgnoredCategoriesHelp').'</span>';
if (!$serialnumberignoredconfigured) {
	print '<br><span class="opacitymedium">'.$langs->trans('SerialNumbersIgnoredCategoriesDefaultHelp').'</span>';
}
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save_serialnumber_settings">';
print '<table class="noborder centpercent">';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('SerialNumbersIgnoredCategories').'</td><td>';
if (empty($serialnumbercategories)) {
	print '<span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span>';
} else {
	print '<select class="flat minwidth500" id="serialnumber_ignored_category_ids" name="serialnumber_ignored_category_ids[]" multiple>';
	foreach ($serialnumbercategories as $categoryid => $category) {
		$categorylabel = (string) $category['label'];
		$translatedlabel = $langs->trans($categorylabel);
		if ($translatedlabel !== $categorylabel) {
			$categorylabel = $translatedlabel;
		}
		if (!empty($category['code'])) {
			$categorylabel .= ' ('.(string) $category['code'].')';
		}
		$selected = in_array((int) $categoryid, $serialnumberignoredids, true) ? ' selected' : '';
		print '<option value="'.((int) $categoryid).'"'.$selected.'>'.dol_escape_htmltag($categorylabel).'</option>';
	}
	print '</select>';
	print '<br><span class="opacitymedium">'.$langs->trans('SerialNumbersIgnoredCategoriesEmptyHelp').'</span>';
}
print '</td></tr>';
print '</table>';
print '<div class="tabsAction">';
print '<input type="submit" class="butAction" value="'.$langs->trans('Save').'">';
print '</div>';
print '</form>';
if ($conf->use_javascript_ajax && !empty($serialnumbercategories)) {
	print '<script nonce="'.getNonce().'">jQuery(function(){jQuery("#serialnumber_ignored_category_ids").select2({width:"resolve",minimumResultsForSearch:0});});</script>';
}
print '<br>';

$conf->global->POWERPLANTPV_PVFREE_IMPORT_RAW_JSON = getDolGlobalInt('POWERPLANTPV_PVFREE_IMPORT_RAW_JSON', 1);
$pvfreeoverwritestrategies = array(
	'never' => $langs->trans('PVFreeOverwriteNever'),
	'empty_only' => $langs->trans('PVFreeOverwriteEmptyOnly'),
	'overwrite_after_confirm' => $langs->trans('PVFreeOverwriteAfterConfirm'),
);
$pvfreemoduledatasets = array(
	'cecmodule' => $langs->trans('PVFreeDatasetCECModule'),
	'pvmodule' => $langs->trans('PVFreeDatasetSandiaModule'),
);
$pvfreeinverterdatasets = array(
	'pvinverter' => $langs->trans('PVFreeDatasetPVInverter'),
);

print load_fiche_titre($langs->trans('PVFreeConnector'), '', '');
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save_pvfree">';
print '<table class="noborder centpercent">';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('PVFreeConnectorEnabled').'</td><td>'.ajax_constantonoff('POWERPLANTPV_PVFREE_ENABLED', array(), (int) $conf->entity, 0, 0, 0, 2, 0, 1).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVFreeStoreRawJson').'</td><td>'.ajax_constantonoff('POWERPLANTPV_PVFREE_IMPORT_RAW_JSON', array(), (int) $conf->entity, 0, 0, 0, 2, 0, 1).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVFreeAPIUrl').'</td><td><input type="text" class="flat minwidth500" name="pvfree_api_url" value="'.dol_escape_htmltag(getDolGlobalString('POWERPLANTPV_PVFREE_API_URL', 'https://pvfree.azurewebsites.net')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVFreeTimeout').'</td><td><input type="number" min="1" class="flat maxwidth100 right" name="pvfree_timeout" value="'.((int) getDolGlobalInt('POWERPLANTPV_PVFREE_TIMEOUT', 10)).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVFreeOverwriteStrategy').'</td><td>'.$form->selectarray('pvfree_overwrite_strategy', $pvfreeoverwritestrategies, getDolGlobalString('POWERPLANTPV_PVFREE_OVERWRITE_EXISTING_DATA', 'empty_only'), 0, 0, '', 0, 0, 0, '', 'flat minwidth300').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVFreeDefaultModuleDataset').'</td><td>'.$form->selectarray('pvfree_default_module_dataset', $pvfreemoduledatasets, getDolGlobalString('POWERPLANTPV_PVFREE_DEFAULT_MODULE_DATASET', 'cecmodule'), 0, 0, '', 0, 0, 0, '', 'flat minwidth300').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PVFreeDefaultInverterDataset').'</td><td>'.$form->selectarray('pvfree_default_inverter_dataset', $pvfreeinverterdatasets, getDolGlobalString('POWERPLANTPV_PVFREE_DEFAULT_INVERTER_DATASET', 'pvinverter'), 0, 0, '', 0, 0, 0, '', 'flat minwidth300').'</td></tr>';
print '</table>';
print '<div class="tabsAction">';
print '<input type="submit" class="butAction" value="'.$langs->trans('Save').'">';
print '</div>';
print '</form>';
if ($conf->use_javascript_ajax) {
	print '<script nonce="'.getNonce().'">jQuery(function(){jQuery("#pvfree_overwrite_strategy,#pvfree_default_module_dataset,#pvfree_default_inverter_dataset").select2({width:"resolve",minimumResultsForSearch:0});});</script>';
}

$conf->global->POWERPLANTPV_COMPONENT_IMPORT_CSV_ENABLED = getDolGlobalInt('POWERPLANTPV_COMPONENT_IMPORT_CSV_ENABLED', 1);
$conf->global->POWERPLANTPV_COMPONENT_IMPORT_XLSX_ENABLED = getDolGlobalInt('POWERPLANTPV_COMPONENT_IMPORT_XLSX_ENABLED', 1);
$conf->global->POWERPLANTPV_IMPORT_RAW_DATA = getDolGlobalInt('POWERPLANTPV_IMPORT_RAW_DATA', 1);
$fileimportoverwritestrategies = array(
	'never' => $langs->trans('ProductTechnicalImportOverwriteNever'),
	'empty_only' => $langs->trans('ProductTechnicalImportOverwriteEmptyOnly'),
	'overwrite_after_confirm' => $langs->trans('ProductTechnicalImportOverwriteAfterConfirm'),
);
$fileimportseparators = array(
	';' => $langs->trans('ProductTechnicalImportSeparatorSemicolon'),
	',' => $langs->trans('ProductTechnicalImportSeparatorComma'),
	'tab' => $langs->trans('ProductTechnicalImportSeparatorTab'),
);

print load_fiche_titre($langs->trans('ProductTechnicalImportConnector'), '', '');
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save_file_import">';
print '<table class="noborder centpercent">';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('ProductTechnicalImportCSVEnabled').'</td><td>'.ajax_constantonoff('POWERPLANTPV_COMPONENT_IMPORT_CSV_ENABLED', array(), (int) $conf->entity, 0, 0, 0, 2, 0, 1).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('ProductTechnicalImportXLSXEnabled').'</td><td>'.ajax_constantonoff('POWERPLANTPV_COMPONENT_IMPORT_XLSX_ENABLED', array(), (int) $conf->entity, 0, 0, 0, 2, 0, 1).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('ProductTechnicalImportStoreRawData').'</td><td>'.ajax_constantonoff('POWERPLANTPV_IMPORT_RAW_DATA', array(), (int) $conf->entity, 0, 0, 0, 2, 0, 1).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('ProductTechnicalImportMaxFileSize').'</td><td><input type="number" min="1" class="flat maxwidth100 right" name="import_max_file_size" value="'.((int) getDolGlobalInt('POWERPLANTPV_IMPORT_MAX_FILE_SIZE', 5)).'"> MB</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PowerPlantPVBulkImportMaximumRows').'</td><td><input type="number" min="1" max="100000" class="flat maxwidth100 right" name="bulk_import_max_rows" value="'.((int) getDolGlobalInt('POWERPLANTPV_BULK_IMPORT_MAX_ROWS', 1000)).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PowerPlantPVBulkImport').'</td><td>'.dolGetButtonAction($langs->trans('PowerPlantPVBulkImportOpen'), '', 'default', dol_buildpath('/powerplantpv/admin/product_import.php', 1), '', true).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('ProductTechnicalImportOverwriteStrategy').'</td><td>'.$form->selectarray('import_overwrite_strategy', $fileimportoverwritestrategies, getDolGlobalString('POWERPLANTPV_IMPORT_OVERWRITE_EXISTING_DATA', 'empty_only'), 0, 0, '', 0, 0, 0, '', 'flat minwidth300').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('ProductTechnicalImportDefaultSeparator').'</td><td>'.$form->selectarray('import_default_separator', $fileimportseparators, getDolGlobalString('POWERPLANTPV_IMPORT_DEFAULT_SEPARATOR', ';'), 0, 0, '', 0, 0, 0, '', 'flat minwidth200').'</td></tr>';
print '</table>';
print '<div class="tabsAction">';
print '<input type="submit" class="butAction" value="'.$langs->trans('Save').'">';
print '</div>';
print '</form>';
if ($conf->use_javascript_ajax) {
	print '<script nonce="'.getNonce().'">jQuery(function(){jQuery("#import_overwrite_strategy,#import_default_separator").select2({width:"resolve",minimumResultsForSearch:0});});</script>';
}

print load_fiche_titre($langs->trans('PowerPlantPVPeakPowerRecalculation'), '', '');
print '<span class="opacitymedium">'.$langs->trans('PowerPlantPVPeakPowerRecalculationHelp').'</span>';
print '<div class="tabsAction">';
print dolGetButtonAction(
	$langs->trans('PowerPlantPVRecalculatePeakPower'),
	'',
	'default',
	$_SERVER['PHP_SELF'].'?action=recalculate_commercial_peak_power&token='.newToken(),
	'',
	true
);
print '</div>';

print load_fiche_titre($langs->trans('PowerPlantPVStorageCapacityRecalculation'), '', '');
print '<span class="opacitymedium">'.$langs->trans('PowerPlantPVStorageCapacityRecalculationHelp').'</span>';
print '<div class="tabsAction">';
print dolGetButtonAction(
	$langs->trans('PowerPlantPVRecalculateStorageCapacity'),
	'',
	'default',
	$_SERVER['PHP_SELF'].'?action=recalculate_commercial_storage_capacity&token='.newToken(),
	'',
	true
);
print '</div>';

if (empty($setupnotempty)) {
	print '<br>'.$langs->trans("NothingToSetup");
}

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
