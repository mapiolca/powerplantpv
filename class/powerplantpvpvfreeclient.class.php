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
 * \file       class/powerplantpvpvfreeclient.class.php
 * \ingroup    powerplantpv
 * \brief      PV Free API client.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';

/**
 * Small client dedicated to the PV Free public API.
 */
class PowerPlantPVPVFreeClient
{
	/**
	 * @var string Error message
	 */
	public $error = '';

	/**
	 * @var array<int,string> Error messages
	 */
	public $errors = array();

	/**
	 * Search PV modules.
	 *
	 * @param string $query   Search query
	 * @param string $dataset Dataset code
	 * @param int    $limit   Result limit
	 * @return array<string,mixed>|null API response
	 */
	public function searchModules($query, $dataset = 'cecmodule', $limit = 20)
	{
		$dataset = in_array($dataset, array('cecmodule', 'pvmodule'), true) ? $dataset : 'cecmodule';

		return $this->request($dataset, array(
			'Name__icontains' => $query,
			'limit' => $this->normalizeLimit($limit),
		));
	}

	/**
	 * Search PV inverters.
	 *
	 * @param string $query   Search query
	 * @param string $dataset Dataset code
	 * @param int    $limit   Result limit
	 * @return array<string,mixed>|null API response
	 */
	public function searchInverters($query, $dataset = 'pvinverter', $limit = 20)
	{
		$dataset = ($dataset === 'pvinverter') ? $dataset : 'pvinverter';

		return $this->request($dataset, array(
			'Name__icontains' => $query,
			'limit' => $this->normalizeLimit($limit),
		));
	}

	/**
	 * Fetch a detail resource from a PV Free resource URI.
	 *
	 * @param string $resourceUri Resource URI
	 * @return array<string,mixed>|null API object
	 */
	public function fetchDetail($resourceUri)
	{
		$resourceUri = trim($resourceUri);
		if (!preg_match('#^/api/v1/(cecmodule|pvmodule|pvinverter)/[0-9]+/$#', $resourceUri)) {
			$this->setError('PVFreeInvalidResourceUri');
			return null;
		}

		return $this->request($resourceUri, array());
	}

	/**
	 * Run a whitelisted GET request against PV Free.
	 *
	 * @param string              $endpoint Endpoint code or resource URI
	 * @param array<string,mixed> $params   Query parameters
	 * @return array<string,mixed>|null Decoded JSON response
	 */
	public function request($endpoint, array $params = array())
	{
		global $conf;

		$this->resetErrors();

		if (!getDolGlobalInt('POWERPLANTPV_PVFREE_ENABLED')) {
			$this->setError('PVFreeConnectorDisabled');
			return null;
		}

		$baseurl = $this->getBaseUrl();
		if ($baseurl === '') {
			$this->setError('PVFreeInvalidAPIUrl');
			return null;
		}

		$path = $this->normalizeEndpoint($endpoint);
		if ($path === '') {
			$this->setError('PVFreeInvalidEndpoint');
			return null;
		}

		$url = $baseurl.$path;
			if (!empty($params)) {
				$url .= '?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
			}

			if (!function_exists('getURLContent')) {
				$this->setError('PVFreeGetURLContentUnavailable');
				return null;
			}

			$timeout = max(1, getDolGlobalInt('POWERPLANTPV_PVFREE_TIMEOUT', 10));
			$hadConnectTimeout = property_exists($conf->global, 'MAIN_USE_CONNECT_TIMEOUT');
			$hadResponseTimeout = property_exists($conf->global, 'MAIN_USE_RESPONSE_TIMEOUT');
			$oldConnectTimeout = $hadConnectTimeout ? $conf->global->MAIN_USE_CONNECT_TIMEOUT : null;
			$oldResponseTimeout = $hadResponseTimeout ? $conf->global->MAIN_USE_RESPONSE_TIMEOUT : null;

			$conf->global->MAIN_USE_CONNECT_TIMEOUT = $timeout;
			$conf->global->MAIN_USE_RESPONSE_TIMEOUT = $timeout;

			$response = null;
			try {
				$response = getURLContent($url, 'GET', '', 1, array('Accept: application/json'), array('https'), 0);
			} catch (Throwable $e) {
				$this->setError('PVFreeUnableToReachAPI');
				$this->errors[] = $e->getMessage();
				return null;
			} finally {
				if ($hadConnectTimeout) {
					$conf->global->MAIN_USE_CONNECT_TIMEOUT = $oldConnectTimeout;
				} else {
					unset($conf->global->MAIN_USE_CONNECT_TIMEOUT);
				}
				if ($hadResponseTimeout) {
					$conf->global->MAIN_USE_RESPONSE_TIMEOUT = $oldResponseTimeout;
				} else {
					unset($conf->global->MAIN_USE_RESPONSE_TIMEOUT);
				}
			}

			if (!is_array($response)) {
				$this->setError('PVFreeUnableToReachAPI');
				return null;
		}

		if (!empty($response['curl_error_no'])) {
			$this->setError(((int) $response['curl_error_no'] === 28) ? 'PVFreeTimeoutError' : 'PVFreeUnableToReachAPI');
			if (!empty($response['curl_error_msg'])) {
				$this->errors[] = (string) $response['curl_error_msg'];
			}
			return null;
		}

		$httpcode = isset($response['http_code']) ? (int) $response['http_code'] : 0;
		if ($httpcode < 200 || $httpcode >= 300) {
			$this->setError('PVFreeUnableToReachAPI');
			$this->errors[] = 'HTTP '.$httpcode;
			return null;
		}

		if (empty($response['content'])) {
			$this->setError('PVFreeInvalidResponse');
			return null;
		}

		$decoded = json_decode((string) $response['content'], true);
		if (!is_array($decoded)) {
			$this->setError('PVFreeInvalidResponse');
			return null;
		}

		return $decoded;
	}

	/**
	 * Return last error.
	 *
	 * @return string Error
	 */
	public function getLastError()
	{
		return $this->error;
	}

	/**
	 * Return last errors.
	 *
	 * @return array<int,string> Errors
	 */
	public function getLastErrors()
	{
		return $this->errors;
	}

	/**
	 * Reset errors.
	 *
	 * @return void
	 */
	protected function resetErrors()
	{
		$this->error = '';
		$this->errors = array();
	}

	/**
	 * Get configured API base URL.
	 *
	 * @return string Base URL
	 */
	protected function getBaseUrl()
	{
		$baseurl = trim(getDolGlobalString('POWERPLANTPV_PVFREE_API_URL', 'https://pvfree.azurewebsites.net'));
		$baseurl = rtrim($baseurl, '/');
		$parts = parse_url($baseurl);
		if (empty($parts['scheme']) || strtolower((string) $parts['scheme']) !== 'https' || empty($parts['host'])) {
			return '';
		}

		return $baseurl;
	}

	/**
	 * Normalize an endpoint to an API path.
	 *
	 * @param string $endpoint Endpoint code or resource URI
	 * @return string API path
	 */
	protected function normalizeEndpoint($endpoint)
	{
		$endpoint = trim($endpoint);
		if (in_array($endpoint, array('cecmodule', 'pvmodule', 'pvinverter'), true)) {
			return '/api/v1/'.$endpoint.'/';
		}
		if (preg_match('#^/api/v1/(cecmodule|pvmodule|pvinverter)/[0-9]+/$#', $endpoint)) {
			return $endpoint;
		}

		return '';
	}

	/**
	 * Normalize result limit.
	 *
	 * @param int $limit Limit
	 * @return int Limit
	 */
	protected function normalizeLimit($limit)
	{
		$limit = (int) $limit;
		if ($limit <= 0) {
			return 20;
		}

		return min($limit, 20);
	}

	/**
	 * Register an error.
	 *
	 * @param string $error Error key
	 * @return void
	 */
	protected function setError($error)
	{
		$this->error = $error;
		$this->errors[] = $error;
	}
}
