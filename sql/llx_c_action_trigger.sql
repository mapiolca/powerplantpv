-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
--
-- Populate business events for powerplantpv module.

UPDATE llx_c_action_trigger
SET label = CASE code
	WHEN 'POWERPLANTPV_POWERPLANT_CREATE' THEN 'PowerPlantTriggerCreate'
	WHEN 'POWERPLANTPV_POWERPLANT_MODIFY' THEN 'PowerPlantTriggerModify'
	WHEN 'POWERPLANTPV_POWERPLANT_DELETE' THEN 'PowerPlantTriggerDelete'
	WHEN 'POWERPLANTPV_POWERPLANT_VALIDATE' THEN 'PowerPlantTriggerValidate'
	WHEN 'POWERPLANTPV_POWERPLANT_UNVALIDATE' THEN 'PowerPlantTriggerUnvalidate'
	WHEN 'POWERPLANTPV_POWERPLANT_CANCEL' THEN 'PowerPlantTriggerCancel'
	WHEN 'POWERPLANTPV_POWERPLANT_REOPEN' THEN 'PowerPlantTriggerReopen'
	WHEN 'POWERPLANTPV_POWERPLANT_SENTBYMAIL' THEN 'PowerPlantTriggerSentByMail'
	WHEN 'POWERPLANTPV_POWERPLANT_INSERVICE' THEN 'PowerPlantTriggerInService'
	WHEN 'POWERPLANTPV_POWERPLANT_OUTOFSERVICE' THEN 'PowerPlantTriggerOutOfService'
	WHEN 'POWERPLANTPV_POWERPLANT_COMP_MODIFY' THEN 'PowerPlantCompTriggerModify'
	WHEN 'POWERPLANTPV_POWERPLANT_COMP_REPLACE' THEN 'PowerPlantCompTriggerReplace'
	WHEN 'POWERPLANTPV_POWERPLANT_COMP_INSERVICE' THEN 'PowerPlantCompTriggerInService'
	WHEN 'POWERPLANTPV_POWERPLANT_COMP_OUTOFSERVICE' THEN 'PowerPlantCompTriggerOutOfService'
	WHEN 'POWERPLANTPV_POWERPLANT_COMP_SERIAL' THEN 'PowerPlantCompTriggerSerial'
	WHEN 'POWERPLANTPV_POWERPLANT_COMP_COMMISSIONING' THEN 'PowerPlantCompTriggerCommissioning'
	WHEN 'POWERPLANTPV_POWERPLANT_COMP_SERIAL_IMPORT' THEN 'PowerPlantCompTriggerSerialImport'
	ELSE label
END,
description = CASE code
	WHEN 'POWERPLANTPV_POWERPLANT_CREATE' THEN 'PowerPlantTriggerCreateDesc'
	WHEN 'POWERPLANTPV_POWERPLANT_MODIFY' THEN 'PowerPlantTriggerModifyDesc'
	WHEN 'POWERPLANTPV_POWERPLANT_DELETE' THEN 'PowerPlantTriggerDeleteDesc'
	WHEN 'POWERPLANTPV_POWERPLANT_VALIDATE' THEN 'PowerPlantTriggerValidateDesc'
	WHEN 'POWERPLANTPV_POWERPLANT_UNVALIDATE' THEN 'PowerPlantTriggerUnvalidateDesc'
	WHEN 'POWERPLANTPV_POWERPLANT_CANCEL' THEN 'PowerPlantTriggerCancelDesc'
	WHEN 'POWERPLANTPV_POWERPLANT_REOPEN' THEN 'PowerPlantTriggerReopenDesc'
	WHEN 'POWERPLANTPV_POWERPLANT_SENTBYMAIL' THEN 'PowerPlantTriggerSentByMailDesc'
	WHEN 'POWERPLANTPV_POWERPLANT_INSERVICE' THEN 'PowerPlantTriggerInServiceDesc'
	WHEN 'POWERPLANTPV_POWERPLANT_OUTOFSERVICE' THEN 'PowerPlantTriggerOutOfServiceDesc'
	WHEN 'POWERPLANTPV_POWERPLANT_COMP_MODIFY' THEN 'PowerPlantCompTriggerModifyDesc'
	WHEN 'POWERPLANTPV_POWERPLANT_COMP_REPLACE' THEN 'PowerPlantCompTriggerReplaceDesc'
	WHEN 'POWERPLANTPV_POWERPLANT_COMP_INSERVICE' THEN 'PowerPlantCompTriggerInServiceDesc'
	WHEN 'POWERPLANTPV_POWERPLANT_COMP_OUTOFSERVICE' THEN 'PowerPlantCompTriggerOutOfServiceDesc'
	WHEN 'POWERPLANTPV_POWERPLANT_COMP_SERIAL' THEN 'PowerPlantCompTriggerSerialDesc'
	WHEN 'POWERPLANTPV_POWERPLANT_COMP_COMMISSIONING' THEN 'PowerPlantCompTriggerCommissioningDesc'
	WHEN 'POWERPLANTPV_POWERPLANT_COMP_SERIAL_IMPORT' THEN 'PowerPlantCompTriggerSerialImportDesc'
	ELSE description
END,
elementtype = 'powerplant@powerplantpv',
rang = CASE code
	WHEN 'POWERPLANTPV_POWERPLANT_CREATE' THEN 45000400
	WHEN 'POWERPLANTPV_POWERPLANT_MODIFY' THEN 45000401
	WHEN 'POWERPLANTPV_POWERPLANT_DELETE' THEN 45000402
	WHEN 'POWERPLANTPV_POWERPLANT_VALIDATE' THEN 45000403
	WHEN 'POWERPLANTPV_POWERPLANT_UNVALIDATE' THEN 45000404
	WHEN 'POWERPLANTPV_POWERPLANT_CANCEL' THEN 45000405
	WHEN 'POWERPLANTPV_POWERPLANT_REOPEN' THEN 45000406
	WHEN 'POWERPLANTPV_POWERPLANT_SENTBYMAIL' THEN 45000407
	WHEN 'POWERPLANTPV_POWERPLANT_INSERVICE' THEN 45000408
	WHEN 'POWERPLANTPV_POWERPLANT_OUTOFSERVICE' THEN 45000409
	WHEN 'POWERPLANTPV_POWERPLANT_COMP_MODIFY' THEN 45000410
	WHEN 'POWERPLANTPV_POWERPLANT_COMP_REPLACE' THEN 45000411
	WHEN 'POWERPLANTPV_POWERPLANT_COMP_INSERVICE' THEN 45000412
	WHEN 'POWERPLANTPV_POWERPLANT_COMP_OUTOFSERVICE' THEN 45000413
	WHEN 'POWERPLANTPV_POWERPLANT_COMP_SERIAL' THEN 45000414
	WHEN 'POWERPLANTPV_POWERPLANT_COMP_COMMISSIONING' THEN 45000415
	WHEN 'POWERPLANTPV_POWERPLANT_COMP_SERIAL_IMPORT' THEN 45000416
	ELSE rang
END
WHERE code IN ('POWERPLANTPV_POWERPLANT_CREATE', 'POWERPLANTPV_POWERPLANT_MODIFY', 'POWERPLANTPV_POWERPLANT_DELETE', 'POWERPLANTPV_POWERPLANT_VALIDATE', 'POWERPLANTPV_POWERPLANT_UNVALIDATE', 'POWERPLANTPV_POWERPLANT_CANCEL', 'POWERPLANTPV_POWERPLANT_REOPEN', 'POWERPLANTPV_POWERPLANT_SENTBYMAIL', 'POWERPLANTPV_POWERPLANT_INSERVICE', 'POWERPLANTPV_POWERPLANT_OUTOFSERVICE', 'POWERPLANTPV_POWERPLANT_COMP_MODIFY', 'POWERPLANTPV_POWERPLANT_COMP_REPLACE', 'POWERPLANTPV_POWERPLANT_COMP_INSERVICE', 'POWERPLANTPV_POWERPLANT_COMP_OUTOFSERVICE', 'POWERPLANTPV_POWERPLANT_COMP_SERIAL', 'POWERPLANTPV_POWERPLANT_COMP_COMMISSIONING', 'POWERPLANTPV_POWERPLANT_COMP_SERIAL_IMPORT');

UPDATE llx_c_action_trigger
SET label = CASE code
	WHEN 'POWERPLANTPV_ATTESTATION_CREATE' THEN 'AttestationTriggerCreate'
	WHEN 'POWERPLANTPV_ATTESTATION_VALIDATE' THEN 'AttestationTriggerValidate'
	WHEN 'POWERPLANTPV_ATTESTATION_GENERATEPDF' THEN 'AttestationTriggerGeneratePdf'
	WHEN 'POWERPLANTPV_ATTESTATION_SENDSIGN' THEN 'AttestationTriggerSendSign'
	WHEN 'POWERPLANTPV_ATTESTATION_SIGN' THEN 'AttestationTriggerSign'
	WHEN 'POWERPLANTPV_ATTESTATION_CANCEL' THEN 'AttestationTriggerCancel'
	WHEN 'POWERPLANTPV_ATTESTATION_DELETE' THEN 'AttestationTriggerDelete'
	ELSE label
END,
description = CASE code
	WHEN 'POWERPLANTPV_ATTESTATION_CREATE' THEN 'AttestationTriggerCreateDesc'
	WHEN 'POWERPLANTPV_ATTESTATION_VALIDATE' THEN 'AttestationTriggerValidateDesc'
	WHEN 'POWERPLANTPV_ATTESTATION_GENERATEPDF' THEN 'AttestationTriggerGeneratePdfDesc'
	WHEN 'POWERPLANTPV_ATTESTATION_SENDSIGN' THEN 'AttestationTriggerSendSignDesc'
	WHEN 'POWERPLANTPV_ATTESTATION_SIGN' THEN 'AttestationTriggerSignDesc'
	WHEN 'POWERPLANTPV_ATTESTATION_CANCEL' THEN 'AttestationTriggerCancelDesc'
	WHEN 'POWERPLANTPV_ATTESTATION_DELETE' THEN 'AttestationTriggerDeleteDesc'
	ELSE description
END,
elementtype = 'attestation@powerplantpv',
rang = CASE code
	WHEN 'POWERPLANTPV_ATTESTATION_CREATE' THEN 45000430
	WHEN 'POWERPLANTPV_ATTESTATION_VALIDATE' THEN 45000431
	WHEN 'POWERPLANTPV_ATTESTATION_GENERATEPDF' THEN 45000432
	WHEN 'POWERPLANTPV_ATTESTATION_SENDSIGN' THEN 45000433
	WHEN 'POWERPLANTPV_ATTESTATION_SIGN' THEN 45000434
	WHEN 'POWERPLANTPV_ATTESTATION_CANCEL' THEN 45000435
	WHEN 'POWERPLANTPV_ATTESTATION_DELETE' THEN 45000436
	ELSE rang
END
WHERE code IN ('POWERPLANTPV_ATTESTATION_CREATE', 'POWERPLANTPV_ATTESTATION_VALIDATE', 'POWERPLANTPV_ATTESTATION_GENERATEPDF', 'POWERPLANTPV_ATTESTATION_SENDSIGN', 'POWERPLANTPV_ATTESTATION_SIGN', 'POWERPLANTPV_ATTESTATION_CANCEL', 'POWERPLANTPV_ATTESTATION_DELETE');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_POWERPLANT_CREATE', 'PowerPlantTriggerCreate', 'PowerPlantTriggerCreateDesc', 'powerplant@powerplantpv', 45000400
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_POWERPLANT_CREATE');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_POWERPLANT_MODIFY', 'PowerPlantTriggerModify', 'PowerPlantTriggerModifyDesc', 'powerplant@powerplantpv', 45000401
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_POWERPLANT_MODIFY');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_POWERPLANT_DELETE', 'PowerPlantTriggerDelete', 'PowerPlantTriggerDeleteDesc', 'powerplant@powerplantpv', 45000402
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_POWERPLANT_DELETE');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_POWERPLANT_VALIDATE', 'PowerPlantTriggerValidate', 'PowerPlantTriggerValidateDesc', 'powerplant@powerplantpv', 45000403
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_POWERPLANT_VALIDATE');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_POWERPLANT_UNVALIDATE', 'PowerPlantTriggerUnvalidate', 'PowerPlantTriggerUnvalidateDesc', 'powerplant@powerplantpv', 45000404
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_POWERPLANT_UNVALIDATE');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_POWERPLANT_CANCEL', 'PowerPlantTriggerCancel', 'PowerPlantTriggerCancelDesc', 'powerplant@powerplantpv', 45000405
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_POWERPLANT_CANCEL');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_POWERPLANT_REOPEN', 'PowerPlantTriggerReopen', 'PowerPlantTriggerReopenDesc', 'powerplant@powerplantpv', 45000406
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_POWERPLANT_REOPEN');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_POWERPLANT_SENTBYMAIL', 'PowerPlantTriggerSentByMail', 'PowerPlantTriggerSentByMailDesc', 'powerplant@powerplantpv', 45000407
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_POWERPLANT_SENTBYMAIL');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_POWERPLANT_INSERVICE', 'PowerPlantTriggerInService', 'PowerPlantTriggerInServiceDesc', 'powerplant@powerplantpv', 45000408
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_POWERPLANT_INSERVICE');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_POWERPLANT_OUTOFSERVICE', 'PowerPlantTriggerOutOfService', 'PowerPlantTriggerOutOfServiceDesc', 'powerplant@powerplantpv', 45000409
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_POWERPLANT_OUTOFSERVICE');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_POWERPLANT_COMP_MODIFY', 'PowerPlantCompTriggerModify', 'PowerPlantCompTriggerModifyDesc', 'powerplant@powerplantpv', 45000410
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_POWERPLANT_COMP_MODIFY');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_POWERPLANT_COMP_REPLACE', 'PowerPlantCompTriggerReplace', 'PowerPlantCompTriggerReplaceDesc', 'powerplant@powerplantpv', 45000411
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_POWERPLANT_COMP_REPLACE');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_POWERPLANT_COMP_INSERVICE', 'PowerPlantCompTriggerInService', 'PowerPlantCompTriggerInServiceDesc', 'powerplant@powerplantpv', 45000412
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_POWERPLANT_COMP_INSERVICE');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_POWERPLANT_COMP_OUTOFSERVICE', 'PowerPlantCompTriggerOutOfService', 'PowerPlantCompTriggerOutOfServiceDesc', 'powerplant@powerplantpv', 45000413
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_POWERPLANT_COMP_OUTOFSERVICE');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_POWERPLANT_COMP_SERIAL', 'PowerPlantCompTriggerSerial', 'PowerPlantCompTriggerSerialDesc', 'powerplant@powerplantpv', 45000414
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_POWERPLANT_COMP_SERIAL');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_POWERPLANT_COMP_COMMISSIONING', 'PowerPlantCompTriggerCommissioning', 'PowerPlantCompTriggerCommissioningDesc', 'powerplant@powerplantpv', 45000415
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_POWERPLANT_COMP_COMMISSIONING');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_POWERPLANT_COMP_SERIAL_IMPORT', 'PowerPlantCompTriggerSerialImport', 'PowerPlantCompTriggerSerialImportDesc', 'powerplant@powerplantpv', 45000416
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_POWERPLANT_COMP_SERIAL_IMPORT');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_ATTESTATION_CREATE', 'AttestationTriggerCreate', 'AttestationTriggerCreateDesc', 'attestation@powerplantpv', 45000430
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_ATTESTATION_CREATE');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_ATTESTATION_VALIDATE', 'AttestationTriggerValidate', 'AttestationTriggerValidateDesc', 'attestation@powerplantpv', 45000431
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_ATTESTATION_VALIDATE');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_ATTESTATION_GENERATEPDF', 'AttestationTriggerGeneratePdf', 'AttestationTriggerGeneratePdfDesc', 'attestation@powerplantpv', 45000432
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_ATTESTATION_GENERATEPDF');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_ATTESTATION_SENDSIGN', 'AttestationTriggerSendSign', 'AttestationTriggerSendSignDesc', 'attestation@powerplantpv', 45000433
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_ATTESTATION_SENDSIGN');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_ATTESTATION_SIGN', 'AttestationTriggerSign', 'AttestationTriggerSignDesc', 'attestation@powerplantpv', 45000434
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_ATTESTATION_SIGN');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_ATTESTATION_CANCEL', 'AttestationTriggerCancel', 'AttestationTriggerCancelDesc', 'attestation@powerplantpv', 45000435
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_ATTESTATION_CANCEL');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_ATTESTATION_DELETE', 'AttestationTriggerDelete', 'AttestationTriggerDeleteDesc', 'attestation@powerplantpv', 45000436
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_ATTESTATION_DELETE');
