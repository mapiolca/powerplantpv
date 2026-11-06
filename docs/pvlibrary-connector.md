# Connecteur Bibliothèque PV — lot 1

Depuis Produits/Services : recherche, aperçu sourcé et confirmation. Les droits `library.read`, `library.import` et les droits natifs produits sont contrôlés sans élévation locale. Réactiver le module pour enregistrer les nouvelles permissions et le sous-menu, en conservant la configuration existante.

Configurer l’URL Dolibarr distante dans l’onglet Bibliothèque des réglages PowerPlantPV. Le serveur web reçoit la clé native REST via `POWERPLANTPV_LIBRARY_API_KEY_<entity>`. Ne pas la placer dans une URL, un fichier versionné ou une capture. La clé reste sur le client ; les redirections HTTP sont refusées.

L’import appelle Product::create et les méthodes techniques PowerPlantPV dans une transaction unique, sous verrou MySQL, avec traçabilité native datasource. Aucun produit existant n’est modifié : les replays retrouvent la liaison, les révisions suivantes attendent le lot de mise à jour à trois versions. Le produit initial est hors achat et vente. Les références source déterministes PVL sont indépendantes des rowids.

Le format v1 exige une référence fabricant exacte, des mesures typées dans les unités du registre et une preuve textuelle par mesure. Les batteries, accessoires, documents et compositions MPPT sont hors de ce premier flux. Les versions v20–v25 restent à qualifier sur instances ; PHP 8.0 reste le socle du code.
