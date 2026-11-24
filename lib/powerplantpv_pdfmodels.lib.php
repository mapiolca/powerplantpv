<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/** @return array<string,array<string,string>> Translation keys by document type and stable model ID. */
function powerplantpvPdfModelLabels(): array
{
	return array(
		'ficheinter' => array(
			'powerplantpvreport' => 'PowerPlantPVInterventionReportPdfName',
			'powerplantpvautocontrol' => 'PowerPlantPVAutocontrolPdfName',
			'powerplantpvresidential' => 'PowerPlantPVResidentialPdfName',
		),
		'powerplant' => array(
			'standard_powerplant' => 'PowerPlantPVStandardPdfName',
			'centralepv_powerplant' => 'PowerPlantPDFCentralePVName',
		),
		'attestation' => array(
			'attestation_bridage_dynamique' => 'AttestationPdfModelBridageDynamiqueName',
			'attestation_bridage_statique' => 'AttestationPdfModelBridageStatiqueName',
			'attestation_reglage_max_freq' => 'AttestationPdfModelReglageMaxFreqName',
			'attestation_installateur_inf100kwc' => 'AttestationPdfModelInstallateurInf100kwcName',
			'attestation_mise_en_service_standard' => 'AttestationPdfModelMiseEnServiceStandardName',
			'attestation_mise_en_service' => 'AttestationPdfModelMiseEnServiceName',
		),
	);
}
