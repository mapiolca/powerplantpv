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


CREATE TABLE llx_powerplantpv_report_source_service(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_report integer NOT NULL,
	fk_report_powerplant integer,
	fk_powerplant integer,
	fk_contract integer,
	contract_ref varchar(128),
	fk_contract_line integer,
	fk_product integer,
	product_ref varchar(128),
	product_label varchar(255),
	fk_maintenance_service integer NOT NULL,
	maintenance_service_code varchar(64),
	maintenance_service_label varchar(255),
	maintenance_service_label_en varchar(255),
	source_mode varchar(16) DEFAULT 'contract' NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer,
	import_key varchar(14)
) ENGINE=innodb;
