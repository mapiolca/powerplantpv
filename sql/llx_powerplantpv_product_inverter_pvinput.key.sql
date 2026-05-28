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


ALTER TABLE llx_powerplantpv_product_inverter_pvinput ADD INDEX idx_powerplantpv_product_inverter_pvinput_rowid (rowid);
ALTER TABLE llx_powerplantpv_product_inverter_pvinput ADD INDEX idx_powerplantpv_product_inverter_pvinput_fk_mppt (fk_mppt);
ALTER TABLE llx_powerplantpv_product_inverter_pvinput ADD UNIQUE INDEX uk_powerplantpv_product_inverter_pvinput_position (fk_mppt, entity, position);
ALTER TABLE llx_powerplantpv_product_inverter_pvinput ADD INDEX idx_powerplantpv_product_inverter_pvinput_entity (entity);
