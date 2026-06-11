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
	protected $heightforfooter = 0;
	protected $footerContentClearance = 4;
	public $watermark = '';
	protected $currentObject;
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
		$this->currentObject = $object;
		$this->currentOutputLangs = $outputlangs;

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
		$this->heightforfooter = $this->marge_basse + (getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS') ? 22 : 12) + $this->footerContentClearance;
		if (method_exists($pdf, 'setAutoPageBreak')) {
			$pdf->setAutoPageBreak(true, 0);
		} else {
			$pdf->SetAutoPageBreak(true, 0);
		}
		$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
		$pdf->SetCreator('Dolibarr '.DOL_VERSION);
		$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
		if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
			$pdf->SetCompression(false);
		}

		$pdf->AddPage();
		$this->reserveFooterSpace($pdf);
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

		$outputlangs->loadLangs(array('main', 'companies'));

		$ltrdirection = 'L';
		if ($outputlangs->trans('DIRECTION') == 'rtl') {
			$ltrdirection = 'R';
		}

		$defaultFontSize = pdf_getPDFFontSize($outputlangs);
		$posy = $this->marge_haute;
		$logoBottom = $posy;

		if (!getDolGlobalInt('PDF_DISABLE_MYCOMPANY_LOGO')) {
			if (!empty($this->emetteur->logo)) {
				$logodir = $conf->mycompany->dir_output;
				if (!empty(getMultidirOutput($object, 'mycompany'))) {
					$logodir = getMultidirOutput($object, 'mycompany');
				}
				if (!getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO') && !empty($this->emetteur->logo_small)) {
					$logo = $logodir.'/logos/thumbs/'.$this->emetteur->logo_small;
				} else {
					$logo = $logodir.'/logos/'.$this->emetteur->logo;
				}
				if (is_readable($logo)) {
					$height = pdf_getHeightForLogo($logo);
					$pdf->Image($logo, $this->marge_gauche, $posy, 0, $height);
					$logoBottom = $posy + $height;
				} else {
					$pdf->SetTextColor(200, 0, 0);
					$pdf->SetFont('', 'B', $defaultFontSize - 2);
					$pdf->SetXY($this->marge_gauche, $posy);
					$pdf->MultiCell(80, 3, $outputlangs->transnoentities('ErrorLogoFileNotFound', $logo), 0, 'L');
					$pdf->MultiCell(80, 3, $outputlangs->transnoentities('ErrorGoToGlobalSetup'), 0, 'L');
					$logoBottom = $pdf->GetY();
				}
			} elseif (!empty($this->emetteur->name)) {
				$pdf->SetTextColor(0, 0, 60);
				$pdf->SetFont('', 'B', $defaultFontSize);
				$pdf->SetXY($this->marge_gauche, $posy);
				$pdf->MultiCell(80, 4, $outputlangs->convToOutputCharset($this->emetteur->name), 0, 'L');
				$logoBottom = $pdf->GetY();
			}
		}

		$derivedData = powerplantpvAttestationGetDerivedData($object, $outputlangs);
		$title = $outputlangs->transnoentities('AttestationDocumentTitle', $outputlangs->transnoentities($this->titleKey));

		$pdf->SetXY($this->marge_gauche + 45, $posy);
		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont('', 'B', $defaultFontSize + 5);
		$pdf->MultiCell(0, 7, $outputlangs->convToOutputCharset($title), 0, 'R');
		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->SetX($this->marge_gauche + 45);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($object->ref), 0, 'R');
		if (!empty($object->date_attestation)) {
			$pdf->SetX($this->marge_gauche + 45);
			$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationDate').' : '.dol_print_date($object->date_attestation, 'day', 'tzuser', $outputlangs)), 0, 'R');
		}
		if (!empty($derivedData['project_name'])) {
			$pdf->SetX($this->marge_gauche + 45);
			$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('PowerPlant').' : '.$derivedData['project_name']), 0, 'R');
		}
		$titleBottom = $pdf->GetY();

		$thirdparty = null;
		if (!empty($object->fk_soc)) {
			require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
			$thirdparty = new Societe($this->db);
			if ($thirdparty->fetch((int) $object->fk_soc) <= 0) {
				$thirdparty = null;
			}
		}
		$caracEmetteur = pdf_build_address($outputlangs, $this->emetteur, $thirdparty, '', 0, 'source', $object);

		$senderY = getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 40 : 42;
		$senderY = max($senderY, $logoBottom + 5);
		$senderY = max($senderY, $titleBottom + 5);
		$senderX = $this->marge_gauche;
		$senderHeight = getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 38 : 40;
		$senderWidth = getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 92 : 82;
		if (getDolGlobalInt('MAIN_INVERT_SENDER_RECIPIENT')) {
			$senderX = $this->page_largeur - $this->marge_droite - $senderWidth;
		}

		if (!getDolGlobalString('MAIN_PDF_NO_SENDER_FRAME')) {
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetFont('', '', $defaultFontSize - 2);
			$pdf->SetXY($senderX, $senderY - 5);
			$pdf->MultiCell($senderWidth, 5, $outputlangs->transnoentities('AttestationIssuer').':', 0, $ltrdirection);
			$pdf->SetXY($senderX, $senderY);
			$pdf->SetFillColor(230, 230, 230);
			$pdf->MultiCell($senderWidth, $senderHeight, '', 0, 'R', true);
			$pdf->SetTextColor(0, 0, 60);
		}

		$currentSenderY = $senderY;
		if (!getDolGlobalString('MAIN_PDF_HIDE_SENDER_NAME')) {
			$pdf->SetXY($senderX + 2, $currentSenderY + 3);
			$pdf->SetFont('', 'B', $defaultFontSize);
			$pdf->MultiCell($senderWidth - 2, 4, $outputlangs->convToOutputCharset($this->emetteur->name), 0, $ltrdirection);
			$currentSenderY = $pdf->GetY();
		}

		$pdf->SetXY($senderX + 2, $currentSenderY);
		$pdf->SetFont('', '', $defaultFontSize - 1);
		$pdf->MultiCell($senderWidth - 2, 4, $caracEmetteur, 0, $ltrdirection);
		$pdf->SetTextColor(0, 0, 0);

		$pdf->SetY(max($pdf->GetY(), $senderY + $senderHeight) + 5);
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
		$this->renderKeyValue($pdf, $outputlangs, 'PowerPlant', $derivedData['project_name']);
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
		if ($object->max_export_power_kw !== null && $object->max_export_power_kw !== '') {
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
		$this->ensureSpace($pdf, 22);
		$pdf->SetFont('', 'B', $defaultFontSize + 1);
		$pdf->MultiCell(0, 6, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationEquipment')), 0, 'L');
		$pdf->SetFont('', 'B', $defaultFontSize - 1);
		$widths = array(42, 76, 48, 24);
		$headers = array('AttestationEquipmentCategory', 'Designation', 'PowerPlantSerialNumber', 'AttestationBridage');
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
			$this->ensureSpace($pdf, 7);
			$pdf->Cell($widths[0], 6, $outputlangs->convToOutputCharset(dol_trunc(powerplantpvAttestationEquipmentCategoryLabel($line, $outputlangs), 28)), 1, 0, 'L');
			$pdf->Cell($widths[1], 6, $outputlangs->convToOutputCharset(dol_trunc((string) $line->designation, 45)), 1, 0, 'L');
			$pdf->Cell($widths[2], 6, $outputlangs->convToOutputCharset(dol_trunc((string) $line->serial_number, 34)), 1, 0, 'L');
			$pdf->Cell($widths[3], 6, $outputlangs->convToOutputCharset(!empty($line->bridage_enabled) ? $outputlangs->transnoentities('Yes') : $outputlangs->transnoentities('No')), 1, 1, 'L');
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
		$this->ensureSpace($pdf, 45);
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
	 * @return	int								Footer height
	 */
	protected function renderFooter($pdf, $object, $outputlangs)
	{
		if (method_exists($pdf, 'setPageOrientation')) {
			$pdf->setPageOrientation('', true, 0);
		}

		return $this->_pagefoot($pdf, $object, $outputlangs);
	}

	/**
	 * Render native Dolibarr footer.
	 *
	 * @param	TCPDF|TCPDI				$pdf			PDF
	 * @param	PowerPlantPVAttestation	$object			Attestation
	 * @param	Translate				$outputlangs	Output lang
	 * @param	int<0,1>				$hidefreetext	Hide free text
	 * @return	int										Footer height
	 */
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		$showdetails = !getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS') ? 0 : getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS');

		return pdf_pagefoot($pdf, $outputlangs, 'POWERPLANTPV_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext, $this->page_largeur, $this->watermark);
	}

	/**
	 * Reserve the bottom area used by the native footer.
	 *
	 * @param	TCPDF|TCPDI	$pdf	PDF
	 * @return	void
	 */
	protected function reserveFooterSpace($pdf)
	{
		if (method_exists($pdf, 'setPageOrientation')) {
			$pdf->setPageOrientation('', true, $this->heightforfooter);
		}
	}

	/**
	 * Return the maximum Y available for content.
	 *
	 * @return	int		Bottom Y
	 */
	protected function getContentBottomY()
	{
		return $this->page_hauteur - $this->heightforfooter;
	}

	/**
	 * Add a page before a block that would collide with the reserved footer area.
	 *
	 * @param	TCPDF|TCPDI	$pdf		PDF
	 * @param	float		$height		Required block height
	 * @return	void
	 */
	protected function ensureSpace($pdf, $height)
	{
		if (($pdf->GetY() + $height) <= $this->getContentBottomY()) {
			return;
		}

		if (is_object($this->currentObject) && is_object($this->currentOutputLangs)) {
			$this->renderFooter($pdf, $this->currentObject, $this->currentOutputLangs);
		}
		$pdf->AddPage();
		$this->reserveFooterSpace($pdf);
		$pdf->SetXY($this->marge_gauche, $this->marge_haute);
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
