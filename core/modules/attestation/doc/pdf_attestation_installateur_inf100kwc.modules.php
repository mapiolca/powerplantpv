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
		$powerplant = $this->fetchPowerPlant($object);
		$producerName = $this->fetchProducerName($object);
		$completionDate = !empty($object->date_completion) ? dol_print_date($object->date_completion, 'day', 'tzuser', $outputlangs) : '';
		$attestationDate = !empty($object->date_attestation) ? dol_print_date($object->date_attestation, 'day', 'tzuser', $outputlangs) : '';

		$pdf->SetFont('', '', $defaultFontSize);
		$this->renderParagraphWithStyledNotProvided($pdf, $outputlangs, $outputlangs->transnoentities(
			'AttestationInstallerInf100Intro',
			$this->valueOrNotProvided($derivedData['writer_name'], $outputlangs),
			$this->valueOrNotProvided($derivedData['installer_name'], $outputlangs),
			$this->valueOrNotProvided($derivedData['installer_siret'], $outputlangs),
			$this->valueOrNotProvided($object->bta_contract_number, $outputlangs),
			$this->valueOrNotProvided($completionDate, $outputlangs)
		), 0, 5, 0, 'L');

		$pdf->Ln(4);
		$this->renderSectionTitle($pdf, $outputlangs, 'AttestationDynamicInstallationTitle', $defaultFontSize);
		$this->renderInfoTable($pdf, $outputlangs, $defaultFontSize, array(
			array('AttestationDynamicProducer', $this->valueOrNotProvided($producerName, $outputlangs)),
			array('PowerPlant', $this->valueOrNotProvided($derivedData['project_name'], $outputlangs)),
			array('AttestationDynamicSiteAddress', $this->valueOrNotProvided($derivedData['site_full_address'], $outputlangs)),
			array('AttestationDynamicPrmPdlReference', $this->valueOrNotProvided($this->getPowerPlantValue($powerplant, 'prm_pdl_number'), $outputlangs)),
			array('AttestationDynamicConnectionRequestReference', $this->valueOrNotProvided($this->getPowerPlantValue($powerplant, 'connection_request_number'), $outputlangs)),
			array('AttestationDynamicInstalledPower', $this->valueOrNotProvided($this->formatPower($this->getPowerPlantValue($powerplant, 'installed_power'), 'kWc'), $outputlangs)),
			array('AttestationBtaContractNumber', $this->valueOrNotProvided($object->bta_contract_number, $outputlangs)),
			array('AttestationCompletionDate', $this->valueOrNotProvided($completionDate, $outputlangs)),
			array('AttestationLandscapeIntegrationPrime', $outputlangs->transnoentities((int) $object->landscape_integration_prime ? 'Yes' : 'No')),
		));

		$pdf->Ln(4);
		$this->renderSectionTitle($pdf, $outputlangs, 'AttestationInstallerInf100Commitments', $defaultFontSize);
		$this->renderCommitmentsParagraph($pdf, $outputlangs, $defaultFontSize);

		$pdf->Ln(4);
		$this->renderSectionTitle($pdf, $outputlangs, 'AttestationInstallerInf100InstalledMaterial', $defaultFontSize);
		$this->renderEquipmentTable($pdf, $object, $outputlangs, $defaultFontSize);

		$this->ensureSpace($pdf, $this->getFinalBlockHeight($pdf, $outputlangs, $defaultFontSize, $derivedData, $attestationDate));
		$pdf->Ln(4);
		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationInstallerInf100ForLegalUse')), 0, 'L');
		$pdf->Ln(5);
		$this->renderParagraphWithStyledNotProvided($pdf, $outputlangs, $outputlangs->transnoentities(
			'AttestationDynamicDoneAt',
			$this->valueOrNotProvided($derivedData['place'], $outputlangs),
			$this->valueOrNotProvided($attestationDate, $outputlangs)
		), 0, 5, 0, 'L');

		$this->renderNativeSignatureStampBoxes($pdf, $object, $outputlangs, $defaultFontSize, $derivedData, 'AttestationCompanySeal');
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
			$this->renderTableRow($pdf, $outputlangs, array($labelWidth, $valueWidth), array($outputlangs->transnoentities($row[0]), $row[1]), $fontSize, array('B', ''));
		}
		$pdf->Ln(2);
	}

	/**
	 * Render installer commitments as paragraphs instead of a table.
	 *
	 * @param	TCPDF|TCPDI	$pdf				PDF
	 * @param	Translate	$outputlangs		Output lang
	 * @param	int			$defaultFontSize	Default font size
	 * @return	void
	 */
	protected function renderCommitmentsParagraph($pdf, $outputlangs, $defaultFontSize)
	{
		$pairs = array(
			array('AttestationInstallerInf100WorksCompliance', 'AttestationInstallerInf100WorksComplianceText'),
			array('AttestationInstallerInf100ProfessionalQualification', 'AttestationInstallerInf100ProfessionalQualificationText'),
			array('AttestationInstallerInf100InstalledEquipment', 'AttestationInstallerInf100InstalledEquipmentText'),
			array('AttestationInstallerInf100LandscapeCriteria', 'AttestationInstallerInf100LandscapeCriteriaText'),
			array('AttestationInstallerInf100ProofCommitment', 'AttestationInstallerInf100ProofCommitmentText'),
			array('AttestationInstallerInf100CriminalPenalties', 'AttestationInstallerInf100CriminalPenaltiesText'),
		);
		$parts = array();
		foreach ($pairs as $pair) {
			$parts[] = $outputlangs->transnoentities($pair[0]).' : '.$outputlangs->transnoentities($pair[1]);
		}

		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset(implode("\n\n", $parts)), 0, 'L');
		$pdf->Ln(2);
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
		$widths = array(30, 24, 50, 28, 28, $tableWidth - 30 - 24 - 50 - 28 - 28);
		$fontSize = max($defaultFontSize - 2, 6);

		$headerValues = array(
			$outputlangs->transnoentities('AttestationEquipmentCategory'),
			$outputlangs->transnoentities('Ref'),
			$outputlangs->transnoentities('Designation'),
			$outputlangs->transnoentities('ProductPhotovoltaicBrand'),
			$outputlangs->transnoentities('ProductPhotovoltaicManufacturer'),
			$outputlangs->transnoentities('PowerPlantSerialNumber'),
		);

		$bodyRows = array();
		if (empty($object->lines)) {
			$bodyRows[] = array(
				'widths' => array($tableWidth),
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
						$this->valueOrNotProvided($equipment['brand'], $outputlangs),
						$this->valueOrNotProvided($equipment['manufacturer'], $outputlangs),
						$this->valueOrNotProvided($equipment['serial_number'], $outputlangs),
					),
				);
			}
		}

		$headerStyles = array('B', 'B', 'B', 'B', 'B', 'B');
		$headerHeight = $this->getTableRowHeight($pdf, $outputlangs, $widths, $headerValues, $fontSize, $headerStyles);
		$firstRowHeight = $this->getTableRowHeight($pdf, $outputlangs, $bodyRows[0]['widths'], $bodyRows[0]['values'], $fontSize);
		$renderHeader = function () use ($pdf, $outputlangs, $widths, $headerValues, $fontSize, $headerStyles) {
			$this->renderTableRow($pdf, $outputlangs, $widths, $headerValues, $fontSize, $headerStyles, true);
		};

		$this->ensureTableHeaderWithFirstRow($pdf, $headerHeight, $firstRowHeight);
		$renderHeader();
		foreach ($bodyRows as $row) {
			$rowHeight = $this->getTableRowHeight($pdf, $outputlangs, $row['widths'], $row['values'], $fontSize);
			$this->repeatTableHeaderIfRowDoesNotFit($pdf, $rowHeight, $headerHeight, $renderHeader);
			$this->renderTableRow($pdf, $outputlangs, $row['widths'], $row['values'], $fontSize, array(), false, false);
		}
		$pdf->Ln(2);
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
			$outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationInstallerInf100ForLegalUse')),
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
