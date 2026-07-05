<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file		class/powerplantpvmaintenancereminder.class.php
 * \ingroup		powerplantpv
 * \brief		Scheduled reminders for upcoming maintenance periods.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
dol_include_once('/powerplantpv/lib/powerplantpv_maintenance.lib.php');
dol_include_once('/powerplantpv/class/powerplantpvmaintenancescheduler.class.php');
dol_include_once('/user/class/user.class.php');

/**
 * Scheduled reminder sender for PowerPlantPV maintenance.
 */
class PowerPlantPVMaintenanceReminder
{
	private const FREQUENCY_WEEKLY = 'weekly';
	private const FREQUENCY_MONTHLY = 'monthly';
	private const CRON_CLASS = '/powerplantpv/class/powerplantpvmaintenancereminder.class.php';
	private const CRON_OBJECT = 'PowerPlantPVMaintenanceReminder';

	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Error message
	 */
	public $error = '';

	/**
	 * @var array<int,string> Error messages
	 */
	public $errors = array();

	/**
	 * @var string Cron output
	 */
	public $output = '';

	/**
	 * Constructor.
	 *
	 * @param	DoliDB	$db	Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Cron entry point for weekly reminders.
	 *
	 * @param	string	$parameters	Cron parameters
	 * @return	int					0 if OK, <0 if KO
	 */
	public function runWeeklyReminder($parameters = '')
	{
		return $this->runReminder(self::FREQUENCY_WEEKLY, (string) $parameters);
	}

	/**
	 * Cron entry point for monthly reminders.
	 *
	 * @param	string	$parameters	Cron parameters
	 * @return	int					0 if OK, <0 if KO
	 */
	public function runMonthlyReminder($parameters = '')
	{
		return $this->runReminder(self::FREQUENCY_MONTHLY, (string) $parameters);
	}

	/**
	 * Update the native cron start date for one reminder frequency.
	 *
	 * @param	DoliDB		$db					Database handler
	 * @param	string		$frequency			Reminder frequency
	 * @param	int			$startTimestamp		Next launch timestamp
	 * @param	User|null	$currentUser		User performing the update
	 * @return	int								>0 if updated, 0 if cron missing, <0 on error
	 */
	public static function updateCronStartTime($db, $frequency, $startTimestamp, $currentUser = null)
	{
		global $conf, $user;

		$method = self::getMethodForFrequency($frequency);
		if ($method === '') {
			return 0;
		}

		$sql = 'SELECT COUNT(rowid) as nb';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'cronjob';
		$sql .= " WHERE jobtype = 'method'";
		$sql .= " AND classesname = '".self::CRON_CLASS."'";
		$sql .= " AND objectname = '".self::CRON_OBJECT."'";
		$sql .= " AND methodename = '".$db->escape($method)."'";
		$sql .= ' AND entity = '.((int) $conf->entity);
		$resql = $db->query($sql);
		if (!$resql) {
			return -1;
		}
		$obj = $db->fetch_object($resql);
		$db->free($resql);
		if (empty($obj) || (int) $obj->nb <= 0) {
			return 0;
		}

		$userForUpdate = $currentUser;
		if (empty($userForUpdate) && !empty($user) && $user instanceof User) {
			$userForUpdate = $user;
		}

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'cronjob SET';
		$sql .= " datestart = '".$db->idate((int) $startTimestamp)."'";
		$sql .= ", datenextrun = '".$db->idate((int) $startTimestamp)."'";
		if (!empty($userForUpdate) && $userForUpdate instanceof User) {
			$sql .= ', fk_user_mod = '.((int) $userForUpdate->id);
		}
		$sql .= " WHERE jobtype = 'method'";
		$sql .= " AND classesname = '".self::CRON_CLASS."'";
		$sql .= " AND objectname = '".self::CRON_OBJECT."'";
		$sql .= " AND methodename = '".$db->escape($method)."'";
		$sql .= ' AND entity = '.((int) $conf->entity);

		return $db->query($sql) ? 1 : -1;
	}

	/**
	 * Return cron method name for a frequency.
	 *
	 * @param	string	$frequency	Frequency code
	 * @return	string				Method name
	 */
	private static function getMethodForFrequency($frequency)
	{
		if ($frequency === self::FREQUENCY_WEEKLY) {
			return 'runWeeklyReminder';
		}
		if ($frequency === self::FREQUENCY_MONTHLY) {
			return 'runMonthlyReminder';
		}

		return '';
	}

	/**
	 * Run reminders for one frequency.
	 *
	 * @param	string	$frequency	Reminder frequency
	 * @param	string	$parameters	Cron parameters
	 * @return	int					0 if OK, <0 if KO
	 */
	private function runReminder($frequency, $parameters)
	{
		global $conf, $langs, $user;

		$this->error = '';
		$this->errors = array();
		$this->output = '';
		$langs->loadLangs(array('powerplantpv@powerplantpv'));

		if (!isModEnabled('powerplantpv') || !getDolGlobalInt('POWERPLANTPV_MAINTENANCE_ENABLE', 1)) {
			$this->output = $langs->trans('PowerPlantPVMaintenanceReminderSkippedDisabled');
			dol_syslog(__METHOD__.' '.$this->output, LOG_DEBUG);
			return 0;
		}

		$forceExecution = $this->isForceExecutionRequested();
		if (!$forceExecution && !$this->isReminderEnabled($frequency)) {
			$this->output = $langs->trans('PowerPlantPVMaintenanceReminderDisabled');
			dol_syslog(__METHOD__.' frequency='.$frequency.' '.$this->output, LOG_INFO);
			return 0;
		}

		$startTimestamp = $this->getReminderStartTimestamp($frequency);
		if ($startTimestamp <= 0) {
			$this->error = $langs->trans('PowerPlantPVMaintenanceReminderStartTimeInvalid');
			$this->output = $this->error;
			dol_syslog(__METHOD__.' frequency='.$frequency.' '.$this->error, LOG_ERR);
			return 0;
		}

		if (!$forceExecution && dol_now() < $startTimestamp) {
			$this->output = $langs->trans('PowerPlantPVMaintenanceReminderNotDue');
			dol_syslog(__METHOD__.' frequency='.$frequency.' '.$this->output.' start='.(int) $startTimestamp, LOG_DEBUG);
			return 0;
		}

		$recipients = $this->fetchRecipientUsers();
		if (empty($recipients)) {
			$this->output = $langs->trans('PowerPlantPVMaintenanceReminderNoRecipient');
			dol_syslog(__METHOD__.' frequency='.$frequency.' '.$this->output, LOG_WARNING);
			return 0;
		}

		$periodKey = $this->getReminderPeriodKey($frequency, $startTimestamp);
		$lockName = $this->getReminderLockName($frequency, $periodKey);
		$lockAcquired = false;
		if (!$forceExecution) {
			$lockAcquired = $this->acquireMysqlLock($lockName);
			if (!$lockAcquired) {
				$this->output = $langs->trans('PowerPlantPVMaintenanceReminderAlreadyRunning');
				dol_syslog(__METHOD__.' '.$this->output.' lock='.$lockName, LOG_INFO);
				return 0;
			}
		}

		$rows = $this->fetchReminderRows();
		if (empty($rows)) {
			if (!$forceExecution) {
				$this->scheduleNextStart($frequency, $startTimestamp);
			}
			$this->output = $langs->trans('PowerPlantPVMaintenanceReminderNoMaintenance');
			dol_syslog(__METHOD__.' frequency='.$frequency.' '.$this->output, LOG_INFO);
			if ($lockAcquired) {
				$this->releaseMysqlLock($lockName);
			}
			return 0;
		}

		$digest = $this->buildDigest($rows);
		$template = $this->fetchConfiguredTemplate();
		$emailsSent = 0;
		$emailsSkipped = 0;
		$errors = 0;

		foreach ($recipients as $recipientUser) {
			$recipient = trim((string) $recipientUser->email);
			if ($recipient === '') {
				continue;
			}
			if (!$forceExecution && $this->wasRecipientSent($frequency, $recipientUser, $periodKey)) {
				$emailsSkipped++;
				continue;
			}

			$mailContent = $this->prepareMailContent($frequency, $template, $digest, $recipientUser);
			if ($this->sendMail($recipient, $mailContent['subject'], $mailContent['body'], $mailContent['is_html'])) {
				$emailsSent++;
				if (!$forceExecution) {
					$this->markRecipientSent($frequency, $recipientUser, $periodKey);
				}
			} else {
				$errors++;
			}
		}

		if ($lockAcquired) {
			$this->releaseMysqlLock($lockName);
		}

		if ($errors > 0) {
			$this->error = $langs->trans('PowerPlantPVMaintenanceReminderSendFailed', $errors);
			$this->output = $this->error;
			dol_syslog(__METHOD__.' frequency='.$frequency.' errors='.(int) $errors.' sent='.(int) $emailsSent, LOG_ERR);
			return -$errors;
		}

		if (!$forceExecution) {
			$this->scheduleNextStart($frequency, $startTimestamp);
		}

		$this->output = $langs->trans('PowerPlantPVMaintenanceReminderSendSuccess', $emailsSent, $emailsSkipped);
		dol_syslog(__METHOD__.' frequency='.$frequency.' '.$this->output.' rows='.count($rows).' parameters='.$parameters, LOG_INFO);

		return 0;
	}

	/**
	 * Check whether a manual forced execution is requested.
	 *
	 * @return	bool	True if forced
	 */
	private function isForceExecutionRequested()
	{
		if ((int) GETPOST('forcerun', 'int') > 0) {
			return true;
		}
		$action = GETPOST('action', 'aZ09');
		$confirm = GETPOST('confirm', 'alpha');

		return ($action === 'confirm_execute' && $confirm === 'yes');
	}

	/**
	 * Check whether one reminder frequency is enabled.
	 *
	 * @param	string	$frequency	Frequency code
	 * @return	bool				True if enabled
	 */
	private function isReminderEnabled($frequency)
	{
		return (bool) getDolGlobalInt($this->getEnableConstName($frequency), 0);
	}

	/**
	 * Return configured start timestamp for one frequency.
	 *
	 * @param	string	$frequency	Frequency code
	 * @return	int					Start timestamp or 0
	 */
	private function getReminderStartTimestamp($frequency)
	{
		$value = getDolGlobalString($this->getStartConstName($frequency), '');
		if ($value === '') {
			return 0;
		}
		if (is_numeric($value)) {
			return (int) $value;
		}

		return (int) dol_stringtotime($value, 0);
	}

	/**
	 * Fetch selected recipient users.
	 *
	 * @return	array<int,User>	Recipient users
	 */
	private function fetchRecipientUsers()
	{
		$ids = $this->parseIdList(getDolGlobalString('POWERPLANTPV_MAINTENANCE_REMINDER_USER_IDS', ''));
		if (empty($ids)) {
			return array();
		}

		$sql = 'SELECT rowid';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'user';
		$sql .= ' WHERE statut = 1';
		$sql .= " AND email IS NOT NULL AND email <> ''";
		$sql .= ' AND entity IN ('.$this->db->sanitize(getEntity('user')).')';
		$sql .= ' AND rowid IN ('.implode(',', $ids).')';
		$sql .= ' ORDER BY lastname ASC, firstname ASC, login ASC, rowid ASC';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->registerError(__METHOD__.' user lookup failed: '.$this->db->lasterror());
			return array();
		}

		$users = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$recipientUser = new User($this->db);
			if ($recipientUser->fetch((int) $obj->rowid) > 0 && trim((string) $recipientUser->email) !== '') {
				$users[(int) $recipientUser->id] = $recipientUser;
			}
		}
		$this->db->free($resql);

		return array_values($users);
	}

	/**
	 * Fetch maintenance rows to include in reminders.
	 *
	 * @return	array<int,array<string,mixed>>	Maintenance rows
	 */
	private function fetchReminderRows()
	{
		$runUser = $this->buildSystemUser();
		$scheduler = new PowerPlantPVMaintenanceScheduler($this->db);

		return $scheduler->getMaintenanceRows($runUser, array(
			'statuses' => array(
				PowerPlantPVMaintenanceScheduler::STATUS_PLANNED,
				PowerPlantPVMaintenanceScheduler::STATUS_DUE,
				PowerPlantPVMaintenanceScheduler::STATUS_OVERDUE,
			),
		), dol_now());
	}

	/**
	 * Build the maintenance digest.
	 *
	 * @param	array<int,array<string,mixed>>	$rows	Maintenance rows
	 * @return	array{html:string,text:string,count:int}	Digest
	 */
	private function buildDigest(array $rows)
	{
		global $langs;

		$html = '<table class="noborder centpercent" border="1" cellpadding="4" cellspacing="0">';
		$html .= '<tr>';
		$html .= '<th>'.$langs->trans('PowerPlant').'</th>';
		$html .= '<th>'.$langs->trans('Contract').'</th>';
		$html .= '<th>'.$langs->trans('PowerPlantPVMaintenancePeriod').'</th>';
		$html .= '<th>'.$langs->trans('PowerPlantPVMaintenanceStatus').'</th>';
		$html .= '<th>'.$langs->trans('PowerPlantPVMaintenancePrestations').'</th>';
		$html .= '</tr>';

		$textLines = array();
		foreach ($rows as $row) {
			$powerPlantLabel = $this->formatPowerPlantLabel($row);
			$contractLabel = $this->formatContractLabel($row);
			$periodLabel = powerplantpvMaintenanceFormatPeriod((int) $row['period_start'], (int) $row['period_end']);
			$textPeriodLabel = $this->formatPeriodText((int) $row['period_start'], (int) $row['period_end']);
			$statusLabel = $langs->trans(PowerPlantPVMaintenanceScheduler::getStatusLabelKey((string) $row['status']));
			$prestationsHtml = powerplantpvMaintenanceRenderPrestations($row['active_services']);
			$prestationsText = $this->formatPrestationsText($row['active_services']);

			$html .= '<tr>';
			$html .= '<td>'.dol_escape_htmltag($powerPlantLabel).'</td>';
			$html .= '<td>'.dol_escape_htmltag($contractLabel).'</td>';
			$html .= '<td>'.$periodLabel.'</td>';
			$html .= '<td>'.dol_escape_htmltag($statusLabel).'</td>';
			$html .= '<td>'.$prestationsHtml.'</td>';
			$html .= '</tr>';

			$textLines[] = $powerPlantLabel.' | '.$contractLabel.' | '.$textPeriodLabel.' | '.$statusLabel.' | '.$prestationsText;
		}
		$html .= '</table>';

		return array(
			'html' => $html,
			'text' => implode("\n", $textLines),
			'count' => count($rows),
		);
	}

	/**
	 * Fetch the configured email template, if any.
	 *
	 * @return	array<string,string>	Template fields
	 */
	private function fetchConfiguredTemplate()
	{
		global $conf;

		$templateId = getDolGlobalInt('POWERPLANTPV_MAINTENANCE_REMINDER_EMAIL_TEMPLATE', 0);
		if ($templateId <= 0) {
			return array();
		}

		$sql = 'SELECT rowid, label, topic, content';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'c_email_templates';
		$sql .= ' WHERE rowid = '.((int) $templateId);
		$sql .= " AND enabled = '1'";
		$sql .= ' AND entity IN (0, '.((int) $conf->entity).')';
		$sql .= " AND type_template = 'actioncomm_send'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->registerError(__METHOD__.' email template lookup failed: '.$this->db->lasterror());
			return array();
		}

		$template = array();
		if (is_object($obj = $this->db->fetch_object($resql))) {
			$template = array(
				'label' => (string) $obj->label,
				'topic' => (string) $obj->topic,
				'content' => (string) $obj->content,
			);
		}
		$this->db->free($resql);

		return $template;
	}

	/**
	 * Prepare subject and body for one recipient.
	 *
	 * @param	string						$frequency		Reminder frequency
	 * @param	array<string,string>		$template		Email template
	 * @param	array{html:string,text:string,count:int}	$digest	Digest
	 * @param	User						$recipientUser	Recipient user
	 * @return	array{subject:string,body:string,is_html:int}	Mail content
	 */
	private function prepareMailContent($frequency, array $template, array $digest, User $recipientUser)
	{
		global $langs;

		$frequencyLabel = $this->getFrequencyLabel($frequency);
		$defaultSubjectKey = ($frequency === self::FREQUENCY_MONTHLY) ? 'PowerPlantPVMaintenanceReminderMonthlySubject' : 'PowerPlantPVMaintenanceReminderWeeklySubject';
		$subject = !empty($template['topic']) ? (string) $template['topic'] : $langs->transnoentities($defaultSubjectKey, (int) $digest['count']);
		$body = !empty($template['content']) ? (string) $template['content'] : '';
		if ($body === '') {
			$body = '<p>'.$langs->trans('PowerPlantPVMaintenanceReminderDefaultIntro', $frequencyLabel, (int) $digest['count']).'</p>';
			$body .= '__POWERPLANTPV_MAINTENANCE_REMINDER_HTML__';
		} elseif (strpos($body, '__POWERPLANTPV_MAINTENANCE_REMINDER_HTML__') === false
			&& strpos($body, '__POWERPLANTPV_MAINTENANCE_REMINDER_TEXT__') === false
		) {
			$body .= "\n\n".'__POWERPLANTPV_MAINTENANCE_REMINDER_HTML__';
		}

		$substitutions = $this->getSubstitutions($frequencyLabel, $digest, $recipientUser);
		if (function_exists('make_substitutions')) {
			$subject = make_substitutions($subject, $substitutions);
			$body = make_substitutions($body, $substitutions);
		} else {
			$subject = strtr($subject, $substitutions);
			$body = strtr($body, $substitutions);
		}

		$subject = dol_string_nohtmltag(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
		$body = html_entity_decode($body, ENT_QUOTES, 'UTF-8');
		$isHtml = (!empty($body) && preg_match('/<[^>]+>/', $body)) ? 1 : 0;
		if (empty($isHtml)) {
			$body = dol_string_nohtmltag($body);
		}

		return array(
			'subject' => $subject,
			'body' => $body,
			'is_html' => $isHtml,
		);
	}

	/**
	 * Build substitution array.
	 *
	 * @param	string	$frequencyLabel	Frequency label
	 * @param	array{html:string,text:string,count:int}	$digest	Digest
	 * @param	User	$recipientUser	Recipient user
	 * @return	array<string,string>	Substitutions
	 */
	private function getSubstitutions($frequencyLabel, array $digest, User $recipientUser)
	{
		global $langs;

		$substitutions = array();
		if (function_exists('getCommonSubstitutionArray')) {
			$substitutions = getCommonSubstitutionArray($langs, 0, null, null, null);
		}
		$substitutions['__POWERPLANTPV_MAINTENANCE_REMINDER_FREQUENCY__'] = $frequencyLabel;
		$substitutions['__POWERPLANTPV_MAINTENANCE_REMINDER_COUNT__'] = (string) $digest['count'];
		$substitutions['__POWERPLANTPV_MAINTENANCE_REMINDER_HTML__'] = (string) $digest['html'];
		$substitutions['__POWERPLANTPV_MAINTENANCE_REMINDER_TEXT__'] = (string) $digest['text'];
		$substitutions['__USER_FIRSTNAME__'] = (string) $recipientUser->firstname;
		$substitutions['__USER_LASTNAME__'] = (string) $recipientUser->lastname;
		$substitutions['__USER_FULLNAME__'] = dolGetFirstLastname($recipientUser->firstname, $recipientUser->lastname);
		$substitutions['__USER_EMAIL__'] = (string) $recipientUser->email;
		if (function_exists('complete_substitutions_array')) {
			complete_substitutions_array($substitutions, $langs, null, $recipientUser);
		}

		return $substitutions;
	}

	/**
	 * Send one email.
	 *
	 * @param	string	$recipient	Recipient email
	 * @param	string	$subject	Subject
	 * @param	string	$body		Body
	 * @param	int		$isHtml		1 for HTML body
	 * @return	bool				True if sent
	 */
	private function sendMail($recipient, $subject, $body, $isHtml)
	{
		$from = getDolGlobalString('MAIN_MAIL_EMAIL_FROM', '');
		if ($from === '') {
			$from = getDolGlobalString('MAIN_INFO_SOCIETE_MAIL', '');
		}
		if ($from === '') {
			$this->registerError('PowerPlantPV maintenance reminder sender email is not configured.');
			return false;
		}

		$mail = new CMailFile($subject, $recipient, $from, $body, array(), array(), array(), '', '', 0, $isHtml);
		$result = $mail->sendfile();
		if (!$result) {
			$this->registerError('PowerPlantPV maintenance reminder email failed for recipient_hash='.$this->getRecipientLogHash($recipient));
			return false;
		}

		return true;
	}

	/**
	 * Schedule next start for one frequency.
	 *
	 * @param	string	$frequency			Frequency code
	 * @param	int		$currentStart		Current start timestamp
	 * @return	int							Next timestamp
	 */
	private function scheduleNextStart($frequency, $currentStart)
	{
		global $conf, $user;

		$nextStart = ($frequency === self::FREQUENCY_MONTHLY)
			? dol_time_plus_duree((int) $currentStart, 1, 'm', 1)
			: dol_time_plus_duree((int) $currentStart, 1, 'w');
		dolibarr_set_const($this->db, $this->getStartConstName($frequency), (string) $nextStart, 'chaine', 0, '', (int) $conf->entity);
		self::updateCronStartTime($this->db, $frequency, $nextStart, $user);

		return $nextStart;
	}

	/**
	 * Check whether a recipient already received this reminder period.
	 *
	 * @param	string	$frequency		Frequency code
	 * @param	User	$recipientUser	Recipient user
	 * @param	string	$periodKey		Reminder period key
	 * @return	bool					True if already sent
	 */
	private function wasRecipientSent($frequency, User $recipientUser, $periodKey)
	{
		return (getDolGlobalString($this->getRecipientMarkerConstName($frequency, $recipientUser), '') === $periodKey);
	}

	/**
	 * Mark one recipient as sent for this reminder period.
	 *
	 * @param	string	$frequency		Frequency code
	 * @param	User	$recipientUser	Recipient user
	 * @param	string	$periodKey		Reminder period key
	 * @return	bool					True if stored
	 */
	private function markRecipientSent($frequency, User $recipientUser, $periodKey)
	{
		global $conf;

		return dolibarr_set_const($this->db, $this->getRecipientMarkerConstName($frequency, $recipientUser), $periodKey, 'chaine', 0, '', (int) $conf->entity) > 0;
	}

	/**
	 * Return recipient marker constant name.
	 *
	 * @param	string	$frequency		Frequency code
	 * @param	User	$recipientUser	Recipient user
	 * @return	string					Constant name
	 */
	private function getRecipientMarkerConstName($frequency, User $recipientUser)
	{
		$key = strtolower($frequency).'|'.((int) $recipientUser->id).'|'.strtolower(trim((string) $recipientUser->email));

		return 'POWERPLANTPV_MAINTENANCE_REMINDER_SENT_'.strtoupper($frequency).'_'.strtoupper(substr(hash('sha256', $key), 0, 24));
	}

	/**
	 * Build a short non-reversible recipient hash for logs.
	 *
	 * @param	string	$recipient	Recipient email
	 * @return	string				Short hash
	 */
	private function getRecipientLogHash($recipient)
	{
		return substr(hash('sha256', strtolower(trim((string) $recipient))), 0, 12);
	}

	/**
	 * Return reminder period key.
	 *
	 * @param	string	$frequency			Frequency code
	 * @param	int		$startTimestamp		Start timestamp
	 * @return	string						Period key
	 */
	private function getReminderPeriodKey($frequency, $startTimestamp)
	{
		return date('YmdHi', (int) $startTimestamp);
	}

	/**
	 * Build the MySQL advisory lock name.
	 *
	 * @param	string	$frequency	Frequency code
	 * @param	string	$periodKey	Period key
	 * @return	string				Lock name
	 */
	private function getReminderLockName($frequency, $periodKey)
	{
		return 'powerplantpv_maintenance_reminder_'.substr(hash('sha256', $frequency.'|'.$periodKey), 0, 24);
	}

	/**
	 * Acquire a MySQL advisory lock without waiting.
	 *
	 * @param	string	$lockName	Lock name
	 * @return	bool				True when acquired
	 */
	private function acquireMysqlLock($lockName)
	{
		$sql = "SELECT GET_LOCK('".$this->db->escape($lockName)."', 0) as lockstatus";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->registerError(__METHOD__.' '.$this->db->lasterror());
			return false;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);

		return (!empty($obj) && (int) $obj->lockstatus === 1);
	}

	/**
	 * Release a MySQL advisory lock.
	 *
	 * @param	string	$lockName	Lock name
	 * @return	void
	 */
	private function releaseMysqlLock($lockName)
	{
		if ($lockName === '') {
			return;
		}

		$sql = "SELECT RELEASE_LOCK('".$this->db->escape($lockName)."')";
		$this->db->query($sql);
	}

	/**
	 * Return enable constant name.
	 *
	 * @param	string	$frequency	Frequency code
	 * @return	string				Constant name
	 */
	private function getEnableConstName($frequency)
	{
		return ($frequency === self::FREQUENCY_MONTHLY)
			? 'POWERPLANTPV_MAINTENANCE_MONTHLY_REMINDER_ENABLE'
			: 'POWERPLANTPV_MAINTENANCE_WEEKLY_REMINDER_ENABLE';
	}

	/**
	 * Return start constant name.
	 *
	 * @param	string	$frequency	Frequency code
	 * @return	string				Constant name
	 */
	private function getStartConstName($frequency)
	{
		return ($frequency === self::FREQUENCY_MONTHLY)
			? 'POWERPLANTPV_MAINTENANCE_MONTHLY_REMINDER_STARTTIME'
			: 'POWERPLANTPV_MAINTENANCE_WEEKLY_REMINDER_STARTTIME';
	}

	/**
	 * Return translated frequency label.
	 *
	 * @param	string	$frequency	Frequency code
	 * @return	string				Label
	 */
	private function getFrequencyLabel($frequency)
	{
		global $langs;

		return $langs->trans($frequency === self::FREQUENCY_MONTHLY ? 'PowerPlantPVMaintenanceReminderMonthly' : 'PowerPlantPVMaintenanceReminderWeekly');
	}

	/**
	 * Build a system user for scheduler scans.
	 *
	 * @return	User	System user
	 */
	private function buildSystemUser()
	{
		global $user;

		if ($user instanceof User && empty($user->socid)) {
			return $user;
		}

		$runUser = new User($this->db);
		$runUser->id = 0;
		$runUser->admin = 1;
		$runUser->socid = 0;

		return $runUser;
	}

	/**
	 * Format a power plant label.
	 *
	 * @param	array<string,mixed>	$row	Maintenance row
	 * @return	string					Label
	 */
	private function formatPowerPlantLabel(array $row)
	{
		$powerplant = (!empty($row['powerplant']) && is_object($row['powerplant'])) ? $row['powerplant'] : null;
		$label = (string) $row['powerplant_ref'];
		if (is_object($powerplant) && !empty($powerplant->label)) {
			$label .= ' - '.(string) $powerplant->label;
		}

		return trim($label, " -\t\n\r\0\x0B");
	}

	/**
	 * Format a contract label.
	 *
	 * @param	array<string,mixed>	$row	Maintenance row
	 * @return	string					Label
	 */
	private function formatContractLabel(array $row)
	{
		$contract = (!empty($row['contract']) && is_array($row['contract'])) ? $row['contract'] : array();
		if (!empty($contract['ref'])) {
			return (string) $contract['ref'];
		}
		if (!empty($row['contract_id'])) {
			return '#'.((int) $row['contract_id']);
		}

		return '-';
	}

	/**
	 * Format a period as plain text.
	 *
	 * @param	int	$periodStart	Start timestamp
	 * @param	int	$periodEnd		End timestamp
	 * @return	string				Plain text
	 */
	private function formatPeriodText($periodStart, $periodEnd)
	{
		global $langs;

		if ($periodStart <= 0 || $periodEnd <= 0) {
			return $langs->trans('PowerPlantPVMaintenancePeriodMissing');
		}

		return dol_print_date($periodStart, 'day').' - '.dol_print_date($periodEnd, 'day');
	}

	/**
	 * Format prestations as plain text.
	 *
	 * @param	array<int,array<string,mixed>>	$services	Service lines
	 * @return	string										Plain text
	 */
	private function formatPrestationsText($services)
	{
		global $langs;

		$labels = array();
		foreach ($services as $service) {
			if (empty($service['maintenance_services']) || !is_array($service['maintenance_services'])) {
				continue;
			}
			foreach ($service['maintenance_services'] as $maintenanceService) {
				$label = trim((string) $maintenanceService['label']);
				if ($label !== '') {
					$labels[$label] = $label;
				}
			}
		}

		return empty($labels) ? $langs->trans('PowerPlantPVNoMaintenanceServiceOnActiveServices') : implode(', ', array_values($labels));
	}

	/**
	 * Parse a comma-separated id list.
	 *
	 * @param	string	$value	Raw value
	 * @return	array<int,int>	Ids
	 */
	private function parseIdList($value)
	{
		$ids = array();
		foreach (explode(',', (string) $value) as $id) {
			$id = (int) $id;
			if ($id > 0) {
				$ids[$id] = $id;
			}
		}

		return array_values($ids);
	}

	/**
	 * Register an error.
	 *
	 * @param	string	$message	Error message
	 * @return	void
	 */
	private function registerError($message)
	{
		$this->error = $message;
		$this->errors[] = $message;
		dol_syslog($message, LOG_WARNING);
	}
}
