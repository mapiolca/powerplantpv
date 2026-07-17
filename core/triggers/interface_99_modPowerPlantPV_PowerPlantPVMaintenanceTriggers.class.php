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
 * \file		core/triggers/interface_99_modPowerPlantPV_PowerPlantPVMaintenanceTriggers.class.php
 * \ingroup		powerplantpv
 * \brief		Maintenance recomputation triggers for PowerPlantPV.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

dol_include_once('/powerplantpv/class/powerplantpvmaintenancerecalculator.class.php');
dol_include_once('/powerplantpv/class/powerplantpvmaintenancecontractlinker.class.php');

/**
 * Maintenance recomputation triggers for PowerPlantPV.
 */
class InterfacePowerPlantPVMaintenanceTriggers extends DolibarrTriggers
{
	/**
	 * Constructor.
	 *
	 * @param	DoliDB	$db	Database handler
	 */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->name = 'PowerPlantPVMaintenanceTriggers';
		$this->description = 'Recompute PowerPlantPV maintenance schedules after contract and intervention changes';
		$this->version = '1.0.0';
		$this->picto = 'fa-sun';
	}

	/**
	 * Trigger action.
	 *
	 * @param	string			$action	Event action code
	 * @param	CommonObject	$object	Object
	 * @param	User			$user	User
	 * @param	Translate		$langs	Language object
	 * @param	Conf			$conf	Configuration object
	 * @return	int						0 on success. Recalculation errors are logged but do not block the source mutation.
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('powerplantpv') || !getDolGlobalInt('POWERPLANTPV_MAINTENANCE_ENABLE', 1)) {
			return 0;
		}

		if ($action === 'FICHINTER_CREATE') {
			$linker = new PowerPlantPVMaintenanceContractLinker($this->db);
			$linkResult = $linker->linkInterventionToMaintenanceContract($object, $user, 'trigger_create');
			if ($linkResult < 0) {
				dol_syslog(__METHOD__.' contract auto-link failed for action='.$action.': '.$linker->error, LOG_WARNING);
			}
		}

		$recalculator = new PowerPlantPVMaintenanceRecalculator($this->db);
		$result = $recalculator->handleTrigger($action, $object, $user);
		if ($result < 0) {
			$this->error = $recalculator->error;
			$this->errors = $recalculator->errors;
			dol_syslog(__METHOD__.' action='.$action.' failed: '.$this->error, LOG_ERR);
		}

		return 0;
	}
}
