-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
--
-- Populate business events for powerplantpv module.

UPDATE llx_c_action_trigger
SET elementtype = 'powerplant@powerplantpv'
WHERE code IN ('POWERPLANTPV_POWERPLANT_CREATE', 'POWERPLANTPV_POWERPLANT_VALIDATE', 'POWERPLANTPV_POWERPLANT_SENTBYMAIL', 'POWERPLANTPV_POWERPLANT_INSERVICE', 'POWERPLANTPV_POWERPLANT_OUTOFSERVICE', 'POWERPLANTPV_POWERPLANT_REOPEN', 'POWERPLANTPV_POWERPLANT_DELETE');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_POWERPLANT_CREATE', 'Création centrale', 'Déclenché quand une centrale est créée.', 'powerplant@powerplantpv', 45000400
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_POWERPLANT_CREATE');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_POWERPLANT_VALIDATE', 'Validation Centrale', 'Déclenché quand une centrale passe au statut validé.', 'powerplant@powerplantpv', 45000401
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_POWERPLANT_VALIDATE');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_POWERPLANT_SENTBYMAIL', 'Envoi e-mail de Centrale', 'Déclenché quand un e-mail est envoyé depuis une centrale.', 'powerplant@powerplantpv', 45000402
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_POWERPLANT_SENTBYMAIL');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_POWERPLANT_INSERVICE', 'Centrale mise en service', 'Déclenché quand une centrale est mise en service.', 'powerplant@powerplantpv', 45000403
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_POWERPLANT_INSERVICE');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_POWERPLANT_OUTOFSERVICE', 'entrale mise hors service', 'Déclenché quand une centrale est mise hors service.', 'powerplant@powerplantpv', 45000403
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_POWERPLANT_OUTOFSERVICE');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_POWERPLANT_REOPEN', 'Retour brouillon centrale', 'Déclenché quand une centrale repasse en brouillon.', 'powerplant@powerplantpv', 45000404
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_POWERPLANT_REOPEN');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'POWERPLANTPV_POWERPLANT_DELETE', 'Suppression centrale', 'Déclenché quand une centrale est supprimée.', 'powerplant@powerplantpv', 45000405
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'POWERPLANTPV_POWERPLANT_DELETE');