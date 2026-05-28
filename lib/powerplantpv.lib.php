<?php
/* Copyright (C) 2025		Pierre Ardoin				<erp@lesmetiersdubatiment.fr>
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
