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
 * \file        core/triggers/interface_99_modPowerPlantPV_PowerPlantPVTriggers.class.php
 * \ingroup     powerplantpv
 * \brief       Triggers for PowerPlantPV.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * Triggers for PowerPlantPV.
 */
class InterfacePowerPlantPVTriggers extends DolibarrTriggers
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

		$result = $this->recalculateCommercialDocumentPeakPower($action, $object);
		if ($result < 0) {
			return -1;
		}

		$result = $this->recalculateCommercialDocumentPeakPowerForProduct($action, $object);
		if ($result < 0) {
			return -1;
		}

		if ($action == 'TICKET_CREATE') {
			return $this->linkTicketToPowerPlant($object, $user);
		}
		if ($action == 'ACTION_CREATE' || $action == 'ACTION_MODIFY') {
			$this->normalizeAgendaPowerPlantLink($object);
		}

		return 0;
	}

	/**
	 * Recalculate stored peak power for supported commercial documents.
	 *
	 * @param	string			$action	Event action code
	 * @param	CommonObject	$object	Object
	 * @return	int						0 on success or ignored action, <0 on error
	 */
	private function recalculateCommercialDocumentPeakPower($action, $object)
	{
		$lineactions = array(
			'LINEPROPAL_INSERT' => array('elementtype' => 'propal', 'parentfield' => 'fk_propal'),
			'LINEPROPAL_MODIFY' => array('elementtype' => 'propal', 'parentfield' => 'fk_propal'),
			'LINEPROPAL_UPDATE' => array('elementtype' => 'propal', 'parentfield' => 'fk_propal'),
			'LINEPROPAL_DELETE' => array('elementtype' => 'propal', 'parentfield' => 'fk_propal'),
			'LINEORDER_INSERT' => array('elementtype' => 'commande', 'parentfield' => 'fk_commande'),
			'LINEORDER_MODIFY' => array('elementtype' => 'commande', 'parentfield' => 'fk_commande'),
			'LINEORDER_UPDATE' => array('elementtype' => 'commande', 'parentfield' => 'fk_commande'),
			'LINEORDER_DELETE' => array('elementtype' => 'commande', 'parentfield' => 'fk_commande'),
			'LINEBILL_INSERT' => array('elementtype' => 'facture', 'parentfield' => 'fk_facture'),
			'LINEBILL_MODIFY' => array('elementtype' => 'facture', 'parentfield' => 'fk_facture'),
			'LINEBILL_UPDATE' => array('elementtype' => 'facture', 'parentfield' => 'fk_facture'),
			'LINEBILL_DELETE' => array('elementtype' => 'facture', 'parentfield' => 'fk_facture'),
		);

		$documentactions = array(
			'PROPAL_CREATE' => 'propal',
			'PROPAL_MODIFY' => 'propal',
			'PROPAL_VALIDATE' => 'propal',
			'PROPAL_REOPEN' => 'propal',
			'PROPAL_CLOSE_REFUSED' => 'propal',
			'PROPAL_CLOSE_SIGNED' => 'propal',
			'PROPAL_CLASSIFY_BILLED' => 'propal',
			'PROPAL_CANCEL' => 'propal',
			'ORDER_CREATE' => 'commande',
			'ORDER_MODIFY' => 'commande',
			'ORDER_VALIDATE' => 'commande',
			'ORDER_UNVALIDATE' => 'commande',
			'ORDER_REOPEN' => 'commande',
			'ORDER_CLOSE' => 'commande',
			'ORDER_CANCEL' => 'commande',
			'ORDER_CLASSIFY_BILLED' => 'commande',
			'ORDER_CLASSIFY_UNBILLED' => 'commande',
			'BILL_CREATE' => 'facture',
			'BILL_MODIFY' => 'facture',
			'BILL_PAYED' => 'facture',
			'BILL_UNPAYED' => 'facture',
			'BILL_CANCEL' => 'facture',
			'BILL_VALIDATE' => 'facture',
			'BILL_UNVALIDATE' => 'facture',
		);

		$elementtype = '';
		$documentid = 0;
		$excludelineid = 0;

		if (!empty($lineactions[$action])) {
			$elementtype = $lineactions[$action]['elementtype'];
			$documentid = $this->getObjectIntProperty($object, $lineactions[$action]['parentfield']);
			if ($documentid <= 0) {
				dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
				$documentid = $this->getDocumentIdFromLine($elementtype, $object, $lineactions[$action]['parentfield']);
			}
			if (substr($action, -7) == '_DELETE') {
				dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
				$excludelineid = powerplantpvGetLineId($object);
			}
		} elseif (!empty($documentactions[$action])) {
			$elementtype = $documentactions[$action];
			$documentid = $this->getObjectId($object);
		}

		if (empty($elementtype) || $documentid <= 0) {
			return 0;
		}

		dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
		$result = powerplantpvRecalculateCommercialDocumentPeakPower($elementtype, $documentid, $excludelineid);
		if ($result < 0) {
			$this->errors[] = 'ErrorFailedToRecalculatePeakPower';
			dol_syslog(__METHOD__.' failed for action='.$action.' elementtype='.$elementtype.' id='.$documentid, LOG_ERR);
			return -1;
		}

		return 0;
	}

	/**
	 * Recalculate stored peak power for documents using a modified product.
	 *
	 * @param	string		$action	Event action code
	 * @param	CommonObject	$object	Object
	 * @return	int					0 on success or ignored action, <0 on error
	 */
	private function recalculateCommercialDocumentPeakPowerForProduct($action, $object)
	{
		if ($action != 'PRODUCT_MODIFY') {
			return 0;
		}

		$productid = $this->getObjectId($object);
		if ($productid <= 0) {
			return 0;
		}

		dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
		$result = powerplantpvRecalculateCommercialDocumentPeakPowerForProduct($productid);
		if ($result < 0) {
			$this->errors[] = 'ErrorFailedToRecalculatePeakPower';
			dol_syslog(__METHOD__.' failed for product id='.$productid, LOG_ERR);
			return -1;
		}

		return 0;
	}

	/**
	 * Return the commercial document id for a line object.
	 *
	 * @param	string	$elementtype	Element type
	 * @param	object	$object		Line object
	 * @param	string	$parentfield	Line parent field
	 * @return	int					Document id
	 */
	private function getDocumentIdFromLine($elementtype, $object, $parentfield)
	{
		if (!is_object($object) || !function_exists('powerplantpvGetCommercialDocumentPeakPowerConfig')) {
			return 0;
		}

		$lineid = powerplantpvGetLineId($object);
		if ($lineid <= 0) {
			return 0;
		}

		$config = powerplantpvGetCommercialDocumentPeakPowerConfig($elementtype);
		if (empty($config)) {
			return 0;
		}

		$sql = "SELECT l.".$parentfield." as fk_parent";
		$sql .= " FROM ".$this->db->prefix().$config['line_table']." as l";
		$sql .= " INNER JOIN ".$this->db->prefix().$config['parent_table']." as d ON d.".$config['parent_pk']." = l.".$parentfield;
		$sql .= " WHERE l.rowid = ".$lineid;
		$sql .= " AND d.entity IN (".getEntity($config['elementtype']).")";
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' failed to read parent line id='.$lineid.' : '.$this->db->lasterror(), LOG_WARNING);
			return 0;
		}

		$documentid = 0;
		if ($obj = $this->db->fetch_object($resql)) {
			$documentid = (int) $obj->fk_parent;
		}
		$this->db->free($resql);

		return $documentid;
	}

	/**
	 * Return an integer object property.
	 *
	 * @param	object	$object		Object
	 * @param	string	$property	Property name
	 * @return	int					Property value
	 */
	private function getObjectIntProperty($object, $property)
	{
		if (is_object($object) && isset($object->$property)) {
			return (int) $object->$property;
		}

		return 0;
	}

	/**
	 * Return the object id from native Dolibarr properties.
	 *
	 * @param	object	$object	Object
	 * @return	int				Object id
	 */
	private function getObjectId($object)
	{
		if (!is_object($object)) {
			return 0;
		}
		if (!empty($object->id)) {
			return (int) $object->id;
		}
		if (!empty($object->rowid)) {
			return (int) $object->rowid;
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

	/**
	 * Normalize Agenda event links to the canonical PowerPlant element type.
	 *
	 * @param	CommonObject	$actioncomm	Agenda action object
	 * @return	int						0 on success or when no normalization is needed
	 */
	private function normalizeAgendaPowerPlantLink($actioncomm)
	{
		if (empty($actioncomm->id)) {
			return 0;
		}

		$fkElement = 0;
		if (!empty($actioncomm->fk_element)) {
			$fkElement = (int) $actioncomm->fk_element;
		} elseif (!empty($actioncomm->elementid)) {
			$fkElement = (int) $actioncomm->elementid;
		}
		if ($fkElement <= 0 || empty($actioncomm->elementtype)) {
			return 0;
		}

		$canonicalType = 'powerplant@powerplantpv';
		$compatibleTypes = array('powerplant', $canonicalType, 'powerplantpv_powerplant');
		if (!in_array($actioncomm->elementtype, $compatibleTypes)) {
			return 0;
		}
		if ($actioncomm->elementtype == $canonicalType) {
			return 0;
		}

		dol_include_once('/powerplantpv/class/powerplant.class.php');
		$powerplant = new PowerPlant($this->db);
		if ($powerplant->fetch($fkElement) <= 0) {
			return 0;
		}

		$sql = "UPDATE ".$this->db->prefix()."actioncomm";
		$sql .= " SET elementtype = '".$this->db->escape($canonicalType)."'";
		$sql .= " WHERE id = ".((int) $actioncomm->id);
		$sql .= " AND fk_element = ".((int) $fkElement);
		$sql .= " AND elementtype = '".$this->db->escape($actioncomm->elementtype)."'";

		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' failed to normalize actioncomm id='.$actioncomm->id.': '.$this->db->lasterror(), LOG_WARNING);
			return 0;
		}

		$actioncomm->elementtype = $canonicalType;
		return 0;
	}
}
