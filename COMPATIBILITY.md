# Compatibility

Copyright (C) 2026  Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>

## Version minimum

Dolibarr v20.0.

## Multicompany

- Les centrales utilisent la colonne `entity` et suivent le périmètre natif `getEntity('powerplant')`.
- La référence d'une centrale est unique dans le périmètre d'entités partagées par Multicompany, mais peut être réutilisée dans deux entités non partagées.
- La numérotation des centrales expose le partage Multicompany `powerplantnumber`; le contrôle de séquence utilise l'union de `getEntity('powerplant')` et `getEntity('powerplantnumber')`.
- Les lignes de composition matérielle sont rattachées à l'entité propriétaire de la centrale.
- Les numéros de série importés utilisent `llx_powerplantpv_serialnumber.entity` et sont rattachés à la centrale, à la ligne de composition, au produit et à la catégorie PV.
- Le dictionnaire photovoltaïque `c_powerplantpv_categorypv` reste déclaré comme dictionnaire Multicompany personnalisable par entité.

## Fonctionnalités avec compatibilité limitée

| Fonctionnalité | Version requise | Raison | Impact si version inférieure |
|---|---:|---|---|
| Ligne `Centrale` dans `categories/index.php` | v23.0 | La page globale des catégories v23 parcourt `Categorie::$MAP_ID` et tient compte du hook `constructCategory`. | En v20-v22, les catégories restent utilisables sur la fiche centrale, mais la ligne n'apparaît pas dans l'index global natif. |
| Import et export XLSX des numéros de série | v20.0 + PhpSpreadsheet disponible | L'analyse et la génération XLSX utilisent la bibliothèque PhpSpreadsheet livrée par Dolibarr. | Si PhpSpreadsheet est absent ou non chargeable, l'import CSV et l'export CSV restent disponibles et l'onglet Compatibilité indique l'indisponibilité XLSX. |

## Fonctionnalités avec rétrocompatibilité

| Fonctionnalité | Version native | Rétrocompatibilité | Fichier |
|---|---:|---|---|
| Sélection des catégories dans une fiche objet | v23.0 | Fallback local vers `select_all_categories()` + `multiselectarray()` quand `Form::selectCategories()` est absent. | `lib/powerplantpv_powerplant.lib.php` |
| Liaison multi-projets | v20.0 | Utilise `llx_element_element`; l'ancien `fk_project` est conservé en base et migré en lien objet à l'activation. | `core/modules/modPowerPlantPV.class.php` |
| Import CSV des numéros de série | v20.0 | Lecture CSV dédiée au module avec `fgetcsv()` et validation métier PowerPlantPV. | `lib/powerplantpv_serialnumber.lib.php` |
