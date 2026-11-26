<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * Licensed under the GNU General Public License, version 3 or later.
 */

/**
 * Read-only ATMOCE Cloud connector, based on API Reference V1.2.6.
 */
final class PowerPlantPVAtmoceClient
{
	public const MAX_SITES_PER_REQUEST = 100;
	public const MONTHLY_CALL_LIMIT = 10000;
	public const CONCURRENT_CALL_LIMIT = 5;
	public const MEASUREMENT_INTERVAL_MINUTES = 15;

	private const API_URL = 'https://www.atmocecloud.com/openapi/v1';
	private string $accessToken = '';
	private int $expiresAt = 0;

	/**
	 * @param Closure(string, string, list<string>, string): array{status: int, body: string}|null $transport
	 */
	public function __construct(private string $appKey, private string $appSecret, private ?Closure $transport = null)
	{
	}

	/**
	 * @return array{token: string, expires_at: int}
	 */
	public function getSession(): array
	{
		if ($this->accessToken === '' || time() >= $this->expiresAt) {
			$response = $this->request('POST', '/auth/token', array(), array(
				'grant_type' => 'system',
				'app_key' => $this->appKey,
				'app_secret' => $this->appSecret,
			), false);
			$session = $response['data'] ?? null;
			$token = is_array($session) ? ($session['access_token'] ?? '') : '';
			$expiry = is_array($session) ? filter_var($session['expires_in'] ?? null, FILTER_VALIDATE_INT) : false;
			if (!is_string($token) || !preg_match('/^[A-Za-z0-9._~+\/=-]+$/D', $token) || $expiry === false || $expiry <= 0) {
				throw new RuntimeException('InvalidAtmoceResponse');
			}
			$this->accessToken = $token;
			$this->expiresAt = time() + $expiry - min(60, intdiv($expiry, 2));
		}

		return array('token' => $this->accessToken, 'expires_at' => $this->expiresAt);
	}

	public function reuseSession(string $token, int $expiresAt): void
	{
		if ($expiresAt > time() && preg_match('/^[A-Za-z0-9._~+\/=-]+$/D', $token)) {
			$this->accessToken = $token;
			$this->expiresAt = $expiresAt;
		}
	}

	/**
	 * @return array{sites: list<array{id: string, name: string, timezone: string}>, complete: bool}
	 */
	public function listSites(int $page = 1): array
	{
		$response = $this->request('GET', '/sites/getSites', array('page' => max(1, $page)));
		$pageEnd = $response['pageEnd'] ?? null;
		if (!in_array($pageEnd, array(0, 1, '0', '1'), true)) {
			throw new RuntimeException('InvalidAtmoceResponse');
		}
		$sites = array();
		foreach ($this->dataList($response) as $row) {
			$id = $row['siteId'] ?? null;
			if (!is_string($id) || !$this->isSiteId($id)) {
				throw new RuntimeException('InvalidAtmoceResponse');
			}
			$sites[] = array(
				'id' => $id,
				'name' => isset($row['name']) && is_string($row['name']) && trim($row['name']) !== '' ? $row['name'] : $id,
				'timezone' => isset($row['timeZone']) && is_string($row['timeZone']) ? $row['timeZone'] : '',
			);
		}

		return array('sites' => $sites, 'complete' => (int) $pageEnd === 1);
	}

	/**
	 * @param list<string> $siteIds
	 * @return list<array{site_id: string, reported_at: int, status: int, power_w: ?float, energy_today_kwh: ?float}>
	 */
	public function latest(array $siteIds): array
	{
		$response = $this->request('GET', '/sites/getSitesLastPower', array('siteIds' => $this->siteIdsParameter($siteIds)));
		$readings = array();
		foreach ($this->dataList($response) as $row) {
			$id = $row['siteId'] ?? null;
			$reportedAt = filter_var($row['lastReportedTime'] ?? null, FILTER_VALIDATE_INT);
			$status = filter_var($row['status'] ?? null, FILTER_VALIDATE_INT);
			if (!is_string($id) || !in_array($id, $siteIds, true) || !is_int($reportedAt) || $reportedAt < 1000 || intdiv($reportedAt, 1000) > time() + 300 || !in_array($status, array(0, 1, 2), true)) {
				throw new RuntimeException('InvalidAtmoceResponse');
			}
			$readings[] = array(
				'site_id' => $id,
				'reported_at' => intdiv($reportedAt, 1000),
				'status' => $status,
				'power_w' => $this->numericValue($row['solarGenerationPower'] ?? null),
				'energy_today_kwh' => $this->numericValue($row['dailySolarGeneration'] ?? null),
			);
		}

		return $readings;
	}

	/** @param list<string> $ids */
	private function siteIdsParameter(array $ids): string
	{
		if (!$ids || count($ids) > self::MAX_SITES_PER_REQUEST) {
			throw new InvalidArgumentException('InvalidAtmoceSiteBatch');
		}
		foreach ($ids as $id) {
			if (!$this->isSiteId($id)) {
				throw new InvalidArgumentException('InvalidAtmoceSiteId');
			}
		}

		return implode(',', $ids);
	}

	private function isSiteId(string $id): bool
	{
		return $id !== '' && strlen($id) <= 128 && !preg_match('/[\x00-\x20\x7f,]/', $id);
	}

	/** @param mixed $value */
	private function numericValue($value): ?float
	{
		if ($value === null) {
			return null;
		}
		if (!is_numeric($value) || !is_finite((float) $value)) {
			throw new RuntimeException('InvalidAtmoceResponse');
		}
		return (float) $value;
	}

	/**
	 * @param array<string, mixed> $response
	 * @return list<array<string, mixed>>
	 */
	private function dataList(array $response): array
	{
		$data = $response['data'] ?? null;
		if (!is_array($data) || array_values($data) !== $data) {
			throw new RuntimeException('InvalidAtmoceResponse');
		}
		foreach ($data as $row) {
			if (!is_array($row)) {
				throw new RuntimeException('InvalidAtmoceResponse');
			}
		}
		return $data;
	}

	/**
	 * @param array<string, int|string> $query
	 * @param array<string, string> $body
	 * @return array<string, mixed>
	 */
	private function request(string $method, string $path, array $query = array(), array $body = array(), bool $authenticate = true): array
	{
		$url = self::API_URL.$path.($query ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
		$headers = array('Accept: application/json');
		if ($authenticate) {
			$headers[] = 'Authorization: Bearer '.$this->getSession()['token'];
		}
		$encodedBody = '';
		if ($method === 'POST') {
			$headers[] = 'Content-Type: application/json';
			$encodedBody = json_encode($body, JSON_THROW_ON_ERROR);
		}
		$transport = $this->transport ?? Closure::fromCallable(array($this, 'send'));
		$result = $transport($method, $url, $headers, $encodedBody);
		$payload = json_decode($result['body'], true);
		$code = is_array($payload) ? ($payload['code'] ?? null) : null;
		if ($result['status'] === 429 || in_array($code, array(429, '429', 4021, '4021'), true)) {
			throw new RuntimeException('AtmoceQuotaExceeded');
		}
		if ($result['status'] === 401 || in_array($code, array(401, '401', 468, '468', 3018, '3018'), true)) {
			$this->accessToken = '';
			$this->expiresAt = 0;
			throw new RuntimeException('AtmoceAuthenticationFailed');
		}
		if ($result['status'] !== 200 || !is_array($payload) || ($payload['success'] ?? null) !== true) {
			throw new RuntimeException('AtmoceRequestFailed');
		}

		return $payload;
	}

	/**
	 * @param list<string> $headers
	 * @return array{status: int, body: string}
	 */
	private function send(string $method, string $url, array $headers, string $body): array
	{
		// Dolibarr v20 getURLContent logs POST parameters. Authentication must keep them out of the logs.
		if (!function_exists('curl_init')) {
			throw new RuntimeException('AtmoceCurlUnavailable');
		}
		$handle = curl_init($url);
		if ($handle === false) {
			throw new RuntimeException('AtmoceRequestFailed');
		}
		$options = array(
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT => 20,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
		);
		if ($method === 'POST') {
			$options[CURLOPT_POST] = true;
			$options[CURLOPT_POSTFIELDS] = $body;
		}
		try {
			if (!curl_setopt_array($handle, $options)) {
				throw new RuntimeException('AtmoceRequestFailed');
			}
			$result = curl_exec($handle);
			if (!is_string($result)) {
				throw new RuntimeException('AtmoceRequestFailed');
			}
			return array('status' => (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'body' => $result);
		} finally {
			curl_close($handle);
		}
	}
}
