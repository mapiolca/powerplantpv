<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once __DIR__.'/pdf_powerplantpvreport.modules.php';
require_once __DIR__.'/powerplantpvreportpartspdf.trait.php';

/**
 * Native intervention PDF model for the six-part C&I self-inspection report.
 */
class pdf_powerplantpvautocontrol extends pdf_powerplantpvreport
{
	use PowerPlantPVReportPartsPdf;

	public $name = 'powerplantpvautocontrol';

	protected const PARTS_SIGNATURE_CARD_HEIGHT = 58;
	protected const REPORT_TEMPLATE_CODE = PowerPlantPVReportTemplate::CODE_AUTOCONTROL;
	protected const MODEL_LABEL = 'PowerPlantPVAutocontrolPdfName';
	protected const MODEL_DESCRIPTION = 'PowerPlantPVAutocontrolPdfDescription';
	protected const PDF_SUBJECT = self::MODEL_LABEL;
}
