Objectif: Clarifier le typage produit PV pour stabiliser les usages futurs.
Stratégie dictionnaire: Normaliser les libellés via dicos Dolibarr pour limiter les variantes.
Stratégie extrafield: Introduire un extrafield structuré pour tracer le type PV sans surcharger le core.
Stratégie ajax: Proposer une auto-complétion Ajax côté fiche pour guider la saisie utilisateur.
Étape de validation: Vérifier l'intégration des trois axes en environnement 21.0+ avant diffusion.

Audit:
- sql/dolibarr_allversions.sql → lignes 72-100 → Inserts/updates de codes 50-55 dans c_product_nature (ProductNaturePVModules/Inverters/Integration/Monitoring/ACBox/DCBox).
	- Extrait: INSERT INTO llx_c_product_nature (code, label, active) ... code = '50' ... UPDATE llx_c_product_nature SET label = 'ProductNaturePVModules', active = 1 WHERE code = '50';
- core/modules/modPowerPlantPV.class.php → lignes 538-545 → Déclaration du nom de table nature + tableau de codes 50-55 utilisés par le module.
	- Extrait: $natureTable = $this->db->prefix().\"c_product_nature\"; … array('code' => '50', 'labelkey' => 'ProductNaturePVModules'), ... '55', 'ProductNaturePVDCBox')
- hooks/powerplantpv_product.class.php → ligne 41 → Test du fk_product_nature == '50' pour le hook produit.
	- Extrait: if ($object->fk_product_nature == '50') {
- powerplant_card.php → lignes 982-1012 → Mapping des natures 50-55 et select_produits filtré par finished avec clause fk_product_nature.
	- Extrait: print $form->select_produits(... 'finished', \" AND p.fk_product_nature = \".((int) $code));
- product_pvpanel.php → lignes 63-64 → Contrôle d’accès basé sur finished == 50 (hors admin).
	- Extrait: // Security: keep your existing rule (only admin or product finished == 50) ; if (!$user->admin && (int) $object->finished !== 50) {

Mise à jour étape 2: suppression des insertions llx_c_product_nature (codes 50-55) dans sql/dolibarr_allversions.sql et du bloc d’activation associé dans core/modules/modPowerPlantPV.class.php.
Mise à jour étape 3: ajout du dictionnaire llx_c_pv_product_type (table + codes MODULE_PV, ONDULEUR, OPTIMISEUR, ARMOIRE_AC, COFFRET_DC, CABLE_DC, CABLE_AC, STRUCTURE, PROTECTION, COMPTAGE, DIVERS).
Mise à jour étape 4: ajout de l’extrafield produit pv_product_type (sellist) alimenté par llx_c_pv_product_type.
Mise à jour étape 5: filtrage des sélections de composants via l’extrafield pv_product_type et nouvel endpoint AJAX.
Mise à jour étape 6: migration best-effort des produits finished 50/51/52/53/54/55 vers pv_product_type sans écrasement.
Fichiers ciblés pour étapes suivantes: sql/dolibarr_allversions.sql, core/modules/modPowerPlantPV.class.php, hooks/powerplantpv_product.class.php, powerplant_card.php, product_pvpanel.php.
