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


CREATE TABLE IF NOT EXISTS llx_powerplantpv_product_inverter(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	fk_product integer NOT NULL,
	entity integer NOT NULL DEFAULT 1,
	pv_max_power double(24,8),
	dc_max_voltage double(24,8),
	startup_voltage double(24,8),
	mppt_voltage_min double(24,8),
	mppt_voltage_max double(24,8),
	nominal_dc_voltage double(24,8),
	ac_nominal_power double(24,8),
	ac_max_power double(24,8),
	ac_apparent_power double(24,8),
	ac_nominal_voltage varchar(128),
	grid_frequency varchar(64),
	ac_max_output_current double(24,8),
	power_factor varchar(64),
	thd varchar(64),
	max_efficiency double(6,3),
	european_efficiency double(6,3),
	dc_switch smallint,
	dc_spd varchar(128),
	ac_spd varchar(128),
	afci smallint,
	pid_recovery smallint,
	anti_islanding smallint,
	dc_reverse_polarity_protection smallint,
	insulation_monitoring smallint,
	residual_current_monitoring smallint,
	ip_rating varchar(64),
	operating_temperature varchar(128),
	relative_humidity varchar(128),
	cooling varchar(128),
	max_altitude integer,
	noise varchar(64),
	topology varchar(128),
	night_consumption varchar(64),
	display_type varchar(128),
	communication_interfaces varchar(255),
	dc_connector varchar(128),
	ac_connector varchar(128),
	mounting varchar(128),
	warranty varchar(128),
	certifications text,
	datec datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer
) ENGINE=innodb;
