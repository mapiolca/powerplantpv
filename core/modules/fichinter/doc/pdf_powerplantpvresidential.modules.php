<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once __DIR__.'/pdf_powerplantpvreport.modules.php';

/**
 * Compact native PDF model for residential installations and self-inspections.
 */
class pdf_powerplantpvresidential extends pdf_powerplantpvreport
{
	public $name = PowerPlantPVReportTemplate::PDF_MODEL_RESIDENTIAL;

	protected const REPORT_TEMPLATE_CODE = PowerPlantPVReportTemplate::CODE_RESIDENTIAL;
	protected const MODEL_LABEL = 'PowerPlantPVResidentialPdfName';
	protected const MODEL_DESCRIPTION = 'PowerPlantPVResidentialPdfDescription';
	protected const PDF_SUBJECT = self::MODEL_LABEL;

	/**
	 * @param TCPDF|TCPDI $pdf PDF handler
	 * @param CommonObject $object Intervention
	 * @param array<string,mixed> $dataset Report snapshot
	 * @param Translate $outputlangs Output language
	 * @return void
	 */
	protected function renderReport(&$pdf, $object, $dataset, $outputlangs)
	{
		$site = array();
		if (is_object($dataset['thirdparty'])) {
			$site[] = array('label' => $outputlangs->transnoentities('Customer'), 'value' => (string) $dataset['thirdparty']->name);
		}
		$project = $dataset['project'] ?? null;
		if (is_object($project)) {
			$site[] = array('label' => $outputlangs->transnoentities('Project'), 'value' => trim($project->ref.' '.$project->title));
		}
		foreach ($dataset['powerplants'] as $plant) {
			$site[] = array('label' => $outputlangs->transnoentities('PowerPlant'), 'value' => $this->powerplantLabel($plant));
			$snapshot = $this->resolvePowerplantTechnicalSummarySnapshot($plant, $outputlangs);
			foreach (($snapshot['sections'] ?? array()) as $section) {
				foreach (($section['rows'] ?? array()) as $fact) {
					if (in_array($fact['label_key'] ?? '', array('PowerPlantPDFAddress', 'PowerPlantPDFInstalledPower'), true)
						&& !empty($fact['value'])) {
						$site[] = array('label' => $outputlangs->transnoentities($fact['label_key']), 'value' => (string) $fact['value']);
					}
				}
			}
		}
		if ($site) {
			$this->visualPanels($pdf, array(
				array('title' => $outputlangs->transnoentities('PowerPlantPVAutocontrolSiteInstallation'), 'rows' => $site),
			), false);
		}
		foreach ($dataset['tree']['sections'] as $row) {
			if (empty($row['section']->visible_pdf)) {
				continue;
			}
			$panel = $this->visualSectionPanel($row, $outputlangs, true);
			if (is_object($row['equipment'] ?? null)) {
				$panel['title'] = PowerPlantPVReportPdfDataset::localizedProperty($row['section'], 'section_label', $outputlangs).' · '.$panel['title'];
			}
			if ($panel['rows']) {
				$this->visualPanels($pdf, array($panel), false);
			}
			$this->visualSectionExtras($pdf, $row, $dataset, $outputlangs);
		}
		$this->visualSignatures($pdf, $object, $dataset, $outputlangs);
	}
}
