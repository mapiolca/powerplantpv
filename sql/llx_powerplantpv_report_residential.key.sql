ALTER TABLE llx_powerplantpv_report_residential ADD INDEX idx_pppv_res_report (entity, fk_report);
ALTER TABLE llx_powerplantpv_report_residential ADD INDEX idx_pppv_res_parent (fk_parent);
