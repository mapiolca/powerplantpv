<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';

/** Publish report evidence in the native intervention attachments and issue limited download links. */
class PowerPlantPVReportEvidence
{
	private const LINK_LIFETIME = 30 * 86400;

	/** @var DoliDB Database handler */
	private $db;

	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/**
	 * Resolve the original evidence, including files uploaded before the intervention was validated.
	 */
	public static function sourcePath($report, $file): string
	{
		global $conf;

		$entity = (int) ($report->entity ?? 0);
		$fieldId = (int) ($file->fk_report_field ?? 0);
		$filename = (string) ($file->filename ?? '');
		$relative = (string) ($file->filepath ?? '');
		if ($entity <= 0 || (int) ($file->entity ?? 0) !== $entity || (int) ($file->fk_report ?? 0) !== (int) $report->id
			|| $fieldId <= 0 || $filename === '' || dol_sanitizeFileName($filename) !== $filename
			|| !preg_match('~^report/[^/\\\\]+/field_'.preg_quote((string) $fieldId, '~').'/'.preg_quote($filename, '~').'$~D', $relative)
			|| in_array(explode('/', $relative)[1], array('.', '..'), true)
			|| empty($conf->powerplantpv->multidir_output[$entity])) {
			return '';
		}
		$root = realpath((string) $conf->powerplantpv->multidir_output[$entity]);
		if ($root === false) {
			return '';
		}
		$expected = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
		$path = realpath($expected);

		return $path === $expected && is_file($path) && is_readable($path) && !is_link($expected) ? $path : '';
	}

	/**
	 * Resolve the path in the native intervention documents directory.
	 */
	public static function attachmentPath(Fichinter $intervention, int $fileId, string $filename): string
	{
		global $conf;

		$entity = (int) $intervention->entity;
		if ($fileId <= 0 || $entity <= 0 || empty($intervention->ref) || !isset($conf->ficheinter->multidir_output[$entity])) {
			return '';
		}
		$root = realpath((string) $conf->ficheinter->multidir_output[$entity]);
		$dir = getMultidirOutput($intervention, 'ficheinter', 1);
		if ($root === false || !is_string($dir) || $dir === '' || str_starts_with($dir, 'error-') || is_link($dir)
			|| realpath(dirname($dir)) !== $root) {
			return '';
		}
		$extension = pathinfo($filename, PATHINFO_EXTENSION);
		$stem = dol_sanitizeFileName(dol_substr(pathinfo($filename, PATHINFO_FILENAME), 0, 48));
		$basename = 'PPV-'.$fileId.'-'.$stem.($extension !== '' ? '.'.dol_sanitizeFileName($extension) : '');

		return $root.DIRECTORY_SEPARATOR.basename($dir).DIRECTORY_SEPARATOR.$basename;
	}

	/**
	 * Make an evidence file available in the intervention's native attachments.
	 * @return string Content fingerprint, or empty on failure
	 */
	public function attach(Fichinter $intervention, $report, $file): string
	{
		if ((int) $report->entity !== (int) $intervention->entity || (int) $report->fk_fichinter !== (int) $intervention->id) {
			return '';
		}
		$source = self::sourcePath($report, $file);
		$destination = self::attachmentPath($intervention, (int) $file->id, (string) $file->filename);
		if ($source === '' || $destination === '' || is_link($destination) || dol_mkdir(dirname($destination)) < 0) {
			return '';
		}
		$hash = hash_file('sha256', $source);
		if (!is_string($hash)) {
			return '';
		}
		$dir = dirname($destination);
		$lockPath = dirname($dir).'/.powerplantpv-evidence.lock';
		$lock = is_link($lockPath) ? false : fopen($lockPath, 'c');
		if ($lock === false) {
			return '';
		}

		$temporary = false;
		try {
			if (!flock($lock, LOCK_EX) || is_link($destination) || realpath($dir) !== $dir) {
				return '';
			}
			if (is_file($destination)) {
				return hash_file('sha256', $destination) === $hash ? $hash : '';
			}
			$temporary = tempnam($dir, '.ppv-evidence-');
			if ($temporary === false || realpath(dirname($temporary)) !== $dir
				|| dol_copy($source, $temporary) <= 0 || hash_file('sha256', $temporary) !== $hash) {
				return '';
			}
			// A replacement for a deleted attachment must never reactivate an old link.
			$now = $this->db->idate(dol_now());
			$sql = 'UPDATE '.MAIN_DB_PREFIX."powerplantpv_report_evidence_link SET expires_at = '".$now."'";
			$sql .= ' WHERE entity = '.((int) $report->entity).' AND fk_report_file = '.((int) $file->id);
			$sql .= " AND expires_at > '".$now."'";
			if (!$this->db->query($sql)) {
				return '';
			}
			// Publish the complete file without overwriting an attachment created concurrently.
			if (!link($temporary, $destination)) {
				return '';
			}
			if (addFileIntoDatabaseIndex($dir, basename($destination), '', 'uploaded', 0, $intervention) < 0) {
				dol_delete_file($destination, 1, 0, 0, $intervention);
				return '';
			}

			return $hash;
		} finally {
			if ($temporary !== false) {
				dol_delete_file($temporary, 1, 1, 1, null, false, 0);
			}
			fclose($lock);
		}
	}

	/**
	 * Create a reusable, expiring URL. The token is returned once and only its hash is saved.
	 */
	public function issueDownload(Fichinter $intervention, User $user, int $fileId, string $fileHash): string
	{
		$url = dol_buildpath('/powerplantpv/public/report_evidence.php', 3);
		if ($fileId <= 0 || !preg_match('/^[0-9a-f]{64}$/D', $fileHash) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
			return '';
		}
		$token = bin2hex(random_bytes(32));
		$now = dol_now();
		$entity = (int) $intervention->entity;
		$table = MAIN_DB_PREFIX.'powerplantpv_report_evidence_link';
		$sql = "INSERT INTO ".$table." (entity, fk_report_file, token_hash, file_hash, date_creation, expires_at, fk_user_creat)";
		$sql .= " VALUES (".$entity.", ".$fileId.", '".$this->db->escape(hash('sha256', $token))."', '".$this->db->escape($fileHash)."'";
		$sql .= ", '".$this->db->idate($now)."', '".$this->db->idate($now + self::LINK_LIFETIME)."', ".((int) $user->id).")";
		if (!$this->db->query($sql)) {
			return '';
		}
		$this->db->query("DELETE FROM ".$table." WHERE entity = ".$entity." AND fk_report_file = ".$fileId
			." AND expires_at <= '".$this->db->idate($now)."'");

		// Keep the secret in the fragment so that opening the URL never sends it in a GET request.
		return $url.'?entity='.$entity.'#'.$token;
	}

	/**
	 * Resolve a public link only for the original attached file, in the original entity.
	 * @return array{stream:resource,name:string,size:int}|null
	 */
	public function resolveDownload(string $token, int $entity): ?array
	{
		if ($entity <= 0 || !preg_match('/^[0-9a-f]{64}$/D', $token)) {
			return null;
		}
		$hash = hash('sha256', $token);
		$sql = 'SELECT l.token_hash, l.file_hash, f.rowid AS file_id, f.filename, i.rowid AS intervention_id, i.ref AS intervention_ref';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'powerplantpv_report_evidence_link AS l';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'powerplantpv_report_file AS f ON f.rowid = l.fk_report_file AND f.entity = l.entity';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'powerplantpv_report AS r ON r.rowid = f.fk_report AND r.entity = l.entity';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'fichinter AS i ON i.rowid = r.fk_fichinter AND i.entity = l.entity';
		$sql .= ' WHERE l.entity = '.$entity." AND l.token_hash = '".$this->db->escape($hash)."'";
		$sql .= " AND l.expires_at > '".$this->db->idate(dol_now())."'";
		$result = $this->db->query($sql);
		if (!$result) {
			return null;
		}
		$link = $this->db->fetch_object($result);
		$this->db->free($result);
		if (!is_object($link) || !hash_equals((string) $link->token_hash, $hash)) {
			return null;
		}
		$intervention = new Fichinter($this->db);
		$intervention->id = (int) $link->intervention_id;
		$intervention->ref = (string) $link->intervention_ref;
		$intervention->entity = $entity;
		$path = self::attachmentPath($intervention, (int) $link->file_id, (string) $link->filename);
		if ($path === '' || realpath($path) !== $path || is_link($path) || !is_file($path) || !is_readable($path)) {
			return null;
		}
		$stream = fopen($path, 'rb');
		if ($stream === false) {
			return null;
		}
		$stat = fstat($stream);
		$digest = hash_init('sha256');
		$bytes = hash_update_stream($digest, $stream);
		if ($stat === false || $bytes !== $stat['size'] || !hash_equals((string) $link->file_hash, hash_final($digest)) || !rewind($stream)) {
			fclose($stream);
			return null;
		}

		return array('stream' => $stream, 'name' => (string) $link->filename, 'size' => $bytes);
	}
}
