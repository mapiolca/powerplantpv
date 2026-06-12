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
	protected $nameKey = '';
	protected $descriptionKey = 'PowerPlantPVAttestationPdfModel';
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
	 * Set translated model metadata.
	 *
	 * @param	string	$nameKey		Translation key for model name
	 * @param	string	$descriptionKey	Translation key for model description
	 * @return	void
	 */
	protected function setModelMetadata($nameKey, $descriptionKey)
	{
		global $langs;

		$this->nameKey = $nameKey;
		$this->descriptionKey = $descriptionKey;
		$this->name = $langs->trans($nameKey);
		$this->description = $langs->trans($descriptionKey);
	}

	/**
	 * Return model information.
	 *
	 * @param	Translate	$langs	Lang output object
	 * @return	string				Description
	 */
	public function info($langs)
	{
		$langs->load('powerplantpv@powerplantpv');

		return $langs->trans($this->descriptionKey);
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
		$this->renderHeader($pdf, $object, $outputlangs, true);
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
	 * @param	bool					$showIssuer		Show issuer block
	 * @return	void
	 */
	protected function renderHeader($pdf, $object, $outputlangs, $showIssuer = true)
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
		$title = $this->getDocumentTitle($object, $outputlangs);

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
		$this->renderAdditionalHeaderLines($pdf, $object, $outputlangs);
		$titleBottom = $pdf->GetY();

		if (empty($showIssuer)) {
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetY(max($logoBottom, $titleBottom) + 5);
			return;
		}

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
	 * Return document title displayed in the PDF header.
	 *
	 * @param	PowerPlantPVAttestation	$object			Attestation
	 * @param	Translate				$outputlangs	Output lang
	 * @return	string									Title
	 */
	protected function getDocumentTitle($object, $outputlangs)
	{
		return $outputlangs->transnoentities('AttestationDocumentTitle', $outputlangs->transnoentities($this->titleKey));
	}

	/**
	 * Render document-model specific lines below the standard header references.
	 *
	 * @param	TCPDF|TCPDI				$pdf			PDF
	 * @param	PowerPlantPVAttestation	$object			Attestation
	 * @param	Translate				$outputlangs	Output lang
	 * @return	void
	 */
	protected function renderAdditionalHeaderLines($pdf, $object, $outputlangs)
	{
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
		$fontSize = $defaultFontSize - 1;
		$widths = array(38, 34, 70, 48);
		$headers = array('AttestationEquipmentCategory', 'Ref', 'Designation', 'PowerPlantSerialNumber');
		$headerValues = array();
		foreach ($headers as $key) {
			$headerValues[] = $outputlangs->transnoentities($key);
		}

		$bodyRows = array();
		if (empty($object->lines)) {
			$bodyRows[] = array(
				'widths' => array(array_sum($widths)),
				'values' => array($outputlangs->transnoentities('None')),
			);
		} else {
			foreach ($object->lines as $line) {
				$equipment = powerplantpvAttestationResolveEquipmentLine($line, $outputlangs);
				$bodyRows[] = array(
					'widths' => $widths,
					'values' => array(
						$this->valueOrNotProvided($equipment['category'], $outputlangs),
						$this->valueOrNotProvided($equipment['product_ref'], $outputlangs),
						$this->valueOrNotProvided($equipment['designation'], $outputlangs),
						$this->valueOrNotProvided($equipment['serial_number'], $outputlangs),
					),
				);
			}
		}

		$headerHeight = $this->getTableRowHeight($pdf, $outputlangs, $widths, $headerValues, $fontSize, array('B', 'B', 'B', 'B'));
		$firstRowHeight = $this->getTableRowHeight($pdf, $outputlangs, $bodyRows[0]['widths'], $bodyRows[0]['values'], $fontSize);
		$renderHeader = function () use ($pdf, $outputlangs, $widths, $headerValues, $fontSize) {
			$this->renderPdfTableRow($pdf, $outputlangs, $widths, $headerValues, $fontSize, array('B', 'B', 'B', 'B'), true);
		};

		$this->ensureTableHeaderWithFirstRow($pdf, $headerHeight, $firstRowHeight);
		$renderHeader();
		foreach ($bodyRows as $row) {
			$rowHeight = $this->getTableRowHeight($pdf, $outputlangs, $row['widths'], $row['values'], $fontSize);
			$this->repeatTableHeaderIfRowDoesNotFit($pdf, $rowHeight, $headerHeight, $renderHeader);
			$this->renderPdfTableRow($pdf, $outputlangs, $row['widths'], $row['values'], $fontSize, array(), false, false);
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
		$defaultFontSize = pdf_getPDFFontSize($outputlangs);

		$pdf->Ln(8);
		$this->renderNativeSignatureStampBoxes($pdf, $object, $outputlangs, $defaultFontSize, $derivedData, 'AttestationCompanySeal');
		if (!empty($object->date_signature)) {
			$pdf->SetFont('', '', $defaultFontSize - 1);
			$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationSignedOn', dol_print_date($object->date_signature, 'dayhour', 'tzuser', $outputlangs))), 0, 'L');
		}
	}

	/**
	 * Render compact native-looking signature and stamp boxes.
	 *
	 * @param	TCPDF|TCPDI				$pdf				PDF
	 * @param	PowerPlantPVAttestation	$object				Attestation
	 * @param	Translate				$outputlangs		Output lang
	 * @param	int						$defaultFontSize	Default font size
	 * @param	array<string,mixed>		$derivedData		Derived data
	 * @param	string					$stampLabelKey		Stamp label translation key
	 * @return	void
	 */
	protected function renderNativeSignatureStampBoxes($pdf, $object, $outputlangs, $defaultFontSize, $derivedData, $stampLabelKey)
	{
		$this->ensureSpace($pdf, 33);
		$gap = 10;
		$boxHeight = 20;
		$boxWidth = ($this->page_largeur - $this->marge_gauche - $this->marge_droite - $gap) / 2;
		$leftX = $this->marge_gauche;
		$rightX = $leftX + $boxWidth + $gap;

		$pdf->SetFont('', 'B', $defaultFontSize);
		$pdf->Cell($boxWidth, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationSignature')), 0, 0, 'L');
		$pdf->SetX($rightX);
		$pdf->Cell($boxWidth, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities($stampLabelKey)), 0, 1, 'L');

		$signer = $this->formatSigner($derivedData, $outputlangs);
		$signerText = $outputlangs->transnoentities('AttestationSignerNameFunction').' : '.$signer;
		$this->setPdfTextStyleForValue($pdf, $signer, $outputlangs, max($defaultFontSize - 2, 6));
		$pdf->Cell($boxWidth, 4, $outputlangs->convToOutputCharset(dol_trunc($signerText, 90)), 0, 1, 'L');
		$this->resetPdfTextStyle($pdf);

		$boxY = $pdf->GetY() + 1;
		$pdf->SetDrawColor(190, 190, 190);
		$this->renderNativeSignatureFrame($pdf, $leftX, $boxY, $boxWidth, $boxHeight);
		$this->renderNativeSignatureFrame($pdf, $rightX, $boxY, $boxWidth, $boxHeight);

		if (!empty($object->signature_file)) {
			$signature = powerplantpvAttestationGetDocumentRootDir($object->entity).'/'.$object->signature_file;
			$this->renderImageInNativeSignatureBox($pdf, $signature, $leftX, $boxY, $boxWidth, $boxHeight);
		}

		$stamp = powerplantpvAttestationGetCompanyStampFile($object->entity);
		$this->renderImageInNativeSignatureBox($pdf, $stamp, $rightX, $boxY, $boxWidth, $boxHeight);

		$pdf->SetDrawColor(0, 0, 0);
		$pdf->SetY($boxY + $boxHeight + 2);
	}

	/**
	 * Render a native PDF signature frame.
	 *
	 * @param	TCPDF|TCPDI	$pdf	PDF
	 * @param	float		$x		X
	 * @param	float		$y		Y
	 * @param	float		$w		Width
	 * @param	float		$h		Height
	 * @return	void
	 */
	protected function renderNativeSignatureFrame($pdf, $x, $y, $w, $h)
	{
		$radius = (float) getDolGlobalString('MAIN_PDF_FRAME_CORNER_RADIUS');
		if ($radius > 0 && method_exists($pdf, 'RoundedRect')) {
			$pdf->RoundedRect($x, $y, $w, $h, $radius, '1111', 'D');
		} else {
			$pdf->Rect($x, $y, $w, $h);
		}
	}

	/**
	 * Render an image centered inside a compact signature box.
	 *
	 * @param	TCPDF|TCPDI	$pdf	PDF
	 * @param	string		$file	Image file
	 * @param	float		$x		Box X
	 * @param	float		$y		Box Y
	 * @param	float		$w		Box width
	 * @param	float		$h		Box height
	 * @return	void
	 */
	protected function renderImageInNativeSignatureBox($pdf, $file, $x, $y, $w, $h)
	{
		if (!is_readable($file)) {
			return;
		}

		$padding = 2;
		$maxWidth = $w - (2 * $padding);
		$maxHeight = $h - (2 * $padding);
		$imageWidth = 0;
		$imageHeight = $maxHeight;
		$size = @getimagesize($file);
		if (is_array($size) && !empty($size[0]) && !empty($size[1])) {
			$scale = min($maxWidth / (float) $size[0], $maxHeight / (float) $size[1]);
			$imageWidth = (float) $size[0] * $scale;
			$imageHeight = (float) $size[1] * $scale;
		}

		$imageX = $x + $padding + ($imageWidth > 0 ? (($maxWidth - $imageWidth) / 2) : 0);
		$imageY = $y + $padding + max(0, ($maxHeight - $imageHeight) / 2);
		$pdf->Image($file, $imageX, $imageY, $imageWidth, $imageHeight, 'PNG');
	}

	/**
	 * Format signer identity.
	 *
	 * @param	array<string,mixed>	$derivedData	Derived data
	 * @param	Translate			$outputlangs	Output lang
	 * @return	string							Formatted signer
	 */
	protected function formatSigner($derivedData, $outputlangs)
	{
		$parts = array();
		if (!empty($derivedData['writer_name'])) {
			$parts[] = (string) $derivedData['writer_name'];
		}
		if (!empty($derivedData['writer_function'])) {
			$parts[] = (string) $derivedData['writer_function'];
		}

		return !empty($parts) ? implode(' / ', $parts) : $outputlangs->transnoentities('AttestationNotProvided');
	}

	/**
	 * Return a printable value or translated fallback.
	 *
	 * @param	mixed		$value			Value
	 * @param	Translate	$outputlangs	Output lang
	 * @return	string						Printable value
	 */
	protected function valueOrNotProvided($value, $outputlangs)
	{
		if ($value === null || $value === '') {
			return $outputlangs->transnoentities('AttestationNotProvided');
		}

		return (string) $value;
	}

	/**
	 * Return translated missing-value labels used by attestation PDF models.
	 *
	 * @param	Translate	$outputlangs	Output lang
	 * @return	string[]					Labels
	 */
	protected function getNotProvidedLabels($outputlangs)
	{
		return array_values(array_unique(array_filter(array(
			$outputlangs->transnoentities('AttestationNotProvided'),
			$outputlangs->transnoentities('AttestationDynamicNotProvided'),
		))));
	}

	/**
	 * Test if a value is the translated missing-value marker.
	 *
	 * @param	mixed		$value			Value
	 * @param	Translate	$outputlangs	Output lang
	 * @return	bool						True when value is missing marker
	 */
	protected function isNotProvidedValue($value, $outputlangs)
	{
		$value = trim((string) $value);
		foreach ($this->getNotProvidedLabels($outputlangs) as $label) {
			if ($value === trim((string) $label)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Apply PDF font and color for a value, highlighting missing values.
	 *
	 * @param	TCPDF|TCPDI	$pdf			PDF
	 * @param	mixed		$value			Value
	 * @param	Translate	$outputlangs	Output lang
	 * @param	int			$fontSize		Font size
	 * @param	string		$style			Regular style
	 * @return	void
	 */
	protected function setPdfTextStyleForValue($pdf, $value, $outputlangs, $fontSize, $style = '')
	{
		if ($this->isNotProvidedValue($value, $outputlangs)) {
			$pdf->SetTextColor(200, 0, 0);
			$pdf->SetFont('', 'B', $fontSize);
			return;
		}

		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', $style, $fontSize);
	}

	/**
	 * Reset PDF text color and style.
	 *
	 * @param	TCPDF|TCPDI	$pdf	PDF
	 * @return	void
	 */
	protected function resetPdfTextStyle($pdf)
	{
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', '');
	}

	/**
	 * Render a paragraph and highlight any translated missing-value marker inside it.
	 *
	 * @param	TCPDF|TCPDI	$pdf			PDF
	 * @param	Translate	$outputlangs	Output lang
	 * @param	string		$text			Text
	 * @param	float		$width			Width
	 * @param	float		$lineHeight		Line height
	 * @param	int|string	$border			Border
	 * @param	string		$align			Alignment
	 * @param	bool		$fill			Fill
	 * @return	void
	 */
	protected function renderParagraphWithStyledNotProvided($pdf, $outputlangs, $text, $width = 0, $lineHeight = 5, $border = 0, $align = 'L', $fill = false)
	{
		$plain = (string) $text;
		$containsNotProvided = false;
		foreach ($this->getNotProvidedLabels($outputlangs) as $label) {
			if ($label !== '' && strpos($plain, (string) $label) !== false) {
				$containsNotProvided = true;
				break;
			}
		}

		if (!$containsNotProvided || !method_exists($pdf, 'writeHTMLCell')) {
			$pdf->MultiCell($width, $lineHeight, $outputlangs->convToOutputCharset($plain), $border, $align, $fill);
			return;
		}

		$html = dol_escape_htmltag($plain);
		foreach ($this->getNotProvidedLabels($outputlangs) as $label) {
			if ($label === '') {
				continue;
			}
			$escapedLabel = dol_escape_htmltag($label);
			$html = str_replace($escapedLabel, '<span style="color:#c00000;font-weight:bold;">'.$escapedLabel.'</span>', $html);
		}
		$pdf->writeHTMLCell($width, 0, '', '', $outputlangs->convToOutputCharset($html), $border, 1, $fill, true, $align, true);
		$this->resetPdfTextStyle($pdf);
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
		if (is_object($this->currentObject) && is_object($this->currentOutputLangs)) {
			$this->renderHeader($pdf, $this->currentObject, $this->currentOutputLangs, false);
		} else {
			$pdf->SetXY($this->marge_gauche, $this->marge_haute);
		}
	}

	/**
	 * Return the height of a table row with multiline cells.
	 *
	 * @param	TCPDF|TCPDI					$pdf			PDF
	 * @param	Translate					$outputlangs	Output lang
	 * @param	array<int,float>|float		$widths			Column widths
	 * @param	array<int,string>			$values			Cell values
	 * @param	int							$fontSize		Font size
	 * @param	array<int,string>			$styles			Column font styles
	 * @param	float						$lineHeight		Line height
	 * @param	float						$cellPadding	Cell padding
	 * @param	float						$minHeight		Minimum height
	 * @return	float										Row height
	 */
	protected function getTableRowHeight($pdf, $outputlangs, $widths, $values, $fontSize, $styles = array(), $lineHeight = 4, $cellPadding = 2, $minHeight = 6)
	{
		if (!is_array($widths)) {
			$widths = array($widths);
		}

		$height = $minHeight;
		$fallbackWidth = count($widths) ? $widths[count($widths) - 1] : 0;
		foreach ($values as $i => $value) {
			$width = isset($widths[$i]) ? $widths[$i] : $fallbackWidth;
			$text = $outputlangs->convToOutputCharset((string) $value);
			$pdf->SetFont('', isset($styles[$i]) ? $styles[$i] : '', $fontSize);
			if (method_exists($pdf, 'getStringHeight')) {
				$height = max($height, $pdf->getStringHeight(max($width - $cellPadding, 1), $text) + $cellPadding);
			} else {
				$height = max($height, $lineHeight * (substr_count((string) $value, "\n") + 1) + $cellPadding);
			}
		}

		return $height;
	}

	/**
	 * Reserve enough space for a table header and its first body row.
	 *
	 * @param	TCPDF|TCPDI	$pdf				PDF
	 * @param	float		$headerHeight		Header height
	 * @param	float		$firstRowHeight		First row height
	 * @return	void
	 */
	protected function ensureTableHeaderWithFirstRow($pdf, $headerHeight, $firstRowHeight)
	{
		$this->ensureSpace($pdf, $headerHeight + $firstRowHeight + 2);
	}

	/**
	 * Add a page and repeat a table header before a row when the row cannot fit.
	 *
	 * @param	TCPDF|TCPDI	$pdf					PDF
	 * @param	float		$rowHeight				Row height
	 * @param	float		$headerHeight			Header height
	 * @param	callable	$renderHeaderCallback	Header renderer
	 * @return	void
	 */
	protected function repeatTableHeaderIfRowDoesNotFit($pdf, $rowHeight, $headerHeight, $renderHeaderCallback)
	{
		if (($pdf->GetY() + $rowHeight + 2) <= $this->getContentBottomY()) {
			return;
		}

		$this->ensureSpace($pdf, $headerHeight + $rowHeight + 2);
		call_user_func($renderHeaderCallback);
	}

	/**
	 * Render a table row with a height based on the tallest cell.
	 *
	 * @param	TCPDF|TCPDI					$pdf			PDF
	 * @param	Translate					$outputlangs	Output lang
	 * @param	array<int,float>|float		$widths			Column widths
	 * @param	array<int,string>			$values			Cell values
	 * @param	int							$fontSize		Font size
	 * @param	array<int,string>			$styles			Column font styles
	 * @param	bool						$fill			Fill row
	 * @param	bool						$checkSpace		Check available space
	 * @return	void
	 */
	protected function renderPdfTableRow($pdf, $outputlangs, $widths, $values, $fontSize, $styles = array(), $fill = false, $checkSpace = true)
	{
		if (!is_array($widths)) {
			$widths = array($widths);
		}

		$lineHeight = 4;
		$height = $this->getTableRowHeight($pdf, $outputlangs, $widths, $values, $fontSize, $styles, $lineHeight, 2, 6);
		if ($checkSpace) {
			$this->ensureSpace($pdf, $height + 2);
		}

		$x = $this->marge_gauche;
		$y = $pdf->GetY();
		if ($fill) {
			$pdf->SetFillColor(245, 245, 245);
		}
		$pdf->SetDrawColor(190, 190, 190);
		$fallbackWidth = count($widths) ? $widths[count($widths) - 1] : 0;
		foreach ($values as $i => $value) {
			$width = isset($widths[$i]) ? $widths[$i] : $fallbackWidth;
			$pdf->Rect($x, $y, $width, $height, $fill ? 'DF' : 'D');
			$pdf->SetXY($x + 1, $y + 1);
			$this->setPdfTextStyleForValue($pdf, $value, $outputlangs, $fontSize, isset($styles[$i]) ? $styles[$i] : '');
			$pdf->MultiCell($width - 2, $lineHeight, $outputlangs->convToOutputCharset((string) $value), 0, 'L', false, 0);
			$this->resetPdfTextStyle($pdf);
			$x += $width;
		}
		$pdf->SetDrawColor(0, 0, 0);
		$pdf->SetY($y + $height);
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
		$this->setPdfTextStyleForValue($pdf, $value, $outputlangs, pdf_getPDFFontSize($outputlangs));
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset((string) $value), 0, 'L');
		$this->resetPdfTextStyle($pdf);
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
