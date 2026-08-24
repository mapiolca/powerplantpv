-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>

CREATE TABLE IF NOT EXISTS llx_powerplantpv_product_battery_accessory(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	fk_product integer NOT NULL,
	entity integer NOT NULL DEFAULT 1,
	role_code varchar(32) NOT NULL DEFAULT 'OTHER',
	note_private text,
	datec datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer
) ENGINE=innodb;
