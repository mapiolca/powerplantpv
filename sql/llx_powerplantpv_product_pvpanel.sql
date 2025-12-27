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


CREATE TABLE llx_powerplantpv_product_pvpanel(
	-- BEGIN MODULEBUILDER FIELDS
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	fk_product integer NOT NULL,
	pmax double,
	power_tolerance double,
	module_efficiency double,
	vmp double,
	imp double,
	voc double,
	isc double,
	front_glass_thickness double,
	back_glass_thickness double,
	cable_section double,
	cable_length integer,
	operating_temperature double,
	max_system_voltage double,
	max_series_fuse double,
	snow_load double,
	wind_load double,
	noct double,
	temp_coeff_pmax double,
	temp_coeff_voc double,
	temp_coeff_isc double,
	first_year_degradation double,
	annual_degradation double,
	product_warranty integer,
	power_warranty integer,
	modules_per_box integer,
	modules_per_container40 integer
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;
