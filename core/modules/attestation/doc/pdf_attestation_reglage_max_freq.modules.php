<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/powerplantpv/core/modules/attestation/doc/pdf_attestation_bridage_dynamique.modules.php');

/**
 * Max frequency attestation PDF.
 */
class pdf_attestation_reglage_max_freq extends pdf_attestation_bridage_dynamique
{
	public function __construct($db)
	{
		parent::__construct($db);
		$this->name = 'attestation_reglage_max_freq';
		$this->titleKey = 'AttestationTypeReglageMaxFreq515Hz';
	}

	/**
	 * Render max frequency attestation body.
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
		$frequency = $this->formatFrequency($object->max_frequency_hz);
		$inverterPower = $this->formatPower($this->getInverterPower($object), 'kVA');
		$attestationDate = !empty($object->date_attestation) ? dol_print_date($object->date_attestation, 'day', 'tzuser', $outputlangs) : '';
		$settingDate = !empty($object->date_setting) ? dol_print_date($object->date_setting, 'day', 'tzuser', $outputlangs) : '';

		$pdf->SetFont('', '', $defaultFontSize);
		$this->renderParagraphWithStyledNotProvided($pdf, $outputlangs, $outputlangs->transnoentities(
			'AttestationFrequencyIntro',
			$this->valueOrNotProvided($derivedData['writer_name'], $outputlangs),
			$this->valueOrNotProvided($derivedData['installer_name'], $outputlangs),
			$this->valueOrNotProvided($derivedData['installer_siret'], $outputlangs)
		), 0, 5, 0, 'L');

		$pdf->Ln(4);
		$this->renderSectionTitle($pdf, $outputlangs, 'AttestationDynamicInstallationTitle', $defaultFontSize);
		$this->renderInfoTable($pdf, $outputlangs, $defaultFontSize, array(
			array('AttestationDynamicProducer', $this->valueOrNotProvided($producerName, $outputlangs)),
			array('AttestationDynamicSiteAddress', $this->valueOrNotProvided($derivedData['site_full_address'], $outputlangs)),
			array('AttestationDynamicPrmPdlReference', $this->valueOrNotProvided($this->getPowerPlantValue($powerplant, 'prm_pdl_number'), $outputlangs)),
			array('AttestationDynamicConnectionRequestReference', $this->valueOrNotProvided($this->getPowerPlantValue($powerplant, 'connection_request_number'), $outputlangs)),
			array('AttestationDynamicInstalledPower', $this->valueOrNotProvided($this->formatPower($this->getPowerPlantValue($powerplant, 'installed_power'), 'kWc'), $outputlangs)),
			array('AttestationDynamicInverterPower', $this->valueOrNotProvided($inverterPower, $outputlangs)),
			array('AttestationFrequencySettingDate', $this->valueOrNotProvided($settingDate, $outputlangs)),
			array('AttestationFrequencyMaxFrequency', $this->valueOrNotProvided($frequency, $outputlangs)),
		));

		$pdf->Ln(4);
		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationFrequencyLimitText')), 0, 'L');
		$pdf->Ln(2);
		$pdf->SetFillColor(245, 245, 245);
		$pdf->SetDrawColor(190, 190, 190);
		$pdf->SetFont('', 'B', $defaultFontSize + 2);
		$this->renderParagraphWithStyledNotProvided($pdf, $outputlangs, $outputlangs->transnoentities(
			'AttestationFrequencyMaxFrequencyBlock',
			$this->valueOrNotProvided($frequency, $outputlangs)
		), 0, 8, 1, 'C', true);
		$pdf->SetDrawColor(0, 0, 0);
		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->Ln(2);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationFrequencyConfigurationText')), 0, 'L');

		$pdf->Ln(4);
		$this->renderSectionTitle($pdf, $outputlangs, 'AttestationFrequencyEquipmentUsed', $defaultFontSize);
		$this->renderEquipmentTable($pdf, $object, $outputlangs, $defaultFontSize);

		$pdf->Ln(4);
		$this->renderSectionTitle($pdf, $outputlangs, 'AttestationFrequencyChecksDone', $defaultFontSize);
		$this->renderBulletList($pdf, $outputlangs, array(
			'AttestationFrequencyCheckInverterConfiguration',
			'AttestationFrequencyCheckThreshold',
			'AttestationFrequencyCheckSettingPersistence',
		));

		$this->ensureSpace($pdf, $this->getFrequencyFinalResultSignatureBlockHeight($pdf, $object, $outputlangs, $defaultFontSize, $derivedData, $attestationDate));
		$pdf->Ln(4);
		$this->renderSectionTitle($pdf, $outputlangs, 'AttestationFrequencyResult', $defaultFontSize);
		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationFrequencyResultText')), 0, 'L');

		$pdf->Ln(5);
		$this->renderParagraphWithStyledNotProvided($pdf, $outputlangs, $outputlangs->transnoentities(
			'AttestationFrequencyDoneAt',
			$this->valueOrNotProvided($derivedData['place'], $outputlangs),
			$this->valueOrNotProvided($attestationDate, $outputlangs)
		), 0, 5, 0, 'L');

		$this->renderFrequencySignatureBlock($pdf, $object, $outputlangs, $defaultFontSize, $derivedData);
	}

	/**
	 * Format frequency value.
	 *
	 * @param	mixed	$value	Frequency value
	 * @return	string			Formatted value
	 */
	protected function formatFrequency($value)
	{
		if ($value === null || $value === '') {
			return '';
		}

		return price($value).' Hz';
	}

	/**
	 * Return the height required to keep result, date/place and signature boxes on one page.
	 *
	 * @param	TCPDF|TCPDI				$pdf				PDF
	 * @param	PowerPlantPVAttestation	$object				Attestation
	 * @param	Translate				$outputlangs		Output lang
	 * @param	int						$defaultFontSize	Default font size
	 * @param	array<string,mixed>		$derivedData		Derived data
	 * @param	string					$attestationDate	Attestation date
	 * @return	float										Required height
	 */
	protected function getFrequencyFinalResultSignatureBlockHeight($pdf, $object, $outputlangs, $defaultFontSize, $derivedData, $attestationDate)
	{
		$contentWidth = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$height = 4 + 6;

		$resultText = $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationFrequencyResultText'));
		$doneAtText = $outputlangs->convToOutputCharset($outputlangs->transnoentities(
			'AttestationFrequencyDoneAt',
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

		$height += 6 + 5 + 4 + 1 + 20 + 2;
		if (!empty($object->date_signature)) {
			$height += 5;
		}

		return $height + 4;
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
	protected function renderFrequencySignatureBlock($pdf, $object, $outputlangs, $defaultFontSize, $derivedData)
	{
		$pdf->Ln(6);
		$this->renderNativeSignatureStampBoxes($pdf, $object, $outputlangs, $defaultFontSize, $derivedData, 'AttestationCompanySeal');

		if (!empty($object->date_signature)) {
			$pdf->SetFont('', '', $defaultFontSize - 1);
			$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities(
				'AttestationSignedOn',
				dol_print_date($object->date_signature, 'dayhour', 'tzuser', $outputlangs)
			)), 0, 'L');
		}
	}
}
