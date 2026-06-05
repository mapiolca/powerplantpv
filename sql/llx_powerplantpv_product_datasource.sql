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


CREATE TABLE llx_powerplantpv_product_datasource (
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer NOT NULL DEFAULT 1,
	fk_product integer NOT NULL,
	source varchar(64) NOT NULL,
	source_dataset varchar(64) DEFAULT NULL,
	source_key varchar(255) NOT NULL,
	source_name varchar(255) DEFAULT NULL,
	source_url varchar(1024) DEFAULT NULL,
	filename varchar(255) DEFAULT NULL,
	raw_json mediumtext,
	normalized_json mediumtext,
	import_status varchar(32) NOT NULL DEFAULT 'imported',
	datec datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer NOT NULL,
	fk_user_modif integer
) ENGINE=innodb DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
