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


ALTER TABLE llx_powerplantpv_index_reading ADD UNIQUE INDEX uk_powerplantpv_index_reading_report_source (entity, fk_powerplant, fk_fichinter_source, fk_report, reading_type_code, meter_ref, fk_report_equipment);
ALTER TABLE llx_powerplantpv_index_reading ADD INDEX idx_powerplantpv_index_reading_entity (entity);
ALTER TABLE llx_powerplantpv_index_reading ADD INDEX idx_powerplantpv_index_reading_powerplant (fk_powerplant);
ALTER TABLE llx_powerplantpv_index_reading ADD INDEX idx_powerplantpv_index_reading_fichinter (fk_fichinter_source);
ALTER TABLE llx_powerplantpv_index_reading ADD INDEX idx_powerplantpv_index_reading_report (fk_report);
ALTER TABLE llx_powerplantpv_index_reading ADD INDEX idx_powerplantpv_index_reading_report_powerplant (fk_report_powerplant);
ALTER TABLE llx_powerplantpv_index_reading ADD INDEX idx_powerplantpv_index_reading_report_equipment (fk_report_equipment);
ALTER TABLE llx_powerplantpv_index_reading ADD INDEX idx_powerplantpv_index_reading_index_type (fk_index_type);
ALTER TABLE llx_powerplantpv_index_reading ADD INDEX idx_powerplantpv_index_reading_type_date (reading_type_code, reading_date);
ALTER TABLE llx_powerplantpv_index_reading ADD INDEX idx_powerplantpv_index_reading_source (source_type);
ALTER TABLE llx_powerplantpv_index_reading ADD INDEX idx_powerplantpv_index_reading_active (active);
ALTER TABLE llx_powerplantpv_index_reading ADD INDEX idx_powerplantpv_index_reading_user_creat (fk_user_creat);
