<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

// Usage: php -d extension=pdo_sqlite test/report_evidence_download.php /path/to/htdocs
if (PHP_SAPI !== 'cli' || !isset($argv[1]) || !is_file($argv[1].'/core/lib/functions.lib.php')) {
	exit(1);
}
$core = realpath($argv[1]);
$module = dirname(__DIR__);
$root = sys_get_temp_dir().'/ppv-evidence-'.bin2hex(random_bytes(5));
define('DOL_DOCUMENT_ROOT', $core);
define('DOL_DATA_ROOT', $root);
define('DOL_URL_ROOT', '');
define('MAIN_DB_PREFIX', 'evidence_');
if (is_file($core.'/version.inc.php')) {
	require_once $core.'/version.inc.php';
}
require_once $core.'/core/lib/functions.lib.php';
require_once $core.'/core/class/conf.class.php';
require_once $core.'/core/db/Database.interface.php';
require_once $core.'/core/db/DoliDB.class.php';
require_once $core.'/core/db/mysqli.class.php';
require_once $core.'/user/class/user.class.php';
require_once $module.'/class/powerplantpvreportevidence.class.php';

/** Execute the actual link SQL in an isolated database. */
final class ReportEvidenceTestDatabase extends DoliDBMysqli
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

	public function last_insert_id($tab, $fieldid = 'rowid')
	{
		return (int) $this->pdo->lastInsertId();
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

	public function free($resultset = null)
	{
		return true;
	}
}

function evidenceCheck(bool $passed, string $message): void
{
	if (!$passed) {
		throw new RuntimeException($message);
	}
}

$db = new ReportEvidenceTestDatabase();
$db->pdo->exec('CREATE TABLE evidence_powerplantpv_report_evidence_link (
	entity INTEGER, fk_report_file INTEGER, token_hash TEXT UNIQUE, file_hash TEXT,
	date_creation TEXT, expires_at TEXT, fk_user_creat INTEGER
)');
$db->pdo->exec('CREATE TABLE evidence_powerplantpv_report_file (rowid INTEGER, entity INTEGER, fk_report INTEGER, filename TEXT)');
$db->pdo->exec('CREATE TABLE evidence_powerplantpv_report (rowid INTEGER, entity INTEGER, fk_fichinter INTEGER)');
$db->pdo->exec('CREATE TABLE evidence_fichinter (rowid INTEGER, entity INTEGER, ref TEXT)');
$db->pdo->exec('CREATE TABLE evidence_ecm_files (
	rowid INTEGER PRIMARY KEY, ref TEXT, label TEXT, share TEXT, entity INTEGER,
	filename TEXT, filepath TEXT, fullpath_orig TEXT, description TEXT, keywords TEXT,
	cover TEXT, position INTEGER, gen_or_uploaded TEXT, extraparams TEXT, date_c TEXT,
	tms TEXT, fk_user_c INTEGER, fk_user_m INTEGER, note_private TEXT, note_public TEXT, acl TEXT, src_object_type TEXT,
	src_object_id INTEGER, agenda_id INTEGER
)');
$db->pdo->exec("INSERT INTO evidence_fichinter VALUES (9, 2, 'FI0009')");
$db->pdo->exec('INSERT INTO evidence_powerplantpv_report VALUES (19, 2, 9)');
$db->pdo->exec("INSERT INTO evidence_powerplantpv_report_file VALUES (42, 2, 19, 'synthetic.pdf')");
$conf = new Conf();
$conf->entity = 2;
$conf->file = (object) array(
	'dol_main_url_root' => 'https://example.test',
	'dol_document_root' => array('main' => $core, 'test' => dirname($module)),
	'dol_url_root' => array('main' => '', 'test' => '/custom'),
	'character_set_client' => 'UTF-8',
);
$conf->ficheinter = (object) array('multidir_output' => array(2 => $root.'/2/ficheinter'));
$conf->powerplantpv = (object) array('multidir_output' => array(2 => $root.'/2/powerplantpv'));
$user = new User($db);
$user->id = 23;
$intervention = new Fichinter($db);
$intervention->id = 9;
$intervention->ref = 'FI0009';
$intervention->entity = 2;
$dir = $root.'/2/ficheinter/FI0009';
mkdir($dir, 0777, true);
$path = $dir.'/PPV-42-synthetic.pdf';
$source = $root.'/2/powerplantpv/report/PROV9/field_8/synthetic.pdf';
mkdir(dirname($source), 0777, true);
file_put_contents($source, 'synthetic PDF evidence');

try {
	$service = new PowerPlantPVReportEvidence($db);
	$report = (object) array('id' => 19, 'entity' => 2, 'fk_fichinter' => 9);
	$file = (object) array('id' => 42, 'entity' => 2, 'fk_report' => 19, 'fk_report_field' => 8,
		'filename' => 'synthetic.pdf', 'filepath' => 'report/PROV9/field_8/synthetic.pdf');
	$fileHash = $service->attach($intervention, $report, $file);
	evidenceCheck($fileHash !== '' && file_get_contents($path) === 'synthetic PDF evidence', 'Legacy evidence is attached to the intervention');
	evidenceCheck($service->attach($intervention, $report, $file) === $fileHash
		&& (int) $db->pdo->query("SELECT COUNT(*) FROM evidence_ecm_files WHERE src_object_type = 'fichinter' AND src_object_id = 9 AND entity = 2")->fetchColumn() === 1,
		'The attachment is indexed by the native API without duplicates');
	$url = $service->issueDownload($intervention, $user, 42, $fileHash);
	$token = (string) parse_url($url, PHP_URL_FRAGMENT);
	evidenceCheck(strlen($token) === 64 && !str_contains((string) parse_url($url, PHP_URL_QUERY), $token), 'Token stays in the URL fragment');
	$storedHash = $db->pdo->query('SELECT token_hash FROM evidence_powerplantpv_report_evidence_link')->fetchColumn();
	evidenceCheck($storedHash === hash('sha256', $token), 'Only the token fingerprint is stored');
	$download = $service->resolveDownload($token, 2);
	evidenceCheck($download !== null && $download['name'] === 'synthetic.pdf'
		&& stream_get_contents($download['stream']) === 'synthetic PDF evidence', 'The original file is downloadable');
	fclose($download['stream']);
	evidenceCheck($service->resolveDownload($token, 1) === null, 'Another entity cannot use the link');
	evidenceCheck($service->resolveDownload(str_repeat('0', 64), 2) === null, 'An unknown token is rejected');
	file_put_contents($path, 'another file at the same path');
	evidenceCheck($service->resolveDownload($token, 2) === null, 'The link cannot download a replaced file');
	file_put_contents($path, 'synthetic PDF evidence');
	$db->pdo->exec("UPDATE evidence_powerplantpv_report_evidence_link SET expires_at = '2000-01-01 00:00:00'");
	evidenceCheck($service->resolveDownload($token, 2) === null, 'An expired link is rejected');
	$newUrl = $service->issueDownload($intervention, $user, 42, $fileHash);
	$newToken = (string) parse_url($newUrl, PHP_URL_FRAGMENT);
	$download = $service->resolveDownload($newToken, 2);
	evidenceCheck($token !== $newToken && $download !== null, 'Regeneration issues a new link');
	fclose($download['stream']);
	unlink($path);
	evidenceCheck($service->resolveDownload($newToken, 2) === null, 'Deleting the native attachment invalidates the link');
	// The native deletion workflow removes its ECM index entry.
	$db->pdo->exec('DELETE FROM evidence_ecm_files');
	evidenceCheck($service->attach($intervention, $report, $file) === $fileHash
		&& $service->resolveDownload($newToken, 2) === null, 'Restoring an attachment does not revive an old link');
	$restoredUrl = $service->issueDownload($intervention, $user, 42, $fileHash);
	$restoredToken = (string) parse_url($restoredUrl, PHP_URL_FRAGMENT);
	$download = $service->resolveDownload($restoredToken, 2);
	evidenceCheck($download !== null, 'A restored attachment can receive a new link');
	fclose($download['stream']);
	$db->pdo->exec('DELETE FROM evidence_powerplantpv_report_file WHERE rowid = 42');
	evidenceCheck($service->resolveDownload($restoredToken, 2) === null, 'Deleting the report evidence invalidates its link');
	echo "Evidence download: 14 checks OK\n";
} finally {
	if (is_file($path)) {
		unlink($path);
	}
	rmdir($dir);
	unlink(dirname($dir).'/.powerplantpv-evidence.lock');
	rmdir(dirname($dir));
	unlink($source);
	$sourceDir = dirname($source);
	for ($level = 0; $level < 4; $level++) {
		rmdir($sourceDir);
		$sourceDir = dirname($sourceDir);
	}
	rmdir($root.'/2');
	rmdir($root);
}
