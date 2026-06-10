<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		attestation_agenda.php
 * \ingroup		powerplantpv
 * \brief		Agenda tab for attestations.
 */

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

include_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/powerplantpv/class/powerplantpvattestation.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_attestation.lib.php');

$langs->loadLangs(array('powerplantpv@powerplantpv', 'other', 'agenda'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');
$socid = GETPOSTINT('socid');
$contextpage = GETPOST('contextpage', 'aZ09') ? GETPOST('contextpage', 'aZ09') : getDolDefaultContextPage(__FILE__);
$backtopage = GETPOST('backtopage', 'alpha');

if (GETPOST('actioncode', 'array')) {
	$actioncode = GETPOST('actioncode', 'array', 3);
	if (!count($actioncode)) {
		$actioncode = '0';
	}
} else {
	$actioncode = GETPOST('actioncode', 'alpha', 3) ? GETPOST('actioncode', 'alpha', 3) : (GETPOST('actioncode') == '0' ? '0' : getDolGlobalString('AGENDA_DEFAULT_FILTER_TYPE_FOR_OBJECT'));
}
$search_rowid = GETPOST('search_rowid');
$search_agenda_label = GETPOST('search_agenda_label');
$search_complete = GETPOST('search_complete', 'alpha');
$search_filtert = GETPOSTINT('search_filtert');

$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page == -1) {
	$page = 0;
}
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (!$sortfield) {
	$sortfield = 'a.datep,a.id';
}
if (!$sortorder) {
	$sortorder = 'DESC';
}

if (!isModEnabled('powerplantpv') || !isModEnabled('agenda') || !getDolGlobalInt('POWERPLANTPV_ATTESTATION_ENABLE', 1)) {
	accessforbidden();
}
if (!powerplantpvAttestationUserHasRight($user, 'read')) {
	accessforbidden();
}
if (function_exists('powerplantpvAttestationGetInstallationIssues')) {
	$attestationInstallationIssues = powerplantpvAttestationGetInstallationIssues();
	if (!empty($attestationInstallationIssues['tables']) || !empty($attestationInstallationIssues['columns'])) {
		accessforbidden($langs->trans('AttestationInstallationIncomplete'));
	}
}

$object = new PowerPlantPVAttestation($db);
$hookmanager->initHooks(array($object->element.'agenda', 'globalcard'));
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden();
}
if (method_exists($object, 'fetch_thirdparty')) {
	$object->fetch_thirdparty();
}
$object->info($object->id);

if ($user->socid > 0) {
	$socid = $user->socid;
}
$isdraft = ((int) $object->status === PowerPlantPVAttestation::STATUS_DRAFT ? 1 : 0);
restrictedArea($user, $object->module, $object, $object->table_element, $object->element, 'fk_soc', 'rowid', $isdraft);

/*
 * Actions
 */

$parameters = array('id' => $id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}
if (empty($reshook)) {
	if (GETPOST('cancel', 'alpha') && !empty($backtopage)) {
		header('Location: '.$backtopage);
		exit;
	}

	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
		$actioncode = '';
		$search_rowid = '';
		$search_agenda_label = '';
		$search_complete = '';
		$search_filtert = '';
	}
}

/*
 * View
 */

$form = new Form($db);
$agenda = (isModEnabled('agenda') && ($user->hasRight('agenda', 'myactions', 'read') || $user->hasRight('agenda', 'allactions', 'read'))) ? '/'.$langs->trans('Agenda') : '';
$title = $langs->trans('Events').$agenda.' - '.$object->ref;

llxHeader('', $title, 'EN:Module_Agenda_En|DE:Modul_Terminplanung', '', 0, 0, '', '', '', 'mod-powerplantpv page-card_agenda page-attestation-agenda');

if (isModEnabled('notification')) {
	$langs->load('mails');
}

$head = powerplantpvAttestationPrepareHead($object);
print dol_get_fiche_head($head, 'agenda', $langs->trans('Attestation'), -1, $object->picto);
dol_banner_tab($object, 'ref', powerplantpvAttestationGetBackToListLink($object), 1, 'ref', 'ref', powerplantpvAttestationBuildBannerMoreHtml($object));

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
dol_print_object_info($object, 1);
print '</div>';
print '<div class="clearboth"></div>';
print dol_get_fiche_end();

$out = '&origin='.urlencode('attestation@powerplantpv').'&originid='.urlencode((string) $object->id);
$urlbacktopage = $_SERVER['PHP_SELF'].'?id='.(int) $object->id;
$out .= '&backtopage='.urlencode($urlbacktopage);
if (!empty($object->fk_soc)) {
	$out .= '&socid='.urlencode((string) $object->fk_soc);
}

$morehtmlright = '';
if ($user->hasRight('agenda', 'myactions', 'create') || $user->hasRight('agenda', 'allactions', 'create')) {
	$morehtmlright .= dolGetButtonTitle($langs->trans('AddAction'), '', 'fa fa-plus-circle', DOL_URL_ROOT.'/comm/action/card.php?action=create'.$out);
} else {
	$morehtmlright .= dolGetButtonTitle($langs->trans('AddAction'), '', 'fa fa-plus-circle', DOL_URL_ROOT.'/comm/action/card.php?action=create'.$out, '', 0);
}

if ($user->hasRight('agenda', 'myactions', 'read') || $user->hasRight('agenda', 'allactions', 'read')) {
	print '<br>';

	$param = '&id='.(int) $object->id;
	if (!empty($contextpage) && $contextpage != getDolDefaultContextPage(__FILE__)) {
		$param .= '&contextpage='.urlencode($contextpage);
	}
	if ($limit > 0 && $limit != $conf->liste_limit) {
		$param .= '&limit='.((int) $limit);
	}
	if ($search_rowid) {
		$param .= '&search_rowid='.urlencode($search_rowid);
	}
	if ($actioncode !== '' && $actioncode !== '-1') {
		if (is_array($actioncode)) {
			foreach ($actioncode as $tmpactioncode) {
				$param .= '&actioncode[]='.urlencode($tmpactioncode);
			}
		} else {
			$param .= '&actioncode='.urlencode($actioncode);
		}
	}
	if ($search_agenda_label) {
		$param .= '&search_agenda_label='.urlencode($search_agenda_label);
	}
	if ($search_complete != '') {
		$param .= '&search_complete='.urlencode($search_complete);
	}
	if ($search_filtert != '') {
		$param .= '&search_filtert='.urlencode((string) $search_filtert);
	}

	$nbEvent = powerplantpvAttestationCountAgendaEvents($object);
	$titlelist = $langs->trans('ActionsOnAttestation').(is_numeric($nbEvent) ? '<span class="opacitymedium colorblack paddingleft">('.$nbEvent.')</span>' : '');
	if (!empty($conf->dol_optimize_smallscreen)) {
		$titlelist = $langs->trans('Actions').(is_numeric($nbEvent) ? '<span class="opacitymedium colorblack paddingleft">('.$nbEvent.')</span>' : '');
	}

	print_barre_liste($titlelist, 0, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', 0, -1, '', 0, $morehtmlright, '', 0, 1, 0);

	$filters = array(
		'search_agenda_label' => $search_agenda_label,
		'search_rowid' => $search_rowid,
		'search_complete' => $search_complete,
		'search_filtert' => $search_filtert,
	);

	show_actions_done($conf, $langs, $db, $object, null, 0, $actioncode, '', $filters, $sortfield, $sortorder, $object->module);
}

llxFooter();
$db->close();
