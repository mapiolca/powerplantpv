CREATE TABLE IF NOT EXISTS llx_powerplantpv_monitor_config (
	entity integer NOT NULL,
	provider varchar(32) NOT NULL,
	enabled smallint NOT NULL DEFAULT 0,
	cadence integer NOT NULL DEFAULT 15,
	credentials text,
	session_data text,
	last_attempt bigint NOT NULL DEFAULT 0,
	last_success bigint NOT NULL DEFAULT 0,
	last_error varchar(64),
	PRIMARY KEY (entity, provider)
) ENGINE=InnoDB;
