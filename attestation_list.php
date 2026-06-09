<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr> */

/**
 * \file		attestation_list.php
 * \ingroup		powerplantpv
 * \brief		Attestation list.
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

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/powerplantpv/class/powerplantpvattestation.class.php');
dol_include_once('/powerplantpv/class/powerplantpvattestationtypes.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_attestation.lib.php');

$langs->loadLangs(array('powerplantpv@powerplantpv', 'companies', 'projects'));

if (!isModEnabled('powerplantpv') || !getDolGlobalInt('POWERPLANTPV_ATTESTATION_ENABLE', 1)) {
	accessforbidden();
}

if (!class_exists('PowerPlantPVAttestation') || !class_exists('PowerPlantPVAttestationTypes') || !function_exists('powerplantpvAttestationUserHasRight')) {
	llxHeader('', $langs->trans('Attestations'), '', '', 0, 0, '', '', '', 'mod-powerplantpv page-attestation-list');
	print '<div class="error">'.$langs->trans('AttestationInstallationIncomplete').'</div>';
	llxFooter();
	$db->close();
	exit;
}

$permissiontoread = powerplantpvAttestationUserHasRight($user, 'read');
$permissiontoadd = powerplantpvAttestationUserHasRight($user, 'write');
$permissiontodelete = powerplantpvAttestationUserHasRight($user, 'delete');
if (!$permissiontoread) {
	accessforbidden();
}

if (function_exists('powerplantpvAttestationGetInstallationIssues')) {
	$attestationInstallationIssues = powerplantpvAttestationGetInstallationIssues();
	if (!empty($attestationInstallationIssues['tables'])) {
		llxHeader('', $langs->trans('Attestations'), '', '', 0, 0, '', '', '', 'mod-powerplantpv page-attestation-list');
		powerplantpvAttestationPrintInstallationWarnings();
		llxFooter();
		$db->close();
		exit;
	}
}

$form = new Form($db);
$object = new PowerPlantPVAttestation($db);

$action = GETPOST('action', 'aZ09');
$massaction = GETPOST('massaction', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$toselect = GETPOST('toselect', 'array:int');
$search_all = trim(GETPOST('search_all', 'alphanohtml'));
$search_ref = trim(GETPOST('search_ref', 'alphanohtml'));
$search_type = GETPOST('search_type', 'alphanohtml');
$search_powerplant = GETPOSTINT('search_powerplant');
$search_soc = GETPOSTINT('search_soc');
$search_project = GETPOSTINT('search_project');
$search_status = GETPOSTISSET('search_status') ? GETPOST('search_status', 'int') : '';
$search_signed = GETPOSTISSET('search_signed') ? GETPOST('search_signed', 'int') : '';
$search_signature = trim(GETPOST('search_signature', 'alphanohtml'));
$search_entity = GETPOST('search_entity', 'array:int');

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
	$sortfield = 't.date_creation';
}
if (!$sortorder) {
	$sortorder = 'DESC';
}

$entityScope = getEntity('attestation');
$availableEntities = array();
foreach (explode(',', $entityScope) as $entityId) {
	$entityId = (int) trim($entityId);
	if ($entityId > 0) {
		$availableEntities[$entityId] = (string) $entityId;
	}
}
$showEnvironment = count($availableEntities) > 1;

if ($massaction == 'delete' && $confirm == 'yes' && $permissiontodelete) {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden('Bad token');
	}
	$error = 0;
	foreach ((array) $toselect as $selectedId) {
		$tmp = new PowerPlantPVAttestation($db);
		if ($tmp->fetch((int) $selectedId) > 0) {
			$result = $tmp->delete($user);
			if ($result < 0) {
				$error++;
				setEventMessages($tmp->error, $tmp->errors, 'errors');
			}
		}
	}
	if (!$error) {
		setEventMessages($langs->trans('RecordsDeleted'), null, 'mesgs');
	}
}

$arrayfields = array(
	't.ref' => array('label' => 'Ref', 'checked' => 1),
	't.type_code' => array('label' => 'AttestationType', 'checked' => 1),
	'p.ref' => array('label' => 'PowerPlant', 'checked' => 1),
	's.nom' => array('label' => 'ThirdParty', 'checked' => 1),
	'pr.ref' => array('label' => 'Project', 'checked' => 1),
	't.date_attestation' => array('label' => 'AttestationDate', 'checked' => 1),
	't.status' => array('label' => 'Status', 'checked' => 1),
	't.date_signature' => array('label' => 'AttestationSigned', 'checked' => 1),
	't.entity' => array('label' => 'Environment', 'checked' => ($showEnvironment ? 1 : 0)),
	't.date_creation' => array('label' => 'DateCreation', 'checked' => 0),
);

$param = '';
foreach (array('search_all', 'search_ref', 'search_type', 'search_powerplant', 'search_soc', 'search_project', 'search_status', 'search_signed', 'search_signature') as $paramkey) {
	if ($$paramkey !== '' && $$paramkey !== 0) {
		$param .= '&'.$paramkey.'='.urlencode((string) $$paramkey);
	}
}
if (is_array($search_entity)) {
	foreach ($search_entity as $entityId) {
		$param .= '&search_entity[]='.(int) $entityId;
	}
}

$sqlFrom = " FROM ".$db->prefix()."powerplantpv_attestation as t";
$sqlFrom .= " LEFT JOIN ".$db->prefix()."powerplantpv_powerplant as p ON p.rowid = t.fk_powerplant";
$sqlFrom .= " LEFT JOIN ".$db->prefix()."societe as s ON s.rowid = t.fk_soc";
$sqlFrom .= " LEFT JOIN ".$db->prefix()."projet as pr ON pr.rowid = t.fk_project";
$sqlWhere = " WHERE t.entity IN (".$entityScope.")";
if (!empty($user->socid)) {
	$sqlWhere .= " AND t.fk_soc = ".((int) $user->socid);
}
if ($search_all !== '') {
	$sqlWhere .= natural_search(array('t.ref', 't.project_name', 't.address', 't.town', 't.installer_name', 't.writer_name'), $search_all);
}
if ($search_ref !== '') {
	$sqlWhere .= natural_search('t.ref', $search_ref);
}
if ($search_type !== '') {
	$sqlWhere .= " AND t.type_code = '".$db->escape($search_type)."'";
}
if ($search_powerplant > 0) {
	$sqlWhere .= " AND t.fk_powerplant = ".$search_powerplant;
}
if ($search_soc > 0) {
	$sqlWhere .= " AND t.fk_soc = ".$search_soc;
}
if ($search_project > 0) {
	$sqlWhere .= " AND t.fk_project = ".$search_project;
}
if ($search_status !== '') {
	$sqlWhere .= " AND t.status = ".((int) $search_status);
}
if ($search_signed !== '') {
	$sqlWhere .= ((int) $search_signed ? " AND t.date_signature IS NOT NULL" : " AND t.date_signature IS NULL");
}
if ($search_signature !== '') {
	$sqlWhere .= natural_search(array('t.signature_hash', 't.signature_ip'), $search_signature);
}
if (!empty($search_entity) && is_array($search_entity)) {
	$entities = array();
	foreach ($search_entity as $entityId) {
		$entityId = (int) $entityId;
		if ($entityId > 0 && isset($availableEntities[$entityId])) {
			$entities[] = $entityId;
		}
	}
	if (!empty($entities)) {
		$sqlWhere .= " AND t.entity IN (".implode(',', $entities).")";
	}
}

$sqlCount = "SELECT COUNT(t.rowid) as nb".$sqlFrom.$sqlWhere;
$resqlCount = $db->query($sqlCount);
$num = 0;
if ($resqlCount && ($objCount = $db->fetch_object($resqlCount))) {
	$num = (int) $objCount->nb;
}

$sql = "SELECT t.rowid, t.ref, t.entity, t.type_code, t.project_name, t.date_attestation, t.status, t.date_signature, t.fk_powerplant, t.fk_soc, t.fk_project,";
$sql .= " p.ref as powerplant_ref, p.label as powerplant_label, s.nom as thirdparty_name, pr.ref as project_ref, pr.title as project_title";
$sql .= $sqlFrom.$sqlWhere.$db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);
$resql = $db->query($sql);

llxHeader('', $langs->trans('Attestations'), '', '', 0, 0, '', '', '', 'mod-powerplantpv page-attestation-list');

$newcardbutton = dolGetButtonTitle($langs->trans('New_Attestation'), '', 'fa fa-plus-circle', dol_buildpath('/powerplantpv/attestation_card.php', 1).'?action=create', '', $permissiontoadd);
print_barre_liste($langs->trans('Attestations'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $num, 'fa-file-signature', 0, $newcardbutton, '', $limit);

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste listwithfilterbefore centpercent">';
print '<tr class="liste_titre_filter">';
print '<td><input type="text" class="flat maxwidth100" name="search_ref" value="'.dol_escape_htmltag($search_ref).'"></td>';
print '<td>'.$form->selectarray('search_type', array('' => '') + PowerPlantPVAttestationTypes::getTypeLabels($langs), $search_type, 0, 0, 0, '', 0, 0, 0, '', 'flat maxwidth150').'</td>';
print '<td><input type="text" class="flat maxwidth100" name="search_powerplant" value="'.($search_powerplant > 0 ? (int) $search_powerplant : '').'"></td>';
print '<td><input type="text" class="flat maxwidth100" name="search_soc" value="'.($search_soc > 0 ? (int) $search_soc : '').'"></td>';
print '<td><input type="text" class="flat maxwidth100" name="search_project" value="'.($search_project > 0 ? (int) $search_project : '').'"></td>';
print '<td></td>';
print '<td>'.$form->selectarray('search_status', array('' => '') + $object->fields['status']['arrayofkeyval'], $search_status, 0, 0, 0, '', 0, 0, 0, '', 'flat maxwidth125').'</td>';
print '<td>'.$form->selectyesno('search_signed', $search_signed, 1).'</td>';
if ($showEnvironment) {
	print '<td>'.$form->multiselectarray('search_entity', $availableEntities, $search_entity, 0, 0, 'minwidth100', 0, '100%').'</td>';
}
print '<td class="center"><button type="submit" class="liste_titre button_search" name="button_search" value="x">'.img_picto($langs->trans('Search'), 'search').'</button></td>';
print '</tr>';

print '<tr class="liste_titre">';
print_liste_field_titre('Ref', $_SERVER['PHP_SELF'], 't.ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('AttestationType', $_SERVER['PHP_SELF'], 't.type_code', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('PowerPlant', $_SERVER['PHP_SELF'], 'p.ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('ThirdParty', $_SERVER['PHP_SELF'], 's.nom', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Project', $_SERVER['PHP_SELF'], 'pr.ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('AttestationDate', $_SERVER['PHP_SELF'], 't.date_attestation', '', $param, 'class="center"', $sortfield, $sortorder);
print_liste_field_titre('Status', $_SERVER['PHP_SELF'], 't.status', '', $param, 'class="center"', $sortfield, $sortorder);
print_liste_field_titre('AttestationSigned', $_SERVER['PHP_SELF'], 't.date_signature', '', $param, 'class="center"', $sortfield, $sortorder);
if ($showEnvironment) {
	print_liste_field_titre('Environment', $_SERVER['PHP_SELF'], 't.entity', '', $param, 'class="center"', $sortfield, $sortorder);
}
print '<th></th>';
print '</tr>';

if ($resql) {
	$i = 0;
	while ($i < min($num, $limit) && ($obj = $db->fetch_object($resql))) {
		$i++;
		$tmp = new PowerPlantPVAttestation($db);
		$tmp->id = (int) $obj->rowid;
		$tmp->ref = (string) $obj->ref;
		$tmp->status = (int) $obj->status;
		$tmp->project_name = (string) $obj->project_name;
		print '<tr class="oddeven">';
		print '<td>'.$tmp->getNomUrl(1).'</td>';
		$type = PowerPlantPVAttestationTypes::getType($obj->type_code);
		print '<td>'.dol_escape_htmltag(!empty($type['label']) ? $langs->trans($type['label']) : $obj->type_code).'</td>';
		print '<td>'.dol_escape_htmltag(trim($obj->powerplant_ref.' - '.$obj->powerplant_label)).'</td>';
		print '<td>'.dol_escape_htmltag($obj->thirdparty_name).'</td>';
		print '<td>'.dol_escape_htmltag(trim($obj->project_ref.' - '.$obj->project_title)).'</td>';
		print '<td class="center">'.dol_print_date($db->jdate($obj->date_attestation), 'day').'</td>';
		print '<td class="center">'.$tmp->getLibStatut(5).'</td>';
		print '<td class="center">'.(!empty($obj->date_signature) ? dol_print_date($db->jdate($obj->date_signature), 'dayhour') : '').'</td>';
		if ($showEnvironment) {
			print '<td class="center">'.((int) $obj->entity).'</td>';
		}
		print '<td class="nowrap center"><input type="checkbox" name="toselect[]" value="'.((int) $obj->rowid).'"></td>';
		print '</tr>';
	}
	if ($i == 0) {
		$colspan = $showEnvironment ? 10 : 9;
		print '<tr class="oddeven"><td colspan="'.$colspan.'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}
} else {
	print '<tr class="oddeven"><td colspan="'.($showEnvironment ? 10 : 9).'">'.dol_escape_htmltag($db->lasterror()).'</td></tr>';
}

print '</table>';
print '</div>';
if ($permissiontodelete) {
	print '<div class="tabsAction">';
	print '<input type="hidden" name="massaction" value="delete">';
	print '<input type="hidden" name="confirm" value="yes">';
	print '<input type="submit" class="button button-delete" value="'.$langs->trans('Delete').'">';
	print '</div>';
}
print '</form>';

llxFooter();
$db->close();
