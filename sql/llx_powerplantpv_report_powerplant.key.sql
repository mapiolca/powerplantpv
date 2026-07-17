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


ALTER TABLE llx_powerplantpv_report_powerplant ADD UNIQUE INDEX uk_powerplantpv_report_powerplant (entity, fk_report, fk_powerplant);
ALTER TABLE llx_powerplantpv_report_powerplant ADD INDEX idx_powerplantpv_report_powerplant_entity (entity);
ALTER TABLE llx_powerplantpv_report_powerplant ADD INDEX idx_powerplantpv_report_powerplant_report (fk_report);
ALTER TABLE llx_powerplantpv_report_powerplant ADD INDEX idx_powerplantpv_report_powerplant_powerplant (fk_powerplant);
ALTER TABLE llx_powerplantpv_report_powerplant ADD INDEX idx_powerplantpv_report_powerplant_soc (fk_soc);
ALTER TABLE llx_powerplantpv_report_powerplant ADD INDEX idx_powerplantpv_report_powerplant_project (fk_project);
