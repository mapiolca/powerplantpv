<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/powerplantpv/core/modules/attestation/doc/pdf_attestation_base.modules.php');

/**
 * Installer under 100 kWc attestation PDF.
 */
class pdf_attestation_installateur_inf100kwc extends pdf_attestation_base
{
	public function __construct($db)
	{
		parent::__construct($db);
		$this->name = 'attestation_installateur_inf100kwc';
		$this->titleKey = 'AttestationTypeInstallateurInf100kwc';
	}

	/**
	 * Render installer under 100 kWc attestation body.
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
		$attestationDate = !empty($object->date_attestation) ? dol_print_date($object->date_attestation, 'day', 'tzuser', $outputlangs) : '';

		$pdf->SetFont('', '', $defaultFontSize);
		$this->renderParagraphWithStyledNotProvided($pdf, $outputlangs, $outputlangs->transnoentities(
			'AttestationInstallerInf100Intro',
			$this->formatInstallerIdentity($derivedData, $outputlangs)
		), 0, 5, 0, 'L');

		$pdf->Ln(2);
		$this->renderInstallerLegalText($pdf, $object, $outputlangs, $defaultFontSize);

		$this->ensureSpace($pdf, $this->getFinalBlockHeight($pdf, $outputlangs, $defaultFontSize, $derivedData, $attestationDate));
		$pdf->Ln(4);
		$pdf->SetFont('', '', $defaultFontSize);
		$this->renderParagraphWithStyledNotProvided($pdf, $outputlangs, $outputlangs->transnoentities(
			'AttestationDynamicDoneAt',
			$this->valueOrNotProvided($derivedData['place'], $outputlangs),
			$this->valueOrNotProvided($attestationDate, $outputlangs)
		), 0, 5, 0, 'L');

		$this->renderNativeSignatureStampBoxes($pdf, $object, $outputlangs, $defaultFontSize, $derivedData, 'AttestationCompanySeal');
	}

	/**
	 * Render model-specific header lines.
	 *
	 * @param	TCPDF|TCPDI				$pdf			PDF
	 * @param	PowerPlantPVAttestation	$object			Attestation
	 * @param	Translate				$outputlangs	Output lang
	 * @return	void
	 */
	protected function renderAdditionalHeaderLines($pdf, $object, $outputlangs)
	{
		$powerplant = $this->fetchPowerPlant($object);
		$contractNumber = $this->getPowerPlantValue($powerplant, 'buyback_contract_number');
		$pdf->SetX($this->marge_gauche + 45);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationContractNumber').' : '.$this->valueOrNotProvided($contractNumber, $outputlangs)), 0, 'R');
	}

	/**
	 * Format installer identity for the legal introduction.
	 *
	 * @param	array<string,mixed>	$derivedData	Derived data
	 * @param	Translate			$outputlangs	Output lang
	 * @return	string							Formatted identity
	 */
	protected function formatInstallerIdentity($derivedData, $outputlangs)
	{
		$parts = array();
		if (!empty($derivedData['writer_name'])) {
			$parts[] = (string) $derivedData['writer_name'];
		}
		if (!empty($derivedData['installer_name'])) {
			$parts[] = (string) $derivedData['installer_name'];
		}
		$address = powerplantpvAttestationFormatDerivedAddress($derivedData, 'installer', 1);
		if ($address !== '') {
			$parts[] = $address;
		}

		return $this->valueOrNotProvided(implode(', ', $parts), $outputlangs);
	}

	/**
	 * Render installer legal text in the requested order.
	 *
	 * @param	TCPDF|TCPDI	$pdf				PDF
	 * @param	PowerPlantPVAttestation	$object	Attestation
	 * @param	Translate	$outputlangs		Output lang
	 * @param	int			$defaultFontSize	Default font size
	 * @return	void
	 */
	protected function renderInstallerLegalText($pdf, $object, $outputlangs, $defaultFontSize)
	{
		$this->renderBulletParagraph($pdf, $outputlangs, 'AttestationInstallerInf100WorksComplianceText', $defaultFontSize);
		$this->renderBulletParagraph($pdf, $outputlangs, 'AttestationInstallerInf100ProfessionalQualificationText', $defaultFontSize);
		$this->renderBulletParagraph($pdf, $outputlangs, 'AttestationInstallerInf100InstalledEquipmentText', $defaultFontSize);
		$this->renderGroupedEquipmentTable($pdf, $object, $outputlangs, $defaultFontSize);
		$this->renderBulletParagraph($pdf, $outputlangs, 'AttestationInstallerInf100LandscapeCriteriaText', $defaultFontSize);

		$this->ensureSpace($pdf, 20);
		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationInstallerInf100ProofCommitmentText')), 0, 'L');
		$pdf->Ln(2);
		$this->ensureSpace($pdf, 24);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationInstallerInf100CriminalPenaltiesText')), 0, 'L');
	}

	/**
	 * Render a legal bullet paragraph.
	 *
	 * @param	TCPDF|TCPDI	$pdf				PDF
	 * @param	Translate	$outputlangs		Output lang
	 * @param	string		$key				Translation key
	 * @param	int			$defaultFontSize	Default font size
	 * @return	void
	 */
	protected function renderBulletParagraph($pdf, $outputlangs, $key, $defaultFontSize)
	{
		$this->ensureSpace($pdf, 14);
		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset('- '.$outputlangs->transnoentities($key)), 0, 'L');
		$pdf->Ln(1);
	}

	/**
	 * Render equipment grouped by photovoltaic category and product reference.
	 *
	 * @param	TCPDF|TCPDI				$pdf				PDF
	 * @param	PowerPlantPVAttestation	$object				Attestation
	 * @param	Translate				$outputlangs		Output lang
	 * @param	int						$defaultFontSize	Default font size
	 * @return	void
	 */
	protected function renderGroupedEquipmentTable($pdf, $object, $outputlangs, $defaultFontSize)
	{
		$groups = $this->buildGroupedEquipmentRows($object, $outputlangs);
		$tableWidth = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$widths = array(42, 42, 48, $tableWidth - 42 - 42 - 48);
		$fontSize = max($defaultFontSize - 1, 7);

		if (empty(array_filter($groups))) {
			$this->ensureSpace($pdf, 8);
			$pdf->SetFont('', 'I', $fontSize);
			$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationNotProvided')), 0, 'L');
			$pdf->SetFont('', '', $fontSize);
			$pdf->Ln(1);
			return;
		}

		$headerValues = array(
			$outputlangs->transnoentities('AttestationInstallerInf100EquipmentCategory'),
			$outputlangs->transnoentities('AttestationInstallerInf100EquipmentBrand'),
			$outputlangs->transnoentities('AttestationInstallerInf100EquipmentReference'),
			$outputlangs->transnoentities('AttestationInstallerInf100EquipmentManufacturer'),
		);
		$headerStyles = array('B', 'B', 'B', 'B');
		$bodyRows = array();
		foreach ($this->getInstallerEquipmentCategoryOrder($outputlangs) as $categoryCode => $categoryLabel) {
			if (empty($groups[$categoryCode])) {
				continue;
			}
			foreach ($groups[$categoryCode] as $row) {
				$bodyRows[] = array(
					'category' => $categoryLabel,
					'brand' => (string) $row['brand'],
					'product_ref' => (string) $row['product_ref'],
					'manufacturer' => (string) $row['manufacturer'],
				);
			}
		}

		$headerHeight = $this->getTableRowHeight($pdf, $outputlangs, $widths, $headerValues, $fontSize, $headerStyles);
		$firstRowValues = $this->formatGroupedEquipmentTableRow($bodyRows[0], $outputlangs);
		$firstRowHeight = $this->getTableRowHeight($pdf, $outputlangs, $widths, $firstRowValues, $fontSize);
		$renderHeader = function () use ($pdf, $outputlangs, $widths, $headerValues, $fontSize, $headerStyles) {
			$this->renderTableRow($pdf, $outputlangs, $widths, $headerValues, $fontSize, $headerStyles, true);
		};

		$this->ensureTableHeaderWithFirstRow($pdf, $headerHeight, $firstRowHeight);
		$renderHeader();
		foreach ($bodyRows as $row) {
			$values = $this->formatGroupedEquipmentTableRow($row, $outputlangs);
			$rowHeight = $this->getTableRowHeight($pdf, $outputlangs, $widths, $values, $fontSize);
			$this->repeatTableHeaderIfRowDoesNotFit($pdf, $rowHeight, $headerHeight, $renderHeader);
			$this->renderTableRow($pdf, $outputlangs, $widths, $values, $fontSize, array(), false, false);
		}
		$pdf->Ln(2);
	}

	/**
	 * Build grouped equipment rows.
	 *
	 * @param	PowerPlantPVAttestation	$object			Attestation
	 * @param	Translate				$outputlangs	Output lang
	 * @return	array<string,array<string,array<string,mixed>>>	Rows by category code and product key
	 */
	protected function buildGroupedEquipmentRows($object, $outputlangs)
	{
		$groups = array();
		foreach ($this->getInstallerEquipmentCategoryOrder($outputlangs) as $categoryCode => $categoryLabel) {
			$groups[$categoryCode] = array();
		}

		if (empty($object->lines)) {
			return $groups;
		}

		foreach ($object->lines as $line) {
			$equipment = powerplantpvAttestationResolveEquipmentLine($line, $outputlangs);
			$categoryCode = strtoupper(trim((string) $equipment['category_code']));
			if (!array_key_exists($categoryCode, $groups)) {
				continue;
			}

			$productRef = trim((string) $equipment['product_ref']);
			$productKey = $productRef !== '' ? $productRef : '#'.((int) (!empty($equipment['fk_product']) ? $equipment['fk_product'] : 0)).'|'.(string) $equipment['brand'].'|'.(string) $equipment['manufacturer'];
			if (empty($groups[$categoryCode][$productKey])) {
				$groups[$categoryCode][$productKey] = array(
					'product_ref' => $productRef,
					'brand' => (string) $equipment['brand'],
					'manufacturer' => (string) $equipment['manufacturer'],
				);
			}
		}

		return $groups;
	}

	/**
	 * Return installer equipment category order.
	 *
	 * @param	Translate	$outputlangs	Output lang
	 * @return	array<string,string>	Labels by category code
	 */
	protected function getInstallerEquipmentCategoryOrder($outputlangs)
	{
		return array(
			'MODULE' => $outputlangs->transnoentities('AttestationInstallerInf100EquipmentModules'),
			'ONDULE' => $outputlangs->transnoentities('AttestationInstallerInf100EquipmentInverters'),
			'COFFAC' => $outputlangs->transnoentities('AttestationInstallerInf100EquipmentACBoxes'),
			'COFFDC' => $outputlangs->transnoentities('AttestationInstallerInf100EquipmentDCBoxes'),
			'SYSINT' => $outputlangs->transnoentities('AttestationInstallerInf100EquipmentIntegration'),
		);
	}

	/**
	 * Format a grouped equipment table row.
	 *
	 * @param	array<string,mixed>	$row			Grouped row
	 * @param	Translate			$outputlangs	Output lang
	 * @return	string[]							Formatted cells
	 */
	protected function formatGroupedEquipmentTableRow($row, $outputlangs)
	{
		return array(
			$this->valueOrNotProvided($row['category'], $outputlangs),
			$this->valueOrNotProvided($row['brand'], $outputlangs),
			$this->valueOrNotProvided($row['product_ref'], $outputlangs),
			$this->valueOrNotProvided($row['manufacturer'], $outputlangs),
		);
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
	protected function renderTableRow($pdf, $outputlangs, $widths, $values, $fontSize, $styles = array(), $fill = false, $checkSpace = true)
	{
		$this->renderPdfTableRow($pdf, $outputlangs, $widths, $values, $fontSize, $styles, $fill, $checkSpace);
	}

	/**
	 * Return the height required to keep the final statement and signature boxes together.
	 *
	 * @param	TCPDF|TCPDI			$pdf				PDF
	 * @param	Translate			$outputlangs		Output lang
	 * @param	int					$defaultFontSize	Default font size
	 * @param	array<string,mixed>	$derivedData		Derived data
	 * @param	string				$attestationDate	Attestation date
	 * @return	float									Required height
	 */
	protected function getFinalBlockHeight($pdf, $outputlangs, $defaultFontSize, $derivedData, $attestationDate)
	{
		$contentWidth = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$height = 4;
		$texts = array(
			$outputlangs->convToOutputCharset($outputlangs->transnoentities(
				'AttestationDynamicDoneAt',
				$this->valueOrNotProvided($derivedData['place'], $outputlangs),
				$this->valueOrNotProvided($attestationDate, $outputlangs)
			)),
		);
		$pdf->SetFont('', '', $defaultFontSize);
		foreach ($texts as $text) {
			if (method_exists($pdf, 'getStringHeight')) {
				$height += max(5, $pdf->getStringHeight($contentWidth, $text));
			} else {
				$height += 5 * (substr_count((string) $text, "\n") + 1);
			}
			$height += 5;
		}

		return $height + 33;
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
