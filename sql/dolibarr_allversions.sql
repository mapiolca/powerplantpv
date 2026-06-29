--
-- Script run when an upgrade of Dolibarr is done. Whatever is the Dolibarr version.
--

ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS commissioning_date date;
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS prm_pdl_number varchar(128);
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS address varchar(255);
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS zip varchar(25);
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS town varchar(255);
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS fk_country integer;
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS access_instructions text;
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS installed_power double;
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS connection_contract_power double;
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS connection_type varchar(128);
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS enedis_commissioning_date date;
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS connection_request_number varchar(128);
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS t0_obtention_date date;
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS buyback_contract_number varchar(128);
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS buyback_tariff double;
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS fk_soc integer;
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS fk_project integer;
ALTER TABLE llx_powerplantpv_powerplant ADD COLUMN IF NOT EXISTS entity integer NOT NULL DEFAULT 1;
ALTER TABLE llx_powerplantpv_powerplant ADD INDEX IF NOT EXISTS idx_powerplantpv_powerplant_fk_country (fk_country);
ALTER TABLE llx_powerplantpv_powerplant ADD INDEX IF NOT EXISTS idx_powerplantpv_powerplant_fk_soc (fk_soc);
ALTER TABLE llx_powerplantpv_powerplant ADD INDEX IF NOT EXISTS idx_powerplantpv_powerplant_fk_project (fk_project);
ALTER TABLE llx_powerplantpv_powerplant ADD INDEX IF NOT EXISTS idx_powerplantpv_powerplant_entity (entity);
ALTER TABLE llx_powerplantpv_powerplant DROP INDEX IF EXISTS uk_powerplantpv_powerplant_ref;
ALTER TABLE llx_powerplantpv_powerplant ADD UNIQUE INDEX IF NOT EXISTS uk_powerplantpv_powerplant_ref_entity (ref, entity);

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

CREATE TABLE IF NOT EXISTS llx_powerplantpv_product_inverter(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	fk_product integer NOT NULL,
	entity integer NOT NULL DEFAULT 1,
	pv_max_power double(24,8),
	dc_max_voltage double(24,8),
	startup_voltage double(24,8),
	mppt_voltage_min double(24,8),
	mppt_voltage_max double(24,8),
	nominal_dc_voltage double(24,8),
	ac_nominal_power double(24,8),
	ac_max_power double(24,8),
	ac_apparent_power double(24,8),
	ac_nominal_voltage varchar(128),
	grid_frequency varchar(64),
	ac_max_output_current double(24,8),
	power_factor varchar(64),
	thd varchar(64),
	max_efficiency double(6,3),
	european_efficiency double(6,3),
	dc_switch smallint,
	dc_spd varchar(128),
	ac_spd varchar(128),
	afci smallint,
	pid_recovery smallint,
	anti_islanding smallint,
	dc_reverse_polarity_protection smallint,
	insulation_monitoring smallint,
	residual_current_monitoring smallint,
	ip_rating varchar(64),
	operating_temperature varchar(128),
	relative_humidity varchar(128),
	cooling varchar(128),
	max_altitude integer,
	noise varchar(64),
	topology varchar(128),
	night_consumption varchar(64),
	display_type varchar(128),
	communication_interfaces varchar(255),
	dc_connector varchar(128),
	ac_connector varchar(128),
	mounting varchar(128),
	warranty varchar(128),
	certifications text,
	datec datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer
) ENGINE=innodb;
ALTER TABLE llx_powerplantpv_product_inverter ADD INDEX IF NOT EXISTS idx_powerplantpv_product_inverter_rowid (rowid);
ALTER TABLE llx_powerplantpv_product_inverter ADD UNIQUE INDEX IF NOT EXISTS uk_powerplantpv_product_inverter_product_entity (fk_product, entity);
ALTER TABLE llx_powerplantpv_product_inverter ADD INDEX IF NOT EXISTS idx_powerplantpv_product_inverter_fk_product (fk_product);
ALTER TABLE llx_powerplantpv_product_inverter ADD INDEX IF NOT EXISTS idx_powerplantpv_product_inverter_entity (entity);

CREATE TABLE IF NOT EXISTS llx_powerplantpv_product_inverter_mppt(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	fk_inverter integer NOT NULL,
	entity integer NOT NULL DEFAULT 1,
	position integer NOT NULL DEFAULT 1,
	label varchar(128),
	voltage_min double(24,8),
	voltage_max double(24,8),
	max_input_current double(24,8),
	max_short_circuit_current double(24,8),
	max_dc_power double(24,8),
	note_private text,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
ALTER TABLE llx_powerplantpv_product_inverter_mppt ADD INDEX IF NOT EXISTS idx_powerplantpv_product_inverter_mppt_rowid (rowid);
ALTER TABLE llx_powerplantpv_product_inverter_mppt ADD INDEX IF NOT EXISTS idx_powerplantpv_product_inverter_mppt_fk_inverter (fk_inverter);
ALTER TABLE llx_powerplantpv_product_inverter_mppt ADD UNIQUE INDEX IF NOT EXISTS uk_powerplantpv_product_inverter_mppt_position (fk_inverter, entity, position);
ALTER TABLE llx_powerplantpv_product_inverter_mppt ADD INDEX IF NOT EXISTS idx_powerplantpv_product_inverter_mppt_entity (entity);

CREATE TABLE IF NOT EXISTS llx_powerplantpv_product_inverter_pvinput(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	fk_mppt integer NOT NULL,
	entity integer NOT NULL DEFAULT 1,
	position integer NOT NULL DEFAULT 1,
	label varchar(128),
	max_input_current double(24,8),
	max_short_circuit_current double(24,8),
	connector_type varchar(128),
	note_private text,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
ALTER TABLE llx_powerplantpv_product_inverter_pvinput ADD INDEX IF NOT EXISTS idx_powerplantpv_product_inverter_pvinput_rowid (rowid);
ALTER TABLE llx_powerplantpv_product_inverter_pvinput ADD INDEX IF NOT EXISTS idx_powerplantpv_product_inverter_pvinput_fk_mppt (fk_mppt);
ALTER TABLE llx_powerplantpv_product_inverter_pvinput ADD UNIQUE INDEX IF NOT EXISTS uk_powerplantpv_product_inverter_pvinput_position (fk_mppt, entity, position);
ALTER TABLE llx_powerplantpv_product_inverter_pvinput ADD INDEX IF NOT EXISTS idx_powerplantpv_product_inverter_pvinput_entity (entity);

CREATE TABLE IF NOT EXISTS llx_powerplantpv_product_datasource (
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer NOT NULL DEFAULT 1,
	fk_product integer NOT NULL,
	source varchar(64) NOT NULL,
	source_dataset varchar(64) DEFAULT NULL,
	source_key varchar(255) NOT NULL,
	source_name varchar(255) DEFAULT NULL,
	source_url varchar(1024) DEFAULT NULL,
	filename varchar(255) DEFAULT NULL,
	raw_json mediumtext,
	normalized_json mediumtext,
	import_status varchar(32) NOT NULL DEFAULT 'imported',
	datec datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer NOT NULL,
	fk_user_modif integer
) ENGINE=innodb DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE llx_powerplantpv_product_datasource ADD COLUMN IF NOT EXISTS filename varchar(255) DEFAULT NULL;
ALTER TABLE llx_powerplantpv_product_datasource ADD UNIQUE INDEX IF NOT EXISTS uk_powerplantpv_product_datasource_source (entity, source, source_dataset, source_key);
ALTER TABLE llx_powerplantpv_product_datasource ADD INDEX IF NOT EXISTS idx_powerplantpv_product_datasource_product (fk_product);
ALTER TABLE llx_powerplantpv_product_datasource ADD INDEX IF NOT EXISTS idx_powerplantpv_product_datasource_entity (entity);
ALTER TABLE llx_powerplantpv_product_datasource ADD INDEX IF NOT EXISTS idx_powerplantpv_product_datasource_source (source);

CREATE TABLE IF NOT EXISTS llx_powerplantpv_powerplantcomp(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	fk_powerplant integer NOT NULL,
	fk_product integer NOT NULL,
	fk_status integer,
	qty double NOT NULL,
	serial_number varchar(128),
	commissioning_date date,
	entity integer NOT NULL DEFAULT 1
) ENGINE=innodb DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE llx_powerplantpv_powerplantcomp ADD INDEX IF NOT EXISTS idx_powerplantpv_powerplantcomp_rowid (rowid);
ALTER TABLE llx_powerplantpv_powerplantcomp ADD INDEX IF NOT EXISTS idx_powerplantpv_powerplantcomp_fk_powerplant (fk_powerplant);
ALTER TABLE llx_powerplantpv_powerplantcomp ADD INDEX IF NOT EXISTS idx_powerplantpv_powerplantcomp_fk_product (fk_product);
ALTER TABLE llx_powerplantpv_powerplantcomp ADD INDEX IF NOT EXISTS idx_powerplantpv_powerplantcomp_fk_status (fk_status);
ALTER TABLE llx_powerplantpv_powerplantcomp ADD INDEX IF NOT EXISTS idx_powerplantpv_powerplantcomp_entity (entity);
ALTER TABLE llx_powerplantpv_powerplantcomp ADD COLUMN IF NOT EXISTS fk_status integer;
ALTER TABLE llx_powerplantpv_powerplantcomp ADD COLUMN IF NOT EXISTS serial_number varchar(128);
ALTER TABLE llx_powerplantpv_powerplantcomp MODIFY COLUMN serial_number varchar(128);
ALTER TABLE llx_powerplantpv_powerplantcomp ADD COLUMN IF NOT EXISTS commissioning_date date;
ALTER TABLE llx_powerplantpv_powerplantcomp CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE llx_powerplantpv_powerplantcomp DROP COLUMN IF EXISTS nature_code;

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

CREATE TABLE IF NOT EXISTS llx_powerplantpv_report_template(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	code varchar(64) NOT NULL,
	label varchar(255) NOT NULL,
	label_en varchar(255),
	description text,
	description_en text,
	target_element varchar(64) DEFAULT 'fichinter' NOT NULL,
	is_default smallint DEFAULT 0 NOT NULL,
	active smallint DEFAULT 1 NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer,
	import_key varchar(14)
) ENGINE=innodb;
ALTER TABLE llx_powerplantpv_report_template ADD UNIQUE INDEX IF NOT EXISTS uk_powerplantpv_report_template_code (entity, code);
ALTER TABLE llx_powerplantpv_report_template ADD INDEX IF NOT EXISTS idx_powerplantpv_report_template_entity (entity);
ALTER TABLE llx_powerplantpv_report_template ADD INDEX IF NOT EXISTS idx_powerplantpv_report_template_active (active);
ALTER TABLE llx_powerplantpv_report_template ADD INDEX IF NOT EXISTS idx_powerplantpv_report_template_default (entity, is_default);
ALTER TABLE llx_powerplantpv_report_template ADD INDEX IF NOT EXISTS idx_powerplantpv_report_template_target (target_element);

CREATE TABLE IF NOT EXISTS llx_powerplantpv_report_template_section(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_report_template integer NOT NULL,
	code varchar(64) NOT NULL,
	label varchar(255) NOT NULL,
	label_en varchar(255),
	description text,
	description_en text,
	scope_type varchar(32) NOT NULL,
	equipment_type varchar(32),
	repeat_mode varchar(32) NOT NULL,
	is_required smallint DEFAULT 0 NOT NULL,
	visible_form smallint DEFAULT 1 NOT NULL,
	visible_pdf smallint DEFAULT 1 NOT NULL,
	active smallint DEFAULT 1 NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer,
	import_key varchar(14)
) ENGINE=innodb;
ALTER TABLE llx_powerplantpv_report_template_section ADD UNIQUE INDEX IF NOT EXISTS uk_powerplantpv_report_template_section_code (entity, fk_report_template, code);
ALTER TABLE llx_powerplantpv_report_template_section ADD INDEX IF NOT EXISTS idx_powerplantpv_report_template_section_entity (entity);
ALTER TABLE llx_powerplantpv_report_template_section ADD INDEX IF NOT EXISTS idx_powerplantpv_report_template_section_template (fk_report_template);
ALTER TABLE llx_powerplantpv_report_template_section ADD INDEX IF NOT EXISTS idx_powerplantpv_report_template_section_scope (scope_type);
ALTER TABLE llx_powerplantpv_report_template_section ADD INDEX IF NOT EXISTS idx_powerplantpv_report_template_section_active (active);

CREATE TABLE IF NOT EXISTS llx_c_powerplantpv_intervention_nature(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	code varchar(64) NOT NULL,
	label varchar(255) NOT NULL,
	label_en varchar(255),
	description text,
	description_en text,
	fk_report_template integer,
	report_template_code varchar(64),
	is_maintenance smallint DEFAULT 0 NOT NULL,
	is_preventive smallint DEFAULT 0 NOT NULL,
	requires_report smallint DEFAULT 0 NOT NULL,
	requires_signature smallint DEFAULT 0 NOT NULL,
	active smallint DEFAULT 1 NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	import_key varchar(14)
) ENGINE=innodb;
ALTER TABLE llx_c_powerplantpv_intervention_nature ADD COLUMN IF NOT EXISTS fk_report_template integer;
ALTER TABLE llx_c_powerplantpv_intervention_nature ADD UNIQUE INDEX IF NOT EXISTS uk_c_powerplantpv_intervention_nature_code (entity, code);
ALTER TABLE llx_c_powerplantpv_intervention_nature ADD INDEX IF NOT EXISTS idx_c_powerplantpv_intervention_nature_entity (entity);
ALTER TABLE llx_c_powerplantpv_intervention_nature ADD INDEX IF NOT EXISTS idx_c_powerplantpv_intervention_nature_active (active);
ALTER TABLE llx_c_powerplantpv_intervention_nature ADD INDEX IF NOT EXISTS idx_c_powerplantpv_intervention_nature_fk_template (fk_report_template);
ALTER TABLE llx_c_powerplantpv_intervention_nature ADD INDEX IF NOT EXISTS idx_c_powerplantpv_intervention_nature_template (report_template_code);

CREATE TABLE IF NOT EXISTS llx_c_powerplantpv_maintenance_service(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	code varchar(64) NOT NULL,
	label varchar(255) NOT NULL,
	label_en varchar(255),
	description text,
	description_en text,
	active smallint DEFAULT 1 NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	import_key varchar(14)
) ENGINE=innodb;
ALTER TABLE llx_c_powerplantpv_maintenance_service ADD UNIQUE INDEX IF NOT EXISTS uk_c_powerplantpv_maintenance_service_code (entity, code);
ALTER TABLE llx_c_powerplantpv_maintenance_service ADD INDEX IF NOT EXISTS idx_c_powerplantpv_maintenance_service_entity (entity);
ALTER TABLE llx_c_powerplantpv_maintenance_service ADD INDEX IF NOT EXISTS idx_c_powerplantpv_maintenance_service_active (active);

CREATE TABLE IF NOT EXISTS llx_c_powerplantpv_report_section(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	code varchar(64) NOT NULL,
	label varchar(255) NOT NULL,
	label_en varchar(255),
	description text,
	description_en text,
	scope_type varchar(32),
	equipment_type varchar(32),
	repeat_mode varchar(32),
	is_base smallint DEFAULT 0 NOT NULL,
	is_required smallint DEFAULT 0 NOT NULL,
	active smallint DEFAULT 1 NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	import_key varchar(14)
) ENGINE=innodb;
ALTER TABLE llx_c_powerplantpv_report_section ADD UNIQUE INDEX IF NOT EXISTS uk_c_powerplantpv_report_section_code (entity, code);
ALTER TABLE llx_c_powerplantpv_report_section ADD INDEX IF NOT EXISTS idx_c_powerplantpv_report_section_entity (entity);
ALTER TABLE llx_c_powerplantpv_report_section ADD INDEX IF NOT EXISTS idx_c_powerplantpv_report_section_active (active);
ALTER TABLE llx_c_powerplantpv_report_section ADD INDEX IF NOT EXISTS idx_c_powerplantpv_report_section_scope (scope_type);

CREATE TABLE IF NOT EXISTS llx_c_powerplantpv_index_type(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	code varchar(64) NOT NULL,
	label varchar(255) NOT NULL,
	label_en varchar(255),
	description text,
	description_en text,
	default_unit varchar(32),
	active smallint DEFAULT 1 NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	import_key varchar(14)
) ENGINE=innodb;
ALTER TABLE llx_c_powerplantpv_index_type ADD UNIQUE INDEX IF NOT EXISTS uk_c_powerplantpv_index_type_code (entity, code);
ALTER TABLE llx_c_powerplantpv_index_type ADD INDEX IF NOT EXISTS idx_c_powerplantpv_index_type_entity (entity);
ALTER TABLE llx_c_powerplantpv_index_type ADD INDEX IF NOT EXISTS idx_c_powerplantpv_index_type_active (active);

CREATE TABLE IF NOT EXISTS llx_powerplantpv_maintenance_service_section(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_report_template integer,
	fk_maintenance_service integer NOT NULL,
	fk_report_section integer NOT NULL,
	fk_report_template_section integer,
	is_required smallint DEFAULT 0 NOT NULL,
	active smallint DEFAULT 1 NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer,
	import_key varchar(14)
) ENGINE=innodb;
ALTER TABLE llx_powerplantpv_maintenance_service_section ADD COLUMN IF NOT EXISTS fk_report_template integer;
ALTER TABLE llx_powerplantpv_maintenance_service_section ADD COLUMN IF NOT EXISTS fk_report_template_section integer;
ALTER TABLE llx_powerplantpv_maintenance_service_section ADD COLUMN IF NOT EXISTS is_required smallint DEFAULT 0 NOT NULL;
ALTER TABLE llx_powerplantpv_maintenance_service_section ADD COLUMN IF NOT EXISTS tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE llx_powerplantpv_maintenance_service_section ADD COLUMN IF NOT EXISTS fk_user_modif integer;
ALTER TABLE llx_powerplantpv_maintenance_service_section ADD UNIQUE INDEX IF NOT EXISTS uk_powerplantpv_maintenance_service_section (entity, fk_maintenance_service, fk_report_section);
ALTER TABLE llx_powerplantpv_maintenance_service_section ADD UNIQUE INDEX IF NOT EXISTS uk_powerplantpv_maintenance_service_template_section (entity, fk_report_template, fk_maintenance_service, fk_report_template_section);
ALTER TABLE llx_powerplantpv_maintenance_service_section ADD INDEX IF NOT EXISTS idx_powerplantpv_maintenance_service_section_entity (entity);
ALTER TABLE llx_powerplantpv_maintenance_service_section ADD INDEX IF NOT EXISTS idx_powerplantpv_maintenance_service_section_template (fk_report_template);
ALTER TABLE llx_powerplantpv_maintenance_service_section ADD INDEX IF NOT EXISTS idx_powerplantpv_maintenance_service_section_service (fk_maintenance_service);
ALTER TABLE llx_powerplantpv_maintenance_service_section ADD INDEX IF NOT EXISTS idx_powerplantpv_maintenance_service_section_section (fk_report_section);
ALTER TABLE llx_powerplantpv_maintenance_service_section ADD INDEX IF NOT EXISTS idx_powerplantpv_maintenance_service_template_section_fk (fk_report_template_section);

CREATE TABLE IF NOT EXISTS llx_powerplantpv_report_template_field(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_report_template integer,
	fk_report_template_section integer,
	report_template_code varchar(64) NOT NULL,
	fk_report_section integer NOT NULL,
	fk_maintenance_service integer,
	code varchar(64) NOT NULL,
	label varchar(255) NOT NULL,
	label_en varchar(255),
	description text,
	description_en text,
	field_type varchar(32) NOT NULL,
	scope_type varchar(32),
	unit varchar(32),
	default_value text,
	placeholder varchar(255),
	help text,
	is_required smallint DEFAULT 0 NOT NULL,
	visible_form smallint DEFAULT 1 NOT NULL,
	visible_pdf smallint DEFAULT 1 NOT NULL,
	readonly smallint DEFAULT 0 NOT NULL,
	active smallint DEFAULT 1 NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer,
	import_key varchar(14)
) ENGINE=innodb;
ALTER TABLE llx_powerplantpv_report_template_field ADD COLUMN IF NOT EXISTS fk_report_template integer;
ALTER TABLE llx_powerplantpv_report_template_field ADD COLUMN IF NOT EXISTS fk_report_template_section integer;
ALTER TABLE llx_powerplantpv_report_template_field ADD COLUMN IF NOT EXISTS default_value text;
ALTER TABLE llx_powerplantpv_report_template_field ADD COLUMN IF NOT EXISTS placeholder varchar(255);
ALTER TABLE llx_powerplantpv_report_template_field ADD COLUMN IF NOT EXISTS help text;
ALTER TABLE llx_powerplantpv_report_template_field ADD COLUMN IF NOT EXISTS visible_form smallint DEFAULT 1 NOT NULL;
ALTER TABLE llx_powerplantpv_report_template_field ADD COLUMN IF NOT EXISTS visible_pdf smallint DEFAULT 1 NOT NULL;
ALTER TABLE llx_powerplantpv_report_template_field ADD COLUMN IF NOT EXISTS readonly smallint DEFAULT 0 NOT NULL;
ALTER TABLE llx_powerplantpv_report_template_field ADD COLUMN IF NOT EXISTS date_creation datetime;
ALTER TABLE llx_powerplantpv_report_template_field ADD COLUMN IF NOT EXISTS tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE llx_powerplantpv_report_template_field ADD COLUMN IF NOT EXISTS fk_user_creat integer;
ALTER TABLE llx_powerplantpv_report_template_field ADD COLUMN IF NOT EXISTS fk_user_modif integer;
ALTER TABLE llx_powerplantpv_report_template_field ADD UNIQUE INDEX IF NOT EXISTS uk_powerplantpv_report_template_field_code (entity, report_template_code, code);
ALTER TABLE llx_powerplantpv_report_template_field ADD INDEX IF NOT EXISTS idx_powerplantpv_report_template_field_entity (entity);
ALTER TABLE llx_powerplantpv_report_template_field ADD INDEX IF NOT EXISTS idx_powerplantpv_report_template_field_fk_template (fk_report_template);
ALTER TABLE llx_powerplantpv_report_template_field ADD INDEX IF NOT EXISTS idx_powerplantpv_report_template_field_fk_section (fk_report_template_section);
ALTER TABLE llx_powerplantpv_report_template_field ADD INDEX IF NOT EXISTS idx_powerplantpv_report_template_field_section (fk_report_section);
ALTER TABLE llx_powerplantpv_report_template_field ADD INDEX IF NOT EXISTS idx_powerplantpv_report_template_field_service (fk_maintenance_service);
ALTER TABLE llx_powerplantpv_report_template_field ADD INDEX IF NOT EXISTS idx_powerplantpv_report_template_field_template (report_template_code);

CREATE TABLE IF NOT EXISTS llx_powerplantpv_report_template_field_option(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_report_template_field integer NOT NULL,
	code varchar(64) NOT NULL,
	label varchar(255) NOT NULL,
	label_en varchar(255),
	active smallint DEFAULT 1 NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer,
	import_key varchar(14)
) ENGINE=innodb;
ALTER TABLE llx_powerplantpv_report_template_field_option ADD UNIQUE INDEX IF NOT EXISTS uk_powerplantpv_report_template_field_option_code (entity, fk_report_template_field, code);
ALTER TABLE llx_powerplantpv_report_template_field_option ADD INDEX IF NOT EXISTS idx_powerplantpv_report_template_field_option_entity (entity);
ALTER TABLE llx_powerplantpv_report_template_field_option ADD INDEX IF NOT EXISTS idx_powerplantpv_report_template_field_option_field (fk_report_template_field);
ALTER TABLE llx_powerplantpv_report_template_field_option ADD INDEX IF NOT EXISTS idx_powerplantpv_report_template_field_option_active (active);

CREATE TABLE IF NOT EXISTS llx_powerplantpv_report(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_fichinter integer NOT NULL,
	fk_soc integer,
	fk_project integer,
	fk_intervention_nature integer,
	intervention_nature_code varchar(64),
	intervention_nature_label varchar(255),
	intervention_nature_label_en varchar(255),
	fk_report_template integer,
	report_template_code varchar(64),
	report_template_label varchar(255),
	report_template_label_en varchar(255),
	source_mode varchar(16) DEFAULT 'contract' NOT NULL,
	status varchar(16) DEFAULT 'draft' NOT NULL,
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer,
	import_key varchar(14)
) ENGINE=innodb;
ALTER TABLE llx_powerplantpv_report ADD UNIQUE INDEX IF NOT EXISTS uk_powerplantpv_report_fichinter (entity, fk_fichinter);
ALTER TABLE llx_powerplantpv_report ADD INDEX IF NOT EXISTS idx_powerplantpv_report_entity (entity);
ALTER TABLE llx_powerplantpv_report ADD INDEX IF NOT EXISTS idx_powerplantpv_report_soc (fk_soc);
ALTER TABLE llx_powerplantpv_report ADD INDEX IF NOT EXISTS idx_powerplantpv_report_project (fk_project);
ALTER TABLE llx_powerplantpv_report ADD INDEX IF NOT EXISTS idx_powerplantpv_report_nature (fk_intervention_nature);
ALTER TABLE llx_powerplantpv_report ADD INDEX IF NOT EXISTS idx_powerplantpv_report_template (fk_report_template);
ALTER TABLE llx_powerplantpv_report ADD INDEX IF NOT EXISTS idx_powerplantpv_report_status (status);

CREATE TABLE IF NOT EXISTS llx_powerplantpv_report_powerplant(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_report integer NOT NULL,
	fk_powerplant integer NOT NULL,
	powerplant_ref varchar(128),
	powerplant_label varchar(255),
	fk_soc integer,
	fk_project integer,
	position integer DEFAULT 0 NOT NULL,
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer,
	import_key varchar(14)
) ENGINE=innodb;
ALTER TABLE llx_powerplantpv_report_powerplant ADD UNIQUE INDEX IF NOT EXISTS uk_powerplantpv_report_powerplant (entity, fk_report, fk_powerplant);
ALTER TABLE llx_powerplantpv_report_powerplant ADD INDEX IF NOT EXISTS idx_powerplantpv_report_powerplant_entity (entity);
ALTER TABLE llx_powerplantpv_report_powerplant ADD INDEX IF NOT EXISTS idx_powerplantpv_report_powerplant_report (fk_report);
ALTER TABLE llx_powerplantpv_report_powerplant ADD INDEX IF NOT EXISTS idx_powerplantpv_report_powerplant_powerplant (fk_powerplant);
ALTER TABLE llx_powerplantpv_report_powerplant ADD INDEX IF NOT EXISTS idx_powerplantpv_report_powerplant_soc (fk_soc);
ALTER TABLE llx_powerplantpv_report_powerplant ADD INDEX IF NOT EXISTS idx_powerplantpv_report_powerplant_project (fk_project);

CREATE TABLE IF NOT EXISTS llx_powerplantpv_report_source_service(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_report integer NOT NULL,
	fk_report_powerplant integer,
	fk_powerplant integer,
	fk_contract integer,
	contract_ref varchar(128),
	fk_contract_line integer,
	fk_product integer,
	product_ref varchar(128),
	product_label varchar(255),
	fk_maintenance_service integer NOT NULL,
	maintenance_service_code varchar(64),
	maintenance_service_label varchar(255),
	maintenance_service_label_en varchar(255),
	source_mode varchar(16) DEFAULT 'contract' NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer,
	import_key varchar(14)
) ENGINE=innodb;
ALTER TABLE llx_powerplantpv_report_source_service ADD INDEX IF NOT EXISTS idx_powerplantpv_report_source_service_entity (entity);
ALTER TABLE llx_powerplantpv_report_source_service ADD INDEX IF NOT EXISTS idx_powerplantpv_report_source_service_report (fk_report);
ALTER TABLE llx_powerplantpv_report_source_service ADD INDEX IF NOT EXISTS idx_powerplantpv_report_source_service_report_powerplant (fk_report_powerplant);
ALTER TABLE llx_powerplantpv_report_source_service ADD INDEX IF NOT EXISTS idx_powerplantpv_report_source_service_powerplant (fk_powerplant);
ALTER TABLE llx_powerplantpv_report_source_service ADD INDEX IF NOT EXISTS idx_powerplantpv_report_source_service_contract (fk_contract);
ALTER TABLE llx_powerplantpv_report_source_service ADD INDEX IF NOT EXISTS idx_powerplantpv_report_source_service_contract_line (fk_contract_line);
ALTER TABLE llx_powerplantpv_report_source_service ADD INDEX IF NOT EXISTS idx_powerplantpv_report_source_service_product (fk_product);
ALTER TABLE llx_powerplantpv_report_source_service ADD INDEX IF NOT EXISTS idx_powerplantpv_report_source_service_service (fk_maintenance_service);

CREATE TABLE IF NOT EXISTS llx_powerplantpv_report_equipment(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_report integer NOT NULL,
	fk_report_powerplant integer,
	fk_powerplant integer,
	fk_powerplant_line integer,
	fk_product integer,
	product_ref varchar(128),
	product_label varchar(255),
	equipment_type varchar(32),
	equipment_ref varchar(128),
	equipment_label varchar(255),
	serial_number varchar(128),
	qty double(24,8),
	technical_key varchar(255),
	position integer DEFAULT 0 NOT NULL,
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer,
	import_key varchar(14)
) ENGINE=innodb;
ALTER TABLE llx_powerplantpv_report_equipment ADD INDEX IF NOT EXISTS idx_powerplantpv_report_equipment_entity (entity);
ALTER TABLE llx_powerplantpv_report_equipment ADD INDEX IF NOT EXISTS idx_powerplantpv_report_equipment_report (fk_report);
ALTER TABLE llx_powerplantpv_report_equipment ADD INDEX IF NOT EXISTS idx_powerplantpv_report_equipment_report_powerplant (fk_report_powerplant);
ALTER TABLE llx_powerplantpv_report_equipment ADD INDEX IF NOT EXISTS idx_powerplantpv_report_equipment_powerplant (fk_powerplant);
ALTER TABLE llx_powerplantpv_report_equipment ADD INDEX IF NOT EXISTS idx_powerplantpv_report_equipment_powerplant_line (fk_powerplant_line);
ALTER TABLE llx_powerplantpv_report_equipment ADD INDEX IF NOT EXISTS idx_powerplantpv_report_equipment_product (fk_product);
ALTER TABLE llx_powerplantpv_report_equipment ADD INDEX IF NOT EXISTS idx_powerplantpv_report_equipment_type (equipment_type);
ALTER TABLE llx_powerplantpv_report_equipment ADD INDEX IF NOT EXISTS idx_powerplantpv_report_equipment_technical_key (technical_key);

CREATE TABLE IF NOT EXISTS llx_powerplantpv_report_section(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_report integer NOT NULL,
	fk_report_powerplant integer,
	fk_report_equipment integer,
	fk_report_template_section integer,
	section_code varchar(64) NOT NULL,
	section_label varchar(255) NOT NULL,
	section_label_en varchar(255),
	section_description text,
	section_description_en text,
	scope_type varchar(32) NOT NULL,
	equipment_type varchar(32),
	repeat_mode varchar(32) NOT NULL,
	occurrence_key varchar(255) NOT NULL,
	is_required smallint DEFAULT 0 NOT NULL,
	visible_form smallint DEFAULT 1 NOT NULL,
	visible_pdf smallint DEFAULT 1 NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer,
	import_key varchar(14)
) ENGINE=innodb;
ALTER TABLE llx_powerplantpv_report_section ADD UNIQUE INDEX IF NOT EXISTS uk_powerplantpv_report_section_occurrence (entity, fk_report, occurrence_key);
ALTER TABLE llx_powerplantpv_report_section ADD INDEX IF NOT EXISTS idx_powerplantpv_report_section_entity (entity);
ALTER TABLE llx_powerplantpv_report_section ADD INDEX IF NOT EXISTS idx_powerplantpv_report_section_report (fk_report);
ALTER TABLE llx_powerplantpv_report_section ADD INDEX IF NOT EXISTS idx_powerplantpv_report_section_powerplant (fk_report_powerplant);
ALTER TABLE llx_powerplantpv_report_section ADD INDEX IF NOT EXISTS idx_powerplantpv_report_section_equipment (fk_report_equipment);
ALTER TABLE llx_powerplantpv_report_section ADD INDEX IF NOT EXISTS idx_powerplantpv_report_section_template_section (fk_report_template_section);
ALTER TABLE llx_powerplantpv_report_section ADD INDEX IF NOT EXISTS idx_powerplantpv_report_section_code (section_code);
ALTER TABLE llx_powerplantpv_report_section ADD INDEX IF NOT EXISTS idx_powerplantpv_report_section_scope (scope_type);

CREATE TABLE IF NOT EXISTS llx_powerplantpv_report_field(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_report integer NOT NULL,
	fk_report_section integer NOT NULL,
	fk_report_powerplant integer,
	fk_report_equipment integer,
	fk_report_template_field integer,
	stable_key varchar(255) NOT NULL,
	field_code varchar(64) NOT NULL,
	field_label varchar(255) NOT NULL,
	field_label_en varchar(255),
	field_description text,
	field_description_en text,
	field_type varchar(32) NOT NULL,
	scope_type varchar(32),
	unit varchar(32),
	default_value text,
	placeholder varchar(255),
	help text,
	options_snapshot mediumtext,
	value_text mediumtext,
	value_number double(24,8),
	value_date datetime,
	is_required smallint DEFAULT 0 NOT NULL,
	visible_form smallint DEFAULT 1 NOT NULL,
	visible_pdf smallint DEFAULT 1 NOT NULL,
	readonly smallint DEFAULT 0 NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer,
	import_key varchar(14)
) ENGINE=innodb;
ALTER TABLE llx_powerplantpv_report_field ADD UNIQUE INDEX IF NOT EXISTS uk_powerplantpv_report_field_stable_key (entity, fk_report, stable_key);
ALTER TABLE llx_powerplantpv_report_field ADD INDEX IF NOT EXISTS idx_powerplantpv_report_field_entity (entity);
ALTER TABLE llx_powerplantpv_report_field ADD INDEX IF NOT EXISTS idx_powerplantpv_report_field_report (fk_report);
ALTER TABLE llx_powerplantpv_report_field ADD INDEX IF NOT EXISTS idx_powerplantpv_report_field_section (fk_report_section);
ALTER TABLE llx_powerplantpv_report_field ADD INDEX IF NOT EXISTS idx_powerplantpv_report_field_powerplant (fk_report_powerplant);
ALTER TABLE llx_powerplantpv_report_field ADD INDEX IF NOT EXISTS idx_powerplantpv_report_field_equipment (fk_report_equipment);
ALTER TABLE llx_powerplantpv_report_field ADD INDEX IF NOT EXISTS idx_powerplantpv_report_field_template_field (fk_report_template_field);
ALTER TABLE llx_powerplantpv_report_field ADD INDEX IF NOT EXISTS idx_powerplantpv_report_field_code (field_code);
ALTER TABLE llx_powerplantpv_report_field ADD INDEX IF NOT EXISTS idx_powerplantpv_report_field_type (field_type);

CREATE TABLE IF NOT EXISTS llx_powerplantpv_report_file(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_report integer NOT NULL,
	fk_report_field integer NOT NULL,
	filename varchar(255) NOT NULL,
	filepath varchar(255) NOT NULL,
	filemime varchar(128),
	filesize integer,
	checksum varchar(128),
	position integer DEFAULT 0 NOT NULL,
	date_upload datetime,
	fk_user_upload integer,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	import_key varchar(14)
) ENGINE=innodb;
ALTER TABLE llx_powerplantpv_report_file ADD INDEX IF NOT EXISTS idx_powerplantpv_report_file_entity (entity);
ALTER TABLE llx_powerplantpv_report_file ADD INDEX IF NOT EXISTS idx_powerplantpv_report_file_report (fk_report);
ALTER TABLE llx_powerplantpv_report_file ADD INDEX IF NOT EXISTS idx_powerplantpv_report_file_field (fk_report_field);

CREATE TABLE IF NOT EXISTS llx_powerplantpv_serialnumber(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_powerplant integer NOT NULL,
	fk_powerplant_line integer NOT NULL,
	fk_product integer NOT NULL,
	fk_categorie integer NOT NULL,
	serial_number varchar(128) NOT NULL,
	source_file varchar(255),
	import_batch varchar(64),
	note text,
	import_status varchar(32) DEFAULT 'validated' NOT NULL,
	datec datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer NOT NULL,
	fk_user_modif integer,
	import_key varchar(14)
) ENGINE=innodb DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE llx_powerplantpv_serialnumber ADD UNIQUE INDEX IF NOT EXISTS uk_powerplantpv_serialnumber_powerplant_serial (entity, fk_powerplant, serial_number);
ALTER TABLE llx_powerplantpv_serialnumber ADD INDEX IF NOT EXISTS idx_powerplantpv_serialnumber_entity (entity);
ALTER TABLE llx_powerplantpv_serialnumber ADD INDEX IF NOT EXISTS idx_powerplantpv_serialnumber_fk_powerplant (fk_powerplant);
ALTER TABLE llx_powerplantpv_serialnumber ADD INDEX IF NOT EXISTS idx_powerplantpv_serialnumber_fk_powerplant_line (fk_powerplant_line);
ALTER TABLE llx_powerplantpv_serialnumber ADD INDEX IF NOT EXISTS idx_powerplantpv_serialnumber_fk_product (fk_product);
ALTER TABLE llx_powerplantpv_serialnumber ADD INDEX IF NOT EXISTS idx_powerplantpv_serialnumber_fk_categorie (fk_categorie);
ALTER TABLE llx_powerplantpv_serialnumber ADD INDEX IF NOT EXISTS idx_powerplantpv_serialnumber_import_batch (import_batch);

CREATE TABLE IF NOT EXISTS llx_powerplantpv_serialnumber_import(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_powerplant integer NOT NULL,
	fk_categorie integer NOT NULL,
	fk_user integer NOT NULL,
	filename varchar(255) NOT NULL,
	filepath varchar(1024),
	status varchar(16) DEFAULT 'draft' NOT NULL,
	import_mode varchar(16) DEFAULT 'add' NOT NULL,
	first_line_headers smallint DEFAULT 1 NOT NULL,
	raw_data_json mediumtext,
	parsed_data_json mediumtext,
	errors_json mediumtext,
	datec datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE llx_powerplantpv_serialnumber_import ADD INDEX IF NOT EXISTS idx_powerplantpv_serialnumber_import_entity (entity);
ALTER TABLE llx_powerplantpv_serialnumber_import ADD INDEX IF NOT EXISTS idx_powerplantpv_serialnumber_import_fk_powerplant (fk_powerplant);
ALTER TABLE llx_powerplantpv_serialnumber_import ADD INDEX IF NOT EXISTS idx_powerplantpv_serialnumber_import_fk_categorie (fk_categorie);
ALTER TABLE llx_powerplantpv_serialnumber_import ADD INDEX IF NOT EXISTS idx_powerplantpv_serialnumber_import_fk_user (fk_user);
ALTER TABLE llx_powerplantpv_serialnumber_import ADD INDEX IF NOT EXISTS idx_powerplantpv_serialnumber_import_status (status);
