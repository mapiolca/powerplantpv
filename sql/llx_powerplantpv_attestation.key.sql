-- Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>

ALTER TABLE llx_powerplantpv_attestation ADD UNIQUE INDEX uk_powerplantpv_attestation_ref_entity (ref, entity);
ALTER TABLE llx_powerplantpv_attestation ADD INDEX idx_powerplantpv_attestation_entity (entity);
ALTER TABLE llx_powerplantpv_attestation ADD INDEX idx_powerplantpv_attestation_fk_powerplant (fk_powerplant);
ALTER TABLE llx_powerplantpv_attestation ADD INDEX idx_powerplantpv_attestation_fk_soc (fk_soc);
ALTER TABLE llx_powerplantpv_attestation ADD INDEX idx_powerplantpv_attestation_fk_project (fk_project);
ALTER TABLE llx_powerplantpv_attestation ADD INDEX idx_powerplantpv_attestation_type (type_code);
ALTER TABLE llx_powerplantpv_attestation ADD INDEX idx_powerplantpv_attestation_status (status);
ALTER TABLE llx_powerplantpv_attestation ADD INDEX idx_powerplantpv_attestation_date_signature (date_signature);
ALTER TABLE llx_powerplantpv_attestation ADD INDEX idx_powerplantpv_attestation_date_attestation (date_attestation);
ALTER TABLE llx_powerplantpv_attestation ADD INDEX idx_powerplantpv_attestation_date_valid (date_valid);
ALTER TABLE llx_powerplantpv_attestation ADD INDEX idx_powerplantpv_attestation_fk_user_valid (fk_user_valid);
