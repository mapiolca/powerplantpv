ALTER TABLE llx_powerplantpv_report_plant_completion ADD UNIQUE INDEX uk_ppv_rpc_report_plant (entity, fk_report, fk_powerplant);
ALTER TABLE llx_powerplantpv_report_plant_completion ADD INDEX idx_ppv_rpc_pending (entity, status, rowid);
