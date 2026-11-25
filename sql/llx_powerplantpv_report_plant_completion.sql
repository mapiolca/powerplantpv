CREATE TABLE IF NOT EXISTS llx_powerplantpv_report_plant_completion (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer NOT NULL,
	fk_report integer NOT NULL,
	fk_powerplant integer NOT NULL,
	fk_user_author integer NOT NULL,
	plant_entity integer NOT NULL,
	proposal mediumtext NOT NULL,
	status smallint NOT NULL DEFAULT 0,
	error_code varchar(128),
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
