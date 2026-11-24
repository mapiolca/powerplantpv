<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		class/powerplantpvreportparts.class.php
 * \ingroup		powerplantpv
 * \brief		Parts of a self-inspection report, each one signed on the day of its tests.
 *
 * A self-inspection report ("dossier d'autocontrôle") is one intervention report split into parts.
 * Each part is signed separately in the ERP; its first signature freezes that part only, so the
 * other parts stay editable for the following test days.
 */

dol_include_once('/powerplantpv/class/powerplantpvreport.class.php');
require_once __DIR__.'/powerplantpvreporttemplate.class.php';

/**
 * Parts definitions and per-part signatures.
 */
class PowerPlantPVReportParts
{
	/** Report template code using parts. */
	public const TEMPLATE_CODE = PowerPlantPVReportTemplate::CODE_AUTOCONTROL;

	/** Intervention nature code of the self-inspection report. */
	public const NATURE_CODE = 'AUTOCONTROL';

	/** Final validation of the complete report, signed once every part is signed. */
	public const FINAL_CODE = 'FINAL';
	public const LEGACY_RESERVES_SECTION = 'AC_RESERVES';
	public const LEGACY_RESERVES_FIELD = 'AC_RESERVES_LIST';

	/** @var DoliDB */
	public $db;

	/** @var string */
	public $error = '';

	/** @var array<int,string> */
	public $errors = array();

	/**
	 * Constructor.
	 *
	 * @param	DoliDB	$db	Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/**
	 * Return true when a report template is split into signed parts.
	 *
	 * @param	string	$templateCode	Report template code
	 * @return	bool
	 */
	public static function isPartsTemplate($templateCode): bool
	{
		return (string) $templateCode === self::TEMPLATE_CODE;
	}

	/**
	 * Return true when a report snapshot is split into signed parts.
	 *
	 * @param	object|null	$report	Report snapshot
	 * @return	bool
	 */
	public static function isPartsReport($report): bool
	{
		return is_object($report) && self::isPartsTemplate((string) ($report->report_template_code ?? ''));
	}

	/**
	 * Parts in display order. Labels are translation keys; references follow the quality forms.
	 *
	 * @param	array<int,array<string,mixed>>	$rows	Report section rows, including identification
	 * @return	array<string,array{number:int,ref:string,label:string,short:string,version:string,application_date:string,decision:bool}>
	 */
	public static function definitions(array $rows = array()): array
	{
		$parts = array();
		$refs = array(1 => 'FM-ESS-001', 2 => 'FM-ESS-002', 3 => 'FM-ESS-003', 4 => 'FM-ESS-004', 5 => 'FM-ESS-005', 6 => 'FM-ESS-006');
		foreach ($refs as $number => $ref) {
			$parts['AC'.$number] = array(
				'number' => $number,
				'ref' => $ref,
				'label' => 'PowerPlantPVAutocontrolPart'.$number,
				'short' => 'PowerPlantPVAutocontrolPart'.$number.'Short',
				'version' => '1.0',
				'application_date' => '06/03/2026',
				'decision' => $number <= 2,
			);
		}
		foreach ($rows as $row) {
			$section = $row['section'] ?? null;
			if (!is_object($section)) {
				continue;
			}
			$code = (string) $section->section_code;
			$part = self::partCodeForSection($code);
			$reference = trim((string) ($section->part_ref ?? ''));
			if ($reference !== '' && isset($parts[$part]) && $code === $part.'_IDENT') {
				$parts[$part]['ref'] = $reference;
			}
		}

		return $parts;
	}

	/**
	 * Return the part of a section, or '' for a section shared by the whole report.
	 *
	 * @param	string	$sectionCode	Section code
	 * @return	string					Part code
	 */
	public static function partCodeForSection($sectionCode): string
	{
		$sectionCode = (string) $sectionCode;
		if ($sectionCode === 'DC_ELECTRICAL_MEASURE') {
			return 'AC2';
		}
		if (preg_match('/^(AC[1-6])_/', $sectionCode, $matches)) {
			return $matches[1];
		}

		return '';
	}

	/**
	 * Signer roles of one part, in display order.
	 *
	 * @param string $partCode Stage or final validation code
	 * @param bool $technicalSignatureEnabled Whether to include the technical manager
	 * @return	array<string,string>	Role => translation key
	 */
	public static function roles($partCode = '', bool $technicalSignatureEnabled = false): array
	{
		if ((string) $partCode === self::FINAL_CODE) {
			$roles = array(
				'works_manager' => 'PowerPlantPVPartSignerWorksManager',
			);
			if ($technicalSignatureEnabled) {
				$roles['technical'] = 'PowerPlantPVFinalSignerTechnical';
			}
			$roles['business_manager'] = 'PowerPlantPVFinalSignerBusinessManager';

			return $roles;
		}

		// A part carries one signature: the person who ran its tests that day.
		return array(
			'technician' => 'PowerPlantPVAutocontrolPartSignature',
		);
	}

	/**
	 * Legacy self-inspection fields replaced by the recorded signatures.
	 */
	public static function isManualSignerField(string $fieldCode): bool
	{
		return preg_match('/^AC[1-6]_(?:TECHNICIANS|TRRT)$/D', $fieldCode) === 1;
	}

	public static function isHoursField(string $fieldCode): bool
	{
		return preg_match('/^AC[1-6]_HOURS$/D', $fieldCode) === 1;
	}

	/**
	 * Read the stored interval or parse a legacy time entry without discarding free text.
	 * @return array{start:string,end:string,legacy:string}
	 */
	public static function decodeHours(string $value): array
	{
		$value = trim($value);
		$hours = array('start' => '', 'end' => '', 'legacy' => '');
		$times = preg_split('/\s*(?:\/|[-–—]|\bà\b)\s*/u', $value, 2);
		foreach (array('start', 'end') as $index => $key) {
			$time = trim($times[$index] ?? '');
			if ($time === '') {
				continue;
			}
			if (!preg_match('/^([01]?\d|2[0-3])\s*(?::|h)\s*([0-5]\d)$/iD', $time, $matches)) {
				return array('start' => '', 'end' => '', 'legacy' => $value);
			}
			$hours[$key] = str_pad($matches[1], 2, '0', STR_PAD_LEFT).':'.$matches[2];
		}

		return $hours;
	}

	/**
	 * Validate the two browser time values and store them in the existing report field.
	 * @param array<string,mixed> $submitted Submitted time controls
	 * @return string|null Encoded interval or null when the previous value must be kept
	 */
	public static function encodeHours(array $submitted, string $previousValue): ?string
	{
		$start = $submitted['start'] ?? null;
		$end = $submitted['end'] ?? null;
		foreach (array($start, $end) as $time) {
			if (!is_string($time) || ($time !== '' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $time))) {
				return null;
			}
		}
		if ($start === '' && $end === '') {
			return self::decodeHours($previousValue)['legacy'] === '' ? '' : null;
		}

		return trim($start.' / '.$end);
	}

	/**
	 * Signature image key of one part role, used by the private signature storage.
	 *
	 * @param	string	$partCode	Part code
	 * @param	string	$role		Signer role
	 * @return	string				Image key, '' when invalid
	 */
	public static function imageKey($partCode, $role): string
	{
		$known = isset(self::definitions()[(string) $partCode]) || (string) $partCode === self::FINAL_CODE;
		if (!$known || !isset(self::roles($partCode, true)[(string) $role])) {
			return '';
		}

		return 'part-'.$partCode.'-'.$role;
	}

	/**
	 * Fetch the signatures of a report.
	 *
	 * @param	PowerPlantPVReport	$report	Report
	 * @return	array<string,array<string,stdClass>>|int	Part => role => signature row, <0 on error
	 */
	public function fetchSignatures(PowerPlantPVReport $report)
	{
		$signatures = array();
		if ((int) $report->id <= 0) {
			return $signatures;
		}
		$sql = "SELECT rowid, part_code, signer_role, fk_user, signer_name, date_sign";
		$sql .= " FROM ".$this->db->prefix()."powerplantpv_report_part_signature";
		$sql .= " WHERE entity = ".((int) $report->entity)." AND fk_report = ".((int) $report->id);
		$sql .= " AND signer_role NOT LIKE 'archived-%'";
		$sql .= " ORDER BY date_sign ASC, rowid ASC";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$obj->date_sign = $this->db->jdate($obj->date_sign);
			$signatures[(string) $obj->part_code][(string) $obj->signer_role] = $obj;
		}
		$this->db->free($resql);

		return $signatures;
	}

	/**
	 * Parts frozen by at least one signature.
	 *
	 * @param	array<string,array<string,stdClass>>	$signatures	Signatures
	 * @return	array<string,bool>							Part code => true
	 */
	public static function signedParts($signatures): array
	{
		$signed = array();
		foreach ((array) $signatures as $partCode => $roles) {
			if (!empty($roles)) {
				$signed[(string) $partCode] = true;
			}
		}

		return $signed;
	}

	/**
	 * Snapshot section ids that belong to a signed part and must not be modified anymore.
	 *
	 * @param	PowerPlantPVReport	$report	Report
	 * @return	array<int,bool>|int			Section id => true, <0 on error
	 */
	public function lockedSectionIds(PowerPlantPVReport $report)
	{
		if (!self::isPartsReport($report)) {
			return array();
		}
		$signatures = $this->fetchSignatures($report);
		if (!is_array($signatures)) {
			return -1;
		}
		$signed = self::signedParts($signatures);
		if (empty($signed)) {
			return array();
		}
		$finalSigned = !empty($signed[self::FINAL_CODE]);
		$sql = "SELECT rowid, section_code FROM ".$this->db->prefix()."powerplantpv_report_section";
		$sql .= " WHERE entity = ".((int) $report->entity)." AND fk_report = ".((int) $report->id);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}
		$locked = array();
		while ($obj = $this->db->fetch_object($resql)) {
			if ($finalSigned || !empty($signed[self::partCodeForSection((string) $obj->section_code)])) {
				$locked[(int) $obj->rowid] = true;
			}
		}
		$this->db->free($resql);

		return $locked;
	}

	/**
	 * Check whether a user may sign one role of a part.
	 *
	 * @param	PowerPlantPVReport						$report			Report
	 * @param	Fichinter								$intervention	Intervention
	 * @param	User									$user			Authenticated user
	 * @param	string									$partCode		Part code
	 * @param	string									$role			Signer role
	 * @param	array<string,array<string,stdClass>>	$signatures		Current signatures
	 * @return	bool
	 */
	public function canSign(PowerPlantPVReport $report, $intervention, User $user, $partCode, $role, $signatures): bool
	{
		if (!self::isPartsReport($report) || self::imageKey($partCode, $role) === '' || self::hasGlobalSignature($report)
			|| (int) $user->socid > 0 || !empty($signatures[$partCode][$role])) {
			return false;
		}
		if (!empty($signatures[self::FINAL_CODE]) && $partCode !== self::FINAL_CODE) {
			return false;
		}
		if ($partCode === self::FINAL_CODE && (!$report->isSaved() || self::unsignedParts($signatures))) {
			return false;
		}
		// One person signs one role per part.
		foreach (($signatures[$partCode] ?? array()) as $signedRole => $signature) {
			// Keep historical final signatures for the audit trail without treating them as an active role.
			if ($partCode === self::FINAL_CODE && $signedRole === 'technician') {
				continue;
			}
			if ((int) $signature->fk_user === (int) $user->id) {
				return false;
			}
		}
		if ($role === 'works_manager') {
			return $this->isWorksManager($report, $user);
		}
		if ($role === 'technical') {
			return $user->hasRight('powerplantpv', 'report', 'sign_technical')
				&& $report->technicalSignatureEnabled() && $report->isTechnicalSupervisor($intervention, $user);
		}
		if ($role === 'business_manager') {
			return $user->hasRight('powerplantpv', 'report', 'sign_technical')
				&& (!$report->technicalSignatureEnabled() || !empty($signatures[self::FINAL_CODE]['technical']));
		}
		if ($partCode !== self::FINAL_CODE && $this->isWorksManager($report, $user)) {
			return true;
		}

		return $report->isAssignedTechnician($intervention, $user);
	}

	/**
	 * Whether the report carries one of the global report signatures, which belong to other report types.
	 *
	 * @param	PowerPlantPVReport	$report	Report
	 * @return	bool
	 */
	public static function hasGlobalSignature($report): bool
	{
		return (int) ($report->fk_user_technical_sign ?? 0) > 0 || (int) ($report->fk_user_technician_sign ?? 0) > 0;
	}

	/**
	 * Whether the user is the internal works manager contact of a power plant of the report.
	 *
	 * @param	PowerPlantPVReport	$report	Report
	 * @param	User				$user	Authenticated user
	 * @return	bool
	 */
	public function isWorksManager($report, User $user): bool
	{
		if ((int) $user->socid > 0 || (int) $user->id <= 0 || (int) $report->id <= 0) {
			return false;
		}
		$sql = "SELECT ec.rowid FROM ".$this->db->prefix()."element_contact as ec";
		$sql .= " INNER JOIN ".$this->db->prefix()."c_type_contact as tc ON tc.rowid = ec.fk_c_type_contact";
		$sql .= " INNER JOIN ".$this->db->prefix()."powerplantpv_report_powerplant as rp ON rp.fk_powerplant = ec.element_id";
		$sql .= " WHERE rp.fk_report = ".((int) $report->id)." AND rp.entity IN (".getEntity('powerplantpv_report').")";
		$sql .= " AND rp.entity = ".((int) $report->entity);
		$sql .= " AND tc.element = 'powerplant' AND tc.source = 'internal' AND tc.code = 'CENTPV_INTERNAL_WORKS_MANAGER'";
		$sql .= " AND ec.fk_socpeople = ".((int) $user->id);
		$result = $this->db->query($sql);
		if (!$result) {
			return false;
		}
		$found = is_object($this->db->fetch_object($result));
		$this->db->free($result);

		return $found;
	}

	/**
	 * Record one part signature. The caller has stored the drawn image beforehand.
	 *
	 * @param	PowerPlantPVReport	$report		Report
	 * @param	User				$user		Signer
	 * @param	string				$partCode	Part code
	 * @param	string				$role		Signer role
	 * @param	string				$signerName	Signer name
	 * @return	int								>0 if OK, <0 on error
	 */
	public function sign(PowerPlantPVReport $report, User $user, $partCode, $role, $signerName): int
	{
		if (self::imageKey($partCode, $role) === '') {
			$this->setError('ErrorBadParameters');
			return -1;
		}
		$now = $this->db->idate(dol_now());
		$sql = "INSERT INTO ".$this->db->prefix()."powerplantpv_report_part_signature";
		$sql .= " (entity, fk_report, part_code, signer_role, fk_user, signer_name, date_sign, date_creation)";
		$sql .= " VALUES (".((int) $report->entity).", ".((int) $report->id).", '".$this->db->escape($partCode)."', '".$this->db->escape($role)."'";
		$sql .= ", ".((int) $user->id).", '".$this->db->escape(dol_substr((string) $signerName, 0, 255))."', '".$now."', '".$now."')";
		if (!$this->db->query($sql)) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		return 1;
	}

	/**
	 * Parts of the report without any signature, in display order.
	 *
	 * @param	array<string,array<string,stdClass>>	$signatures	Signatures
	 * @return	array<int,string>							Part codes
	 */
	public static function unsignedParts($signatures): array
	{
		$missing = array();
		foreach (array_keys(self::definitions()) as $partCode) {
			if (empty($signatures[$partCode]['technician'])) {
				$missing[] = $partCode;
			}
		}

		return $missing;
	}

	/**
	 * Return true once the complete report has been validated.
	 *
	 * @param	array<string,array<string,stdClass>>	$signatures	Signatures
	 * @return	bool
	 */
	public static function isFinalSigned($signatures): bool
	{
		return !empty($signatures[self::FINAL_CODE]);
	}

	/** Check the recorded signer or their direct supervisor independently of functional permissions. */
	public function isSignerOrSupervisor(User $user, int $signerId): bool
	{
		if ((int) $user->socid > 0 || (int) $user->id <= 0 || $signerId <= 0) {
			return false;
		}
		if ($signerId === (int) $user->id) {
			return true;
		}
		$signer = new User($this->db);

		return $signer->fetch($signerId) > 0 && (int) $signer->socid === 0 && (int) $signer->fk_user === (int) $user->id;
	}

	/** Reopen a signed stage and archive any final approvals based on it. */
	public function reopenPart(PowerPlantPVReport $report, Fichinter $intervention, User $user, string $partCode): int
	{
		require_once __DIR__.'/../lib/powerplantpv_reportsignature.lib.php';
		if (!$user->hasRight('powerplantpv', 'attestation', 'manage_signed') || !self::isPartsReport($report)
			|| !isset(self::definitions()[$partCode]) || (int) $report->fk_fichinter !== (int) $intervention->id
			|| (int) $report->entity !== (int) $intervention->entity) {
			$this->setError('ErrorForbidden');
			return -1;
		}
		if ($this->db->begin() <= 0) {
			$this->setError($this->db->lasterror());
			return -1;
		}
		$state = $report->readInterventionSignatureState($intervention, true);
		$closedStatus = defined('Fichinter::STATUS_CLOSED') ? (int) constant('Fichinter::STATUS_CLOSED') : 3;
		$locked = $state !== null && (int) $state->fk_statut !== $closedStatus
			&& !PowerPlantPVReport::interventionSignedByThirdParty((int) $state->signed_status) && $report->lockUnsignedForEdit();
		$signatures = $locked ? $this->fetchSignatures($report) : -1;
		$stageSigner = is_array($signatures) ? ($signatures[$partCode]['technician'] ?? null) : null;
		if (!is_object($stageSigner) || !$this->isSignerOrSupervisor($user, (int) $stageSigner->fk_user)) {
			$this->db->rollback();
			$this->setError('ErrorForbidden');
			return -1;
		}

		$moved = array();
		$committed = false;
		try {
			foreach (array($partCode, self::FINAL_CODE) as $code) {
				foreach ($signatures[$code] ?? array() as $role => $signature) {
					$path = powerplantpvReportSignaturePath((int) $report->entity, (int) $report->id, 'part-'.$code.'-'.$role);
					$rowId = (int) $signature->rowid;
					$archivedRole = 'archived-'.$rowId;
					$archive = dirname($path).'/'.$archivedRole.'.png';
					if ($path === '' || $rowId <= 0 || is_link($archive) || file_exists($archive)
						|| (is_file($path) && !rename($path, $archive))) {
						$this->setError('PowerPlantPVPartReopenFailed');
						return -1;
					}
					if (is_file($archive)) {
						$moved[$archive] = $path;
					}
					$sql = "UPDATE ".$this->db->prefix()."powerplantpv_report_part_signature SET signer_role = '".$archivedRole."'";
					$sql .= " WHERE rowid = ".$rowId." AND entity = ".((int) $report->entity)." AND fk_report = ".((int) $report->id);
					if (!$this->db->query($sql)) {
						$this->setError($this->db->lasterror());
						return -1;
					}
				}
			}
			$report->status = PowerPlantPVReport::STATUS_DRAFT;
			if ($report->update($user, 1) < 0 || $report->recordLifecycleEvent($intervention, $user, 'stage_reopened', $partCode) < 0
				|| $this->db->commit() <= 0) {
				$this->setError($report->error ?: 'PowerPlantPVPartReopenFailed');
				return -1;
			}
			$committed = true;
			return 1;
		} finally {
			if (!$committed) {
				foreach ($moved as $archive => $path) {
					if (!rename($archive, $path)) {
						dol_syslog(__METHOD__.' failed to restore an archived report signature', LOG_ERR);
					}
				}
				$this->db->rollback();
			}
		}
	}

	/**
	 * Store an error.
	 *
	 * @param	string	$error	Error
	 * @return	void
	 */
	private function setError($error)
	{
		$this->error = (string) $error;
		$this->errors[] = (string) $error;
	}
}
