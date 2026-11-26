CREATE TABLE IF NOT EXISTS llx_powerplantpv_monitor_reading (
	entity integer NOT NULL,
	fk_link integer NOT NULL,
	site_id varchar(128) COLLATE utf8mb4_bin NOT NULL,
	reported_at bigint NOT NULL,
	device_status smallint NOT NULL,
	power_w double,
	energy_today_kwh double,
	PRIMARY KEY (entity, fk_link, site_id, reported_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
