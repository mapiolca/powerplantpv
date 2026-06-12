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
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/cactioncomm.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
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
$search_rowid = GETPOST('search_rowid', 'alphanohtml');
$search_agenda_label = GETPOST('search_agenda_label', 'alphanohtml');
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
$formactions = new FormActions($db);
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

	$allowedSortFields = array('a.id', 'a.datep,a.id', 'a.percent');
	if (!in_array($sortfield, $allowedSortFields, true)) {
		$sortfield = 'a.datep,a.id';
	}
	$sortorder = strtoupper($sortorder);
	if (!in_array($sortorder, array('ASC', 'DESC'), true)) {
		$sortorder = 'DESC';
	}

	$start_year = GETPOSTINT('dateevent_startyear');
	$start_month = GETPOSTINT('dateevent_startmonth');
	$start_day = GETPOSTINT('dateevent_startday');
	$end_year = GETPOSTINT('dateevent_endyear');
	$end_month = GETPOSTINT('dateevent_endmonth');
	$end_day = GETPOSTINT('dateevent_endday');
	$tms_start = '';
	$tms_end = '';
	if (!empty($start_year) && !empty($start_month) && !empty($start_day)) {
		$tms_start = dol_mktime(0, 0, 0, $start_month, $start_day, $start_year, 'tzuserrel');
	}
	if (!empty($end_year) && !empty($end_month) && !empty($end_day)) {
		$tms_end = dol_mktime(23, 59, 59, $end_month, $end_day, $end_year, 'tzuserrel');
	}
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
		$tms_start = '';
		$tms_end = '';
	}

	$canedit = 1;
	if (!$user->hasRight('agenda', 'myactions', 'read') || !$user->hasRight('agenda', 'allactions', 'read')) {
		$canedit = 0;
	}
	if (!$user->hasRight('agenda', 'allactions', 'read')) {
		$search_filtert = (int) $user->id;
	}

	$filters = array(
		'search_agenda_label' => $search_agenda_label,
		'search_rowid' => $search_rowid,
		'search_complete' => $search_complete,
		'search_filtert' => $search_filtert,
	);

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
	foreach (array('start' => array($start_year, $start_month, $start_day), 'end' => array($end_year, $end_month, $end_day)) as $datesuffix => $dateparts) {
		if (!empty($dateparts[0]) && !empty($dateparts[1]) && !empty($dateparts[2])) {
			$param .= '&dateevent_'.$datesuffix.'year='.((int) $dateparts[0]);
			$param .= '&dateevent_'.$datesuffix.'month='.((int) $dateparts[1]);
			$param .= '&dateevent_'.$datesuffix.'day='.((int) $dateparts[2]);
		}
	}

	$titlelist = $langs->trans('ActionsOnAttestation');
	if (!empty($conf->dol_optimize_smallscreen)) {
		$titlelist = $langs->trans('Actions');
	}

	$sqlselect = "SELECT a.id, a.label, a.datep as dp, a.datep2 as dp2, a.percent, a.fk_element, a.elementtype, a.fk_contact, a.code,";
	$sqlselect .= " c.code as acode, c.libelle as alabel, c.picto as apicto,";
	$sqlselect .= " u.rowid as user_id, u.login as user_login, u.photo as user_photo, u.firstname as user_firstname, u.lastname as user_lastname";

	$sqlfromwhere = " FROM ".$db->prefix()."actioncomm as a";
	$sqlfromwhere .= " LEFT JOIN ".$db->prefix()."user as u ON u.rowid = a.fk_user_action";
	$sqlfromwhere .= " LEFT JOIN ".$db->prefix()."c_actioncomm as c ON a.fk_action = c.id";
	$sqlfromwhere .= " WHERE a.entity IN (".getEntity('agenda').")";
	$sqlfromwhere .= " AND a.fk_element = ".((int) $object->id);
	$sqlfromwhere .= " AND a.elementtype = 'attestation@powerplantpv'";

	if (!empty($tms_start) && !empty($tms_end)) {
		$sqlfromwhere .= " AND ((a.datep BETWEEN '".$db->idate($tms_start)."' AND '".$db->idate($tms_end)."') OR (a.datep2 BETWEEN '".$db->idate($tms_start)."' AND '".$db->idate($tms_end)."'))";
	} elseif (empty($tms_start) && !empty($tms_end)) {
		$sqlfromwhere .= " AND ((a.datep <= '".$db->idate($tms_end)."') OR (a.datep2 <= '".$db->idate($tms_end)."'))";
	} elseif (!empty($tms_start) && empty($tms_end)) {
		$sqlfromwhere .= " AND ((a.datep >= '".$db->idate($tms_start)."') OR (a.datep2 >= '".$db->idate($tms_start)."'))";
	}

	if (is_array($actioncode) && !empty($actioncode)) {
		$actionconditions = array();
		foreach ($actioncode as $code) {
			if ((string) $code === '-1' || (string) $code === '') {
				continue;
			}
			$condition = '';
			addEventTypeSQL($condition, $code, '');
			$condition = trim($condition);
			if ($condition !== '') {
				$actionconditions[] = $condition;
			}
		}
		if (!empty($actionconditions)) {
			$sqlfromwhere .= " AND (".implode(' OR ', $actionconditions).")";
		}
	} elseif (!empty($actioncode) && $actioncode != '-1') {
		addEventTypeSQL($sqlfromwhere, $actioncode);
	}

	addOtherFilterSQL($sqlfromwhere, '', dol_now('tzuser'), $filters);

	$totalnboflines = 0;
	$sqlcount = "SELECT COUNT(a.id) as nb".$sqlfromwhere;
	$resqlcount = $db->query($sqlcount);
	if ($resqlcount) {
		$objcount = $db->fetch_object($resqlcount);
		$totalnboflines = !empty($objcount->nb) ? (int) $objcount->nb : 0;
		$db->free($resqlcount);
	} else {
		dol_print_error($db);
	}

	$sqllist = $sqlselect.$sqlfromwhere.$db->order($sortfield, $sortorder);
	if ($limit) {
		$sqllist .= $db->plimit($limit + 1, $offset);
	}
	$resqllist = $db->query($sqllist);
	$num = 0;
	if ($resqllist) {
		$num = $db->num_rows($resqllist);
	} else {
		dol_print_error($db);
	}

	print '<form name="listactionsfilter" class="listactionsfilter" action="'.$_SERVER['PHP_SELF'].'" method="GET">';
	print '<input type="hidden" name="id" value="'.((int) $object->id).'">';
	print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
	print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
	if (!empty($contextpage) && $contextpage != getDolDefaultContextPage(__FILE__)) {
		print '<input type="hidden" name="contextpage" value="'.dol_escape_htmltag($contextpage).'">';
	}

	print_barre_liste($titlelist, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $totalnboflines, 'object_action', 0, $morehtmlright, '', $limit, 0, 0, 1);

	$complete = (string) (!empty($filters['search_complete']) ? $filters['search_complete'] : '');
	$percent = ($complete !== '' ? $complete : -1);
	if ((string) $complete === '0') {
		$percent = '0';
	} elseif ((int) $complete === 100) {
		$percent = '100';
	}

	print '<div class="div-table-responsive-no-min">';
	print '<table class="tagtable nobottomiftotal liste listwithfilterbefore">';

	print '<tr class="liste_titre_filter">';
	if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
		print '<th class="liste_titre width50 middle">';
		print $form->showFilterAndCheckAddButtons(0, 'checkforselect', 1);
		print '</th>';
	}
	print '<td class="liste_titre"><input type="text" class="width50" name="search_rowid" value="'.dol_escape_htmltag((string) $search_rowid).'"></td>';
	print '<td class="liste_titre center">';
	print $form->selectDateToDate($tms_start, $tms_end, 'dateevent', 1);
	print '</td>';
	print '<td class="liste_titre">';
	print $form->select_dolusers(($filters['search_filtert'] > 0 ? $filters['search_filtert'] : ''), 'search_filtert', 1, null, (int) !$canedit, '', '', '0', 0, 0, '', 2, '', 'minwidth100 maxwidth250 widthcentpercentminusx');
	print '</td>';
	print '<td class="liste_titre">';
	print $formactions->select_type_actions($actioncode, 'actioncode', '', getDolGlobalString('AGENDA_USE_EVENT_TYPE') ? -1 : 1, 0, (getDolGlobalString('AGENDA_USE_MULTISELECT_TYPE') ? 1 : 0), 1, 'selecttype combolargeelem minwidth100 maxwidth150', 1);
	print '</td>';
	print '<td class="liste_titre maxwidth100onsmartphone"><input type="text" class="maxwidth125" name="search_agenda_label" value="'.dol_escape_htmltag((string) $filters['search_agenda_label']).'"></td>';
	print '<td class="liste_titre"></td>';
	print '<td class="liste_titre"></td>';
	print '<td class="liste_titre parentonrightofpage">';
	print $formactions->form_select_status_action('formaction', $percent, 1, 'search_complete', 1, 2, 'search_status width100 onrightofpage', 1);
	print '</td>';
	if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
		print '<td class="liste_titre center">';
		print $form->showFilterAndCheckAddButtons(0, 'checkforselect', 1);
		print '</td>';
	}
	print '</tr>';

	print '<tr class="liste_titre">';
	if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
		print getTitleFieldOfList('', 0, $_SERVER['PHP_SELF'], '', '', $param, '', $sortfield, $sortorder, 'maxwidthsearch ');
	}
	print getTitleFieldOfList('Ref', 0, $_SERVER['PHP_SELF'], 'a.id', '', $param, '', $sortfield, $sortorder);
	print getTitleFieldOfList('Date', 0, $_SERVER['PHP_SELF'], 'a.datep,a.id', '', $param, '', $sortfield, $sortorder, 'center ');
	print getTitleFieldOfList('Owner');
	print getTitleFieldOfList('Type');
	print getTitleFieldOfList('Title', 0, $_SERVER['PHP_SELF'], '', '', $param, '', $sortfield, $sortorder);
	print getTitleFieldOfList('ActionOnContact', 0, $_SERVER['PHP_SELF'], '', '', $param, '', $sortfield, $sortorder, 'tdoverflowmax125 ', 0, '', 0);
	print getTitleFieldOfList('LinkedObject', 0, $_SERVER['PHP_SELF'], '', '', $param, '', $sortfield, $sortorder);
	print getTitleFieldOfList('Status', 0, $_SERVER['PHP_SELF'], 'a.percent', '', $param, '', $sortfield, $sortorder, 'center ');
	if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
		print getTitleFieldOfList('', 0, $_SERVER['PHP_SELF'], '', '', $param, '', $sortfield, $sortorder, 'maxwidthsearch ');
	}
	print '</tr>';

	$caction = new CActionComm($db);
	$arraylist = $caction->liste_array(1, 'code', '', (getDolGlobalString('AGENDA_USE_EVENT_TYPE') ? 0 : 1), '', 1);
	$userlinkcache = array();
	$contactlinkcache = array();
	$elementlinkcache = array();
	$i = 0;
	$imaxinloop = ($limit ? min($num, $limit) : $num);
	if ($resqllist) {
		while ($i < $imaxinloop) {
			$obj = $db->fetch_object($resqllist);
			if (empty($obj)) {
				break;
			}

			$actionstatic = new ActionComm($db);
			$actionstatic->fetch((int) $obj->id);
			$actionstatic->fetchResources();
			if (empty($actionstatic->code)) {
				$actionstatic->code = !empty($obj->code) ? $obj->code : $obj->acode;
			}
			$actionstatic->type_picto = !empty($obj->apicto) ? $obj->apicto : '';
			$actionstatic->type_code = $obj->acode;
			$datestart = $db->jdate($obj->dp);
			$dateend = $db->jdate($obj->dp2);

			print '<tr class="oddeven">';
			if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
				print '<td></td>';
			}

			print '<td class="nowraponall">'.$actionstatic->getNomUrl(1, -1).'</td>';

			print '<td class="center nowraponall nopaddingtopimp nopaddingbottomimp">';
			$tmpa = dol_getdate($datestart);
			$tmpb = !empty($dateend) ? dol_getdate($dateend) : $tmpa;
			if ($tmpa['mday'] == $tmpb['mday'] && $tmpa['mon'] == $tmpb['mon'] && $tmpa['year'] == $tmpb['year']) {
				print '<div class="center inline-block lineheightsmall">';
				print dol_print_date($datestart, 'dayreduceformat', 'tzuserrel');
				print '<br><span class="opacitymedium hourspan">';
				print dol_print_date($datestart, 'hourreduceformat', 'tzuserrel');
				if (!empty($dateend) && ($tmpa['hours'] != $tmpb['hours'] || $tmpa['minutes'] != $tmpb['minutes'])) {
					print '-'.dol_print_date($dateend, 'hourreduceformat', 'tzuserrel');
				}
				print '</span></div>';
			} else {
				print '<div class="center inline-block lineheightsmall">';
				print dol_print_date($datestart, 'dayreduceformat', 'tzuserrel');
				print '<br><span class="opacitymedium hourspan">'.dol_print_date($datestart, 'hourreduceformat', 'tzuserrel').'</span>';
				print '</div> - <div class="center inline-block lineheightsmall">';
				print dol_print_date($dateend, 'dayreduceformat', 'tzuserrel');
				print '<br><span class="opacitymedium hourspan">'.dol_print_date($dateend, 'hourreduceformat', 'tzuserrel').'</span>';
				print '</div>';
			}
			print '</td>';

			print '<td class="tdoverflowmax125">';
			if (!empty($obj->user_id)) {
				if (!isset($userlinkcache[$obj->user_id])) {
					$userstatic = new User($db);
					if ($userstatic->fetch((int) $obj->user_id) > 0) {
						$userlinkcache[$obj->user_id] = $userstatic->getNomUrl(-1, '', 0, 0, 16, 0, 'firstelselast', '');
					} else {
						$userlinkcache[$obj->user_id] = dol_escape_htmltag((string) $obj->user_login);
					}
				}
				print $userlinkcache[$obj->user_id];
			}
			print '</td>';

			$labeltype = $actionstatic->type_code;
			if (!getDolGlobalString('AGENDA_USE_EVENT_TYPE') && empty($arraylist[$labeltype])) {
				$labeltype = 'AC_OTH';
			}
			if (!empty($actionstatic->code) && preg_match('/^TICKET_MSG/', $actionstatic->code)) {
				$labeltype = $langs->trans('Message');
			} else {
				if (!empty($arraylist[$labeltype])) {
					$labeltype = $arraylist[$labeltype];
				} elseif ($actionstatic->type_code == 'AC_EMAILING') {
					$langs->load('mails');
					$labeltype = $langs->trans('Emailing');
				}
				if ($actionstatic->type_code == 'AC_OTH_AUTO' && ($actionstatic->type_code != $actionstatic->code) && $labeltype && !empty($arraylist[$actionstatic->code])) {
					$labeltype .= ' - '.$arraylist[$actionstatic->code];
				}
			}
			$labeltypelong = $labeltype.($actionstatic->type_code == 'AC_OTH_AUTO' ? ' (auto)' : '');
			print '<td class="tdoverflowmax125" title="'.dol_escape_htmltag($labeltypelong).'">';
			print $actionstatic->getTypePicto();
			print dol_trunc($labeltype, 28);
			print '</td>';

			print '<td class="tdoverflowmax300" title="'.dol_escape_htmltag($actionstatic->label).'">';
			print dol_trunc($actionstatic->label, 120);
			print '</td>';

			print '<td class="valignmiddle">';
			if (!empty($actionstatic->socpeopleassigned) && is_array($actionstatic->socpeopleassigned)) {
				foreach ($actionstatic->socpeopleassigned as $cid => $cvalue) {
					$contactid = is_array($cvalue) && !empty($cvalue['id']) ? (int) $cvalue['id'] : (int) $cid;
					if ($contactid <= 0) {
						continue;
					}
					if (!isset($contactlinkcache[$contactid])) {
						$contact = new Contact($db);
						$contactlinkcache[$contactid] = ($contact->fetch($contactid) > 0 ? $contact->getNomUrl(-1, '', 0) : '');
					}
					print $contactlinkcache[$contactid];
				}
			} elseif (!empty($obj->fk_contact)) {
				$contactid = (int) $obj->fk_contact;
				if (!isset($contactlinkcache[$contactid])) {
					$contact = new Contact($db);
					$contactlinkcache[$contactid] = ($contact->fetch($contactid) > 0 ? $contact->getNomUrl(-1, '', 0) : '');
				}
				print $contactlinkcache[$contactid];
			}
			print '</td>';

			print '<td class="tdoverflowmax200 nowraponall">';
			if (!empty($obj->elementtype) && !empty($obj->fk_element)) {
				if (!isset($elementlinkcache[$obj->elementtype])) {
					$elementlinkcache[$obj->elementtype] = array();
				}
				if (!isset($elementlinkcache[$obj->elementtype][$obj->fk_element])) {
					$link = dolGetElementUrl((int) $obj->fk_element, $obj->elementtype, 1);
					if (empty($link) && $obj->elementtype === 'attestation@powerplantpv' && (int) $obj->fk_element === (int) $object->id) {
						$link = $object->getNomUrl(1);
					}
					$elementlinkcache[$obj->elementtype][$obj->fk_element] = $link;
				}
				print $elementlinkcache[$obj->elementtype][$obj->fk_element];
			}
			print '</td>';

			print '<td class="nowrap center">';
			print $actionstatic->LibStatut((int) $obj->percent, 2, 0, $datestart);
			print '</td>';

			if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
				print '<td></td>';
			}
			print '</tr>';

			$i++;
		}
		$db->free($resqllist);
	}
	if ($num == 0) {
		print '<tr class="oddeven"><td colspan="9"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}

	print '</table>';
	print '</div>';
	print '</form>';
}

llxFooter();
$db->close();
