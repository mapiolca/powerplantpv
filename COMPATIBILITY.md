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
- Les sources PV Free importées utilisent `llx_powerplantpv_product_datasource.entity` et restent rattachées au produit Dolibarr enrichi.
- Les sources CSV/XLSX importées utilisent aussi `llx_powerplantpv_product_datasource.entity` et restent rattachées au produit Dolibarr enrichi.
- Les attestations utilisent la colonne `entity` et suivent le périmètre natif `getEntity('attestation')`.
- La numérotation des attestations expose le partage Multicompany `attestationnumber`; le contrôle de séquence utilise l'union de `getEntity('attestation')` et `getEntity('attestationnumber')`.
- Les fichiers d'attestation utilisent le répertoire documentaire de l'entité propriétaire via `getMultidirOutput()` avec fallback sur `multidir_output[$object->entity]`.
- Le dictionnaire photovoltaïque `c_powerplantpv_categorypv` reste déclaré comme dictionnaire Multicompany personnalisable par entité.
- Les dictionnaires maintenance `c_powerplantpv_intervention_nature`, `c_powerplantpv_maintenance_service` et `c_powerplantpv_index_type` portent `entity` et sont déclarés comme dictionnaires Multicompany personnalisables par entité.
- Le dictionnaire historique `c_powerplantpv_report_section` porte toujours `entity`, mais il n'est plus exposé comme dictionnaire d'administration actif depuis le moteur de modèles de rapports.
- Les modèles de rapports, sections, champs, options de champs et mappings prestations -> sections portent `entity` et sont administrés séparément par entité.
- Les liaisons maintenance stockent uniquement des clés `fk_*` vers les prestations, modèles et sections ; les libellés restent dans les tables sources.

## Fonctionnalités avec compatibilité limitée

| Fonctionnalité | Version requise | Raison | Impact si version inférieure |
|---|---:|---|---|
| Ligne `Centrale` dans `categories/index.php` | v23.0 | La page globale des catégories v23 parcourt `Categorie::$MAP_ID` et tient compte du hook `constructCategory`. | En v20-v22, les catégories restent utilisables sur la fiche centrale, mais la ligne n'apparaît pas dans l'index global natif. |
| Import et export XLSX des numéros de série | v20.0 + PhpSpreadsheet disponible | L'analyse et la génération XLSX utilisent la bibliothèque PhpSpreadsheet livrée par Dolibarr. | Si PhpSpreadsheet est absent ou non chargeable, l'import CSV et l'export CSV restent disponibles et l'onglet Compatibilité indique l'indisponibilité XLSX. |
| Connecteur PV Free | v20.0 + accès HTTPS sortant | Les appels passent par `getURLContent()` et ne sont déclenchés que par action utilisateur. | Si PV Free est désactivé ou inaccessible, aucun appel automatique n'est fait et l'import reste indisponible. |
| Import XLSX des caractéristiques produit | v20.0 + lecteur XLSX disponible | L'analyse XLSX utilise d'abord une lecture ZIP/XML native, puis PhpSpreadsheet si disponible. | Si aucun lecteur XLSX n'est disponible, l'import CSV reste disponible et l'onglet Compatibilité indique l'indisponibilité XLSX. |
| Socle données maintenance | v20.0 | Dictionnaires, droits et extrafields utilisent les APIs Dolibarr disponibles en v20. | Le socle, l'onglet Rapport, le PDF dynamique et l'avancement automatique sont disponibles. |
| Onglet Maintenance centrale et moteur d'échéances | v20.0 | Le calcul s'appuie sur les liens natifs `element_element`, les contrats validés, les lignes de contrat ouvertes, les extrafields de période et les interventions Dolibarr v20. | La prochaine période initiale doit être configurée sur le contrat ; après couverture, l'avancement automatique applique la récurrence configurée. |
| Moteur de modèles de rapports | v20.0 | Les classes métier, tables normalisées et pages d'administration utilisent les APIs Dolibarr disponibles en v20 et PHP 8.0. | L'administration des modèles, l'onglet Rapport et la génération PDF dynamique sont disponibles. |
| Attestations PowerPlantPV | v20.0 | L'objet, les listes, les documents, les triggers, les notifications et les réglages utilisent les APIs Dolibarr disponibles en v20. | La fonctionnalité est masquée si le module est désactivé ou si les droits dédiés sont absents. |
| Modèles PDF d'attestation | v20.0 | Les modèles héritent du générateur PDF Dolibarr et écrivent dans le répertoire documentaire Multicompany de l'objet. | Les modèles V1 restent des squelettes fonctionnels à valider métier avant usage contractuel. |
| Signature en ligne d'attestation | v20.0 + `hash_file()` disponible | La signature utilise en priorité le schéma natif Dolibarr `source/ref/securekey` via `/public/onlinesign/newonlinesign.php` lorsque le core supporte explicitement `powerplantpv_attestation`. Sinon, le module fournit une page publique alternative reproduisant le flux natif. | Si `hash_file()` est indisponible ou si la page alternative du module est absente, l'onglet Compatibilité signale l'indisponibilité de la signature en ligne et aucun lien n'est affiché. |

## Fonctionnalités avec rétrocompatibilité

| Fonctionnalité | Version native | Rétrocompatibilité | Fichier |
|---|---:|---|---|
| Sélection des catégories dans une fiche objet | v23.0 | Fallback local vers `select_all_categories()` + `multiselectarray()` quand `Form::selectCategories()` est absent. | `lib/powerplantpv_powerplant.lib.php` |
| Liaison multi-projets | v20.0 | Utilise `llx_element_element`; l'ancien `fk_project` est conservé en base et migré en lien objet à l'activation. | `core/modules/modPowerPlantPV.class.php` |
| Import CSV des numéros de série | v20.0 | Lecture CSV dédiée au module avec `fgetcsv()` et validation métier PowerPlantPV. | `lib/powerplantpv_serialnumber.lib.php` |
| Import CSV des caractéristiques produit | v20.0 | Lecture CSV dédiée au module avec détection de séparateur et mapping d'alias FR/EN. | `class/powerplantpvfileimport.class.php` |
