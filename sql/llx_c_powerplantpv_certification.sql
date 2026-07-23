CREATE TABLE IF NOT EXISTS llx_c_powerplantpv_certification(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	code varchar(64) NOT NULL,
	label varchar(255) NOT NULL,
	description text,
	active smallint DEFAULT 1 NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	import_key varchar(14)
) ENGINE=innodb;
