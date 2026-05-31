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
 * \file        core/substitutions/functions_powerplantpv.lib.php
 * \ingroup     powerplantpv
 * \brief       Substitution functions for PowerPlantPV.
 */

if (!defined('DOL_DOCUMENT_ROOT')) {
	exit;
}

dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');

/**
 * Complete substitution array with linked power plant data.
 *
 * @param	array<string,string>	$substitutionarray	Substitution array
 * @param	Translate			$outputlangs		Output language
 * @param	CommonObject		$object				Current object
 * @param	mixed				$parameters			Optional parameters
 * @return	void
 */
function powerplantpv_completesubstitutionarray(&$substitutionarray, $outputlangs, $object, $parameters = null)
{
	if (!is_object($object) || !isModEnabled('powerplantpv')) {
		return;
	}

	$powerplants = powerplantpvGetLinkedPowerPlants($object);
	$count = count($powerplants);

	powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_count', (string) $count);
	if ($count === 0) {
		powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_refs', '');
		powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_summary', '');
		return;
	}

	$refs = array();
	$labels = array();
	$addresses = array();
	$towns = array();
	$prmpdls = array();
	$buybackcontracts = array();
	$installedpowers = array();
	$connectionpowers = array();
	$summarylines = array();
	$totalinstalledpower = 0.0;

	$i = 0;
	foreach ($powerplants as $powerplant) {
		$i++;

		$installedpower = powerplantpvFormatPowerForSubstitution($powerplant->installed_power);
		$connectionpower = powerplantpvFormatPowerForSubstitution($powerplant->connection_contract_power);
		$buybacktariff = powerplantpvFormatPriceForSubstitution($powerplant->buyback_tariff);

		if ($powerplant->installed_power !== null && $powerplant->installed_power !== '') {
			$totalinstalledpower += (float) $powerplant->installed_power;
		}

		$refs[] = (string) $powerplant->ref;
		if (!empty($powerplant->label)) {
			$labels[] = (string) $powerplant->label;
		}
		if (!empty($powerplant->address)) {
			$addresses[] = (string) $powerplant->address;
		}
		if (!empty($powerplant->town)) {
			$towns[] = (string) $powerplant->town;
		}
		if (!empty($powerplant->prm_pdl_number)) {
			$prmpdls[] = (string) $powerplant->prm_pdl_number;
		}
		if (!empty($powerplant->buyback_contract_number)) {
			$buybackcontracts[] = (string) $powerplant->buyback_contract_number;
		}
		if ($installedpower !== '') {
			$installedpowers[] = $installedpower;
		}
		if ($connectionpower !== '') {
			$connectionpowers[] = $connectionpower;
		}

		$summary = trim($powerplant->ref.' - '.$powerplant->label);
		if ($installedpower !== '') {
			$summary .= ' - '.$installedpower;
		}
		if (!empty($powerplant->town)) {
			$summary .= ' - '.$powerplant->town;
		}
		$summarylines[] = $summary;

		powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_'.$i.'_ref', (string) $powerplant->ref);
		powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_'.$i.'_label', (string) $powerplant->label);
		powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_'.$i.'_address', (string) $powerplant->address);
		powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_'.$i.'_zip', (string) $powerplant->zip);
		powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_'.$i.'_town', (string) $powerplant->town);
		powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_'.$i.'_prm_pdl_number', (string) $powerplant->prm_pdl_number);
		powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_'.$i.'_installed_power', $installedpower);
		powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_'.$i.'_connection_contract_power', $connectionpower);
		powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_'.$i.'_connection_type', (string) $powerplant->connection_type);
		powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_'.$i.'_commissioning_date', powerplantpvFormatDateForSubstitution($powerplant->commissioning_date, $outputlangs));
		powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_'.$i.'_enedis_commissioning_date', powerplantpvFormatDateForSubstitution($powerplant->enedis_commissioning_date, $outputlangs));
		powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_'.$i.'_connection_request_number', (string) $powerplant->connection_request_number);
		powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_'.$i.'_t0_obtention_date', powerplantpvFormatDateForSubstitution($powerplant->t0_obtention_date, $outputlangs));
		powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_'.$i.'_buyback_contract_number', (string) $powerplant->buyback_contract_number);
		powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_'.$i.'_buyback_tariff', $buybacktariff);
	}

	powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_refs', implode(', ', $refs));
	powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_labels', implode(', ', $labels));
	powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_addresses', implode(', ', $addresses));
	powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_towns', implode(', ', $towns));
	powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_prm_pdl_numbers', implode(', ', $prmpdls));
	powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_buyback_contract_numbers', implode(', ', $buybackcontracts));
	powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_installed_powers', implode(', ', $installedpowers));
	powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_connection_contract_powers', implode(', ', $connectionpowers));
	powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_installed_power_total', powerplantpvFormatPowerForSubstitution($totalinstalledpower));
	powerplantpvSetSubstitution($substitutionarray, 'powerplantpv_summary', implode("\n", $summarylines));
}

/**
 * Add lower-case ODT and upper-case free-text style substitution keys.
 *
 * @param	array<string,string>	$substitutionarray	Substitution array
 * @param	string				$key				Lower-case key
 * @param	string				$value				Value
 * @return	void
 */
function powerplantpvSetSubstitution(&$substitutionarray, $key, $value)
{
	$substitutionarray[$key] = (string) $value;
	$substitutionarray['__'.strtoupper($key).'__'] = (string) $value;
}

/**
 * Format a date for substitutions.
 *
 * @param	int|string|null	$date			Date value
 * @param	Translate		$outputlangs	Output language
 * @return	string							Formatted date
 */
function powerplantpvFormatDateForSubstitution($date, $outputlangs)
{
	if (empty($date)) {
		return '';
	}

	return dol_print_date($date, 'day', 'tzuser', $outputlangs, true);
}

/**
 * Format a power value for substitutions.
 *
 * @param	float|string|null	$value	Power value
 * @return	string						Formatted power
 */
function powerplantpvFormatPowerForSubstitution($value)
{
	if ($value === null || $value === '') {
		return '';
	}

	return price($value).' kWc';
}

/**
 * Format a price value for substitutions.
 *
 * @param	float|string|null	$value	Price value
 * @return	string						Formatted price
 */
function powerplantpvFormatPriceForSubstitution($value)
{
	if ($value === null || $value === '') {
		return '';
	}

	return price($value);
}
