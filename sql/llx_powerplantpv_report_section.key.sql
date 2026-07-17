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


ALTER TABLE llx_powerplantpv_report_section ADD UNIQUE INDEX uk_powerplantpv_report_section_occurrence (entity, fk_report, occurrence_key);
ALTER TABLE llx_powerplantpv_report_section ADD INDEX idx_powerplantpv_report_section_entity (entity);
ALTER TABLE llx_powerplantpv_report_section ADD INDEX idx_powerplantpv_report_section_report (fk_report);
ALTER TABLE llx_powerplantpv_report_section ADD INDEX idx_powerplantpv_report_section_powerplant (fk_report_powerplant);
ALTER TABLE llx_powerplantpv_report_section ADD INDEX idx_powerplantpv_report_section_equipment (fk_report_equipment);
ALTER TABLE llx_powerplantpv_report_section ADD INDEX idx_powerplantpv_report_section_template_section (fk_report_template_section);
ALTER TABLE llx_powerplantpv_report_section ADD INDEX idx_powerplantpv_report_section_code (section_code);
ALTER TABLE llx_powerplantpv_report_section ADD INDEX idx_powerplantpv_report_section_scope (scope_type);
