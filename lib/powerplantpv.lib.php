<?php
/* Copyright (C) 2025		Pierre Ardoin				<erp@lesmetiersdubatiment.fr>
 * Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 * Copyright (C) 2025       Frédéric France         <frederic.france@free.fr>
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
 * \file    powerplantpv/lib/powerplantpv.lib.php
 * \ingroup powerplantpv
 * \brief   Library files with common functions for PowerPlantPV
 */

/**
 * Prepare admin pages header
 *
 * @return array<array{string,string,string}>
 */
function powerplantpvAdminPrepareHead()
{
	global $langs, $conf;

	// global $db;
	// $extrafields = new ExtraFields($db);
	// $extrafields->fetch_name_optionals_label('myobject');

	$langs->load("powerplantpv@powerplantpv");

	$h = 0;
	$head = array();

	$head[$h][0] = dolBuildUrl(dol_buildpath("/powerplantpv/admin/setup.php", 1));
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	/*
	$head[$h][0] = dolBuildUrl(dol_buildpath("/powerplantpv/admin/myobject_extrafields.php", 1));
	$head[$h][1] = $langs->trans("ExtraFields");
	$nbExtrafields = (isset($extrafields->attributes['myobject']['label']) && is_countable($extrafields->attributes['myobject']['label'])) ? count($extrafields->attributes['myobject']['label']) : 0;
	if ($nbExtrafields > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">' . $nbExtrafields . '</span>';
	}
	$head[$h][2] = 'myobject_extrafields';
	$h++;

	$head[$h][0] = dolBuildUrl(dol_buildpath("/powerplantpv/admin/myobjectline_extrafields.php", 1));
	$head[$h][1] = $langs->trans("ExtraFieldsLines");
	$nbExtrafields = (isset($extrafields->attributes['myobjectline']['label']) && is_countable($extrafields->attributes['myobjectline']['label'])) ? count($extrafields->attributes['myobject']['label']) : 0;
	if ($nbExtrafields > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">' . $nbExtrafields . '</span>';
	}
	$head[$h][2] = 'myobject_extrafieldsline';
	$h++;
	*/

	$head[$h][0] = dolBuildUrl(dol_buildpath("/powerplantpv/admin/about.php", 1));
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@powerplantpv:/powerplantpv/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@powerplantpv:/powerplantpv/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, null, $head, $h, 'powerplantpv@powerplantpv');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'powerplantpv@powerplantpv', 'remove');

	return $head;
}

/**
 * Normalize a Dolibarr element type to the value stored in llx_element_element.
 *
 * @param	string	$elementtype	Element type
 * @return	string					Normalized element type
 */
function powerplantpvNormalizeElementType($elementtype)
{
	if ($elementtype == 'order') {
		return 'commande';
	}
	if ($elementtype == 'contract') {
		return 'contrat';
	}
	if ($elementtype == 'propale') {
		return 'propal';
	}
	if ($elementtype == 'invoice') {
		return 'facture';
	}

	return (string) $elementtype;
}

/**
 * Normalize a Dolibarr origin type to the value stored in llx_element_element.
 *
 * @param	string	$origin		Origin type
 * @return	string				Normalized origin type, empty if unsupported
 */
function powerplantpvNormalizeOriginType($origin)
{
	$origin = powerplantpvNormalizeElementType($origin);
	if (in_array($origin, array('commande', 'propal', 'contrat'))) {
		return $origin;
	}

	return '';
}

/**
 * Fetch an object that can be linked to a power plant.
 *
 * @param	string	$origin		Origin type
 * @param	int		$originid	Origin object id
 * @return	CommonObject|null	Fetched object, null if unsupported or not found
 */
function powerplantpvFetchOriginObject($origin, $originid)
{
	global $db;

	$origin = powerplantpvNormalizeOriginType($origin);
	$originid = (int) $originid;
	if (empty($origin) || $originid <= 0) {
		return null;
	}

	if ($origin == 'commande') {
		dol_include_once('/commande/class/commande.class.php');
		$classname = 'Commande';
	} elseif ($origin == 'propal') {
		dol_include_once('/comm/propal/class/propal.class.php');
		$classname = 'Propal';
	} elseif ($origin == 'contrat') {
		dol_include_once('/contrat/class/contrat.class.php');
		$classname = 'Contrat';
	} else {
		return null;
	}

	if (!class_exists($classname)) {
		return null;
	}

	$sourceobject = new $classname($db);
	$result = $sourceobject->fetch($originid);
	if ($result <= 0) {
		return null;
	}

	return $sourceobject;
}

/**
 * Apply default third party/project values from a source object to a power plant.
 *
 * @param	PowerPlant	$powerplant	Power plant object to initialize
 * @param	string		$origin		Origin type
 * @param	int			$originid	Origin object id
 * @return	int						Return integer <0 if KO, 0 if no origin, >0 if defaults were applied
 */
function powerplantpvApplyOriginDefaults(&$powerplant, $origin, $originid)
{
	global $langs;

	$sourceobject = powerplantpvFetchOriginObject($origin, $originid);
	if (!is_object($sourceobject)) {
		return 0;
	}

	if (empty($powerplant->fk_soc)) {
		$originsocid = 0;
		if (!empty($sourceobject->socid)) {
			$originsocid = (int) $sourceobject->socid;
		} elseif (!empty($sourceobject->fk_soc)) {
			$originsocid = (int) $sourceobject->fk_soc;
		}
		if ($originsocid > 0) {
			$powerplant->fk_soc = $originsocid;
			$powerplant->socid = $originsocid;
		}
	}
	if (empty($powerplant->fk_project) && !empty($sourceobject->fk_project)) {
		$powerplant->fk_project = (int) $sourceobject->fk_project;
	}
	if (empty($powerplant->label) && !empty($sourceobject->ref)) {
		$powerplant->label = (is_object($langs) ? $langs->trans('PowerPlantCreatedFrom', $sourceobject->ref) : $sourceobject->ref);
	}

	return 1;
}

/**
 * Fetch an origin object and load its commercial lines when available.
 *
 * @param	string	$origin		Origin type
 * @param	int		$originid	Origin object id
 * @return	CommonObject|null	Fetched object, null if unsupported or not found
 */
function powerplantpvFetchOriginObjectWithLines($origin, $originid)
{
	$sourceobject = powerplantpvFetchOriginObject($origin, $originid);
	if (!is_object($sourceobject)) {
		return null;
	}

	if (method_exists($sourceobject, 'fetch_lines')) {
		$sourceobject->fetch_lines();
	} elseif (method_exists($sourceobject, 'fetchLines')) {
		$sourceobject->fetchLines();
	}

	return $sourceobject;
}

/**
 * Return the numeric status of a Dolibarr object.
 *
 * @param	CommonObject	$object	Source object
 * @return	int					Status, -9999 if no status is available
 */
function powerplantpvGetObjectStatus($object)
{
	if (is_object($object) && isset($object->statut)) {
		return (int) $object->statut;
	}
	if (is_object($object) && isset($object->status)) {
		return (int) $object->status;
	}

	return -9999;
}

/**
 * Check if the source object status allows automatic material creation.
 *
 * @param	string			$origin			Normalized origin type
 * @param	CommonObject	$sourceobject	Source object
 * @return	bool							True if source status is eligible
 */
function powerplantpvIsAutomaticMaterialSourceEligible($origin, $sourceobject)
{
	$status = powerplantpvGetObjectStatus($sourceobject);

	if ($origin == 'propal') {
		if (!class_exists('Propal')) {
			return false;
		}
		$statussigned = defined('Propal::STATUS_SIGNED') ? constant('Propal::STATUS_SIGNED') : 2;
		return $status == $statussigned;
	}

	if ($origin == 'commande') {
		if (!class_exists('Commande')) {
			return false;
		}
		$statusdraft = defined('Commande::STATUS_DRAFT') ? constant('Commande::STATUS_DRAFT') : 0;
		$statusclosed = defined('Commande::STATUS_CLOSED') ? constant('Commande::STATUS_CLOSED') : 3;
		$statuscanceled = defined('Commande::STATUS_CANCELED') ? constant('Commande::STATUS_CANCELED') : -1;
		return $status > $statusdraft && $status < $statusclosed && $status != $statuscanceled;
	}

	return false;
}

/**
 * Return a product id from a commercial line.
 *
 * @param	object	$line	Commercial line
 * @return	int				Product id
 */
function powerplantpvGetLineProductId($line)
{
	if (!empty($line->fk_product)) {
		return (int) $line->fk_product;
	}
	if (!empty($line->fk_product_or_service)) {
		return (int) $line->fk_product_or_service;
	}

	return 0;
}

/**
 * Return a commercial line id.
 *
 * @param	object	$line	Commercial line
 * @return	int				Line id
 */
function powerplantpvGetLineId($line)
{
	if (!empty($line->id)) {
		return (int) $line->id;
	}
	if (!empty($line->rowid)) {
		return (int) $line->rowid;
	}

	return 0;
}

/**
 * Return a commercial line quantity.
 *
 * @param	object	$line	Commercial line
 * @return	float			Quantity
 */
function powerplantpvGetLineQty($line)
{
	if (isset($line->qty)) {
		return (float) $line->qty;
	}
	if (isset($line->quantity)) {
		return (float) $line->quantity;
	}

	return 0.0;
}

/**
 * Return product PV category data indexed by product id.
 *
 * @param	int[]	$productids	Product ids
 * @return	array<int,array<string,mixed>>	Product category data
 */
function powerplantpvGetProductPhotovoltaicCategories($productids)
{
	global $db, $langs;

	$productids = array_values(array_unique(array_filter(array_map('intval', $productids))));
	if (empty($productids)) {
		return array();
	}

	$sql = "SELECT p.rowid, p.ref, p.label, pe.categorie_photovoltaique as category_id, cpv.code as category_code, cpv.label as category_label";
	$sql .= " FROM ".$db->prefix()."product as p";
	$sql .= " INNER JOIN ".$db->prefix()."product_extrafields as pe ON pe.fk_object = p.rowid";
	$sql .= " LEFT JOIN ".$db->prefix()."c_powerplantpv_categorypv as cpv ON cpv.rowid = pe.categorie_photovoltaique";
	$sql .= " WHERE p.rowid IN (".implode(',', $productids).")";
	$sql .= " AND pe.categorie_photovoltaique IS NOT NULL AND pe.categorie_photovoltaique <> ''";
	$sql .= " AND p.entity IN (".getEntity('product').")";

	$products = array();
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$categoryid = (int) $obj->category_id;
			$categorylabel = '';
			if (!empty($obj->category_label)) {
				$categorylabel = $obj->category_label;
			} elseif (!empty($obj->category_code)) {
				$categorylabel = $obj->category_code;
			} else {
				$categorylabel = (is_object($langs) ? $langs->trans('Category') : 'Category').' '.$categoryid;
			}

			$products[(int) $obj->rowid] = array(
				'product_id' => (int) $obj->rowid,
				'product_ref' => $obj->ref,
				'product_label' => $obj->label,
				'category_id' => $categoryid,
				'category_code' => $obj->category_code,
				'category_label' => $categorylabel,
			);
		}
		$db->free($resql);
	}

	return $products;
}

/**
 * Build the automatic material summary from a proposal or customer order.
 *
 * @param	string	$origin					Origin type
 * @param	int		$originid				Origin object id
 * @param	int<0,1>	$checksourceeligible	1=ignore lines when the source status is not eligible
 * @return	array<string,mixed>				Summary data
 */
function powerplantpvGetAutomaticMaterialSummary($origin, $originid, $checksourceeligible = 1)
{
	global $langs;

	$origin = powerplantpvNormalizeOriginType($origin);
	$summary = array(
		'origin' => $origin,
		'origin_id' => (int) $originid,
		'source_object' => null,
		'eligible' => false,
		'lines' => array(),
		'categories' => array(),
		'total_components' => 0,
		'total_ignored_qty' => 0.0,
		'warnings' => array(),
		'errors' => array(),
	);

	if (!in_array($origin, array('propal', 'commande'), true) || (int) $originid <= 0) {
		return $summary;
	}

	$sourceobject = powerplantpvFetchOriginObjectWithLines($origin, $originid);
	if (!is_object($sourceobject)) {
		$summary['errors'][] = 'ErrorFailedToFetchOrigin';
		return $summary;
	}
	$summary['source_object'] = $sourceobject;
	$summary['eligible'] = powerplantpvIsAutomaticMaterialSourceEligible($origin, $sourceobject);
	if (!empty($checksourceeligible) && empty($summary['eligible'])) {
		return $summary;
	}

	$productids = array();
	if (!empty($sourceobject->lines) && is_array($sourceobject->lines)) {
		foreach ($sourceobject->lines as $line) {
			$productid = powerplantpvGetLineProductId($line);
			if ($productid > 0) {
				$productids[] = $productid;
			}
		}
	}

	$products = powerplantpvGetProductPhotovoltaicCategories($productids);
	if (empty($products) || empty($sourceobject->lines) || !is_array($sourceobject->lines)) {
		return $summary;
	}

	foreach ($sourceobject->lines as $line) {
		$productid = powerplantpvGetLineProductId($line);
		if ($productid <= 0 || empty($products[$productid])) {
			continue;
		}

		$qty = round(powerplantpvGetLineQty($line), 8);
		if ($qty <= 0) {
			continue;
		}

		$components = (int) floor($qty);
		$ignoredqty = $qty - $components;
		if ($ignoredqty < 0.00000001) {
			$ignoredqty = 0.0;
		}
		if ($components <= 0) {
			if ($ignoredqty > 0) {
				$summary['total_ignored_qty'] += $ignoredqty;
				if (is_object($langs)) {
					$summary['warnings'][] = $langs->trans('PowerPlantFractionalQuantityIgnored', $products[$productid]['product_ref'], price($ignoredqty));
				}
			}
			continue;
		}

		$product = $products[$productid];
		$categorykey = (string) $product['category_id'];
		if (empty($summary['categories'][$categorykey])) {
			$summary['categories'][$categorykey] = array(
				'category_id' => (int) $product['category_id'],
				'category_code' => $product['category_code'],
				'category_label' => $product['category_label'],
				'total_components' => 0,
				'total_qty' => 0.0,
				'total_ignored_qty' => 0.0,
			);
		}

		$summaryline = array(
			'source_line_id' => powerplantpvGetLineId($line),
			'fk_product' => $productid,
			'product_ref' => $product['product_ref'],
			'product_label' => $product['product_label'],
			'category_id' => (int) $product['category_id'],
			'category_code' => $product['category_code'],
			'category_label' => $product['category_label'],
			'source_qty' => $qty,
			'components_to_create' => $components,
			'ignored_qty' => $ignoredqty,
		);

		$summary['lines'][] = $summaryline;
		$summary['total_components'] += $components;
		$summary['total_ignored_qty'] += $ignoredqty;
		$summary['categories'][$categorykey]['total_components'] += $components;
		$summary['categories'][$categorykey]['total_qty'] += $qty;
		$summary['categories'][$categorykey]['total_ignored_qty'] += $ignoredqty;

		if ($ignoredqty > 0 && is_object($langs)) {
			$summary['warnings'][] = $langs->trans('PowerPlantFractionalQuantityIgnored', $product['product_ref'], price($ignoredqty));
		}
	}

	return $summary;
}

/**
 * Create the material composition from the source object's photovoltaic lines.
 *
 * @param	PowerPlant	$powerplant	Power plant object
 * @param	string		$origin		Origin type
 * @param	int			$originid	Origin object id
 * @param	User		$user		User creating the object
 * @return	int						Number of created component lines, <0 if KO
 */
function powerplantpvCreateComponentsFromOrigin($powerplant, $origin, $originid, $user)
{
	global $conf;

	$db = $powerplant->db;
	$summary = powerplantpvGetAutomaticMaterialSummary($origin, $originid, 1);
	if (empty($summary['total_components']) || empty($summary['lines'])) {
		$powerplant->context['powerplantpv_material_summary'] = $summary;
		return 0;
	}

	$created = 0;
	foreach ($summary['lines'] as $line) {
		$i = 0;
		while ($i < (int) $line['components_to_create']) {
			$sql = "INSERT INTO ".$db->prefix()."powerplantpv_powerplantcomp";
			$sql .= "(fk_powerplant, fk_product, fk_status, qty, serial_number, commissioning_date, entity)";
			$sql .= " VALUES (".((int) $powerplant->id).", ".((int) $line['fk_product']).", 4, 1, '', NULL, ".((int) $conf->entity).")";
			$resql = $db->query($sql);
			if (!$resql) {
				$powerplant->error = $db->lasterror();
				$powerplant->errors[] = $powerplant->error;
				return -1;
			}
			$created++;
			$i++;
		}
	}

	$summary['created_components'] = $created;
	$powerplant->context['powerplantpv_material_summary'] = $summary;

	return $created;
}

/**
 * Return the linked-object element types for an object.
 *
 * @param	CommonObject	$object	Object
 * @return	string[]				Element types
 */
function powerplantpvGetObjectElementTypes($object)
{
	$elementtypes = array();
	if (is_object($object) && method_exists($object, 'getElementType')) {
		$elementtypes[] = $object->getElementType();
	}
	if (is_object($object) && !empty($object->element)) {
		$elementtypes[] = $object->element;
		$elementtypes[] = powerplantpvNormalizeElementType($object->element);
	}

	return array_values(array_unique(array_filter($elementtypes)));
}

/**
 * Return power plants linked to a Dolibarr object.
 *
 * @param	CommonObject	$object		Object with links
 * @return	PowerPlant[]				Linked power plants indexed by id
 */
function powerplantpvGetLinkedPowerPlants($object)
{
	if (!is_object($object) || empty($object->id) || !isModEnabled('powerplantpv')) {
		return array();
	}

	$db = (!empty($object->db) ? $object->db : (isset($GLOBALS['db']) ? $GLOBALS['db'] : null));
	$objecttypes = powerplantpvGetObjectElementTypes($object);
	if (empty($db) || empty($objecttypes)) {
		return array();
	}
	dol_include_once('/powerplantpv/class/powerplant.class.php');

	$escapedobjecttypes = array();
	foreach ($objecttypes as $objecttype) {
		$escapedobjecttypes[] = "'".$db->escape($objecttype)."'";
	}

	$powerplanttypes = array('powerplantpv_powerplant', 'powerplant@powerplantpv', 'powerplant');
	$escapedpowerplanttypes = array();
	foreach ($powerplanttypes as $powerplanttype) {
		$escapedpowerplanttypes[] = "'".$db->escape($powerplanttype)."'";
	}

	$sql = "SELECT ee.rowid, ee.fk_source, ee.sourcetype, ee.fk_target, ee.targettype";
	$sql .= " FROM ".$db->prefix()."element_element as ee";
	$sql .= " WHERE (ee.fk_source = ".((int) $object->id);
	$sql .= " AND ee.sourcetype IN (".implode(',', $escapedobjecttypes).")";
	$sql .= " AND ee.targettype IN (".implode(',', $escapedpowerplanttypes)."))";
	$sql .= " OR (ee.fk_target = ".((int) $object->id);
	$sql .= " AND ee.targettype IN (".implode(',', $escapedobjecttypes).")";
	$sql .= " AND ee.sourcetype IN (".implode(',', $escapedpowerplanttypes)."))";
	$sql .= " ORDER BY ee.rowid ASC";

	$powerplants = array();
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$powerplantid = 0;
			if (in_array($obj->targettype, $powerplanttypes)) {
				$powerplantid = (int) $obj->fk_target;
			} elseif (in_array($obj->sourcetype, $powerplanttypes)) {
				$powerplantid = (int) $obj->fk_source;
			}
			if ($powerplantid <= 0 || isset($powerplants[$powerplantid])) {
				continue;
			}

			$powerplant = new PowerPlant($db);
			if ($powerplant->fetch($powerplantid) > 0) {
				$powerplants[$powerplantid] = $powerplant;
			}
		}
		$db->free($resql);
	}

	return $powerplants;
}
