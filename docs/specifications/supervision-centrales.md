# Supervision des centrales photovoltaïques

PowerPlantPV intègre la supervision multifabricant. ATMOCE Cloud, utilisé par ATMOZEN, est le premier connecteur. Le module gère les droits, les liaisons, la collecte et la restitution. Chaque connecteur gère son authentification, la découverte des sites, la conversion des mesures et la description de ses capacités.

## Réglages de supervision

Un onglet interne **Supervision** présente un tableau de réglages repliable par fabricant, sur le modèle des familles de modules Dolibarr. Chaque groupe affiche l’état du connecteur et son interrupteur d’activation. Le tableau donne accès aux paramètres, au test de connexion et au dernier résultat. Les caractéristiques restent consultables pendant la désactivation.

Le tableau des caractéristiques présente les données disponibles, les modes d’accès, la fréquence des mesures, les fenêtres historiques, les quotas et la taille des lots. Il estime la consommation d’appels selon le nombre de centrales liées, la cadence et les collectes activées, puis signale un dépassement prévisionnel. La désactivation suspend les appels et conserve les liaisons, les réglages et l’historique.

Pour ATMOCE, la référence API V1.2.6 annonce 10 000 appels par mois et cinq appels simultanés par jeton, avec jusqu’à 100 sites par appel de dernières mesures. Exemple sur 31 jours avec un jeton et 300 sites interrogés toutes les 15 minutes. La collecte de la production et de l’état global utilise 8 928 appels, laissant 1 072 appels pour les autres opérations.

## Fiche et liste des centrales

Dans l’onglet **Supervision**, l’utilisateur habilité choisit un connecteur actif, puis un site accessible dans la liste renvoyée par le fabricant. Le rattachement ATMOCE utilise son identifiant de site. Les actions de rattachement et de modification conservent les contrôles de droits, d’entité et de jeton CSRF de la fiche. La lecture suit le périmètre de la centrale.

La fiche montre la dernière puissance disponible, la production du jour, l’horodatage de la dernière mesure et l’état technique. Le graphique présente la puissance intrajournalière en kW selon les relevés disponibles et la production journalière en kWh sur 7 ou 30 jours. Une mesure de zéro reste visible et une mesure absente laisse une interruption. Les détails d’équipement sont affichés selon les capacités du fabricant.

La liste comporte une colonne et un filtre **Supervision**. La tuile **Centrales** conserve son total et ajoute des compteurs cliquables vers la liste filtrée, notamment pour les défauts, les sites hors ligne et les données anciennes. La liste permet également d’identifier les centrales sans liaison.

## Statistiques et prestation de maintenance

Les statistiques des centrales présentent un camembert **État des sites supervisés**, avec effectif, pourcentage et lien vers la liste filtrée pour chaque part. Chaque centrale liée à un connecteur actif compte une fois dans le périmètre des filtres et droits courants. Les états sont **Normal**, **En défaut**, **Hors ligne**, **Données anciennes** et **En attente de données**. Une mesure ancienne classe la centrale dans **Données anciennes**. La fiche indique son dernier état connu et la date de la mesure. Un échec de collecte apparaît aussi dans le diagnostic de connexion.

Le camembert et la liste offrent le filtre **Prestation Supervision** avec les choix **Toutes les centrales**, **Avec prestation** et **Sans prestation**. Le badge est calculé à partir de la prestation de maintenance en vigueur. Le clic sur le camembert conserve ce filtre jusque dans la liste, et l’extraction utilise le même périmètre lorsque l’export est autorisé.

La tuile donne accès aux **Défauts sous prestation** et aux **Centrales sous prestation sans remontée**. Ce dernier compteur couvre les centrales non liées, celles dont le connecteur est désactivé et celles qui attendent leur première mesure. Ces motifs sont visibles dans la liste.

## Collecte et validation

- Déclarer la collecte dans les Travaux planifiés Dolibarr. Regrouper les appels, limiter leur fréquence et leur concurrence, reprendre sur erreur et conserver l’historique sans doublons. L’affichage lit les données enregistrées.
- Isoler les réglages, secrets et écritures par entité. Contrôler le périmètre des centrales, y compris pour les recherches, les compteurs, les statistiques et les exports. Garder les secrets côté serveur et hors des journaux.
- Pour ATMOCE, valider sur un site pilote les droits d’accès, la production, les unités, le fuseau horaire, l’état technique et la fraîcheur. La collecte initiale vise la lecture. La disponibilité des alarmes détaillées reste à confirmer auprès du fabricant.
- Vérifier les filtres et les liens avec chaque état, les centrales sous prestation avec et sans liaison, les droits partiels, deux entités et les cas de donnée absente ou égale à zéro. Vérifier aussi la désactivation, le quota et le rejeu d’une collecte.

## Premier incrément

Actualiser le module dans Dolibarr pour créer les tables et déclarer la collecte dans les Travaux planifiés. Dans **Réglages → Supervision**, enregistrer les identifiants d’intégration ATMOCE, tester la connexion enregistrée puis activer le connecteur. Sur une centrale, rechercher les sites autorisés et enregistrer la liaison. La fréquence se règle par entité et la collecte commence dès le prochain passage éligible du travail planifié.

Les courbes utilisent les mesures acquises après la liaison. Les vues de 7 et 30 jours reprennent le dernier cumul journalier disponible pour chaque jour. L’import de l’historique fabricant, les relevés détaillés par équipement, le filtre de liste, la tuile, le camembert et le rapprochement des prestations relèvent des incréments suivants. La connexion OAuth avec autorisation du propriétaire reste également à intégrer.

## Import des séries et calepinage ATMOCE

Depuis **Composition → Calepinage ATMOCE**, charger l’inventaire du site lié. Choisir une ligne de composition pour les nouvelles séries et corriger les positions proposées avant validation. L’inventaire utilise l’API V1.2.6, section 4.3. Les coordonnées de la vue Array ne font pas partie de cette réponse.

Pour un plan de pose avec QR, prendre une photo de face par feuille, délimiter la grille si nécessaire et renseigner sa taille. La grille propose les colonnes de gauche à droite et numérote les lignes du bas vers le haut. Saisir une zone ou une feuille pour distinguer les mêmes repères sur plusieurs plans. La lecture par case est locale, avec jsQR 1.4.0 sous licence Apache-2.0. Jusqu’à 300 cases sont analysées par passage. Les QR inconnus et les positions déjà attribuées à une autre série demandent une vérification. Une capture Array sans QR peut être jointe comme preuve et servir à la saisie des repères.

La preuve est facultative. Quand elle est choisie à la validation, la photo est réencodée avec GD et EXIF, mémorisée dans l’espace documentaire privé de la centrale et accessible depuis les positions du lot. Le dépôt d’une preuve accepte les images JPEG ou PNG dans les limites de l’instance et de 16 mégapixels. L’actualisation native du module crée les deux tables de calepinage. La validation requiert la lecture et la modification de la centrale, ainsi que les droits de lecture et d’import des séries dans l’entité propriétaire.
