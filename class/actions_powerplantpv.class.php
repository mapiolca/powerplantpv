<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
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
 * \file        class/actions_powerplantpv.class.php
 * \ingroup     powerplantpv
 * \brief       Hooks for PowerPlantPV.
 */

/**
 * Hooks for PowerPlantPV.
 */
class ActionsPowerplantpv
{
	/**
	 * @var array<string,mixed> Hook results
	 */
	public $results = array();

	/**
	 * @var string HTML printed by hook
	 */
	public $resprints = '';

	/**
	 * @var string[] Hook errors
	 */
	public $errors = array();

	/**
	 * Add action buttons on native object cards.
	 *
	 * @param	array<string,mixed>	$parameters		Hook parameters
	 * @param	CommonObject		$object			Current object
	 * @param	string				$action			Current action
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int									0 on success, <0 on error
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $user;

		if (!isModEnabled('powerplantpv') || empty($object->id)) {
			return 0;
		}
		if (!$user->hasRight('powerplantpv', 'powerplant', 'write')) {
			return 0;
		}

		$contexts = $this->getContexts($parameters, $hookmanager);
		$origin = '';
		if (in_array('ordercard', $contexts)) {
			$origin = 'commande';
		} elseif (in_array('propalcard', $contexts)) {
			$origin = 'propal';
		}

		if (empty($origin)) {
			return 0;
		}

		$langs->load('powerplantpv@powerplantpv');

		$url = dol_buildpath('/powerplantpv/powerplant_card.php', 1);
		$url .= '?action=create';
		$url .= '&origin='.urlencode($origin);
		$url .= '&originid='.urlencode((string) $object->id);
		if (!empty($_SERVER['REQUEST_URI'])) {
			$url .= '&backtopage='.urlencode($_SERVER['REQUEST_URI']);
			$url .= '&backtopageforcancel='.urlencode($_SERVER['REQUEST_URI']);
		}

		print dolGetButtonAction($langs->trans('CreatePowerPlant'), '', 'default', $url, '', true);

		return 0;
	}

	/**
	 * Add PowerPlant objects into Dolibarr native "Link to..." selector.
	 *
	 * @param	array<string,mixed>	$parameters		Hook parameters
	 * @param	CommonObject		$object			Current object
	 * @param	string				$action			Current action
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int									0 on success, <0 on error
	 */
	public function showLinkToObjectBlock($parameters, &$object, &$action, $hookmanager)
	{
		global $db, $langs, $user;

		if (!isModEnabled('powerplantpv') || !$user->hasRight('powerplantpv', 'powerplant', 'read')) {
			return 0;
		}

		$listofidcompanytoscan = empty($parameters['listofidcompanytoscan']) ? '' : $parameters['listofidcompanytoscan'];
		$listofidcompanytoscan = $this->sanitizeIdList($listofidcompanytoscan);
		if ($listofidcompanytoscan === '') {
			return 0;
		}

		$langs->load('powerplantpv@powerplantpv');

		$sql = "SELECT s.rowid as socid, s.nom as name, s.client, t.rowid, t.ref, t.label as ref_client, NULL as total_ht";
		$sql .= " FROM ".$db->prefix()."societe as s, ".$db->prefix()."powerplantpv_powerplant as t";
		$sql .= " WHERE t.fk_soc = s.rowid";
		$sql .= " AND t.fk_soc IN (".$db->sanitize($listofidcompanytoscan).")";
		$sql .= " AND t.entity IN (".getEntity('powerplant').")";
		$sql .= " ORDER BY t.ref ASC";

		if (empty($hookmanager->resArray) || !is_array($hookmanager->resArray)) {
			$hookmanager->resArray = array();
		}

		$hookmanager->resArray['powerplantpv_powerplant'] = array(
			'enabled' => isModEnabled('powerplantpv'),
			'perms' => $user->hasRight('powerplantpv', 'powerplant', 'read'),
			'label' => 'LinkToPowerPlant',
			'sql' => $sql,
		);

		return 0;
	}

	/**
	 * Describe the PowerPlantPV object to Dolibarr generic object APIs.
	 *
	 * @param	array<string,mixed>	$parameters		Hook parameters
	 * @param	CommonObject		$object			Current object
	 * @param	string				$action			Current action
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int									1 to replace element properties, 0 otherwise
	 */
	public function getElementProperties($parameters, &$object, &$action, $hookmanager)
	{
		global $conf;

		if (empty($parameters['elementType']) || !in_array($parameters['elementType'], array('powerplantpv_powerplant', 'powerplant@powerplantpv', 'powerplant'))) {
			return 0;
		}

		$diroutput = '';
		if (isset($conf->powerplantpv) && !empty($conf->powerplantpv->dir_output)) {
			$diroutput = $conf->powerplantpv->dir_output;
		}

		$this->results = array(
			'module' => 'powerplantpv',
			'element' => 'powerplant',
			'table_element' => 'powerplantpv_powerplant',
			'subelement' => 'powerplant',
			'classpath' => 'powerplantpv/class',
			'classfile' => 'powerplant',
			'classname' => 'PowerPlant',
			'dir_output' => $diroutput,
		);
		$hookmanager->resArray = $this->results;

		return 1;
	}

	/**
	 * Return hook contexts from parameters or manager.
	 *
	 * @param	array<string,mixed>	$parameters		Hook parameters
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	string[]							Context list
	 */
	private function getContexts($parameters, $hookmanager)
	{
		if (!empty($parameters['currentcontext'])) {
			return explode(':', (string) $parameters['currentcontext']);
		}
		if (!empty($hookmanager->contextarray) && is_array($hookmanager->contextarray)) {
			return $hookmanager->contextarray;
		}

		return array();
	}

	/**
	 * Keep only integer ids from a comma-separated list.
	 *
	 * @param	string|int	$value	Input value
	 * @return	string			Comma-separated integer ids
	 */
	private function sanitizeIdList($value)
	{
		$ids = array();
		foreach (explode(',', (string) $value) as $id) {
			$id = (int) trim($id);
			if ($id > 0) {
				$ids[] = $id;
			}
		}

		return implode(',', $ids);
	}
}
