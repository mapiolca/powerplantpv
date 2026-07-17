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


ALTER TABLE llx_powerplantpv_report_template ADD UNIQUE INDEX uk_powerplantpv_report_template_code (entity, code);
ALTER TABLE llx_powerplantpv_report_template ADD INDEX idx_powerplantpv_report_template_entity (entity);
ALTER TABLE llx_powerplantpv_report_template ADD INDEX idx_powerplantpv_report_template_active (active);
ALTER TABLE llx_powerplantpv_report_template ADD INDEX idx_powerplantpv_report_template_default (entity, is_default);
ALTER TABLE llx_powerplantpv_report_template ADD INDEX idx_powerplantpv_report_template_target (target_element);
