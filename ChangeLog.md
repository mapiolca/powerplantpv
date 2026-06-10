# CHANGELOG MODULE POWERPLANTPV FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## Unreleased

Cette version ajoute le périmètre V1 des attestations PowerPlantPV :
- nouvel objet métier `PowerPlantPVAttestation` avec quatre types d'attestations : bridage dynamique onduleur, bridage statique onduleur, réglage fréquence maximale 51,5 Hz et installateur inférieur à 100 kWc ;
- tables SQL principales et lignes d'équipements, avec `entity`, références, liens centrale/tiers/projet, données gelées, métadonnées de signature et documents ;
- listes, fiches, onglet documents, onglet agenda et workflow de signature interne avec token, image PNG, tampon d'entreprise, copie PDF signée et empreinte SHA-256 ;
- modèles PDF fonctionnels pour les quatre types, prêts pour validation métier des contenus légaux ;
- droits Dolibarr dédiés, menus internes au module, numérotation native, réglages par entité, partage Multicompany et colonne Environnement en liste lorsque nécessaire ;
- triggers Agenda et Notifications natifs pour création, validation, génération PDF, envoi en signature, signature, annulation et suppression ;
- correction des contrôles d'accès aux attestations : la création utilise le droit d'écriture, les pages partagent le même helper de droits et l'administration signale les tables ou droits manquants après un déploiement incomplet ;
- les données de lieu et d'installateur des attestations sont désormais préremplies depuis les informations société Dolibarr de l'entité (`MAIN_INFO_*`) et ne sont plus ressaisies dans les réglages PowerPlantPV ;
- la fonction du rédacteur des attestations est désormais préremplie depuis le champ natif utilisateur `Poste/fonction`, sans réglage PowerPlantPV dédié ;
- les champs de lieu, installateur, site et rédacteur des attestations ne sont plus stockés sur l'objet attestation : ils sont résolus depuis l'entité Dolibarr, la centrale PV liée et l'utilisateur auteur `fk_user_creat` ;
- les modèles core des attestations sont isolés dans `core/modules/attestation` et les pages liste/fiche journalisent désormais les sorties précoces pour diagnostiquer les erreurs 500 ;
- correction du bloc documents des attestations : la génération utilise les modèles de `core/modules/attestation` sans demander à `FormFile::showdocuments()` une classe PDF sous `core/modules/powerplantpv` ;
- l'onglet de réglages Attestations expose désormais les blocs natifs Dolibarr de numérotation et de modèles de documents pour l'objet `attestation` ;
- la validation des attestations indique désormais le champ ou la source native manquante, et la fiche affiche les champs métier conditionnels utiles à la validation ;
- la puissance maximale injectée des attestations de bridage dynamique est obligatoire mais peut désormais valoir `0`, tandis que le bridage statique exige toujours une valeur strictement positive ;
- la fiche et l'onglet Fichiers joints des attestations affichent désormais les fichiers générés avec les blocs natifs Dolibarr ;
- correction du rendu documentaire des attestations : la génération et les fichiers de la fiche utilisent le même tableau natif Dolibarr, les aperçus pointent vers `powerplantpv/attestation/<ref>/<ref>.pdf` et les anciens fichiers générés un niveau trop haut sont déplacés sans écrasement ;
- traductions `fr_FR`, `en_US`, `es_ES`, `it_IT` et `de_DE` mises à jour.

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
