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
 * \file		lib/powerplantpv_attestation.lib.php
 * \ingroup		powerplantpv
 * \brief		Helpers for PowerPlantPV attestations.
 */

dol_include_once('/powerplantpv/class/powerplantpvattestation.class.php');
dol_include_once('/powerplantpv/class/powerplantpvattestationtypes.class.php');

/**
 * Check an attestation permission with Dolibarr v20+ and legacy fallback.
 *
 * @param	User	$user	Current user
 * @param	string	$right	Permission subkey
 * @return	int<0,1>		1 if allowed, 0 otherwise
 */
function powerplantpvAttestationUserHasRight($user, $right)
{
	if (!is_object($user) || $right === '') {
		return 0;
	}
	if (method_exists($user, 'hasRight')) {
		return (int) $user->hasRight('powerplantpv', 'attestation', $right);
	}
	if (!empty($user->rights->powerplantpv->attestation->{$right})) {
		return 1;
	}

	return 0;
}

/**
 * Prepare attestation tabs.
 *
 * @param	PowerPlantPVAttestation	$object	Attestation
 * @return	array<array{string,string,string}>	Tabs
 */
function powerplantpvAttestationPrepareHead($object)
{
	global $conf, $langs;

	$langs->load('powerplantpv@powerplantpv');
	$h = 0;
	$head = array();

	$head[$h][0] = dolBuildUrl(dol_buildpath('/powerplantpv/attestation_card.php', 1), array('id' => $object->id));
	$head[$h][1] = $langs->trans('Attestation');
	$head[$h][2] = 'card';
	$h++;

	$nbFiles = powerplantpvAttestationCountAttachedFilesAndLinks($object);
	$head[$h][0] = dolBuildUrl(dol_buildpath('/powerplantpv/attestation_document.php', 1), array('id' => $object->id));
	$head[$h][1] = $langs->trans('Documents');
	if ($nbFiles > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">'.$nbFiles.'</span>';
	}
	$head[$h][2] = 'document';
	$h++;

	if (isModEnabled('agenda')) {
		$nbEvent = powerplantpvAttestationCountAgendaEvents($object);
		$head[$h][0] = dolBuildUrl(dol_buildpath('/powerplantpv/attestation_agenda.php', 1), array('id' => $object->id));
		$head[$h][1] = $langs->trans('EventsAgenda');
		if ($nbEvent > 0) {
			$head[$h][1] .= '<span class="badge marginleftonlyshort">'.$nbEvent.'</span>';
		}
		$head[$h][2] = 'agenda';
		$h++;
	}

	$nbNote = (!empty($object->note_private) ? 1 : 0) + (!empty($object->note_public) ? 1 : 0);
	$head[$h][0] = dolBuildUrl(dol_buildpath('/powerplantpv/attestation_card.php', 1), array('id' => $object->id, 'tab' => 'notes'));
	$head[$h][1] = $langs->trans('Notes');
	if ($nbNote > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">'.$nbNote.'</span>';
	}
	$head[$h][2] = 'notes';
	$h++;

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'attestation@powerplantpv');
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'attestation@powerplantpv', 'remove');

	return $head;
}

/**
 * Return native document modulepart.
 *
 * @return	string	Modulepart
 */
function powerplantpvAttestationGetDocumentModulePart()
{
	return 'powerplantpv';
}

/**
 * Return relative document path.
 *
 * @param	PowerPlantPVAttestation	$object	Attestation
 * @return	string							Relative path
 */
function powerplantpvAttestationGetDocumentRelativePath($object)
{
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

	return 'attestation/'.dol_sanitizeFileName($object->ref);
}

/**
 * Return document root directory.
 *
 * @param	int|null	$entity	Entity
 * @return	string			Root dir
 */
function powerplantpvAttestationGetDocumentRootDir($entity = null)
{
	global $conf;

	$entity = (!empty($entity) ? (int) $entity : (int) $conf->entity);
	if (!empty($conf->powerplantpv->multidir_output[$entity])) {
		return $conf->powerplantpv->multidir_output[$entity];
	}

	return $conf->powerplantpv->dir_output;
}

/**
 * Return document upload directory.
 *
 * @param	PowerPlantPVAttestation	$object	Attestation
 * @return	string							Upload dir
 */
function powerplantpvAttestationGetDocumentUploadDir($object)
{
	global $conf;

	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	if (function_exists('getMultidirOutput')) {
		$uploadDir = getMultidirOutput($object, 'powerplantpv', 1);
		if (!empty($uploadDir)) {
			return $uploadDir;
		}
	}

	$entity = (!empty($object->entity) ? (int) $object->entity : (int) $conf->entity);

	return powerplantpvAttestationGetDocumentRootDir($entity).'/'.powerplantpvAttestationGetDocumentRelativePath($object);
}

/**
 * Count attached files and external links.
 *
 * @param	PowerPlantPVAttestation	$object	Attestation
 * @return	int								Count
 */
function powerplantpvAttestationCountAttachedFilesAndLinks($object)
{
	global $db;

	if (empty($object->id) || empty($object->ref)) {
		return 0;
	}

	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';

	$uploadDir = powerplantpvAttestationGetDocumentUploadDir($object);
	$nbFiles = count(dol_dir_list($uploadDir, 'files', 0, '', '(\.meta|_preview.*\.png)$'));
	$nbLinks = Link::count($db, $object->element, $object->id);

	return $nbFiles + $nbLinks;
}

/**
 * Count agenda events.
 *
 * @param	PowerPlantPVAttestation	$object	Attestation
 * @return	int								Count
 */
function powerplantpvAttestationCountAgendaEvents($object)
{
	global $db;

	if (empty($object->id)) {
		return 0;
	}

	$sql = "SELECT COUNT(a.id) as nb";
	$sql .= " FROM ".$db->prefix()."actioncomm as a";
	$sql .= " WHERE a.fk_element = ".((int) $object->id);
	$sql .= " AND a.elementtype = 'attestation@powerplantpv'";

	$resql = $db->query($sql);
	if (!$resql) {
		return 0;
	}
	$obj = $db->fetch_object($resql);
	$db->free($resql);

	return ($obj ? (int) $obj->nb : 0);
}

/**
 * Return link back to list.
 *
 * @param	PowerPlantPVAttestation	$object	Attestation
 * @return	string							HTML link
 */
function powerplantpvAttestationGetBackToListLink($object)
{
	global $langs;

	return '<a href="'.dol_buildpath('/powerplantpv/attestation_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
}

/**
 * Build banner extra HTML.
 *
 * @param	PowerPlantPVAttestation	$object	Attestation
 * @return	string							HTML
 */
function powerplantpvAttestationBuildBannerMoreHtml($object)
{
	global $db, $langs;

	$html = '<div class="refidno">';
	$type = PowerPlantPVAttestationTypes::getType($object->type_code);
	if (!empty($type['label'])) {
		$html .= $langs->trans($type['label']);
	}
	if (!empty($object->fk_powerplant)) {
		dol_include_once('/powerplantpv/class/powerplant.class.php');
		$powerplant = new PowerPlant($db);
		if ($powerplant->fetch((int) $object->fk_powerplant) > 0) {
			$html .= '<br>'.$langs->trans('PowerPlant').' : '.$powerplant->getNomUrl(1);
		}
	}
	$html .= '</div>';

	return $html;
}

/**
 * Return a MyCompany value with a snapshot fallback.
 *
 * @param	string	$constant	MyCompany constant
 * @param	string	$fallback	Fallback value
 * @return	string				Value
 */
function powerplantpvAttestationGetMyCompanyValue($constant, $fallback = '')
{
	$value = getDolGlobalString($constant, '');
	if ($value !== '') {
		return $value;
	}

	return (string) $fallback;
}

/**
 * Apply defaults and copied power plant data to an attestation before creation.
 *
 * @param	PowerPlantPVAttestation	$attestation	Attestation
 * @param	int						$fkPowerPlant	Power plant id
 * @param	User					$user			Current user
 * @return	int										>0 if OK, <0 if KO
 */
function powerplantpvAttestationPrefillFromPowerPlant($attestation, $fkPowerPlant, $user)
{
	global $db, $conf, $mysoc;

	$attestation->place = powerplantpvAttestationGetMyCompanyValue('MAIN_INFO_SOCIETE_TOWN', (is_object($mysoc) ? $mysoc->town : ''));
	$attestation->installer_name = powerplantpvAttestationGetMyCompanyValue('MAIN_INFO_SOCIETE_NOM', (is_object($mysoc) ? $mysoc->name : ''));
	$attestation->installer_address = powerplantpvAttestationGetMyCompanyValue('MAIN_INFO_SOCIETE_ADDRESS', (is_object($mysoc) ? $mysoc->address : ''));
	$attestation->installer_zip = powerplantpvAttestationGetMyCompanyValue('MAIN_INFO_SOCIETE_ZIP', (is_object($mysoc) ? $mysoc->zip : ''));
	$attestation->installer_town = powerplantpvAttestationGetMyCompanyValue('MAIN_INFO_SOCIETE_TOWN', (is_object($mysoc) ? $mysoc->town : ''));
	$attestation->installer_siret = powerplantpvAttestationGetMyCompanyValue('MAIN_INFO_SIRET', (is_object($mysoc) ? $mysoc->idprof2 : ''));
	$attestation->installer_vat = powerplantpvAttestationGetMyCompanyValue('MAIN_INFO_TVAINTRA', (is_object($mysoc) ? $mysoc->tva_intra : ''));
	$attestation->installer_fk_pays = (is_object($mysoc) && !empty($mysoc->country_id) ? (int) $mysoc->country_id : null);
	$attestation->writer_name = trim($user->firstname.' '.$user->lastname);
	$attestation->writer_function = getDolGlobalString('POWERPLANTPV_ATTESTATION_WRITER_FUNCTION', !empty($user->job) ? $user->job : '');
	$attestation->max_frequency_hz = getDolGlobalString('POWERPLANTPV_ATTESTATION_DEFAULT_MAX_FREQUENCY_HZ', '51.5');
	$attestation->max_export_power_kw = getDolGlobalString('POWERPLANTPV_ATTESTATION_DEFAULT_BRIDAGE_POWER', '');
	$attestation->date_attestation = dol_now();

	if ($fkPowerPlant <= 0) {
		return 1;
	}

	dol_include_once('/powerplantpv/class/powerplant.class.php');
	$powerplant = new PowerPlant($db);
	$result = $powerplant->fetch($fkPowerPlant);
	if ($result <= 0) {
		$attestation->error = $powerplant->error;
		return -1;
	}

	$attestation->fk_powerplant = (int) $powerplant->id;
	$attestation->entity = !empty($powerplant->entity) ? (int) $powerplant->entity : (int) $conf->entity;
	$attestation->fk_soc = !empty($powerplant->fk_soc) ? (int) $powerplant->fk_soc : null;
	$attestation->socid = $attestation->fk_soc;
	$attestation->fk_project = !empty($powerplant->fk_project) ? (int) $powerplant->fk_project : null;
	$attestation->project_name = powerplantpvAttestationResolveProjectName($powerplant);
	$attestation->address = (string) $powerplant->address;
	$attestation->zip = (string) $powerplant->zip;
	$attestation->town = (string) $powerplant->town;
	$attestation->fk_pays = !empty($powerplant->fk_country) ? (int) $powerplant->fk_country : null;
	if ($attestation->max_export_power_kw === '' || $attestation->max_export_power_kw === null) {
		$attestation->max_export_power_kw = !empty($powerplant->connection_contract_power) ? $powerplant->connection_contract_power : $powerplant->installed_power;
	}
	$attestation->bta_contract_number = !empty($powerplant->connection_request_number) ? $powerplant->connection_request_number : $powerplant->buyback_contract_number;
	if (!empty($powerplant->commissioning_date)) {
		$attestation->date_completion = $powerplant->commissioning_date;
	}

	$attestation->lines = powerplantpvAttestationBuildEquipmentSnapshot($powerplant, $attestation->type_code);

	return 1;
}

/**
 * Resolve project display name from power plant.
 *
 * @param	PowerPlant	$powerplant	Power plant
 * @return	string					Name
 */
function powerplantpvAttestationResolveProjectName($powerplant)
{
	global $db;

	if (!empty($powerplant->fk_project) && isModEnabled('project')) {
		require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
		$project = new Project($db);
		if ($project->fetch((int) $powerplant->fk_project) > 0) {
			return trim($project->ref.' - '.$project->title);
		}
	}

	return trim($powerplant->ref.' - '.$powerplant->label);
}

/**
 * Build equipment snapshot from power plant composition and serial numbers.
 *
 * @param	PowerPlant	$powerplant	Power plant
 * @param	string		$typeCode	Type code
 * @return	PowerPlantPVAttestationEquipmentLine[]	Lines
 */
function powerplantpvAttestationBuildEquipmentSnapshot($powerplant, $typeCode)
{
	global $db, $conf;

	$definition = PowerPlantPVAttestationTypes::getType($typeCode);
	$allowed = !empty($definition['equipment_types']) ? (array) $definition['equipment_types'] : array();
	$defaultBridageType = !empty($definition['bridage_type']) ? (string) $definition['bridage_type'] : '';
	$entity = !empty($powerplant->entity) ? (int) $powerplant->entity : (int) $conf->entity;

	$sql = "SELECT c.rowid as fk_powerplant_line, c.fk_product, c.serial_number as composition_serial_number, p.ref as product_ref, p.label as product_label, p.fk_product_type,";
	$sql .= " pe.categorie_photovoltaique, cpv.code as category_code, cpv.label as category_label,";
	$sql .= " inv.ac_max_power, inv.ac_nominal_power, inv.grid_frequency,";
	$sql .= " sn.serial_number as imported_serial_number";
	$sql .= " FROM ".$db->prefix()."powerplantpv_powerplantcomp as c";
	$sql .= " INNER JOIN ".$db->prefix()."product as p ON p.rowid = c.fk_product";
	$sql .= " LEFT JOIN ".$db->prefix()."product_extrafields as pe ON pe.fk_object = p.rowid";
	$sql .= " LEFT JOIN ".$db->prefix()."c_powerplantpv_categorypv as cpv ON cpv.rowid = pe.categorie_photovoltaique";
	$sql .= " LEFT JOIN ".$db->prefix()."powerplantpv_product_inverter as inv ON inv.fk_product = p.rowid AND inv.entity IN (".getEntity('product').")";
	$sql .= " LEFT JOIN ".$db->prefix()."powerplantpv_serialnumber as sn ON sn.fk_powerplant_line = c.rowid AND sn.entity = c.entity";
	$sql .= " WHERE c.fk_powerplant = ".((int) $powerplant->id);
	$sql .= " AND c.entity = ".$entity;
	$sql .= " AND (c.fk_status IS NULL OR c.fk_status <> 6)";
	$sql .= " ORDER BY c.rowid ASC, sn.rowid ASC";

	$lines = array();
	$resql = $db->query($sql);
	if (!$resql) {
		return $lines;
	}

	$rank = 0;
	$seen = array();
	while ($obj = $db->fetch_object($resql)) {
		$equipmentType = powerplantpvAttestationGuessEquipmentType($obj);
		if (!empty($allowed) && !in_array($equipmentType, $allowed, true)) {
			continue;
		}

		$serial = !empty($obj->imported_serial_number) ? (string) $obj->imported_serial_number : (string) $obj->composition_serial_number;
		$key = ((int) $obj->fk_powerplant_line).'|'.$serial;
		if (!empty($seen[$key])) {
			continue;
		}
		$seen[$key] = 1;
		$rank++;

		$line = new PowerPlantPVAttestationEquipmentLine();
		$line->entity = $entity;
		$line->fk_powerplant_line = (int) $obj->fk_powerplant_line;
		$line->fk_product = (int) $obj->fk_product;
		$line->equipment_type = $equipmentType;
		$line->designation = trim($obj->product_ref.' - '.$obj->product_label);
		$line->brand = '';
		$line->model = (string) $obj->product_ref;
		$line->manufacturer = '';
		$line->serial_number = $serial;
		$line->bridage_enabled = ($defaultBridageType !== '' ? 1 : 0);
		$line->bridage_type = $defaultBridageType;
		$line->max_power_kw = !empty($obj->ac_max_power) ? $obj->ac_max_power : $obj->ac_nominal_power;
		$line->rank = $rank;
		$lines[] = $line;
	}
	$db->free($resql);

	return $lines;
}

/**
 * Guess equipment type from product category.
 *
 * @param	object	$obj	Composition row
 * @return	string			Equipment type
 */
function powerplantpvAttestationGuessEquipmentType($obj)
{
	$text = strtoupper((string) $obj->category_code.' '.(string) $obj->category_label.' '.(string) $obj->product_label);
	if (strpos($text, 'ONDULEUR') !== false || strpos($text, 'INVERTER') !== false) {
		return 'INVERTER';
	}
	if (strpos($text, 'CONNECT') !== false || strpos($text, 'CABLE') !== false) {
		return 'CONNECTOR';
	}
	if (strpos($text, 'COFFRET') !== false || strpos($text, 'BOITIER') !== false || strpos($text, 'BOÎTIER') !== false || strpos($text, 'BOX') !== false) {
		return 'BOX';
	}

	return 'MODULE';
}

/**
 * Trigger an agenda event through Dolibarr triggers.
 *
 * @param	PowerPlantPVAttestation	$object			Attestation
 * @param	User					$user			User
 * @param	string					$triggercode	Trigger code
 * @param	string					$label			Event label
 * @param	string					$message		Event message
 * @return	int										0 on success, <0 on error
 */
function powerplantpvAttestationTriggerAgendaEvent($object, $user, $triggercode, $label, $message)
{
	if (empty($object->id) || empty($triggercode)) {
		return 0;
	}

	if (!isset($object->context) || !is_array($object->context)) {
		$object->context = array();
	}
	$oldContext = $object->context;
	$object->context['actionmsg'] = $message;
	$object->context['actionmsg2'] = $label;
	$object->actionmsg = $message;
	$object->actionmsg2 = $label;
	$result = $object->call_trigger($triggercode, $user);
	$object->context = $oldContext;

	return ($result < 0 ? -1 : 0);
}

/**
 * Return configured company stamp relative file.
 *
 * @return	string	Relative file
 */
function powerplantpvAttestationGetCompanyStampRelativeFile()
{
	$file = getDolGlobalString('POWERPLANTPV_ATTESTATION_COMPANY_STAMP', 'setup/company_stamp.png');
	if ($file === '' || preg_match('#(^/|\\\\|\.\.)#', $file)) {
		return 'setup/company_stamp.png';
	}

	return $file;
}

/**
 * Return configured company stamp absolute file.
 *
 * @param	int|null	$entity	Entity
 * @return	string			Absolute path
 */
function powerplantpvAttestationGetCompanyStampFile($entity = null)
{
	return powerplantpvAttestationGetDocumentRootDir($entity).'/'.powerplantpvAttestationGetCompanyStampRelativeFile();
}

/**
 * Decode and store a PNG signature image.
 *
 * @param	PowerPlantPVAttestation	$object		Attestation
 * @param	string					$dataUrl	PNG data URL
 * @return	string								Relative file, empty on failure
 */
function powerplantpvAttestationStoreSignatureImage($object, $dataUrl)
{
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

	if (!preg_match('#^data:image/png;base64,#', $dataUrl)) {
		$object->error = 'AttestationSignatureMustBePng';
		return '';
	}

	$raw = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
	if ($raw === false || strlen($raw) < 32) {
		$object->error = 'AttestationInvalidSignatureData';
		return '';
	}

	$uploadDir = powerplantpvAttestationGetDocumentUploadDir($object);
	dol_mkdir($uploadDir);
	$relative = powerplantpvAttestationGetDocumentRelativePath($object).'/signature.png';
	$target = powerplantpvAttestationGetDocumentRootDir($object->entity).'/'.$relative;
	dol_mkdir(dirname($target));
	if (file_put_contents($target, $raw) === false) {
		$object->error = 'ErrorFailedToSaveFile';
		return '';
	}

	return $relative;
}
