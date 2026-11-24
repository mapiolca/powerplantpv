<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		core/modules/attestation/doc/pdf_attestation_mise_en_service.modules.php
 * \ingroup		powerplantpv
 * \brief		Commissioning attestation PDF, with the visual identity of the maintenance and self-inspection reports.
 */

require_once __DIR__.'/pdf_attestation_base.modules.php';
require_once __DIR__.'/../../../../class/powerplantpvattestationtypes.class.php';

/**
 * Commissioning attestation PDF.
 */
class pdf_attestation_mise_en_service extends pdf_attestation_base
{
	protected $centerTableCells = true;

	/** @var array<int,array{0:array<int,int>,1:array<int,int>}> Background and text colors by check result */
	protected const CHECK_BADGE_COLORS = array(
		0 => array(array(237, 240, 244), array(73, 85, 103)),
		1 => array(array(227, 245, 238), array(18, 128, 92)),
		2 => array(array(252, 231, 231), array(149, 37, 37)),
		3 => array(array(237, 240, 244), array(73, 85, 103)),
		4 => array(array(255, 240, 218), array(132, 76, 14)),
	);
	/** @var array<int,int> Report palette: navy text */
	protected const NAVY = array(23, 40, 74);
	/** @var array<int,int> Report palette: accent */
	protected const ACCENT = array(22, 136, 224);
	/** @var array<int,int> Report palette: light card */
	protected const CARD = array(232, 243, 253);
	/** @var array<int,int> Report palette: panel border */
	protected const BORDER = array(228, 232, 239);
	/** @var array<int,int> Report palette: muted text */
	protected const MUTED = array(102, 112, 133);
	/** @var array<int,int> Report palette: row label */
	protected const LABEL = array(78, 99, 126);
	/** @var array<int,int> Report palette: body text */
	protected const TEXT = array(40, 50, 68);
	/** @var array<int,int> Missing value */
	protected const MISSING = array(200, 0, 0);


	/**
	 * Constructor.
	 *
	 * @param	DoliDB	$db	Database handler
	 */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->setModelTranslationKeys('AttestationPdfModelMiseEnServiceName', 'AttestationPdfModelMiseEnServiceDescription');
		$this->titleKey = 'AttestationTypeMiseEnService';
	}

	/**
	 * Compact report header: logo on the left, title and reference on the right.
	 *
	 * @param	TCPDF|TCPDI				$pdf			PDF
	 * @param	PowerPlantPVAttestation	$object			Attestation
	 * @param	Translate				$outputlangs	Output lang
	 * @param	bool					$showIssuer		Unused, the issuer is named in the body
	 * @return	void
	 */
	protected function renderHeader($pdf, $object, $outputlangs, $showIssuer = true)
	{
		global $conf;

		$posy = $this->marge_haute;
		$logoBottom = $posy;
		if (!getDolGlobalInt('PDF_DISABLE_MYCOMPANY_LOGO') && is_object($this->emetteur)) {
			if (!empty($this->emetteur->logo)) {
				$logodir = (string) ($conf->mycompany->multidir_output[(int) $object->entity] ?? '');
				$logo = $logodir.'/logos/'.$this->emetteur->logo;
				if (!getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO') && !empty($this->emetteur->logo_small)) {
					$logo = $logodir.'/logos/thumbs/'.$this->emetteur->logo_small;
				}
				if ($logodir !== '' && is_readable($logo)) {
					$height = min(14, pdf_getHeightForLogo($logo));
					$pdf->Image($logo, $this->marge_gauche, $posy, 0, $height);
					$logoBottom = $posy + $height;
				}
			} elseif (!empty($this->emetteur->name)) {
				$this->setColor($pdf, 'text', self::NAVY);
				$pdf->SetFont('', 'B', 12);
				$pdf->SetXY($this->marge_gauche, $posy);
				$pdf->MultiCell(80, 5, $outputlangs->convToOutputCharset($this->emetteur->name), 0, 'L');
				$logoBottom = $pdf->GetY();
			}
		}

		$this->setColor($pdf, 'text', self::NAVY);
		$pdf->SetFont('', 'B', 11);
		$pdf->SetXY($this->marge_gauche + 55, $posy);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationTypeMiseEnService')), 0, 'R');
		$reference = (string) $object->ref;
		if (!empty($object->date_attestation)) {
			$reference .= ' · '.dol_print_date($object->date_attestation, 'day', 'tzuser', $outputlangs);
		}
		$pdf->SetFont('', 'B', 10);
		$pdf->SetX($this->marge_gauche + 55);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($reference), 0, 'R');
		$pdf->SetY(max($logoBottom, $pdf->GetY()) + 5);
		$this->resetStyle($pdf);
	}

	/**
	 * Render the attestation body.
	 *
	 * @param	TCPDF|TCPDI				$pdf				PDF
	 * @param	PowerPlantPVAttestation	$object				Attestation
	 * @param	Translate				$outputlangs		Output lang
	 * @param	int						$defaultFontSize	Default font size
	 * @return	void
	 */
	protected function renderBody($pdf, $object, $outputlangs, $defaultFontSize)
	{
		$data = $this->collectCommissioningData($object, $outputlangs);
		$width = $this->contentWidth();

		// Title band
		$y = $pdf->GetY();
		$this->setColor($pdf, 'fill', self::CARD);
		$pdf->RoundedRect($this->marge_gauche, $y, $width, 19, 2.65, '1111', 'F');
		$this->setColor($pdf, 'fill', self::ACCENT);
		$pdf->RoundedRect($this->marge_gauche + 5, $y + 4, 1.6, 11, 0.8, '1111', 'F');
		$this->setColor($pdf, 'text', self::NAVY);
		$pdf->SetFont('', 'B', 17);
		$pdf->MultiCell($width - 16, 8, $outputlangs->convToOutputCharset(dol_strtoupper($outputlangs->transnoentities('AttestationTypeMiseEnService'))), 0, 'L', false, 1, $this->marge_gauche + 10, $y + 2.6);
		$this->setColor($pdf, 'text', self::MUTED);
		$pdf->SetFont('', '', 10);
		$pdf->MultiCell($width - 16, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationCommissioningSubtitle', $this->plain($data['project'], $outputlangs))), 0, 'L', false, 1, $this->marge_gauche + 10, $y + 10.6);
		$pdf->SetY($y + 22);

		// Declaration
		$this->renderRichParagraph($pdf, $outputlangs, $outputlangs->transnoentities(
			'AttestationCommissioningIntro',
			$this->strong($data['writer_name'], $outputlangs),
			$this->strong($data['writer_function'], $outputlangs),
			$this->strong($data['company'], $outputlangs),
			$this->strong($data['project'], $outputlangs)
		));
		$pdf->Ln(3);

		// Key figures
		$this->renderKpis($pdf, $outputlangs, array(
			array($data['installed_power'], 'AttestationCommissioningKpiPower'),
			array($data['commissioning_date'], 'AttestationCommissioningDate'),
			array($data['modules_count'], 'AttestationCommissioningKpiModules'),
			array($data['inverters_count'], 'AttestationCommissioningKpiInverters'),
		));

		// Site, grid connection and installation
		$this->renderPanels($pdf, $outputlangs, array(
			array(
				'title' => $outputlangs->transnoentities('AttestationCommissioningSite'),
				'ratio' => 0.34,
				'rows' => array(
					array($outputlangs->transnoentities('AttestationCommissioningSiteAddress'), $data['site_address']),
					array($outputlangs->transnoentities('AttestationCommissioningClient'), $data['client']),
					array($outputlangs->transnoentities('AttestationCommissioningPdl'), $data['pdl']),
					array($outputlangs->transnoentities('AttestationCommissioningRacc'), $data['racc']),
				),
			),
			array(
				'title' => $outputlangs->transnoentities('AttestationCommissioningInstallation'),
				'ratio' => 0.36,
				'rows' => array(
					array($outputlangs->transnoentities('AttestationCommissioningModulesModel'), $data['modules_models']),
					array($outputlangs->transnoentities('AttestationCommissioningInvertersModel'), $data['inverters_models']),
					array($outputlangs->transnoentities('AttestationCommissioningConnectionType'), $data['connection_type']),
				),
			),
		));

		// Checks done before commissioning
		$this->renderTitle($pdf, $outputlangs->transnoentities('AttestationCommissioningChecksTitle'), 30);
		$this->renderMuted($pdf, $outputlangs->transnoentities('AttestationCommissioningChecksIntro'));
		$checks = array();
		foreach (PowerPlantPVAttestationTypes::COMMISSIONING_CHECKS as $field => $label) {
			$result = (int) $object->{$field};
			$result = isset(PowerPlantPVAttestationTypes::COMMISSIONING_RESULTS[$result]) ? $result : 0;
			$checks[] = array($outputlangs->transnoentities($label), $outputlangs->transnoentities(PowerPlantPVAttestationTypes::COMMISSIONING_RESULTS[$result]), $result);
		}
		$this->renderPanels($pdf, $outputlangs, array(array('title' => '', 'ratio' => 0.78, 'rows' => $checks)));

		// Compliance
		$this->renderTitle($pdf, $outputlangs->transnoentities('AttestationCommissioningComplianceTitle'), 12);
		$this->renderPanels($pdf, $outputlangs, array(array('title' => '', 'ratio' => 0.65, 'rows' => array(
			array($outputlangs->transnoentities('AttestationConsuelNumber'), $data['consuel_number']),
			array($outputlangs->transnoentities('AttestationConsuelDate'), $data['consuel_date']),
		))));
		$this->renderSignature($pdf, $object, $outputlangs, $data);
	}

	/**
	 * Collect every value printed in the attestation.
	 *
	 * @param	PowerPlantPVAttestation	$object			Attestation
	 * @param	Translate				$outputlangs	Output lang
	 * @return	array<string,string>					Printable values, '' when unknown
	 */
	protected function collectCommissioningData($object, $outputlangs)
	{
		$derived = powerplantpvAttestationGetDerivedData($object, $outputlangs);
		$data = array(
			'writer_name' => (string) $derived['writer_name'],
			'writer_function' => (string) $derived['writer_function'],
			'company' => (string) $derived['installer_name'],
			'place' => (string) $derived['place'],
			'site_address' => (string) $derived['site_full_address'],
			'project' => '',
			'client' => '',
			'commissioning_date' => !empty($object->date_completion) ? dol_print_date($object->date_completion, 'day', 'tzuser', $outputlangs) : '',
			'attestation_date' => !empty($object->date_attestation) ? dol_print_date($object->date_attestation, 'day', 'tzuser', $outputlangs) : '',
			'installed_power' => '',
			'modules_count' => '',
			'modules_models' => '',
			'inverters_count' => '',
			'inverters_models' => '',
			'connection_type' => '',
			'consuel_number' => (string) $object->consuel_number,
			'consuel_date' => !empty($object->date_consuel) ? dol_print_date($object->date_consuel, 'day', 'tzuser', $outputlangs) : '',
			'pdl' => '',
			'racc' => '',
		);

		if (!empty($object->fk_soc)) {
			require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
			$thirdparty = new Societe($this->db);
			if ($thirdparty->fetch((int) $object->fk_soc) > 0
				&& in_array((int) $thirdparty->entity, array_map('intval', explode(',', getEntity('societe'))), true)) {
				$data['client'] = (string) $thirdparty->name;
			}
		}
		if (!empty($object->fk_project) && isModEnabled('project')) {
			require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
			$project = new Project($this->db);
			if ($project->fetch((int) $object->fk_project) > 0
				&& in_array((int) $project->entity, array_map('intval', explode(',', getEntity('project'))), true)) {
				$data['project'] = (string) $project->title;
			}
		}

		if (!empty($object->fk_powerplant)) {
			dol_include_once('/powerplantpv/class/powerplant.class.php');
			$powerplant = new PowerPlant($this->db);
			if ($powerplant->fetch((int) $object->fk_powerplant) > 0
				&& in_array((int) $powerplant->entity, array_map('intval', explode(',', getEntity('powerplant'))), true)) {
				if ($data['project'] === '') {
					$data['project'] = (string) ($powerplant->label !== '' ? $powerplant->label : $powerplant->ref);
				}
				if ($powerplant->installed_power !== null && $powerplant->installed_power !== '') {
					$data['installed_power'] = price($powerplant->installed_power).' kWc';
				}
				$data['connection_type'] = (string) PowerPlant::getConnectionTypeLabel($powerplant->connection_type, $outputlangs);
				$data['pdl'] = (string) $powerplant->prm_pdl_number;
				$data['racc'] = (string) $powerplant->connection_request_number;
			}
		}

		$equipment = $this->summarizeEquipment($object, $outputlangs);
		foreach (array('MODULE' => 'modules', 'INVERTER' => 'inverters') as $type => $prefix) {
			if (!empty($equipment[$type]['count'])) {
				$data[$prefix.'_count'] = (string) $equipment[$type]['count'];
				$data[$prefix.'_models'] = implode("\n", $equipment[$type]['models']);
			}
		}

		return $data;
	}

	/**
	 * Count modules and inverters of the snapshot and list their brand and model.
	 * A composition line without serial numbers stands for its whole quantity.
	 *
	 * @param	PowerPlantPVAttestation	$object			Attestation
	 * @param	Translate				$outputlangs	Output lang
	 * @return	array<string,array{count:int,models:array<int,string>}>	Summary by equipment type
	 */
	protected function summarizeEquipment($object, $outputlangs)
	{
		$summary = array('MODULE' => array('count' => 0, 'models' => array()), 'INVERTER' => array('count' => 0, 'models' => array()));
		if (empty($object->lines)) {
			return $summary;
		}

		$byLine = array();
		foreach ($object->lines as $line) {
			$equipment = powerplantpvAttestationResolveEquipmentLine($line, $outputlangs);
			$type = (string) $equipment['equipment_type'];
			if (!isset($summary[$type])) {
				continue;
			}
			$model = trim(implode(' ', array_unique(array_filter(array(trim((string) $equipment['brand']), trim((string) $equipment['product_ref']))))));
			if ($model === '') {
				$model = trim((string) $equipment['designation']);
			}
			if ($model !== '' && !in_array($model, $summary[$type]['models'], true)) {
				$summary[$type]['models'][] = $model;
			}
			$key = !empty($line->fk_powerplant_line) ? (int) $line->fk_powerplant_line : 0;
			if ($key > 0) {
				$byLine[$key] = array('type' => $type, 'units' => ($byLine[$key]['units'] ?? 0) + 1);
			} else {
				$summary[$type]['count']++;
			}
		}

		$quantities = $this->fetchCompositionQuantities(array_keys($byLine), (int) $object->entity);
		foreach ($byLine as $lineId => $line) {
			$summary[$line['type']]['count'] += max($line['units'], (int) round($quantities[$lineId] ?? 0));
		}

		return $summary;
	}

	/**
	 * Return the quantity of power plant composition lines.
	 *
	 * @param	array<int,int>	$lineIds	Composition line ids
	 * @param	int				$entity		Attestation entity
	 * @return	array<int,float>			Quantity by line id
	 */
	protected function fetchCompositionQuantities($lineIds, $entity)
	{
		$quantities = array();
		$lineIds = array_filter(array_map('intval', $lineIds));
		if (empty($lineIds)) {
			return $quantities;
		}
		$sql = "SELECT rowid, qty FROM ".$this->db->prefix()."powerplantpv_powerplantcomp";
		$sql .= " WHERE rowid IN (".$this->db->sanitize(implode(',', $lineIds)).")";
		$sql .= " AND entity = ".((int) $entity);
		$resql = $this->db->query($sql);
		if (!$resql) {
			return $quantities;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$quantities[(int) $obj->rowid] = (float) $obj->qty;
		}
		$this->db->free($resql);

		return $quantities;
	}

	/**
	 * Return the signature and company stamp image files.
	 *
	 * @param	PowerPlantPVAttestation	$object	Attestation
	 * @return	array{0:string,1:string}		Signature file, stamp file
	 */
	protected function getSignatureImages($object)
	{
		$signature = '';
		$directory = realpath(powerplantpvAttestationGetDocumentUploadDir($object));
		if ($directory !== false && !empty($object->signature_file)) {
			$root = powerplantpvAttestationGetDocumentRootDir($object->entity);
			$file = $root !== '' ? realpath($root.'/'.$object->signature_file) : false;
			if ($file !== false && str_starts_with($file, $directory.DIRECTORY_SEPARATOR)) {
				$signature = $file;
			}
		}

		return array($signature, (string) powerplantpvAttestationGetCompanyStampFile($object->entity));
	}

	/**
	 * Section title with the blue accent bar of the reports.
	 *
	 * @param	TCPDF|TCPDI	$pdf		PDF
	 * @param	string		$title		Title
	 * @param	float		$keepWith	Height kept on the same page after the title
	 * @return	void
	 */
	protected function renderTitle($pdf, $title, $keepWith = 20)
	{
		$this->ensureSpace($pdf, 9 + $keepWith);
		$y = $pdf->GetY();
		$this->setColor($pdf, 'fill', self::ACCENT);
		$pdf->RoundedRect($this->marge_gauche, $y + 0.6, 1.6, 4.8, 0.8, '1111', 'F');
		$this->setColor($pdf, 'text', self::NAVY);
		$pdf->SetFont('', 'B', 13);
		$pdf->MultiCell($this->contentWidth() - 4, 6, $this->currentOutputLangs->convToOutputCharset($title), 0, 'L', false, 1, $this->marge_gauche + 4, $y);
		$pdf->Ln(1.5);
		$this->resetStyle($pdf);
	}

	/**
	 * Small grey text.
	 *
	 * @param	TCPDF|TCPDI	$pdf	PDF
	 * @param	string		$text	Text
	 * @return	void
	 */
	protected function renderMuted($pdf, $text)
	{
		$this->setColor($pdf, 'text', self::MUTED);
		$pdf->SetFont('', '', 9);
		$pdf->MultiCell(0, 4.5, $this->currentOutputLangs->convToOutputCharset($text), 0, 'L');
		$pdf->Ln(1.5);
		$this->resetStyle($pdf);
	}

	/**
	 * Paragraph containing HTML-escaped values built with strong().
	 *
	 * @param	TCPDF|TCPDI	$pdf			PDF
	 * @param	Translate	$outputlangs	Output lang
	 * @param	string		$html			Paragraph
	 * @return	void
	 */
	protected function renderRichParagraph($pdf, $outputlangs, $html)
	{
		$pdf->SetFont('', '', 9.5);
		$this->setColor($pdf, 'text', self::TEXT);
		$pdf->writeHTMLCell($this->contentWidth(), 0, $this->marge_gauche, $pdf->GetY(), $outputlangs->convToOutputCharset('<span style="line-height:1.45;">'.$html.'</span>'), 0, 1, false, true, 'L', true);
		$this->resetStyle($pdf);
	}

	/**
	 * Light blue cards with a large value and a small caption.
	 *
	 * @param	TCPDF|TCPDI								$pdf			PDF
	 * @param	Translate								$outputlangs	Output lang
	 * @param	array<int,array{0:string,1:string}>		$cards			Value and caption key
	 * @return	void
	 */
	protected function renderKpis($pdf, $outputlangs, $cards)
	{
		$columns = count($cards);
		$gap = 3;
		$height = 16;
		$width = ($this->contentWidth() - ($columns - 1) * $gap) / $columns;
		$this->ensureSpace($pdf, $height + 4);
		$y = $pdf->GetY();
		foreach (array_values($cards) as $i => $card) {
			$x = $this->marge_gauche + $i * ($width + $gap);
			$this->setColor($pdf, 'fill', self::CARD);
			$pdf->RoundedRect($x, $y, $width, $height, 2.65, '1111', 'F');
			$missing = ($card[0] === '');
			$value = $outputlangs->convToOutputCharset($missing ? $outputlangs->transnoentities('AttestationNotProvided') : $card[0]);
			$this->setColor($pdf, 'text', $missing ? self::MISSING : self::NAVY);
			$pdf->SetFont('', 'B', $missing ? 11 : 14);
			if ($pdf->GetStringWidth($value) > $width - 6) {
				$pdf->SetFont('', 'B', 11);
			}
			$pdf->MultiCell($width - 6, 0, $value, 0, 'L', false, 1, $x + 3, $y + 2);
			$this->setColor($pdf, 'text', self::MUTED);
			$pdf->SetFont('', '', 7.5);
			$pdf->MultiCell($width - 6, 0, $outputlangs->convToOutputCharset(dol_strtoupper($outputlangs->transnoentities($card[1]))), 0, 'L', false, 1, $x + 3, $y + 9.5);
		}
		$pdf->SetY($y + $height + 4);
		$this->resetStyle($pdf);
	}

	/**
	 * One or two white rounded panels side by side, each with label/value rows.
	 * A row with a check result in its third item uses a badge.
	 *
	 * @param	TCPDF|TCPDI								$pdf			PDF
	 * @param	Translate								$outputlangs	Output lang
	 * @param	array<int,array<string,mixed>>			$panels			Panels (title, ratio, rows)
	 * @return	void
	 */
	protected function renderPanels($pdf, $outputlangs, $panels)
	{
		$gap = 3;
		$columns = count($panels);
		$width = ($this->contentWidth() - ($columns - 1) * $gap) / $columns;
		$layouts = array();
		$height = 0;
		foreach ($panels as $i => $panel) {
			$layouts[$i] = $this->layoutPanel($pdf, $outputlangs, $panel, $width);
			$height = max($height, $layouts[$i]['height']);
		}
		$this->ensureSpace($pdf, $height + 4);
		$y = $pdf->GetY();
		foreach ($layouts as $i => $layout) {
			$this->drawPanel($pdf, $layout, $this->marge_gauche + $i * ($width + $gap), $y, $width, $height);
		}
		$pdf->SetY($y + $height + 4);
		$this->resetStyle($pdf);
	}

	/**
	 * Measure a panel.
	 *
	 * @param	TCPDF|TCPDI				$pdf			PDF
	 * @param	Translate				$outputlangs	Output lang
	 * @param	array<string,mixed>		$panel			Panel
	 * @param	float					$width			Panel width
	 * @return	array<string,mixed>						Layout
	 */
	protected function layoutPanel($pdf, $outputlangs, $panel, $width)
	{
		$inner = $width - 8;
		$labelWidth = $inner * (float) $panel['ratio'];
		$badgeWidth = 0;
		$pdf->SetFont('', 'B', 8.5);
		foreach ($panel['rows'] as $row) {
			if (isset($row[2])) {
				$badgeWidth = max($badgeWidth, $pdf->GetStringWidth($outputlangs->convToOutputCharset($row[1])) + 10);
			}
		}
		$valueWidth = max($inner - $labelWidth, $badgeWidth + 2);
		$labelWidth = $inner - $valueWidth;
		$layout = array('title' => $outputlangs->convToOutputCharset((string) $panel['title']), 'labelWidth' => $labelWidth, 'valueWidth' => $valueWidth, 'badgeWidth' => $badgeWidth, 'rows' => array());
		$height = 3;
		if ($layout['title'] !== '') {
			$pdf->SetFont('', 'B', 11);
			$layout['titleHeight'] = $pdf->getStringHeight($inner, $layout['title']);
			$height += $layout['titleHeight'] + 2;
		}
		foreach ($panel['rows'] as $row) {
			$result = $row[2] ?? null;
			$missing = $result === null && trim((string) $row[1]) === '';
			$label = $outputlangs->convToOutputCharset((string) $row[0]);
			$value = $outputlangs->convToOutputCharset($missing ? $outputlangs->transnoentities('AttestationNotProvided') : (string) $row[1]);
			$pdf->SetFont('', '', 9.5);
			$rowHeight = $pdf->getStringHeight($labelWidth, $label);
			$pdf->SetFont('', 'B', 9.5);
			$valueHeight = $result === null ? $pdf->getStringHeight($valueWidth, $value) : 5.2;
			$rowHeight = max(7.5, max($rowHeight, $valueHeight) + 2.8);
			$layout['rows'][] = array('label' => $label, 'value' => $value, 'result' => $result, 'missing' => $missing, 'height' => $rowHeight);
			$height += $rowHeight;
		}
		$layout['height'] = $height + 3;

		return $layout;
	}

	/**
	 * Draw a measured panel.
	 *
	 * @param	TCPDF|TCPDI				$pdf		PDF
	 * @param	array<string,mixed>		$layout		Layout from layoutPanel()
	 * @param	float					$x			X
	 * @param	float					$y			Y
	 * @param	float					$width		Width
	 * @param	float					$height		Height
	 * @return	void
	 */
	protected function drawPanel($pdf, $layout, $x, $y, $width, $height)
	{
		$this->setColor($pdf, 'draw', self::BORDER);
		$pdf->SetFillColor(255, 255, 255);
		$pdf->SetLineWidth(0.25);
		$pdf->RoundedRect($x, $y, $width, $height, 2.65, '1111', 'DF');
		$cy = $y + 3;
		if ($layout['title'] !== '') {
			$this->setColor($pdf, 'text', self::NAVY);
			$pdf->SetFont('', 'B', 11);
			$pdf->MultiCell($width - 8, $layout['titleHeight'], $layout['title'], 0, 'L', false, 1, $x + 4, $cy);
			$cy += $layout['titleHeight'] + 2;
		}
		$count = count($layout['rows']);
		// The shorter side-by-side panel uses its extra height across its rows.
		$extraRowHeight = $count > 0 ? ($height - $layout['height']) / $count : 0;
		foreach ($layout['rows'] as $j => $row) {
			$rh = $row['height'] + $extraRowHeight;
			$vx = $x + 4 + $layout['labelWidth'];
			$this->setColor($pdf, 'text', self::LABEL);
			$pdf->SetFont('', '', 9.5);
			$this->renderTableCellText($pdf, $layout['labelWidth'], $rh, $row['label'], $x + 4, $cy);
			if ($row['result'] !== null) {
				$pdf->SetFont('', 'B', 8.5);
				$bw = $layout['badgeWidth'];
				$bh = 5.2;
				$bx = $vx + $layout['valueWidth'] - $bw;
				$by = $cy + ($rh - $bh) / 2;
				list($fill, $text) = self::CHECK_BADGE_COLORS[$row['result']];
				$this->setColor($pdf, 'fill', $fill);
				$pdf->RoundedRect($bx, $by, $bw, $bh, $bh / 2, '1111', 'F');
				$hasCheck = $row['result'] === 1;
				if ($hasCheck) {
					$this->drawCheck($pdf, $bx + 3.3, $by + $bh / 2, 2.5, $text);
				}
				$this->setColor($pdf, 'text', $text);
				$inset = $hasCheck ? 5 : 0;
				$textHeight = $pdf->getStringHeight($bw - $inset, $row['value']);
				$pdf->MultiCell($bw - $inset, 0, $row['value'], 0, 'C', false, 0, $bx + $inset, $by + ($bh - $textHeight) / 2);
			} else {
				$this->setColor($pdf, 'text', $row['missing'] ? self::MISSING : self::NAVY);
				$pdf->SetFont('', 'B', 9.5);
				$this->renderTableCellText($pdf, $layout['valueWidth'], $rh, $row['value'], $vx, $cy);
			}
			$cy += $rh;
			if ($j < $count - 1) {
				$this->setColor($pdf, 'draw', self::BORDER);
				$pdf->Line($x + 4, $cy, $x + $width - 4, $cy);
			}
		}
	}


	/**
	 * Place, date and signatory on the left, signature and company stamp frames on the right.
	 *
	 * @param	TCPDF|TCPDI				$pdf			PDF
	 * @param	PowerPlantPVAttestation	$object			Attestation
	 * @param	Translate				$outputlangs	Output lang
	 * @param	array<string,string>	$data			Printed values
	 * @return	void
	 */
	protected function renderSignature($pdf, $object, $outputlangs, $data)
	{
		$gap = 4;
		$boxHeight = 24;
		$full = $this->contentWidth();
		$textWidth = $full * 0.36;
		$boxWidth = ($full - $textWidth - 2 * $gap) / 2;
		$text = $outputlangs->transnoentities('AttestationCommissioningDoneAt', $this->strong($data['place'], $outputlangs), $this->strong($data['attestation_date'], $outputlangs));
		$text .= '<br><br>'.$outputlangs->transnoentities('AttestationCommissioningForCompany', $this->strong($data['company'], $outputlangs));
		$text .= '<br>'.$this->strong($data['writer_name'], $outputlangs).'<br>'.$this->escape($this->plain($data['writer_function'], $outputlangs), $data['writer_function'] === '');
		$plainText = html_entity_decode(strip_tags(str_replace('<br>', "
", $text)), ENT_QUOTES, 'UTF-8');
		$pdf->SetFont('', 'B', 9.5);
		$textHeight = $pdf->getStringHeight($textWidth, $outputlangs->convToOutputCharset($plainText)) * 1.2;
		$closingHeight = max($boxHeight + 6, $textHeight + 1) + 6;
		if (!empty($object->date_signature)) {
			$closingHeight += 5;
		}
		$this->renderRemarksBeforeSignature($pdf, $outputlangs, (string) $object->note_public, 9.5, $closingHeight, function () use ($pdf, $outputlangs) {
			$this->renderTitle($pdf, $outputlangs->transnoentities('AttestationCommissioningRemarks'), 0);
		});
		$pdf->Ln(3);
		list($signature, $stamp) = $this->getSignatureImages($object);
		$y = $pdf->GetY();
		$pdf->SetFont('', '', 9.5);
		$this->setColor($pdf, 'text', self::TEXT);
		$pdf->writeHTMLCell($textWidth, 0, $this->marge_gauche, $y + 1, $outputlangs->convToOutputCharset('<span style="line-height:1.4;">'.$text.'</span>'), 0, 1, false, true, 'L', true);
		$textBottom = $pdf->GetY();

		foreach (array(array('AttestationSignature', $signature), array('AttestationCompanySeal', $stamp)) as $i => $box) {
			$x = $this->marge_gauche + $textWidth + $gap + $i * ($boxWidth + $gap);
			$this->setColor($pdf, 'text', self::LABEL);
			$pdf->SetFont('', 'B', 9);
			$pdf->MultiCell($boxWidth, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities($box[0])), 0, 'L', false, 1, $x, $y);
			$pdf->SetDrawColor(218, 228, 240);
			$pdf->SetFillColor(255, 255, 255);
			$pdf->RoundedRect($x, $y + 6, $boxWidth, $boxHeight, 2.65, '1111', 'DF');
			if ($box[1] !== '') {
				$this->renderImageInNativeSignatureBox($pdf, $box[1], $x, $y + 6, $boxWidth, $boxHeight);
			}
		}
		$pdf->SetY(max($textBottom, $y + 6 + $boxHeight) + 2);
		if (!empty($object->date_signature)) {
			$this->setColor($pdf, 'text', self::MUTED);
			$pdf->SetFont('', '', 8.5);
			$pdf->MultiCell(0, 4.5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationSignedOn', dol_print_date($object->date_signature, 'dayhour', 'tzuser', $outputlangs))), 0, 'R');
		}
		$this->resetStyle($pdf);
	}

	/**
	 * Vector check mark, visible with every configured PDF font.
	 *
	 * @param	TCPDF|TCPDI		$pdf	PDF
	 * @param	float			$x		Center X
	 * @param	float			$y		Center Y
	 * @param	float			$width	Width
	 * @param	array<int,int>	$color	RGB
	 * @return	void
	 */
	protected function drawCheck($pdf, $x, $y, $width, $color)
	{
		$pdf->Polygon(array(
			$x - $width * 0.5, $y,
			$x - $width * 0.37, $y - $width * 0.13,
			$x - $width * 0.13, $y + $width * 0.10,
			$x + $width * 0.37, $y - $width * 0.38,
			$x + $width * 0.5, $y - $width * 0.24,
			$x - $width * 0.13, $y + $width * 0.36,
		), 'F', array(), $color);
	}

	/**
	 * Bold navy value for HTML paragraphs, red "Not provided" when empty.
	 *
	 * @param	string		$value			Value
	 * @param	Translate	$outputlangs	Output lang
	 * @return	string						HTML
	 */
	protected function strong($value, $outputlangs)
	{
		if (trim((string) $value) === '') {
			return '<span style="color:#c80000;font-weight:bold;">'.dol_escape_htmltag($outputlangs->transnoentities('AttestationNotProvided')).'</span>';
		}

		return '<span style="color:#17284a;font-weight:bold;">'.dol_escape_htmltag((string) $value).'</span>';
	}

	/**
	 * Escape a plain value, red when missing.
	 *
	 * @param	string	$value		Value
	 * @param	bool	$missing	Missing marker
	 * @return	string				HTML
	 */
	protected function escape($value, $missing)
	{
		return $missing ? '<span style="color:#c80000;font-weight:bold;">'.dol_escape_htmltag($value).'</span>' : dol_escape_htmltag($value);
	}

	/**
	 * Value or translated fallback, as plain text.
	 *
	 * @param	string		$value			Value
	 * @param	Translate	$outputlangs	Output lang
	 * @return	string						Text
	 */
	protected function plain($value, $outputlangs)
	{
		return trim((string) $value) === '' ? $outputlangs->transnoentities('AttestationNotProvided') : (string) $value;
	}

	/**
	 * @return	float	Printable width
	 */
	protected function contentWidth()
	{
		return $this->page_largeur - $this->marge_gauche - $this->marge_droite;
	}

	/**
	 * @param	TCPDF|TCPDI		$pdf	PDF
	 * @param	string			$what	text|fill|draw
	 * @param	array<int,int>	$rgb	Color
	 * @return	void
	 */
	protected function setColor($pdf, $what, $rgb)
	{
		if ($what === 'text') {
			$pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
		} elseif ($what === 'fill') {
			$pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
		} else {
			$pdf->SetDrawColor($rgb[0], $rgb[1], $rgb[2]);
		}
	}

	/**
	 * @param	TCPDF|TCPDI	$pdf	PDF
	 * @return	void
	 */
	protected function resetStyle($pdf)
	{
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetDrawColor(0, 0, 0);
		$pdf->SetFont('', '', 10);
	}
}
