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

if (empty($sortfield)) {
	$sortfield = 'p.ref';
}
if (empty($sortorder)) {
	$sortorder = 'ASC';
}

$search_ref = trim(GETPOST('search_ref', 'alphanohtml'));
$search_label = trim(GETPOST('search_label', 'alphanohtml'));
$search_nature = GETPOSTINT('search_nature');
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
	$search_serial = '';
	$search_commissioning = '';
}

$canedit = ($permissiontoadd && (int) $object->status === (int) $object::STATUS_DRAFT);
$showaddform = ($canedit && $action === 'addcomposition');
$openaddmodal = 0;
if ($showaddform) {
	$openaddmodal = 1;
}

if ($action === 'createcomposition' && $canedit) {
	if (!powerplantpv_check_token()) {
		accessforbidden();
	}

	$fk_product = GETPOSTINT('fk_product');
	$qty = GETPOSTINT('qty');
	if ($qty < 1) {
		$qty = 1;
	}

	if ($fk_product > 0) {
		$i = 0;
		while ($i < $qty) {
			$sql = 'INSERT INTO '.$db->prefix()."powerplantpv_powerplantcomp(fk_powerplant, fk_product, qty, serial_number, commissioning_date, entity)";
			$sql .= ' VALUES ('.((int) $object->id).', '.((int) $fk_product).', 1, \'\', NULL, '.((int) $conf->entity).')';
			$db->query($sql);
			$i++;
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
	$db->query($sql);
}

if ($massaction === 'massdelete' && $canedit && is_array($toselect) && count($toselect) > 0) {
	if (!powerplantpv_check_token()) {
		accessforbidden();
	}

	$idstodelete = array_map('intval', $toselect);
	$idstodelete = array_filter($idstodelete, function ($v) {
		return ($v > 0);
	});
	if (!empty($idstodelete)) {
		$sql = 'DELETE FROM '.$db->prefix().'powerplantpv_powerplantcomp';
		$sql .= ' WHERE fk_powerplant = '.((int) $object->id);
		$sql .= ' AND entity = '.((int) $conf->entity);
		$sql .= ' AND rowid IN ('.implode(',', $idstodelete).')';
		$db->query($sql);
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

$sql = 'SELECT c.rowid, c.serial_number, c.commissioning_date, p.ref as product_ref, p.label as product_label, cpv.label as category_label, pe.categorie_photovoltaique';
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

	$linkback = '<a href="'.dol_buildpath('/powerplantpv/powerplant_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

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
	if ($search_serial !== '') {
		$param .= '&search_serial='.urlencode($search_serial);
	}
	if ($search_commissioning !== '') {
		$param .= '&search_commissioning='.urlencode($search_commissioning);
	}

		$massactionbutton = '';
		$newcardbutton = '';
		if ($canedit) {
			$arrayofmassactions = array(
				'massdelete' => img_picto('', 'delete', 'class="pictofixedwidth"').$langs->trans('Delete')
			);
			$massactionbutton = $form->selectMassAction('', $arrayofmassactions);
			$newcardbutton = dolGetButtonTitle($langs->trans('Add'), '', 'fa fa-plus-circle', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=addcomposition&token='.newToken());
		}

		print_barre_liste($langs->trans('PowerPlantMaterialComposition'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, $massactionbutton, $nbtotalofrecords, $nbtotalofrecords, 'product', 0, $newcardbutton, '', $limit, 0, 0, 1);

		$filterforproducts = " AND EXISTS (SELECT 1 FROM ".$db->prefix()."product_extrafields pe WHERE pe.fk_object = p.rowid AND pe.categorie_photovoltaique IS NOT NULL AND pe.categorie_photovoltaique <> '')";
		print '<div id="dialog-addcomposition" class="hideobject">';
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="createcomposition">';
		print '<table class="noborder centpercent">';
		print '<tr><td class="titlefieldcreate">'.$langs->trans('Product').'</td><td>'.$form->select_produits(0, 'fk_product', '', 0, 0, -1, 2, '', 0, array(), '', 1, 1, '', '', 0, '', $filterforproducts).'</td></tr>';
		print '<tr><td class="titlefieldcreate">'.$langs->trans('PVQuantity').'</td><td><input type="number" class="flat width50" min="1" name="qty" value="1"></td></tr>';
		print '</table>';
		print '<div class="center">';
		print '<input type="submit" class="button button-add" value="'.$langs->trans('Add').'">';
		print ' <input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'">';
		print '</div>';
		print '</form>';
		print '</div>';

		print '<script nonce="'.getNonce().'">';
		print 'jQuery(function(){';
		print 'jQuery("#dialog-addcomposition").dialog({autoOpen:false,modal:true,width:900,title:"'.dol_escape_js($langs->trans('PowerPlantMaterialComposition')).'"});';
		if ($openaddmodal) {
			print 'jQuery("#dialog-addcomposition").dialog("open");';
		}
		print '});';
		print '</script>';

		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="id" value="'.$object->id.'">';
		print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
		print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
		print '<input type="hidden" name="page" value="'.((int) $page).'">';
		print '<input type="hidden" name="limit" value="'.((int) $limit).'">';
		print '<div class="div-table-responsive">';
		print '<table class="tagtable liste centpercent">';

			print '<tr class="liste_titre_filter">';
			print '<td class="liste_titre center maxwidthsearch">&nbsp;</td>';
			print '<td class="liste_titre center maxwidthsearch">';
		print '<div class="nowraponall">';
		print '<button type="submit" class="liste_titre button_search reposition" name="button_search_x" value="x"><span class="fas fa-search"></span></button>';
		print '<button type="submit" class="liste_titre button_removefilter reposition" name="button_removefilter_x" value="x"><span class="fas fa-times"></span></button>';
		print '</div>';
		print '</td>';
		print '<td class="liste_titre left"><input type="text" class="flat width75" name="search_ref" value="'.dol_escape_htmltag($search_ref).'"></td>';
		print '<td class="liste_titre left"><input type="text" class="flat width100" name="search_label" value="'.dol_escape_htmltag($search_label).'"></td>';
		print '<td class="liste_titre left">'.$form->selectarray('search_nature', array(-1 => '') + $categories, ($search_nature > 0 ? $search_nature : -1), 0).'</td>';
		print '<td class="liste_titre left"><input type="text" class="flat width100" name="search_serial" value="'.dol_escape_htmltag($search_serial).'"></td>';
		print '<td class="liste_titre left"><input type="date" class="flat width100" name="search_commissioning" value="'.dol_escape_htmltag($search_commissioning).'"></td>';
		print '</tr>';

		print '<tr class="liste_titre">';
		print_liste_field_titre($form->showCheckAddButtons('checkforselect', 1), $_SERVER['PHP_SELF'], '', '', $param, 'class="center"', $sortfield, $sortorder);
		print_liste_field_titre($langs->trans('Ref'), $_SERVER['PHP_SELF'], 'p.ref', '', $param, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('Label'), $_SERVER['PHP_SELF'], 'p.label', '', $param, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('Category'), $_SERVER['PHP_SELF'], 'cpv.label', '', $param, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('PowerPlantSerialNumber'), $_SERVER['PHP_SELF'], 'c.serial_number', '', $param, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('PowerPlantCommissioningDate'), $_SERVER['PHP_SELF'], 'c.commissioning_date', '', $param, '', $sortfield, $sortorder);
	print_liste_field_titre('', $_SERVER['PHP_SELF'], '', '', $param, 'class="center"', $sortfield, $sortorder);
	print '</tr>';

	if ($resql && $db->num_rows($resql) > 0) {
		$num = min($db->num_rows($resql), $limit);
		$i = 0;
		while ($i < $num) {
			$objline = $db->fetch_object($resql);
				print '<tr class="oddeven">';
				print '<td class="center">';
				print '<input class="flat checkforselect" type="checkbox" name="toselect[]" value="'.((int) $objline->rowid).'">';
				print '</td>';
				print '<td>'.dol_escape_htmltag($objline->product_ref).'</td>';
			print '<td>'.dol_escape_htmltag($objline->product_label).'</td>';
				print '<td>'.dol_escape_htmltag($objline->category_label).'</td>';
			print '<td>'.dol_escape_htmltag($objline->serial_number).'</td>';
			print '<td>'.(!empty($objline->commissioning_date) ? dol_print_date($db->jdate($objline->commissioning_date), 'day') : '').'</td>';
			print '<td class="center">';
			if ($canedit) {
				print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=delcomposition&lineid='.(int) $objline->rowid.'&token='.newToken().'">'.img_delete().'</a>';
			}
			print '</td>';
			print '</tr>';
			$i++;
		}
		} else {
			print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('None').'</span></td></tr>';
		}

	print '</table>';
	print '</div>';
	print '</form>';
	print '</div>';
	print dol_get_fiche_end();
}

llxFooter();
$db->close();
