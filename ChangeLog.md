# CHANGELOG MODULE POWERPLANTPV FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.3.0

- Tableau de bord maintenance : alignement des widgets personnalisables sur la structure et les icônes natives des boîtes Dolibarr, avec aide, poignée de déplacement et fermeture dans le bandeau du widget.
- Tableau de bord maintenance : nouvelle page d'entrée `maintenance_index.php`, disposition de widgets personnalisable par utilisateur et par entité, filtre de période commun et réorganisation en deux colonnes avec repli mobile.
- Tableau de bord maintenance : correction du passage de décembre à janvier dans les séries mensuelles, sans transmettre de date vide à PHP 8.
- Statistiques maintenance : remplacement du tableau de bord personnalisable par une analyse fixe comparant deux ou trois années civiles, avec volumes mensuels, réalisations, natures, centrales et clients issus des interventions datées et dédoublonnées.
- Statistiques maintenance : catalogue et service d'agrégation partagés pour les synthèses, échéances, retards, charge mensuelle, répartitions et qualité de configuration.
- Accueil Dolibarr : ajout de boîtes natives de maintenance activables individuellement par chaque utilisateur, toutes déclarées sans activation par défaut.
- Maintenance : ajout du statut `scheduled` / `Programmée`, avec intervention programmée séparée de l'intervention couvrante et mode par entité `created` ou `validated`.
- Rappels : fenêtres glissantes J+6/J+29, conservation de tous les retards, langue et droits par destinataire, liens absolus, rattrapage sans rafale, verrou par entité et gestion explicite des erreurs SMTP/persistance.
- Liste maintenance : pagination dans le formulaire natif, tri en liste blanche avant pagination, totaux corrigés et colonne Environnement Multicompany filtrable.
- Statistiques maintenance : alternance et surbrillance des lignes confiées aux classes de liste natives du thème Dolibarr.
- Correction de l'idempotence d'activation/réactivation : les scripts SQL de création de tables utilisent `IF NOT EXISTS` et les extrafields déjà conformes ne sont plus réenregistrés inutilement.
- Maintenance : ajout des rappels automatiques hebdomadaires et mensuels des maintenances à prévoir, en cours ou en retard, avec destinataires configurables, modèle d'email optionnel et délai de bascule `Couverte` vers `À prévoir`.
- Maintenance : alignement des couleurs des badges d'état sur la grille métier et affichage du libellé dans les tableaux.
- Administration maintenance : séparation des switches d'activation dans une colonne dédiée sur les listes de modèles de rapport, prestations déclenchant des sections et natures d'intervention.
- Maintenance : liaison automatique d'une intervention de maintenance au contrat actif de la centrale via les liens natifs Dolibarr, sans remplacer un contrat déjà fourni.
- Maintenance : avancement automatique d'une période de contrat récurrente lorsqu'une intervention clôturée ou signée couvre la période courante, avec travail planifié natif de rattrapage pour les signatures sans trigger Dolibarr.
- Maintenance : une intervention clôturée ou signée sans période native peut désormais couvrir une période contractuelle via un fallback sur sa date de finalisation disponible.
- Maintenance : correction de la navigation du calendrier en décembre, qui pouvait provoquer une erreur fatale sur le mois suivant.
- Stabilisation finale v1.3 Maintenance : audit de cohérence, documentation utilisateur, administrateur, technique et checklist de release.
- Harmonisation de l'onglet `Rapport` des interventions : boutons de soumission alignés sur les formulaires Dolibarr, sans modifier le workflow de sauvegarde, upload, suppression de fichier ou ajout de mesure DC.
- Conservation renforcée des modèles documentaires lors de l'activation/réactivation : les modèles PowerPlantPV et Attestation manquants sont ajoutés sans suppression/réinsertion des lignes déjà présentes.
- Ajout du socle données maintenance préventive : dictionnaires des natures d'intervention, services de maintenance, sections de rapport et types de relevés.
- Ajout des tables de liaison services/sections et des champs de gabarit de rapport de maintenance, avec données par défaut idempotentes et par entité.
- Ajout des droits Dolibarr dédiés à la maintenance et des champs complémentaires sur contrats, produits/services et interventions.
- Déclaration des dictionnaires maintenance dans le partage Multicompany et dans l'onglet de compatibilité.
- Ajout du moteur configurable de modèles de rapports d'intervention : modèles, sections, champs, options de champs, mappings prestations -> sections et association nature d'intervention -> modèle.
- Migration du socle PR1 pour que la maintenance préventive soit portée par le modèle préinstallé `preventive_maintenance`, sans logique de rapport codée en dur.
- Ajout des écrans d'administration internes aux réglages PowerPlantPV pour créer, modifier, désactiver, dupliquer et réordonner les éléments du moteur de modèles.
- Retrait du dictionnaire historique `c_powerplantpv_report_section` de l'administration active ; il reste conservé comme source de migration.
- Ajout de la documentation technique `docs/technical/v1.3-pr2-report-template-engine.md`.
- Ajout de l'onglet `Maintenance` sur la fiche centrale, avec affichage des contrats liés, services actifs, prestations de maintenance, interventions liées et statut de prochaine maintenance.
- Ajout du moteur `PowerPlantPVMaintenanceScheduler` calculant les statuts `not_required`, `planned`, `due`, `overdue`, `covered` et `incomplete` depuis les liens natifs, les périodes configurées sur contrat et les interventions couvrantes clôturées/signées.
- Ajout d'un bouton de création d'intervention de maintenance préventive depuis l'onglet centrale, avec préremplissage centrale, contrat, nature et période de contexte.
- Ajout de la documentation technique `docs/technical/v1.3-pr4-maintenance-tab-scheduler.md`.
- Ajout de l'onglet `Rapport` sur les fiches interventions, généré depuis la nature d'intervention, le modèle associé, les prestations actives et les centrales liées.
- Ajout des tables de rapport généré `powerplantpv_report`, sections, champs, fichiers, équipements, centrales et prestations sources, avec snapshot figé au premier enregistrement.
- Ajout de la sauvegarde brouillon/simple, du recalcul depuis le modèle avec conservation des valeurs par clé stable et du verrouillage en lecture seule quand l'intervention est signée ou clôturée.
- Ajout de la documentation technique `docs/technical/v1.3-pr6-intervention-report-tab.md`.
- Complétude du modèle préinstallé `preventive_maintenance` : sections de base, sections conditionnelles, champs N-1/N, options thermographie et mappings prestations -> sections, avec seed d'activation non destructif.
- Ajout de la documentation technique `docs/technical/v1.3-pr7-preventive-template.md`.
- Ajout du support multi-centrales dans le rapport d'intervention : prestations résolues par centrale, sections groupées par centrale et sections équipement répétées uniquement sur les équipements concernés.
- Ajout de la configuration installée MPPT / entrées PV / strings sur les onduleurs de composition centrale, avec préremplissage non destructif depuis les caractéristiques produit.
- Ajout des mesures DC spécialisées `powerplantpv_report_dc_measure`, générées automatiquement depuis la topologie installée, avec mode manuel et conservation des valeurs au recalcul par clé stable.
- Extension du snapshot équipement de rapport avec source équipement, marque, modèle, position et snapshot technique JSON.
- Ajout de la documentation technique `docs/technical/v1.3-pr8-multi-equipment-dc-measures.md`.
- Ajout de l'onglet centrale `Production/consommation`, de l'archive `powerplantpv_index_reading`, de la saisie manuelle des relevés et de la synchronisation des champs `PRODUCTION_READING` des rapports finalisés.
- Préremplissage non éditable des champs `N-1` depuis le dernier relevé archivé et ajout des champs `SELF_CONSUMPTION_N_MINUS_1` / `SELF_CONSUMPTION` au modèle préinstallé.
- Ajout de la documentation technique `docs/technical/v1.3-pr9-production-consumption.md`.
- Ajout du modèle PDF Fichinter `powerplantpvreport`, généré dynamiquement depuis le snapshot du rapport d'intervention sans créer de snapshot pendant la génération.
- Ajout du dataset PDF `PowerPlantPVReportPdfDataset`, du rendu multi-centrales, multi-équipements, mesures DC, relevés N-1/N, thermographie, fichiers et signatures.
- Ajout du réglage par entité `POWERPLANTPV_REPORT_PDF_LEGAL_NOTICE` pour les mentions légales du PDF et de la documentation technique `docs/technical/v1.3-pr10-dynamic-pdf.md`.
- Ajout de la documentation consolidée `docs/user/v1.3-maintenance-user-guide.md`, `docs/admin/v1.3-maintenance-admin-guide.md`, `docs/technical/v1.3-maintenance-technical-overview.md` et `docs/release/v1.3-maintenance-release-checklist.md`.
- Notes de migration : réactiver le module en recette avant release, vérifier l'absence de doublons de dictionnaires/modèles, vérifier que les personnalisations de modèles de rapport et modèles documentaires sont conservées, puis dérouler la checklist de release v1.3.
- Dette historique conservée hors PR12 : les anciens triggers custom non-CRUD du périmètre PowerPlant/Attestation restent présents pour compatibilité Agenda/Notifications existante et ne sont pas étendus par la maintenance v1.3.

## 1.2.1

- Complétude des modèles et de la mécanique d'import CSV/XLSX des onduleurs avec tous les champs généraux peuplables, hors lignes MPPT et entrées PV.
- Ajout d'un réglage par entité pour exclure certaines catégories photovoltaïques du contrôle des numéros de série, avec coffrets AC, coffrets DC et systèmes d'intégration ignorés par défaut tant que le réglage n'a jamais été enregistré.

## 1.2.0

Cette version ajoute le périmètre des attestations PowerPlantPV :
- nouvel objet métier `PowerPlantPVAttestation` avec quatre types d'attestations : bridage dynamique onduleur, bridage statique onduleur, réglage fréquence maximale 51,5 Hz et installateur inférieur à 100 kWc ;
- tables SQL principales et lignes d'équipements, avec `entity`, références, liens centrale/tiers/projet, données gelées, métadonnées de signature et documents ;
- listes, fiches, onglet documents, onglet agenda et signature en ligne strictement déléguée au process natif Dolibarr lorsque le core supporte la source `powerplantpv_attestation` ;
- modèles PDF fonctionnels pour les quatre types, prêts pour validation métier des contenus légaux ;
- droits Dolibarr dédiés, menus internes au module, numérotation native, réglages par entité, partage Multicompany et colonne Environnement en liste lorsque nécessaire ;
- triggers Agenda et Notifications natifs pour création, validation, génération PDF, envoi en signature, signature, annulation et suppression ;
- complétude des traductions pour les événements automatiques, notifications et statuts longs/courts des centrales PV et attestations ;
- traductions `fr_FR`, `en_US`, `es_ES`, `it_IT` et `de_DE` mises à jour.
- Ajout du droit spécifique `powerplantpv / attestation / manage_signed` permettant, avec le droit de lecture, de modifier, supprimer et régénérer les PDF des attestations signées.
- Ajout d'un bouton d'action `Créer une attestation` sur la fiche centrale et affichage du projet lié dans la bannière de la fiche attestation.
- Ajout d'une signature en ligne alternative pour les attestations lorsque le core Dolibarr ne supporte pas nativement la source `powerplantpv_attestation`, avec page publique module, endpoint Ajax sécurisé et génération du PDF signé via les modèles existants.
- Ajout dans l'onglet `Fichiers joints` des centrales d'un tableau natif listant les attestations liées, avec date de validation dédiée et colonne Environnement seulement lorsque nécessaire en Multicompany.
- Précision des unités attendues sur les caractéristiques détaillées des onduleurs et conservation des champs numériques vides en `NULL` lors de la saisie manuelle, sans convertir les valeurs existantes.

## 1.1.0

Cette version améliore la fiche Centrale PV, le modèle PDF et l'intégration Multicompany :
- fusion des sections Réseau et harmonisation des champs de raccordement ;
- affichage des unités `kWc` et `kVA` sur la fiche et dans le PDF ;
- remplacement du type de raccordement par un choix Select2 avec clés techniques stables et compatibilité avec les anciennes valeurs ;
- correction de la couleur du tableau Contacts dans le PDF Centrale PV ;
- repositionnement du Tiers après le Libellé dans la création et la liste ;
- ajout de l'affichage conditionnel de l'environnement Multicompany en fiche et en liste.
- ajout de l'import CSV/XLSX des numéros de série par catégorie PV de composition, avec validation, table dédiée, droits spécifiques, export CSV/XLSX et traçabilité ;
- ajout de l'onglet de réglages Compatibilité pour signaler les fonctionnalités disponibles ou indisponibles, notamment le lecteur XLSX PhpSpreadsheet ;
- ajout d'un import PV Free depuis l'onglet Caractéristiques détaillées des produits Dolibarr ;
- alimentation des tables techniques existantes des modules PV et onduleurs, sans référentiel composant séparé ;
- ajout d'une table de traçabilité des sources importées ;
- ajout des réglages PV Free, des stratégies d'écrasement et des datasets par défaut ;
- les détails MPPT / entrées DC des onduleurs ne sont pas écrasés automatiquement et restent à vérifier manuellement.
- ajout de l'import CSV/XLSX des caractéristiques détaillées depuis un produit existant ;
- ajout des modèles CSV/XLSX téléchargeables et de la modale d'import depuis l'onglet produit ;
- prévisualisation des champs reconnus, ignorés et modifiés avant confirmation ;
- sélection d'une ligne lorsque le fichier contient plusieurs lignes ;
- réutilisation de la traçabilité des sources avec `source = csv` ou `source = xlsx` et nom du fichier importé ;
- les détails MPPT / entrées DC restent exclus de l'import automatique et doivent être vérifiés manuellement.

## 1.0.1

Cette version corrige principalement une erreur 500 lors du clonage / de la création d’une centrale PV depuis un document commercial.

Elle améliore aussi l’intégration Dolibarr du module avec :
- correction Multicompany des références de Centrales : unicité par entité et numérotation dans le périmètre partagé natif ;
- complétude des réglages Multicompany : partage des Centrales, partage de la numérotation et dictionnaire photovoltaïque traduit ;
- affichage des réglages de partage Multicompany PowerPlantPV dans toutes les entités via les hooks dédiés ;
- rattachement des lignes de composition à l'entité propriétaire de la Centrale ;
- alignement de l'affichage du Tiers dans la bannière Centrale ;
- ajout de l'onglet Localisation/Accès des Centrales et de l'adresse formatée dans la bannière ;
- ajout du modèle PDF Centrale PV pour générer une synthèse autonome des caractéristiques d'une centrale ;
- correction de la migration de la colonne Consignes d'accès à l'activation du module ;
- amélioration de l'affichage de la Centrale dans les mails de création de tickets ;
- création directe d’une centrale PV depuis devis ou commande ;
- meilleure gestion des liens entre objets Dolibarr ;
- recalcul automatique de la puissance crête sur devis, commandes et factures ;
- ajout du prix par Wc dans les informations de marge ;
- nouveaux widgets de suivi de puissance installée annuelle, mensuelle et hebdomadaire ;
- les widgets kWc utilisent désormais la puissance-crête des commandes client livrées et leur date de clôture ;
- ajout d'un diagnostic explicite lorsque les widgets kWc n'ont aucune commande livrée/clôturée dans le périmètre affiché ;
- amélioration des messages d’erreur et des traductions.

## 1.0

Initial version
