-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
-- SPDX-License-Identifier: GPL-3.0-or-later

CREATE TABLE IF NOT EXISTS llx_powerplantpv_monitor_layout (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer NOT NULL,
	fk_powerplant integer NOT NULL,
	fk_user_creat integer NOT NULL,
	date_creation datetime NOT NULL,
	proof_ref varchar(32),
	KEY idx_ppv_layout_plant (entity, fk_powerplant)
) ENGINE=InnoDB;
