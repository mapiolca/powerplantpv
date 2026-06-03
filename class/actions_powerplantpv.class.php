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
	 * @var string Identifier used by Multicompany external sharing payload
	 */
	public const MULTICOMPANY_SHARING_ROOT_KEY = 'powerplantpv';

	/**
	 * @var array<string,mixed> Hook results
	 */
	public $results = array();

	/**
	 * @var string HTML printed by hook
	 */
	public $resprints = '';

	/**
	 * @var string Hook error
	 */
	public $error = '';

	/**
	 * @var string[] Hook errors
	 */
	public $errors = array();

	/**
	 * @var string[] Hook warnings
	 */
	public $warnings = array();

	/**
	 * Build the Multicompany sharing payload for the module.
	 *
	 * @return	array<string,array<string,mixed>>	Sharing definition
	 */
	public static function getMulticompanySharingDefinition()
	{
		return array(
			self::MULTICOMPANY_SHARING_ROOT_KEY => array(
				'sharingelements' => array(
					'powerplant' => array(
						'type' => 'element',
						'icon' => 'sun',
						'lang' => 'powerplantpv@powerplantpv',
						'tooltip' => 'PowerPlantSharingInfo',
						'enable' => '! empty($conf->powerplantpv->enabled)',
						'input' => array(
							'global' => array(
								'showhide' => true,
								'hide' => true,
								'del' => true,
							),
						),
					),
					'powerplantnumber' => array(
						'type' => 'objectnumber',
						'icon' => 'hashtag',
						'lang' => 'powerplantpv@powerplantpv',
						'tooltip' => 'PowerPlantNumberSharingInfo',
						'enable' => '! empty($conf->powerplantpv->enabled)',
						'input' => array(
							'global' => array(
								'showhide' => true,
								'hide' => true,
								'del' => true,
							),
						),
					),
				),
				'sharingmodulename' => array(
					'powerplant' => 'powerplantpv',
					'powerplantnumber' => 'powerplantpv',
				),
				'dictionary' => array(
					'c_powerplantpv_categorypv' => array(
						'type' => 'dictionary',
						'icon' => 'tags',
						'transkey' => 'PhotovoltaicCategoryDictionary',
						'tooltip' => 'PhotovoltaicCategoryDictionarySharingInfo',
						'lang' => 'powerplantpv@powerplantpv',
						'filepath' => '/powerplantpv/sql/llx_c_powerplantpv_categorypv.sql',
					),
				),
			),
		);
	}

	/**
	 * Register sharing definition for Multicompany hooks.
	 *
	 * @return	void
	 */
	private function registerMulticompanySharingDefinition()
	{
		global $langs;

		$langs->loadLangs(array('powerplantpv@powerplantpv'));
		if (!is_array($this->results)) {
			$this->results = array();
		}

		$this->results = array_replace_recursive($this->results, self::getMulticompanySharingDefinition());
	}

	/**
	 * Provide sharing options through the Multicompany external module hook.
	 *
	 * @param	array<string,mixed>	$parameters		Hook parameters
	 * @param	CommonObject		$object			Current object
	 * @param	string				$action			Current action
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int									0 on success
	 */
	public function multicompanyExternalModulesSharing($parameters, &$object, &$action, $hookmanager)
	{
		$this->registerMulticompanySharingDefinition();

		return 0;
	}

	/**
	 * Backward-compatible alias for Multicompany sharing hook name.
	 *
	 * @param	array<string,mixed>	$parameters		Hook parameters
	 * @param	CommonObject		$object			Current object
	 * @param	string				$action			Current action
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int									0 on success
	 */
	public function multicompanyExternalModuleSharing($parameters, &$object, &$action, $hookmanager)
	{
		$this->registerMulticompanySharingDefinition();

		return 0;
	}

	/**
	 * Additional alias for broader Multicompany sharing options requests.
	 *
	 * @param	array<string,mixed>	$parameters		Hook parameters
	 * @param	CommonObject		$object			Current object
	 * @param	string				$action			Current action
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int									0 on success
	 */
	public function multicompanySharingOptions($parameters, &$object, &$action, $hookmanager)
	{
		$this->registerMulticompanySharingDefinition();

		return 0;
	}

	/**
	 * Load PowerPlantPV translations for ticket contexts.
	 *
	 * @param	array<string,mixed>	$parameters		Hook parameters
	 * @param	CommonObject		$object			Current object
	 * @param	string				$action			Current action
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int									0 on success, <0 on error
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		if (!isModEnabled('powerplantpv')) {
			return 0;
		}

		$contexts = $this->getContexts($parameters, $hookmanager);
		if (in_array('ticketcard', $contexts) || in_array('publicnewticketcard', $contexts)) {
			$langs->load('powerplantpv@powerplantpv');
		}

		return 0;
	}

	/**
	 * Add native icon before the ticket power plant extrafield selector on ticket creation.
	 *
	 * @param	array<string,mixed>	$parameters		Hook parameters
	 * @param	CommonObject		$object			Current object
	 * @param	string				$action			Current action
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int									0 on success, <0 on error
	 */
	public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		$this->resprints = '';

		if (!isModEnabled('powerplantpv')) {
			return 0;
		}

		$contexts = $this->getContexts($parameters, $hookmanager);
		if (!in_array('ticketcard', $contexts)) {
			return 0;
		}

		if (GETPOST('action', 'aZ09') != 'create') {
			return 0;
		}

		if (empty($object->element) || $object->element != 'ticket') {
			return 0;
		}

		print $this->getTicketPowerPlantPictoScript();

		return 0;
	}

	/**
	 * Add price per watt-peak to the native margin table.
	 *
	 * @param	array<string,mixed>	$parameters		Hook parameters
	 * @param	CommonObject		$object			Current object
	 * @param	string				$action			Current action
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int									0 on success, <0 on error
	 */
	public function displayMarginInfos($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		$this->resprints = '';

		if (!isModEnabled('powerplantpv') || empty($object->id) || empty($parameters['marginInfo']) || !is_array($parameters['marginInfo'])) {
			return 0;
		}

		dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');

		$config = array();
		$elementtypeforcalculation = '';
		foreach (powerplantpvGetObjectElementTypes($object) as $elementtype) {
			$config = powerplantpvGetCommercialDocumentPeakPowerConfig($elementtype);
			if (!empty($config)) {
				$elementtypeforcalculation = $config['elementtype'];
				break;
			}
		}
		if (empty($config)) {
			return 0;
		}

		$peakpowerkwc = powerplantpvGetObjectPeakPowerKwc($object);
		if ($peakpowerkwc <= 0) {
			$objectid = 0;
			if (!empty($object->id)) {
				$objectid = (int) $object->id;
			} elseif (!empty($object->rowid)) {
				$objectid = (int) $object->rowid;
			}
			if ($objectid > 0 && $elementtypeforcalculation !== '') {
				$calculation = powerplantpvCalculateCommercialDocumentPeakPowerKwc($elementtypeforcalculation, $objectid);
				if ($calculation['result'] < 0) {
					dol_syslog(__METHOD__.' failed to calculate peak power for margin display: '.$calculation['error'], LOG_WARNING);
					return 0;
				}
				$peakpowerkwc = (float) $calculation['peak_power_kwc'];
			}
		}

		$peakpowerwc = $peakpowerkwc * 1000;
		if ($peakpowerwc <= 0) {
			return 0;
		}

		$marginInfo = $parameters['marginInfo'];
		$langs->load('powerplantpv@powerplantpv');

		$pvtotal = isset($marginInfo['pv_total']) ? (float) $marginInfo['pv_total'] : 0.0;
		$patotal = isset($marginInfo['pa_total']) ? (float) $marginInfo['pa_total'] : 0.0;
		$totalmargin = isset($marginInfo['total_margin']) ? (float) $marginInfo['total_margin'] : 0.0;

		$html = '<tr class="oddeven margininfo powerplantpv-price-per-wattpeak">';
		$html .= '<td>'.dol_escape_htmltag($langs->trans('PowerPlantPVPricePerWattPeak')).'</td>';
		$html .= '<td class="right">'.$this->formatPricePerWattPeak($pvtotal / $peakpowerwc).'</td>';
		$html .= '<td class="right">'.$this->formatPricePerWattPeak($patotal / $peakpowerwc).'</td>';
		$html .= '<td class="right">'.$this->formatPricePerWattPeak($totalmargin / $peakpowerwc).'</td>';
		if (getDolGlobalString('DISPLAY_MARGIN_RATES')) {
			$html .= '<td class="right"></td>';
		}
		if (getDolGlobalString('DISPLAY_MARK_RATES')) {
			$html .= '<td class="right"></td>';
		}
		$html .= '</tr>';

		$this->resprints = $html;

		return 0;
	}

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

		dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');

		$summary = powerplantpvGetAutomaticMaterialSummary($origin, (int) $object->id, 1);
		if (empty($summary['total_components'])) {
			return 0;
		}

		$langs->load('powerplantpv@powerplantpv');

		$url = dol_buildpath('/powerplantpv/powerplant_card.php', 1);
		$url .= '?action=create';
		$url .= '&origin='.urlencode($origin);
		$url .= '&originid='.urlencode((string) $object->id);
		$url .= '&create_material_from_origin=1';
		if (!empty($_SERVER['REQUEST_URI'])) {
			$url .= '&backtopage='.urlencode($_SERVER['REQUEST_URI']);
			$url .= '&backtopageforcancel='.urlencode($_SERVER['REQUEST_URI']);
		}

		$buttonid = 'powerplantpv-create-powerplant-'.$origin.'-'.((int) $object->id);
		$dropdownhtml = '<a class="butAction" href="'.dol_escape_htmltag($url).'" id="'.dol_escape_htmltag($buttonid.'-dropdown').'">'.dol_escape_htmltag($langs->trans('CreatePowerPlantDropdown')).'</a>';
		print '<span id="'.dol_escape_htmltag($buttonid.'-holder').'" class="powerplantpv-create-powerplant-holder">';
		print dolGetButtonAction($langs->trans('CreatePowerPlant'), $langs->trans('CreatePowerPlantDropdown'), 'default', $url, $buttonid, true);
		print '</span>';
		print '<script nonce="'.getNonce().'">';
		print 'jQuery(function(){';
		print 'var holder=jQuery("#'.dol_escape_js($buttonid.'-holder').'");';
		print 'var createLabel="'.dol_escape_js($langs->transnoentitiesnoconv('Create')).'";';
		print 'var target=jQuery();';
		print 'jQuery(".tabsAction .dropdown-holder").each(function(){';
		print 'var current=jQuery(this);';
		print 'var text=jQuery.trim(current.children(".dropdown-toggle").first().text());';
		print 'if(text===createLabel){target=current.children(".dropdown-content").first();return false;}';
		print '});';
		print 'if(target.length){';
		print 'target.append("'.dol_escape_js($dropdownhtml).'");';
		print 'holder.remove();';
		print '}';
		print '});';
		print '</script>';

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

		$this->results = array();

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

		$this->results['powerplantpv_powerplant'] = array(
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
			'classpath' => 'custom/powerplantpv/class',
			'classfile' => 'powerplant',
			'classname' => 'PowerPlant',
			'dir_output' => $diroutput,
		);
		$hookmanager->resArray = $this->results;

		return 1;
	}

	/**
	 * Add the native category type used by power plants.
	 *
	 * @param	array<string,mixed>	$parameters		Hook parameters
	 * @param	Categorie			$object			Category object
	 * @param	string				$action			Current action
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int									0 on success, <0 on error
	 */
	public function constructCategory($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		if (!isModEnabled('powerplantpv')) {
			return 0;
		}
		$langs->load('powerplantpv@powerplantpv');

		$this->results = array(
			array(
				'id' => 450004,
				'code' => 'powerplant',
				'cat_fk' => 'powerplant',
				'cat_table' => 'powerplant',
				'obj_class' => 'PowerPlant',
				'obj_table' => 'powerplantpv_powerplant',
				'label' => 'PowerPlant',
			),
		);
		$hookmanager->resArray = $this->results;

		return 0;
	}

	/**
	 * Declare PowerPlantPV trigger codes supported by Dolibarr notifications.
	 *
	 * @param	array<string,mixed>	$parameters		Hook parameters
	 * @param	stdClass			$object			Unused object
	 * @param	string				$action			Current action
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int									0 on success, <0 on error
	 */
	public function notifsupported($parameters, &$object, &$action, $hookmanager)
	{
		if (!isModEnabled('powerplantpv')) {
			return 0;
		}

		$this->results = array(
			'arrayofnotifsupported' => array(
				'POWERPLANTPV_POWERPLANT_CREATE',
				'POWERPLANTPV_POWERPLANT_MODIFY',
				'POWERPLANTPV_POWERPLANT_DELETE',
				'POWERPLANTPV_POWERPLANT_VALIDATE',
				'POWERPLANTPV_POWERPLANT_UNVALIDATE',
				'POWERPLANTPV_POWERPLANT_CANCEL',
				'POWERPLANTPV_POWERPLANT_REOPEN',
				'POWERPLANTPV_POWERPLANT_SENTBYMAIL',
				'POWERPLANTPV_POWERPLANT_INSERVICE',
				'POWERPLANTPV_POWERPLANT_OUTOFSERVICE',
				'POWERPLANTPV_POWERPLANT_COMP_MODIFY',
				'POWERPLANTPV_POWERPLANT_COMP_REPLACE',
				'POWERPLANTPV_POWERPLANT_COMP_INSERVICE',
				'POWERPLANTPV_POWERPLANT_COMP_OUTOFSERVICE',
				'POWERPLANTPV_POWERPLANT_COMP_SERIAL',
				'POWERPLANTPV_POWERPLANT_COMP_COMMISSIONING',
			),
		);
		$hookmanager->resArray = $this->results;

		return 0;
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
		$contexts = array();
		if (!empty($parameters['context'])) {
			$contexts = array_merge($contexts, explode(':', (string) $parameters['context']));
		}
		if (!empty($parameters['currentcontext'])) {
			$contexts = array_merge($contexts, explode(':', (string) $parameters['currentcontext']));
		}
		if (!empty($hookmanager->contextarray) && is_array($hookmanager->contextarray)) {
			$contexts = array_merge($contexts, $hookmanager->contextarray);
		}

		return array_values(array_unique(array_filter($contexts)));
	}

	/**
	 * Return the script that inserts the PowerPlant icon before the ticket extrafield selector.
	 *
	 * @return	string	HTML script
	 */
	private function getTicketPowerPlantPictoScript()
	{
		$picto = img_picto('', 'fa-sun', 'class="pictofixedwidth valignmiddle powerplantpv-ticket-powerplant-picto"');

		$html = '<script nonce="'.getNonce().'">';
		$html .= 'jQuery(function(){';
		$html .= 'var picto="'.dol_escape_js($picto).'";';
		$html .= 'jQuery("td.valuefieldcreate.ticket_extras_powerplantpv_powerplant").each(function(){';
		$html .= 'var cell=jQuery(this);';
		$html .= 'if(cell.children(".powerplantpv-ticket-powerplant-picto").length){return;}';
		$html .= 'var target=cell.children(".select2-container").first();';
		$html .= 'if(!target.length){target=cell.children("select[name=\'options_powerplantpv_powerplant\'],input[name=\'options_powerplantpv_powerplant\']").first();}';
		$html .= 'if(target.length){target.before(picto);}else{cell.prepend(picto);}';
		$html .= '});';
		$html .= '});';
		$html .= '</script>';

		return $html;
	}

	/**
	 * Format a price per watt-peak with unit-price precision.
	 *
	 * @param	float	$amount	Amount per Wc
	 * @return	string			Formatted price
	 */
	private function formatPricePerWattPeak($amount)
	{
		global $langs;

		return price((float) $amount, 0, $langs, 1, -1, 4);
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
