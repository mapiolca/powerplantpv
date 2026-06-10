-- Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE llx_powerplantpv_attestation(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer NOT NULL DEFAULT 1,
	ref varchar(128) NOT NULL,
	fk_powerplant integer,
	fk_soc integer,
	fk_project integer,
	type_code varchar(64) NOT NULL,
	model_pdf varchar(128),
	date_attestation date,
	date_setting date,
	date_completion date,
	bta_contract_number varchar(128),
	max_export_power_kw double(24,8),
	max_frequency_hz double(24,8),
	landscape_integration_prime smallint DEFAULT 0,
	fk_user_sign integer,
	date_signature datetime,
	signature_ip varchar(64),
	signature_user_agent varchar(255),
	signature_hash varchar(128),
	signature_token_hash varchar(128),
	signature_token_date datetime,
	signature_token_expiry datetime,
	signature_file varchar(255),
	signed_pdf_file varchar(255),
	note_public text,
	note_private text,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer NOT NULL,
	fk_user_modif integer,
	last_main_doc varchar(255),
	import_key varchar(14),
	status integer NOT NULL DEFAULT 0
) ENGINE=innodb DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
