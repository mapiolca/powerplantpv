<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/powerplantpv/core/modules/powerplantpv/doc/pdf_attestation_base.modules.php');

/**
 * Static inverter bridage attestation PDF.
 */
class pdf_attestation_bridage_statique extends pdf_attestation_base
{
	public function __construct($db)
	{
		parent::__construct($db);
		$this->name = 'attestation_bridage_statique';
		$this->titleKey = 'AttestationTypeBridageStatiqueOnduleur';
		$this->validationWarningKey = 'AttestationStaticBridageTemplateToValidate';
	}
}
