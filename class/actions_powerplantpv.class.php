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
					'attestation' => array(
						'type' => 'element',
						'icon' => 'file-signature',
						'lang' => 'powerplantpv@powerplantpv',
						'tooltip' => 'AttestationSharingInfo',
						'enable' => '! empty($conf->powerplantpv->enabled)',
						'input' => array(
							'global' => array(
								'showhide' => true,
								'hide' => true,
								'del' => true,
							),
						),
					),
					'attestationnumber' => array(
						'type' => 'objectnumber',
						'icon' => 'hashtag',
						'lang' => 'powerplantpv@powerplantpv',
						'tooltip' => 'AttestationNumberSharingInfo',
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
					'attestation' => 'powerplantpv',
					'attestationnumber' => 'powerplantpv',
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
					'c_powerplantpv_intervention_nature' => array(
						'type' => 'dictionary',
						'icon' => 'tools',
						'transkey' => 'InterventionNatureDictionary',
						'tooltip' => 'InterventionNatureDictionarySharingInfo',
						'lang' => 'powerplantpv@powerplantpv',
						'filepath' => '/powerplantpv/sql/llx_c_powerplantpv_intervention_nature.sql',
					),
					'c_powerplantpv_maintenance_service' => array(
						'type' => 'dictionary',
						'icon' => 'wrench',
						'transkey' => 'MaintenanceServiceDictionary',
						'tooltip' => 'MaintenanceServiceDictionarySharingInfo',
						'lang' => 'powerplantpv@powerplantpv',
						'filepath' => '/powerplantpv/sql/llx_c_powerplantpv_maintenance_service.sql',
					),
					'c_powerplantpv_index_type' => array(
						'type' => 'dictionary',
						'icon' => 'tachometer',
						'transkey' => 'IndexTypeDictionary',
						'tooltip' => 'IndexTypeDictionarySharingInfo',
						'lang' => 'powerplantpv@powerplantpv',
						'filepath' => '/powerplantpv/sql/llx_c_powerplantpv_index_type.sql',
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
	 * Handle PowerPlantPV quick links on native cards.
	 *
	 * @param	array<string,mixed>	$parameters		Hook parameters
	 * @param	CommonObject		$object			Current object
	 * @param	string				$action			Current action
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int									0 on success, <0 on error
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $user;

		if (!isModEnabled('powerplantpv')) {
			return 0;
		}

		$contexts = $this->getContexts($parameters, $hookmanager);
		if (in_array('ticketcard', $contexts) || in_array('publicnewticketcard', $contexts)) {
			$langs->load('powerplantpv@powerplantpv');
		}
		if ($this->isNativePowerPlantLinkContext($contexts)) {
			$langs->load('powerplantpv@powerplantpv');
			dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');

			$this->prefillInterventionNature($object, $contexts);

			if ($action == 'add' && $this->canEditNativePowerPlantLinks($object, $contexts)) {
				$this->normalizeExternalPowerPlantOriginPost();
				$this->injectNativeLinkedObjectsFromRequest($object, $contexts);
			}

			if ($action == 'powerplantpv_set_powerplants') {
				$managedobject = $this->fetchNativePowerPlantLinkObject($object, $contexts);
				if (!is_object($managedobject) || empty($managedobject->id)) {
					$this->error = 'ErrorRecordNotFound';
					$this->errors[] = $this->error;
					return -1;
				}
				if (!$this->isSubmittedTokenValid()) {
					accessforbidden('Invalid CSRF token');
				}
				if (!$this->canEditNativePowerPlantLinks($managedobject, $contexts)) {
					accessforbidden();
				}

				$selectedids = powerplantpvGetRequestedPowerPlantIds($managedobject, 0);
				$result = powerplantpvSyncNativePowerPlantLinks($managedobject, $selectedids, $user);
				if ($result < 0) {
					setEventMessages($managedobject->error, $managedobject->errors, 'errors');
					return -1;
				}

				setEventMessages($langs->trans('PowerPlantPVPowerPlantsLinked'), null, 'mesgs');
				$url = $_SERVER['PHP_SELF'].'?id='.(int) $managedobject->id;
				header('Location: '.$url);
				exit;
			}
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
		if ($this->isNativePowerPlantLinkContext($contexts)) {
			dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
			$this->prefillInterventionNature($object, $contexts);
			$this->resprints .= $this->renderNativePowerPlantOptionRows($object, $contexts, $parameters);
		}

		if (!in_array('ticketcard', $contexts)) {
			return 0;
		}

		if (GETPOST('action', 'aZ09') != 'create') {
			return 0;
		}

		if (empty($object->element) || $object->element != 'ticket') {
			return 0;
		}

		$this->resprints .= $this->getTicketPowerPlantPictoScript();

		return 0;
	}

	/**
	 * Add the quick native power plant link block on existing contract/intervention cards.
	 *
	 * @param	array<string,mixed>	$parameters		Hook parameters
	 * @param	CommonObject		$object			Current object
	 * @param	string				$action			Current action
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int									0 on success, <0 on error
	 */
	public function formConfirm($parameters, &$object, &$action, $hookmanager)
	{
		$this->resprints = '';

		if (!isModEnabled('powerplantpv')) {
			return 0;
		}

		$contexts = $this->getContexts($parameters, $hookmanager);
		if (!$this->isNativePowerPlantLinkContext($contexts)) {
			return 0;
		}

		dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
		$managedobject = $this->fetchNativePowerPlantLinkObject($object, $contexts);
		if (!is_object($managedobject) || empty($managedobject->id)) {
			return 0;
		}

		$this->syncAfterNativeCreationWhenNeeded($managedobject, $contexts, $action);

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
	 * Map PowerPlantPV objects for UserNavHistory object reload.
	 *
	 * @param	array<string,mixed>	$parameters		Hook parameters
	 * @param	CommonObject		$object			Current object
	 * @param	string				$action			Current action
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int									0 on success
	 */
	public function getObjectByElement($parameters, &$object, &$action, $hookmanager)
	{
		if (!isModEnabled('powerplantpv')) {
			return 0;
		}

		$contexts = $this->getContexts($parameters, $hookmanager);
		if (!in_array('usernavhistorydao', $contexts, true)) {
			return 0;
		}

		$elementtype = !empty($parameters['elementtype']) ? (string) $parameters['elementtype'] : '';
		if (in_array($elementtype, array('powerplantpv_attestation', 'attestation@powerplantpv', 'attestation'), true)) {
			$mapping = array(
				'module' => 'powerplantpv',
				'classpath' => 'custom/powerplantpv/class',
				'classfile' => 'powerplantpvattestation',
				'classname' => 'PowerPlantPVAttestation',
			);
		} elseif (in_array($elementtype, array('powerplantpv_powerplant', 'powerplant@powerplantpv', 'powerplant'), true)) {
			$mapping = array(
				'module' => 'powerplantpv',
				'classpath' => 'custom/powerplantpv/class',
				'classfile' => 'powerplant',
				'classname' => 'PowerPlant',
			);
		} else {
			return 0;
		}

		foreach ($mapping as $key => $value) {
			if (array_key_exists($key, $parameters)) {
				$parameters[$key] = $value;
			}
		}

		$this->results = array('elementtype' => $elementtype) + $mapping;
		$hookmanager->resArray = $this->results;

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

		if (empty($parameters['elementType']) || !in_array($parameters['elementType'], array('powerplantpv_powerplant', 'powerplant@powerplantpv', 'powerplant', 'powerplantpv_attestation', 'attestation@powerplantpv', 'attestation'))) {
			return 0;
		}

		$diroutput = '';
		if (isset($conf->powerplantpv) && !empty($conf->powerplantpv->dir_output)) {
			$diroutput = $conf->powerplantpv->dir_output;
		}

		if (in_array($parameters['elementType'], array('powerplantpv_attestation', 'attestation@powerplantpv', 'attestation'))) {
			$this->results = array(
				'module' => 'powerplantpv',
				'element' => 'attestation',
				'table_element' => 'powerplantpv_attestation',
				'subelement' => 'attestation',
				'classpath' => 'custom/powerplantpv/class',
				'classfile' => 'powerplantpvattestation',
				'classname' => 'PowerPlantPVAttestation',
				'dir_output' => $diroutput,
			);
		} else {
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
		}
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
				'POWERPLANTPV_ATTESTATION_CREATE',
				'POWERPLANTPV_ATTESTATION_VALIDATE',
				'POWERPLANTPV_ATTESTATION_GENERATEPDF',
				'POWERPLANTPV_ATTESTATION_SENDSIGN',
				'POWERPLANTPV_ATTESTATION_SIGN',
				'POWERPLANTPV_ATTESTATION_CANCEL',
				'POWERPLANTPV_ATTESTATION_DELETE',
			),
		);
		$hookmanager->resArray = $this->results;

		return 0;
	}

	/**
	 * Check if the current hook context is a supported native quick link context.
	 *
	 * @param	string[]	$contexts	Hook contexts
	 * @return	bool				True if supported
	 */
	private function isNativePowerPlantLinkContext($contexts)
	{
		return in_array('contractcard', $contexts, true)
			|| in_array('interventioncard', $contexts, true)
			|| in_array('fichintercard', $contexts, true);
	}

	/**
	 * Return the native linked-object type for the current hook contexts.
	 *
	 * @param	string[]	$contexts	Hook contexts
	 * @return	string				Native linked-object type
	 */
	private function getNativePowerPlantLinkElementType($contexts)
	{
		if (in_array('contractcard', $contexts, true)) {
			return 'contrat';
		}
		if (in_array('interventioncard', $contexts, true) || in_array('fichintercard', $contexts, true)) {
			return 'fichinter';
		}

		return '';
	}

	/**
	 * Fetch the object currently handled by a native quick link hook.
	 *
	 * @param	CommonObject	$object		Hook object
	 * @param	string[]			$contexts	Hook contexts
	 * @return	CommonObject|null			Fetched object or null
	 */
	private function fetchNativePowerPlantLinkObject($object, $contexts)
	{
		global $db;

		if (is_object($object) && !empty($object->id) && function_exists('powerplantpvSupportsNativePowerPlantLinks') && powerplantpvSupportsNativePowerPlantLinks($object)) {
			return $object;
		}

		$id = GETPOSTINT('id');
		if ($id <= 0) {
			return null;
		}

		$elementtype = $this->getNativePowerPlantLinkElementType($contexts);
		if ($elementtype == 'contrat') {
			dol_include_once('/contrat/class/contrat.class.php');
			if (!class_exists('Contrat')) {
				return null;
			}
			$linkedobject = new Contrat($db);
		} elseif ($elementtype == 'fichinter') {
			dol_include_once('/fichinter/class/fichinter.class.php');
			if (!class_exists('Fichinter')) {
				return null;
			}
			$linkedobject = new Fichinter($db);
		} else {
			return null;
		}

		$result = $linkedobject->fetch($id);
		if ($result <= 0) {
			return null;
		}

		return $linkedobject;
	}

	/**
	 * Check display permissions for quick power plant links.
	 *
	 * @param	CommonObject	$object		Object
	 * @param	string[]			$contexts	Hook contexts
	 * @return	bool						True if allowed
	 */
	private function canViewNativePowerPlantLinks($object, $contexts)
	{
		global $user;

		if (!is_object($object) || !function_exists('powerplantpvUserHasRightPath')) {
			return false;
		}
		if (!powerplantpvUserHasRightPath($user, array('powerplantpv', 'powerplant', 'read'))) {
			return false;
		}
		if (!powerplantpvUserHasMaintenanceRight($user, 'read')) {
			return false;
		}

		$elementtype = $this->getNativePowerPlantLinkElementType($contexts);
		if ($elementtype == 'contrat') {
			return (bool) powerplantpvUserHasRightPath($user, array('contrat', 'lire'));
		}
		if ($elementtype == 'fichinter') {
			return (bool) powerplantpvUserHasRightPath($user, array('ficheinter', 'lire'));
		}

		return false;
	}

	/**
	 * Check edit permissions for quick power plant links.
	 *
	 * @param	CommonObject	$object		Object
	 * @param	string[]			$contexts	Hook contexts
	 * @return	bool						True if allowed
	 */
	private function canEditNativePowerPlantLinks($object, $contexts)
	{
		global $user;

		if (!$this->canViewNativePowerPlantLinks($object, $contexts)) {
			return false;
		}
		if (!powerplantpvUserHasMaintenanceRight($user, 'write')) {
			return false;
		}

		$elementtype = $this->getNativePowerPlantLinkElementType($contexts);
		if ($elementtype == 'contrat') {
			return (bool) powerplantpvUserHasRightPath($user, array('contrat', 'creer'));
		}
		if ($elementtype == 'fichinter') {
			return (bool) powerplantpvUserHasRightPath($user, array('ficheinter', 'creer'));
		}

		return false;
	}

	/**
	 * Apply the requested third party to the temporary hook object when core has not done it yet.
	 *
	 * @param	CommonObject	$object	Hook object
	 * @return	void
	 */
	private function applyRequestThirdPartyToObject(&$object)
	{
		if (!is_object($object)) {
			return;
		}

		$socid = GETPOSTINT('socid') > 0 ? GETPOSTINT('socid') : GETPOSTINT('fk_soc');
		if ($socid <= 0) {
			return;
		}
		if (empty($object->socid)) {
			$object->socid = $socid;
		}
		if (empty($object->fk_soc)) {
			$object->fk_soc = $socid;
		}
	}

	/**
	 * Render the power plant selector row on native creation forms.
	 *
	 * @param	CommonObject		$object		Hook object
	 * @param	string[]				$contexts	Hook contexts
	 * @param	array<string,mixed>	$parameters	Hook parameters
	 * @return	string							HTML
	 */
	private function renderNativePowerPlantCreateRow(&$object, $contexts, $parameters)
	{
		global $langs;

		if (GETPOST('action', 'aZ09') != 'create') {
			return '';
		}
		$this->applyRequestThirdPartyToObject($object);
		if (!$this->canEditNativePowerPlantLinks($object, $contexts)) {
			return '';
		}

		$selectedids = powerplantpvGetRequestedPowerPlantIds($object, 0);
		$options = powerplantpvGetSelectablePowerPlantOptions($object, $selectedids);
		$colspan = !empty($parameters['colspan']) ? (string) $parameters['colspan'] : '';
		$contractid = $this->getRequestedContractId();
		$interventionnatureid = $this->getInterventionNatureIdFromRequest();

		$html = '<tr class="powerplantpv-quick-powerplants">';
		$html .= '<td class="titlefieldcreate">'.$langs->trans('PowerPlantPVCentrals').'</td>';
		$html .= '<td'.$colspan.'>';
		$html .= $this->renderPowerPlantMultiselect('powerplantpv_powerplants', $options, $selectedids);
		if ($contractid > 0) {
			$html .= '<input type="hidden" name="fk_contrat" value="'.((int) $contractid).'">';
		}
		if ($interventionnatureid > 0) {
			$html .= '<input type="hidden" name="options_powerplantpv_intervention_nature" value="'.((int) $interventionnatureid).'">';
		}
		if (in_array('interventioncard', $contexts, true) || in_array('fichintercard', $contexts, true)) {
			$html .= $this->renderMaintenancePeriodHiddenInputs();
		}
		$html .= '</td>';
		$html .= '</tr>';

		if (in_array('interventioncard', $contexts, true) || in_array('fichintercard', $contexts, true)) {
			$html .= $this->renderMaintenancePeriodCreateRow($colspan);
		}

		return $html;
	}

	/**
	 * Render the power plant row on native contract/intervention cards.
	 *
	 * @param	CommonObject		$object		Hook object
	 * @param	string[]				$contexts	Hook contexts
	 * @param	array<string,mixed>	$parameters	Hook parameters
	 * @return	string							HTML
	 */
	private function renderNativePowerPlantOptionRows(&$object, $contexts, $parameters)
	{
		if (GETPOST('action', 'aZ09') == 'create') {
			return $this->renderNativePowerPlantCreateRow($object, $contexts, $parameters);
		}

		$managedobject = $this->fetchNativePowerPlantLinkObject($object, $contexts);
		if (!is_object($managedobject) || empty($managedobject->id)) {
			return '';
		}

		return $this->renderNativePowerPlantOptionRow($managedobject, $contexts, $parameters);
	}

	/**
	 * Render the read-only or inline-edit power plant row on existing native cards.
	 *
	 * @param	CommonObject		$object		Object
	 * @param	string[]				$contexts	Hook contexts
	 * @param	array<string,mixed>	$parameters	Hook parameters
	 * @return	string							HTML
	 */
	private function renderNativePowerPlantOptionRow($object, $contexts, $parameters)
	{
		global $langs;

		if (!$this->canViewNativePowerPlantLinks($object, $contexts)) {
			return '';
		}

		$powerplants = powerplantpvGetLinkedPowerPlants($object);
		$selectedids = array();
		foreach ($powerplants as $powerplantid => $powerplant) {
			$selectedids[] = (int) $powerplantid;
		}

		$canedit = $this->canEditNativePowerPlantLinks($object, $contexts);
		$isedit = ($canedit && GETPOST('action', 'aZ09') == 'powerplantpv_edit_powerplants');
		$colspan = !empty($parameters['colspan']) ? (string) $parameters['colspan'] : '';

		$html = '<tr class="oddeven powerplantpv-quick-powerplants">';
		$html .= '<td class="titlefield">'.$langs->trans('PowerPlantPVCentrals');
		if ($canedit && !$isedit) {
			$url = $_SERVER['PHP_SELF'].'?id='.((int) $object->id).'&action=powerplantpv_edit_powerplants&token='.newToken();
			$html .= ' <a class="editfielda" href="'.dol_escape_htmltag($url).'">'.img_edit($langs->transnoentitiesnoconv('Modify'), 0).'</a>';
		}
		$html .= '</td>';
		$html .= '<td'.$colspan.'>';
		if ($isedit) {
			$options = powerplantpvGetSelectablePowerPlantOptions($object, $selectedids);
			$html .= '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.((int) $object->id).'">';
			$html .= '<input type="hidden" name="token" value="'.newToken().'">';
			$html .= '<input type="hidden" name="action" value="powerplantpv_set_powerplants">';
			$html .= '<input type="hidden" name="id" value="'.((int) $object->id).'">';
			$html .= $this->renderPowerPlantMultiselect('powerplantpv_powerplants', $options, $selectedids);
			$html .= ' <input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Modify')).'">';
			$html .= '</form>';
		} else {
			$html .= $this->renderLinkedPowerPlantList($powerplants);
		}
		$html .= '</td>';
		$html .= '</tr>';

		return $html;
	}

	/**
	 * Render hidden maintenance period inputs on native intervention creation forms.
	 *
	 * @return	string	HTML
	 */
	private function renderMaintenancePeriodHiddenInputs()
	{
		$period = $this->getRequestedMaintenancePeriod();
		if (empty($period['start']) || empty($period['end'])) {
			return '';
		}

		$html = '<input type="hidden" name="powerplantpv_maintenance_period_start" value="'.dol_escape_htmltag($period['start']).'">';
		$html .= '<input type="hidden" name="powerplantpv_maintenance_period_end" value="'.dol_escape_htmltag($period['end']).'">';

		return $html;
	}

	/**
	 * Render maintenance period context row on native intervention creation forms.
	 *
	 * @param	string	$colspan	Colspan attribute
	 * @return	string				HTML
	 */
	private function renderMaintenancePeriodCreateRow($colspan)
	{
		global $langs;

		$period = $this->getRequestedMaintenancePeriod();
		if (empty($period['start_timestamp']) || empty($period['end_timestamp'])) {
			return '';
		}

		$html = '<tr class="powerplantpv-maintenance-period">';
		$html .= '<td class="titlefieldcreate">'.$langs->trans('PowerPlantPVMaintenanceCoveredPeriod').'</td>';
		$html .= '<td'.$colspan.'>';
		$html .= img_picto('', 'fa-calendar', 'class="pictofixedwidth"');
		$html .= dol_print_date((int) $period['start_timestamp'], 'day').' - '.dol_print_date((int) $period['end_timestamp'], 'day');
		$html .= '</td>';
		$html .= '</tr>';

		return $html;
	}

	/**
	 * Return requested maintenance period context.
	 *
	 * @return	array{start:string,end:string,start_timestamp:int,end_timestamp:int}	Period values
	 */
	private function getRequestedMaintenancePeriod()
	{
		$start = GETPOST('powerplantpv_maintenance_period_start', 'alphanohtml');
		$end = GETPOST('powerplantpv_maintenance_period_end', 'alphanohtml');
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
			return array('start' => '', 'end' => '', 'start_timestamp' => 0, 'end_timestamp' => 0);
		}

		$startParts = explode('-', $start);
		$endParts = explode('-', $end);
		$startTimestamp = dol_mktime(0, 0, 0, (int) $startParts[1], (int) $startParts[2], (int) $startParts[0]);
		$endTimestamp = dol_mktime(23, 59, 59, (int) $endParts[1], (int) $endParts[2], (int) $endParts[0]);

		return array(
			'start' => $start,
			'end' => $end,
			'start_timestamp' => (int) $startTimestamp,
			'end_timestamp' => (int) $endTimestamp,
		);
	}

	/**
	 * Render a native multiselect for power plant ids.
	 *
	 * @param	string				$htmlname		HTML field name
	 * @param	array<int,string>	$options		Available options
	 * @param	int[]				$selectedids	Selected ids
	 * @return	string								HTML
	 */
	private function renderPowerPlantMultiselect($htmlname, $options, $selectedids)
	{
		global $db, $langs;

		if (empty($options)) {
			return '<span class="opacitymedium">'.$langs->trans('NoPowerPlantLinked').'</span>';
		}

		if (!class_exists('Form')) {
			require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
		}
		$form = new Form($db);

		return img_picto('', 'fa-sun', 'class="pictofixedwidth"')
			.$form->multiselectarray($htmlname, $options, $selectedids, 0, 0, 'minwidth300 maxwidth500 widthcentpercentminusxx', 0, 0);
	}

	/**
	 * Render linked power plants as native object links.
	 *
	 * @param	array<int,PowerPlant>	$powerplants	Linked power plants
	 * @return	string								HTML
	 */
	private function renderLinkedPowerPlantList($powerplants)
	{
		global $langs;

		if (empty($powerplants)) {
			return '<span class="opacitymedium">'.$langs->trans('NoPowerPlantLinked').'</span>';
		}

		$html = '';
		foreach ($powerplants as $powerplant) {
			if (!is_object($powerplant)) {
				continue;
			}
			$html .= '<div>';
			if (method_exists($powerplant, 'getNomUrl')) {
				$html .= $powerplant->getNomUrl(1);
			} elseif (!empty($powerplant->ref)) {
				$html .= dol_escape_htmltag((string) $powerplant->ref);
			} else {
				$html .= '#'.((int) powerplantpvGetCommonObjectId($powerplant));
			}
			$html .= '</div>';
		}

		return $html !== '' ? $html : '<span class="opacitymedium">'.$langs->trans('NoPowerPlantLinked').'</span>';
	}

	/**
	 * Synchronize links after a native object creation that did not process every linked object itself.
	 *
	 * @param	CommonObject	$object		Object
	 * @param	string[]			$contexts	Hook contexts
	 * @param	string			$action		Current action
	 * @return	void
	 */
	private function syncAfterNativeCreationWhenNeeded($object, $contexts, $action)
	{
		global $user;

		static $synced = array();

		if ($action != 'add' || !$this->canEditNativePowerPlantLinks($object, $contexts)) {
			return;
		}

		$key = $this->getNativePowerPlantLinkElementType($contexts).':'.((int) $object->id);
		if (!empty($synced[$key])) {
			return;
		}

		$selectedids = powerplantpvGetRequestedPowerPlantIds($object, 0);
		if (empty($selectedids)) {
			return;
		}

		$synced[$key] = 1;
		$result = powerplantpvSyncNativePowerPlantLinks($object, $selectedids, $user);
		if ($result < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}

	/**
	 * Inject sanitized linked objects into the native core creation payload.
	 *
	 * @param	CommonObject	$object		Hook object
	 * @param	string[]			$contexts	Hook contexts
	 * @return	void
	 */
	private function injectNativeLinkedObjectsFromRequest(&$object, $contexts)
	{
		$this->applyRequestThirdPartyToObject($object);

		$powerplantids = powerplantpvGetRequestedPowerPlantIds($object, 0);
		if (!empty($powerplantids)) {
			$powerplantids = powerplantpvFilterSelectablePowerPlantIds($powerplantids, $object, array());
			if (!empty($powerplantids)) {
				$this->mergeOtherLinkedObjectsPost(powerplantpvGetCanonicalPowerPlantLinkType(), $powerplantids);
			}
		}

		$elementtype = $this->getNativePowerPlantLinkElementType($contexts);
		if ($elementtype == 'fichinter') {
			$contractid = $this->getRequestedContractId();
			$origin = powerplantpvNormalizeElementType(GETPOST('origin', 'alphanohtml'));
			$originid = GETPOSTINT('originid') > 0 ? GETPOSTINT('originid') : GETPOSTINT('origin_id');
			if ($contractid > 0 && !($origin == 'contrat' && $originid == $contractid)) {
				$this->mergeOtherLinkedObjectsPost('contrat', array($contractid));
			}
		}
	}

	/**
	 * Keep external PowerPlantPV origins resolvable by Dolibarr during native intervention creation.
	 *
	 * @return	void
	 */
	private function normalizeExternalPowerPlantOriginPost()
	{
		$origin = GETPOST('origin', 'alphanohtml');
		$originid = GETPOSTINT('originid') > 0 ? GETPOSTINT('originid') : GETPOSTINT('origin_id');
		if ($originid <= 0 || !in_array($origin, array('powerplant', 'powerplant@powerplantpv', 'powerplantpv_powerplant'), true)) {
			return;
		}

		$selectedids = powerplantpvGetRequestedPowerPlantIds(null, 0);
		if (!in_array($originid, $selectedids, true)) {
			return;
		}

		$_POST['origin'] = powerplantpvGetCanonicalPowerPlantLinkType();
		$_REQUEST['origin'] = powerplantpvGetCanonicalPowerPlantLinkType();
	}

	/**
	 * Merge linked object ids into the native other_linked_objects POST array.
	 *
	 * @param	string	$elementtype	Linked object type
	 * @param	int[]	$ids			Linked object ids
	 * @return	void
	 */
	private function mergeOtherLinkedObjectsPost($elementtype, $ids)
	{
		$ids = powerplantpvSanitizeIdArray($ids);
		if (empty($elementtype) || empty($ids)) {
			return;
		}

		$otherlinkedobjects = GETPOST('other_linked_objects', 'array:int');
		if (!is_array($otherlinkedobjects)) {
			$otherlinkedobjects = array();
		}
		$existingids = array();
		if (!empty($otherlinkedobjects[$elementtype])) {
			$existingids = powerplantpvSanitizeIdArray($otherlinkedobjects[$elementtype]);
		}
		$otherlinkedobjects[$elementtype] = array_values(array_unique(array_merge($existingids, $ids)));

		$_POST['other_linked_objects'] = $otherlinkedobjects;
		$_REQUEST['other_linked_objects'] = $otherlinkedobjects;
	}

	/**
	 * Return the contract id carried by the current request.
	 *
	 * @return	int	Contract id
	 */
	private function getRequestedContractId()
	{
		$contractid = GETPOSTINT('fk_contrat') > 0 ? GETPOSTINT('fk_contrat') : GETPOSTINT('contratid');
		$origin = powerplantpvNormalizeElementType(GETPOST('origin', 'alphanohtml'));
		$originid = GETPOSTINT('originid') > 0 ? GETPOSTINT('originid') : GETPOSTINT('origin_id');
		if ($origin == 'contrat' && $originid > 0) {
			$contractid = $originid;
		}

		return (int) $contractid;
	}

	/**
	 * Prefill the PowerPlantPV intervention nature extrafield when a request asks for it.
	 *
	 * @param	CommonObject	$object		Hook object
	 * @param	string[]			$contexts	Hook contexts
	 * @return	void
	 */
	private function prefillInterventionNature(&$object, $contexts)
	{
		if (!in_array('interventioncard', $contexts, true) && !in_array('fichintercard', $contexts, true)) {
			return;
		}

		$natureid = $this->getInterventionNatureIdFromRequest();
		if ($natureid <= 0 || !is_object($object)) {
			return;
		}

		if (empty($object->array_options) || !is_array($object->array_options)) {
			$object->array_options = array();
		}
		$object->array_options['options_powerplantpv_intervention_nature'] = $natureid;
		$_POST['options_powerplantpv_intervention_nature'] = $natureid;
		$_REQUEST['options_powerplantpv_intervention_nature'] = $natureid;
	}

	/**
	 * Return an intervention nature id requested by id or code.
	 *
	 * @return	int	Nature row id
	 */
	private function getInterventionNatureIdFromRequest()
	{
		global $db;

		$natureid = GETPOSTINT('powerplantpv_intervention_nature');
		if ($natureid > 0) {
			return $natureid;
		}

		$naturecode = GETPOST('powerplantpv_intervention_nature_code', 'alphanohtml');
		if ($naturecode === '') {
			return 0;
		}

		$sql = "SELECT rowid";
		$sql .= " FROM ".$db->prefix()."c_powerplantpv_intervention_nature";
		$sql .= " WHERE code = '".$db->escape($naturecode)."'";
		$sql .= " AND active = 1";
		$sql .= " AND entity IN (".getEntity('c_powerplantpv_intervention_nature').")";
		$sql .= " ORDER BY entity DESC";
		$resql = $db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' failed to read intervention nature '.$naturecode.': '.$db->lasterror(), LOG_WARNING);
			return 0;
		}

		$id = 0;
		if ($obj = $db->fetch_object($resql)) {
			$id = (int) $obj->rowid;
		}
		$db->free($resql);

		return $id;
	}

	/**
	 * Validate the submitted token without weakening Dolibarr's global CSRF check.
	 *
	 * @return	bool	True if token is valid enough for the current Dolibarr version
	 */
	private function isSubmittedTokenValid()
	{
		$token = GETPOST('token', 'alphanohtml');
		if ($token === '') {
			return false;
		}
		if (function_exists('dol_verifyToken')) {
			return (bool) dol_verifyToken($token);
		}
		$valid = false;
		if (function_exists('currentToken')) {
			$valid = hash_equals((string) currentToken(), (string) $token);
		}
		if (!$valid && !empty($_SESSION['newtoken'])) {
			$valid = hash_equals((string) $_SESSION['newtoken'], (string) $token);
		}

		return $valid || !function_exists('currentToken');
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
