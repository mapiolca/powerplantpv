<?php
/* Copyright (C) 2026		Pierre Ardoin			<developpeur@lesmetiersdubatiment.fr>
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
 * \file		powerplant_composition.php
 * \ingroup	powerplantpv
 * \brief	Material composition list tab for power plant
 */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)).'/main.inc.php')) {
	$res = @include substr($tmp, 0, ($i + 1)).'/main.inc.php';
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php')) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php';
}
if (!$res && file_exists('../main.inc.php')) {
	$res = @include '../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
	$res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = @include '../../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
dol_include_once('/powerplantpv/class/powerplant.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv_powerplant.lib.php');

$langs->loadLangs(array('powerplantpv@powerplantpv', 'products', 'other'));

if (!function_exists('powerplantpv_check_token')) {
	/**
	 * Cross-version CSRF token check helper.
	 *
	 * @return bool
	 */
	function powerplantpv_check_token()
	{
		$token = GETPOST('token', 'alphanohtml');
		if (function_exists('checkToken')) {
			return checkToken();
		}
		if (function_exists('dol_verifyToken')) {
			return dol_verifyToken($token);
		}
		return (!empty($token) && !empty($_SESSION['newtoken']) && $token === $_SESSION['newtoken']);
	}
}

if (!function_exists('powerplantCompositionFetchLine')) {
	/**
	 * Fetch a composition line with product display data.
	 *
	 * @param	int	$powerplantid	Power plant id
	 * @param	int	$lineid			Composition line id
	 * @return	stdClass|null		Line object or null
	 */
	function powerplantCompositionFetchLine($powerplantid, $lineid)
	{
		global $db, $conf;

		if ($powerplantid <= 0 || $lineid <= 0) {
			return null;
		}

		$sql = "SELECT c.rowid, c.fk_product, c.fk_status, c.serial_number, c.commissioning_date, p.ref as product_ref, p.label as product_label";
		$sql .= " FROM ".$db->prefix()."powerplantpv_powerplantcomp as c";
		$sql .= " JOIN ".$db->prefix()."product as p ON p.rowid = c.fk_product";
		$sql .= " WHERE c.rowid = ".((int) $lineid);
		$sql .= " AND c.fk_powerplant = ".((int) $powerplantid);
		$sql .= " AND c.entity = ".((int) $conf->entity);

		$resql = $db->query($sql);
		if (!$resql) {
			return null;
		}

		$obj = $db->fetch_object($resql);
		return ($obj ?: null);
	}
}

if (!function_exists('powerplantCompositionLineLabel')) {
	/**
	 * Return a readable composition line label.
	 *
	 * @param	stdClass	$line	Composition line
	 * @return	string			Label
	 */
	function powerplantCompositionLineLabel($line)
	{
		$label = '';
		if (!empty($line->product_ref)) {
			$label = $line->product_ref;
		}
		if (!empty($line->product_label)) {
			$label .= ($label !== '' ? ' - ' : '').$line->product_label;
		}
		if (!empty($line->serial_number)) {
			$label .= ($label !== '' ? ' / ' : '').$line->serial_number;
		}

		return ($label !== '' ? $label : '#'.((int) $line->rowid));
	}
}

if (!function_exists('powerplantCompositionDateToSqlDate')) {
	/**
	 * Normalize a composition date for comparisons.
	 *
	 * @param	string|null	$date	Date value
	 * @return	string			YYYY-MM-DD or empty string
	 */
	function powerplantCompositionDateToSqlDate($date)
	{
		if (empty($date)) {
			return '';
		}
		if (preg_match('/^(\d{4}-\d{2}-\d{2})/', (string) $date, $matches)) {
			return $matches[1];
		}

		return '';
	}
}

if (!function_exists('powerplantCompositionTriggerForStatus')) {
	/**
	 * Return the composition trigger code matching a status change.
	 *
	 * @param	int	$status	Status
	 * @return	string		Trigger code
	 */
	function powerplantCompositionTriggerForStatus($status)
	{
		if ((int) $status === 4) {
			return 'POWERPLANTPV_POWERPLANT_COMP_INSERVICE';
		}
		if ((int) $status === 8) {
			return 'POWERPLANTPV_POWERPLANT_COMP_OUTOFSERVICE';
		}
		if ((int) $status === 6) {
			return 'POWERPLANTPV_POWERPLANT_COMP_REPLACE';
		}

		return 'POWERPLANTPV_POWERPLANT_COMP_MODIFY';
	}
}

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$massaction = GETPOST('massaction', 'alpha');
$lineid = GETPOSTINT('lineid');
$toselect = GETPOST('toselect', 'array:int');

$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_search_x', 'alpha') || GETPOST('button_removefilter', 'alpha') || GETPOST('button_removefilter_x', 'alpha')) {
	$page = 0;
}
$offset = $limit * $page;

// Keep sort fields restricted to existing SQL columns to avoid SQL errors with old saved list params.
$sortfieldlist = array(
	'p.ref',
	'p.label',
	'cpv.label',
	'c.fk_status',
	'c.serial_number',
	'c.commissioning_date',
	'c.rowid'
);
$sortfields = array_filter(array_map('trim', explode(',', $sortfield)));
if (empty($sortfields) || count(array_diff($sortfields, $sortfieldlist)) > 0) {
	$sortfield = 'c.fk_status,cpv.label,c.rowid';
	$sortorder = '';
} else {
	$sortfield = implode(',', $sortfields);
}
$sortorders = array_filter(array_map('trim', explode(',', $sortorder)));
foreach ($sortorders as $key => $val) {
	$sortorders[$key] = strtoupper($val);
}
if (empty($sortorders) || count(array_diff($sortorders, array('ASC', 'DESC'))) > 0) {
	$sortorder = 'ASC,ASC,ASC';
} else {
	$sortorder = implode(',', $sortorders);
}

$search_ref = trim(GETPOST('search_ref', 'alphanohtml'));
$search_label = trim(GETPOST('search_label', 'alphanohtml'));
$search_nature = GETPOSTINT('search_nature');
$search_status = GETPOST('search_status', 'alphanohtml');
$search_status = ($search_status === '' ? '' : (string) ((int) $search_status));
$search_nature = ($search_nature < 0 ? 0 : $search_nature);
if ($search_status !== '' && (int) $search_status < 0) {
	$search_status = '';
}
$search_serial = trim(GETPOST('search_serial', 'alphanohtml'));
$search_commissioning = trim(GETPOST('search_commissioning', 'alphanohtml'));

if (GETPOST('cancel', 'alpha')) {
	$action = 'view';
}

$object = new PowerPlant($db);
$form = new Form($db);
$hookmanager->initHooks(array($object->element.'composition', 'globalcard'));

$enablepermissioncheck = getDolGlobalInt('POWERPLANTPV_ENABLE_PERMISSION_CHECK');
if ($enablepermissioncheck) {
	$permissiontoread = $user->hasRight('powerplantpv', 'powerplant', 'read');
	$permissiontoadd = $user->hasRight('powerplantpv', 'powerplant', 'write');
} else {
	$permissiontoread = 1;
	$permissiontoadd = 1;
}

if (!isModEnabled($object->module) || !$permissiontoread) {
	accessforbidden();
}

include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

powerplantHandleSetLabelAction($object, $action, $permissiontoadd, $user);
powerplantHandleSetThirdpartyAction($object, $action, $permissiontoadd, $user);

$categories = array();
$sqlcategories = "SELECT rowid, label FROM ".$db->prefix()."c_powerplantpv_categorypv WHERE active = 1 ORDER BY label ASC";
$rescategories = $db->query($sqlcategories);
if ($rescategories) {
	while ($objcat = $db->fetch_object($rescategories)) {
		$categories[(int) $objcat->rowid] = $objcat->label;
	}
}

if (GETPOST('button_removefilter', 'alpha') || GETPOST('button_removefilter_x', 'alpha')) {
	$search_ref = '';
	$search_label = '';
	$search_nature = 0;
	$search_status = '';
	$search_serial = '';
	$search_commissioning = '';
}

$componentstatus = array(
	0 => $langs->trans('PowerPlantCompStatusInactive'),
	4 => $langs->trans('PowerPlantCompStatusActive'),
	6 => $langs->trans('PowerPlantCompStatusReplaced'),
	8 => $langs->trans('PowerPlantCompStatusOutOfService')
);

$canedit = ($permissiontoadd && (int) $object->status === (int) $object::STATUS_DRAFT);
$canmanagecomposition = ($permissiontoadd && (int) $object->status !== (int) $object::STATUS_CANCELED);
$availablemassactions = array();
if ($canmanagecomposition) {
	$availablemassactions[] = 'massreplace';
	$availablemassactions[] = 'massupdatecommissioning';
	$availablemassactions[] = 'massupdatestatus';
}
if ($canedit) {
	$availablemassactions[] = 'massdelete';
}
$showaddform = ($canedit && $action === 'addcomposition');
$openaddmodal = 0;
if ($showaddform) {
	$openaddmodal = 1;
}
$recalculateinstalledpower = 0;

if ($action === 'createcomposition' && $canedit) {
	if (!powerplantpv_check_token()) {
		accessforbidden();
	}

	$fk_product = GETPOSTINT('fk_product');
	$qty = GETPOSTINT('qty');
	$fk_status = GETPOST('fk_status', 'alphanohtml');
	$fk_status = ($fk_status === '' ? 4 : (int) $fk_status);
	if (!array_key_exists((int) $fk_status, $componentstatus)) {
		$fk_status = 4;
	}
	$commissioning_date = GETPOST('commissioning_date', 'alphanohtml');
	$commissioning_date_sql = 'NULL';
	if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $commissioning_date)) {
		$commissioning_date_sql = "'".$db->escape($commissioning_date)."'";
	}
	if ($qty < 1) {
		$qty = 1;
	}

	$isallowedproduct = 0;
	if ($fk_product > 0) {
		$sqlcheck = "SELECT pe.fk_object FROM ".$db->prefix()."product_extrafields as pe";
		$sqlcheck .= " WHERE pe.fk_object = ".((int) $fk_product);
		$sqlcheck .= " AND pe.categorie_photovoltaique IS NOT NULL AND pe.categorie_photovoltaique <> ''";
		$rescheck = $db->query($sqlcheck);
		if ($rescheck && $db->num_rows($rescheck) > 0) {
			$isallowedproduct = 1;
		}
	}

	if ($fk_product > 0 && $isallowedproduct) {
		$i = 0;
		$nbcreated = 0;
		while ($i < $qty) {
			$sql = 'INSERT INTO '.$db->prefix()."powerplantpv_powerplantcomp(fk_powerplant, fk_product, fk_status, qty, serial_number, commissioning_date, entity)";
			$sql .= ' VALUES ('.((int) $object->id).', '.((int) $fk_product).', '.((int) $fk_status).', 1, \'\', '.$commissioning_date_sql.', '.((int) $conf->entity).')';
			if ($db->query($sql)) {
				$nbcreated++;
			} else {
				setEventMessages($db->lasterror(), null, 'errors');
			}
			$i++;
		}
		if ($nbcreated > 0) {
			$recalculateinstalledpower = 1;
		}
	}

	$action = 'view';
	$showaddform = false;
}

if ($action === 'delcomposition' && $canedit && $lineid > 0) {
	if (!powerplantpv_check_token()) {
		accessforbidden();
	}

	$sql = 'DELETE FROM '.$db->prefix().'powerplantpv_powerplantcomp';
	$sql .= ' WHERE rowid = '.((int) $lineid);
	$sql .= ' AND fk_powerplant = '.((int) $object->id);
	$sql .= ' AND entity = '.((int) $conf->entity);
	if ($db->query($sql)) {
		$recalculateinstalledpower = 1;
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
}

if ($action === 'updateline' && $canmanagecomposition && $lineid > 0) {
	if (!powerplantpv_check_token()) {
		accessforbidden();
	}

	$oldline = powerplantCompositionFetchLine($object->id, $lineid);
	$serial_number = GETPOST('serial_number', 'alphanohtml');
	$fk_status = GETPOST('fk_status_edit', 'alphanohtml');
	$fk_status = ($fk_status === '' ? 4 : (int) $fk_status);
	if (!array_key_exists((int) $fk_status, $componentstatus)) {
		$fk_status = 4;
	}
	$commissioning_date = GETPOST('commissioning_date', 'alphanohtml');
	$commissioning_date_sql = 'NULL';
	if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $commissioning_date)) {
		$commissioning_date_sql = "'".$db->escape($commissioning_date)."'";
	}
	$sql = "UPDATE ".$db->prefix()."powerplantpv_powerplantcomp";
	$sql .= " SET serial_number = '".$db->escape($serial_number)."', fk_status = ".((int) $fk_status).", commissioning_date = ".$commissioning_date_sql;
	$sql .= " WHERE rowid = ".((int) $lineid)." AND fk_powerplant = ".((int) $object->id)." AND entity = ".((int) $conf->entity);
	$resupdate = $db->query($sql);
	if (!$resupdate) {
		setEventMessages($db->lasterror(), null, 'errors');
	} else {
		$recalculateinstalledpower = 1;
		$newline = powerplantCompositionFetchLine($object->id, $lineid);
		if ($oldline && $newline) {
			$changes = array();
			$triggercode = 'POWERPLANTPV_POWERPLANT_COMP_MODIFY';
			if ((string) $oldline->serial_number !== (string) $newline->serial_number) {
				$changes[] = $langs->transnoentities('PowerPlantCompositionSerialChanged', (string) $oldline->serial_number, (string) $newline->serial_number);
				$triggercode = 'POWERPLANTPV_POWERPLANT_COMP_SERIAL';
			}
			if ((int) $oldline->fk_status !== (int) $newline->fk_status) {
				$oldstatus = isset($componentstatus[(int) $oldline->fk_status]) ? $componentstatus[(int) $oldline->fk_status] : (string) $oldline->fk_status;
				$newstatus = isset($componentstatus[(int) $newline->fk_status]) ? $componentstatus[(int) $newline->fk_status] : (string) $newline->fk_status;
				$changes[] = $langs->transnoentities('PowerPlantCompositionStatusChanged', $oldstatus, $newstatus);
				$triggercode = powerplantCompositionTriggerForStatus((int) $newline->fk_status);
			}
			$olddate = powerplantCompositionDateToSqlDate($oldline->commissioning_date);
			$newdate = powerplantCompositionDateToSqlDate($newline->commissioning_date);
			if ($olddate !== $newdate) {
				$changes[] = $langs->transnoentities('PowerPlantCompositionCommissioningChanged', $olddate, $newdate);
				if ($triggercode === 'POWERPLANTPV_POWERPLANT_COMP_MODIFY') {
					$triggercode = 'POWERPLANTPV_POWERPLANT_COMP_COMMISSIONING';
				}
			}
			if (!empty($changes)) {
				$label = $langs->transnoentities('PowerPlantCompositionLineModified', powerplantCompositionLineLabel($newline));
				$message = $langs->transnoentities('PowerPlantCompositionLineModifiedDesc', powerplantCompositionLineLabel($newline))."\n".implode("\n", $changes);
				powerplantTriggerAgendaEvent($object, $user, $triggercode, $label, $message);
			}
		}
	}
	$action = 'view';
}

if (($action === '' || $action === 'view' || $action === 'list') && $massaction !== '' && is_array($toselect) && count($toselect) > 0) {
	if (!powerplantpv_check_token()) {
		accessforbidden();
	}
	if (in_array($massaction, $availablemassactions, true)) {
		$action = $massaction;
	}
}

$massselectedids = array_map('intval', (array) $toselect);
$massselectedids = array_filter($massselectedids, function ($v) {
	return ($v > 0);
});
$massselectedids = array_values($massselectedids);
$masslines = array();
$masslinesbyid = array();

// Load selected lines outside modal rendering to keep data available after list reload.
if (!empty($massselectedids)) {
	$sqlmasslines = "SELECT rowid, fk_product, serial_number, commissioning_date, fk_status";
	$sqlmasslines .= " FROM ".$db->prefix()."powerplantpv_powerplantcomp";
	$sqlmasslines .= " WHERE fk_powerplant = ".((int) $object->id)." AND entity = ".((int) $conf->entity);
	$sqlmasslines .= " AND rowid IN (".implode(',', $massselectedids).")";
	$sqlmasslines .= " ORDER BY rowid ASC";
	$resmasslines = $db->query($sqlmasslines);
	if ($resmasslines) {
		while ($objmassline = $db->fetch_object($resmasslines)) {
			$masslines[] = $objmassline;
			$masslinesbyid[(int) $objmassline->rowid] = $objmassline;
		}
	}
}

if (GETPOSTINT('confirmmassaction') && GETPOSTINT('massaction_confirmed') && $massaction === 'massdelete' && $canedit) {
	if (!powerplantpv_check_token()) {
		accessforbidden();
	}
	$idstodelete = array_map('intval', GETPOST('toselect', 'array:int'));
	$idstodelete = array_filter($idstodelete, function ($v) {
		return ($v > 0);
	});
	if (!empty($idstodelete)) {
		$sql = 'DELETE FROM '.$db->prefix().'powerplantpv_powerplantcomp';
		$sql .= ' WHERE fk_powerplant = '.((int) $object->id);
		$sql .= ' AND entity = '.((int) $conf->entity);
		$sql .= ' AND rowid IN ('.implode(',', $idstodelete).')';
		if ($db->query($sql)) {
			$recalculateinstalledpower = 1;
		} else {
			setEventMessages($db->lasterror(), null, 'errors');
		}
	}
	$action = 'view';
}

if (GETPOSTINT('confirmmassaction') && GETPOSTINT('massaction_confirmed') && $massaction === 'massupdatecommissioning' && $canmanagecomposition) {
	if (!powerplantpv_check_token()) {
		accessforbidden();
	}
	$idstoupdate = array_map('intval', GETPOST('toselect', 'array:int'));
	$idstoupdate = array_filter($idstoupdate, function ($v) {
		return ($v > 0);
	});
	$commissioning_date_mass = GETPOST('commissioning_date_mass', 'alphanohtml');
	$apply_to_all_date = GETPOSTINT('apply_to_all_date');
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $commissioning_date_mass)) {
		$commissioning_date_mass = '';
	}
	if (!empty($idstoupdate) && $commissioning_date_mass !== '') {
		$idslist = $idstoupdate;
		if (!$apply_to_all_date) {
			$idslist = array((int) $idstoupdate[0]);
		}
		$nbchanged = 0;
		foreach ($idslist as $idlineupdate) {
			if (!empty($masslinesbyid[(int) $idlineupdate]) && powerplantCompositionDateToSqlDate($masslinesbyid[(int) $idlineupdate]->commissioning_date) !== $commissioning_date_mass) {
				$nbchanged++;
			}
		}
		$sql = "UPDATE ".$db->prefix()."powerplantpv_powerplantcomp";
		$sql .= " SET commissioning_date = '".$db->escape($commissioning_date_mass)."'";
		$sql .= " WHERE fk_powerplant = ".((int) $object->id)." AND entity = ".((int) $conf->entity);
		$sql .= " AND rowid IN (".implode(',', $idslist).")";
		$resupdate = $db->query($sql);
		if (!$resupdate) {
			setEventMessages($db->lasterror(), null, 'errors');
		} elseif ($nbchanged > 0) {
			$label = $langs->transnoentities('PowerPlantCompositionCommissioningMassChanged', $object->ref);
			$message = $langs->transnoentities('PowerPlantCompositionCommissioningMassChangedDesc', $nbchanged, dol_print_date($db->jdate($commissioning_date_mass.' 00:00:00'), 'day'));
			powerplantTriggerAgendaEvent($object, $user, 'POWERPLANTPV_POWERPLANT_COMP_COMMISSIONING', $label, $message);
		}
	}
	$action = 'view';
}

if (GETPOSTINT('confirmmassaction') && GETPOSTINT('massaction_confirmed') && $massaction === 'massupdatestatus' && $canmanagecomposition && !GETPOST('cancel', 'alpha')) {
	if (!powerplantpv_check_token()) {
		accessforbidden();
	}
	$lineids = GETPOST('lineid_mass_status', 'array:int');
	$statuses = GETPOST('status_mass_line', 'array');
	if (!is_array($lineids)) {
		$lineids = array();
	}
	if (!is_array($statuses)) {
		$statuses = array();
	}
	$lineids = array_map('intval', $lineids);
	if (!empty($lineids)) {
		$error = 0;
		$nbchanged = 0;
		$statuschanges = array();
		$db->begin();
		foreach ($lineids as $idx => $lineidmass) {
			$lineidmass = (int) $lineidmass;
			if ($lineidmass <= 0 || !isset($statuses[$idx])) {
				continue;
			}
			$statusline = (int) $statuses[$idx];
			if (!array_key_exists($statusline, $componentstatus)) {
				continue;
			}
			if (!empty($masslinesbyid[$lineidmass]) && (int) $masslinesbyid[$lineidmass]->fk_status === $statusline) {
				continue;
			}
			$sql = "UPDATE ".$db->prefix()."powerplantpv_powerplantcomp";
			$sql .= " SET fk_status = ".((int) $statusline);
			$sql .= " WHERE rowid = ".((int) $lineidmass)." AND fk_powerplant = ".((int) $object->id)." AND entity = ".((int) $conf->entity);
			$resupdate = $db->query($sql);
			if (!$resupdate) {
				$error++;
				setEventMessages($db->lasterror(), null, 'errors');
				break;
			}
			$nbchanged++;
			if (empty($statuschanges[$statusline])) {
				$statuschanges[$statusline] = 0;
			}
			$statuschanges[$statusline]++;
		}
		if ($error) {
			$db->rollback();
		} else {
			$db->commit();
			if ($nbchanged > 0) {
				$recalculateinstalledpower = 1;
				$triggercode = 'POWERPLANTPV_POWERPLANT_COMP_MODIFY';
				if (count($statuschanges) === 1) {
					$statuskeys = array_keys($statuschanges);
					$triggercode = powerplantCompositionTriggerForStatus((int) $statuskeys[0]);
				}
				$details = array();
				foreach ($statuschanges as $statuskey => $nbstatus) {
					$statuslabel = isset($componentstatus[(int) $statuskey]) ? $componentstatus[(int) $statuskey] : (string) $statuskey;
					$details[] = $langs->transnoentities('PowerPlantCompositionStatusMassChangedLine', $nbstatus, $statuslabel);
				}
				$label = $langs->transnoentities('PowerPlantCompositionStatusMassChanged', $object->ref);
				$message = $langs->transnoentities('PowerPlantCompositionStatusMassChangedDesc', $nbchanged)."\n".implode("\n", $details);
				powerplantTriggerAgendaEvent($object, $user, $triggercode, $label, $message);
			}
		}
	}
	$action = 'view';
}

if (GETPOSTINT('confirmmassaction') && GETPOSTINT('massaction_confirmed') && $massaction === 'massreplace' && $canmanagecomposition && !GETPOST('cancel', 'alpha')) {
	if (!powerplantpv_check_token()) {
		accessforbidden();
	}
	$lineids = array_map('intval', GETPOST('lineid_mass_replace', 'array:int'));
	$products = array_map('intval', GETPOST('fk_product_mass_replace', 'array:int'));
	$serials = GETPOST('serial_number_mass_replace', 'array');
	$dates = GETPOST('commissioning_date_mass_replace', 'array');
	$statuses = GETPOST('fk_status_mass_replace', 'array');
	if (!empty($lineids)) {
		$nbreplaced = 0;
		$error = 0;
		$db->begin();
		foreach ($lineids as $idx => $lineidmass) {
			$lineidmass = (int) $lineidmass;
			$productid = isset($products[$idx]) ? (int) $products[$idx] : 0;
			$serial = isset($serials[$idx]) ? $db->escape($serials[$idx]) : '';
			$dateval = isset($dates[$idx]) ? $dates[$idx] : '';
			$statusval = isset($statuses[$idx]) ? (int) $statuses[$idx] : 4;
			if (!array_key_exists($statusval, $componentstatus)) {
				$statusval = 4;
			}
			if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateval)) {
				$dateval = dol_print_date(dol_now(), '%Y-%m-%d');
			}
			if ($lineidmass <= 0 || $productid <= 0) {
				continue;
			}
			$sqlold = "UPDATE ".$db->prefix()."powerplantpv_powerplantcomp";
			$sqlold .= " SET fk_status = 6";
			$sqlold .= " WHERE rowid = ".((int) $lineidmass)." AND fk_powerplant = ".((int) $object->id)." AND entity = ".((int) $conf->entity);
			$resold = $db->query($sqlold);

			$sqlnew = 'INSERT INTO '.$db->prefix()."powerplantpv_powerplantcomp(fk_powerplant, fk_product, fk_status, qty, serial_number, commissioning_date, entity)";
			$sqlnew .= " VALUES (".((int) $object->id).", ".((int) $productid).", ".((int) $statusval).", 1, '".$serial."', '".$db->escape($dateval)."', ".((int) $conf->entity).")";
			$resnew = $db->query($sqlnew);
			if (!$resold || !$resnew) {
				$error++;
				setEventMessages($db->lasterror(), null, 'errors');
				break;
			}
			$nbreplaced++;
		}
		if ($error) {
			$db->rollback();
		} else {
			$db->commit();
			if ($nbreplaced > 0) {
				$recalculateinstalledpower = 1;
				$label = $langs->transnoentities('PowerPlantCompositionMassReplaced', $object->ref);
				$message = $langs->transnoentities('PowerPlantCompositionMassReplacedDesc', $nbreplaced);
				powerplantTriggerAgendaEvent($object, $user, 'POWERPLANTPV_POWERPLANT_COMP_REPLACE', $label, $message);
			}
		}
	}
	$action = 'view';
}

if ($action === 'confirmreplacecomposition' && $canmanagecomposition && $lineid > 0) {
	if (!powerplantpv_check_token()) {
		accessforbidden();
	}

	$fk_product_replace = GETPOSTINT('fk_product_replace');
	$serial_number_replace = GETPOST('serial_number_replace', 'alphanohtml');
	$fk_status_replace = GETPOST('fk_status_replace', 'alphanohtml');
	$fk_status_replace = ($fk_status_replace === '' ? 4 : (int) $fk_status_replace);
	if (!array_key_exists((int) $fk_status_replace, $componentstatus)) {
		$fk_status_replace = 4;
	}
	$commissioning_date_replace = GETPOST('commissioning_date_replace', 'alphanohtml');
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $commissioning_date_replace)) {
		$commissioning_date_replace = dol_print_date(dol_now(), '%Y-%m-%d');
	}

	$sqlcheckline = "SELECT rowid FROM ".$db->prefix()."powerplantpv_powerplantcomp";
	$sqlcheckline .= " WHERE rowid = ".((int) $lineid)." AND fk_powerplant = ".((int) $object->id)." AND entity = ".((int) $conf->entity);
	$rescheckline = $db->query($sqlcheckline);
	if ($rescheckline && $db->num_rows($rescheckline) > 0 && $fk_product_replace > 0) {
		$oldline = powerplantCompositionFetchLine($object->id, $lineid);
		$db->begin();

		$sqlreplaceold = "UPDATE ".$db->prefix()."powerplantpv_powerplantcomp";
		$sqlreplaceold .= " SET fk_status = 6";
		$sqlreplaceold .= " WHERE rowid = ".((int) $lineid)." AND fk_powerplant = ".((int) $object->id)." AND entity = ".((int) $conf->entity);
		$resreplaceold = $db->query($sqlreplaceold);

		$sqladdnew = 'INSERT INTO '.$db->prefix()."powerplantpv_powerplantcomp(fk_powerplant, fk_product, fk_status, qty, serial_number, commissioning_date, entity)";
		$sqladdnew .= " VALUES (".((int) $object->id).", ".((int) $fk_product_replace).", ".((int) $fk_status_replace).", 1, '".$db->escape($serial_number_replace)."', '".$db->escape($commissioning_date_replace)."', ".((int) $conf->entity).")";
		$resaddnew = $db->query($sqladdnew);

		if ($resreplaceold && $resaddnew) {
			$newlineid = $db->last_insert_id($db->prefix()."powerplantpv_powerplantcomp", "rowid");
			$db->commit();
			$recalculateinstalledpower = 1;
			$newline = powerplantCompositionFetchLine($object->id, (int) $newlineid);
			$oldlabel = ($oldline ? powerplantCompositionLineLabel($oldline) : '#'.((int) $lineid));
			$newlabel = ($newline ? powerplantCompositionLineLabel($newline) : '#'.((int) $newlineid));
			$label = $langs->transnoentities('PowerPlantCompositionLineReplaced', $oldlabel);
			$message = $langs->transnoentities('PowerPlantCompositionLineReplacedDesc', $oldlabel, $newlabel);
			powerplantTriggerAgendaEvent($object, $user, 'POWERPLANTPV_POWERPLANT_COMP_REPLACE', $label, $message);
		} else {
			$db->rollback();
		}
	}

	$action = 'view';
}

if (!empty($recalculateinstalledpower)) {
	$resultrecalculate = powerplantRecalculateInstalledPower($object);
	if ($resultrecalculate < 0) {
		setEventMessages(!empty($object->error) ? $object->error : $langs->trans('PowerPlantInstalledPowerRecalculationError'), $object->errors, 'errors');
	}
}

$sqlwhere = ' WHERE c.fk_powerplant = '.((int) $object->id).' AND c.entity = '.((int) $conf->entity);
if ($search_ref !== '') {
	$sqlwhere .= " AND p.ref LIKE '%".$db->escape($search_ref)."%'";
}
if ($search_label !== '') {
	$sqlwhere .= " AND p.label LIKE '%".$db->escape($search_label)."%'";
}
if ($search_nature > 0) {
	$sqlwhere .= ' AND pe.categorie_photovoltaique = '.((int) $search_nature);
}
if ($search_status !== '') {
	$sqlwhere .= ' AND c.fk_status = '.((int) $search_status);
}
if ($search_serial !== '') {
	$sqlwhere .= " AND c.serial_number LIKE '%".$db->escape($search_serial)."%'";
}
if ($search_commissioning !== '') {
	$sqlwhere .= " AND c.commissioning_date = '".$db->escape($search_commissioning)."'";
}

$sqlcount = 'SELECT COUNT(c.rowid) as nb';
$sqlcount .= ' FROM '.$db->prefix().'powerplantpv_powerplantcomp as c';
$sqlcount .= ' JOIN '.$db->prefix().'product as p ON p.rowid = c.fk_product';
$sqlcount .= ' LEFT JOIN '.$db->prefix().'product_extrafields as pe ON pe.fk_object = p.rowid';
$sqlcount .= $sqlwhere;
$rescount = $db->query($sqlcount);
$nbtotalofrecords = 0;
if ($rescount) {
	$objcount = $db->fetch_object($rescount);
	$nbtotalofrecords = (int) $objcount->nb;
}

$sql = 'SELECT c.rowid, c.fk_status, c.serial_number, c.commissioning_date, p.rowid as fk_product, p.ref as product_ref, p.label as product_label, cpv.label as category_label, pe.categorie_photovoltaique';
$sql .= ' FROM '.$db->prefix().'powerplantpv_powerplantcomp as c';
$sql .= ' JOIN '.$db->prefix().'product as p ON p.rowid = c.fk_product';
$sql .= ' LEFT JOIN '.$db->prefix().'product_extrafields as pe ON pe.fk_object = p.rowid';
$sql .= ' LEFT JOIN '.$db->prefix().'c_powerplantpv_categorypv as cpv ON cpv.rowid = pe.categorie_photovoltaique';
$sql .= $sqlwhere;
$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);
$resql = $db->query($sql);

$title = $langs->trans('PowerPlant').' - '.$langs->trans('PowerPlantMaterialComposition');
llxHeader('', $title, '');

if ($id > 0 || !empty($ref)) {
	$object->fetch_thirdparty();

	$head = powerplantPrepareHead($object);
	print dol_get_fiche_head($head, 'composition', $langs->trans('PowerPlant'), -1, $object->picto);

	$linkback = powerplantGetBackToListLink($object);
	$morehtmlref = powerplantBuildBannerMoreHtml($object, $permissiontoadd, $action);
	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';

	$param = 'id='.$object->id;
	if ($search_ref !== '') {
		$param .= '&search_ref='.urlencode($search_ref);
	}
	if ($search_label !== '') {
		$param .= '&search_label='.urlencode($search_label);
	}
	if ($search_nature > 0) {
		$param .= '&search_nature='.$search_nature;
	}
	if ($search_status !== '') {
		$param .= '&search_status='.$search_status;
	}
	if ($search_serial !== '') {
		$param .= '&search_serial='.urlencode($search_serial);
	}
	if ($search_commissioning !== '') {
		$param .= '&search_commissioning='.urlencode($search_commissioning);
	}

	$massactionbutton = '';
	$newcardbutton = '';
	$arrayofmassactions = array();
	if ($canmanagecomposition) {
		$arrayofmassactions['massreplace'] = img_picto('', 'refresh', 'class="pictofixedwidth"').$langs->trans('PowerPlantMassReplaceSelected');
		$arrayofmassactions['massupdatecommissioning'] = img_picto('', 'calendar', 'class="pictofixedwidth"').$langs->trans('PowerPlantMassUpdateCommissioningDate');
		$arrayofmassactions['massupdatestatus'] = img_picto('', 'status', 'class="pictofixedwidth"').$langs->trans('PowerPlantMassUpdateStatus');
	}
	if ($canedit) {
		$arrayofmassactions['massdelete'] = img_picto('', 'delete', 'class="pictofixedwidth"').$langs->trans('Delete');
		$newcardbutton = dolGetButtonTitle($langs->trans('Add'), '', 'fa fa-plus-circle', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=addcomposition&token='.newToken());
	}
	if (!empty($arrayofmassactions)) {
		$massactionbutton = $form->selectMassAction('', $arrayofmassactions);
	}
	$showmassactions = !empty($arrayofmassactions);

	$productsforcomposition = array();
	$sqlproducts = "SELECT p.rowid, p.ref, p.label";
	$sqlproducts .= " FROM ".$db->prefix()."product as p";
	$sqlproducts .= " INNER JOIN ".$db->prefix()."product_extrafields as pe ON pe.fk_object = p.rowid";
	$sqlproducts .= " WHERE pe.categorie_photovoltaique IS NOT NULL AND pe.categorie_photovoltaique <> ''";
	$sqlproducts .= " AND p.entity IN (".getEntity('product').")";
	$sqlproducts .= " ORDER BY p.ref ASC";
	$resproducts = $db->query($sqlproducts);
	if ($resproducts) {
		while ($objproduct = $db->fetch_object($resproducts)) {
			$productlabel = $objproduct->ref;
			if (!empty($objproduct->label)) {
				$productlabel .= ' - '.$objproduct->label;
			}
			$productsforcomposition[(int) $objproduct->rowid] = $productlabel;
		}
	}

		print '<div id="dialog-addcomposition" class="hideobject">';
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="createcomposition">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans('Product').'</td>';
		print '<td>'.$langs->trans('PVQuantity').'</td>';
		print '<td>'.$langs->trans('PowerPlantStatus').'</td>';
		print '<td>'.$langs->trans('PowerPlantCommissioningDate').'</td>';
		print '</tr>';
		print '<tr>';
		print '<td>'.$form->selectarray('fk_product', $productsforcomposition, 0, 0, 0, '', 0, 0, 0, '', 'flat minwidth200imp maxwidth300').'</td>';
		print '<td><input type="number" class="flat width50" min="1" name="qty" value="1"></td>';
		print '<td>'.$form->selectarray('fk_status', $componentstatus, 4, 0, 0, '', 0, 0, 0, '', 'flat minwidth100').'</td>';
		print '<td><input type="date" class="flat width125" name="commissioning_date" value=""></td>';
		print '</tr>';
		print '</table>';
		print '<div class="center">';
		print '<input type="submit" class="button button-add" value="'.$langs->trans('Add').'">';
		print ' <input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'">';
		print '</div>';
		print '</form>';
		print '</div>';

		print '<script nonce="'.getNonce().'">';
		print 'jQuery(function(){';
		print 'jQuery("#dialog-addcomposition").dialog({autoOpen:false,modal:true,width:900,title:"'.dol_escape_js($langs->transnoentitiesnoconv('PowerPlantMaterialComposition')).'"});';
		print 'if (jQuery("#fk_product").length) {';
		print 'jQuery("#fk_product").addClass("flat minwidth200imp maxwidth300");';
		print 'jQuery("#fk_product").select2({';
		print 'dir:"ltr",';
		print 'width:"resolve",';
		print 'minimumInputLength:0,';
		print 'language:(typeof select2arrayoflanguage === "undefined") ? "en" : select2arrayoflanguage,';
		print 'theme:"default",';
		print 'containerCssClass:":all:",';
		print 'selectionCssClass:":all:",';
		print 'dropdownCssClass:"ui-dialog",';
		print 'matcher:function(params,data){';
		print 'if (jQuery.trim(params.term) === "") { return data; }';
		print 'var term = params.term.toLowerCase();';
		print 'var text = (data.text || "").toLowerCase();';
		print 'var keywords = term.split(" ");';
		print 'for (var i = 0; i < keywords.length; i++) { if (text.indexOf(keywords[i]) === -1) { return null; } }';
		print 'return data;';
		print '},';
		print 'templateResult:function(data,container){';
		print 'if (data.element) { jQuery(container).addClass(jQuery(data.element).attr("class")); }';
		print 'if (data.id == "-1" && jQuery(data.element).attr("data-html") == undefined) { return "&nbsp;"; }';
		print 'if (jQuery(data.element).attr("data-html") != undefined && typeof htmlEntityDecodeJs === "function") { return htmlEntityDecodeJs(jQuery(data.element).attr("data-html")); }';
		print 'return data.text;';
		print '},';
		print 'templateSelection:function(selection){ if (selection.id == "-1") return "<span class=\"placeholder\">"+selection.text+"</span>"; return selection.text; },';
		print 'escapeMarkup:function(markup){ return markup; }';
		print '});';
		print '}';
		print 'if (jQuery("#fk_status").length) {';
		print 'jQuery("#fk_status").select2({';
		print 'dir:"ltr",';
		print 'width:"resolve",';
		print 'minimumResultsForSearch:0,';
		print 'language:(typeof select2arrayoflanguage === "undefined") ? "en" : select2arrayoflanguage,';
		print 'theme:"default",';
		print 'dropdownCssClass:"ui-dialog"';
		print '});';
		print '}';
		if ($openaddmodal) {
			print 'jQuery("#dialog-addcomposition").dialog("open");';
		}
		print '});';
		print '</script>';

		if ($canmanagecomposition && $action === 'editline' && $lineid > 0) {
			$sqledit = "SELECT rowid, fk_status, serial_number, commissioning_date FROM ".$db->prefix()."powerplantpv_powerplantcomp";
			$sqledit .= " WHERE rowid = ".((int) $lineid)." AND fk_powerplant = ".((int) $object->id)." AND entity = ".((int) $conf->entity);
			$resedit = $db->query($sqledit);
			if ($resedit && ($objedit = $db->fetch_object($resedit))) {
				print '<div id="dialog-editcomposition" class="hideobject">';
				print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="updateline">';
				print '<input type="hidden" name="lineid" value="'.((int) $objedit->rowid).'">';
				print '<table class="noborder centpercent">';
				print '<tr class="liste_titre">';
				print '<td>'.$langs->trans('PowerPlantSerialNumber').'</td>';
				print '<td>'.$langs->trans('PowerPlantStatus').'</td>';
				print '<td>'.$langs->trans('PowerPlantCommissioningDate').'</td>';
				print '</tr>';
				print '<tr>';
				print '<td><input type="text" class="flat minwidth100" name="serial_number" value="'.dol_escape_htmltag($objedit->serial_number).'"></td>';
				print '<td>'.$form->selectarray('fk_status_edit', $componentstatus, ($objedit->fk_status !== null ? (int) $objedit->fk_status : 4), 0, 0, '', 0, 0, 0, '', 'flat minwidth100').'</td>';
				print '<td><input type="date" class="flat width125" name="commissioning_date" value="'.($objedit->commissioning_date ? dol_print_date($db->jdate($objedit->commissioning_date), '%Y-%m-%d') : '').'"></td>';
				print '</tr>';
				print '</table>';
				print '<div class="center">';
				print '<input type="submit" class="button button-edit" value="'.$langs->trans('Modify').'">';
				print ' <input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'">';
				print '</div>';
				print '</form>';
				print '</div>';

				print '<script nonce="'.getNonce().'">';
				print 'jQuery(function(){';
				print 'jQuery("#dialog-editcomposition").dialog({autoOpen:true,modal:true,width:700,title:"'.dol_escape_js($langs->transnoentitiesnoconv('Modify')).'"});';
				print 'if (jQuery("#fk_status_edit").length) {';
				print 'jQuery("#fk_status_edit").select2({';
				print 'dir:"ltr",';
				print 'width:"resolve",';
				print 'minimumResultsForSearch:0,';
				print 'language:(typeof select2arrayoflanguage === "undefined") ? "en" : select2arrayoflanguage,';
				print 'theme:"default",';
				print 'dropdownCssClass:"ui-dialog"';
				print '});';
				print '}';
				print '});';
				print '</script>';
			}
		}

		if ($canmanagecomposition && $action === 'replaceline' && $lineid > 0) {
			$sqlreplace = "SELECT rowid, fk_product, serial_number FROM ".$db->prefix()."powerplantpv_powerplantcomp";
			$sqlreplace .= " WHERE rowid = ".((int) $lineid)." AND fk_powerplant = ".((int) $object->id)." AND entity = ".((int) $conf->entity);
			$resreplace = $db->query($sqlreplace);
			if ($resreplace && ($objreplace = $db->fetch_object($resreplace))) {
				print '<div id="dialog-replacecomposition" class="hideobject">';
				print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="confirmreplacecomposition">';
				print '<input type="hidden" name="lineid" value="'.((int) $objreplace->rowid).'">';
				print '<table class="noborder">';
				print '<tr class="liste_titre">';
				print '<td>'.$langs->trans('Product').'</td>';
				print '<td>'.$langs->trans('PowerPlantSerialNumber').'</td>';
				print '<td>'.$langs->trans('PowerPlantCommissioningDate').'</td>';
				print '<td>'.$langs->trans('PowerPlantStatus').'</td>';
				print '</tr>';
				print '<tr>';
				print '<td>'.$form->selectarray('fk_product_replace', $productsforcomposition, (int) $objreplace->fk_product, 0, 0, '', 0, 0, 0, '', 'flat').'</td>';
				print '<td><input type="text" class="flat minwidth100" name="serial_number_replace" value=""></td>';
				print '<td><input type="date" class="flat width125" name="commissioning_date_replace" value="'.dol_print_date(dol_now(), '%Y-%m-%d').'"></td>';
				print '<td>'.$form->selectarray('fk_status_replace', $componentstatus, 4, 0, 0, '', 0, 0, 0, '', 'flat minwidth100').'</td>';
				print '</tr>';
				print '</table>';
				print '<div class="center">';
				print '<input type="submit" class="button button-edit" value="'.$langs->trans('PowerPlantReplace').'">';
				print ' <input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'">';
				print '</div>';
				print '</form>';
				print '</div>';

				print '<script nonce="'.getNonce().'">';
				print 'jQuery(function(){';
				print 'jQuery("#dialog-replacecomposition").dialog({autoOpen:true,modal:true,width:"auto",title:"'.dol_escape_js($langs->transnoentitiesnoconv('PowerPlantReplacement')).'"});';
				print 'if (jQuery("#fk_product_replace").length) {';
				print 'jQuery("#fk_product_replace").select2({';
				print 'dir:"ltr",';
				print 'width:"75px",';
				print 'minimumInputLength:0,';
				print 'language:(typeof select2arrayoflanguage === "undefined") ? "en" : select2arrayoflanguage,';
				print 'theme:"default",';
				print 'dropdownCssClass:"ui-dialog"';
				print '});';
				print '}';
				print 'if (jQuery("#fk_status_replace").length) {';
				print 'jQuery("#fk_status_replace").select2({';
				print 'dir:"ltr",';
				print 'width:"resolve",';
				print 'minimumResultsForSearch:0,';
				print 'language:(typeof select2arrayoflanguage === "undefined") ? "en" : select2arrayoflanguage,';
				print 'theme:"default",';
				print 'dropdownCssClass:"ui-dialog"';
				print '});';
				print '}';
				print '});';
				print '</script>';
			}
		}

		if ($canedit && $action === 'massdelete' && !empty($massselectedids)) {
			print '<div id="dialog-massdeletecomposition" class="hideobject">';
			print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="view">';
			print '<input type="hidden" name="massaction" value="massdelete">';
			print '<input type="hidden" name="confirmmassaction" value="1">';
			print '<input type="hidden" name="massaction_confirmed" value="1">';
			foreach ($massselectedids as $selectedid) {
				print '<input type="hidden" name="toselect[]" value="'.((int) $selectedid).'">';
			}
			print '<p>'.$langs->trans('ConfirmDelete').'</p>';
			print '<div class="center">';
			print '<input type="submit" class="button button-delete" value="'.$langs->trans('Delete').'">';
			print ' <input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'">';
			print '</div>';
			print '</form>';
			print '</div>';
			print '<script nonce="'.getNonce().'">jQuery(function(){jQuery("#dialog-massdeletecomposition").dialog({autoOpen:true,modal:true,width:550,title:"'.dol_escape_js($langs->transnoentitiesnoconv('Delete')).'"});});</script>';
		}

		if ($canmanagecomposition && $action === 'massupdatecommissioning' && !empty($massselectedids)) {
			print '<div id="dialog-massdatecomposition" class="hideobject">';
			print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="view">';
			print '<input type="hidden" name="massaction" value="massupdatecommissioning">';
			print '<input type="hidden" name="confirmmassaction" value="1">';
			print '<input type="hidden" name="massaction_confirmed" value="1">';
			foreach ($massselectedids as $selectedid) {
				print '<input type="hidden" name="toselect[]" value="'.((int) $selectedid).'">';
			}
			print '<table class="noborder centpercent">';
			print '<tr><td>'.$langs->trans('PowerPlantCommissioningDate').'</td><td><input type="date" class="flat width125" name="commissioning_date_mass" value="'.dol_print_date(dol_now(), '%Y-%m-%d').'"></td></tr>';
			print '<tr><td>'.$langs->trans('PowerPlantApplyToAll').'</td><td><input type="checkbox" class="flat" name="apply_to_all_date" value="1" checked></td></tr>';
			print '</table>';
			print '<div class="center">';
			print '<input type="submit" class="button button-edit" value="'.$langs->trans('Modify').'">';
			print ' <input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'">';
			print '</div>';
			print '</form>';
			print '</div>';
			print '<script nonce="'.getNonce().'">jQuery(function(){jQuery("#dialog-massdatecomposition").dialog({autoOpen:true,modal:true,width:650,title:"'.dol_escape_js($langs->transnoentitiesnoconv('PowerPlantMassUpdateCommissioningDate')).'"});});</script>';
		}

		if ($canmanagecomposition && $action === 'massupdatestatus' && !empty($massselectedids)) {
			print '<div id="dialog-massstatuscomposition" class="hideobject">';
			print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="view">';
			print '<input type="hidden" name="massaction" value="massupdatestatus">';
			print '<input type="hidden" name="confirmmassaction" value="1">';
			print '<input type="hidden" name="massaction_confirmed" value="1">';
			foreach ($massselectedids as $selectedid) {
				print '<input type="hidden" name="toselect[]" value="'.((int) $selectedid).'">';
			}
			print '<table class="noborder centpercent">';
			print '<tr class="liste_titre"><td>'.$langs->trans('Product').'</td><td>'.$langs->trans('PowerPlantSerialNumber').'</td><td>'.$langs->trans('PowerPlantCommissioningDate').'</td><td>'.$langs->trans('PowerPlantStatus').'</td></tr>';
			if (!empty($masslines)) {
				foreach ($masslines as $idx => $massline) {
					$productlabel = isset($productsforcomposition[(int) $massline->fk_product]) ? $productsforcomposition[(int) $massline->fk_product] : '#'.((int) $massline->fk_product);
					print '<tr>';
					print '<td><input type="hidden" name="lineid_mass_status['.$idx.']" value="'.((int) $massline->rowid).'">'.dol_escape_htmltag($productlabel).'</td>';
					print '<td>'.dol_escape_htmltag($massline->serial_number).'</td>';
					print '<td>'.(!empty($massline->commissioning_date) ? dol_print_date($db->jdate($massline->commissioning_date), 'day') : '').'</td>';
					print '<td>'.$form->selectarray('status_mass_line['.$idx.']', $componentstatus, ($massline->fk_status !== null ? (int) $massline->fk_status : 4), 0, 0, '', 0, 0, 0, '', 'flat minwidth100 massstatus-line-select').'</td>';
					print '</tr>';
				}
			} else {
				print '<tr><td colspan="4"><span class="opacitymedium">'.$langs->trans('None').'</span></td></tr>';
			}
			print '</table>';
			print '<div class="center">';
			print '<input type="submit" class="button button-edit" value="'.$langs->trans('Modify').'">';
			print ' <input type="button" class="button button-cancel" id="massstatus-cancel-btn" value="'.$langs->trans('Cancel').'">';
			print '</div>';
			print '</form>';
			print '</div>';
			print '<script nonce="'.getNonce().'">';
			print 'jQuery(function(){';
			print 'jQuery("#dialog-massstatuscomposition").dialog({';
			print 'autoOpen:true,';
			print 'modal:true,';
			print 'width:980,';
			print 'title:"'.dol_escape_js($langs->transnoentitiesnoconv('PowerPlantMassUpdateStatus')).'",';
			print 'open:function(){';
			print 'jQuery(this).find(".massstatus-line-select").each(function(){';
			print 'if (jQuery(this).hasClass("select2-hidden-accessible")) { jQuery(this).select2("destroy"); }';
			print 'jQuery(this).select2({width:"resolve",minimumResultsForSearch:0,dropdownCssClass:"ui-dialog"});';
			print '});';
			print '}';
			print '});';
			print 'jQuery("#massstatus-cancel-btn").on("click", function(){';
			print 'jQuery("#dialog-massstatuscomposition").dialog("close");';
			print '});';
			print '});';
			print '</script>';
		}

		if ($canmanagecomposition && $action === 'massreplace' && !empty($massselectedids)) {
			print '<div id="dialog-massreplacecomposition" class="hideobject">';
			print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="view">';
			print '<input type="hidden" name="massaction" value="massreplace">';
			print '<input type="hidden" name="confirmmassaction" value="1">';
			print '<input type="hidden" name="massaction_confirmed" value="1">';
			foreach ($massselectedids as $selectedid) {
				print '<input type="hidden" name="toselect[]" value="'.((int) $selectedid).'">';
			}
			print '<table class="noborder centpercent">';
			print '<tr class="liste_titre"><td>'.$langs->trans('Product').'</td><td>'.$langs->trans('PowerPlantSerialNumber').'</td><td>'.$langs->trans('PowerPlantCommissioningDate').'</td><td>'.$langs->trans('PowerPlantStatus').'</td></tr>';
			if (!empty($masslines)) {
				foreach ($masslines as $idx => $massline) {
					print '<tr>';
					print '<td><input type="hidden" name="lineid_mass_replace['.$idx.']" value="'.((int) $massline->rowid).'">'.$form->selectarray('fk_product_mass_replace['.$idx.']', $productsforcomposition, (int) $massline->fk_product, 0, 0, '', 0, 0, 0, '', 'flat minwidth100imp maxwidth200 massreplace-product-select').'</td>';
					print '<td><input type="text" class="flat minwidth100" name="serial_number_mass_replace['.$idx.']" value=""></td>';
					print '<td><input type="date" class="flat width125" name="commissioning_date_mass_replace['.$idx.']" value="'.dol_print_date(dol_now(), '%Y-%m-%d').'"></td>';
					print '<td>'.$form->selectarray('fk_status_mass_replace['.$idx.']', $componentstatus, 4, 0, 0, '', 0, 0, 0, '', 'flat minwidth100 massreplace-status-select').'</td>';
					print '</tr>';
				}
			} else {
				print '<tr><td colspan="4"><span class="opacitymedium">'.$langs->trans('None').'</span></td></tr>';
			}
			print '</table>';
			print '<div class="center">';
			print '<input type="submit" class="button button-edit" value="'.$langs->trans('PowerPlantReplace').'">';
			print ' <input type="button" class="button button-cancel" id="massreplace-cancel-btn" value="'.$langs->trans('Cancel').'">';
			print '</div>';
			print '</form>';
			print '</div>';
			print '<script nonce="'.getNonce().'">';
			print 'jQuery(function(){';
			print 'jQuery("#dialog-massreplacecomposition").dialog({';
			print 'autoOpen:true,';
			print 'modal:true,';
			print 'width:980,';
			print 'title:"'.dol_escape_js($langs->transnoentitiesnoconv('PowerPlantMassReplaceSelected')).'",';
			print 'open:function(){';
			print 'jQuery(this).find(".massreplace-product-select").each(function(){';
			print 'if (jQuery(this).hasClass("select2-hidden-accessible")) { jQuery(this).select2("destroy"); }';
			print 'jQuery(this).select2({width:"resolve",dropdownCssClass:"ui-dialog"});';
			print '});';
			print 'jQuery(this).find(".massreplace-status-select").each(function(){';
			print 'if (jQuery(this).hasClass("select2-hidden-accessible")) { jQuery(this).select2("destroy"); }';
			print 'jQuery(this).select2({width:"resolve",minimumResultsForSearch:0,dropdownCssClass:"ui-dialog"});';
			print '});';
			print '}';
			print '});';
			print 'jQuery("#massreplace-cancel-btn").on("click", function(){';
			print 'jQuery("#dialog-massreplacecomposition").dialog("close");';
			print '});';
			print '});';
			print '</script>';
		}

		print '<form method="POST" id="searchFormList" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
		print '<input type="hidden" name="action" value="list">';
		print '<input type="hidden" name="id" value="'.$object->id.'">';
		print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
		print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
		print '<input type="hidden" name="page" value="'.((int) $page).'">';
		print '<input type="hidden" name="page_y" value="">';
		print '<input type="hidden" name="limit" value="'.((int) $limit).'">';
		print_barre_liste($langs->trans('PowerPlantMaterialComposition'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, $massactionbutton, $nbtotalofrecords, $nbtotalofrecords, 'product', 0, $newcardbutton, '', $limit, 0, 0, 1);
		print '<div class="div-table-responsive">';
		print '<table class="tagtable liste centpercent">';

			print '<tr class="liste_titre_filter">';
			print '<td class="liste_titre center maxwidthsearch">';
			print '<div class="nowraponall">';
			print '<button type="submit" class="liste_titre button_search reposition" name="button_search_x" value="x"><span class="fas fa-search"></span></button>';
			print '<button type="submit" class="liste_titre button_removefilter reposition" name="button_removefilter_x" value="x"><span class="fas fa-times"></span></button>';
			print '</div>';
			print '</td>';
			print '<td class="liste_titre left"><input type="text" class="flat width75" name="search_ref" value="'.dol_escape_htmltag($search_ref).'"></td>';
			print '<td class="liste_titre left"><input type="text" class="flat width100" name="search_label" value="'.dol_escape_htmltag($search_label).'"></td>';
			print '<td class="liste_titre left">'.$form->selectarray('search_nature', array('' => '') + $categories, ($search_nature > 0 ? $search_nature : ''), 0).'</td>';
			print '<td class="liste_titre left"><input type="text" class="flat width100" name="search_serial" value="'.dol_escape_htmltag($search_serial).'"></td>';
			print '<td class="liste_titre left"><input type="date" class="flat width100" name="search_commissioning" value="'.dol_escape_htmltag($search_commissioning).'"></td>';
			print '<td class="liste_titre left">'.$form->selectarray('search_status', array('' => '') + $componentstatus, ($search_status !== '' ? (int) $search_status : ''), 0, 0, '', 0, 0, 0, '', 'flat minwidth100').'</td>';
			print '<td class="liste_titre"></td>';
			print '</tr>';

		print '<tr class="liste_titre">';
		print_liste_field_titre(($showmassactions ? $form->showCheckAddButtons('checkforselect', 1) : ''), $_SERVER['PHP_SELF'], '', '', $param, 'class="center"', $sortfield, $sortorder);
		print_liste_field_titre($langs->trans('Ref'), $_SERVER['PHP_SELF'], 'p.ref', '', $param, '', $sortfield, $sortorder);
		print_liste_field_titre($langs->trans('Label'), $_SERVER['PHP_SELF'], 'p.label', '', $param, '', $sortfield, $sortorder);
		print_liste_field_titre($langs->trans('Category'), $_SERVER['PHP_SELF'], 'cpv.label', '', $param, '', $sortfield, $sortorder);
		print_liste_field_titre($langs->trans('PowerPlantSerialNumber'), $_SERVER['PHP_SELF'], 'c.serial_number', '', $param, '', $sortfield, $sortorder);
		print_liste_field_titre($langs->trans('PowerPlantCommissioningDate'), $_SERVER['PHP_SELF'], 'c.commissioning_date', '', $param, '', $sortfield, $sortorder);
		print_liste_field_titre($langs->trans('PowerPlantStatus'), $_SERVER['PHP_SELF'], 'c.fk_status', '', $param, '', $sortfield, $sortorder);
		print_liste_field_titre('', $_SERVER['PHP_SELF'], '', '', $param, 'class="center"', $sortfield, $sortorder);
		print '</tr>';

	if ($resql && $db->num_rows($resql) > 0) {
		$num = min($db->num_rows($resql), $limit);
		$i = 0;
		while ($i < $num) {
			$objline = $db->fetch_object($resql);
			print '<tr class="oddeven">';
			print '<td class="center">';
			if ($showmassactions) {
				print '<input class="flat checkforselect" type="checkbox" name="toselect[]" value="'.((int) $objline->rowid).'">';
			}
			print '</td>';
			$productstatic = new Product($db);
			$productstatic->id = (int) $objline->fk_product;
			$productstatic->ref = $objline->product_ref;
			$productstatic->label = $objline->product_label;
			print '<td>'.$productstatic->getNomUrl(1).'</td>';
			print '<td>'.dol_escape_htmltag($objline->product_label).'</td>';
			print '<td>'.dol_escape_htmltag($objline->category_label).'</td>';
			print '<td>'.dol_escape_htmltag($objline->serial_number).'</td>';
			print '<td>'.(!empty($objline->commissioning_date) ? dol_print_date($db->jdate($objline->commissioning_date), 'day') : '').'</td>';
			print '<td>';
			if ($objline->fk_status !== null && $objline->fk_status !== '') {
				$statuskey = (int) $objline->fk_status;
				$statuslabel = isset($componentstatus[$statuskey]) ? $componentstatus[$statuskey] : $statuskey;
				print '<span class="badge badge-status'.$statuskey.'">'.dol_escape_htmltag($statuslabel).'</span>';
			}
			print '</td>';
			print '<td class="center">';
			if ($canmanagecomposition) {
				print '<a class="editfielda reposition" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=editline&token='.newToken().'&lineid='.(int) $objline->rowid.'">'.img_edit().'</a>';
				print '<a class="reposition marginleftonly marginrightonly" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=replaceline&lineid='.(int) $objline->rowid.'&token='.newToken().'" title="'.$langs->trans('PowerPlantReplace').'"><span class="fas fa-exchange-alt"></span></a>';
			}
			if ($canedit) {
				print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=delcomposition&lineid='.(int) $objline->rowid.'&token='.newToken().'">'.img_delete().'</a>';
			}
			print '</td>';
			print '</tr>';
			$i++;
		}
	} else {
		print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('None').'</span></td></tr>';
	}

	print '</table>';
	print '</div>';
	print '</form>';
	print '</div>';
	print dol_get_fiche_end();
}

llxFooter();
$db->close();
