--
-- Script run when an upgrade of Dolibarr is done. Whatever is the Dolibarr version.
--

ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN commissioning_date date;
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN prm_pdl_number varchar(128);
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN address varchar(255);
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN zip varchar(25);
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN town varchar(255);
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN fk_country integer;
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN installed_power double;
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN connection_contract_power double;
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN connection_type varchar(128);
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN enedis_commissioning_date date;
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN connection_request_number varchar(128);
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN t0_obtention_date date;
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN buyback_contract_number varchar(128);
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN buyback_tariff double;
ALTER TABLE llx_powerplantpv_powerplant ADD INDEX idx_powerplantpv_powerplant_fk_country (fk_country);
