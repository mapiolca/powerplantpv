<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		class/powerplantpvreport.class.php
 * \ingroup		powerplantpv
 * \brief		Generated intervention report snapshot object.
 */

dol_include_once('/powerplantpv/class/powerplantpvreportgeneratedbase.class.php');

/**
 * Generated intervention report snapshot object.
 */
class PowerPlantPVReport extends PowerPlantPVReportGeneratedBase
{
	public const STATUS_DRAFT = 'draft';
	public const STATUS_SAVED = 'saved';

	public $element = 'powerplantpv_report';
	public $table_element = 'powerplantpv_report';
	public $TRIGGER_PREFIX = 'POWERPLANTPV_REPORT';

	/** Publish a report lifecycle change through the native Agenda CRUD triggers. */
	public function recordLifecycleEvent(Fichinter $intervention, User $actor, string $operation, string $partCode = ''): int
	{
		global $langs;

		$labels = array(
			'created' => 'PowerPlantPVReportAgendaCreated',
			'stage_validated' => 'PowerPlantPVReportAgendaStageValidated',
			'signed' => 'PowerPlantPVReportAgendaSigned',
			'stage_reopened' => 'PowerPlantPVReportAgendaStageReopened',
			'finalized' => 'PowerPlantPVReportAgendaFinalized',
		);
		if (!isset($labels[$operation]) || (int) $this->fk_fichinter !== (int) $intervention->id
			|| (int) $this->entity !== (int) $intervention->entity) {
			return -1;
		}
		$langs->load('powerplantpv@powerplantpv');
		$stage = $partCode;
		if (preg_match('/^AC([1-6])$/D', $partCode, $matches)) {
			$stage = $langs->transnoentities('PowerPlantPVAutocontrolPartNumber', (int) $matches[1]);
		} elseif ($stage === '') {
			$stage = $langs->transnoentities('PowerPlantPVReportTab');
		} elseif ($stage === 'FINAL') {
			$stage = $langs->transnoentities('PowerPlantPVAutocontrolFinalValidation');
		}
		$this->context['trigger_reason'] = $operation;
		$this->context['part_code'] = $partCode;
		$this->context['actionmsg2'] = $langs->transnoentities($labels[$operation], (string) $intervention->ref, $stage);
		$result = $this->call_trigger($this->TRIGGER_PREFIX.($operation === 'created' ? '_CREATE' : '_UPDATE'), $actor);
		unset($this->context['trigger_reason'], $this->context['part_code'], $this->context['actionmsg2']);

		return $result;
	}

	/** Link from the native Agenda to the report of the parent intervention. */
	public function getNomUrl($withpicto = 0)
	{
		global $langs, $user;

		$langs->load('powerplantpv@powerplantpv');
		$label = $langs->trans('PowerPlantPVReportTab');
		if (!($user->hasRight('powerplantpv', 'report', 'read') || !empty($user->admin)) || !$user->hasRight('ficheinter', 'lire')) {
			return $label;
		}
		$url = dol_buildpath('/powerplantpv/maintenance_intervention_report.php', 1).'?id='.(int) $this->fk_fichinter;

		return '<a href="'.dol_escape_htmltag($url).'">'.($withpicto ? img_picto('', 'fa-file-alt') : '').$label.'</a>';
	}

	/**
	 * @var array<string,array<string,mixed>> Fields
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => -2),
		'fk_fichinter' => array('type' => 'integer', 'label' => 'Intervention', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1),
		'fk_soc' => array('type' => 'integer', 'label' => 'ThirdParty', 'enabled' => 1, 'position' => 20, 'notnull' => 0, 'visible' => 1),
		'fk_project' => array('type' => 'integer', 'label' => 'Project', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 1),
		'fk_intervention_nature' => array('type' => 'integer', 'label' => 'PowerPlantPVMaintenanceNature', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1),
		'intervention_nature_code' => array('type' => 'varchar(64)', 'label' => 'Code', 'enabled' => 1, 'position' => 41, 'notnull' => 0, 'visible' => 1),
		'intervention_nature_label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 42, 'notnull' => 0, 'visible' => 1),
		'intervention_nature_label_en' => array('type' => 'varchar(255)', 'label' => 'PowerPlantPVEnglishLabel', 'enabled' => 1, 'position' => 43, 'notnull' => 0, 'visible' => 1),
		'fk_report_template' => array('type' => 'integer', 'label' => 'PowerPlantPVReportTemplate', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'report_template_code' => array('type' => 'varchar(64)', 'label' => 'PowerPlantPVReportTemplateCode', 'enabled' => 1, 'position' => 51, 'notnull' => 0, 'visible' => 1),
		'report_template_label' => array('type' => 'varchar(255)', 'label' => 'PowerPlantPVReportTemplate', 'enabled' => 1, 'position' => 52, 'notnull' => 0, 'visible' => 1),
		'report_template_label_en' => array('type' => 'varchar(255)', 'label' => 'PowerPlantPVEnglishLabel', 'enabled' => 1, 'position' => 53, 'notnull' => 0, 'visible' => 1),
		'source_mode' => array('type' => 'varchar(16)', 'label' => 'PowerPlantPVReportSourceMode', 'enabled' => 1, 'position' => 60, 'notnull' => 1, 'visible' => 1, 'default' => 'contract'),
		'status' => array('type' => 'varchar(16)', 'label' => 'Status', 'enabled' => 1, 'position' => 70, 'notnull' => 1, 'visible' => 1, 'default' => self::STATUS_DRAFT),
		'fk_user_technical_sign' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'PowerPlantPVVisualTechnicalServiceSignature', 'enabled' => 1, 'position' => 71, 'notnull' => 0, 'visible' => 0),
		'date_technical_sign' => array('type' => 'datetime', 'label' => 'Date', 'enabled' => 1, 'position' => 72, 'notnull' => 0, 'visible' => 0),
		'fk_user_technician_sign' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'PowerPlantPVReportTechnicianSignature', 'enabled' => 1, 'position' => 74, 'notnull' => 0, 'visible' => 0),
		'date_technician_sign' => array('type' => 'datetime', 'label' => 'Date', 'enabled' => 1, 'position' => 75, 'notnull' => 0, 'visible' => 0),
		'technician_signer_name' => array('type' => 'varchar(255)', 'label' => 'Name', 'enabled' => 1, 'position' => 76, 'notnull' => 0, 'visible' => 0),
		'technical_signer_name' => array('type' => 'varchar(255)', 'label' => 'Name', 'enabled' => 1, 'position' => 73, 'notnull' => 0, 'visible' => 0),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 0, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => 0, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => 0, 'visible' => -2),
	);

	public $fk_fichinter;
	public $fk_soc;
	public $fk_project;
	public $fk_intervention_nature;
	public $intervention_nature_code;
	public $intervention_nature_label;
	public $intervention_nature_label_en;
	public $fk_report_template;
	public $report_template_code;
	public $report_template_label;
	public $report_template_label_en;
	public $source_mode;
	public $status;
	public $fk_user_technical_sign;
	public $date_technical_sign;
	public $technical_signer_name;
	public $fk_user_technician_sign;
	public $date_technician_sign;
	public $technician_signer_name;

	/** Check an internal intervention contact against the authenticated signer. */
	public function isAssignedTechnician(Fichinter $intervention, User $signer): bool
	{
		if ((int) $signer->id <= 0 || (int) $signer->socid > 0 || (int) $this->fk_fichinter !== (int) $intervention->id
			|| (int) $this->entity !== (int) $intervention->entity) {
			return false;
		}
		$contacts = $intervention->liste_contact(-1, 'internal');
		if (!is_array($contacts)) {
			return false;
		}
		foreach ($contacts as $contact) {
			if ((int) ($contact['id'] ?? 0) === (int) $signer->id
				&& in_array($contact['code'] ?? '', array('INTERVENING', 'INTERREPFOLL'), true)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Read the native signature state, including in v20 where Fichinter::fetch() does not load it.
	 * A signing transaction locks the intervention before updating the report.
	 * @return object{signed_status: int|string|null, fk_statut: int|string, last_main_doc: ?string}|null
	 */
	public function readInterventionSignatureState(Fichinter $intervention, bool $lock = false): ?object
	{
		$sql = "SELECT signed_status, fk_statut, last_main_doc FROM ".$this->db->prefix()."fichinter";
		$sql .= " WHERE rowid = ".((int) $intervention->id)." AND entity = ".((int) $intervention->entity);
		if ($lock) {
			$sql .= " FOR UPDATE";
		}
		$result = $this->db->query($sql);
		if (!$result) {
			return null;
		}
		$row = $this->db->fetch_object($result);
		$this->db->free($result);

		return is_object($row) ? $row : null;
	}

	/**
	 * Derive the native signed_status from the existing third-party status and the report's internal signatures.
	 * @param array<string,int> $statuses Native signature statuses for the active core
	 */
	public static function resolveInterventionSignatureStatus(array $statuses, int $currentStatus, bool $technicianSigned, bool $technicalRequired, bool $technicalSigned): int
	{
		$internallySigned = $technicianSigned && (!$technicalRequired || $technicalSigned);
		$receiverSigned = $currentStatus === $statuses['STATUS_SIGNED_RECEIVER']
			|| $currentStatus === ($statuses['STATUS_SIGNED_RECEIVER_ONLINE'] ?? -1);
		if ($internallySigned) {
			return $receiverSigned || $currentStatus === $statuses['STATUS_SIGNED_ALL']
				? $statuses['STATUS_SIGNED_ALL'] : $statuses['STATUS_SIGNED_SENDER'];
		}
		if ($currentStatus === $statuses['STATUS_SIGNED_SENDER']) {
			return $statuses['STATUS_NO_SIGNATURE'];
		}
		if ($currentStatus === $statuses['STATUS_SIGNED_ALL']) {
			return $statuses['STATUS_SIGNED_RECEIVER_ONLINE'] ?? $statuses['STATUS_SIGNED_RECEIVER'];
		}

		return $currentStatus;
	}

	/** @return array<string,int> Native signature status names and values. */
	public static function interventionSignatureStatuses(): array
	{
		if (property_exists(Fichinter::class, 'SIGNED_STATUSES')) {
			return Fichinter::$SIGNED_STATUSES;
		}

		return array(
			'STATUS_NO_SIGNATURE' => (int) constant('Fichinter::STATUS_NO_SIGNATURE'),
			'STATUS_SIGNED_SENDER' => (int) constant('Fichinter::STATUS_SIGNED_SENDER'),
			'STATUS_SIGNED_RECEIVER' => (int) constant('Fichinter::STATUS_SIGNED_RECEIVER'),
			'STATUS_SIGNED_ALL' => (int) constant('Fichinter::STATUS_SIGNED_ALL'),
		);
	}

	/**
	 * Synchronize a finalized maintenance report within the caller's intervention transaction.
	 * @return int 1 on persisted state, 0 when the intervention has no finalized report, -1 on error
	 */
	public function synchronizeInterventionSignature(Fichinter $intervention, User $user, int $observedStatus): int
	{
		$sql = "SELECT rowid, entity, fk_user_technician_sign, fk_user_technical_sign FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE fk_fichinter = ".((int) $intervention->id)." AND entity = ".((int) $intervention->entity);
		$sql .= " AND status = '".self::STATUS_SAVED."' ORDER BY rowid DESC";
		$sql .= $this->db->plimit(1)." FOR UPDATE";
		$result = $this->db->query($sql);
		if (!$result) {
			return -1;
		}
		$row = $this->db->fetch_object($result);
		$this->db->free($result);
		if (!is_object($row)) {
			return 0;
		}
		$this->entity = (int) $row->entity;
		$nextStatus = self::resolveInterventionSignatureStatus(
			self::interventionSignatureStatuses(),
			$observedStatus,
			(int) $row->fk_user_technician_sign > 0,
			$this->technicalSignatureEnabled(),
			(int) $row->fk_user_technical_sign > 0
		);
		if ($nextStatus !== (int) $intervention->signed_status) {
			$lifecycleStatus = (int) $intervention->status;
			$context = $intervention->context;
			$oldCopy = $intervention->oldcopy;
			$result = $intervention->setSignedStatus($user, $nextStatus, 1);
			$intervention->status = $lifecycleStatus;
			$intervention->context = $context;
			$intervention->oldcopy = $oldCopy;
			if ($result <= 0) {
				return -1;
			}
		}
		$intervention->signed_status = $nextStatus;

		return 1;
	}

	/** Whether the native status includes a signature from the third party. */
	public static function interventionSignedByThirdParty(int $status): bool
	{
		$statuses = self::interventionSignatureStatuses();

		return $status === $statuses['STATUS_SIGNED_RECEIVER'] || $status === $statuses['STATUS_SIGNED_ALL']
			|| $status === ($statuses['STATUS_SIGNED_RECEIVER_ONLINE'] ?? -1);
	}

	/** Store the drawing and signer on the same finalized report revision. */
	public function signDrawn(Fichinter $intervention, User $signer, string $role, string $name, string $drawing): int
	{
		require_once __DIR__.'/../lib/powerplantpv_reportsignature.lib.php';
		if (!$this->isSaved() || !in_array($role, powerplantpvReportSignatureRoles(), true)) {
			return -1;
		}
		if ($role === 'technician' && !$this->isAssignedTechnician($intervention, $signer)) {
			return -1;
		}
		if ($role === 'technical' && (!($signer->hasRight('powerplantpv', 'report', 'sign_technical') || !empty($signer->admin))
			|| !$this->technicalSignatureEnabled() || !$this->isTechnicalSupervisor($intervention, $signer))) {
			return -1;
		}

		// The role is selected from a fixed list and is used in column names only.
		$signerColumn = 'fk_user_'.$role.'_sign';
		$sql = "UPDATE ".$this->db->prefix().$this->table_element;
		$sql .= " SET ".$signerColumn." = ".((int) $signer->id);
		$sql .= ", date_".$role."_sign = '".$this->db->idate(dol_now())."'";
		$sql .= ", ".$role."_signer_name = '".$this->db->escape(dol_substr($name, 0, 255))."'";
		$sql .= " WHERE rowid = ".((int) $this->id)." AND entity = ".((int) $this->entity);
		$sql .= " AND status = '".self::STATUS_SAVED."' AND ".$signerColumn." IS NULL";

		if ($this->db->begin() <= 0) {
			return -1;
		}
		$state = $this->readInterventionSignatureState($intervention, true);
		if ($state === null || (int) $state->fk_statut !== Fichinter::STATUS_VALIDATED) {
			$this->db->rollback();
			return -1;
		}
		$intervention->signed_status = (int) $state->signed_status;
		$intervention->last_main_doc = (string) $state->last_main_doc;
		$result = $this->db->query($sql);
		if (!$result || (int) $this->db->affected_rows($result) !== 1) {
			$this->db->rollback();
			return -1;
		}
		$path = powerplantpvReportSignaturePath((int) $this->entity, (int) $this->id, $role, true);
		if (!powerplantpvReportSignatureStore($drawing, $path)) {
			$this->db->rollback();
			return -1;
		}
		if ($this->synchronizeInterventionSignature($intervention, $signer, (int) $state->signed_status) <= 0
			|| $this->recordLifecycleEvent($intervention, $signer, 'signed') < 0
			|| $this->db->commit() <= 0) {
			$this->db->rollback();
			dol_delete_file($path, 1, 1, 1, null, false, 0);
			return -1;
		}

		return 1;
	}

	public function technicalSignatureEnabled(): bool
	{
		global $conf;
		if ((int) $this->entity === (int) $conf->entity) {
			return getDolGlobalInt('POWERPLANTPV_REPORT_PDF_TECHNICAL_SERVICE_SIGNATURE') > 0;
		}
		require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

		return (int) dolibarr_get_const($this->db, 'POWERPLANTPV_REPORT_PDF_TECHNICAL_SERVICE_SIGNATURE', (int) $this->entity) > 0;
	}

	/**
	 * Check the direct supervisor of an assigned internal technician.
	 * @param Fichinter $intervention Authorized intervention with native contacts
	 * @param User $signer Authenticated internal user
	 */
	public function isTechnicalSupervisor($intervention, User $signer): bool
	{
		if ((int) $signer->socid > 0 || (int) $signer->id <= 0 || (int) $this->fk_fichinter !== (int) $intervention->id
			|| (int) $this->entity !== (int) $intervention->entity) {
			return false;
		}
		$contacts = $intervention->liste_contact(-1, 'internal');
		if (!is_array($contacts)) {
			return false;
		}
		$technicians = array_filter($contacts, static function ($contact) {
			return ($contact['code'] ?? '') === 'INTERVENING';
		});
		if (!$technicians) {
			// Standard Dolibarr installations use the internal intervention manager role for the technician.
			$technicians = array_filter($contacts, static function ($contact) {
				return ($contact['code'] ?? '') === 'INTERREPFOLL';
			});
		}
		foreach ($technicians as $contact) {
			$technicianId = (int) ($contact['id'] ?? 0);
			if ($technicianId <= 0 || $technicianId === (int) $signer->id) {
				continue;
			}
			$technician = new User($this->db);
			if ($technician->fetch($technicianId) > 0 && (int) $technician->socid === 0 && (int) $technician->fk_user === (int) $signer->id) {
				return true;
			}
		}

		return false;
	}

	/** Record the signer's identity and date once for this finalized snapshot. */
	public function signAsTechnicalSupervisor($intervention, User $signer, string $signerName): int
	{
		if (!($signer->hasRight('powerplantpv', 'report', 'sign_technical') || !empty($signer->admin))
			|| !$this->isSaved() || !$this->technicalSignatureEnabled() || !$this->isTechnicalSupervisor($intervention, $signer)) {
			return -1;
		}
		$id = (int) $this->id;
		$entity = (int) $this->entity;
		$now = dol_now();
		$sql = "UPDATE ".$this->db->prefix().$this->table_element;
		$sql .= " SET fk_user_technical_sign = ".((int) $signer->id).", date_technical_sign = '".$this->db->idate($now)."'";
		$sql .= ", technical_signer_name = '".$this->db->escape(dol_substr($signerName, 0, 255))."'";
		$sql .= " WHERE rowid = ".$id." AND entity = ".$entity;
		$sql .= " AND status = '".self::STATUS_SAVED."' AND fk_user_technical_sign IS NULL";
		$result = $this->db->query($sql);

		return $result && (int) $this->db->affected_rows($result) === 1 ? 1 : -1;
	}

	/** Check the technical signature under a database lock within a report edit transaction. */
	public function lockUnsignedForEdit(): bool
	{
		$sql = "SELECT fk_user_technical_sign, fk_user_technician_sign FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE rowid = ".((int) $this->id)." AND entity = ".((int) $this->entity)." FOR UPDATE";
		$result = $this->db->query($sql);
		if (!$result) {
			return false;
		}
		$row = $this->db->fetch_object($result);
		$this->db->free($result);

		return is_object($row) && empty($row->fk_user_technical_sign) && empty($row->fk_user_technician_sign);
	}

	/**
	 * Fetch report by intervention.
	 *
	 * @param	int	$interventionId	Intervention id
	 * @return	int					>0 if found, 0 if not found, <0 on error
	 */
	public function fetchByIntervention($interventionId)
	{
		$interventionId = (int) $interventionId;
		if ($interventionId <= 0) {
			return 0;
		}

		$sql = "SELECT rowid";
		$sql .= " FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE fk_fichinter = ".$interventionId;
		$sql .= " AND entity IN (".$this->db->sanitize(getEntity('fichinter')).")";
		$sql .= " ORDER BY rowid DESC";
		$sql .= " LIMIT 1";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($obj)) {
			return 0;
		}

		return $this->fetch((int) $obj->rowid);
	}

	/**
	 * Return true when status is saved.
	 *
	 * @return	bool	True if saved
	 */
	public function isSaved()
	{
		return (string) $this->status === self::STATUS_SAVED;
	}
}
