<?php
/* Copyright (C) 2025		Pierre Ardoin				<erp@lesmetiersdubatiment.fr>
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
 * 	@file      ajax/products.php
 * 	@ingroup   powerplantpv
 * 	@brief     Ajax endpoint to search PV products by dictionary type.
 */

// Load Dolibarr environment
$res = 0;
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

if (empty($user->rights->produit->lire)) {
	accessforbidden();
}

header('Content-Type: application/json');

dol_include_once('/core/lib/functions2.lib.php');

$term = trim(GETPOST('term', 'alphanohtml'));
$typeCode = GETPOST('type_code', 'alphanohtml');
$typeRowid = GETPOSTINT('type_rowid');

if (empty($typeCode) && empty($typeRowid)) {
	echo json_encode(array());
	exit;
}

$sql = array();
$sql[] = "SELECT p.rowid, p.ref, p.label";
$sql[] = "FROM ".$db->prefix()."product as p";
$sql[] = "JOIN ".$db->prefix()."product_extrafields as pe ON pe.fk_object = p.rowid";
$sql[] = "JOIN ".$db->prefix()."c_pv_product_type as t ON t.rowid = pe.pv_product_type";
$sql[] = "WHERE p.entity IN (".getEntity('product').")";
$sql[] = "AND p.status = 1";

if (!empty($typeCode)) {
	$sql[] = "AND t.code = '".$db->escape($typeCode)."'";
}
if (!empty($typeRowid)) {
	$sql[] = "AND t.rowid = ".((int) $typeRowid);
}
if ($term !== '') {
	$escapedTerm = $db->escape($term);
	$sql[] = "AND (p.ref LIKE '%".$escapedTerm."%' OR p.label LIKE '%".$escapedTerm."%')";
}

$sql[] = "ORDER BY p.ref ASC";
$sql[] = $db->plimit(25, 0);

$products = array();
$resql = $db->query(implode(' ', $sql));
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$products[] = array(
			'id' => $obj->rowid,
			'text' => dol_escape_htmltag($obj->ref.' - '.$obj->label)
		);
	}
} else {
	setEventMessages($db->lasterror(), null, 'errors');
}

echo json_encode($products);
exit;
