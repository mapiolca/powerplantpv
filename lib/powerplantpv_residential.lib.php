<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once __DIR__.'/../class/powerplantpvresidentialreport.class.php';
require_once __DIR__.'/../class/powerplantpvcompatibility.class.php';

/** Render the residential rows inside the existing report form. */
function powerplantpvResidentialRender(string $section, array $context, bool $editable, Form $form): void
{
	global $langs;

	$data = $context['data'];
	$options = $context['options'];
	if ($section === 'RES_PROTECTION') {
		print '<div class="warning"><strong>'.$langs->trans('PVResidentialSafetyNotice').'</strong><br>'.$langs->trans('PVResidentialSafetyText').'</div>';
		return;
	}
	if (!$editable) {
		foreach (powerplantpvResidentialRows($section, $data, $langs) as list($label, $value)) {
			print '<div class="powerplantpv-report-field"><strong>'.dol_escape_htmltag($label).'</strong><div>'.dol_htmlentitiesbr($value).'</div></div>';
		}
		return;
	}
	if ($section === 'RES_SALES') {
		foreach (PowerPlantPVResidentialReport::PLACEMENTS as $type => $definition) {
			$row = $data['placements'][$type] ?? array();
			$key = 'residential[placements]['.$type.']';
			print '<div class="powerplantpv-report-field" data-residential-placement>';
			print '<label><input type="checkbox" name="'.$key.'[selected]" value="1"'.($row ? ' checked' : '').'> '.$langs->trans($definition[0]).'</label>';
			print '<div data-residential-placement-fields'.(!$row ? ' hidden' : '').'>';
			$choices = $options['placements'][$type] ?? array();
			if ($choices) {
				print '<label for="residentialplacements'.$type.'fk_component">'.$langs->trans('Ref').'</label> ';
				print $form->selectarray($key.'[fk_component]', $choices, $row['fk_component'] ?? '', 1, 0, 0, 'data-residential-select', 0, 0, 0, '', 'minwidth100', 0);
			}
			print '<label class="block" for="residential-location-'.$type.'">'.$langs->trans('PVResidentialLocation').'</label>';
			print '<input id="residential-location-'.$type.'" type="text" class="flat maxwidth500 powerplantpv-report-control" maxlength="255" name="'.$key.'[description]" value="'.dol_escape_htmltag((string) ($row['description'] ?? '')).'">';
			print '</div></div>';
		}
		powerplantpvResidentialRenderGroup('roofs', $data['roofs'], $options, $form);
	} elseif ($section === 'RES_STRINGS') {
		powerplantpvResidentialRenderGroup('strings', $data['strings'] ?: array(array()), $options, $form);
	}
}

/** Render a repeatable section and an inert template for additions. */
function powerplantpvResidentialRenderGroup(string $group, array $items, array $options, Form $form): void
{
	global $langs;
	$isRoof = $group === 'roofs';
	$products = $isRoof ? '' : json_encode($options['products'], JSON_INVALID_UTF8_SUBSTITUTE);
	print '<div class="powerplantpv-report-field" data-residential-group="'.$group.'" data-products="'.dol_escape_htmltag((string) $products).'"';
	print ' data-maximum="'.dol_escape_htmltag($langs->transnoentities('PVMaxMicroinvertersPerString')).'" data-no-limit="'.dol_escape_htmltag($langs->transnoentities('PVMicroLimitUnset')).'"';
	print ' data-exceeded="'.dol_escape_htmltag($langs->transnoentities('PVResidentialMicroExceeded')).'" data-no-product="'.dol_escape_htmltag($langs->transnoentities('PVResidentialNoMicroProduct')).'"';
	print ' data-serial="'.dol_escape_htmltag($langs->transnoentities('PVResidentialSerialNumber')).'" data-remove="'.dol_escape_htmltag($langs->transnoentities('Remove')).'">';
	if ($isRoof) {
		print '<strong>'.$langs->trans('PVResidentialLayout').'</strong>';
	}
	print '<div data-residential-items>';
	foreach (array_values($items) as $position => $row) {
		powerplantpvResidentialRenderItem($group, (string) $position, $row, $options, $form);
	}
	print '</div><template data-residential-template>';
	powerplantpvResidentialRenderItem($group, 'RESINDEX', array(), $options, $form);
	print '</template><button type="button" class="button" data-residential-add>'.img_picto('', 'fa-plus-circle').' '.$langs->trans($isRoof ? 'PVResidentialAddRoof' : 'PVResidentialAddString').'</button></div>';
}

/** Render one roof or string in the residential report. */
function powerplantpvResidentialRenderItem(string $group, string $index, array $row, array $options, Form $form): void
{
	global $langs;
	$name = 'residential['.$group.']['.$index.']';
	$id = 'residential-'.$group.'-'.$index;
	$isRoof = $group === 'roofs';
	print '<fieldset class="border marginbottomonly" data-residential-item><legend>'.$langs->trans($isRoof ? 'PVResidentialRoof' : 'PVResidentialString').' <span data-residential-number></span></legend>';
	if ($isRoof) {
		$orientations = array();
		foreach (PowerPlantPVResidentialReport::ORIENTATIONS as $code) {
			$orientations[$code] = $langs->transnoentities('PVResidentialOrientation'.$code);
		}
		print '<label class="block">'.$langs->trans('PVResidentialOrientation').' ';
		print $form->selectarray($name.'[orientation]', $orientations, $row['orientation'] ?? '', 1, 0, 0, 'data-residential-select', 0, 0, 0, '', 'minwidth100', 0).'</label>';
		$fields = array('module_count' => 'PVResidentialModuleCount', 'row_count' => 'PVResidentialRowCount', 'column_count' => 'PVResidentialColumnCount');
		foreach ($fields as $key => $label) {
			powerplantpvResidentialNumber($name, $key, $label, $row[$key] ?? '', 'min="1" step="1"');
		}
	} else {
		$isMicro = !empty($row['micro']);
		print '<label><input type="checkbox" name="'.$name.'[micro]" value="1" data-residential-micro'.($isMicro ? ' checked' : '').'> '.$langs->trans('PVResidentialMicroinverters').'</label>';
		print '<div data-residential-conventional'.($isMicro ? ' hidden' : '').'>';
		powerplantpvResidentialNumber($name, 'voltage', 'PVResidentialVoltage', $row['voltage'] ?? '', 'step="any"');
		powerplantpvResidentialNumber($name, 'module_count', 'PVResidentialModuleCount', $row['module_count'] ?? '', 'min="1" step="1"');
		print '</div><div data-residential-micro-fields'.(!$isMicro ? ' hidden' : '').'>';
		$plant = (int) ($row['fk_powerplant'] ?? 0);
		if (count($options['plants']) === 1) {
			$plant = (int) array_key_first($options['plants']);
			print '<input type="hidden" name="'.$name.'[fk_powerplant]" data-residential-plant value="'.$plant.'">';
		} else {
			print '<label class="block">'.$langs->trans('PowerPlant').' ';
			print $form->selectarray($name.'[fk_powerplant]', $options['plants'], $plant, 1, 0, 0, 'data-residential-plant data-residential-select', 0, 0, 0, '', 'minwidth100', 0).'</label>';
		}
		$references = array();
		foreach ($options['products'][$plant] ?? array() as $reference => $definition) {
			$references[$reference] = $definition['label'];
		}
		print '<label class="block">'.$langs->trans('Ref').' ';
		print $form->selectarray($name.'[fk_product]', $references, $row['fk_product'] ?? '', 1, 0, 0, 'data-residential-product data-residential-select', 0, 0, 0, '', 'minwidth100', 0).'</label>';
		powerplantpvResidentialNumber($name, 'quantity', 'Qty', $row['quantity'] ?? '', 'min="1" step="1" data-residential-quantity');
		print '<span class="opacitymedium" data-residential-limit aria-live="polite"></span>';
		if (!empty($options['chainProof'])) {
			$choices = array();
			foreach ($options['chainProof'] as $fileId => $file) {
				$choices[$fileId] = $file->filename;
			}
			print '<label class="block">'.$langs->trans('PVResidentialMicroProofChain').' ';
			print $form->selectarray($name.'[fk_micro_proof]', $choices, $row['fk_micro_proof'] ?? '', 1, 0, 0, 'data-residential-select', 0, 0, 0, '', 'minwidth100', 0).'</label>';
		}
		print '<div data-residential-serials data-name="'.$name.'[serials][]" id="'.$id.'-serials">';
		foreach ($row['serials'] ?? array() as $position => $serial) {
			print '<div class="marginbottomonly" data-residential-serial><label>'.$langs->trans('PVResidentialSerialNumber').' <span data-residential-serial-number>'.($position + 1).'</span> ';
			print '<input type="text" maxlength="128" class="flat maxwidth150" name="'.$name.'[serials][]" value="'.dol_escape_htmltag($serial).'"></label> ';
			print '<button type="button" class="button" data-residential-remove-serial>'.$langs->trans('Remove').'</button></div>';
		}
		print '</div></div>';
	}
	print '<button type="button" class="button" data-residential-remove>'.$langs->trans('Remove').'</button></fieldset>';
}

/** Render an integer or decimal input using the report form conventions. */
function powerplantpvResidentialNumber(string $name, string $key, string $label, $value, string $attributes): void
{
	global $langs;
	print '<label class="block">'.$langs->trans($label).' <input type="number" class="flat maxwidth100 powerplantpv-report-control" name="'.$name.'['.$key.']" value="'.dol_escape_htmltag((string) $value).'" '.$attributes.'></label>';
}

/** @param array<string,array> $data Residential rows
 * @return list<array{string,string}> Localized labels and values for the report and PDF
 */
function powerplantpvResidentialRows(string $section, array $data, Translate $langs): array
{
	$rows = array();
	if ($section === 'RES_SALES') {
		foreach ($data['placements'] as $key => $row) {
			if (isset(PowerPlantPVResidentialReport::PLACEMENTS[$key])) {
				$value = implode(' · ', array_filter(array($row['reference_label'], $row['description']), static fn ($part) => $part !== ''));
				$rows[] = array($langs->transnoentities(PowerPlantPVResidentialReport::PLACEMENTS[$key][0]), $value);
			}
		}
		foreach ($data['roofs'] as $index => $row) {
			$value = empty($row['orientation']) ? '' : $langs->transnoentities('PVResidentialOrientation'.$row['orientation']);
			foreach (array('module_count' => 'PVResidentialModuleCount', 'row_count' => 'PVResidentialRowCount', 'column_count' => 'PVResidentialColumnCount') as $key => $label) {
				$value .= "\n".$langs->transnoentities($label).' : '.($row[$key] ?? '');
			}
			$rows[] = array($langs->transnoentities('PVResidentialRoof').' '.($index + 1), trim($value));
		}
	} elseif ($section === 'RES_STRINGS') {
		foreach ($data['strings'] as $index => $chain) {
			if (!empty($chain['micro'])) {
				$value = $chain['reference_label'].' · '.$langs->transnoentities('Qty').' : '.$chain['quantity'];
				$value .= "\n".(!empty($chain['maximum']) ? $langs->transnoentities('PVMaxMicroinvertersPerString').' : '.$chain['maximum'] : $langs->transnoentities('PVMicroLimitUnset'));
				$serials = array_filter($chain['serials'], static fn (string $serial): bool => $serial !== '');
				if ($serials) {
					$value .= "\n".$langs->transnoentities('PVResidentialSerialNumber').' : '.implode(', ', $serials);
				}
				$proof = $data['chainProof'][$chain['fk_micro_proof'] ?? 0] ?? null;
				if ($proof instanceof PowerPlantPVReportFile) {
					$value .= "\n".$langs->transnoentities('PVResidentialMicroProofChain').' : '.$proof->filename;
				}
			} else {
				$value = $langs->transnoentities('PVResidentialVoltage').' : '.($chain['voltage'] ?? '').' V · '.$langs->transnoentities('PVResidentialModuleCount').' : '.($chain['module_count'] ?? '');
			}
			$rows[] = array($langs->transnoentities('PVResidentialString').' '.($index + 1), $value);
		}
	}
	return $rows;
}

function powerplantpvIsMicroProofField(string $code): bool
{
	return in_array($code, array(PowerPlantPVResidentialReport::COMMON_PROOF, PowerPlantPVResidentialReport::CHAIN_PROOF), true);
}

function powerplantpvIsResidentialEvidenceField(string $code): bool
{
	return $code === 'RES_SALES_FILES' || powerplantpvIsMicroProofField($code);
}

/** @return array{commonProof:array<int,PowerPlantPVReportFile>,chainProof:array<int,PowerPlantPVReportFile>} Usable proofs for this report. */
function powerplantpvResidentialProofOptions(array $tree, Fichinter $intervention): array
{
	$proofs = array('commonProof' => array(), 'chainProof' => array());
	$report = $tree['report'] ?? null;
	if (!$report instanceof PowerPlantPVReport) {
		return $proofs;
	}
	foreach (PowerPlantPVResidentialReport::proofFields($tree) as $code => $field) {
		$key = $code === PowerPlantPVResidentialReport::COMMON_PROOF ? 'commonProof' : 'chainProof';
		foreach ($field->files ?? array() as $file) {
			if ($file instanceof PowerPlantPVReportFile && powerplantpvResidentialProofImage($report, $intervention, $file) !== '') {
				$proofs[$key][(int) $file->id] = $file;
			}
		}
	}
	return $proofs;
}

function powerplantpvResidentialProofImage(PowerPlantPVReport $report, Fichinter $intervention, PowerPlantPVReportFile $file): string
{
	$source = powerplantpvResidentialFilePath($report, $intervention, $file);
	if ($source === '' || in_array($file->filemime, array('image/png', 'image/jpeg'), true)) {
		return $source;
	}
	$preview = $source.'.preview.png';
	return $file->filemime === 'application/pdf' && !is_link($preview) && is_readable($preview)
		&& is_file($preview) && (@getimagesize($preview)[2] ?? 0) === IMAGETYPE_PNG ? $preview : '';
}

/**
 * Publish used proofs to the native attachment areas. Runs inside the finalization transaction.
 * @param list<array<string,mixed>> $chains Final residential strings
 * @param array<string,array> $options Verified report proofs
 * @param list<string> $created Files to roll back if finalization fails
 */
function powerplantpvResidentialAttachProofs(PowerPlantPVReport $report, Fichinter $intervention, User $user, array $chains, array $options, array &$created): bool
{
	global $db, $conf;

	$byPlant = array();
	foreach ($chains as $chain) {
		if (empty($chain['micro'])) {
			continue;
		}
		$files = $options['commonProof'];
		$selected = $options['chainProof'][$chain['fk_micro_proof'] ?? 0] ?? null;
		if ($selected instanceof PowerPlantPVReportFile) {
			$files[(int) $selected->id] = $selected;
		}
		if ($files) {
			$plantId = (int) $chain['fk_powerplant'];
			$byPlant[$plantId] = ($byPlant[$plantId] ?? array()) + $files;
		}
	}
	if (!$byPlant) {
		return true;
	}
	if (!$user->hasRight('ficheinter', 'creer') || !$user->hasRight('powerplantpv', 'powerplant', 'write')) {
		return false;
	}

	require_once __DIR__.'/../class/powerplant.class.php';
	require_once __DIR__.'/powerplantpv_powerplant.lib.php';
	require_once __DIR__.'/../class/powerplantpvreportevidence.class.php';
	foreach ($byPlant as $plantId => $files) {
		$plant = new PowerPlant($db);
		if ($plant->fetch($plantId, null, 1, 1) <= 0 || (int) $plant->entity !== (int) $report->entity
			|| empty($conf->powerplantpv->multidir_output[$plant->entity])) {
			return false;
		}
		$root = realpath((string) $conf->powerplantpv->multidir_output[$plant->entity]);
		$plantDir = powerplantGetDocumentUploadDir($plant);
		$parent = dirname($plantDir);
		if ($root === false || is_link($parent) || is_link($plantDir) || dol_mkdir($plantDir) < 0
			|| realpath($parent) !== $root.DIRECTORY_SEPARATOR.'powerplant'
			|| realpath($plantDir) !== realpath($parent).DIRECTORY_SEPARATOR.basename($plantDir)) {
			return false;
		}
		foreach ($files as $file) {
			$source = powerplantpvResidentialFilePath($report, $intervention, $file);
			$interventionFile = PowerPlantPVReportEvidence::attachmentPath($intervention, (int) $file->id, (string) $file->filename);
			if ($source === '' || $interventionFile === ''
				|| !powerplantpvResidentialPublishProof($source, $interventionFile, $intervention, $created)
				|| !powerplantpvResidentialPublishProof($source, $plantDir.'/'.basename($interventionFile), $plant, $created)) {
				return false;
			}
		}
	}
	return true;
}

/** Publish a complete native attachment without replacing files created concurrently. */
function powerplantpvResidentialPublishProof(string $source, string $destination, CommonObject $object, array &$created): bool
{
	$dir = dirname($destination);
	if (is_link($dir) || is_link($destination) || dol_mkdir($dir) < 0 || realpath($dir) === false) {
		return false;
	}
	$hash = hash_file('sha256', $source);
	if (!is_string($hash)) {
		return false;
	}
	if (is_file($destination)) {
		return hash_file('sha256', $destination) === $hash;
	}
	$temp = tempnam($dir, '.ppv-proof-');
	if ($temp === false) {
		return false;
	}
	try {
		if (realpath(dirname($temp)) !== realpath($dir) || dol_copy($source, $temp) <= 0
			|| hash_file('sha256', $temp) !== $hash || !link($temp, $destination)) {
			return false;
		}
		$created[] = $destination;
		return addFileIntoDatabaseIndex($dir, basename($destination), '', 'uploaded', 0, $object) >= 0;
	} finally {
		dol_delete_file($temp, 1, 1, 1, null, false, 0);
	}
}

/** @return array<string,string> Available scope evidence MIME types indexed by extension. */
function powerplantpvResidentialEvidenceTypes(bool $microProof = false): array
{
	$types = array(
		'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf',
		'txt' => 'text/plain', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'odt' => 'application/vnd.oasis.opendocument.text', 'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
	);
	if ($microProof) {
		$types = array_intersect_key($types, array_flip(array('jpg', 'jpeg', 'png', 'pdf')));
		if (!class_exists('Imagick') || !function_exists('dol_convert_file')) {
			unset($types['pdf']);
		}
	}
	return array_filter($types, array(PowerPlantPVCompatibility::class, 'isResidentialEvidenceAvailable'));
}

/** Upload a batch of private scope evidence, leaving no partial batch after an error.
 * @param array<string,mixed> $upload PHP multi-file upload entry
 */
function powerplantpvResidentialUpload(PowerPlantPVReport $report, Fichinter $intervention, PowerPlantPVReportField $field, array $upload, User $user): int
{
	global $db;

	$directory = powerplantpvReportOutputDir($report, $intervention);
	if ($directory === '' || !is_array($upload['name'] ?? null) || !$upload['name'] || !PowerPlantPVCompatibility::isResidentialEvidenceAvailable()) {
		return -1;
	}
	$base = getMultidirOutput($report, 'powerplantpv', 0);
	$relative = 'report/'.dol_sanitizeFileName((string) $intervention->ref).'/field_'.((int) $field->id);
	$target = $directory.'/field_'.((int) $field->id);
	$root = realpath((string) $base);
	if ($root === false || !is_string($base) || $base === '' || strpos($base, 'error-') === 0 || !is_dir($root)
		|| is_link(dirname($directory)) || is_link($directory) || is_link($target) || dol_mkdir($target) < 0) {
		return -1;
	}
	$realTarget = realpath($target);
	if ($realTarget === false || strpos($realTarget, $root.DIRECTORY_SEPARATOR) !== 0 || $db->begin() <= 0) {
		return -1;
	}
	$created = array();
	$success = false;
	try {
		if (!$report->lockUnsignedForEdit(true)) {
			return -1;
		}
		$mimeDetector = new finfo(FILEINFO_MIME_TYPE);
		$microProof = powerplantpvIsMicroProofField($field->field_code);
		$allowed = powerplantpvResidentialEvidenceTypes($microProof);
		foreach ($upload['name'] as $index => $original) {
			$source = $upload['tmp_name'][$index] ?? '';
			if (!is_string($source) || !is_uploaded_file($source) || ($upload['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
				return -1;
			}
			$size = filesize($source);
			$max = getDolGlobalInt('MAIN_UPLOAD_DOC');
			if (!$size || ($max > 0 && $size > $max * 1024)) {
				return -1;
			}
			$name = dol_sanitizeFileName(basename(str_replace('\\', '/', (string) $original)));
			$extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
			$mime = $mimeDetector->file($source);
			if (!$mime || ($allowed[$extension] ?? '') !== $mime || strlen($name) > 240) {
				return -1;
			}
			$storageName = bin2hex(random_bytes(16)).'.'.$extension;
			$path = $target.'/'.$storageName;
			$created[] = $path;
			$created[] = $path.'.noexe';
			if (strncmp($mime, 'image/', 6) === 0) {
				$temp = $target.'/incoming-'.bin2hex(random_bytes(16)).'.'.$extension;
				$created[] = $temp;
				$created[] = $temp.'.noexe';
				if (!powerplantpvResidentialCleanPhoto($source, $temp, $path, $mime)) {
					return -1;
				}
			} elseif (dol_move_uploaded_file($source, $path, 0) !== 1) {
				return -1;
			}
			if ($microProof && $mime === 'application/pdf') {
				$preview = $path.'.preview.png';
				$created[] = $preview;
				try {
					$converted = dol_convert_file($path, 'png', $preview, '0');
				} catch (Exception $exception) {
					dol_syslog('Microinverter proof PDF conversion failed', LOG_WARNING);
					return -1;
				}
				if ($converted !== 1 || (@getimagesize($preview)[2] ?? 0) !== IMAGETYPE_PNG) {
					return -1;
				}
			}
			$file = new PowerPlantPVReportFile($db);
			$file->entity = (int) $report->entity;
			$file->fk_report = (int) $report->id;
			$file->fk_report_field = (int) $field->id;
			$file->filename = $name;
			$file->filepath = $relative.'/'.$storageName;
			$file->filemime = $mime;
			$file->filesize = (int) filesize($path);
			$file->checksum = (string) hash_file('sha256', $path);
			$file->date_upload = dol_now();
			$file->fk_user_upload = (int) $user->id;
			$file->position = 0;
			if (!$file->filesize || $file->checksum === '' || $file->create($user, 0) <= 0) {
				return -1;
			}
		}
		$success = $db->commit() > 0;
		return $success ? 1 : -1;
	} finally {
		if (!$success) {
			$db->rollback();
			foreach ($created as $path) {
				if (is_file($path)) {
					dol_delete_file($path, 1);
				}
			}
		}
	}
}

/** Keep the rendered image and its orientation while discarding embedded metadata. */
function powerplantpvResidentialCleanPhoto(string $source, string $temp, string $destination, string $mime): bool
{
	$jpeg = $mime === 'image/jpeg';
	if (!function_exists($jpeg ? 'imagecreatefromjpeg' : 'imagecreatefrompng')
		|| ($jpeg && !function_exists('exif_read_data'))
		|| dol_move_uploaded_file($source, $temp, 0) !== 1) {
		return false;
	}
	try {
		$image = $jpeg ? @imagecreatefromjpeg($temp) : @imagecreatefrompng($temp);
		if (!$image) {
			return false;
		}
		if (!$jpeg) {
			imagesavealpha($image, true);
			return imagepng($image, $destination);
		}
		$exif = @exif_read_data($temp);
		$orientation = (int) ($exif['Orientation'] ?? 1);
		if (in_array($orientation, array(2, 5, 7), true)) {
			imageflip($image, IMG_FLIP_HORIZONTAL);
		} elseif ($orientation === 4) {
			imageflip($image, IMG_FLIP_VERTICAL);
		}
		$angle = array(3 => 180, 5 => 90, 6 => 270, 7 => 270, 8 => 90)[$orientation] ?? 0;
		if ($angle !== 0) {
			$image = imagerotate($image, $angle, 0);
		}
		return $image && imagejpeg($image, $destination, 95);
	} finally {
		dol_delete_file($temp, 1);
	}
}

/** Resolve private evidence to the exact field directory of the authorized intervention. */
function powerplantpvResidentialFilePath(PowerPlantPVReport $report, Fichinter $intervention, PowerPlantPVReportFile $file): string
{
	global $db;

	if ((int) $file->fk_report !== (int) $report->id || (int) $file->entity !== (int) $report->entity) {
		return '';
	}
	$field = new PowerPlantPVReportField($db);
	if ($field->fetchCommon((int) $file->fk_report_field, null, '', 1) <= 0 || (int) $field->fk_report !== (int) $report->id
		|| !powerplantpvIsResidentialEvidenceField($field->field_code)) {
		return '';
	}
	$entity = (int) $report->entity;
	$interventionId = (int) $intervention->id;
	if ($entity <= 0 || $interventionId <= 0 || $entity !== (int) $intervention->entity || (int) $report->fk_fichinter !== $interventionId) {
		return '';
	}
	// Dolibarr renames its own intervention directory on validation. Module evidence stays at its upload path.
	// Recalculation can also reattach metadata without renaming the original field directory.
	$allowedRefs = array(dol_sanitizeFileName((string) $intervention->ref), dol_sanitizeFileName('(PROV'.$interventionId.')'));
	if (!preg_match('~^report/([^/]+)/(field_[1-9][0-9]*)/([a-f0-9]{32}\.[a-z0-9]+)$~D', (string) $file->filepath, $parts)
		|| in_array($parts[1], array('.', '..'), true) || !in_array($parts[1], $allowedRefs, true)) {
		return '';
	}
	global $conf;
	$configured = $conf->powerplantpv->multidir_output[$entity] ?? null;
	if (!is_string($configured) || trim($configured) === '') {
		return '';
	}
	$root = getMultidirOutput($report, 'powerplantpv', 0);
	if (!is_string($root) || $root === '' || strpos($root, 'error-') === 0) {
		return '';
	}
	$directory = rtrim($root, '/\\').'/report/'.$parts[1];
	$parent = $parts[2];
	$path = $directory.'/'.$parent.'/'.$parts[3];
	$realDir = realpath($directory);
	$realPath = realpath($path);
	if ($realDir === false || $realPath === false || !is_readable($realPath) || !is_file($realPath)
		|| is_link(dirname($directory)) || is_link($directory) || is_link(dirname($path)) || is_link($path)
		|| dirname($realPath) !== $realDir.DIRECTORY_SEPARATOR.$parent) {
		return '';
	}
	return $realPath;
}
