<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
// Renders the self-inspection report PDF with the real TCPDF of a Dolibarr core, without database.
// Six parts, two of them signed on their test day, and the final validation not yet given.
if (PHP_SAPI !== 'cli') { exit(1); }
$core = $argv[1] ?? '';
$module = $argv[2] ?? dirname(__DIR__);
$outputDir = $argv[3] ?? '';
if (!is_file($core.'/core/lib/functions.lib.php') || basename($module) !== 'powerplantpv') {
	fwrite(STDERR, "Usage: php test/autocontrol_report_pdf_native.php CORE_HTDOCS MODULE_DIR_NAMED_POWERPLANTPV [OUTPUT_DIR]\n"); exit(1);
}
$dataRoot = sys_get_temp_dir().'/ppv-autocontrol-'.bin2hex(random_bytes(6));
mkdir($dataRoot.'/mycompany/logos', 0777, true);
define('DOL_DOCUMENT_ROOT', $core);
define('DOL_DATA_ROOT', $dataRoot);
define('DOL_URL_ROOT', '');
define('DOL_MAIN_URL_ROOT', 'https://example.test');
define('MAIN_DB_PREFIX', 'visual_test_');
define('TCPDF_PATH', DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/');
define('TCPDI_PATH', DOL_DOCUMENT_ROOT.'/includes/tcpdi/');
if (is_file(DOL_DOCUMENT_ROOT.'/version.inc.php')) {
	require_once DOL_DOCUMENT_ROOT.'/version.inc.php';
} else {
	$versionSource = file_get_contents(DOL_DOCUMENT_ROOT.'/filefunc.inc.php');
	if (!is_string($versionSource) || !preg_match('/define\([\'"]DOL_VERSION[\'"],\s*[\'"]([^\'"]+)/', $versionSource, $versionMatch)) {
		throw new RuntimeException('Cannot identify the Dolibarr core version.');
	}
	define('DOL_VERSION', $versionMatch[1]);
}
require_once $core.'/core/lib/functions.lib.php';
if (is_file($core.'/core/lib/html.lib.php')) { require_once $core.'/core/lib/html.lib.php'; }
require_once $core.'/core/class/translate.class.php';
$conf = (object) array(
	'entity' => 1,
	'global' => (object) array('MAIN_DISABLE_PDF_COMPRESSION' => '1', 'MAIN_LANG_DEFAULT' => 'fr_FR'),
	'file' => (object) array('character_set_client' => 'UTF-8', 'dol_document_root' => array('main' => $core, 'test' => dirname($module)), 'dol_url_root' => array('main' => '', 'test' => '/custom')),
	'mycompany' => (object) array('dir_output' => $dataRoot.'/mycompany', 'multidir_output' => array(1 => $dataRoot.'/mycompany')),
	'ficheinter' => (object) array('dir_output' => $dataRoot.'/ficheinter', 'multidir_output' => array(1 => $dataRoot.'/ficheinter')),
	'powerplantpv' => (object) array('dir_output' => $dataRoot.'/powerplantpv', 'multidir_output' => array(1 => $dataRoot.'/powerplantpv')),
	'tzuserinputkey' => 'tzserver', 'format_date_short' => '%d/%m/%Y', 'format_date_text_short' => '%d %b %Y',
);
$langs = new Translate('', $conf);
$langs->setDefaultLang('fr_FR');
$langs->loadLangs(array('main', 'companies', 'interventions', 'products', 'dict', 'powerplantpv@powerplantpv'));
$user = new class {
	public $id = 1;
	public $admin = 0;
	public function getFullName($langs) { return 'Technicien Test'; }
};
$mysoc = (object) array('name' => 'Installateur test', 'logo' => '', 'logo_small' => '');
require_once $core.'/core/lib/pdf.lib.php';
require_once $module.'/core/modules/fichinter/doc/pdf_powerplantpvreport.modules.php';

$checks = 0;
function visualPdfCheck($condition, $message)
{
	$GLOBALS['checks']++;
	if (!$condition) { throw new RuntimeException($message); }
}

/** Fixture model: frozen snapshot and signatures instead of the database. */
class AutocontrolPdfFixture extends pdf_powerplantpvreport
{
	public $snapshot = array();
	public $signatures = array();
	public $signatureImage = '';
	protected function resolvePowerplantTechnicalSummarySnapshot($powerplant, $outputlangs) { return $this->snapshot; }
	protected function partsSignatures($dataset) { return $this->signatures; }
	protected function partsSignatureImage($dataset, $partCode, $role) { return $this->signatureImage; }
	protected function partsSection(&$pdf, $row, $dataset, $langs, $technician = null)
	{
		parent::partsSection($pdf, $row, $dataset, $langs, $technician);
		if ($row['section']->section_code === 'AC2_BOX') {
			// Enough room for the conclusion alone, but not the signature card.
			$pdf->SetY($this->getContentBottomY() - 44);
		}
	}
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		$pdf->SetFont('', '', 7); $pdf->SetXY($this->marge_gauche, $this->page_hauteur - 14);
		$pdf->Cell(150, 4, 'Pied de page test', 0, 0, 'L');
		$pdf->Cell(0, 4, 'Page '.$pdf->getPage(), 0, 0, 'R');
		return 10;
	}
	protected function computeFooterHeight() { return 28; }
	public static function create($issuer)
	{
		$model = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();
		$model->page_largeur = 210; $model->page_hauteur = 297; $model->format = array(210, 297);
		$model->marge_gauche = 10; $model->marge_droite = 10; $model->marge_haute = 10; $model->marge_basse = 10; $model->emetteur = $issuer;
		return $model;
	}
	public function build($object, $dataset, $file)
	{
		$pdf = $this->buildReportPdf($object, $dataset, $GLOBALS['langs'], false);
		$pages = $pdf->getNumPages();
		$pdf->Close(); $pdf->Output($file, 'F');
		return $pages;
	}
}

function acField($code, $label, $type, $value, $unit = '')
{
	$field = new stdClass();
	$field->field_code = $code; $field->field_label = $label; $field->field_type = $type; $field->unit = $unit; $field->visible_pdf = 1;
	$field->value_text = $type === 'number' ? null : (string) $value;
	$field->value_number = $type === 'number' ? $value : null;
	$field->value_date = null; $field->options_snapshot = ''; $field->files = array();
	return $field;
}
function acSelect($code, $label, $value, $options)
{
	$field = acField($code, $label, 'select', $value);
	$encoded = array();
	foreach ($options as $optionCode => $optionLabel) { $encoded[] = array('code' => $optionCode, 'label' => $optionLabel); }
	$field->options_snapshot = json_encode($encoded);
	return $field;
}
function acSection($code, $label, $position, $fields, $plant = 0, $equipment = null, $measures = array())
{
	return array('section' => (object) array('id' => $position, 'section_code' => $code, 'section_label' => $label, 'visible_pdf' => 1, 'fk_report_powerplant' => $plant, 'position' => $position), 'fields' => $fields, 'equipment' => $equipment, 'dc_measures' => $measures);
}

$results = array('CONFORM' => 'Conforme', 'NON_CONFORM' => 'Non conforme', 'RESERVES' => 'Conforme avec réserves');
$decisions = array('AUTHORIZE' => 'Autoriser les essais DC/AC', 'BLOCK' => 'Bloquer et corriger');
$general = array();
$byPlant = array();
$position = 10;
$labels = array(1 => 'Pré-contrôles visuels et documentaires', 2 => 'Essais DC', 3 => 'Essais AC et protections', 4 => 'Mise en service', 5 => 'Supervision', 6 => 'Essais fonctionnels');
foreach ($labels as $part => $label) {
	$p = 'AC'.$part.'_';
	$filled = $part <= 3;
	$general[] = acSection($p.'IDENT', 'Partie '.$part.' · Identification', $position, array(
		acField($p.'DATE', 'Date des essais', 'text', $filled ? (17 + $part).'/11/2026' : ''),
		acField($p.'HOURS', 'Heure de début / fin', 'varchar', $part === 1 ? '8h15 – 17h45' : ''),
		acField($p.'TECHNICIANS', 'Technicien(s)', 'text', 'Ancien nom saisi'),
		acField($p.'TRRT', 'TR / RT', 'text', 'Ancien visa saisi'),
	));
	if ($part === 2) {
		$general[count($general) - 1]['section']->part_ref = 'CI-DC-0042';
	}
	$position += 10;
	if ($part === 1) {
		$general[] = acSection('AC1_CHECKLIST', 'Partie 1 · Check-list', $position, array(
			acField('AC1_CHK_DOCS', 'Documents à jour', 'conformity_so_valid_obs', 'valid'),
			acField('AC1_CHK_LABELING', 'Repérage', 'conformity_so_valid_obs', 'observation'),
			acField('AC1_CHK_SAFETY', 'Sécurité', 'conformity_so_valid_obs', 'valid'),
			acField('AC1_CHECKLIST_NOTES', 'Observations / actions', 'textarea', 'Repérage du coffret CC2 à compléter.'),
		));
		$position += 10;
	}
	if ($part === 3) {
		$general[] = acSection('AC3_GRID', 'Partie 3 · Mesures réseau', $position, array(
			acField('AC3_GRID_VOLTAGE', 'Tension entre phases', 'number', null, 'V'),
			acField('AC3_GRID_FREQUENCY', 'Fréquence', 'number', 0, 'Hz'),
			acField('AC3_GRID_ALARM', 'Alarmes réseau', 'text', ''),
			acField('AC3_GRID_FILES', 'Preuves', 'file', ''),
		));
		$position += 10;
	}
	if ($part === 2) {
		$measures = array();
		foreach (range(0, 7) as $i) {
			$measure = new stdClass();
			$measure->fk_powerplant = 7; $measure->fk_inverter = 101; $measure->stable_key = 'plant:7:line:101:dc:'.$i;
			$measure->inverter_marker = 'O1'; $measure->inverter_identification = ''; $measure->inverter_ref = 'SUN2000-40K-MC0'; $measure->inverter_label = 'Onduleur HUAWEI'; $measure->inverter_serial = 'SN'.$i;
			$measure->mppt_number = intdiv($i, 2) + 1; $measure->pv_input_number = $i + 1; $measure->position = $i; $measure->string_ref = 'PV'.($i + 1); $measure->is_connected = 1;
			$measure->open_circuit_voltage = 520 + $i; $measure->polarity_checked = 1; $measure->insulation_status = 'valid';
			$measure->insulation_positive_to_ground = null; $measure->insulation_negative_to_ground = null; $measure->observation = '';
			$measure->positive_to_ground_voltage = 2; $measure->negative_to_ground_voltage = -3;
			$measures[] = $measure;
		}
		$byPlant[] = acSection('DC_ELECTRICAL_MEASURE', 'Partie 2 · Relevés par string', $position, array(), 7, null, $measures);
		$position += 10;
		$byPlant[] = acSection('AC2_BOX', 'Partie 2 · Contrôle coffret DC', $position, array(
			acField('AC2_BOX_LABELING', 'Repérage', 'conformity_so_valid_obs', 'valid'),
			acField('AC2_BOX_SWITCH', 'Sectionneur', 'conformity_so_valid_obs', 'valid'),
		), 7, (object) array('fk_powerplant_line' => 201, 'equipment_marker' => 'CC1', 'equipment_identification' => 'Coffret DC', 'equipment_ref' => 'CC1', 'equipment_label' => 'Coffret DC CC1', 'product_ref' => 'CC1', 'serial_number' => ''));
		$position += 10;
	}
	$conclusion = array(acSelect($p.'RESULT', 'Résultat', $part === 1 ? 'RESERVES' : ($filled ? 'CONFORM' : ''), $results));
	if ($part <= 2) {
		$conclusion[] = acSelect($p.'DECISION', 'Décision', $filled ? 'AUTHORIZE' : '', $decisions);
	}
	$deviations = array(1 => 'Repérage CC2 repris le jour même.', 4 => str_repeat("Écart vérifié et consigné.\n", 80).'Dernier écart consigné.');
	$conclusion[] = acField($p.'DEVIATIONS', 'Écarts / actions / re-contrôle', 'textarea', $deviations[$part] ?? '');
	$general[] = acSection($p.'CONCLUSION', 'Partie '.$part.' · Conclusion', $position, $conclusion);
	$position += 10;
}
$general[] = acSection('AC_RESERVES', 'Synthèse des réserves', $position, array(acField('AC_RESERVES_LIST', 'Réserves à lever', 'textarea', 'Partie 1 : repérage CC2, levée le 18/11/2026.')));

$plant = (object) array('id' => 7, 'fk_powerplant' => 18, 'powerplant_ref' => 'PP2611-0018', 'powerplant_label' => 'COSEC Léo Lagrange');
$dataset = array(
	'report_found' => true,
	'report' => (object) array('report_template_code' => 'autocontrol', 'report_template_label' => 'Dossier d’autocontrôle photovoltaïque', 'intervention_nature_label' => 'Autocontrôle avant mise en service', 'id' => 0, 'entity' => 1),
	'intervention' => (object) array('ref' => 'FI2611-0200'),
	'thirdparty' => (object) array('name' => 'Ville de Mérignac', 'country_code' => 'FR'),
	'project' => (object) array('ref' => 'P26074', 'title' => 'COSEC Gymnase Léo Lagrange'), 'contracts' => array(),
	'internal_contacts' => array(), 'source_services' => array(), 'powerplants' => array($plant),
	'general_sections' => $general, 'sections_by_powerplant' => array(7 => $byPlant),
);
$snapshot = array('sections' => array(array('code' => 'site', 'rows' => array(
	array('label_key' => 'PowerPlantPDFReference', 'value' => 'PP2611-0018'),
	array('label_key' => 'PowerPlantPDFInstalledPower', 'value' => '44,64 kWc'),
	array('label_key' => 'PowerPlantPDFAddress', 'value' => 'Avenue du Président René Coty, 33700 Mérignac'),
))));
$sign = static function ($name, $day) {
	return (object) array('signer_name' => $name, 'date_sign' => dol_mktime(17, 30, 0, 11, $day, 2026), 'fk_user' => crc32($name));
};
$object = (object) array('ref' => 'FI2611-0200', 'entity' => 1, 'datei' => dol_mktime(8, 0, 0, 11, 18, 2026));

$model = AutocontrolPdfFixture::create($mysoc);
$model->snapshot = $snapshot;
$model->signatures = array(
	'AC1' => array('technician' => $sign('A. Martin', 18), 'works_manager' => $sign('C. Lasserre', 18)),
	'AC2' => array('technician' => $sign('B. Durand', 19)),
);
$model->signatureImage = $dataRoot.'/signature.png';
file_put_contents($model->signatureImage, base64_decode('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'));
$file = ($outputDir !== '' ? rtrim($outputDir, '/\\') : $dataRoot).'/autocontrol.pdf';
$pages = $model->build($object, $dataset, $file);
visualPdfCheck(is_file($file) && $pages >= 8, 'The cover, six parts and final validation are rendered');
function autocontrolPdfText(string $file): string
{
	$raw = file_get_contents($file);
	preg_match_all('/\[((?:\\\\.|[^\]\\\\])*)\]\s*TJ|\(((?:\\\\.|[^)\\\\])*)\)\s*Tj/s', $raw, $matches, PREG_SET_ORDER);
	$text = '';
	foreach ($matches as $match) {
		preg_match_all('/\(((?:\\\\.|[^)\\\\])*)\)/s', $match[1] !== '' ? $match[1] : '('.($match[2] ?? '').')', $pieces);
		$text .= stripcslashes(implode('', $pieces[1]))."\n";
	}

	return mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
}
$layoutText = autocontrolPdfText($file);
$text = str_replace("\n", '', $layoutText);
foreach (array('Partie 1 / 6', 'Partie 6 / 6', 'FM-ESS-001', 'CI-DC-0042', 'A. Martin', 'B. Durand', 'Non signé', 'Validation du dossier complet', 'Conforme avec réserves') as $needle) {
	visualPdfCheck(strpos($text, $needle) !== false, 'PDF text contains '.$needle);
}
$cover = explode('Pied de page test', $layoutText, 2)[0];
visualPdfCheck(strpos($cover, 'CI-DC-0042') !== false && substr_count($text, 'CI-DC-0042') === 1
	&& strpos($text, 'FM-ESS-002') === false, 'The saved reference appears only in the cover table');
visualPdfCheck(strpos($text, 'Date d’application') === false && strpos($text, 'Version 1.0') === false,
	'No form metadata line is printed under the part titles');
// A part prints one signature only, even if a legacy row carries another role.
visualPdfCheck(strpos($text, 'C. Lasserre') === false, 'A part prints a single signature');
$signaturePages = array_filter(explode('Pied de page test', $layoutText), static function ($pageText) {
	return strpos($pageText, "\nSignature\n") !== false && preg_match('/Partie [1-6] \/ 6/', $pageText);
});
visualPdfCheck(count($signaturePages) === 6, 'Each stage has its own signature card');
foreach ($signaturePages as $pageText) {
	visualPdfCheck(
		strpos($pageText, 'Conclusion :') !== false && strpos($pageText, "\nNom\n") !== false && strpos($pageText, "\nDate\n") !== false,
		'The stage conclusion and the full signature card are on the same PDF page'
	);
}
visualPdfCheck(strpos($text, 'Dernier écart consigné.') !== false, 'Long notes are kept in full');
visualPdfCheck(strpos($text, '0 Hz') !== false, 'A zero measurement remains visible');
visualPdfCheck(strpos($text, 'Aucun enregistrement trouvé') !== false, 'The empty attachments message is kept');
visualPdfCheck(strpos($text, 'Heure de début08:15Heure de fin17:45') !== false
	&& strpos($text, 'Heure de début / fin') === false, 'Start and end times have separate PDF rows');
visualPdfCheck(PowerPlantPVReportParts::decodeHours(' / 16:30')['end'] === '16:30'
	&& PowerPlantPVReportParts::decodeHours('08:00 /')['start'] === '08:00', 'Either time can be saved independently');
visualPdfCheck(PowerPlantPVReportParts::encodeHours(array('start' => '08:00', 'end' => '16:30'), '') === '08:00 / 16:30'
	&& PowerPlantPVReportParts::encodeHours(array('start' => '24:00', 'end' => ''), '') === null,
	'Save valid times and reject invalid hours');
visualPdfCheck(PowerPlantPVReportParts::encodeHours(array('start' => '', 'end' => ''), 'Horaire à confirmer') === null
	&& PowerPlantPVReportParts::encodeHours(array('start' => '', 'end' => ''), '08:00 / 16:30') === '',
	'Keep unparseable legacy input until replaced and allow clearing structured times');
visualPdfCheck(strpos($text, 'P26074') !== false, 'The project appears on the cover');
visualPdfCheck(strpos($text, 'Technicien signataire') !== false && strpos($text, 'Ancien nom saisi') === false
	&& strpos($text, 'Ancien visa saisi') === false, 'The stage technician comes from the signature and legacy manual names are ignored');
visualPdfCheck(strpos($text, 'Responsable technique') === false, 'The technical manager card is absent when disabled');
$conf->global->POWERPLANTPV_REPORT_PDF_TECHNICAL_SERVICE_SIGNATURE = 1;
$model->signatures['FINAL']['technical'] = $sign('R. Technique', 21);
$technicalFile = dirname($file).'/autocontrol-technical.pdf';
$model->build($object, $dataset, $technicalFile);
$technicalText = autocontrolPdfText($technicalFile);
visualPdfCheck(strpos($technicalText, 'Responsable technique') !== false && strpos($technicalText, 'R. Technique') !== false,
	'The enabled technical manager card uses the recorded signature');
echo 'Autocontrol PDF: '.$pages.' pages, '.$checks.' checks OK'.($outputDir !== '' ? ' -> '.$file : '')."\n";
