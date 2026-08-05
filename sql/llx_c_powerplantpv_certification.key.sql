ALTER TABLE llx_c_powerplantpv_certification ADD UNIQUE INDEX uk_c_powerplantpv_certification_code (entity, code);
ALTER TABLE llx_c_powerplantpv_certification ADD INDEX idx_c_powerplantpv_certification_entity (entity);
ALTER TABLE llx_c_powerplantpv_certification ADD INDEX idx_c_powerplantpv_certification_active (active);
