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
 * \file		class/powerplantpvattestation.class.php
 * \ingroup		powerplantpv
 * \brief		CRUD class for PowerPlantPV attestations.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
dol_include_once('/powerplantpv/class/powerplantpvattestationtypes.class.php');

/**
 * Class for PowerPlantPV attestations.
 */
class PowerPlantPVAttestation extends CommonObject
{
	public $module = 'powerplantpv';
	public $mainmodule = 'powerplantpv';
	public $element = 'attestation';
	public $TRIGGER_PREFIX = 'POWERPLANTPV_ATTESTATION';
	public $table_element = 'powerplantpv_attestation';
	public $picto = 'fa-file-signature';
	public $isextrafieldmanaged = 0;
	public $ismultientitymanaged = 1;

	public const STATUS_DRAFT = 0;
	public const STATUS_VALIDATED = 1;
	public const STATUS_PENDING_SIGNATURE = 2;
	public const STATUS_SIGNED = 3;
	public const STATUS_CANCELED = 9;

	/**
	 * @var array<string,array<string,mixed>>
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'default' => '(PROV)'),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => -2, 'default' => 1, 'index' => 1),
		'fk_powerplant' => array('type' => 'integer:PowerPlant:powerplantpv/class/powerplant.class.php:1:((entity:IN:__SHARED_ENTITIES__))', 'label' => 'PowerPlant', 'picto' => 'fa-sun', 'enabled' => 1, 'position' => 30, 'notnull' => -1, 'visible' => 1, 'index' => 1, 'css' => 'maxwidth500'),
		'fk_soc' => array('type' => 'integer:Societe:societe/class/societe.class.php:1:((status:=:1) AND (entity:IN:__SHARED_ENTITIES__))', 'label' => 'ThirdParty', 'picto' => 'company', 'enabled' => "isModEnabled('societe')", 'position' => 40, 'notnull' => -1, 'visible' => 1, 'index' => 1, 'css' => 'maxwidth500'),
		'fk_project' => array('type' => 'integer:Project:projet/class/project.class.php:1', 'label' => 'Project', 'picto' => 'project', 'enabled' => "isModEnabled('project')", 'position' => 50, 'notnull' => -1, 'visible' => 1, 'index' => 1, 'css' => 'maxwidth500'),
		'type_code' => array('type' => 'varchar(64)', 'label' => 'AttestationType', 'enabled' => 1, 'position' => 60, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'arrayofkeyval' => array()),
		'model_pdf' => array('type' => 'varchar(128)', 'label' => 'ModelPDF', 'enabled' => 1, 'position' => 70, 'notnull' => -1, 'visible' => 0),
		'project_name' => array('type' => 'varchar(255)', 'label' => 'ProjectName', 'enabled' => 1, 'position' => 80, 'notnull' => 0, 'visible' => 1, 'searchall' => 1, 'css' => 'minwidth300'),
		'address' => array('type' => 'varchar(255)', 'label' => 'Address', 'enabled' => 1, 'position' => 90, 'notnull' => 0, 'visible' => 3, 'searchall' => 1, 'css' => 'minwidth300'),
		'zip' => array('type' => 'varchar(25)', 'label' => 'Zip', 'enabled' => 1, 'position' => 100, 'notnull' => 0, 'visible' => 1, 'searchall' => 1, 'csslist' => 'nowraponall'),
		'town' => array('type' => 'varchar(255)', 'label' => 'Town', 'enabled' => 1, 'position' => 110, 'notnull' => 0, 'visible' => 1, 'searchall' => 1),
		'fk_pays' => array('type' => 'sellist:c_country:label:rowid::active=1', 'label' => 'Country', 'enabled' => 1, 'position' => 120, 'notnull' => -1, 'visible' => 3, 'index' => 1),
		'installer_name' => array('type' => 'varchar(255)', 'label' => 'AttestationInstallerName', 'enabled' => 1, 'position' => 130, 'notnull' => 0, 'visible' => 1, 'searchall' => 1),
		'installer_address' => array('type' => 'varchar(255)', 'label' => 'AttestationInstallerAddress', 'enabled' => 1, 'position' => 140, 'notnull' => 0, 'visible' => 3),
		'installer_zip' => array('type' => 'varchar(25)', 'label' => 'AttestationInstallerZip', 'enabled' => 1, 'position' => 150, 'notnull' => 0, 'visible' => 3),
		'installer_town' => array('type' => 'varchar(255)', 'label' => 'AttestationInstallerTown', 'enabled' => 1, 'position' => 160, 'notnull' => 0, 'visible' => 3),
		'installer_fk_pays' => array('type' => 'sellist:c_country:label:rowid::active=1', 'label' => 'AttestationInstallerCountry', 'enabled' => 1, 'position' => 170, 'notnull' => -1, 'visible' => 3),
		'installer_siret' => array('type' => 'varchar(64)', 'label' => 'SIRET', 'enabled' => 1, 'position' => 180, 'notnull' => 0, 'visible' => 3),
		'installer_vat' => array('type' => 'varchar(64)', 'label' => 'VATIntra', 'enabled' => 1, 'position' => 190, 'notnull' => 0, 'visible' => 3),
		'writer_name' => array('type' => 'varchar(255)', 'label' => 'AttestationWriterName', 'enabled' => 1, 'position' => 200, 'notnull' => 0, 'visible' => 3),
		'writer_function' => array('type' => 'varchar(255)', 'label' => 'AttestationWriterFunction', 'enabled' => 1, 'position' => 210, 'notnull' => 0, 'visible' => 3),
		'date_attestation' => array('type' => 'date', 'label' => 'AttestationDate', 'enabled' => 1, 'position' => 220, 'notnull' => 0, 'visible' => 1, 'index' => 1),
		'place' => array('type' => 'varchar(255)', 'label' => 'AttestationPlace', 'enabled' => 1, 'position' => 230, 'notnull' => 0, 'visible' => 3),
		'date_setting' => array('type' => 'date', 'label' => 'AttestationSettingDate', 'enabled' => 1, 'position' => 240, 'notnull' => 0, 'visible' => 3),
		'date_completion' => array('type' => 'date', 'label' => 'AttestationCompletionDate', 'enabled' => 1, 'position' => 250, 'notnull' => 0, 'visible' => 3),
		'bta_contract_number' => array('type' => 'varchar(128)', 'label' => 'AttestationBtaContractNumber', 'enabled' => 1, 'position' => 260, 'notnull' => 0, 'visible' => 1, 'searchall' => 1),
		'max_export_power_kw' => array('type' => 'double(24,8)', 'label' => 'AttestationMaxExportPowerKw', 'enabled' => 1, 'position' => 270, 'notnull' => 0, 'visible' => 3, 'css' => 'right', 'cssview' => 'right', 'csslist' => 'right'),
		'max_frequency_hz' => array('type' => 'double(24,8)', 'label' => 'AttestationMaxFrequencyHz', 'enabled' => 1, 'position' => 280, 'notnull' => 0, 'visible' => 3, 'css' => 'right', 'cssview' => 'right', 'csslist' => 'right'),
		'landscape_integration_prime' => array('type' => 'boolean', 'label' => 'AttestationLandscapeIntegrationPrime', 'enabled' => 1, 'position' => 290, 'notnull' => 0, 'visible' => 3),
		'fk_user_sign' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'AttestationSigner', 'picto' => 'user', 'enabled' => 1, 'position' => 300, 'notnull' => -1, 'visible' => 1, 'index' => 1),
		'date_signature' => array('type' => 'datetime', 'label' => 'AttestationSignatureDate', 'enabled' => 1, 'position' => 310, 'notnull' => 0, 'visible' => 1, 'index' => 1),
		'signature_ip' => array('type' => 'varchar(64)', 'label' => 'IPAddress', 'enabled' => 1, 'position' => 320, 'notnull' => 0, 'visible' => 0),
		'signature_user_agent' => array('type' => 'varchar(255)', 'label' => 'UserAgent', 'enabled' => 1, 'position' => 330, 'notnull' => 0, 'visible' => 0),
		'signature_hash' => array('type' => 'varchar(128)', 'label' => 'AttestationSignatureHash', 'enabled' => 1, 'position' => 340, 'notnull' => 0, 'visible' => 0),
		'signature_file' => array('type' => 'varchar(255)', 'label' => 'AttestationSignatureFile', 'enabled' => 1, 'position' => 350, 'notnull' => 0, 'visible' => 0),
		'signed_pdf_file' => array('type' => 'varchar(255)', 'label' => 'AttestationSignedPdfFile', 'enabled' => 1, 'position' => 360, 'notnull' => 0, 'visible' => 0),
		'note_public' => array('type' => 'html', 'label' => 'NotePublic', 'enabled' => 1, 'position' => 400, 'notnull' => 0, 'visible' => 0),
		'note_private' => array('type' => 'html', 'label' => 'NotePrivate', 'enabled' => 1, 'position' => 410, 'notnull' => 0, 'visible' => 0),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'picto' => 'user', 'enabled' => 1, 'position' => 520, 'notnull' => 1, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'picto' => 'user', 'enabled' => 1, 'position' => 530, 'notnull' => -1, 'visible' => -2),
		'last_main_doc' => array('type' => 'varchar(255)', 'label' => 'LastMainDoc', 'enabled' => 1, 'position' => 540, 'notnull' => 0, 'visible' => 0),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 550, 'notnull' => -1, 'visible' => -2),
		'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'position' => 600, 'notnull' => 1, 'visible' => 1, 'default' => self::STATUS_DRAFT, 'index' => 1, 'arrayofkeyval' => array(self::STATUS_DRAFT => 'Draft', self::STATUS_VALIDATED => 'Validated', self::STATUS_PENDING_SIGNATURE => 'AttestationPendingSignature', self::STATUS_SIGNED => 'AttestationSigned', self::STATUS_CANCELED => 'Canceled')),
	);

	public $rowid;
	public $ref;
	public $entity;
	public $fk_powerplant;
	public $fk_soc;
	public $socid;
	public $fk_project;
	public $type_code;
	public $model_pdf;
	public $project_name;
	public $address;
	public $zip;
	public $town;
	public $fk_pays;
	public $installer_name;
	public $installer_address;
	public $installer_zip;
	public $installer_town;
	public $installer_fk_pays;
	public $installer_siret;
	public $installer_vat;
	public $writer_name;
	public $writer_function;
	public $date_attestation;
	public $place;
	public $date_setting;
	public $date_completion;
	public $bta_contract_number;
	public $max_export_power_kw;
	public $max_frequency_hz;
	public $landscape_integration_prime;
	public $fk_user_sign;
	public $date_signature;
	public $signature_ip;
	public $signature_user_agent;
	public $signature_hash;
	public $signature_file;
	public $signed_pdf_file;
	public $note_public;
	public $note_private;
	public $date_creation;
	public $tms;
	public $fk_user_creat;
	public $fk_user_modif;
	public $last_main_doc;
	public $import_key;
	public $status;

	/**
	 * @var PowerPlantPVAttestationEquipmentLine[]
	 */
	public $lines = array();

	/**
	 * Constructor.
	 *
	 * @param	DoliDB	$db	Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		global $langs;
		if (is_object($langs)) {
			$this->fields['type_code']['arrayofkeyval'] = PowerPlantPVAttestationTypes::getTypeLabels($langs);
		}
	}

	/**
	 * Create object into database.
	 *
	 * @param	User		$user		User
	 * @param	int<0,1>	$notrigger	1 disables triggers
	 * @return	int<-1,max>				Object id or <0
	 */
	public function create(User $user, $notrigger = 0)
	{
		global $conf;

		if (empty($this->fk_soc) && !empty($this->socid)) {
			$this->fk_soc = (int) $this->socid;
		}
		if (!empty($this->fk_soc)) {
			$this->socid = (int) $this->fk_soc;
		}
		if (empty($this->entity)) {
			$this->entity = (int) $conf->entity;
		}
		if (empty($this->ref)) {
			$this->ref = '(PROV)';
		}
		if (!isset($this->status)) {
			$this->status = self::STATUS_DRAFT;
		}
		$this->setDefaultModelFromType();
		if (empty($this->date_attestation)) {
			$this->date_attestation = dol_now();
		}

		$this->db->begin();
		$result = $this->createCommon($user, 1);
		if ($result < 0) {
			$this->db->rollback();
			return $result;
		}

		if ($this->ref === '(PROV)') {
			$refresult = $this->assignFinalReference($user);
			if ($refresult < 0) {
				$this->db->rollback();
				return -1;
			}
		}

		if (!empty($this->lines)) {
			$lineResult = $this->replaceEquipmentLines($this->lines);
			if ($lineResult < 0) {
				$this->db->rollback();
				return -1;
			}
		}

		if (!$notrigger && $this->callAttestationTrigger('CREATE', $user) < 0) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();

		return $result;
	}

	/**
	 * Fetch object.
	 *
	 * @param	int		$id		Object id
	 * @param	string	$ref	Object ref
	 * @return	int				>0 if found, 0 not found, <0 error
	 */
	public function fetch($id, $ref = '')
	{
		$result = $this->fetchCommon($id, $ref);
		if ($result > 0) {
			$this->socid = (int) $this->fk_soc;
			$this->fetchEquipmentLines();
		}

		return $result;
	}

	/**
	 * Update object.
	 *
	 * @param	User		$user		User
	 * @param	int<0,1>	$notrigger	1 disables triggers
	 * @return	int<-1,1>				>0 if OK
	 */
	public function update(User $user, $notrigger = 0)
	{
		if ($this->status == self::STATUS_SIGNED) {
			$this->error = 'AttestationSignedCannotBeModified';
			return -1;
		}
		$this->setDefaultModelFromType();

		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Delete object.
	 *
	 * @param	User		$user		User
	 * @param	int<0,1>	$notrigger	1 disables triggers
	 * @return	int<-1,1>				>0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		if (!in_array((int) $this->status, array(self::STATUS_DRAFT, self::STATUS_CANCELED), true)) {
			$this->error = 'AttestationOnlyDraftOrCanceledCanBeDeleted';
			return -1;
		}

		$this->db->begin();
		$result = $this->deleteEquipmentLines();
		if ($result < 0) {
			$this->db->rollback();
			return -1;
		}

		if (!$notrigger && $this->callAttestationTrigger('DELETE', $user) < 0) {
			$this->db->rollback();
			return -1;
		}

		$result = $this->deleteCommon($user, 1);
		if ($result < 0) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();

		return $result;
	}

	/**
	 * Validate attestation.
	 *
	 * @param	User		$user		User
	 * @param	int<0,1>	$notrigger	1 disables triggers
	 * @return	int<-1,1>				>0 if OK
	 */
	public function validate(User $user, $notrigger = 0)
	{
		if ((int) $this->status !== self::STATUS_DRAFT) {
			$this->error = 'AttestationOnlyDraftCanBeValidated';
			return -1;
		}
		if ($this->checkBusinessRequirements() < 0) {
			return -1;
		}

		return $this->setStatusCommon($user, self::STATUS_VALIDATED, $notrigger, $this->TRIGGER_PREFIX.'_VALIDATE');
	}

	/**
	 * Move attestation to pending signature.
	 *
	 * @param	User		$user		User
	 * @param	int<0,1>	$notrigger	1 disables triggers
	 * @return	int<-1,1>				>0 if OK
	 */
	public function sendToSignature(User $user, $notrigger = 0)
	{
		if (!in_array((int) $this->status, array(self::STATUS_VALIDATED, self::STATUS_PENDING_SIGNATURE), true)) {
			$this->error = 'AttestationMustBeValidatedBeforeSignature';
			return -1;
		}

		return $this->setStatusCommon($user, self::STATUS_PENDING_SIGNATURE, $notrigger, $this->TRIGGER_PREFIX.'_SENDSIGN');
	}

	/**
	 * Cancel attestation.
	 *
	 * @param	User		$user		User
	 * @param	int<0,1>	$notrigger	1 disables triggers
	 * @return	int<-1,1>				>0 if OK
	 */
	public function cancel(User $user, $notrigger = 0)
	{
		if ((int) $this->status === self::STATUS_SIGNED) {
			$this->error = 'AttestationSignedCannotBeCanceled';
			return -1;
		}

		return $this->setStatusCommon($user, self::STATUS_CANCELED, $notrigger, $this->TRIGGER_PREFIX.'_CANCEL');
	}

	/**
	 * Store signature metadata and mark signed.
	 *
	 * @param	User	$user				Signer
	 * @param	string	$signatureFile		Relative signature file
	 * @param	string	$signedPdfFile		Relative signed PDF file
	 * @param	string	$signatureHash		Signed PDF hash
	 * @return	int<-1,1>					>0 if OK
	 */
	public function sign(User $user, $signatureFile, $signedPdfFile, $signatureHash)
	{
		if (!in_array((int) $this->status, array(self::STATUS_VALIDATED, self::STATUS_PENDING_SIGNATURE), true)) {
			$this->error = 'AttestationMustBeValidatedBeforeSignature';
			return -1;
		}

		$this->db->begin();
		$remoteAddr = !empty($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		$userAgent = !empty($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : '';
		$sql = "UPDATE ".$this->db->prefix().$this->table_element;
		$sql .= " SET status = ".self::STATUS_SIGNED;
		$sql .= ", fk_user_sign = ".((int) $user->id);
		$sql .= ", date_signature = '".$this->db->idate(dol_now())."'";
		$sql .= ", signature_ip = '".$this->db->escape($remoteAddr)."'";
		$sql .= ", signature_user_agent = '".$this->db->escape($userAgent)."'";
		$sql .= ", signature_file = '".$this->db->escape($signatureFile)."'";
		$sql .= ", signed_pdf_file = '".$this->db->escape($signedPdfFile)."'";
		$sql .= ", signature_hash = '".$this->db->escape($signatureHash)."'";
		$sql .= ", fk_user_modif = ".((int) $user->id);
		$sql .= " WHERE rowid = ".((int) $this->id);
		$sql .= " AND entity = ".((int) $this->entity);

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		$this->status = self::STATUS_SIGNED;
		$this->fk_user_sign = (int) $user->id;
		$this->date_signature = dol_now();
		$this->signature_file = $signatureFile;
		$this->signed_pdf_file = $signedPdfFile;
		$this->signature_hash = $signatureHash;

		if ($this->callAttestationTrigger('SIGN', $user) < 0) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();

		return 1;
	}

	/**
	 * Generate document.
	 *
	 * @param	string				$modele			Model
	 * @param	Translate			$outputlangs	Output language
	 * @param	int<0,1>			$hidedetails	Hide details
	 * @param	int<0,1>			$hidedesc		Hide description
	 * @param	int<0,1>			$hideref		Hide ref
	 * @param	array<string,mixed>|null	$moreparams	More params
	 * @return	int									0 if KO, 1 if OK
	 */
	public function generateDocument($modele, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0, $moreparams = null)
	{
		global $langs;

		$langs->load('powerplantpv@powerplantpv');
		if ($this->checkBusinessRequirements() < 0) {
			return -1;
		}
		if (!dol_strlen($modele)) {
			$modele = !empty($this->model_pdf) ? $this->model_pdf : getDolGlobalString('POWERPLANTPV_ATTESTATION_ADDON_PDF', 'attestation_bridage_dynamique');
		}

		$result = $this->commonGenerateDocument('core/modules/attestation/doc/', $modele, $outputlangs, $hidedetails, $hidedesc, $hideref, $moreparams);
		if ($result > 0) {
			global $user;
			$this->callAttestationTrigger('GENERATEPDF', $user);
		}

		return $result;
	}

	/**
	 * Return next free ref.
	 *
	 * @return	string	Next ref
	 */
	public function getNextNumRef()
	{
		global $conf, $langs;

		$langs->load('powerplantpv@powerplantpv');
		if (!getDolGlobalString('POWERPLANTPV_ATTESTATION_ADDON')) {
			$conf->global->POWERPLANTPV_ATTESTATION_ADDON = 'mod_attestation_standard';
		}

		$moduleName = getDolGlobalString('POWERPLANTPV_ATTESTATION_ADDON', 'mod_attestation_standard');
		$file = $moduleName.'.php';
		$loaded = false;
		foreach (array_merge(array('/'), (array) $conf->modules_parts['models']) as $reldir) {
			$dir = dol_buildpath($reldir.'core/modules/attestation/');
			$loaded = $loaded || @include_once $dir.$file;
		}

		if (!$loaded || !class_exists($moduleName)) {
			$this->error = $langs->trans('Error').' : '.$moduleName;
			return '';
		}

		$module = new $moduleName($this->db);
		$numref = $module->getNextValue($this);
		if ($numref != '' && $numref != '-1') {
			return $numref;
		}

		$this->error = $module->error;
		return '';
	}

	/**
	 * Return URL label.
	 *
	 * @param	int		$withpicto		Picto mode
	 * @param	string	$option			Option
	 * @param	int		$notooltip		Disable tooltip
	 * @param	string	$morecss		More CSS
	 * @param	int		$save_lastsearch_value	Save search
	 * @return	string					HTML link
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		global $langs;

		$result = '';
		$label = '<u>'.$langs->trans('Attestation').'</u>';
		$label .= '<br><b>'.$langs->trans('Ref').':</b> '.dol_escape_htmltag($this->ref);
		if (!empty($this->project_name)) {
			$label .= '<br><b>'.$langs->trans('ProjectName').':</b> '.dol_escape_htmltag($this->project_name);
		}

		$url = dol_buildpath('/powerplantpv/attestation_card.php', 1).'?id='.(int) $this->id;
		if ($option !== 'nolink') {
			$result .= '<a href="'.$url.'"';
			if (!$notooltip) {
				$result .= ' title="'.dol_escape_htmltag($label).'" class="classfortooltip '.$morecss.'"';
			} elseif ($morecss) {
				$result .= ' class="'.$morecss.'"';
			}
			$result .= '>';
		}
		if ($withpicto) {
			$result .= img_picto('', $this->picto, 'class="paddingright"');
		}
		$result .= dol_escape_htmltag($this->ref);
		if ($option !== 'nolink') {
			$result .= '</a>';
		}

		return $result;
	}

	/**
	 * Return translated status.
	 *
	 * @param	int		$mode	Display mode
	 * @return	string			Status label
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut((int) $this->status, $mode);
	}

	/**
	 * Return translated status for status value.
	 *
	 * @param	int		$status	Status
	 * @param	int		$mode	Display mode
	 * @return	string			Status label
	 */
	public function LibStatut($status, $mode = 0)
	{
		global $langs;

		$labels = array(
			self::STATUS_DRAFT => 'Draft',
			self::STATUS_VALIDATED => 'Validated',
			self::STATUS_PENDING_SIGNATURE => 'AttestationPendingSignature',
			self::STATUS_SIGNED => 'AttestationSigned',
			self::STATUS_CANCELED => 'Canceled',
		);
		$statusType = array(
			self::STATUS_DRAFT => 'status0',
			self::STATUS_VALIDATED => 'status4',
			self::STATUS_PENDING_SIGNATURE => 'status1',
			self::STATUS_SIGNED => 'status6',
			self::STATUS_CANCELED => 'status9',
		);

		return dolGetStatus($langs->trans(isset($labels[$status]) ? $labels[$status] : 'Unknown'), '', '', isset($statusType[$status]) ? $statusType[$status] : 'status0', $mode);
	}

	/**
	 * Initialize specimen.
	 *
	 * @return	void
	 */
	public function initAsSpecimen()
	{
		$this->id = 0;
		$this->ref = 'ATT2606-0001';
		$this->entity = 1;
		$this->type_code = PowerPlantPVAttestationTypes::TYPE_BRIDAGE_DYNAMIQUE_ONDULEUR;
		$this->model_pdf = PowerPlantPVAttestationTypes::getModelForType($this->type_code);
		$this->project_name = 'Projet photovoltaïque exemple';
		$this->address = '1 rue du Soleil';
		$this->zip = '75000';
		$this->town = 'Paris';
		$this->installer_name = 'Installateur exemple';
		$this->writer_name = 'Rédacteur exemple';
		$this->writer_function = 'Responsable technique';
		$this->place = 'Paris';
		$this->date_attestation = dol_now();
		$this->max_export_power_kw = 36;
		$this->max_frequency_hz = 51.5;
		$this->status = self::STATUS_DRAFT;
		$this->lines = array(
			PowerPlantPVAttestationEquipmentLine::fromArray(array(
				'equipment_type' => 'INVERTER',
				'designation' => 'Onduleur exemple',
				'brand' => 'Marque',
				'model' => 'Modèle',
				'serial_number' => 'SN-EXAMPLE',
				'bridage_enabled' => 1,
				'bridage_type' => 'DYNAMIC',
				'max_power_kw' => 36,
				'rank' => 1,
			)),
		);
	}

	/**
	 * Fetch equipment lines.
	 *
	 * @return	int<-1,max>		Line count or <0
	 */
	public function fetchEquipmentLines()
	{
		$this->lines = array();
		if (empty($this->id)) {
			return 0;
		}

		$sql = "SELECT rowid, entity, fk_attestation, fk_powerplant_line, fk_product, equipment_type, designation, brand, model, manufacturer, serial_number, bridage_enabled, bridage_type, max_power_kw, rank";
		$sql .= " FROM ".$this->db->prefix()."powerplantpv_attestation_equipment";
		$sql .= " WHERE fk_attestation = ".((int) $this->id);
		$sql .= " AND entity = ".((int) $this->entity);
		$sql .= " ORDER BY rank ASC, rowid ASC";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$this->lines[] = PowerPlantPVAttestationEquipmentLine::fromObject($obj);
		}
		$this->db->free($resql);

		return count($this->lines);
	}

	/**
	 * Replace equipment lines.
	 *
	 * @param	PowerPlantPVAttestationEquipmentLine[]	$lines	Lines
	 * @return	int<-1,1>										>0 if OK
	 */
	public function replaceEquipmentLines($lines)
	{
		$result = $this->deleteEquipmentLines();
		if ($result < 0) {
			return -1;
		}

		$rank = 0;
		foreach ($lines as $line) {
			$rank++;
			if (!($line instanceof PowerPlantPVAttestationEquipmentLine)) {
				continue;
			}
			$line->fk_attestation = (int) $this->id;
			$line->entity = (int) $this->entity;
			if (empty($line->rank)) {
				$line->rank = $rank;
			}
			$result = $line->insert($this->db);
			if ($result < 0) {
				$this->error = $line->error;
				return -1;
			}
		}

		$this->lines = $lines;

		return 1;
	}

	/**
	 * Delete equipment lines.
	 *
	 * @return	int<-1,1>	>0 if OK
	 */
	public function deleteEquipmentLines()
	{
		if (empty($this->id)) {
			return 1;
		}

		$sql = "DELETE FROM ".$this->db->prefix()."powerplantpv_attestation_equipment";
		$sql .= " WHERE fk_attestation = ".((int) $this->id);
		$sql .= " AND entity = ".((int) $this->entity);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Assign final reference.
	 *
	 * @param	User	$user	User
	 * @return	int<-1,1>	>0 if OK
	 */
	protected function assignFinalReference(User $user)
	{
		$nextref = $this->getNextNumRef();
		if ($nextref === '') {
			return -1;
		}

		$sql = "UPDATE ".$this->db->prefix().$this->table_element;
		$sql .= " SET ref = '".$this->db->escape($nextref)."'";
		$sql .= ", fk_user_modif = ".((int) $user->id);
		$sql .= " WHERE rowid = ".((int) $this->id);
		$sql .= " AND entity = ".((int) $this->entity);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$this->ref = $nextref;

		return 1;
	}

	/**
	 * Set default PDF model from type.
	 *
	 * @return	void
	 */
	protected function setDefaultModelFromType()
	{
		if (!empty($this->type_code)) {
			$model = PowerPlantPVAttestationTypes::getModelForType($this->type_code);
			if ($model !== '') {
				$this->model_pdf = $model;
			}
		}
	}

	/**
	 * Call a canonical attestation trigger.
	 *
	 * @param	string	$action	Trigger suffix
	 * @param	User	$user	User
	 * @return	int			Trigger result
	 */
	protected function callAttestationTrigger($action, User $user)
	{
		$triggercode = $this->TRIGGER_PREFIX.'_'.strtoupper($action);
		$result = $this->call_trigger($triggercode, $user);
		if ($result < 0 && empty($this->error)) {
			$this->error = 'ErrorTriggerFailed';
		}

		return $result;
	}

	/**
	 * Check business requirements before validation or document generation.
	 *
	 * @return	int<-1,1>	>0 if OK
	 */
	protected function checkBusinessRequirements()
	{
		if (!PowerPlantPVAttestationTypes::isValidType($this->type_code)) {
			$this->error = 'AttestationInvalidType';
			return -1;
		}

		if ($this->type_code !== PowerPlantPVAttestationTypes::TYPE_INSTALLATEUR_INF_100KWC && empty($this->fk_powerplant)) {
			$this->error = 'AttestationPowerPlantRequired';
			return -1;
		}

		if (empty($this->address) || empty($this->zip) || empty($this->town) || empty($this->installer_name) || empty($this->writer_name)) {
			$this->error = 'AttestationSnapshotDataRequired';
			return -1;
		}

		return 1;
	}
}

/**
 * Attestation equipment snapshot line.
 */
class PowerPlantPVAttestationEquipmentLine
{
	public $id;
	public $rowid;
	public $entity;
	public $fk_attestation;
	public $fk_powerplant_line;
	public $fk_product;
	public $equipment_type;
	public $designation;
	public $brand;
	public $model;
	public $manufacturer;
	public $serial_number;
	public $bridage_enabled;
	public $bridage_type;
	public $max_power_kw;
	public $rank;
	public $error = '';

	/**
	 * Build line from object.
	 *
	 * @param	object	$obj	Database row
	 * @return	self			Line
	 */
	public static function fromObject($obj)
	{
		return self::fromArray(get_object_vars($obj));
	}

	/**
	 * Build line from array.
	 *
	 * @param	array<string,mixed>	$data	Data
	 * @return	self					Line
	 */
	public static function fromArray($data)
	{
		$line = new self();
		foreach ($data as $key => $value) {
			if (property_exists($line, $key)) {
				$line->$key = $value;
			}
		}
		if (!empty($line->rowid)) {
			$line->id = (int) $line->rowid;
		}

		return $line;
	}

	/**
	 * Insert line.
	 *
	 * @param	DoliDB	$db	Database handler
	 * @return	int<-1,max>	Row id or <0
	 */
	public function insert($db)
	{
		$sql = "INSERT INTO ".$db->prefix()."powerplantpv_attestation_equipment (";
		$sql .= "entity, fk_attestation, fk_powerplant_line, fk_product, equipment_type, designation, brand, model, manufacturer, serial_number, bridage_enabled, bridage_type, max_power_kw, rank";
		$sql .= ") VALUES (";
		$sql .= ((int) $this->entity);
		$sql .= ", ".((int) $this->fk_attestation);
		$sql .= ", ".($this->fk_powerplant_line > 0 ? ((int) $this->fk_powerplant_line) : 'NULL');
		$sql .= ", ".($this->fk_product > 0 ? ((int) $this->fk_product) : 'NULL');
		$sql .= ", '".$db->escape((string) $this->equipment_type)."'";
		$sql .= ", '".$db->escape((string) $this->designation)."'";
		$sql .= ", '".$db->escape((string) $this->brand)."'";
		$sql .= ", '".$db->escape((string) $this->model)."'";
		$sql .= ", '".$db->escape((string) $this->manufacturer)."'";
		$sql .= ", '".$db->escape((string) $this->serial_number)."'";
		$sql .= ", ".((int) $this->bridage_enabled);
		$sql .= ", '".$db->escape((string) $this->bridage_type)."'";
		$sql .= ", ".($this->max_power_kw !== null && $this->max_power_kw !== '' ? price2num($this->max_power_kw, 'MU') : 'NULL');
		$sql .= ", ".((int) $this->rank);
		$sql .= ")";

		if (!$db->query($sql)) {
			$this->error = $db->lasterror();
			return -1;
		}
		$this->id = (int) $db->last_insert_id($db->prefix()."powerplantpv_attestation_equipment");
		$this->rowid = $this->id;

		return $this->id;
	}
}
