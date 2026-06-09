<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/powerplantpv/core/modules/attestation/doc/pdf_attestation_base.modules.php');

/**
 * Installer under 100 kWc attestation PDF.
 */
class pdf_attestation_installateur_inf100kwc extends pdf_attestation_base
{
	public function __construct($db)
	{
		parent::__construct($db);
		$this->name = 'attestation_installateur_inf100kwc';
		$this->titleKey = 'AttestationTypeInstallateurInf100kwc';
	}
}
