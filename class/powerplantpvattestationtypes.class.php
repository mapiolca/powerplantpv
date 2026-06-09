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
 * \file		class/powerplantpvattestationtypes.class.php
 * \ingroup		powerplantpv
 * \brief		Attestation type definitions for PowerPlantPV.
 */

/**
 * Central definition of attestation types.
 */
class PowerPlantPVAttestationTypes
{
	public const TYPE_BRIDAGE_DYNAMIQUE_ONDULEUR = 'BRIDAGE_DYNAMIQUE_ONDULEUR';
	public const TYPE_BRIDAGE_STATIQUE_ONDULEUR = 'BRIDAGE_STATIQUE_ONDULEUR';
	public const TYPE_REGLAGE_MAX_FREQ_51_5HZ = 'REGLAGE_MAX_FREQ_51_5HZ';
	public const TYPE_INSTALLATEUR_INF_100KWC = 'INSTALLATEUR_INF_100KWC';

	/**
	 * Return type definitions.
	 *
	 * @return	array<string,array<string,mixed>>	Type definitions
	 */
	public static function getTypes()
	{
		return array(
			self::TYPE_BRIDAGE_DYNAMIQUE_ONDULEUR => array(
				'label' => 'AttestationTypeBridageDynamiqueOnduleur',
				'model_pdf' => 'attestation_bridage_dynamique',
				'equipment_types' => array('INVERTER'),
				'bridage_type' => 'DYNAMIC',
				'required_fields' => array('fk_powerplant', 'project_name', 'address', 'zip', 'town', 'max_export_power_kw', 'installer_name', 'date_attestation', 'place'),
			),
			self::TYPE_BRIDAGE_STATIQUE_ONDULEUR => array(
				'label' => 'AttestationTypeBridageStatiqueOnduleur',
				'model_pdf' => 'attestation_bridage_statique',
				'equipment_types' => array('INVERTER'),
				'bridage_type' => 'STATIC',
				'required_fields' => array('fk_powerplant', 'project_name', 'address', 'zip', 'town', 'max_export_power_kw', 'installer_name', 'date_attestation', 'place'),
			),
			self::TYPE_REGLAGE_MAX_FREQ_51_5HZ => array(
				'label' => 'AttestationTypeReglageMaxFreq515Hz',
				'model_pdf' => 'attestation_reglage_max_freq',
				'equipment_types' => array('INVERTER'),
				'required_fields' => array('fk_powerplant', 'project_name', 'address', 'zip', 'town', 'date_setting', 'max_frequency_hz', 'writer_name', 'writer_function', 'date_attestation', 'place'),
			),
			self::TYPE_INSTALLATEUR_INF_100KWC => array(
				'label' => 'AttestationTypeInstallateurInf100kwc',
				'model_pdf' => 'attestation_installateur_inf100kwc',
				'equipment_types' => array('MODULE', 'CONNECTOR', 'BOX'),
				'required_fields' => array('bta_contract_number', 'installer_name', 'installer_address', 'date_completion', 'date_attestation', 'place'),
			),
		);
	}

	/**
	 * Return one type definition.
	 *
	 * @param	string	$typecode	Type code
	 * @return	array<string,mixed>|null	Type definition or null
	 */
	public static function getType($typecode)
	{
		$types = self::getTypes();

		return isset($types[$typecode]) ? $types[$typecode] : null;
	}

	/**
	 * Return translated type labels.
	 *
	 * @param	Translate	$langs	Translation handler
	 * @return	array<string,string>	Labels indexed by type code
	 */
	public static function getTypeLabels($langs)
	{
		$labels = array();
		foreach (self::getTypes() as $code => $definition) {
			$labels[$code] = $langs->trans($definition['label']);
		}

		return $labels;
	}

	/**
	 * Check if a type is known.
	 *
	 * @param	string	$typecode	Type code
	 * @return	bool				True when supported
	 */
	public static function isValidType($typecode)
	{
		return self::getType($typecode) !== null;
	}

	/**
	 * Return PDF model for type.
	 *
	 * @param	string	$typecode	Type code
	 * @return	string				Model name, empty if unknown
	 */
	public static function getModelForType($typecode)
	{
		$definition = self::getType($typecode);
		$default = !empty($definition['model_pdf']) ? (string) $definition['model_pdf'] : '';
		$const = '';
		if ($typecode == self::TYPE_BRIDAGE_DYNAMIQUE_ONDULEUR) {
			$const = 'POWERPLANTPV_ATTESTATION_BRIDAGE_DYNAMIQUE_MODEL';
		} elseif ($typecode == self::TYPE_BRIDAGE_STATIQUE_ONDULEUR) {
			$const = 'POWERPLANTPV_ATTESTATION_BRIDAGE_STATIQUE_MODEL';
		} elseif ($typecode == self::TYPE_REGLAGE_MAX_FREQ_51_5HZ) {
			$const = 'POWERPLANTPV_ATTESTATION_REGLAGE_FREQ_MODEL';
		} elseif ($typecode == self::TYPE_INSTALLATEUR_INF_100KWC) {
			$const = 'POWERPLANTPV_ATTESTATION_INSTALLATEUR_INF100KWC_MODEL';
		}
		if ($const !== '' && function_exists('getDolGlobalString')) {
			return getDolGlobalString($const, $default);
		}

		return $default;
	}
}
