<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/** Runs after JPSUN's 999 trigger, which may create/link a project during signing. */
class InterfaceProjectSignedTotals extends DolibarrTriggers
{
	public function __construct($db)
	{
		parent::__construct($db);
		$this->name = 'ProjectSignedTotals';
		$this->description = 'Refresh project signed technical totals after native proposal mutations';
		$this->version = '1.5.0';
		$this->picto = 'fa-sun';
	}

	/**
	 * @param string $action Native event code
	 * @param CommonObject $object Native trigger object
	 * @param User $user Native operation actor
	 * @param Translate $langs Translator
	 * @param Conf $conf Configuration
	 * @return int 0 on success, -1 on failure in the caller's transaction
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('powerplantpv') || !isModEnabled('project')) {
			return 0;
		}
		$isline = in_array($action, array('LINEPROPAL_INSERT', 'LINEPROPAL_UPDATE', 'LINEPROPAL_MODIFY', 'LINEPROPAL_DELETE'), true);
		$isproposal = $isline || in_array($action, array('PROPAL_CREATE', 'PROPAL_MODIFY', 'PROPAL_VALIDATE',
			'PROPAL_REOPEN', 'PROPAL_CLOSE_SIGNED', 'PROPAL_CLOSE_REFUSED', 'PROPAL_CLASSIFY_BILLED', 'PROPAL_CANCEL', 'PROPAL_DELETE'), true);
		$isproduct = in_array($action, array('PRODUCT_MODIFY', 'PRODUCT_SUBPRODUCT_ADD', 'PRODUCT_SUBPRODUCT_UPDATE', 'PRODUCT_SUBPRODUCT_DELETE'), true);
		$islink = in_array($action, array('OBJECT_LINK_INSERT', 'OBJECT_LINK_DELETE', 'OBJECT_LINK_MODIFY'), true)
			&& in_array($object->element, array('project', 'propal'), true);
		$isproject = in_array($action, array('PROJECT_CREATE', 'PROJECT_MODIFY'), true)
			&& ($object->context['trigger_reason'] ?? '') !== 'project_powerplant_links';
		if (!$isproposal && !$isproduct && !$islink && !$isproject) {
			return 0;
		}
		$projectids = array();
		if ($isproposal && $action !== 'PROPAL_MODIFY') {
			$proposalid = $isline ? (int) ($object->fk_propal ?? 0) : (int) $object->id;
			$sql = 'SELECT fk_projet, fk_statut FROM '.MAIN_DB_PREFIX.'propal WHERE rowid = '.$proposalid;
			$sql .= ' AND entity IN ('.$this->db->sanitize(getEntity('propal')).')';
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return -1;
			}
			$row = $this->db->fetch_object($resql);
			$this->db->free($resql);
			if (!$row || (int) $row->fk_projet <= 0 || ($isline && !in_array((int) $row->fk_statut, array(2,4), true))) {
				return 0;
			}
			$projectids[] = (int) $row->fk_projet;
		} elseif ($isproject || ($islink && $object->element === 'project')) {
			$projectids[] = (int) $object->id;
		}
		dol_include_once('/powerplantpv/class/powerplantpvprojectsignedtotals.class.php');
		$service = new PowerPlantPVProjectSignedTotals($this->db);
		$result = $service->refresh($user, $action === 'PROPAL_DELETE' ? (int) $object->id : 0, $projectids);
		if ($result < 0) {
			$langs->load('powerplantpv@powerplantpv');
			$this->error = $langs->trans('PowerPlantPVProjectTotalsFailed');
			$this->errors[] = $this->error;
			dol_syslog(__METHOD__.' '.$service->error, LOG_ERR);
			return -1;
		}
		return 0;
	}
}
