<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

// Usage: php -d extension=pdo_sqlite test/autocontrol_report_workflow.php CORE_HTDOCS [MODULE_DIR_NAMED_POWERPLANTPV]
if (PHP_SAPI !== 'cli' || !isset($argv[1]) || !is_file($argv[1].'/core/lib/functions.lib.php')) {
	exit(1);
}
$core = realpath($argv[1]);
$module = $argv[2] ?? dirname(__DIR__);
define('DOL_DOCUMENT_ROOT', $core);
define('DOL_URL_ROOT', '');
define('MAIN_DB_PREFIX', 'workflow_');
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
require_once $core.'/core/lib/files.lib.php';
require_once $core.'/core/class/conf.class.php';
require_once $core.'/core/class/translate.class.php';
require_once $core.'/core/class/html.form.class.php';
require_once $core.'/core/db/Database.interface.php';
require_once $core.'/core/db/DoliDB.class.php';
require_once $core.'/core/db/mysqli.class.php';
require_once $core.'/user/class/user.class.php';
require_once $core.'/fichinter/class/fichinter.class.php';

$conf = new Conf();
$conf->entity = 1;
$conf->file = (object) array('character_set_client' => 'UTF-8', 'dol_document_root' => array('main' => $core, 'test' => dirname($module)));
$root = sys_get_temp_dir().'/ppv-workflow-'.bin2hex(random_bytes(5));
$directory = $root.'/report-signatures/9';
mkdir($directory, 0777, true);
$conf->powerplantpv = (object) array('multidir_output' => array(1 => $root));
$langs = new Translate('', $conf);
$langs->setDefaultLang('fr_FR');
require_once $module.'/class/powerplantpvreport.class.php';
require_once $module.'/class/powerplantpvreportparts.class.php';
require_once $module.'/class/powerplantpvresidentialreport.class.php';
require_once $module.'/class/powerplantpvreportfield.class.php';
require_once $module.'/class/powerplantpvreportfile.class.php';
require_once $module.'/lib/powerplantpv_residential.lib.php';

final class PartWorkflowDatabase extends DoliDBMysqli
{
	public PDO $pdo;

	public function __construct()
	{
		$this->pdo = new PDO('sqlite::memory:');
	}

	public function escape($value)
	{
		return str_replace("'", "''", (string) $value);
	}

	public function query($sql, $usesavepoint = 0, $type = 'auto', $result_mode = 0)
	{
		$result = $this->pdo->query($sql);
		return new ArrayIterator($result->columnCount() ? $result->fetchAll(PDO::FETCH_OBJ) : array());
	}

	public function fetch_object($resultset = null)
	{
		$row = $resultset->current();
		$resultset->next();
		return $row;
	}

	public function last_insert_id($tab, $fieldid = 'rowid')
	{
		return (int) $this->pdo->lastInsertId();
	}

	public function free($resultset = null)
	{
		return true;
	}
}

final class PartWorkflowReport extends PowerPlantPVReport
{
	public array $events = array();
	public bool $failEvent = false;

	public function readInterventionSignatureState(Fichinter $intervention, bool $lock = false): ?object
	{
		return (object) array('signed_status' => 0, 'fk_statut' => Fichinter::STATUS_VALIDATED, 'last_main_doc' => null);
	}

	public function lockUnsignedForEdit(bool $requireDraft = false): bool
	{
		return true;
	}

	public function update(User $user, $notrigger = 0)
	{
		return 1;
	}

	public function call_trigger($triggerName, $user)
	{
		$this->events[] = array($triggerName, $this->context['trigger_reason'], $this->context['actionmsg2']);
		return $this->failEvent ? -1 : 1;
	}
}

final class PartWorkflowActor extends User
{
	public bool $canManageSigned = false;

	public function hasRight($module, $permlevel1, $permlevel2 = '')
	{
		return $this->canManageSigned && array($module, $permlevel1, $permlevel2) === array('powerplantpv', 'attestation', 'manage_signed');
	}
}

$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks) {
	$checks++;
	if (!$condition) {
		throw new RuntimeException($message);
	}
};
$db = new PartWorkflowDatabase();
$report = new PartWorkflowReport($db);
$report->id = 9;
$report->entity = 1;
$report->fk_fichinter = 8;
$report->report_template_code = PowerPlantPVReportParts::TEMPLATE_CODE;
$report->status = PowerPlantPVReport::STATUS_SAVED;
$intervention = new Fichinter($db);
$intervention->id = 8;
$intervention->entity = 1;
$intervention->ref = 'FI-TEST';
$actor = new PartWorkflowActor($db);
$actor->id = 21;
$actor->socid = 0;
$parts = new PowerPlantPVReportParts($db);
$db->pdo->exec('CREATE TABLE workflow_powerplantpv_report_part_signature (
	rowid INTEGER PRIMARY KEY, entity INTEGER, fk_report INTEGER, part_code TEXT, signer_role TEXT,
	fk_user INTEGER, signer_name TEXT, date_sign TEXT, UNIQUE(entity, fk_report, part_code, signer_role)
)');
$db->pdo->exec("INSERT INTO workflow_powerplantpv_report_part_signature VALUES
	(1, 1, 9, 'AC1', 'technician', 21, 'A', '2026-11-24 10:00:00'),
	(2, 1, 9, 'AC2', 'technician', 22, 'B', '2026-11-24 10:10:00'),
	(3, 1, 9, 'FINAL', 'technical', 24, 'C', '2026-11-24 10:20:00')");
file_put_contents($directory.'/part-AC1-technician.png', 'stage');
file_put_contents($directory.'/part-AC2-technician.png', 'another stage');
file_put_contents($directory.'/part-FINAL-technical.png', 'final approval');

$proofPath = '';
try {
	$conf->use_javascript_ajax = 0;
	$form = new Form($db);
	$nativeOptions = array(
		'plants' => array(1 => 'A', 2 => 'B'),
		'products' => array(1 => array(7 => array('label' => 'MICRO-A')), 2 => array(8 => array('label' => 'MICRO-B'))),
	);
	$nativeData = array('placements' => array(), 'roofs' => array(), 'strings' => array(
		array('micro' => 1, 'fk_powerplant' => 1, 'quantity' => 2, 'maximum' => 2),
	));
	ob_start();
	foreach (array('RES_SALES', 'RES_STRINGS') as $section) {
		powerplantpvResidentialRender($section, array('data' => $nativeData, 'options' => $nativeOptions), true, $form);
	}
	$nativeFields = (string) ob_get_clean();
	$check(str_contains($nativeFields, '<div data-residential-placement-fields>') && str_contains($nativeFields, 'name="residential[placements][battery][description]"'),
		'The location descriptions are visible before JavaScript initialization');
	$check(str_contains($nativeFields, 'type="submit" name="residential_row_submit" value="add_roofs"')
		&& str_contains($nativeFields, 'type="submit" name="residential_row_submit" value="add_strings"'),
		'Roof and string additions can submit the report form');
	$check(str_contains($nativeFields, '<div data-residential-micro-fields>') && substr_count($nativeFields, ' name="residential[strings][0][serials][]"') === 2
		&& str_contains($nativeFields, 'MICRO-B') && str_contains($nativeFields, 'value="remove_serial_0_1"'),
		'The native form renders eligible plant choices and editable serial rows from the saved quantity');

	$check($parts->reopenPart($report, $intervention, $actor, 'AC1') < 0, 'The signer needs the dedicated permission');
	$actor->canManageSigned = true;
	$report->failEvent = true;
	$check($parts->reopenPart($report, $intervention, $actor, 'AC1') < 0, 'An Agenda failure rolls back reopening');
	$check(isset($parts->fetchSignatures($report)['FINAL']['technical']) && is_file($directory.'/part-AC1-technician.png'),
		'Rollback keeps original signatures and drawings');
	$report->failEvent = false;
	$report->status = PowerPlantPVReport::STATUS_SAVED;
	$actor->canManageSigned = false;
	$actor->admin = 1;
	$check($parts->reopenPart($report, $intervention, $actor, 'AC1') > 0 && $report->status === PowerPlantPVReport::STATUS_DRAFT,
		'An administrator who signed the stage can reopen it without an explicit permission');
	$active = $parts->fetchSignatures($report);
	$check(!isset($active['AC1'], $active['FINAL']) && count($active) === 1 && isset($active['AC2']['technician']),
		'Other stages remain signed and final approval is invalidated');
	$check(file_get_contents($directory.'/archived-1.png') === 'stage'
		&& file_get_contents($directory.'/archived-3.png') === 'final approval'
		&& file_get_contents($directory.'/part-AC2-technician.png') === 'another stage', 'Drawings are preserved as private archives');
	$check($parts->reopenPart($report, $intervention, $actor, 'AC1') < 0, 'Reopening cannot be replayed');
	foreach (array('created', 'stage_validated', 'signed', 'finalized') as $operation) {
		$check($report->recordLifecycleEvent($intervention, $actor, $operation, 'AC1') === 1, 'Lifecycle operation emits a trigger');
	}
	$check($report->events[1] === array('POWERPLANTPV_REPORT_UPDATE', 'stage_reopened', 'FI-TEST · Partie 1 rouverte')
		&& $report->events[2][0] === 'POWERPLANTPV_REPORT_CREATE' && $report->events[3][0] === 'POWERPLANTPV_REPORT_UPDATE',
		'Lifecycle labels identify the report and the stage and use CRUD triggers');
	$schema = file_get_contents($module.'/sql/llx_powerplantpv_report_residential.sql');
	$check(is_string($schema), 'The residential schema is present');
	$db->pdo->exec(str_replace(
		array('llx_', 'integer AUTO_INCREMENT PRIMARY KEY', ' ENGINE=innodb'),
		array(MAIN_DB_PREFIX, 'INTEGER PRIMARY KEY AUTOINCREMENT', ''),
		$schema
	));
	$first = clone $report;
	$first->report_template_code = 'autocontrol_residential';
	$second = clone $first;
	$second->entity = 2;
	$residential = new PowerPlantPVResidentialReport($db);
	$rows = array('placements' => array(), 'staff' => array('roof_user' => array(21 => 'Technicien A'), 'electrical' => array(22 => 'Technicien B')), 'roofs' => array(array('orientation' => 'SE', 'module_count' => 4)), 'strings' => array(
		array('micro' => 1, 'fk_powerplant' => 2, 'fk_product' => 7, 'reference_label' => 'MICRO-7', 'maximum' => 3, 'quantity' => 2,
			'serials' => array('SER-01', 'SER-02')),
	));
	$check($db->begin() > 0 && $residential->save($first, $rows) && $residential->save($second, $rows) && $db->commit() > 0,
		'Residential report rows are written transactionally in both entities');
	$db->pdo->exec('CREATE TABLE workflow_user (rowid INTEGER, entity INTEGER, statut INTEGER, admin INTEGER, fk_soc INTEGER, firstname TEXT, lastname TEXT, login TEXT)');
	$db->pdo->exec("INSERT INTO workflow_user VALUES (21,1,1,0,NULL,'Anne','Toit','anne'), (22,2,1,0,NULL,'Bert','Circuit','bert'),
		(23,1,0,0,NULL,'Caro','Inactif','caro'), (24,0,1,1,NULL,'Dan','Global','dan'), (25,2,1,1,NULL,'Eve','Admin','eve')");
	$db->pdo->exec('CREATE TABLE workflow_usergroup_user (rowid INTEGER, fk_user INTEGER, entity INTEGER)');
	$db->pdo->exec('INSERT INTO workflow_usergroup_user VALUES (1,21,2),(2,23,2),(3,22,1)');
	$equipment = array('powerplants' => array(array('id' => 1, 'ref' => 'PV1', 'label' => 'Fixture')));
	$check(array_keys($residential->options($equipment, 1, $actor)['staff']) === array(21),
		'With Multicompany disabled, only active users of the owner entity are available');
	$conf->modules['multicompany'] = 1;
	$check(array_keys($residential->options($equipment, 2, $actor)['staff']) === array(25, 22, 24),
		'Entity accounts and global users are available in non-transverse mode');
	$conf->global->MULTICOMPANY_TRANSVERSE_MODE = 1;
	$check(array_keys($residential->options($equipment, 2, $actor)['staff']) === array(25, 24, 21),
		'Transverse mode requires membership in the owner entity for standard users');
	unset($conf->modules['multicompany'], $conf->global->MULTICOMPANY_TRANSVERSE_MODE);
	$loaded = $residential->load($first);
	$check(is_array($loaded) && $loaded['initialized'] && (int) $loaded['strings'][0]['maximum'] === 3
		&& $loaded['strings'][0]['serials'] === array('SER-01', 'SER-02'), 'Strings reload with their original maximum and serial order');
	$check($loaded['staff'] === $rows['staff'], 'Report staff assignments reload with user ids and snapshot names');
	$rows['strings'][0]['maximum'] = 1;
	$rows['strings'][0]['serials'] = array('SER-01');
	$rows['strings'][0]['quantity'] = 1;
	$check($db->begin() > 0 && $residential->save($first, $rows) && (bool) $db->rollback(), 'A replacement can roll back');
	$check($residential->load($first)['strings'][0]['serials'] === array('SER-01', 'SER-02'), 'Rollback restores previous strings and series');
	$check($db->begin() > 0 && $residential->save($first, $rows) && $db->commit() > 0
		&& $residential->load($first)['strings'][0]['serials'] === array('SER-01')
		&& $residential->load($second)['strings'][0]['serials'] === array('SER-01', 'SER-02'),
		'Replacing a report keeps other entities and removes only its old serial rows');
	$fieldSchema = file_get_contents($module.'/sql/llx_powerplantpv_report_field.sql');
	$db->pdo->exec(str_replace(
		array('llx_', 'integer AUTO_INCREMENT PRIMARY KEY NOT NULL', ' ON UPDATE CURRENT_TIMESTAMP', ' ENGINE=innodb'),
		array(MAIN_DB_PREFIX, 'INTEGER PRIMARY KEY AUTOINCREMENT', '', ''),
		$fieldSchema
	));
	$db->pdo->exec("INSERT INTO workflow_powerplantpv_report_field (rowid, entity, fk_report, fk_report_section, stable_key, field_code, field_label, field_type)
		VALUES (5, 1, 9, 1, 'proof', 'RES_MICRO_PROOF_COMMON', 'Proof', 'file')");
	$proof = new PowerPlantPVReportFile($db);
	$proof->entity = 1;
	$proof->fk_report = 9;
	$proof->fk_report_field = 5;
	$proof->filepath = 'report/'.dol_sanitizeFileName('(PROV8)').'/field_5/'.str_repeat('a', 32).'.png';
	$proofPath = $root.'/'.$proof->filepath;
	mkdir(dirname($proofPath), 0777, true);
	file_put_contents($proofPath, 'fixture');
	$intervention->ref = '(PROV8)';
	$check(powerplantpvResidentialFilePath($first, $intervention, $proof) === realpath($proofPath), 'A draft resolves its proof');
	$intervention->ref = 'FI-TEST';
	$check(powerplantpvResidentialFilePath($first, $intervention, $proof) === realpath($proofPath), 'Proof uploaded on the draft still resolves after the intervention obtains a final reference');
	$intervention->id = 7;
	$check(powerplantpvResidentialFilePath($first, $intervention, $proof) === '', 'Another intervention cannot resolve the provisional proof');
	echo 'Autocontrol workflow: '.$checks." checks OK\n";
} finally {
	if ($proofPath !== '') {
		unlink($proofPath);
		rmdir(dirname($proofPath));
		rmdir(dirname($proofPath, 2));
		rmdir(dirname($proofPath, 3));
	}
	foreach (glob($directory.'/*.png') ?: array() as $drawing) {
		unlink($drawing);
	}
	rmdir($directory);
	rmdir(dirname($directory));
	rmdir($root);
}
