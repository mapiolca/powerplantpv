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


CREATE TABLE llx_powerplantpv_report(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_fichinter integer NOT NULL,
	fk_soc integer,
	fk_project integer,
	fk_intervention_nature integer,
	intervention_nature_code varchar(64),
	intervention_nature_label varchar(255),
	intervention_nature_label_en varchar(255),
	fk_report_template integer,
	report_template_code varchar(64),
	report_template_label varchar(255),
	report_template_label_en varchar(255),
	source_mode varchar(16) DEFAULT 'contract' NOT NULL,
	status varchar(16) DEFAULT 'draft' NOT NULL,
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer,
	import_key varchar(14)
) ENGINE=innodb;
