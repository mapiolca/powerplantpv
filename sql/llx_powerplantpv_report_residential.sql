-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
-- SPDX-License-Identifier: GPL-3.0-or-later

CREATE TABLE IF NOT EXISTS llx_powerplantpv_report_residential (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_report integer NOT NULL,
	fk_parent integer,
	row_type varchar(12) NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	placement_type varchar(16),
	fk_component integer,
	fk_powerplant integer,
	fk_product integer,
	reference_label varchar(512),
	description varchar(255),
	orientation varchar(2),
	module_count integer,
	row_count integer,
	column_count integer,
	voltage double(24,8),
	micro smallint,
	quantity integer,
	maximum integer,
	serial_number varchar(128)
) ENGINE=innodb;
