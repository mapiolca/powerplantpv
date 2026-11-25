<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once __DIR__.'/powerplantpvequipmentidentity.class.php';
require_once __DIR__.'/powerplantpvequipmentnumbering.class.php';
require_once __DIR__.'/powerplantpvstringallocation.class.php';
require_once __DIR__.'/powerplant.class.php';
require_once __DIR__.'/../lib/powerplantpv_powerplant.lib.php';

/** Pending observations attached to one report and applied to one plant at a time. */
class PowerPlantPVReportPlantCompletion
{
	const PENDING = 0;
	const APPLIED = 1;
	const REVIEW = 2;
	const FAILED = 3;
	const INVALID = 'PowerPlantPVCompletionInvalid';
	const CONFLICT = 'PowerPlantPVCompletionConflict';
	const DATABASE = 'PowerPlantPVCompletionDatabase';

	public string $error = '';
	public string $output = '';

	public function __construct(private DoliDB $db)
	{
	}

	private function rows(string $sql): array
	{
		$result = $this->db->query($sql);
		if (!$result) {
			throw new RuntimeException(self::DATABASE);
		}
		$rows = array();
		while ($row = $this->db->fetch_object($result)) {
			$rows[] = $row;
		}
		$this->db->free($result);
		return $rows;
	}

	private function execute(string $sql): void
	{
		if (!$this->db->query($sql)) {
			throw new RuntimeException(self::DATABASE);
		}
	}

	private function quote(string $value): string
	{
		return "'".$this->db->escape($value)."'";
	}

	private function table(): string
	{
		return $this->db->prefix().'powerplantpv_report_plant_completion';
	}

	/** @return array<int,object> Proposals indexed by plant id */
	public function forReport(int $report, int $entity): array
	{
		if ($report <= 0) {
			return array();
		}
		$rows = array();
		foreach ($this->rows('SELECT rowid, fk_report, entity, fk_powerplant, plant_entity, fk_user_author, proposal, status, error_code FROM '.$this->table()
			.' WHERE entity = '.$entity.' AND fk_report = '.$report) as $row) {
			$row->data = json_decode($row->proposal, true, 32, JSON_THROW_ON_ERROR);
			$rows[(int) $row->fk_powerplant] = $row;
		}
		return $rows;
	}

	/** Server-side scope shared by authoring and by revalidation of the recorded author. */
	public function plantForAuthor(Fichinter $intervention, User $author, int $id): ?PowerPlant
	{
		$plant = new PowerPlant($this->db);
		if ($plant->fetch($id) <= 0 || (int) $plant->status === PowerPlant::STATUS_CANCELED
			|| !in_array($id, array_map('intval', array_column(powerplantpvGetLinkedPowerPlantRows($intervention), 'powerplant_id')), true)
			|| !in_array((int) $plant->entity, powerplantpvSanitizeIdArray(explode(',', (string) getEntity('powerplant'))), true)) {
			return null;
		}
		if ((int) $author->socid > 0 && ((int) $plant->fk_soc !== (int) $author->socid || (int) $intervention->socid !== (int) $author->socid)) {
			return null;
		}
		$draft = (int) $plant->status === PowerPlant::STATUS_DRAFT ? 1 : 0;
		return restrictedArea($author, 'powerplantpv', $plant, powerplantpvGetSharedObjectTableAndShare($plant), 'powerplant', 'fk_soc', 'rowid', $draft, 1) ? $plant : null;
	}

	/** @return array<int,object> Catalog references indexed by product id */
	public function catalog(): array
	{
		$entities = powerplantpvSanitizeIdArray(explode(',', (string) getEntity('product')));
		if (!$entities) {
			return array();
		}
		$p = $this->db->prefix();
		$sql = "SELECT p.rowid, p.ref, p.label, cat.code, panel.pmax, inv.rowid AS inverter_id
			FROM ".$p."product p
			LEFT JOIN ".$p."product_extrafields pe ON pe.fk_object = p.rowid
			LEFT JOIN ".$p."c_powerplantpv_categorypv cat ON cat.rowid = pe.categorie_photovoltaique
			LEFT JOIN ".$p."powerplantpv_product_inverter inv ON inv.fk_product = p.rowid AND inv.entity = p.entity
			LEFT JOIN ".$p."powerplantpv_product_pvpanel panel ON panel.fk_product = p.rowid AND panel.entity = p.entity
			WHERE p.fk_product_type = 0 AND p.entity IN (".$this->db->sanitize(implode(',', $entities)).")
			AND (cat.code IN ('MODULE', 'ONDULE') OR (cat.code IS NULL AND inv.rowid IS NOT NULL)) ORDER BY p.ref";
		$products = array();
		foreach ($this->rows($sql) as $row) {
			$products[(int) $row->rowid] = $row;
		}
		return $products;
	}

	/**
	 * Read current installed composition; the transfer holds the parent lock before requesting locking reads.
	 * @param array<int,object> $catalog Visible PV catalog
	 * @return array{inverters:array,mppts:array,strings:array,modules:array,used:array}
	 */
	public function composition(PowerPlant $plant, array $catalog, bool $lock = false): array
	{
		$p = $this->db->prefix();
		$where = ' WHERE entity = '.(int) $plant->entity.' AND fk_powerplant = '.(int) $plant->id;
		$lockClause = $lock ? ' FOR UPDATE' : '';
		$inverters = array();
		foreach ($this->rows('SELECT rowid, fk_product, qty, fk_status, commissioning_date, equipment_marker, equipment_identification, serial_number FROM '.$p.'powerplantpv_powerplantcomp'.$where.' AND fk_status = 4'.$lockClause) as $row) {
			$product = $catalog[(int) $row->fk_product] ?? null;
			if ((float) $row->qty === 1.0 && $product && $product->code !== 'MODULE') {
				$inverters[(int) $row->rowid] = $row;
			}
		}
		$mppts = array();
		foreach ($this->rows('SELECT fk_inverter, mppt_number, pv_input_count FROM '.$p.'powerplantpv_equipment_mppt'.$where.$lockClause) as $row) {
			$mppts[$row->fk_inverter][$row->mppt_number] = (int) $row->pv_input_count;
		}
		$strings = array();
		foreach ($this->rows('SELECT rowid, fk_inverter, mppt_number, pv_input_number, string_ref, fk_product, module_count, module_power, is_connected FROM '.$p.'powerplantpv_equipment_string'.$where.$lockClause) as $row) {
			$strings[$row->fk_inverter][$row->mppt_number][$row->pv_input_number] = $row;
		}
		$allocation = new PowerPlantPVStringAllocation($this->db, (int) $plant->id, (int) $plant->entity);
		$modules = $allocation->modules($lock);
		$used = $allocation->connected(0, 0, 0, $lock);
		if ($modules === null || $used === null) {
			throw new RuntimeException(self::DATABASE);
		}
		return compact('inverters', 'mppts', 'strings', 'modules', 'used');
	}

	private function cleanText($value, int $length): string
	{
		if (!is_string($value) || mb_strlen($value, 'UTF-8') > $length
			|| preg_match('/[<>\x00-\x1F\x7F]/u', $value) !== 0) {
			throw new DomainException(self::INVALID);
		}
		return trim($value);
	}

	private function positive($value): int
	{
		if (!is_int($value) && (!is_string($value) || !preg_match('/^[1-9][0-9]{0,9}$/D', $value))) {
			throw new DomainException(self::INVALID);
		}
		$number = filter_var($value, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 2147483647)));
		if ($number === false) {
			throw new DomainException(self::INVALID);
		}
		return $number;
	}

	/** @return array<int|string,array> */
	private function entries(array $payload, string $key): array
	{
		$entries = $payload[$key] ?? array();
		if (!is_array($entries) || count($entries) > 1000) {
			throw new DomainException(self::INVALID);
		}
		foreach ($entries as $entry) {
			if (!is_array($entry)) {
				throw new DomainException(self::INVALID);
			}
		}
		return $entries;
	}

	private function additionsOnly(string $observed, string $stored): void
	{
		if ($observed !== '' && $stored !== '' && $observed !== $stored) {
			throw new DomainException(self::CONFLICT);
		}
	}

	/** Validate the entire proposed topology against the same installed stock used by Composition. */
	public function validate(PowerPlant $plant, array $proposal, array $catalog, array $current): array
	{
		$prm = $this->cleanText($proposal['prm'] ?? '', 128);
		$type = $this->cleanText($proposal['type'] ?? '', 128);
		$this->additionsOnly($prm, trim((string) $plant->prm_pdl_number));
		$this->additionsOnly($type, (string) $plant->connection_type);
		if ($type !== '' && $type !== (string) $plant->connection_type && !isset(PowerPlant::getConnectionTypeOptions()[$type])) {
			throw new DomainException(self::INVALID);
		}

		$stock = array_map(static fn ($module) => $module['qty'], $current['modules']);
		$modules = array();
		foreach ($this->entries($proposal, 'modules') as $module) {
			$product = $this->positive($module['product'] ?? null);
			$count = $this->positive($module['count'] ?? null);
			if (($catalog[$product]->code ?? '') !== 'MODULE' || isset($modules[$product])) {
				throw new DomainException(self::INVALID);
			}
			if (isset($stock[$product]) && (float) $stock[$product] !== (float) $count) {
				throw new DomainException(self::CONFLICT);
			}
			$stock[$product] = $count;
			$modules[$product] = array('product' => $product, 'count' => $count);
		}

		$used = $current['used']['products'];
		$total = $current['used']['total'];
		$inverters = array();
		$identities = array('equipment_marker' => array(), 'serial_number' => array());
		foreach ($current['inverters'] as $id => $line) {
			foreach (array_keys($identities) as $field) {
				if (trim((string) $line->$field) !== '') {
					$identities[$field][mb_strtolower(trim((string) $line->$field), 'UTF-8')] = 'line-'.$id;
				}
			}
		}

		foreach ($this->entries($proposal, 'inverters') as $inverter) {
			$key = $inverter['key'] ?? '';
			if (!is_string($key) || !preg_match('/^(line-[1-9][0-9]{0,9}|new-[a-f0-9]{16})$/D', $key) || isset($inverters[$key])) {
				throw new DomainException(self::INVALID);
			}
			$id = strncmp($key, 'line-', 5) === 0 ? $this->positive(substr($key, 5)) : 0;
			if (!$id) {
				$matches = array();
				foreach ($current['inverters'] as $candidateId => $candidate) {
					foreach (array('equipment_marker', 'serial_number') as $field) {
						$value = $this->cleanText($inverter[$field] ?? '', $field === 'equipment_marker' ? 32 : 128);
						if ($value !== '' && mb_strtolower($value, 'UTF-8') === mb_strtolower(trim((string) $candidate->$field), 'UTF-8')) {
							$matches[$candidateId] = $candidate;
						}
					}
				}
				if (count($matches) > 1) {
					throw new DomainException(self::CONFLICT);
				}
				if ($matches) {
					$id = (int) key($matches);
					if ((int) $matches[$id]->fk_product !== $this->positive($inverter['product'] ?? null)) {
						throw new DomainException(self::CONFLICT);
					}
				}
			}
			$line = $current['inverters'][$id] ?? null;
			if ($id && !$line) {
				throw new DomainException(self::CONFLICT);
			}
			$product = $line ? (int) $line->fk_product : $this->positive($inverter['product'] ?? null);
			if (!isset($catalog[$product]) || $catalog[$product]->code === 'MODULE') {
				throw new DomainException(self::INVALID);
			}
			$identity = array();
			foreach (array('equipment_marker' => 32, 'equipment_identification' => 255, 'serial_number' => 128) as $field => $length) {
				$identity[$field] = $this->cleanText($inverter[$field] ?? '', $length);
				$this->additionsOnly($identity[$field], trim((string) ($line->$field ?? '')));
				if (isset($identities[$field]) && $identity[$field] !== '') {
					$identityKey = mb_strtolower($identity[$field], 'UTF-8');
					if (isset($identities[$field][$identityKey]) && !in_array($identities[$field][$identityKey], array($key, 'line-'.$id), true)) {
						throw new DomainException(self::CONFLICT);
					}
					$identities[$field][$identityKey] = $key;
				}
			}
			$mppts = $this->validateMppts($this->entries($inverter, 'mppts'), $current['mppts'][$id] ?? array(), $current['strings'][$id] ?? array(), $stock, $used, $total);
			if (!$id || array_filter($identity) || $mppts) {
				$inverters[$key] = array('key' => $key, 'product' => $product, 'mppts' => $mppts, 'resolved' => $id) + $identity;
			}
		}
		if ($total > $current['used']['total'] && $total > array_sum($stock)) {
			throw new DomainException('PowerPlantPVModuleAllocationExceeded');
		}
		return compact('prm', 'type', 'modules', 'inverters');
	}

	private function validateMppts(array $entries, array $existingMppts, array $existingStrings, array $stock, array &$used, int &$total): array
	{
		$mppts = array();
		foreach ($entries as $entry) {
			$number = $this->positive($entry['number'] ?? null);
			$inputs = $this->positive($entry['inputs'] ?? null);
			if ($number > 999 || $inputs > 128 || isset($mppts[$number])) {
				throw new DomainException(self::INVALID);
			}
			$existingCount = $existingMppts[$number] ?? 0;
			if ($existingCount && $inputs !== $existingCount) {
				throw new DomainException(self::CONFLICT);
			}
			$strings = array();
			foreach ($this->entries($entry, 'strings') as $string) {
				$input = $this->positive($string['input'] ?? null);
				$product = $this->positive($string['product'] ?? null);
				$count = $this->positive($string['count'] ?? null);
				if ($input > $inputs || isset($strings[$input]) || !isset($stock[$product])) {
					throw new DomainException(self::INVALID);
				}
				$existing = $existingStrings[$number][$input] ?? null;
				$alreadyConnected = $existing && (int) $existing->is_connected === 1;
				if ($existing && (((int) $existing->fk_product > 0 && (int) $existing->fk_product !== $product)
					|| ((int) $existing->module_count > 0 && (int) $existing->module_count !== $count)
					|| ((int) $existing->fk_product === 0 && $existing->module_power !== null && (float) $existing->module_power > 0))) {
					throw new DomainException(self::CONFLICT);
				}
				$previousCount = $alreadyConnected ? (int) $existing->module_count : 0;
				$total += $count - $previousCount;
				$used[$product] = ($used[$product] ?? 0) + $count - ($alreadyConnected && (int) $existing->fk_product === $product ? $previousCount : 0);
				if ($used[$product] > $stock[$product]) {
					throw new DomainException('PowerPlantPVModuleAllocationExceeded');
				}
				$strings[$input] = compact('input', 'product', 'count');
			}
			$mppts[$number] = compact('number', 'inputs', 'strings');
		}
		return $mppts;
	}

	/** Update only proposals that changed. The enclosing report lock serializes editing and signing. */
	public function save(PowerPlantPVReport $report, Fichinter $intervention, User $author, array $submitted): bool
	{
		try {
			$stored = $this->forReport((int) $report->id, (int) $report->entity);
			$catalog = $this->catalog();
			foreach ($submitted as $id => $entry) {
				$id = $this->positive($id);
				if (!is_array($entry) || !is_string($entry['revision'] ?? null) || !is_array($entry['proposal'] ?? null)) {
					throw new DomainException(self::INVALID);
				}
				$previous = $stored[$id] ?? null;
				$plant = $this->plantForAuthor($intervention, $author, $id);
				if (!$plant || !hash_equals(hash('sha256', $previous->proposal ?? ''), (string) ($entry['revision'] ?? ''))) {
					throw new DomainException(self::CONFLICT);
				}
				$proposal = $this->validate($plant, $entry['proposal'], $catalog, $this->composition($plant, $catalog));
				$filter = ' WHERE entity = '.(int) $report->entity.' AND fk_report = '.(int) $report->id.' AND fk_powerplant = '.$id.' AND status = 0';
				if ($proposal === array('prm' => '', 'type' => '', 'modules' => array(), 'inverters' => array())) {
					if ($previous) {
						$this->execute('DELETE FROM '.$this->table().$filter);
					}
					continue;
				}
				$json = json_encode($proposal, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
				if ($previous && $previous->proposal === $json) {
					continue;
				}
				$values = array((int) $report->entity, (int) $report->id, $id, (int) $author->id, (int) $plant->entity, $this->quote($json));
				$this->execute('INSERT INTO '.$this->table().' (entity, fk_report, fk_powerplant, fk_user_author, plant_entity, proposal) VALUES ('.implode(', ', $values).')'
					.' ON DUPLICATE KEY UPDATE fk_user_author = VALUES(fk_user_author), proposal = VALUES(proposal), plant_entity = VALUES(plant_entity)');
			}
			return true;
		} catch (DomainException | RuntimeException | JsonException | TypeError $e) {
			$this->error = $e instanceof DomainException || $e instanceof RuntimeException ? $e->getMessage() : self::INVALID;
			return false;
		}
	}

	/** Native scheduled-job entry point. The job's user does not authorize plant changes. */
	public function runTransfers(): int
	{
		global $conf;
		if (!isModEnabled('powerplantpv') || !getDolGlobalInt('POWERPLANTPV_MAINTENANCE_ENABLE', 1)) {
			return 0;
		}
		require_once __DIR__.'/powerplantpvreport.class.php';
		require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
		$entity = (int) $conf->entity;
		$reportTable = $this->db->prefix().'powerplantpv_report';
		$interventionTable = $this->db->prefix().'fichinter';
		$signed = PowerPlantPVReport::interventionSignatureStatuses()['STATUS_SIGNED_ALL'];
		try {
			$pending = $this->rows('SELECT c.rowid FROM '.$this->table().' c'
				.' INNER JOIN '.$reportTable.' r ON r.rowid = c.fk_report AND r.entity = c.entity'
				.' INNER JOIN '.$interventionTable.' f ON f.rowid = r.fk_fichinter AND f.entity = r.entity'
				.' WHERE c.entity = '.$entity.' AND c.status = '.self::PENDING
				.' AND r.status = '.$this->quote(PowerPlantPVReport::STATUS_SAVED).' AND f.signed_status = '.$signed
				.' ORDER BY c.rowid'.$this->db->plimit(50));
		} catch (RuntimeException $e) {
			$this->error = $e->getMessage();
			return -1;
		}
		$errors = 0;
		foreach ($pending as $entry) {
			if ($this->transfer((int) $entry->rowid, $entity) < 0) {
				$errors++;
			}
		}
		$this->output = (string) (count($pending) - $errors).' / '.count($pending);
		return $errors ? -1 : 0;
	}

	/** Draft entries become eligible when the existing signature flow commits SIGNED_ALL. */
	public function transfer(int $id, int $entity, bool $retry = false): int
	{
		$this->error = '';
		if ($this->db->begin() <= 0) {
			$this->error = self::DATABASE;
			return -1;
		}
		$row = null;
		try {
			$allowedStatus = $retry ? self::FAILED : self::PENDING;
			$filter = ' WHERE rowid = '.$id.' AND entity = '.$entity.' AND status = '.$allowedStatus;
			$row = $this->rows('SELECT fk_report, fk_powerplant, plant_entity, fk_user_author, proposal FROM '.$this->table().$filter.' FOR UPDATE')[0] ?? null;
			if (!$row) {
				$this->db->rollback();
				return 0;
			}
			$report = $this->rows('SELECT fk_fichinter, fk_user_technician_sign FROM '.$this->db->prefix().'powerplantpv_report'
				.' WHERE entity = '.$entity.' AND rowid = '.(int) $row->fk_report.' AND status = '.$this->quote(PowerPlantPVReport::STATUS_SAVED))[0] ?? null;
			$intervention = new Fichinter($this->db);
			if (!$report || $intervention->fetch((int) $report->fk_fichinter) <= 0 || (int) $intervention->entity !== $entity
				|| (int) $intervention->signed_status !== PowerPlantPVReport::interventionSignatureStatuses()['STATUS_SIGNED_ALL']
				|| (int) $report->fk_user_technician_sign <= 0) {
				$this->db->rollback();
				return 0;
			}

			$plantId = (int) $row->fk_powerplant;
			$plantTable = $this->db->prefix().'powerplantpv_powerplant';
			$where = ' WHERE rowid = '.$plantId.' AND entity = '.(int) $row->plant_entity;
			$locked = $this->rows('SELECT rowid FROM '.$plantTable.$where.' FOR UPDATE');
			$author = new User($this->db);
			if (!$locked || $author->fetch((int) $row->fk_user_author) <= 0 || (int) $author->statut !== 1) {
				throw new DomainException(self::CONFLICT);
			}
			$author->loadRights('', 1);
			if (!$author->hasRight('ficheinter', 'lire') || !$author->hasRight('powerplantpv', 'maintenance', 'read')
				|| (!$author->hasRight('powerplantpv', 'maintenance', 'write') && !$author->hasRight('powerplantpv', 'maintenance', 'report'))
				|| !$author->hasRight('powerplantpv', 'powerplant', 'read') || !$author->hasRight('powerplantpv', 'powerplant', 'write')
				|| !restrictedArea($author, 'ficheinter', $intervention, 'fichinter', '', 'fk_soc', 'rowid', 0, 1)) {
				throw new DomainException(self::CONFLICT);
			}
			$plant = $this->plantForAuthor($intervention, $author, $plantId);
			if (!$plant || (int) $plant->entity !== (int) $row->plant_entity) {
				throw new DomainException(self::CONFLICT);
			}
			$catalog = $this->catalog();
			$current = $this->composition($plant, $catalog, true);
			$proposal = $this->validate($plant, json_decode($row->proposal, true, 32, JSON_THROW_ON_ERROR), $catalog, $current);
			$this->apply($plant, $author, $proposal, $current);
			$this->execute('UPDATE '.$this->table().' SET status = '.self::APPLIED.', error_code = NULL'.$filter);
			if ($this->db->commit() <= 0) {
				throw new RuntimeException(self::DATABASE);
			}
			return 1;
		} catch (DomainException | RuntimeException | JsonException | TypeError $e) {
			$this->db->rollback();
			$code = $e instanceof DomainException ? $e->getMessage() : self::DATABASE;
			$status = $e instanceof DomainException ? self::REVIEW : self::FAILED;
			if ($row && !$this->db->query('UPDATE '.$this->table().' SET status = '.$status.', error_code = '.$this->quote($code).$filter)) {
				$code = self::DATABASE;
			}
			$this->error = $code;
			dol_syslog(__METHOD__.' proposal='.$id.' '.$code, $status === self::FAILED ? LOG_ERR : LOG_WARNING);
			return $status === self::REVIEW ? 0 : -1;
		}
	}

	private function apply(PowerPlant $plant, User $author, array $proposal, array $current): void
	{
		global $langs;
		$langs->load('powerplantpv@powerplantpv');
		$p = $this->db->prefix();
		$id = (int) $plant->id;
		$entity = (int) $plant->entity;
		$changed = false;
		foreach (array('prm' => 'prm_pdl_number', 'type' => 'connection_type') as $key => $field) {
			if ($proposal[$key] !== '' && trim((string) $plant->$field) === '') {
				$plant->$field = $proposal[$key];
				$changed = true;
			}
		}
		foreach ($proposal['modules'] as $module) {
			if (!isset($current['modules'][$module['product']])) {
				$this->execute('INSERT INTO '.$p.'powerplantpv_powerplantcomp (entity, fk_powerplant, fk_product, qty, fk_status) VALUES ('
					.$entity.', '.$id.', '.$module['product'].', '.$module['count'].', 4)');
			}
		}
		$identity = new PowerPlantPVEquipmentIdentity($this->db);
		$numbering = new PowerPlantPVEquipmentNumbering($this->db, 'string');
		$now = $this->quote($this->db->idate(dol_now()));
		$authorId = (int) $author->id;
		foreach ($proposal['inverters'] as $inverter) {
			$lineId = $inverter['resolved'];
			$line = $current['inverters'][$lineId] ?? null;
			if (!$line) {
				$this->execute('INSERT INTO '.$p.'powerplantpv_powerplantcomp (entity, fk_powerplant, fk_product, qty, fk_status, serial_number) VALUES ('
					.$entity.', '.$id.', '.$inverter['product'].', 1, 4, '.$this->quote($inverter['serial_number']).')');
				$lineId = (int) $this->db->last_insert_id($p.'powerplantpv_powerplantcomp');
				if ($lineId <= 0) {
					throw new RuntimeException(self::DATABASE);
				}
			}
			$marker = $inverter['equipment_marker'] ?: (string) ($line->equipment_marker ?? '');
			$name = $inverter['equipment_identification'] ?: (string) ($line->equipment_identification ?? '');
			if (!$line || ($marker !== (string) $line->equipment_marker || $name !== (string) $line->equipment_identification)) {
				$revision = $line ? PowerPlantPVEquipmentIdentity::revision($line) : null;
				if ($identity->save($id, $entity, $lineId, $marker, $name, !$line, $revision, true) < 0) {
					throw $identity->error === 'PowerPlantPVMarkerAlreadyUsed' ? new DomainException(self::CONFLICT) : new RuntimeException(self::DATABASE);
				}
			}
			$equipment = ' WHERE entity = '.$entity.' AND fk_powerplant = '.$id.' AND fk_inverter = '.$lineId;
			if ($line && $inverter['serial_number'] !== '' && (string) $line->serial_number === '') {
				$this->execute('UPDATE '.$p.'powerplantpv_powerplantcomp SET serial_number = '.$this->quote($inverter['serial_number'])
					.' WHERE entity = '.$entity.' AND fk_powerplant = '.$id.' AND rowid = '.$lineId);
			}

			foreach ($inverter['mppts'] as $mppt) {
				$number = $mppt['number'];
				if (!isset($current['mppts'][$lineId][$number])) {
					$this->execute('INSERT INTO '.$p.'powerplantpv_equipment_mppt (entity, fk_powerplant, fk_inverter, mppt_number, pv_input_count, position, date_creation, fk_user_creat)'
						.' VALUES ('.$entity.', '.$id.', '.$lineId.', '.$number.', '.$mppt['inputs'].', '.$number.', '.$now.', '.$authorId.')');
				} elseif ($current['mppts'][$lineId][$number] === 0) {
					$this->execute('UPDATE '.$p.'powerplantpv_equipment_mppt SET pv_input_count = '.$mppt['inputs'].', fk_user_modif = '.$authorId
						.$equipment.' AND mppt_number = '.$number.' AND pv_input_count = 0');
				}
				foreach ($mppt['strings'] as $string) {
					$input = $string['input'];
					$existing = $current['strings'][$lineId][$number][$input] ?? null;
					$reference = (string) ($existing->string_ref ?? '');
					if ($reference === '') {
						$reference = $numbering->getNextValue((object) array('id' => $id, 'entity' => $entity, 'fk_inverter' => $lineId));
						if ($reference === '') {
							throw new RuntimeException(self::DATABASE);
						}
					}
					if ($existing) {
						$this->execute('UPDATE '.$p.'powerplantpv_equipment_string SET fk_product = '.$string['product'].', module_count = '.$string['count']
							.', is_connected = 1, string_ref = '.$this->quote($reference).', fk_user_modif = '.$authorId
							.$equipment.' AND mppt_number = '.$number.' AND pv_input_number = '.$input);
					} else {
						$this->execute('INSERT INTO '.$p.'powerplantpv_equipment_string (entity, fk_powerplant, fk_inverter, mppt_number, pv_input_number, string_ref, fk_product, module_count, is_connected, position, date_creation, fk_user_creat)'
							.' VALUES ('.$entity.', '.$id.', '.$lineId.', '.$number.', '.$input.', '.$this->quote($reference).', '.$string['product']
							.', '.$string['count'].', 1, '.$input.', '.$now.', '.$authorId.')');
					}
				}
			}
		}
		if ($changed && $plant->update($author) <= 0) {
			throw new RuntimeException(self::DATABASE);
		}
	}
}
