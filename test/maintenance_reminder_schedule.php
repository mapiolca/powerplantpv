<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Run with PHP and pdo_sqlite, passing the path to Dolibarr's htdocs.
 * Uses a disposable database with no instance bootstrap or email transport.
 */
if (PHP_SAPI !== 'cli' || empty($argv[1]) || !is_file($argv[1].'/core/lib/functions.lib.php')) {
	exit(1);
}
define('DOL_DOCUMENT_ROOT', realpath($argv[1]));
define('MAIN_DB_PREFIX', 'ppvtest_');
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

final class ReminderScheduleDatabase
{
	public PDO $pdo;
	public function __construct()
	{
		$this->pdo = new PDO('sqlite::memory:');
	}
	public function query($sql)
	{
		return $this->pdo->query($sql);
	}
	public function fetch_object($result)
	{
		return $result->fetch(PDO::FETCH_OBJ);
	}
	public function free($result)
	{
		$result->closeCursor();
	}
	public function idate($date)
	{
		return gmdate('Y-m-d H:i:s', $date);
	}
	public function jdate($date)
	{
		return strtotime($date.' UTC');
	}
	public function escape($value)
	{
		return str_replace("'", "''", $value);
	}
	public function encrypt($value)
	{
		return $this->pdo->quote($value);
	}
	public function begin()
	{
		return $this->pdo->beginTransaction();
	}
	public function commit()
	{
		return $this->pdo->commit();
	}
}

function verifyReminderSchedule($expected, $actual, string $label): void
{
	if ($expected !== $actual) {
		throw new RuntimeException($label.' expected '.var_export($expected, true).', got '.var_export($actual, true));
	}
}

$conf = (object) array('entity' => 2, 'global' => (object) array());
$conf->file = (object) array('dol_document_root' => array('main' => DOL_DOCUMENT_ROOT));
$db = new ReminderScheduleDatabase();
$hookmanager = new HookManager($db);
require_once dirname(__DIR__).'/class/powerplantpvmaintenancereminder.class.php';
$user = new User($db);
$user->id = 7;
$user->email = 'reminder@example.invalid';
$reminder = new PowerPlantPVMaintenanceReminder($db);
$db->query('CREATE TABLE ppvtest_const (name TEXT, value TEXT, type TEXT, visible INTEGER, note TEXT, entity INTEGER, fk_user_creat INTEGER, fk_user_modif INTEGER)');
$db->query('CREATE TABLE ppvtest_cronjob (rowid INTEGER PRIMARY KEY, entity INTEGER, jobtype TEXT, classesname TEXT, objectname TEXT, methodename TEXT, datestart TEXT, datenextrun TEXT, frequency INTEGER, unitfrequency TEXT, status INTEGER, fk_user_mod INTEGER)');

$marker = new ReflectionMethod($reminder, 'markRecipientSent');
if (PHP_VERSION_ID < 80100) {
	$marker->setAccessible(true);
}
verifyReminderSchedule(true, $marker->invoke($reminder, 'monthly', $user, '202611231805'), 'CLI stores the send marker through Dolibarr');
verifyReminderSchedule('202611231805', $db->query('SELECT value FROM ppvtest_const WHERE entity = 2')->fetchColumn(), 'marker belongs to the current entity');

$start = strtotime('2026-11-23 18:05 UTC');
foreach (array('weekly' => array('604800', '2026-11-30'), 'monthly' => array('2678400', '2026-12-23')) as $frequency => [$unit, $nextDay]) {
	$code = strtoupper($frequency);
	$method = 'run'.ucfirst($frequency).'Reminder';
	$conf->global->{'POWERPLANTPV_MAINTENANCE_'.$code.'_REMINDER_ENABLE'} = '1';
	$conf->global->{'POWERPLANTPV_MAINTENANCE_'.$code.'_REMINDER_STARTTIME'} = (string) $start;
	$db->query("INSERT INTO ppvtest_cronjob (entity, jobtype, classesname, objectname, methodename, frequency, unitfrequency, status)"
		." VALUES (2, 'method', '/powerplantpv/class/powerplantpvmaintenancereminder.class.php', 'PowerPlantPVMaintenanceReminder', '".$method."', 5, '60', 1),"
		." (1, 'method', '/powerplantpv/class/powerplantpvmaintenancereminder.class.php', 'PowerPlantPVMaintenanceReminder', '".$method."', 5, '60', 0)");
	verifyReminderSchedule(0, $reminder->getNextSendTimestamp($frequency), 'the five-minute poll is not shown as the next send');
	verifyReminderSchedule(1, PowerPlantPVMaintenanceReminder::updateCronStartTime($db, $frequency, $start, $user), 'settings reschedule an existing task');
	$task = $db->query("SELECT rowid, frequency, unitfrequency, datestart FROM ppvtest_cronjob WHERE entity = 2 AND methodename = '".$method."'")->fetch(PDO::FETCH_OBJ);
	verifyReminderSchedule(1, (int) $task->frequency, 'one occurrence');
	verifyReminderSchedule($unit, $task->unitfrequency, 'native unit of frequency');
	verifyReminderSchedule($db->idate($start), $task->datestart, 'configured start written to task');
	verifyReminderSchedule($start, $reminder->getNextSendTimestamp($frequency), 'initial execution date');
	$db->query("UPDATE ppvtest_cronjob SET datenextrun = '".$nextDay." 18:05:00' WHERE rowid = ".((int) $task->rowid));
	verifyReminderSchedule(1, PowerPlantPVMaintenanceReminder::updateCronStartTime($db, $frequency, $start, $user), 'settings accept an unchanged start');
	verifyReminderSchedule(strtotime($nextDay.' 18:05 UTC'), $reminder->getNextSendTimestamp($frequency), 'saving the same start preserves the next date');
	$newStart = $start + 3600;
	verifyReminderSchedule(1, PowerPlantPVMaintenanceReminder::updateCronStartTime($db, $frequency, $newStart, $user), 'settings accept a different start');
	verifyReminderSchedule($newStart, $reminder->getNextSendTimestamp($frequency), 'changing the start resets the next date');
	verifyReminderSchedule('60', $db->query("SELECT unitfrequency FROM ppvtest_cronjob WHERE entity = 1 AND methodename = '".$method."'")->fetchColumn(), 'another entity is untouched');
	$db->query('UPDATE ppvtest_cronjob SET status = 0 WHERE rowid = '.((int) $task->rowid));
	verifyReminderSchedule(0, $reminder->getNextSendTimestamp($frequency), 'a disabled task has no displayed next send');
}

echo "Maintenance reminder scheduling checks passed.\n";
