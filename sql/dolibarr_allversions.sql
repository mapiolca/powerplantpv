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
	entity integer NOT NULL DEFAULT 1
) ENGINE=innodb;
ALTER TABLE llx_powerplantpv_powerplantcomp ADD INDEX IF NOT EXISTS idx_powerplantpv_powerplantcomp_rowid (rowid);
ALTER TABLE llx_powerplantpv_powerplantcomp ADD INDEX IF NOT EXISTS idx_powerplantpv_powerplantcomp_fk_powerplant (fk_powerplant);
ALTER TABLE llx_powerplantpv_powerplantcomp ADD INDEX IF NOT EXISTS idx_powerplantpv_powerplantcomp_fk_product (fk_product);
ALTER TABLE llx_powerplantpv_powerplantcomp ADD INDEX IF NOT EXISTS idx_powerplantpv_powerplantcomp_entity (entity);

CREATE TABLE IF NOT EXISTS llx_c_pv_product_type (
	rowid integer AUTO_INCREMENT,
	code varchar(32) NOT NULL,
	label varchar(255) NOT NULL,
	active tinyint DEFAULT 1,
	entity integer DEFAULT 1,
	position integer DEFAULT 0,
	PRIMARY KEY(rowid),
	UNIQUE(code, entity)
) ENGINE=innodb;

INSERT INTO llx_c_pv_product_type (code, label, active, entity, position)
SELECT 'MODULE_PV', 'MODULE_PV', 1, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM llx_c_pv_product_type WHERE code = 'MODULE_PV' AND entity = 1);
INSERT INTO llx_c_pv_product_type (code, label, active, entity, position)
SELECT 'ONDULEUR', 'ONDULEUR', 1, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM llx_c_pv_product_type WHERE code = 'ONDULEUR' AND entity = 1);
INSERT INTO llx_c_pv_product_type (code, label, active, entity, position)
SELECT 'OPTIMISEUR', 'OPTIMISEUR', 1, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM llx_c_pv_product_type WHERE code = 'OPTIMISEUR' AND entity = 1);
INSERT INTO llx_c_pv_product_type (code, label, active, entity, position)
SELECT 'ARMOIRE_AC', 'ARMOIRE_AC', 1, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM llx_c_pv_product_type WHERE code = 'ARMOIRE_AC' AND entity = 1);
INSERT INTO llx_c_pv_product_type (code, label, active, entity, position)
SELECT 'COFFRET_DC', 'COFFRET_DC', 1, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM llx_c_pv_product_type WHERE code = 'COFFRET_DC' AND entity = 1);
INSERT INTO llx_c_pv_product_type (code, label, active, entity, position)
SELECT 'CABLE_DC', 'CABLE_DC', 1, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM llx_c_pv_product_type WHERE code = 'CABLE_DC' AND entity = 1);
INSERT INTO llx_c_pv_product_type (code, label, active, entity, position)
SELECT 'CABLE_AC', 'CABLE_AC', 1, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM llx_c_pv_product_type WHERE code = 'CABLE_AC' AND entity = 1);
INSERT INTO llx_c_pv_product_type (code, label, active, entity, position)
SELECT 'STRUCTURE', 'STRUCTURE', 1, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM llx_c_pv_product_type WHERE code = 'STRUCTURE' AND entity = 1);
INSERT INTO llx_c_pv_product_type (code, label, active, entity, position)
SELECT 'PROTECTION', 'PROTECTION', 1, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM llx_c_pv_product_type WHERE code = 'PROTECTION' AND entity = 1);
INSERT INTO llx_c_pv_product_type (code, label, active, entity, position)
SELECT 'COMPTAGE', 'COMPTAGE', 1, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM llx_c_pv_product_type WHERE code = 'COMPTAGE' AND entity = 1);
INSERT INTO llx_c_pv_product_type (code, label, active, entity, position)
SELECT 'DIVERS', 'DIVERS', 1, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM llx_c_pv_product_type WHERE code = 'DIVERS' AND entity = 1);
