<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		class/powerplantpvpowerplantsummary.class.php
 * \ingroup		powerplantpv
 * \brief		Power plant technical summary snapshot builder.
 */

dol_include_once('/powerplantpv/class/powerplant.class.php');
require_once __DIR__.'/powerplantpvreportpresentation.class.php';
dol_include_once('/powerplantpv/lib/powerplantpv_serialnumber.lib.php');
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

/**
 * Power plant technical summary snapshot builder.
 */
class PowerPlantPVPowerPlantSummary
{
	/**
	 * @var DoliDB Database handler
	 */
	private $db;

	/**
	 * @var string Error message
	 */
	public $error = '';

	/**
	 * @var array<int,string> Error messages
	 */
	public $errors = array();

	/**
	 * Constructor.
	 *
	 * @param	DoliDB	$db	Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/**
	 * Build a frozen display snapshot from a power plant id.
	 *
	 * @param	int					$powerplantId	Power plant id
	 * @param	Translate|null		$outputlangs	Output language
	 * @return	array<string,mixed>					Snapshot, empty array on error
	 */
	public function buildSnapshotById($powerplantId, $outputlangs = null, array $completion = array())
	{
		$this->error = '';
		$this->errors = array();
		$powerplantId = (int) $powerplantId;
		if ($powerplantId <= 0) {
			return array();
		}

		$powerplant = new PowerPlant($this->db);
		$result = $powerplant->fetch($powerplantId);
		if ($result <= 0) {
			$this->error = $result < 0 ? (string) $powerplant->error : 'Power plant not found';
			if (!empty($powerplant->errors) && is_array($powerplant->errors)) {
				$this->errors = array_map('strval', $powerplant->errors);
			}
			if ($this->error !== '') {
				$this->errors[] = $this->error;
			}
			return array();
		}

		return $this->buildSnapshot($powerplant, $outputlangs, $completion);
	}

	/**
	 * Build a frozen display snapshot from a loaded power plant.
	 *
	 * @param	PowerPlant			$powerplant		Power plant
	 * @param	Translate|null		$outputlangs	Output language
	 * @return	array<string,mixed>					Snapshot
	 */
	public function buildSnapshot($powerplant, $outputlangs = null, array $completion = array())
	{
		global $langs;

		$this->error = '';
		$this->errors = array();
		$translator = is_object($outputlangs) ? $outputlangs : $langs;
		if (!is_object($translator)) {
			$this->error = 'Missing Dolibarr translator';
			$this->errors[] = $this->error;
			return array();
		}

		$components = $this->fetchComponents($powerplant, $translator);
		if ($completion) {
			$powerplant = clone $powerplant;
			if ($completion['prm'] !== '') {
				$powerplant->prm_pdl_number = $completion['prm'];
			}
			if ($completion['type'] !== '') {
				$powerplant->connection_type = $completion['type'];
			}
		}
		$thirdparty = $this->fetchThirdpartyName($powerplant);
		$address = $this->formatPowerPlantAddress($powerplant, $translator);
		$installedPower = $this->formatNumber($powerplant->installed_power, 2, $translator);
		if ($installedPower !== '') {
			$installedPower .= ' kWc';
		}
		$connectionPower = $this->formatNumber($powerplant->connection_contract_power, 2, $translator);
		if ($connectionPower !== '') {
			$connectionPower .= ' kVA';
		}
		$buybackTariff = $this->formatNumber($powerplant->buyback_tariff, 4, $translator);
		if ($buybackTariff !== '') {
			$buybackTariff .= ' €';
		}
		$connectionType = class_exists('PowerPlant') ? PowerPlant::getConnectionTypeLabel($powerplant->connection_type, $translator) : (string) $powerplant->connection_type;

		$powerplantId = (int) $powerplant->id;
		$sections = array(
			array(
				'code' => 'site',
				'title_key' => 'PowerPlantPDFSectionSite',
				'type' => 'key_value',
				'rows' => $this->filterSnapshotRows(array(
					$this->keyValueRow('PowerPlantPDFReference', $powerplant->ref),
					$this->keyValueRow('PowerPlantPDFLabel', $powerplant->label),
					$this->keyValueRow('PowerPlantPDFThirdParty', $thirdparty),
					$this->keyValueRow('PowerPlantPDFAddress', $address),
					$this->keyValueRow('PowerPlantPDFStatusPowerPlant', $this->statusText($powerplant)),
					$this->keyValueRow('PowerPlantPDFCommissioningDate', $this->formatDate($powerplant->commissioning_date, $translator)),
					$this->keyValueRow('PowerPlantPDFDescription', $powerplant->description),
				)),
			),
			array(
				'code' => 'connection',
				'title_key' => 'PowerPlantPDFSectionConnection',
				'type' => 'key_value',
				'rows' => $this->filterSnapshotRows(array(
					$this->keyValueRow('PowerPlantPDFPrmPdl', $powerplant->prm_pdl_number),
					$this->keyValueRow('PowerPlantPDFConnectionContractPower', $connectionPower),
					$this->keyValueRow('PowerPlantPDFConnectionType', $connectionType),
					$this->keyValueRow('PowerPlantPDFEnedisCommissioningDate', $this->formatDate($powerplant->enedis_commissioning_date, $translator)),
					$this->keyValueRow('PowerPlantPDFConnectionRequestNumber', $powerplant->connection_request_number),
					$this->keyValueRow('PowerPlantPDFT0ObtentionDate', $this->formatDate($powerplant->t0_obtention_date, $translator)),
				)),
			),
			array(
				'code' => 'operation',
				'title_key' => 'PowerPlantPDFSectionOperation',
				'type' => 'key_value',
				'rows' => $this->filterSnapshotRows(array(
					$this->keyValueRow('PowerPlantPDFInstalledPower', $installedPower),
					$this->keyValueRow('PowerPlantPDFBuybackContractNumber', $powerplant->buyback_contract_number),
					$this->keyValueRow('PowerPlantPDFBuybackTariff', $buybackTariff),
				)),
			),
			array(
				'code' => 'contacts',
				'title_key' => 'PowerPlantPVReportPowerPlantSummaryUsefulContacts',
				'type' => 'table',
				'columns' => $this->columns(array('role', 'name', 'phone', 'email'), array('PowerPlantPDFRole', 'PowerPlantPDFName', 'PowerPlantPDFPhone', 'PowerPlantPDFEmail')),
				'rows' => $this->fetchContacts($powerplant, $translator),
			),
			array(
				'code' => 'material',
				'title_key' => 'PowerPlantPDFSectionMaterial',
				'type' => 'grouped_tables',
				'tables' => array(
					array(
						'title_key' => 'PowerPlantPDFModules',
						'columns' => $this->columns(array('product', 'qty', 'power', 'serial', 'status'), array('PowerPlantPDFProduct', 'PowerPlantPDFQuantity', 'PowerPlantPDFPower', 'PowerPlantPDFSerialNumber', 'PowerPlantPDFStatus')),
						'rows' => $this->buildMaterialRows($components, array('MODULE'), $translator, 'module', $powerplantId),
					),
					array(
						'title_key' => 'PowerPlantPDFInverters',
						'columns' => $this->columns(array('product', 'qty', 'power', 'serial', 'status'), array('PowerPlantPDFProduct', 'PowerPlantPDFQuantity', 'PowerPlantPDFPower', 'PowerPlantPDFSerialNumber', 'PowerPlantPDFStatus')),
						'rows' => $this->buildMaterialRows($components, array('ONDULE'), $translator, 'inverter', $powerplantId),
					),
				),
			),
			array(
				'code' => 'composition',
				'title_key' => 'PowerPlantPDFSectionComposition',
				'type' => 'table',
				'columns' => $this->columns(array('category', 'product', 'qty', 'serial', 'date', 'status'), array('PowerPlantPDFCategory', 'PowerPlantPDFProduct', 'PowerPlantPDFQuantity', 'PowerPlantPDFSerialNumber', 'PowerPlantPDFDate', 'PowerPlantPDFStatus')),
				'rows' => $this->buildCompositionRows($components, $translator),
			),
		);

		if ($completion) {
			$sections[] = $this->completionSection($completion);
		}
		$sections = array_values(array_filter($sections, array($this, 'snapshotSectionHasContent')));

		return array(
			'version' => 2,
			'source_powerplant_id' => !empty($powerplant->id) ? (int) $powerplant->id : 0,
			'source_powerplant_ref' => (string) $powerplant->ref,
			'serial_inventory' => $this->fetchPanelSerialInventory($powerplant, $components),
			'sections' => $sections,
		);
	}

	/**
	 * Decode a stored JSON snapshot.
	 *
	 * @param	mixed	$json	Stored JSON
	 * @param Translate|null $outputlangs Display language, defaults to the current language
	 * @return	array<string,mixed>	Snapshot
	 */
	public function decodeSnapshot($json, $outputlangs = null)
	{
		$json = trim((string) $json);
		if ($json === '') {
			return array();
		}

		$data = json_decode($json, true);
		if (!is_array($data)) {
			return array();
		}

		// Version 1 stored already formatted rows, including individual serials and a
		// duplicate full composition. Adapt only the display; never refresh frozen data.
		if ((int) ($data['version'] ?? 1) < 2 && isset($data['sections']) && is_array($data['sections'])) {
			global $langs;
			$translator = is_object($outputlangs) ? $outputlangs : $langs;
			if (!is_object($translator)) { return $data; }
			$materialProducts = array();
			foreach ($data['sections'] as &$section) {
				if (!is_array($section) || ($section['code'] ?? '') !== 'material' || !is_array($section['tables'] ?? null)) { continue; }
				foreach ($section['tables'] as &$table) {
					if (!is_array($table) || !in_array($table['title_key'] ?? '', array('PowerPlantPDFModules', 'PowerPlantPDFInverters'), true) || !is_array($table['rows'] ?? null)) { continue; }
					$groups = array();
					foreach ($table['rows'] as $index => $row) {
						if (!is_array($row)) { continue; }
						$product = (string) ($row['product'] ?? '');
						if ($product !== '') { $materialProducts[$product] = true; }
						// The old formatter always wrote two decimal places. Decode that
						// persisted format independently of the current reader's language.
						$quantity = preg_replace('/[\s\x{00A0}\x{202F}]/u', '', (string) ($row['qty'] ?? ''));
						$numericQuantity = null;
						if (preg_match('/^(-?[0-9.,]+)[.,]([0-9]{2})$/', (string) $quantity, $parts)) {
							$integer = str_replace(array('.', ','), '', $parts[1]);
							if (preg_match('/^-?[0-9]+$/', $integer)) {
								$numericQuantity = (float) price2num($integer.'.'.$parts[2], '', 1);
							}
						} elseif (preg_match('/^-?[0-9]+$/', (string) $quantity)) {
							$numericQuantity = (float) price2num($quantity, '', 1);
						}
						$key = $product !== '' && $numericQuantity !== null ? $product : ':row:'.$index;
						$row['serial'] = $translator->transnoentities('PowerPlantPVConsultPowerplant');
						$row['serial_powerplant_id'] = (int) ($data['source_powerplant_id'] ?? 0);
						if (!isset($groups[$key])) {
							$groups[$key] = array('row' => $row, 'quantity' => $numericQuantity);
						} else {
							$groups[$key]['quantity'] += $numericQuantity;
							if (($groups[$key]['row']['status'] ?? '') !== ($row['status'] ?? '')) {
								$groups[$key]['row']['status'] = $translator->transnoentities('PowerPlantPVMultipleStatuses');
							}
						}
					}
					$table['rows'] = array();
					foreach ($groups as $group) {
						if ($group['quantity'] !== null) { $group['row']['qty'] = $this->formatNumber($group['quantity'], 2, $translator); }
						$table['rows'][] = $group['row'];
					}
				}
				unset($table);
			}
			unset($section);
			foreach ($data['sections'] as &$section) {
				if (is_array($section) && ($section['code'] ?? '') === 'composition' && is_array($section['rows'] ?? null)) {
					$section['rows'] = array_values(array_filter($section['rows'], static function ($row) use ($materialProducts) {
						return !is_array($row) || !isset($materialProducts[(string) ($row['product'] ?? '')]);
					}));
				}
			}
			unset($section);
		}

		return $data;
	}

	/**
	 * Test if a snapshot contains displayable sections.
	 *
	 * @param	array<string,mixed>	$snapshot	Snapshot
	 * @return	bool							True if empty
	 */
	public function isEmptySnapshot($snapshot)
	{
		return empty($snapshot['sections']) || !is_array($snapshot['sections']);
	}

	/**
	 * Fetch material composition rows.
	 *
	 * @param	PowerPlant	$powerplant		Power plant
	 * @param	Translate	$outputlangs	Output language
	 * @return	array<int,array<string,mixed>>	Rows
	 */
	private function completionSection(array $completion): array
	{
		require_once __DIR__.'/powerplantpvreportplantcompletion.class.php';
		$service = new PowerPlantPVReportPlantCompletion($this->db);
		$catalog = $service->catalog();
		$rows = array();
		foreach ($completion['modules'] as $module) {
			$product = $catalog[$module['product']] ?? null;
			$rows[] = array('product' => trim(($product->ref ?? '').' '.($product->label ?? '')), 'count' => (string) $module['count']);
		}
		foreach ($completion['inverters'] as $inverter) {
			$product = $catalog[$inverter['product']] ?? null;
			$identity = array(
				'product' => trim(($product->ref ?? '').' '.($product->label ?? '')),
				'marker' => trim($inverter['equipment_marker'].' '.$inverter['equipment_identification']),
				'serial' => $inverter['serial_number'],
			);
			$rows[] = $identity;
			foreach ($inverter['mppts'] as $mppt) {
				if (!$mppt['strings']) {
					$rows[] = $identity + array('mppt' => (string) $mppt['number']);
				}
				foreach ($mppt['strings'] as $string) {
					$module = $catalog[$string['product']] ?? null;
					$rows[] = $identity + array(
						'mppt' => $mppt['number'].' / '.$string['input'],
						'module' => trim(($module->ref ?? '').' '.($module->label ?? '')),
						'count' => (string) $string['count'],
					);
				}
			}
		}
		return array(
			'code' => 'plant_completion',
			'title_key' => 'PowerPlantPVCompletion',
			'type' => 'table',
			'columns' => $this->columns(array('product', 'marker', 'serial', 'mppt', 'module', 'count'),
				array('Product', 'PowerPlantPVCompletionMarker', 'SerialNumber', 'PowerPlantPVCompletionInput', 'PowerPlantPDFModules', 'Qty')),
			'rows' => $rows,
		);
	}

	private function fetchComponents($powerplant, $outputlangs)
	{
		$rows = array();
		$powerplantId = !empty($powerplant->id) ? (int) $powerplant->id : 0;
		if ($powerplantId <= 0) {
			return $rows;
		}

		$entity = !empty($powerplant->entity) ? (int) $powerplant->entity : 1;
		$productEntities = $this->sanitizeEntityList(getEntity('product'));

		$sql = "SELECT c.rowid, c.entity as component_entity, c.fk_product, c.fk_status, c.qty, c.serial_number, c.commissioning_date,";
		$sql .= " p.ref as product_ref, p.label as product_label,";
		$sql .= " pe.categorie_photovoltaique as fk_categorypv, pvcat.code as category_code, pvcat.label as category_label";
		$sql .= " FROM ".$this->db->prefix()."powerplantpv_powerplantcomp as c";
		$sql .= " LEFT JOIN ".$this->db->prefix()."product as p ON p.rowid = c.fk_product";
		$sql .= " LEFT JOIN ".$this->db->prefix()."product_extrafields as pe ON pe.fk_object = c.fk_product";
		$sql .= " LEFT JOIN ".$this->db->prefix()."c_powerplantpv_categorypv as pvcat ON pvcat.rowid = pe.categorie_photovoltaique";
		$sql .= " WHERE c.fk_powerplant = ".$powerplantId;
		$sql .= " AND c.entity = ".$entity;
		$sql .= " AND (p.rowid IS NULL OR p.entity IN (".$productEntities."))";
		$sql .= " ORDER BY pvcat.code ASC, p.ref ASC, c.rowid ASC";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return $rows;
		}

		$powerCache = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$productId = (int) $obj->fk_product;
			if ($productId > 0 && !isset($powerCache[$productId])) {
				$powerCache[$productId] = $this->fetchProductPowerData($productId);
			}
			$productPower = $productId > 0 ? $powerCache[$productId] : array();
			$rows[] = array(
				'id' => (int) $obj->rowid,
				'entity' => (int) $obj->component_entity,
				'fk_product' => $productId,
				'fk_categorypv' => !empty($obj->fk_categorypv) ? (int) $obj->fk_categorypv : 0,
				'product_ref' => (string) $obj->product_ref,
				'product_label' => (string) $obj->product_label,
				'category_code' => (string) $obj->category_code,
				'category_label' => (string) $obj->category_label,
				'qty' => $obj->qty,
				'serial_number' => (string) $obj->serial_number,
				'commissioning_date' => $obj->commissioning_date,
				'status' => $this->componentStatusText((int) $obj->fk_status, $outputlangs),
				'pmax' => isset($productPower['pmax']) ? $productPower['pmax'] : null,
				'ac_nominal_power' => isset($productPower['ac_nominal_power']) ? $productPower['ac_nominal_power'] : null,
				'ac_max_power' => isset($productPower['ac_max_power']) ? $productPower['ac_max_power'] : null,
			);
		}
		$this->db->free($resql);

		return $rows;
	}

	/**
	 * Fetch photovoltaic product power data.
	 *
	 * @param	int	$productId	Product id
	 * @return	array<string,mixed>	Data
	 */
	private function fetchProductPowerData($productId)
	{
		$data = array();
		$productEntities = $this->sanitizeEntityList(getEntity('product'));

		$sql = "SELECT pmax";
		$sql .= " FROM ".$this->db->prefix()."powerplantpv_product_pvpanel";
		$sql .= " WHERE fk_product = ".((int) $productId);
		$sql .= " AND entity IN (".$productEntities.")";
		$sql .= " ORDER BY entity DESC";
		$sql .= " LIMIT 1";
		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			$obj = $this->db->fetch_object($resql);
			if (is_object($obj)) {
				$data['pmax'] = $obj->pmax;
			}
		}
		if ($resql) {
			$this->db->free($resql);
		}

		$sql = "SELECT ac_nominal_power, ac_max_power";
		$sql .= " FROM ".$this->db->prefix()."powerplantpv_product_inverter";
		$sql .= " WHERE fk_product = ".((int) $productId);
		$sql .= " AND entity IN (".$productEntities.")";
		$sql .= " ORDER BY entity DESC";
		$sql .= " LIMIT 1";
		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			$obj = $this->db->fetch_object($resql);
			if (is_object($obj)) {
				$data['ac_nominal_power'] = $obj->ac_nominal_power;
				$data['ac_max_power'] = $obj->ac_max_power;
			}
		}
		if ($resql) {
			$this->db->free($resql);
		}

		return $data;
	}

	/**
	 * Fetch contacts linked to the power plant.
	 *
	 * @param	PowerPlant	$powerplant		Power plant
	 * @param	Translate	$outputlangs	Output language
	 * @return	array<int,array<string,string>>	Rows
	 */
	private function fetchContacts($powerplant, $outputlangs)
	{
		$rows = array();
		$contacts = array_merge((array) $powerplant->liste_contact(-1, 'internal'), (array) $powerplant->liste_contact(-1, 'external'));

		foreach ($contacts as $contact) {
			if (!is_array($contact)) {
				continue;
			}
			$source = (string) $contact['source'];
			$id = (int) $contact['id'];
			$name = trim((string) ($contact['firstname'] ?? '').' '.(string) ($contact['lastname'] ?? ''));
			$email = !empty($contact['email']) ? (string) $contact['email'] : '';
			$phone = '';

			if ($source === 'internal') {
				$tmpuser = new User($this->db);
				if ($id > 0 && $tmpuser->fetch($id) > 0) {
					$name = $tmpuser->getFullName($outputlangs);
					$email = (string) $tmpuser->email;
					$phone = $this->firstNonEmpty(array($tmpuser->office_phone, $tmpuser->user_mobile));
				}
			} elseif ($source === 'external') {
				$tmpcontact = new Contact($this->db);
				if ($id > 0 && $tmpcontact->fetch($id) > 0) {
					$name = $tmpcontact->getFullName($outputlangs);
					$email = (string) $tmpcontact->email;
					$phone = $this->firstNonEmpty(array($tmpcontact->phone_pro, $tmpcontact->phone_mobile, $tmpcontact->phone_perso));
				}
			}

			$rows[] = array(
				'role' => !empty($contact['libelle']) ? (string) $contact['libelle'] : '',
				'name' => $name,
				'phone' => $phone,
				'email' => $email,
			);
		}

		return array_values(array_filter($rows, array($this, 'tableRowHasContent')));
	}

	/**
	 * Build material rows from composition.
	 *
	 * @param	array<int,array<string,mixed>>	$components		Components
	 * @param	array<int,string>				$codes			Category codes
	 * @param	Translate						$outputlangs	Output language
	 * @param	string							$type			Material type
	 * @param int $powerplantId Owner plant id
	 * @return	array<int,array<string,mixed>>					Rows
	 */
	private function buildMaterialRows($components, $codes, $outputlangs, $type, $powerplantId)
	{
		$rows = array();
		foreach (PowerPlantPVReportPresentation::consolidate($components) as $component) {
			if (!in_array((string) $component['category_code'], $codes, true)) {
				continue;
			}
			$power = '';
			if ($type === 'module' && $this->isFilled($component['pmax'])) {
				$power = $this->formatNumber($component['pmax'], 0, $outputlangs).' W';
			}
			if ($type === 'inverter') {
				$values = array();
				if ($this->isFilled($component['ac_nominal_power'])) {
					$values[] = $outputlangs->transnoentities('PowerPlantPDFACNominalPowerShort').' '.$this->formatNumber($component['ac_nominal_power'], 2, $outputlangs);
				}
				if ($this->isFilled($component['ac_max_power'])) {
					$values[] = $outputlangs->transnoentities('PowerPlantPDFACMaxPowerShort').' '.$this->formatNumber($component['ac_max_power'], 2, $outputlangs);
				}
				$power = !empty($values) ? implode(' / ', $values).' W' : '';
			}
			$rows[] = array(
				'product' => $this->productLabel($component),
				'qty' => $this->formatNumber($component['qty'], 2, $outputlangs),
				'power' => $power,
				'serial' => $outputlangs->transnoentities('PowerPlantPVConsultPowerplant'),
				'serial_powerplant_id' => (int) $powerplantId,
				'status' => !empty($component['multiple_statuses']) ? $outputlangs->transnoentities('PowerPlantPVMultipleStatuses') : (string) $component['status'],
			);
		}

		return array_values(array_filter($rows, array($this, 'tableRowHasContent')));
	}

	/**
	 * Build complete composition rows.
	 *
	 * @param	array<int,array<string,mixed>>	$components		Components
	 * @param	Translate						$outputlangs	Output language
	 * @return	array<int,array<string,string>>					Rows
	 */
	private function buildCompositionRows($components, $outputlangs)
	{
		$rows = array();
		foreach ($components as $component) {
			if (in_array((string) $component['category_code'], array('MODULE', 'ONDULE'), true)) {
				continue;
			}
			$rows[] = array(
				'category' => $this->firstNonEmpty(array($component['category_label'], $component['category_code'])),
				'product' => $this->productLabel($component),
				'qty' => $this->formatNumber($component['qty'], 2, $outputlangs),
				'serial' => $this->serialNumberDisplayValue($component, $outputlangs),
				'date' => $this->formatDate($component['commissioning_date'], $outputlangs),
				'status' => (string) $component['status'],
			);
		}

		return array_values(array_filter($rows, array($this, 'tableRowHasContent')));
	}

	/**
	 * Freeze PV module serials without adding them to display sections.
	 * @param PowerPlant $plant Owner plant
	 * @param array<int,array<string,mixed>> $components Installed composition
	 * @return array<int,array<string,string>> Historical inventory
	 */
	private function fetchPanelSerialInventory($plant, $components)
	{
		$rows = array();
		$panels = array();
		foreach ($components as $component) {
			if ((string) $component['category_code'] !== 'MODULE') { continue; }
			$panels[(int) $component['id']] = $component;
			if (trim((string) $component['serial_number']) !== '') {
				$rows[(int) $component['id'].':'.(string) $component['serial_number']] = array('product_ref' => (string) $component['product_ref'], 'product_label' => (string) $component['product_label'], 'serial' => (string) $component['serial_number']);
			}
		}
		$sql = 'SELECT fk_powerplant_line, serial_number FROM '.$this->db->prefix().'powerplantpv_serialnumber';
		$sql .= ' WHERE fk_powerplant = '.(int) $plant->id.' AND entity = '.(int) $plant->entity;
		$sql .= " AND import_status = 'validated' ORDER BY rowid";
		$result = $this->db->query($sql);
		if (!$result) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return array();
		}
		while ($row = $this->db->fetch_object($result)) {
			$id = (int) $row->fk_powerplant_line;
			if (!isset($panels[$id])) { continue; }
			$rows[$id.':'.(string) $row->serial_number] = array('product_ref' => (string) $panels[$id]['product_ref'], 'product_label' => (string) $panels[$id]['product_label'], 'serial' => (string) $row->serial_number);
		}
		return array_values($rows);
	}

	/**
	 * Fetch thirdparty name.
	 *
	 * @param	PowerPlant	$powerplant	Power plant
	 * @return	string					Name
	 */
	private function fetchThirdpartyName($powerplant)
	{
		if (empty($powerplant->fk_soc)) {
			return '';
		}

		$thirdparty = new Societe($this->db);
		if ($thirdparty->fetch((int) $powerplant->fk_soc) <= 0) {
			return '';
		}

		return (string) $thirdparty->name;
	}

	/**
	 * Return the serial number display value for a component.
	 *
	 * @param	array<string,mixed>	$component		Component data
	 * @param	Translate			$outputlangs	Output language
	 * @return	string								Display value
	 */
	private function serialNumberDisplayValue($component, $outputlangs)
	{
		return powerplantpvSerialNumberDisplayValue(
			isset($component['serial_number']) ? (string) $component['serial_number'] : '',
			isset($component['fk_categorypv']) ? (int) $component['fk_categorypv'] : 0,
			isset($component['entity']) ? (int) $component['entity'] : 0,
			$outputlangs
		);
	}

	/**
	 * Format the power plant address.
	 *
	 * @param	PowerPlant	$powerplant		Power plant
	 * @param	Translate	$outputlangs	Output language
	 * @return	string						Address
	 */
	private function formatPowerPlantAddress($powerplant, $outputlangs)
	{
		if (empty($powerplant->address) && empty($powerplant->zip) && empty($powerplant->town) && empty($powerplant->fk_country)) {
			return '';
		}

		$addressObject = new stdClass();
		$addressObject->address = $powerplant->address;
		$addressObject->zip = $powerplant->zip;
		$addressObject->town = $powerplant->town;
		$addressObject->country_id = $powerplant->fk_country;
		$addressObject->country_code = '';

		if (!empty($powerplant->fk_country)) {
			$country = getCountry((int) $powerplant->fk_country, 'all', null, $outputlangs, 0);
			if (is_array($country)) {
				$addressObject->country_code = $country['code'];
				$addressObject->country = $country['label'];
			}
		}

		return dol_format_address($addressObject, 1, ', ', $outputlangs);
	}

	/**
	 * Return status text.
	 *
	 * @param	PowerPlant	$powerplant	Power plant
	 * @return	string					Status
	 */
	private function statusText($powerplant)
	{
		if (method_exists($powerplant, 'getLibStatut')) {
			return dol_string_nohtmltag($powerplant->getLibStatut(0), 1);
		}

		return (string) $powerplant->status;
	}

	/**
	 * Return component status text.
	 *
	 * @param	int			$status			Status id
	 * @param	Translate	$outputlangs	Output language
	 * @return	string						Status
	 */
	private function componentStatusText($status, $outputlangs)
	{
		$map = array(
			0 => 'PowerPlantCompStatusInactive',
			4 => 'PowerPlantCompStatusActive',
			6 => 'PowerPlantCompStatusReplaced',
			8 => 'PowerPlantCompStatusOutOfService',
		);
		if (isset($map[$status])) {
			return $outputlangs->transnoentities($map[$status]);
		}

		return (string) $status;
	}

	/**
	 * Return product display label.
	 *
	 * @param	array<string,mixed>	$component	Component
	 * @return	string							Label
	 */
	private function productLabel($component)
	{
		$product = $this->firstNonEmpty(array($component['product_ref'], $component['product_label']));
		if ($this->isFilled($component['product_ref']) && $this->isFilled($component['product_label'])) {
			$product = $component['product_ref'].' - '.$component['product_label'];
		}

		return $product;
	}

	/**
	 * Format a number with Dolibarr rules.
	 *
	 * @param	mixed		$value			Value
	 * @param	int			$decimals		Decimals
	 * @param	Translate	$outputlangs	Output language
	 * @return	string						Formatted value
	 */
	private function formatNumber($value, $decimals, $outputlangs)
	{
		if (!$this->isFilled($value)) {
			return '';
		}

		return price($value, 0, $outputlangs, 0, 0, $decimals);
	}

	/**
	 * Format a date.
	 *
	 * @param	mixed		$value			Date value
	 * @param	Translate	$outputlangs	Output language
	 * @return	string						Formatted date
	 */
	private function formatDate($value, $outputlangs)
	{
		if (!$this->isFilled($value)) {
			return '';
		}
		if (is_numeric($value)) {
			return dol_print_date((int) $value, 'day', false, $outputlangs, true);
		}

		return dol_print_date($this->db->jdate($value), 'day', false, $outputlangs, true);
	}

	/**
	 * Return a sanitized entity list for SQL IN.
	 *
	 * @param	string	$list	Entity list
	 * @return	string			Sanitized list
	 */
	private function sanitizeEntityList($list)
	{
		global $conf;

		$entities = array();
		foreach (explode(',', (string) $list) as $entity) {
			$entity = trim($entity);
			if ($entity !== '' && preg_match('/^\d+$/', $entity)) {
				$entities[(int) $entity] = (int) $entity;
			}
		}
		if (empty($entities)) {
			$entities[(int) $conf->entity] = (int) $conf->entity;
		}
		ksort($entities, SORT_NUMERIC);

		return implode(',', $entities);
	}

	/**
	 * Create a key/value snapshot row.
	 *
	 * @param	string	$labelKey	Label translation key
	 * @param	mixed	$value		Value
	 * @return	array{label_key:string,value:string}	Row
	 */
	private function keyValueRow($labelKey, $value)
	{
		return array('label_key' => $labelKey, 'value' => $this->normalizeValue($value));
	}

	/**
	 * Create snapshot table columns.
	 *
	 * @param	array<int,string>	$keys		Data keys
	 * @param	array<int,string>	$labelKeys	Label translation keys
	 * @return	array<int,array{key:string,label_key:string}>	Columns
	 */
	private function columns($keys, $labelKeys)
	{
		$columns = array();
		foreach ($keys as $index => $key) {
			$columns[] = array(
				'key' => (string) $key,
				'label_key' => isset($labelKeys[$index]) ? (string) $labelKeys[$index] : (string) $key,
			);
		}

		return $columns;
	}

	/**
	 * Filter empty key/value rows.
	 *
	 * @param	array<int,array{label_key:string,value:string}>	$rows	Rows
	 * @return	array<int,array{label_key:string,value:string}>			Filtered rows
	 */
	private function filterSnapshotRows($rows)
	{
		$filtered = array();
		foreach ($rows as $row) {
			if (isset($row['value']) && $this->isFilled($row['value'])) {
				$filtered[] = $row;
			}
		}

		return $filtered;
	}

	/**
	 * Test if a snapshot section has content.
	 *
	 * @param	array<string,mixed>	$section	Section
	 * @return	bool							True if content exists
	 */
	private function snapshotSectionHasContent($section)
	{
		if (isset($section['rows']) && is_array($section['rows']) && !empty($section['rows'])) {
			return true;
		}
		if (isset($section['tables']) && is_array($section['tables'])) {
			foreach ($section['tables'] as $table) {
				if (is_array($table) && !empty($table['rows']) && is_array($table['rows'])) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Test if a table row has at least one value.
	 *
	 * @param	array<string,mixed>	$row	Row
	 * @return	bool						True if non-empty
	 */
	private function tableRowHasContent($row)
	{
		foreach ($row as $value) {
			if ($this->isFilled($value)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalize a value for display.
	 *
	 * @param	mixed	$value	Value
	 * @return	string			Text
	 */
	private function normalizeValue($value)
	{
		if (is_array($value)) {
			$value = implode("\n", array_filter(array_map('strval', $value)));
		}

		return trim((string) dol_string_nohtmltag((string) $value, 0));
	}

	/**
	 * Test if a value is filled.
	 *
	 * @param	mixed	$value	Value
	 * @return	bool			True if filled
	 */
	private function isFilled($value)
	{
		return !(is_null($value) || trim((string) $value) === '');
	}

	/**
	 * Return the first non-empty value.
	 *
	 * @param	array<int,mixed>	$values	Values
	 * @return	string					Value
	 */
	private function firstNonEmpty($values)
	{
		foreach ($values as $value) {
			if ($this->isFilled($value)) {
				return (string) $value;
			}
		}

		return '';
	}
}
