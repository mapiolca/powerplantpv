-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>

ALTER TABLE llx_powerplantpv_product_battery ADD UNIQUE INDEX uk_powerplantpv_product_battery_product_entity (fk_product, entity);
ALTER TABLE llx_powerplantpv_product_battery ADD INDEX idx_powerplantpv_product_battery_product (fk_product);
ALTER TABLE llx_powerplantpv_product_battery ADD INDEX idx_powerplantpv_product_battery_entity (entity);
ALTER TABLE llx_powerplantpv_product_battery ADD INDEX idx_powerplantpv_product_battery_type (storage_type);
