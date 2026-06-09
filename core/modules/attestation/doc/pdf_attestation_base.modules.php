<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		core/modules/attestation/doc/pdf_attestation_base.modules.php
 * \ingroup		powerplantpv
 * \brief		Shared PDF helpers for attestation models.
 */

dol_include_once('/powerplantpv/core/modules/attestation/modules_attestation.php');
dol_include_once('/powerplantpv/lib/powerplantpv_attestation.lib.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';

/**
 * Shared implementation for attestation PDF models.
 */
abstract class pdf_attestation_base extends ModelePDFAttestation
{
	public $db;
	public $entity;
	public $name;
	public $description;
	public $update_main_doc_field = 1;
	public $type = 'pdf';
	public $phpmin = array(8, 0);
	public $version = 'dolibarr';
	public $emetteur;
	protected $titleKey = 'Attestation';
	protected $validationWarningKey = '';

	/**
	 * Constructor.
	 *
	 * @param	DoliDB	$db	Database handler
	 */
	public function __construct($db)
	{
		global $langs, $mysoc;

		$this->db = $db;
		$langs->loadLangs(array('main', 'companies', 'powerplantpv@powerplantpv'));
		$this->description = $langs->trans('PowerPlantPVAttestationPdfModel');

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
	 * Write file.
	 *
	 * @param	PowerPlantPVAttestation	$object				Source object
	 * @param	Translate				$outputlangs		Output lang
	 * @param	string					$srctemplatepath	Source template
	 * @param	int<0,1>				$hidedetails		Hide details
	 * @param	int<0,1>				$hidedesc			Hide desc
	 * @param	int<0,1>				$hideref			Hide ref
	 * @return	int<-1,1>									1 if OK
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $conf, $langs, $user;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		if (getDolGlobalInt('MAIN_USE_FPDF')) {
			$outputlangs->charset_output = 'ISO-8859-1';
		}
		$outputlangs->loadLangs(array('main', 'companies', 'powerplantpv@powerplantpv'));

		if (empty($object->ref)) {
			$this->error = $outputlangs->transnoentities('ErrorUnknown');
			return -1;
		}
		if (method_exists($object, 'fetchEquipmentLines')) {
			$object->fetchEquipmentLines();
		}

		$dir = powerplantpvAttestationGetDocumentUploadDir($object);
		if (!file_exists($dir) && dol_mkdir($dir) < 0) {
			$this->error = $langs->transnoentities('ErrorCanNotCreateDir', $dir);
			return -1;
		}

		$file = $dir.'/'.dol_sanitizeFileName($object->ref).'.pdf';
		$pdf = pdf_getInstance($this->format);
		'@phan-var-force TCPDI|TCPDF $pdf';
		$defaultFontSize = pdf_getPDFFontSize($outputlangs);
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', $defaultFontSize);
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$pdf->SetAutoPageBreak(true, $this->marge_basse + 10);
		$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
		$pdf->SetCreator('Dolibarr '.DOL_VERSION);
		$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
		if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
			$pdf->SetCompression(false);
		}

		$pdf->AddPage();
		$this->renderHeader($pdf, $object, $outputlangs);
		$this->renderBody($pdf, $object, $outputlangs, $defaultFontSize);
		$this->renderFooter($pdf, $object, $outputlangs);

		$pdf->Output($file, 'F');
		if (!empty($this->update_main_doc_field)) {
			$this->updateLastMainDoc($object, $file);
		}

		$this->result = array('fullpath' => $file);

		return 1;
	}

	/**
	 * Render header.
	 *
	 * @param	TCPDF|TCPDI				$pdf			PDF
	 * @param	PowerPlantPVAttestation	$object			Attestation
	 * @param	Translate				$outputlangs	Output lang
	 * @return	void
	 */
	protected function renderHeader($pdf, $object, $outputlangs)
	{
		global $conf;

		$y = $this->marge_haute;
		$logo = $conf->mycompany->dir_output.'/logos/'.$this->emetteur->logo;
		if (!empty($this->emetteur->logo) && file_exists($logo)) {
			$pdf->Image($logo, $this->marge_gauche, $y, 35);
		}

		$pdf->SetXY($this->marge_gauche + 45, $y);
		$pdf->SetFont('', 'B', 14);
		$pdf->MultiCell(0, 7, $outputlangs->convToOutputCharset($outputlangs->transnoentities($this->titleKey)), 0, 'R');
		$pdf->SetFont('', '', 9);
		$pdf->SetX($this->marge_gauche + 45);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($object->ref), 0, 'R');
		$pdf->Ln(12);
	}

	/**
	 * Render body.
	 *
	 * @param	TCPDF|TCPDI				$pdf				PDF
	 * @param	PowerPlantPVAttestation	$object				Attestation
	 * @param	Translate				$outputlangs		Output lang
	 * @param	int						$defaultFontSize	Default font size
	 * @return	void
	 */
	protected function renderBody($pdf, $object, $outputlangs, $defaultFontSize)
	{
		$derivedData = powerplantpvAttestationGetDerivedData($object, $outputlangs);

		$pdf->SetFont('', 'B', $defaultFontSize + 1);
		$pdf->MultiCell(0, 6, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationGeneralInformation')), 0, 'L');
		$pdf->SetFont('', '', $defaultFontSize);
		$this->renderKeyValue($pdf, $outputlangs, 'AttestationType', $this->translatedType($object, $outputlangs));
		$this->renderKeyValue($pdf, $outputlangs, 'ProjectName', $derivedData['project_name']);
		$this->renderKeyValue($pdf, $outputlangs, 'Address', $derivedData['site_full_address']);
		$this->renderKeyValue($pdf, $outputlangs, 'AttestationDate', !empty($object->date_attestation) ? dol_print_date($object->date_attestation, 'day', 'tzuser', $outputlangs) : '');
		$this->renderKeyValue($pdf, $outputlangs, 'AttestationPlace', $derivedData['place']);
		$this->renderKeyValue($pdf, $outputlangs, 'AttestationInstallerName', $derivedData['installer_name']);
		$this->renderKeyValue($pdf, $outputlangs, 'AttestationInstallerAddress', powerplantpvAttestationFormatDerivedAddress($derivedData, 'installer', 1));
		$this->renderKeyValue($pdf, $outputlangs, 'SIRET', $derivedData['installer_siret']);
		$this->renderKeyValue($pdf, $outputlangs, 'VATIntra', $derivedData['installer_vat']);

		$pdf->Ln(3);
		$pdf->SetFont('', 'B', $defaultFontSize + 1);
		$pdf->MultiCell(0, 6, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationSpecificData')), 0, 'L');
		$pdf->SetFont('', '', $defaultFontSize);
		if (!empty($object->max_export_power_kw)) {
			$this->renderKeyValue($pdf, $outputlangs, 'AttestationMaxExportPowerKw', price($object->max_export_power_kw).' kW');
		}
		if (!empty($object->max_frequency_hz)) {
			$this->renderKeyValue($pdf, $outputlangs, 'AttestationMaxFrequencyHz', price($object->max_frequency_hz).' Hz');
		}
		if (!empty($object->date_setting)) {
			$this->renderKeyValue($pdf, $outputlangs, 'AttestationSettingDate', dol_print_date($object->date_setting, 'day', 'tzuser', $outputlangs));
		}
		if (!empty($object->date_completion)) {
			$this->renderKeyValue($pdf, $outputlangs, 'AttestationCompletionDate', dol_print_date($object->date_completion, 'day', 'tzuser', $outputlangs));
		}
		if (!empty($object->bta_contract_number)) {
			$this->renderKeyValue($pdf, $outputlangs, 'AttestationBtaContractNumber', $object->bta_contract_number);
		}

		$this->renderLegalText($pdf, $object, $outputlangs);
		$this->renderEquipment($pdf, $object, $outputlangs, $defaultFontSize);
		$this->renderSignatureBlock($pdf, $object, $outputlangs);
	}

	/**
	 * Render legal text skeleton.
	 *
	 * @param	TCPDF|TCPDI				$pdf			PDF
	 * @param	PowerPlantPVAttestation	$object			Attestation
	 * @param	Translate				$outputlangs	Output lang
	 * @return	void
	 */
	protected function renderLegalText($pdf, $object, $outputlangs)
	{
		$pdf->Ln(3);
		$text = $outputlangs->transnoentities('AttestationPdfSkeletonText');
		if ($this->validationWarningKey !== '') {
			$text .= "\n".$outputlangs->transnoentities($this->validationWarningKey);
		}
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($text), 1, 'L');
	}

	/**
	 * Render equipment table.
	 *
	 * @param	TCPDF|TCPDI				$pdf				PDF
	 * @param	PowerPlantPVAttestation	$object				Attestation
	 * @param	Translate				$outputlangs		Output lang
	 * @param	int						$defaultFontSize	Default font size
	 * @return	void
	 */
	protected function renderEquipment($pdf, $object, $outputlangs, $defaultFontSize)
	{
		$pdf->Ln(5);
		$pdf->SetFont('', 'B', $defaultFontSize + 1);
		$pdf->MultiCell(0, 6, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationEquipment')), 0, 'L');
		$pdf->SetFont('', 'B', $defaultFontSize - 1);
		$widths = array(28, 62, 32, 38, 22);
		$headers = array('Type', 'Designation', 'Model', 'SerialNumber', 'AttestationBridage');
		foreach ($headers as $i => $key) {
			$pdf->Cell($widths[$i], 6, $outputlangs->convToOutputCharset($outputlangs->transnoentities($key)), 1, 0, 'L');
		}
		$pdf->Ln();
		$pdf->SetFont('', '', $defaultFontSize - 1);
		if (empty($object->lines)) {
			$pdf->Cell(array_sum($widths), 6, $outputlangs->convToOutputCharset($outputlangs->transnoentities('None')), 1, 1, 'L');
			return;
		}
		foreach ($object->lines as $line) {
			$pdf->Cell($widths[0], 6, $outputlangs->convToOutputCharset((string) $line->equipment_type), 1, 0, 'L');
			$pdf->Cell($widths[1], 6, $outputlangs->convToOutputCharset(dol_trunc((string) $line->designation, 45)), 1, 0, 'L');
			$pdf->Cell($widths[2], 6, $outputlangs->convToOutputCharset(dol_trunc((string) $line->model, 22)), 1, 0, 'L');
			$pdf->Cell($widths[3], 6, $outputlangs->convToOutputCharset(dol_trunc((string) $line->serial_number, 28)), 1, 0, 'L');
			$pdf->Cell($widths[4], 6, $outputlangs->convToOutputCharset(!empty($line->bridage_enabled) ? $outputlangs->transnoentities('Yes') : $outputlangs->transnoentities('No')), 1, 1, 'L');
		}
	}

	/**
	 * Render signature block.
	 *
	 * @param	TCPDF|TCPDI				$pdf			PDF
	 * @param	PowerPlantPVAttestation	$object			Attestation
	 * @param	Translate				$outputlangs	Output lang
	 * @return	void
	 */
	protected function renderSignatureBlock($pdf, $object, $outputlangs)
	{
		$derivedData = powerplantpvAttestationGetDerivedData($object, $outputlangs);

		$pdf->Ln(8);
		$pdf->SetFont('', '', 9);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationWriterSignature', $derivedData['writer_name'], $derivedData['writer_function'])), 0, 'R');

		$x = $this->page_largeur - $this->marge_droite - 70;
		$y = $pdf->GetY();
		$stamp = powerplantpvAttestationGetCompanyStampFile($object->entity);
		if (file_exists($stamp)) {
			$pdf->Image($stamp, $x, $y, 30);
		}
		if (!empty($object->signature_file)) {
			$signature = powerplantpvAttestationGetDocumentRootDir($object->entity).'/'.$object->signature_file;
			if (file_exists($signature)) {
				$pdf->Image($signature, $x + 35, $y, 35);
			}
		}
		if (!empty($object->date_signature)) {
			$pdf->SetY($y + 25);
			$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationSignedOn', dol_print_date($object->date_signature, 'dayhour', 'tzuser', $outputlangs))), 0, 'R');
		}
	}

	/**
	 * Render footer.
	 *
	 * @param	TCPDF|TCPDI				$pdf			PDF
	 * @param	PowerPlantPVAttestation	$object			Attestation
	 * @param	Translate				$outputlangs	Output lang
	 * @return	void
	 */
	protected function renderFooter($pdf, $object, $outputlangs)
	{
		$pdf->SetY(-15);
		$pdf->SetFont('', '', 7);
		$pdf->Cell(0, 5, $outputlangs->convToOutputCharset($this->emetteur->name.' - '.$object->ref), 0, 0, 'C');
	}

	/**
	 * Render key value.
	 *
	 * @param	TCPDF|TCPDI	$pdf			PDF
	 * @param	Translate	$outputlangs	Output lang
	 * @param	string		$key			Translation key
	 * @param	string		$value			Value
	 * @return	void
	 */
	protected function renderKeyValue($pdf, $outputlangs, $key, $value)
	{
		if ($value === null || $value === '') {
			return;
		}
		$pdf->SetFont('', 'B');
		$pdf->Cell(48, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities($key)), 0, 0, 'L');
		$pdf->SetFont('', '');
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset((string) $value), 0, 'L');
	}

	/**
	 * Return translated type.
	 *
	 * @param	PowerPlantPVAttestation	$object			Attestation
	 * @param	Translate				$outputlangs	Output lang
	 * @return	string									Label
	 */
	protected function translatedType($object, $outputlangs)
	{
		$type = PowerPlantPVAttestationTypes::getType($object->type_code);

		return !empty($type['label']) ? $outputlangs->transnoentities($type['label']) : (string) $object->type_code;
	}

	/**
	 * Update last main doc without invoking business update restrictions.
	 *
	 * @param	PowerPlantPVAttestation	$object	Attestation
	 * @param	string					$file	Full file path
	 * @return	void
	 */
	protected function updateLastMainDoc($object, $file)
	{
		$relative = powerplantpvAttestationGetDocumentRelativePath($object).'/'.basename($file);
		$sql = "UPDATE ".$this->db->prefix().$object->table_element;
		$sql .= " SET last_main_doc = '".$this->db->escape($relative)."'";
		$sql .= " WHERE rowid = ".((int) $object->id);
		$sql .= " AND entity = ".((int) $object->entity);
		$this->db->query($sql);
		$object->last_main_doc = $relative;
	}
}
