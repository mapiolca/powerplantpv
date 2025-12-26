-- Copyright (C) 2025		Pierre Ardoin				<erp@lesmetiersdubatiment.fr>
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


CREATE TABLE llx_powerplantpv_powerplant(
	-- BEGIN MODULEBUILDER FIELDS
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL, 
	ref varchar(128) NOT NULL, 
	label varchar(255), 
	commissioning_date date, 
	prm_pdl_number varchar(128), 
	address varchar(255), 
	zip varchar(25), 
	town varchar(255), 
	fk_country integer, 
	installed_power double, 
	connection_contract_power double, 
	connection_type varchar(128), 
	enedis_commissioning_date date, 
	connection_request_number varchar(128), 
	t0_obtention_date date, 
	buyback_contract_number varchar(128), 
	buyback_tariff double, 
	fk_soc integer, 
	fk_project integer, 
	description text, 
	note_public text, 
	note_private text, 
	date_creation datetime NOT NULL, 
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
	fk_user_creat integer NOT NULL, 
	fk_user_modif integer, 
	last_main_doc varchar(255), 
	import_key varchar(14), 
	model_pdf varchar(255), 
	status integer NOT NULL
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;
