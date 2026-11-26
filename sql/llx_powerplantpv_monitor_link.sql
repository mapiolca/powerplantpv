CREATE TABLE IF NOT EXISTS llx_powerplantpv_monitor_link (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer NOT NULL,
	fk_powerplant integer NOT NULL,
	provider varchar(32) NOT NULL,
	site_id varchar(128) COLLATE utf8mb4_bin NOT NULL,
	site_name varchar(255) NOT NULL,
	site_timezone varchar(64) NOT NULL DEFAULT '',
	UNIQUE KEY uk_ppv_monitor_plant (entity, fk_powerplant),
	UNIQUE KEY uk_ppv_monitor_site (entity, provider, site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
