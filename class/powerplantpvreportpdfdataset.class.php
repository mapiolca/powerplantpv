<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		class/powerplantpvreportpdfdataset.class.php
 * \ingroup		powerplantpv
 * \brief		Dataset loader for dynamic intervention report PDFs.
 */

dol_include_once('/powerplantpv/class/powerplantpvreport.class.php');
dol_include_once('/powerplantpv/class/powerplantpvreportbuilder.class.php');
dol_include_once('/powerplantpv/class/powerplantpvreportsourceservice.class.php');
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

if (is_readable(DOL_DOCUMENT_ROOT.'/projet/class/project.class.php')) {
	require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
}

/**
 * Dataset loader and normalizer for the PowerPlantPV intervention report PDF.
 */
class PowerPlantPVReportPdfDataset
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
	 * Load all data required by the dynamic report PDF.
	 *
	 * @param	CommonObject	$intervention	Intervention object
	 * @param	Translate		$outputlangs	Output language
	 * @return	array<string,mixed>|int	Dataset or <0 on error
	 */
	public function loadForIntervention($intervention, $outputlangs)
	{
		$thirdparty = $this->loadThirdparty($intervention);
		$project = $this->loadProject($intervention);
		$internalContacts = $this->loadInternalContacts($intervention, $outputlangs);

		$empty = array(
			'intervention' => $intervention,
			'thirdparty' => $thirdparty,
			'project' => $project,
			'internal_contacts' => $internalContacts,
			'report' => null,
			'report_found' => false,
			'tree' => array(),
			'source_services' => array(),
			'contracts' => array(),
			'powerplants' => array(),
			'powerplants_by_id' => array(),
			'equipment' => array(),
			'equipment_by_powerplant' => array(),
			'general_sections' => array(),
			'sections_by_powerplant' => array(),
		);

		$report = new PowerPlantPVReport($this->db);
		$result = $report->fetchByIntervention((int) $intervention->id);
		if ($result < 0) {
			$this->copyErrorsFrom($report);
			return -1;
		}
		if ($result === 0) {
			return $empty;
		}

		$builder = new PowerPlantPVReportBuilder($this->db);
		$tree = $builder->loadReportTree((int) $report->id);
		if (!is_array($tree)) {
			$this->copyErrorsFrom($builder);
			return -1;
		}

		$sourceServiceObject = new PowerPlantPVReportSourceService($this->db);
		$sourceServices = $sourceServiceObject->fetchAllByReport((int) $report->id, 'position', 'ASC');
		if (!is_array($sourceServices)) {
			$this->copyErrorsFrom($sourceServiceObject);
			return -1;
		}

		$powerplants = isset($tree['powerplants']) && is_array($tree['powerplants']) ? $tree['powerplants'] : array();
		$equipment = isset($tree['equipment']) && is_array($tree['equipment']) ? $tree['equipment'] : array();
		$sections = isset($tree['sections']) && is_array($tree['sections']) ? $tree['sections'] : array();

		$powerplantsById = array();
		foreach ($powerplants as $powerplant) {
			if (is_object($powerplant)) {
				$powerplantsById[(int) $powerplant->id] = $powerplant;
			}
		}

		$equipmentByPowerplant = array();
		foreach ($equipment as $equipmentRow) {
			if (!is_object($equipmentRow)) {
				continue;
			}
			$powerplantId = (int) $equipmentRow->fk_report_powerplant;
			if (!isset($equipmentByPowerplant[$powerplantId])) {
				$equipmentByPowerplant[$powerplantId] = array();
			}
			$equipmentByPowerplant[$powerplantId][] = $equipmentRow;
		}

		$generalSections = array();
		$sectionsByPowerplant = array();
		foreach ($sections as $sectionRow) {
			if (!is_array($sectionRow) || !isset($sectionRow['section']) || !is_object($sectionRow['section'])) {
				continue;
			}
			$section = $sectionRow['section'];
			if (PowerPlantPVReportBuilder::isIgnoredReportSectionCode((string) $section->section_code)) {
				continue;
			}
			$powerplantId = (int) $section->fk_report_powerplant;
			if ($powerplantId > 0) {
				if (!isset($sectionsByPowerplant[$powerplantId])) {
					$sectionsByPowerplant[$powerplantId] = array();
				}
				$sectionsByPowerplant[$powerplantId][] = $sectionRow;
			} else {
				$generalSections[] = $sectionRow;
			}
		}

		return array(
			'intervention' => $intervention,
			'thirdparty' => $thirdparty,
			'project' => $project,
			'internal_contacts' => $internalContacts,
			'report' => $report,
			'report_found' => true,
			'tree' => $tree,
			'source_services' => $sourceServices,
			'contracts' => $this->extractContracts($sourceServices),
			'powerplants' => $powerplants,
			'powerplants_by_id' => $powerplantsById,
			'equipment' => $equipment,
			'equipment_by_powerplant' => $equipmentByPowerplant,
			'general_sections' => $generalSections,
			'sections_by_powerplant' => $sectionsByPowerplant,
		);
	}

	/**
	 * Return a localized snapshot label from an object.
	 *
	 * @param	object|null	$object			Object
	 * @param	string		$property		Base property
	 * @param	Translate	$outputlangs	Output language
	 * @return	string						Label
	 */
	public static function localizedProperty($object, $property, $outputlangs)
	{
		if (!is_object($object)) {
			return '';
		}
		$value = '';
		if (isset($object->{$property})) {
			$value = (string) $object->{$property};
		}
		if (is_object($outputlangs) && $outputlangs->defaultlang === 'en_US') {
			$englishProperty = $property.'_en';
			if (!empty($object->{$englishProperty})) {
				$value = (string) $object->{$englishProperty};
			}
		}

		return $value;
	}

	/**
	 * Return true when a report field type stores its value in value_number.
	 *
	 * @param	string	$fieldType	Field type
	 * @return	bool				True for numeric field types
	 */
	public static function isNumericFieldType($fieldType)
	{
		return in_array((string) $fieldType, array('number', 'double', 'real', 'integer', 'price'), true);
	}

	/**
	 * Return true when a field is a file field.
	 *
	 * @param	PowerPlantPVReportField	$field	Field
	 * @return	bool							True for file fields
	 */
	public static function isFileField($field)
	{
		return is_object($field) && (string) $field->field_type === 'file';
	}

	/**
	 * Return true when a field is a signature field.
	 *
	 * @param	PowerPlantPVReportField	$field	Field
	 * @return	bool							True for signature fields
	 */
	public static function isSignatureField($field)
	{
		return is_object($field) && (string) $field->field_type === 'signature';
	}

	/**
	 * Return field options indexed by code.
	 *
	 * @param	PowerPlantPVReportField	$field			Field
	 * @param	Translate				$outputlangs	Output language
	 * @return	array<string,string>						Options
	 */
	public static function fieldOptions($field, $outputlangs)
	{
		if (!is_object($field)) {
			return array();
		}
		if ((string) $field->field_type === 'conformity_so_valid_obs') {
			return array(
				'valid' => $outputlangs->transnoentities('PowerPlantPVReportConformityValid'),
				'observation' => $outputlangs->transnoentities('PowerPlantPVReportConformityObservation'),
				'not_applicable' => $outputlangs->transnoentities('PowerPlantPVReportConformityNotApplicable'),
			);
		}

		$options = array();
		$decoded = !empty($field->options_snapshot) ? json_decode((string) $field->options_snapshot, true) : array();
		if (!is_array($decoded)) {
			return $options;
		}

		foreach ($decoded as $option) {
			if (!is_array($option) || empty($option['code'])) {
				continue;
			}
			$label = isset($option['label']) ? (string) $option['label'] : (string) $option['code'];
			if (is_object($outputlangs) && $outputlangs->defaultlang === 'en_US' && !empty($option['label_en'])) {
				$label = (string) $option['label_en'];
			}
			$options[(string) $option['code']] = $label;
		}

		return $options;
	}

	/**
	 * Format a field value for the PDF.
	 *
	 * @param	PowerPlantPVReportField	$field			Field
	 * @param	Translate				$outputlangs	Output language
	 * @return	string									Formatted value
	 */
	public static function formatFieldValue($field, $outputlangs)
	{
		if (!is_object($field)) {
			return '';
		}

		$type = (string) $field->field_type;
		if (self::isNumericFieldType($type)) {
			if ($field->value_number === null || (string) $field->value_number === '') {
				return '';
			}
			$value = price($field->value_number);
			if (!empty($field->unit)) {
				$value .= ' '.(string) $field->unit;
			}
			return $value;
		}

		if ($type === 'date' || $type === 'datetime') {
			$date = self::normalizeDateValue($field->value_date);
			if (empty($date)) {
				return '';
			}
			return dol_print_date($date, $type === 'datetime' ? 'dayhour' : 'day', 'tzuser', $outputlangs);
		}

		if (in_array($type, array('checkbox', 'yesno', 'boolean'), true)) {
			if ((string) $field->value_text === '') {
				return '';
			}
			return ((int) $field->value_text) ? $outputlangs->transnoentities('Yes') : $outputlangs->transnoentities('No');
		}

		if (in_array($type, array('select', 'conformity_so_valid_obs'), true)) {
			$options = self::fieldOptions($field, $outputlangs);
			$code = (string) $field->value_text;
			return isset($options[$code]) ? $options[$code] : $code;
		}

		if ($type === 'multiselect') {
			$options = self::fieldOptions($field, $outputlangs);
			$values = preg_split('/\r\n|\r|\n/', (string) $field->value_text);
			if (!is_array($values)) {
				return '';
			}
			$labels = array();
			foreach ($values as $code) {
				$code = trim((string) $code);
				if ($code === '') {
					continue;
				}
				$labels[] = isset($options[$code]) ? $options[$code] : $code;
			}
			return implode(', ', $labels);
		}

		$value = (string) $field->value_text;
		if (!empty($field->unit) && $value !== '') {
			$value .= ' '.(string) $field->unit;
		}

		return $value;
	}

	/**
	 * Build an array of fields indexed by field code.
	 *
	 * @param	array<int,PowerPlantPVReportField>	$fields	Fields
	 * @return	array<string,PowerPlantPVReportField>		Fields by code
	 */
	public static function fieldsByCode($fields)
	{
		$byCode = array();
		if (!is_array($fields)) {
			return $byCode;
		}
		foreach ($fields as $field) {
			if (is_object($field)) {
				$byCode[(string) $field->field_code] = $field;
			}
		}

		return $byCode;
	}

	/**
	 * Resolve the absolute path of a report file snapshot.
	 *
	 * @param	PowerPlantPVReport|null	$report	Report
	 * @param	PowerPlantPVReportFile	$file	File snapshot
	 * @return	string							Absolute path, or empty string
	 */
	public static function resolveReportFilePath($report, $file)
	{
		global $conf;

		if (!is_object($report) || !is_object($file) || empty($file->filepath)) {
			return '';
		}
		if (strpos((string) $file->filepath, '..') !== false) {
			return '';
		}
		$objectEntity = !empty($report->entity) ? (int) $report->entity : (int) $conf->entity;
		$baseDir = '';
		if (!empty($conf->powerplantpv->multidir_output[$objectEntity])) {
			$baseDir = $conf->powerplantpv->multidir_output[$objectEntity];
		} elseif (!empty($conf->powerplantpv->dir_output)) {
			$baseDir = $conf->powerplantpv->dir_output;
		}
		if ($baseDir === '') {
			return '';
		}

		$path = rtrim($baseDir, '/').'/'.ltrim((string) $file->filepath, '/');
		if (!is_readable($path)) {
			return '';
		}

		$realBase = realpath($baseDir);
		$realPath = realpath($path);
		if (!is_string($realBase) || !is_string($realPath) || strpos($realPath, $realBase) !== 0) {
			return '';
		}

		return $realPath;
	}

	/**
	 * Return true if a file snapshot is an image.
	 *
	 * @param	PowerPlantPVReportFile	$file	File snapshot
	 * @param	string					$path	Resolved path
	 * @return	bool							True for image files
	 */
	public static function isImageFile($file, $path = '')
	{
		if (is_object($file) && !empty($file->filemime) && strpos((string) $file->filemime, 'image/') === 0) {
			return true;
		}
		$name = is_object($file) ? (string) $file->filename : $path;
		$extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

		return in_array($extension, array('jpg', 'jpeg', 'png', 'gif'), true);
	}

	/**
	 * Normalize a date or datetime value.
	 *
	 * @param	mixed	$value	Date value
	 * @return	int|string		Dolibarr timestamp/string usable by dol_print_date
	 */
	public static function normalizeDateValue($value)
	{
		if ($value === null || (string) $value === '') {
			return '';
		}
		if (is_numeric($value)) {
			return (int) $value;
		}
		$timestamp = strtotime((string) $value);

		return $timestamp > 0 ? $timestamp : '';
	}

	/**
	 * Load the intervention thirdparty.
	 *
	 * @param	CommonObject	$intervention	Intervention object
	 * @return	Societe|null					Thirdparty
	 */
	private function loadThirdparty($intervention)
	{
		if (method_exists($intervention, 'fetch_thirdparty')) {
			$result = $intervention->fetch_thirdparty();
			if ($result > 0 && !empty($intervention->thirdparty) && is_object($intervention->thirdparty)) {
				return $intervention->thirdparty;
			}
		}
		if (!empty($intervention->socid)) {
			$thirdparty = new Societe($this->db);
			if ($thirdparty->fetch((int) $intervention->socid) > 0) {
				return $thirdparty;
			}
		}

		return null;
	}

	/**
	 * Load the intervention project.
	 *
	 * @param	CommonObject	$intervention	Intervention object
	 * @return	Project|null					Project
	 */
	private function loadProject($intervention)
	{
		if (!class_exists('Project') || empty($intervention->fk_project)) {
			return null;
		}
		$project = new Project($this->db);
		if ($project->fetch((int) $intervention->fk_project) > 0) {
			return $project;
		}

		return null;
	}

	/**
	 * Load native internal contacts linked to the intervention.
	 *
	 * @param	CommonObject	$intervention	Intervention object
	 * @param	Translate		$outputlangs	Output language
	 * @return	array<int,array<string,string>>	Contacts
	 */
	private function loadInternalContacts($intervention, $outputlangs)
	{
		$contacts = array();
		if (!method_exists($intervention, 'liste_contact')) {
			return $contacts;
		}

		$rows = $intervention->liste_contact(-1, 'internal');
		if (!is_array($rows)) {
			return $contacts;
		}

		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			$userId = !empty($row['id']) ? (int) $row['id'] : 0;
			$fullName = '';
			$email = '';
			if ($userId > 0) {
				$tmpuser = new User($this->db);
				if ($tmpuser->fetch($userId) > 0) {
					$fullName = $tmpuser->getFullName($outputlangs);
					$email = (string) $tmpuser->email;
				}
			}
			if ($fullName === '' && !empty($row['name'])) {
				$fullName = (string) $row['name'];
			}
			if ($fullName === '' && (!empty($row['firstname']) || !empty($row['lastname']))) {
				$fullName = trim((string) ($row['firstname'] ?? '').' '.(string) ($row['lastname'] ?? ''));
			}
			if ($fullName === '') {
				continue;
			}
			$contacts[] = array(
				'name' => $fullName,
				'function' => !empty($row['libelle']) ? (string) $row['libelle'] : '',
				'email' => $email,
			);
		}

		return $contacts;
	}

	/**
	 * Extract unique contracts from source service snapshots.
	 *
	 * @param	array<int,PowerPlantPVReportSourceService>	$sourceServices	Source services
	 * @return	array<int,array<string,mixed>>								Contracts
	 */
	private function extractContracts($sourceServices)
	{
		$contracts = array();
		$seen = array();
		foreach ($sourceServices as $sourceService) {
			if (!is_object($sourceService) || empty($sourceService->fk_contract)) {
				continue;
			}
			$key = (string) ((int) $sourceService->fk_contract);
			if (isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;
			$contracts[] = array(
				'id' => (int) $sourceService->fk_contract,
				'ref' => (string) $sourceService->contract_ref,
				'fk_report_powerplant' => (int) $sourceService->fk_report_powerplant,
			);
		}

		return $contracts;
	}

	/**
	 * Copy errors from another module object.
	 *
	 * @param	object	$object	Object with errors
	 * @return	void
	 */
	private function copyErrorsFrom($object)
	{
		if (!empty($object->error)) {
			$this->error = (string) $object->error;
		}
		if (!empty($object->errors) && is_array($object->errors)) {
			foreach ($object->errors as $error) {
				$this->errors[] = (string) $error;
			}
		}
		if ($this->error === '' && !empty($this->errors)) {
			$this->error = (string) reset($this->errors);
		}
	}
}
