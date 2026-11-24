<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once __DIR__.'/pdf_attestation_bridage_dynamique.modules.php';
require_once __DIR__.'/../../../../class/powerplantpvattestationtypes.class.php';

/**
 * Commissioning attestation using the existing attestation layout.
 */
class pdf_attestation_mise_en_service_standard extends pdf_attestation_bridage_dynamique
{
	public function __construct($db)
	{
		parent::__construct($db);
		$this->setModelTranslationKeys('AttestationPdfModelMiseEnServiceStandardName', 'AttestationPdfModelMiseEnServiceStandardDescription');
		$this->titleKey = 'AttestationTypeMiseEnService';
	}

	/** @inheritDoc */
	protected function getDocumentTitle($object, $outputlangs)
	{
		return $outputlangs->transnoentities($this->titleKey);
	}

	/**
	 * @param TCPDF|TCPDI $pdf PDF
	 * @param PowerPlantPVAttestation $object Attestation
	 * @param Translate $outputlangs PDF language
	 * @param int $defaultFontSize Body font size
	 * @return void
	 */
	protected function renderBody($pdf, $object, $outputlangs, $defaultFontSize)
	{
		$derived = powerplantpvAttestationGetDerivedData($object, $outputlangs);
		$powerplant = $this->fetchPowerPlant($object);
		$commissioningDate = !empty($object->date_completion) ? dol_print_date($object->date_completion, 'day', 'tzuser', $outputlangs) : '';
		$consuelDate = !empty($object->date_consuel) ? dol_print_date($object->date_consuel, 'day', 'tzuser', $outputlangs) : '';

		$this->renderSectionTitle($pdf, $outputlangs, 'AttestationDynamicInstallationTitle', $defaultFontSize);
		$this->renderInfoTable($pdf, $outputlangs, $defaultFontSize, array(
			array('Project', $this->valueOrNotProvided($derived['project_name'], $outputlangs)),
			array('AttestationDynamicProducer', $this->valueOrNotProvided($this->fetchProducerName($object), $outputlangs)),
			array('AttestationDynamicSiteAddress', $this->valueOrNotProvided($derived['site_full_address'], $outputlangs)),
			array('AttestationDynamicPrmPdlReference', $this->valueOrNotProvided($this->getPowerPlantValue($powerplant, 'prm_pdl_number'), $outputlangs)),
			array('AttestationDynamicConnectionRequestReference', $this->valueOrNotProvided($this->getPowerPlantValue($powerplant, 'connection_request_number'), $outputlangs)),
			array('AttestationDynamicInstalledPower', $this->valueOrNotProvided($this->formatPower($this->getPowerPlantValue($powerplant, 'installed_power'), 'kWc'), $outputlangs)),
			array('AttestationCommissioningDate', $this->valueOrNotProvided($commissioningDate, $outputlangs)),
		));

		$pdf->Ln(4);
		$this->renderSectionTitle($pdf, $outputlangs, 'AttestationCommissioningChecksTitle', $defaultFontSize);
		$rows = array();
		foreach (PowerPlantPVAttestationTypes::COMMISSIONING_CHECKS as $field => $label) {
			$result = PowerPlantPVAttestationTypes::COMMISSIONING_RESULTS[(int) $object->{$field}] ?? PowerPlantPVAttestationTypes::COMMISSIONING_RESULTS[0];
			$rows[] = array($label, $outputlangs->transnoentities($result));
		}
		$this->renderInfoTable($pdf, $outputlangs, $defaultFontSize, $rows);

		$pdf->Ln(4);
		$this->renderSectionTitle($pdf, $outputlangs, 'AttestationCommissioningComplianceTitle', $defaultFontSize);
		$this->renderInfoTable($pdf, $outputlangs, $defaultFontSize, array(
			array('AttestationConsuelNumber', $this->valueOrNotProvided((string) $object->consuel_number, $outputlangs)),
			array('AttestationConsuelDate', $this->valueOrNotProvided($consuelDate, $outputlangs)),
		));

		if (trim((string) $object->note_public) !== '') {
			$pdf->Ln(4);
			$this->renderSectionTitle($pdf, $outputlangs, 'AttestationCommissioningRemarks', $defaultFontSize);
			$pdf->SetFont('', '', $defaultFontSize);
			$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset(dol_string_nohtmltag($object->note_public, 0)), 0, 'L');
		}
		if (!empty($object->lines)) {
			$pdf->Ln(4);
			$this->renderSectionTitle($pdf, $outputlangs, 'AttestationStaticEquipmentUsed', $defaultFontSize);
			$this->renderEquipmentTable($pdf, $object, $outputlangs, $defaultFontSize);
		}

		$this->ensureSpace($pdf, 48);
		$pdf->Ln(4);
		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities(
			'AttestationCommissioningDoneAt',
			$derived['place'],
			!empty($object->date_attestation) ? dol_print_date($object->date_attestation, 'day', 'tzuser', $outputlangs) : ''
		)), 0, 'L');
		$pdf->Ln(5);
		$this->renderNativeSignatureStampBoxes($pdf, $object, $outputlangs, $defaultFontSize, $derived, 'AttestationCompanySeal');
		if (!empty($object->date_signature)) {
			$pdf->SetFont('', '', $defaultFontSize - 1);
			$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities(
				'AttestationSignedOn',
				dol_print_date($object->date_signature, 'dayhour', 'tzuser', $outputlangs)
			)), 0, 'L');
		}
	}
}
