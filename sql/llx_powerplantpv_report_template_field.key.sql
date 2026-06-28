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


ALTER TABLE llx_powerplantpv_report_template_field ADD UNIQUE INDEX uk_powerplantpv_report_template_field_code (entity, report_template_code, code);
ALTER TABLE llx_powerplantpv_report_template_field ADD INDEX idx_powerplantpv_report_template_field_entity (entity);
ALTER TABLE llx_powerplantpv_report_template_field ADD INDEX idx_powerplantpv_report_template_field_fk_template (fk_report_template);
ALTER TABLE llx_powerplantpv_report_template_field ADD INDEX idx_powerplantpv_report_template_field_fk_section (fk_report_template_section);
ALTER TABLE llx_powerplantpv_report_template_field ADD INDEX idx_powerplantpv_report_template_field_section (fk_report_section);
ALTER TABLE llx_powerplantpv_report_template_field ADD INDEX idx_powerplantpv_report_template_field_service (fk_maintenance_service);
ALTER TABLE llx_powerplantpv_report_template_field ADD INDEX idx_powerplantpv_report_template_field_template (report_template_code);
