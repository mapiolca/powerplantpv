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

	$nbNote = (!empty($object->note_private) ? 1 : 0) + (!empty($object->note_public) ? 1 : 0);
	$head[$h][0] = dolBuildUrl(dol_buildpath('/powerplantpv/attestation_note.php', 1), array('id' => $object->id));
	$head[$h][1] = $langs->trans('Notes');
	if ($nbNote > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">'.$nbNote.'</span>';
	}
	$head[$h][2] = 'note';
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
 * Return native document modulepart used by FormFile for PDF model discovery.
 *
 * @return	string	Modulepart with object suffix
 */
function powerplantpvAttestationGetDocumentGenerationModulePart()
{
	return powerplantpvAttestationGetDocumentModulePart().':Attestation';
}

/**
 * Return the online signature source key used by the native Dolibarr public page.
 *
 * @return	string	Source key
 */
function powerplantpvAttestationGetOnlineSignatureSource()
{
	return 'powerplantpv_attestation';
}

/**
 * Return a native public online signature URL for an attestation.
 *
 * @param	int<0,1>					$mode				0=real URL, 1=template URL
 * @param	PowerPlantPVAttestation	$object				Attestation
 * @param	int<0,1>					$localorexternal	0=current root, 1=external root
 * @return	string										URL
 */
function powerplantpvAttestationGetOnlineSignatureUrl($mode, $object, $localorexternal = 1)
{
	if (!is_object($object) || empty($object->ref)) {
		return '';
	}

	require_once DOL_DOCUMENT_ROOT.'/core/lib/signature.lib.php';
	if (!function_exists('getOnlineSignatureUrl')) {
		return '';
	}

	return getOnlineSignatureUrl((int) $mode, powerplantpvAttestationGetOnlineSignatureSource(), (string) $object->ref, (int) $localorexternal, $object);
}

/**
 * Return the native online signature link block for an attestation.
 *
 * @param	PowerPlantPVAttestation	$object	Attestation
 * @param	string					$mode	Display mode
 * @return	string							HTML
 */
function powerplantpvAttestationShowOnlineSignatureUrl($object, $mode = '')
{
	if (!is_object($object) || empty($object->ref)) {
		return '';
	}

	require_once DOL_DOCUMENT_ROOT.'/core/lib/signature.lib.php';
	if (!function_exists('showOnlineSignatureUrl')) {
		return '';
	}

	return showOnlineSignatureUrl(powerplantpvAttestationGetOnlineSignatureSource(), (string) $object->ref, $object, $mode);
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

	$entity = (!empty($object->entity) ? (int) $object->entity : (int) $conf->entity);
	$diroutput = powerplantpvAttestationGetDocumentRootDir($entity);

	return $diroutput.'/'.powerplantpvAttestationGetDocumentRelativePath($object);
}

/**
 * Move legacy attestation documents generated one level too high into the native object folder.
 *
 * @param	PowerPlantPVAttestation	$object	Attestation
 * @return	int								Number of moved files, 0 when nothing changed, -1 on error
 */
function powerplantpvAttestationNormalizeDocumentDirectory($object)
{
	global $conf;

	if (empty($object->ref)) {
		return 0;
	}

	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

	$entity = (!empty($object->entity) ? (int) $object->entity : (int) $conf->entity);
	$sanitizedRef = dol_sanitizeFileName($object->ref);
	$rootDir = rtrim(powerplantpvAttestationGetDocumentRootDir($entity), '/\\');
	$targetDir = rtrim(powerplantpvAttestationGetDocumentUploadDir($object), '/\\');

	if (!is_dir($targetDir) && dol_mkdir($targetDir) < 0) {
		dol_syslog('PowerPlantPV attestation document normalization failed: cannot create '.$targetDir, LOG_ERR);
		return -1;
	}

	$moved = 0;
	$error = 0;
	$legacyDirs = array(
		$rootDir.'/'.$sanitizedRef,
		$rootDir.'/'.$sanitizedRef.'/attestation/'.$sanitizedRef,
		$rootDir.'/attestation',
	);
	$processedDirs = array();
	foreach ($legacyDirs as $legacyDir) {
		$legacyDir = rtrim($legacyDir, '/\\');
		$normalizedLegacyDir = str_replace('\\', '/', $legacyDir);
		$normalizedTargetDir = str_replace('\\', '/', $targetDir);
		if ($normalizedLegacyDir === $normalizedTargetDir || isset($processedDirs[$normalizedLegacyDir]) || !is_dir($legacyDir)) {
			continue;
		}
		$processedDirs[$normalizedLegacyDir] = true;

		$legacyFiles = dol_dir_list($legacyDir, 'files', 0, '^'.preg_quote($sanitizedRef, '/').'($|[._-])', '', 'name', SORT_ASC, 0);
		foreach ($legacyFiles as $file) {
			$source = $legacyDir.'/'.$file['name'];
			$target = $targetDir.'/'.$file['name'];
			if (file_exists($target)) {
				continue;
			}
			$result = dol_move($source, $target, '0', 0, 0, 0, array(), $entity);
			if ($result > 0) {
				$moved++;
				dol_syslog('PowerPlantPV attestation document normalized from '.$source.' to '.$target, LOG_INFO);
			} else {
				$error++;
				dol_syslog('PowerPlantPV attestation document normalization failed moving '.$source.' to '.$target, LOG_WARNING);
			}
		}
	}

	return ($error ? -1 : $moved);
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

	powerplantpvAttestationNormalizeDocumentDirectory($object);
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
 * Return a MyCompany value for an entity.
 *
 * @param	string	$constant	MyCompany constant
 * @param	string	$fallback	Fallback value
 * @param	int		$entity		Entity id
 * @return	string				Value
 */
function powerplantpvAttestationGetMyCompanyValue($constant, $fallback = '', $entity = 0)
{
	global $conf, $db;

	$entity = ($entity > 0 ? (int) $entity : (int) $conf->entity);
	if ($entity === (int) $conf->entity) {
		$value = getDolGlobalString($constant, '');
		if ($value !== '') {
			return $value;
		}
	}

	$sql = "SELECT value";
	$sql .= " FROM ".$db->prefix()."const";
	$sql .= " WHERE name = '".$db->escape($constant)."'";
	$sql .= " AND entity = ".$entity;
	$sql .= " ORDER BY rowid DESC";
	$resql = $db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		$db->free($resql);
		if ($obj && (string) $obj->value !== '') {
			return (string) $obj->value;
		}
	}

	return (string) $fallback;
}

/**
 * Return MyCompany country id for an entity.
 *
 * @param	int	$entity	Entity id
 * @return	int			Country id
 */
function powerplantpvAttestationGetMyCompanyCountryId($entity = 0)
{
	global $conf, $mysoc;

	$entity = ($entity > 0 ? (int) $entity : (int) $conf->entity);
	if ($entity === (int) $conf->entity && is_object($mysoc) && !empty($mysoc->country_id)) {
		return (int) $mysoc->country_id;
	}

	foreach (array('MAIN_INFO_SOCIETE_COUNTRY_ID', 'MAIN_INFO_SOCIETE_COUNTRY') as $constant) {
		$value = powerplantpvAttestationGetMyCompanyValue($constant, '', $entity);
		if ($value !== '' && ctype_digit((string) $value)) {
			return (int) $value;
		}
	}

	return 0;
}

/**
 * Return a country label.
 *
 * @param	int				$countryId		Country id
 * @param	Translate|null	$outputlangs	Output language
 * @return	string							Country label
 */
function powerplantpvAttestationGetCountryLabel($countryId, $outputlangs = null)
{
	global $langs;

	if (empty($countryId)) {
		return '';
	}
	if (!is_object($outputlangs)) {
		$outputlangs = $langs;
	}
	require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
	$country = getCountry((int) $countryId, 'all', null, $outputlangs, 0);
	if (is_array($country)) {
		return !empty($country['label']) ? (string) $country['label'] : (!empty($country['code']) ? (string) $country['code'] : '');
	}

	return '';
}

/**
 * Return attestation values derived from native Dolibarr sources.
 *
 * @param	PowerPlantPVAttestation	$attestation	Attestation
 * @param	Translate|null			$outputlangs	Output language
 * @return	array<string,mixed>						Derived data
 */
function powerplantpvAttestationGetDerivedData($attestation, $outputlangs = null)
{
	global $conf, $db, $langs, $mysoc, $user;

	if (!is_object($outputlangs)) {
		$outputlangs = $langs;
	}

	$entity = !empty($attestation->entity) ? (int) $attestation->entity : (int) $conf->entity;
	$isCurrentEntity = ($entity === (int) $conf->entity);
	$data = array(
		'entity' => $entity,
		'place' => powerplantpvAttestationGetMyCompanyValue('MAIN_INFO_SOCIETE_TOWN', ($isCurrentEntity && is_object($mysoc) ? $mysoc->town : ''), $entity),
		'installer_name' => powerplantpvAttestationGetMyCompanyValue('MAIN_INFO_SOCIETE_NOM', ($isCurrentEntity && is_object($mysoc) ? $mysoc->name : ''), $entity),
		'installer_address' => powerplantpvAttestationGetMyCompanyValue('MAIN_INFO_SOCIETE_ADDRESS', ($isCurrentEntity && is_object($mysoc) ? $mysoc->address : ''), $entity),
		'installer_zip' => powerplantpvAttestationGetMyCompanyValue('MAIN_INFO_SOCIETE_ZIP', ($isCurrentEntity && is_object($mysoc) ? $mysoc->zip : ''), $entity),
		'installer_town' => powerplantpvAttestationGetMyCompanyValue('MAIN_INFO_SOCIETE_TOWN', ($isCurrentEntity && is_object($mysoc) ? $mysoc->town : ''), $entity),
		'installer_siret' => powerplantpvAttestationGetMyCompanyValue('MAIN_INFO_SIRET', ($isCurrentEntity && is_object($mysoc) ? $mysoc->idprof2 : ''), $entity),
		'installer_vat' => powerplantpvAttestationGetMyCompanyValue('MAIN_INFO_TVAINTRA', ($isCurrentEntity && is_object($mysoc) ? $mysoc->tva_intra : ''), $entity),
		'installer_country_id' => powerplantpvAttestationGetMyCompanyCountryId($entity),
		'installer_country_label' => '',
		'writer_id' => !empty($attestation->fk_user_creat) ? (int) $attestation->fk_user_creat : (is_object($user) ? (int) $user->id : 0),
		'writer_name' => '',
		'writer_function' => '',
		'project_name' => '',
		'site_address' => '',
		'site_zip' => '',
		'site_town' => '',
		'site_country_id' => 0,
		'site_country_label' => '',
		'site_full_address' => '',
	);

	$data['installer_country_label'] = powerplantpvAttestationGetCountryLabel((int) $data['installer_country_id'], $outputlangs);

	if (!empty($attestation->fk_powerplant)) {
		dol_include_once('/powerplantpv/class/powerplant.class.php');
		$powerplant = new PowerPlant($db);
		if ($powerplant->fetch((int) $attestation->fk_powerplant) > 0) {
			$data['project_name'] = powerplantpvAttestationResolveProjectName($powerplant);
			$data['site_address'] = (string) $powerplant->address;
			$data['site_zip'] = (string) $powerplant->zip;
			$data['site_town'] = (string) $powerplant->town;
			$data['site_country_id'] = !empty($powerplant->fk_country) ? (int) $powerplant->fk_country : 0;
			$data['site_country_label'] = powerplantpvAttestationGetCountryLabel((int) $data['site_country_id'], $outputlangs);
		}
	}
	$data['site_full_address'] = powerplantpvAttestationFormatDerivedAddress($data, 'site', 1);

	if (!empty($data['writer_id'])) {
		require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
		if (is_object($user) && (int) $user->id === (int) $data['writer_id']) {
			$writer = $user;
		} else {
			$writer = new User($db);
			if ($writer->fetch((int) $data['writer_id']) <= 0) {
				$writer = null;
			}
		}
		if (is_object($writer)) {
			$data['writer_name'] = method_exists($writer, 'getFullName') ? $writer->getFullName($outputlangs) : trim($writer->firstname.' '.$writer->lastname);
			if ($data['writer_name'] === '' && !empty($writer->login)) {
				$data['writer_name'] = (string) $writer->login;
			}
			$data['writer_function'] = !empty($writer->job) ? (string) $writer->job : '';
		}
	}

	return $data;
}

/**
 * Format an address from derived attestation data.
 *
 * @param	array<string,mixed>	$data			Derived data
 * @param	string				$prefix			site|installer
 * @param	int<0,1>			$includeCountry	Include country label
 * @return	string								Formatted address
 */
function powerplantpvAttestationFormatDerivedAddress($data, $prefix = 'site', $includeCountry = 0)
{
	$parts = array();
	foreach (array($prefix.'_address', $prefix.'_zip', $prefix.'_town') as $key) {
		if (!empty($data[$key])) {
			$parts[] = (string) $data[$key];
		}
	}
	if ($includeCountry && !empty($data[$prefix.'_country_label'])) {
		$parts[] = (string) $data[$prefix.'_country_label'];
	}

	return trim(implode(' ', $parts));
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
	global $db, $conf;

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
	$entity = !empty($powerplant->entity) ? (int) $powerplant->entity : (int) $conf->entity;

	$sql = "SELECT c.rowid as fk_powerplant_line, c.fk_product, c.serial_number as composition_serial_number, p.ref as product_ref, p.label as product_label, p.fk_product_type,";
	$sql .= " pe.categorie_photovoltaique, cpv.code as category_code, cpv.label as category_label,";
	$sql .= " inv.ac_max_power, inv.ac_nominal_power, inv.grid_frequency,";
	$sql .= " sn.rowid as fk_powerplant_serialnumber, sn.serial_number as imported_serial_number";
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
		$line->fk_powerplant_serialnumber = !empty($obj->fk_powerplant_serialnumber) ? (int) $obj->fk_powerplant_serialnumber : null;
		$line->fk_product = (int) $obj->fk_product;
		$line->fk_categorie = !empty($obj->categorie_photovoltaique) ? (int) $obj->categorie_photovoltaique : null;
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
 * Check if a database column exists.
 *
 * @param	string	$table	Full table name
 * @param	string	$column	Column name
 * @return	bool			True if column exists
 */
function powerplantpvAttestationDatabaseColumnExists($table, $column)
{
	global $db;

	static $cache = array();
	$cachekey = $table.'.'.$column;
	if (array_key_exists($cachekey, $cache)) {
		return $cache[$cachekey];
	}

	$sql = "SHOW COLUMNS FROM ".$db->sanitize($table)." LIKE '".$db->escape($column)."'";
	$resql = $db->query($sql);
	if (!$resql) {
		$cache[$cachekey] = false;
		return false;
	}
	$cache[$cachekey] = ($db->num_rows($resql) > 0);
	$db->free($resql);

	return $cache[$cachekey];
}

/**
 * Check if a database table exists.
 *
 * @param	string	$table	Full table name
 * @return	bool			True if table exists
 */
function powerplantpvAttestationDatabaseTableExists($table)
{
	global $db;

	static $cache = array();
	if (array_key_exists($table, $cache)) {
		return $cache[$table];
	}

	$sql = "SHOW TABLES LIKE '".$db->escape($table)."'";
	$resql = $db->query($sql);
	if (!$resql) {
		$cache[$table] = false;
		return false;
	}
	$cache[$table] = ($db->num_rows($resql) > 0);
	$db->free($resql);

	return $cache[$table];
}

/**
 * Resolve an attestation equipment line from native referenced data.
 *
 * @param	PowerPlantPVAttestationEquipmentLine	$line			Equipment line
 * @param	Translate|null						$outputlangs	Output language
 * @return	array<string,mixed>									Resolved data
 */
function powerplantpvAttestationResolveEquipmentLine($line, $outputlangs = null)
{
	global $db, $langs;

	if (!is_object($outputlangs)) {
		$outputlangs = $langs;
	}

	$legacyMaxPower = (isset($line->max_power_kw) && $line->max_power_kw !== '') ? $line->max_power_kw : null;
	$fallback = array(
		'category' => !empty($line->category_label) ? (string) $line->category_label : (!empty($line->category_code) ? (string) $line->category_code : ''),
		'category_code' => !empty($line->category_code) ? (string) $line->category_code : '',
		'fk_product' => !empty($line->fk_product) ? (int) $line->fk_product : 0,
		'product_ref' => !empty($line->model) ? (string) $line->model : '',
		'designation' => !empty($line->designation) ? (string) $line->designation : '',
		'serial_number' => !empty($line->serial_number) ? (string) $line->serial_number : '',
		'brand' => !empty($line->brand) ? (string) $line->brand : '',
		'manufacturer' => !empty($line->manufacturer) ? (string) $line->manufacturer : '',
		'max_power_kw' => $legacyMaxPower,
		'equipment_type' => !empty($line->equipment_type) ? (string) $line->equipment_type : '',
	);

	$fkProduct = !empty($line->fk_product) ? (int) $line->fk_product : 0;
	$fkPowerplantLine = !empty($line->fk_powerplant_line) ? (int) $line->fk_powerplant_line : 0;
	$fkSerial = !empty($line->fk_powerplant_serialnumber) ? (int) $line->fk_powerplant_serialnumber : 0;
	if ($fkProduct <= 0 && $fkPowerplantLine <= 0 && $fkSerial <= 0) {
		return $fallback;
	}

	$entity = !empty($line->entity) ? (int) $line->entity : 0;
	static $cache = array();
	$cachekey = $entity.'|'.$fkProduct.'|'.$fkPowerplantLine.'|'.$fkSerial.'|'.((int) (!empty($line->fk_categorie) ? $line->fk_categorie : 0));
	if (array_key_exists($cachekey, $cache)) {
		return $cache[$cachekey];
	}

	$productExtrafieldsTable = $db->prefix()."product_extrafields";
	$brandSelect = powerplantpvAttestationDatabaseColumnExists($productExtrafieldsTable, 'product_photovoltaic_brand') ? "pe.product_photovoltaic_brand" : "'' as product_photovoltaic_brand";
	$manufacturerSelect = powerplantpvAttestationDatabaseColumnExists($productExtrafieldsTable, 'product_photovoltaic_manufacturer') ? "pe.product_photovoltaic_manufacturer" : "'' as product_photovoltaic_manufacturer";

	$sql = "SELECT p.rowid as fk_product, p.ref as product_ref, p.label as product_label,";
	$sql .= " pe.categorie_photovoltaique, ".$brandSelect.", ".$manufacturerSelect.",";
	$sql .= " cpv.code as category_code, cpv.label as category_label,";
	$sql .= " c.serial_number as composition_serial_number,";
	$sql .= " sn.serial_number as imported_serial_number,";
	$sql .= " inv.ac_max_power, inv.ac_nominal_power";
	$sql .= " FROM ".$db->prefix()."product as p";
	$sql .= " LEFT JOIN ".$db->prefix()."powerplantpv_powerplantcomp as c ON c.rowid = ".$fkPowerplantLine;
	if ($entity > 0) {
		$sql .= " AND c.entity = ".$entity;
	}
	$sql .= " LEFT JOIN ".$db->prefix()."powerplantpv_serialnumber as sn ON sn.rowid = ".$fkSerial;
	if ($entity > 0) {
		$sql .= " AND sn.entity = ".$entity;
	}
	$sql .= " LEFT JOIN ".$db->prefix()."product_extrafields as pe ON pe.fk_object = p.rowid";
	$sql .= " LEFT JOIN ".$db->prefix()."c_powerplantpv_categorypv as cpv ON cpv.rowid = ".(!empty($line->fk_categorie) ? ((int) $line->fk_categorie) : "pe.categorie_photovoltaique");
	$sql .= " LEFT JOIN ".$db->prefix()."powerplantpv_product_inverter as inv ON inv.fk_product = p.rowid AND inv.entity IN (".getEntity('product').")";
	$sql .= " WHERE p.entity IN (".getEntity('product').")";
	if ($fkProduct > 0) {
		$sql .= " AND p.rowid = ".$fkProduct;
	} elseif ($fkSerial > 0) {
		$sql .= " AND p.rowid = sn.fk_product";
	} else {
		$sql .= " AND p.rowid = c.fk_product";
	}
	$sql .= " LIMIT 1";

	$resql = $db->query($sql);
	if (!$resql) {
		$cache[$cachekey] = $fallback;
		return $fallback;
	}

	$obj = $db->fetch_object($resql);
	$db->free($resql);
	if (!$obj) {
		$cache[$cachekey] = $fallback;
		return $fallback;
	}

	$resolved = array(
		'category' => !empty($obj->category_label) ? (string) $obj->category_label : (!empty($obj->category_code) ? (string) $obj->category_code : $fallback['category']),
		'category_code' => !empty($obj->category_code) ? (string) $obj->category_code : $fallback['category_code'],
		'fk_product' => !empty($obj->fk_product) ? (int) $obj->fk_product : $fallback['fk_product'],
		'product_ref' => (string) $obj->product_ref,
		'designation' => (string) $obj->product_label,
		'serial_number' => !empty($obj->imported_serial_number) ? (string) $obj->imported_serial_number : (!empty($obj->composition_serial_number) ? (string) $obj->composition_serial_number : $fallback['serial_number']),
		'brand' => !empty($obj->product_photovoltaic_brand) ? (string) $obj->product_photovoltaic_brand : $fallback['brand'],
		'manufacturer' => !empty($obj->product_photovoltaic_manufacturer) ? (string) $obj->product_photovoltaic_manufacturer : $fallback['manufacturer'],
		'max_power_kw' => ($obj->ac_max_power !== null && $obj->ac_max_power !== '') ? $obj->ac_max_power : (($obj->ac_nominal_power !== null && $obj->ac_nominal_power !== '') ? $obj->ac_nominal_power : $fallback['max_power_kw']),
		'equipment_type' => '',
	);
	$resolved['equipment_type'] = powerplantpvAttestationGuessEquipmentType((object) array(
		'category_code' => $resolved['category_code'],
		'category_label' => $resolved['category'],
		'product_label' => $resolved['designation'],
	));

	$cache[$cachekey] = $resolved;
	return $resolved;
}

/**
 * Return photovoltaic category label for an attestation equipment snapshot line.
 *
 * @param	PowerPlantPVAttestationEquipmentLine	$line			Equipment line
 * @param	Translate|null						$outputlangs	Output language
 * @return	string											Category label
 */
function powerplantpvAttestationEquipmentCategoryLabel($line, $outputlangs = null)
{
	$resolved = powerplantpvAttestationResolveEquipmentLine($line, $outputlangs);

	return !empty($resolved['category']) ? (string) $resolved['category'] : '';
}

/**
 * Fetch attestations linked to a power plant for the power plant document tab.
 *
 * @param	PowerPlant	$powerplant	Power plant object
 * @param	User		$user		Current user
 * @return	PowerPlantPVAttestation[]	Linked attestations
 */
function powerplantpvAttestationFetchForPowerPlantDocumentTab($powerplant, $user)
{
	global $db;

	if (!is_object($powerplant) || empty($powerplant->id)) {
		return array();
	}

	$table = $db->prefix().'powerplantpv_attestation';
	if (!powerplantpvAttestationDatabaseTableExists($table)) {
		return array();
	}

	$hasDateValid = powerplantpvAttestationDatabaseColumnExists($table, 'date_valid');
	$sql = "SELECT t.rowid, t.ref, t.entity, t.type_code, t.fk_project, t.status";
	$sql .= $hasDateValid ? ", t.date_valid" : ", NULL as date_valid";
	$sql .= " FROM ".$db->sanitize($table)." as t";
	$sql .= " WHERE t.fk_powerplant = ".((int) $powerplant->id);
	$sql .= " AND t.entity IN (".getEntity('attestation').")";
	if (!empty($user->socid)) {
		$sql .= " AND t.fk_soc = ".((int) $user->socid);
	}
	$sql .= " ORDER BY ";
	if ($hasDateValid) {
		$sql .= "t.date_valid DESC, ";
	}
	$sql .= "t.tms DESC, t.rowid DESC";

	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__METHOD__.' '.$db->lasterror(), LOG_ERR);
		return array();
	}

	$attestations = array();
	while ($obj = $db->fetch_object($resql)) {
		$attestation = new PowerPlantPVAttestation($db);
		$attestation->id = (int) $obj->rowid;
		$attestation->rowid = (int) $obj->rowid;
		$attestation->ref = (string) $obj->ref;
		$attestation->entity = (int) $obj->entity;
		$attestation->type_code = (string) $obj->type_code;
		$attestation->fk_project = !empty($obj->fk_project) ? (int) $obj->fk_project : 0;
		$attestation->status = (int) $obj->status;
		$attestation->date_valid = !empty($obj->date_valid) ? (string) $obj->date_valid : '';
		$attestations[] = $attestation;
	}
	$db->free($resql);

	return $attestations;
}

/**
 * Return entity labels indexed by entity id.
 *
 * @param	int[]	$entityIds	Entity ids
 * @return	array<int,string>	Labels
 */
function powerplantpvAttestationGetEntityLabels($entityIds)
{
	global $db;

	$entityIds = array_values(array_unique(array_filter(array_map('intval', $entityIds))));
	if (empty($entityIds)) {
		return array();
	}

	$table = $db->prefix().'entity';
	if (!powerplantpvAttestationDatabaseTableExists($table)) {
		$labels = array();
		foreach ($entityIds as $entityId) {
			$labels[$entityId] = (string) $entityId;
		}

		return $labels;
	}

	$labelfield = 'rowid';
	if (powerplantpvAttestationDatabaseColumnExists($table, 'label')) {
		$labelfield = 'label';
	} elseif (powerplantpvAttestationDatabaseColumnExists($table, 'name')) {
		$labelfield = 'name';
	}

	$sql = "SELECT rowid, ".$db->sanitize($labelfield)." as entity_label";
	$sql .= " FROM ".$db->sanitize($table);
	$sql .= " WHERE rowid IN (".implode(',', $entityIds).")";
	$resql = $db->query($sql);
	if (!$resql) {
		return array();
	}

	$labels = array();
	while ($obj = $db->fetch_object($resql)) {
		$labels[(int) $obj->rowid] = (string) $obj->entity_label;
	}
	$db->free($resql);
	foreach ($entityIds as $entityId) {
		if (!isset($labels[$entityId])) {
			$labels[$entityId] = (string) $entityId;
		}
	}

	return $labels;
}

/**
 * Render linked attestations table on the power plant document tab.
 *
 * @param	PowerPlant	$powerplant	Power plant object
 * @param	User		$user		Current user
 * @return	string					HTML
 */
function powerplantpvAttestationRenderPowerPlantDocumentTabTable($powerplant, $user)
{
	global $db, $langs;

	if (!isModEnabled('powerplantpv') || !getDolGlobalInt('POWERPLANTPV_ATTESTATION_ENABLE', 1)) {
		return '';
	}
	if (!powerplantpvAttestationUserHasRight($user, 'read')) {
		return '';
	}

	$attestations = powerplantpvAttestationFetchForPowerPlantDocumentTab($powerplant, $user);
	$typeLabels = PowerPlantPVAttestationTypes::getTypeLabels($langs);
	$projectCache = array();
	$entityIds = array();
	$showEnvironment = false;
	$powerplantEntity = !empty($powerplant->entity) ? (int) $powerplant->entity : 0;

	foreach ($attestations as $attestation) {
		$entityIds[] = (int) $attestation->entity;
		if ($powerplantEntity > 0 && (int) $attestation->entity !== $powerplantEntity) {
			$showEnvironment = true;
		}
	}
	$entityLabels = $showEnvironment ? powerplantpvAttestationGetEntityLabels($entityIds) : array();
	if (isModEnabled('project')) {
		require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
	}

	$colspan = $showEnvironment ? 6 : 5;
	$out = "\n".'<br>';
	$out .= load_fiche_titre($langs->trans('Attestations'), '', 'fa-file-signature');
	$out .= '<div class="div-table-responsive-no-min">';
	$out .= '<table class="noborder centpercent">';
	$out .= '<tr class="liste_titre">';
	$out .= '<td>'.$langs->trans('Ref').'</td>';
	$out .= '<td>'.$langs->trans('Label').'</td>';
	$out .= '<td>'.$langs->trans('Project').'</td>';
	$out .= '<td class="center">'.$langs->trans('AttestationValidationDate').'</td>';
	if ($showEnvironment) {
		$out .= '<td>'.$langs->trans('PowerPlantEnvironment').'</td>';
	}
	$out .= '<td class="center">'.$langs->trans('Status').'</td>';
	$out .= '</tr>';

	if (empty($attestations)) {
		$out .= '<tr class="oddeven"><td colspan="'.$colspan.'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	} else {
		foreach ($attestations as $attestation) {
			$projectHtml = '';
			if (!empty($attestation->fk_project) && isModEnabled('project')) {
				if (!array_key_exists((int) $attestation->fk_project, $projectCache)) {
					$project = new Project($db);
					$projectCache[(int) $attestation->fk_project] = ($project->fetch((int) $attestation->fk_project) > 0) ? $project : null;
				}
				if (is_object($projectCache[(int) $attestation->fk_project])) {
					$projectHtml = $projectCache[(int) $attestation->fk_project]->getNomUrl(1);
				}
			}

			$dateValid = '';
			if (!empty($attestation->date_valid)) {
				$dateValid = dol_print_date($db->jdate($attestation->date_valid), 'dayhour', 'tzuserrel');
			}

			$out .= '<tr class="oddeven">';
			$out .= '<td class="nowraponall">'.$attestation->getNomUrl(1).'</td>';
			$out .= '<td>'.dol_escape_htmltag(isset($typeLabels[$attestation->type_code]) ? $typeLabels[$attestation->type_code] : $attestation->type_code).'</td>';
			$out .= '<td>'.$projectHtml.'</td>';
			$out .= '<td class="center nowraponall">'.$dateValid.'</td>';
			if ($showEnvironment) {
				$out .= '<td>'.dol_escape_htmltag(isset($entityLabels[(int) $attestation->entity]) ? $entityLabels[(int) $attestation->entity] : (string) $attestation->entity).'</td>';
			}
			$out .= '<td class="center nowraponall">'.$attestation->getLibStatut(5).'</td>';
			$out .= '</tr>';
		}
	}

	$out .= '</table>';
	$out .= '</div>';

	return $out;
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
