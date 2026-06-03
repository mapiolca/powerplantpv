# CHANGELOG MODULE POWERPLANTPV FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.0.1

Cette version corrige principalement une erreur 500 lors du clonage / de la création d’une centrale PV depuis un document commercial.

Elle améliore aussi l’intégration Dolibarr du module avec :
- correction Multicompany des references de Centrales : unicite par entite et numerotation dans le perimetre partage natif ;
- completude des reglages Multicompany : partage des Centrales, partage de la numerotation et dictionnaire photovoltaique traduit ;
- rattachement des lignes de composition a l'entite proprietaire de la Centrale ;
- alignement de l'affichage du Tiers dans la banniere Centrale ;
- ajout de l'onglet Localisation/Acces des Centrales et de l'adresse formatee dans la banniere ;
- ajout du modele PDF Centrale PV pour generer une synthese autonome des caracteristiques d'une centrale ;
- correction de la migration de la colonne Consignes d'acces a l'activation du module ;
- amelioration de l'affichage de la Centrale dans les mails de creation de tickets ;
- création directe d’une centrale PV depuis devis ou commande ;
- meilleure gestion des liens entre objets Dolibarr ;
- recalcul automatique de la puissance crête sur devis, commandes et factures ;
- ajout du prix par Wc dans les informations de marge ;
- nouveaux widgets de suivi de puissance installée annuelle, mensuelle et hebdomadaire ;
- amélioration des messages d’erreur et des traductions.

## 1.0

Initial version
