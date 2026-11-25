ALTER TABLE llx_powerplantpv_report_evidence_link ADD UNIQUE INDEX uk_ppv_ev_link_hash (token_hash);
ALTER TABLE llx_powerplantpv_report_evidence_link ADD INDEX idx_ppv_ev_link_file (entity, fk_report_file, expires_at);
