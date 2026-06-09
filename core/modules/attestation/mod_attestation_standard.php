<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		core/modules/attestation/mod_attestation_standard.php
 * \ingroup		powerplantpv
 * \brief		Standard attestation numbering rule.
 */

dol_include_once('/powerplantpv/core/modules/attestation/modules_attestation.php');

/**
 * Standard attestation numbering.
 */
class mod_attestation_standard extends ModeleNumRefAttestation
{
	public $version = 'dolibarr';
	public $prefix = 'ATT';
	public $error = '';
	public $name = 'standard';

	/**
	 * Return description.
	 *
	 * @param	Translate	$langs	Translation handler
	 * @return	string				Description
	 */
	public function info($langs)
	{
		return $langs->trans('SimpleNumRefModelDesc', $this->prefix);
	}

	/**
	 * Return example.
	 *
	 * @return	string	Example
	 */
	public function getExample()
	{
		return $this->prefix.'2606-0001';
	}

	/**
	 * Check if numbering can be activated.
	 *
	 * @param	CommonObject	$object	Object
	 * @return	bool					True if OK
	 */
	public function canBeActivated($object)
	{
		return true;
	}

	/**
	 * Return next value.
	 *
	 * @param	PowerPlantPVAttestation	$object	Object
	 * @return	string|int<-1,0>					Next value
	 */
	public function getNextValue($object)
	{
		global $db;

		$posindice = strlen($this->prefix) + 6;
		$sql = "SELECT MAX(CAST(SUBSTRING(t.ref FROM ".$posindice.") AS SIGNED)) as max";
		$sql .= " FROM ".$db->prefix()."powerplantpv_attestation as t";
		$sql .= " WHERE t.ref LIKE '".$db->escape($this->prefix)."____-%'";
		if ($object->ismultientitymanaged == 1 && $this->hasEntityField($db)) {
			$sql .= " AND t.entity IN (".self::getAttestationReferenceEntityList($object).")";
		}

		$resql = $db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' '.$db->lasterror(), LOG_ERR);
			return -1;
		}

		$obj = $db->fetch_object($resql);
		$max = ($obj ? (int) $obj->max : 0);
		$db->free($resql);

		$date = !empty($object->date_creation) ? $object->date_creation : dol_now();
		$yymm = dol_print_date($date, '%y%m');
		$num = ($max >= 9999 ? (string) ($max + 1) : sprintf('%04u', $max + 1));

		return $this->prefix.$yymm.'-'.$num;
	}
}
