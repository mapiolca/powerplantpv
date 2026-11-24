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
	protected const SECTION_ACCENT = array(141, 181, 0);

	/**
	 * @param TCPDF|TCPDI $pdf PDF handler
	 * @param CommonObject $object Intervention
	 * @param array<string,mixed> $dataset Report snapshot
	 * @param Translate $outputlangs Output language
	 * @return void
	 */
	protected function renderReport(&$pdf, $object, $dataset, $outputlangs)
	{
		$this->renderResidentialTitle($pdf, $outputlangs);
		$sections = array_filter($dataset['tree']['sections'], static function ($row) {
			return !empty($row['section']->visible_pdf);
		});
		$this->renderResidentialOverview($pdf, $dataset, $sections, $outputlangs);

		$sectionNumber = 1;
		$previousCode = '';
		foreach ($sections as $row) {
			$code = (string) $row['section']->section_code;
			if ($code !== $previousCode) {
				$label = PowerPlantPVReportPdfDataset::localizedProperty($row['section'], 'section_label', $outputlangs);
				$this->renderSectionTitle($pdf, ++$sectionNumber.' · '.$label, 2);
				$previousCode = $code;
			}
			$panel = $this->visualSectionPanel($row, $outputlangs, true);
			if ($panel['rows']) {
				if (!is_object($row['equipment'] ?? null)) {
					$panel['title'] = '';
				}
				$this->visualPanels($pdf, array($panel), false);
			}
			$this->visualSectionExtras($pdf, $row, $dataset, $outputlangs);
		}
		$this->visualSignatures($pdf, $object, $dataset, $outputlangs);
	}

	/**
	 * @param TCPDF|TCPDI $pdf PDF handler
	 * @param Translate $langs Output language
	 * @return void
	 */
	protected function renderResidentialTitle(&$pdf, $langs)
	{
		$title = $langs->convToOutputCharset(dol_strtoupper($langs->transnoentities(static::MODEL_LABEL)));
		$subtitle = $langs->convToOutputCharset($langs->transnoentities(static::MODEL_DESCRIPTION));
		$x = $this->marge_gauche;
		$width = $this->page_largeur - $x - $this->marge_droite;
		$textWidth = $width - 16;
		$pdf->SetFont('', 'B', 18);
		$titleHeight = $pdf->getStringHeight($textWidth, $title);
		$pdf->SetFont('', '', 10);
		$subtitleHeight = $pdf->getStringHeight($textWidth, $subtitle);
		$height = $titleHeight + $subtitleHeight + 12;
		$this->ensureSpace($pdf, $height + 5);
		$y = $pdf->GetY();

		$pdf->SetFillColor(242, 246, 224);
		$pdf->RoundedRect($x, $y, $width, $height, 3, '1111', 'F');
		$pdf->SetFillColorArray(static::SECTION_ACCENT);
		$pdf->RoundedRect($x + 4, $y + 4, 1.6, $height - 8, 0.8, '1111', 'F');
		$pdf->SetFont('', 'B', 18);
		$pdf->SetTextColor(23, 40, 74);
		$pdf->MultiCell($textWidth, $titleHeight, $title, 0, 'L', false, 1, $x + 9, $y + 5);
		$pdf->SetFont('', '', 10);
		$pdf->SetTextColor(78, 99, 126);
		$pdf->MultiCell($textWidth, $subtitleHeight, $subtitle, 0, 'L', false, 1, $x + 9, $y + 7 + $titleHeight);
		$pdf->SetY($y + $height + 5);
	}

	/**
	 * @param TCPDF|TCPDI $pdf PDF handler
	 * @param array<string,mixed> $dataset Report dataset
	 * @param array<int,array<string,mixed>> $sections Visible sections
	 * @param Translate $langs Output language
	 * @return void
	 */
	protected function renderResidentialOverview(&$pdf, array $dataset, array $sections, $langs)
	{
		$panels = array($this->visualInspectionSitePanel($dataset, $langs));
		$installedPowers = array();
		foreach ($dataset['powerplants'] as $plant) {
			$facts = array();
			$snapshot = $this->resolvePowerplantTechnicalSummarySnapshot($plant, $langs);
			foreach (($snapshot['sections'] ?? array()) as $section) {
				foreach (($section['rows'] ?? array()) as $fact) {
					if (isset($fact['label_key'], $fact['value'])) {
						$facts[(string) $fact['label_key']] = (string) $fact['value'];
					}
				}
			}
			$installedPowers[] = $facts['PowerPlantPDFInstalledPower'] ?? $langs->transnoentities('PowerPlantPVDataNotFilled');
			$panels[] = $this->visualInspectionPlantPanel($plant, $facts, $langs);
		}
		$strings = $checks = $valid = $inverters = 0;
		foreach ($sections as $row) {
			if ((string) $row['section']->section_code === 'RES_INVERTER' && is_object($row['equipment'] ?? null)) {
				$inverters++;
			}
			foreach ($this->filterVisibleFields($row['fields']) as $field) {
				if ((string) $field->field_type === 'conformity_so_valid_obs') {
					$checks++;
					$valid += (int) ($field->value_text === 'valid');
				}
				if (preg_match('/^RES_STRING_[0-9]+_VOLTAGE$/', (string) $field->field_code) && is_numeric($field->value_number)) {
					$strings++;
				}
			}
		}
		$cards = array(
			array((string) $strings, $langs->transnoentities('PowerPlantPVAutocontrolStrings')),
			array($valid.' / '.$checks, $langs->transnoentities('PowerPlantPVVisualChecksConform')),
			array((string) $inverters, $langs->transnoentities('PowerPlantPDFInverters')),
		);
		if (count($installedPowers) === 1) {
			array_unshift($cards, array($installedPowers[0], $langs->transnoentities('PowerPlantInstalledPower')));
		}
		$this->visualKpis($pdf, $cards, count($cards), 18.5);
		$this->renderSectionTitle($pdf, '1 · '.$langs->transnoentities('PowerPlantPVAutocontrolSiteInstallation'), 2);
		$this->visualPanels($pdf, $panels, count($panels) > 1);
	}
}
