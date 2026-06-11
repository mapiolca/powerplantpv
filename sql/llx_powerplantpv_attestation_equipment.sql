-- Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>

CREATE TABLE llx_powerplantpv_attestation_equipment(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer NOT NULL DEFAULT 1,
	fk_attestation integer NOT NULL,
	fk_powerplant_line integer,
	fk_powerplant_serialnumber integer,
	fk_product integer,
	fk_categorie integer,
	rank integer DEFAULT 0
) ENGINE=innodb DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
