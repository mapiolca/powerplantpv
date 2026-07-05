-- Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.


CREATE TABLE llx_powerplantpv_report_dc_measure(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_report integer NOT NULL,
	fk_report_section integer NOT NULL,
	fk_report_powerplant integer,
	fk_report_equipment integer,
	fk_powerplant integer,
	fk_inverter integer,
	inverter_ref varchar(128),
	inverter_label varchar(255),
	inverter_serial varchar(128),
	mppt_number integer,
	pv_input_number integer,
	string_ref varchar(128),
	is_connected smallint DEFAULT 1 NOT NULL,
	open_circuit_voltage double(24,8),
	polarity_checked smallint DEFAULT 0 NOT NULL,
	insulation_status varchar(32),
	insulation_positive_to_ground double(24,8),
	insulation_negative_to_ground double(24,8),
	observation text,
	stable_key varchar(255) NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer,
	import_key varchar(14)
) ENGINE=innodb;
