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


-- BEGIN MODULEBUILDER INDEXES
ALTER TABLE llx_powerplantpv_product_pvpanel ADD INDEX idx_powerplantpv_product_pvpanel_rowid (rowid);
ALTER TABLE llx_powerplantpv_product_pvpanel ADD INDEX idx_powerplantpv_product_pvpanel_fk_product (fk_product);
ALTER TABLE llx_powerplantpv_product_pvpanel ADD INDEX idx_powerplantpv_product_pvpanel_entity (entity);
-- END MODULEBUILDER INDEXES
