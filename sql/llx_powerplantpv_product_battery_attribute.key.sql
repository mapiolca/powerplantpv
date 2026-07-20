-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>

ALTER TABLE llx_powerplantpv_product_battery_attribute ADD UNIQUE INDEX uk_powerplantpv_battery_attribute (entity, fk_battery, attribute_type, attribute_code);
ALTER TABLE llx_powerplantpv_product_battery_attribute ADD INDEX idx_powerplantpv_battery_attribute_parent (fk_battery);
ALTER TABLE llx_powerplantpv_product_battery_attribute ADD INDEX idx_powerplantpv_battery_attribute_type (attribute_type, attribute_code);
ALTER TABLE llx_powerplantpv_product_battery_attribute ADD INDEX idx_powerplantpv_battery_attribute_entity (entity);
