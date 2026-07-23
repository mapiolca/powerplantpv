CREATE TABLE IF NOT EXISTS llx_powerplantpv_product_communication_protocol(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_product integer NOT NULL,
	fk_communication_protocol integer NOT NULL,
	date_creation datetime NOT NULL,
	fk_user_creat integer NOT NULL
) ENGINE=innodb;
