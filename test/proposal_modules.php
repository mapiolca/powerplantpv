<?php
/** SQLite exercises the real counting SQL; access context is simulated, no ERP writes.
 * php -d extension=pdo_sqlite test/proposal_modules.php
 */
if (PHP_SAPI !== 'cli') {
	exit(1);
}
define('MAIN_DB_PREFIX', 'fixture_');
// Isolated SQL harness: emulate only module-path resolution, without an ERP bootstrap.
function dol_include_once($path) {
    if (!in_array($path, array('/powerplantpv/lib/powerplantpv.lib.php', '/powerplantpv/lib/powerplantpv_proposal_modules.lib.php'), true)) {
        throw new RuntimeException('Unexpected module include');
    }
    require_once dirname(__DIR__).substr($path, strlen('/powerplantpv'));
    return 1;
}
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
$allowed = true;
$active = true;
function isModEnabled($key) { return $GLOBALS['active']; }
function getEntity($key) { return $key === 'product' ? '1,2' : '1'; }
function restrictedArea(...$args) { return $GLOBALS['allowed'] ? 1 : 0; }
class ProposalModulesDatabase
{
	public $pdo;
	public $fail = false;
	public $queries = array();
	public function __construct() { $this->pdo = new PDO('sqlite::memory:'); }
	public function prefix() { return MAIN_DB_PREFIX; }
	public function escape($value) { return str_replace("'", "''", $value); }
	public function sanitize($value) { if (!preg_match('/^[0-9,]+$/', $value)) { throw new RuntimeException('Unsafe identifiers'); } return $value; }
	public function query($sql) { $this->queries[] = $sql; return $this->fail ? false : $this->pdo->query($sql); }
	public function fetch_object($res) { return $res->fetchObject(); }
	public function free($res) { $res->closeCursor(); }
	public function lasterror() { return 'simulated read error'; }
}
$db = new ProposalModulesDatabase();
$db->pdo->exec("CREATE TABLE fixture_c_powerplantpv_categorypv(rowid INTEGER, code TEXT);
CREATE TABLE fixture_propal(rowid INTEGER,entity INTEGER);
CREATE TABLE fixture_propaldet(fk_propal INTEGER, fk_product INTEGER,qty REAL);
CREATE TABLE fixture_product(rowid INTEGER, entity INTEGER);
CREATE TABLE fixture_product_extrafields(fk_object INTEGER,categorie_photovoltaique INTEGER);
INSERT INTO fixture_c_powerplantpv_categorypv VALUES(7,'MODULE'),(9,'ONDULE');
INSERT INTO fixture_propal VALUES(1,1),(2,2);
INSERT INTO fixture_product VALUES(11,1),(12,2),(13,3),(14,1);
INSERT INTO fixture_product_extrafields VALUES(11,7),(12,7),(13,7),(14,9);
INSERT INTO fixture_propaldet VALUES(1,11,20),(1,11,30),(1,11,-2),(1,12,4),(1,13,999),(1,14,9),(1,NULL,10),(2,11,800);");
$actor = new class { public $right = true; public function hasRight(...$args) { return $this->right; } };
$proposal = (object) array('id' => 1, 'entity' => 1);
function expect($condition, $label) { if (!$condition) { throw new RuntimeException($label); } echo "OK $label\n"; }
$result = powerplantpvCountProposalModules($proposal, $actor);
expect($result['result'] === 1 && $result['quantity'] === 52.0, 'module classification, repeated products, signed quantities and product sharing');
$proposal->entity = 2;
expect(powerplantpvCountProposalModules($proposal, $actor)['quantity'] === 0.0, 'owner mismatch cannot expose another proposal');
$proposal->id = 2;
expect(powerplantpvCountProposalModules($proposal, $actor)['quantity'] === 0.0, 'proposal entity sharing enforced before aggregation');
$actor->right = false;
expect(powerplantpvCountProposalModules($proposal, $actor)['result'] < 0, 'functional read right required');
$actor->right = true;
$allowed = false;
expect(powerplantpvCountProposalModules($proposal, $actor)['result'] < 0, 'native business restriction enforced');
$allowed = true;
$active = false;
expect(powerplantpvCountProposalModules($proposal, $actor)['result'] < 0, 'inactive dependency refused');
$active = true;
$db->fail = true;
expect(powerplantpvCountProposalModules($proposal, $actor)['result'] < 0, 'read error is not silently zero');
foreach ($db->queries as $sql) {
	expect(strpos($sql, 'SELECT ') === 0, 'counting never writes commercial data');
}
