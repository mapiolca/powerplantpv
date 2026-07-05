<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		core/modules/fichinter/doc/pdf_powerplantpvreport.modules.php
 * \ingroup		powerplantpv
 * \brief		Dynamic PDF model for PowerPlantPV intervention reports.
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/fichinter/modules_fichinter.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
dol_include_once('/powerplantpv/class/powerplantpvreportpdfdataset.class.php');

/**
 * Dynamic PDF model for PowerPlantPV intervention reports.
 */
class pdf_powerplantpvreport extends ModelePDFFicheinter
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Model name
	 */
	public $name = 'powerplantpvreport';

	/**
	 * @var string Model description
	 */
	public $description;

	/**
	 * @var int Save as main document
	 */
	public $update_main_doc_field = 1;

	/**
	 * @var string Document type
	 */
	public $type = 'pdf';

	/**
	 * @var array{0:int,1:int} Minimum PHP version
	 */
	public $phpmin = array(8, 0);

	/**
	 * @var string Model version
	 */
	public $version = 'dolibarr';

	/**
	 * @var Societe Issuer company
	 */
	public $emetteur;

	/**
	 * @var int Support company logo
	 */
	public $option_logo = 1;

	/**
	 * @var int Support multi-language
	 */
	public $option_multilang = 1;

	/**
	 * @var int Support draft watermark
	 */
	public $option_draft_watermark = 1;

	/**
	 * @var string Watermark
	 */
	public $watermark = '';

	/**
	 * @var int Default font size
	 */
	protected $defaultFontSize = 9;

	/**
	 * @var int Footer reserved height
	 */
	protected $heightforfooter = 0;

	/**
	 * @var CommonObject|null Current object
	 */
	protected $currentObject;

	/**
	 * @var Translate|null Current output language
	 */
	protected $currentOutputLangs;

	/**
	 * Constructor.
	 *
	 * @param	DoliDB	$db	Database handler
	 */
	public function __construct($db)
	{
		global $langs, $mysoc;

		$this->db = $db;
		$langs->loadLangs(array('main', 'companies', 'interventions', 'powerplantpv@powerplantpv'));
		$this->description = $langs->trans('PowerPlantPVInterventionReportPdfModel');

		$formatarray = pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
		$this->emetteur = $mysoc;
	}

	/**
	 * Return model information.
	 *
	 * @param	Translate	$langs	Language object
	 * @return	string				Description
	 */
	public function info($langs)
	{
		return $langs->trans('PowerPlantPVInterventionReportPdfModel');
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 * Build and write the PDF file.
	 *
	 * @param	CommonObject	$object				Fichinter object
	 * @param	Translate		$outputlangs		Output language
	 * @param	string			$srctemplatepath	Source template
	 * @param	int<0,1>		$hidedetails		Hide details
	 * @param	int<0,1>		$hidedesc			Hide description
	 * @param	int<0,1>		$hideref			Hide references
	 * @return	int<-1,1>							1 if OK, <=0 if KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		// phpcs:enable
		global $conf, $langs, $user, $hookmanager, $action;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		if (getDolGlobalInt('MAIN_USE_FPDF')) {
			$outputlangs->charset_output = 'ISO-8859-1';
		}
		$outputlangs->loadLangs(array('main', 'companies', 'interventions', 'dict', 'powerplantpv@powerplantpv'));

		if (empty($object->ref)) {
			$this->error = $outputlangs->transnoentities('ErrorUnknown');
			return -1;
		}
		if (method_exists($object, 'fetch_thirdparty')) {
			$object->fetch_thirdparty();
		}

		$dir = $this->getInterventionOutputDir($object);
		if ($dir === '') {
			$this->error = $langs->transnoentities('ErrorConstantNotDefined', 'FICHEINTER_OUTPUTDIR');
			return 0;
		}
		if (!file_exists($dir) && dol_mkdir($dir) < 0) {
			$this->error = $langs->transnoentities('ErrorCanNotCreateDir', $dir);
			return 0;
		}
		if (!file_exists($dir)) {
			$this->error = $langs->transnoentities('ErrorCanNotCreateDir', $dir);
			return 0;
		}

		$file = $dir.'/'.(!empty($object->specimen) ? 'SPECIMEN' : dol_sanitizeFileName($object->ref)).'.pdf';

		if (!is_object($hookmanager)) {
			require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
			$hookmanager = new HookManager($this->db);
		}
		$hookmanager->initHooks(array('pdfgeneration'));
		$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
		$reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action);
		if ($reshook < 0) {
			$this->error = $hookmanager->error;
			$this->errors = $hookmanager->errors;
			return -1;
		}

		$datasetLoader = new PowerPlantPVReportPdfDataset($this->db);
		$dataset = $datasetLoader->loadForIntervention($object, $outputlangs);
		if (!is_array($dataset)) {
			$this->error = $datasetLoader->error;
			$this->errors = $datasetLoader->errors;
			return -1;
		}

		$pdf = pdf_getInstance($this->format);
		'@phan-var-force TCPDI|TCPDF $pdf';
		$defaultFontSize = pdf_getPDFFontSize($outputlangs);
		$this->defaultFontSize = max(8, $defaultFontSize - 1);
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		if (method_exists($pdf, 'setAutoPageBreak')) {
			$pdf->setAutoPageBreak(true, 0);
		} else {
			$pdf->SetAutoPageBreak(true, 0);
		}
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', $this->defaultFontSize);
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
		$pdf->SetSubject($outputlangs->convToOutputCharset($outputlangs->transnoentities('PowerPlantPVReportPdfTitle')));
		$pdf->SetCreator('Dolibarr '.DOL_VERSION);
		$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
		$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref.' '.$outputlangs->transnoentities('PowerPlantPVReportPdfTitle')));
		if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
			$pdf->SetCompression(false);
		}

		$this->heightforfooter = $this->computeFooterHeight();
		$this->currentObject = $object;
		$this->currentOutputLangs = $outputlangs;

		$pdf->Open();
		$pdf->AddPage();
		$this->reserveFooterSpace($pdf);
		$this->renderHeader($pdf, $object, $dataset, $outputlangs);
		$this->renderReport($pdf, $object, $dataset, $outputlangs);
		$this->renderFooters($pdf, $object, $outputlangs);

		$hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action);

		$pdf->Close();
		$pdf->Output($file, 'F');
		if (!empty($conf->global->MAIN_UMASK)) {
			dolChmod($file);
		}

		if (!empty($this->update_main_doc_field)) {
			$this->updateLastMainDoc($object, $file);
		}

		$this->result = array('fullpath' => $file);

		return 1;
	}

	/**
	 * Return the intervention output directory.
	 *
	 * @param	CommonObject	$object	Intervention object
	 * @return	string					Output directory
	 */
	protected function getInterventionOutputDir($object)
	{
		global $conf;

		$dir = '';
		if (function_exists('getMultidirOutput')) {
			$dir = getMultidirOutput($object, 'ficheinter', 1);
		}
		if (!empty($dir)) {
			return $dir;
		}

		$objectEntity = !empty($object->entity) ? (int) $object->entity : (int) $conf->entity;
		$moduleOutput = '';
		if (!empty($conf->ficheinter->multidir_output[$objectEntity])) {
			$moduleOutput = $conf->ficheinter->multidir_output[$objectEntity];
		} elseif (!empty($conf->ficheinter->dir_output)) {
			$moduleOutput = $conf->ficheinter->dir_output;
		}
		if ($moduleOutput === '') {
			return '';
		}

		return rtrim($moduleOutput, '/').'/'.dol_sanitizeFileName($object->ref);
	}

	/**
	 * Render the document header.
	 *
	 * @param	TCPDF|TCPDI		$pdf			PDF handler
	 * @param	CommonObject	$object			Intervention
	 * @param	array<string,mixed>	$dataset	Dataset
	 * @param	Translate		$outputlangs	Output language
	 * @return	void
	 */
	protected function renderHeader(&$pdf, $object, $dataset, $outputlangs)
	{
		global $conf;

		$posy = $this->marge_haute;
		$logoBottom = $posy;
		if (!getDolGlobalInt('PDF_DISABLE_MYCOMPANY_LOGO') && is_object($this->emetteur)) {
			if (!empty($this->emetteur->logo)) {
				$logodir = $conf->mycompany->dir_output;
				if (!empty(getMultidirOutput($object, 'mycompany'))) {
					$logodir = getMultidirOutput($object, 'mycompany');
				}
				$logo = $logodir.'/logos/'.$this->emetteur->logo;
				if (!getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO') && !empty($this->emetteur->logo_small)) {
					$logo = $logodir.'/logos/thumbs/'.$this->emetteur->logo_small;
				}
				if (is_readable($logo)) {
					$height = pdf_getHeightForLogo($logo);
					$pdf->Image($logo, $this->marge_gauche, $posy, 0, $height);
					$logoBottom = $posy + $height;
				}
			} elseif (!empty($this->emetteur->name)) {
				$pdf->SetTextColor(0, 0, 60);
				$pdf->SetFont('', 'B', $this->defaultFontSize);
				$pdf->SetXY($this->marge_gauche, $posy);
				$pdf->MultiCell(80, 4, $outputlangs->convToOutputCharset($this->emetteur->name), 0, 'L');
				$logoBottom = $pdf->GetY();
			}
		}

		$title = $outputlangs->transnoentities('PowerPlantPVReportPdfTitle');
		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont('', 'B', $this->defaultFontSize + 5);
		$pdf->SetXY($this->marge_gauche + 55, $posy);
		$pdf->MultiCell(0, 7, $outputlangs->convToOutputCharset($title), 0, 'R');
		$pdf->SetFont('', '', $this->defaultFontSize);
		$pdf->SetX($this->marge_gauche + 55);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('Intervention').' '.$object->ref), 0, 'R');
		$dateIntervention = $this->getInterventionDate($object);
		if (!empty($dateIntervention)) {
			$pdf->SetX($this->marge_gauche + 55);
			$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset(dol_print_date($dateIntervention, 'day', 'tzuser', $outputlangs)), 0, 'R');
		}

		$pdf->SetY(max($logoBottom, $pdf->GetY()) + 5);
		$customer = is_object($dataset['thirdparty']) ? $dataset['thirdparty'] : null;
		$project = is_object($dataset['project']) ? $dataset['project'] : null;
		$report = is_object($dataset['report']) ? $dataset['report'] : null;
		$rows = array(
			array($outputlangs->transnoentities('Customer'), is_object($customer) ? $customer->name : ''),
			array($outputlangs->transnoentities('Address'), is_object($customer) ? dol_format_address($customer, 1, "\n", $outputlangs) : ''),
			array($outputlangs->transnoentities('Project'), is_object($project) ? trim((string) $project->ref.' - '.(string) $project->title) : ''),
			array($outputlangs->transnoentities('PowerPlantPVInterventionNature'), PowerPlantPVReportPdfDataset::localizedProperty($report, 'intervention_nature_label', $outputlangs)),
			array($outputlangs->transnoentities('PowerPlantPVReportTemplate'), PowerPlantPVReportPdfDataset::localizedProperty($report, 'report_template_label', $outputlangs)),
			array($outputlangs->transnoentities('PowerPlantPVReportContracts'), $this->joinContractRefs($dataset['contracts'])),
			array($outputlangs->transnoentities('PowerPlantPVReportPowerPlants'), $this->joinPowerplantLabels($dataset['powerplants'])),
		);
		$this->renderKeyValueTable($pdf, $this->filterRows($rows), '');
		$pdf->Ln(3);
	}

	/**
	 * Render all report content.
	 *
	 * @param	TCPDF|TCPDI		$pdf			PDF handler
	 * @param	CommonObject	$object			Intervention
	 * @param	array<string,mixed>	$dataset	Dataset
	 * @param	Translate		$outputlangs	Output language
	 * @return	void
	 */
	protected function renderReport(&$pdf, $object, $dataset, $outputlangs)
	{
		if (empty($dataset['report_found'])) {
			$this->renderSectionTitle($pdf, $outputlangs->transnoentities('PowerPlantPVReport'), 1);
			$this->renderEmptyBlock($pdf, $outputlangs->transnoentities('PowerPlantPVReportNoSnapshot'));
			return;
		}

		if (!empty($dataset['general_sections']) && is_array($dataset['general_sections'])) {
			foreach ($dataset['general_sections'] as $sectionRow) {
				$this->renderSection($pdf, $sectionRow, $dataset, $outputlangs);
			}
		}

		if (empty($dataset['powerplants']) || !is_array($dataset['powerplants'])) {
			$this->renderTechnicianFallback($pdf, $dataset, $outputlangs);
			return;
		}

		foreach ($dataset['powerplants'] as $powerplant) {
			if (!is_object($powerplant)) {
				continue;
			}
			$this->ensureSpace($pdf, 28);
			$this->renderSectionTitle($pdf, $this->powerplantLabel($powerplant), 1);
			$this->renderEquipmentTable($pdf, isset($dataset['equipment_by_powerplant'][(int) $powerplant->id]) ? $dataset['equipment_by_powerplant'][(int) $powerplant->id] : array(), $outputlangs);

			$sourceServices = $this->filterSourceServicesByPowerplant($dataset['source_services'], (int) $powerplant->id);
			if (!empty($sourceServices)) {
				$this->renderSourceServices($pdf, $sourceServices, $outputlangs);
			}

			$sections = isset($dataset['sections_by_powerplant'][(int) $powerplant->id]) ? $dataset['sections_by_powerplant'][(int) $powerplant->id] : array();
			if (empty($sections)) {
				$this->renderEmptyBlock($pdf, $outputlangs->transnoentities('NoRecordFound'));
			} else {
				foreach ($sections as $sectionRow) {
					$this->renderSection($pdf, $sectionRow, $dataset, $outputlangs);
				}
			}
		}
		$this->renderTechnicianFallback($pdf, $dataset, $outputlangs);
	}

	/**
	 * Render one snapshot section.
	 *
	 * @param	TCPDF|TCPDI		$pdf			PDF handler
	 * @param	array<string,mixed>	$sectionRow	Section row
	 * @param	array<string,mixed>	$dataset	Dataset
	 * @param	Translate		$outputlangs	Output language
	 * @return	void
	 */
	protected function renderSection(&$pdf, $sectionRow, $dataset, $outputlangs)
	{
		if (empty($sectionRow['section']) || !is_object($sectionRow['section'])) {
			return;
		}
		$section = $sectionRow['section'];
		if (empty($section->visible_pdf)) {
			return;
		}

		$fields = isset($sectionRow['fields']) && is_array($sectionRow['fields']) ? $sectionRow['fields'] : array();
		$visibleFields = $this->filterVisibleFields($fields);
		$dcMeasures = isset($sectionRow['dc_measures']) && is_array($sectionRow['dc_measures']) ? $sectionRow['dc_measures'] : array();
		$label = PowerPlantPVReportPdfDataset::localizedProperty($section, 'section_label', $outputlangs);
		$this->renderSectionTitle($pdf, $label, 2);

		if (!empty($sectionRow['equipment']) && is_object($sectionRow['equipment'])) {
			$this->renderEquipmentSummary($pdf, $sectionRow['equipment'], $outputlangs);
		}

		$sectionCode = (string) $section->section_code;
		if ($sectionCode === 'DC_ELECTRICAL_MEASURE') {
			$this->renderDcMeasures($pdf, $dcMeasures, $outputlangs);
			$this->renderGenericFields($pdf, $visibleFields, $dataset, $outputlangs, true);
			return;
		}
		if ($sectionCode === 'PRODUCTION_READING') {
			$this->renderProductionReadings($pdf, $visibleFields, $dataset, $outputlangs);
			return;
		}
		if ($sectionCode === 'THERMOGRAPHY') {
			$this->renderThermography($pdf, $visibleFields, $dataset, $outputlangs);
			return;
		}

		$this->renderGenericFields($pdf, $visibleFields, $dataset, $outputlangs, false);
	}

	/**
	 * Render generic fields.
	 *
	 * @param	TCPDF|TCPDI		$pdf			PDF handler
	 * @param	array<int,PowerPlantPVReportField>	$fields	Fields
	 * @param	array<string,mixed>	$dataset	Dataset
	 * @param	Translate		$outputlangs	Output language
	 * @param	bool			$hideEmpty		Hide empty block when no generic field
	 * @return	void
	 */
	protected function renderGenericFields(&$pdf, $fields, $dataset, $outputlangs, $hideEmpty = false)
	{
		$rows = array();
		$fileFields = array();
		$signatureFields = array();
		foreach ($fields as $field) {
			if (PowerPlantPVReportPdfDataset::isFileField($field)) {
				$fileFields[] = $field;
				continue;
			}
			if (PowerPlantPVReportPdfDataset::isSignatureField($field)) {
				$signatureFields[] = $field;
				continue;
			}
			$value = PowerPlantPVReportPdfDataset::formatFieldValue($field, $outputlangs);
			if ($value === '') {
				continue;
			}
			$rows[] = array(PowerPlantPVReportPdfDataset::localizedProperty($field, 'field_label', $outputlangs), $value);
		}

		if (!empty($rows)) {
			$this->renderKeyValueTable($pdf, $rows, '');
		} elseif (!$hideEmpty && empty($fileFields) && empty($signatureFields)) {
			$this->renderEmptyBlock($pdf, $outputlangs->transnoentities('NoRecordFound'));
		}

		foreach ($fileFields as $field) {
			$this->renderFieldFiles($pdf, $field, $dataset, $outputlangs);
		}
		if (!empty($signatureFields)) {
			$this->renderSectionTitle($pdf, $outputlangs->transnoentities('PowerPlantPVReportSignatures'), 3);
			foreach ($signatureFields as $field) {
				$this->renderSignatureField($pdf, $field, $dataset, $outputlangs);
			}
		}
	}

	/**
	 * Render DC measures.
	 *
	 * @param	TCPDF|TCPDI	$pdf			PDF handler
	 * @param	array<int,PowerPlantPVReportDcMeasure>	$measures	Measures
	 * @param	Translate	$outputlangs	Output language
	 * @return	void
	 */
	protected function renderDcMeasures(&$pdf, $measures, $outputlangs)
	{
		if (empty($measures)) {
			$this->renderEmptyBlock($pdf, $outputlangs->transnoentities('NoRecordFound'));
			return;
		}

		$html = '<table border="1" cellpadding="3" cellspacing="0" width="100%">';
		$html .= '<tr style="background-color:#f0f3f5;font-weight:bold;">';
		foreach (array('PowerPlantPVInverter', 'PowerPlantPVMPPT', 'PowerPlantPVPVInput', 'PowerPlantPVStringRef', 'PowerPlantPVOpenCircuitVoltage', 'PowerPlantPVPolarityChecked', 'PowerPlantPVInsulationStatus', 'PowerPlantPVInsulationPositiveToGround', 'PowerPlantPVInsulationNegativeToGround', 'Observation') as $key) {
			$html .= '<td>'.dol_escape_htmltag($outputlangs->transnoentities($key)).'</td>';
		}
		$html .= '</tr>';
		foreach ($measures as $measure) {
			if (!is_object($measure)) {
				continue;
			}
			$inverter = trim((string) $measure->inverter_ref.' '.(string) $measure->inverter_label);
			if (!empty($measure->inverter_serial)) {
				$inverter .= ' ('.(string) $measure->inverter_serial.')';
			}
			$html .= '<tr>';
			$html .= '<td>'.dol_escape_htmltag($inverter).'</td>';
			$html .= '<td>'.dol_escape_htmltag((string) $measure->mppt_number).'</td>';
			$html .= '<td>'.dol_escape_htmltag((string) $measure->pv_input_number).'</td>';
			$html .= '<td>'.dol_escape_htmltag((string) $measure->string_ref).'</td>';
			$html .= '<td>'.dol_escape_htmltag($this->formatOptionalNumber($measure->open_circuit_voltage, 'V')).'</td>';
			$html .= '<td>'.dol_escape_htmltag(((int) $measure->polarity_checked) ? $outputlangs->transnoentities('Yes') : $outputlangs->transnoentities('No')).'</td>';
			$html .= '<td>'.dol_escape_htmltag((string) $measure->insulation_status).'</td>';
			$html .= '<td>'.dol_escape_htmltag($this->formatOptionalNumber($measure->insulation_positive_to_ground, 'MOhm')).'</td>';
			$html .= '<td>'.dol_escape_htmltag($this->formatOptionalNumber($measure->insulation_negative_to_ground, 'MOhm')).'</td>';
			$html .= '<td>'.dol_htmlentitiesbr((string) $measure->observation).'</td>';
			$html .= '</tr>';
		}
		$html .= '</table>';
		$this->writeHtml($pdf, $html);
	}

	/**
	 * Render production readings.
	 *
	 * @param	TCPDF|TCPDI	$pdf			PDF handler
	 * @param	array<int,PowerPlantPVReportField>	$fields	Fields
	 * @param	array<string,mixed>	$dataset	Dataset
	 * @param	Translate	$outputlangs	Output language
	 * @return	void
	 */
	protected function renderProductionReadings(&$pdf, $fields, $dataset, $outputlangs)
	{
		$fieldsByCode = PowerPlantPVReportPdfDataset::fieldsByCode($fields);
		$readingDate = $this->resolveReadingDate($dataset, $outputlangs);
		$rows = array();
		foreach ($fieldsByCode as $code => $field) {
			if (substr($code, -10) === '_N_MINUS_1' || $code === 'PRODUCTION_READING_OBSERVATION') {
				continue;
			}
			$previousCode = $code.'_N_MINUS_1';
			if (!isset($fieldsByCode[$previousCode])) {
				continue;
			}
			$previous = $fieldsByCode[$previousCode];
			$valuePrevious = ($previous->value_number === null || (string) $previous->value_number === '') ? '' : (float) $previous->value_number;
			$valueCurrent = ($field->value_number === null || (string) $field->value_number === '') ? '' : (float) $field->value_number;
			$delta = ($valuePrevious !== '' && $valueCurrent !== '') ? ($valueCurrent - $valuePrevious) : '';
			$rows[] = array(
				PowerPlantPVReportPdfDataset::localizedProperty($field, 'field_label', $outputlangs),
				$readingDate,
				$valuePrevious === '' ? '' : price($valuePrevious),
				$valueCurrent === '' ? '' : price($valueCurrent),
				$delta === '' ? '' : price($delta),
				(string) $field->unit,
			);
		}

		if (empty($rows)) {
			$this->renderEmptyBlock($pdf, $outputlangs->transnoentities('NoRecordFound'));
			return;
		}

		$html = '<table border="1" cellpadding="3" cellspacing="0" width="100%">';
		$html .= '<tr style="background-color:#f0f3f5;font-weight:bold;">';
		foreach (array('Type', 'Date', 'N-1', 'N', 'PowerPlantPVReadingDelta', 'Unit') as $key) {
			$html .= '<td>'.dol_escape_htmltag($outputlangs->transnoentities($key)).'</td>';
		}
		$html .= '</tr>';
		foreach ($rows as $row) {
			$html .= '<tr>';
			foreach ($row as $value) {
				$html .= '<td>'.dol_escape_htmltag((string) $value).'</td>';
			}
			$html .= '</tr>';
		}
		$html .= '</table>';
		$this->writeHtml($pdf, $html);

		if (isset($fieldsByCode['PRODUCTION_READING_OBSERVATION'])) {
			$value = PowerPlantPVReportPdfDataset::formatFieldValue($fieldsByCode['PRODUCTION_READING_OBSERVATION'], $outputlangs);
			if ($value !== '') {
				$this->renderKeyValueTable($pdf, array(array($outputlangs->transnoentities('Observation'), $value)), '');
			}
		}
	}

	/**
	 * Render thermography fields and photos.
	 *
	 * @param	TCPDF|TCPDI	$pdf			PDF handler
	 * @param	array<int,PowerPlantPVReportField>	$fields	Fields
	 * @param	array<string,mixed>	$dataset	Dataset
	 * @param	Translate	$outputlangs	Output language
	 * @return	void
	 */
	protected function renderThermography(&$pdf, $fields, $dataset, $outputlangs)
	{
		$genericFields = array();
		$visiblePhotoFields = array();
		$thermalPhotoFields = array();
		foreach ($fields as $field) {
			if (!is_object($field)) {
				continue;
			}
			if ((string) $field->field_code === 'THERMO_VISIBLE_PHOTO') {
				$visiblePhotoFields[] = $field;
			} elseif ((string) $field->field_code === 'THERMO_THERMAL_PHOTO') {
				$thermalPhotoFields[] = $field;
			} else {
				$genericFields[] = $field;
			}
		}
		$this->renderGenericFields($pdf, $genericFields, $dataset, $outputlangs, false);
		foreach (array(
			$outputlangs->transnoentities('PowerPlantPVReportVisiblePhoto') => $visiblePhotoFields,
			$outputlangs->transnoentities('PowerPlantPVReportThermalPhoto') => $thermalPhotoFields,
		) as $title => $photoFields) {
			if (empty($photoFields)) {
				continue;
			}
			$this->renderSectionTitle($pdf, $title, 3);
			foreach ($photoFields as $photoField) {
				$this->renderFieldFiles($pdf, $photoField, $dataset, $outputlangs);
			}
		}
	}

	/**
	 * Render files linked to a field.
	 *
	 * @param	TCPDF|TCPDI	$pdf			PDF handler
	 * @param	PowerPlantPVReportField	$field	Field
	 * @param	array<string,mixed>	$dataset	Dataset
	 * @param	Translate	$outputlangs	Output language
	 * @return	void
	 */
	protected function renderFieldFiles(&$pdf, $field, $dataset, $outputlangs)
	{
		$files = isset($field->files) && is_array($field->files) ? $field->files : array();
		$title = PowerPlantPVReportPdfDataset::localizedProperty($field, 'field_label', $outputlangs);
		if ($title !== '') {
			$this->renderSectionTitle($pdf, $title, 3);
		}
		if (empty($files)) {
			$this->renderEmptyBlock($pdf, $outputlangs->transnoentities('NoRecordFound'));
			return;
		}

		$nonImages = array();
		foreach ($files as $file) {
			if (!is_object($file)) {
				continue;
			}
			$path = PowerPlantPVReportPdfDataset::resolveReportFilePath($dataset['report'], $file);
			if (PowerPlantPVReportPdfDataset::isImageFile($file, $path)) {
				if ($path !== '') {
					$this->renderImage($pdf, $path, (string) $file->filename, $outputlangs);
				} else {
					$this->renderEmptyBlock($pdf, $outputlangs->transnoentities('PowerPlantPVReportImageMissing').' : '.(string) $file->filename);
				}
			} else {
				$nonImages[] = (string) $file->filename;
			}
		}
		if (!empty($nonImages)) {
			$rows = array();
			foreach ($nonImages as $filename) {
				$rows[] = array($outputlangs->transnoentities('File'), $filename);
			}
			$this->renderKeyValueTable($pdf, $rows, '');
		}
	}

	/**
	 * Render one signature field.
	 *
	 * @param	TCPDF|TCPDI	$pdf			PDF handler
	 * @param	PowerPlantPVReportField	$field	Field
	 * @param	array<string,mixed>	$dataset	Dataset
	 * @param	Translate	$outputlangs	Output language
	 * @return	void
	 */
	protected function renderSignatureField(&$pdf, $field, $dataset, $outputlangs)
	{
		$label = PowerPlantPVReportPdfDataset::localizedProperty($field, 'field_label', $outputlangs);
		$value = PowerPlantPVReportPdfDataset::formatFieldValue($field, $outputlangs);
		$rows = array(array($label, $value));
		$this->renderKeyValueTable($pdf, $this->filterRows($rows), '');

		$files = isset($field->files) && is_array($field->files) ? $field->files : array();
		foreach ($files as $file) {
			if (!is_object($file)) {
				continue;
			}
			$path = PowerPlantPVReportPdfDataset::resolveReportFilePath($dataset['report'], $file);
			if ($path !== '' && PowerPlantPVReportPdfDataset::isImageFile($file, $path)) {
				$this->renderImage($pdf, $path, (string) $file->filename, $outputlangs, 55);
				return;
			}
		}
	}

	/**
	 * Render native internal intervention contacts when no technician signature field exists.
	 *
	 * @param	TCPDF|TCPDI		$pdf			PDF handler
	 * @param	array<string,mixed>	$dataset	Dataset
	 * @param	Translate		$outputlangs	Output language
	 * @return	void
	 */
	protected function renderTechnicianFallback(&$pdf, $dataset, $outputlangs)
	{
		if ($this->datasetHasTechnicianField($dataset) || empty($dataset['internal_contacts']) || !is_array($dataset['internal_contacts'])) {
			return;
		}

		$rows = array();
		foreach ($dataset['internal_contacts'] as $contact) {
			if (!is_array($contact) || empty($contact['name'])) {
				continue;
			}
			$value = (string) $contact['name'];
			if (!empty($contact['function'])) {
				$value .= ' - '.(string) $contact['function'];
			}
			$rows[] = array($outputlangs->transnoentities('PowerPlantPVReportSignatory'), $value);
		}
		if (empty($rows)) {
			return;
		}

		$this->renderSectionTitle($pdf, $outputlangs->transnoentities('PowerPlantPVReportTechnicianSignature'), 2);
		$this->renderKeyValueTable($pdf, $rows, '');
	}

	/**
	 * Return true if the dataset already contains a technician signature field.
	 *
	 * @param	array<string,mixed>	$dataset	Dataset
	 * @return	bool							True if a technician field exists
	 */
	protected function datasetHasTechnicianField($dataset)
	{
		$sections = array();
		if (!empty($dataset['general_sections']) && is_array($dataset['general_sections'])) {
			$sections = array_merge($sections, $dataset['general_sections']);
		}
		if (!empty($dataset['sections_by_powerplant']) && is_array($dataset['sections_by_powerplant'])) {
			foreach ($dataset['sections_by_powerplant'] as $powerplantSections) {
				if (is_array($powerplantSections)) {
					$sections = array_merge($sections, $powerplantSections);
				}
			}
		}
		foreach ($sections as $sectionRow) {
			if (!is_array($sectionRow) || empty($sectionRow['fields']) || !is_array($sectionRow['fields'])) {
				continue;
			}
			foreach ($sectionRow['fields'] as $field) {
				if (!is_object($field)) {
					continue;
				}
				$code = (string) $field->field_code;
				if (strpos($code, 'TECHNICIAN_') === 0 || strpos($code, 'TECH_') === 0) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Render equipment list.
	 *
	 * @param	TCPDF|TCPDI	$pdf		PDF handler
	 * @param	array<int,PowerPlantPVReportEquipment>	$equipment	Equipment rows
	 * @param	Translate	$outputlangs	Output language
	 * @return	void
	 */
	protected function renderEquipmentTable(&$pdf, $equipment, $outputlangs)
	{
		if (empty($equipment)) {
			return;
		}
		$html = '<table border="1" cellpadding="3" cellspacing="0" width="100%">';
		$html .= '<tr style="background-color:#f0f3f5;font-weight:bold;">';
		foreach (array('PowerPlantPVEquipmentType', 'PowerPlantPVBrand', 'PowerPlantPVModel', 'Ref', 'SerialNumber', 'PowerPlantPVEquipmentPosition') as $key) {
			$html .= '<td>'.dol_escape_htmltag($outputlangs->transnoentities($key)).'</td>';
		}
		$html .= '</tr>';
		foreach ($equipment as $equipmentRow) {
			if (!is_object($equipmentRow)) {
				continue;
			}
			$html .= '<tr>';
			$html .= '<td>'.dol_escape_htmltag((string) $equipmentRow->equipment_type).'</td>';
			$html .= '<td>'.dol_escape_htmltag((string) $equipmentRow->equipment_brand).'</td>';
			$html .= '<td>'.dol_escape_htmltag((string) $equipmentRow->equipment_model).'</td>';
			$html .= '<td>'.dol_escape_htmltag(trim((string) $equipmentRow->equipment_ref.' '.(string) $equipmentRow->product_ref)).'</td>';
			$html .= '<td>'.dol_escape_htmltag((string) $equipmentRow->serial_number).'</td>';
			$html .= '<td>'.dol_escape_htmltag((string) $equipmentRow->equipment_position).'</td>';
			$html .= '</tr>';
		}
		$html .= '</table>';
		$this->writeHtml($pdf, $html);
		$pdf->Ln(2);
	}

	/**
	 * Render equipment summary for an equipment section.
	 *
	 * @param	TCPDF|TCPDI	$pdf			PDF handler
	 * @param	PowerPlantPVReportEquipment	$equipment	Equipment
	 * @param	Translate	$outputlangs	Output language
	 * @return	void
	 */
	protected function renderEquipmentSummary(&$pdf, $equipment, $outputlangs)
	{
		$rows = array(
			array($outputlangs->transnoentities('PowerPlantPVEquipmentType'), (string) $equipment->equipment_type),
			array($outputlangs->transnoentities('PowerPlantPVBrand'), (string) $equipment->equipment_brand),
			array($outputlangs->transnoentities('PowerPlantPVModel'), (string) $equipment->equipment_model),
			array($outputlangs->transnoentities('Ref'), trim((string) $equipment->equipment_ref.' '.(string) $equipment->product_ref)),
			array($outputlangs->transnoentities('SerialNumber'), (string) $equipment->serial_number),
			array($outputlangs->transnoentities('PowerPlantPVEquipmentPosition'), (string) $equipment->equipment_position),
		);
		$this->renderKeyValueTable($pdf, $this->filterRows($rows), '');
	}

	/**
	 * Render source service list.
	 *
	 * @param	TCPDF|TCPDI	$pdf			PDF handler
	 * @param	array<int,PowerPlantPVReportSourceService>	$sourceServices	Source services
	 * @param	Translate	$outputlangs	Output language
	 * @return	void
	 */
	protected function renderSourceServices(&$pdf, $sourceServices, $outputlangs)
	{
		$rows = array();
		foreach ($sourceServices as $sourceService) {
			if (!is_object($sourceService)) {
				continue;
			}
			$service = PowerPlantPVReportPdfDataset::localizedProperty($sourceService, 'maintenance_service_label', $outputlangs);
			if ($service === '') {
				$service = trim((string) $sourceService->product_ref.' '.(string) $sourceService->product_label);
			}
			$rows[] = array((string) $sourceService->contract_ref, $service);
		}
		if (!empty($rows)) {
			$this->renderKeyValueTable($pdf, $rows, $outputlangs->transnoentities('PowerPlantPVReportSourceServices'));
		}
	}

	/**
	 * Render a section title.
	 *
	 * @param	TCPDF|TCPDI	$pdf	PDF handler
	 * @param	string		$title	Title
	 * @param	int			$level	Title level
	 * @return	void
	 */
	protected function renderSectionTitle(&$pdf, $title, $level)
	{
		$title = trim((string) $title);
		if ($title === '') {
			return;
		}
		$this->ensureSpace($pdf, 10);
		$size = $level === 1 ? $this->defaultFontSize + 3 : ($level === 2 ? $this->defaultFontSize + 1 : $this->defaultFontSize);
		$pdf->SetTextColor($level === 1 ? 0 : 45, $level === 1 ? 0 : 45, $level === 1 ? 80 : 45);
		$pdf->SetFont('', 'B', $size);
		$pdf->MultiCell(0, 6, $this->currentOutputLangs->convToOutputCharset($title), 0, 'L');
		$pdf->SetFont('', '', $this->defaultFontSize);
		$pdf->SetTextColor(0, 0, 0);
	}

	/**
	 * Render a key/value table.
	 *
	 * @param	TCPDF|TCPDI	$pdf	PDF handler
	 * @param	array<int,array{0:string,1:string}>	$rows Rows
	 * @param	string		$title	Optional title
	 * @return	void
	 */
	protected function renderKeyValueTable(&$pdf, $rows, $title = '')
	{
		if (empty($rows)) {
			return;
		}
		if ($title !== '') {
			$this->renderSectionTitle($pdf, $title, 3);
		}
		$html = '<table border="1" cellpadding="3" cellspacing="0" width="100%">';
		foreach ($rows as $row) {
			$html .= '<tr>';
			$html .= '<td width="32%" style="background-color:#f0f3f5;font-weight:bold;">'.dol_escape_htmltag((string) $row[0]).'</td>';
			$html .= '<td width="68%">'.dol_htmlentitiesbr((string) $row[1]).'</td>';
			$html .= '</tr>';
		}
		$html .= '</table>';
		$this->writeHtml($pdf, $html);
		$pdf->Ln(2);
	}

	/**
	 * Render an empty data block.
	 *
	 * @param	TCPDF|TCPDI	$pdf	PDF handler
	 * @param	string		$text	Text
	 * @return	void
	 */
	protected function renderEmptyBlock(&$pdf, $text)
	{
		$html = '<table border="1" cellpadding="4" cellspacing="0" width="100%"><tr><td style="color:#777777;">'.dol_escape_htmltag((string) $text).'</td></tr></table>';
		$this->writeHtml($pdf, $html);
		$pdf->Ln(2);
	}

	/**
	 * Render an image.
	 *
	 * @param	TCPDF|TCPDI	$pdf			PDF handler
	 * @param	string		$path			Image path
	 * @param	string		$caption		Caption
	 * @param	Translate	$outputlangs	Output language
	 * @param	float		$maxWidth		Max width
	 * @return	void
	 */
	protected function renderImage(&$pdf, $path, $caption, $outputlangs, $maxWidth = 80)
	{
		$size = @getimagesize($path);
		if (empty($size[0]) || empty($size[1])) {
			$this->renderEmptyBlock($pdf, $outputlangs->transnoentities('PowerPlantPVReportImageMissing').' : '.$caption);
			return;
		}

		$maxHeight = 70;
		$ratio = min($maxWidth / $size[0], $maxHeight / $size[1]);
		$width = $size[0] * $ratio;
		$height = $size[1] * $ratio;
		$this->ensureSpace($pdf, $height + 12);
		$x = $this->marge_gauche;
		$y = $pdf->GetY();
		$pdf->Image($path, $x, $y, $width, $height);
		$pdf->SetY($y + $height + 1);
		if ($caption !== '') {
			$pdf->SetFont('', '', max(7, $this->defaultFontSize - 1));
			$pdf->MultiCell($width, 4, $outputlangs->convToOutputCharset($caption), 0, 'C');
			$pdf->SetFont('', '', $this->defaultFontSize);
		}
		$pdf->Ln(2);
	}

	/**
	 * Write HTML with footer-aware page break reserve.
	 *
	 * @param	TCPDF|TCPDI	$pdf	PDF handler
	 * @param	string		$html	HTML
	 * @return	void
	 */
	protected function writeHtml(&$pdf, $html)
	{
		$this->ensureSpace($pdf, 12);
		$pdf->writeHTMLCell(0, 0, '', '', $this->currentOutputLangs->convToOutputCharset($html), 0, 1, false, true, '', true);
	}

	/**
	 * Ensure enough vertical space remains.
	 *
	 * @param	TCPDF|TCPDI	$pdf		PDF handler
	 * @param	float		$needed		Needed height
	 * @return	void
	 */
	protected function ensureSpace(&$pdf, $needed)
	{
		if ($pdf->GetY() + $needed <= $this->getContentBottomY()) {
			return;
		}
		$pdf->AddPage();
		$this->reserveFooterSpace($pdf);
	}

	/**
	 * Reserve bottom footer area.
	 *
	 * @param	TCPDF|TCPDI	$pdf	PDF handler
	 * @return	void
	 */
	protected function reserveFooterSpace(&$pdf)
	{
		if (method_exists($pdf, 'setPageOrientation')) {
			$pdf->setPageOrientation('', true, $this->heightforfooter);
		}
	}

	/**
	 * Render native footers page by page.
	 *
	 * @param	TCPDF|TCPDI		$pdf			PDF handler
	 * @param	CommonObject	$object			Intervention
	 * @param	Translate		$outputlangs	Output language
	 * @return	void
	 */
	protected function renderFooters(&$pdf, $object, $outputlangs)
	{
		$pageCount = method_exists($pdf, 'getNumPages') ? (int) $pdf->getNumPages() : (int) $pdf->getPage();
		for ($page = 1; $page <= $pageCount; $page++) {
			$pdf->setPage($page);
			if (method_exists($pdf, 'setPageOrientation')) {
				$pdf->setPageOrientation('', true, 0);
			}
			$this->_pagefoot($pdf, $object, $outputlangs, $page < $pageCount ? 1 : 0);
		}
		$pdf->setPage($pageCount);
	}

	/**
	 * Render native Dolibarr page footer.
	 *
	 * @param	TCPDF|TCPDI		$pdf			PDF handler
	 * @param	CommonObject	$object			Intervention
	 * @param	Translate		$outputlangs	Output language
	 * @param	int<0,1>		$hidefreetext	Hide free text
	 * @return	int								Footer height
	 */
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		$showdetails = !getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS') ? 0 : getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS');

		return pdf_pagefoot($pdf, $outputlangs, 'POWERPLANTPV_REPORT_PDF_LEGAL_NOTICE', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext, $this->page_largeur, $this->watermark);
	}

	/**
	 * Compute footer reserved height.
	 *
	 * @return	int	Footer height
	 */
	protected function computeFooterHeight()
	{
		$legalNotice = dol_string_nohtmltag(getDolGlobalString('POWERPLANTPV_REPORT_PDF_LEGAL_NOTICE'));
		$legalHeight = $legalNotice === '' ? 0 : min(26, max(6, (int) ceil(dol_strlen($legalNotice) / 95) * 4));
		$detailsHeight = getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS') ? 22 : 12;

		return max(28, $this->marge_basse + $detailsHeight + $legalHeight + 4);
	}

	/**
	 * Return content bottom Y coordinate.
	 *
	 * @return	float	Bottom Y
	 */
	protected function getContentBottomY()
	{
		return $this->page_hauteur - $this->heightforfooter;
	}

	/**
	 * Update intervention last main document if supported.
	 *
	 * @param	CommonObject	$object	Intervention
	 * @param	string			$file	File path
	 * @return	void
	 */
	protected function updateLastMainDoc($object, $file)
	{
		if (!property_exists($object, 'last_main_doc')) {
			return;
		}

		$object->last_main_doc = basename($file);
		if (method_exists($object, 'update')) {
			global $user;
			$object->update($user, 1);
		}
	}

	/**
	 * Filter rows with empty values.
	 *
	 * @param	array<int,array{0:string,1:string}>	$rows	Rows
	 * @return	array<int,array{0:string,1:string}>			Filtered rows
	 */
	protected function filterRows($rows)
	{
		$filtered = array();
		foreach ($rows as $row) {
			if (isset($row[1]) && trim((string) $row[1]) !== '') {
				$filtered[] = $row;
			}
		}

		return $filtered;
	}

	/**
	 * Filter PDF-visible fields.
	 *
	 * @param	array<int,PowerPlantPVReportField>	$fields	Fields
	 * @return	array<int,PowerPlantPVReportField>			Visible fields
	 */
	protected function filterVisibleFields($fields)
	{
		$visible = array();
		foreach ($fields as $field) {
			if (is_object($field) && !empty($field->visible_pdf)) {
				$visible[] = $field;
			}
		}

		return $visible;
	}

	/**
	 * Return a power plant display label.
	 *
	 * @param	PowerPlantPVReportPowerPlant	$powerplant	Power plant snapshot
	 * @return	string										Label
	 */
	protected function powerplantLabel($powerplant)
	{
		$label = trim((string) $powerplant->powerplant_ref.' - '.(string) $powerplant->powerplant_label);

		return $label !== '-' ? $label : (string) $powerplant->fk_powerplant;
	}

	/**
	 * Join power plant labels.
	 *
	 * @param	array<int,PowerPlantPVReportPowerPlant>	$powerplants	Power plants
	 * @return	string													Labels
	 */
	protected function joinPowerplantLabels($powerplants)
	{
		$labels = array();
		if (is_array($powerplants)) {
			foreach ($powerplants as $powerplant) {
				if (is_object($powerplant)) {
					$labels[] = $this->powerplantLabel($powerplant);
				}
			}
		}

		return implode(', ', $labels);
	}

	/**
	 * Join contract refs.
	 *
	 * @param	array<int,array<string,mixed>>	$contracts	Contracts
	 * @return	string										Refs
	 */
	protected function joinContractRefs($contracts)
	{
		$refs = array();
		if (is_array($contracts)) {
			foreach ($contracts as $contract) {
				if (!empty($contract['ref'])) {
					$refs[] = (string) $contract['ref'];
				}
			}
		}

		return implode(', ', array_unique($refs));
	}

	/**
	 * Filter source services by report power plant id.
	 *
	 * @param	array<int,PowerPlantPVReportSourceService>	$sourceServices	Source services
	 * @param	int											$powerplantId	Report power plant id
	 * @return	array<int,PowerPlantPVReportSourceService>					Filtered rows
	 */
	protected function filterSourceServicesByPowerplant($sourceServices, $powerplantId)
	{
		$filtered = array();
		if (!is_array($sourceServices)) {
			return $filtered;
		}
		foreach ($sourceServices as $sourceService) {
			if (is_object($sourceService) && (int) $sourceService->fk_report_powerplant === (int) $powerplantId) {
				$filtered[] = $sourceService;
			}
		}

		return $filtered;
	}

	/**
	 * Format optional number with a unit.
	 *
	 * @param	mixed	$value	Value
	 * @param	string	$unit	Unit
	 * @return	string			Formatted value
	 */
	protected function formatOptionalNumber($value, $unit)
	{
		if ($value === null || (string) $value === '') {
			return '';
		}

		return price($value).' '.$unit;
	}

	/**
	 * Resolve a production reading date for PDF display.
	 *
	 * @param	array<string,mixed>	$dataset	Dataset
	 * @param	Translate			$outputlangs	Output language
	 * @return	string							Formatted date
	 */
	protected function resolveReadingDate($dataset, $outputlangs)
	{
		$date = '';
		if (!empty($dataset['intervention']) && is_object($dataset['intervention'])) {
			$date = $this->getInterventionDate($dataset['intervention']);
		}
		if (empty($date) && !empty($dataset['report']) && is_object($dataset['report']) && !empty($dataset['report']->date_creation)) {
			$date = PowerPlantPVReportPdfDataset::normalizeDateValue($dataset['report']->date_creation);
		}

		return empty($date) ? '' : dol_print_date($date, 'day', 'tzuser', $outputlangs);
	}

	/**
	 * Return intervention date.
	 *
	 * @param	CommonObject	$object	Intervention
	 * @return	int|string				Date value
	 */
	protected function getInterventionDate($object)
	{
		foreach (array('datei', 'date_intervention', 'date_valid', 'date_creation', 'datec') as $property) {
			if (!empty($object->{$property})) {
				return PowerPlantPVReportPdfDataset::normalizeDateValue($object->{$property});
			}
		}

		return '';
	}
}
