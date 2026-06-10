<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/powerplantpv/core/modules/attestation/doc/pdf_attestation_base.modules.php');

/**
 * Dynamic inverter bridage attestation PDF.
 */
class pdf_attestation_bridage_dynamique extends pdf_attestation_base
{
	public function __construct($db)
	{
		parent::__construct($db);
		$this->name = 'attestation_bridage_dynamique';
		$this->titleKey = 'AttestationTypeBridageDynamiqueOnduleur';
	}

	/**
	 * Render dynamic inverter bridage attestation body.
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
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities(
			'AttestationDynamicIntro',
			$this->valueOrNotProvided($derivedData['writer_name'], $outputlangs),
			$this->valueOrNotProvided($derivedData['installer_name'], $outputlangs),
			$this->valueOrNotProvided($derivedData['installer_siret'], $outputlangs)
		)), 0, 'L');

		$pdf->Ln(4);
		$this->renderSectionTitle($pdf, $outputlangs, 'AttestationDynamicInstallationTitle', $defaultFontSize);
		$this->renderKeyValue($pdf, $outputlangs, 'AttestationDynamicProducer', $this->valueOrNotProvided($producerName, $outputlangs));
		$this->renderKeyValue($pdf, $outputlangs, 'AttestationDynamicSiteAddress', $this->valueOrNotProvided($derivedData['site_full_address'], $outputlangs));
		$this->renderKeyValue($pdf, $outputlangs, 'AttestationDynamicPrmPdlReference', $this->valueOrNotProvided($this->getPowerPlantValue($powerplant, 'prm_pdl_number'), $outputlangs));
		$this->renderKeyValue($pdf, $outputlangs, 'AttestationDynamicConnectionRequestReference', $this->valueOrNotProvided($this->getPowerPlantValue($powerplant, 'connection_request_number'), $outputlangs));
		$this->renderKeyValue($pdf, $outputlangs, 'AttestationDynamicInstalledPower', $this->valueOrNotProvided($this->formatPower($this->getPowerPlantValue($powerplant, 'installed_power'), 'kWc'), $outputlangs));
		$this->renderKeyValue($pdf, $outputlangs, 'AttestationDynamicInverterPower', $this->valueOrNotProvided($inverterPower, $outputlangs));
		$this->renderKeyValue($pdf, $outputlangs, 'AttestationDynamicAuthorizedMaxInjectionPower', $this->valueOrNotProvided($maxExportPower, $outputlangs));

		$pdf->Ln(4);
		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationDynamicLimitText')), 0, 'L');
		$pdf->Ln(2);
		$pdf->SetFillColor(245, 245, 245);
		$pdf->SetDrawColor(190, 190, 190);
		$pdf->SetFont('', 'B', $defaultFontSize + 2);
		$pdf->MultiCell(0, 8, $outputlangs->convToOutputCharset($outputlangs->transnoentities(
			'AttestationDynamicMaxInjectedPower',
			$this->valueOrNotProvided($maxExportPower, $outputlangs)
		)), 1, 'C', true);
		$pdf->SetDrawColor(0, 0, 0);
		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->Ln(2);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationDynamicMeasureControlText')), 0, 'L');

		$pdf->Ln(4);
		$this->renderSectionTitle($pdf, $outputlangs, 'AttestationDynamicEquipmentUsed', $defaultFontSize);
		$this->renderKeyValue($pdf, $outputlangs, 'AttestationDynamicInverters', $this->formatEquipmentList($this->getEquipmentLinesByType($object, 'INVERTER'), $outputlangs));
		$this->renderKeyValue($pdf, $outputlangs, 'AttestationDynamicMeteringDevice', $this->formatEquipmentList($this->getMeteringLines($object), $outputlangs));
		$this->renderKeyValue($pdf, $outputlangs, 'AttestationDynamicCommunication', $this->valueOrNotProvided($this->detectCommunication($object), $outputlangs));

		$pdf->Ln(4);
		$this->renderSectionTitle($pdf, $outputlangs, 'AttestationDynamicChecksDone', $defaultFontSize);
		$this->renderBulletList($pdf, $outputlangs, array(
			'AttestationDynamicCheckMeasureDirection',
			'AttestationDynamicCheckInverterCommunication',
			'AttestationDynamicCheckInjectionLimit',
			'AttestationDynamicCheckDynamicBridageTest',
		));

		$pdf->Ln(4);
		$this->renderSectionTitle($pdf, $outputlangs, 'AttestationDynamicResult', $defaultFontSize);
		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationDynamicResultText')), 0, 'L');

		$pdf->Ln(5);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities(
			'AttestationDynamicDoneAt',
			$this->valueOrNotProvided($derivedData['place'], $outputlangs),
			$this->valueOrNotProvided($attestationDate, $outputlangs)
		)), 0, 'L');

		$pdf->Ln(3);
		$this->renderSectionTitle($pdf, $outputlangs, 'AttestationDynamicSignerNameFunction', $defaultFontSize);
		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($this->formatSigner($derivedData, $outputlangs)), 0, 'L');

		$this->renderDynamicSignatureBlock($pdf, $object, $outputlangs, $defaultFontSize);
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
	 * Render a translated bullet list.
	 *
	 * @param	TCPDF|TCPDI			$pdf			PDF
	 * @param	Translate			$outputlangs	Output lang
	 * @param	array<int,string>	$keys			Translation keys
	 * @return	void
	 */
	protected function renderBulletList($pdf, $outputlangs, $keys)
	{
		foreach ($keys as $key) {
			$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset('- '.$outputlangs->transnoentities($key)), 0, 'L');
		}
	}

	/**
	 * Render signature and company stamp boxes.
	 *
	 * @param	TCPDF|TCPDI				$pdf				PDF
	 * @param	PowerPlantPVAttestation	$object				Attestation
	 * @param	Translate				$outputlangs		Output lang
	 * @param	int						$defaultFontSize	Default font size
	 * @return	void
	 */
	protected function renderDynamicSignatureBlock($pdf, $object, $outputlangs, $defaultFontSize)
	{
		$pdf->Ln(6);
		$this->ensureSpace($pdf, 45);
		$gap = 8;
		$boxHeight = 32;
		$boxWidth = ($this->page_largeur - $this->marge_gauche - $this->marge_droite - $gap) / 2;
		$leftX = $this->marge_gauche;
		$rightX = $leftX + $boxWidth + $gap;

		$pdf->SetFont('', 'B', $defaultFontSize);
		$pdf->Cell($boxWidth, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationSignature')), 0, 0, 'L');
		$pdf->SetX($rightX);
		$pdf->Cell($boxWidth, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AttestationDynamicCompanySeal')), 0, 1, 'L');

		$boxY = $pdf->GetY();
		$pdf->SetDrawColor(190, 190, 190);
		$pdf->Rect($leftX, $boxY, $boxWidth, $boxHeight);
		$pdf->Rect($rightX, $boxY, $boxWidth, $boxHeight);

		if (!empty($object->signature_file)) {
			$signature = powerplantpvAttestationGetDocumentRootDir($object->entity).'/'.$object->signature_file;
			if (file_exists($signature)) {
				$pdf->Image($signature, $leftX + 4, $boxY + 4, 45, 0, 'PNG');
			}
		}

		$stamp = powerplantpvAttestationGetCompanyStampFile($object->entity);
		if (file_exists($stamp)) {
			$pdf->Image($stamp, $rightX + 4, $boxY + 4, 35, 0, 'PNG');
		}
		$pdf->SetDrawColor(0, 0, 0);
		$pdf->SetY($boxY + $boxHeight + 2);

		if (!empty($object->date_signature)) {
			$pdf->SetFont('', '', $defaultFontSize - 1);
			$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities(
				'AttestationSignedOn',
				dol_print_date($object->date_signature, 'dayhour', 'tzuser', $outputlangs)
			)), 0, 'L');
		}
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
	 * Sum inverter power from equipment lines.
	 *
	 * @param	PowerPlantPVAttestation	$object	Attestation
	 * @return	float|string						Total or empty string
	 */
	protected function getInverterPower($object)
	{
		$total = 0;
		$found = 0;
		if (empty($object->lines)) {
			return '';
		}
		foreach ($object->lines as $line) {
			if ((string) $line->equipment_type !== 'INVERTER' || $line->max_power_kw === null || $line->max_power_kw === '') {
				continue;
			}
			$total += (float) $line->max_power_kw;
			$found = 1;
		}

		return $found ? $total : '';
	}

	/**
	 * Get equipment lines by type.
	 *
	 * @param	PowerPlantPVAttestation	$object	Attestation
	 * @param	string					$type	Type
	 * @return	array<int,PowerPlantPVAttestationEquipmentLine>	Lines
	 */
	protected function getEquipmentLinesByType($object, $type)
	{
		$lines = array();
		if (empty($object->lines)) {
			return $lines;
		}
		foreach ($object->lines as $line) {
			if ((string) $line->equipment_type === $type) {
				$lines[] = $line;
			}
		}

		return $lines;
	}

	/**
	 * Get metering equipment candidates.
	 *
	 * @param	PowerPlantPVAttestation	$object	Attestation
	 * @return	array<int,PowerPlantPVAttestationEquipmentLine>	Lines
	 */
	protected function getMeteringLines($object)
	{
		$lines = array();
		if (empty($object->lines)) {
			return $lines;
		}
		foreach ($object->lines as $line) {
			if ((string) $line->equipment_type === 'INVERTER') {
				continue;
			}
			$text = strtoupper((string) $line->equipment_type.' '.(string) $line->designation.' '.(string) $line->model.' '.(string) $line->manufacturer);
			if (strpos($text, 'COMPTEUR') !== false || strpos($text, 'METER') !== false || strpos($text, 'MESURE') !== false || strpos($text, 'MEASURE') !== false || strpos($text, 'CENTRALE') !== false) {
				$lines[] = $line;
			}
		}

		return $lines;
	}

	/**
	 * Format equipment lines for a PDF key/value row.
	 *
	 * @param	array<int,PowerPlantPVAttestationEquipmentLine>	$lines			Lines
	 * @param	Translate										$outputlangs	Output lang
	 * @return	string															Formatted lines
	 */
	protected function formatEquipmentList($lines, $outputlangs)
	{
		if (empty($lines)) {
			return $this->valueOrNotProvided('', $outputlangs);
		}
		$formatted = array();
		foreach ($lines as $line) {
			$parts = array();
			if (!empty($line->designation)) {
				$parts[] = (string) $line->designation;
			}
			if (!empty($line->model) && strpos((string) $line->designation, (string) $line->model) === false) {
				$parts[] = (string) $line->model;
			}
			if (!empty($line->serial_number)) {
				$parts[] = $outputlangs->transnoentities('SerialNumber').': '.(string) $line->serial_number;
			}
			if (empty($parts)) {
				continue;
			}
			$formatted[] = '- '.implode(' - ', $parts);
		}
		if (empty($formatted)) {
			return $this->valueOrNotProvided('', $outputlangs);
		}

		return implode("\n", $formatted);
	}

	/**
	 * Detect communication protocol names from equipment data.
	 *
	 * @param	PowerPlantPVAttestation	$object	Attestation
	 * @return	string							Protocol list
	 */
	protected function detectCommunication($object)
	{
		if (empty($object->lines)) {
			return '';
		}
		$protocols = array();
		foreach ($object->lines as $line) {
			$text = strtoupper((string) $line->designation.' '.(string) $line->model.' '.(string) $line->manufacturer);
			foreach (array('MODBUS' => 'Modbus', 'RS485' => 'RS485', 'RS-485' => 'RS485', 'ETHERNET' => 'Ethernet') as $needle => $label) {
				if (strpos($text, $needle) !== false) {
					$protocols[$label] = $label;
				}
			}
		}

		return implode(' / ', $protocols);
	}

	/**
	 * Format signer line.
	 *
	 * @param	array<string,mixed>	$derivedData	Derived data
	 * @param	Translate			$outputlangs	Output lang
	 * @return	string								Signer
	 */
	protected function formatSigner($derivedData, $outputlangs)
	{
		$parts = array();
		if (!empty($derivedData['writer_name'])) {
			$parts[] = (string) $derivedData['writer_name'];
		}
		if (!empty($derivedData['writer_function'])) {
			$parts[] = (string) $derivedData['writer_function'];
		}

		return $this->valueOrNotProvided(implode(' / ', $parts), $outputlangs);
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
			return $outputlangs->transnoentities('AttestationDynamicNotProvided');
		}

		return (string) $value;
	}
}
