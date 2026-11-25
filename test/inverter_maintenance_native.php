<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
// Native classes and formatting, no ERP bootstrap and no database connection.
if (PHP_SAPI !== 'cli') { exit(1); }
$core = $argv[1] ?? '';
$module = $argv[2] ?? dirname(__DIR__);
if (!is_file($core.'/core/lib/functions.lib.php') || basename($module) !== 'powerplantpv') {
	fwrite(STDERR, "Usage: php test/inverter_maintenance_native.php CORE_HTDOCS MODULE_DIR_NAMED_POWERPLANTPV\n"); exit(1);
}
define('DOL_DOCUMENT_ROOT', $core);
define('DOL_URL_ROOT', '');
define('DOL_MAIN_URL_ROOT', 'https://example.test');
define('MAIN_DB_PREFIX', 'native_test_');
if (is_file(DOL_DOCUMENT_ROOT.'/version.inc.php')) {
	require_once DOL_DOCUMENT_ROOT.'/version.inc.php';
} else {
	// Older core versions define their version in filefunc.inc.php; do not bootstrap an instance.
	$versionSource = file_get_contents(DOL_DOCUMENT_ROOT.'/filefunc.inc.php');
	if (!is_string($versionSource) || !preg_match('/define\([\'"]DOL_VERSION[\'"],\s*[\'"]([^\'"]+)/', $versionSource, $versionMatch)) {
		throw new RuntimeException('Cannot identify the Dolibarr core version.');
	}
	define('DOL_VERSION', $versionMatch[1]);
}

require_once $core.'/core/lib/functions.lib.php';
require_once $core.'/core/class/translate.class.php';
$conf = (object) array('entity' => 2, 'global' => (object) array(), 'file' => (object) array('dol_document_root' => array('main' => $core, 'test' => dirname($module)), 'dol_url_root' => array('main' => '', 'test' => '/custom')));
$langs = new Translate('', $conf);
$langs->defaultlang = 'fr_FR';
$langs->tab_translate = array('SeparatorDecimal' => ',', 'SeparatorThousand' => ' ', 'PowerPlantPVConsultPowerplant' => 'Consulter la centrale', 'PowerPlantPVMultipleStatuses' => 'États multiples');
require_once $module.'/class/powerplantpvpowerplantsummary.class.php';
$checks = 0;
function nativeMaintenanceCheck($condition, $message)
{
	$GLOBALS['checks']++;
	if (!$condition) { throw new RuntimeException($message); }
}
$summary = (new ReflectionClass('PowerPlantPVPowerPlantSummary'))->newInstanceWithoutConstructor();
$method = new ReflectionMethod($summary, 'buildMaterialRows');
$method->setAccessible(true);
$base = array('id' => 1, 'fk_product' => 10, 'category_code' => 'MODULE', 'product_ref' => 'PV-450', 'product_label' => 'Panneau <450>', 'qty' => 1, 'pmax' => 450, 'ac_nominal_power' => null, 'ac_max_power' => null, 'serial_number' => 'SECRET-SERIES', 'status' => 'Actif');
$rows = $method->invoke($summary, array($base, $base), array('MODULE'), $langs, 'module', 22);
nativeMaintenanceCheck(count($rows) === 1, 'Native summary consolidates product rows');
nativeMaintenanceCheck(price2num($rows[0]['qty']) == 2, 'Native locale quantity equals two');
nativeMaintenanceCheck($rows[0]['power'] === '450 W', 'Unit power remains 450 W');
nativeMaintenanceCheck($rows[0]['serial'] === 'Consulter la centrale' && $rows[0]['serial_powerplant_id'] === 22, 'Native summary carries a safe plant target, not serial data');
nativeMaintenanceCheck(strpos(json_encode($rows), 'SECRET-SERIES') === false, 'No serial leaked in display rows');
// Legacy frozen reports keep their historical values but use the current material presentation.
$legacyRow = array('product' => 'PV-450 - Panneau historique', 'qty' => '1,00', 'power' => '450 W', 'serial' => 'OLD-SERIAL', 'status' => 'Actif');
$legacy = array('version' => 1, 'source_powerplant_id' => 22, 'sections' => array(
	array('code' => 'material', 'type' => 'grouped_tables', 'tables' => array(
		array('title_key' => 'PowerPlantPDFModules', 'rows' => array($legacyRow, $legacyRow)),
	)),
	array('code' => 'composition', 'rows' => array($legacyRow, array('product' => 'CABLE - Accessoire', 'qty' => '1,00', 'serial' => 'CABLE-SERIAL'))),
));
$legacyJson = json_encode($legacy);
$legacyDisplay = $summary->decodeSnapshot($legacyJson);
$legacyRows = $legacyDisplay['sections'][0]['tables'][0]['rows'];
nativeMaintenanceCheck(count($legacyRows) === 1 && price2num($legacyRows[0]['qty']) == 2, 'Legacy frozen duplicate material rows consolidate with their original quantities');
nativeMaintenanceCheck($legacyRows[0]['serial'] === 'Consulter la centrale' && $legacyRows[0]['serial_powerplant_id'] === 22, 'Legacy material serials become a link to their own plant');
nativeMaintenanceCheck(count($legacyDisplay['sections'][1]['rows']) === 1 && $legacyDisplay['sections'][1]['rows'][0]['product'] === 'CABLE - Accessoire', 'Legacy composition no longer repeats panels and inverters; accessories remain');
nativeMaintenanceCheck($legacyJson === json_encode($legacy), 'Legacy decoding does not rewrite the stored report');
nativeMaintenanceCheck($summary->decodeSnapshot(json_encode($legacyDisplay)) === $legacyDisplay, 'Legacy display normalization is idempotent');
$legacy['sections'][0]['tables'][0]['rows'][0]['qty'] = '1,234.00';
$legacy['sections'][0]['tables'][0]['rows'][1]['qty'] = '2.00';
$legacy['sections'][0]['tables'][0]['rows'][1]['status'] = 'Remplacé';
$legacyDisplay = $summary->decodeSnapshot(json_encode($legacy));
$legacyRows = $legacyDisplay['sections'][0]['tables'][0]['rows'];
nativeMaintenanceCheck(price2num($legacyRows[0]['qty']) == 1236, 'Legacy English formatted quantities survive a French reader');
nativeMaintenanceCheck($legacyRows[0]['status'] === 'États multiples', 'Legacy consolidation reports mixed statuses without splitting the reference');
$modern = array('version' => 2, 'source_powerplant_id' => 22, 'sections' => array(array('code' => 'material', 'tables' => array(array('rows' => array($legacyRow))))));
nativeMaintenanceCheck($summary->decodeSnapshot(json_encode($modern)) === $modern, 'Current snapshots are not reformatted by the legacy adapter');

$other = $base; $other['status'] = 'Inactif';
$rows = $method->invoke($summary, array($base, $other), array('MODULE'), $langs, 'module', 22);
nativeMaintenanceCheck($rows[0]['status'] === 'États multiples', 'Mixed equipment statuses consolidated');
// Read the native descriptor migration on a simulated existing schema.
require_once $module.'/core/modules/modPowerPlantPV.class.php';
nativeMaintenanceCheck(method_exists('CommonObject', 'indexFile') && function_exists('getMultidirOutput'), 'Native document contracts exist on the tested core');
$descriptor = (new ReflectionClass('modPowerPlantPV'))->newInstanceWithoutConstructor();
nativeMaintenanceCheck(method_exists($descriptor, 'init'), 'Descriptor loads before activation without installed tables');

require_once $module.'/class/powerplantpvreportbuilder.class.php';
$builder = (new ReflectionClass('PowerPlantPVReportBuilder'))->newInstanceWithoutConstructor();
$cleaning = (new ReflectionClass('PowerPlantPVReportField'))->newInstanceWithoutConstructor();
$cleaning->field_type = 'yesno';
$cleaning->field_code = 'BOX_JOINTS_HINGES_CLEANING';
$assignField = new ReflectionMethod($builder, 'assignSubmittedValueToField');
$assignField->setAccessible(true);
nativeMaintenanceCheck($assignField->invoke($builder, $cleaning, 'not_applicable') && $cleaning->value_text === 'not_applicable', 'Save retains the third cabinet cleaning answer');
nativeMaintenanceCheck(!$assignField->invoke($builder, $cleaning, null) && !$assignField->invoke($builder, $cleaning, array('0')) && $cleaning->value_text === 'not_applicable', 'Missing or invalid input preserves the existing third answer');
nativeMaintenanceCheck($assignField->invoke($builder, $cleaning, '0') && $cleaning->value_text === '0', 'Explicit No remains distinct from not applicable');
$guess = new ReflectionMethod($builder, 'guessEquipmentType'); $guess->setAccessible(true);
nativeMaintenanceCheck($guess->invoke($builder, (object) array('category_code'=>'ONDACC','inverter_id'=>20,'product_ref'=>'INV-CABLE','product_label'=>'Accessoire onduleur')) === 'OTHER', 'Native accessory category is not an installed inverter');
nativeMaintenanceCheck($guess->invoke($builder, (object) array('category_code'=>'MODULE','product_ref'=>'PV','product_label'=>'Panel')) === 'PANEL', 'Native PV category identifies panel serial policy');
$measure = (new ReflectionClass('PowerPlantPVReportDcMeasure'))->newInstanceWithoutConstructor();
$measure->stable_key = 'plant:line:1:dc:inverter:1:mppt:1:pvinput:1';
$measure->inverter_label = 'Original'; $measure->mppt_number = 1; $measure->pv_input_number = 1; $measure->is_connected = 1;
$legacyVoltage = clone $measure;
$legacyVoltage->is_connected = 1;
$legacyVoltage->insulation_positive_to_ground = '0.00000000';
$legacyVoltage->insulation_negative_to_ground = '-18.25000000';
$legacyVoltage->negative_to_ground_voltage = '17.00000000';
nativeMaintenanceCheck($legacyVoltage->hasLegacyPoleVoltages(), 'A legacy zero can fill a missing voltage');
$legacyVoltage->adoptLegacyPoleVoltages();
nativeMaintenanceCheck($legacyVoltage->positive_to_ground_voltage === '0.00000000'
	&& $legacyVoltage->insulation_positive_to_ground === null, 'Reclassification preserves zero and removes the incorrect historical unit');
nativeMaintenanceCheck($legacyVoltage->negative_to_ground_voltage === '17.00000000'
	&& $legacyVoltage->insulation_negative_to_ground === '-18.25000000', 'A separately recorded voltage and its historical resistance are preserved');
$legacyVoltage->negative_to_ground_voltage = null;
$legacyVoltage->is_connected = 0;
$legacyVoltage->adoptLegacyPoleVoltages();
nativeMaintenanceCheck($legacyVoltage->negative_to_ground_voltage === null, 'An unused input is ineligible');
$legacyVoltage->is_connected = 1;
$legacyVoltage->adoptLegacyPoleVoltages();
$legacyVoltage->adoptLegacyPoleVoltages();
nativeMaintenanceCheck($legacyVoltage->negative_to_ground_voltage === '-18.25000000'
	&& $legacyVoltage->insulation_negative_to_ground === null && !$legacyVoltage->hasLegacyPoleVoltages(), 'Negative voltage is reclassified exactly once');
$assign = new ReflectionMethod($builder, 'assignSubmittedValueToDcMeasure'); $assign->setAccessible(true);
$assign->invoke($builder, $measure, array('inverter_label' => 'Falsified', 'mppt_number' => '99', 'pv_input_number' => '99', 'is_connected' => '0', 'open_circuit_voltage' => '0', 'polarity_checked' => '0'));
nativeMaintenanceCheck($measure->inverter_label === 'Original' && $measure->mppt_number === 1 && $measure->pv_input_number === 1 && $measure->is_connected === 1, 'Posted hidden controls cannot alter generated equipment topology');
nativeMaintenanceCheck($measure->open_circuit_voltage === 0.0 && $measure->polarity_checked === 0, 'Explicit measurement zero remains valid');
$measure->stable_key = 'section:dc:manual:1';
$assign->invoke($builder, $measure, array('inverter_label' => 'Manual', 'mppt_number' => '2', 'pv_input_number' => '3'));
nativeMaintenanceCheck($measure->inverter_label === 'Manual' && $measure->mppt_number === 2 && $measure->pv_input_number === 3, 'Manual topology remains editable');

class NativeMaintenanceSchemaDb
{
 public $columns = array(); public $index = false; public $ddl = array();
 public $evidenceExpiryNullable = false;
 public function prefix() { return 'long_test_prefix_'; }
 public function sanitize($value) { return $value; }
 public function escape($value) { return $value; }
 public function idate($value) { return '2000-01-01 00:00:00'; }
 public function query($sql) {
  if (strpos($sql, "WHERE Field = 'expires_at' AND `Null` = 'NO'") !== false) {
   return (object) array('count' => $this->evidenceExpiryNullable ? 0 : 1);
  }
  if (strpos($sql, 'MODIFY COLUMN expires_at datetime DEFAULT NULL') !== false) {
   $this->evidenceExpiryNullable = true;
   $this->ddl[] = $sql;
   return true;
  }
  if (strpos($sql, 'CREATE TABLE') === 0) { return true; }
  if (preg_match("/SHOW COLUMNS FROM ([a-z_]+) LIKE '((?:equipment|inverter)_(?:marker|identification)|fk_product|fk_report_illustration)'/", $sql, $m)) {
   return (object) array('count' => isset($this->columns[$m[1].'.'.$m[2]]) ? 1 : 0);
  }
  if (strpos($sql, 'SHOW INDEX') === 0 && strpos($sql, 'uk_ppv_comp_marker') !== false) { return (object) array('count' => $this->index ? 1 : 0); }
  if (strpos($sql, 'ALTER TABLE') === 0) { $this->index = true; $this->ddl[] = $sql; return true; }
  if (strpos($sql, 'SHOW ') === 0) { return (object) array('count' => 1); }
  throw new RuntimeException('Unexpected migration mutation: '.$sql);
 }
 public function num_rows($result) { return $result->count; }
 public function free($result) {}
 public function DDLAddField($table, $field, $definition) { $this->columns[$table.'.'.$field] = true; $this->ddl[] = $table.'.'.$field; return 1; }
 public function lasterror() { return 'Simulated schema error'; }
}
$schema = new NativeMaintenanceSchemaDb();
$dbProperty = new ReflectionProperty($descriptor, 'db'); $dbProperty->setAccessible(true); $dbProperty->setValue($descriptor, $schema);
$migration = new ReflectionMethod($descriptor, 'ensureGeneratedReportSchema'); $migration->setAccessible(true);
nativeMaintenanceCheck($migration->invoke($descriptor) === 1 && count($schema->columns) === 8 && $schema->index, 'Existing schema receives six nullable identity columns and the module product relationship and a unique marker index');
nativeMaintenanceCheck($schema->evidenceExpiryNullable, 'Existing evidence links support permanent expiry settings');
$count = count($schema->ddl);
nativeMaintenanceCheck($migration->invoke($descriptor) === 1 && count($schema->ddl) === $count, 'Repeated activation does not recreate columns or indexes');

// Exercise the actual PDF HTML renderers with a capture object, not a rendered PDF.
if (is_file(DOL_DOCUMENT_ROOT.'/core/lib/html.lib.php')) { require_once DOL_DOCUMENT_ROOT.'/core/lib/html.lib.php'; }
require_once $module.'/core/modules/fichinter/doc/pdf_powerplantpvreport.modules.php';
$pdfModel = (new ReflectionClass('pdf_powerplantpvreport'))->newInstanceWithoutConstructor();
$pdfModel->page_hauteur = 297;
$languageProperty = new ReflectionProperty($pdfModel, 'currentOutputLangs'); $languageProperty->setAccessible(true); $languageProperty->setValue($pdfModel, $langs);
$capture = new class {
 public $html = '';
 public function GetY() { return 20; }
 public function SetY($y) {}
 public function getPage() { return 1; }
 public function setPage($page) {}
 public function setPageOrientation(...$args) {}
 public function writeHTMLCell($w, $h, $x, $y, $html, ...$rest) { $this->html .= $html; }
 public function Ln($height) {}
};
$_SERVER['HTTP_HOST'] = 'example.test'; $_SERVER['SERVER_PORT'] = 443; $_SERVER['HTTPS'] = 'on';
$render = new ReflectionMethod($pdfModel, 'renderPowerplantSummaryTable'); $render->setAccessible(true);
$render->invokeArgs($pdfModel, array(&$capture, array(array('key'=>'qty','label_key'=>'Qty'), array('key'=>'serial','label_key'=>'SerialNumber')), $rows, $langs));
nativeMaintenanceCheck(strpos($capture->html, '<table') !== false && strpos($capture->html, 'href=') === false && strpos($capture->html, 'SerialNumber') === false && strpos($capture->html, 'SECRET-SERIES') === false, 'PDF material table omits the serial column and its link reserved for the appendix');
$capture->html = '';
$render->invokeArgs($pdfModel, array(&$capture, array(array('key'=>'power','label_key'=>'PowerPlantInstalledPower')), array(
	array('power' => 'AC nom. 100 000,00 / AC max. 110 000,00 W'),
	array('power' => 'AC nom. 900.00 / AC max. 1,250.00 W'),
	array('power' => '450 W'),
	array('power' => '1 000 W'),
), $langs));
$powerText = strip_tags($capture->html);
nativeMaintenanceCheck(strpos($powerText, '100 kW / AC max. 110 kW') !== false
	&& strpos($powerText, '900 W / AC max. 1,25 kW') !== false
	&& strpos($powerText, '450 W') !== false
	&& strpos($powerText, '1 000 W') !== false, 'PDF material powers scale per value across snapshot locales while preserving 1000 W and lower');
$capture->html = '';
$render = new ReflectionMethod($pdfModel, 'renderEquipmentSummary'); $render->setAccessible(true);
$panel = (object) array('equipment_type'=>'PANEL','equipment_brand'=>'Brand','equipment_model'=>'450 W','product_label'=>'Panel','product_ref'=>'PV-450','equipment_ref'=>'PV-450','serial_number'=>'SECRET-PANEL-SERIAL','equipment_position'=>'');
$render->invokeArgs($pdfModel, array(&$capture, $panel, $langs));
nativeMaintenanceCheck(strpos($capture->html, 'SECRET-PANEL-SERIAL') === false, 'Individual panel control summary does not leak a panel serial');
$capture->html = '';
$dcRows = array();
foreach (array(2, 1) as $id) {
	$dc = (new ReflectionClass('PowerPlantPVReportDcMeasure'))->newInstanceWithoutConstructor();
	$dc->fk_powerplant = 21; $dc->fk_inverter = $id;
	$dc->inverter_marker = 'OND0'.$id; $dc->inverter_identification = 'Roof '.$id;
	$dc->inverter_serial = 'SERIAL-'.$id; $dc->mppt_number = 1; $dc->pv_input_number = 1;
	$dc->string_ref = 'PV00'; $dc->is_connected = 1;
	$dcRows[] = $dc;
}
$dcRender = new ReflectionMethod($pdfModel, 'renderDcMeasures'); $dcRender->setAccessible(true);
$dcRender->invokeArgs($pdfModel, array(&$capture, $dcRows, $langs));
nativeMaintenanceCheck(substr_count($capture->html, '<thead>') === 2 && strpos($capture->html, 'nobr="true"') !== false, 'PDF groups repeat their headers and keep measurement rows together');
nativeMaintenanceCheck(strpos($capture->html, 'OND01 | Roof 1 | SERIAL-1') < strpos($capture->html, 'OND02 | Roof 2 | SERIAL-2'), 'PDF physical inverter groups retain their complete identity in natural order');
require_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmfiles.class.php';
$db = $schema;
$filterError = '';
$filterSql = forgeSQLFromUniversalSearchCriteria('(t.entity:=:2) AND (t.src_object_id:=:99)', $filterError);
nativeMaintenanceCheck($filterError === '' && strpos($filterSql, 't.entity') !== false && method_exists('EcmFiles', 'fetchAll'), 'Owner-specific ECM lookup uses a native v20+ USF contract');

require_once $module.'/class/powerplantpvequipmentnumbering.class.php';
class NativeEquipmentCounterDb
{
 public $last = null; public $nb = 0; public $queries = array();
 public function prefix() { return MAIN_DB_PREFIX; }
 public function escape($value) { return addslashes($value); }
 public function sanitize($value) { return $value; }
 public function query($sql) { $this->queries[] = $sql; return (object) array('nb' => $this->nb, 'val' => $this->last); }
 public function fetch_object($row) { return $row; }
 public function lasterror() { return 'Database failure'; }
}
$counterDb = new NativeEquipmentCounterDb();
$inverterModel = new PowerPlantPVEquipmentNumbering($counterDb, 'inverter');
$stringModel = new PowerPlantPVEquipmentNumbering($counterDb, 'string');
$scope = (object) array('id' => 21, 'entity' => 2, 'fk_inverter' => 31);
nativeMaintenanceCheck($inverterModel->getNextValue($scope, 'OND{00}') === 'OND01', 'Native two-digit inverter counter starts at one');
nativeMaintenanceCheck(!isset($conf->global->MAIN_COUNTER_WITH_LESS_3_DIGITS), 'Short-counter allowance does not leak to other modules');
nativeMaintenanceCheck($stringModel->getNextValue($scope, 'PV{00}') === 'PV00', 'First chain starts at zero');
$counterDb->last = '0'; $counterDb->nb = 1;
nativeMaintenanceCheck($stringModel->getNextValue($scope, 'PV{00}') === 'PV01', 'PV00 advances to PV01');
$counterDb->last = '9';
nativeMaintenanceCheck($stringModel->getNextValue($scope, 'TOIT-{000}') === 'TOIT-010', 'Free prefix and native padding retained');
$queries = implode('\n', $counterDb->queries);
nativeMaintenanceCheck(strpos($queries, 'fk_powerplant = 21 AND entity = 2 AND fk_inverter = 31') !== false, 'Chain counter constrained by plant, owner and installed inverter');
$counterDb->last = null; $counterDb->nb = 0; $scope->fk_inverter = 32;
nativeMaintenanceCheck($stringModel->getNextValue($scope, 'PV{00}') === 'PV00', 'Another inverter starts its own zero counter');
nativeMaintenanceCheck($stringModel->getNextValue($scope, 'PV00') === '' && $stringModel->error === 'ErrorBadMask', 'Invalid mask rejected before allocation');
$counterDb->last = '99'; $counterDb->nb = 1;
nativeMaintenanceCheck($inverterModel->getNextValue($scope, 'OND{00}') === '', 'Exhausted native counter fails instead of truncating or duplicating');

require_once $module.'/class/powerplantpvindexreading.class.php';
$reading = (new ReflectionClass('PowerPlantPVIndexReading'))->newInstanceWithoutConstructor();
$readingDb = new class {
 public $sql = '';
 public function prefix() { return MAIN_DB_PREFIX; }
 public function sanitize($value) { return $value; }
 public function escape($value) { return str_replace("'", "''", $value); }
 public function query($sql) { $this->sql = $sql; return (object) array(); }
 public function fetch_object($result) { return null; }
 public function free($result) {}
};
$reading->db = $readingDb;
$reading->fetchLatestValue(6, 'INVERTER_PRODUCTION', '', 14, 114);
nativeMaintenanceCheck(strpos($readingDb->sql, 'e.fk_powerplant_line') !== false && strpos($readingDb->sql, '= 114)') !== false
	&& strpos($readingDb->sql, 'fk_powerplant_line = 114 OR EXISTS') !== false, 'Inverter history includes manual and report readings of the same installed inverter');
nativeMaintenanceCheck(strpos($readingDb->sql, 'e.entity = '.MAIN_DB_PREFIX.'powerplantpv_index_reading.entity') !== false && strpos($readingDb->sql, 'fk_report <> 14') !== false, 'Reading history keeps owner entity and excludes the current report');
$reading->fetchLatestValue(6, 'INVERTER_PRODUCTION');
nativeMaintenanceCheck(strpos($readingDb->sql, 'COALESCE(fk_report_equipment, 0) = 0 AND fk_powerplant_line IS NULL') !== false, 'Legacy global history excludes manual and report readings attributed to an inverter');
$reading->fetchPreviousValue(6, 'INVERTER_INJECTION', '2026-11-18 00:00:00', 20, 115);
nativeMaintenanceCheck(strpos($readingDb->sql, 'COALESCE(t.fk_powerplant_line, NULLIF(e.fk_powerplant_line, 0), e.fk_source_equipment) = 115') !== false, 'Delta history follows the same inverter across manual and report sources');
$reading->fetchLatestValue(6, 'PRODUCTION_INDEX');
nativeMaintenanceCheck(strpos($readingDb->sql, 'e.fk_powerplant_line') === false, 'Common plant meter history is not filtered by inverter');
nativeMaintenanceCheck(method_exists('CommonObject', 'setValueFrom'), 'Native single-field update API exists on the tested core');

require_once $module.'/class/actions_powerplantpv.class.php';
$langs->tab_translate['PowerPlantPVInterventionReportPdfName'] = 'Rapport de maintenance photovoltaïque';
$langs->tab_translate['PowerPlantPVAutocontrolPdfName'] = 'Rapport d’autocontrôle C&I';
$langs->tab_translate['PowerPlantPVResidentialPdfName'] = 'Rapport d’autocontrôles Résidentiel';
$models = array(
	'powerplantpvreport' => 'powerplantpvreport',
	PowerPlantPVReportParts::PDF_MODEL => PowerPlantPVReportParts::PDF_MODEL,
	PowerPlantPVReportTemplate::PDF_MODEL_RESIDENTIAL => PowerPlantPVReportTemplate::PDF_MODEL_RESIDENTIAL,
	'soleil' => 'soleil',
);
(new ActionsPowerplantpv())->getListOfModels(array('type' => 'ficheinter', 'list' => &$models));
nativeMaintenanceCheck($models === array(
	'powerplantpvreport' => 'Rapport de maintenance photovoltaïque',
	PowerPlantPVReportParts::PDF_MODEL => 'Rapport d’autocontrôle C&I',
	PowerPlantPVReportTemplate::PDF_MODEL_RESIDENTIAL => 'Rapport d’autocontrôles Résidentiel',
	'soleil' => 'soleil',
), 'The selector lists all three separate translated models alongside other native choices');

require_once $module.'/core/modules/fichinter/doc/pdf_powerplantpvreport.modules.php';
class NativeMaintenancePdfLifecycle extends pdf_powerplantpvreport
{
 public $footers = array();
 public function __construct() { $this->heightforfooter = 30; $this->page_hauteur = 297; $this->currentOutputLangs = $GLOBALS['langs']; }
 protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0) { $this->footers[] = array($pdf->getPage(), $hidefreetext, $pdf->automatic, $pdf->textColor); return 0; }
 public function renderTest(&$pdf) { $pdf->SetTextColor(18, 128, 92); $this->writeHtml($pdf, '<p>Two-page simulated HTML</p>'); $pdf->SetTextColor(18, 128, 92); $this->renderFooters($pdf, null, $this->currentOutputLangs); }
}
$pdfCapture = new class {
 public $page = 1; public $y = 20; public $automatic = true; public $textColor = array();
 public function GetY() { return $this->y; }
 public function SetY($y) { $this->y = $y; }
 public function getPage() { return $this->page; }
 public function setPage($page) { $this->page = $page; }
 public function writeHTMLCell(...$args) { $this->page = 2; $this->y = 50; }
 public function SetAutoPageBreak($auto, $margin = 0) { $this->automatic = $auto; }
 public function setPageOrientation($orientation, $auto, $margin) { $this->automatic = $auto; }
 public function getFontFamily() { return 'helvetica'; }
 public function getFontStyle() { return ''; }
 public function getFontSizePt() { return 9; }
 public function SetFont(...$args) {}
 public function SetTextColor(...$args) { $this->textColor = $args; }
};
$pdfLifecycle = new NativeMaintenancePdfLifecycle();
$pdfLifecycle->renderTest($pdfCapture);
nativeMaintenanceCheck($pdfLifecycle->footers === array(array(1, 1, false, array(0, 0, 0)), array(2, 0, false, array(0, 0, 0))), 'Intermediate and final native footers start in black regardless of the last content color');
nativeMaintenanceCheck($pdfCapture->page === 2 && $pdfCapture->y === 50 && $pdfCapture->automatic, 'HTML rendering restores its current page, cursor and reserved bottom margin');
$field = (new ReflectionClass('PowerPlantPVReportField'))->newInstanceWithoutConstructor();
$field->field_type = 'conformity_so_valid_obs'; $field->value_text = '-1';
$langs->tab_translate['PowerPlantPVDataNotFilled'] = 'Donnée non renseignée';
nativeMaintenanceCheck(PowerPlantPVReportPdfDataset::formatFieldValue($field, $langs) === 'Donnée non renseignée', 'Missing conformity selection never appears as raw -1 in the PDF');
require_once $core.'/fichinter/class/fichinter.class.php';
require_once $module.'/class/powerplantpvinterventionnature.class.php';
$natureDb = new class {
	public $model;
	public $sql;
	public function prefix() { return MAIN_DB_PREFIX; }
	public function sanitize($value) { return $value; }
	public function query($sql) { $this->sql = $sql; return true; }
	public function fetch_object($result) { return (object) array('model_pdf' => $this->model); }
	public function free($result) {}
};
$nature = (new ReflectionClass('PowerPlantPVInterventionNature'))->newInstanceWithoutConstructor();
$nature->db = $natureDb;
$newIntervention = (new ReflectionClass('Fichinter'))->newInstanceWithoutConstructor();
$newIntervention->id = 91;
$newIntervention->entity = null;
nativeMaintenanceCheck($nature->configuredPdfModel($newIntervention) === '', 'A newly created intervention without a model allows the native default');
$natureDb->model = 'powerplantpvreport';
nativeMaintenanceCheck($nature->configuredPdfModel($newIntervention) === 'powerplantpvreport'
	&& strpos($natureDb->sql, 'fi.rowid = 91 AND fi.entity IN (2)') !== false
	&& strpos($natureDb->sql, 'n.entity IN (2)') !== false, 'A v20 creation gets its PDF model from the persisted intervention in the active entity');

$mc = new class {
	public $mode = 0;
	public function getEntity($table, $shared, $object = null) {
		if ($table !== 'c_powerplantpv_intervention_nature') {
			return 2;
		}
		return $this->mode === 0 ? 1 : ($this->mode === 2 && $shared ? '1,2' : 2);
	}
};
$newIntervention->entity = 2;
foreach (array(0 => '1', 1 => '2', 2 => '1,2') as $mode => $entities) {
	$mc->mode = $mode;
	nativeMaintenanceCheck($nature->configuredPdfModel($newIntervention) === 'powerplantpvreport'
		&& strpos($natureDb->sql, 'n.entity IN ('.$entities.')') !== false
		&& strpos($natureDb->sql, 'fi.rowid = 91 AND fi.entity = 2') !== false,
		'PDF model lookup follows dictionary sharing mode '.$mode.' without changing intervention ownership');
}
$mc = null;
$natureDb->model = PowerPlantPVReportParts::PDF_MODEL;
nativeMaintenanceCheck($nature->configuredPdfModel($newIntervention) === PowerPlantPVReportParts::PDF_MODEL,
	'The nature resolves the dedicated C&I model for document generation');
$statuses = PowerPlantPVReport::interventionSignatureStatuses();
foreach (array(false, true) as $technicalRequired) {
	$orders = $technicalRequired
		? array('TRC', 'TCR', 'RTC', 'RCT', 'CTR', 'CRT')
		: array('TC', 'CT');
	foreach ($orders as $order) {
		$observed = $statuses['STATUS_NO_SIGNATURE'];
		$completed = '';
		foreach (str_split($order) as $signer) {
			$completed .= $signer;
			if ($signer === 'C') {
				$observed = $statuses['STATUS_SIGNED_RECEIVER_ONLINE'] ?? $statuses['STATUS_SIGNED_RECEIVER'];
			}
			$observed = PowerPlantPVReport::resolveInterventionSignatureStatus(
				$statuses,
				$observed,
				str_contains($completed, 'T'),
				$technicalRequired,
				str_contains($completed, 'R')
			);
			$internal = str_contains($completed, 'T') && (!$technicalRequired || str_contains($completed, 'R'));
			$thirdParty = str_contains($completed, 'C');
			if ($internal) {
				$expected = $thirdParty ? $statuses['STATUS_SIGNED_ALL'] : $statuses['STATUS_SIGNED_SENDER'];
			} else {
				$expected = $thirdParty
					? ($statuses['STATUS_SIGNED_RECEIVER_ONLINE'] ?? $statuses['STATUS_SIGNED_RECEIVER'])
					: $statuses['STATUS_NO_SIGNATURE'];
			}
			nativeMaintenanceCheck($observed === $expected, 'Native signature state after '.$completed.' in order '.$order);
		}
		nativeMaintenanceCheck($observed === $statuses['STATUS_SIGNED_ALL'], 'All required parties produce combined status for '.$order);
	}
}
nativeMaintenanceCheck(PowerPlantPVReport::resolveInterventionSignatureStatus($statuses, $statuses['STATUS_SIGNED_RECEIVER'], true, false, false)
	=== $statuses['STATUS_SIGNED_ALL'], 'A third-party signature marked manually also combines with the technician signature');

print 'OK: '.$checks.' native checks, PHP '.PHP_VERSION.', Dolibarr '.DOL_VERSION." (no live database).\n";
