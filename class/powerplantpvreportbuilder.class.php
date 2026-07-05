<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		class/powerplantpvreportbuilder.class.php
 * \ingroup		powerplantpv
 * \brief		Generated intervention report builder.
 */

dol_include_once('/powerplantpv/class/powerplantpvreport.class.php');
dol_include_once('/powerplantpv/class/powerplantpvreportpowerplant.class.php');
dol_include_once('/powerplantpv/class/powerplantpvreportsourceservice.class.php');
dol_include_once('/powerplantpv/class/powerplantpvreportequipment.class.php');
dol_include_once('/powerplantpv/class/powerplantpvreportsection.class.php');
dol_include_once('/powerplantpv/class/powerplantpvreportfield.class.php');
dol_include_once('/powerplantpv/class/powerplantpvreportfile.class.php');
dol_include_once('/powerplantpv/class/powerplantpvreportdcmeasure.class.php');
dol_include_once('/powerplantpv/class/powerplantpvindexreading.class.php');
dol_include_once('/powerplantpv/class/powerplantpvinterventionnature.class.php');
dol_include_once('/powerplantpv/class/powerplantpvreporttemplate.class.php');
dol_include_once('/powerplantpv/class/powerplantpvreporttemplatesection.class.php');
dol_include_once('/powerplantpv/class/powerplantpvreporttemplatefield.class.php');
dol_include_once('/powerplantpv/class/powerplantpvreporttemplatefieldoption.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_reporttemplate.lib.php');

/**
 * Generated intervention report builder.
 */
class PowerPlantPVReportBuilder
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
	 * Build a read-only preview tree from current model data without writing to database.
	 *
	 * @param	CommonObject	$intervention	Intervention object
	 * @param	int[]			$manualServiceIds	Manual maintenance service ids
	 * @return	array<string,mixed>			Preview tree
	 */
	public function buildPreviewTree($intervention, $manualServiceIds = array())
	{
		$context = $this->buildGenerationContext($intervention, $manualServiceIds);
		if (empty($context['can_generate'])) {
			return $context;
		}

		$report = $this->newReportFromContext($context, PowerPlantPVReport::STATUS_DRAFT);
		$tree = $this->buildSectionFieldObjects($context, 0, array(), array());
		$context['report'] = $report;
		$context['sections'] = $tree['sections'];

		return $context;
	}

	/**
	 * Load an existing snapshot tree.
	 *
	 * @param	int	$reportId	Report id
	 * @return	array<string,mixed>|int	Tree or <0 on error
	 */
	public function loadReportTree($reportId)
	{
		$report = new PowerPlantPVReport($this->db);
		$result = $report->fetch((int) $reportId);
		if ($result <= 0) {
			return $result;
		}

		$sectionObject = new PowerPlantPVReportSection($this->db);
		$sections = $sectionObject->fetchAllByReport((int) $report->id, 'position', 'ASC');
		if (!is_array($sections)) {
			$this->copyErrorsFrom($sectionObject);
			return -1;
		}

		$powerplantObject = new PowerPlantPVReportPowerPlant($this->db);
		$powerplants = $powerplantObject->fetchAllByReport((int) $report->id, 'position', 'ASC');
		if (!is_array($powerplants)) {
			$this->copyErrorsFrom($powerplantObject);
			return -1;
		}
		$powerplantsById = array();
		foreach ($powerplants as $powerplant) {
			$powerplantsById[(int) $powerplant->id] = $powerplant;
		}

		$equipmentObject = new PowerPlantPVReportEquipment($this->db);
		$equipmentRows = $equipmentObject->fetchAllByReport((int) $report->id, 'position', 'ASC');
		if (!is_array($equipmentRows)) {
			$this->copyErrorsFrom($equipmentObject);
			return -1;
		}
		$equipmentById = array();
		foreach ($equipmentRows as $equipment) {
			$equipmentById[(int) $equipment->id] = $equipment;
		}

		$fieldObject = new PowerPlantPVReportField($this->db);
		$fileObject = new PowerPlantPVReportFile($this->db);
		$dcMeasureObject = new PowerPlantPVReportDcMeasure($this->db);
		$sectionRows = array();
		foreach ($sections as $section) {
			$fields = $fieldObject->fetchAllBySection((int) $section->id, 'position', 'ASC');
			if (!is_array($fields)) {
				$this->copyErrorsFrom($fieldObject);
				return -1;
			}
			foreach ($fields as $fieldKey => $field) {
				$files = $fileObject->fetchAllByField((int) $field->id);
				if (!is_array($files)) {
					$this->copyErrorsFrom($fileObject);
					return -1;
				}
				$fields[$fieldKey]->files = $files;
			}
			$dcMeasures = array();
			if ((string) $section->section_code === 'DC_ELECTRICAL_MEASURE') {
				$dcMeasures = $dcMeasureObject->fetchAllBySection((int) $section->id, 'position', 'ASC');
				if (!is_array($dcMeasures)) {
					$this->copyErrorsFrom($dcMeasureObject);
					return -1;
				}
			}
			$sectionRows[] = array(
				'section' => $section,
				'fields' => $fields,
				'dc_measures' => $dcMeasures,
				'powerplant' => !empty($powerplantsById[(int) $section->fk_report_powerplant]) ? $powerplantsById[(int) $section->fk_report_powerplant] : null,
				'equipment' => !empty($equipmentById[(int) $section->fk_report_equipment]) ? $equipmentById[(int) $section->fk_report_equipment] : null,
			);
		}

		return array(
			'can_generate' => 1,
			'messages' => array(),
			'report' => $report,
			'powerplants' => $powerplants,
			'equipment' => $equipmentRows,
			'sections' => $sectionRows,
		);
	}

	/**
	 * Create a snapshot from current model data.
	 *
	 * @param	CommonObject	$intervention	Intervention object
	 * @param	User			$user			User
	 * @param	int[]			$manualServiceIds	Manual maintenance service ids
	 * @param	string			$status			Report status
	 * @return	PowerPlantPVReport|int	Report object or <0 on error
	 */
	public function createSnapshot($intervention, User $user, $manualServiceIds = array(), $status = PowerPlantPVReport::STATUS_DRAFT)
	{
		$context = $this->buildGenerationContext($intervention, $manualServiceIds);
		if (empty($context['can_generate'])) {
			$this->errors = isset($context['messages']) && is_array($context['messages']) ? $context['messages'] : array('PowerPlantPVReportCannotBeGenerated');
			$this->error = (string) reset($this->errors);
			return -1;
		}

		$this->db->begin();
		$report = $this->newReportFromContext($context, $status);
		$reportId = $report->create($user, 0);
		if ($reportId <= 0) {
			$this->copyErrorsFrom($report);
			$this->db->rollback();
			return -1;
		}
		$report->id = $reportId;
		$report->rowid = $reportId;

		$result = $this->persistSnapshotChildren($report, $context, $user, array(), array());
		if ($result < 0) {
			$this->db->rollback();
			return -1;
		}
		if ((string) $status === PowerPlantPVReport::STATUS_SAVED) {
			$result = $this->syncIndexReadingsFromReport($report, $user);
			if ($result < 0) {
				$this->db->rollback();
				return -1;
			}
		}

		$this->db->commit();

		return $report;
	}

	/**
	 * Recalculate a snapshot from the current model while preserving matching values.
	 *
	 * @param	PowerPlantPVReport	$report			Existing report
	 * @param	CommonObject		$intervention	Intervention object
	 * @param	User				$user			User
	 * @param	int[]				$manualServiceIds	Manual maintenance service ids
	 * @param	string				$status			Report status
	 * @return	int									>0 if OK, <0 on error
	 */
	public function recalculateSnapshot(PowerPlantPVReport $report, $intervention, User $user, $manualServiceIds = array(), $status = PowerPlantPVReport::STATUS_DRAFT)
	{
		$context = $this->buildGenerationContext($intervention, $manualServiceIds);
		if (empty($context['can_generate'])) {
			$this->errors = isset($context['messages']) && is_array($context['messages']) ? $context['messages'] : array('PowerPlantPVReportCannotBeGenerated');
			$this->error = (string) reset($this->errors);
			return -1;
		}

		$oldValues = $this->fetchExistingFieldValues((int) $report->id);
		$oldFiles = $this->fetchExistingFilesByStableKey((int) $report->id);
		$oldDcValues = $this->fetchExistingDcMeasureValues((int) $report->id);

		$this->db->begin();
		$report->status = $status;
		$report->fk_soc = $context['fk_soc'];
		$report->fk_project = $context['fk_project'];
		$report->fk_intervention_nature = $context['nature']['id'];
		$report->intervention_nature_code = $context['nature']['code'];
		$report->intervention_nature_label = $context['nature']['label'];
		$report->intervention_nature_label_en = $context['nature']['label_en'];
		$report->fk_report_template = $context['template']['id'];
		$report->report_template_code = $context['template']['code'];
		$report->report_template_label = $context['template']['label'];
		$report->report_template_label_en = $context['template']['label_en'];
		$report->source_mode = $context['source_mode'];
		if ($report->update($user, 0) < 0) {
			$this->copyErrorsFrom($report);
			$this->db->rollback();
			return -1;
		}

		$result = $this->archiveExistingFieldStableKeys((int) $report->id);
		if ($result < 0 || $this->deleteSnapshotChildren((int) $report->id, 0) < 0) {
			$this->db->rollback();
			return -1;
		}

		$newFieldIds = $this->persistSnapshotChildren($report, $context, $user, $oldValues, $oldDcValues);
		if ($newFieldIds < 0) {
			$this->db->rollback();
			return -1;
		}
		$result = $this->reattachExistingFiles($oldFiles, $newFieldIds);
		if ($result < 0) {
			$this->db->rollback();
			return -1;
		}
		$result = $this->deleteArchivedFields((int) $report->id, $newFieldIds);
		if ($result < 0) {
			$this->db->rollback();
			return -1;
		}
		if ((string) $status === PowerPlantPVReport::STATUS_SAVED) {
			$result = $this->syncIndexReadingsFromReport($report, $user);
			if ($result < 0) {
				$this->db->rollback();
				return -1;
			}
		} else {
			$result = $this->deactivateIndexReadingsForReport((int) $report->id, $user);
			if ($result < 0) {
				$this->db->rollback();
				return -1;
			}
		}

		$this->db->commit();

		return 1;
	}

	/**
	 * Save submitted values into existing report fields.
	 *
	 * @param	int						$reportId	Report id
	 * @param	array<string,mixed>		$values		Values by stable key
	 * @param	array<string,mixed>		$dateValues	Date values by stable key
	 * @param	User					$user		User
	 * @param	string					$status		Report status
	 * @return	int									>0 if OK, <0 on error
	 */
	public function saveValues($reportId, $values, $dateValues, User $user, $status)
	{
		$report = new PowerPlantPVReport($this->db);
		$result = $report->fetch((int) $reportId);
		if ($result <= 0) {
			$this->setError('ErrorRecordNotFound');
			return -1;
		}

		$fieldObject = new PowerPlantPVReportField($this->db);
		$fields = $fieldObject->fetchAllByReport((int) $report->id, 'position', 'ASC');
		if (!is_array($fields)) {
			$this->copyErrorsFrom($fieldObject);
			return -1;
		}

		$this->db->begin();
		foreach ($fields as $field) {
			if (!empty($field->readonly)) {
				continue;
			}
			$stableKey = (string) $field->stable_key;
			$value = array_key_exists($stableKey, $values) ? $values[$stableKey] : null;
			if (array_key_exists($stableKey, $dateValues)) {
				$value = $dateValues[$stableKey];
			}
			if (!$this->assignSubmittedValueToField($field, $value)) {
				continue;
			}
			if ($field->update($user, 0) < 0) {
				$this->copyErrorsFrom($field);
				$this->db->rollback();
				return -1;
			}
		}

		$report->status = $status;
		if ($report->update($user, 0) < 0) {
			$this->copyErrorsFrom($report);
			$this->db->rollback();
			return -1;
		}
		if ((string) $status === PowerPlantPVReport::STATUS_SAVED) {
			$result = $this->syncIndexReadingsFromReport($report, $user);
		} else {
			$result = $this->deactivateIndexReadingsForReport((int) $report->id, $user);
		}
		if ($result < 0) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();

		return 1;
	}

	/**
	 * Save submitted DC measure rows.
	 *
	 * @param	int						$reportId	Report id
	 * @param	array<string,mixed>		$values		Submitted rows
	 * @param	User					$user		User
	 * @return	int									>0 if OK, <0 on error
	 */
	public function saveDcMeasureValues($reportId, $values, User $user)
	{
		if (empty($values) || !is_array($values)) {
			return 1;
		}

		$report = new PowerPlantPVReport($this->db);
		$result = $report->fetch((int) $reportId);
		if ($result <= 0) {
			$this->setError('ErrorRecordNotFound');
			return -1;
		}

		$measureObject = new PowerPlantPVReportDcMeasure($this->db);
		$measures = $measureObject->fetchAllByReport((int) $report->id, 'position', 'ASC');
		if (!is_array($measures)) {
			$this->copyErrorsFrom($measureObject);
			return -1;
		}
		$byId = array();
		$byStableKey = array();
		foreach ($measures as $measure) {
			$byId[(int) $measure->id] = $measure;
			$byStableKey[(string) $measure->stable_key] = $measure;
		}

		$this->db->begin();
		foreach ($values as $row) {
			if (!is_array($row)) {
				continue;
			}
			$id = !empty($row['id']) ? (int) $row['id'] : 0;
			$stableKey = !empty($row['stable_key']) ? (string) $row['stable_key'] : '';
			$measure = null;
			if ($id > 0 && isset($byId[$id])) {
				$measure = $byId[$id];
			} elseif ($stableKey !== '' && isset($byStableKey[$stableKey])) {
				$measure = $byStableKey[$stableKey];
			}
			if (!$measure instanceof PowerPlantPVReportDcMeasure) {
				continue;
			}
			$this->assignSubmittedValueToDcMeasure($measure, $row);
			if ($measure->update($user, 0) < 0) {
				$this->copyErrorsFrom($measure);
				$this->db->rollback();
				return -1;
			}
		}
		$this->db->commit();

		return 1;
	}

	/**
	 * Add a manual DC measure row to an existing report section.
	 *
	 * @param	int		$reportId	Report id
	 * @param	int		$sectionId	Section id
	 * @param	User	$user		User
	 * @return	int					Created row id, <0 on error
	 */
	public function addManualDcMeasureLine($reportId, $sectionId, User $user)
	{
		$report = new PowerPlantPVReport($this->db);
		if ($report->fetch((int) $reportId) <= 0) {
			$this->setError('ErrorRecordNotFound');
			return -1;
		}

		$section = new PowerPlantPVReportSection($this->db);
		if ($section->fetch((int) $sectionId) <= 0 || (int) $section->fk_report !== (int) $report->id || (string) $section->section_code !== 'DC_ELECTRICAL_MEASURE') {
			$this->setError('ErrorRecordNotFound');
			return -1;
		}

		$fkPowerplant = $this->fetchSourcePowerplantId((int) $section->fk_report_powerplant);
		$position = $this->fetchNextDcMeasurePosition((int) $section->id);
		$row = new PowerPlantPVReportDcMeasure($this->db);
		$row->entity = (int) $report->entity;
		$row->fk_report = (int) $report->id;
		$row->fk_report_section = (int) $section->id;
		$row->fk_report_powerplant = (int) $section->fk_report_powerplant;
		$row->fk_report_equipment = 0;
		$row->fk_powerplant = $fkPowerplant;
		$row->fk_inverter = 0;
		$row->inverter_ref = '';
		$row->inverter_label = '';
		$row->inverter_serial = '';
		$row->mppt_number = null;
		$row->pv_input_number = null;
		$row->string_ref = '';
		$row->is_connected = 1;
		$row->polarity_checked = 0;
		$row->insulation_status = '';
		$row->stable_key = (string) $section->occurrence_key.':dc:manual:pending:'.dol_now().':'.random_int(1000, 999999);
		$row->position = $position;

		$this->db->begin();
		$rowId = $row->create($user, 0);
		if ($rowId <= 0) {
			$this->copyErrorsFrom($row);
			$this->db->rollback();
			return -1;
		}
		$row->id = $rowId;
		$row->rowid = $rowId;
		$row->stable_key = (string) $section->occurrence_key.':dc:manual:'.$rowId;
		if ($row->update($user, 0) < 0) {
			$this->copyErrorsFrom($row);
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();

		return $rowId;
	}

	/**
	 * Return active maintenance service options for manual mode.
	 *
	 * @return	array<int,string>	Options
	 */
	public function fetchManualMaintenanceServiceOptions()
	{
		$sql = "SELECT rowid, code, label, label_en";
		$sql .= " FROM ".$this->db->prefix()."c_powerplantpv_maintenance_service";
		$sql .= " WHERE active = 1";
		$sql .= " AND entity IN (".$this->db->sanitize(getEntity('c_powerplantpv_maintenance_service')).")";
		$sql .= " ORDER BY position ASC, label ASC, rowid ASC";

		$options = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' maintenance service lookup failed: '.$this->db->lasterror(), LOG_WARNING);
			return $options;
		}

		while (is_object($obj = $this->db->fetch_object($resql))) {
			$label = $this->localizedLabel($obj, 'label');
			$options[(int) $obj->rowid] = trim((string) $obj->code.' - '.$label, " -\t\n\r\0\x0B");
		}
		$this->db->free($resql);

		return $options;
	}

	/**
	 * Build generation context.
	 *
	 * @param	CommonObject	$intervention	Intervention object
	 * @param	int[]			$manualServiceIds	Manual maintenance service ids
	 * @return	array<string,mixed>			Context
	 */
	private function buildGenerationContext($intervention, $manualServiceIds)
	{
		$messages = array();
		$interventionId = powerplantpvGetCommonObjectId($intervention);
		if ($interventionId <= 0) {
			return array('can_generate' => 0, 'messages' => array('ErrorRecordNotFound'));
		}

		$nature = $this->fetchInterventionNature($intervention);
		if (empty($nature)) {
			$messages[] = 'PowerPlantPVReportNoInterventionNature';
			return array('can_generate' => 0, 'messages' => $messages);
		}

		$template = $this->fetchTemplateForNature($nature);
		if (empty($template)) {
			$messages[] = 'PowerPlantPVReportNoTemplateForNature';
			return array('can_generate' => 0, 'messages' => $messages, 'nature' => $nature);
		}

		$powerplants = $this->fetchLinkedPowerPlantContext($intervention);
		$services = $this->fetchSourceServices($intervention, $powerplants, $manualServiceIds);
		$sourceMode = !empty($services['contract_services_found']) ? 'contract' : 'manual';
		$serviceRows = isset($services['rows']) && is_array($services['rows']) ? $services['rows'] : array();
		$serviceIds = array();
		$serviceIdsByPowerplant = array();
		foreach ($serviceRows as $serviceRow) {
			if ((int) $serviceRow['fk_maintenance_service'] > 0) {
				$serviceIds[(int) $serviceRow['fk_maintenance_service']] = (int) $serviceRow['fk_maintenance_service'];
				$powerplantKey = (int) $serviceRow['fk_powerplant'];
				if (!isset($serviceIdsByPowerplant[$powerplantKey])) {
					$serviceIdsByPowerplant[$powerplantKey] = array();
				}
				$serviceIdsByPowerplant[$powerplantKey][(int) $serviceRow['fk_maintenance_service']] = (int) $serviceRow['fk_maintenance_service'];
			}
		}
		if (empty($services['contract_services_found']) && empty($manualServiceIds)) {
			$messages[] = 'PowerPlantPVReportNoContractPrestations';
		}

		$equipment = $this->fetchEquipmentContext($powerplants);
		$dcMeasureInputs = $this->fetchDcMeasureInputContext($powerplants, $equipment);
		$templateSections = $this->fetchTemplateSections((int) $template['id']);
		$templateFields = $this->fetchTemplateFields((int) $template['id']);
		$fieldOptions = $this->fetchFieldOptions($templateFields);
		$mappedSectionIds = $this->fetchMappedSectionIds((int) $template['id'], $serviceRows);
		$mappedSectionIdsByPowerplant = $this->fetchMappedSectionIdsByPowerplant((int) $template['id'], $serviceRows);

		return array(
			'can_generate' => 1,
			'messages' => $messages,
			'intervention_id' => $interventionId,
			'entity' => !empty($intervention->entity) ? (int) $intervention->entity : (int) $GLOBALS['conf']->entity,
			'fk_soc' => !empty($intervention->socid) ? (int) $intervention->socid : (!empty($intervention->fk_soc) ? (int) $intervention->fk_soc : 0),
			'fk_project' => !empty($intervention->fk_project) ? (int) $intervention->fk_project : (!empty($intervention->fk_projet) ? (int) $intervention->fk_projet : 0),
			'nature' => $nature,
			'template' => $template,
			'powerplants' => $powerplants,
			'source_services' => $serviceRows,
			'service_ids' => $serviceIds,
			'service_ids_by_powerplant' => $serviceIdsByPowerplant,
			'source_mode' => $sourceMode,
			'equipment' => $equipment,
			'dc_measure_inputs' => $dcMeasureInputs,
			'template_sections' => $templateSections,
			'template_fields' => $templateFields,
			'field_options' => $fieldOptions,
			'mapped_section_ids' => $mappedSectionIds,
			'mapped_section_ids_by_powerplant' => $mappedSectionIdsByPowerplant,
		);
	}

	/**
	 * Build a report object from generation context.
	 *
	 * @param	array<string,mixed>	$context	Context
	 * @param	string				$status		Status
	 * @return	PowerPlantPVReport			Report object
	 */
	private function newReportFromContext($context, $status)
	{
		$report = new PowerPlantPVReport($this->db);
		$report->entity = (int) $context['entity'];
		$report->fk_fichinter = (int) $context['intervention_id'];
		$report->fk_soc = (int) $context['fk_soc'];
		$report->fk_project = (int) $context['fk_project'];
		$report->fk_intervention_nature = (int) $context['nature']['id'];
		$report->intervention_nature_code = (string) $context['nature']['code'];
		$report->intervention_nature_label = (string) $context['nature']['label'];
		$report->intervention_nature_label_en = (string) $context['nature']['label_en'];
		$report->fk_report_template = (int) $context['template']['id'];
		$report->report_template_code = (string) $context['template']['code'];
		$report->report_template_label = (string) $context['template']['label'];
		$report->report_template_label_en = (string) $context['template']['label_en'];
		$report->source_mode = (string) $context['source_mode'];
		$report->status = $status;

		return $report;
	}

	/**
	 * Persist child snapshot rows.
	 *
	 * @param	PowerPlantPVReport		$report		Report
	 * @param	array<string,mixed>		$context	Context
	 * @param	User					$user		User
	 * @param	array<string,array<string,mixed>>	$oldValues	Old field values by stable key
	 * @param	array<string,array<string,mixed>>	$oldDcValues	Old DC measure values by stable key
	 * @return	array<string,int>|int	New field ids by stable key or <0 on error
	 */
	private function persistSnapshotChildren(PowerPlantPVReport $report, $context, User $user, $oldValues, $oldDcValues)
	{
		$powerplantMap = $this->persistPowerPlantSnapshot($report, $context, $user);
		if (!is_array($powerplantMap)) {
			return -1;
		}
		$equipmentMap = $this->persistEquipmentSnapshot($report, $context, $user, $powerplantMap);
		if (!is_array($equipmentMap)) {
			return -1;
		}
		if ($this->persistSourceServiceSnapshot($report, $context, $user, $powerplantMap) < 0) {
			return -1;
		}

		$result = $this->persistSectionFieldSnapshot($report, $context, $user, $powerplantMap, $equipmentMap, $oldValues, $oldDcValues);
		if (!is_array($result)) {
			return -1;
		}

		return $result;
	}

	/**
	 * Persist linked power plants.
	 *
	 * @param	PowerPlantPVReport	$report		Report
	 * @param	array<string,mixed>	$context	Context
	 * @param	User				$user		User
	 * @return	array<int,int>|int				Report power plant id by original power plant id or <0 on error
	 */
	private function persistPowerPlantSnapshot(PowerPlantPVReport $report, $context, User $user)
	{
		$map = array();
		$position = 0;
		foreach ($context['powerplants'] as $powerplantId => $powerplant) {
			$row = new PowerPlantPVReportPowerPlant($this->db);
			$row->entity = (int) $report->entity;
			$row->fk_report = (int) $report->id;
			$row->fk_powerplant = (int) $powerplantId;
			$row->powerplant_ref = (string) $powerplant['ref'];
			$row->powerplant_label = (string) $powerplant['label'];
			$row->fk_soc = (int) $powerplant['fk_soc'];
			$row->fk_project = (int) $powerplant['fk_project'];
			$row->position = $position;
			$rowId = $row->create($user, 0);
			if ($rowId <= 0) {
				$this->copyErrorsFrom($row);
				return -1;
			}
			$map[(int) $powerplantId] = $rowId;
			$position += 10;
		}

		return $map;
	}

	/**
	 * Persist equipment snapshot.
	 *
	 * @param	PowerPlantPVReport	$report			Report
	 * @param	array<string,mixed>	$context		Context
	 * @param	User				$user			User
	 * @param	array<int,int>		$powerplantMap	Report power plant ids
	 * @return	array<string,int>|int	Report equipment id by technical key or <0 on error
	 */
	private function persistEquipmentSnapshot(PowerPlantPVReport $report, $context, User $user, $powerplantMap)
	{
		$map = array();
		$position = 0;
		foreach ($context['equipment'] as $equipment) {
			$row = new PowerPlantPVReportEquipment($this->db);
			$row->entity = (int) $report->entity;
			$row->fk_report = (int) $report->id;
			$row->fk_powerplant = (int) $equipment['fk_powerplant'];
			$row->fk_report_powerplant = isset($powerplantMap[(int) $equipment['fk_powerplant']]) ? (int) $powerplantMap[(int) $equipment['fk_powerplant']] : 0;
			$row->fk_powerplant_line = (int) $equipment['fk_powerplant_line'];
			$row->fk_source_equipment = (int) $equipment['fk_source_equipment'];
			$row->fk_product = (int) $equipment['fk_product'];
			$row->product_ref = (string) $equipment['product_ref'];
			$row->product_label = (string) $equipment['product_label'];
			$row->equipment_brand = (string) $equipment['equipment_brand'];
			$row->equipment_model = (string) $equipment['equipment_model'];
			$row->equipment_type = (string) $equipment['equipment_type'];
			$row->equipment_ref = (string) $equipment['equipment_ref'];
			$row->equipment_label = (string) $equipment['equipment_label'];
			$row->serial_number = (string) $equipment['serial_number'];
			$row->qty = (float) $equipment['qty'];
			$row->technical_key = (string) $equipment['technical_key'];
			$row->equipment_position = (string) $equipment['equipment_position'];
			$row->technical_snapshot = (string) $equipment['technical_snapshot'];
			$row->position = $position;
			$rowId = $row->create($user, 0);
			if ($rowId <= 0) {
				$this->copyErrorsFrom($row);
				return -1;
			}
			$map[(string) $equipment['technical_key']] = $rowId;
			$position += 10;
		}

		return $map;
	}

	/**
	 * Persist source services.
	 *
	 * @param	PowerPlantPVReport	$report			Report
	 * @param	array<string,mixed>	$context		Context
	 * @param	User				$user			User
	 * @param	array<int,int>		$powerplantMap	Report power plant ids
	 * @return	int								>0 if OK, <0 on error
	 */
	private function persistSourceServiceSnapshot(PowerPlantPVReport $report, $context, User $user, $powerplantMap)
	{
		$position = 0;
		foreach ($context['source_services'] as $service) {
			$row = new PowerPlantPVReportSourceService($this->db);
			$row->entity = (int) $report->entity;
			$row->fk_report = (int) $report->id;
			$row->fk_powerplant = (int) $service['fk_powerplant'];
			$row->fk_report_powerplant = isset($powerplantMap[(int) $service['fk_powerplant']]) ? (int) $powerplantMap[(int) $service['fk_powerplant']] : 0;
			$row->fk_contract = (int) $service['fk_contract'];
			$row->contract_ref = (string) $service['contract_ref'];
			$row->fk_contract_line = (int) $service['fk_contract_line'];
			$row->fk_product = (int) $service['fk_product'];
			$row->product_ref = (string) $service['product_ref'];
			$row->product_label = (string) $service['product_label'];
			$row->fk_maintenance_service = (int) $service['fk_maintenance_service'];
			$row->maintenance_service_code = (string) $service['maintenance_service_code'];
			$row->maintenance_service_label = (string) $service['maintenance_service_label'];
			$row->maintenance_service_label_en = (string) $service['maintenance_service_label_en'];
			$row->source_mode = (string) $service['source_mode'];
			$row->position = $position;
			if ($row->create($user, 0) <= 0) {
				$this->copyErrorsFrom($row);
				return -1;
			}
			$position += 10;
		}

		return 1;
	}

	/**
	 * Persist generated sections and fields.
	 *
	 * @param	PowerPlantPVReport	$report			Report
	 * @param	array<string,mixed>	$context		Context
	 * @param	User				$user			User
	 * @param	array<int,int>		$powerplantMap	Report power plant ids
	 * @param	array<string,int>	$equipmentMap	Report equipment ids
	 * @param	array<string,array<string,mixed>>	$oldValues	Old field values
	 * @param	array<string,array<string,mixed>>	$oldDcValues	Old DC measure values
	 * @return	array<string,int>|int	New field ids by stable key or <0 on error
	 */
	private function persistSectionFieldSnapshot(PowerPlantPVReport $report, $context, User $user, $powerplantMap, $equipmentMap, $oldValues, $oldDcValues)
	{
		$fieldIds = array();
		$plans = $this->buildSectionPlans($context);
		foreach ($plans as $plan) {
			$templateSection = $plan['section'];
			$section = new PowerPlantPVReportSection($this->db);
			$section->entity = (int) $report->entity;
			$section->fk_report = (int) $report->id;
			$section->fk_report_powerplant = isset($powerplantMap[(int) $plan['fk_powerplant']]) ? (int) $powerplantMap[(int) $plan['fk_powerplant']] : 0;
			$section->fk_report_equipment = isset($equipmentMap[(string) $plan['equipment_key']]) ? (int) $equipmentMap[(string) $plan['equipment_key']] : 0;
			$section->fk_report_template_section = (int) $templateSection->id;
			$section->section_code = (string) $templateSection->code;
			$section->section_label = (string) $templateSection->label;
			$section->section_label_en = (string) $templateSection->label_en;
			$section->section_description = (string) $templateSection->description;
			$section->section_description_en = (string) $templateSection->description_en;
			$section->scope_type = (string) $templateSection->scope_type;
			$section->equipment_type = (string) $templateSection->equipment_type;
			$section->repeat_mode = (string) $templateSection->repeat_mode;
			$section->occurrence_key = (string) $plan['occurrence_key'];
			$section->is_required = (int) $templateSection->is_required;
			$section->visible_form = (int) $templateSection->visible_form;
			$section->visible_pdf = (int) $templateSection->visible_pdf;
			$section->position = (int) $plan['position'];
			$sectionId = $section->create($user, 0);
			if ($sectionId <= 0) {
				$this->copyErrorsFrom($section);
				return -1;
			}

			foreach ($plan['fields'] as $templateField) {
				$field = $this->newFieldFromTemplate($report, $section, $templateField, $plan, $context);
				$field->fk_report_section = $sectionId;
				if (isset($oldValues[(string) $field->stable_key])) {
					$this->copyStoredValueToField($field, $oldValues[(string) $field->stable_key]);
				}
				$this->applyArchivedPreviousReadingToField($field, $section, $plan, (int) $report->id);
				$fieldId = $field->create($user, 0);
				if ($fieldId <= 0) {
					$this->copyErrorsFrom($field);
					return -1;
				}
				$fieldIds[(string) $field->stable_key] = $fieldId;
			}
			if ((string) $section->section_code === 'DC_ELECTRICAL_MEASURE') {
				$result = $this->persistDcMeasureRowsForSection($report, $section, $sectionId, $plan, $context, $equipmentMap, $oldDcValues, $user);
				if ($result < 0) {
					return -1;
				}
			}
		}

		return $fieldIds;
	}

	/**
	 * Build section and field objects without persistence.
	 *
	 * @param	array<string,mixed>	$context	Context
	 * @param	int					$reportId	Report id
	 * @param	array<int,int>		$powerplantMap	Report power plant ids
	 * @param	array<string,int>	$equipmentMap	Report equipment ids
	 * @return	array<string,mixed>			Tree
	 */
	private function buildSectionFieldObjects($context, $reportId, $powerplantMap, $equipmentMap)
	{
		$report = isset($context['report']) && $context['report'] instanceof PowerPlantPVReport ? $context['report'] : $this->newReportFromContext($context, PowerPlantPVReport::STATUS_DRAFT);
		$report->id = $reportId;

		$sections = array();
		foreach ($this->buildSectionPlans($context) as $plan) {
			$templateSection = $plan['section'];
			$section = new PowerPlantPVReportSection($this->db);
			$section->id = 0;
			$section->rowid = 0;
			$section->entity = (int) $context['entity'];
			$section->fk_report = $reportId;
			$section->fk_report_powerplant = isset($powerplantMap[(int) $plan['fk_powerplant']]) ? (int) $powerplantMap[(int) $plan['fk_powerplant']] : 0;
			$section->fk_report_equipment = isset($equipmentMap[(string) $plan['equipment_key']]) ? (int) $equipmentMap[(string) $plan['equipment_key']] : 0;
			$section->fk_report_template_section = (int) $templateSection->id;
			$section->section_code = (string) $templateSection->code;
			$section->section_label = (string) $templateSection->label;
			$section->section_label_en = (string) $templateSection->label_en;
			$section->section_description = (string) $templateSection->description;
			$section->section_description_en = (string) $templateSection->description_en;
			$section->scope_type = (string) $templateSection->scope_type;
			$section->equipment_type = (string) $templateSection->equipment_type;
			$section->repeat_mode = (string) $templateSection->repeat_mode;
			$section->occurrence_key = (string) $plan['occurrence_key'];
			$section->is_required = (int) $templateSection->is_required;
			$section->visible_form = (int) $templateSection->visible_form;
			$section->visible_pdf = (int) $templateSection->visible_pdf;
			$section->position = (int) $plan['position'];

			$fields = array();
			foreach ($plan['fields'] as $templateField) {
				$field = $this->newFieldFromTemplate($report, $section, $templateField, $plan, $context);
				$field->id = 0;
				$field->rowid = 0;
				$field->files = array();
				$fields[] = $field;
			}
			$dcMeasures = array();
			if ((string) $section->section_code === 'DC_ELECTRICAL_MEASURE') {
				$dcMeasures = $this->buildDcMeasureRowsForPlan($report, $section, $plan, $context, $equipmentMap, array());
			}
			$sections[] = array(
				'section' => $section,
				'fields' => $fields,
				'dc_measures' => $dcMeasures,
				'powerplant' => !empty($context['powerplants'][(int) $plan['fk_powerplant']]) ? $context['powerplants'][(int) $plan['fk_powerplant']] : null,
				'equipment' => $this->findEquipmentContextByKey($context, (string) $plan['equipment_key']),
			);
		}

		return array('sections' => $sections);
	}

	/**
	 * Build DC measure rows for one section plan.
	 *
	 * @param	PowerPlantPVReport			$report			Report
	 * @param	PowerPlantPVReportSection	$section		Section
	 * @param	array<string,mixed>			$plan			Section plan
	 * @param	array<string,mixed>			$context		Context
	 * @param	array<string,int>			$equipmentMap	Report equipment ids
	 * @param	array<string,array<string,mixed>>	$oldDcValues	Old DC values
	 * @return	array<int,PowerPlantPVReportDcMeasure>	Rows
	 */
	private function buildDcMeasureRowsForPlan(PowerPlantPVReport $report, PowerPlantPVReportSection $section, $plan, $context, $equipmentMap, $oldDcValues)
	{
		$rows = array();
		if (empty($context['dc_measure_inputs']) || !is_array($context['dc_measure_inputs'])) {
			return $rows;
		}

		$position = 0;
		foreach ($context['dc_measure_inputs'] as $input) {
			if ((int) $input['fk_powerplant'] !== (int) $plan['fk_powerplant']) {
				continue;
			}
			$stableKey = (string) $plan['occurrence_key'].':dc:inverter:'.((int) $input['fk_inverter']).':mppt:'.((int) $input['mppt_number']).':pvinput:'.((int) $input['pv_input_number']);
			$row = $this->newDcMeasureFromInput($report, $section, $input, $stableKey, $position, $equipmentMap);
			if (!empty($oldDcValues[$stableKey])) {
				$this->copyStoredValueToDcMeasure($row, $oldDcValues[$stableKey]);
			}
			$rows[] = $row;
			$position += 10;
		}

		return $rows;
	}

	/**
	 * Persist DC measure rows for one generated section.
	 *
	 * @param	PowerPlantPVReport			$report			Report
	 * @param	PowerPlantPVReportSection	$section		Section
	 * @param	int							$sectionId		Section id
	 * @param	array<string,mixed>			$plan			Section plan
	 * @param	array<string,mixed>			$context		Context
	 * @param	array<string,int>			$equipmentMap	Report equipment ids
	 * @param	array<string,array<string,mixed>>	$oldDcValues	Old DC values
	 * @param	User						$user			User
	 * @return	int										>0 if OK, <0 on error
	 */
	private function persistDcMeasureRowsForSection(PowerPlantPVReport $report, PowerPlantPVReportSection $section, $sectionId, $plan, $context, $equipmentMap, $oldDcValues, User $user)
	{
		$section->id = (int) $sectionId;
		$section->rowid = (int) $sectionId;
		$rows = $this->buildDcMeasureRowsForPlan($report, $section, $plan, $context, $equipmentMap, $oldDcValues);
		$position = count($rows) * 10;
		$manualPrefix = (string) $plan['occurrence_key'].':dc:manual:';
		foreach ($oldDcValues as $stableKey => $oldValue) {
			if (strpos((string) $stableKey, $manualPrefix) !== 0) {
				continue;
			}
			$row = $this->newManualDcMeasureFromStoredValue($report, $section, $oldValue, (string) $stableKey, $position);
			$rows[] = $row;
			$position += 10;
		}

		foreach ($rows as $row) {
			$row->fk_report_section = (int) $sectionId;
			if ($row->create($user, 0) <= 0) {
				$this->copyErrorsFrom($row);
				return -1;
			}
		}

		return 1;
	}

	/**
	 * Create one generated DC measure object from installed input context.
	 *
	 * @param	PowerPlantPVReport			$report			Report
	 * @param	PowerPlantPVReportSection	$section		Section
	 * @param	array<string,mixed>			$input			Input context
	 * @param	string						$stableKey		Stable key
	 * @param	int							$position		Position
	 * @param	array<string,int>			$equipmentMap	Equipment map
	 * @return	PowerPlantPVReportDcMeasure				Measure object
	 */
	private function newDcMeasureFromInput(PowerPlantPVReport $report, PowerPlantPVReportSection $section, $input, $stableKey, $position, $equipmentMap)
	{
		$row = new PowerPlantPVReportDcMeasure($this->db);
		$row->entity = (int) $report->entity;
		$row->fk_report = (int) $report->id;
		$row->fk_report_section = (int) $section->id;
		$row->fk_report_powerplant = (int) $section->fk_report_powerplant;
		$row->fk_report_equipment = !empty($equipmentMap[(string) $input['equipment_key']]) ? (int) $equipmentMap[(string) $input['equipment_key']] : 0;
		$row->fk_powerplant = (int) $input['fk_powerplant'];
		$row->fk_inverter = (int) $input['fk_inverter'];
		$row->inverter_ref = (string) $input['inverter_ref'];
		$row->inverter_label = (string) $input['inverter_label'];
		$row->inverter_serial = (string) $input['inverter_serial'];
		$row->mppt_number = (int) $input['mppt_number'];
		$row->pv_input_number = (int) $input['pv_input_number'];
		$row->string_ref = (string) $input['string_ref'];
		$row->is_connected = (int) $input['is_connected'];
		$row->open_circuit_voltage = null;
		$row->polarity_checked = 0;
		$row->insulation_status = '';
		$row->insulation_positive_to_ground = null;
		$row->insulation_negative_to_ground = null;
		$row->observation = '';
		$row->stable_key = $stableKey;
		$row->position = $position;

		return $row;
	}

	/**
	 * Create one manual DC measure object from a previous snapshot value.
	 *
	 * @param	PowerPlantPVReport			$report		Report
	 * @param	PowerPlantPVReportSection	$section	Section
	 * @param	array<string,mixed>			$value		Stored value
	 * @param	string						$stableKey	Stable key
	 * @param	int							$position	Position
	 * @return	PowerPlantPVReportDcMeasure			Measure object
	 */
	private function newManualDcMeasureFromStoredValue(PowerPlantPVReport $report, PowerPlantPVReportSection $section, $value, $stableKey, $position)
	{
		$row = new PowerPlantPVReportDcMeasure($this->db);
		$row->entity = (int) $report->entity;
		$row->fk_report = (int) $report->id;
		$row->fk_report_section = (int) $section->id;
		$row->fk_report_powerplant = (int) $section->fk_report_powerplant;
		$row->fk_report_equipment = 0;
		$row->fk_powerplant = isset($value['fk_powerplant']) ? (int) $value['fk_powerplant'] : $this->fetchSourcePowerplantId((int) $section->fk_report_powerplant);
		$row->fk_inverter = isset($value['fk_inverter']) ? (int) $value['fk_inverter'] : 0;
		$row->stable_key = $stableKey;
		$row->position = $position;
		$this->copyStoredValueToDcMeasure($row, $value);

		return $row;
	}

	/**
	 * Create a field snapshot object from a template field.
	 *
	 * @param	PowerPlantPVReport			$report			Report
	 * @param	PowerPlantPVReportSection	$section		Section
	 * @param	PowerPlantPVReportTemplateField	$templateField	Template field
	 * @param	array<string,mixed>			$plan			Section plan
	 * @param	array<string,mixed>			$context		Context
	 * @return	PowerPlantPVReportField					Field object
	 */
	private function newFieldFromTemplate(PowerPlantPVReport $report, PowerPlantPVReportSection $section, PowerPlantPVReportTemplateField $templateField, $plan, $context)
	{
		$field = new PowerPlantPVReportField($this->db);
		$field->entity = (int) $report->entity;
		$field->fk_report = (int) $report->id;
		$field->fk_report_section = (int) $section->id;
		$field->fk_report_powerplant = (int) $section->fk_report_powerplant;
		$field->fk_report_equipment = (int) $section->fk_report_equipment;
		$field->fk_report_template_field = (int) $templateField->id;
		$field->stable_key = (string) $plan['occurrence_key'].':field:'.(string) $templateField->code;
		$field->field_code = (string) $templateField->code;
		$field->field_label = (string) $templateField->label;
		$field->field_label_en = (string) $templateField->label_en;
		$field->field_description = (string) $templateField->description;
		$field->field_description_en = (string) $templateField->description_en;
		$field->field_type = (string) $templateField->field_type;
		$field->scope_type = (string) $templateField->scope_type;
		$field->unit = (string) $templateField->unit;
		$field->default_value = (string) $templateField->default_value;
		$field->placeholder = (string) $templateField->placeholder;
		$field->help = (string) $templateField->help;
		$field->options_snapshot = $this->encodeFieldOptions((int) $templateField->id, $context['field_options']);
		$field->value_text = (string) $templateField->default_value;
		$field->value_number = null;
		$field->value_date = null;
		$field->is_required = (int) $templateField->is_required;
		$field->visible_form = (int) $templateField->visible_form;
		$field->visible_pdf = (int) $templateField->visible_pdf;
		$field->readonly = (int) $templateField->readonly;
		$field->position = (int) $templateField->position;
		$this->applyArchivedPreviousReadingToField($field, $section, $plan, (int) $report->id);

		return $field;
	}

	/**
	 * Prefill an N-1 production reading field from the archived readings.
	 *
	 * @param	PowerPlantPVReportField		$field		Field object
	 * @param	PowerPlantPVReportSection	$section	Section object
	 * @param	array<string,mixed>			$plan		Section plan
	 * @param	int							$excludeReportId	Report id to exclude
	 * @return	void
	 */
	private function applyArchivedPreviousReadingToField(PowerPlantPVReportField $field, PowerPlantPVReportSection $section, $plan, $excludeReportId = 0)
	{
		if ((string) $section->section_code !== 'PRODUCTION_READING') {
			return;
		}
		$typeCode = $this->productionReadingTypeCodeFromField((string) $field->field_code, 1);
		if ($typeCode === '') {
			return;
		}
		$fkPowerplant = isset($plan['fk_powerplant']) ? (int) $plan['fk_powerplant'] : 0;
		if ($fkPowerplant <= 0) {
			return;
		}
		if (!$this->tableExists($this->db->prefix().'powerplantpv_index_reading')) {
			$field->readonly = 1;
			return;
		}

		$archive = new PowerPlantPVIndexReading($this->db);
		$latest = $archive->fetchLatestValue($fkPowerplant, $typeCode, '', (int) $excludeReportId);
		$field->readonly = 1;
		$field->value_text = null;
		$field->value_date = null;
		$field->value_number = is_array($latest) ? (float) $latest['value'] : null;
	}

	/**
	 * Return production reading report field map.
	 *
	 * @return	array<string,string>	Field code to reading type code map
	 */
	private function productionReadingFieldMap()
	{
		return array(
			'INVERTER_PRODUCTION' => 'INVERTER_PRODUCTION',
			'PRODUCTION_INDEX' => 'PRODUCTION_INDEX',
			'INJECTION_INDEX' => 'INJECTION_INDEX',
			'CONSUMPTION_INDEX' => 'CONSUMPTION_INDEX',
			'ANNUAL_PRODUCTION' => 'ANNUAL_PRODUCTION',
			'SELF_CONSUMPTION' => 'SELF_CONSUMPTION',
		);
	}

	/**
	 * Resolve a report field code to a production reading type code.
	 *
	 * @param	string	$fieldCode	Field code
	 * @param	int		$previous	1 to require an N-1 field, 0 to require an N field
	 * @return	string				Reading type code, empty when not mapped
	 */
	private function productionReadingTypeCodeFromField($fieldCode, $previous)
	{
		$fieldCode = trim((string) $fieldCode);
		$isPrevious = (substr($fieldCode, -10) === '_N_MINUS_1');
		if ($previous && !$isPrevious) {
			return '';
		}
		if (!$previous && $isPrevious) {
			return '';
		}
		$baseCode = $isPrevious ? substr($fieldCode, 0, -10) : $fieldCode;
		$map = $this->productionReadingFieldMap();

		return isset($map[$baseCode]) ? $map[$baseCode] : '';
	}

	/**
	 * Build section occurrence plans.
	 *
	 * @param	array<string,mixed>	$context	Context
	 * @return	array<int,array<string,mixed>>	Plans
	 */
	private function buildSectionPlans($context)
	{
		$plans = array();
		$fieldsBySection = array();
		foreach ($context['template_fields'] as $field) {
			if (empty($field->active) || empty($field->visible_form)) {
				continue;
			}
			if ((int) $field->fk_maintenance_service > 0 && empty($context['service_ids'][(int) $field->fk_maintenance_service])) {
				continue;
			}
			$sectionId = (int) $field->fk_report_template_section;
			if (!isset($fieldsBySection[$sectionId])) {
				$fieldsBySection[$sectionId] = array();
			}
			$fieldsBySection[$sectionId][] = $field;
		}

		$position = 0;
		foreach ($context['template_sections'] as $section) {
			$sectionId = (int) $section->id;
			if (empty($section->active) || empty($section->visible_form)) {
				continue;
			}
			if (empty($section->is_required) && empty($context['mapped_section_ids'][$sectionId])) {
				continue;
			}
			$fields = isset($fieldsBySection[$sectionId]) ? $fieldsBySection[$sectionId] : array();
			if (empty($fields)) {
				continue;
			}
			$occurrences = $this->buildSectionOccurrences($section, $context);
			foreach ($occurrences as $occurrence) {
				if (empty($section->is_required) && !$this->isSectionMappedForPowerplant($sectionId, (int) $occurrence['fk_powerplant'], $context)) {
					continue;
				}
				$plans[] = array(
					'section' => $section,
					'fields' => $fields,
					'occurrence_key' => (string) $occurrence['occurrence_key'],
					'fk_powerplant' => (int) $occurrence['fk_powerplant'],
					'equipment_key' => (string) $occurrence['equipment_key'],
					'position' => $position,
				);
				$position += 10;
			}
		}

		return $plans;
	}

	/**
	 * Build occurrences for a template section.
	 *
	 * @param	PowerPlantPVReportTemplateSection	$section	Template section
	 * @param	array<string,mixed>					$context	Context
	 * @return	array<int,array<string,mixed>>	Occurrences
	 */
	private function buildSectionOccurrences($section, $context)
	{
		$code = (string) $section->code;
		$scope = (string) $section->scope_type;
		$repeat = (string) $section->repeat_mode;
		$equipmentType = (string) $section->equipment_type;

		if ($code === 'DC_ELECTRICAL_MEASURE') {
			$occurrences = array();
			foreach (array_keys($context['powerplants']) as $powerplantId) {
				$occurrences[] = array(
					'occurrence_key' => 'section:'.$code.':powerplant:'.((int) $powerplantId),
					'fk_powerplant' => (int) $powerplantId,
					'equipment_key' => '',
				);
			}
			return !empty($occurrences) ? $occurrences : array(array('occurrence_key' => 'section:'.$code.':manual:1', 'fk_powerplant' => 0, 'equipment_key' => ''));
		}

		if ($repeat === 'once_per_powerplant' || $scope === 'powerplant') {
			$occurrences = array();
			foreach (array_keys($context['powerplants']) as $powerplantId) {
				$occurrences[] = array(
					'occurrence_key' => 'section:'.$code.':powerplant:'.((int) $powerplantId),
					'fk_powerplant' => (int) $powerplantId,
					'equipment_key' => '',
				);
			}
			return !empty($occurrences) ? $occurrences : array(array('occurrence_key' => 'section:'.$code, 'fk_powerplant' => 0, 'equipment_key' => ''));
		}

		if ($repeat === 'once_per_equipment' || $scope === 'equipment' || $scope === 'inverter' || $scope === 'electrical_box') {
			$occurrences = array();
			foreach ($context['equipment'] as $equipment) {
				if (!$this->equipmentMatchesSection($equipment, $scope, $equipmentType)) {
					continue;
				}
				$occurrences[] = array(
					'occurrence_key' => 'section:'.$code.':equipment:'.(string) $equipment['technical_key'],
					'fk_powerplant' => (int) $equipment['fk_powerplant'],
					'equipment_key' => (string) $equipment['technical_key'],
				);
			}
			return !empty($occurrences) ? $occurrences : array(array('occurrence_key' => 'section:'.$code.':manual:1', 'fk_powerplant' => 0, 'equipment_key' => ''));
		}

		if ($repeat === 'once_per_mppt' || $repeat === 'once_per_pv_input' || $scope === 'mppt' || $scope === 'pv_input' || $scope === 'free_line') {
			if (!empty($context['dc_measure_inputs']) && is_array($context['dc_measure_inputs'])) {
				$seen = array();
				$occurrences = array();
				foreach ($context['dc_measure_inputs'] as $input) {
					$powerplantId = (int) $input['fk_powerplant'];
					$equipmentKey = (string) $input['equipment_key'];
					if ($repeat === 'once_per_mppt' || $scope === 'mppt') {
						$key = 'section:'.$code.':inverter:'.((int) $input['fk_inverter']).':mppt:'.((int) $input['mppt_number']);
					} else {
						$key = 'section:'.$code.':inverter:'.((int) $input['fk_inverter']).':mppt:'.((int) $input['mppt_number']).':pvinput:'.((int) $input['pv_input_number']);
					}
					if (!empty($seen[$key])) {
						continue;
					}
					$seen[$key] = 1;
					$occurrences[] = array(
						'occurrence_key' => $key,
						'fk_powerplant' => $powerplantId,
						'equipment_key' => $equipmentKey,
					);
				}
				if (!empty($occurrences)) {
					return $occurrences;
				}
			}
			return array(array('occurrence_key' => 'section:'.$code.':manual:1', 'fk_powerplant' => 0, 'equipment_key' => ''));
		}

		return array(array('occurrence_key' => 'section:'.$code, 'fk_powerplant' => 0, 'equipment_key' => ''));
	}

	/**
	 * Check if an equipment row matches a section scope.
	 *
	 * @param	array<string,mixed>	$equipment	Equipment row
	 * @param	string				$scope		Scope
	 * @param	string				$equipmentType	Equipment type
	 * @return	bool							True if matching
	 */
	private function equipmentMatchesSection($equipment, $scope, $equipmentType)
	{
		$type = (string) $equipment['equipment_type'];
		if ($equipmentType !== '' && $type !== $equipmentType) {
			return false;
		}
		if ($scope === 'inverter') {
			return $type === 'INVERTER';
		}
		if ($scope === 'electrical_box') {
			return in_array($type, array('DC_BOX', 'AC_BOX'), true);
		}

		return true;
	}

	/**
	 * Fetch intervention nature from object extrafield.
	 *
	 * @param	CommonObject	$intervention	Intervention object
	 * @return	array<string,mixed>			Nature data
	 */
	private function fetchInterventionNature($intervention)
	{
		if (empty($intervention->array_options) || !is_array($intervention->array_options)) {
			if (method_exists($intervention, 'fetch_optionals')) {
				$intervention->fetch_optionals();
			}
		}
		$natureId = 0;
		if (!empty($intervention->array_options['options_powerplantpv_intervention_nature'])) {
			$natureId = (int) $intervention->array_options['options_powerplantpv_intervention_nature'];
		}
		if ($natureId <= 0) {
			return array();
		}

		$nature = new PowerPlantPVInterventionNature($this->db);
		$result = $nature->fetch($natureId);
		if ($result <= 0 || empty($nature->active)) {
			return array();
		}

		return array(
			'id' => (int) $nature->id,
			'code' => (string) $nature->code,
			'label' => (string) $nature->label,
			'label_en' => (string) $nature->label_en,
			'fk_report_template' => (int) $nature->fk_report_template,
			'report_template_code' => (string) $nature->report_template_code,
		);
	}

	/**
	 * Fetch active report template associated to a nature.
	 *
	 * @param	array<string,mixed>	$nature	Nature data
	 * @return	array<string,mixed>			Template data
	 */
	private function fetchTemplateForNature($nature)
	{
		$template = new PowerPlantPVReportTemplate($this->db);
		$result = 0;
		if (!empty($nature['fk_report_template'])) {
			$result = $template->fetch((int) $nature['fk_report_template']);
		} elseif (!empty($nature['report_template_code'])) {
			$result = $template->fetchByCode((string) $nature['report_template_code']);
		}
		if ($result <= 0 || empty($template->active) || (string) $template->target_element !== PowerPlantPVReportTemplate::TARGET_INTERVENTION) {
			return array();
		}

		return array(
			'id' => (int) $template->id,
			'code' => (string) $template->code,
			'label' => (string) $template->label,
			'label_en' => (string) $template->label_en,
		);
	}

	/**
	 * Fetch linked power plants as immutable source rows.
	 *
	 * @param	CommonObject	$intervention	Intervention object
	 * @return	array<int,array<string,mixed>>	Power plants indexed by id
	 */
	private function fetchLinkedPowerPlantContext($intervention)
	{
		$rows = array();
		foreach (powerplantpvGetLinkedPowerPlants($intervention) as $powerplantId => $powerplant) {
			$id = powerplantpvGetCommonObjectId($powerplant);
			if ($id <= 0) {
				$id = (int) $powerplantId;
			}
			$rows[$id] = array(
				'id' => $id,
				'ref' => (string) $powerplant->ref,
				'label' => (string) $powerplant->label,
				'fk_soc' => !empty($powerplant->fk_soc) ? (int) $powerplant->fk_soc : 0,
				'fk_project' => !empty($powerplant->fk_project) ? (int) $powerplant->fk_project : 0,
			);
		}

		return $rows;
	}

	/**
	 * Fetch source services from active contracts or manual selected services.
	 *
	 * @param	CommonObject				$intervention	Intervention object
	 * @param	array<int,array<string,mixed>>	$powerplants	Power plants
	 * @param	int[]						$manualServiceIds	Manual service ids
	 * @return	array<string,mixed>						Result
	 */
	private function fetchSourceServices($intervention, $powerplants, $manualServiceIds)
	{
		$rows = array();
		$contractIdsByPowerplant = $this->fetchContractIdsForInterventionAndPowerplants($intervention, array_keys($powerplants));
		$contractServicesFound = 0;
		$contractContexts = array();
		foreach ($contractIdsByPowerplant as $powerplantId => $contractIds) {
			if ((int) $powerplantId > 0 && !empty($contractIds)) {
				$contractContexts[(int) $powerplantId] = $contractIds;
			}
		}
		if (empty($contractContexts) && !empty($contractIdsByPowerplant[0])) {
			$fallbackPowerplants = !empty($powerplants) ? array_keys($powerplants) : array(0);
			foreach ($fallbackPowerplants as $powerplantId) {
				$contractContexts[(int) $powerplantId] = $contractIdsByPowerplant[0];
			}
		}

		foreach ($contractContexts as $powerplantId => $contractIds) {
			foreach ($contractIds as $contractId) {
				foreach ($this->fetchActiveServicesWithMaintenancePrestations((int) $contractId) as $line) {
					if (empty($line['maintenance_service_ids'])) {
						continue;
					}
					$contractServicesFound = 1;
					foreach ($line['maintenance_service_ids'] as $maintenanceServiceId) {
						if (empty($line['maintenance_services'][$maintenanceServiceId])) {
							continue;
						}
						$service = $line['maintenance_services'][$maintenanceServiceId];
						$rows[] = array(
							'fk_powerplant' => (int) $powerplantId,
							'fk_contract' => (int) $contractId,
							'contract_ref' => (string) $line['contract_ref'],
							'fk_contract_line' => (int) $line['id'],
							'fk_product' => (int) $line['fk_product'],
							'product_ref' => (string) $line['product_ref'],
							'product_label' => (string) $line['product_label'],
							'fk_maintenance_service' => (int) $service['id'],
							'maintenance_service_code' => (string) $service['code'],
							'maintenance_service_label' => (string) $service['label'],
							'maintenance_service_label_en' => (string) $service['label_en'],
							'source_mode' => 'contract',
						);
					}
				}
			}
		}

		if (!$contractServicesFound && !empty($manualServiceIds)) {
			$manualPowerplants = !empty($powerplants) ? array_keys($powerplants) : array(0);
			foreach ($this->fetchMaintenanceServicesByIds($manualServiceIds) as $service) {
				foreach ($manualPowerplants as $powerplantId) {
					$rows[] = array(
						'fk_powerplant' => (int) $powerplantId,
						'fk_contract' => 0,
						'contract_ref' => '',
						'fk_contract_line' => 0,
						'fk_product' => 0,
						'product_ref' => '',
						'product_label' => '',
						'fk_maintenance_service' => (int) $service['id'],
						'maintenance_service_code' => (string) $service['code'],
						'maintenance_service_label' => (string) $service['label'],
						'maintenance_service_label_en' => (string) $service['label_en'],
						'source_mode' => 'manual',
					);
				}
			}
		}

		return array('rows' => $rows, 'contract_services_found' => $contractServicesFound);
	}

	/**
	 * Fetch contract ids linked to intervention and linked power plants.
	 *
	 * @param	CommonObject	$intervention	Intervention
	 * @param	int[]			$powerplantIds	Power plant ids
	 * @return	array<int,array<int,int>>	Contract ids by power plant id, 0 for intervention-level links
	 */
	private function fetchContractIdsForInterventionAndPowerplants($intervention, $powerplantIds)
	{
		$result = array(0 => array());
		$interventionId = powerplantpvGetCommonObjectId($intervention);
		if (!empty($intervention->fk_contrat)) {
			$result[0][(int) $intervention->fk_contrat] = (int) $intervention->fk_contrat;
		}
		if (!empty($intervention->fk_contract)) {
			$result[0][(int) $intervention->fk_contract] = (int) $intervention->fk_contract;
		}
		foreach ($this->fetchInterventionContractLinks(array($interventionId)) as $interventionLinks) {
			foreach ($interventionLinks as $contractId) {
				$result[0][(int) $contractId] = (int) $contractId;
			}
		}
		foreach ($this->fetchPowerPlantContractLinks($powerplantIds) as $powerplantId => $contractIds) {
			if (!isset($result[$powerplantId])) {
				$result[$powerplantId] = array();
			}
			foreach ($contractIds as $contractId) {
				$result[$powerplantId][(int) $contractId] = (int) $contractId;
			}
		}

		return $result;
	}

	/**
	 * Fetch active service lines and maintenance prestations.
	 *
	 * @param	int	$contractId	Contract id
	 * @return	array<int,array<string,mixed>>	Service rows
	 */
	private function fetchActiveServicesWithMaintenancePrestations($contractId)
	{
		$productExtraTable = $this->db->prefix().'product_extrafields';
		$hasProductExtra = $this->tableExists($productExtraTable)
			&& $this->columnExists($productExtraTable, 'powerplantpv_maintenance_services');
		$serviceStatusOpen = $this->getContractLineOpenStatus();

		$sql = "SELECT c.ref as contract_ref, d.rowid, d.fk_product, d.description, d.qty, d.statut, d.rang";
		$sql .= ", p.ref as product_ref, p.label as product_label";
		if ($hasProductExtra) {
			$sql .= ", pe.powerplantpv_maintenance_services as maintenance_services";
		} else {
			$sql .= ", '' as maintenance_services";
		}
		$sql .= " FROM ".$this->db->prefix()."contratdet AS d";
		$sql .= " INNER JOIN ".$this->db->prefix()."contrat AS c ON c.rowid = d.fk_contrat";
		$sql .= " LEFT JOIN ".$this->db->prefix()."product AS p ON p.rowid = d.fk_product";
		if ($hasProductExtra) {
			$sql .= " LEFT JOIN ".$productExtraTable." AS pe ON pe.fk_object = d.fk_product";
		}
		$sql .= " WHERE d.fk_contrat = ".((int) $contractId);
		$sql .= " AND d.statut = ".((int) $serviceStatusOpen);
		$sql .= " AND d.product_type = 1";
		$sql .= " AND d.fk_product > 0";
		$sql .= " AND c.entity IN (".$this->db->sanitize(getEntity('contrat')).")";
		$sql .= " AND (p.rowid IS NULL OR p.entity IN (".$this->db->sanitize(getEntity('product'))."))";
		$sql .= " ORDER BY d.rang ASC, d.rowid ASC";

		$lines = array();
		$maintenanceIds = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' active service lookup failed: '.$this->db->lasterror(), LOG_WARNING);
			return $lines;
		}
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$lineMaintenanceIds = $this->parseMaintenanceServiceIds(isset($obj->maintenance_services) ? $obj->maintenance_services : '');
			foreach ($lineMaintenanceIds as $maintenanceId) {
				$maintenanceIds[$maintenanceId] = $maintenanceId;
			}
			$lines[] = array(
				'id' => (int) $obj->rowid,
				'contract_ref' => (string) $obj->contract_ref,
				'fk_product' => (int) $obj->fk_product,
				'product_ref' => (string) $obj->product_ref,
				'product_label' => (string) $obj->product_label,
				'maintenance_service_ids' => $lineMaintenanceIds,
				'maintenance_services' => array(),
			);
		}
		$this->db->free($resql);

		if (empty($lines) || empty($maintenanceIds)) {
			return $lines;
		}

		$serviceLabels = $this->fetchMaintenanceServicesByIds(array_values($maintenanceIds));
		foreach ($lines as $lineKey => $line) {
			foreach ($line['maintenance_service_ids'] as $maintenanceId) {
				if (isset($serviceLabels[$maintenanceId])) {
					$lines[$lineKey]['maintenance_services'][$maintenanceId] = $serviceLabels[$maintenanceId];
				}
			}
		}

		return $lines;
	}

	/**
	 * Fetch active maintenance services by ids.
	 *
	 * @param	int[]	$ids	Service ids
	 * @return	array<int,array<string,mixed>>	Services
	 */
	private function fetchMaintenanceServicesByIds($ids)
	{
		$cleanIds = array();
		foreach ($ids as $id) {
			if ((int) $id > 0) {
				$cleanIds[(int) $id] = (int) $id;
			}
		}
		if (empty($cleanIds)) {
			return array();
		}

		$sql = "SELECT rowid, code, label, label_en, active, position";
		$sql .= " FROM ".$this->db->prefix()."c_powerplantpv_maintenance_service";
		$sql .= " WHERE rowid IN (".implode(',', $cleanIds).")";
		$sql .= " AND active = 1";
		$sql .= " AND entity IN (".$this->db->sanitize(getEntity('c_powerplantpv_maintenance_service')).")";
		$sql .= " ORDER BY position ASC, label ASC, rowid ASC";

		$services = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' maintenance service lookup failed: '.$this->db->lasterror(), LOG_WARNING);
			return $services;
		}
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$services[(int) $obj->rowid] = array(
				'id' => (int) $obj->rowid,
				'code' => (string) $obj->code,
				'label' => $this->localizedLabel($obj, 'label'),
				'label_en' => (string) $obj->label_en,
				'active' => (int) $obj->active,
				'position' => (int) $obj->position,
			);
		}
		$this->db->free($resql);

		return $services;
	}

	/**
	 * Fetch equipment rows from linked power plants.
	 *
	 * @param	array<int,array<string,mixed>>	$powerplants	Power plants
	 * @return	array<int,array<string,mixed>>	Equipment rows
	 */
	private function fetchEquipmentContext($powerplants)
	{
		if (empty($powerplants) || !$this->tableExists($this->db->prefix().'powerplantpv_powerplantcomp')) {
			return array();
		}
		$powerplantIds = array_map('intval', array_keys($powerplants));
		$productExtraTable = $this->db->prefix().'product_extrafields';
		$hasProductExtra = $this->tableExists($productExtraTable);
		$hasBrand = $hasProductExtra && $this->columnExists($productExtraTable, 'product_photovoltaic_brand');
		$hasManufacturer = $hasProductExtra && $this->columnExists($productExtraTable, 'product_photovoltaic_manufacturer');

		$sql = "SELECT pc.rowid, pc.fk_powerplant, pc.fk_product, pc.qty, pc.serial_number";
		$sql .= ", p.ref as product_ref, p.label as product_label";
		$sql .= ", inv.rowid as inverter_id";
		$sql .= $hasBrand ? ", pe.product_photovoltaic_brand as equipment_brand" : ", '' as equipment_brand";
		$sql .= $hasManufacturer ? ", pe.product_photovoltaic_manufacturer as equipment_manufacturer" : ", '' as equipment_manufacturer";
		$sql .= " FROM ".$this->db->prefix()."powerplantpv_powerplantcomp AS pc";
		$sql .= " LEFT JOIN ".$this->db->prefix()."product AS p ON p.rowid = pc.fk_product";
		if ($hasProductExtra) {
			$sql .= " LEFT JOIN ".$productExtraTable." AS pe ON pe.fk_object = pc.fk_product";
		}
		$sql .= " LEFT JOIN ".$this->db->prefix()."powerplantpv_product_inverter AS inv ON inv.fk_product = pc.fk_product";
		$sql .= " WHERE pc.fk_powerplant IN (".implode(',', $powerplantIds).")";
		$sql .= " AND pc.entity IN (".$this->db->sanitize(getEntity('powerplant')).")";
		$sql .= " ORDER BY pc.fk_powerplant ASC, pc.rowid ASC";

		$rows = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' equipment lookup failed: '.$this->db->lasterror(), LOG_WARNING);
			return $rows;
		}
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$type = $this->guessEquipmentType($obj);
			$technicalKey = 'powerplant:'.((int) $obj->fk_powerplant).':line:'.((int) $obj->rowid);
			$productLabel = (string) $obj->product_label;
			$brand = !empty($obj->equipment_brand) ? (string) $obj->equipment_brand : (!empty($obj->equipment_manufacturer) ? (string) $obj->equipment_manufacturer : '');
			$snapshot = array(
				'fk_powerplant_line' => (int) $obj->rowid,
				'fk_product' => (int) $obj->fk_product,
				'product_ref' => (string) $obj->product_ref,
				'product_label' => $productLabel,
				'equipment_type' => $type,
				'serial_number' => (string) $obj->serial_number,
				'qty' => (float) $obj->qty,
				'catalog_inverter_id' => !empty($obj->inverter_id) ? (int) $obj->inverter_id : 0,
			);
			$snapshotJson = json_encode($snapshot);
			$rows[] = array(
				'fk_powerplant' => (int) $obj->fk_powerplant,
				'fk_powerplant_line' => (int) $obj->rowid,
				'fk_source_equipment' => (int) $obj->rowid,
				'fk_product' => (int) $obj->fk_product,
				'product_ref' => (string) $obj->product_ref,
				'product_label' => $productLabel,
				'equipment_brand' => $brand,
				'equipment_model' => (string) $obj->product_ref,
				'equipment_type' => $type,
				'equipment_ref' => (string) $obj->product_ref,
				'equipment_label' => $productLabel,
				'serial_number' => (string) $obj->serial_number,
				'qty' => (float) $obj->qty,
				'technical_key' => $technicalKey,
				'equipment_position' => '',
				'technical_snapshot' => is_string($snapshotJson) ? $snapshotJson : '',
			);
		}
		$this->db->free($resql);

		return $rows;
	}

	/**
	 * Fetch installed MPPT/PV input context from power plant equipment configuration.
	 *
	 * @param	array<int,array<string,mixed>>	$powerplants	Power plants
	 * @param	array<int,array<string,mixed>>	$equipment	Equipment rows
	 * @return	array<int,array<string,mixed>>		DC input rows
	 */
	private function fetchDcMeasureInputContext($powerplants, $equipment)
	{
		if (empty($powerplants) || empty($equipment)) {
			return array();
		}
		if (!$this->tableExists($this->db->prefix().'powerplantpv_equipment_mppt') || !$this->tableExists($this->db->prefix().'powerplantpv_equipment_string')) {
			return array();
		}

		$invertersByLine = array();
		foreach ($equipment as $equipmentRow) {
			if ((string) $equipmentRow['equipment_type'] !== 'INVERTER') {
				continue;
			}
			$lineId = (int) $equipmentRow['fk_powerplant_line'];
			if ($lineId > 0) {
				$invertersByLine[$lineId] = $equipmentRow;
			}
		}
		if (empty($invertersByLine)) {
			return array();
		}

		$powerplantIds = array_map('intval', array_keys($powerplants));
		$inverterIds = array_map('intval', array_keys($invertersByLine));
		$inputsByKey = array();

		$sql = "SELECT rowid, fk_powerplant, fk_inverter, mppt_number, pv_input_count, position";
		$sql .= " FROM ".$this->db->prefix()."powerplantpv_equipment_mppt";
		$sql .= " WHERE fk_powerplant IN (".implode(',', $powerplantIds).")";
		$sql .= " AND fk_inverter IN (".implode(',', $inverterIds).")";
		$sql .= " AND entity IN (".$this->db->sanitize(getEntity('powerplant')).")";
		$sql .= " ORDER BY fk_powerplant ASC, fk_inverter ASC, position ASC, mppt_number ASC";
		$resql = $this->db->query($sql);
		if ($resql) {
			while (is_object($obj = $this->db->fetch_object($resql))) {
				$inputCount = max(0, (int) $obj->pv_input_count);
				for ($inputNumber = 1; $inputNumber <= $inputCount; $inputNumber++) {
					$key = ((int) $obj->fk_powerplant).':'.((int) $obj->fk_inverter).':'.((int) $obj->mppt_number).':'.$inputNumber;
					$equipmentRow = $invertersByLine[(int) $obj->fk_inverter];
					$inputsByKey[$key] = $this->newDcMeasureInputContextRow($equipmentRow, (int) $obj->mppt_number, $inputNumber, '', 0, ((int) $obj->position * 100) + $inputNumber);
				}
			}
			$this->db->free($resql);
		}

		$sql = "SELECT rowid, fk_powerplant, fk_inverter, mppt_number, pv_input_number, string_ref, module_count, module_power, orientation, tilt, is_connected, position";
		$sql .= " FROM ".$this->db->prefix()."powerplantpv_equipment_string";
		$sql .= " WHERE fk_powerplant IN (".implode(',', $powerplantIds).")";
		$sql .= " AND fk_inverter IN (".implode(',', $inverterIds).")";
		$sql .= " AND entity IN (".$this->db->sanitize(getEntity('powerplant')).")";
		$sql .= " ORDER BY fk_powerplant ASC, fk_inverter ASC, position ASC, mppt_number ASC, pv_input_number ASC";
		$resql = $this->db->query($sql);
		if ($resql) {
			while (is_object($obj = $this->db->fetch_object($resql))) {
				$key = ((int) $obj->fk_powerplant).':'.((int) $obj->fk_inverter).':'.((int) $obj->mppt_number).':'.((int) $obj->pv_input_number);
				$equipmentRow = $invertersByLine[(int) $obj->fk_inverter];
				$row = $this->newDcMeasureInputContextRow($equipmentRow, (int) $obj->mppt_number, (int) $obj->pv_input_number, (string) $obj->string_ref, (int) $obj->is_connected, (int) $obj->position);
				$row['module_count'] = isset($obj->module_count) ? (int) $obj->module_count : 0;
				$row['module_power'] = isset($obj->module_power) ? (float) $obj->module_power : 0.0;
				$row['orientation'] = isset($obj->orientation) ? (string) $obj->orientation : '';
				$row['tilt'] = isset($obj->tilt) ? (float) $obj->tilt : 0.0;
				$inputsByKey[$key] = $row;
			}
			$this->db->free($resql);
		}

		$inputs = array_values($inputsByKey);
		usort($inputs, function ($a, $b) {
			$cmp = ((int) $a['fk_powerplant'] <=> (int) $b['fk_powerplant']);
			if ($cmp !== 0) {
				return $cmp;
			}
			$cmp = ((int) $a['fk_inverter'] <=> (int) $b['fk_inverter']);
			if ($cmp !== 0) {
				return $cmp;
			}
			$cmp = ((int) $a['mppt_number'] <=> (int) $b['mppt_number']);
			if ($cmp !== 0) {
				return $cmp;
			}
			return ((int) $a['pv_input_number'] <=> (int) $b['pv_input_number']);
		});

		return $inputs;
	}

	/**
	 * Build one DC input context row.
	 *
	 * @param	array<string,mixed>	$equipmentRow	Equipment context row
	 * @param	int					$mpptNumber		MPPT number
	 * @param	int					$pvInputNumber	PV input number
	 * @param	string				$stringRef		String reference
	 * @param	int					$isConnected	Connected flag
	 * @param	int					$position		Position
	 * @return	array<string,mixed>				Context row
	 */
	private function newDcMeasureInputContextRow($equipmentRow, $mpptNumber, $pvInputNumber, $stringRef, $isConnected, $position)
	{
		return array(
			'fk_powerplant' => (int) $equipmentRow['fk_powerplant'],
			'fk_inverter' => (int) $equipmentRow['fk_powerplant_line'],
			'equipment_key' => (string) $equipmentRow['technical_key'],
			'inverter_ref' => (string) $equipmentRow['equipment_ref'],
			'inverter_label' => (string) $equipmentRow['equipment_label'],
			'inverter_serial' => (string) $equipmentRow['serial_number'],
			'mppt_number' => (int) $mpptNumber,
			'pv_input_number' => (int) $pvInputNumber,
			'string_ref' => (string) $stringRef,
			'is_connected' => ((int) $isConnected ? 1 : 0),
			'module_count' => 0,
			'module_power' => 0.0,
			'orientation' => '',
			'tilt' => 0.0,
			'position' => (int) $position,
		);
	}

	/**
	 * Guess equipment type from product data.
	 *
	 * @param	stdClass	$obj	SQL row
	 * @return	string				Equipment type
	 */
	private function guessEquipmentType($obj)
	{
		if (!empty($obj->inverter_id)) {
			return 'INVERTER';
		}
		$text = strtoupper((string) $obj->product_ref.' '.(string) $obj->product_label);
		if (strpos($text, 'ONDULEUR') !== false || strpos($text, 'INVERTER') !== false) {
			return 'INVERTER';
		}
		if (strpos($text, 'COFFRET AC') !== false || strpos($text, 'AC BOX') !== false) {
			return 'AC_BOX';
		}
		if (strpos($text, 'COFFRET DC') !== false || strpos($text, 'DC BOX') !== false) {
			return 'DC_BOX';
		}
		if (strpos($text, 'MODULE') !== false || strpos($text, 'PANNEAU') !== false || strpos($text, 'PANEL') !== false) {
			return 'PANEL';
		}

		return 'OTHER';
	}

	/**
	 * Fetch active template sections.
	 *
	 * @param	int	$templateId	Template id
	 * @return	array<int,PowerPlantPVReportTemplateSection>	Sections
	 */
	private function fetchTemplateSections($templateId)
	{
		$sectionObject = new PowerPlantPVReportTemplateSection($this->db);
		$rows = $sectionObject->fetchAll(1, array('fk_report_template' => (int) $templateId), 'position', 'ASC');
		return is_array($rows) ? $rows : array();
	}

	/**
	 * Fetch active template fields.
	 *
	 * @param	int	$templateId	Template id
	 * @return	array<int,PowerPlantPVReportTemplateField>	Fields
	 */
	private function fetchTemplateFields($templateId)
	{
		$fieldObject = new PowerPlantPVReportTemplateField($this->db);
		$rows = $fieldObject->fetchAll(1, array('fk_report_template' => (int) $templateId), 'position', 'ASC');

		return is_array($rows) ? $rows : array();
	}

	/**
	 * Fetch field options.
	 *
	 * @param	array<int,PowerPlantPVReportTemplateField>	$fields	Template fields
	 * @return	array<int,array<int,array<string,string>>>	Options by field id
	 */
	private function fetchFieldOptions($fields)
	{
		$fieldIds = array();
		foreach ($fields as $field) {
			$fieldIds[(int) $field->id] = (int) $field->id;
		}
		if (empty($fieldIds)) {
			return array();
		}

		$sql = "SELECT rowid, fk_report_template_field, code, label, label_en";
		$sql .= " FROM ".$this->db->prefix()."powerplantpv_report_template_field_option";
		$sql .= " WHERE fk_report_template_field IN (".implode(',', $fieldIds).")";
		$sql .= " AND active = 1";
		$sql .= " AND entity IN (".$this->db->sanitize(getEntity('powerplantpv_report_template_field_option')).")";
		$sql .= " ORDER BY fk_report_template_field ASC, position ASC, rowid ASC";

		$options = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' field option lookup failed: '.$this->db->lasterror(), LOG_WARNING);
			return $options;
		}
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$fieldId = (int) $obj->fk_report_template_field;
			if (!isset($options[$fieldId])) {
				$options[$fieldId] = array();
			}
			$options[$fieldId][] = array(
				'code' => (string) $obj->code,
				'label' => (string) $obj->label,
				'label_en' => (string) $obj->label_en,
			);
		}
		$this->db->free($resql);

		return $options;
	}

	/**
	 * Fetch mapped section ids for active service rows.
	 *
	 * @param	int						$templateId	Template id
	 * @param	array<int,array<string,mixed>>	$serviceRows	Source service rows
	 * @return	array<int,int>								Section ids
	 */
	private function fetchMappedSectionIds($templateId, $serviceRows)
	{
		$serviceIds = array();
		foreach ($serviceRows as $row) {
			if ((int) $row['fk_maintenance_service'] > 0) {
				$serviceIds[(int) $row['fk_maintenance_service']] = (int) $row['fk_maintenance_service'];
			}
		}
		if (empty($serviceIds)) {
			return array();
		}

		$sql = "SELECT fk_report_template_section";
		$sql .= " FROM ".$this->db->prefix()."powerplantpv_maintenance_service_section";
		$sql .= " WHERE active = 1";
		$sql .= " AND fk_maintenance_service IN (".implode(',', $serviceIds).")";
		$sql .= " AND (fk_report_template = ".((int) $templateId)." OR fk_report_template IS NULL OR fk_report_template = 0)";
		$sql .= " AND fk_report_template_section IS NOT NULL";
		$sql .= " AND fk_report_template_section > 0";
		$sql .= " AND entity IN (".$this->db->sanitize(getEntity('powerplantpv_maintenance_service_section')).")";

		$ids = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' service section mapping lookup failed: '.$this->db->lasterror(), LOG_WARNING);
			return $ids;
		}
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$ids[(int) $obj->fk_report_template_section] = (int) $obj->fk_report_template_section;
		}
		$this->db->free($resql);

		return $ids;
	}

	/**
	 * Fetch mapped section ids for active service rows, grouped by power plant.
	 *
	 * @param	int						$templateId		Template id
	 * @param	array<int,array<string,mixed>>	$serviceRows	Source service rows
	 * @return	array<int,array<int,int>>						Section ids by power plant id
	 */
	private function fetchMappedSectionIdsByPowerplant($templateId, $serviceRows)
	{
		$serviceRowsByPowerplant = array();
		foreach ($serviceRows as $row) {
			$powerplantId = (int) $row['fk_powerplant'];
			if (!isset($serviceRowsByPowerplant[$powerplantId])) {
				$serviceRowsByPowerplant[$powerplantId] = array();
			}
			$serviceRowsByPowerplant[$powerplantId][] = $row;
		}

		$mapped = array();
		foreach ($serviceRowsByPowerplant as $powerplantId => $rows) {
			$mapped[(int) $powerplantId] = $this->fetchMappedSectionIds($templateId, $rows);
		}

		return $mapped;
	}

	/**
	 * Check if an optional section is mapped for one power plant.
	 *
	 * @param	int					$sectionId	Section id
	 * @param	int					$powerplantId	Power plant id
	 * @param	array<string,mixed>	$context	Context
	 * @return	bool							True when mapped
	 */
	private function isSectionMappedForPowerplant($sectionId, $powerplantId, $context)
	{
		if (!empty($context['mapped_section_ids_by_powerplant'][$powerplantId][$sectionId])) {
			return true;
		}
		if ($powerplantId <= 0 && !empty($context['mapped_section_ids'][$sectionId])) {
			return true;
		}

		return false;
	}

	/**
	 * Find equipment context by technical key.
	 *
	 * @param	array<string,mixed>	$context	Context
	 * @param	string				$key		Technical key
	 * @return	array<string,mixed>|null		Equipment context or null
	 */
	private function findEquipmentContextByKey($context, $key)
	{
		if ($key === '' || empty($context['equipment']) || !is_array($context['equipment'])) {
			return null;
		}
		foreach ($context['equipment'] as $equipment) {
			if ((string) $equipment['technical_key'] === $key) {
				return $equipment;
			}
		}

		return null;
	}

	/**
	 * Encode field option snapshot.
	 *
	 * @param	int										$fieldId	Field id
	 * @param	array<int,array<int,array<string,string>>>	$options	Options by field id
	 * @return	string												JSON snapshot
	 */
	private function encodeFieldOptions($fieldId, $options)
	{
		$fieldOptions = isset($options[$fieldId]) ? $options[$fieldId] : array();
		if (empty($fieldOptions)) {
			return '';
		}
		$json = json_encode($fieldOptions);

		return is_string($json) ? $json : '';
	}

	/**
	 * Fetch old field values.
	 *
	 * @param	int	$reportId	Report id
	 * @return	array<string,array<string,mixed>>	Values by stable key
	 */
	private function fetchExistingFieldValues($reportId)
	{
		$sql = "SELECT stable_key, value_text, value_number, value_date";
		$sql .= " FROM ".$this->db->prefix()."powerplantpv_report_field";
		$sql .= " WHERE fk_report = ".((int) $reportId);

		$values = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			return $values;
		}
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$values[(string) $obj->stable_key] = array(
				'value_text' => isset($obj->value_text) ? (string) $obj->value_text : '',
				'value_number' => isset($obj->value_number) ? $obj->value_number : null,
				'value_date' => isset($obj->value_date) ? (string) $obj->value_date : null,
			);
		}
		$this->db->free($resql);

		return $values;
	}

	/**
	 * Fetch old DC measure values.
	 *
	 * @param	int	$reportId	Report id
	 * @return	array<string,array<string,mixed>>	Values by stable key
	 */
	private function fetchExistingDcMeasureValues($reportId)
	{
		if (!$this->tableExists($this->db->prefix().'powerplantpv_report_dc_measure')) {
			return array();
		}

		$sql = "SELECT rowid, fk_report_powerplant, fk_report_equipment, fk_powerplant, fk_inverter, inverter_ref, inverter_label, inverter_serial";
		$sql .= ", mppt_number, pv_input_number, string_ref, is_connected, open_circuit_voltage, polarity_checked, insulation_status";
		$sql .= ", insulation_positive_to_ground, insulation_negative_to_ground, observation, stable_key, position";
		$sql .= " FROM ".$this->db->prefix()."powerplantpv_report_dc_measure";
		$sql .= " WHERE fk_report = ".((int) $reportId);

		$values = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			return $values;
		}
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$values[(string) $obj->stable_key] = array(
				'rowid' => (int) $obj->rowid,
				'fk_report_powerplant' => isset($obj->fk_report_powerplant) ? (int) $obj->fk_report_powerplant : 0,
				'fk_report_equipment' => isset($obj->fk_report_equipment) ? (int) $obj->fk_report_equipment : 0,
				'fk_powerplant' => isset($obj->fk_powerplant) ? (int) $obj->fk_powerplant : 0,
				'fk_inverter' => isset($obj->fk_inverter) ? (int) $obj->fk_inverter : 0,
				'inverter_ref' => isset($obj->inverter_ref) ? (string) $obj->inverter_ref : '',
				'inverter_label' => isset($obj->inverter_label) ? (string) $obj->inverter_label : '',
				'inverter_serial' => isset($obj->inverter_serial) ? (string) $obj->inverter_serial : '',
				'mppt_number' => isset($obj->mppt_number) ? (int) $obj->mppt_number : null,
				'pv_input_number' => isset($obj->pv_input_number) ? (int) $obj->pv_input_number : null,
				'string_ref' => isset($obj->string_ref) ? (string) $obj->string_ref : '',
				'is_connected' => isset($obj->is_connected) ? (int) $obj->is_connected : 0,
				'open_circuit_voltage' => isset($obj->open_circuit_voltage) ? $obj->open_circuit_voltage : null,
				'polarity_checked' => isset($obj->polarity_checked) ? (int) $obj->polarity_checked : 0,
				'insulation_status' => isset($obj->insulation_status) ? (string) $obj->insulation_status : '',
				'insulation_positive_to_ground' => isset($obj->insulation_positive_to_ground) ? $obj->insulation_positive_to_ground : null,
				'insulation_negative_to_ground' => isset($obj->insulation_negative_to_ground) ? $obj->insulation_negative_to_ground : null,
				'observation' => isset($obj->observation) ? (string) $obj->observation : '',
				'stable_key' => (string) $obj->stable_key,
				'position' => isset($obj->position) ? (int) $obj->position : 0,
			);
		}
		$this->db->free($resql);

		return $values;
	}

	/**
	 * Fetch old file rows grouped by stable key.
	 *
	 * @param	int	$reportId	Report id
	 * @return	array<string,array<int,int>>	File ids by stable key
	 */
	private function fetchExistingFilesByStableKey($reportId)
	{
		$sql = "SELECT f.rowid, rf.stable_key";
		$sql .= " FROM ".$this->db->prefix()."powerplantpv_report_file AS f";
		$sql .= " INNER JOIN ".$this->db->prefix()."powerplantpv_report_field AS rf ON rf.rowid = f.fk_report_field";
		$sql .= " WHERE f.fk_report = ".((int) $reportId);

		$files = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			return $files;
		}
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$key = (string) $obj->stable_key;
			if (!isset($files[$key])) {
				$files[$key] = array();
			}
			$files[$key][] = (int) $obj->rowid;
		}
		$this->db->free($resql);

		return $files;
	}

	/**
	 * Copy stored value to a new field object.
	 *
	 * @param	PowerPlantPVReportField	$field	Field
	 * @param	array<string,mixed>		$value	Value row
	 * @return	void
	 */
	private function copyStoredValueToField(PowerPlantPVReportField $field, $value)
	{
		$field->value_text = isset($value['value_text']) ? (string) $value['value_text'] : null;
		$field->value_number = isset($value['value_number']) && $value['value_number'] !== null ? (float) $value['value_number'] : null;
		$field->value_date = !empty($value['value_date']) ? (string) $value['value_date'] : null;
	}

	/**
	 * Copy stored DC value to a measure object.
	 *
	 * @param	PowerPlantPVReportDcMeasure	$measure	Measure
	 * @param	array<string,mixed>			$value		Value row
	 * @return	void
	 */
	private function copyStoredValueToDcMeasure(PowerPlantPVReportDcMeasure $measure, $value)
	{
		if (!empty($value['inverter_ref'])) {
			$measure->inverter_ref = (string) $value['inverter_ref'];
		}
		if (!empty($value['inverter_label'])) {
			$measure->inverter_label = (string) $value['inverter_label'];
		}
		if (!empty($value['inverter_serial'])) {
			$measure->inverter_serial = (string) $value['inverter_serial'];
		}
		if (isset($value['mppt_number'])) {
			$measure->mppt_number = $value['mppt_number'] !== null ? (int) $value['mppt_number'] : null;
		}
		if (isset($value['pv_input_number'])) {
			$measure->pv_input_number = $value['pv_input_number'] !== null ? (int) $value['pv_input_number'] : null;
		}
		$measure->string_ref = isset($value['string_ref']) ? (string) $value['string_ref'] : (string) $measure->string_ref;
		$measure->is_connected = isset($value['is_connected']) ? (int) $value['is_connected'] : (int) $measure->is_connected;
		$measure->open_circuit_voltage = isset($value['open_circuit_voltage']) && $value['open_circuit_voltage'] !== null ? (float) $value['open_circuit_voltage'] : null;
		$measure->polarity_checked = isset($value['polarity_checked']) ? (int) $value['polarity_checked'] : 0;
		$measure->insulation_status = isset($value['insulation_status']) ? (string) $value['insulation_status'] : '';
		$measure->insulation_positive_to_ground = isset($value['insulation_positive_to_ground']) && $value['insulation_positive_to_ground'] !== null ? (float) $value['insulation_positive_to_ground'] : null;
		$measure->insulation_negative_to_ground = isset($value['insulation_negative_to_ground']) && $value['insulation_negative_to_ground'] !== null ? (float) $value['insulation_negative_to_ground'] : null;
		$measure->observation = isset($value['observation']) ? (string) $value['observation'] : '';
	}

	/**
	 * Assign submitted values to a DC measure.
	 *
	 * @param	PowerPlantPVReportDcMeasure	$measure	Measure
	 * @param	array<string,mixed>			$row		Submitted row
	 * @return	void
	 */
	private function assignSubmittedValueToDcMeasure(PowerPlantPVReportDcMeasure $measure, $row)
	{
		$measure->inverter_label = isset($row['inverter_label']) ? dol_string_nohtmltag((string) $row['inverter_label']) : (string) $measure->inverter_label;
		$measure->mppt_number = isset($row['mppt_number']) && (string) $row['mppt_number'] !== '' ? (int) $row['mppt_number'] : null;
		$measure->pv_input_number = isset($row['pv_input_number']) && (string) $row['pv_input_number'] !== '' ? (int) $row['pv_input_number'] : null;
		$measure->string_ref = isset($row['string_ref']) ? dol_string_nohtmltag((string) $row['string_ref']) : '';
		$measure->is_connected = !empty($row['is_connected']) ? 1 : 0;
		$measure->open_circuit_voltage = (isset($row['open_circuit_voltage']) && (string) $row['open_circuit_voltage'] !== '') ? (float) price2num((string) $row['open_circuit_voltage']) : null;
		$measure->polarity_checked = !empty($row['polarity_checked']) ? 1 : 0;
		$measure->insulation_status = isset($row['insulation_status']) ? dol_string_nohtmltag((string) $row['insulation_status']) : '';
		$measure->insulation_positive_to_ground = (isset($row['insulation_positive_to_ground']) && (string) $row['insulation_positive_to_ground'] !== '') ? (float) price2num((string) $row['insulation_positive_to_ground']) : null;
		$measure->insulation_negative_to_ground = (isset($row['insulation_negative_to_ground']) && (string) $row['insulation_negative_to_ground'] !== '') ? (float) price2num((string) $row['insulation_negative_to_ground']) : null;
		$measure->observation = isset($row['observation']) ? dol_string_nohtmltag((string) $row['observation']) : '';
	}

	/**
	 * Assign a submitted value to a field.
	 *
	 * @param	PowerPlantPVReportField	$field	Field
	 * @param	mixed					$value	Submitted value
	 * @return	bool							True if assigned
	 */
	private function assignSubmittedValueToField(PowerPlantPVReportField $field, $value)
	{
		if ($value === null && in_array((string) $field->field_type, array('checkbox', 'yesno'), true)) {
			$value = 0;
		}
		if ($value === null) {
			return false;
		}
		if (is_array($value)) {
			$clean = array();
			foreach ($value as $item) {
				$item = trim((string) $item);
				if ($item !== '') {
					$clean[] = $item;
				}
			}
			$field->value_text = implode("\n", $clean);
			return true;
		}
		if ($this->isNumericReportFieldType((string) $field->field_type)) {
			$field->value_number = price2num((string) $value);
			$field->value_text = null;
			$field->value_date = null;
			return true;
		}
		if ((string) $field->field_type === 'date' || (string) $field->field_type === 'datetime') {
			$field->value_date = (string) $value;
			$field->value_text = null;
			$field->value_number = null;
			return true;
		}
		if ((string) $field->field_type === 'checkbox' || (string) $field->field_type === 'yesno') {
			$field->value_text = ((int) $value) ? '1' : '0';
			return true;
		}

		$field->value_text = (string) $value;
		return true;
	}

	/**
	 * Return true for report field types stored in value_number.
	 *
	 * @param	string	$fieldType	Field type
	 * @return	bool				True for numeric types
	 */
	private function isNumericReportFieldType($fieldType)
	{
		return in_array((string) $fieldType, array('number', 'double', 'real', 'integer', 'price'), true);
	}

	/**
	 * Synchronize saved production readings from a finalized report into the archive.
	 *
	 * @param	PowerPlantPVReport	$report	Report
	 * @param	User				$user	User
	 * @return	int							>0 if OK, <0 on error
	 */
	private function syncIndexReadingsFromReport(PowerPlantPVReport $report, User $user)
	{
		if (!$this->tableExists($this->db->prefix().'powerplantpv_index_reading')) {
			return 1;
		}

		$rows = $this->fetchProductionReadingRowsForReport((int) $report->id);
		if (!is_array($rows)) {
			return -1;
		}
		$commentsBySection = $this->fetchProductionReadingCommentsBySection((int) $report->id);
		$typeIdsByCode = $this->fetchIndexTypeIdsByCode();
		$readingDate = $this->fetchReportReadingDate($report);

		foreach ($rows as $row) {
			$typeCode = $this->productionReadingTypeCodeFromField((string) $row['field_code'], 0);
			if ($typeCode === '') {
				continue;
			}
			$fkPowerplant = (int) $row['fk_powerplant'];
			if ($fkPowerplant <= 0) {
				continue;
			}
			$meterRef = '';
			$fkReportEquipment = (int) $row['fk_report_equipment'];
			$reading = new PowerPlantPVIndexReading($this->db);
			$existing = $reading->fetchByReportSource($fkPowerplant, (int) $report->fk_fichinter, (int) $report->id, $typeCode, $meterRef, $fkReportEquipment);
			if ($existing < 0) {
				$this->copyErrorsFrom($reading);
				return -1;
			}

			$value = $this->normalizeProductionReadingValue($row);
			if ($value === null) {
				if ($existing > 0 && !empty($reading->active)) {
					$reading->active = 0;
					if ($reading->update($user, 1) < 0) {
						$this->copyErrorsFrom($reading);
						return -1;
					}
				}
				continue;
			}

			$reading->entity = (int) $report->entity;
			$reading->fk_powerplant = $fkPowerplant;
			$reading->fk_fichinter_source = (int) $report->fk_fichinter;
			$reading->fk_report = (int) $report->id;
			$reading->fk_report_powerplant = (int) $row['fk_report_powerplant'];
			$reading->fk_report_equipment = $fkReportEquipment;
			$reading->fk_index_type = isset($typeIdsByCode[$typeCode]) ? (int) $typeIdsByCode[$typeCode] : null;
			$reading->reading_type_code = $typeCode;
			$reading->reading_date = $this->db->idate($readingDate);
			$reading->value = $value;
			$reading->unit = !empty($row['unit']) ? (string) $row['unit'] : 'kWh';
			$reading->meter_ref = $meterRef;
			$reading->source_type = PowerPlantPVIndexReading::SOURCE_REPORT;
			$reading->comment = isset($commentsBySection[(int) $row['fk_report_section']]) ? (string) $commentsBySection[(int) $row['fk_report_section']] : '';
			$reading->active = 1;
			$result = ($existing > 0) ? $reading->update($user, 1) : $reading->create($user, 1);
			if ($result <= 0) {
				$this->copyErrorsFrom($reading);
				return -1;
			}
		}

		return 1;
	}

	/**
	 * Deactivate all index readings previously archived from a report.
	 *
	 * @param	int		$reportId	Report id
	 * @param	User	$user		User
	 * @return	int					>0 if OK, <0 on error
	 */
	private function deactivateIndexReadingsForReport($reportId, User $user)
	{
		if (!$this->tableExists($this->db->prefix().'powerplantpv_index_reading')) {
			return 1;
		}
		$reading = new PowerPlantPVIndexReading($this->db);
		$result = $reading->deactivateByReport((int) $reportId, $user);
		if ($result < 0) {
			$this->copyErrorsFrom($reading);
			return -1;
		}

		return 1;
	}

	/**
	 * Fetch production reading N fields from a report.
	 *
	 * @param	int	$reportId	Report id
	 * @return	array<int,array<string,mixed>>|int	Rows or <0 on error
	 */
	private function fetchProductionReadingRowsForReport($reportId)
	{
		$sql = "SELECT f.rowid, f.fk_report_section, f.fk_report_powerplant, f.fk_report_equipment, f.field_code, f.value_text, f.value_number, f.unit";
		$sql .= ", s.fk_report_powerplant as section_report_powerplant, rp.fk_powerplant";
		$sql .= " FROM ".$this->db->prefix()."powerplantpv_report_field as f";
		$sql .= " INNER JOIN ".$this->db->prefix()."powerplantpv_report_section as s ON s.rowid = f.fk_report_section";
		$sql .= " LEFT JOIN ".$this->db->prefix()."powerplantpv_report_powerplant as rp ON rp.rowid = COALESCE(NULLIF(f.fk_report_powerplant, 0), s.fk_report_powerplant)";
		$sql .= " WHERE f.fk_report = ".((int) $reportId);
		$sql .= " AND s.section_code = 'PRODUCTION_READING'";
		$sql .= " ORDER BY s.position ASC, f.position ASC, f.rowid ASC";

		$rows = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$rows[] = array(
				'rowid' => (int) $obj->rowid,
				'fk_report_section' => (int) $obj->fk_report_section,
				'fk_report_powerplant' => !empty($obj->fk_report_powerplant) ? (int) $obj->fk_report_powerplant : (int) $obj->section_report_powerplant,
				'fk_report_equipment' => !empty($obj->fk_report_equipment) ? (int) $obj->fk_report_equipment : 0,
				'fk_powerplant' => !empty($obj->fk_powerplant) ? (int) $obj->fk_powerplant : 0,
				'field_code' => (string) $obj->field_code,
				'value_text' => isset($obj->value_text) ? (string) $obj->value_text : '',
				'value_number' => isset($obj->value_number) ? $obj->value_number : null,
				'unit' => isset($obj->unit) ? (string) $obj->unit : '',
			);
		}
		$this->db->free($resql);

		return $rows;
	}

	/**
	 * Fetch production reading comments by section.
	 *
	 * @param	int	$reportId	Report id
	 * @return	array<int,string>	Comment by report section id
	 */
	private function fetchProductionReadingCommentsBySection($reportId)
	{
		$sql = "SELECT f.fk_report_section, f.value_text";
		$sql .= " FROM ".$this->db->prefix()."powerplantpv_report_field as f";
		$sql .= " INNER JOIN ".$this->db->prefix()."powerplantpv_report_section as s ON s.rowid = f.fk_report_section";
		$sql .= " WHERE f.fk_report = ".((int) $reportId);
		$sql .= " AND s.section_code = 'PRODUCTION_READING'";
		$sql .= " AND f.field_code = 'PRODUCTION_READING_OBSERVATION'";

		$comments = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' production reading comments lookup failed: '.$this->db->lasterror(), LOG_WARNING);
			return $comments;
		}
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$comments[(int) $obj->fk_report_section] = isset($obj->value_text) ? (string) $obj->value_text : '';
		}
		$this->db->free($resql);

		return $comments;
	}

	/**
	 * Fetch index type row ids by code.
	 *
	 * @return	array<string,int>	Dictionary row ids by code
	 */
	private function fetchIndexTypeIdsByCode()
	{
		$sql = "SELECT rowid, code";
		$sql .= " FROM ".$this->db->prefix()."c_powerplantpv_index_type";
		$sql .= " WHERE entity IN (".$this->db->sanitize(getEntity('c_powerplantpv_index_type')).")";

		$ids = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' index type lookup failed: '.$this->db->lasterror(), LOG_WARNING);
			return $ids;
		}
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$ids[(string) $obj->code] = (int) $obj->rowid;
		}
		$this->db->free($resql);

		return $ids;
	}

	/**
	 * Normalize a production reading value from a report field row.
	 *
	 * @param	array<string,mixed>	$row	Report field row
	 * @return	float|null					Numeric value or null when empty
	 */
	private function normalizeProductionReadingValue($row)
	{
		if (isset($row['value_number']) && $row['value_number'] !== null && (string) $row['value_number'] !== '') {
			return (float) $row['value_number'];
		}
		if (isset($row['value_text']) && trim((string) $row['value_text']) !== '') {
			return (float) price2num((string) $row['value_text']);
		}

		return null;
	}

	/**
	 * Resolve the reading date for report-sourced readings.
	 *
	 * @param	PowerPlantPVReport	$report	Report
	 * @return	int							Unix timestamp
	 */
	private function fetchReportReadingDate(PowerPlantPVReport $report)
	{
		$dateColumns = array('datei', 'dateo', 'date_valid', 'datec', 'tms');
		$availableColumns = array();
		$table = $this->db->prefix().'fichinter';
		foreach ($dateColumns as $column) {
			if ($this->columnExists($table, $column)) {
				$availableColumns[] = $column;
			}
		}
		if (!empty($availableColumns) && !empty($report->fk_fichinter)) {
			$selects = array();
			foreach ($availableColumns as $column) {
				$selects[] = $this->db->sanitize($column);
			}
			$sql = "SELECT ".implode(', ', $selects);
			$sql .= " FROM ".$table;
			$sql .= " WHERE rowid = ".((int) $report->fk_fichinter);
			$resql = $this->db->query($sql);
			if ($resql) {
				$obj = $this->db->fetch_object($resql);
				$this->db->free($resql);
				if (is_object($obj)) {
					foreach ($availableColumns as $column) {
						if (!empty($obj->{$column})) {
							return $this->db->jdate((string) $obj->{$column});
						}
					}
				}
			}
		}
		if (!empty($report->date_creation)) {
			return is_numeric($report->date_creation) ? (int) $report->date_creation : $this->db->jdate((string) $report->date_creation);
		}

		return dol_now();
	}

	/**
	 * Archive existing stable keys before recalculation.
	 *
	 * @param	int	$reportId	Report id
	 * @return	int				>0 if OK, <0 on error
	 */
	private function archiveExistingFieldStableKeys($reportId)
	{
		$sql = "UPDATE ".$this->db->prefix()."powerplantpv_report_field";
		$sql .= " SET stable_key = CONCAT('__old_', rowid)";
		$sql .= " WHERE fk_report = ".((int) $reportId);
		if (!$this->db->query($sql)) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		return 1;
	}

	/**
	 * Delete child rows except fields archived for file reattachment.
	 *
	 * @param	int		$reportId	Report id
	 * @param	int<0,1>	$deleteFields	Delete fields too
	 * @return	int					>0 if OK, <0 on error
	 */
	private function deleteSnapshotChildren($reportId, $deleteFields)
	{
		$tables = array(
			'powerplantpv_report_source_service',
			'powerplantpv_report_dc_measure',
			'powerplantpv_report_section',
			'powerplantpv_report_equipment',
			'powerplantpv_report_powerplant',
		);
		if ($deleteFields) {
			array_unshift($tables, 'powerplantpv_report_file', 'powerplantpv_report_field');
		}
		foreach ($tables as $table) {
			$sql = "DELETE FROM ".$this->db->prefix().$table." WHERE fk_report = ".((int) $reportId);
			if (!$this->db->query($sql)) {
				$this->setError($this->db->lasterror());
				return -1;
			}
		}

		return 1;
	}

	/**
	 * Reattach old files to new matching field ids.
	 *
	 * @param	array<string,array<int,int>>	$oldFiles	Old file ids by stable key
	 * @param	array<string,int>			$newFieldIds	New field ids by stable key
	 * @return	int										>0 if OK, <0 on error
	 */
	private function reattachExistingFiles($oldFiles, $newFieldIds)
	{
		foreach ($oldFiles as $stableKey => $fileIds) {
			if (empty($newFieldIds[$stableKey])) {
				continue;
			}
			foreach ($fileIds as $fileId) {
				$sql = "UPDATE ".$this->db->prefix()."powerplantpv_report_file";
				$sql .= " SET fk_report_field = ".((int) $newFieldIds[$stableKey]);
				$sql .= " WHERE rowid = ".((int) $fileId);
				if (!$this->db->query($sql)) {
					$this->setError($this->db->lasterror());
					return -1;
				}
			}
		}

		return 1;
	}

	/**
	 * Delete archived old fields and orphan file metadata after recalculation.
	 *
	 * @param	int					$reportId	Report id
	 * @param	array<string,int>	$newFieldIds	New field ids
	 * @return	int								>0 if OK, <0 on error
	 */
	private function deleteArchivedFields($reportId, $newFieldIds)
	{
		$newIds = array_values(array_filter(array_map('intval', $newFieldIds)));
		$whereKeep = !empty($newIds) ? " AND rowid NOT IN (".implode(',', $newIds).")" : '';
		$sql = "DELETE FROM ".$this->db->prefix()."powerplantpv_report_field";
		$sql .= " WHERE fk_report = ".((int) $reportId).$whereKeep;
		if (!$this->db->query($sql)) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		$sql = "DELETE f FROM ".$this->db->prefix()."powerplantpv_report_file AS f";
		$sql .= " LEFT JOIN ".$this->db->prefix()."powerplantpv_report_field AS rf ON rf.rowid = f.fk_report_field";
		$sql .= " WHERE f.fk_report = ".((int) $reportId)." AND rf.rowid IS NULL";
		if (!$this->db->query($sql)) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		return 1;
	}

	/**
	 * Fetch native contract links attached to interventions.
	 *
	 * @param	int[]	$interventionIds	Intervention ids
	 * @return	array<int,array<int,int>>	Contract ids by intervention id
	 */
	private function fetchInterventionContractLinks($interventionIds)
	{
		$cleanIds = array();
		foreach ($interventionIds as $id) {
			if ((int) $id > 0) {
				$cleanIds[(int) $id] = (int) $id;
			}
		}
		if (empty($cleanIds)) {
			return array();
		}

		$sql = "SELECT ee.sourcetype, ee.fk_source, ee.targettype, ee.fk_target";
		$sql .= " FROM ".$this->db->prefix()."element_element AS ee";
		$sql .= " INNER JOIN ".$this->db->prefix()."contrat AS c ON (";
		$sql .= "(ee.sourcetype = 'fichinter' AND ee.targettype = 'contrat' AND c.rowid = ee.fk_target)";
		$sql .= " OR ";
		$sql .= "(ee.targettype = 'fichinter' AND ee.sourcetype = 'contrat' AND c.rowid = ee.fk_source)";
		$sql .= ")";
		$sql .= " WHERE c.entity IN (".$this->db->sanitize(getEntity('contrat')).")";
		$sql .= " AND (";
		$sql .= "(ee.sourcetype = 'fichinter' AND ee.targettype = 'contrat' AND ee.fk_source IN (".implode(',', $cleanIds)."))";
		$sql .= " OR ";
		$sql .= "(ee.targettype = 'fichinter' AND ee.sourcetype = 'contrat' AND ee.fk_target IN (".implode(',', $cleanIds)."))";
		$sql .= ")";

		$links = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' intervention contract link lookup failed: '.$this->db->lasterror(), LOG_WARNING);
			return $links;
		}
		while (is_object($obj = $this->db->fetch_object($resql))) {
			if ((string) $obj->sourcetype === 'fichinter') {
				$interventionId = (int) $obj->fk_source;
				$contractId = (int) $obj->fk_target;
			} else {
				$interventionId = (int) $obj->fk_target;
				$contractId = (int) $obj->fk_source;
			}
			if ($interventionId > 0 && $contractId > 0) {
				if (!isset($links[$interventionId])) {
					$links[$interventionId] = array();
				}
				$links[$interventionId][$contractId] = $contractId;
			}
		}
		$this->db->free($resql);

		return $links;
	}

	/**
	 * Fetch contracts linked to power plants.
	 *
	 * @param	int[]	$powerplantIds	Power plant ids
	 * @return	array<int,array<int,int>>	Contract ids by power plant id
	 */
	private function fetchPowerPlantContractLinks($powerplantIds)
	{
		$cleanIds = array();
		foreach ($powerplantIds as $id) {
			if ((int) $id > 0) {
				$cleanIds[(int) $id] = (int) $id;
			}
		}
		if (empty($cleanIds)) {
			return array();
		}
		$powerplantTypes = array();
		foreach (powerplantpvGetPowerPlantLinkTypes() as $type) {
			$powerplantTypes[] = "'".$this->db->escape($type)."'";
		}

		$sql = "SELECT ee.sourcetype, ee.fk_source, ee.targettype, ee.fk_target";
		$sql .= " FROM ".$this->db->prefix()."element_element AS ee";
		$sql .= " INNER JOIN ".$this->db->prefix()."contrat AS c ON (";
		$sql .= "(ee.sourcetype IN (".implode(',', $powerplantTypes).") AND ee.targettype = 'contrat' AND c.rowid = ee.fk_target)";
		$sql .= " OR ";
		$sql .= "(ee.targettype IN (".implode(',', $powerplantTypes).") AND ee.sourcetype = 'contrat' AND c.rowid = ee.fk_source)";
		$sql .= ")";
		$sql .= " WHERE c.entity IN (".$this->db->sanitize(getEntity('contrat')).")";
		$sql .= " AND (";
		$sql .= "(ee.sourcetype IN (".implode(',', $powerplantTypes).") AND ee.fk_source IN (".implode(',', $cleanIds)."))";
		$sql .= " OR ";
		$sql .= "(ee.targettype IN (".implode(',', $powerplantTypes).") AND ee.fk_target IN (".implode(',', $cleanIds)."))";
		$sql .= ")";

		$links = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' power plant contract link lookup failed: '.$this->db->lasterror(), LOG_WARNING);
			return $links;
		}
		while (is_object($obj = $this->db->fetch_object($resql))) {
			if (powerplantpvIsPowerPlantLinkType((string) $obj->sourcetype)) {
				$powerplantId = (int) $obj->fk_source;
				$contractId = (int) $obj->fk_target;
			} else {
				$powerplantId = (int) $obj->fk_target;
				$contractId = (int) $obj->fk_source;
			}
			if ($powerplantId > 0 && $contractId > 0) {
				if (!isset($links[$powerplantId])) {
					$links[$powerplantId] = array();
				}
				$links[$powerplantId][$contractId] = $contractId;
			}
		}
		$this->db->free($resql);

		return $links;
	}

	/**
	 * Parse a Dolibarr multiselect extrafield value into ids.
	 *
	 * @param	string|array<int|string,mixed>|null	$value	Raw value
	 * @return	int[]										Ids
	 */
	private function parseMaintenanceServiceIds($value)
	{
		if (is_array($value)) {
			$parts = $value;
		} else {
			$normalized = str_replace(array(';', '|'), ',', (string) $value);
			$parts = explode(',', $normalized);
		}

		$ids = array();
		foreach ($parts as $part) {
			$id = (int) trim((string) $part);
			if ($id > 0) {
				$ids[$id] = $id;
			}
		}

		return array_values($ids);
	}

	/**
	 * Return Dolibarr v20 contract line open status.
	 *
	 * @return	int	Status value
	 */
	private function getContractLineOpenStatus()
	{
		return (class_exists('ContratLigne') && defined('ContratLigne::STATUS_OPEN')) ? (int) constant('ContratLigne::STATUS_OPEN') : 4;
	}

	/**
	 * Fetch source power plant id from a report power plant row.
	 *
	 * @param	int	$reportPowerplantId	Report power plant row id
	 * @return	int						Source power plant id
	 */
	private function fetchSourcePowerplantId($reportPowerplantId)
	{
		if ($reportPowerplantId <= 0) {
			return 0;
		}
		$sql = "SELECT fk_powerplant";
		$sql .= " FROM ".$this->db->prefix()."powerplantpv_report_powerplant";
		$sql .= " WHERE rowid = ".((int) $reportPowerplantId);
		$resql = $this->db->query($sql);
		if (!$resql) {
			return 0;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);

		return is_object($obj) ? (int) $obj->fk_powerplant : 0;
	}

	/**
	 * Fetch next DC measure position for a section.
	 *
	 * @param	int	$sectionId	Section id
	 * @return	int				Next position
	 */
	private function fetchNextDcMeasurePosition($sectionId)
	{
		$sql = "SELECT MAX(position) as max_position";
		$sql .= " FROM ".$this->db->prefix()."powerplantpv_report_dc_measure";
		$sql .= " WHERE fk_report_section = ".((int) $sectionId);
		$resql = $this->db->query($sql);
		if (!$resql) {
			return 10;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		$position = (is_object($obj) && $obj->max_position !== null) ? (int) $obj->max_position : 0;

		return $position + 10;
	}

	/**
	 * Return localized label from SQL object.
	 *
	 * @param	stdClass	$obj			SQL row
	 * @param	string		$baseProperty	Base property
	 * @return	string						Label
	 */
	private function localizedLabel($obj, $baseProperty)
	{
		global $langs;

		$label = isset($obj->{$baseProperty}) ? (string) $obj->{$baseProperty} : '';
		$englishProperty = $baseProperty.'_en';
		if (is_object($langs) && $langs->defaultlang == 'en_US' && !empty($obj->{$englishProperty})) {
			$label = (string) $obj->{$englishProperty};
		}

		return $label;
	}

	/**
	 * Check table existence.
	 *
	 * @param	string	$table	Full table name
	 * @return	bool			True if table exists
	 */
	private function tableExists($table)
	{
		if (function_exists('powerplantpvDatabaseTableExists')) {
			return powerplantpvDatabaseTableExists($table);
		}

		$sql = "SHOW TABLES LIKE '".$this->db->escape($table)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return false;
		}
		$exists = ($this->db->num_rows($resql) > 0);
		$this->db->free($resql);

		return $exists;
	}

	/**
	 * Check column existence.
	 *
	 * @param	string	$table	Full table name
	 * @param	string	$column	Column name
	 * @return	bool			True if column exists
	 */
	private function columnExists($table, $column)
	{
		if (function_exists('powerplantpvDatabaseTableColumnExists')) {
			return powerplantpvDatabaseTableColumnExists($table, $column);
		}

		$sql = "SHOW COLUMNS FROM ".$this->db->sanitize($table)." LIKE '".$this->db->escape($column)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return false;
		}
		$exists = ($this->db->num_rows($resql) > 0);
		$this->db->free($resql);

		return $exists;
	}

	/**
	 * Copy errors from another object.
	 *
	 * @param	object	$object	Object with error/errors
	 * @return	void
	 */
	private function copyErrorsFrom($object)
	{
		if (!empty($object->error)) {
			$this->error = (string) $object->error;
		}
		if (!empty($object->errors) && is_array($object->errors)) {
			$this->errors = array_merge($this->errors, $object->errors);
		} elseif (!empty($this->error)) {
			$this->errors[] = $this->error;
		}
	}

	/**
	 * Set an error.
	 *
	 * @param	string	$error	Error key/message
	 * @return	void
	 */
	private function setError($error)
	{
		$this->error = $error;
		$this->errors[] = $error;
	}
}
