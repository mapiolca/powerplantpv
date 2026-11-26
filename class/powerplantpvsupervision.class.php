<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * Licensed under the GNU General Public License, version 3 or later.
 */

require_once __DIR__.'/powerplantpvatmoceclient.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';

/** Configuration, linkage and collection for the supervision feature. */
final class PowerPlantPVSupervision
{
	public const ATMOCE = 'atmoce';
	public const CADENCES = array(15, 30, 60);

	public string $error = '';
	public string $output = '';

	public function __construct(private DoliDB $db)
	{
	}

	/** @return stdClass|null */
	public function configuration(int $entity): ?stdClass
	{
		$sql = 'SELECT enabled, cadence, credentials, session_data, last_attempt, last_success, last_error'
			.' FROM '.MAIN_DB_PREFIX.'powerplantpv_monitor_config'
			.' WHERE entity = '.$entity." AND provider = '".self::ATMOCE."'";
		$result = $this->query($sql);
		$row = $this->db->fetch_object($result);
		$this->db->free($result);
		return $row instanceof stdClass ? $row : null;
	}

	public function saveSettings(int $entity, int $cadence, string $key, string $secret): void
	{
		if (!in_array($cadence, self::CADENCES, true) || (($key === '') !== ($secret === ''))) {
			throw new InvalidArgumentException('PowerPlantPVSupervisionInvalidSettings');
		}
		$this->withLock($entity, function () use ($entity, $cadence, $key, $secret): void {
			$current = $this->configuration($entity);
			$replace = $key !== '';
			$credentials = $replace ? $this->encrypt(array('key' => $key, 'secret' => $secret)) : ($current->credentials ?? '');
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'powerplantpv_monitor_config'
				.' (entity, provider, cadence, credentials) VALUES ('.$entity.", '".self::ATMOCE."', ".$cadence.', '.$this->quote($credentials).')'
				.' ON DUPLICATE KEY UPDATE cadence = VALUES(cadence)';
			if ($replace) {
				$sql .= ', credentials = VALUES(credentials), session_data = NULL, last_attempt = 0, last_success = 0, last_error = NULL';
			}
			$this->query($sql);
		});
	}

	public function setEnabled(int $entity, bool $enabled): void
	{
		$this->withLock($entity, function () use ($entity, $enabled): void {
			$config = $this->configuration($entity);
			if ($config === null || ($enabled && (empty($config->credentials) || !function_exists('curl_init')))) {
				throw new RuntimeException('PowerPlantPVSupervisionCredentialsRequired');
			}
			$this->updateState($entity, $config, 'enabled = '.((int) $enabled));
		});
	}

	/**
	 * @return array{sites: list<array{id: string, name: string, timezone: string}>, complete: bool}
	 */
	public function sites(int $entity, int $page, bool $requireEnabled = true): array
	{
		return $this->withLock($entity, function () use ($entity, $page, $requireEnabled) {
			$config = $this->configuration($entity);
			if ($config === null || ($requireEnabled && !(int) $config->enabled)) {
				throw new RuntimeException('PowerPlantPVSupervisionUnavailable');
			}
			try {
				return $this->client($entity, $config)->listSites($page);
			} catch (RuntimeException $e) {
				$this->recordApiFailure($entity, $config, $e->getMessage());
				throw $e;
			}
		});
	}

	public function testConnection(int $entity): void
	{
		$this->withLock($entity, function () use ($entity): void {
			$config = $this->configuration($entity);
			if ($config === null) {
				throw new RuntimeException('PowerPlantPVSupervisionCredentialsRequired');
			}
			try {
				$this->client($entity, $config)->listSites();
				$this->updateState($entity, $config, 'last_success = '.time().', last_error = NULL');
			} catch (RuntimeException $e) {
				$this->recordApiFailure($entity, $config, $e->getMessage());
				throw $e;
			}
		});
	}

	public function countLinks(int $entity): int
	{
		$result = $this->query('SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'powerplantpv_monitor_link AS l'
			.' INNER JOIN '.MAIN_DB_PREFIX.'powerplantpv_powerplant AS p ON p.rowid = l.fk_powerplant AND p.entity = l.entity'
			.' WHERE l.entity = '.$entity." AND l.provider = '".self::ATMOCE."'");
		$row = $this->db->fetch_object($result);
		$this->db->free($result);
		return (int) ($row->total ?? 0);
	}

	public static function estimatedCalls(int $siteCount, int $cadence, int $days = 31): int
	{
		return (int) ceil($siteCount / PowerPlantPVAtmoceClient::MAX_SITES_PER_REQUEST) * intdiv($days * 1440, $cadence);
	}

	/** @return stdClass|null */
	public function linkForPlant(int $entity, int $plantId): ?stdClass
	{
		$sql = 'SELECT l.rowid, l.site_id, l.site_name, l.site_timezone, l.provider, c.enabled, c.cadence, c.last_error'
			.' FROM '.MAIN_DB_PREFIX.'powerplantpv_monitor_link AS l'
			.' LEFT JOIN '.MAIN_DB_PREFIX.'powerplantpv_monitor_config AS c ON c.entity = l.entity AND c.provider = l.provider'
			.' WHERE l.entity = '.$entity.' AND l.fk_powerplant = '.$plantId;
		$result = $this->query($sql);
		$row = $this->db->fetch_object($result);
		$this->db->free($result);
		return $row instanceof stdClass ? $row : null;
	}

	public function link(int $entity, int $plantId, string $siteId, int $page): void
	{
		$this->withLock($entity, function () use ($entity, $plantId, $siteId, $page): void {
			$config = $this->configuration($entity);
			if ($config === null || !(int) $config->enabled) {
				throw new RuntimeException('PowerPlantPVSupervisionUnavailable');
			}
			try {
				$sites = $this->client($entity, $config)->listSites($page)['sites'];
			} catch (RuntimeException $e) {
				$this->recordApiFailure($entity, $config, $e->getMessage());
				throw $e;
			}
			foreach ($sites as $site) {
				if ($site['id'] !== $siteId) {
					continue;
				}
				// Do not use an upsert here: a conflict on the site key must never move another plant's link.
				$current = $this->linkForPlant($entity, $plantId);
				$values = "provider = '".self::ATMOCE."', site_id = ".$this->quote($siteId)
					.', site_name = '.$this->quote(mb_substr($site['name'], 0, 255))
					.', site_timezone = '.$this->quote(substr($site['timezone'], 0, 64));
				if ($current === null) {
					$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'powerplantpv_monitor_link (entity, fk_powerplant, provider, site_id, site_name, site_timezone)'
						." SELECT p.entity, p.rowid, '".self::ATMOCE."', ".$this->quote($siteId)
						.', '.$this->quote(mb_substr($site['name'], 0, 255)).', '.$this->quote(substr($site['timezone'], 0, 64))
						.' FROM '.MAIN_DB_PREFIX.'powerplantpv_powerplant AS p WHERE p.entity = '.$entity.' AND p.rowid = '.$plantId;
				} else {
					$sql = 'UPDATE '.MAIN_DB_PREFIX.'powerplantpv_monitor_link SET '.$values
						.' WHERE entity = '.$entity.' AND fk_powerplant = '.$plantId.' AND site_id = '.$this->quote($current->site_id);
				}
				$result = $this->query($sql);
				if (($current === null || $current->site_id !== $siteId) && $this->db->affected_rows($result) !== 1) {
					throw new RuntimeException('PowerPlantPVSupervisionConcurrentChange');
				}
				return;
			}
			throw new RuntimeException('PowerPlantPVSupervisionInvalidSite');
		});
	}

	/** @return array{site: string, devices: list<array{serial: string, model: string}>} */
	public function microinverterInventory(int $entity, int $plantId): array
	{
		return $this->withLock($entity, function () use ($entity, $plantId): array {
			$link = $this->linkForPlant($entity, $plantId);
			$config = $this->configuration($entity);
			if ($link === null || $link->provider !== self::ATMOCE || $config === null || !(int) $config->enabled) {
				throw new RuntimeException('PowerPlantPVSupervisionUnavailable');
			}
			try {
				return array('site' => $link->site_id, 'devices' => $this->client($entity, $config)->microinverters($link->site_id));
			} catch (RuntimeException $e) {
				$this->recordApiFailure($entity, $config, $e->getMessage());
				throw $e;
			}
		});
	}

	/** Remove dependent records inside the caller's plant deletion transaction. */
	public function deleteForPlant(int $entity, int $plantId): void
	{
		$sql = 'DELETE r FROM '.MAIN_DB_PREFIX.'powerplantpv_monitor_reading AS r'
			.' INNER JOIN '.MAIN_DB_PREFIX.'powerplantpv_monitor_link AS l ON l.entity = r.entity AND l.rowid = r.fk_link'
			.' WHERE r.entity = '.$entity.' AND l.fk_powerplant = '.$plantId;
		$this->query($sql);
		$this->query('DELETE FROM '.MAIN_DB_PREFIX.'powerplantpv_monitor_link WHERE entity = '.$entity.' AND fk_powerplant = '.$plantId);
	}

	/** @return list<stdClass> */
	public function readings(int $entity, stdClass $link, int $since, int $limit = 2881): array
	{
		$sql = 'SELECT reported_at, device_status, power_w, energy_today_kwh'
			.' FROM '.MAIN_DB_PREFIX.'powerplantpv_monitor_reading'
			.' WHERE entity = '.$entity.' AND fk_link = '.((int) $link->rowid).' AND site_id = '.$this->quote($link->site_id)
			.' AND reported_at >= '.$since.' ORDER BY reported_at DESC'.$this->db->plimit($limit);
		$result = $this->query($sql);
		$rows = array();
		while ($row = $this->db->fetch_object($result)) {
			$rows[] = $row;
		}
		$this->db->free($result);
		return $rows;
	}

	public static function displayState(?stdClass $reading, int $cadence, int $now): string
	{
		if ($reading === null) {
			return 'PowerPlantPVSupervisionAwaiting';
		}
		if ($now - (int) $reading->reported_at > 2 * $cadence * 60) {
			return 'PowerPlantPVSupervisionStale';
		}
		return array(
			0 => 'PowerPlantPVSupervisionOffline',
			1 => 'PowerPlantPVSupervisionNormal',
			2 => 'PowerPlantPVSupervisionFault',
		)[(int) $reading->device_status] ?? 'PowerPlantPVSupervisionAwaiting';
	}

	public function runScheduledCollection(): int
	{
		global $conf;

		if (!isModEnabled('powerplantpv') || (int) $conf->entity <= 0) {
			return 0;
		}
		$entity = (int) $conf->entity;
		try {
			$count = $this->withLock($entity, function () use ($entity) {
				$config = $this->configuration($entity);
				if ($config === null || !(int) $config->enabled || time() - (int) $config->last_attempt < (int) $config->cadence * 60) {
					return 0;
				}
				return $this->collect($entity, $config);
			}, true);
			$this->output = (string) ($count ?? 0);
			return 0;
		} catch (RuntimeException $e) {
			$this->error = $e->getMessage();
			dol_syslog(__METHOD__.' entity='.$entity.' '.$this->error, LOG_ERR);
			return 1;
		}
	}

	private function collect(int $entity, stdClass $config): int
	{
		$result = $this->query('SELECT l.rowid, l.site_id FROM '.MAIN_DB_PREFIX.'powerplantpv_monitor_link AS l'
			.' INNER JOIN '.MAIN_DB_PREFIX.'powerplantpv_powerplant AS p ON p.rowid = l.fk_powerplant AND p.entity = l.entity'
			.' WHERE l.entity = '.$entity." AND l.provider = '".self::ATMOCE."' ORDER BY l.rowid");
		$links = array();
		while ($row = $this->db->fetch_object($result)) {
			$links['site:'.$row->site_id] = $row;
		}
		$this->db->free($result);
		if (!$links) {
			return 0;
		}
		$this->updateState($entity, $config, 'last_attempt = '.time());
		$count = 0;
		$seen = array();
		try {
			$client = $this->client($entity, $config);
			foreach (array_chunk(array_values($links), PowerPlantPVAtmoceClient::MAX_SITES_PER_REQUEST) as $batch) {
				$siteIds = array_map(static fn ($row): string => $row->site_id, $batch);
				$readings = $client->latest($siteIds);
				foreach ($readings as $reading) {
					$link = $links['site:'.$reading['site_id']];
					// SELECT guards against observations being written to a link changed during the HTTP call.
					$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'powerplantpv_monitor_reading'
						.' (entity, fk_link, site_id, reported_at, device_status, power_w, energy_today_kwh)'
						.' SELECT l.entity, l.rowid, l.site_id, '.$reading['reported_at'].', '.$reading['status'].', '
						.($reading['power_w'] ?? 'NULL').', '.($reading['energy_today_kwh'] ?? 'NULL')
						.' FROM '.MAIN_DB_PREFIX.'powerplantpv_monitor_link AS l'
						.' WHERE l.entity = '.$entity.' AND l.rowid = '.((int) $link->rowid).' AND l.site_id = '.$this->quote($link->site_id)
						.' ON DUPLICATE KEY UPDATE device_status = VALUES(device_status), power_w = VALUES(power_w), energy_today_kwh = VALUES(energy_today_kwh)';
					$this->query($sql);
					$count++;
					$seen['site:'.$reading['site_id']] = true;
				}
			}
			if ($count === 0) {
				throw new RuntimeException('PowerPlantPVSupervisionNoMeasurements');
			}
			if (count($seen) !== count($links)) {
				throw new RuntimeException('PowerPlantPVSupervisionPartialMeasurements');
			}
			$this->updateState($entity, $config, 'last_success = '.time().', last_error = NULL');
		} catch (RuntimeException $e) {
			$this->recordApiFailure($entity, $config, $e->getMessage());
			throw $e;
		}
		return $count;
	}

	private function recordApiFailure(int $entity, stdClass $config, string $error): void
	{
		$change = 'last_error = '.$this->quote($error);
		if ($error === 'AtmoceAuthenticationFailed') {
			$change .= ', session_data = NULL';
		}
		$this->updateState($entity, $config, $change);
	}

	private function client(int $entity, stdClass $config): PowerPlantPVAtmoceClient
	{
		$credentials = $this->decrypt((string) $config->credentials);
		if (!isset($credentials['key'], $credentials['secret']) || !is_string($credentials['key']) || !is_string($credentials['secret'])) {
			throw new RuntimeException('PowerPlantPVSupervisionCredentialsRequired');
		}
		$client = new PowerPlantPVAtmoceClient($credentials['key'], $credentials['secret']);
		$session = $this->decrypt((string) $config->session_data);
		if (isset($session['token'], $session['expires_at']) && is_string($session['token']) && is_int($session['expires_at'])) {
			$client->reuseSession($session['token'], $session['expires_at']);
		}
		$current = $client->getSession();
		if ($current !== $session) {
			$this->updateState($entity, $config, 'session_data = '.$this->quote($this->encrypt($current)));
		}
		return $client;
	}

	private function encryptionKey(): string
	{
		global $conf;

		$key = $conf->file->dolcrypt_key ?? '';
		return $key !== '' ? $key : ($conf->file->instance_unique_id ?? '');
	}

	/** @param array<string, int|string> $values */
	private function encrypt(array $values): string
	{
		$key = $this->encryptionKey();
		$encrypted = $key === '' ? '' : dolEncrypt(json_encode($values, JSON_THROW_ON_ERROR), $key);
		if (!is_string($encrypted) || strpos($encrypted, 'dolcrypt:') !== 0) {
			throw new RuntimeException('PowerPlantPVSupervisionCryptoUnavailable');
		}
		return base64_encode($encrypted);
	}

	/** @return array<string, mixed> */
	private function decrypt(string $value): array
	{
		$key = $this->encryptionKey();
		$encrypted = base64_decode($value, true);
		if ($key === '' || $encrypted === false || strpos($encrypted, 'dolcrypt:') !== 0) {
			return array();
		}
		$clear = dolDecrypt($encrypted, $key);
		$data = is_string($clear) ? json_decode($clear, true) : null;
		return is_array($data) ? $data : array();
	}

	private function updateState(int $entity, stdClass $config, string $change): void
	{
		$this->query('UPDATE '.MAIN_DB_PREFIX.'powerplantpv_monitor_config SET '.$change
			.' WHERE entity = '.$entity." AND provider = '".self::ATMOCE."' AND BINARY credentials = ".$this->quote((string) $config->credentials));
	}

	/**
	 * @template T
	 * @param Closure(): T $operation
	 * @return T|null
	 */
	private function withLock(int $entity, Closure $operation, bool $skipIfBusy = false)
	{
		$name = $this->quote('ppv_monitor_'.substr(hash('sha256', $this->db->database_name.'|'.MAIN_DB_PREFIX.'|'.$entity), 0, 36));
		$result = $this->query('SELECT GET_LOCK('.$name.', 0) AS acquired');
		$row = $this->db->fetch_object($result);
		$this->db->free($result);
		if ((int) ($row->acquired ?? 0) !== 1) {
			if ($skipIfBusy) {
				return null;
			}
			throw new RuntimeException('PowerPlantPVSupervisionBusy');
		}
		try {
			return $operation();
		} finally {
			$this->db->query('SELECT RELEASE_LOCK('.$name.')');
		}
	}

	private function quote(string $value): string
	{
		return "'".$this->db->escape($value)."'";
	}

	/** @return mysqli_result|true */
	private function query(string $sql)
	{
		$result = $this->db->query($sql);
		if (!$result) {
			throw new RuntimeException('PowerPlantPVSupervisionDatabaseError');
		}
		return $result;
	}
}
