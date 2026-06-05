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


ALTER TABLE llx_powerplantpv_serialnumber ADD UNIQUE INDEX uk_powerplantpv_serialnumber_powerplant_serial (entity, fk_powerplant, serial_number);
ALTER TABLE llx_powerplantpv_serialnumber ADD INDEX idx_powerplantpv_serialnumber_entity (entity);
ALTER TABLE llx_powerplantpv_serialnumber ADD INDEX idx_powerplantpv_serialnumber_fk_powerplant (fk_powerplant);
ALTER TABLE llx_powerplantpv_serialnumber ADD INDEX idx_powerplantpv_serialnumber_fk_powerplant_line (fk_powerplant_line);
ALTER TABLE llx_powerplantpv_serialnumber ADD INDEX idx_powerplantpv_serialnumber_fk_product (fk_product);
ALTER TABLE llx_powerplantpv_serialnumber ADD INDEX idx_powerplantpv_serialnumber_fk_categorie (fk_categorie);
ALTER TABLE llx_powerplantpv_serialnumber ADD INDEX idx_powerplantpv_serialnumber_import_batch (import_batch);
