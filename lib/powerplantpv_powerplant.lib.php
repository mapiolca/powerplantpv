<?php
/* Copyright (C) 2025		Pierre Ardoin				<erp@lesmetiersdubatiment.fr>
 * Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 * Copyright (C) 2025       Frédéric France         <frederic.france@free.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    lib/powerplantpv_powerplant.lib.php
 * \ingroup powerplantpv
 * \brief   Library files with common functions for PowerPlant
 */

/**
 * Prepare array of tabs for PowerPlant
 *
 * @param	PowerPlant	$object					PowerPlant
 * @return 	array<array{string,string,string}>	Array of tabs
 */
function powerplantPrepareHead($object)
{
	global $db, $langs, $conf;

	$langs->load("powerplantpv@powerplantpv");

	$h = 0;
	$head = array();

	$head[$h][0] = dolBuildUrl(dol_buildpath("/powerplantpv/powerplant_card.php", 1), ['id' => $object->id]);
	$head[$h][1] = $langs->trans("PowerPlant");
	$head[$h][2] = 'card';
	$h++;


	$head[$h][0] = dolBuildUrl(dol_buildpath('/powerplantpv/powerplant_composition.php', 1), ['id' => $object->id]);
	$head[$h][1] = $langs->trans('PowerPlantMaterialComposition');
	$head[$h][2] = 'composition';
	$h++;

	$nbContact = powerplantCountContacts($object);
	$head[$h][0] = dolBuildUrl(dol_buildpath("/powerplantpv/powerplant_contact.php", 1), ['id' => $object->id]);
	$head[$h][1] = $langs->trans("ContactsAddresses");
	if ($nbContact > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">'.$nbContact.'</span>';
	}
	$head[$h][2] = 'contact';
	$h++;

	if (isset($object->fields['note_public']) || isset($object->fields['note_private'])) {
		$nbNote = 0;
		if (!empty($object->note_private)) {
			$nbNote++;
		}
		if (!empty($object->note_public)) {
			$nbNote++;
		}
		$head[$h][0] = dolBuildUrl(dol_buildpath('/powerplantpv/powerplant_note.php', 1), ['id' => $object->id]);
		$head[$h][1] = $langs->trans('Notes');
		if ($nbNote > 0) {
			$head[$h][1] .= (!getDolGlobalInt('MAIN_OPTIMIZEFORTEXTBROWSER') ? '<span class="badge marginleftonlyshort">'.$nbNote.'</span>' : '');
		}
		$head[$h][2] = 'note';
		$h++;
	}

	$nbFiles = powerplantCountAttachedFilesAndLinks($object);
	$head[$h][0] = dolBuildUrl(dol_buildpath("/powerplantpv/powerplant_document.php", 1), ['id' => $object->id]);
	$head[$h][1] = $langs->trans('Documents');
	if ($nbFiles > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">'.$nbFiles.'</span>';
	}
	$head[$h][2] = 'document';
	$h++;

	if (isModEnabled('agenda')) {
		$nbEvent = powerplantCountAgendaEvents($object);
		$head[$h][0] = dolBuildUrl(dol_buildpath("/powerplantpv/powerplant_agenda.php", 1), ['id' => $object->id]);
		$head[$h][1] = $langs->trans("EventsAgenda");
		if ($nbEvent > 0) {
			$head[$h][1] .= '<span class="badge marginleftonlyshort">'.$nbEvent.'</span>';
		}
		$head[$h][2] = 'agenda';
		$h++;
	}

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@powerplantpv:/powerplantpv/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@powerplantpv:/powerplantpv/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'powerplant@powerplantpv');

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'powerplant@powerplantpv', 'remove');

	return $head;
}

/**
 * Count contacts linked to a power plant.
 *
 * @param	PowerPlant	$object	PowerPlant
 * @return	int					Number of contacts
 */
function powerplantCountContacts($object)
{
	if (empty($object->id) || !method_exists($object, 'liste_contact')) {
		return 0;
	}

	$internal = $object->liste_contact(-1, 'internal');
	$external = $object->liste_contact(-1, 'external');

	return (is_array($internal) ? count($internal) : 0) + (is_array($external) ? count($external) : 0);
}

/**
 * Count attached files and external links.
 *
 * @param	PowerPlant	$object	PowerPlant
 * @return	int					Number of files and links
 */
function powerplantCountAttachedFilesAndLinks($object)
{
	global $db, $conf;

	if (empty($object->id) || empty($object->ref)) {
		return 0;
	}

	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';

	$upload_dir = powerplantGetDocumentUploadDir($object);
	$nbFiles = count(dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$'));
	$nbLinks = Link::count($db, $object->element, $object->id);

	return $nbFiles + $nbLinks;
}

/**
 * Return the native document modulepart for power plant files.
 *
 * @return	string	Document modulepart
 */
function powerplantGetDocumentModulePart()
{
	return 'powerplantpv';
}

/**
 * Return the relative document path for a power plant.
 *
 * @param	PowerPlant	$object	PowerPlant
 * @return	string				Relative path without trailing slash
 */
function powerplantGetDocumentRelativePath($object)
{
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

	return 'powerplant/'.dol_sanitizeFileName($object->ref);
}

/**
 * Return the upload directory for power plant documents.
 *
 * @param	PowerPlant	$object	PowerPlant
 * @return	string				Absolute directory
 */
function powerplantGetDocumentUploadDir($object)
{
	global $conf;

	$entity = (!empty($object->entity) ? $object->entity : $conf->entity);
	if (!empty($conf->powerplantpv->multidir_output[$entity])) {
		$diroutput = $conf->powerplantpv->multidir_output[$entity];
	} else {
		$diroutput = $conf->powerplantpv->dir_output;
	}

	return $diroutput.'/'.powerplantGetDocumentRelativePath($object);
}

/**
 * Return the canonical agenda element type for a power plant.
 *
 * @return	string	Agenda element type
 */
function powerplantGetAgendaElementType()
{
	return 'powerplant@powerplantpv';
}

/**
 * Return agenda element types that may have been used by older module versions.
 *
 * @return	string[]	Element types
 */
function powerplantGetCompatibleAgendaElementTypes()
{
	return array(powerplantGetAgendaElementType(), 'powerplant', 'powerplantpv_powerplant');
}

/**
 * Count agenda events linked to a power plant.
 *
 * @param	PowerPlant	$object	PowerPlant
 * @return	int					Number of events
 */
function powerplantCountAgendaEvents($object)
{
	global $db;

	if (empty($object->id) || !isModEnabled('agenda')) {
		return 0;
	}

	$elementtypes = powerplantGetCompatibleAgendaElementTypes();
	$escapedelementtypes = array();
	foreach ($elementtypes as $elementtype) {
		$escapedelementtypes[] = "'".$db->escape($elementtype)."'";
	}

	$sql = "SELECT COUNT(a.id) as nb";
	$sql .= " FROM ".$db->prefix()."actioncomm as a";
	$sql .= " WHERE a.fk_element = ".((int) $object->id);
	$sql .= " AND a.elementtype IN (".implode(',', $escapedelementtypes).")";
	$sql .= " AND a.entity IN (".getEntity('agenda').")";

	$resql = $db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		$db->free($resql);
		return (int) $obj->nb;
	}

	return 0;
}

/**
 * Return the back-to-list link used by power plant tabs.
 *
 * @param	PowerPlant	$object	PowerPlant
 * @param	int			$socid	Third party id
 * @return	string				HTML link
 */
function powerplantGetBackToListLink($object, $socid = 0)
{
	global $langs;

	return '<a href="'.dol_buildpath('/powerplantpv/powerplant_list.php', 1).'?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.(int) $socid : '').'">'.$langs->trans("BackToList").'</a>';
}

/**
 * Build native banner details for a power plant.
 *
 * @param	PowerPlant	$object				PowerPlant
 * @param	int<0,1>	$permissiontoadd	User can edit
 * @param	string		$action				Current action
 * @return	string							HTML details
 */
function powerplantBuildBannerMoreHtml($object, $permissiontoadd = 0, $action = '')
{
	global $db, $langs;

	require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
	$form = new Form($db);

	$morehtmlref = '<div class="refidno">';

	$morehtmlref .= '<br>';
	$morehtmlref .= $form->editfieldkey('Label', 'label', $object->label, $object, $permissiontoadd, 'string', '', 0, 1);
	$morehtmlref .= $form->editfieldval('Label', 'label', $object->label, $object, $permissiontoadd, 'string', dol_escape_htmltag($object->label), null, null, 'id='.$object->id, 1);

	if (isModEnabled('societe')) {
		if (!empty($object->fk_soc) || !empty($object->socid)) {
			$object->fetch_thirdparty();
		}

		$morehtmlref .= '<br>';
		if ($permissiontoadd && $action != 'editcustomer') {
			$morehtmlref .= ' <a class="editfielda" href="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.((int) $object->id).'&action=editcustomer">'.img_edit($langs->transnoentitiesnoconv('SetThirdParty'), 0).'</a>';
		}

		if ($permissiontoadd && $action == 'editcustomer') {
			$morehtmlref .= '<form method="post" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.((int) $object->id).'">';
			$morehtmlref .= '<input type="hidden" name="token" value="'.newToken().'">';
			$morehtmlref .= '<input type="hidden" name="action" value="set_thirdparty">';
			$morehtmlref .= $form->select_company((!empty($object->fk_soc) ? $object->fk_soc : $object->socid), 'fk_soc', '', 1, 0, 0, array(), 0, 'minwidth300');
			$morehtmlref .= ' <input type="submit" class="button valignmiddle" value="'.$langs->trans("Modify").'">';
			$morehtmlref .= '</form>';
		} elseif (!empty($object->thirdparty) && !empty($object->thirdparty->id)) {
			$morehtmlref .= $object->thirdparty->getNomUrl(1, 'customer');
		} else {
			$morehtmlref .= img_picto($langs->trans("ThirdParty"), 'company', 'class="pictofixedwidth"');
			$morehtmlref .= '<span class="opacitymedium">'.$langs->trans("None").'</span>';
		}
	}

	$morehtmlref .= '</div>';

	return $morehtmlref;
}

/**
 * Handle the native label edition action from banner.
 *
 * @param	PowerPlant	$object				PowerPlant
 * @param	string		$action				Current action
 * @param	int<0,1>	$permissiontoadd	User can edit
 * @param	User		$user				User
 * @return	int								0 if no action, <0 if KO
 */
function powerplantHandleSetLabelAction($object, $action, $permissiontoadd, $user)
{
	global $langs;

	if ($action != 'setlabel') {
		return 0;
	}
	if (GETPOST('cancel', 'alpha')) {
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	}
	if (empty($permissiontoadd)) {
		accessforbidden();
	}
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden();
	}

	$label = GETPOST('label', 'restricthtml');
	$triggermodname = $object->TRIGGER_PREFIX.'_MODIFY';
	$result = $object->setValueFrom('label', $label, '', $object->id, 'text', '', $user, $triggermodname);
	if ($result > 0) {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	}

	setEventMessages($object->error, $object->errors, 'errors');
	return -1;
}

/**
 * Handle the native third party edition action from banner.
 *
 * @param	PowerPlant	$object				PowerPlant
 * @param	string		$action				Current action
 * @param	int<0,1>	$permissiontoadd	User can edit
 * @param	User		$user				User
 * @return	int								0 if no action, <0 if KO
 */
function powerplantHandleSetThirdpartyAction($object, $action, $permissiontoadd, $user)
{
	global $langs;

	if ($action != 'set_thirdparty') {
		return 0;
	}
	if (empty($permissiontoadd)) {
		accessforbidden();
	}
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden();
	}

	$fk_soc = GETPOSTINT('fk_soc');
	$object->fk_soc = ($fk_soc > 0 ? $fk_soc : null);
	$object->socid = ($fk_soc > 0 ? $fk_soc : null);
	$result = $object->update($user);
	if ($result > 0) {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	}

	setEventMessages($object->error, $object->errors, 'errors');
	return -1;
}

/**
 * Render native category selector with a v20 fallback.
 *
 * @param	Form		$form		Form helper
 * @param	string		$categtype	Category type
 * @param	string		$htmlname	Input name
 * @param	PowerPlant	$object		PowerPlant
 * @return	string					HTML selector
 */
function powerplantSelectCategories($form, $categtype, $htmlname, $object)
{
	global $db;

	if (method_exists($form, 'selectCategories')) {
		return $form->selectCategories($categtype, $htmlname, $object);
	}

	require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';

	$cat = new Categorie($db);
	$cate_arbo = $form->select_all_categories($categtype, '', 'parent', 64, 0, 3);

	$arrayselected = GETPOST($htmlname, 'array:int');
	if (!is_array($arrayselected) && !empty($object->id)) {
		$arrayselected = $cat->containing($object->id, $categtype, 'id');
	}
	if (!is_array($arrayselected)) {
		$arrayselected = array();
	}

	$out = img_picto('', 'category', 'class="pictofixedwidth"');
	$out .= $form->multiselectarray($htmlname, $cate_arbo, $arrayselected, 0, 0, 'minwidth100 widthcentpercentminusxx', 0, 0);

	return $out;
}

/**
 * Trigger a PowerPlantPV automatic Agenda event with explicit label and note.
 *
 * @param	PowerPlant	$object		Power plant
 * @param	User		$user		User
 * @param	string		$triggercode	Business trigger code
 * @param	string		$label		Event label
 * @param	string		$message		Event private note
 * @return	int					0 on success, <0 on error
 */
function powerplantTriggerAgendaEvent($object, $user, $triggercode, $label, $message)
{
	if (empty($object->id) || empty($triggercode)) {
		return 0;
	}

	if (!isset($object->context) || !is_array($object->context)) {
		$object->context = array();
	}

	$oldContext = $object->context;
	$objectvars = get_object_vars($object);
	$hadActionMsg = array_key_exists('actionmsg', $objectvars);
	$hadActionMsg2 = array_key_exists('actionmsg2', $objectvars);
	$hadActionTypeCode = array_key_exists('actiontypecode', $objectvars);
	$oldActionMsg = ($hadActionMsg ? $object->actionmsg : null);
	$oldActionMsg2 = ($hadActionMsg2 ? $object->actionmsg2 : null);
	$oldActionTypeCode = ($hadActionTypeCode ? $object->actiontypecode : null);

	$object->context['actionmsg'] = $message;
	$object->context['actionmsg2'] = $label;
	$object->actionmsg = $message;
	$object->actionmsg2 = $label;
	$result = $object->call_trigger($triggercode, $user);

	$object->context = $oldContext;
	if ($hadActionMsg) {
		$object->actionmsg = $oldActionMsg;
	} else {
		unset($object->actionmsg);
	}
	if ($hadActionMsg2) {
		$object->actionmsg2 = $oldActionMsg2;
	} else {
		unset($object->actionmsg2);
	}
	if ($hadActionTypeCode) {
		$object->actiontypecode = $oldActionTypeCode;
	} else {
		unset($object->actiontypecode);
	}

	return ($result < 0 ? -1 : 0);
}

/**
 * Return a SQL date from a Dolibarr date value.
 *
 * @param	int|string|null	$date	Date value
 * @return	string				YYYY-MM-DD or empty string
 */
function powerplantDateToSqlDate($date)
{
	if (empty($date)) {
		return '';
	}
	if (is_numeric($date)) {
		return dol_print_date((int) $date, '%Y-%m-%d');
	}
	if (preg_match('/^(\d{4}-\d{2}-\d{2})/', (string) $date, $matches)) {
		return $matches[1];
	}

	return '';
}

/**
 * Return the commissioning date to apply to composition lines.
 *
 * @param	PowerPlant	$object	Power plant
 * @return	string				YYYY-MM-DD
 */
function powerplantGetCompositionCommissioningDate($object)
{
	$date = powerplantDateToSqlDate($object->commissioning_date);
	if ($date === '') {
		$date = dol_print_date(dol_now(), '%Y-%m-%d');
	}

	return $date;
}

/**
 * Count non-replaced composition lines that already have a commissioning date.
 *
 * @param	PowerPlant	$object	Power plant
 * @return	int				Number of lines
 */
function powerplantCountCompositionCommissioningDateConflicts($object)
{
	global $db, $conf;

	$entity = (!empty($object->entity) ? (int) $object->entity : (int) $conf->entity);
	$sql = "SELECT COUNT(c.rowid) as nb";
	$sql .= " FROM ".$db->prefix()."powerplantpv_powerplantcomp as c";
	$sql .= " WHERE c.fk_powerplant = ".((int) $object->id);
	$sql .= " AND c.entity = ".$entity;
	$sql .= " AND (c.fk_status IS NULL OR c.fk_status <> 6)";
	$sql .= " AND c.commissioning_date IS NOT NULL";

	$resql = $db->query($sql);
	if (!$resql) {
		return 0;
	}
	$obj = $db->fetch_object($resql);

	return ($obj ? (int) $obj->nb : 0);
}

/**
 * Apply the power plant commissioning date to active composition lines.
 *
 * @param	PowerPlant	$object					Power plant
 * @param	User		$user					User
 * @param	int<0,1>	$overwriteExistingDates	1=replace existing line dates
 * @return	int									Updated line count, <0 on error
 */
function powerplantApplyCompositionCommissioningDate($object, $user, $overwriteExistingDates = 0)
{
	global $db, $conf, $langs;

	$entity = (!empty($object->entity) ? (int) $object->entity : (int) $conf->entity);
	$date = powerplantGetCompositionCommissioningDate($object);
	$where = " WHERE fk_powerplant = ".((int) $object->id);
	$where .= " AND entity = ".$entity;
	$where .= " AND (fk_status IS NULL OR fk_status NOT IN (6, 8))";

	$sqlcount = "SELECT COUNT(rowid) as nb FROM ".$db->prefix()."powerplantpv_powerplantcomp".$where;
	if (empty($overwriteExistingDates)) {
		$sqlcount .= " AND commissioning_date IS NULL";
	}
	$rescount = $db->query($sqlcount);
	if (!$rescount) {
		$object->error = $db->lasterror();
		return -1;
	}
	$objcount = $db->fetch_object($rescount);
	$nbtoupdate = ($objcount ? (int) $objcount->nb : 0);

	$db->begin();

	if (powerplantDateToSqlDate($object->commissioning_date) === '') {
		$sqlpowerplant = "UPDATE ".$db->prefix().$object->table_element;
		$sqlpowerplant .= " SET commissioning_date = '".$db->escape($date)."'";
		$sqlpowerplant .= " WHERE rowid = ".((int) $object->id);
		$respowerplant = $db->query($sqlpowerplant);
		if (!$respowerplant) {
			$object->error = $db->lasterror();
			$db->rollback();
			return -1;
		}
		$object->commissioning_date = $db->jdate($date.' 00:00:00');
	}

	if ($nbtoupdate > 0) {
		$sqlupdate = "UPDATE ".$db->prefix()."powerplantpv_powerplantcomp";
		$sqlupdate .= " SET commissioning_date = '".$db->escape($date)."'";
		$sqlupdate .= $where;
		if (empty($overwriteExistingDates)) {
			$sqlupdate .= " AND commissioning_date IS NULL";
		}
		$resupdate = $db->query($sqlupdate);
		if (!$resupdate) {
			$object->error = $db->lasterror();
			$db->rollback();
			return -1;
		}
	}

	$db->commit();

	if ($nbtoupdate > 0) {
		$label = $langs->transnoentities('PowerPlantCompositionCommissioningDateUpdated', $object->ref);
		$message = $langs->transnoentities('PowerPlantCompositionCommissioningDateUpdatedDesc', $nbtoupdate, dol_print_date($db->jdate($date.' 00:00:00'), 'day'));
		$message .= "\n".$langs->transnoentities(empty($overwriteExistingDates) ? 'PowerPlantCompositionExistingDatesKept' : 'PowerPlantCompositionExistingDatesOverwritten');
		$result = powerplantTriggerAgendaEvent($object, $user, 'POWERPLANTPV_POWERPLANT_COMP_COMMISSIONING', $label, $message);
		if ($result < 0) {
			return -1;
		}
	}

	return $nbtoupdate;
}

/**
 * Return a translated composition status label.
 *
 * @param	int	$status	Status code
 * @return	string			Translated label
 */
function powerplantCompositionStatusLabel($status)
{
	global $langs;

	$labels = array(
		0 => 'PowerPlantCompStatusInactive',
		4 => 'PowerPlantCompStatusActive',
		6 => 'PowerPlantCompStatusReplaced',
		8 => 'PowerPlantCompStatusOutOfService',
	);

	return $langs->trans(isset($labels[(int) $status]) ? $labels[(int) $status] : (string) $status);
}

/**
 * Set all non-replaced composition lines to a service status.
 *
 * @param	PowerPlant	$object	Power plant
 * @param	User		$user	User
 * @param	int			$status	Target composition status
 * @return	int					Changed line count, <0 on error
 */
function powerplantSetCompositionServiceStatus($object, $user, $status)
{
	global $db, $conf, $langs;

	$status = (int) $status;
	if (empty($object->id) || !in_array($status, array(4, 8), true)) {
		return 0;
	}
	$entity = (!empty($object->entity) ? (int) $object->entity : (int) $conf->entity);

	$where = " WHERE fk_powerplant = ".((int) $object->id);
	$where .= " AND entity = ".$entity;
	$where .= " AND (fk_status IS NULL OR fk_status <> 6)";
	$where .= " AND (fk_status IS NULL OR fk_status <> ".$status.")";

	$sqlcount = "SELECT COUNT(rowid) as nb FROM ".$db->prefix()."powerplantpv_powerplantcomp".$where;
	$rescount = $db->query($sqlcount);
	if (!$rescount) {
		$object->error = $db->lasterror();
		return -1;
	}
	$objcount = $db->fetch_object($rescount);
	$nbchanged = ($objcount ? (int) $objcount->nb : 0);
	if ($nbchanged <= 0) {
		return 0;
	}

	$sqlupdate = "UPDATE ".$db->prefix()."powerplantpv_powerplantcomp";
	$sqlupdate .= " SET fk_status = ".$status;
	$sqlupdate .= $where;
	$resupdate = $db->query($sqlupdate);
	if (!$resupdate) {
		$object->error = $db->lasterror();
		return -1;
	}

	$statuslabel = powerplantCompositionStatusLabel($status);
	$label = $langs->transnoentities('PowerPlantCompositionStatusMassChanged', $object->ref);
	$message = $langs->transnoentities('PowerPlantCompositionStatusMassChangedDesc', $nbchanged);
	$message .= "\n".$langs->transnoentities('PowerPlantCompositionStatusMassChangedLine', $nbchanged, $statuslabel);
	$triggercode = ($status === 8 ? 'POWERPLANTPV_POWERPLANT_COMP_OUTOFSERVICE' : 'POWERPLANTPV_POWERPLANT_COMP_INSERVICE');
	powerplantTriggerAgendaEvent($object, $user, $triggercode, $label, $message);

	return $nbchanged;
}

/**
 * Recalculate installed power from active and out-of-service PV module lines.
 *
 * @param	PowerPlant|int	$object	Power plant object or id
 * @param	int				$entity	Power plant entity, when object id is passed
 * @return	int						1 on success, <0 on error
 */
function powerplantRecalculateInstalledPower($object, $entity = 0)
{
	global $db, $conf;

	$powerplantid = is_object($object) ? (int) $object->id : (int) $object;
	if ($powerplantid <= 0) {
		return 0;
	}
	$powerplantentity = (!empty($entity) ? (int) $entity : 0);
	if (is_object($object) && !empty($object->entity)) {
		$powerplantentity = (int) $object->entity;
	}
	if ($powerplantentity <= 0) {
		$sqlentity = "SELECT entity FROM ".$db->prefix()."powerplantpv_powerplant WHERE rowid = ".$powerplantid;
		$resentity = $db->query($sqlentity);
		if ($resentity) {
			$objentity = $db->fetch_object($resentity);
			if ($objentity) {
				$powerplantentity = (int) $objentity->entity;
			}
		} elseif (is_object($object)) {
			$object->error = $db->lasterror();
			return -1;
		}
	}
	if ($powerplantentity <= 0) {
		$powerplantentity = (int) $conf->entity;
	}

	$sql = "SELECT c.fk_product, c.qty";
	$sql .= " FROM ".$db->prefix()."powerplantpv_powerplantcomp as c";
	$sql .= " INNER JOIN ".$db->prefix()."product_extrafields as pe ON pe.fk_object = c.fk_product";
	$sql .= " INNER JOIN ".$db->prefix()."c_powerplantpv_categorypv as cpv ON cpv.rowid = pe.categorie_photovoltaique";
	$sql .= " WHERE c.fk_powerplant = ".$powerplantid;
	$sql .= " AND c.entity = ".$powerplantentity;
	$sql .= " AND cpv.code = 'MODULE'";
	$sql .= " AND (c.fk_status IS NULL OR c.fk_status IN (4, 8))";

	$resql = $db->query($sql);
	if (!$resql) {
		if (is_object($object)) {
			$object->error = $db->lasterror();
		}
		return -1;
	}

	$pmaxbyproduct = array();
	$totalwattpeak = 0;
	while ($line = $db->fetch_object($resql)) {
		$productid = (int) $line->fk_product;
		if ($productid <= 0) {
			continue;
		}
		if (!array_key_exists($productid, $pmaxbyproduct)) {
			$sqlpanel = "SELECT pmax";
			$sqlpanel .= " FROM ".$db->prefix()."powerplantpv_product_pvpanel";
			$sqlpanel .= " WHERE fk_product = ".$productid;
			$sqlpanel .= " AND entity IN (".getEntity('product').")";
			$sqlpanel .= " ORDER BY entity DESC";
			$respanel = $db->query($sqlpanel);
			if (!$respanel) {
				if (is_object($object)) {
					$object->error = $db->lasterror();
				}
				return -1;
			}
			$objpanel = $db->fetch_object($respanel);
			$pmaxbyproduct[$productid] = ($objpanel && $objpanel->pmax !== null && $objpanel->pmax !== '' ? (float) $objpanel->pmax : 0);
		}
		$totalwattpeak += ((float) $line->qty * (float) $pmaxbyproduct[$productid]);
	}

	$installedpower = $totalwattpeak / 1000;
	$sqlupdate = "UPDATE ".$db->prefix()."powerplantpv_powerplant";
	$sqlupdate .= " SET installed_power = ".sprintf('%.8F', $installedpower);
	$sqlupdate .= " WHERE rowid = ".$powerplantid;
	$resupdate = $db->query($sqlupdate);
	if (!$resupdate) {
		if (is_object($object)) {
			$object->error = $db->lasterror();
		}
		return -1;
	}

	if (is_object($object)) {
		$object->installed_power = $installedpower;
	}

	return 1;
}

/**
 * Recalculate installed power for all power plants using a product.
 *
 * @param	int	$productid	Product id
 * @return	int				Number of recalculated power plants, <0 on error
 */
function powerplantRecalculateInstalledPowerForProduct($productid)
{
	global $db;

	$productid = (int) $productid;
	if ($productid <= 0) {
		return 0;
	}

	$sql = "SELECT DISTINCT c.fk_powerplant, p.entity as powerplant_entity";
	$sql .= " FROM ".$db->prefix()."powerplantpv_powerplantcomp as c";
	$sql .= " INNER JOIN ".$db->prefix()."powerplantpv_powerplant as p ON p.rowid = c.fk_powerplant";
	$sql .= " WHERE c.fk_product = ".$productid;
	$sql .= " AND p.entity IN (".getEntity('powerplant').")";
	$resql = $db->query($sql);
	if (!$resql) {
		return -1;
	}

	$nb = 0;
	while ($obj = $db->fetch_object($resql)) {
		$result = powerplantRecalculateInstalledPower((int) $obj->fk_powerplant, (int) $obj->powerplant_entity);
		if ($result < 0) {
			return -1;
		}
		$nb++;
	}

	return $nb;
}
