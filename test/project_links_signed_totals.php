<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Native objects, rights, links, extrafields, HookManager and nested transactions.
 * SQL runs in an isolated SQLite database; FOR UPDATE is omitted by the adapter.
 * This does not prove MySQL locking, a deployed UI or a real Multicompany installation.
 * Run: php -d extension=pdo_sqlite test/project_links_signed_totals.php /path/to/htdocs
 */
if (PHP_SAPI !== 'cli') {
	exit(1);
}
define('DOL_DOCUMENT_ROOT', realpath($argv[1] ?? dirname(__DIR__, 2).'/dolibarr/htdocs'));
define('DOL_URL_ROOT', '');
define('MAIN_DB_PREFIX', 'pvtest_');
if (is_file(DOL_DOCUMENT_ROOT.'/version.inc.php')) {
	require_once DOL_DOCUMENT_ROOT.'/version.inc.php';
} else {
	$version = file_get_contents(DOL_DOCUMENT_ROOT.'/filefunc.inc.php');
	preg_match('/define\([\'"]DOL_VERSION[\'"],\s*[\'"]([^\'"]+)/', $version, $matches);
	define('DOL_VERSION', $matches[1]);
}
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/db/Database.interface.php';
require_once DOL_DOCUMENT_ROOT.'/core/db/DoliDB.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/db/mysqli.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/conf.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
foreach (array('html', 'images') as $library) {
	if (is_file(DOL_DOCUMENT_ROOT.'/core/lib/'.$library.'.lib.php')) {
		require_once DOL_DOCUMENT_ROOT.'/core/lib/'.$library.'.lib.php';
	}
}
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once dirname(__DIR__).'/class/powerplantpvprojectlink.class.php';
require_once dirname(__DIR__).'/class/powerplantpvprojectsignedtotals.class.php';
require_once dirname(__DIR__).'/class/actions_powerplantpv.class.php';
require_once dirname(__DIR__).'/core/triggers/interface_9999_modPowerPlantPV_ProjectSignedTotals.class.php';

/** SQLite adapter; business classes and the DoliDB transaction implementation stay native. */
final class ProjectTestDatabase extends DoliDBMysqli
{
	public $pdo;
	public $failure = '';
	public $failCommit = false;
	private $lastError = '';
	public function __construct() { $this->pdo = new PDO('sqlite::memory:'); }
	public function escape($value) { return str_replace("'", "''", (string) $value); }
	public function lasterror() { return $this->lastError; }
	public function query($sql, $usesavepoint = 0, $type = 'auto', $result_mode = 0)
	{
		if (($this->failure !== '' && strpos($sql, $this->failure) !== false) || ($this->failCommit && $sql === 'COMMIT')) {
			$this->lastError = 'Injected SQL failure';
			return false;
		}
		$sql = preg_replace('/ FOR UPDATE$/', '', $sql);
		try {
			$result = $this->pdo->query($sql);
			return new ArrayIterator($result->columnCount() ? $result->fetchAll(PDO::FETCH_OBJ) : array());
		} catch (PDOException $e) {
			$this->lastError = $e->getMessage().' SQL: '.$sql;
			throw new RuntimeException($this->lastError);
		}
	}
	public function fetch_object($result) { if (!$result->valid()) { return false; } $row = $result->current(); $result->next(); return $row; }
	public function fetch_row($result) { $row = $this->fetch_object($result); return $row ? array_values((array) $row) : false; }
	public function num_rows($result) { return $result->count(); }
	public function affected_rows($result) { return 1; }
	public function free($result = null) { return true; }
	public function last_insert_id($tab, $fieldid = 'rowid') { return (int) $this->pdo->lastInsertId(); }
}

$conf = new Conf();
foreach (array(
	'entity' => 1, 'global' => (object) array(), 'currency' => 'EUR',
	'file' => (object) array('dol_document_root' => array('main' => DOL_DOCUMENT_ROOT, 'module' => dirname(__DIR__, 2))),
) as $key => $value) {
	$conf->$key = $value;
}
foreach (array('powerplantpv', 'project', 'societe') as $module) {
	$conf->$module = (object) array('enabled' => 1);
	$conf->modules[$module] = 1;
}
$db = new ProjectTestDatabase();
$hookmanager = new HookManager($db);
$langs = new Translate('', $conf);
$langs->defaultlang = 'en_US';
$langs->tab_translate = array('SeparatorDecimal' => '.', 'SeparatorThousand' => 'None');
$user = new User($db);
$user->id = 7;
$user->socid = 0;
$user->rights = (object) array(
	'powerplantpv' => (object) array('powerplant' => (object) array('read' => 1, 'write' => 1)),
	'projet' => (object) array('lire' => 1, 'creer' => 1, 'all' => (object) array('lire' => 1, 'creer' => 1)),
	'societe' => (object) array('client' => (object) array('voir' => 1)),
);
$db->query('CREATE TABLE pvtest_projet (rowid INTEGER PRIMARY KEY, ref TEXT, title TEXT, entity INTEGER, fk_soc INTEGER, public INTEGER, fk_statut INTEGER)');
$db->query('CREATE TABLE pvtest_powerplantpv_powerplant (rowid INTEGER PRIMARY KEY, ref TEXT, label TEXT, entity INTEGER, fk_soc INTEGER, status INTEGER)');
$db->query('CREATE TABLE pvtest_element_element (rowid INTEGER PRIMARY KEY AUTOINCREMENT, fk_source INTEGER, sourcetype TEXT, fk_target INTEGER, targettype TEXT)');
$db->query('CREATE TABLE pvtest_c_type_contact (rowid INTEGER PRIMARY KEY, element TEXT, source TEXT, code TEXT)');
$db->query('CREATE TABLE pvtest_element_contact (rowid INTEGER PRIMARY KEY, element_id INTEGER, fk_c_type_contact INTEGER, fk_socpeople INTEGER)');
$db->query('CREATE TABLE pvtest_societe_commerciaux (fk_soc INTEGER, fk_user INTEGER)');
$db->query("INSERT INTO pvtest_c_type_contact VALUES (1,'project','internal','PROJECTLEADER')");
$db->query("INSERT INTO pvtest_projet VALUES (11,'PR11','Project one',1,1,1,1),(12,'PR12','Project two',1,1,1,1),(99,'SECRET','Hidden',2,1,1,1)");
$db->query("INSERT INTO pvtest_powerplantpv_powerplant VALUES (1,'PV1','Plant one',1,1,1),(2,'PV2','Plant two',1,1,1),(9,'SECRET','Hidden',2,1,1)");

$checks = 0;
function verify($value, $message) { global $checks; $checks++; if (!$value) { throw new RuntimeException($message); } }
function scalar($sql) { global $db; return $db->pdo->query($sql)->fetchColumn(); }
$links = new PowerPlantPVProjectLink($db);
$plants = $links->powerplants($user);
$projects = $links->projects($user);
verify(count($plants) === 2 && count($projects) === 2, 'Entity scopes must hide entity 2');
verify($links->sync($plants[1], array(11,12), $user, 1) === 1, 'Link one plant to two projects');
verify($links->sync($projects[11], array(1,2), $user, 1) === 1, 'Link one project to two plants');
verify(count($links->linked($plants[1], $user)) === 2, 'Plant banner counterparts');
verify(count($links->linked($projects[11], $user)) === 2, 'Project banner counterparts');
verify($links->sync($projects[11], array(1,2), $user, 1) === 1 && scalar('SELECT COUNT(*) FROM pvtest_element_element') == 3, 'Repeated save is idempotent');
$db->query("INSERT INTO pvtest_element_element (fk_source,sourcetype,fk_target,targettype) VALUES (99,'project',1,'powerplant@powerplantpv')");
verify($plants[1]->getSingleProjectId($user) === 0, 'A hidden second project must not become an arbitrary default');
verify($links->sync($plants[1], array(12), $user, 1) === 1, 'Remove visible project from plant');
verify(scalar('SELECT COUNT(*) FROM pvtest_element_element WHERE fk_source=99') == 1, 'Hidden relation preserved');
verify(count($links->linked($projects[11], $user)) === 1, 'Reverse edit immediately visible');
verify($links->sync($plants[1], array(999), $user, 1) === -1, 'Forged id refused');
$user->rights->powerplantpv->powerplant->write = 0;
$user->admin = 1;
verify($links->sync($plants[1], array(), $user, 1) === -1, 'Administrator without hasRight must be refused');
$user->admin = 0;
$user->rights->powerplantpv->powerplant->write = 1;
$revision = hash('sha256', json_encode($links->rows($plants[1])));
verify($links->sync($plants[1], array(11), $user, 1) === 1, 'Change links');
verify($links->sync($plants[1], array(12), $user, 1, $revision) === -1 && $links->error === 'PowerPlantPVProjectLinksConflict', 'Stale selection refused');
$db->failure = 'INSERT INTO pvtest_element_element';
verify($links->sync($plants[1], array(12), $user, 1) === -1, 'Insertion failure propagated');
$db->failure = '';
verify(isset($links->linked($plants[1], $user)[11]), 'Failed replacement rolls back deletion');
$db->begin();
verify($links->sync($plants[2], array(12), $user, 1) === 1, 'Nested native transaction succeeds locally');
$db->rollback();
verify(isset($links->linked($plants[2], $user)[11]), 'Outer rollback restores nested link writes');
$db->failCommit = true;
verify($links->sync($plants[2], array(12), $user, 1) === -1, 'Commit failure never reports success');
$db->failCommit = false;
verify(isset($links->linked($plants[2], $user)[11]), 'Commit failure rolls back pending writes');

// A schema fixture for the native ExtraFields metadata reader, across v20-v25.
$columns = array('rowid INTEGER PRIMARY KEY', 'name TEXT', 'label TEXT', "type TEXT DEFAULT 'double'", "size TEXT DEFAULT '24,8'", "elementtype TEXT DEFAULT 'projet'");
foreach (array('fieldunique','fieldrequired','pos','alwayseditable','emptyonclone','printable','showintooltip','totalizable','personal_data') as $key) {
	$columns[] = $key.' INTEGER DEFAULT 0';
}
foreach (array('param','perms','langs','fielddefault','fieldcomputed','help','aiprompt','css','cssview','csslist') as $key) {
	$columns[] = $key.' TEXT';
}
$columns[] = 'entity INTEGER DEFAULT 1';
$columns[] = 'enabled TEXT DEFAULT 1';
$columns[] = 'list TEXT DEFAULT 5';
$db->query('CREATE TABLE pvtest_extrafields ('.implode(',', $columns).')');
foreach (array('powerplantpv_signed_peak_power', 'powerplantpv_signed_storage_capacity') as $i => $key) {
	$db->query("INSERT INTO pvtest_extrafields (rowid,name,label) VALUES (".($i+1).",'".$key."','".$key."')");
}
$db->query('CREATE TABLE pvtest_projet_extrafields (rowid INTEGER PRIMARY KEY AUTOINCREMENT, fk_object INTEGER UNIQUE, powerplantpv_signed_peak_power REAL, powerplantpv_signed_storage_capacity REAL, jpsun_project_pc TEXT)');
$db->query("INSERT INTO pvtest_projet_extrafields (fk_object,jpsun_project_pc) VALUES (11,'legacy-preserved')");
$db->query('CREATE TABLE pvtest_propal (rowid INTEGER PRIMARY KEY, fk_projet INTEGER, entity INTEGER, fk_statut INTEGER)');
$db->query('CREATE TABLE pvtest_propal_extrafields (fk_object INTEGER UNIQUE, powerplantpv_peak_power REAL, powerplantpv_storage_capacity REAL)');
$db->query('ALTER TABLE pvtest_projet ADD COLUMN fk_user_modif INTEGER');
$db->query('INSERT INTO pvtest_propal VALUES (1,11,1,2),(2,11,1,4),(3,11,1,1),(4,12,1,2)');
$db->query('INSERT INTO pvtest_propal_extrafields VALUES (1,10,2),(2,5,8),(3,999,999),(4,0,0)');
$totals = new PowerPlantPVProjectSignedTotals($db);
verify($totals->refresh($user) === 2, 'Refresh signed totals with native extrafields: '.$totals->error);
verify(scalar('SELECT powerplantpv_signed_peak_power FROM pvtest_projet_extrafields WHERE fk_object=11') == 15, 'Only signed and billed power included');
verify(scalar('SELECT powerplantpv_signed_storage_capacity FROM pvtest_projet_extrafields WHERE fk_object=11') == 10, 'Capacity summed');
verify(scalar('SELECT jpsun_project_pc FROM pvtest_projet_extrafields WHERE fk_object=11') === 'legacy-preserved', 'Native update must preserve JPSUN data, including v20');
$knownZero = scalar('SELECT powerplantpv_signed_peak_power FROM pvtest_projet_extrafields WHERE fk_object=12');
// PDO SQLite before PHP 8.1 may return a numeric string; NULL and empty remain invalid.
verify(is_numeric($knownZero) && (float) $knownZero === 0.0, 'Zero remains a known value: '.var_export($knownZero, true));
$db->query('UPDATE pvtest_propal_extrafields SET powerplantpv_storage_capacity=NULL WHERE fk_object=2');
verify($totals->refresh($user) === 2, 'Refresh incomplete capacity');
verify(scalar('SELECT powerplantpv_signed_storage_capacity FROM pvtest_projet_extrafields WHERE fk_object=11') === null, 'Incomplete capacity is NULL rather than partial');
verify(scalar('SELECT powerplantpv_signed_peak_power FROM pvtest_projet_extrafields WHERE fk_object=11') == 15, 'Incomplete capacity does not erase known power');
$db->query('UPDATE pvtest_propal SET fk_statut=1 WHERE rowid=1');
verify($totals->refresh($user) === 2 && scalar('SELECT powerplantpv_signed_peak_power FROM pvtest_projet_extrafields WHERE fk_object=11') == 5, 'Reopened proposal removed');
$db->query('UPDATE pvtest_propal SET fk_projet=12 WHERE rowid=2');
verify($totals->refresh($user) === 2 && scalar('SELECT powerplantpv_signed_peak_power FROM pvtest_projet_extrafields WHERE fk_object=11') == 0, 'Project move clears former total');
verify($totals->refresh($user, 2) === 2 && scalar('SELECT powerplantpv_signed_peak_power FROM pvtest_projet_extrafields WHERE fk_object=12') == 0, 'Pre-delete trigger excludes deleted proposal');
$db->query('INSERT INTO pvtest_propal VALUES (10,11,2,2)');
verify($totals->refresh($user) === 2 && scalar('SELECT powerplantpv_signed_peak_power FROM pvtest_projet_extrafields WHERE fk_object=11') === null, 'Unavailable source entity must not produce a partial total');
verify(scalar('SELECT COUNT(*) FROM pvtest_projet_extrafields WHERE fk_object=99') == 0, 'No writes in hidden project entity');
$db->failure = 'UPDATE pvtest_projet_extrafields';
verify($totals->refresh($user) === -1, 'Extrafield write failure propagated');
$db->failure = '';

// Run actual HookManager resets and native HTML generation; no deployed instance is bootstrapped.
$conf->modules_parts['hooks'] = array('powerplantpv' => array('projectcard', 'powerplantcard'));
$conf->file->dol_url_root = array('main' => '', 'module' => '/custom');
$conf->use_javascript_ajax = 1;
$conf->dol_no_mouse_hover = 1;
$_SERVER['PHP_SELF'] = '/projet/card.php';
$_SESSION = array('newtoken' => bin2hex(random_bytes(16)));
$hookmanager->initHooks(array('projectcard', 'powerplantcard'));
$bannerobject = $projects[11];
$banneraction = '';
verify($hookmanager->executeHooks('formDolBanner', array(), $bannerobject, $banneraction) === 0, 'Native banner hook returns append result');
verify(strpos($hookmanager->resPrint, 'PV1') !== false && strpos($hookmanager->resPrint, 'PV2') !== false, 'Executed project banner shows both plants');
$banneraction = 'powerplantpv_edit_plants';
verify($hookmanager->executeHooks('formDolBanner', array(), $bannerobject, $banneraction) === 0, 'Second hook call supports manager reset');
$html = $hookmanager->resPrint;
libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML($html);
$xpath = new DOMXPath($dom);
verify($xpath->query('//select[@multiple and @name="powerplantpv_link_ids[]"]')->length === 1, 'Banner uses native multiple selection');
verify($xpath->query('//input[@name="token" and string-length(@value)>0]')->length === 1, 'Editor contains a native CSRF token');
verify($xpath->query('//input[@name="powerplantpv_links_revision" and string-length(@value)=64]')->length === 1, 'Editor contains a conflict revision');
$unrelated = new stdClass();
$unrelated->id = 5;
$unrelated->element = 'societe';
verify($hookmanager->executeHooks('formDolBanner', array(), $unrelated, $banneraction) === 0 && $hookmanager->resPrint === '', 'A later unrelated banner must not inherit links');

$user->rights->societe->client->voir = 0;
verify(count($links->powerplants($user)) === 0, 'Unassigned third parties must not leak through selectors');
$db->query('INSERT INTO pvtest_societe_commerciaux VALUES (1,7)');
verify(count($links->powerplants($user)) === 2, 'Assigned third parties remain selectable');
$user->rights->societe->client->voir = 1;
$user->socid = 2;
verify(count($links->powerplants($user)) === 0 && $links->sync($plants[1], array(12), $user, 1) === -1, 'External actor cannot mutate or read another third party');
$user->socid = 0;
$user->rights->powerplantpv->powerplant->write = 0;
verify($links->sync($projects[11], array(1,2), $user, 1) === 1, 'Project write plus plant read is sufficient from the project card');
$user->rights->powerplantpv->powerplant->write = 1;

// Fixture for a proposal whose project is attached by the earlier JPSUN trigger.
$db->query('INSERT INTO pvtest_propal VALUES (50,NULL,1,2)');
$db->query('INSERT INTO pvtest_propal_extrafields VALUES (50,21,3)');
$proposal = (object) array('id' => 50, 'element' => 'propal');
$trigger = new InterfaceProjectSignedTotals($db);
verify($trigger->runTrigger('PROPAL_CLOSE_SIGNED', $proposal, $user, $langs, $conf) === 0, 'Unlinked signed proposal stays neutral');
$db->query("INSERT INTO pvtest_projet (rowid,ref,title,entity,fk_soc,public,fk_statut) VALUES (13,'AUTO','Automatic project',1,1,1,1)");
$db->query('UPDATE pvtest_propal SET fk_projet=13 WHERE rowid=50');
verify($trigger->runTrigger('PROPAL_CLOSE_SIGNED', $proposal, $user, $langs, $conf) === 0, 'Late native event reads newly attached project');
verify(scalar('SELECT powerplantpv_signed_peak_power FROM pvtest_projet_extrafields WHERE fk_object=13') == 21
	&& scalar('SELECT powerplantpv_signed_storage_capacity FROM pvtest_projet_extrafields WHERE fk_object=13') == 3, 'Signing updates both new project totals');
$db->query('UPDATE pvtest_propal SET fk_statut=1 WHERE rowid=50');
verify($trigger->runTrigger('PROPAL_REOPEN', $proposal, $user, $langs, $conf) === 0
	&& scalar('SELECT powerplantpv_signed_peak_power FROM pvtest_projet_extrafields WHERE fk_object=13') == 0, 'Late reopen event clears signed power');

echo $checks.' checks passed; PHP '.PHP_VERSION.', Dolibarr '.DOL_VERSION."; native objects/helpers, SQLite SQL, locking not simulated.\n";
