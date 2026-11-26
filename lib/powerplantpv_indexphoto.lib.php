<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

function powerplantpvIndexPhotoAvailable(): bool
{
	return extension_loaded('gd') && function_exists('exif_read_data')
		&& (imagetypes() & (IMG_JPG | IMG_PNG)) === (IMG_JPG | IMG_PNG);
}

function powerplantpvIndexPhotoApplies(string $code): bool
{
	return in_array($code, array('PRODUCTION_INDEX', 'INJECTION_INDEX', 'INVERTER_PRODUCTION', 'INVERTER_INJECTION'), true);
}

/** Keep evidence tied to its recorded index across snapshot rebuilds. */
function powerplantpvReportIndexPhotoPath(PowerPlantPVReportField $field, bool $create = false): string
{
	if (!is_numeric($field->value_number) || !powerplantpvIndexPhotoApplies((string) $field->field_code)) {
		return '';
	}
	$identity = $field->stable_key.':'.sprintf('%.8F', (float) $field->value_number);

	return powerplantpvIndexPhotoPath((int) $field->entity, 'report', (int) $field->fk_report, $identity, $create);
}

function powerplantpvIndexPhotoRequired(int $entity): bool
{
	global $db, $conf;

	require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
	return $entity === (int) $conf->entity
		? getDolGlobalInt('POWERPLANTPV_REQUIRE_INDEX_PHOTO') > 0
		: (int) dolibarr_get_const($db, 'POWERPLANTPV_REQUIRE_INDEX_PHOTO', $entity) > 0;
}

/**
 * Resolve a private photo after the calling page has authorized the owning object.
 * The identity is a reading row ID or a stable report field key.
 */
function powerplantpvIndexPhotoPath(int $entity, string $scope, int $objectId, string $identity, bool $create = false): string
{
	global $conf;

	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	$root = $conf->powerplantpv->multidir_output[$entity] ?? '';
	if ($entity <= 0 || $objectId <= 0 || !is_string($root) || trim($root) === '' || $identity === ''
		|| !in_array($scope, array('plant', 'report', 'layout'), true)) {
		return '';
	}
	$root = realpath($root);
	if ($root === false || !is_dir($root)) {
		return '';
	}
	$dir = $root.'/index-photos/'.$scope.'/'.$objectId;
	// Reject linked ancestors before creating a directory.
	foreach (array($root.'/index-photos', $root.'/index-photos/'.$scope, $dir) as $ancestor) {
		if (is_link($ancestor)) {
			return '';
		}
	}
	if ($create && !is_dir($dir) && dol_mkdir($dir) < 0) {
		return '';
	}
	$path = $dir.'/'.hash('sha256', $identity).'.jpg';

	return is_dir($dir) && !is_link($path) ? $path : '';
}

/** @param array<string,mixed> $upload One PHP file-upload entry. */
function powerplantpvIndexPhotoStore(array $upload, string $destination): bool
{
	$tmp = $upload['tmp_name'] ?? '';
	if (!powerplantpvIndexPhotoAvailable() || $destination === '' || ($upload['error'] ?? null) !== UPLOAD_ERR_OK
		|| !is_string($tmp) || !is_uploaded_file($tmp) || !is_string($upload['name'] ?? null)) {
		return false;
	}
	require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
	$limits = getMaxFileSizeArray();
	$limit = (int) $limits['maxmin'] * 1024;
	$size = filesize($tmp);
	if (!$size || ($limit > 0 && $size > $limit)) {
		return false;
	}

	$staged = tempnam(dirname($destination), 'index-photo-');
	if ($staged === false) {
		return false;
	}
	if (powerplantpvIndexPhotoEncode($tmp, strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION)), $staged, dol_now())
		&& filesize($staged) > 0 && rename($staged, $destination)) {
		return true;
	}
	dol_delete_file($staged, 1, 1, 1, null, false, 0);

	return false;
}

/** Re-encode the pixels and retain only the validated capture date or the upload time. */
function powerplantpvIndexPhotoEncode(string $source, string $extension, string $destination, ?int $uploadedAt = null): bool
{
	$info = @getimagesize($source);
	if (!is_array($info) || $info[0] * $info[1] > 16000000) {
		return false;
	}
	$orientation = 1;
	$captureDate = null;
	if ($info[2] === IMAGETYPE_JPEG && in_array($extension, array('jpg', 'jpeg'), true)) {
		$image = @imagecreatefromjpeg($source);
		$exif = @exif_read_data($source);
		$orientation = (int) ($exif['Orientation'] ?? 1);
		foreach (array('DateTimeOriginal', 'DateTimeDigitized') as $key) {
			$date = $exif[$key] ?? null;
			if (!is_string($date) || !preg_match('/^([0-9]{4}):([0-9]{2}):([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2})$/D', $date, $parts)) {
				continue;
			}
			if (checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
				&& (int) $parts[4] < 24 && (int) $parts[5] < 60 && (int) $parts[6] < 60) {
				// Preserve the camera's wall-clock time when its time zone is unknown.
				$captureDate = gmmktime((int) $parts[4], (int) $parts[5], (int) $parts[6], (int) $parts[2], (int) $parts[3], (int) $parts[1]);
				break;
			}
		}
	} elseif ($info[2] === IMAGETYPE_PNG && $extension === 'png') {
		$image = @imagecreatefrompng($source);
	} else {
		return false;
	}
	if ($image === false) {
		return false;
	}
	if (in_array($orientation, array(2, 4, 5, 7), true)) {
		imageflip($image, $orientation === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
	}
	$angle = array(3 => 180, 5 => 90, 6 => -90, 7 => -90, 8 => 90)[$orientation] ?? 0;
	if ($angle) {
		$image = imagerotate($image, $angle, 0);
		if ($image === false) {
			return false;
		}
	}

	if (!imagejpeg($image, $destination, 88)) {
		return false;
	}
	if ($captureDate === null && $uploadedAt === null) {
		return true;
	}

	// Store one server-generated marker with the pixels so replacements are atomic.
	$jpeg = file_get_contents($destination);
	if (!is_string($jpeg) || !str_starts_with($jpeg, "\xff\xd8")) {
		return false;
	}
	$stamp = 'PowerPlantPV:'.($captureDate !== null ? 'capture:' : 'upload:').($captureDate ?? $uploadedAt);
	$jpeg = "\xff\xd8\xff\xfe".pack('n', strlen($stamp) + 2).$stamp.substr($jpeg, 2);

	return file_put_contents($destination, $jpeg) === strlen($jpeg);
}

/** Format the timestamp generated by this module, without reading arbitrary image metadata. */
function powerplantpvIndexPhotoCaption(string $path, Translate $langs): string
{
	$header = @file_get_contents($path, false, null, 0, 64);
	if (is_string($header) && strlen($header) >= 6 && str_starts_with($header, "\xff\xd8\xff\xfe")) {
		$size = unpack('nlength', substr($header, 4, 2));
		$stamp = substr($header, 6, (int) ($size['length'] ?? 0) - 2);
		if (preg_match('/^PowerPlantPV:(capture|upload):(-?[0-9]{1,12})$/D', $stamp, $parts)) {
			$captured = $parts[1] === 'capture';
			return $langs->transnoentities(
				$captured ? 'PowerPlantPVIndexPhotoCapturedAt' : 'PowerPlantPVIndexPhotoUploadedAt',
				dol_print_date((int) $parts[2], 'dayhour', $captured ? 'gmt' : 'tzuser', $langs)
			);
		}
	}
	return $langs->transnoentities('PowerPlantPVIndexPhotoDateUnknown');
}

/** Send a validated photo only after authorizing its owning reading or report. */
function powerplantpvIndexPhotoDownload(string $path): void
{
	if ($path === '' || !is_file($path) || !is_readable($path)) {
		http_response_code(404);
		exit;
	}
	header('Content-Type: image/jpeg');
	header('Content-Disposition: inline; filename="index.jpg"');
	header('X-Content-Type-Options: nosniff');
	header('Cache-Control: private, no-store');
	readfile($path);
	exit;
}
