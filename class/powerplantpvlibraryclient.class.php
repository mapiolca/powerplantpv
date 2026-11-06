<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
require_once __DIR__.'/powerplantpvlibrarycontract.class.php';

/** Pull-only client: fixed configured HTTPS origin, native REST authentication, no redirects. */
class PowerPlantPVLibraryClient
{
	/** @return array<string,mixed> */
	public function get(string $path): array
	{
		$base = rtrim(getDolGlobalString('POWERPLANTPV_LIBRARY_URL'), '/');
		$key = getenv('POWERPLANTPV_LIBRARY_API_KEY_'.((int) $GLOBALS['conf']->entity));
		if (!preg_match('~^https://[a-zA-Z0-9.-]+(?::443)?(?:/[a-zA-Z0-9_/-]+)?$~D', $base)
			|| !is_string($key) || $key === '' || preg_match('/[\r\n]/', $key) || !preg_match('~^/(?:capabilities|items(?:/[a-f0-9]{32})?)(?:\?[a-zA-Z0-9_%=&.+-]*)?$~D', $path)) {
			throw new RuntimeException('PVLibraryNotConfigured');
		}
		// Origin is administrator-controlled. Never send the key to a returned document or citation URL.
		$response = getURLContent($base.'/api/index.php/pvlibrary/v1'.$path, 'GET', '', 0,
			array('DOLAPIKEY: '.$key), array('https'), 0, 1, 5, 20, array(CURLINFO_HEADER_OUT => false, CURLOPT_VERBOSE => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2));
		if ($response['http_code'] !== 200 || strlen($response['content']) > 2097152) {
			throw new RuntimeException('PVLibraryRemoteError');
		}
		$data = json_decode($response['content'], true);
		if (!is_array($data)) {
			throw new RuntimeException('PVLibraryRemoteError');
		}
		return $data;
	}
}
