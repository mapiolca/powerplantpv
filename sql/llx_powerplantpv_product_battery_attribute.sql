-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>

CREATE TABLE IF NOT EXISTS llx_powerplantpv_product_battery_attribute(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	fk_battery integer NOT NULL,
	entity integer NOT NULL DEFAULT 1,
	attribute_type varchar(32) NOT NULL,
	attribute_code varchar(128) NOT NULL,
	attribute_label varchar(255),
	position integer NOT NULL DEFAULT 0,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
