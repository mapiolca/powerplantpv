# Tuiles de l’accueil — 1.7.0

Version préparée : **1.7.0**.

- **Centrales** : brouillons, validées, en service et hors service. Les centrales
  annulées ne figurent pas dans ces quatre compteurs.
- **Maintenances** : total des périodes contractuelles nécessitant une maintenance
  (y compris programmées, réalisées et configuration incomplète), à prévoir
  (`planned` et `due`) et en retard (`overdue`). Les lignes « non requise » sont
  exclues du total. Il s’agit des périodes calculées actuelles, sans historique
  d’interventions et sans restriction à l’année courante.
- Chaque compteur ouvre la liste correspondante avec le même filtre de statut.
- Les droits de lecture des centrales sont requis ; la seconde tuile exige aussi
  la lecture des maintenances et l’activation du socle maintenance. Les entités
  suivent `getEntity('powerplant')`, les échéances et accès liés sont calculés par
  le planificateur existant. Les comptes externes restent limités à leur tiers.

Après intégration et déploiement, actualiser les hooks via la mise à jour native
du module dans chaque entité, en conservant les réglages existants. Les tuiles
sont intégrées au tableau de bord natif, indépendamment des boxes facultatives.

Contrat vérifié par lecture du tag Dolibarr 20.0.0, commit
`697bf01970740a3339cd99cf055b4428fc5e051c` : contexte `index`, hooks
`addOpenElementsDashboardLine` / `addOpenElementsDashboardGroup`, réponses
`WorkboardResponse`, rendu et badges natifs dans `htdocs/index.php`.
Le CSS ajouté définit uniquement les deux alias de pictogrammes Font Awesome
attendus par le moteur de groupes, sans modifier la mise en page du thème.

Recette restant à effectuer : accueil avec les droits partiels et sans droit,
zéros, chaque statut, deux entités avec/sans partage, activation maintenance,
navigation depuis chaque compteur, rendu sur téléphone et bureau. Vérifier les
versions Dolibarr 21–25 ; la lecture v20 ne constitue pas une recette d’instance.

## Vérifications du patch

- `test/home_dashboard.php` : réponses et cycle successif du HookManager natif
  v20, v21, v22, v23, v24 et v25 alpha, sous PHP 8.4.22 ; base et planificateur
  simulés. Les révisions sont celles de la matrice CI existante. La v25 utilise
  `97f5408336b0544276e1c1c0a8bc2619e4afc5ee`, une préversion.
- Régressions locales sur le core 25.0.0-alpha : 197 contrôles projets,
  22 contrôles de valeurs par défaut et 101 contrôles des puissances ; SQLite ou
  SQL simulé, sans instance ni génération de PDF.
- PHPStan non exécuté : aucun exécutable ni configuration d’analyse propre au
  module disponible dans ce checkout. Syntaxe PHP et diff vérifiés.
- L’onglet À propos lit déjà la version du descripteur : il affiche donc 1.7.0
  sans seconde constante de version.
- Aucun paquet ni déploiement préparé ; les essais d’installation, l’acceptation
  Dolistore et la recette visuelle restent hors de ces preuves locales.
