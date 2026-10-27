<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Native User, Project, CommonObject and access checks with isolated SQLite data.
 * Run: php -d extension=mysqli -d extension=pdo_sqlite test/attestation_link_defaults.php /path/to/htdocs
 * Does not bootstrap an instance, save an attestation or generate a PDF.
 */
if (PHP_SAPI !== 'cli') {
	exit(1);
}
define('DOL_DOCUMENT_ROOT', realpath($argv[1] ?? dirname(__DIR__, 2).'/dolibarr/htdocs'));
define('DOL_URL_ROOT', '');
define('MAIN_DB_PREFIX', 'pvdefaults_');
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
require_once DOL_DOCUMENT_ROOT.'/core/class/conf.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

/** Native SQL calls use a disposable SQLite connection, without a Dolibarr server. */
final class AttestationDefaultsDatabase extends DoliDBMysqli
{
	public PDO $pdo;
	public string $failure = '';
	public function __construct() { $this->pdo = new PDO('sqlite::memory:'); }
	public function escape($value) { return str_replace("'", "''", (string) $value); }
	public function lasterror() { return 'Injected SQL failure'; }
	public function query($sql, $usesavepoint = 0, $type = 'auto', $result_mode = 0)
	{
		if ($this->failure !== '' && strpos($sql, $this->failure) !== false) {
			return false;
		}
		$result = $this->pdo->query($sql);
		return new ArrayIterator($result->columnCount() ? $result->fetchAll(PDO::FETCH_OBJ) : array());
	}
	public function fetch_object($result) { if (!$result->valid()) { return false; } $row = $result->current(); $result->next(); return $row; }
	public function fetch_row($result) { $row = $this->fetch_object($result); return $row ? array_values((array) $row) : false; }
	public function num_rows($result) { return $result->count(); }
	public function free($result = null) { return true; }
}

$conf = new Conf();
$conf->entity = 1;
$conf->global = (object) array();
$conf->file = (object) array('dol_document_root' => array('main' => DOL_DOCUMENT_ROOT, 'module' => dirname(__DIR__, 2)));
foreach (array('powerplantpv', 'project', 'societe') as $module) {
	$conf->$module = (object) array('enabled' => 1);
	$conf->modules[$module] = 1;
}
$db = new AttestationDefaultsDatabase();
$langs = new Translate('', $conf);
$langs->defaultlang = 'en_US';
require_once dirname(__DIR__).'/class/powerplantpvattestation.class.php';
require_once dirname(__DIR__).'/class/powerplantpvprojectlink.class.php';
$user = new User($db);
$user->id = 7;
$user->socid = 0;
$user->rights = (object) array(
	'powerplantpv' => (object) array('powerplant' => (object) array('read' => 1)),
	'projet' => (object) array('lire' => 1, 'all' => (object) array('lire' => 1)),
	'societe' => (object) array('lire' => 1, 'client' => (object) array('voir' => 1)),
);
$db->query('CREATE TABLE pvdefaults_societe (rowid INTEGER PRIMARY KEY, entity INTEGER)');
$db->query('CREATE TABLE pvdefaults_societe_commerciaux (fk_soc INTEGER, fk_user INTEGER)');
$db->query('CREATE TABLE pvdefaults_projet (rowid INTEGER PRIMARY KEY, ref TEXT, title TEXT, entity INTEGER, fk_soc INTEGER, public INTEGER, fk_statut INTEGER)');
$db->query('CREATE TABLE pvdefaults_powerplantpv_powerplant (rowid INTEGER PRIMARY KEY, ref TEXT, label TEXT, entity INTEGER, fk_soc INTEGER, status INTEGER)');
$db->query('CREATE TABLE pvdefaults_element_element (rowid INTEGER PRIMARY KEY, fk_source INTEGER, sourcetype TEXT, fk_target INTEGER, targettype TEXT)');
$db->query('CREATE TABLE pvdefaults_c_type_contact (rowid INTEGER PRIMARY KEY, element TEXT, source TEXT, code TEXT)');
$db->query('CREATE TABLE pvdefaults_element_contact (rowid INTEGER PRIMARY KEY, element_id INTEGER, fk_c_type_contact INTEGER, fk_socpeople INTEGER)');
$db->query('INSERT INTO pvdefaults_societe VALUES (1,1),(2,1),(9,2)');
$db->query("INSERT INTO pvdefaults_projet VALUES (11,'PR11','Project one',1,1,1,1),(12,'PR12','Project two',1,1,1,1),(99,'SECRET','Hidden',2,1,1,1)");
$db->query("INSERT INTO pvdefaults_powerplantpv_powerplant VALUES (1,'PV1','Plant one',1,1,1)");
$db->query("INSERT INTO pvdefaults_element_element VALUES (1,1,'powerplant@powerplantpv',11,'project')");
$source = new PowerPlant($db);
$source->id = 1;
$source->entity = 1;
$source->fk_soc = 1;

$checks = 0;
function verifyDefault($expected, $actual, string $label): void
{
	global $checks;
	$checks++;
	if ($expected !== $actual) {
		throw new RuntimeException($label.': expected '.var_export($expected, true).', got '.var_export($actual, true));
	}
}
function newAttestation(): PowerPlantPVAttestation
{
	global $db;
	$object = new PowerPlantPVAttestation($db);
	$object->fk_powerplant = 1;
	return $object;
}

$object = newAttestation();
verifyDefault(2, $object->setDefaultLinksFromPowerPlant($source, $user), 'both linked defaults proposed');
verifyDefault(1, $object->fk_soc, 'producer proposed from plant');
verifyDefault(1, $object->socid, 'native third-party alias stays consistent');
verifyDefault(11, $object->fk_project, 'unique linked project proposed');
verifyDefault(0, $object->setDefaultLinksFromPowerPlant($source, $user), 'repeated proposal preserves choices');
$object->fk_soc = 2;
$object->fk_project = 12;
verifyDefault(0, $object->setDefaultLinksFromPowerPlant($source, $user), 'manual choices preserved');
verifyDefault(2, $object->fk_soc, 'manual producer preserved');
verifyDefault(12, $object->fk_project, 'manual project preserved');

$db->query("INSERT INTO pvdefaults_element_element VALUES (2,1,'powerplant@powerplantpv',12,'project')");
$object = newAttestation();
$object->setDefaultLinksFromPowerPlant($source, $user);
verifyDefault(null, $object->fk_project, 'multiple linked projects do not choose arbitrarily');
$db->query('UPDATE pvdefaults_element_element SET fk_target=99 WHERE rowid=2');
$object = newAttestation();
$object->setDefaultLinksFromPowerPlant($source, $user);
verifyDefault(null, $object->fk_project, 'a hidden additional project does not remove ambiguity');
$db->query('DELETE FROM pvdefaults_element_element WHERE rowid=1');
$object = newAttestation();
$object->setDefaultLinksFromPowerPlant($source, $user);
verifyDefault(null, $object->fk_project, 'hidden unique project not proposed');
$db->query('UPDATE pvdefaults_element_element SET fk_target=11 WHERE rowid=2');

$user->rights->powerplantpv->powerplant->read = 0;
verifyDefault(0, newAttestation()->setDefaultLinksFromPowerPlant($source, $user), 'no plant read right');
$user->rights->powerplantpv->powerplant->read = 1;
$user->rights->societe->lire = 0;
$object = newAttestation();
$object->setDefaultLinksFromPowerPlant($source, $user);
verifyDefault(null, $object->fk_soc, 'no third-party read right');
$user->rights->societe->lire = 1;
$user->rights->projet->lire = $user->rights->projet->all->lire = 0;
$object = newAttestation();
$object->setDefaultLinksFromPowerPlant($source, $user);
verifyDefault(null, $object->fk_project, 'no project read right');
$user->rights->projet->lire = $user->rights->projet->all->lire = 1;
$user->socid = 2;
verifyDefault(0, newAttestation()->setDefaultLinksFromPowerPlant($source, $user), 'external user cannot inherit another customer');
$user->socid = 0;
$user->rights->societe->client->voir = 0;
verifyDefault(0, newAttestation()->setDefaultLinksFromPowerPlant($source, $user), 'sales restriction on parent enforced');
$db->query('INSERT INTO pvdefaults_societe_commerciaux VALUES (1,7)');
verifyDefault(2, newAttestation()->setDefaultLinksFromPowerPlant($source, $user), 'assigned sales representative allowed');
$user->rights->societe->client->voir = 1;
$source->entity = 2;
verifyDefault(0, newAttestation()->setDefaultLinksFromPowerPlant($source, $user), 'plant outside entity scope');
$source->entity = 1;
$source->id = 2;
verifyDefault(0, newAttestation()->setDefaultLinksFromPowerPlant($source, $user), 'unrelated plant rejected');
$source->id = 1;
$source->fk_soc = 9;
$object = newAttestation();
$object->setDefaultLinksFromPowerPlant($source, $user);
verifyDefault(null, $object->fk_soc, 'third party outside entity scope');
$source->fk_soc = 999;
$object = newAttestation();
$object->setDefaultLinksFromPowerPlant($source, $user);
verifyDefault(null, $object->fk_soc, 'missing third party');
$source->fk_soc = 1;
$db->failure = 'SELECT rowid FROM pvdefaults_societe';
$object = newAttestation();
$object->setDefaultLinksFromPowerPlant($source, $user);
verifyDefault(null, $object->fk_soc, 'SQL failure never supplies an unverified third party');

echo $checks.' checks, 0 failures; PHP '.PHP_VERSION.', Dolibarr '.DOL_VERSION." (native access checks, SQLite).\n";
