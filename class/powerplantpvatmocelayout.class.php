<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once __DIR__.'/powerplantpvserialnumber.class.php';
require_once __DIR__.'/../lib/powerplantpv_serialnumber.lib.php';
require_once __DIR__.'/../lib/powerplantpv_powerplant.lib.php';
require_once __DIR__.'/../lib/powerplantpv_indexphoto.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

final class PowerPlantPVAtmoceLayout
{
	public function __construct(private DoliDB $db)
	{
	}

	/** @return array<int, array<string, mixed>> */
	public function compositionLines(PowerPlant $plant): array
	{
		$inverters = array();
		foreach (powerplantCompositionFetchInverterLines((int) $plant->id, (int) $plant->entity) as $line) {
			$inverters[(int) $line->rowid] = $line;
		}
		$lines = array();
		foreach (powerplantpvSerialImportFetchCompositionCategories($plant) as $category) {
			foreach (powerplantpvSerialImportFetchCategoryLines((int) $plant->id, (int) $category['id'], (int) $plant->entity) as $id => $line) {
				if (isset($inverters[$id])) {
					$marker = (string) $inverters[$id]->equipment_marker;
					$line['product_display'] = ($marker === '' ? '' : $marker.' · ').$line['product_display'];
					$lines[$id] = $line;
				}
			}
		}
		return $lines;
	}

	/** @return list<stdClass> */
	public function positionRows(PowerPlant $plant): array
	{
		$result = $this->query('SELECT s.serial_number, p.position, n.proof_ref, p.fk_layout FROM '.MAIN_DB_PREFIX.'powerplantpv_monitor_position AS p'
			.' INNER JOIN '.MAIN_DB_PREFIX.'powerplantpv_serialnumber AS s ON s.rowid = p.fk_serial AND s.entity = p.entity AND s.fk_powerplant = p.fk_powerplant'
			.' LEFT JOIN '.MAIN_DB_PREFIX.'powerplantpv_monitor_layout AS n ON n.rowid = p.fk_layout AND n.entity = p.entity AND n.fk_powerplant = p.fk_powerplant'
			.' WHERE p.entity = '.((int) $plant->entity).' AND p.fk_powerplant = '.((int) $plant->id).' ORDER BY p.position');
		$rows = array();
		while ($row = $this->db->fetch_object($result)) {
			$rows[] = $row;
		}
		$this->db->free($result);
		return $rows;
	}

	/** @return list<stdClass> */
	public function evidence(PowerPlant $plant, int $layoutId = 0): array
	{
		$sql = 'SELECT rowid, date_creation, proof_ref FROM '.MAIN_DB_PREFIX.'powerplantpv_monitor_layout'
			.' WHERE entity = '.((int) $plant->entity).' AND fk_powerplant = '.((int) $plant->id).' AND proof_ref IS NOT NULL';
		$sql .= $layoutId > 0 ? ' AND rowid = '.$layoutId : ' ORDER BY rowid DESC';
		$result = $this->query($sql);
		$rows = array();
		while ($row = $this->db->fetch_object($result)) {
			$rows[] = $row;
		}
		$this->db->free($result);
		return $rows;
	}

	/**
	 * @param array{site: string, devices: list<array{serial: string, model: string}>} $inventory
	 * @param array<string,mixed>|null $photo
	 */
	public function save(PowerPlant $plant, User $user, array $inventory, string $review, ?array $photo): void
	{
		$submitted = json_decode($review, true);
		if (!is_array($submitted) || !$submitted || array_values($submitted) !== $submitted || count($submitted) > count($inventory['devices'])) {
			throw new RuntimeException('PowerPlantPVLayoutInvalidReview');
		}
		$entity = (int) $plant->entity;
		$plantId = (int) $plant->id;
		$devices = array_column($inventory['devices'], 'serial');
		$file = '';
		if (!$this->db->begin()) {
			throw new RuntimeException('PowerPlantPVSupervisionDatabaseError');
		}
		try {
			$this->lockPlantAndSite($entity, $plantId, $inventory['site']);
			$lines = $this->compositionLines($plant);
			$existing = powerplantpvSerialImportFetchExistingSerials($plantId, $entity);
			$positions = array();
			foreach ($this->positionRows($plant) as $row) {
				$positions['sn:'.$row->serial_number][] = $row->position;
			}
			$confirmed = array();
			$serials = array();
			$now = dol_now();
			foreach ($submitted as $row) {
				if (!is_array($row) || !is_string($row['serial'] ?? null) || !in_array($row['serial'], $devices, true)
					|| !is_int($row['line'] ?? null) || !is_string($row['positions'] ?? null) || !is_string($row['previous'] ?? null)
					|| strlen($row['positions']) > 9216 || isset($serials['sn:'.$row['serial']])) {
					throw new RuntimeException('PowerPlantPVLayoutInvalidReview');
				}
				$serial = $row['serial'];
				$key = 'sn:'.$serial;
				$serials[$key] = true;
				$before = $positions[$key] ?? array();
				if (hash('sha256', implode("\n", $before)) !== $row['previous']) {
					throw new RuntimeException('PowerPlantPVSupervisionConcurrentChange');
				}
				$known = $existing[powerplantpvSerialImportSerialKey($serial)] ?? null;
				$lineId = $known !== null ? (int) $known['fk_powerplant_line'] : $row['line'];
				$newPositions = self::parsePositions($row['positions']);
				if ($lineId <= 0 || !isset($lines[$lineId])) {
					throw new RuntimeException('PowerPlantPVLayoutChooseLine');
				}
				if ($known !== null && ($known['serial_number'] !== $serial || ($row['line'] > 0 && $row['line'] !== $lineId))) {
					throw new RuntimeException('PowerPlantPVLayoutSerialConflict');
				}
				$serialId = $this->serialId($entity, $plantId, $serial);
				if ($serialId === 0) {
					$line = &$lines[$lineId];
					if ($known === null && powerplantpvSerialImportLineCapacity($line, 'add') <= 0) {
						throw new RuntimeException('PowerPlantPVLayoutLineFull');
					}
					$record = new PowerPlantPVSerialNumber($this->db);
					$record->entity = $entity;
					$record->fk_powerplant = $plantId;
					$record->fk_powerplant_line = $lineId;
					$record->fk_product = (int) $line['fk_product'];
					$record->fk_categorie = (int) $line['fk_categorie'];
					$record->serial_number = $serial;
					$record->source_file = 'ATMOCE Cloud';
					$record->datec = $now;
					$serialId = $record->create($user, 1);
					if ($serialId <= 0) {
						throw new RuntimeException('PowerPlantPVLayoutSerialConflict');
					}
					$line['stored_qty']++;
					unset($line);
				}
				$confirmed[] = array($serialId, array_diff($before, $newPositions), array_diff($newPositions, $before));
			}

			$proof = '';
			if ($photo !== null) {
				$proof = bin2hex(random_bytes(16));
				$file = powerplantpvIndexPhotoPath($entity, 'layout', $plantId, $proof, true);
				if ($file === '' || !powerplantpvIndexPhotoStore($photo, $file)) {
					throw new RuntimeException('PowerPlantPVLayoutInvalidPhoto');
				}
			}
			$table = MAIN_DB_PREFIX.'powerplantpv_monitor_layout';
			$this->query('INSERT INTO '.$table.' (entity, fk_powerplant, fk_user_creat, date_creation, proof_ref) VALUES ('
				.$entity.', '.$plantId.', '.((int) $user->id).", '".$this->db->idate($now)."', ".($proof === '' ? 'NULL' : $this->quote($proof)).')');
			$layoutId = (int) $this->db->last_insert_id($table);
			if ($layoutId <= 0) {
				throw new RuntimeException('PowerPlantPVSupervisionDatabaseError');
			}

			$scope = ' WHERE entity = '.$entity.' AND fk_powerplant = '.$plantId;
			$table = MAIN_DB_PREFIX.'powerplantpv_monitor_position';
			$this->query('DELETE p FROM '.$table.' AS p LEFT JOIN '.MAIN_DB_PREFIX.'powerplantpv_serialnumber AS s'
				.' ON s.rowid = p.fk_serial AND s.entity = p.entity AND s.fk_powerplant = p.fk_powerplant'
				.' WHERE p.entity = '.$entity.' AND p.fk_powerplant = '.$plantId.' AND s.rowid IS NULL');
			foreach ($confirmed as [$serialId, $removed]) {
				foreach ($removed as $position) {
					$this->query('DELETE FROM '.$table.$scope.' AND fk_serial = '.$serialId.' AND position = '.$this->quote($position));
				}
			}
			foreach ($confirmed as [$serialId, , $labels]) {
				foreach ($labels as $position) {
					$sql = 'INSERT INTO '.$table.' (entity, fk_powerplant, fk_serial, fk_layout, position) VALUES ('
						.$entity.', '.$plantId.', '.$serialId.', '.$layoutId.', '.$this->quote($position).')';
					if (!$this->db->query($sql)) {
						throw new RuntimeException('PowerPlantPVLayoutPositionConflict');
					}
				}
			}
			if (!$this->db->commit()) {
				throw new RuntimeException('PowerPlantPVSupervisionDatabaseError');
			}
		} catch (Throwable $error) {
			$this->db->rollback();
			if ($file !== '') {
				dol_delete_file($file, 1, 1, 1, null, false, 0);
			}
			throw $error;
		}
	}

	/** @return list<string> */
	public static function parsePositions(string $value): array
	{
		$positions = array();
		foreach (explode(',', $value) as $raw) {
			$label = mb_strtoupper(trim(preg_replace('/[ \t]+/u', ' ', $raw) ?? ''), 'UTF-8');
			if ($label === '') {
				continue;
			}
			if (mb_strlen($label, 'UTF-8') > 96 || !preg_match('/^[\pL\pN][\pL\pN ._\/-]*$/uD', $label)) {
				throw new RuntimeException('PowerPlantPVLayoutInvalidPosition');
			}
			$positions[] = $label;
		}
		if (count($positions) > 24 || count(array_unique($positions)) !== count($positions)) {
			throw new RuntimeException('PowerPlantPVLayoutPositionConflict');
		}
		return $positions;
	}

	private function serialId(int $entity, int $plantId, string $serial): int
	{
		$result = $this->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'powerplantpv_serialnumber WHERE entity = '.$entity
			.' AND fk_powerplant = '.$plantId.' AND BINARY serial_number = '.$this->quote($serial));
		$row = $this->db->fetch_object($result);
		$this->db->free($result);
		return (int) ($row->rowid ?? 0);
	}

	private function lockPlantAndSite(int $entity, int $plantId, string $site): void
	{
		$result = $this->query('SELECT l.site_id FROM '.MAIN_DB_PREFIX.'powerplantpv_powerplant AS p'
			.' INNER JOIN '.MAIN_DB_PREFIX.'powerplantpv_monitor_link AS l ON l.fk_powerplant = p.rowid AND l.entity = p.entity'
			.' INNER JOIN '.MAIN_DB_PREFIX.'powerplantpv_monitor_config AS c ON c.provider = l.provider AND c.entity = l.entity'
			.' WHERE p.entity = '.$entity.' AND p.rowid = '.$plantId." AND l.provider = 'atmoce' AND c.enabled = 1 FOR UPDATE");
		$row = $this->db->fetch_object($result);
		$this->db->free($result);
		if (!$row || $row->site_id !== $site) {
			throw new RuntimeException('PowerPlantPVSupervisionConcurrentChange');
		}
	}

	private function quote(string $value): string
	{
		return "'".$this->db->escape($value)."'";
	}

	/** @return mysqli_result|true */
	private function query(string $sql)
	{
		$result = $this->db->query($sql);
		if (!$result) {
			throw new RuntimeException('PowerPlantPVSupervisionDatabaseError');
		}
		return $result;
	}
}
