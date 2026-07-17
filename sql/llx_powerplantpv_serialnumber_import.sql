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


CREATE TABLE IF NOT EXISTS llx_powerplantpv_serialnumber_import(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_powerplant integer NOT NULL,
	fk_categorie integer NOT NULL,
	fk_user integer NOT NULL,
	filename varchar(255) NOT NULL,
	filepath varchar(1024),
	status varchar(16) DEFAULT 'draft' NOT NULL,
	import_mode varchar(16) DEFAULT 'add' NOT NULL,
	first_line_headers smallint DEFAULT 1 NOT NULL,
	raw_data_json mediumtext,
	parsed_data_json mediumtext,
	errors_json mediumtext,
	datec datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
