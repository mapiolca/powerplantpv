-- Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>

CREATE TABLE llx_powerplantpv_attestation_equipment(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer NOT NULL DEFAULT 1,
	fk_attestation integer NOT NULL,
	fk_powerplant_line integer,
	fk_product integer,
	equipment_type varchar(64) NOT NULL,
	designation varchar(255),
	brand varchar(255),
	model varchar(255),
	manufacturer varchar(255),
	serial_number varchar(255),
	bridage_enabled smallint DEFAULT 0,
	bridage_type varchar(64),
	max_power_kw double(24,8),
	rank integer DEFAULT 0
) ENGINE=innodb DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
