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


CREATE TABLE IF NOT EXISTS llx_powerplantpv_report_field(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_report integer NOT NULL,
	fk_report_section integer NOT NULL,
	fk_report_powerplant integer,
	fk_report_equipment integer,
	fk_report_template_field integer,
	stable_key varchar(255) NOT NULL,
	field_code varchar(64) NOT NULL,
	field_label varchar(255) NOT NULL,
	field_label_en varchar(255),
	field_description text,
	field_description_en text,
	field_type varchar(32) NOT NULL,
	scope_type varchar(32),
	unit varchar(32),
	default_value text,
	placeholder varchar(255),
	help text,
	options_snapshot mediumtext,
	value_text mediumtext,
	value_number double(24,8),
	value_date datetime,
	is_required smallint DEFAULT 0 NOT NULL,
	visible_form smallint DEFAULT 1 NOT NULL,
	visible_pdf smallint DEFAULT 1 NOT NULL,
	readonly smallint DEFAULT 0 NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer,
	import_key varchar(14)
) ENGINE=innodb;
