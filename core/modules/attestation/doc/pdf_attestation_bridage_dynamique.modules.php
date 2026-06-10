<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/powerplantpv/core/modules/attestation/doc/pdf_attestation_base.modules.php');

/**
 * Dynamic inverter bridage attestation PDF.
 */
class pdf_attestation_bridage_dynamique extends pdf_attestation_base
{
	public function __construct($db)
	{
		parent::__construct($db);
		$this->name = 'attestation_bridage_dynamique';
		$this->titleKey = 'AttestationTypeBridageDynamiqueOnduleur';
	}

	/**
	 * Render dynamic inverter bridage attestation body.
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
		$maxExportPower = $this->formatPower($object->max_export_power_kw, 'kW');
		$inverterPower = $this->formatPower($this->getInverterPower($object), 'kVA');
		$attestationDate = !empty($object->date_attestation) ? dol_print_date($object->date_attestation, 'day', 'tzuser', $outputlangs) : '';

		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities(
			'AttestationDynamicIntro',
			$this->valueOrNotProvided($derivedData['writer_name'], $outputlangs),
			$this->valueOrNotProvided($derivedData['installer_name'], $outputlangs),
			$this->valueOrNotProvided($derivedData['installer_siret'], $outputlangs)
		)), 0, 'L');

		$pdf->Ln(4);
		$this->renderSectionTitle($pdf, $outputlangs, 'AttestationDynamicInstallationTitle', $defaultFontSize);
		$this->renderInfoTable($pdf, $outputlangs, $defaultFontSize, array(
			array('AttestationDynamicProducer', $this->valueOrNotProvided($producerName, $outputlangs)),
			array('AttestationDynamicSiteAddress', $this->valueOrNotProvided($derivedData['site_full_address'], $outputlangs)),
			array('AttestationDynamicPrmPdlReference', $this->valueOrNotProvided($this->getPowerPlantValue($powerplant, 'prm_pdl_number'), $outputlangs)),
			array('AttestationDynamicConnectionRequestReference', $this->valueOrNotProvided($this->getPowerPlantValue($powerplant, 'connection_request_number'), $outputlangs)),
			array('AttestationDynamicInstalledPower', $this->valueOrNotProvided($this->formatPower($this->getPowerPlantValue($powerplant, 'installed_power'), 'kWc'), $outputlangs)),
			array('AttestationDynamicInverterPower', $this->valueOrNotProvided($inverterPower, $outputlangs)),
			array('AttestationDynamicAuthorizedMaxInjectionPower', $this->valueOrNotProvided($maxExportPower, $outputlangs)),
		));

		$pdf->Ln(4);
		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationDynamicLimitText')), 0, 'L');
		$pdf->Ln(2);
		$pdf->SetFillColor(245, 245, 245);
		$pdf->SetDrawColor(190, 190, 190);
		$pdf->SetFont('', 'B', $defaultFontSize + 2);
		$pdf->MultiCell(0, 8, $outputlangs->convToOutputCharset($outputlangs->transnoentities(
			'AttestationDynamicMaxInjectedPower',
			$this->valueOrNotProvided($maxExportPower, $outputlangs)
		)), 1, 'C', true);
		$pdf->SetDrawColor(0, 0, 0);
		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->Ln(2);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationDynamicMeasureControlText')), 0, 'L');

		$pdf->Ln(4);
		$this->renderSectionTitle($pdf, $outputlangs, 'AttestationDynamicEquipmentUsed', $defaultFontSize);
		$this->renderEquipmentTable($pdf, $object, $outputlangs, $defaultFontSize);

		$pdf->Ln(4);
		$this->renderSectionTitle($pdf, $outputlangs, 'AttestationDynamicChecksDone', $defaultFontSize);
		$this->renderBulletList($pdf, $outputlangs, array(
			'AttestationDynamicCheckMeasureDirection',
			'AttestationDynamicCheckInverterCommunication',
			'AttestationDynamicCheckInjectionLimit',
			'AttestationDynamicCheckDynamicBridageTest',
		));

		$this->ensureSpace($pdf, $this->getFinalResultSignatureBlockHeight($pdf, $object, $outputlangs, $defaultFontSize, $derivedData, $attestationDate));
		$pdf->Ln(4);
		$this->renderSectionTitle($pdf, $outputlangs, 'AttestationDynamicResult', $defaultFontSize);
		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationDynamicResultText')), 0, 'L');

		$pdf->Ln(5);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities(
			'AttestationDynamicDoneAt',
			$this->valueOrNotProvided($derivedData['place'], $outputlangs),
			$this->valueOrNotProvided($attestationDate, $outputlangs)
		)), 0, 'L');

		$this->renderDynamicSignatureBlock($pdf, $object, $outputlangs, $defaultFontSize, $derivedData);
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
		$pdf->SetFont('', 'B', $fontSize + 1);
		$pdf->MultiCell(0, 6, $outputlangs->convToOutputCharset($outputlangs->transnoentities($key)), 0, 'L');
		$pdf->SetFont('', '', $fontSize);
	}

	/**
	 * Render a translated bullet list.
	 *
	 * @param	TCPDF|TCPDI			$pdf			PDF
	 * @param	Translate			$outputlangs	Output lang
	 * @param	array<int,string>	$keys			Translation keys
	 * @return	void
	 */
	protected function renderBulletList($pdf, $outputlangs, $keys)
	{
		foreach ($keys as $key) {
			$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset('- '.$outputlangs->transnoentities($key)), 0, 'L');
		}
	}

	/**
	 * Return the height required to keep result, date/place and signature boxes on one page.
	 *
	 * @param	TCPDF|TCPDI					$pdf				PDF
	 * @param	PowerPlantPVAttestation		$object				Attestation
	 * @param	Translate					$outputlangs		Output lang
	 * @param	int							$defaultFontSize	Default font size
	 * @param	array<string,mixed>			$derivedData		Derived data
	 * @param	string						$attestationDate	Attestation date
	 * @return	float											Required height
	 */
	protected function getFinalResultSignatureBlockHeight($pdf, $object, $outputlangs, $defaultFontSize, $derivedData, $attestationDate)
	{
		$contentWidth = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$height = 4 + 6; // Spacing and result title.

		$resultText = $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationDynamicResultText'));
		$doneAtText = $outputlangs->convToOutputCharset($outputlangs->transnoentities(
			'AttestationDynamicDoneAt',
			$this->valueOrNotProvided($derivedData['place'], $outputlangs),
			$this->valueOrNotProvided($attestationDate, $outputlangs)
		));

		$pdf->SetFont('', '', $defaultFontSize);
		if (method_exists($pdf, 'getStringHeight')) {
			$height += max(5, $pdf->getStringHeight($contentWidth, $resultText));
			$height += 5;
			$height += max(5, $pdf->getStringHeight($contentWidth, $doneAtText));
		} else {
			$height += 5 * (substr_count($resultText, "\n") + 1);
			$height += 5;
			$height += 5 * (substr_count($doneAtText, "\n") + 1);
		}

		$height += 6 + 5 + 44 + 2; // Signature block spacing, labels, boxes and trailing spacing.
		if (!empty($object->date_signature)) {
			$height += 5;
		}

		return $height + 4;
	}

	/**
	 * Render section rows as a native-looking PDF table.
	 *
	 * @param	TCPDF|TCPDI							$pdf				PDF
	 * @param	Translate							$outputlangs		Output lang
	 * @param	int									$defaultFontSize	Default font size
	 * @param	array<int,array{0:string,1:string}>	$rows				Rows
	 * @return	void
	 */
	protected function renderInfoTable($pdf, $outputlangs, $defaultFontSize, $rows)
	{
		$labelWidth = 62;
		$valueWidth = $this->page_largeur - $this->marge_gauche - $this->marge_droite - $labelWidth;
		$fontSize = max($defaultFontSize - 1, 7);

		foreach ($rows as $row) {
			$this->renderInfoTableRow($pdf, $outputlangs, array($labelWidth, $valueWidth), $row[0], $row[1], $fontSize);
		}
		$pdf->Ln(2);
	}

	/**
	 * Render a multiline table row.
	 *
	 * @param	TCPDF|TCPDI			$pdf			PDF
	 * @param	Translate			$outputlangs	Output lang
	 * @param	array<int,float>	$widths			Column widths
	 * @param	string				$labelKey		Translation key
	 * @param	string				$value			Value
	 * @param	int					$fontSize		Font size
	 * @return	void
	 */
	protected function renderInfoTableRow($pdf, $outputlangs, $widths, $labelKey, $value, $fontSize)
	{
		$label = $outputlangs->convToOutputCharset($outputlangs->transnoentities($labelKey));
		$text = $outputlangs->convToOutputCharset((string) $value);
		$lineHeight = 4;
		$cellPadding = 2;
		$height = 6;
		if (method_exists($pdf, 'getStringHeight')) {
			$pdf->SetFont('', 'B', $fontSize);
			$height = max($height, $pdf->getStringHeight(max($widths[0] - $cellPadding, 1), $label) + $cellPadding);
			$pdf->SetFont('', '', $fontSize);
			$height = max($height, $pdf->getStringHeight(max($widths[1] - $cellPadding, 1), $text) + $cellPadding);
		} else {
			$height = max($height, $lineHeight * (max(substr_count((string) $label, "\n"), substr_count((string) $value, "\n")) + 1) + $cellPadding);
		}
		$this->ensureSpace($pdf, $height + 2);

		$x = $this->marge_gauche;
		$y = $pdf->GetY();
		$pdf->SetDrawColor(190, 190, 190);
		$pdf->SetFillColor(245, 245, 245);
		$pdf->Rect($x, $y, $widths[0], $height, 'DF');
		$pdf->Rect($x + $widths[0], $y, $widths[1], $height, 'D');

		$pdf->SetXY($x + 1, $y + 1);
		$pdf->SetFont('', 'B', $fontSize);
		$pdf->MultiCell($widths[0] - 2, $lineHeight, $label, 0, 'L', false, 0);
		$x += $widths[0];

		$pdf->SetXY($x + 1, $y + 1);
		$pdf->SetFont('', '', $fontSize);
		$pdf->MultiCell($widths[1] - 2, $lineHeight, $text, 0, 'L', false, 0);
		$pdf->SetDrawColor(0, 0, 0);
		$pdf->SetY($y + $height);
	}

	/**
	 * Render equipment lines as a table.
	 *
	 * @param	TCPDF|TCPDI				$pdf				PDF
	 * @param	PowerPlantPVAttestation	$object				Attestation
	 * @param	Translate				$outputlangs		Output lang
	 * @param	int						$defaultFontSize	Default font size
	 * @return	void
	 */
	protected function renderEquipmentTable($pdf, $object, $outputlangs, $defaultFontSize)
	{
		$tableWidth = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$widths = array(42, $tableWidth - 42 - 48, 48);
		$fontSize = max($defaultFontSize - 1, 7);

		$this->renderEquipmentTableRow($pdf, $outputlangs, $widths, array(
			$outputlangs->transnoentities('AttestationEquipmentCategory'),
			$outputlangs->transnoentities('Designation'),
			$outputlangs->transnoentities('PowerPlantSerialNumber'),
		), $fontSize, array('B', 'B', 'B'), true);

		if (empty($object->lines)) {
			$this->renderEquipmentTableRow($pdf, $outputlangs, array($tableWidth), array($outputlangs->transnoentities('None')), $fontSize);
			$pdf->Ln(2);
			return;
		}

		foreach ($object->lines as $line) {
			$this->renderEquipmentTableRow($pdf, $outputlangs, $widths, array(
				powerplantpvAttestationEquipmentCategoryLabel($line, $outputlangs),
				(string) $line->designation,
				(string) $line->serial_number,
			), $fontSize);
		}
		$pdf->Ln(2);
	}

	/**
	 * Render a table row with a height based on the tallest cell.
	 *
	 * @param	TCPDF|TCPDI			$pdf			PDF
	 * @param	Translate			$outputlangs	Output lang
	 * @param	array<int,float>	$widths			Column widths
	 * @param	array<int,string>	$values			Cell values
	 * @param	int					$fontSize		Font size
	 * @param	array<int,string>	$styles			Column font styles
	 * @param	bool				$fill			Fill row
	 * @return	void
	 */
	protected function renderEquipmentTableRow($pdf, $outputlangs, $widths, $values, $fontSize, $styles = array(), $fill = false)
	{
		$lineHeight = 4;
		$cellPadding = 2;
		$height = 6;
		foreach ($values as $i => $value) {
			$width = isset($widths[$i]) ? $widths[$i] : end($widths);
			$text = $outputlangs->convToOutputCharset((string) $value);
			$pdf->SetFont('', isset($styles[$i]) ? $styles[$i] : '', $fontSize);
			if (method_exists($pdf, 'getStringHeight')) {
				$height = max($height, $pdf->getStringHeight(max($width - $cellPadding, 1), $text) + $cellPadding);
			} else {
				$height = max($height, $lineHeight * (substr_count((string) $value, "\n") + 1) + $cellPadding);
			}
		}
		$this->ensureSpace($pdf, $height + 2);

		$x = $this->marge_gauche;
		$y = $pdf->GetY();
		$pdf->SetDrawColor(190, 190, 190);
		if ($fill) {
			$pdf->SetFillColor(245, 245, 245);
		}

		foreach ($values as $i => $value) {
			$width = isset($widths[$i]) ? $widths[$i] : end($widths);
			$pdf->Rect($x, $y, $width, $height, $fill ? 'DF' : 'D');
			$pdf->SetXY($x + 1, $y + 1);
			$pdf->SetFont('', isset($styles[$i]) ? $styles[$i] : '', $fontSize);
			$pdf->MultiCell($width - 2, $lineHeight, $outputlangs->convToOutputCharset((string) $value), 0, 'L', false, 0);
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
	 * @param	array<string,mixed>		$derivedData		Derived data
	 * @return	void
	 */
	protected function renderDynamicSignatureBlock($pdf, $object, $outputlangs, $defaultFontSize, $derivedData)
	{
		$pdf->Ln(6);
		$this->ensureSpace($pdf, 56);
		$gap = 8;
		$boxHeight = 44;
		$boxWidth = ($this->page_largeur - $this->marge_gauche - $this->marge_droite - $gap) / 2;
		$leftX = $this->marge_gauche;
		$rightX = $leftX + $boxWidth + $gap;

		$pdf->SetFont('', 'B', $defaultFontSize);
		$pdf->Cell($boxWidth, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationSignature')), 0, 0, 'L');
		$pdf->SetX($rightX);
		$pdf->Cell($boxWidth, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationDynamicCompanySeal')), 0, 1, 'L');

		$boxY = $pdf->GetY();
		$pdf->SetDrawColor(190, 190, 190);
		$pdf->Rect($leftX, $boxY, $boxWidth, $boxHeight);
		$pdf->Rect($rightX, $boxY, $boxWidth, $boxHeight);

		$pdf->SetXY($leftX + 4, $boxY + 4);
		$pdf->SetFont('', 'B', max($defaultFontSize - 1, 7));
		$pdf->MultiCell($boxWidth - 8, 4, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationDynamicSignerNameFunction')), 0, 'L');
		$pdf->SetX($leftX + 4);
		$pdf->SetFont('', '', max($defaultFontSize - 1, 7));
		$pdf->MultiCell($boxWidth - 8, 5, $outputlangs->convToOutputCharset($this->formatSigner($derivedData, $outputlangs)), 0, 'L');
		$signatureY = max($pdf->GetY() + 2, $boxY + 20);

		if (!empty($object->signature_file)) {
			$signature = powerplantpvAttestationGetDocumentRootDir($object->entity).'/'.$object->signature_file;
			if (file_exists($signature)) {
				$pdf->Image($signature, $leftX + 4, $signatureY, 45, 0, 'PNG');
			}
		}

		$stamp = powerplantpvAttestationGetCompanyStampFile($object->entity);
		if (file_exists($stamp)) {
			$pdf->Image($stamp, $rightX + 4, $boxY + 4, 35, 0, 'PNG');
		}
		$pdf->SetDrawColor(0, 0, 0);
		$pdf->SetY($boxY + $boxHeight + 2);

		if (!empty($object->date_signature)) {
			$pdf->SetFont('', '', $defaultFontSize - 1);
			$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities(
				'AttestationSignedOn',
				dol_print_date($object->date_signature, 'dayhour', 'tzuser', $outputlangs)
			)), 0, 'L');
		}
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
	 * Sum inverter power from equipment lines.
	 *
	 * @param	PowerPlantPVAttestation	$object	Attestation
	 * @return	float|string						Total or empty string
	 */
	protected function getInverterPower($object)
	{
		$total = 0;
		$found = 0;
		if (empty($object->lines)) {
			return '';
		}
		foreach ($object->lines as $line) {
			if ((string) $line->equipment_type !== 'INVERTER' || $line->max_power_kw === null || $line->max_power_kw === '') {
				continue;
			}
			$total += (float) $line->max_power_kw;
			$found = 1;
		}

		return $found ? $total : '';
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
			return $outputlangs->transnoentities('AttestationDynamicNotProvided');
		}

		return (string) $value;
	}
}
