<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
dol_include_once('/powerplantpv/class/powerplantpvfileimport.class.php');
dol_include_once('/powerplantpv/class/powerplantpvproductimport.class.php');
dol_include_once('/powerplantpv/class/powerplantpvproductdictionary.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_powerplant.lib.php');

/**
 * Validate and upsert several Dolibarr products with their PV characteristics.
 */
class PowerPlantPVBulkProductImport
{
	/** @var DoliDB */
	private $db;

	/** @var int */
	private $entity;

	/** @var string */
	public $error = '';

	/** @var array<int,string> */
	public $errors = array();

	/** @param DoliDB $db Database handler @param int $entity Current entity */
	public function __construct($db, $entity)
	{
		$this->db = $db;
		$this->entity = (int) $entity;
	}

	/**
	 * @return array<int,string>
	 */
	public static function getNativeHeaders()
	{
		return array(
			'ref', 'category_code', 'label', 'description', 'status_sell', 'status_buy', 'price',
			'price_base_type', 'vat_rate', 'barcode', 'barcode_type_code', 'weight', 'weight_unit',
			'length', 'width', 'height', 'size_unit',
		);
	}

	/**
	 * Return a mixed template suitable for CSV and XLSX exports.
	 *
	 * @return array<int,string>
	 */
	public static function getTemplateHeaders()
	{
		$headers = array();
		foreach (self::getNativeHeaders() as $field) {
			$definition = self::getNativeFieldDefinition($field);
			$parts = array('type='.$definition['type']);
			if (!empty($definition['unit'])) {
				$parts[] = 'unit='.$definition['unit'];
			}
			$parts[] = 'format='.$definition['format'];
			$parts[] = 'source='.$definition['source'];
			$headers[] = $field.' ['.implode('; ', $parts).']';
		}
		$technical = array();
		foreach (array(
			'module' => PowerPlantPVProductImport::getModuleImportFields(),
			'inverter' => PowerPlantPVProductImport::getInverterImportFields(),
			'battery' => PowerPlantPVProductImport::getBatteryImportFields(),
		) as $type => $fields) {
			foreach ($fields as $field) {
				if (!isset($technical[$field])) {
					$technical[$field] = PowerPlantPVProductImport::getTemplateHeader($type, $field);
				}
			}
		}
		foreach (PowerPlantPVProductImport::getInverterMPPTCompositionTemplateFields(4, 2) as $field) {
			$unitfield = preg_replace('/^mppt_[0-9]+_(?:input_[0-9]+_)?/', '', $field);
			$unit = in_array($unitfield, array('voltage_min', 'voltage_max'), true) ? 'V' : (strpos($unitfield, 'current') !== false ? 'A' : ($unitfield === 'max_dc_power' ? 'W' : 'text'));
			$datatype = $unit === 'text' ? 'text' : 'decimal';
			$parts = array('type='.$datatype);
			if ($unit !== 'text') {
				$parts[] = 'unit='.$unit;
			}
			$parts[] = 'format='.($unit === 'text' ? 'TEXT' : 'SIGNED_DECIMAL');
			$technical[$field] = $field.' ['.implode('; ', $parts).']';
		}
		foreach (PowerPlantPVProductImport::getTechnicalDictionaryTemplateFields() as $field) {
			$technical[$field] = PowerPlantPVProductImport::getTemplateHeader('module', $field);
		}
		return array_merge($headers, array_values($technical));
	}

	/** @return array<string,string> */
	public static function getNativeFieldDefinition($field)
	{
		return PowerPlantPVProductImport::getNativeProductImportFieldDefinition($field);
	}

	/**
	 * Prevalidate every row without changing the database.
	 *
	 * @param array<int,array<int,string>> $rows Raw spreadsheet rows
	 * @param int $maxRows Maximum accepted data rows
	 * @param array<string,array<string,mixed>> $dictionaryResolutions Trusted dictionary decisions
	 * @return array<int,array<string,mixed>>
	 */
	public function previewRows(array $rows, $maxRows, array $dictionaryResolutions = array())
	{
		$this->error = '';
		$this->errors = array();
		$headerIndex = $this->findHeaderRow($rows);
		if ($headerIndex < 0) {
			$this->error = 'PowerPlantPVBulkImportMissingHeaders';
			return array();
		}
		$headers = (array) $rows[$headerIndex];
		$fileImport = new PowerPlantPVFileImport();
		$normalizedHeaders = $fileImport->normalizeHeaders($headers);
		$namedHeaders = array_values(array_filter($normalizedHeaders, static function ($header) {
			return $header !== '';
		}));
		if (count(array_unique($namedHeaders)) !== count($namedHeaders)) {
			$this->error = 'PowerPlantPVBulkImportDuplicateHeaders';
			return array();
		}
		$headerWarnings = array();
		$nativeFields = array_fill_keys(self::getNativeHeaders(), true);
		foreach ($normalizedHeaders as $columnIndex => $field) {
			if (!isset($nativeFields[$field])) {
				continue;
			}
			$rawHeader = isset($headers[$columnIndex]) ? (string) $headers[$columnIndex] : '';
			if (!$fileImport->validateDocumentedHeader($rawHeader, self::getNativeFieldDefinition($field), $field, $headerWarnings)) {
				$this->error = $fileImport->getLastError();
				return array();
			}
		}


		$preview = array();
		$seenRefs = array();
		for ($i = $headerIndex + 1; $i < count($rows); $i++) {
			$cells = (array) $rows[$i];
			if ($this->isEmptyRow($cells) || $fileImport->isTemplateMetadataRow($cells)) {
				continue;
			}
			if (count($preview) >= $maxRows) {
				$this->error = 'PowerPlantPVBulkImportTooManyRows';
				return array();
			}
			$assoc = $this->rowToAssoc($normalizedHeaders, $cells);
			$entry = $this->previewOneRow($headers, $cells, $assoc, $i + 1, $dictionaryResolutions);
			$refKey = strtoupper(trim((string) (isset($assoc['ref']) ? $assoc['ref'] : '')));
			if ($refKey !== '' && isset($seenRefs[$refKey])) {
				$entry['status'] = 'ERROR';
				$entry['errors'][] = 'PowerPlantPVBulkImportDuplicateReference';
				$preview[$seenRefs[$refKey]]['status'] = 'ERROR';
				$preview[$seenRefs[$refKey]]['errors'][] = 'PowerPlantPVBulkImportDuplicateReference';
			} elseif ($refKey !== '') {
				$seenRefs[$refKey] = count($preview);
			}
			$preview[] = $entry;
		}
		if (empty($preview)) {
			$this->error = 'ProductTechnicalImportNoUsableLine';
		}
		return $preview;
	}

	/**
	 * Execute each valid row in an independent transaction.
	 *
	 * @param array<int,array<string,mixed>> $preview Validated preview
	 * @param User   $user           Administrator
	 * @param string $sourceFilename Uploaded filename
	 * @return array<int,array<string,mixed>>
	 */
	public function execute(array $preview, User $user, $sourceFilename = '')
	{
		$results = array();
		foreach ($preview as $entry) {
			if (!isset($entry['status']) || in_array($entry['status'], array('ERROR', 'REVIEW'), true)) {
				$entry['result_status'] = 'ERROR';
				$results[] = $entry;
				continue;
			}
			$this->db->begin();
			$result = $this->upsertOne($entry, $user, $sourceFilename);
			if ($result['result'] < 0) {
				$this->db->rollback();
				$entry['result_status'] = 'ERROR';
				$entry['errors'][] = $result['error'];
			} else {
				$this->db->commit();
				$entry['product_id'] = $result['product_id'];
				$entry['result_status'] = $result['changed'] ? $entry['status'] : 'UNCHANGED';
			}
			$results[] = $entry;
		}
		return $results;
	}

	/** @return array<string,mixed> */
	private function previewOneRow(array $headers, array $cells, array $assoc, $line, array $dictionaryResolutions = array())
	{
		$errors = array();
		$ref = trim((string) (isset($assoc['ref']) ? $assoc['ref'] : ''));
		$category = strtoupper(trim((string) (isset($assoc['category_code']) ? $assoc['category_code'] : '')));
		if ($ref === '' || !preg_match('/^[A-Za-z0-9_.\/-]+$/D', $ref)) {
			$errors[] = 'PowerPlantPVBulkImportInvalidReference';
		}
		if (!in_array($category, array('MODULE', 'ONDULE', 'BATTER'), true) || $this->fetchCategoryId($category) <= 0) {
			$errors[] = 'PowerPlantPVBulkImportInvalidCategory';
		}

		$productInfo = $ref !== '' ? $this->findProductByReference($ref) : array();
		if (!empty($productInfo['other_entity'])) {
			$errors[] = 'PowerPlantPVBulkImportOtherEntityProduct';
		}
		if (!empty($productInfo['service'])) {
			$errors[] = 'PowerPlantPVBulkImportServiceForbidden';
		}
		$productId = isset($productInfo['id']) ? (int) $productInfo['id'] : 0;
		if ($productId <= 0 && trim((string) (isset($assoc['label']) ? $assoc['label'] : '')) === '') {
			$errors[] = 'PowerPlantPVBulkImportLabelRequired';
		}
		$currentCategory = $productId > 0 ? $this->fetchProductCategoryCode($productId) : '';
		if ($productId > 0 && $currentCategory !== '' && $currentCategory !== $category) {
			$errors[] = 'PowerPlantPVBulkImportCategoryChangeForbidden';
		}
		if ($productId > 0 && $category === 'BATTER' && powerplantpvProductHasNativeComponents($productId) !== 0) {
			$errors[] = 'ProductTechnicalImportBatteryKitForbidden';
		}

		$native = $this->normalizeNativeData($assoc, $errors);
		$technical = array();
		$dictionaryIssues = array();
		$dictionaryWarnings = array();
		$dictionaryPreview = array('complete' => true);
		if (empty($errors) && in_array($category, array('MODULE', 'ONDULE', 'BATTER'), true)) {
			$type = $category === 'MODULE' ? 'module' : ($category === 'ONDULE' ? 'inverter' : 'battery');
			$fileImport = new PowerPlantPVFileImport();
			$parsed = $fileImport->buildImportRows(array($headers, $cells), $type);
			if (!empty($parsed['rows'][0]['normalized'])) {
				$technical = $parsed['rows'][0]['normalized'];
			} elseif ($fileImport->getLastError() !== 'ProductTechnicalImportNoRecognizedColumn') {
				$errors[] = $fileImport->getLastError();
			}
			if ($this->hasForeignTechnicalData($assoc, $category)) {
				$errors[] = 'PowerPlantPVBulkImportTechnicalFamilyMismatch';
			}
			$dictionaryPreview = $this->analyzeDictionaryCodes($technical, $dictionaryResolutions);
			if ($dictionaryPreview === false) {
				$errors[] = $this->error !== '' ? $this->error : 'PowerPlantPVTechnicalDictionaryInvalidSelection';
			} else {
				$dictionaryIssues = $dictionaryPreview['issues'];
				$dictionaryWarnings = $dictionaryPreview['warnings'];
			}
		}
		if ($this->error !== '') {
			$errors[] = 'ErrorFailedToReadData';
		}

		return array(
			'line' => (int) $line,
			'ref' => $ref,
			'category_code' => $category,
			'status' => !empty($errors) ? 'ERROR' : (!empty($dictionaryIssues) && empty($dictionaryPreview['complete']) ? 'REVIEW' : ($productId > 0 ? 'UPDATE' : 'CREATE')),
			'product_id' => $productId,
			'native' => $native,
			'technical' => $technical,
			'raw' => $assoc,
			'errors' => $errors,
			'technical_dictionary_issues' => $dictionaryIssues,
			'technical_dictionary_warnings' => $dictionaryWarnings,
			'dictionary_resolutions' => $dictionaryResolutions,
		);
	}

	/** @return array<string,mixed> */
	private function normalizeNativeData(array $assoc, array &$errors)
	{
		$data = array();
		foreach (self::getNativeHeaders() as $field) {
			$value = isset($assoc[$field]) ? trim((string) $assoc[$field]) : '';
			if ($value === '') {
				continue;
			}
			$data[$field] = $value;
		}
		foreach (array('status_sell', 'status_buy') as $field) {
			if (isset($data[$field]) && !in_array($data[$field], array('0', '1'), true)) {
				$errors[] = 'PowerPlantPVBulkImportInvalidStatus';
			}
		}
		if (isset($data['price_base_type'])) {
			$data['price_base_type'] = strtoupper($data['price_base_type']);
			if (!in_array($data['price_base_type'], array('HT', 'TTC'), true)) {
				$errors[] = 'PowerPlantPVBulkImportInvalidPriceBase';
			}
		}
		foreach (array('price', 'vat_rate', 'weight', 'length', 'width', 'height') as $field) {
			if (!isset($data[$field])) {
				continue;
			}
			$number = powerplantpvParseTechnicalNumber($data[$field]);
			if ($number === null) {
				$errors[] = 'ProductTechnicalImportNumericValueRequired';
			} else {
				$data[$field] = $number;
			}
		}
		if (isset($data['weight_unit'])) {
			$data['weight_units'] = $this->resolveUnitScale($data['weight_unit'], 'weight');
			if ($data['weight_units'] === null) {
				$errors[] = 'PowerPlantPVBulkImportInvalidUnit';
			}
		}
		if (isset($data['size_unit'])) {
			$data['size_units'] = $this->resolveUnitScale($data['size_unit'], 'size');
			if ($data['size_units'] === null) {
				$errors[] = 'PowerPlantPVBulkImportInvalidUnit';
			}
		}
		if (isset($data['barcode_type_code'])) {
			$data['barcode_type'] = $this->resolveBarcodeType($data['barcode_type_code']);
			if ($data['barcode_type'] <= 0) {
				$errors[] = 'PowerPlantPVBulkImportInvalidBarcodeType';
			}
		}
		return $data;
	}

	/** @return array{result:int,product_id:int,changed:bool,error:string} */
	private function upsertOne(array $entry, User $user, $sourceFilename)
	{
		$product = new Product($this->db);
		$isCreate = empty($entry['product_id']);
		if (!$isCreate && $product->fetch((int) $entry['product_id']) <= 0) {
			return array('result' => -1, 'product_id' => 0, 'changed' => false, 'error' => 'ErrorRecordNotFound');
		}
		if (!$isCreate && ((int) $product->entity !== $this->entity || (int) $product->type !== 0)) {
			return array('result' => -1, 'product_id' => 0, 'changed' => false, 'error' => 'PowerPlantPVBulkImportProductScopeError');
		}

		$native = isset($entry['native']) && is_array($entry['native']) ? $entry['native'] : array();
		if ($isCreate) {
			$product->ref = (string) $entry['ref'];
			$product->label = (string) $native['label'];
			$product->type = 0;
			$product->status = 0;
			$product->status_buy = 0;
			$product->price = 0;
			$product->price_base_type = getDolGlobalString('PRODUCT_PRICE_BASE_TYPE', 'HT');
			$product->tva_tx = 0;
			$product->entity = $this->entity;
		} else {
			$product->fetch_optionals($product->id, null);
		}
		$currentCategory = $isCreate ? '' : $this->fetchProductCategoryCode((int) $product->id);
		$categoryChanged = $isCreate || $currentCategory !== (string) $entry['category_code'];
		$nativeChanged = $isCreate || $this->nativeDataChangesProduct($product, $native);
		$priceChanged = $isCreate || $this->priceDataChangesProduct($product, $native);
		$this->applyNativeProperties($product, $native);
		$categoryId = $this->fetchCategoryId((string) $entry['category_code']);
		if ($categoryId <= 0) {
			return array('result' => -1, 'product_id' => 0, 'changed' => false, 'error' => 'PowerPlantPVBulkImportInvalidCategory');
		}
		$product->array_options['options_categorie_photovoltaique'] = $categoryId;
		if ($isCreate) {
			$result = $product->create($user);
			if ($result <= 0) {
				return array('result' => -1, 'product_id' => (int) $product->id, 'changed' => false, 'error' => $product->error);
			}
		} elseif ($nativeChanged || $categoryChanged) {
			$result = $product->update($product->id, $user, 0);
			if ($result <= 0) {
				return array('result' => -1, 'product_id' => (int) $product->id, 'changed' => false, 'error' => $product->error);
			}
		}

		if ($priceChanged) {
			$price = isset($native['price']) ? $native['price'] : (isset($product->price) ? $product->price : 0);
			$base = isset($native['price_base_type']) ? $native['price_base_type'] : (isset($product->price_base_type) ? $product->price_base_type : 'HT');
			$vat = isset($native['vat_rate']) ? $native['vat_rate'] : (isset($product->tva_tx) ? $product->tva_tx : 0);
			if ($product->updatePrice($price, $base, $user, $vat) < 0) {
				return array('result' => -1, 'product_id' => (int) $product->id, 'changed' => false, 'error' => $product->error);
			}
		}

		$technical = isset($entry['technical']) && is_array($entry['technical']) ? $entry['technical'] : array();
		$technicalChanged = false;
		$traceSaved = false;
		$raw = array('line' => (int) $entry['line'], 'row' => $entry['raw']);
		$source = array('source' => 'bulk_file', 'filename' => (string) $sourceFilename, 'line' => (int) $entry['line']);
		$source['dictionary_resolutions'] = isset($entry['dictionary_resolutions']) && is_array($entry['dictionary_resolutions']) ? $entry['dictionary_resolutions'] : array();
		if (!empty($technical)) {
			$importer = new PowerPlantPVProductImport($this->db);
			if ($entry['category_code'] === 'MODULE') {
				$importResult = $importer->importModuleToProduct($product->id, $technical, $raw, $user, PowerPlantPVProductImport::STRATEGY_OVERWRITE_AFTER_CONFIRM, $source, $source['dictionary_resolutions'], false);
			} elseif ($entry['category_code'] === 'ONDULE') {
				$importResult = $importer->importInverterToProduct($product->id, $technical, $raw, $user, PowerPlantPVProductImport::STRATEGY_OVERWRITE_AFTER_CONFIRM, $source, $source['dictionary_resolutions'], false);
			} else {
				$importResult = $importer->importBatteryToProduct($product->id, $technical, $raw, $user, PowerPlantPVProductImport::STRATEGY_OVERWRITE_AFTER_CONFIRM, $source, $source['dictionary_resolutions'], false);
			}
			if ($importResult['result'] < 0) {
				return array('result' => -1, 'product_id' => (int) $product->id, 'changed' => false, 'error' => $importer->error);
			}
			$technicalChanged = $importResult['result'] > 0;
			$traceSaved = $technicalChanged;
		}
		if (!$traceSaved) {
			$importer = isset($importer) && $importer instanceof PowerPlantPVProductImport ? $importer : new PowerPlantPVProductImport($this->db);
			if ($importer->saveDataSource($product->id, $source, $raw, $technical, $user) < 0) {
				return array('result' => -1, 'product_id' => (int) $product->id, 'changed' => false, 'error' => $importer->error);
			}
		}
		return array('result' => 1, 'product_id' => (int) $product->id, 'changed' => $isCreate || $nativeChanged || $categoryChanged || $priceChanged || $technicalChanged, 'error' => '');
	}

	/** @return void */
	private function applyNativeProperties(Product $product, array $native)
	{
		$map = array(
			'label' => 'label', 'description' => 'description', 'status_sell' => 'status', 'status_buy' => 'status_buy',
			'barcode' => 'barcode', 'barcode_type' => 'barcode_type', 'weight' => 'weight', 'weight_units' => 'weight_units',
			'length' => 'length', 'width' => 'width', 'height' => 'height',
		);
		foreach ($map as $source => $target) {
			if (array_key_exists($source, $native)) {
				$product->{$target} = $native[$source];
			}
		}
		if (isset($native['size_units'])) {
			$product->length_units = $native['size_units'];
			$product->width_units = $native['size_units'];
			$product->height_units = $native['size_units'];
		}
	}

	/** @return array<string,mixed> */
	private function findProductByReference($ref)
	{
		$sql = 'SELECT rowid, entity, fk_product_type FROM '.$this->db->prefix().'product WHERE ref = \''.$this->db->escape($ref).'\' ORDER BY entity ASC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setDatabaseError();
			return array();
		}
		$hasOtherEntity = false;
		$found = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			if ((int) $obj->entity === $this->entity) {
				$found['id'] = (int) $obj->rowid;
				$found['service'] = (int) $obj->fk_product_type === 1;
			} else {
				$hasOtherEntity = true;
			}
		}
		$this->db->free($resql);
		if (empty($found['id']) && $hasOtherEntity) {
			$found['other_entity'] = true;
		}
		return $found;
	}

	/** @return string */
	private function fetchProductCategoryCode($productId)
	{
		$sql = 'SELECT c.code FROM '.$this->db->prefix().'product_extrafields as e';
		$sql .= ' LEFT JOIN '.$this->db->prefix().'c_powerplantpv_categorypv as c ON c.rowid = e.categorie_photovoltaique';
		$sql .= ' WHERE e.fk_object = '.((int) $productId);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setDatabaseError();
			return '';
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		return $obj ? (string) $obj->code : '';
	}

	/** @return int */
	private function fetchCategoryId($code)
	{
		$sql = 'SELECT rowid FROM '.$this->db->prefix().'c_powerplantpv_categorypv WHERE code = \''.$this->db->escape($code).'\' AND active = 1';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setDatabaseError();
			return 0;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		return $obj ? (int) $obj->rowid : 0;
	}

	/** @return int|null */
	private function resolveUnitScale($code, $type)
	{
		$sql = 'SELECT scale FROM '.$this->db->prefix().'c_units WHERE active = 1 AND unit_type = \''.$this->db->escape($type).'\'';
		$sql .= ' AND (UPPER(code) = UPPER(\''.$this->db->escape($code).'\') OR UPPER(short_label) = UPPER(\''.$this->db->escape($code).'\'))';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setDatabaseError();
			return null;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		return $obj ? (int) $obj->scale : null;
	}

	/** @return int */
	private function resolveBarcodeType($code)
	{
		$sql = 'SELECT rowid FROM '.$this->db->prefix().'c_barcode_type WHERE UPPER(code) = UPPER(\''.$this->db->escape($code).'\')';
		$sql .= ' AND entity = '.$this->entity." AND coder <> '0' ORDER BY rowid ASC";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setDatabaseError();
			return 0;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		return $obj ? (int) $obj->rowid : 0;
	}

	/**
	 * Analyze dictionary codes without writing to the database.
	 *
	 * @return array<string,mixed>|false
	 */
	private function analyzeDictionaryCodes(array $technical, array $dictionaryResolutions)
	{
		$groups = isset($technical['_technical_dictionary_codes']) && is_array($technical['_technical_dictionary_codes']) ? $technical['_technical_dictionary_codes'] : array();
		$service = new PowerPlantPVProductDictionary($this->db);
		$issues = array();
		$warnings = array();
		$complete = true;
		foreach ($groups as $type => $values) {
			if (!is_array($values)) {
				continue;
			}
			$plan = $service->buildImportResolutionPlan($type, $values, $this->entity, $dictionaryResolutions);
			if ($plan === false) {
				$this->error = $service->error;
				$this->errors = array_merge($this->errors, $service->errors);
				return false;
			}
			$issues = array_replace($issues, (array) $plan['issues']);
			$warnings = array_merge($warnings, (array) $plan['warnings']);
			if (empty($plan['complete'])) {
				$complete = false;
			}
		}
		return array('issues' => $issues, 'warnings' => $warnings, 'complete' => $complete);
	}

	/** @return bool */
	private function hasMpptData(array $assoc)
	{
		foreach ($assoc as $header => $value) {
			if (strpos((string) $header, 'mppt_') === 0 && trim((string) $value) !== '') {
				return true;
			}
		}
		return false;
	}

	/**
	 * Reject non-empty technical columns that belong only to another PV family.
	 *
	 * @param array<string,string> $assoc    Normalized row
	 * @param string               $category PV family code
	 * @return bool
	 */
	private function hasForeignTechnicalData(array $assoc, $category)
	{
		if ($category !== 'ONDULE' && $this->hasMpptData($assoc)) {
			return true;
		}
		$categoryTypes = array('MODULE' => 'module', 'ONDULE' => 'inverter', 'BATTER' => 'battery');
		if (!isset($categoryTypes[$category])) {
			return false;
		}
		$sets = array(
			'module' => array_fill_keys(PowerPlantPVProductImport::getModuleImportFields(), true),
			'inverter' => array_fill_keys(PowerPlantPVProductImport::getInverterImportFields(), true),
			'battery' => array_fill_keys(PowerPlantPVProductImport::getBatteryImportFields(), true),
		);
		$currentType = $categoryTypes[$category];
		foreach ($assoc as $header => $value) {
			if (trim((string) $value) === '' || isset($sets[$currentType][$header])) {
				continue;
			}
			foreach ($sets as $type => $fields) {
				if ($type !== $currentType && isset($fields[$header])) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Test whether non-price native values differ from the loaded product.
	 *
	 * @param Product             $product Product
	 * @param array<string,mixed> $native  Non-empty imported values
	 * @return bool
	 */
	private function nativeDataChangesProduct(Product $product, array $native)
	{
		$map = array(
			'label' => 'label', 'description' => 'description', 'status_sell' => 'status', 'status_buy' => 'status_buy',
			'barcode' => 'barcode', 'barcode_type' => 'barcode_type', 'weight' => 'weight', 'weight_units' => 'weight_units',
			'length' => 'length', 'width' => 'width', 'height' => 'height',
		);
		foreach ($map as $source => $target) {
			if (array_key_exists($source, $native) && (string) $product->{$target} !== (string) $native[$source]) {
				return true;
			}
		}
		if (isset($native['size_units'])) {
			foreach (array('length_units', 'width_units', 'height_units') as $target) {
				if ((string) $product->{$target} !== (string) $native['size_units']) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Test whether price data differs from the loaded product.
	 *
	 * @param Product             $product Product
	 * @param array<string,mixed> $native  Non-empty imported values
	 * @return bool
	 */
	private function priceDataChangesProduct(Product $product, array $native)
	{
		if (isset($native['price']) && (string) price2num($product->price, 'MU') !== (string) price2num($native['price'], 'MU')) {
			return true;
		}
		if (isset($native['price_base_type']) && (string) $product->price_base_type !== (string) $native['price_base_type']) {
			return true;
		}
		if (isset($native['vat_rate']) && (string) price2num($product->tva_tx, 'MU') !== (string) price2num($native['vat_rate'], 'MU')) {
			return true;
		}
		return false;
	}

	/** @return int */
	private function findHeaderRow(array $rows)
	{
		$fileImport = new PowerPlantPVFileImport();
		foreach ($rows as $index => $row) {
			$headers = $fileImport->normalizeHeaders((array) $row);
			if (in_array('ref', $headers, true) && in_array('category_code', $headers, true)) {
				return (int) $index;
			}
		}
		return -1;
	}

	/** @return array<string,string> */
	private function rowToAssoc(array $headers, array $cells)
	{
		$row = array();
		foreach ($headers as $index => $header) {
			if ($header !== '') {
				$row[$header] = isset($cells[$index]) ? (string) $cells[$index] : '';
			}
		}
		return $row;
	}

	/** @return bool */
	private function isEmptyRow(array $cells)
	{
		foreach ($cells as $cell) {
			if (trim((string) $cell) !== '') {
				return false;
			}
		}
		return true;
	}

	/** @return void */
	private function setDatabaseError()
	{
		$error = $this->db->lasterror();
		if ($error === '') {
			$error = 'ErrorFailedToReadData';
		}
		$this->error = $error;
		$this->errors[] = $error;
		dol_syslog(__METHOD__.' '.$error, LOG_ERR);
	}
}
