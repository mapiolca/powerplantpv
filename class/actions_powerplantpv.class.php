<?php
/* Copyright (C) 2026	Pierre Ardoin			<developpeur@lesmetiersdubatiment.fr>
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
 * \file		class/actions_powerplantpv.class.php
 * \ingroup	powerplantpv
 * \brief		Hook class for PowerPlantPV module.
 */
class ActionsPowerplantpv
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var int Priority of hook (50 is used if value is not defined)
	 */
	public $priority;

	/**
	 * @var int Priority of hook (50 is used if value is not defined)
	 */
	public $formconfirm;

	/**
	 * Constructor.
	 *
	 * @param	DoliDB	$db	Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Add tab on product card.
	 *
	 * @param	array			$parameters		Hook parameters
	 * @param	Product			$object			Product object
	 * @param	string			$action			Current action
	 * @param	HookManager		$hookmanager	Hook manager
	 * @return	int						0 or 1 to replace standard behavior, <0 on error
	 */
	public function addMoreTabs($parameters, &$object, &$action, $hookmanager)
	{
		global $db, $langs, $user;

		$langs->loadLangs(array('powerplantpv@powerplantpv'));

		if (empty($object->id) || !$user->hasRight('produit', 'lire')) {
			return 0;
		}

		$allowedCodes = array('ONDULE', 'MODULE');
		$sql = 'SELECT cpv.code';
		$sql .= ' FROM '.$db->prefix().'product_extrafields as pe';
		$sql .= ' LEFT JOIN '.$db->prefix().'c_powerplantpv_categorypv as cpv ON cpv.rowid = pe.categorie_photovoltaique';
		$sql .= ' WHERE pe.fk_object = '.((int) $object->id);

		$resql = $db->query($sql);
		if (!$resql) {
			return 0;
		}

		$obj = $db->fetch_object($resql);
		$categoryCode = !empty($obj->code) ? (string) $obj->code : '';
		$db->free($resql);

		if (in_array($categoryCode, $allowedCodes, true)) {
			$tabs = array(array(
				'url' => dol_buildpath('/powerplantpv/product_pvpanel.php', 1).'?id='.$object->id,
				'title' => $langs->trans('PVPanelTabTitle'),
				'id' => 'pvpanel'
			));
			$hookmanager->resArray = array_merge($hookmanager->resArray, $tabs);
		}

		return 0;
	}
}
