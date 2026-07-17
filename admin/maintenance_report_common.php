<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		admin/maintenance_report_common.php
 * \ingroup		powerplantpv
 * \brief		Common helpers for maintenance report template admin pages.
 */

if (!defined('DOL_DOCUMENT_ROOT')) {
	die('This file must be included by a Dolibarr page');
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/lib/powerplantpv_reporttemplate.lib.php');

/**
 * Check admin access for report template engine pages.
 *
 * @return	void
 */
function powerplantpvReportTemplateAdminAccess()
{
	global $user;

	if (!isModEnabled('powerplantpv') || !powerplantpvMaintenanceUserHasRight($user, 'config')) {
		accessforbidden();
	}
}

/**
 * Print common admin page header.
 *
 * @param	string	$title		Title
 * @param	string	$activeTab	Active tab
 * @return	void
 */
function powerplantpvReportTemplateAdminHeader($title, $activeTab)
{
	global $langs;

	$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword='.urlencode('powerplantpv').'">'.img_picto($langs->trans('BackToModuleList'), 'back', 'class="pictofixedwidth"').'<span class="hideonsmartphone">'.$langs->trans('BackToModuleList').'</span></a>';

	llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-powerplantpv page-admin');
	print load_fiche_titre($title, $linkback, 'title_setup');
	$head = powerplantpvAdminPrepareHead();
	print dol_get_fiche_head($head, $activeTab, $title, -1, 'fa-clipboard-list');
}

/**
 * Print common admin page footer.
 *
 * @return	void
 */
function powerplantpvReportTemplateAdminFooter()
{
	global $db;

	print dol_get_fiche_end();
	llxFooter();
	$db->close();
}

/**
 * Return report template options for current entity.
 *
 * @param	int<0,1>	$includeEmpty	Include empty option
 * @return	array<int,string>					Options
 */
function powerplantpvReportTemplateOptions($includeEmpty = 0)
{
	global $db, $conf, $langs;

	$options = array();
	if ($includeEmpty) {
		$options[0] = '';
	}

	$sql = "SELECT rowid, code, label, active";
	$sql .= " FROM ".$db->prefix()."powerplantpv_report_template";
	$sql .= " WHERE entity = ".((int) $conf->entity);
	$sql .= " ORDER BY position ASC, label ASC";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$label = trim((string) $obj->label);
			if (empty($obj->active)) {
				$label .= ' ('.$langs->trans('Disabled').')';
			}
			$options[(int) $obj->rowid] = $label.' ['.(string) $obj->code.']';
		}
		$db->free($resql);
	}

	return $options;
}

/**
 * Return template section options.
 *
 * @param	int		$templateId	Template id
 * @param	int<0,1>	$includeEmpty	Include empty option
 * @return	array<int,string>					Options
 */
function powerplantpvReportTemplateSectionOptions($templateId, $includeEmpty = 0)
{
	global $db, $conf, $langs;

	$options = array();
	if ($includeEmpty) {
		$options[0] = '';
	}

	$sql = "SELECT rowid, code, label, active";
	$sql .= " FROM ".$db->prefix()."powerplantpv_report_template_section";
	$sql .= " WHERE entity = ".((int) $conf->entity);
	$sql .= " AND fk_report_template = ".((int) $templateId);
	$sql .= " ORDER BY position ASC, label ASC";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$label = trim((string) $obj->label);
			if (empty($obj->active)) {
				$label .= ' ('.$langs->trans('Disabled').')';
			}
			$options[(int) $obj->rowid] = $label.' ['.(string) $obj->code.']';
		}
		$db->free($resql);
	}

	return $options;
}

/**
 * Return maintenance service options.
 *
 * @param	int<0,1>	$includeEmpty	Include empty option
 * @return	array<int,string>					Options
 */
function powerplantpvMaintenanceServiceOptions($includeEmpty = 0)
{
	global $db, $conf, $langs;

	$options = array();
	if ($includeEmpty) {
		$options[0] = '';
	}

	$sql = "SELECT rowid, code, label, active";
	$sql .= " FROM ".$db->prefix()."c_powerplantpv_maintenance_service";
	$sql .= " WHERE entity = ".((int) $conf->entity);
	$sql .= " ORDER BY position ASC, label ASC";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$label = trim((string) $obj->label);
			if (empty($obj->active)) {
				$label .= ' ('.$langs->trans('Disabled').')';
			}
			$options[(int) $obj->rowid] = $label.' ['.(string) $obj->code.']';
		}
		$db->free($resql);
	}

	return $options;
}

/**
 * Return the default report template id.
 *
 * @return	int	Template id or 0
 */
function powerplantpvDefaultReportTemplateId()
{
	global $db, $conf;

	$sql = "SELECT rowid";
	$sql .= " FROM ".$db->prefix()."powerplantpv_report_template";
	$sql .= " WHERE entity = ".((int) $conf->entity);
	$sql .= " ORDER BY is_default DESC, position ASC, rowid ASC";
	$resql = $db->query($sql);
	if (!$resql) {
		return 0;
	}

	$obj = $db->fetch_object($resql);
	$db->free($resql);

	return is_object($obj) ? (int) $obj->rowid : 0;
}

/**
 * Move a row up or down by swapping positions with the nearest row.
 *
 * @param	string	$table		Table without prefix
 * @param	int		$id			Current row id
 * @param	string	$direction	up or down
 * @param	string	$whereExtra	Additional SQL where clause starting with AND
 * @return	int					1 if OK, 0 if no swap, <0 if KO
 */
function powerplantpvReportTemplateMoveRow($table, $id, $direction, $whereExtra = '')
{
	global $db, $conf;

	$fullTable = $db->prefix().$table;
	$sql = "SELECT rowid, position FROM ".$fullTable;
	$sql .= " WHERE rowid = ".((int) $id);
	$sql .= " AND entity = ".((int) $conf->entity);
	$sql .= $whereExtra;
	$resql = $db->query($sql);
	if (!$resql) {
		return -1;
	}
	$current = $db->fetch_object($resql);
	$db->free($resql);
	if (!is_object($current)) {
		return 0;
	}

	$operator = ($direction === 'up') ? '<' : '>';
	$order = ($direction === 'up') ? 'DESC' : 'ASC';
	$sql = "SELECT rowid, position FROM ".$fullTable;
	$sql .= " WHERE entity = ".((int) $conf->entity);
	$sql .= " AND position ".$operator." ".((int) $current->position);
	$sql .= $whereExtra;
	$sql .= " ORDER BY position ".$order.", rowid ".$order;
	$sql .= $db->plimit(1);
	$resql = $db->query($sql);
	if (!$resql) {
		return -1;
	}
	$other = $db->fetch_object($resql);
	$db->free($resql);
	if (!is_object($other)) {
		return 0;
	}

	$db->begin();
	$sql = "UPDATE ".$fullTable." SET position = ".((int) $other->position)." WHERE rowid = ".((int) $current->rowid)." AND entity = ".((int) $conf->entity);
	if (!$db->query($sql)) {
		$db->rollback();
		return -1;
	}
	$sql = "UPDATE ".$fullTable." SET position = ".((int) $current->position)." WHERE rowid = ".((int) $other->rowid)." AND entity = ".((int) $conf->entity);
	if (!$db->query($sql)) {
		$db->rollback();
		return -1;
	}
	$db->commit();

	return 1;
}

/**
 * Print a native no-record line.
 *
 * @param	int	$colspan	Visible columns
 * @return	void
 */
function powerplantpvPrintNoRecordFound($colspan)
{
	global $langs;

	print '<tr class="oddeven"><td colspan="'.((int) $colspan).'">';
	print '<span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span>';
	print '</td></tr>';
}
