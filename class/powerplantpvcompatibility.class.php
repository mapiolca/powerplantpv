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
 * \file		class/powerplantpvcompatibility.class.php
 * \ingroup		powerplantpv
 * \brief		Compatibility checks for PowerPlantPV features.
 */

/**
 * Centralized compatibility checks.
 */
class PowerPlantPVCompatibility
{
	public const MIN_PHP_VERSION = '8.0.0';
	public const MIN_DOLIBARR_VERSION = '20.0.0';

	/**
	 * Check Dolibarr version.
	 *
	 * @param	string	$version	Version
	 * @return	bool				True if current Dolibarr version is at least this version
	 */
	public static function isDolibarrVersionAtLeast($version)
	{
		return defined('DOL_VERSION') && version_compare(DOL_VERSION, $version, '>=');
	}

	/**
	 * Check PHP version.
	 *
	 * @param	string	$version	Version
	 * @return	bool				True if current PHP version is at least this version
	 */
	public static function isPhpVersionAtLeast($version)
	{
		return version_compare(PHP_VERSION, $version, '>=');
	}

	/**
	 * Check if the installed Dolibarr core can handle attestation online signatures natively.
	 *
	 * @return	bool	True if core public and ajax online signature endpoints know the attestation source
	 */
	public static function isCoreOnlineSignatureAttestationSupported()
	{
		static $supported = null;

		if ($supported !== null) {
			return $supported;
		}

		$supported = false;
		$source = 'powerplantpv_attestation';
		$publicfile = DOL_DOCUMENT_ROOT.'/public/onlinesign/newonlinesign.php';
		$ajaxfile = DOL_DOCUMENT_ROOT.'/core/ajax/onlineSign.php';

		if (!is_readable($publicfile) || !is_readable($ajaxfile)) {
			return false;
		}

		$publiccontent = @file_get_contents($publicfile, false, null, 0, 262144);
		$ajaxcontent = @file_get_contents($ajaxfile, false, null, 0, 262144);
		if (!is_string($publiccontent) || !is_string($ajaxcontent)) {
			return false;
		}

		$supported = (strpos($publiccontent, $source) !== false && strpos($ajaxcontent, $source) !== false);

		return $supported;
	}

	/**
	 * Return feature compatibility definitions.
	 *
	 * @return	array<string,array<string,mixed>>	Feature definitions
	 */
	public static function getFeatures()
	{
		dol_include_once('/powerplantpv/lib/powerplantpv_serialnumber.lib.php');
		require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';

		$baseavailable = self::isPhpVersionAtLeast(self::MIN_PHP_VERSION)
			&& self::isDolibarrVersionAtLeast(self::MIN_DOLIBARR_VERSION);
		$xlsxreadavailable = $baseavailable && powerplantpvSerialImportIsXlsxReadAvailable();
		$xlsxwriteavailable = $baseavailable && powerplantpvSerialImportIsXlsxAvailable();
		$pvfreeavailable = $baseavailable && function_exists('getURLContent');
		$signaturelibavailable = false;
		if (is_readable(DOL_DOCUMENT_ROOT.'/core/lib/signature.lib.php')) {
			require_once DOL_DOCUMENT_ROOT.'/core/lib/signature.lib.php';
			$signaturelibavailable = function_exists('getOnlineSignatureUrl') && function_exists('showOnlineSignatureUrl');
		}
		$attestationnativeonlinesignatureavailable = $baseavailable
			&& function_exists('hash_file')
			&& $signaturelibavailable
			&& self::isCoreOnlineSignatureAttestationSupported();
		$attestationalternativefile = dol_buildpath('/powerplantpv/public/onlinesign/attestation.php');
		$attestationalternativeonlinesignatureavailable = $baseavailable
			&& function_exists('hash_file')
			&& is_readable($attestationalternativefile);
		$attestationonlinesignatureavailable = $attestationnativeonlinesignatureavailable || $attestationalternativeonlinesignatureavailable;
		$attestationonlinesignaturereason = '';
		if (!$baseavailable) {
			$attestationonlinesignaturereason = 'PowerPlantPVRequiresDolibarr20Php80';
		} elseif (!function_exists('hash_file')) {
			$attestationonlinesignaturereason = 'AttestationSignatureHashUnavailable';
		} elseif (!$attestationonlinesignatureavailable) {
			$attestationonlinesignaturereason = 'AttestationAlternativeOnlineSignatureUnavailable';
		}
		$attestationnativeonlinesignaturereason = '';
		if (!$baseavailable) {
			$attestationnativeonlinesignaturereason = 'PowerPlantPVRequiresDolibarr20Php80';
		} elseif (!function_exists('hash_file')) {
			$attestationnativeonlinesignaturereason = 'AttestationSignatureHashUnavailable';
		} elseif (!$signaturelibavailable || !self::isCoreOnlineSignatureAttestationSupported()) {
			$attestationnativeonlinesignaturereason = 'AttestationNativeOnlineSignatureUnsupported';
		}
		$attestationalternativeonlinesignaturereason = '';
		if (!$baseavailable) {
			$attestationalternativeonlinesignaturereason = 'PowerPlantPVRequiresDolibarr20Php80';
		} elseif (!function_exists('hash_file')) {
			$attestationalternativeonlinesignaturereason = 'AttestationSignatureHashUnavailable';
		} elseif (!$attestationalternativeonlinesignatureavailable) {
			$attestationalternativeonlinesignaturereason = 'AttestationAlternativeOnlineSignatureUnavailable';
		}

		return array(
			'powerplant_core' => array(
				'label' => 'PowerPlant',
				'description' => 'PowerPlantCompatibilityCoreDescription',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'available' => $baseavailable,
				'reason' => ($baseavailable ? '' : 'PowerPlantPVRequiresDolibarr20Php80'),
			),
			'maintenance_data_foundation' => array(
				'label' => 'MaintenanceDataFoundation',
				'description' => 'MaintenanceDataFoundationCompatibilityDescription',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'available' => $baseavailable,
				'reason' => ($baseavailable ? '' : 'PowerPlantPVRequiresDolibarr20Php80'),
			),
			'native_powerplant_links' => array(
				'label' => 'PowerPlantPVNativePowerPlantLinks',
				'description' => 'PowerPlantPVNativePowerPlantLinksCompatibilityDescription',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'available' => $baseavailable,
				'reason' => ($baseavailable ? '' : 'PowerPlantPVRequiresDolibarr20Php80'),
			),
			'maintenance_scheduler' => array(
				'label' => 'PowerPlantPVMaintenanceSchedulerFeature',
				'description' => 'PowerPlantPVMaintenanceSchedulerCompatibilityDescription',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'available' => $baseavailable,
				'reason' => ($baseavailable ? '' : 'PowerPlantPVRequiresDolibarr20Php80'),
			),
			'report_template_engine' => array(
				'label' => 'PowerPlantPVReportTemplateEngine',
				'description' => 'PowerPlantPVReportTemplateEngineCompatibilityDescription',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'available' => $baseavailable,
				'reason' => ($baseavailable ? '' : 'PowerPlantPVRequiresDolibarr20Php80'),
			),
			'intervention_report_tab' => array(
				'label' => 'PowerPlantPVInterventionReportTabFeature',
				'description' => 'PowerPlantPVInterventionReportTabCompatibilityDescription',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'available' => $baseavailable,
				'reason' => ($baseavailable ? '' : 'PowerPlantPVRequiresDolibarr20Php80'),
			),
			'serial_number_import_csv' => array(
				'label' => 'SerialNumbersImportCsvFeature',
				'description' => 'SerialNumbersImportCsvFeatureDescription',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'available' => $baseavailable,
				'reason' => ($baseavailable ? '' : 'PowerPlantPVRequiresDolibarr20Php80'),
			),
			'serial_number_import_xlsx' => array(
				'label' => 'SerialNumbersImportXlsxFeature',
				'description' => 'SerialNumbersImportXlsxFeatureDescription',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'available' => $xlsxreadavailable,
				'reason' => ($xlsxreadavailable ? '' : 'SerialNumbersXlsxReaderUnavailable'),
			),
			'serial_number_export_csv' => array(
				'label' => 'SerialNumbersExportCsvFeature',
				'description' => 'SerialNumbersExportCsvFeatureDescription',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'available' => $baseavailable,
				'reason' => ($baseavailable ? '' : 'PowerPlantPVRequiresDolibarr20Php80'),
			),
			'serial_number_export_xlsx' => array(
				'label' => 'SerialNumbersExportXlsxFeature',
				'description' => 'SerialNumbersExportXlsxFeatureDescription',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'available' => $xlsxwriteavailable,
				'reason' => ($xlsxwriteavailable ? '' : 'SerialNumbersXlsxReaderUnavailable'),
			),
			'product_technical_import_csv' => array(
				'label' => 'ProductTechnicalImportCsvFeature',
				'description' => 'ProductTechnicalImportCsvFeatureDescription',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'available' => $baseavailable,
				'reason' => ($baseavailable ? '' : 'PowerPlantPVRequiresDolibarr20Php80'),
			),
			'product_technical_import_xlsx' => array(
				'label' => 'ProductTechnicalImportXlsxFeature',
				'description' => 'ProductTechnicalImportXlsxFeatureDescription',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'available' => $xlsxreadavailable,
				'reason' => ($xlsxreadavailable ? '' : 'ProductTechnicalImportXlsxReaderUnavailable'),
			),
			'pvfree_connector' => array(
				'label' => 'PVFreeConnector',
				'description' => 'PVFreeConnectorCompatibilityDescription',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'available' => $pvfreeavailable,
				'reason' => ($pvfreeavailable ? '' : 'PVFreeGetURLContentUnavailable'),
			),
			'attestation_core' => array(
				'label' => 'Attestations',
				'description' => 'AttestationCompatibilityCoreDescription',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'available' => $baseavailable,
				'reason' => ($baseavailable ? '' : 'PowerPlantPVRequiresDolibarr20Php80'),
			),
			'attestation_pdf' => array(
				'label' => 'AttestationPdfModels',
				'description' => 'AttestationCompatibilityPdfDescription',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'available' => $baseavailable,
				'reason' => ($baseavailable ? '' : 'PowerPlantPVRequiresDolibarr20Php80'),
			),
			'attestation_online_signature' => array(
				'label' => 'AttestationOnlineSignature',
				'description' => 'AttestationCompatibilitySignatureDescription',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'available' => $attestationonlinesignatureavailable,
				'reason' => $attestationonlinesignaturereason,
			),
			'attestation_native_online_signature' => array(
				'label' => 'AttestationNativeOnlineSignature',
				'description' => 'AttestationCompatibilityNativeSignatureDescription',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'available' => $attestationnativeonlinesignatureavailable,
				'reason' => $attestationnativeonlinesignaturereason,
			),
			'attestation_alternative_online_signature' => array(
				'label' => 'AttestationAlternativeOnlineSignature',
				'description' => 'AttestationCompatibilityAlternativeSignatureDescription',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'available' => $attestationalternativeonlinesignatureavailable,
				'reason' => $attestationalternativeonlinesignaturereason,
			),
		);
	}

	/**
	 * Check if a feature is available.
	 *
	 * @param	string	$code	Feature code
	 * @return	bool			True if available
	 */
	public static function isFeatureAvailable($code)
	{
		$features = self::getFeatures();

		return !empty($features[$code]['available']);
	}

	/**
	 * Return unavailable feature definitions.
	 *
	 * @return	array<string,array<string,mixed>>	Unavailable features
	 */
	public static function getUnavailableFeatures()
	{
		$unavailable = array();
		foreach (self::getFeatures() as $code => $feature) {
			if (empty($feature['available'])) {
				$unavailable[$code] = $feature;
			}
		}

		return $unavailable;
	}
}
