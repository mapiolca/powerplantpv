<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
// Renders the commissioning attestation PDF with the real TCPDF of a Dolibarr core, without database:
// both layouts, with mixed check results and with or without a CONSUEL reference.
if (PHP_SAPI !== 'cli') { exit(1); }
$core = $argv[1] ?? '';
$module = $argv[2] ?? dirname(__DIR__);
$outputDir = $argv[3] ?? '';
if (!is_file($core.'/core/lib/functions.lib.php') || basename($module) !== 'powerplantpv') {
	fwrite(STDERR, "Usage: php test/attestation_mise_en_service_pdf_native.php CORE_HTDOCS MODULE_DIR_NAMED_POWERPLANTPV [OUTPUT_DIR]\n"); exit(1);
}
$dataRoot = sys_get_temp_dir().'/ppv-commissioning-'.bin2hex(random_bytes(6));
mkdir($dataRoot.'/mycompany/logos', 0777, true);
mkdir($dataRoot.'/powerplantpv/setup', 0777, true);
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
	'powerplantpv' => (object) array('dir_output' => $dataRoot.'/powerplantpv', 'multidir_output' => array(1 => $dataRoot.'/powerplantpv')),
	'modules_parts' => array(),
	'tzuserinputkey' => 'tzserver', 'format_date_short' => '%d/%m/%Y', 'format_date_text_short' => '%d %b %Y',
);
$langs = new Translate('', $conf);
$langs->setDefaultLang('fr_FR');
$langs->loadLangs(array('main', 'companies', 'powerplantpv@powerplantpv'));
$user = new class {
	public $id = 1;
	public $admin = 0;
	public $job = 'Responsable de mise en service';
	public function getFullName($langs) { return 'Technicien Test'; }
};
$mysoc = (object) array(
	'name' => 'Société de recette', 'logo' => '', 'logo_small' => '',
	'town' => 'Le Teich', 'zip' => '33470', 'address' => '10 rue de la Centrale',
	'idprof2' => '', 'tva_intra' => '', 'country_id' => 0,
	'phone' => '', 'phone_mobile' => '', 'fax' => '', 'email' => '', 'url' => '',
);
// No database: the paper format falls back to A4, the fixture supplies every value.
$db = new class {
	public function escape($value) { return addslashes((string) $value); }
	public function sanitize($value) { return (string) $value; }
	public function prefix() { return MAIN_DB_PREFIX; }
	public function query($sql) { return false; }
	public function lasterror() { return ''; }
};
require_once $core.'/core/lib/pdf.lib.php';
require_once $module.'/core/modules/attestation/doc/pdf_attestation_mise_en_service.modules.php';

function commissioningPdfText($file)
{
	$raw = file_get_contents($file);
	preg_match_all('/\[((?:\\\\.|[^\]\\\\])*)\]\s*TJ|\(((?:\\\\.|[^)\\\\])*)\)\s*Tj/s', $raw, $matches, PREG_SET_ORDER);
	$text = '';
	foreach ($matches as $match) {
		preg_match_all('/\(((?:\\\\.|[^)\\\\])*)\)/s', $match[1] !== '' ? $match[1] : '('.($match[2] ?? '').')', $pieces);
		$text .= stripcslashes(implode('', $pieces[1]));
	}
	return mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
}


require_once $module.'/core/modules/attestation/doc/pdf_attestation_mise_en_service_standard.modules.php';
require_once $module.'/class/powerplantpvattestation.class.php';

$object = new PowerPlantPVAttestation($db);
$object->entity = 1;
$object->type_code = PowerPlantPVAttestationTypes::TYPE_MISE_EN_SERVICE;
$object->date_attestation = dol_mktime(12, 0, 0, 11, 24, 2026);
$object->date_completion = dol_mktime(12, 0, 0, 11, 20, 2026);
$object->check_visual = 1;
$object->check_strings = 2;
$object->check_protections = 1;
$object->check_inverters = 1;
$object->check_production = 4;
$object->check_injection_limit = 3;
$object->check_supervision = null;

if ($outputDir !== '') {
	$conf->powerplantpv->multidir_output[1] = rtrim($outputDir, '/\\');
}
foreach (PowerPlantPVAttestationTypes::COMMISSIONING_MODELS as $modelName) {
	if (!$object->isDocumentModelCompatible($modelName)) {
		throw new RuntimeException('Incompatible commissioning model: '.$modelName);
	}
	$class = 'pdf_'.$modelName;
	$model = new $class($db);
	foreach (array('filled', 'missing', 'long-remarks') as $case) {
		$object->ref = $modelName.'-'.$case;
		$object->note_public = 'Réserve de recette';
		if ($case === 'long-remarks') {
			for ($i = 1; $i <= 95; $i++) {
				$object->note_public .= "
".sprintf('Observation de contrôle %03d : point à vérifier avant la levée des réserves.', $i);
			}
			$object->note_public .= "
Dernière observation à signer.";
		}
		$object->consuel_number = $case === 'filled' ? 'CONS-2026-12345' : null;
		$object->date_consuel = $case === 'filled' ? dol_mktime(12, 0, 0, 11, 12, 2026) : null;
		if ($model->write_file($object, $langs) !== 1) {
			throw new RuntimeException($class.' did not generate a PDF: '.$model->error);
		}
		$file = $model->result['fullpath'];
		$pdfText = commissioningPdfText($file);
		foreach (array('Non conforme', 'Non réalisé', 'Sans objet', 'Non renseigné', 'Réserve de recette') as $needle) {
			if (strpos($pdfText, $needle) === false) {
				throw new RuntimeException($class.': missing PDF text '.$needle);
			}
		}
		if ((strpos($pdfText, 'CONS-2026-12345') !== false) !== ($case === 'filled')) {
			throw new RuntimeException($class.': incorrect CONSUEL reference for '.$case);
		}
		if ($case === 'long-remarks') {
			for ($i = 1; $i <= 95; $i++) {
				$observation = sprintf('Observation de contrôle %03d', $i);
				if (substr_count($pdfText, $observation) !== 1) {
					throw new RuntimeException($class.': missing or repeated '.$observation);
				}
			}
			if (strpos($pdfText, 'Dernière observation à signer.') === false) {
				throw new RuntimeException($class.': missing the end of the remarks.');
			}
		}
		echo $file.PHP_EOL;
	}
}
if ($object->isDocumentModelCompatible('attestation_bridage_statique')) {
	throw new RuntimeException('A commissioning attestation accepted a curtailment layout.');
}
$object->type_code = PowerPlantPVAttestationTypes::TYPE_BRIDAGE_STATIQUE_ONDULEUR;
if ($object->isDocumentModelCompatible(PowerPlantPVAttestationTypes::COMMISSIONING_DEFAULT_MODEL)) {
	throw new RuntimeException('A curtailment attestation accepted a commissioning layout.');
}
$setting = PowerPlantPVAttestationTypes::COMMISSIONING_MODEL_SETTING;
$commissioningType = PowerPlantPVAttestationTypes::TYPE_MISE_EN_SERVICE;
foreach (array('attestation_mise_en_service', 'attestation_bridage_statique') as $configured) {
	$conf->global->{$setting} = $configured;
	$expected = $configured === 'attestation_mise_en_service' ? $configured : PowerPlantPVAttestationTypes::COMMISSIONING_DEFAULT_MODEL;
	if (PowerPlantPVAttestationTypes::getModelForType($commissioningType) !== $expected) {
		throw new RuntimeException('Unexpected commissioning default for '.$configured);
	}
}
echo 'Commissioning PDF checks passed on Dolibarr '.DOL_VERSION.' / PHP '.PHP_VERSION.'.'.PHP_EOL;
