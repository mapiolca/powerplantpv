<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later */

/**
 * Count PV module quantities without recalculating or writing any commercial totals.
 * The caller must supply a loaded proposal; native access restrictions are checked again.
 * @param Propal $proposal Proposal
 * @param User $actor Actor
 * @return array{result:int,quantity:float,error:string}
 */
function powerplantpvCountProposalModules($proposal, $actor): array
{
	global $db;
	if (!isModEnabled('powerplantpv') || !isModEnabled('propal') || !$actor->hasRight('propal', 'lire') || (int) $proposal->id <= 0) {
		return array('result' => -1, 'quantity' => 0.0, 'error' => 'PermissionDenied');
	}
	if (!restrictedArea($actor, 'propal', $proposal->id, 'propal', '', 'fk_soc', 'rowid', 0, 1, 'read')) {
		return array('result' => -1, 'quantity' => 0.0, 'error' => 'PermissionDenied');
	}
	$categories = powerplantpvGetPhotovoltaicModuleCategoryIds();
	if ($categories['result'] < 0) {
		return array('result' => -1, 'quantity' => 0.0, 'error' => (string) $categories['error']);
	}
	$ids = array();
	foreach ($categories['ids'] as $id) {
		if (is_numeric($id) && (int) $id > 0) {
			$ids[] = (int) $id;
		}
	}
	if (!$ids) {
		return array('result' => 1, 'quantity' => 0.0, 'error' => '');
	}
	$sql = 'SELECT SUM(l.qty) AS quantity FROM '.MAIN_DB_PREFIX.'propaldet l';
	$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'propal d ON d.rowid = l.fk_propal';
	$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid = l.fk_product';
	$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'product_extrafields pe ON pe.fk_object = p.rowid';
	$sql .= ' WHERE d.rowid = '.((int) $proposal->id).' AND d.entity = '.((int) $proposal->entity);
	$sql .= ' AND d.entity IN ('.$db->sanitize(getEntity('propal')).')';
	$sql .= ' AND p.entity IN ('.$db->sanitize(getEntity('product')).')';
	$sql .= ' AND pe.categorie_photovoltaique IN ('.$db->sanitize(implode(',', $ids)).')';
	$res = $db->query($sql);
	if (!$res) {
		return array('result' => -1, 'quantity' => 0.0, 'error' => $db->lasterror());
	}
	$row = $db->fetch_object($res);
	$db->free($res);
	if (!is_object($row)) {
		return array('result' => -1, 'quantity' => 0.0, 'error' => 'Module quantity unavailable');
	}
	return array('result' => 1, 'quantity' => (float) ($row->quantity ?? 0), 'error' => '');
}
