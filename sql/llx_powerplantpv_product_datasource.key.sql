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


ALTER TABLE llx_powerplantpv_product_datasource ADD UNIQUE INDEX uk_powerplantpv_product_datasource_source (entity, source, source_dataset, source_key);
ALTER TABLE llx_powerplantpv_product_datasource ADD INDEX idx_powerplantpv_product_datasource_product (fk_product);
ALTER TABLE llx_powerplantpv_product_datasource ADD INDEX idx_powerplantpv_product_datasource_entity (entity);
ALTER TABLE llx_powerplantpv_product_datasource ADD INDEX idx_powerplantpv_product_datasource_source (source);
