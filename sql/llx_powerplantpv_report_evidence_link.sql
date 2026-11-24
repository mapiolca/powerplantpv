CREATE TABLE IF NOT EXISTS llx_powerplantpv_report_evidence_link (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_report_file integer NOT NULL,
	token_hash varchar(64) NOT NULL,
	file_hash varchar(64) NOT NULL,
	date_creation datetime NOT NULL,
	expires_at datetime NOT NULL,
	fk_user_creat integer NOT NULL
) ENGINE=InnoDB;
