-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
-- SPDX-License-Identifier: GPL-3.0-or-later

CREATE TABLE IF NOT EXISTS llx_powerplantpv_monitor_position (
	entity integer NOT NULL,
	fk_powerplant integer NOT NULL,
	fk_serial integer NOT NULL,
	fk_layout integer NOT NULL,
	position varchar(96) COLLATE utf8mb4_bin NOT NULL,
	PRIMARY KEY (entity, fk_powerplant, position),
	KEY idx_ppv_position_serial (entity, fk_serial),
	KEY idx_ppv_position_layout (entity, fk_layout)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
