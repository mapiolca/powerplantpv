<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

// Template data based on FR37-V01 for photovoltaic installation and commissioning.
$sections = array(
	array('RES_SITE', 'Chantier et préparation', 'Site and preparation', 'intervention', '', 'once', ''),
	array('RES_SAFETY', 'Risques et sécurité', 'Risks and safety', 'intervention', '', 'once', ''),
	array('RES_WORK', 'Prestation et travaux réalisés', 'Scope and completed work', 'intervention', '', 'once', ''),
	array('RES_TESTS', 'Contrôles électriques', 'Electrical checks', 'intervention', '', 'once', ''),
	array('RES_STRINGS', 'Relevés des strings', 'String readings', 'intervention', '', 'once', ''),
	array('RES_INVERTER', 'Essais et connexion de l’onduleur', 'Inverter tests and connection', 'inverter', '', 'once_per_equipment', ''),
	array('RES_CONCLUSION', 'Observations et conclusion', 'Observations and conclusion', 'intervention', '', 'once', ''),
);

$fields = array(
	array('RES_SITE', 'RES_CUSTOMER_PRESENT', 'Client présent sur site', 'Customer on site', 'yesno', '', 0),
	array('RES_SITE', 'RES_ROOF_TEAM', 'Équipe toiture', 'Roofing team', 'varchar', '', 0),
	array('RES_SITE', 'RES_ELECTRICAL_TEAM', 'Référent électricité sur le chantier', 'On-site electrical lead', 'varchar', '', 0),
	array('RES_SITE', 'RES_ROOF_TYPE', 'Type de toiture', 'Roof type', 'select', '', 0, '', array(
		'TILE' => array('Tuile', 'Tile'),
		'STEEL' => array('Bac acier', 'Steel deck'),
		'FLAT' => array('Toit terrasse', 'Flat roof'),
		'OTHER' => array('Autre', 'Other'),
	)),
	array('RES_SITE', 'RES_MOUNTING', 'Type de pose', 'Mounting type', 'select', '', 0, '', array(
		'ABOVE' => array('Surimposition', 'Above-roof'),
		'INTEGRATED' => array('Intégration au bâti', 'Building-integrated'),
		'FLAT' => array('Toit terrasse', 'Flat roof'),
		'OTHER' => array('Autre', 'Other'),
	)),
	array('RES_SITE', 'RES_ACCESS', 'Accès toiture et moyens d’accès', 'Roof access and equipment', 'varchar', '', 0,
		'Échelle, nacelle, échafaudage, trappe ou autre'),
	array('RES_SITE', 'RES_PHASES', 'Raccordement électrique', 'Electrical connection', 'select', '', 0, '', array(
		'SINGLE' => array('Monophasé', 'Single-phase'),
		'THREE' => array('Triphasé', 'Three-phase'),
	)),
	array('RES_SAFETY', 'RES_RISKS', 'Risques identifiés', 'Identified risks', 'textarea', '', 0),
	array('RES_SAFETY', 'RES_PREVENTION', 'Mesures de prévention et consignes', 'Preventive measures and instructions', 'textarea', '', 0),
	array('RES_SAFETY', 'RES_COLLECTIVE_PROTECTION', 'Protections collectives', 'Collective protection', 'varchar', '', 0),
	array('RES_SAFETY', 'RES_PROTECTIVE_EQUIPMENT', 'Équipements de protection individuelle', 'Personal protective equipment', 'varchar', '', 0),
	array('RES_SAFETY', 'RES_PPE_CHECK', 'Équipements de protection vérifiés', 'Protective equipment checked', 'conformity_so_valid_obs', '', 0),
	array('RES_WORK', 'RES_SOLD_SCOPE', 'Prestation vendue', 'Sold scope of work', 'textarea', '', 0),
	array('RES_WORK', 'RES_COMPLETED_WORK', 'Travaux réalisés', 'Completed work', 'textarea', '', 0),
	array('RES_WORK', 'RES_MODULE_LAYOUT', 'Emplacement et calepinage des panneaux', 'Module location and layout', 'varchar', '', 0),
	array('RES_WORK', 'RES_INVERTER_LOCATION', 'Emplacement de l’onduleur', 'Inverter location', 'varchar', '', 0),
	array('RES_WORK', 'RES_PHOTOS_BEFORE', 'Photos avant travaux', 'Before-work photos', 'file', '', 0),
	array('RES_WORK', 'RES_PHOTOS_AFTER', 'Photos après travaux', 'After-work photos', 'file', '', 0),
	array('RES_TESTS', 'RES_CONSUEL_CASE', 'Cas de raccordement CONSUEL', 'CONSUEL connection case', 'select', '', 0, '', array(
		'CASE_1' => array('Cas 1', 'Case 1'),
		'CASE_2' => array('Cas 2', 'Case 2'),
		'CASE_3' => array('Cas 3', 'Case 3'),
		'CASE_4' => array('Cas 4', 'Case 4'),
		'SUBPANEL' => array('Tableau divisionnaire', 'Subpanel'),
	)),
	array('RES_TESTS', 'RES_CONSUEL_DIAGRAM', 'Schéma électrique / justificatifs', 'Wiring diagram / evidence', 'file', '', 0),
	array('RES_TESTS', 'RES_DC_CONNECTORS', 'Connectique DC', 'DC connectors', 'conformity_so_valid_obs', '', 1),
	array('RES_TESTS', 'RES_AC_CONNECTORS', 'Connectique du coffret AC', 'AC enclosure connections', 'conformity_so_valid_obs', '', 1),
	array('RES_TESTS', 'RES_CABLE_TRUNKING', 'Câbles protégés sous goulotte', 'Cables protected in trunking', 'conformity_so_valid_obs', '', 1),
	array('RES_TESTS', 'RES_EARTHING', 'Terres raccordées (onduleurs, coffrets et panneaux)', 'Earthing connected (inverters, enclosures and modules)', 'conformity_so_valid_obs', '', 1),
	array('RES_TESTS', 'RES_EARTH_RESISTANCE', 'Résistance de terre', 'Earth resistance', 'number', 'ohm', 0),
	array('RES_TESTS', 'RES_LABELS', 'Étiquetage des équipements et protections', 'Equipment and protection labelling', 'conformity_so_valid_obs', '', 1),
);

for ($string = 1; $string <= 3; $string++) {
	$fields[] = array('RES_STRINGS', 'RES_STRING_'.$string.'_VOLTAGE', 'Tension du string '.$string, 'String '.$string.' voltage', 'number', 'V', 0);
	$fields[] = array('RES_STRINGS', 'RES_STRING_'.$string.'_PANELS', 'Nombre de panneaux du string '.$string, 'String '.$string.' module count', 'number', '', 0);
}

$fields = array_merge($fields, array(
	array('RES_INVERTER', 'RES_INVERTER_TEST', 'Essais de l’onduleur', 'Inverter tests', 'conformity_so_valid_obs', '', 1),
	array('RES_INVERTER', 'RES_INVERTER_POWER', 'Puissance mesurée en sortie', 'Measured output power', 'number', 'W', 0),
	array('RES_INVERTER', 'RES_NETWORK', 'Connexion internet', 'Internet connection', 'select', '', 0, '', array(
		'WIFI' => array('Wi-Fi', 'Wi-Fi'),
		'RJ45' => array('Câblée (RJ45)', 'Wired (RJ45)'),
		'OTHER' => array('Autre', 'Other'),
	)),
	array('RES_INVERTER', 'RES_WIFI_REASON', 'Motif du Wi-Fi', 'Reason for Wi-Fi', 'varchar', '', 0),
	array('RES_CONCLUSION', 'RES_OBSERVATIONS', 'Écarts et observations', 'Deviations and observations', 'textarea', '', 0),
	array('RES_CONCLUSION', 'RES_RESULT', 'Résultat', 'Result', 'select', '', 1, '', array(
		'CONFORM' => array('Conforme', 'Compliant'),
		'RESERVES' => array('Conforme avec réserves', 'Compliant with reservations'),
		'NON_CONFORM' => array('Non conforme', 'Non-compliant'),
	)),
));

return array('sections' => $sections, 'fields' => $fields);
