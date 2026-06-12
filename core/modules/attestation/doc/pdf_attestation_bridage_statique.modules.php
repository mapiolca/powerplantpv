<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/powerplantpv/core/modules/attestation/doc/pdf_attestation_bridage_dynamique.modules.php');

/**
 * Static inverter bridage attestation PDF.
 */
class pdf_attestation_bridage_statique extends pdf_attestation_bridage_dynamique
{
	public function __construct($db)
	{
		parent::__construct($db);
		$this->name = 'attestation_bridage_statique';
		$this->titleKey = 'AttestationTypeBridageStatiqueOnduleur';
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
		$maxExportPower = $this->formatPower($object->max_export_power_kw, 'kW');
		$inverterPower = $this->formatPower($this->getInverterPower($object), 'kVA');
		$attestationDate = !empty($object->date_attestation) ? dol_print_date($object->date_attestation, 'day', 'tzuser', $outputlangs) : '';

		$pdf->SetFont('', '', $defaultFontSize);
		$this->renderParagraphWithStyledNotProvided($pdf, $outputlangs, $outputlangs->transnoentities(
			'AttestationStaticIntro',
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
			array('AttestationDynamicAuthorizedMaxInjectionPower', $this->valueOrNotProvided($maxExportPower, $outputlangs)),
		));

		$pdf->Ln(4);
		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationStaticLimitText')), 0, 'L');
		$pdf->Ln(2);
		$pdf->SetFillColor(245, 245, 245);
		$pdf->SetDrawColor(190, 190, 190);
		$pdf->SetFont('', 'B', $defaultFontSize + 2);
		$this->renderParagraphWithStyledNotProvided($pdf, $outputlangs, $outputlangs->transnoentities(
			'AttestationStaticMaxInjectedPower',
			$this->valueOrNotProvided($maxExportPower, $outputlangs)
		), 0, 8, 1, 'C', true);
		$pdf->SetDrawColor(0, 0, 0);
		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->Ln(2);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationStaticConfigurationText')), 0, 'L');

		$pdf->Ln(4);
		$this->renderSectionTitle($pdf, $outputlangs, 'AttestationStaticEquipmentUsed', $defaultFontSize);
		$this->renderEquipmentTable($pdf, $object, $outputlangs, $defaultFontSize);

		$pdf->Ln(4);
		$this->renderSectionTitle($pdf, $outputlangs, 'AttestationStaticChecksDone', $defaultFontSize);
		$this->renderBulletList($pdf, $outputlangs, array(
			'AttestationStaticCheckInverterConfiguration',
			'AttestationStaticCheckInjectionLimit',
			'AttestationStaticCheckSettingPersistence',
		));

		$this->ensureSpace($pdf, $this->getStaticFinalResultSignatureBlockHeight($pdf, $object, $outputlangs, $defaultFontSize, $derivedData, $attestationDate));
		$pdf->Ln(4);
		$this->renderSectionTitle($pdf, $outputlangs, 'AttestationStaticResult', $defaultFontSize);
		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationStaticResultText')), 0, 'L');

		$pdf->Ln(5);
		$this->renderParagraphWithStyledNotProvided($pdf, $outputlangs, $outputlangs->transnoentities(
			'AttestationStaticDoneAt',
			$this->valueOrNotProvided($derivedData['place'], $outputlangs),
			$this->valueOrNotProvided($attestationDate, $outputlangs)
		), 0, 5, 0, 'L');

		$this->renderStaticSignatureBlock($pdf, $object, $outputlangs, $defaultFontSize, $derivedData);
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
	protected function getStaticFinalResultSignatureBlockHeight($pdf, $object, $outputlangs, $defaultFontSize, $derivedData, $attestationDate)
	{
		$contentWidth = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$height = 4 + 6;

		$resultText = $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationStaticResultText'));
		$doneAtText = $outputlangs->convToOutputCharset($outputlangs->transnoentities(
			'AttestationStaticDoneAt',
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
	protected function renderStaticSignatureBlock($pdf, $object, $outputlangs, $defaultFontSize, $derivedData)
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
