-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
-- SPDX-License-Identifier: GPL-3.0-or-later
-- Global photovoltaic consumption modes, shared by all entities.
CREATE TABLE IF NOT EXISTS llx_c_powerplantpv_connection_type (
 rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
 code varchar(128) NOT NULL,
 label varchar(255) NOT NULL,
 active smallint DEFAULT 1 NOT NULL,
 position integer DEFAULT 0 NOT NULL
) ENGINE=innodb;
