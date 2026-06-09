<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/powerplantpv/core/modules/attestation/doc/pdf_attestation_base.modules.php');

/**
 * Max frequency attestation PDF.
 */
class pdf_attestation_reglage_max_freq extends pdf_attestation_base
{
	public function __construct($db)
	{
		parent::__construct($db);
		$this->name = 'attestation_reglage_max_freq';
		$this->titleKey = 'AttestationTypeReglageMaxFreq515Hz';
	}
}
