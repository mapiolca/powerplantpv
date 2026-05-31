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

	$diroutput = '';
	if (!empty($conf->powerplantpv->multidir_output[$object->entity])) {
		$diroutput = $conf->powerplantpv->multidir_output[$object->entity];
	} elseif (!empty($conf->powerplantpv->dir_output)) {
		$diroutput = $conf->powerplantpv->dir_output;
	}

	$upload_dir = $diroutput.'/powerplant/'.dol_sanitizeFileName($object->ref);
	$nbFiles = count(dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$'));
	$nbLinks = Link::count($db, $object->element, $object->id);

	return $nbFiles + $nbLinks;
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

	$elementtypes = array('powerplant', 'powerplant@powerplantpv');
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

	if (!empty($object->label)) {
		$morehtmlref .= '<br><span class="opacitymedium">'.$langs->trans("Label").'</span>: '.dol_escape_htmltag($object->label);
	}

	if (isModEnabled('societe')) {
		if (!empty($object->fk_soc) || !empty($object->socid)) {
			$object->fetch_thirdparty();
		}

		$morehtmlref .= '<br><span class="opacitymedium">'.$langs->trans("ThirdParty").'</span>';
		if ($permissiontoadd && $action != 'editcustomer') {
			$morehtmlref .= ' <a class="editfielda" href="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.((int) $object->id).'&action=editcustomer">'.img_edit($langs->transnoentitiesnoconv('SetThirdParty'), 0).'</a>';
		}
		$morehtmlref .= ': ';

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
			$morehtmlref .= '<span class="opacitymedium">'.$langs->trans("None").'</span>';
		}
	}

	$morehtmlref .= '</div>';

	return $morehtmlref;
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
