# Compatibility

Copyright (C) 2026  Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>

## Version minimum

Dolibarr v20.0.

## Fonctionnalites avec compatibilite limitee

| Fonctionnalite | Version requise | Raison | Impact si version inferieure |
|---|---:|---|---|
| Ligne `Centrale` dans `categories/index.php` | v23.0 | La page globale des categories v23 parcourt `Categorie::$MAP_ID` et tient compte du hook `constructCategory`. | En v20-v22, les categories restent utilisables sur la fiche centrale, mais la ligne n'apparait pas dans l'index global natif. |

## Fonctionnalites avec retrocompatibilite

| Fonctionnalite | Version native | Retrocompatibilite | Fichier |
|---|---:|---|---|
| Selection des categories dans une fiche objet | v23.0 | Fallback local vers `select_all_categories()` + `multiselectarray()` quand `Form::selectCategories()` est absent. | `lib/powerplantpv_powerplant.lib.php` |
| Liaison multi-projets | v20.0 | Utilise `llx_element_element`; l'ancien `fk_project` est conserve en base et migre en lien objet a l'activation. | `core/modules/modPowerPlantPV.class.php` |
