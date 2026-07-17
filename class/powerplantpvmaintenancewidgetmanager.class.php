<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

dol_include_once('/powerplantpv/class/powerplantpvmaintenancewidget.class.php');

/**
 * Persist per-user, per-entity maintenance widget layouts.
 */
class PowerPlantPVMaintenanceWidgetManager
{
	/** @var DoliDB */
	private $db;

	/** @var string */
	public $error = '';

	/** @var array<int,string> */
	public $errors = array();

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Return a user's effective layout.
	 *
	 * @param int $userId User id
	 * @param int $entity Entity id
	 * @return array<int,array{code:string,column:int,position:int}>
	 */
	public function getLayout($userId, $entity)
	{
		$sql = 'SELECT widget_code, visible, column_index, position';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'powerplantpv_maintenance_widget_user';
		$sql .= ' WHERE entity = '.((int) $entity).' AND fk_user = '.((int) $userId);
		$sql .= ' ORDER BY column_index ASC, position ASC, rowid ASC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return PowerPlantPVMaintenanceWidget::getDefaultStatsLayout();
		}
		$hasPreferences = $this->db->num_rows($resql) > 0;
		$layout = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$code = (string) $obj->widget_code;
			if (!empty($obj->visible) && PowerPlantPVMaintenanceWidget::isAvailable($code, 'stats')) {
				$layout[] = array('code' => $code, 'column' => min(1, max(0, (int) $obj->column_index)), 'position' => max(0, (int) $obj->position));
			}
		}
		$this->db->free($resql);

		return $hasPreferences ? $layout : PowerPlantPVMaintenanceWidget::getDefaultStatsLayout();
	}

	/**
	 * Save a complete layout, including invisible catalog entries.
	 *
	 * @param int $userId User id
	 * @param int $entity Entity id
	 * @param array<int,array<string,mixed>> $layout Visible layout
	 * @param int $authorId Author id
	 * @return int<-1,1>
	 */
	public function saveLayout($userId, $entity, array $layout, $authorId)
	{
		$layout = self::normalizeLayout($layout);
		$visible = array();
		foreach ($layout as $item) {
			$visible[$item['code']] = $item;
		}
		$this->db->begin();
		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'powerplantpv_maintenance_widget_user';
		$sql .= ' WHERE entity = '.((int) $entity).' AND fk_user = '.((int) $userId);
		if (!$this->db->query($sql)) {
			return $this->rollbackWithError();
		}
		$now = $this->db->idate(dol_now());
		foreach (PowerPlantPVMaintenanceWidget::getCatalog() as $code => $definition) {
			$item = isset($visible[$code]) ? $visible[$code] : array('column' => 0, 'position' => (int) $definition['position']);
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'powerplantpv_maintenance_widget_user(entity, fk_user, widget_code, visible, column_index, position, date_creation, fk_user_creat, fk_user_modif) VALUES (';
			$sql .= ((int) $entity).', '.((int) $userId).", '".$this->db->escape($code)."', ".(isset($visible[$code]) ? '1' : '0').', '.((int) $item['column']).', '.((int) $item['position']).", '".$now."', ".((int) $authorId).', '.((int) $authorId).')';
			if (!$this->db->query($sql)) {
				return $this->rollbackWithError();
			}
		}
		$this->db->commit();
		return 1;
	}

	/**
	 * Reset a user's layout to defaults by removing preferences.
	 *
	 * @param int $userId User id
	 * @param int $entity Entity id
	 * @return int<-1,1>
	 */
	public function resetLayout($userId, $entity)
	{
		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'powerplantpv_maintenance_widget_user';
		$sql .= ' WHERE entity = '.((int) $entity).' AND fk_user = '.((int) $userId);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 * Normalize and de-duplicate a visible layout.
	 *
	 * @param array<int,array<string,mixed>> $layout Raw layout
	 * @return array<int,array{code:string,column:int,position:int}>
	 */
	public static function normalizeLayout(array $layout)
	{
		$normalized = array();
		$seen = array();
		$positions = array(0 => 0, 1 => 0);
		foreach ($layout as $item) {
			$code = isset($item['code']) ? (string) $item['code'] : '';
			if (isset($seen[$code]) || !PowerPlantPVMaintenanceWidget::isAvailable($code, 'stats')) {
				continue;
			}
			$column = !empty($item['column']) ? 1 : 0;
			$positions[$column] += 10;
			$normalized[] = array('code' => $code, 'column' => $column, 'position' => $positions[$column]);
			$seen[$code] = true;
		}
		return $normalized;
	}

	/** @return int */
	private function rollbackWithError()
	{
		$this->error = $this->db->lasterror();
		$this->errors[] = $this->error;
		$this->db->rollback();
		return -1;
	}
}
