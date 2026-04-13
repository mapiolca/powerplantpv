--
-- Script run when an upgrade of Dolibarr is done. Whatever is the Dolibarr version.
--

ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS commissioning_date date;
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS prm_pdl_number varchar(128);
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS address varchar(255);
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS zip varchar(25);
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS town varchar(255);
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS fk_country integer;
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS installed_power double;
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS connection_contract_power double;
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS connection_type varchar(128);
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS enedis_commissioning_date date;
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS connection_request_number varchar(128);
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS t0_obtention_date date;
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS buyback_contract_number varchar(128);
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS buyback_tariff double;
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS entity integer NOT NULL DEFAULT 1;
ALTER TABLE llx_powerplantpv_powerplant ADD INDEX IF NOT EXISTS idx_powerplantpv_powerplant_fk_country (fk_country);
ALTER TABLE llx_powerplantpv_powerplant ADD INDEX IF NOT EXISTS idx_powerplantpv_powerplant_entity (entity);

CREATE TABLE IF NOT EXISTS llx_powerplantpv_product_pvpanel(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	fk_product integer NOT NULL,
	entity integer NOT NULL DEFAULT 1,
	pmax double,
	power_tolerance double,
	module_efficiency double,
	vmp double,
	imp double,
	voc double,
	isc double,
	front_glass_thickness double,
	back_glass_thickness double,
	cable_section double,
	cable_length integer,
	operating_temperature double,
	max_system_voltage double,
	max_series_fuse double,
	snow_load double,
	wind_load double,
	noct double,
	temp_coeff_pmax double,
	temp_coeff_voc double,
	temp_coeff_isc double,
	first_year_degradation double,
	annual_degradation double,
	product_warranty integer,
	power_warranty integer,
	modules_per_box integer,
	modules_per_container40 integer
) ENGINE=innodb;
ALTER TABLE llx_powerplantpv_product_pvpanel ADD INDEX IF NOT EXISTS idx_powerplantpv_product_pvpanel_rowid (rowid);
ALTER TABLE llx_powerplantpv_product_pvpanel ADD INDEX IF NOT EXISTS idx_powerplantpv_product_pvpanel_fk_product (fk_product);
ALTER TABLE llx_powerplantpv_product_pvpanel ADD COLUMN IF NOT EXISTS entity integer NOT NULL DEFAULT 1;
ALTER TABLE llx_powerplantpv_product_pvpanel ADD INDEX IF NOT EXISTS idx_powerplantpv_product_pvpanel_entity (entity);

CREATE TABLE IF NOT EXISTS llx_powerplantpv_powerplantcomp(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	fk_powerplant integer NOT NULL,
	fk_product integer NOT NULL,
	nature_code integer NOT NULL,
	qty double NOT NULL,
	serial_number varchar(128),
	commissioning_date date,
	entity integer NOT NULL DEFAULT 1
) ENGINE=innodb;
ALTER TABLE llx_powerplantpv_powerplantcomp ADD INDEX IF NOT EXISTS idx_powerplantpv_powerplantcomp_rowid (rowid);
ALTER TABLE llx_powerplantpv_powerplantcomp ADD INDEX IF NOT EXISTS idx_powerplantpv_powerplantcomp_fk_powerplant (fk_powerplant);
ALTER TABLE llx_powerplantpv_powerplantcomp ADD INDEX IF NOT EXISTS idx_powerplantpv_powerplantcomp_fk_product (fk_product);
ALTER TABLE llx_powerplantpv_powerplantcomp ADD INDEX IF NOT EXISTS idx_powerplantpv_powerplantcomp_entity (entity);
ALTER TABLE llx_powerplantpv_powerplantcomp ADD COLUMN IF NOT EXISTS serial_number varchar(128);
ALTER TABLE llx_powerplantpv_powerplantcomp ADD COLUMN IF NOT EXISTS commissioning_date date;

INSERT INTO llx_c_product_nature (code, label, active)
SELECT '50', 'ProductNaturePVModules', 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_product_nature WHERE code = '50');
UPDATE llx_c_product_nature SET label = 'ProductNaturePVModules', active = 1 WHERE code = '50';

INSERT INTO llx_c_product_nature (code, label, active)
SELECT '51', 'ProductNaturePVInverters', 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_product_nature WHERE code = '51');
UPDATE llx_c_product_nature SET label = 'ProductNaturePVInverters', active = 1 WHERE code = '51';

INSERT INTO llx_c_product_nature (code, label, active)
SELECT '52', 'ProductNaturePVIntegration', 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_product_nature WHERE code = '52');
UPDATE llx_c_product_nature SET label = 'ProductNaturePVIntegration', active = 1 WHERE code = '52';

INSERT INTO llx_c_product_nature (code, label, active)
SELECT '53', 'ProductNaturePVMonitoring', 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_product_nature WHERE code = '53');
UPDATE llx_c_product_nature SET label = 'ProductNaturePVMonitoring', active = 1 WHERE code = '53';

INSERT INTO llx_c_product_nature (code, label, active)
SELECT '54', 'ProductNaturePVACBox', 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_product_nature WHERE code = '54');
UPDATE llx_c_product_nature SET label = 'ProductNaturePVACBox', active = 1 WHERE code = '54';

INSERT INTO llx_c_product_nature (code, label, active)
SELECT '55', 'ProductNaturePVDCBox', 1
WHERE NOT EXISTS (SELECT 1 FROM llx_c_product_nature WHERE code = '55');
UPDATE llx_c_product_nature SET label = 'ProductNaturePVDCBox', active = 1 WHERE code = '55';

CREATE TABLE IF NOT EXISTS llx_c_powerplantpv_categorypv(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	code varchar(64) NOT NULL,
	label varchar(255) NOT NULL,
	active smallint NOT NULL DEFAULT 1
) ENGINE=innodb;
ALTER TABLE llx_c_powerplantpv_categorypv ADD INDEX IF NOT EXISTS idx_c_powerplantpv_categorypv_rowid (rowid);
ALTER TABLE llx_c_powerplantpv_categorypv ADD UNIQUE INDEX IF NOT EXISTS uk_c_powerplantpv_categorypv_code (code);
ALTER TABLE llx_c_powerplantpv_categorypv ADD INDEX IF NOT EXISTS idx_c_powerplantpv_categorypv_active (active);
