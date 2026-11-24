<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Signatures drawn directly in the ERP for maintenance reports.
 * Images are private files of the report, re-encoded server side as PNG.
 */

/** @return list<string> Supported signature roles, in display order. */
function powerplantpvReportSignatureRoles(): array
{
	return array('technician', 'technical');
}

/**
 * Whether a signature image key is known: a report role, or one role of a self-inspection part.
 *
 * @param	string	$role	Report role, or part key such as part-AC2-technician
 * @return	bool
 */
function powerplantpvReportSignatureRoleIsValid(string $role): bool
{
	return in_array($role, powerplantpvReportSignatureRoles(), true)
		|| (bool) preg_match('/^part-(AC[1-6]-technician|FINAL-(technician|works_manager|business_manager))$/D', $role);
}

/** Whether the server can validate and re-encode drawn signatures. */
function powerplantpvReportSignatureAvailable(): bool
{
	return extension_loaded('gd') && function_exists('imagecreatefromstring') && function_exists('imagepng');
}

/**
 * Resolve the private signature image of a report after the calling page has authorized the report.
 *
 * @param	int		$entity		Report entity
 * @param	int		$reportId	Report id
 * @param	string	$role		technician or technical
 * @param	bool	$create		Create the directory when missing
 * @return	string				Absolute path, or '' when unavailable
 */
function powerplantpvReportSignaturePath(int $entity, int $reportId, string $role, bool $create = false): string
{
	global $conf;

	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	$root = $conf->powerplantpv->multidir_output[$entity] ?? '';
	if ($entity <= 0 || $reportId <= 0 || !is_string($root) || trim($root) === '' || !powerplantpvReportSignatureRoleIsValid($role)) {
		return '';
	}
	$root = realpath($root);
	if ($root === false || !is_dir($root)) {
		return '';
	}
	$dir = $root.'/report-signatures/'.$reportId;
	// Reject linked ancestors before creating a directory.
	foreach (array($root.'/report-signatures', $dir) as $ancestor) {
		if (is_link($ancestor)) {
			return '';
		}
	}
	if ($create && !is_dir($dir) && dol_mkdir($dir) < 0 && !is_dir($dir)) {
		return '';
	}
	$path = $dir.'/'.$role.'.png';

	return is_dir($dir) && !is_link($dir) && !is_link($path) ? $path : '';
}

/**
 * Validate a PNG data URL drawn in the browser and store its re-encoded pixels.
 *
 * @param	string	$dataUrl		Value posted by the signature pad
 * @param	string	$destination	Absolute destination path
 * @return	bool					True when stored
 */
function powerplantpvReportSignatureStore(string $dataUrl, string $destination): bool
{
	$prefix = 'data:image/png;base64,';
	if (!powerplantpvReportSignatureAvailable() || $destination === '' || strpos($dataUrl, $prefix) !== 0 || strlen($dataUrl) > 2000000) {
		return false;
	}
	$binary = base64_decode(substr($dataUrl, strlen($prefix)), true);
	if (!is_string($binary) || strncmp($binary, "\x89PNG\r\n\x1a\n", 8) !== 0) {
		return false;
	}
	$info = @getimagesizefromstring($binary);
	if (!is_array($info) || $info[2] !== IMAGETYPE_PNG || $info[0] < 50 || $info[1] < 20 || $info[0] * $info[1] > 4000000) {
		return false;
	}
	$image = @imagecreatefromstring($binary);
	if ($image === false || !powerplantpvReportSignatureHasInk($image)) {
		return false;
	}
	// Flatten on white: PDF engines embed opaque PNG without any temporary alpha processing.
	$flat = imagecreatetruecolor($info[0], $info[1]);
	if ($flat === false) {
		return false;
	}
	imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
	imagealphablending($flat, true);
	imagecopy($flat, $image, 0, 0, 0, 0, $info[0], $info[1]);
	$staged = tempnam(dirname($destination), 'signature-');
	if ($staged === false) {
		return false;
	}
	if (imagepng($flat, $staged, 9) && filesize($staged) > 0 && rename($staged, $destination)) {
		return true;
	}
	dol_delete_file($staged, 1, 1, 1, null, false, 0);

	return false;
}

/**
 * Reject an empty pad: at least a few visible dark pixels are required.
 *
 * @param	GdImage|resource	$image	Decoded image
 * @return	bool					True when the image contains strokes
 */
function powerplantpvReportSignatureHasInk($image): bool
{
	$width = imagesx($image);
	$height = imagesy($image);
	$step = max(1, (int) floor(min($width, $height) / 100));
	$ink = 0;
	for ($x = 0; $x < $width; $x += $step) {
		for ($y = 0; $y < $height; $y += $step) {
			$rgba = imagecolorsforindex($image, imagecolorat($image, $x, $y));
			if ($rgba['alpha'] < 100 && ($rgba['red'] + $rgba['green'] + $rgba['blue']) < 450 && ++$ink >= 20) {
				return true;
			}
		}
	}

	return false;
}
