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
 * \file        core/triggers/interface_modPowerPlantPV_PowerPlantPVTriggers.class.php
 * \ingroup     powerplantpv
 * \brief       Triggers for PowerPlantPV.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * Triggers for PowerPlantPV.
 */
class InterfaceModPowerPlantPVPowerPlantPVTriggers extends DolibarrTriggers
{
	/**
	 * Constructor.
	 *
	 * @param	DoliDB	$db	Database handler
	 */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->name = 'PowerPlantPVTriggers';
		$this->description = 'Triggers for PowerPlantPV module';
		$this->version = '1.0.0';
		$this->picto = 'fa-sun';
	}

	/**
	 * Trigger action.
	 *
	 * @param	string		$action		Event action code
	 * @param	CommonObject	$object		Object
	 * @param	User		$user		User
	 * @param	Translate	$langs		Language object
	 * @param	Conf		$conf		Configuration object
	 * @return	int						0 on success, <0 on error
	 */
	public function runTrigger($action, &$object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('powerplantpv')) {
			return 0;
		}

		if ($action == 'TICKET_CREATE') {
			return $this->linkTicketToPowerPlant($object, $user);
		}

		return 0;
	}

	/**
	 * Link a created ticket to the selected power plant.
	 *
	 * @param	CommonObject	$ticket	Ticket object
	 * @param	User		$user	User
	 * @return	int					0 on success, <0 on error
	 */
	private function linkTicketToPowerPlant($ticket, User $user)
	{
		$powerplantid = 0;
		if (!empty($ticket->array_options['options_powerplantpv_powerplant'])) {
			$powerplantid = (int) $ticket->array_options['options_powerplantpv_powerplant'];
		}
		if ($powerplantid <= 0 && function_exists('GETPOSTINT')) {
			$powerplantid = GETPOSTINT('options_powerplantpv_powerplant');
		}
		if ($powerplantid <= 0 || empty($ticket->id)) {
			return 0;
		}

		dol_include_once('/powerplantpv/class/powerplant.class.php');
		$powerplant = new PowerPlant($this->db);
		$result = $powerplant->fetch($powerplantid);
		if ($result <= 0) {
			return 0;
		}

		$targettype = $powerplant->getElementType();
		$sql = "SELECT ee.rowid";
		$sql .= " FROM ".$this->db->prefix()."element_element as ee";
		$sql .= " WHERE ee.fk_source = ".((int) $ticket->id);
		$sql .= " AND ee.sourcetype = 'ticket'";
		$sql .= " AND ee.fk_target = ".((int) $powerplant->id);
		$sql .= " AND ee.targettype = '".$this->db->escape($targettype)."'";

		$resql = $this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql) > 0) {
				$this->db->free($resql);
				return 0;
			}
			$this->db->free($resql);
		} else {
			$this->errors[] = $this->db->lasterror();
			return -1;
		}

		$result = $powerplant->add_object_linked('ticket', (int) $ticket->id, $user, 0);
		if ($result <= 0) {
			$this->errors[] = $powerplant->error;
			return -1;
		}

		return 0;
	}
}
