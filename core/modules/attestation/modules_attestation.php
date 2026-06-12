<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		core/modules/attestation/modules_attestation.php
 * \ingroup		powerplantpv
 * \brief		Parent classes for attestation documents and numbering.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commondocgenerator.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/commonnumrefgenerator.class.php';

/**
 * Parent class for attestation PDF models.
 */
abstract class ModelePDFAttestation extends CommonDocGenerator
{
	/**
	 * Return list of active generation modules.
	 *
	 * @param	DoliDB	$db					Database handler
	 * @param	int		$maxfilenamelength	Max filename length
	 * @return	string[]|int<-1,0>			Models
	 */
	public static function liste_modeles($db, $maxfilenamelength = 0)
	{
		include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

		return getListOfModels($db, 'attestation', $maxfilenamelength);
	}

	/**
	 * Write file.
	 *
	 * @param	PowerPlantPVAttestation	$object				Object
	 * @param	Translate				$outputlangs		Output langs
	 * @param	string					$srctemplatepath	Source template
	 * @param	int<0,1>				$hidedetails		Hide details
	 * @param	int<0,1>				$hidedesc			Hide description
	 * @param	int<0,1>				$hideref			Hide ref
	 * @return	int<-1,1>									1 if OK
	 */
	abstract public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0);
}

/**
 * Parent class for attestation numbering modules.
 */
abstract class ModeleNumRefAttestation extends CommonNumRefGenerator
{
	/**
	 * Check if entity column exists.
	 *
	 * @param	DoliDB	$db	Database handler
	 * @return	bool		True if column exists
	 */
	protected function hasEntityField($db)
	{
		static $cache = null;
		if ($cache === null) {
			$sql = "SHOW COLUMNS FROM ".$db->prefix()."powerplantpv_attestation LIKE 'entity'";
			$resql = $db->query($sql);
			$cache = ($resql && $db->num_rows($resql) > 0);
		}

		return (bool) $cache;
	}

	/**
	 * Return entities where references must be unique.
	 *
	 * @param	CommonObject|null	$object	Object
	 * @return	string						Comma-separated entity ids
	 */
	public static function getAttestationReferenceEntityList($object = null)
	{
		global $conf;

		$entities = array();
		foreach (array(getEntity('attestation'), getEntity('attestationnumber', 1, $object)) as $scope) {
			foreach (explode(',', (string) $scope) as $entity) {
				$entity = trim($entity);
				if ($entity !== '' && preg_match('/^\d+$/', $entity)) {
					$entities[(int) $entity] = (int) $entity;
				}
			}
		}
		if (empty($entities)) {
			$entities[(int) $conf->entity] = (int) $conf->entity;
		}
		ksort($entities, SORT_NUMERIC);

		return implode(',', $entities);
	}

	/**
	 * Return an example.
	 *
	 * @return	string	Example
	 */
	abstract public function getExample();

	/**
	 * Return next value.
	 *
	 * @param	PowerPlantPVAttestation	$object	Object
	 * @return	string|int<-1,0>					Next value
	 */
	abstract public function getNextValue($object);
}
