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
 * 	\file       powerplantpv/hooks/powerplantpv_product.class.php
 *	\ingroup    powerplantpv
 *	\brief      Hook for product card.
 */

class ActionsPowerplantpv_product
{
	/**
	 * Remove PV panel tab from product card when photovoltaic category is not allowed.
	 *
	 * @param	array			$parameters		Hook parameters
	 * @param	Product			$object			Product object
	 * @param	string			$action			Current action
	 * @param	HookManager		$hookmanager	Hook manager
	 * @return	int							0 on success
	 */
	public function completeTabsHead($parameters, &$object, &$action, $hookmanager)
	{
		if (!is_array($parameters) || empty($parameters['head']) || !is_array($parameters['head'])) {
			return 0;
		}
		if (empty($object->id)) {
			return 0;
		}

		$object->fetch_optionals($object->id, null);
		$categoryRowId = !empty($object->array_options['options_categorie_photovoltaique']) ? (int) $object->array_options['options_categorie_photovoltaique'] : 0;
		if ($categoryRowId === 1) {
			return 0;
		}

		$newhead = $parameters['head'];
		foreach ($newhead as $key => $tab) {
			if (isset($tab[2]) && $tab[2] === 'pvpanel') {
				unset($newhead[$key]);
			}
		}

		$hookmanager->resArray = $newhead;

		return 1;
	}
}
