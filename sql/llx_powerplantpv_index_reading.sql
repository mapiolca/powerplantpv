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


CREATE TABLE IF NOT EXISTS llx_powerplantpv_index_reading(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_powerplant integer NOT NULL,
	fk_fichinter_source integer,
	fk_report integer,
	fk_report_powerplant integer DEFAULT 0 NOT NULL,
	fk_report_equipment integer DEFAULT 0 NOT NULL,
	fk_index_type integer,
	reading_type_code varchar(64) NOT NULL,
	reading_date datetime NOT NULL,
	value double(24,8) NOT NULL,
	unit varchar(32) DEFAULT 'kWh' NOT NULL,
	meter_ref varchar(128) DEFAULT '' NOT NULL,
	source_type varchar(32) DEFAULT 'manual' NOT NULL,
	comment text,
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer,
	active smallint DEFAULT 1 NOT NULL
) ENGINE=innodb;
