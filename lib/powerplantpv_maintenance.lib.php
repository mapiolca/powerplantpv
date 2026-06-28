<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file		lib/powerplantpv_maintenance.lib.php
 * \ingroup		powerplantpv
 * \brief		Shared UI helpers for PowerPlantPV maintenance pages.
 */

dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
dol_include_once('/powerplantpv/class/powerplantpvmaintenancescheduler.class.php');
dol_include_once('/powerplantpv/class/powerplant.class.php');
dol_include_once('/societe/class/societe.class.php');
dol_include_once('/contrat/class/contrat.class.php');
dol_include_once('/fichinter/class/fichinter.class.php');

/**
 * Return maintenance status options.
 *
 * @return	array<string,string>	Status options
 */
function powerplantpvMaintenanceStatusOptions()
{
	global $langs;

	$options = array();
	foreach (array(
		PowerPlantPVMaintenanceScheduler::STATUS_NOT_REQUIRED,
		PowerPlantPVMaintenanceScheduler::STATUS_PLANNED,
		PowerPlantPVMaintenanceScheduler::STATUS_DUE,
		PowerPlantPVMaintenanceScheduler::STATUS_OVERDUE,
		PowerPlantPVMaintenanceScheduler::STATUS_COVERED,
		PowerPlantPVMaintenanceScheduler::STATUS_INCOMPLETE,
	) as $status) {
		$options[$status] = $langs->trans(PowerPlantPVMaintenanceScheduler::getStatusLabelKey($status));
	}

	return $options;
}

/**
 * Return a scheduler status badge.
 *
 * @param	string	$status	Scheduler status
 * @return	string			HTML
 */
function powerplantpvMaintenanceStatusBadge($status)
{
	global $langs;

	$label = $langs->trans(PowerPlantPVMaintenanceScheduler::getStatusLabelKey($status));
	$statusType = PowerPlantPVMaintenanceScheduler::getStatusType($status);
	if (function_exists('dolGetStatus')) {
		return dolGetStatus($label, $label, '', $statusType, 3);
	}

	return '<span class="badge '.$statusType.'">'.dol_escape_htmltag($label).'</span>';
}

/**
 * Format a maintenance period.
 *
 * @param	int	$periodStart	Start timestamp
 * @param	int	$periodEnd		End timestamp
 * @return	string				HTML
 */
function powerplantpvMaintenanceFormatPeriod($periodStart, $periodEnd)
{
	global $langs;

	if ($periodStart <= 0 || $periodEnd <= 0) {
		return '<span class="opacitymedium">'.$langs->trans('PowerPlantPVMaintenancePeriodMissing').'</span>';
	}

	return dol_print_date($periodStart, 'day').' - '.dol_print_date($periodEnd, 'day');
}

/**
 * Return a translated recurrence label.
 *
 * @param	string	$recurrence	Recurrence code
 * @return	string				Label
 */
function powerplantpvMaintenanceRecurrenceLabel($recurrence)
{
	global $langs;

	$recurrenceLabels = PowerPlantPVMaintenanceScheduler::getRecurrenceLabelKeys();
	$recurrence = (string) $recurrence;

	return isset($recurrenceLabels[$recurrence]) ? $langs->trans($recurrenceLabels[$recurrence]) : $langs->trans('PowerPlantPVNotConfigured');
}

/**
 * Return a power plant link.
 *
 * @param	PowerPlant	$powerplant	Power plant
 * @return	string					HTML
 */
function powerplantpvMaintenancePowerPlantLink($powerplant)
{
	if (is_object($powerplant) && method_exists($powerplant, 'getNomUrl')) {
		return $powerplant->getNomUrl(1);
	}

	return '';
}

/**
 * Return a third party link.
 *
 * @param	int	$socid	Third party id
 * @return	string		HTML
 */
function powerplantpvMaintenanceThirdPartyLink($socid)
{
	global $db;

	$socid = (int) $socid;
	if ($socid <= 0) {
		return '<span class="opacitymedium">-</span>';
	}

	static $cache = array();
	if (!isset($cache[$socid])) {
		$soc = new Societe($db);
		$result = $soc->fetch($socid);
		$cache[$socid] = ($result > 0) ? $soc : null;
	}
	if (is_object($cache[$socid])) {
		return $cache[$socid]->getNomUrl(1);
	}

	return '<span class="opacitymedium">#'.((int) $socid).'</span>';
}

/**
 * Return a contract link.
 *
 * @param	int		$contractId	Contract id
 * @param	string	$fallbackRef	Fallback reference
 * @return	string					HTML
 */
function powerplantpvMaintenanceContractLink($contractId, $fallbackRef)
{
	global $db;

	if ($contractId <= 0) {
		return '<span class="opacitymedium">-</span>';
	}
	if (class_exists('Contrat')) {
		$contract = new Contrat($db);
		if ($contract->fetch($contractId) > 0) {
			return $contract->getNomUrl(1);
		}
	}

	return '<a href="'.DOL_URL_ROOT.'/contrat/card.php?id='.((int) $contractId).'">'.dol_escape_htmltag($fallbackRef).'</a>';
}

/**
 * Return an intervention link.
 *
 * @param	int		$interventionId	Intervention id
 * @param	string	$fallbackRef	Fallback reference
 * @return	string					HTML
 */
function powerplantpvMaintenanceInterventionLink($interventionId, $fallbackRef)
{
	global $db;

	if ($interventionId <= 0) {
		return '<span class="opacitymedium">-</span>';
	}
	if (class_exists('Fichinter')) {
		$intervention = new Fichinter($db);
		if ($intervention->fetch($interventionId) > 0) {
			return $intervention->getNomUrl(1);
		}
	}

	return '<a href="'.DOL_URL_ROOT.'/fichinter/card.php?id='.((int) $interventionId).'">'.dol_escape_htmltag($fallbackRef).'</a>';
}

/**
 * Return an intervention status badge.
 *
 * @param	int	$interventionId	Intervention id
 * @param	int	$status			Status
 * @return	string				HTML
 */
function powerplantpvMaintenanceInterventionStatus($interventionId, $status)
{
	global $db, $langs;

	if (class_exists('Fichinter') && $interventionId > 0) {
		$intervention = new Fichinter($db);
		if ($intervention->fetch($interventionId) > 0) {
			return $intervention->getLibStatut(3);
		}
	}

	return '<span class="badge badge-status'.((int) $status).'">'.dol_escape_htmltag($langs->trans('Status').' '.$status).'</span>';
}

/**
 * Render active service labels.
 *
 * @param	array<int,array<string,mixed>>	$services	Service lines
 * @return	string										HTML
 */
function powerplantpvMaintenanceRenderActiveServices($services)
{
	global $langs;

	if (empty($services)) {
		return '<span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span>';
	}

	$html = '';
	foreach ($services as $service) {
		$product = trim((string) $service['product_ref'].' - '.(string) $service['product_label'], " -\t\n\r\0\x0B");
		if ($product === '') {
			$product = !empty($service['description']) ? (string) $service['description'] : '#'.((int) $service['id']);
		}
		$html .= '<div>'.dol_escape_htmltag($product).'</div>';
	}

	return $html;
}

/**
 * Render maintenance prestation labels.
 *
 * @param	array<int,array<string,mixed>>	$services	Service lines
 * @return	string										HTML
 */
function powerplantpvMaintenanceRenderPrestations($services)
{
	global $langs;

	$html = '';
	foreach ($services as $service) {
		if (empty($service['maintenance_services']) || !is_array($service['maintenance_services'])) {
			continue;
		}
		foreach ($service['maintenance_services'] as $maintenanceService) {
			$html .= '<div>'.dol_escape_htmltag((string) $maintenanceService['label']).'</div>';
		}
	}

	if ($html === '') {
		return '<span class="opacitymedium">'.$langs->trans('PowerPlantPVNoMaintenanceServiceOnActiveServices').'</span>';
	}

	return $html;
}

/**
 * Build the create-intervention URL for one maintenance row or item.
 *
 * @param	PowerPlant			$powerplant	Power plant
 * @param	array<string,mixed>	$item		Scheduler item or global row
 * @param	string				$backtopage	Back URL
 * @return	string							URL
 */
function powerplantpvMaintenanceBuildCreateInterventionUrl($powerplant, $item, $backtopage = '')
{
	global $langs;

	$sourceItem = (!empty($item['item']) && is_array($item['item'])) ? $item['item'] : $item;
	$contract = isset($sourceItem['contract']) && is_array($sourceItem['contract']) ? $sourceItem['contract'] : array();
	$contractId = !empty($contract['id']) ? (int) $contract['id'] : 0;
	$socid = !empty($contract['fk_soc']) ? (int) $contract['fk_soc'] : (int) $powerplant->fk_soc;
	$projectId = !empty($contract['fk_project']) ? (int) $contract['fk_project'] : (int) $powerplant->fk_project;
	$periodStart = !empty($sourceItem['period_start']) ? (int) $sourceItem['period_start'] : 0;
	$periodEnd = !empty($sourceItem['period_end']) ? (int) $sourceItem['period_end'] : 0;

	if ($periodStart > 0 && $periodEnd > 0) {
		$description = $langs->transnoentities('PowerPlantPVMaintenanceInterventionDescription', $powerplant->ref, dol_print_date($periodStart, 'day'), dol_print_date($periodEnd, 'day'));
	} else {
		$description = $langs->transnoentities('PowerPlantPVMaintenanceInterventionDescriptionNoPeriod', $powerplant->ref);
	}

	$url = dol_buildpath('/fichinter/card.php', 1).'?action=create';
	$url .= '&origin='.urlencode(powerplantpvGetCanonicalPowerPlantLinkType());
	$url .= '&originid='.((int) $powerplant->id);
	$url .= '&fk_powerplant='.((int) $powerplant->id);
	$url .= '&powerplantpv_powerplants[]='.((int) $powerplant->id);
	if ($socid > 0) {
		$url .= '&socid='.$socid;
	}
	if ($projectId > 0) {
		$url .= '&projectid='.$projectId;
	}
	if ($contractId > 0) {
		$url .= '&contratid='.$contractId.'&fk_contrat='.$contractId;
	}
	$url .= '&powerplantpv_intervention_nature_code=PREVENTIVE_MAINTENANCE';
	if ($periodStart > 0 && $periodEnd > 0) {
		$url .= '&powerplantpv_maintenance_period_start='.urlencode(date('Y-m-d', $periodStart));
		$url .= '&powerplantpv_maintenance_period_end='.urlencode(date('Y-m-d', $periodEnd));
	}
	$url .= '&description='.urlencode($description);
	$url .= '&backtopage='.urlencode($backtopage !== '' ? $backtopage : dol_buildpath('/powerplantpv/powerplant_maintenance.php', 1).'?id='.((int) $powerplant->id));

	return $url;
}

/**
 * Check if the current user may create a native maintenance intervention.
 *
 * @param	User	$user	Current user
 * @return	bool		True if creation is allowed
 */
function powerplantpvMaintenanceCanCreateIntervention($user)
{
	return isModEnabled('ficheinter')
		&& powerplantpvUserHasMaintenanceRight($user, 'write')
		&& powerplantpvUserHasRightPath($user, array('ficheinter', 'creer'));
}

/**
 * Return selectable intervention natures.
 *
 * @param	int<0,1>	$maintenanceOnly	Only maintenance natures
 * @return	array<int,string>					Options
 */
function powerplantpvMaintenanceInterventionNatureOptions($maintenanceOnly = 1)
{
	global $db, $langs;

	$sql = "SELECT rowid, code, label, label_en";
	$sql .= " FROM ".$db->prefix()."c_powerplantpv_intervention_nature";
	$sql .= " WHERE active = 1";
	$sql .= " AND entity IN (".$db->sanitize(getEntity('c_powerplantpv_intervention_nature')).")";
	if ($maintenanceOnly) {
		$sql .= " AND is_maintenance = 1";
	}
	$sql .= " ORDER BY position ASC, label ASC, rowid ASC";

	$options = array();
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__METHOD__.' failed to fetch intervention natures: '.$db->lasterror(), LOG_WARNING);
		return $options;
	}

	while (is_object($obj = $db->fetch_object($resql))) {
		$label = (is_object($langs) && $langs->defaultlang == 'en_US' && !empty($obj->label_en)) ? (string) $obj->label_en : (string) $obj->label;
		$options[(int) $obj->rowid] = $label;
	}
	$db->free($resql);

	return $options;
}

/**
 * Return selectable maintenance services.
 *
 * @return	array<int,string>	Options
 */
function powerplantpvMaintenanceServiceOptions()
{
	global $db, $langs;

	$sql = "SELECT rowid, code, label, label_en";
	$sql .= " FROM ".$db->prefix()."c_powerplantpv_maintenance_service";
	$sql .= " WHERE active = 1";
	$sql .= " AND entity IN (".$db->sanitize(getEntity('c_powerplantpv_maintenance_service')).")";
	$sql .= " ORDER BY position ASC, label ASC, rowid ASC";

	$options = array();
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__METHOD__.' failed to fetch maintenance services: '.$db->lasterror(), LOG_WARNING);
		return $options;
	}

	while (is_object($obj = $db->fetch_object($resql))) {
		$label = (is_object($langs) && $langs->defaultlang == 'en_US' && !empty($obj->label_en)) ? (string) $obj->label_en : (string) $obj->label;
		$options[(int) $obj->rowid] = $label;
	}
	$db->free($resql);

	return $options;
}

/**
 * Return selectable contracts for a maintenance intervention creation form.
 *
 * @param	int[]	$powerplantIds	Selected power plant ids
 * @return	array<int,string>		Contract options
 */
function powerplantpvMaintenanceContractOptions($powerplantIds = array())
{
	global $db, $user;

	if (!powerplantpvUserHasRightPath($user, array('contrat', 'lire'))) {
		return array();
	}

	$powerplantIds = powerplantpvSanitizeIdArray($powerplantIds);
	$powerPlantTypes = array();
	foreach (powerplantpvGetPowerPlantLinkTypes() as $type) {
		$powerPlantTypes[] = "'".$db->escape($type)."'";
	}

	$sql = "SELECT DISTINCT c.rowid, c.ref, c.ref_customer, c.fk_soc, s.nom as socname";
	$sql .= " FROM ".$db->prefix()."contrat AS c";
	if (!empty($powerplantIds)) {
		$sql .= " INNER JOIN ".$db->prefix()."element_element AS ee ON (";
		$sql .= "(ee.sourcetype = 'contrat' AND ee.fk_source = c.rowid AND ee.targettype IN (".implode(',', $powerPlantTypes).") AND ee.fk_target IN (".implode(',', $powerplantIds)."))";
		$sql .= " OR ";
		$sql .= "(ee.targettype = 'contrat' AND ee.fk_target = c.rowid AND ee.sourcetype IN (".implode(',', $powerPlantTypes).") AND ee.fk_source IN (".implode(',', $powerplantIds)."))";
		$sql .= ")";
	}
	$sql .= " LEFT JOIN ".$db->prefix()."societe AS s ON s.rowid = c.fk_soc";
	$sql .= " WHERE c.entity IN (".$db->sanitize(getEntity('contrat')).")";
	if (!empty($user->socid)) {
		$sql .= " AND c.fk_soc = ".((int) $user->socid);
	}
	$sql .= " ORDER BY c.ref ASC, c.rowid ASC";

	$options = array();
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__METHOD__.' failed to fetch contracts: '.$db->lasterror(), LOG_WARNING);
		return $options;
	}
	while (is_object($obj = $db->fetch_object($resql))) {
		$label = (string) $obj->ref;
		if (!empty($obj->ref_customer)) {
			$label .= ' - '.(string) $obj->ref_customer;
		}
		if (!empty($obj->socname)) {
			$label .= ' ('.(string) $obj->socname.')';
		}
		$options[(int) $obj->rowid] = $label;
	}
	$db->free($resql);

	return $options;
}
