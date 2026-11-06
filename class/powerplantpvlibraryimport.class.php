<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once __DIR__.'/powerplantpvlibrarycontract.class.php';
require_once __DIR__.'/powerplantpvproductdatasource.class.php';

/** First import creates a product. Replays return the mapping; updates require the future three-way workflow. */
class PowerPlantPVLibraryImport
{
	/** @var DoliDB */
	public $db;
	public function __construct($db) { $this->db = $db; }

	/** @param array<string,mixed> $item @return int Product id */
	public function import(array $item, string $libraryId, User $user): int
	{
		global $conf;
		if (!isModEnabled('powerplantpv') || !isModEnabled('product') || $user->socid > 0
			|| !$user->hasRight('powerplantpv', 'library', 'import') || !$user->hasRight('produit', 'creer') || !$user->hasRight('produit', 'lire')) {
			throw new RuntimeException('NotEnoughPermissions');
		}
		PowerPlantPVLibraryContract::validate($item);
		if (!preg_match('/^[a-f0-9]{32}$/D', $libraryId)) {
			throw new InvalidArgumentException('PVLibraryInvalidIdentity');
		}
		$source = 'pvlibrary';
		$mapping = new PowerPlantPVProductDataSource($this->db);
		// MySQL advisory lock covers all channels; uniqueness in datasource remains the final guard.
		$lock = 'pvlib:'.hash('sha256', $conf->entity.':'.$libraryId.':'.$item['catalog_id']);
		$lock = substr($lock, 0, 64);
		$res = $this->db->query("SELECT GET_LOCK('".$this->db->escape($lock)."', 0) AS acquired");
		$row = $res ? $this->db->fetch_object($res) : null;
		if (!is_object($row) || (int) $row->acquired !== 1) {
			throw new RuntimeException('PVLibraryBusy');
		}
		try {
			$found = $mapping->fetchBySource($source, $libraryId, $item['catalog_id']);
			if ($found < 0) { throw new RuntimeException('PVLibraryDatabaseError'); }
			if ($found > 0) {
				$product = new Product($this->db);
				$id = (int) ($mapping->data['fk_product'] ?? 0);
				if ($product->fetch($id) <= 0 || (int) $product->entity !== (int) $conf->entity) { throw new RuntimeException('PVLibraryMappingUnavailable'); }
				return $id;
			}
			if ($this->db->begin() <= 0) { throw new RuntimeException('PVLibraryDatabaseError'); }
			try {
				$categoryCode = $item['type'] === 'module' ? 'MODULE' : 'ONDULE';
				$res = $this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."c_powerplantpv_categorypv WHERE active = 1 AND code = '".$categoryCode."'");
				$category = $res ? $this->db->fetch_object($res) : null;
				if (!is_object($category)) { throw new RuntimeException('PVLibraryCategoryUnavailable'); }
				$product = new Product($this->db);
				// Deterministic source reference avoids collisions across libraries and variants. No number sequence is consumed.
				$product->ref = 'PVL-'.substr(hash('sha256', $libraryId.':'.$item['catalog_id']), 0, 24);
				$product->label = $item['label'];
				$product->description = $item['manufacturer'].' '.$item['model'];
				$product->type = 0;
				$product->entity = (int) $conf->entity;
				$product->status = 0;
				$product->status_buy = 0;
				$product->array_options['options_categorie_photovoltaique'] = (int) $category->rowid;
				if ($product->create($user) <= 0) { throw new RuntimeException('PVLibraryProductCreationFailed'); }
				$technical = PowerPlantPVLibraryContract::normalized($item);
				$importer = new PowerPlantPVProductImport($this->db);
				$trace = array('source' => $source, 'source_dataset' => $libraryId, 'source_key' => $item['catalog_id'], 'source_name' => $item['manufacturer'].' '.$item['model']);
				if ($item['type'] === 'module') {
					$result = $importer->importModuleToProduct($product->id, $technical, $item, $user, PowerPlantPVProductImport::STRATEGY_EMPTY_ONLY, $trace, array(), false);
				} else {
					$result = $importer->importInverterToProduct($product->id, $technical, $item, $user, PowerPlantPVProductImport::STRATEGY_EMPTY_ONLY, $trace, array(), false);
				}
				if ($result['result'] < 0 || $importer->saveDataSource($product->id, $trace, $item, $technical, $user) < 0) { throw new RuntimeException('PVLibraryTechnicalImportFailed'); }
				if ($this->db->commit() <= 0) { throw new RuntimeException('PVLibraryDatabaseError'); }
				return (int) $product->id;
			} catch (Throwable $e) {
				$this->db->rollback();
				throw $e;
			}
		} finally {
			$this->db->query("SELECT RELEASE_LOCK('".$this->db->escape($lock)."')");
		}
	}
}
