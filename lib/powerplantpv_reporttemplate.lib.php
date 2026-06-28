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
 * \file		lib/powerplantpv_reporttemplate.lib.php
 * \ingroup		powerplantpv
 * \brief		Report template engine helpers.
 */

/**
 * Check a maintenance permission, with Dolibarr administrator bypass.
 *
 * @param	User	$user	Current user
 * @param	string	$right	Right code
 * @return	bool			True when allowed
 */
function powerplantpvMaintenanceUserHasRight($user, $right = 'config')
{
	if (!empty($user->admin)) {
		return true;
	}

	if (method_exists($user, 'hasRight')) {
		return (bool) $user->hasRight('powerplantpv', 'maintenance', $right);
	}

	return !empty($user->rights->powerplantpv->maintenance->{$right});
}

/**
 * Return supported report section scopes.
 *
 * @return	array<string,string>	Option labels by code
 */
function powerplantpvReportTemplateScopeTypes()
{
	return array(
		'intervention' => 'PowerPlantPVReportScopeIntervention',
		'powerplant' => 'PowerPlantPVReportScopePowerPlant',
		'equipment' => 'PowerPlantPVReportScopeEquipment',
		'inverter' => 'PowerPlantPVReportScopeInverter',
		'electrical_box' => 'PowerPlantPVReportScopeElectricalBox',
		'mppt' => 'PowerPlantPVReportScopeMppt',
		'pv_input' => 'PowerPlantPVReportScopePvInput',
		'roof_area' => 'PowerPlantPVReportScopeRoofArea',
		'free_line' => 'PowerPlantPVReportScopeFreeLine',
	);
}

/**
 * Return supported equipment types.
 *
 * @return	array<string,string>	Option labels by code
 */
function powerplantpvReportTemplateEquipmentTypes()
{
	return array(
		'' => '',
		'PANEL' => 'PowerPlantPVEquipmentTypePanel',
		'INVERTER' => 'PowerPlantPVEquipmentTypeInverter',
		'DC_BOX' => 'PowerPlantPVEquipmentTypeDcBox',
		'AC_BOX' => 'PowerPlantPVEquipmentTypeAcBox',
		'METER' => 'PowerPlantPVEquipmentTypeMeter',
		'STRING' => 'PowerPlantPVEquipmentTypeString',
		'ROOF_AREA' => 'PowerPlantPVEquipmentTypeRoofArea',
		'THERMOGRAPHY_AREA' => 'PowerPlantPVEquipmentTypeThermographyArea',
		'OTHER' => 'PowerPlantPVEquipmentTypeOther',
	);
}

/**
 * Return supported repetition modes.
 *
 * @return	array<string,string>	Option labels by code
 */
function powerplantpvReportTemplateRepeatModes()
{
	return array(
		'once' => 'PowerPlantPVRepeatOnce',
		'once_per_powerplant' => 'PowerPlantPVRepeatOncePerPowerPlant',
		'once_per_equipment' => 'PowerPlantPVRepeatOncePerEquipment',
		'once_per_mppt' => 'PowerPlantPVRepeatOncePerMppt',
		'once_per_pv_input' => 'PowerPlantPVRepeatOncePerPvInput',
		'user_defined_lines' => 'PowerPlantPVRepeatUserDefinedLines',
	);
}

/**
 * Return supported field types.
 *
 * @return	array<string,string>	Option labels by code
 */
function powerplantpvReportTemplateFieldTypes()
{
	return array(
		'text' => 'PowerPlantPVFieldTypeText',
		'textarea' => 'PowerPlantPVFieldTypeTextarea',
		'number' => 'PowerPlantPVFieldTypeNumber',
		'date' => 'PowerPlantPVFieldTypeDate',
		'datetime' => 'PowerPlantPVFieldTypeDatetime',
		'checkbox' => 'PowerPlantPVFieldTypeCheckbox',
		'yesno' => 'PowerPlantPVFieldTypeYesNo',
		'select' => 'PowerPlantPVFieldTypeSelect',
		'multiselect' => 'PowerPlantPVFieldTypeMultiselect',
		'conformity_so_valid_obs' => 'PowerPlantPVFieldTypeConformitySoValidObs',
		'file' => 'PowerPlantPVFieldTypeFile',
		'signature' => 'PowerPlantPVFieldTypeSignature',
		'dynamic_table' => 'PowerPlantPVFieldTypeDynamicTable',
		'computed' => 'PowerPlantPVFieldTypeComputed',
	);
}

/**
 * Return active flag options.
 *
 * @return	array<int,string>	Options
 */
function powerplantpvReportTemplateActiveOptions()
{
	return array(1 => 'Enabled', 0 => 'Disabled');
}

/**
 * Return translated options for a selectarray.
 *
 * @param	array<string|int,string>	$options	Options with translation keys as values
 * @return	array<string|int,string>				Translated options
 */
function powerplantpvReportTemplateTranslateOptions($options)
{
	global $langs;

	$translated = array();
	foreach ($options as $key => $label) {
		$translated[$key] = ($label === '' ? '' : $langs->trans($label));
	}

	return $translated;
}

/**
 * Validate a technical code.
 *
 * @param	string	$code	Code to validate
 * @return	bool			True when valid
 */
function powerplantpvReportTemplateIsValidCode($code)
{
	return (bool) preg_match('/^[A-Za-z0-9_][A-Za-z0-9_\\-]*$/', (string) $code);
}

/**
 * Normalize a value imported from the PR1 maintenance foundation.
 *
 * @param	string	$type	Legacy field type
 * @return	string			Current field type
 */
function powerplantpvReportTemplateNormalizeFieldType($type)
{
	$map = array(
		'boolean' => 'yesno',
		'status' => 'conformity_so_valid_obs',
		'varchar' => 'text',
		'double' => 'number',
		'text' => 'textarea',
	);

	return isset($map[$type]) ? $map[$type] : $type;
}

/**
 * Normalize a PR1 section scope.
 *
 * @param	string	$scope	Legacy scope
 * @return	string			Current scope
 */
function powerplantpvReportTemplateNormalizeScopeType($scope)
{
	$map = array(
		'dc_measure' => 'pv_input',
		'roof' => 'roof_area',
		'thermography' => 'free_line',
	);

	return isset($map[$scope]) ? $map[$scope] : $scope;
}

/**
 * Normalize a PR1 repetition mode.
 *
 * @param	string	$repeatMode	Legacy repeat mode
 * @return	string				Current repeat mode
 */
function powerplantpvReportTemplateNormalizeRepeatMode($repeatMode)
{
	$map = array(
		'per_powerplant' => 'once_per_powerplant',
		'per_equipment' => 'once_per_equipment',
		'dynamic_rows' => 'user_defined_lines',
	);

	return isset($map[$repeatMode]) ? $map[$repeatMode] : $repeatMode;
}

/**
 * Normalize a PR1 equipment type.
 *
 * @param	string	$equipmentType	Legacy equipment type
 * @return	string					Current equipment type
 */
function powerplantpvReportTemplateNormalizeEquipmentType($equipmentType)
{
	$map = array(
		'panel' => 'PANEL',
		'inverter' => 'INVERTER',
		'electrical_box' => 'DC_BOX',
		'roof' => 'ROOF_AREA',
		'thermography' => 'THERMOGRAPHY_AREA',
	);

	return isset($map[$equipmentType]) ? $map[$equipmentType] : strtoupper((string) $equipmentType);
}
