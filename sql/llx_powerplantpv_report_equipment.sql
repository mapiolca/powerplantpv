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


CREATE TABLE llx_powerplantpv_report_equipment(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_report integer NOT NULL,
	fk_report_powerplant integer,
	fk_powerplant integer,
	fk_powerplant_line integer,
	fk_product integer,
	product_ref varchar(128),
	product_label varchar(255),
	equipment_type varchar(32),
	equipment_ref varchar(128),
	equipment_label varchar(255),
	serial_number varchar(128),
	qty double(24,8),
	technical_key varchar(255),
	position integer DEFAULT 0 NOT NULL,
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer,
	import_key varchar(14)
) ENGINE=innodb;
