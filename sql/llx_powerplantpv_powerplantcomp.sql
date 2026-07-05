-- Copyright (C) 2025		Pierre Ardoin				<erp@lesmetiersdubatiment.fr>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.


CREATE TABLE IF NOT EXISTS llx_powerplantpv_powerplantcomp(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	fk_powerplant integer NOT NULL,
	fk_product integer NOT NULL,
	fk_status integer,
	qty double NOT NULL,
	serial_number varchar(128),
	commissioning_date date,
	entity integer NOT NULL DEFAULT 1
) ENGINE=innodb DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
