-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>

ALTER TABLE llx_powerplantpv_product_battery_accessory ADD UNIQUE INDEX uk_powerplantpv_battery_accessory_product_entity (fk_product, entity);
ALTER TABLE llx_powerplantpv_product_battery_accessory ADD INDEX idx_powerplantpv_battery_accessory_product (fk_product);
ALTER TABLE llx_powerplantpv_product_battery_accessory ADD INDEX idx_powerplantpv_battery_accessory_entity (entity);
ALTER TABLE llx_powerplantpv_product_battery_accessory ADD INDEX idx_powerplantpv_battery_accessory_role (role_code);
