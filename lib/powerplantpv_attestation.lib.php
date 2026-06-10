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
 * Return the online signature source key used by the attestation public page.
 *
 * @return	string	Source key
 */
function powerplantpvAttestationGetOnlineSignatureSource()
{
	return 'powerplantpv_attestation';
}

/**
 * Return the online signature security seed.
 *
 * @return	string	Security seed
 */
function powerplantpvAttestationGetOnlineSignatureSecuritySeed()
{
	global $dolibarr_main_instance_unique_id;

	$seed = getDolGlobalString('POWERPLANTPV_ATTESTATION_ONLINE_SIGNATURE_SECURITY_TOKEN');
	if ($seed !== '') {
		return $seed;
	}

	return substr(dol_hash('dolibarr'.(string) $dolibarr_main_instance_unique_id, 'sha256'), 0, 32);
}

/**
 * Check if the Dolibarr core online signature page can handle the attestation source.
 *
 * Core v20/v23 rejects unsupported custom sources before module hooks can load the object,
 * so the module endpoint is kept as a native-compatible fallback.
 *
 * @return	int<0,1>	1 if core public page explicitly supports attestations
 */
function powerplantpvAttestationCanUseCoreOnlineSignaturePage()
{
	static $canusecore = null;

	if ($canusecore !== null) {
		return $canusecore;
	}

	$canusecore = 0;
	$corefile = DOL_DOCUMENT_ROOT.'/public/onlinesign/newonlinesign.php';
	if (is_readable($corefile)) {
		$content = file_get_contents($corefile, false, null, 0, 65536);
		if (is_string($content) && strpos($content, powerplantpvAttestationGetOnlineSignatureSource()) !== false) {
			$canusecore = 1;
		}
	}

	return $canusecore;
}

/**
 * Return the public online signature endpoint.
 *
 * @return	string	URL path from Dolibarr root
 */
function powerplantpvAttestationGetOnlineSignatureEndpoint()
{
	if (powerplantpvAttestationCanUseCoreOnlineSignaturePage()) {
		return '/public/onlinesign/newonlinesign.php';
	}

	return '/custom/powerplantpv/attestation_signature.php';
}

/**
 * Return a public online signature URL for an attestation.
 *
 * @param	int<0,1>					$mode				0=real URL, 1=template URL
 * @param	PowerPlantPVAttestation	$object				Attestation
 * @param	int<0,1>					$localorexternal	0=current root, 1=external root
 * @return	string										URL
 */
function powerplantpvAttestationGetOnlineSignatureUrl($mode, $object, $localorexternal = 1)
{
	global $dolibarr_main_url_root;

	if (!is_object($object) || empty($object->ref)) {
		return '';
	}

	$urlwithouturlroot = preg_replace('/'.preg_quote(DOL_URL_ROOT, '/').'$/i', '', trim($dolibarr_main_url_root));
	$urlwithroot = $urlwithouturlroot.DOL_URL_ROOT;
	$urltouse = $localorexternal ? $urlwithroot : DOL_MAIN_URL_ROOT;
	$type = powerplantpvAttestationGetOnlineSignatureSource();
	$ref = (string) $object->ref;
	$entity = !empty($object->entity) ? (int) $object->entity : 1;
	$seed = powerplantpvAttestationGetOnlineSignatureSecuritySeed();

	$out = $urltouse.powerplantpvAttestationGetOnlineSignatureEndpoint().'?source='.$type.'&ref=';
	if ($mode == 1) {
		$out .= 'attestation_ref';
		$out .= '&securekey=hash(\''.$seed.'\' + \''.$type.'\' + attestation_ref)';
	} else {
		$out .= urlencode($ref);
		$out .= '&securekey='.urlencode(dol_hash($seed.$type.$ref.(isModEnabled('multicompany') ? $entity : ''), '0'));
	}
	if (isModEnabled('multicompany')) {
		$out .= '&entity='.$entity;
	}

	return $out;
}

/**
 * Return a native-like online signature link block for an attestation.
 *
 * @param	PowerPlantPVAttestation	$object	Attestation
 * @param	string					$mode	Display mode
 * @return	string							HTML
 */
function powerplantpvAttestationShowOnlineSignatureUrl($object, $mode = '')
{
	global $langs;

	$langs->loadLangs(array('payment', 'stripe', 'powerplantpv@powerplantpv'));

	$servicename = 'Online';
	$out = '';
	if ($mode !== 'short') {
		$out .= img_picto('', 'globe', 'class="pictofixedwidth"');
	}
	$out .= $langs->trans('ToOfferALinkForOnlineSignature', $servicename).'<br>';
	$url = powerplantpvAttestationGetOnlineSignatureUrl(0, $object, 1);
	if ($url === '') {
		$out .= $langs->trans('FeatureOnlineSignDisabled');
	} else {
		$out .= '<input type="text" id="onlinesignatureurl" class="quatrevingtpercentminusx" readonly="readonly" value="'.dol_escape_htmltag($url).'">';
		$out .= ' <a href="'.$url.'" target="_blank" rel="noopener noreferrer">'.img_picto('', 'globe', 'class="paddingleft"').'</a>';
		if (function_exists('ajax_autoselect')) {
			$out .= '<br>'.ajax_autoselect('onlinesignatureurl', '');
		}
	}

	return $out;
}

/**
 * Check the public online signature secure key.
 *
 * @param	PowerPlantPVAttestation	$object		Attestation
 * @param	string					$securekey	Submitted secure key
 * @return	int<0,1>							1 if valid
 */
function powerplantpvAttestationVerifyOnlineSignatureSecureKey($object, $securekey)
{
	if (!is_object($object) || empty($object->ref) || $securekey === '') {
		return 0;
	}

	$type = powerplantpvAttestationGetOnlineSignatureSource();
	$entity = !empty($object->entity) ? (int) $object->entity : 1;
	$payload = powerplantpvAttestationGetOnlineSignatureSecuritySeed().$type.(string) $object->ref.(isModEnabled('multicompany') ? $entity : '');

	if (function_exists('dol_verifyHash')) {
		return (int) dol_verifyHash($payload, $securekey, '0');
	}

	$expected = dol_hash($payload, '0');
	return (int) (function_exists('hash_equals') ? hash_equals($expected, $securekey) : ($expected === $securekey));
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
		$line->fk_categorie = !empty($obj->categorie_photovoltaique) ? (int) $obj->categorie_photovoltaique : null;
		$line->category_code = (string) $obj->category_code;
		$line->category_label = (string) $obj->category_label;
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
 * Return photovoltaic category label for an attestation equipment snapshot line.
 *
 * @param	PowerPlantPVAttestationEquipmentLine	$line			Equipment line
 * @param	Translate|null						$outputlangs	Output language
 * @return	string											Category label
 */
function powerplantpvAttestationEquipmentCategoryLabel($line, $outputlangs = null)
{
	global $db, $langs;

	if (!is_object($outputlangs)) {
		$outputlangs = $langs;
	}
	if (!empty($line->category_label)) {
		return (string) $line->category_label;
	}
	if (!empty($line->category_code)) {
		return (string) $line->category_code;
	}

	static $cache = array();
	$cachekey = ((int) $line->fk_categorie).'|'.((int) $line->fk_powerplant_line).'|'.((int) $line->fk_product);
	if (array_key_exists($cachekey, $cache)) {
		return $cache[$cachekey];
	}

	$sql = "SELECT cpv.code, cpv.label";
	$sql .= " FROM ".$db->prefix()."c_powerplantpv_categorypv as cpv";
	if (!empty($line->fk_categorie)) {
		$sql .= " WHERE cpv.rowid = ".((int) $line->fk_categorie);
	} else {
		$sql .= " INNER JOIN ".$db->prefix()."product_extrafields as pe ON pe.categorie_photovoltaique = cpv.rowid";
		if (!empty($line->fk_powerplant_line)) {
			$sql .= " INNER JOIN ".$db->prefix()."powerplantpv_powerplantcomp as c ON c.fk_product = pe.fk_object";
			$sql .= " WHERE c.rowid = ".((int) $line->fk_powerplant_line);
		} elseif (!empty($line->fk_product)) {
			$sql .= " WHERE pe.fk_object = ".((int) $line->fk_product);
		} else {
			$cache[$cachekey] = '';
			return '';
		}
	}
	$sql .= " LIMIT 1";

	$resql = $db->query($sql);
	if (!$resql) {
		$cache[$cachekey] = '';
		return '';
	}
	$obj = $db->fetch_object($resql);
	$db->free($resql);
	if (!$obj) {
		$cache[$cachekey] = '';
		return '';
	}

	$cache[$cachekey] = !empty($obj->label) ? (string) $obj->label : (string) $obj->code;

	return $cache[$cachekey];
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

	$dataUrl = trim((string) $dataUrl);
	if (strpos($dataUrl, 'image/png;base64,') === 0) {
		$dataUrl = 'data:'.$dataUrl;
	}
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
	$date = dol_print_date(dol_now(), '%Y%m%d%H%M%S');
	$relative = powerplantpvAttestationGetDocumentRelativePath($object).'/signatures/'.$date.'_signature.png';
	$target = powerplantpvAttestationGetDocumentRootDir($object->entity).'/'.$relative;
	dol_mkdir(dirname($target));
	if (file_put_contents($target, $raw) === false) {
		$object->error = 'ErrorFailedToSaveFile';
		return '';
	}
	if (function_exists('dolChmod')) {
		dolChmod($target);
	}

	return $relative;
}
