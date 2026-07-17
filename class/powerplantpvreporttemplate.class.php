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
 * \file		class/powerplantpvreporttemplate.class.php
 * \ingroup		powerplantpv
 * \brief		Report template configuration object.
 */

dol_include_once('/powerplantpv/class/powerplantpvreportconfigbase.class.php');

/**
 * Report template configuration object.
 */
class PowerPlantPVReportTemplate extends PowerPlantPVReportConfigBase
{
	public const TARGET_INTERVENTION = 'fichinter';

	public $element = 'powerplantpv_report_template';
	public $table_element = 'powerplantpv_report_template';

	/**
	 * @var array<string,array<string,mixed>> Fields
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => -2, 'default' => 1),
		'code' => array('type' => 'varchar(64)', 'label' => 'Code', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1, 'searchall' => 1),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1, 'searchall' => 1),
		'label_en' => array('type' => 'varchar(255)', 'label' => 'PowerPlantPVEnglishLabel', 'enabled' => 1, 'position' => 21, 'notnull' => 0, 'visible' => 1),
		'description' => array('type' => 'text', 'label' => 'Description', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 1),
		'description_en' => array('type' => 'text', 'label' => 'PowerPlantPVEnglishDescription', 'enabled' => 1, 'position' => 31, 'notnull' => 0, 'visible' => 1),
		'target_element' => array('type' => 'varchar(64)', 'label' => 'PowerPlantPVReportTargetElement', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'visible' => 1, 'default' => self::TARGET_INTERVENTION),
		'is_default' => array('type' => 'smallint', 'label' => 'Default', 'enabled' => 1, 'position' => 50, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'active' => array('type' => 'smallint', 'label' => 'Status', 'enabled' => 1, 'position' => 60, 'notnull' => 1, 'visible' => 1, 'default' => 1),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => 1, 'position' => 70, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 0, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => 0, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => 0, 'visible' => -2),
	);

	public $code;
	public $label;
	public $label_en;
	public $description;
	public $description_en;
	public $target_element;
	public $is_default;
	public $active;
	public $position;

	/**
	 * Validate object before persistence.
	 *
	 * @return	int		1 if OK, <0 if KO
	 */
	protected function validateObject()
	{
		if ($this->validateCodeField('code') < 0) {
			return -1;
		}
		if ($this->validateRequiredString('label', 'PowerPlantPVReportTemplateLabelRequired') < 0) {
			return -1;
		}
		if (empty($this->target_element)) {
			$this->target_element = self::TARGET_INTERVENTION;
		}
		if ((string) $this->target_element !== self::TARGET_INTERVENTION) {
			$this->setError('PowerPlantPVReportTemplateTargetInvalid');
			return -1;
		}

		$this->is_default = (int) $this->is_default;
		$this->active = (int) $this->active;
		$this->position = (int) $this->position;

		return 1;
	}

	/**
	 * Build additional SQL filters for fetchAll().
	 *
	 * @param	array<string,mixed>	$filters	Filters
	 * @return	string							SQL fragment
	 */
	protected function buildFetchAllWhere($filters)
	{
		$sql = '';
		if (!empty($filters['search'])) {
			$search = $this->db->escape((string) $filters['search']);
			$sql .= " AND (t.code LIKE '%".$search."%' OR t.label LIKE '%".$search."%')";
		}
		if (isset($filters['is_default']) && $filters['is_default'] !== '') {
			$sql .= " AND t.is_default = ".((int) $filters['is_default']);
		}

		return $sql;
	}

	/**
	 * Set this template as entity default.
	 *
	 * @param	User	$user	User
	 * @return	int			>0 if OK, <0 on error
	 */
	public function setDefault(User $user)
	{
		global $conf;

		if (empty($this->id) && !empty($this->rowid)) {
			$this->id = (int) $this->rowid;
		}
		if (empty($this->id)) {
			$this->setError('ErrorRecordNotFound');
			return -1;
		}

		$this->db->begin();
		$sql = "UPDATE ".$this->db->prefix().$this->table_element;
		$sql .= " SET is_default = 0, fk_user_modif = ".((int) $user->id);
		$sql .= " WHERE entity = ".((int) $conf->entity);
		if (!$this->db->query($sql)) {
			$this->setError($this->db->lasterror());
			$this->db->rollback();
			return -1;
		}

		$this->is_default = 1;
		$result = $this->update($user, 0);
		if ($result < 0) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();

		return 1;
	}

	/**
	 * Duplicate the template tree.
	 *
	 * @param	User	$user		User
	 * @param	string	$newCode	New code
	 * @param	string	$newLabel	New label
	 * @return	int					New template id, <0 on error
	 */
	public function duplicateTemplate(User $user, $newCode, $newLabel)
	{
		dol_include_once('/powerplantpv/class/powerplantpvreporttemplatesection.class.php');
		dol_include_once('/powerplantpv/class/powerplantpvreporttemplatefield.class.php');
		dol_include_once('/powerplantpv/class/powerplantpvreporttemplatefieldoption.class.php');
		dol_include_once('/powerplantpv/class/powerplantpvmaintenanceservicesection.class.php');

		if (empty($this->id) && !empty($this->rowid)) {
			$this->id = (int) $this->rowid;
		}
		if (empty($this->id) || $this->validateRequiredString('code', 'ErrorRecordNotFound') < 0) {
			return -1;
		}

		$this->db->begin();

		$newTemplate = new self($this->db);
		$newTemplate->code = trim((string) $newCode);
		$newTemplate->label = trim((string) $newLabel);
		$newTemplate->label_en = $this->label_en;
		$newTemplate->description = $this->description;
		$newTemplate->description_en = $this->description_en;
		$newTemplate->target_element = $this->target_element;
		$newTemplate->is_default = 0;
		$newTemplate->active = 0;
		$newTemplate->position = ((int) $this->position) + 10;
		$newTemplateId = $newTemplate->create($user, 0);
		if ($newTemplateId <= 0) {
			$this->copyErrorsFrom($newTemplate);
			$this->db->rollback();
			return -1;
		}

		$sectionMap = array();
		$sectionObject = new PowerPlantPVReportTemplateSection($this->db);
		$sections = $sectionObject->fetchAll(-1, array('fk_report_template' => (int) $this->id), 'position', 'ASC');
		if (!is_array($sections)) {
			$this->copyErrorsFrom($sectionObject);
			$this->db->rollback();
			return -1;
		}
		foreach ($sections as $section) {
			$newSection = new PowerPlantPVReportTemplateSection($this->db);
			$newSection->fk_report_template = $newTemplateId;
			$newSection->code = $section->code;
			$newSection->label = $section->label;
			$newSection->label_en = $section->label_en;
			$newSection->description = $section->description;
			$newSection->description_en = $section->description_en;
			$newSection->scope_type = $section->scope_type;
			$newSection->equipment_type = $section->equipment_type;
			$newSection->repeat_mode = $section->repeat_mode;
			$newSection->is_required = $section->is_required;
			$newSection->visible_form = $section->visible_form;
			$newSection->visible_pdf = $section->visible_pdf;
			$newSection->active = $section->active;
			$newSection->position = $section->position;
			$newSectionId = $newSection->create($user, 0);
			if ($newSectionId <= 0) {
				$this->copyErrorsFrom($newSection);
				$this->db->rollback();
				return -1;
			}
			$sectionMap[(int) $section->id] = $newSectionId;
		}

		$fieldMap = array();
		$fieldObject = new PowerPlantPVReportTemplateField($this->db);
		$fields = $fieldObject->fetchAll(-1, array('fk_report_template' => (int) $this->id), 'position', 'ASC');
		if (!is_array($fields)) {
			$this->copyErrorsFrom($fieldObject);
			$this->db->rollback();
			return -1;
		}
		foreach ($fields as $field) {
			$oldSectionId = (int) $field->fk_report_template_section;
			$newField = new PowerPlantPVReportTemplateField($this->db);
			$newField->fk_report_template = $newTemplateId;
			$newField->fk_report_template_section = !empty($sectionMap[$oldSectionId]) ? $sectionMap[$oldSectionId] : null;
			$newField->report_template_code = $newTemplate->code;
			$newField->fk_report_section = (int) $field->fk_report_section;
			$newField->fk_maintenance_service = $field->fk_maintenance_service;
			$newField->code = $field->code;
			$newField->label = $field->label;
			$newField->label_en = $field->label_en;
			$newField->description = $field->description;
			$newField->description_en = $field->description_en;
			$newField->field_type = $field->field_type;
			$newField->scope_type = $field->scope_type;
			$newField->unit = $field->unit;
			$newField->default_value = $field->default_value;
			$newField->placeholder = $field->placeholder;
			$newField->help = $field->help;
			$newField->is_required = $field->is_required;
			$newField->visible_form = $field->visible_form;
			$newField->visible_pdf = $field->visible_pdf;
			$newField->readonly = $field->readonly;
			$newField->active = $field->active;
			$newField->position = $field->position;
			$newFieldId = $newField->create($user, 0);
			if ($newFieldId <= 0) {
				$this->copyErrorsFrom($newField);
				$this->db->rollback();
				return -1;
			}
			$fieldMap[(int) $field->id] = $newFieldId;
		}

		$optionObject = new PowerPlantPVReportTemplateFieldOption($this->db);
		foreach ($fieldMap as $oldFieldId => $newFieldId) {
			$options = $optionObject->fetchAll(-1, array('fk_report_template_field' => (int) $oldFieldId), 'position', 'ASC');
			if (!is_array($options)) {
				$this->copyErrorsFrom($optionObject);
				$this->db->rollback();
				return -1;
			}
			foreach ($options as $option) {
				$newOption = new PowerPlantPVReportTemplateFieldOption($this->db);
				$newOption->fk_report_template_field = $newFieldId;
				$newOption->code = $option->code;
				$newOption->label = $option->label;
				$newOption->label_en = $option->label_en;
				$newOption->active = $option->active;
				$newOption->position = $option->position;
				if ($newOption->create($user, 0) <= 0) {
					$this->copyErrorsFrom($newOption);
					$this->db->rollback();
					return -1;
				}
			}
		}

		$mappingObject = new PowerPlantPVMaintenanceServiceSection($this->db);
		$mappings = $mappingObject->fetchAll(-1, array('fk_report_template' => (int) $this->id), 'position', 'ASC');
		if (!is_array($mappings)) {
			$this->copyErrorsFrom($mappingObject);
			$this->db->rollback();
			return -1;
		}
		foreach ($mappings as $mapping) {
			$oldSectionId = (int) $mapping->fk_report_template_section;
			if (empty($sectionMap[$oldSectionId])) {
				continue;
			}
			$newMapping = new PowerPlantPVMaintenanceServiceSection($this->db);
			$newMapping->fk_report_template = $newTemplateId;
			$newMapping->fk_maintenance_service = $mapping->fk_maintenance_service;
			$newMapping->fk_report_section = $mapping->fk_report_section;
			$newMapping->fk_report_template_section = $sectionMap[$oldSectionId];
			$newMapping->is_required = $mapping->is_required;
			$newMapping->active = $mapping->active;
			$newMapping->position = $mapping->position;
			if ($newMapping->create($user, 0) <= 0) {
				$this->copyErrorsFrom($newMapping);
				$this->db->rollback();
				return -1;
			}
		}

		$this->db->commit();

		return $newTemplateId;
	}

	/**
	 * Copy errors from another object.
	 *
	 * @param	object	$object	Object
	 * @return	void
	 */
	private function copyErrorsFrom($object)
	{
		if (!empty($object->error)) {
			$this->error = $object->error;
		}
		if (!empty($object->errors) && is_array($object->errors)) {
			$this->errors = array_merge($this->errors, $object->errors);
		}
	}
}
