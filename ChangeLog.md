# CHANGELOG MODULE POWERPLANTPV FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.3.0

- Ajout du périmètre de maintenance préventive : droits dédiés, dictionnaires, prestations contractuelles, périodes récurrentes et calcul des états à prévoir, programmée, en retard, couverte, non requise ou à configurer.
- Ajout d'un onglet `Maintenance` sur les centrales, avec suivi des contrats et interventions, prochaine échéance et création d'une intervention préremplie.
- Liaison automatique des interventions au contrat actif et avancement des périodes récurrentes après clôture ou signature couvrante, avec travail planifié de rattrapage.
- Ajout des vues de pilotage : liste et calendrier fiabilisés, tableau de bord personnalisable, boxes d'accueil et statistiques comparatives sur deux ou trois ans, compatibles Multicompany.
- Ajout des rappels automatiques hebdomadaires et mensuels pour les maintenances à prévoir, programmées ou en retard, avec destinataires et modèles d'email configurables.
- Ajout d'un moteur de rapports d'intervention configurable par nature et prestation, avec sections conditionnelles, snapshots, recalcul contrôlé et verrouillage après signature ou clôture.
- Prise en charge des rapports multi-centrales et multi-équipements, de la topologie MPPT/entrées PV/strings, des mesures DC et des relevés de production/consommation N-1 et N.
- Ajout du modèle PDF d'intervention `powerplantpvreport`, incluant équipements, mesures, relevés, thermographie, fichiers, signatures et mentions légales configurables par entité.
- Ajout des écrans d'administration, traductions, partages Multicompany et documentations nécessaires au paramétrage et à l'exploitation de la maintenance.
- Fiabilisation de l'activation/réactivation, de la pagination, des changements d'année et de la conservation des modèles et réglages existants.
- Optimisation du rapport de maintenance sur iOS afin d'éviter le zoom automatique à la saisie, sans désactiver le zoom manuel d'accessibilité.

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
