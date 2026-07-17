-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>

ALTER TABLE llx_powerplantpv_maintenance_widget_user ADD UNIQUE INDEX uk_powerplantpv_maintenance_widget_user (entity, fk_user, widget_code);
ALTER TABLE llx_powerplantpv_maintenance_widget_user ADD INDEX idx_powerplantpv_maintenance_widget_user_entity (entity);
ALTER TABLE llx_powerplantpv_maintenance_widget_user ADD INDEX idx_powerplantpv_maintenance_widget_user_user (fk_user);
ALTER TABLE llx_powerplantpv_maintenance_widget_user ADD INDEX idx_powerplantpv_maintenance_widget_user_position (entity, fk_user, column_index, position);
