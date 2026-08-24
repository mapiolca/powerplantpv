-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>

CREATE TABLE IF NOT EXISTS llx_powerplantpv_product_battery_accessory_rule(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	fk_accessory integer NOT NULL,
	entity integer NOT NULL DEFAULT 1,
	rule_effect varchar(16) NOT NULL DEFAULT 'COMPATIBLE',
	criterion_type varchar(32) NOT NULL,
	fk_target_product integer,
	value_code varchar(255),
	min_value double(24,8),
	max_value double(24,8),
	unit_code varchar(32),
	min_quantity double(24,8),
	max_quantity double(24,8),
	position integer NOT NULL DEFAULT 0,
	note_private text,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
