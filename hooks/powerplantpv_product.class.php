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
	 * Add tab on product card.
	 *
	 * @param	array			$parameters		Hook parameters
	 * @param	Product			$object			Product object
	 * @param	string			$action			Current action
	 * @param	HookManager		$hookmanager	Hook manager
	 * @return	int							0 or 1 to replace standard behavior, <0 on error
	 */
	public function addMoreTabs($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		$langs->loadLangs(array('powerplantpv@powerplantpv'));

		if ($object->fk_product_nature == '50') {
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
