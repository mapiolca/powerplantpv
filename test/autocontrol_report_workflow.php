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
if (is_file($core.'/version.inc.php')) {
	require_once $core.'/version.inc.php';
}
require_once $core.'/core/lib/functions.lib.php';
require_once $core.'/core/class/conf.class.php';
require_once $core.'/core/class/translate.class.php';
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

try {
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
	$rows = array('placements' => array(), 'roofs' => array(array('orientation' => 'SE', 'module_count' => 4)), 'strings' => array(
		array('micro' => 1, 'fk_powerplant' => 2, 'fk_product' => 7, 'reference_label' => 'MICRO-7', 'maximum' => 3, 'quantity' => 2,
			'serials' => array('SER-01', 'SER-02')),
	));
	$check($db->begin() > 0 && $residential->save($first, $rows) && $residential->save($second, $rows) && $db->commit() > 0,
		'Residential report rows are written transactionally in both entities');
	$loaded = $residential->load($first);
	$check(is_array($loaded) && $loaded['initialized'] && (int) $loaded['strings'][0]['maximum'] === 3
		&& $loaded['strings'][0]['serials'] === array('SER-01', 'SER-02'), 'Strings reload with their original maximum and serial order');
	$rows['strings'][0]['maximum'] = 1;
	$rows['strings'][0]['serials'] = array('SER-01');
	$rows['strings'][0]['quantity'] = 1;
	$check($db->begin() > 0 && $residential->save($first, $rows) && (bool) $db->rollback(), 'A replacement can roll back');
	$check($residential->load($first)['strings'][0]['serials'] === array('SER-01', 'SER-02'), 'Rollback restores previous strings and series');
	$check($db->begin() > 0 && $residential->save($first, $rows) && $db->commit() > 0
		&& $residential->load($first)['strings'][0]['serials'] === array('SER-01')
		&& $residential->load($second)['strings'][0]['serials'] === array('SER-01', 'SER-02'),
		'Replacing a report keeps other entities and removes only its old serial rows');
	echo 'Autocontrol workflow: '.$checks." checks OK\n";
} finally {
	foreach (glob($directory.'/*.png') ?: array() as $drawing) {
		unlink($drawing);
	}
	rmdir($directory);
	rmdir(dirname($directory));
	rmdir($root);
}
