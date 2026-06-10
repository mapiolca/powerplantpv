<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/powerplantpv/core/modules/attestation/doc/pdf_attestation_base.modules.php');

/**
 * Static inverter bridage attestation PDF.
 */
class pdf_attestation_bridage_statique extends pdf_attestation_base
{
	public function __construct($db)
	{
		parent::__construct($db);
		$this->name = 'attestation_bridage_statique';
		$this->titleKey = 'AttestationTypeBridageStatiqueOnduleur';
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

		$title = $outputlangs->transnoentities('AttestationDocumentTitle', $outputlangs->transnoentities($this->titleKey));
		$pdf->SetXY($this->marge_gauche + 45, $y);
		$pdf->SetFont('', 'B', 14);
		$pdf->MultiCell(0, 7, $outputlangs->convToOutputCharset($title), 0, 'R');
		$pdf->SetFont('', '', 9);
		$pdf->SetX($this->marge_gauche + 45);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($object->ref), 0, 'R');
		$pdf->Ln(12);
	}

	/**
	 * Render static inverter bridage attestation body.
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
		$powerplant = $this->fetchPowerPlant($object);
		$producerName = $this->fetchProducerName($object);

		$this->renderSectionTitle($pdf, $outputlangs, 'AttestationPhotovoltaicInstallation', $defaultFontSize);
		$this->renderInstallationTable($pdf, $object, $outputlangs, $defaultFontSize, array(
			array('AttestationType', $this->translatedType($object, $outputlangs)),
			array('AttestationDynamicProducer', $this->valueOrNotProvided($producerName, $outputlangs)),
			array('ProjectName', $this->valueOrNotProvided($derivedData['project_name'], $outputlangs)),
			array('AttestationDynamicSiteAddress', $this->valueOrNotProvided($derivedData['site_full_address'], $outputlangs)),
			array('AttestationDynamicPrmPdlReference', $this->valueOrNotProvided($this->getPowerPlantValue($powerplant, 'prm_pdl_number'), $outputlangs)),
			array('AttestationDynamicConnectionRequestReference', $this->valueOrNotProvided($this->getPowerPlantValue($powerplant, 'connection_request_number'), $outputlangs)),
			array('AttestationDynamicInstalledPower', $this->valueOrNotProvided($this->formatPower($this->getPowerPlantValue($powerplant, 'installed_power'), 'kWc'), $outputlangs)),
			array('AttestationMaxExportPowerKw', $this->valueOrNotProvided($this->formatPower($object->max_export_power_kw, 'kW'), $outputlangs)),
			array('AttestationDate', !empty($object->date_attestation) ? dol_print_date($object->date_attestation, 'day', 'tzuser', $outputlangs) : $this->valueOrNotProvided('', $outputlangs)),
			array('AttestationInstallerName', $this->valueOrNotProvided($derivedData['installer_name'], $outputlangs)),
			array('SIRET', $this->valueOrNotProvided($derivedData['installer_siret'], $outputlangs)),
		));

		$this->renderStaticEquipmentTable($pdf, $object, $outputlangs, $defaultFontSize);
		$this->renderSignatureStampBoxes($pdf, $object, $outputlangs, $defaultFontSize);
	}

	/**
	 * Render a section title.
	 *
	 * @param	TCPDF|TCPDI	$pdf			PDF
	 * @param	Translate	$outputlangs	Output lang
	 * @param	string		$key			Translation key
	 * @param	int			$fontSize		Font size
	 * @return	void
	 */
	protected function renderSectionTitle($pdf, $outputlangs, $key, $fontSize)
	{
		$pdf->Ln(3);
		$pdf->SetFont('', 'B', $fontSize + 1);
		$pdf->MultiCell(0, 6, $outputlangs->convToOutputCharset($outputlangs->transnoentities($key)), 0, 'L');
		$pdf->SetFont('', '', $fontSize);
	}

	/**
	 * Render installation information as a table.
	 *
	 * @param	TCPDF|TCPDI				$pdf				PDF
	 * @param	PowerPlantPVAttestation	$object				Attestation
	 * @param	Translate				$outputlangs		Output lang
	 * @param	int						$defaultFontSize	Default font size
	 * @param	array<int,array{0:string,1:string}>	$rows		Rows
	 * @return	void
	 */
	protected function renderInstallationTable($pdf, $object, $outputlangs, $defaultFontSize, $rows)
	{
		$widths = array(62, $this->page_largeur - $this->marge_gauche - $this->marge_droite - 62);
		$pdf->SetFont('', '', $defaultFontSize - 1);
		foreach ($rows as $row) {
			$this->renderTableRow($pdf, $outputlangs, $widths, array($outputlangs->transnoentities($row[0]), $row[1]), $defaultFontSize - 1, array('B', ''));
		}
		$pdf->Ln(2);
	}

	/**
	 * Render equipment as a table.
	 *
	 * @param	TCPDF|TCPDI				$pdf				PDF
	 * @param	PowerPlantPVAttestation	$object				Attestation
	 * @param	Translate				$outputlangs		Output lang
	 * @param	int						$defaultFontSize	Default font size
	 * @return	void
	 */
	protected function renderStaticEquipmentTable($pdf, $object, $outputlangs, $defaultFontSize)
	{
		$this->renderSectionTitle($pdf, $outputlangs, 'AttestationMaterialUsed', $defaultFontSize);

		$widths = array(27, 68, 32, 40, 22);
		$headers = array('Type', 'Designation', 'Model', 'SerialNumber', 'AttestationBridage');
		$headerValues = array();
		foreach ($headers as $key) {
			$headerValues[] = $outputlangs->transnoentities($key);
		}
		$this->renderTableRow($pdf, $outputlangs, $widths, $headerValues, $defaultFontSize - 1, array('B', 'B', 'B', 'B', 'B'), true);

		$pdf->SetFont('', '', $defaultFontSize - 1);
		if (empty($object->lines)) {
			$this->renderTableRow($pdf, $outputlangs, array_sum($widths), array($outputlangs->transnoentities('None')), $defaultFontSize - 1);
			$pdf->Ln(2);
			return;
		}

		foreach ($object->lines as $line) {
			$this->renderTableRow($pdf, $outputlangs, $widths, array(
				(string) $line->equipment_type,
				(string) $line->designation,
				(string) $line->model,
				(string) $line->serial_number,
				!empty($line->bridage_enabled) ? $outputlangs->transnoentities('Yes') : $outputlangs->transnoentities('No'),
			), $defaultFontSize - 1);
		}
		$pdf->Ln(2);
	}

	/**
	 * Render a table row with multiline cells.
	 *
	 * @param	TCPDF|TCPDI					$pdf			PDF
	 * @param	Translate					$outputlangs	Output lang
	 * @param	array<int,float>|float		$widths			Column widths
	 * @param	array<int,string>			$values			Cell values
	 * @param	int							$fontSize		Font size
	 * @param	array<int,string>			$styles			Column font styles
	 * @param	bool						$fill			Fill row
	 * @return	void
	 */
	protected function renderTableRow($pdf, $outputlangs, $widths, $values, $fontSize, $styles = array(), $fill = false)
	{
		if (!is_array($widths)) {
			$widths = array($widths);
		}
		$height = 6;
		foreach ($values as $i => $value) {
			$width = isset($widths[$i]) ? $widths[$i] : end($widths);
			$text = $outputlangs->convToOutputCharset((string) $value);
			if (method_exists($pdf, 'getStringHeight')) {
				$height = max($height, $pdf->getStringHeight($width, $text) + 2);
			} else {
				$height = max($height, 5 * (substr_count((string) $value, "\n") + 1));
			}
		}
		$this->ensureSpace($pdf, $height + 2);

		$x = $this->marge_gauche;
		$y = $pdf->GetY();
		if ($fill) {
			$pdf->SetFillColor(245, 245, 245);
		}
		$pdf->SetDrawColor(190, 190, 190);
		foreach ($values as $i => $value) {
			$width = isset($widths[$i]) ? $widths[$i] : end($widths);
			$pdf->SetXY($x, $y);
			$pdf->SetFont('', isset($styles[$i]) ? $styles[$i] : '', $fontSize);
			$pdf->MultiCell($width, $height, $outputlangs->convToOutputCharset((string) $value), 1, 'L', $fill, 0);
			$x += $width;
		}
		$pdf->SetDrawColor(0, 0, 0);
		$pdf->SetY($y + $height);
	}

	/**
	 * Render signature and company stamp boxes.
	 *
	 * @param	TCPDF|TCPDI				$pdf				PDF
	 * @param	PowerPlantPVAttestation	$object				Attestation
	 * @param	Translate				$outputlangs		Output lang
	 * @param	int						$defaultFontSize	Default font size
	 * @return	void
	 */
	protected function renderSignatureStampBoxes($pdf, $object, $outputlangs, $defaultFontSize)
	{
		$derivedData = powerplantpvAttestationGetDerivedData($object, $outputlangs);

		$this->ensureSpace($pdf, 52);
		$this->renderSectionTitle($pdf, $outputlangs, 'AttestationSignerNameFunction', $defaultFontSize);
		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($this->formatSigner($derivedData, $outputlangs)), 0, 'L');
		$pdf->Ln(4);

		$gap = 8;
		$boxHeight = 32;
		$boxWidth = ($this->page_largeur - $this->marge_gauche - $this->marge_droite - $gap) / 2;
		$leftX = $this->marge_gauche;
		$rightX = $leftX + $boxWidth + $gap;

		$pdf->SetFont('', 'B', $defaultFontSize);
		$pdf->Cell($boxWidth, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationSignature')), 0, 0, 'L');
		$pdf->SetX($rightX);
		$pdf->Cell($boxWidth, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationCompanySeal')), 0, 1, 'L');

		$boxY = $pdf->GetY();
		$pdf->SetDrawColor(190, 190, 190);
		$pdf->Rect($leftX, $boxY, $boxWidth, $boxHeight);
		$pdf->Rect($rightX, $boxY, $boxWidth, $boxHeight);

		if (!empty($object->signature_file)) {
			$signature = powerplantpvAttestationGetDocumentRootDir($object->entity).'/'.$object->signature_file;
			if (file_exists($signature)) {
				$pdf->Image($signature, $leftX + 4, $boxY + 4, 45, 0, 'PNG');
			}
		}

		$stamp = powerplantpvAttestationGetCompanyStampFile($object->entity);
		if (file_exists($stamp)) {
			$pdf->Image($stamp, $rightX + 4, $boxY + 4, 35, 0, 'PNG');
		}
		$pdf->SetDrawColor(0, 0, 0);
		$pdf->SetY($boxY + $boxHeight + 2);
	}

	/**
	 * Fetch linked power plant.
	 *
	 * @param	PowerPlantPVAttestation	$object	Attestation
	 * @return	PowerPlant|null				Power plant
	 */
	protected function fetchPowerPlant($object)
	{
		if (empty($object->fk_powerplant)) {
			return null;
		}
		dol_include_once('/powerplantpv/class/powerplant.class.php');
		$powerplant = new PowerPlant($this->db);
		if ($powerplant->fetch((int) $object->fk_powerplant) <= 0) {
			return null;
		}

		return $powerplant;
	}

	/**
	 * Fetch linked producer name.
	 *
	 * @param	PowerPlantPVAttestation	$object	Attestation
	 * @return	string							Producer name
	 */
	protected function fetchProducerName($object)
	{
		if (empty($object->fk_soc)) {
			return '';
		}
		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		$thirdparty = new Societe($this->db);
		if ($thirdparty->fetch((int) $object->fk_soc) <= 0) {
			return '';
		}

		if (!empty($thirdparty->name)) {
			return (string) $thirdparty->name;
		}
		if (!empty($thirdparty->nom)) {
			return (string) $thirdparty->nom;
		}

		return '';
	}

	/**
	 * Get a power plant property value.
	 *
	 * @param	PowerPlant|null	$powerplant	Power plant
	 * @param	string			$key		Property
	 * @return	mixed						Value
	 */
	protected function getPowerPlantValue($powerplant, $key)
	{
		if (!is_object($powerplant) || !isset($powerplant->{$key})) {
			return '';
		}

		return $powerplant->{$key};
	}

	/**
	 * Format a numeric power value with its unit.
	 *
	 * @param	mixed	$value	Value
	 * @param	string	$unit	Unit
	 * @return	string			Formatted value
	 */
	protected function formatPower($value, $unit)
	{
		if ($value === null || $value === '') {
			return '';
		}

		return price($value).' '.$unit;
	}

	/**
	 * Format signer line.
	 *
	 * @param	array<string,mixed>	$derivedData	Derived data
	 * @param	Translate			$outputlangs	Output lang
	 * @return	string								Signer
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

		return $this->valueOrNotProvided(implode(' / ', $parts), $outputlangs);
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
}
