-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>

CREATE TABLE IF NOT EXISTS llx_powerplantpv_maintenance_widget_user(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_user integer NOT NULL,
	widget_code varchar(64) NOT NULL,
	visible smallint DEFAULT 1 NOT NULL,
	column_index smallint DEFAULT 0 NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer,
	import_key varchar(14)
) ENGINE=innodb;
