ALTER TABLE llx_c_powerplantpv_protection ADD UNIQUE INDEX uk_c_powerplantpv_protection_code (entity, code);
ALTER TABLE llx_c_powerplantpv_protection ADD INDEX idx_c_powerplantpv_protection_entity (entity);
ALTER TABLE llx_c_powerplantpv_protection ADD INDEX idx_c_powerplantpv_protection_active (active);
