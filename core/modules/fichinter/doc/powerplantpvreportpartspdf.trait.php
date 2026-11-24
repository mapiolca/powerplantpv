<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once __DIR__.'/../../../../class/powerplantpvreportparts.class.php';

/**
 * Self-inspection report layout: one part per test day, each closed by the signatures given on that day,
 * then the final validation of the complete report. Same visual identity as the maintenance report.
 */
trait PowerPlantPVReportPartsPdf
{
	/** @var string Second header line, set per part */
	protected $visualHeaderSubtitle = '';

	/**
	 * @param TCPDF|TCPDI $pdf PDF handler
	 * @param CommonObject $object Intervention
	 * @param array<string,mixed> $dataset Dataset
	 * @param Translate $langs Output language
	 * @return void
	 */
	protected function renderPartsReport(&$pdf, $object, $dataset, $langs)
	{
		$langs->load('powerplantpv@powerplantpv');
		$signatures = $this->partsSignatures($dataset);
		$all = $dataset['general_sections'];
		foreach ($dataset['sections_by_powerplant'] as $rows) {
			$all = array_merge($all, $rows);
		}
		usort($all, static function ($a, $b) {
			return ((int) $a['section']->position <=> (int) $b['section']->position) ?: ((int) $a['section']->id <=> (int) $b['section']->id);
		});
		$definitions = PowerPlantPVReportParts::definitions($all);
		$rowsByPart = array_fill_keys(array_keys($definitions), array());
		$rowsByPart[''] = array();
		foreach ($all as $row) {
			if (!empty($row['section']->visible_pdf)) {
				$rowsByPart[PowerPlantPVReportParts::partCodeForSection((string) $row['section']->section_code)][] = $row;
			}
		}

		$this->partsCover($pdf, $dataset, $rowsByPart, $signatures, $definitions, $langs);
		foreach ($definitions as $partCode => $definition) {
			$this->visualHeaderSubtitle = $langs->transnoentities('PowerPlantPVAutocontrolHeaderPart', (string) ($dataset['intervention']->ref ?? ''), $definition['number'], count($definitions), $langs->transnoentities($definition['short']));
			$this->completePageFooter($pdf, 1);
			$pdf->AddPage();
			$this->reserveFooterSpace($pdf);
			$this->renderSectionTitle($pdf, $langs->transnoentities('PowerPlantPVAutocontrolPartNumber', $definition['number']).' · '.$langs->transnoentities($definition['label']), 2);
			$this->partsMuted($pdf, $langs->transnoentities('PowerPlantPVAutocontrolFormMeta', $definition['ref'], $definition['version'], $definition['application_date']));
			foreach ($rowsByPart[$partCode] as $row) {
				$this->partsSection($pdf, $row, $dataset, $langs);
			}
			$this->partsSignatureLine($pdf, $dataset, $partCode, $signatures[$partCode] ?? array(), $langs);
		}

		$this->visualHeaderSubtitle = '';
		$this->completePageFooter($pdf, 1);
		$pdf->AddPage();
		$this->reserveFooterSpace($pdf);
		foreach ($rowsByPart[''] as $row) {
			foreach ($this->filterVisibleFields($row['fields']) as $field) {
				$this->visualNote($pdf, PowerPlantPVReportPdfDataset::localizedProperty($field, 'field_label', $langs), PowerPlantPVReportPdfDataset::formatFieldValue($field, $langs), $langs->transnoentities('PowerPlantPVAutocontrolNoReservation'));
			}
		}
		$this->partsFinalValidation($pdf, $dataset, $signatures[PowerPlantPVReportParts::FINAL_CODE] ?? array(), $langs);
	}

	/**
	 * Cover: key figures, site and installation, then the follow-up of the parts.
	 * @param TCPDF|TCPDI $pdf @param array<string,mixed> $dataset @param array<string,array<int,array<string,mixed>>> $rowsByPart
	 * @param array<string,array<string,stdClass>> $signatures
	 * @param array<string,array{number:int,ref:string,label:string,short:string,version:string,application_date:string,decision:bool}> $definitions
	 * @param Translate $langs
	 * @return void
	 */
	protected function partsCover(&$pdf, $dataset, $rowsByPart, $signatures, $definitions, $langs)
	{
		$missing = $langs->transnoentities('PowerPlantPVDataNotFilled');
		$facts = array();
		$plant = null;
		foreach (array_values($dataset['powerplants']) as $candidate) {
			$plant = $candidate;
			$snapshot = $this->resolvePowerplantTechnicalSummarySnapshot($candidate, $langs);
			foreach (($snapshot['sections'] ?? array()) as $section) {
				foreach (($section['rows'] ?? array()) as $fact) {
					if (isset($fact['label_key'], $fact['value']) && !isset($facts[(string) $fact['label_key']])) {
						$facts[(string) $fact['label_key']] = (string) $fact['value'];
					}
				}
			}
		}
		$strings = 0;
		$checks = 0;
		$valid = 0;
		foreach ($rowsByPart as $rows) {
			foreach ($rows as $row) {
				$strings += count(PowerPlantPVReportPresentation::visibleMeasures($row['dc_measures'] ?? array(), true));
				foreach ($this->filterVisibleFields($row['fields']) as $field) {
					if ((string) $field->field_type === 'conformity_so_valid_obs') {
						$checks++;
						$valid += (string) $field->value_text === 'valid' ? 1 : 0;
					}
				}
			}
		}
		$signedParts = count(array_diff(array_keys($definitions), PowerPlantPVReportParts::unsignedParts($signatures)));
		$this->visualKpis($pdf, array(
			array($facts['PowerPlantPDFInstalledPower'] ?? $missing, $langs->transnoentities('PowerPlantInstalledPower')),
			array((string) $strings, $langs->transnoentities('PowerPlantPVAutocontrolStrings')),
			array($valid.' / '.$checks, $langs->transnoentities('PowerPlantPVVisualChecksConform')),
			array($signedParts.' / '.count($definitions), $langs->transnoentities('PowerPlantPVAutocontrolSignedParts')),
		), 4, 18.5);

		$this->renderSectionTitle($pdf, $langs->transnoentities('PowerPlantPVAutocontrolSiteInstallation'), 2);
		$client = $dataset['thirdparty'];
		$left = array(array('label' => $langs->transnoentities('Customer'), 'value' => is_object($client) ? (string) $client->name : $missing));
		$project = $dataset['project'] ?? null;
		if (is_object($project)) {
			$left[] = array('label' => $langs->transnoentities('Project'), 'value' => trim((string) $project->ref.' - '.(string) $project->title));
		}
		$left[] = array('label' => $langs->transnoentities('Intervention'), 'value' => (string) ($dataset['intervention']->ref ?? ''));
		$right = array();
		foreach (array('PowerPlantPDFReference' => 'Reference', 'PowerPlantPDFAddress' => 'Address', 'PowerPlantPDFInstalledPower' => 'PowerPlantPDFPower', 'PowerPlantPDFConnectionType' => 'Type') as $key => $label) {
			if (!empty($facts[$key])) {
				$right[] = array('label' => $langs->transnoentities($label), 'value' => $facts[$key]);
			}
		}
		$plantTitle = is_object($plant) ? trim($langs->transnoentities('PowerPlant').' '.(string) $plant->powerplant_label) : $langs->transnoentities('PowerPlant');
		$this->visualPanels($pdf, array(
			array('title' => $langs->transnoentities('PowerPlantPVAutocontrolSite'), 'rows' => $left, 'info' => true, 'compact' => 0.26),
			array('title' => $plantTitle, 'rows' => $right, 'info' => true, 'compact' => 0.22),
		), true);

		$rows = array();
		foreach ($definitions as $partCode => $definition) {
			$fields = array();
			foreach ($rowsByPart[$partCode] as $row) {
				foreach ($row['fields'] as $field) {
					$fields[(string) $field->field_code] = $field;
				}
			}
			$technicians = array();
			foreach (array('technician') as $role) {
				if (!empty($signatures[$partCode][$role])) {
					$technicians[] = (string) $signatures[$partCode][$role]->signer_name;
				}
			}
			$date = isset($fields[$partCode.'_DATE']) ? PowerPlantPVReportPdfDataset::formatFieldValue($fields[$partCode.'_DATE'], $langs) : '';
			$result = isset($fields[$partCode.'_RESULT']) ? PowerPlantPVReportPdfDataset::formatFieldValue($fields[$partCode.'_RESULT'], $langs) : '';
			$rows[] = array(
				'label' => $definition['number'].'. '.$langs->transnoentities($definition['short']),
				'value' => $definition['ref'].'  ·  '.($date !== '' ? $date : $missing).'  ·  '.($technicians ? implode(', ', $technicians) : $langs->transnoentities('PowerPlantPVAutocontrolNotSigned')).($result !== '' ? '  ·  '.$result : ''),
				'status' => 'text',
			);
		}
		$this->renderSectionTitle($pdf, $langs->transnoentities('PowerPlantPVAutocontrolPartsFollowUp'), 2);
		$this->visualPanels($pdf, array(array('title' => '', 'rows' => $rows, 'info' => true, 'compact' => 0.3)), false);
	}

	/**
	 * One section of a part.
	 * @param TCPDF|TCPDI $pdf @param array<string,mixed> $row @param array<string,mixed> $dataset @param Translate $langs
	 * @return void
	 */
	protected function partsSection(&$pdf, $row, $dataset, $langs)
	{
		$code = (string) $row['section']->section_code;
		if ($code === 'DC_ELECTRICAL_MEASURE') {
			$groups = PowerPlantPVReportPresentation::groupMeasures(PowerPlantPVReportPresentation::visibleMeasures($row['dc_measures'] ?? array(), false));
			foreach ($groups as $group) {
				$this->ensureSpace($pdf, 42);
				$this->visualDcTable($pdf, $group, $langs);
			}
			return;
		}
		if (preg_match('/_CONCLUSION$/', $code)) {
			$this->partsConclusion($pdf, $row, $langs);
			return;
		}
		$panel = $this->visualSectionPanel($row, $langs);
		if (empty($row['equipment'])) {
			$panel['title'] = preg_replace('/^[^·]+·\s*/u', '', (string) $panel['title']);
		}
		if (preg_match('/_IDENT$/', $code)) {
			foreach ($panel['rows'] as &$panelRow) {
				$panelRow = array('label' => $panelRow['label'], 'value' => $panelRow['value']);
			}
			unset($panelRow);
			$panel['info'] = true;
			$panel['compact'] = 0.3;
		}
		if ($panel['rows']) {
			$this->visualPanels($pdf, array($panel), false);
		}
		$this->visualSectionExtras($pdf, $row, $dataset, $langs);
	}

	/**
	 * Verdict box of a part: result, decision and deviations.
	 * @param TCPDF|TCPDI $pdf @param array<string,mixed> $row @param Translate $langs
	 * @return void
	 */
	protected function partsConclusion(&$pdf, $row, $langs)
	{
		$result = '';
		$resultCode = '';
		$lines = array();
		$deviations = '';
		foreach ($this->filterVisibleFields($row['fields']) as $field) {
			$value = trim(dol_string_nohtmltag(PowerPlantPVReportPdfDataset::formatFieldValue($field, $langs), 0));
			$fieldCode = (string) $field->field_code;
			if (preg_match('/_RESULT$/', $fieldCode)) {
				$result = $value;
				$resultCode = (string) $field->value_text;
			} elseif (preg_match('/_DEVIATIONS$/', $fieldCode)) {
				$deviations = $value;
			} elseif ($value !== '') {
				$lines[] = PowerPlantPVReportPdfDataset::localizedProperty($field, 'field_label', $langs).' : '.$value;
			}
		}
		$colors = array(
			'CONFORM' => array(array(227, 245, 238), array(185, 228, 210), array(18, 128, 92)),
			'RESERVES' => array(array(253, 243, 225), array(243, 217, 164), array(133, 84, 12)),
			'NON_CONFORM' => array(array(253, 232, 232), array(240, 190, 190), array(170, 40, 40)),
		);
		$palette = $colors[$resultCode] ?? array(array(247, 249, 252), array(218, 228, 240), array(100, 116, 139));
		$width = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$text = implode("\n", array_merge($lines, $deviations !== '' ? array($langs->transnoentities('PowerPlantPVAutocontrolDeviations').' : '.$deviations) : array()));
		$pdf->SetFont('', '', 8.5);
		$height = max(14, ($text !== '' ? $pdf->getStringHeight($width - 10, $text) : 0) + 11);
		$this->ensureSpace($pdf, $height + 5);
		$y = $pdf->GetY();
		$pdf->SetFillColor($palette[0][0], $palette[0][1], $palette[0][2]);
		$pdf->SetDrawColor($palette[1][0], $palette[1][1], $palette[1][2]);
		$pdf->RoundedRect($this->marge_gauche, $y, $width, $height, 2.65, '1111', 'DF');
		$pdf->SetTextColor($palette[2][0], $palette[2][1], $palette[2][2]);
		$pdf->SetFont('', 'B', 9);
		$pdf->SetXY($this->marge_gauche + 5, $y + 3);
		$pdf->Cell($width - 10, 4, $langs->convToOutputCharset($langs->transnoentities('PowerPlantPVVisualConclusion').' : '.($result !== '' ? $result : $langs->transnoentities('PowerPlantPVDataNotFilled'))), 0, 0, 'L');
		if ($text !== '') {
			$pdf->SetTextColor(40, 50, 68);
			$pdf->SetFont('', '', 8.5);
			$pdf->SetXY($this->marge_gauche + 5, $y + 8.5);
			$pdf->MultiCell($width - 10, 4, $langs->convToOutputCharset($text), 0, 'L');
		}
		$pdf->SetY($y + $height + 5);
		$pdf->SetFont('', '', $this->defaultFontSize);
		$pdf->SetTextColor(24, 47, 79);
	}

	/**
	 * Signature closing a part: no frame and no role grid, only "Signature" and who signed.
	 * @param TCPDF|TCPDI $pdf @param array<string,mixed> $dataset @param string $partCode
	 * @param array<string,stdClass> $partSignatures @param Translate $langs
	 * @return void
	 */
	protected function partsSignatureLine(&$pdf, $dataset, $partCode, $partSignatures, $langs)
	{
		$signed = array();
		foreach (array_keys(PowerPlantPVReportParts::roles($partCode)) as $role) {
			if (!empty($partSignatures[$role]) && is_object($partSignatures[$role])) {
				$signed[$role] = $partSignatures[$role];
			}
		}
		$this->ensureSpace($pdf, 32);
		$y = $pdf->GetY() + 2;
		$pdf->SetFont('', 'B', 9);
		$pdf->SetTextColor(24, 47, 79);
		$pdf->SetXY($this->marge_gauche, $y);
		$pdf->Cell(0, 5, $langs->convToOutputCharset($langs->transnoentities('PowerPlantPVAutocontrolPartSignature')), 0, 0, 'L');
		$y += 6;
		if (empty($signed)) {
			$pdf->SetFont('', 'I', 8);
			$pdf->SetTextColor(100, 116, 139);
			$pdf->SetXY($this->marge_gauche, $y);
			$pdf->Cell(0, 4, $langs->convToOutputCharset($langs->transnoentities('PowerPlantPVAutocontrolNotSigned')), 0, 0, 'L');
			$pdf->SetY($y + 8);
			$pdf->SetFont('', '', $this->defaultFontSize);
			$pdf->SetTextColor(24, 47, 79);
			return;
		}
		$gap = 6;
		$width = min(60, ($this->page_largeur - $this->marge_gauche - $this->marge_droite - (count($signed) - 1) * $gap) / count($signed));
		$i = 0;
		foreach ($signed as $role => $signature) {
			$x = $this->marge_gauche + $i * ($width + $gap);
			$pdf->SetFont('', 'B', 8.5);
			$pdf->SetTextColor(25, 39, 60);
			$pdf->SetXY($x, $y);
			$pdf->Cell($width, 4, $langs->convToOutputCharset(dol_trunc((string) $signature->signer_name, 40)), 0, 0, 'L');
			$pdf->SetFont('', '', 7.5);
			$pdf->SetTextColor(100, 116, 139);
			$pdf->SetXY($x, $y + 4.5);
			$pdf->Cell($width, 4, $langs->convToOutputCharset(dol_print_date($signature->date_sign, 'dayhour', 'tzuser', $langs)), 0, 0, 'L');
			$image = $this->partsSignatureImage($dataset, $partCode, $role);
			$size = $image !== '' ? @getimagesize($image) : false;
			if (!empty($size[0]) && !empty($size[1])) {
				$ratio = min($width / $size[0], 16 / $size[1]);
				$pdf->Image($image, $x, $y + 9.5, $size[0] * $ratio, $size[1] * $ratio);
			}
			$i++;
		}
		$pdf->SetY($y + 28);
		$pdf->SetFont('', '', $this->defaultFontSize);
		$pdf->SetTextColor(24, 47, 79);
	}

	/**
	 * Final validation of the complete report, framed like the maintenance report signatures.
	 * @param TCPDF|TCPDI $pdf @param array<string,mixed> $dataset @param array<string,stdClass> $finalSignatures @param Translate $langs
	 * @return void
	 */
	protected function partsFinalValidation(&$pdf, $dataset, $finalSignatures, $langs)
	{
		$roles = PowerPlantPVReportParts::roles(PowerPlantPVReportParts::FINAL_CODE);
		$gap = 4;
		$height = 58;
		$columns = count($roles);
		$this->ensureSpace($pdf, $height + 20);
		$this->renderSectionTitle($pdf, $langs->transnoentities('PowerPlantPVAutocontrolFinalValidation'), 2);
		$this->partsMuted($pdf, $langs->transnoentities('PowerPlantPVAutocontrolFinalValidationHelp'));
		$y = $pdf->GetY();
		$width = ($this->page_largeur - $this->marge_droite - $this->marge_gauche - ($columns - 1) * $gap) / $columns;
		$i = 0;
		foreach ($roles as $role => $labelKey) {
			$signature = $finalSignatures[$role] ?? null;
			$x = $this->marge_gauche + $i * ($width + $gap);
			$pdf->SetDrawColor(218, 228, 240);
			$pdf->SetFillColor(255, 255, 255);
			$pdf->RoundedRect($x, $y, $width, $height, 2.65, '1111', 'DF');
			$pdf->SetTextColor(24, 47, 79);
			$pdf->SetFont('', 'B', 9);
			$pdf->SetXY($x + 4, $y + 3);
			$pdf->MultiCell($width - 8, 4, $langs->convToOutputCharset($langs->transnoentities($labelKey)), 0, 'L');
			$name = is_object($signature) ? (string) $signature->signer_name : '';
			$date = is_object($signature) ? dol_print_date($signature->date_sign, 'day', 'tzuser', $langs) : '';
			foreach (array(array('Name', $name, 13), array('Date', $date, 18)) as $line) {
				$pdf->SetFont('', '', 8);
				$pdf->SetTextColor(100, 116, 139);
				$pdf->SetXY($x + 4, $y + $line[2]);
				$pdf->Cell(12, 4, $langs->convToOutputCharset($langs->transnoentities($line[0])), 0, 0, 'L');
				if ($line[1] !== '') {
					$pdf->SetTextColor(25, 39, 60);
					$pdf->SetFont('', 'B', 8);
					$pdf->SetXY($x + 16, $y + $line[2]);
					$pdf->Cell($width - 20, 4, $langs->convToOutputCharset(dol_trunc($line[1], 40)), 0, 0, 'L');
				} else {
					$pdf->SetDrawColor(184, 192, 204);
					$pdf->SetLineStyle(array('width' => 0.2, 'dash' => '0.6,0.8'));
					$pdf->Line($x + 16, $y + $line[2] + 3.5, $x + $width - 4, $y + $line[2] + 3.5);
					$pdf->SetLineStyle(array('width' => 0.2, 'dash' => 0));
				}
			}
			$bx = $x + 4;
			$by = $y + 25;
			$bw = $width - 8;
			$bh = $height - 29;
			$pdf->SetDrawColor(207, 213, 223);
			$pdf->SetLineStyle(array('width' => 0.2, 'dash' => '1,1'));
			$pdf->RoundedRect($bx, $by, $bw, $bh, 1.5, '1111', 'D');
			$pdf->SetLineStyle(array('width' => 0.2, 'dash' => 0));
			$image = is_object($signature) ? $this->partsSignatureImage($dataset, PowerPlantPVReportParts::FINAL_CODE, $role) : '';
			$size = $image !== '' ? @getimagesize($image) : false;
			if (!empty($size[0]) && !empty($size[1])) {
				$ratio = min(($bw - 6) / $size[0], ($bh - 4) / $size[1]);
				$pdf->Image($image, $bx + ($bw - $size[0] * $ratio) / 2, $by + ($bh - $size[1] * $ratio) / 2, $size[0] * $ratio, $size[1] * $ratio);
			}
			$i++;
		}
		$pdf->SetY($y + $height + 4);
		$pdf->SetFont('', '', $this->defaultFontSize);
		$pdf->SetTextColor(24, 47, 79);
	}

	/**
	 * Muted one-line text under a title.
	 * @param TCPDF|TCPDI $pdf @param string $text
	 * @return void
	 */
	protected function partsMuted(&$pdf, $text)
	{
		$pdf->SetFont('', '', 8);
		$pdf->SetTextColor(100, 116, 139);
		$pdf->SetX($this->marge_gauche + 4);
		$pdf->MultiCell(0, 4, $this->currentOutputLangs->convToOutputCharset($text), 0, 'L');
		$pdf->Ln(2);
		$pdf->SetFont('', '', $this->defaultFontSize);
		$pdf->SetTextColor(24, 47, 79);
	}

	/**
	 * Part signatures of the report.
	 * @param array<string,mixed> $dataset Dataset
	 * @return array<string,array<string,stdClass>> Part => role => signature
	 */
	protected function partsSignatures($dataset)
	{
		if (!($dataset['report'] ?? null) instanceof PowerPlantPVReport) {
			return array();
		}
		$signatures = (new PowerPlantPVReportParts($this->db))->fetchSignatures($dataset['report']);

		return is_array($signatures) ? $signatures : array();
	}

	/**
	 * Drawn image of a part signature.
	 * @param array<string,mixed> $dataset @param string $partCode @param string $role
	 * @return string Absolute path or ''
	 */
	protected function partsSignatureImage($dataset, $partCode, $role)
	{
		$report = $dataset['report'] ?? null;
		if (!is_object($report) || empty($report->id) || !function_exists('powerplantpvReportSignaturePath')) {
			return '';
		}
		$path = powerplantpvReportSignaturePath((int) $report->entity, (int) $report->id, PowerPlantPVReportParts::imageKey($partCode, $role));

		return $path !== '' && is_readable($path) ? $path : '';
	}
}
