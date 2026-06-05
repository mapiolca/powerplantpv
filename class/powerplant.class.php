<?php
/* Copyright (C) 2017       Laurent Destailleur      <eldy@users.sourceforge.net>
 * Copyright (C) 2023-2025  Frédéric France          <frederic.france@free.fr>
 * Copyright (C) 2025		Pierre Ardoin				<erp@lesmetiersdubatiment.fr>
 * Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
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
 * \file        class/powerplant.class.php
 * \ingroup     powerplantpv
 * \brief       This file is a CRUD class file for PowerPlant (Create/Read/Update/Delete)
 */

// Put here all includes required by your class file
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
//require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
//require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

/**
 * Class for PowerPlant
 */
class PowerPlant extends CommonObject
{
	/**
	 * @var string 		ID of module.
	 */
	public $module = 'powerplantpv';

	/**
	 * @var string 		Main module name.
	 */
	public $mainmodule = 'powerplantpv';

	/**
	 * @var string 		ID to identify managed object.
	 */
	public $element = 'powerplant';

	/**
	 * @var string		Prefix to check for any trigger code of any business class to prevent bad value for trigger code.
	 * @see CommonTrigger::call_trigger()
	 */
	public $TRIGGER_PREFIX = 'POWERPLANTPV_POWERPLANT';	// Will be used to build trgiger keys 'POWERPLANTPV_POWERPLANT_MODIFY', ...

	/**
	 * @var string 		Name of table without prefix where object is stored. This is also the key used for extrafields management (so extrafields know the link to the parent table).
	 */
	public $table_element = 'powerplantpv_powerplant';

	/**
	 * @var string 		If permission must be checked with hasRight('powerplantpv', 'read') and not hasright('powerplantpv', 'powerplant', 'read'), you can uncomment this line
	 */
	//public $element_for_permission = 'powerplantpv';

	/**
	 * @var string 		String with name of icon for powerplant. Must be a 'fa-xxx' fontawesome code (or 'fa-xxx_fa_color_size') or 'powerplant@powerplantpv' if picto is file 'img/object_powerplant.png'.
	 */
	public $picto = 'fa-sun';

	/**
	 * @var int<0,1>	Does object support extrafields ? 0=No, 1=Yes
	 */
	public $isextrafieldmanaged = 0;

	/**
	 * @var int<0,1>|string		Does this object support multicompany module ?
	 * 							0=No test on entity, 1=Test with field entity in local table, 'field@table'=Test entity into the field@table (example 'fk_soc@societe')
	 */
	public $ismultientitymanaged = 1;


	const STATUS_DRAFT = 0;
	const STATUS_VALIDATED = 1;
	const STATUS_IN_SERVICE = 2;
	const STATUS_OUT_OF_SERVICE = 3;
	const STATUS_CANCELED = 9;

	const CONNECTION_TYPE_SELF_CONSUMPTION = 'self_consumption';
	const CONNECTION_TYPE_SELF_CONSUMPTION_SURPLUS = 'self_consumption_surplus';
	const CONNECTION_TYPE_TOTAL_SALE = 'total_sale';
	const CONNECTION_TYPE_COLLECTIVE_SELF_CONSUMPTION = 'collective_self_consumption';

	/**
	 *  'type' field format:
	 *  	'integer', 'integer:ObjectClass:PathToClass[:AddCreateButtonOrNot[:Filter[:Sortfield]]]',
	 *  	'select' (list of values are in 'options'. for integer list of values are in 'arrayofkeyval'),
	 *  	'sellist:TableName:LabelFieldName[:KeyFieldName[:KeyFieldParent[:Filter[:CategoryIdType[:CategoryIdList[:SortField]]]]]]',
	 *  	'chkbxlst:...',
	 *  	'varchar(x)',
	 *  	'text', 'text:none', 'html',
	 *   	'double(24,8)', 'real', 'price', 'stock',
	 *  	'date', 'datetime', 'timestamp', 'duration',
	 *  	'boolean', 'checkbox', 'radio', 'array',
	 *  	'email', 'phone', 'url', 'password', 'ip'
	 *		Note: Filter must be a Dolibarr Universal Filter syntax string. Example: "(t.ref:like:'SO-%') or (t.date_creation:<:'20160101') or (t.status:!=:0) or (t.nature:is:NULL)"
	 *  'length' the length of field. Example: 255, '24,8'
	 *  'label' the translation key.
	 *  'langfile' the key of the language file for translation.
	 *  'alias' the alias used into some old hard coded SQL requests
	 *  'picto' is code of a picto to show before value in forms
	 *  'enabled' is a condition when the field must be managed (Example: 1 or 'getDolGlobalInt("MY_SETUP_PARAM")' or 'isModEnabled("multicurrency")' ...)
	 *  'position' is the sort order of field.
	 *  'notnull' is set to 1 if not null in database. Set to -1 if we must set data to null if empty ('' or 0).
	 *  'visible' says if field is visible in list (Examples: 0=Not visible, 1=Visible on list and create/update/view forms, 2=Visible on list only, 3=Visible on create/update/view form only (not list), 4=Visible on list and update/view form (not create). 5=Visible on list and view form (not create/not update). 6=visible on list and update/view form (not update). Using a negative value means field is not shown by default on list but can be selected for viewing)
	 *  'noteditable' says if field is not editable (1 or 0)
	 *  'alwayseditable' says if field can be modified also when status is not draft ('1' or '0')
	 *  'default' is a default value for creation (can still be overwritten by the Setup of Default Values if the field is editable in creation form). Note: If default is set to '(PROV)' and field is 'ref', the default value will be set to '(PROVid)' where id is rowid when a new record is created.
	 *  'index' if we want an index in database.
	 *  'foreignkey'=>'tablename.field' if the field is a foreign key (it is recommended to name the field fk_...).
	 *  'searchall' is 1 if we want to search in this field when making a search from the quick search button.
	 *  'isameasure' must be set to 1 or 2 if field can be used for measure. Field type must be summable like integer or double(24,8). Use 1 in most cases, or 2 if you don't want to see the column total into list (for example for percentage)
	 *  'css' and 'cssview' and 'csslist' is the CSS style to use on field. 'css' is used in creation and update. 'cssview' is used in view mode. 'csslist' is used for columns in lists. For example: 'css'=>'minwidth300 maxwidth500 widthcentpercentminusx', 'cssview'=>'wordbreak', 'csslist'=>'tdoverflowmax200'
	 *  'placeholder' to set the placeholder of a varchar field.
	 *  'help' and 'helplist' is a 'TranslationString' to use to show a tooltip on field. You can also use 'TranslationString:keyfortooltiponlick' for a tooltip on click.
	 *  'showoncombobox' if value of the field must be visible into the label of the combobox that list record
	 *  'disabled' is 1 if we want to have the field locked by a 'disabled' attribute. In most cases, this is never set into the definition of $fields into class, but is set dynamically by some part of code like the constructor of the class.
	 *  'arrayofkeyval' to set a list of values if type is a list of predefined values. For example: array("0"=>"Draft","1"=>"Active","-1"=>"Cancel"). Note that type can be 'integer' or 'varchar'
	 *  'autofocusoncreate' to have field having the focus on a create form. Only 1 field should have this property set to 1.
	 *  'comment' is not used. You can store here any text of your choice. It is not used by application.
	 *	'validate' is 1 if you need to validate the field with $this->validateField(). Need MAIN_ACTIVATE_VALIDATION_RESULT.
	 *  'copytoclipboard' is 1 or 2 to allow to add a picto to copy value into clipboard (1=picto after label, 2=picto after value)
	 *
	 *  Note: To have value dynamic, you can set value to 0 in definition and edit the value on the fly into the constructor.
	 */

	// BEGIN MODULEBUILDER PROPERTIES
	/**
	 * @inheritdoc
	 * Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.
	 */
	public $fields = array(
		"rowid" => array("type" => "integer", "label" => "TechnicalID", "enabled" => "1", 'position' => 1, 'notnull' => 1, "visible" => "0", "noteditable" => "1", "index" => "1", "css" => "left", "comment" => "Id"),
		"ref" => array("type" => "varchar(128)", "label" => "Ref", "enabled" => "1", 'position' => 20, 'notnull' => 1, "visible" => "1", "index" => "1", "searchall" => "1", "showoncombobox" => "1", "validate" => "1", "default" => "(PROV)", "comment" => "Reference of object"),
		"label" => array("type" => "varchar(255)", "label" => "Label", "enabled" => "1", 'position' => 30, 'notnull' => 0, "visible" => "1", "alwayseditable" => "1", "searchall" => "1", "css" => "minwidth300", "cssview" => "wordbreak", "help" => "Help text", "showoncombobox" => "2", "validate" => "1",),
		"entity" => array("type" => "integer", "label" => "Entity", "enabled" => "1", 'position' => 900, 'notnull' => 1, "visible" => "-2", "default" => 1, "index" => "1"),
		"commissioning_date" => array("type" => "date", "label" => "PowerPlantCommissioningDate", "enabled" => "1", 'position' => 35, 'notnull' => 0, "visible" => "1", "validate" => "1",),
		"prm_pdl_number" => array("type" => "varchar(128)", "label" => "PowerPlantPrmPdlNumber", "enabled" => "1", 'position' => 36, 'notnull' => 0, "visible" => "1", "searchall" => "1", "validate" => "1",),
		"address" => array("type" => "varchar(255)", "label" => "Address", "enabled" => "1", 'position' => 37, 'notnull' => 0, "visible" => "3", "searchall" => "1", "css" => "minwidth300", "cssview" => "wordbreak", "validate" => "1",),
		"zip" => array("type" => "varchar(25)", "label" => "Zip", "enabled" => "1", 'position' => 38, 'notnull' => 0, "visible" => "1", "searchall" => "1", "csslist" => "nowraponall", "validate" => "1",),
		"town" => array("type" => "varchar(255)", "label" => "Town", "enabled" => "1", 'position' => 39, 'notnull' => 0, "visible" => "1", "searchall" => "1", "validate" => "1",),
		"fk_country" => array("type" => "sellist:c_country:label:rowid::active=1", "label" => "Country", "enabled" => "1", 'position' => 40, 'notnull' => 0, "visible" => "1", "index" => "1", "validate" => "1",),
		"access_instructions" => array("type" => "text", "label" => "PowerPlantAccessInstructions", "enabled" => "1", 'position' => 40, 'notnull' => 0, "visible" => "3", "css" => "minwidth500", "cssview" => "wordbreak", "validate" => "1",),
		"installed_power" => array("type" => "double(24,8)", "label" => "PowerPlantInstalledPower", "enabled" => "1", 'position' => 41, 'notnull' => 0, "visible" => "1", "noteditable" => "1", "isameasure" => "1", "validate" => "1", "default" => "0", "css" => "right", "cssview" => "right", "csslist" => "right"),
		"connection_contract_power" => array("type" => "double(24,8)", "label" => "PowerPlantConnectionContractPower", "enabled" => "1", 'position' => 42, 'notnull' => 0, "visible" => "1", "isameasure" => "1", "validate" => "1", "css" => "right", "cssview" => "right", "csslist" => "right"),
		"connection_type" => array("type" => "varchar(128)", "label" => "PowerPlantConnectionType", "enabled" => "1", 'position' => 43, 'notnull' => 0, "visible" => "1", "searchall" => "1", "validate" => "1", "arrayofkeyval" => array(),),
		"enedis_commissioning_date" => array("type" => "date", "label" => "PowerPlantEnedisCommissioningDate", "enabled" => "1", 'position' => 44, 'notnull' => 0, "visible" => "1", "validate" => "1",),
		"connection_request_number" => array("type" => "varchar(128)", "label" => "PowerPlantConnectionRequestNumber", "enabled" => "1", 'position' => 45, 'notnull' => 0, "visible" => "1", "searchall" => "1", "validate" => "1",),
		"t0_obtention_date" => array("type" => "date", "label" => "PowerPlantT0ObtentionDate", "enabled" => "1", 'position' => 46, 'notnull' => 0, "visible" => "1", "validate" => "1",),
		"buyback_contract_number" => array("type" => "varchar(128)", "label" => "PowerPlantBuybackContractNumber", "enabled" => "1", 'position' => 47, 'notnull' => 0, "visible" => "1", "searchall" => "1", "validate" => "1",),
		"buyback_tariff" => array("type" => "price", "label" => "PowerPlantBuybackTariff", "enabled" => "1", 'position' => 48, 'notnull' => 0, "visible" => "1", "validate" => "1",),
		"fk_soc" => array("type" => "integer:Societe:societe/class/societe.class.php:1:((status:=:1) AND (entity:IN:__SHARED_ENTITIES__))", "label" => "ThirdParty", "picto" => "company", "enabled" => "isModEnabled('societe')", 'position' => 31, 'notnull' => -1, "visible" => "1", "index" => "1", "css" => "maxwidth500 widthcentpercentminusxx", "csslist" => "tdoverflowmax150", "help" => "OrganizationEventLinkToThirdParty", "validate" => "1",),
		"fk_project" => array("type" => "integer:Project:projet/class/project.class.php:1", "label" => "Project", "picto" => "project", "enabled" => "isModEnabled('project')", 'position' => 32, 'notnull' => -1, "visible" => "1", "index" => "1", "css" => "maxwidth500 widthcentpercentminusxx", "csslist" => "tdoverflowmax150", "validate" => "1",),
		"description" => array("type" => "text", "label" => "Description", "enabled" => "1", 'position' => 60, 'notnull' => 0, "visible" => "3", "validate" => "1",),
		"note_public" => array("type" => "html", "label" => "NotePublic", "enabled" => "1", 'position' => 61, 'notnull' => 0, "visible" => "0", "cssview" => "wordbreak", "validate" => "1",),
		"note_private" => array("type" => "html", "label" => "NotePrivate", "enabled" => "1", 'position' => 62, 'notnull' => 0, "visible" => "0", "cssview" => "wordbreak", "validate" => "1",),
		"date_creation" => array("type" => "datetime", "label" => "DateCreation", "enabled" => "1", 'position' => 500, 'notnull' => 1, "visible" => "-2",),
		"tms" => array("type" => "timestamp", "label" => "DateModification", "enabled" => "1", 'position' => 501, 'notnull' => 0, "visible" => "-2",),
		"fk_user_creat" => array("type" => "integer:User:user/class/user.class.php", "label" => "UserAuthor", "picto" => "user", "enabled" => "1", 'position' => 510, 'notnull' => 1, "visible" => "-2", "csslist" => "tdoverflowmax150",),
		"fk_user_modif" => array("type" => "integer:User:user/class/user.class.php", "label" => "UserModif", "picto" => "user", "enabled" => "1", 'position' => 511, 'notnull' => -1, "visible" => "-2", "csslist" => "tdoverflowmax150",),
		"last_main_doc" => array("type" => "varchar(255)", "label" => "LastMainDoc", "enabled" => "1", 'position' => 600, 'notnull' => 0, "visible" => "0",),
		"import_key" => array("type" => "varchar(14)", "label" => "ImportId", "enabled" => "1", 'position' => 1000, 'notnull' => -1, "visible" => "-2",),
		"model_pdf" => array("type" => "varchar(255)", "label" => "Model pdf", "enabled" => "1", 'position' => 1010, 'notnull' => -1, "visible" => "0",),
		"status" => array("type" => "integer", "label" => "Status", "enabled" => "1", 'position' => 2000, 'notnull' => 1, "visible" => "4", "index" => "1", "default" => self::STATUS_DRAFT, "arrayofkeyval" => array(self::STATUS_DRAFT => "Draft", self::STATUS_VALIDATED => "Validated", self::STATUS_IN_SERVICE => "PowerPlantInService", self::STATUS_OUT_OF_SERVICE => "PowerPlantOutOfService", self::STATUS_CANCELED => "Canceled"), "validate" => "1",),
	);
	public $rowid;
	public $ref;
	public $label;
	public $entity;
	public $commissioning_date;
	public $prm_pdl_number;
	public $address;
	public $zip;
	public $town;
	public $fk_country;
	public $access_instructions;
	public $installed_power;
	public $connection_contract_power;
	public $connection_type;
	public $enedis_commissioning_date;
	public $connection_request_number;
	public $t0_obtention_date;
	public $buyback_contract_number;
	public $buyback_tariff;
	public $fk_soc;
	public $socid;
	public $fk_project;
	public $description;
	public $note_public;
	public $note_private;
	public $date_creation;
	public $tms;
	public $fk_user_creat;
	public $fk_user_modif;
	public $last_main_doc;
	public $import_key;
	public $model_pdf;
	public $status;

	/**
	 * @var int<0,1> Create material composition from the origin object after creation.
	 */
	public $create_material_from_origin = 0;
	// END MODULEBUILDER PROPERTIES


	// If this object has a subtable with lines

	// /**
	//  * @var string    Name of subtable line
	//  */
	// public $table_element_line = 'powerplantpv_powerplantline';

	// /**
	//  * @var string    Field name with ID of parent key if this object has a parent, Or Field name of in child tables to link to this record.
	//  */
	// public $fk_element = 'fk_powerplant';

	// /**
	//  * @var string    Name of subtable class that manage subtable lines
	//  */
	// public $class_element_line = 'PowerPlantline';

	// /**
	//  * @var array	List of child tables. To test if we can delete object.
	//  */
	// protected $childtables = array('mychildtable' => array('name'=>'PowerPlant', 'fk_element'=>'fk_powerplant'));

	// /**
	//  * @var array    List of child tables. To know object to delete on cascade.
	//  *               If name matches '@ClassName:FilePathClass:ParentFkFieldName' (the recommended mode) it will
	//  *               call method ClassName->deleteByParentField(parentId, 'ParentFkFieldName') to fetch and delete child object.
	//  *               Using an array like childtables should not be implemented because a child may have other child, so we must only use the method that call deleteByParentField().
	//  */
	// protected $childtablesoncascade = array('powerplantpv_powerplantdet');

	// /**
	//  * @var PowerPlantLine[]     Array of subtable lines
	//  */
	// public $lines = array();



	/**
	 * Constructor
	 *
	 * @param	DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $langs;

		$this->db = $db;

		if (!getDolGlobalInt('MAIN_SHOW_TECHNICAL_ID') && isset($this->fields['rowid']) && !empty($this->fields['ref'])) {
			$this->fields['rowid']['visible'] = 0;
		}
		if (!isModEnabled('multicompany') && isset($this->fields['entity'])) {
			$this->fields['entity']['enabled'] = 0;
		}
		if (isset($this->fields['connection_type'])) {
			$this->fields['connection_type']['arrayofkeyval'] = self::getConnectionTypeOptions();
		}

		// Example to show how to set values of fields definition dynamically
		/*if ($user->hasRight('powerplantpv', 'powerplant', 'read')) {
			$this->fields['myfield']['visible'] = 1;
			$this->fields['myfield']['noteditable'] = 0;
		}*/

		// Unset fields that are disabled
		foreach ($this->fields as $key => $val) {
			$enabled = (isset($val['enabled']) ? $val['enabled'] : 1);
			if (is_string($enabled) && !is_numeric($enabled)) {
				$enabled = dol_eval($enabled, 1);
			}
			if (empty($enabled)) {
				unset($this->fields[$key]);
			}
		}

		// Translate some data of arrayofkeyval
		if (is_object($langs)) {
			foreach ($this->fields as $key => $val) {
				if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
					foreach ($val['arrayofkeyval'] as $key2 => $val2) {
						$this->fields[$key]['arrayofkeyval'][$key2] = $langs->trans($val2);
					}
				}
			}
		}
	}

	/**
	 * Return available grid connection types.
	 *
	 * @return	array<string,string>	Technical key => translation key
	 */
	public static function getConnectionTypeOptions()
	{
		return array(
			self::CONNECTION_TYPE_SELF_CONSUMPTION => 'PowerPlantConnectionTypeSelfConsumption',
			self::CONNECTION_TYPE_SELF_CONSUMPTION_SURPLUS => 'PowerPlantConnectionTypeSelfConsumptionSurplus',
			self::CONNECTION_TYPE_TOTAL_SALE => 'PowerPlantConnectionTypeTotalSale',
			self::CONNECTION_TYPE_COLLECTIVE_SELF_CONSUMPTION => 'PowerPlantConnectionTypeCollectiveSelfConsumption',
		);
	}

	/**
	 * Normalize a stored or legacy connection type value.
	 *
	 * Known legacy labels are mapped to stable technical keys. Unknown values are
	 * returned unchanged so existing records remain readable and editable.
	 *
	 * @param	mixed	$value	Stored value
	 * @return	string			Technical key or original unknown value
	 */
	public static function normalizeConnectionTypeValue($value)
	{
		$value = trim((string) $value);
		if ($value === '' || $value === '0' || $value === '-1') {
			return '';
		}

		$options = self::getConnectionTypeOptions();
		if (array_key_exists($value, $options)) {
			return $value;
		}

		$normalized = self::normalizeConnectionTypeSearchValue($value);
		$mapping = array(
			'self_consumption' => self::CONNECTION_TYPE_SELF_CONSUMPTION,
			'autoconsommation' => self::CONNECTION_TYPE_SELF_CONSUMPTION,
			'auto_consumption' => self::CONNECTION_TYPE_SELF_CONSUMPTION,
			'autoconsumo' => self::CONNECTION_TYPE_SELF_CONSUMPTION,
			'eigenverbrauch' => self::CONNECTION_TYPE_SELF_CONSUMPTION,
			'selfconsumption' => self::CONNECTION_TYPE_SELF_CONSUMPTION,
			'self_consumption_surplus' => self::CONNECTION_TYPE_SELF_CONSUMPTION_SURPLUS,
			'self_consumption_with_surplus' => self::CONNECTION_TYPE_SELF_CONSUMPTION_SURPLUS,
			'self_consumption_with_surplus_monetization' => self::CONNECTION_TYPE_SELF_CONSUMPTION_SURPLUS,
			'autoconsommation_avec_surplus' => self::CONNECTION_TYPE_SELF_CONSUMPTION_SURPLUS,
			'autoconsommation_avec_valorisation_du_surplus' => self::CONNECTION_TYPE_SELF_CONSUMPTION_SURPLUS,
			'autoconsumo_con_valorizacion_del_excedente' => self::CONNECTION_TYPE_SELF_CONSUMPTION_SURPLUS,
			'autoconsumo_con_valorizzazione_del_surplus' => self::CONNECTION_TYPE_SELF_CONSUMPTION_SURPLUS,
			'eigenverbrauch_mit_verwertung_des_uberschusses' => self::CONNECTION_TYPE_SELF_CONSUMPTION_SURPLUS,
			'valorisation_du_surplus' => self::CONNECTION_TYPE_SELF_CONSUMPTION_SURPLUS,
			'vente_surplus' => self::CONNECTION_TYPE_SELF_CONSUMPTION_SURPLUS,
			'surplus' => self::CONNECTION_TYPE_SELF_CONSUMPTION_SURPLUS,
			'total_sale' => self::CONNECTION_TYPE_TOTAL_SALE,
			'total_sale_of_production' => self::CONNECTION_TYPE_TOTAL_SALE,
			'valorisation_totale_de_la_production' => self::CONNECTION_TYPE_TOTAL_SALE,
			'valorisation_totale_production' => self::CONNECTION_TYPE_TOTAL_SALE,
			'valorizacion_total_de_la_produccion' => self::CONNECTION_TYPE_TOTAL_SALE,
			'valorizzazione_totale_della_produzione' => self::CONNECTION_TYPE_TOTAL_SALE,
			'vollstandige_vermarktung_der_produktion' => self::CONNECTION_TYPE_TOTAL_SALE,
			'vente_totale' => self::CONNECTION_TYPE_TOTAL_SALE,
			'revente_totale' => self::CONNECTION_TYPE_TOTAL_SALE,
			'collective_self_consumption' => self::CONNECTION_TYPE_COLLECTIVE_SELF_CONSUMPTION,
			'collective_autoconsommation' => self::CONNECTION_TYPE_COLLECTIVE_SELF_CONSUMPTION,
			'autoconsommation_collective' => self::CONNECTION_TYPE_COLLECTIVE_SELF_CONSUMPTION,
			'autoconsumo_colectivo' => self::CONNECTION_TYPE_COLLECTIVE_SELF_CONSUMPTION,
			'autoconsumo_collettivo' => self::CONNECTION_TYPE_COLLECTIVE_SELF_CONSUMPTION,
			'kollektiver_eigenverbrauch' => self::CONNECTION_TYPE_COLLECTIVE_SELF_CONSUMPTION,
		);

		return (isset($mapping[$normalized]) ? $mapping[$normalized] : $value);
	}

	/**
	 * Return the display label for a connection type.
	 *
	 * @param	mixed			$value			Stored value
	 * @param	Translate|null	$outputlangs	Output language
	 * @return	string							Translated label or original unknown value
	 */
	public static function getConnectionTypeLabel($value, $outputlangs = null)
	{
		global $langs;

		$value = trim((string) $value);
		if ($value === '') {
			return '';
		}

		$normalized = self::normalizeConnectionTypeValue($value);
		if ($normalized === '') {
			return '';
		}
		$options = self::getConnectionTypeOptions();
		if (!isset($options[$normalized])) {
			return $value;
		}

		$translator = (is_object($outputlangs) ? $outputlangs : $langs);
		if (is_object($translator)) {
			return $translator->trans($options[$normalized]);
		}

		return $options[$normalized];
	}

	/**
	 * Normalize a connection type string for legacy matching.
	 *
	 * @param	string	$value	Raw value
	 * @return	string			Search key
	 */
	protected static function normalizeConnectionTypeSearchValue($value)
	{
		$value = html_entity_decode(trim((string) $value), ENT_QUOTES, 'UTF-8');
		$value = strtr($value, array(
			'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
			'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
			'Ç' => 'C', 'ç' => 'c',
			'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
			'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
			'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
			'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
			'Ñ' => 'N', 'ñ' => 'n',
			'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
			'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
			'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
			'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
			'Ý' => 'Y', 'ý' => 'y', 'ÿ' => 'y',
			'Œ' => 'OE', 'œ' => 'oe',
		));
		$value = strtolower($value);
		$value = preg_replace('/[^a-z0-9]+/', '_', $value);

		return trim((string) $value, '_');
	}

	/**
	 * Create object into database
	 *
	 * @param	User		$user		User that creates
	 * @param	int<0,1> 	$notrigger	0=launch triggers after, 1=disable triggers
	 * @return	int<-1,max>				Return integer <0 if KO, Id of created object if OK
	 */
	public function create(User $user, $notrigger = 0)
	{
		global $conf;

		if (empty($this->fk_soc) && !empty($this->socid)) {
			$this->fk_soc = (int) $this->socid;
		}
		if (!empty($this->fk_soc)) {
			$this->socid = (int) $this->fk_soc;
		}
		if (empty($this->entity)) {
			$this->entity = (int) $conf->entity;
		}
		if (isset($this->connection_type)) {
			$this->connection_type = self::normalizeConnectionTypeValue($this->connection_type);
		}

		// Ensure provisional reference and draft status before creation
		if (empty($this->ref)) {
			$this->ref = '(PROV)';
		}
		if (!isset($this->status)) {
			$this->status = self::STATUS_DRAFT;
		}

		$this->db->begin();

		$result = $this->createCommon($user, 1);
		if ($result < 0) {
			$this->db->rollback();
			return $result;
		}

		if ($result > 0 && !empty($this->ref) && $this->ref === '(PROV)') {
			// EN: Assign final reference using selected numbering module
			// FR: Attribuer la référence finale via le module de numérotation sélectionné
			$refResult = $this->assignFinalReference($user);
			if ($refResult < 0) {
				if (!empty($this->error)) {
					$this->errors[] = $this->error;
				}
				$this->db->rollback();
				return $refResult;
			}
		}

		if ($result > 0 && !empty($this->origin) && !empty($this->origin_id)) {
			$linkResult = $this->linkOriginObject($user, $notrigger);
			if ($linkResult < 0) {
				if (!empty($this->error)) {
					$this->errors[] = $this->error;
				}
				$this->db->rollback();
				return $linkResult;
			}
		}

		if ($result > 0 && !empty($this->context['powerplantpv_origin_fk_project'])) {
			$linkResult = $this->linkProjectObject((int) $this->context['powerplantpv_origin_fk_project'], $user, $notrigger);
			if ($linkResult < 0) {
				if (!empty($this->error)) {
					$this->errors[] = $this->error;
				}
				$this->db->rollback();
				return $linkResult;
			}
		}

		if ($result > 0 && !empty($this->create_material_from_origin) && !empty($this->origin) && !empty($this->origin_id)) {
			dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
			$compositionResult = powerplantpvCreateComponentsFromOrigin($this, $this->origin, (int) $this->origin_id, $user);
			if ($compositionResult < 0) {
				if (!empty($this->error)) {
					$this->errors[] = $this->error;
				}
				$this->db->rollback();
				return -1;
			}
		}

		// uncomment lines below if you want to validate object after creation
		// if ($result > 0) {
		// $this->fetch($this->id); // needed to retrieve some fields (ie date_creation for masked ref)
		// $resultupdate = $this->validate($user, $notrigger);
		// if ($resultupdate < 0) { return $resultupdate; }
		// }

		if ($result > 0 && !$notrigger) {
			$triggerResult = $this->callPowerPlantTrigger('CREATE', $user);
			if ($triggerResult < 0) {
				$this->db->rollback();
				return -1;
			}
		}

		$this->db->commit();

		return $result;
	}

	/**
	 * Call a canonical PowerPlantPV business trigger.
	 *
	 * @param	string	$action	Trigger action suffix
	 * @param	User	$user	User that performs the action
	 * @return	int			Return integer <0 if KO, >=0 if OK
	 */
	protected function callPowerPlantTrigger($action, User $user)
	{
		$triggercode = $this->TRIGGER_PREFIX.'_'.strtoupper($action);
		$result = $this->call_trigger($triggercode, $user);
		if ($result < 0 && empty($this->error)) {
			$this->error = 'ErrorTriggerFailed';
		}

		return $result;
	}

	/**
	 * Link this power plant to the creation origin.
	 *
	 * @param	User		$user		User that creates the link
	 * @param	int<0,1>	$notrigger	1=disable triggers
	 * @return	int<-1,1>				Return integer <0 if KO, >0 if OK
	 */
	protected function linkOriginObject(User $user, $notrigger = 0)
	{
		$origin = (string) $this->origin;
		$originid = (int) $this->origin_id;
		if ($origin == 'order') {
			$origin = 'commande';
		}
		if ($origin == 'contract') {
			$origin = 'contrat';
		}
		if ($origin === '' || $originid <= 0 || empty($this->id)) {
			return 1;
		}

		$targettype = $this->getElementType();
		$sql = "SELECT ee.rowid";
		$sql .= " FROM ".$this->db->prefix()."element_element as ee";
		$sql .= " WHERE ee.fk_source = ".$originid;
		$sql .= " AND ee.sourcetype = '".$this->db->escape($origin)."'";
		$sql .= " AND ee.fk_target = ".((int) $this->id);
		$sql .= " AND ee.targettype = '".$this->db->escape($targettype)."'";

		$resql = $this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql) > 0) {
				$this->db->free($resql);
				return 1;
			}
			$this->db->free($resql);
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$result = $this->add_object_linked($origin, $originid, $user, $notrigger);
		if ($result <= 0) {
			if (empty($this->error)) {
				$this->error = 'ErrorFailedToLinkToOrigin';
			}
			return -1;
		}

		return 1;
	}

	/**
	 * Link this power plant to a project with the native object link table.
	 *
	 * @param	int			$projectid	Project id
	 * @param	User		$user		User that creates the link
	 * @param	int<0,1>	$notrigger	1=disable triggers
	 * @return	int<-1,1>				Return integer <0 if KO, >0 if OK
	 */
	protected function linkProjectObject($projectid, User $user, $notrigger = 0)
	{
		$projectid = (int) $projectid;
		if ($projectid <= 0 || empty($this->id)) {
			return 1;
		}

		$targettype = $this->getElementType();
		$sql = "SELECT ee.rowid";
		$sql .= " FROM ".$this->db->prefix()."element_element as ee";
		$sql .= " WHERE ee.fk_source = ".$projectid;
		$sql .= " AND ee.sourcetype = 'project'";
		$sql .= " AND ee.fk_target = ".((int) $this->id);
		$sql .= " AND ee.targettype = '".$this->db->escape($targettype)."'";

		$resql = $this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql) > 0) {
				$this->db->free($resql);
				return 1;
			}
			$this->db->free($resql);
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$result = $this->add_object_linked('project', $projectid, $user, $notrigger);
		if ($result <= 0) {
			if (empty($this->error)) {
				$this->error = 'ErrorFailedToLinkToProject';
			}
			return -1;
		}

		return 1;
	}

	/**
	 * Clone an object into another one
	 *
	 * @param	User 	$user		User that creates
	 * @param	int 	$fromid		Id of object to clone
	 * @return	self|int<-1,-1>		New object created, <0 if KO
	 */
	public function createFromClone(User $user, $fromid)
	{
		global $langs, $extrafields;
		$error = 0;

		dol_syslog(__METHOD__, LOG_DEBUG);

		$object = new self($this->db);

		$this->db->begin();

		// Load source object
		$result = $object->fetchCommon($fromid);
		if ($result > 0 && !empty($object->table_element_line)) {
			$object->fetchLines();
		}

		// get lines so they will be clone
		//foreach($this->lines as $line)
		//	$line->fetch_optionals();

		// Reset some properties
		unset($object->id);
		unset($object->fk_user_creat);
		unset($object->import_key);

		// Clear fields
		if (property_exists($object, 'ref')) {
			$object->ref = empty($this->fields['ref']['default']) ? "Copy_Of_".$object->ref : $this->fields['ref']['default'];
		}
		if (property_exists($object, 'label')) {
			$object->label = empty($this->fields['label']['default']) ? $langs->trans("CopyOf")." ".$object->label : $this->fields['label']['default'];
		}
		if (property_exists($object, 'status')) {
			$object->status = self::STATUS_DRAFT;
		}
		if (property_exists($object, 'fk_project')) {
			$object->fk_project = null;
		}
		if (property_exists($object, 'connection_type')) {
			$object->connection_type = self::normalizeConnectionTypeValue($object->connection_type);
		}
		if (property_exists($object, 'date_creation')) {
			$object->date_creation = dol_now();
		}
		if (property_exists($object, 'date_modification')) {
			$object->date_modification = null;
		}
		// ...
		// Clear extrafields that are unique
		if (is_array($object->array_options) && count($object->array_options) > 0) {
			$extrafields->fetch_name_optionals_label($this->table_element);
			foreach ($object->array_options as $key => $option) {
				$shortkey = preg_replace('/options_/', '', $key);
				if (!empty($extrafields->attributes[$this->table_element]['unique'][$shortkey])) {
					//var_dump($key);
					//var_dump($clonedObj->array_options[$key]); exit;
					unset($object->array_options[$key]);
				}
			}
		}

		// Create clone
		$object->context['createfromclone'] = 'createfromclone';
		$result = $object->createCommon($user, 1);
		if ($result < 0) {
			$error++;
			$this->setErrorsFromObject($object);
		}
		if (!$error && $object->callPowerPlantTrigger('CREATE', $user) < 0) {
			$error++;
			$this->setErrorsFromObject($object);
		}

		if (!$error) {
			// copy internal contacts
			if ($this->copy_linked_contact($object, 'internal') < 0) {
				$error++;
			}
		}

		if (!$error) {
			// copy external contacts if same company
			if (!empty($object->socid) && ((property_exists($this, 'fk_soc') && ($this->fk_soc == $object->socid)) || (property_exists($this, 'socid') && ($this->socid == $object->socid)))) {	// @phpstan-ignore-line
				if ($this->copy_linked_contact($object, 'external') < 0) {
					$error++;
				}
			}
		}

		unset($object->context['createfromclone']);

		// End
		if (!$error) {
			$this->db->commit();
			return $object;
		} else {
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param	int    		$id   			Id object
	 * @param	string 		$ref  			Ref
	 * @param	int<0,1>	$noextrafields	0=Default to load extrafields, 1=No extrafields
	 * @param	int<0,1>	$nolines		0=Default to load lines, 1=No lines
	 * @return	int<-1,1>					Return integer <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id, $ref = null, $noextrafields = 0, $nolines = 0)
	{
		$result = $this->fetchCommon($id, $ref, '', $noextrafields);
		if ($result > 0 && !empty($this->fk_soc)) {
			$this->socid = (int) $this->fk_soc;
		}
		if ($result > 0 && !empty($this->table_element_line) && empty($nolines)) {
			$this->fetchLines($noextrafields);
		}
		return $result;
	}

	/**
	 * Load numbering module and set final reference.
	 *
	 * @param	User	$user	Current user
	 * @return	int				<0 if error, 0 if OK
	 */
	protected function assignFinalReference(User $user)
	{
		global $conf, $langs;

		$moduleName = getDolGlobalString('POWERPLANTPV_POWERPLANT_ADDON', 'mod_powerplant_standard');
		$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);
		$loaded = false;

		foreach ($dirmodels as $reldir) {
			$file = dol_buildpath($reldir.'core/modules/powerplantpv/'.$moduleName.'.php', 0);
			if (is_readable($file)) {
				require_once $file;
				$loaded = true;
				break;
			}
		}

		if (!$loaded || !class_exists($moduleName)) {
			$this->error = $langs->trans('Error') . ' : ' . $moduleName;
			return -1;
		}

		$module = new $moduleName($this->db);
		if (empty($module->isEnabled()) && method_exists($module, 'isEnabled')) {
			$this->error = $langs->trans('Error') . ' : ' . $moduleName;
			return -1;
		}

		$maxtries = 5;
		for ($attempt = 0; $attempt < $maxtries; $attempt++) {
			$nextRef = $module->getNextValue($this);
			if (empty($nextRef) || preg_match('/^Error/', (string) $nextRef)) {
				$this->error = $langs->trans('Error') . ' : ' . $module->error;
				return -1;
			}

			$refalreadyused = $this->isReferenceUsedInSharedEntities((string) $nextRef);
			if ($refalreadyused < 0) {
				return -1;
			}
			if ($refalreadyused > 0) {
				continue;
			}

			$this->ref = (string) $nextRef;

			$sql = "UPDATE ".$this->db->prefix().$this->table_element;
			$sql .= " SET ref = '".$this->db->escape($this->ref)."'";
			$sql .= " WHERE rowid = ".((int) $this->id);

			$resql = $this->db->query($sql);
			if ($resql) {
				return 0;
			}

			$this->error = $this->db->lasterror();
			if (preg_match('/duplicate|duplicata|unique/i', $this->error)) {
				continue;
			}

			return -1;
		}

		if (empty($this->error)) {
			$this->error = $langs->trans('ErrorRefAlreadyExists');
		}

		return -1;
	}

	/**
	 * Return the entities where the current reference must be unique.
	 *
	 * @return	string	Comma-separated entity ids
	 */
	protected function getReferenceEntityList()
	{
		if (!class_exists('ModeleNumRefPowerPlant')) {
			dol_include_once('/powerplantpv/core/modules/powerplantpv/modules_powerplant.php');
		}

		if (class_exists('ModeleNumRefPowerPlant')) {
			return ModeleNumRefPowerPlant::getPowerPlantReferenceEntityList($this);
		}

		return getEntity($this->element);
	}

	/**
	 * Check if a reference already exists in the native multicompany sharing scope.
	 *
	 * @param	string	$ref	Reference to check
	 * @return	int				1 if used, 0 if free, <0 if KO
	 */
	protected function isReferenceUsedInSharedEntities($ref)
	{
		if ($ref === '') {
			return 0;
		}

		$sql = "SELECT t.rowid";
		$sql .= " FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE t.ref = '".$this->db->escape($ref)."'";
		if (!empty($this->id)) {
			$sql .= " AND t.rowid <> ".((int) $this->id);
		}
		if ($this->ismultientitymanaged == 1 && !empty($this->fields['entity'])) {
			$sql .= " AND t.entity IN (".$this->getReferenceEntityList().")";
		}
		$sql .= " LIMIT 1";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$found = ($this->db->num_rows($resql) > 0 ? 1 : 0);
		$this->db->free($resql);

		return $found;
	}

	/**
	 * Compute provisional reference preview for creation form.
	 *
	 * @return	string	Provisional reference like (PROV1)
	 */
	public function getProvisionalRefPreview()
	{
		global $conf;

		$nextId = 1;

		$sql = "SELECT MAX(rowid) as maxid FROM ".$this->db->prefix().$this->table_element;
		if ($this->ismultientitymanaged == 1 && !empty($this->fields['entity'])) {
			$sql .= " WHERE entity = ".((int) $conf->entity);
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj && $obj->maxid !== null) {
				$nextId = ((int) $obj->maxid) + 1;
			}
		}

		return '(PROV'.$nextId.')';
	}

	/**
	 * Load object lines in memory from the database
	 *
	 * @param	int<0,1>	$noextrafields	0=Default to load extrafields, 1=No extrafields
	 * @return 	int<-1,1>					Return integer <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetchLines($noextrafields = 0)
	{
		$this->lines = array();

		$result = $this->fetchLinesCommon('', $noextrafields);
		return $result;
	}


	/**
	 * Load list of objects in memory from the database.
	 * Using a fetchAll() with limit = 0 is a very bad practice. Instead try to forge yourself an optimized SQL request with
	 * your own loop with start and stop pagination.
	 *
	 * @param	string		$sortorder	Sort Order
	 * @param	string		$sortfield	Sort field
	 * @param	int<0,max>	$limit		Limit the number of lines returned
	 * @param	int<0,max>	$offset		Offset
	 * @param	string		$filter		Filter as an Universal Search string.
	 *                                  Example: '((client:=:1) OR ((client:>=:2) AND (client:<=:3))) AND (client:!=:8) AND (nom:like:'a%')'
	 * @param	string		$filtermode	No longer used
	 * @return	array<int,self>|int<-1,-1>	 <0 if KO, array of pages if OK
	 */
	public function fetchAll($sortorder = '', $sortfield = '', $limit = 1000, $offset = 0, string $filter = '', $filtermode = 'AND')
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		$records = array();

		$sql = "SELECT ";
		$sql .= $this->getFieldList('t');
		$sql .= " FROM ".$this->db->prefix().$this->table_element." as t";
		if (!empty($this->isextrafieldmanaged) && $this->isextrafieldmanaged == 1) {
			$sql .= " LEFT JOIN ".$this->db->prefix().$this->table_element."_extrafields as te ON te.fk_object = t.rowid";
		}
		if (!empty($this->ismultientitymanaged) && (int) $this->ismultientitymanaged == 1) {
			$sql .= " WHERE t.entity IN (".getEntity($this->element).")";
		} elseif (preg_match('/^\w+@\w+$/', (string) $this->ismultientitymanaged)) {
			$tmparray = explode('@', (string) $this->ismultientitymanaged);
			$sql .= " LEFT JOIN ".$this->db->prefix().$tmparray[1]." as pt ON t.".$this->db->sanitize($tmparray[0])." = pt.rowid";
			$sql .= " WHERE pt.entity IN (".getEntity($this->element).")";
		} else {
			$sql .= " WHERE 1 = 1";
		}

		// Manage filter
		$errormessage = '';
		$sql .= forgeSQLFromUniversalSearchCriteria($filter, $errormessage);
		if ($errormessage) {
			$this->errors[] = $errormessage;
			dol_syslog(__METHOD__.' '.implode(',', $this->errors), LOG_ERR);
			return -1;
		}

		if (!empty($sortfield)) {
			$sql .= $this->db->order($sortfield, $sortorder);
		}
		if (!empty($limit)) {
			$sql .= $this->db->plimit($limit, $offset);
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < ($limit ? min($limit, $num) : $num)) {
				$obj = $this->db->fetch_object($resql);

				$record = new self($this->db);
				$record->setVarsFromFetchObj($obj);

				if (!empty($record->isextrafieldmanaged)) {
					$record->fetch_optionals();
				}

				$records[$record->id] = $record;

				$i++;
			}
			$this->db->free($resql);

			return $records;
		} else {
			$this->errors[] = 'Error '.$this->db->lasterror();
			dol_syslog(__METHOD__.' '.implode(',', $this->errors), LOG_ERR);

			return -1;
		}
	}

	/**
	 * Update object into database
	 *
	 * @param	User		$user		User that modifies
	 * @param	int<0,1>	$notrigger	0=launch triggers after, 1=disable triggers
	 * @return	int<-1,1>				Return integer <0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = 0)
	{
		if (empty($this->fk_soc) && !empty($this->socid)) {
			$this->fk_soc = (int) $this->socid;
		}
		if (!empty($this->fk_soc)) {
			$this->socid = (int) $this->fk_soc;
		}
		if (isset($this->connection_type)) {
			$this->connection_type = self::normalizeConnectionTypeValue($this->connection_type);
		}

		$result = $this->updateCommon($user, 1);
		if ($result > 0 && !$notrigger) {
			$triggerResult = $this->callPowerPlantTrigger('MODIFY', $user);
			if ($triggerResult < 0) {
				return -1;
			}
		}

		return $result;
	}

	/**
	 * Delete object in database
	 *
	 * @param	User		$user		User that deletes
	 * @param	int<0,1> 	$notrigger	0=launch triggers, 1=disable triggers
	 * @return	int<-1,1>				Return integer <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		if (!$notrigger) {
			$triggerResult = $this->callPowerPlantTrigger('DELETE', $user);
			if ($triggerResult < 0) {
				return -1;
			}
		}

		return $this->deleteCommon($user, 1);
		//return $this->deleteCommon($user, $notrigger, 1);
	}

	/**
	 *  Delete a line of object in database
	 *
	 *	@param	User		$user		User that delete
	 *  @param	int			$idline		Id of line to delete
	 *  @param	int<0,1>	$notrigger	0=launch triggers after, 1=disable triggers
	 *  @return	int<-2,1>				>0 if OK, <0 if KO
	 */
	public function deleteLine(User $user, $idline, $notrigger = 0)
	{
		if ($this->status < 0) {
			$this->error = 'ErrorDeleteLineNotAllowedByObjectStatus';
			return -2;
		}

		return $this->deleteLineCommon($user, $idline, $notrigger);
	}


	/**
	 *	Validate object
	 *
	 *	@param	User		$user		User making status change
	 *  @param	int<0,1>	$notrigger	1=Does not execute triggers, 0= execute triggers
	 *	@return	int<-1,1>				Return integer <=0 if OK, 0=Nothing done, >0 if KO
	 */
	public function validate($user, $notrigger = 0)
	{
		global $conf;

		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

		$error = 0;

		// Protection
		if ($this->status == self::STATUS_VALIDATED) {
			dol_syslog(get_class($this)."::validate action abandoned: already validated", LOG_WARNING);
			return 0;
		}

		/* if (! ((!getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && $user->hasRight('powerplantpv', 'powerplant', 'write'))
		 || (getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && $user->hasRight('powerplantpv', 'powerplant_advance', 'validate')))
		 {
		 $this->error='NotEnoughPermissions';
		 dol_syslog(get_class($this)."::valid ".$this->error, LOG_ERR);
		 return -1;
		 }*/

		$now = dol_now();

		$this->db->begin();

		// Define new ref
		if (preg_match('/^[\(]?PROV/i', $this->ref) || empty($this->ref)) { // empty should not happened, but when it occurs, the test save life
			$refResult = $this->assignFinalReference($user);
			if ($refResult < 0) {
				$this->db->rollback();
				return -1;
			}
			$num = (string) $this->ref;
		} else {
			$num = (string) $this->ref;
		}
		$this->newref = $num;

		if (!empty($num)) {
			// Validate
			$sql = "UPDATE ".$this->db->prefix().$this->table_element;
			$sql .= " SET ";
			if (!empty($this->fields['ref'])) {
				$sql .= " ref = '".$this->db->escape($num)."',";
			}
			$sql .= " status = ".self::STATUS_VALIDATED;
			if (!empty($this->fields['date_validation'])) {
				$sql .= ", date_validation = '".$this->db->idate($now)."'";
			}
			if (!empty($this->fields['fk_user_valid'])) {
				$sql .= ", fk_user_valid = ".((int) $user->id);
			}
			$sql .= " WHERE rowid = ".((int) $this->id);

			dol_syslog(get_class($this)."::validate()", LOG_DEBUG);
			$resql = $this->db->query($sql);
			if (!$resql) {
				dol_print_error($this->db);
				$this->error = $this->db->lasterror();
				$error++;
			}

			if (!$error && !$notrigger) {
				// Call trigger
				$result = $this->call_trigger($this->TRIGGER_PREFIX.'_VALIDATE', $user);
				if ($result < 0) {
					$error++;
				}
				// End call triggers
			}
		}

		if (!$error) {
			$this->oldref = $this->ref;

			// Rename directory if dir was a temporary ref
			if (preg_match('/^[\(]?PROV/i', $this->ref)) {
				// Now we rename also files into index
				$sql = 'UPDATE '.$this->db->prefix()."ecm_files set filename = CONCAT('".$this->db->escape($this->newref)."', SUBSTR(filename, ".(strlen($this->ref) + 1).")), filepath = 'powerplant/".$this->db->escape($this->newref)."'";
				$sql .= " WHERE filename LIKE '".$this->db->escape($this->ref)."%' AND filepath = 'powerplant/".$this->db->escape($this->ref)."' and entity = ".$conf->entity;
				$resql = $this->db->query($sql);
				if (!$resql) {
					$error++;
					$this->error = $this->db->lasterror();
				}
				$sql = 'UPDATE '.$this->db->prefix()."ecm_files set filepath = 'powerplant/".$this->db->escape($this->newref)."'";
				$sql .= " WHERE filepath = 'powerplant/".$this->db->escape($this->ref)."' and entity = ".$conf->entity;
				$resql = $this->db->query($sql);
				if (!$resql) {
					$error++;
					$this->error = $this->db->lasterror();
				}

				// We rename directory ($this->ref = old ref, $num = new ref) in order not to lose the attachments
				$oldref = dol_sanitizeFileName($this->ref);
				$newref = dol_sanitizeFileName($num);
				$dirsource = $conf->powerplantpv->dir_output.'/powerplant/'.$oldref;
				$dirdest = $conf->powerplantpv->dir_output.'/powerplant/'.$newref;
				if (!$error && file_exists($dirsource)) {
					dol_syslog(get_class($this)."::validate() rename dir ".$dirsource." into ".$dirdest);

					if (@rename($dirsource, $dirdest)) {
						dol_syslog("Rename ok");
						// Rename docs starting with $oldref with $newref
						$listoffiles = dol_dir_list($conf->powerplantpv->dir_output.'/powerplant/'.$newref, 'files', 1, '^'.preg_quote($oldref, '/'));
						foreach ($listoffiles as $fileentry) {
							$dirsource = $fileentry['name'];
							$dirdest = preg_replace('/^'.preg_quote($oldref, '/').'/', $newref, $dirsource);
							$dirsource = $fileentry['path'].'/'.$dirsource;
							$dirdest = $fileentry['path'].'/'.$dirdest;
							@rename($dirsource, $dirdest);
						}
					}
				}
			}
		}

		// Set new ref and current status
		if (!$error) {
			$this->ref = $num;
			$this->status = self::STATUS_VALIDATED;
		}

		if (!$error) {
			$this->db->commit();
			return 1;
		} else {
			$this->db->rollback();
			return -1;
		}
	}


	/**
	 *	Set draft status
	 *
	 *	@param	User		$user		Object user that modify
	 *  @param	int<0,1>	$notrigger	1=Does not execute triggers, 0=Execute triggers
	 *	@return	int<0,1>				Return integer <0 if KO, >0 if OK
	 */
	public function setDraft($user, $notrigger = 0)
	{
		// Protection
		if ($this->status <= self::STATUS_DRAFT) {
			return 0;
		}

		/* if (! ((!getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && $user->hasRight('powerplantpv','write'))
		 || (getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && $user->hasRight('powerplantpv','powerplantpv_advance','validate'))))
		 {
		 $this->error='Permission denied';
		 return -1;
		 }*/

		return $this->setStatusCommon($user, self::STATUS_DRAFT, $notrigger, 'POWERPLANTPV_POWERPLANT_UNVALIDATE');
	}

	/**
	 *	Set cancel status
	 *
	 *	@param	User		$user		Object user that modify
	 *  @param	int<0,1>	$notrigger	1=Does not execute triggers, 0=Execute triggers
	 *	@return	int<-1,1>				Return integer <0 if KO, 0=Nothing done, >0 if OK
	 */
	public function cancel($user, $notrigger = 0)
	{
		// Protection
		if ($this->status != self::STATUS_VALIDATED) {
			return 0;
		}

		/* if (! ((!getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && $user->hasRight('powerplantpv','write'))
		 || (getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && $user->hasRight('powerplantpv','powerplantpv_advance','validate'))))
		 {
		 $this->error='Permission denied';
		 return -1;
		 }*/

		return $this->setStatusCommon($user, self::STATUS_CANCELED, $notrigger, 'POWERPLANTPV_POWERPLANT_CANCEL');
	}

	/**
	 *	Set back to validated status
	 *
	 *	@param	User		$user			Object user that modify
	 *  @param	int<0,1>	$notrigger		1=Does not execute triggers, 0=Execute triggers
	 *	@return	int<-1,1>					Return integer <0 if KO, 0=Nothing done, >0 if OK
	 */
	public function reopen($user, $notrigger = 0)
	{
		// Protection
		if ($this->status == self::STATUS_VALIDATED) {
			return 0;
		}

		/*if (! ((!getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && $user->hasRight('powerplantpv','write'))
		 || (getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && $user->hasRight('powerplantpv','powerplantpv_advance','validate'))))
		 {
		 $this->error='Permission denied';
		 return -1;
		 }*/

		return $this->setStatusCommon($user, self::STATUS_VALIDATED, $notrigger, 'POWERPLANTPV_POWERPLANT_REOPEN');
	}

	/**
	 * Set power plant in service.
	 *
	 * @param	User		$user		Object user that modifies
	 * @param	int<0,1>	$notrigger	1=Does not execute triggers, 0=Execute triggers
	 * @return	int<-1,1>				Return integer <0 if KO, 0=Nothing done, >0 if OK
	 */
	public function setInService($user, $notrigger = 0)
	{
		if ($this->status == self::STATUS_IN_SERVICE) {
			return 0;
		}

		return $this->setStatusCommon($user, self::STATUS_IN_SERVICE, $notrigger, 'POWERPLANTPV_POWERPLANT_INSERVICE');
	}

	/**
	 * Set power plant out of service.
	 *
	 * @param	User		$user		Object user that modifies
	 * @param	int<0,1>	$notrigger	1=Does not execute triggers, 0=Execute triggers
	 * @return	int<-1,1>				Return integer <0 if KO, 0=Nothing done, >0 if OK
	 */
	public function setOutOfService($user, $notrigger = 0)
	{
		if ($this->status == self::STATUS_OUT_OF_SERVICE) {
			return 0;
		}

		return $this->setStatusCommon($user, self::STATUS_OUT_OF_SERVICE, $notrigger, 'POWERPLANTPV_POWERPLANT_OUTOFSERVICE');
	}

	/**
	 * getTooltipContentArray
	 *
	 * @param	array<string,string> 	$params 	Params to construct tooltip data
	 * @since 	v18
	 * @return	array{optimize?:string,picto?:string,ref?:string}
	 */
	public function getTooltipContentArray($params)
	{
		global $langs;

		$datas = [];

		if (getDolGlobalInt('MAIN_OPTIMIZEFORTEXTBROWSER')) {
			return ['optimize' => $langs->trans("ShowPowerPlant")];
		}
		$datas['picto'] = img_picto('', $this->picto).' <u>'.$langs->trans("PowerPlant").'</u>';
		if (isset($this->status)) {
			$datas['picto'] .= ' '.$this->getLibStatut(5);
		}
		if (property_exists($this, 'ref')) {
			$datas['ref'] = '<br><b>'.$langs->trans('Ref').':</b> '.$this->ref;
		}
		if (property_exists($this, 'label')) {
			$datas['ref'] = '<br>'.$langs->trans('Label').':</b> '.$this->label;
		}

		return $datas;
	}

	/**
	 *  Return a link to the object card (with optionally the picto)
	 *
	 *  @param	int     $withpicto                  Include picto in link (0=No picto, 1=Include picto into link, 2=Only picto)
	 *  @param	string  $option                     On what the link point to ('nolink', ...)
	 *  @param	int     $notooltip                  1=Disable tooltip
	 *  @param	string  $morecss                    Add more css on link
	 *  @param	int     $save_lastsearch_value      -1=Auto, 0=No save of lastsearch_values when clicking, 1=Save lastsearch_values whenclicking
	 *  @return	string                              String with URL
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		global $conf, $langs, $hookmanager;

		if (!empty($conf->dol_no_mouse_hover)) {
			$notooltip = 1; // Force disable tooltips
		}

		$result = '';
		$params = [
			'id' => (string) $this->id,
			'objecttype' => $this->element.($this->module ? '@'.$this->module : ''),
			'option' => $option,
		];
		$classfortooltip = 'classfortooltip';
		$dataparams = '';
		if (getDolGlobalInt('MAIN_ENABLE_AJAX_TOOLTIP')) {
			$classfortooltip = 'classforajaxtooltip';
			$dataparams = ' data-params="'.dol_escape_htmltag(json_encode($params)).'"';
			$label = '';
		} else {
			$label = implode($this->getTooltipContentArray($params));
		}

		$baseurl = dol_buildpath('/powerplantpv/powerplant_card.php', 1);
		$query = ['id' => $this->id];
		if ($option !== 'nolink') {
			// Add param to save lastsearch_values or not
			$add_save_lastsearch_values = ($save_lastsearch_value == 1 ? 1 : 0);
			if ($save_lastsearch_value == -1 && isset($_SERVER["PHP_SELF"]) && preg_match('/list\.php/', $_SERVER["PHP_SELF"])) {
				$add_save_lastsearch_values = 1;
			}
			if ($add_save_lastsearch_values) {
				$query = array_merge($query, ['save_lastsearch_values' => 1]);
			}
		}
		$url = dolBuildUrl($baseurl, $query);

		$linkclose = '';
		if (empty($notooltip)) {
			if (getDolGlobalInt('MAIN_OPTIMIZEFORTEXTBROWSER')) {
				$label = $langs->trans("ShowPowerPlant");
				$linkclose .= ' alt="'.dolPrintHTMLForAttribute($label).'"';
			}
			$linkclose .= ($label ? ' title="'.dolPrintHTMLForAttribute($label).'"' : ' title="tocomplete"');
			$linkclose .= $dataparams.' class="'.$classfortooltip.($morecss ? ' '.$morecss : '').'"';
		} else {
			$linkclose = ($morecss ? ' class="'.$morecss.'"' : '');
		}

		if ($option == 'nolink') {
			$linkstart = '<span';
		} else {
			$linkstart = '<a href="'.$url.'"';
		}
		$linkstart .= $linkclose.'>';
		if ($option == 'nolink') {
			$linkend = '</span>';
		} else {
			$linkend = '</a>';
		}

		$result .= $linkstart;

		$withpictorendered = ((int) $withpicto === 3 ? 0 : $withpicto);
		if (empty($this->showphoto_on_popup)) {
			if ($withpictorendered) {
				$result .= img_object(($notooltip ? '' : $label), ($this->picto ? $this->picto : 'generic'), (($withpicto != 2) ? 'class="paddingright"' : ''), 0, 0, $notooltip ? 0 : 1);
			}
		} else {
			if ($withpictorendered) {
				require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

				list($class, $module) = explode('@', $this->picto);
				$upload_dir = $conf->$module->multidir_output[$conf->entity]."/$class/".dol_sanitizeFileName($this->ref);
				$filearray = dol_dir_list($upload_dir, "files");
				$filename = $filearray[0]['name'];
				if (!empty($filename)) {
					$pospoint = strpos($filearray[0]['name'], '.');

					$pathtophoto = $class.'/'.$this->ref.'/thumbs/'.substr($filename, 0, $pospoint).'_mini'.substr($filename, $pospoint);
					if (!getDolGlobalString(strtoupper($module.'_'.$class).'_FORMATLISTPHOTOSASUSERS')) {
						$result .= '<div class="floatleft inline-block valignmiddle divphotoref"><div class="photoref"><img class="photo'.$module.'" alt="No photo" border="0" src="'.DOL_URL_ROOT.'/viewimage.php?modulepart='.$module.'&entity='.$conf->entity.'&file='.urlencode($pathtophoto).'"></div></div>';
					} else {
						$result .= '<div class="floatleft inline-block valignmiddle divphotoref"><img class="photouserphoto userphoto" alt="No photo" border="0" src="'.DOL_URL_ROOT.'/viewimage.php?modulepart='.$module.'&entity='.$conf->entity.'&file='.urlencode($pathtophoto).'"></div>';
					}

					$result .= '</div>';
				} else {
					$result .= img_object(($notooltip ? '' : $label), ($this->picto ? $this->picto : 'generic'), ($notooltip ? (($withpicto != 2) ? 'class="paddingright"' : '') : 'class="'.(($withpicto != 2) ? 'paddingright ' : '').'"'), 0, 0, $notooltip ? 0 : 1);
				}
			}
		}

		if ($withpictorendered != 2) {
			$displaytextparts = array();
			$displayref = isset($this->ref) ? trim((string) $this->ref) : '';
			$displaylabel = isset($this->label) ? trim((string) $this->label) : '';
			if ($displayref !== '') {
				$displaytextparts[] = $displayref;
			}
			if ((int) $withpicto === 3 && $displaylabel !== '') {
				$displaytextparts[] = $displaylabel;
			}
			$result .= dol_escape_htmltag(implode(' - ', $displaytextparts));
		}

		$result .= $linkend;
		//if ($withpicto != 2) $result.=(($addlabel && $this->label) ? $sep . dol_trunc($this->label, ($addlabel > 1 ? $addlabel : 0)) : '');

		global $action, $hookmanager;
		$hookmanager->initHooks(array($this->element.'dao'));
		$parameters = array('id' => $this->id, 'getnomurl' => &$result);
		$reshook = $hookmanager->executeHooks('getNomUrl', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
		if ($reshook > 0) {
			$result = $hookmanager->resPrint;
		} else {
			$result .= $hookmanager->resPrint;
		}

		return $result;
	}

	/**
	 *	Return a thumb for kanban views
	 *
	 *	@param	string	    			$option		Where point the link (0=> main card, 1,2 => shipment, 'nolink'=>No link)
	 *  @param	?array<string,mixed>	$arraydata	Array of data
	 *  @return	string								HTML Code for Kanban thumb.
	 */
	public function getKanbanView($option = '', $arraydata = null)
	{
		global $conf, $langs;

		$selected = (empty($arraydata['selected']) ? 0 : $arraydata['selected']);

		$return = '<div class="box-flex-item box-flex-grow-zero">';
		$return .= '<div class="info-box info-box-sm">';
		$return .= '<span class="info-box-icon bg-infobox-action">';
		$return .= img_picto('', $this->picto);
		$return .= '</span>';
		$return .= '<div class="info-box-content">';
		$return .= '<span class="info-box-ref inline-block tdoverflowmax150 valignmiddle">'.(method_exists($this, 'getNomUrl') ? $this->getNomUrl() : $this->ref).'</span>';
		if ($selected >= 0) {
			$return .= '<input id="cb'.$this->id.'" class="flat checkforselect fright" type="checkbox" name="toselect[]" value="'.$this->id.'"'.($selected ? ' checked="checked"' : '').'>';
		}
		if (property_exists($this, 'label')) {
			$return .= ' <div class="inline-block opacitymedium valignmiddle tdoverflowmax100">'.$this->label.'</div>';
		}
		if (property_exists($this, 'thirdparty') && is_object($this->thirdparty)) {
			$return .= '<br><div class="info-box-ref tdoverflowmax150">'.$this->thirdparty->getNomUrl(1).'</div>';
		}
		if (property_exists($this, 'amount')) {
			$return .= '<br>';
			$return .= '<span class="info-box-label amount">'.price($this->amount, 0, $langs, 1, -1, -1, $conf->currency).'</span>';
		}
		if (method_exists($this, 'getLibStatut')) {
			$return .= '<br><div class="info-box-status">'.$this->getLibStatut(3).'</div>';
		}
		$return .= '</div>';
		$return .= '</div>';
		$return .= '</div>';

		return $return;
	}

	/**
	 * Return linked native categories.
	 *
	 * @return	int[]|int	Array of category IDs or <0 if KO
	 */
	public function getCategories()
	{
		return $this->getCategoriesCommon('powerplant');
	}

	/**
	 * Set linked native categories.
	 *
	 * @param	int[]|int	$categories	Category ID or list of category IDs
	 * @return	int						Return integer <0 if KO, >0 if OK
	 */
	public function setCategories($categories)
	{
		return $this->setCategoriesCommon($categories, 'powerplant');
	}

	/**
	 *  Return the label of the status
	 *
	 *  @param	int<0,6>	$mode          0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 *  @return	string 			       Label of status
	 */
	public function getLabelStatus($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	/**
	 *  Return the label of the status
	 *
	 *  @param	int<0,6>	$mode	0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 *  @return	string				Label of status
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Return the label of a given status
	 *
	 *  @param	int			$status		Id status
	 *  @param	int<0,6>	$mode		0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 *  @return	string					Label of status
	 */
	public function LibStatut($status, $mode = 0)
	{
		// phpcs:enable
		if (is_null($status)) {
			return '';
		}

		$paramsBadge = array('badgeParams' => array('attr' => array(
			'data-status-element' => $this->element,
			'data-status' => (int) $status
		)));


		if (empty($this->labelStatus) || empty($this->labelStatusShort)) {
			global $langs;
			//$langs->load("powerplantpv@powerplantpv");
			$this->labelStatus[self::STATUS_DRAFT] = $langs->transnoentitiesnoconv('Draft');
			$this->labelStatus[self::STATUS_VALIDATED] = $langs->transnoentitiesnoconv('Validated');
			$this->labelStatus[self::STATUS_IN_SERVICE] = $langs->transnoentitiesnoconv('PowerPlantInService');
			$this->labelStatus[self::STATUS_OUT_OF_SERVICE] = $langs->transnoentitiesnoconv('PowerPlantOutOfService');
			$this->labelStatus[self::STATUS_CANCELED] = $langs->transnoentitiesnoconv('Canceled');
			$this->labelStatusShort[self::STATUS_DRAFT] = $langs->transnoentitiesnoconv('Draft');
			$this->labelStatusShort[self::STATUS_VALIDATED] = $langs->transnoentitiesnoconv('Validated');
			$this->labelStatusShort[self::STATUS_IN_SERVICE] = $langs->transnoentitiesnoconv('PowerPlantInServiceShort');
			$this->labelStatusShort[self::STATUS_OUT_OF_SERVICE] = $langs->transnoentitiesnoconv('PowerPlantOutOfServiceShort');
			$this->labelStatusShort[self::STATUS_CANCELED] = $langs->transnoentitiesnoconv('Canceled');
		}

		$statusType = 'status'.$status;
		if ($status == self::STATUS_IN_SERVICE) {
			$statusType = 'status4';
		}
		if ($status == self::STATUS_OUT_OF_SERVICE) {
			$statusType = 'status8';
		}
		if ($status == self::STATUS_CANCELED) {
			$statusType = 'status6';
		}

		return dolGetStatus($this->labelStatus[$status], $this->labelStatusShort[$status], '', $statusType, $mode, '', $paramsBadge);
	}

	/**
	 *	Load the info information in the object
	 *
	 *	@param	int		$id       Id of object
	 *	@return	void
	 */
	public function info($id)
	{
		$sql = "SELECT t.rowid, t.date_creation as datec";
		if (!empty($this->isextrafieldmanaged) && $this->isextrafieldmanaged == 1) {
			$sql .= ", GREATEST(t.tms, te.tms) as datem";
		} else {
			$sql .= ", t.tms as datem";
		}
		if (!empty($this->fields['date_validation'])) {
			$sql .= ", t.date_validation as datev";
		}
		if (!empty($this->fields['fk_user_creat'])) {
			$sql .= ", t.fk_user_creat";
		}
		if (!empty($this->fields['fk_user_modif'])) {
			$sql .= ", t.fk_user_modif";
		}
		if (!empty($this->fields['fk_user_valid'])) {
			$sql .= ", t.fk_user_valid";
		}
		$sql .= " FROM ".$this->db->prefix().$this->table_element." as t";
		if (!empty($this->isextrafieldmanaged) && $this->isextrafieldmanaged == 1) {
			$sql .= " LEFT JOIN ".$this->db->prefix().$this->table_element."_extrafields as te ON te.fk_object = t.rowid";
		}
		$sql .= " WHERE t.rowid = ".((int) $id);

		$result = $this->db->query($sql);
		if ($result) {
			if ($this->db->num_rows($result)) {
				$obj = $this->db->fetch_object($result);

				$this->id = $obj->rowid;

				if (!empty($this->fields['fk_user_creat'])) {
					$this->user_creation_id = $obj->fk_user_creat;
				}
				if (!empty($this->fields['fk_user_modif'])) {
					$this->user_modification_id = $obj->fk_user_modif;
				}
				if (!empty($this->fields['fk_user_valid'])) {
					$this->user_validation_id = $obj->fk_user_valid;
				}
				$this->date_creation = $this->db->jdate($obj->datec);
				$this->date_modification = empty($obj->datem) ? '' : $this->db->jdate($obj->datem);
				if (!empty($obj->datev)) {
					$this->date_validation = empty($obj->datev) ? '' : $this->db->jdate($obj->datev);
				}
			}

			$this->db->free($result);
		} else {
			dol_print_error($this->db);
		}
	}

	/**
	 * Validate a serial number import batch for this power plant.
	 *
	 * @param	PowerPlantPVSerialNumberImport	$import			Import batch
	 * @param	array<int,int>					$assignments	Manual composition line assignments
	 * @param	User							$user			User
	 * @return	array<string,mixed>|int							Result array, <0 on error
	 */
	public function validateSerialNumbersImport($import, $assignments, User $user)
	{
		dol_include_once('/powerplantpv/lib/powerplantpv_serialnumber.lib.php');

		return powerplantpvSerialImportValidateBatch($this, $import, $assignments, $user);
	}

	/**
	 * Cancel a serial number import batch.
	 *
	 * @param	PowerPlantPVSerialNumberImport	$import	Import batch
	 * @param	User							$user	User
	 * @return	int									1 if OK, <0 on error
	 */
	public function cancelSerialNumbersImport($import, User $user)
	{
		dol_include_once('/powerplantpv/lib/powerplantpv_serialnumber.lib.php');

		return powerplantpvSerialImportCancelBatch($import);
	}

	/**
	 * Delete one serial number and clear the linked composition line value.
	 *
	 * @param	int		$serialid	Serial number id
	 * @param	User	$user		User
	 * @return	int					1 if OK, 0 if not found, <0 on error
	 */
	public function deleteSerialNumber($serialid, User $user)
	{
		global $conf;

		$serialid = (int) $serialid;
		if ($serialid <= 0 || empty($this->id)) {
			return 0;
		}
		$entity = (!empty($this->entity) ? (int) $this->entity : (int) $conf->entity);

		$sql = "SELECT rowid, fk_powerplant_line FROM ".$this->db->prefix()."powerplantpv_serialnumber";
		$sql .= " WHERE rowid = ".$serialid;
		$sql .= " AND fk_powerplant = ".((int) $this->id);
		$sql .= " AND entity = ".$entity;
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		if (!$obj) {
			return 0;
		}

		$this->db->begin();
		$sqldelete = "DELETE FROM ".$this->db->prefix()."powerplantpv_serialnumber WHERE rowid = ".$serialid." AND entity = ".$entity;
		if (!$this->db->query($sqldelete)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		$sqlclear = "UPDATE ".$this->db->prefix()."powerplantpv_powerplantcomp";
		$sqlclear .= " SET serial_number = ''";
		$sqlclear .= " WHERE rowid = ".((int) $obj->fk_powerplant_line);
		$sqlclear .= " AND fk_powerplant = ".((int) $this->id);
		$sqlclear .= " AND entity = ".$entity;
		if (!$this->db->query($sqlclear)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();

		return 1;
	}

	/**
	 * Delete serial numbers by category for this power plant.
	 *
	 * @param	int		$categoryid	Category id
	 * @param	User	$user		User
	 * @return	int					Deleted count, <0 on error
	 */
	public function deleteSerialNumbersByCategory($categoryid, User $user)
	{
		dol_include_once('/powerplantpv/lib/powerplantpv_serialnumber.lib.php');

		return powerplantpvSerialNumberDeleteByFilter($this, array('fk_categorie' => (int) $categoryid));
	}

	/**
	 * Initialize object with example values
	 * Id must be 0 if object instance is a specimen
	 *
	 * @return	int
	 */
	public function initAsSpecimen()
	{
		// Set here init that are not commonf fields
		// $this->property1 = ...
		// $this->property2 = ...

		return $this->initAsSpecimenCommon();
	}

	/**
	 * 	Create an array of lines
	 *
	 * 	@return	CommonObjectLine[]|int		array of lines if OK, <0 if KO
	 */
	public function getLinesArray()
	{
		$this->lines = array();

		$objectline = new PowerPlantLine($this->db);
		$result = $objectline->fetchAll('ASC', 'position', 0, 0, '(fk_powerplant:=:'.((int) $this->id).')');

		if (is_numeric($result)) {
			$this->setErrorsFromObject($objectline);
			return $result;
		} else {
			$this->lines = $result;
			return $this->lines;
		}
	}

	/**
	 *  Returns the reference to the following non used object depending on the active numbering module.
	 *
	 *  @return	string      		Object free reference
	 */
	public function getNextNumRef()
	{
		global $langs, $conf;
		$langs->load("powerplantpv@powerplantpv");

		if (!getDolGlobalString('POWERPLANTPV_POWERPLANT_ADDON')) {
			$conf->global->POWERPLANTPV_POWERPLANT_ADDON = 'mod_powerplant_standard';
		}

		if (getDolGlobalString('POWERPLANTPV_POWERPLANT_ADDON')) {
			$mybool = false;

			$file = getDolGlobalString('POWERPLANTPV_POWERPLANT_ADDON').".php";
			$classname = getDolGlobalString('POWERPLANTPV_POWERPLANT_ADDON');

			// Include file with class
			$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);
			foreach ($dirmodels as $reldir) {
				$dir = dol_buildpath($reldir."core/modules/powerplantpv/");

				// Load file with numbering class (if found)
				$mybool = $mybool || @include_once $dir.$file;
			}

			if (!$mybool) {
				dol_print_error(null, "Failed to include file ".$file);
				return '';
			}

			if (class_exists($classname)) {
				$obj = new $classname();
				'@phan-var-force ModeleNumRefPowerPlant $obj';
				$numref = $obj->getNextValue($this);

				if ($numref != '' && $numref != '-1') {
					return $numref;
				} else {
					$this->error = $obj->error;
					//dol_print_error($this->db,get_class($this)."::getNextNumRef ".$obj->error);
					return "";
				}
			} else {
				print $langs->trans("Error")." ".$langs->trans("ClassNotFound").' '.$classname;
				return "";
			}
		} else {
			print $langs->trans("ErrorNumberingModuleNotSetup", $this->element);
			return "";
		}
	}

	/**
	 *  Create a document onto disk according to template module.
	 *
	 *  @param	string		$modele			Force template to use ('' to not force)
	 *  @param	Translate	$outputlangs	object lang a utiliser pour traduction
	 *  @param	int<0,1>	$hidedetails    Hide details of lines
	 *  @param	int<0,1>	$hidedesc       Hide description
	 *  @param	int<0,1>	$hideref        Hide ref
	 *  @param	?array<string,string>  $moreparams     Array to provide more information
	 *  @return	int         				0 if KO, 1 if OK
	 */
	public function generateDocument($modele, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0, $moreparams = null)
	{
		global $langs;

		$result = 0;
		$includedocgeneration = 1;

		$langs->load("powerplantpv@powerplantpv");

		if (!dol_strlen($modele)) {
			if (!empty($this->model_pdf)) {
				$modele = $this->model_pdf;
			} else {
				$modele = getDolGlobalString('POWERPLANTPV_POWERPLANT_ADDON_PDF', 'standard_powerplant');
			}
		}

		$modelpath = "core/modules/powerplantpv/doc/";

		if ($includedocgeneration && !empty($modele)) {
			$result = $this->commonGenerateDocument($modelpath, $modele, $outputlangs, $hidedetails, $hidedesc, $hideref, $moreparams);
		}

		return $result;
	}

	/**
	 * Return validation test result for a field.
	 * Need MAIN_ACTIVATE_VALIDATION_RESULT to be called.
	 *
	 * @param   array<string,array{type:string,label:string,enabled:int<0,2>|string,position:int,notnull?:int,visible:int<-2,5>|string,noteditable?:int<0,1>,default?:int<0,1>|string,index?:int,foreignkey?:string,searchall?:int<0,1>,isameasure?:int<0,1>,css?:string,csslist?:string,help?:string,showoncombobox?:int<0,2>,disabled?:int<0,1>,arrayofkeyval?:array<int|string,string>,comment?:string,validate?:int<0,1>}>  $fields Array of properties of field to show
	 * @param	string  $fieldKey            Key of attribute
	 * @param	string  $fieldValue          value of attribute
	 * @return	bool 						Return false if fail, true on success, set $this->error for error message
	 */
	public function validateField($fields, $fieldKey, $fieldValue)
	{
		// Add your own validation rules here.
		// ...

		return parent::validateField($fields, $fieldKey, $fieldValue);
	}

	/**
	 * Action executed by scheduler
	 * CAN BE A CRON TASK. In such a case, parameters come from the schedule job setup field 'Parameters'
	 * Use public function doScheduledJob($param1, $param2, ...) to get parameters
	 *
	 * @return	int			0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
	 */
	public function doScheduledJob()
	{
		//global $conf, $langs;

		//$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_mydedicatedlogfile.log';

		$error = 0;
		$this->output = '';
		$this->error = '';

		dol_syslog(__METHOD__." start", LOG_INFO);

		$now = dol_now();

		$this->db->begin();

		// ...

		$this->db->commit();

		dol_syslog(__METHOD__." end", LOG_INFO);

		return $error;
	}
}


require_once DOL_DOCUMENT_ROOT.'/core/class/commonobjectline.class.php';

/**
 * Class PowerPlantLine. You can also remove this and generate a CRUD class for lines objects.
 */
class PowerPlantLine extends CommonObjectLine
{
	// To complete with content of an object PowerPlantLine
	// We should have a field rowid, fk_powerplant and position

	/**
	 * To overload
	 * @see CommonObjectLine
	 */
	public $parent_element = '';		// Example: '' or 'powerplant'

	/**
	 * To overload
	 * @see CommonObjectLine
	 */
	public $fk_parent_attribute = '';	// Example: '' or 'fk_powerplant'

	/**
	 * @var int<0,1>	Does object support extrafields ? 0=No, 1=Yes
	 */
	public $isextrafieldmanaged = 0;

	/**
	 * @var int<0,1>|string|null  	Does this object support multicompany module ?
	 * 								0=No test on entity, 1=Test with field entity in local table, 'field@table'=Test entity into the field@table (example 'fk_soc@societe')
	 */
	public $ismultientitymanaged = 0;


	/**
	 * Constructor
	 *
	 * @param	DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}
}
