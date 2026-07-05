<?php
/* Copyright (C) 2004-2018	Laurent Destailleur			<eldy@users.sourceforge.net>
 * Copyright (C) 2018-2019	Nicolas ZABOURI				<info@inovea-conseil.com>
 * Copyright (C) 2019-2024	Frédéric France				<frederic.france@free.fr>
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
 * 	\defgroup   powerplantpv     Module PowerPlantPV
 *  \brief      PowerPlantPV module descriptor.
 *
 *  \file       htdocs/powerplantpv/core/modules/modPowerPlantPV.class.php
 *  \ingroup    powerplantpv
 *  \brief      Description and activation file for module PowerPlantPV
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';


/**
 *  Description and activation class for module PowerPlantPV
 */
class modPowerPlantPV extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs;

		$this->db = $db;

		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->numero = 450004; // TODO Go on page https://wiki.dolibarr.org/index.php/List_of_modules_id to reserve an id number for your module

		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'powerplantpv';

		// Family can be 'base' (core modules),'crm','financial','hr','projects','products','ecm','technic' (transverse modules),'interface' (link with external tools),'other','...'
		// It is used to group modules by family in module setup page
		$this->family = "Les Métiers du Bâtiment";

		// Module position in the family on 2 digits ('01', '10', '20', ...)
		$this->module_position = '90';

		// Gives the possibility for the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position. Avoid this)
		//$this->familyinfo = array('myownfamily' => array('position' => '01', 'label' => $langs->trans("MyOwnFamily")));
		// Module label (no space allowed), used if translation string 'ModulePowerPlantPVName' not found (PowerPlantPV is name of module).
		$this->name = preg_replace('/^mod/i', '', get_class($this));

		// DESCRIPTION_FLAG
		// Module description, used if translation string 'ModulePowerPlantPVDesc' not found (PowerPlantPV is name of module).
		$this->description = "ModulePowerPlantPVDesc";
		// Used only if file README.md and README-LL.md not found.
		$this->descriptionlong = "ModulePowerPlantPVDesc";

		// Author
		$this->editor_name = 'Les Métiers du Bâtiment';
		$this->editor_url = 'lesmetiersdubatiment.fr';		// Must be an external online web site
		$this->editor_squarred_logo = '';					// Must be image filename into the module/img directory followed with @modulename. Example: 'myimage.png@powerplantpv'

		// Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated', 'experimental_deprecated' or a version string like 'x.y.z'
		$this->version = '1.3.0';
		// Url to the file with your last numberversion of this module
		//$this->url_last_version = 'http://www.example.com/versionmodule.txt';

		// Key used in llx_const table to save module status enabled/disabled (where POWERPLANTPV is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
		// To use a supported fa-xxx css style of font awesome, use this->picto='xxx'
		$this->picto = 'fa-sun';

		// Define some features supported by module (triggers, login, substitutions, menus, css, etc...)
		$this->module_parts = array(
			// Set this to 1 if module has its own trigger directory (core/triggers)
			'triggers' => 1,
			// Set this to 1 if module has its own login method file (core/login)
			'login' => 0,
			// Set this to 1 if module has its own substitution function file (core/substitutions)
			'substitutions' => 1,
			// Set this to 1 if module has its own menus handler directory (core/menus)
			'menus' => 0,
			// Set this to 1 if module overwrite template dir (core/tpl)
			'tpl' => 1,
			// Set this to 1 if module has its own barcode directory (core/modules/barcode)
			'barcode' => 0,
			// Set this to 1 if module has its own models directory (core/modules/xxx)
			'models' => 1,
			// Set this to 1 if module has its own printing directory (core/modules/printing)
			'printing' => 0,
			// Set this to 1 if module has its own theme directory (theme)
			'theme' => 0,
			// Set this to relative path of css file if module has its own css file
			'css' => array(
				//    '/powerplantpv/css/powerplantpv.css.php',
			),
			// Set this to relative path of js file if module must load a js on all pages
			'js' => array(
				//   '/powerplantpv/js/powerplantpv.js.php',
			),
			// Set here all hooks context managed by module. To find available hook context, make a "grep -r '>initHooks(' *" on source code. You can also set hook context to 'all'
			/* BEGIN MODULEBUILDER HOOKSCONTEXTS */
			'hooks' => array(
				'data' => array(
					'commonobject',
					'ordercard',
					'propalcard',
					'invoicecard',
					'contractcard',
					'interventioncard',
					'fichintercard',
					'ticketcard',
					'publicnewticketcard',
					'category',
					'elementproperties',
					'usernavhistorydao',
					'notification',
					'multicompanyexternalmodulesharing',
					'multicompanyexternalmodules',
					'multicompanysharingoptions',
				),
				'entity' => '0',
			),
			/* END MODULEBUILDER HOOKSCONTEXTS */
			// Set this to 1 if features of module are opened to external users
			'moduleforexternal' => 0,
			// Set this to 1 if the module provides a website template into doctemplates/websites/website_template-mytemplate
			'websitetemplates' => 0,
			// Set this to 1 if the module provides a captcha driver
			'captcha' => 0
		);

		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/powerplantpv/temp","/powerplantpv/subdir");
		$this->dirs = array("/powerplantpv/temp");

		// Config pages. Put here list of php page, stored into powerplantpv/admin directory, to use to setup module.
		$this->config_page_url = array("setup.php@powerplantpv");

		// Dependencies
		// A condition to hide module
		$this->hidden = getDolGlobalInt('MODULE_POWERPLANTPV_DISABLED'); // A condition to disable module;
		// List of module class names that must be enabled if this module is enabled. Example: array('always'=>array('modModuleToEnable1','modModuleToEnable2'), 'FR'=>array('modModuleToEnableFR')...)
		$this->depends = array();
		// List of module class names to disable if this one is disabled. Example: array('modModuleToDisable1', ...)
		$this->requiredby = array();
		// List of module class names this module is in conflict with. Example: array('modModuleToDisable1', ...)
		$this->conflictwith = array();

		// The language file dedicated to your module
		$this->langfiles = array("powerplantpv@powerplantpv");

		// Prerequisites
		$this->phpmin = array(8, 0); // Minimum version of PHP required by module
		// $this->phpmax = array(8, 0); // Maximum version of PHP required by module
		$this->need_dolibarr_version = array(20, 0); // Minimum version of Dolibarr required by module
		// $this->max_dolibarr_version = array(19, -3); // Maximum version of Dolibarr required by module
		$this->need_javascript_ajax = 0;

		// Messages at activation
		$this->warnings_activation = array(); 		// Warning to show when we activate a module. Example: array('always'='text') or array('FR'='textfr','MX'='textmx'...)
		$this->warnings_activation_ext = array(); 	// Warning to show when we activate a module if another module is on. Example: array('modOtherModule' => array('always'=>'text')) or array('always' => array('FR'=>'textfr','MX'=>'textmx'...))
		//$this->automatic_activation = array('FR'=>'PowerPlantPVWasAutomaticallyActivatedBecauseOfYourCountryChoice');
		//$this->always_enabled = false;			// If true, can't be disabled. Value true is reserved for core modules. Not allowed for external modules.

		// Constants
		// List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
		// Example: $this->const=array(1 => array('POWERPLANTPV_MYNEWCONST1', 'chaine', 'myvalue', 'This is a constant to add', 1),
		//                             2 => array('POWERPLANTPV_MYNEWCONST2', 'chaine', 'myvalue', 'This is another constant to add', 0, 'current', 1)
		// );
		$this->const = array();
		foreach (array_merge($this->getPowerPlantActionTriggers(), $this->getAttestationActionTriggers()) as $trigger) {
			$this->const[] = array('MAIN_AGENDA_ACTIONAUTO_'.$trigger['code'], 'chaine', '1', $trigger['description'], 0, 'current');
		}
		$this->const[] = array('POWERPLANTPV_ATTESTATION_ENABLE', 'chaine', '1', 'Enable attestations', 0, 'current');
		$this->const[] = array('POWERPLANTPV_ATTESTATION_ADDON', 'chaine', 'mod_attestation_standard', 'Default attestation numbering model', 0, 'current');
		$this->const[] = array('POWERPLANTPV_ATTESTATION_MASK', 'chaine', 'ATT{yy}{mm}-{0000}', 'Default attestation numbering mask', 0, 'current');
		$this->const[] = array('POWERPLANTPV_ATTESTATION_DEFAULT_MAX_FREQUENCY_HZ', 'chaine', '51.5', 'Default attestation maximum frequency', 0, 'current');
		$this->const[] = array('POWERPLANTPV_ATTESTATION_DEFAULT_BRIDAGE_POWER', 'chaine', '', 'Default attestation curtailment power', 0, 'current');
		$this->const[] = array('POWERPLANTPV_ATTESTATION_ONLINE_SIGNATURE_SECURITY_TOKEN', 'chaine', '', 'Attestation online signature security seed', 0, 'current');
		$this->const[] = array('POWERPLANTPV_ATTESTATION_COMPANY_STAMP', 'chaine', 'setup/company_stamp.png', 'Default attestation company stamp', 0, 'current');
		$this->const[] = array('POWERPLANTPV_ATTESTATION_BRIDAGE_DYNAMIQUE_MODEL', 'chaine', 'attestation_bridage_dynamique', 'Default dynamic curtailment attestation PDF model', 0, 'current');
		$this->const[] = array('POWERPLANTPV_ATTESTATION_BRIDAGE_STATIQUE_MODEL', 'chaine', 'attestation_bridage_statique', 'Default static curtailment attestation PDF model', 0, 'current');
		$this->const[] = array('POWERPLANTPV_ATTESTATION_REGLAGE_FREQ_MODEL', 'chaine', 'attestation_reglage_max_freq', 'Default max frequency attestation PDF model', 0, 'current');
		$this->const[] = array('POWERPLANTPV_ATTESTATION_INSTALLATEUR_INF100KWC_MODEL', 'chaine', 'attestation_installateur_inf100kwc', 'Default installer under 100 kWc attestation PDF model', 0, 'current');
		$this->const[] = array('POWERPLANTPV_MAINTENANCE_ENABLE', 'chaine', '1', 'Enable maintenance foundation features', 0, 'current');
		$this->const[] = array('POWERPLANTPV_MAINTENANCE_DEFAULT_REPORT_TEMPLATE', 'chaine', 'preventive_maintenance', 'Default maintenance report template code', 0, 'current');
		$this->const[] = array('POWERPLANTPV_REPORT_PDF_LEGAL_NOTICE', 'chaine', '', 'Legal notice for dynamic intervention report PDFs', 0, 'current');

		// Some keys to add into the overwriting translation tables
		/*$this->overwrite_translation = array(
			'en_US:ParentCompany'=>'Parent company or reseller',
			'fr_FR:ParentCompany'=>'Maison mère ou revendeur'
		)*/

		if (!isModEnabled("powerplantpv")) {
			$conf->powerplantpv = new stdClass();
			$conf->powerplantpv->enabled = 0;
		}

		// Array to add new pages in new tabs
		/* BEGIN MODULEBUILDER TABS */
		// Don't forget to deactivate/reactivate your module to test your changes
		$this->tabs = array(
			'product:+pvpanel:PVPanelTabTitle:powerplantpv@powerplantpv:$user->hasRight(\'produit\', \'lire\'):/powerplantpv/product_detailedcaracteristics.php?id=__ID__',
			'intervention:+powerplantpv_report:PowerPlantPVReportTab:powerplantpv@powerplantpv:($user->hasRight(\'powerplantpv\', \'maintenance\', \'read\') || !empty($user->admin)):/powerplantpv/maintenance_intervention_report.php?id=__ID__'
		);
		/* END MODULEBUILDER TABS */
		// Example:
		// To add a new tab identified by code tabname1
		// $this->tabs[] = array('data' => 'objecttype:+tabname1:Title1:mylangfile@powerplantpv:$user->hasRight('powerplantpv', 'powerplant', 'read'):/powerplantpv/mynewtab1.php?id=__ID__');
		// To add another new tab identified by code tabname2. Label will be result of calling all substitution functions on 'Title2' key.
		// $this->tabs[] = array('data' => 'objecttype:+tabname2:SUBSTITUTION_Title2:mylangfile@powerplantpv:$user->hasRight('othermodule', 'otherobject', 'read'):/powerplantpv/mynewtab2.php?id=__ID__',
		// To remove an existing tab identified by code tabname
		// $this->tabs[] = array('data' => 'objecttype:-tabname:NU:conditiontoremove');
		//
		// Where objecttype can be
		// 'categories_x'	  to add a tab in category view (replace 'x' by type of category (0=product, 1=supplier, 2=customer, 3=member)
		// 'contact'          to add a tab in contact view
		// 'contract'         to add a tab in contract view
		// 'delivery'         to add a tab in delivery view
		// 'group'            to add a tab in group view
		// 'intervention'     to add a tab in intervention view
		// 'invoice'          to add a tab in customer invoice view
		// 'supplier_invoice' to add a tab in supplier invoice view
		// 'member'           to add a tab in foundation member view
		// 'opensurveypoll'	  to add a tab in opensurvey poll view
		// 'order'            to add a tab in sale order view
		// 'supplier_order'   to add a tab in supplier order view
		// 'payment'		  to add a tab in payment view
		// 'supplier_payment' to add a tab in supplier payment view
		// 'product'          to add a tab in product view
		// 'propal'           to add a tab in propal view
		// 'project'          to add a tab in project view
		// 'stock'            to add a tab in stock view
		// 'thirdparty'       to add a tab in third party view
		// 'user'             to add a tab in user view


		// Dictionaries
		/* Example:
		 $this->dictionaries=array(
		 'langs' => 'powerplantpv@powerplantpv',
		 // List of tables we want to see into dictionary editor
		 'tabname' => array("table1", "table2", "table3"),
		 // Label of tables
		 'tablib' => array("Table1", "Table2", "Table3"),
		 // Request to select fields
		 'tabsql' => array('SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.$this->db->prefix().'table1 as f', 'SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.$this->db->prefix().'table2 as f', 'SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.$this->db->prefix().'table3 as f'),
		 // Sort order
		 'tabsqlsort' => array("label ASC", "label ASC", "label ASC"),
		 // List of fields (result of select to show dictionary)
		 'tabfield' => array("code,label", "code,label", "code,label"),
		 // List of fields (list of fields to edit a record)
		 'tabfieldvalue' => array("code,label", "code,label", "code,label"),
		 // List of fields (list of fields for insert)
		 'tabfieldinsert' => array("code,label", "code,label", "code,label"),
		 // Name of columns with primary key (try to always name it 'rowid')
		 'tabrowid' => array("rowid", "rowid", "rowid"),
		 // Condition to show each dictionary
		 'tabcond' => array(isModEnabled('powerplantpv'), isModEnabled('powerplantpv'), isModEnabled('powerplantpv')),
		 // Tooltip for every fields of dictionaries: DO NOT PUT AN EMPTY ARRAY
		 'tabhelp' => array(array('code' => $langs->trans('CodeTooltipHelp'), 'field2' => 'field2tooltip'), array('code' => $langs->trans('CodeTooltipHelp'), 'field2' => 'field2tooltip'), ...),
		 );
		 */
		/* BEGIN MODULEBUILDER DICTIONARIES */
		$this->dictionaries = array(
			'langs' => 'powerplantpv@powerplantpv',
			'tabname' => array(
				'c_powerplantpv_categorypv',
				'c_powerplantpv_intervention_nature',
				'c_powerplantpv_maintenance_service',
				'c_powerplantpv_index_type',
			),
			'tablib' => array(
				'PhotovoltaicCategoryDictionary',
				'InterventionNatureDictionary',
				'MaintenanceServiceDictionary',
				'IndexTypeDictionary',
			),
			'tabsql' => array(
				'SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.$this->db->prefix().'c_powerplantpv_categorypv as f',
				'SELECT f.rowid as rowid, f.code, f.label, f.report_template_code, f.is_maintenance, f.is_preventive, f.requires_report, f.requires_signature, f.active, f.position FROM '.$this->db->prefix().'c_powerplantpv_intervention_nature as f WHERE f.entity = '.((int) $conf->entity),
				'SELECT f.rowid as rowid, f.code, f.label, f.description, f.active, f.position FROM '.$this->db->prefix().'c_powerplantpv_maintenance_service as f WHERE f.entity = '.((int) $conf->entity),
				'SELECT f.rowid as rowid, f.code, f.label, f.description, f.default_unit, f.active, f.position FROM '.$this->db->prefix().'c_powerplantpv_index_type as f WHERE f.entity = '.((int) $conf->entity),
			),
			'tabsqlsort' => array(
				'f.label ASC',
				'f.position ASC, f.label ASC',
				'f.position ASC, f.label ASC',
				'f.position ASC, f.label ASC',
			),
			'tabfield' => array(
				'code,label',
				'code,label,report_template_code,is_maintenance,is_preventive,requires_report,requires_signature,position',
				'code,label,description,position',
				'code,label,description,default_unit,position',
			),
			'tabfieldvalue' => array(
				'code,label',
				'code,label,report_template_code,is_maintenance,is_preventive,requires_report,requires_signature,position',
				'code,label,description,position',
				'code,label,description,default_unit,position',
			),
			'tabfieldinsert' => array(
				'code,label',
				'code,label,report_template_code,is_maintenance,is_preventive,requires_report,requires_signature,position',
				'code,label,description,position',
				'code,label,description,default_unit,position',
			),
			'tabrowid' => array('rowid', 'rowid', 'rowid', 'rowid'),
			'tabcond' => array(
				isModEnabled('powerplantpv'),
				isModEnabled('powerplantpv'),
				isModEnabled('powerplantpv'),
				isModEnabled('powerplantpv'),
			),
			'tabhelp' => array(
				array('code' => $langs->trans('CodeTooltipHelp')),
				array('code' => $langs->trans('CodeTooltipHelp')),
				array('code' => $langs->trans('CodeTooltipHelp')),
				array('code' => $langs->trans('CodeTooltipHelp')),
			),
		);
		/* END MODULEBUILDER DICTIONARIES */

		// Boxes/Widgets
		// Add here list of php file(s) stored in powerplantpv/core/boxes that contains a class to show a widget.
		/* BEGIN MODULEBUILDER WIDGETS */
		$this->boxes = array(
			array(
				'file' => 'powerplantpv_graph_installedpower_totalyear.php@powerplantpv',
				'note' => 'BoxPowerPlantPVInstalledPowerTotal',
				'enabledbydefaulton' => 'Home',
			),
			array(
				'file' => 'powerplantpv_graph_installedpower_monthly.php@powerplantpv',
				'note' => 'BoxPowerPlantPVInstalledPowerMonthly',
				'enabledbydefaulton' => 'Home',
			),
			array(
				'file' => 'powerplantpv_graph_installedpower_weekly.php@powerplantpv',
				'note' => 'BoxPowerPlantPVInstalledPowerWeekly',
				'enabledbydefaulton' => 'Home',
			),
		);
		/* END MODULEBUILDER WIDGETS */

		// Cronjobs (List of cron jobs entries to add when module is enabled)
		// unit_frequency must be 60 for minute, 3600 for hour, 86400 for day, 604800 for week
		/* BEGIN MODULEBUILDER CRON */
		$this->cronjobs = array(
			//  0 => array(
			//      'label' => 'MyJob label',
			//      'jobtype' => 'method',
			//      'class' => '/powerplantpv/class/powerplant.class.php',
			//      'objectname' => 'PowerPlant',
			//      'method' => 'doScheduledJob',
			//      'parameters' => '',
			//      'comment' => 'Comment',
			//      'frequency' => 2,
			//      'unitfrequency' => 3600,
			//      'status' => 0,
			//      'test' => 'isModEnabled("powerplantpv")',
			//      'priority' => 50,
			//  ),
		);
		/* END MODULEBUILDER CRON */
		// Example: $this->cronjobs=array(
		//    0=>array('label'=>'My label', 'jobtype'=>'method', 'class'=>'/dir/class/file.class.php', 'objectname'=>'MyClass', 'method'=>'myMethod', 'parameters'=>'param1, param2', 'comment'=>'Comment', 'frequency'=>2, 'unitfrequency'=>3600, 'status'=>0, 'test'=>'isModEnabled("powerplantpv")', 'priority'=>50),
		//    1=>array('label'=>'My label', 'jobtype'=>'command', 'command'=>'', 'parameters'=>'param1, param2', 'comment'=>'Comment', 'frequency'=>1, 'unitfrequency'=>3600*24, 'status'=>0, 'test'=>'isModEnabled("powerplantpv")', 'priority'=>50)
		// );

		// Permissions provided by this module
		$this->rights = array();
		$r = 0;
		// Add here entries to declare new permissions
		/* BEGIN MODULEBUILDER PERMISSIONS */
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PowerPlantPermissionRead';
		$this->rights[$r][4] = 'powerplant';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PowerPlantPermissionWrite';
		$this->rights[$r][4] = 'powerplant';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PowerPlantPermissionDelete';
		$this->rights[$r][4] = 'powerplant';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PowerPlantPermissionInService';
		$this->rights[$r][4] = 'powerplant';
		$this->rights[$r][5] = 'inservice';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PowerPlantPermissionOutOfService';
		$this->rights[$r][4] = 'powerplant';
		$this->rights[$r][5] = 'outofservice';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PowerPlantSerialNumberPermissionRead';
		$this->rights[$r][4] = 'serialnumber';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PowerPlantSerialNumberPermissionImport';
		$this->rights[$r][4] = 'serialnumber';
		$this->rights[$r][5] = 'import';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PowerPlantSerialNumberPermissionDelete';
		$this->rights[$r][4] = 'serialnumber';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PowerPlantSerialNumberPermissionExport';
		$this->rights[$r][4] = 'serialnumber';
		$this->rights[$r][5] = 'export';
		$r++; // Reserved historical permission offset 10.
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PowerPlantPVAttestationPermissionRead';
		$this->rights[$r][4] = 'attestation';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PowerPlantPVAttestationPermissionWrite';
		$this->rights[$r][4] = 'attestation';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PowerPlantPVAttestationPermissionDelete';
		$this->rights[$r][4] = 'attestation';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PowerPlantPVAttestationPermissionValidate';
		$this->rights[$r][4] = 'attestation';
		$this->rights[$r][5] = 'validate';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PowerPlantPVAttestationPermissionSign';
		$this->rights[$r][4] = 'attestation';
		$this->rights[$r][5] = 'sign';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PowerPlantPVAttestationPermissionCancel';
		$this->rights[$r][4] = 'attestation';
		$this->rights[$r][5] = 'cancel';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PowerPlantPVAttestationPermissionSetup';
		$this->rights[$r][4] = 'attestation';
		$this->rights[$r][5] = 'setup';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PowerPlantPVAttestationPermissionManageSigned';
		$this->rights[$r][4] = 'attestation';
		$this->rights[$r][5] = 'manage_signed';
		$r++; // Reserved historical permission offset 19.
		$r++; // Reserved historical permission offset 20.
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PowerPlantPVMaintenancePermissionRead';
		$this->rights[$r][4] = 'maintenance';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PowerPlantPVMaintenancePermissionWrite';
		$this->rights[$r][4] = 'maintenance';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PowerPlantPVMaintenancePermissionDelete';
		$this->rights[$r][4] = 'maintenance';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PowerPlantPVMaintenancePermissionReport';
		$this->rights[$r][4] = 'maintenance';
		$this->rights[$r][5] = 'report';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PowerPlantPVMaintenancePermissionConfig';
		$this->rights[$r][4] = 'maintenance';
		$this->rights[$r][5] = 'config';

		/* END MODULEBUILDER PERMISSIONS */


		// Main menu entries to add
		$this->menu = array();
		$r = 0;
		// Add here entries to declare new menus
		/* BEGIN MODULEBUILDER TOPMENU */
		$this->menu[$r++] = array(
			'fk_menu' => '', // Will be stored into mainmenu + leftmenu. Use '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'top', // This is a Top menu entry
			'titre' => 'ModulePowerPlantPVName',
			'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'powerplantpv',
			'leftmenu' => '',
			'url' => '/powerplantpv/powerplantpvindex.php',
			'langs' => 'powerplantpv@powerplantpv', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled' => "isModEnabled('powerplantpv')", // Define condition to show or hide menu entry. Use "isModEnabled('powerplantpv')" if entry must be visible if module is enabled (those quote marks are importants).
			'perms' => '1', // Use 'perms'=>'$user->hasRight("powerplantpv", "powerplant", "read")' if you want your menu with a permission rules
			'target' => '',
			'user' => 2, // 0=Menu for internal users, 1=external users, 2=both
		);
		/* END MODULEBUILDER TOPMENU */

		/* BEGIN MODULEBUILDER LEFTMENU POWERPLANT */
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=powerplantpv',
			'type' => 'left',
			'titre' => 'PowerPlant',
			'prefix' => img_picto('', $this->picto, 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'powerplantpv',
			'leftmenu' => 'powerplant',
			'url' => '/powerplantpv/powerplantpvindex.php',
			'langs' => 'powerplantpv@powerplantpv',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("powerplantpv")',
			'perms' => '$user->hasRight("powerplantpv", "powerplant", "read")',
			'target' => '',
			'user' => 2,
			'object' => 'PowerPlant'
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=powerplantpv,fk_leftmenu=powerplant',
			'type' => 'left',
			'titre' => 'New_PowerPlant',
			'mainmenu' => 'powerplantpv',
			'leftmenu' => 'powerplantpv_powerplant_new',
			'url' => '/powerplantpv/powerplant_card.php?action=create',
			'langs' => 'powerplantpv@powerplantpv',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("powerplantpv")',
			'perms' => '$user->hasRight("powerplantpv", "powerplant", "write")',
			'target' => '',
			'user' => 2,
			'object' => 'PowerPlant'
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=powerplantpv,fk_leftmenu=powerplant',
			'type' => 'left',
			'titre' => 'List_PowerPlant',
			'mainmenu' => 'powerplantpv',
			'leftmenu' => 'powerplantpv_powerplant_list',
			'url' => '/powerplantpv/powerplant_list.php',
			'langs' => 'powerplantpv@powerplantpv',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("powerplantpv")',
			'perms' => '$user->hasRight("powerplantpv", "powerplant", "read")',
			'target' => '',
			'user' => 2,
			'object' => 'PowerPlant'
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=powerplantpv',
			'type' => 'left',
			'titre' => 'Attestations',
			'prefix' => img_picto('', 'fa-file-signature', 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'powerplantpv',
			'leftmenu' => 'attestation',
			'url' => '/powerplantpv/attestation_list.php',
			'langs' => 'powerplantpv@powerplantpv',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("powerplantpv") && getDolGlobalInt("POWERPLANTPV_ATTESTATION_ENABLE", 1)',
			'perms' => '$user->hasRight("powerplantpv", "attestation", "read")',
			'target' => '',
			'user' => 2,
			'object' => 'PowerPlantPVAttestation'
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=powerplantpv,fk_leftmenu=attestation',
			'type' => 'left',
			'titre' => 'New_Attestation',
			'mainmenu' => 'powerplantpv',
			'leftmenu' => 'powerplantpv_attestation_new',
			'url' => '/powerplantpv/attestation_card.php?action=create',
			'langs' => 'powerplantpv@powerplantpv',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("powerplantpv") && getDolGlobalInt("POWERPLANTPV_ATTESTATION_ENABLE", 1)',
			'perms' => '$user->hasRight("powerplantpv", "attestation", "write")',
			'target' => '',
			'user' => 2,
			'object' => 'PowerPlantPVAttestation'
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=powerplantpv,fk_leftmenu=attestation',
			'type' => 'left',
			'titre' => 'List_Attestations',
			'mainmenu' => 'powerplantpv',
			'leftmenu' => 'powerplantpv_attestation_list',
			'url' => '/powerplantpv/attestation_list.php',
			'langs' => 'powerplantpv@powerplantpv',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("powerplantpv") && getDolGlobalInt("POWERPLANTPV_ATTESTATION_ENABLE", 1)',
			'perms' => '$user->hasRight("powerplantpv", "attestation", "read")',
			'target' => '',
			'user' => 2,
			'object' => 'PowerPlantPVAttestation'
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=powerplantpv',
			'type' => 'left',
			'titre' => 'Maintenance',
			'prefix' => img_picto('', 'fa-tools', 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'powerplantpv',
			'leftmenu' => 'powerplantpv_maintenance',
			'url' => '/powerplantpv/maintenance_list.php',
			'langs' => 'powerplantpv@powerplantpv',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("powerplantpv") && getDolGlobalInt("POWERPLANTPV_MAINTENANCE_ENABLE", 1)',
			'perms' => '(!empty($user->admin) || $user->hasRight("powerplantpv", "maintenance", "read"))',
			'target' => '',
			'user' => 2,
			'object' => 'PowerPlantPVMaintenance'
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=powerplantpv,fk_leftmenu=powerplantpv_maintenance',
			'type' => 'left',
			'titre' => 'NewMaintenanceIntervention',
			'mainmenu' => 'powerplantpv',
			'leftmenu' => 'powerplantpv_maintenance_new_intervention',
			'url' => '/powerplantpv/maintenance_intervention_card.php?action=create',
			'langs' => 'powerplantpv@powerplantpv',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("powerplantpv") && isModEnabled("ficheinter") && getDolGlobalInt("POWERPLANTPV_MAINTENANCE_ENABLE", 1)',
			'perms' => '(!empty($user->admin) || ($user->hasRight("powerplantpv", "maintenance", "write") && $user->hasRight("ficheinter", "creer")))',
			'target' => '',
			'user' => 2,
			'object' => 'PowerPlantPVMaintenance'
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=powerplantpv,fk_leftmenu=powerplantpv_maintenance',
			'type' => 'left',
			'titre' => 'ListMaintenances',
			'mainmenu' => 'powerplantpv',
			'leftmenu' => 'powerplantpv_maintenance_list',
			'url' => '/powerplantpv/maintenance_list.php',
			'langs' => 'powerplantpv@powerplantpv',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("powerplantpv") && getDolGlobalInt("POWERPLANTPV_MAINTENANCE_ENABLE", 1)',
			'perms' => '(!empty($user->admin) || $user->hasRight("powerplantpv", "maintenance", "read"))',
			'target' => '',
			'user' => 2,
			'object' => 'PowerPlantPVMaintenance'
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=powerplantpv,fk_leftmenu=powerplantpv_maintenance',
			'type' => 'left',
			'titre' => 'MaintenanceCalendar',
			'mainmenu' => 'powerplantpv',
			'leftmenu' => 'powerplantpv_maintenance_calendar',
			'url' => '/powerplantpv/maintenance_calendar.php',
			'langs' => 'powerplantpv@powerplantpv',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("powerplantpv") && getDolGlobalInt("POWERPLANTPV_MAINTENANCE_ENABLE", 1)',
			'perms' => '(!empty($user->admin) || $user->hasRight("powerplantpv", "maintenance", "read"))',
			'target' => '',
			'user' => 2,
			'object' => 'PowerPlantPVMaintenance'
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=powerplantpv,fk_leftmenu=powerplantpv_maintenance',
			'type' => 'left',
			'titre' => 'MaintenanceStatistics',
			'mainmenu' => 'powerplantpv',
			'leftmenu' => 'powerplantpv_maintenance_stats',
			'url' => '/powerplantpv/maintenance_stats.php',
			'langs' => 'powerplantpv@powerplantpv',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("powerplantpv") && getDolGlobalInt("POWERPLANTPV_MAINTENANCE_ENABLE", 1)',
			'perms' => '(!empty($user->admin) || $user->hasRight("powerplantpv", "maintenance", "read"))',
			'target' => '',
			'user' => 2,
			'object' => 'PowerPlantPVMaintenance'
		);
		/* END MODULEBUILDER LEFTMENU POWERPLANT */
		/* BEGIN MODULEBUILDER LEFTMENU MYOBJECT */
		/*
		$this->menu[$r++]=array(
			'fk_menu' => 'fk_mainmenu=powerplantpv',      // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                          // This is a Left menu entry
			'titre' => 'PowerPlant',
			'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle paddingright"'),
			'mainmenu' => 'powerplantpv',
			'leftmenu' => 'powerplant',
			'url' => '/powerplantpv/powerplantpvindex.php',
			'langs' => 'powerplantpv@powerplantpv',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled' => "isModEnabled('powerplantpv')", // Define condition to show or hide menu entry. Use isModEnabled("powerplantpv") if entry must be visible if module is enabled.
			'perms' => '$user->hasRight("powerplantpv", "powerplant", "read")',
			'target' => '',
			'user' => 2,				                // 0=Menu for internal users, 1=external users, 2=both
			'object' => 'PowerPlant'
		);
		$this->menu[$r++]=array(
			'fk_menu' => 'fk_mainmenu=powerplantpv,fk_leftmenu=powerplant',	    // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',			                // This is a Left menu entry
			'titre' => 'New_PowerPlant',
			'mainmenu' => 'powerplantpv',
			'leftmenu' => 'powerplantpv_powerplant_new',
			'url' => '/powerplantpv/powerplant_card.php?action=create',
			'langs' => 'powerplantpv@powerplantpv',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled' => "isModEnabled('powerplantpv')", // Define condition to show or hide menu entry. Use isModEnabled("powerplantpv") if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '$user->hasRight("powerplantpv", "powerplant", "write")'
			'target' => '',
			'user' => 2,				                // 0=Menu for internal users, 1=external users, 2=both
			'object' => 'PowerPlant'
		);
		$this->menu[$r++]=array(
			'fk_menu' => 'fk_mainmenu=powerplantpv,fk_leftmenu=powerplant',	    // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',			                // This is a Left menu entry
			'titre' => 'List_PowerPlant',
			'mainmenu' => 'powerplantpv',
			'leftmenu' => 'powerplantpv_powerplant_list',
			'url' => '/powerplantpv/powerplant_list.php',
			'langs' => 'powerplantpv@powerplantpv',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled' => "isModEnabled('powerplantpv')", // Define condition to show or hide menu entry. Use isModEnabled("powerplantpv") if entry must be visible if module is enabled.
			'perms' => '$user->hasRight("powerplantpv", "powerplant", "read")'
			'target' => '',
			'user' => 2,				                // 0=Menu for internal users, 1=external users, 2=both
			'object' => 'PowerPlant'
		);
		*/
		/* END MODULEBUILDER LEFTMENU MYOBJECT */


		// Exports profiles provided by this module
		$r = 0;
		/* BEGIN MODULEBUILDER EXPORT MYOBJECT */
		/*
		$langs->load("powerplantpv@powerplantpv");
		$this->export_code[$r] = $this->rights_class.'_'.$r;
		$this->export_label[$r] = 'PowerPlantLines';	// Translation key (used only if key ExportDataset_xxx_z not found)
		$this->export_icon[$r] = $this->picto;
		// Define $this->export_fields_array, $this->export_TypeFields_array and $this->export_entities_array
		$keyforclass = 'PowerPlant'; $keyforclassfile='/powerplantpv/class/powerplant.class.php'; $keyforelement='powerplant@powerplantpv';
		include DOL_DOCUMENT_ROOT.'/core/commonfieldsinexport.inc.php';
		//$this->export_fields_array[$r]['t.fieldtoadd']='FieldToAdd'; $this->export_TypeFields_array[$r]['t.fieldtoadd']='Text';
		//unset($this->export_fields_array[$r]['t.fieldtoremove']);
		//$keyforclass = 'PowerPlantLine'; $keyforclassfile='/powerplantpv/class/powerplant.class.php'; $keyforelement='powerplantline@powerplantpv'; $keyforalias='tl';
		//include DOL_DOCUMENT_ROOT.'/core/commonfieldsinexport.inc.php';
		$keyforselect='powerplant'; $keyforaliasextra='extra'; $keyforelement='powerplant@powerplantpv';
		include DOL_DOCUMENT_ROOT.'/core/extrafieldsinexport.inc.php';
		//$keyforselect='powerplantline'; $keyforaliasextra='extraline'; $keyforelement='powerplantline@powerplantpv';
		//include DOL_DOCUMENT_ROOT.'/core/extrafieldsinexport.inc.php';
		//$this->export_dependencies_array[$r] = array('powerplantline' => array('tl.rowid','tl.ref')); // To force to activate one or several fields if we select some fields that need same (like to select a unique key if we ask a field of a child to avoid the DISTINCT to discard them, or for computed field than need several other fields)
		//$this->export_special_array[$r] = array('t.field' => '...');
		//$this->export_examplevalues_array[$r] = array('t.field' => 'Example');
		//$this->export_help_array[$r] = array('t.field' => 'FieldDescHelp');
		$this->export_sql_start[$r]='SELECT DISTINCT ';
		$this->export_sql_end[$r]  =' FROM '.$this->db->prefix().'powerplantpv_powerplant as t';
		//$this->export_sql_end[$r]  .=' LEFT JOIN '.$this->db->prefix().'powerplantpv_powerplant_line as tl ON tl.fk_powerplant = t.rowid';
		$this->export_sql_end[$r] .=' WHERE 1 = 1';
		$this->export_sql_end[$r] .=' AND t.entity IN ('.getEntity('powerplant').')';
		$r++; */
		/* END MODULEBUILDER EXPORT MYOBJECT */

		// Imports profiles provided by this module
		$r = 0;
		/* BEGIN MODULEBUILDER IMPORT MYOBJECT */
		/*
		$langs->load("powerplantpv@powerplantpv");
		$this->import_code[$r] = $this->rights_class.'_'.$r;
		$this->import_label[$r] = 'PowerPlantLines';	// Translation key (used only if key ExportDataset_xxx_z not found)
		$this->import_icon[$r] = $this->picto;
		$this->import_tables_array[$r] = array('t' => $this->db->prefix().'powerplantpv_powerplant', 'extra' => $this->db->prefix().'powerplantpv_powerplant_extrafields');
		$this->import_tables_creator_array[$r] = array('t' => 'fk_user_author'); // Fields to store import user id
		$import_sample = array();
		$keyforclass = 'PowerPlant'; $keyforclassfile='/powerplantpv/class/powerplant.class.php'; $keyforelement='powerplant@powerplantpv';
		include DOL_DOCUMENT_ROOT.'/core/commonfieldsinimport.inc.php';
		$import_extrafield_sample = array();
		$keyforselect='powerplant'; $keyforaliasextra='extra'; $keyforelement='powerplant@powerplantpv';
		include DOL_DOCUMENT_ROOT.'/core/extrafieldsinimport.inc.php';
		$this->import_fieldshidden_array[$r] = array('extra.fk_object' => 'lastrowid-'.$this->db->prefix().'powerplantpv_powerplant');
		$this->import_regex_array[$r] = array();
		$this->import_examplevalues_array[$r] = array_merge($import_sample, $import_extrafield_sample);
		$this->import_updatekeys_array[$r] = array('t.ref' => 'Ref');
		$this->import_convertvalue_array[$r] = array(
			't.ref' => array(
				'rule'=>'getrefifauto',
				'class'=>(!getDolGlobalString('POWERPLANTPV_MYOBJECT_ADDON') ? 'mod_powerplant_standard' : getDolGlobalString('POWERPLANTPV_MYOBJECT_ADDON')),
				'path'=>"/core/modules/powerplantpv/".(!getDolGlobalString('POWERPLANTPV_MYOBJECT_ADDON') ? 'mod_powerplant_standard' : getDolGlobalString('POWERPLANTPV_MYOBJECT_ADDON')).'.php',
				'classobject'=>'PowerPlant',
				'pathobject'=>'/powerplantpv/class/powerplant.class.php',
			),
			't.fk_soc' => array('rule' => 'fetchidfromref', 'file' => '/societe/class/societe.class.php', 'class' => 'Societe', 'method' => 'fetch', 'element' => 'ThirdParty'),
			't.fk_user_valid' => array('rule' => 'fetchidfromref', 'file' => '/user/class/user.class.php', 'class' => 'User', 'method' => 'fetch', 'element' => 'user'),
			't.fk_mode_reglement' => array('rule' => 'fetchidfromcodeorlabel', 'file' => '/compta/paiement/class/cpaiement.class.php', 'class' => 'Cpaiement', 'method' => 'fetch', 'element' => 'cpayment'),
		);
		$this->import_run_sql_after_array[$r] = array();
		$r++; */
		/* END MODULEBUILDER IMPORT MYOBJECT */
	}

	/**
	 *  Function called when module is enabled.
	 *  The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *  It also creates data directories
	 *
	 *  @param      string  $options    Options when enabling module ('', 'noboxes')
	 *  @return     int<-1,1>          	1 if OK, <=0 if KO
	 */
	public function init($options = '')
	{
		global $conf, $langs;

		// Create tables of module at module activation
		//$result = $this->_load_tables('/install/mysql/', 'powerplantpv');
		$result = $this->_load_tables('/powerplantpv/sql/');
		if ($result < 0) {
			return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')
		}

		$result = $this->ensurePowerPlantSchema();
		if ($result < 0) {
			return -1;
		}
		$result = $this->ensureAttestationSchema();
		if ($result < 0) {
			return -1;
		}
		$result = $this->ensureReportTemplateEngineSchema();
		if ($result < 0) {
			return -1;
		}
		$result = $this->ensureGeneratedReportSchema();
		if ($result < 0) {
			return -1;
		}
		$result = $this->ensureIndexReadingSchema();
		if ($result < 0) {
			return -1;
		}

		// Create product extrafield for photovoltaic category.
		include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
		$extrafields = new ExtraFields($this->db);
		$extrafields->fetch_name_optionals_label('product');
		if (empty($extrafields->attributes['product']['label']['categorie_photovoltaique'])) {
			$categorySellist = array(
				'options' => array('c_powerplantpv_categorypv:label:rowid::(active:=:1)' => null)
			);
			$result = $extrafields->addExtraField(
				'categorie_photovoltaique',
				'ProductPhotovoltaicCategory',
				'sellist',
				200,
				'',
				'product',
				0,
				0,
				'',
				$categorySellist,
				1,
				'',
				-1,
				'',
				'',
				'',
				'powerplantpv@powerplantpv',
				'isModEnabled("powerplantpv")'
			);
			if ($result < 0) {
				$this->errors[] = $extrafields->error;
				return -1;
			}
		}
		foreach (array(
			'product_photovoltaic_brand' => array('label' => 'ProductPhotovoltaicBrand', 'position' => 201),
			'product_photovoltaic_manufacturer' => array('label' => 'ProductPhotovoltaicManufacturer', 'position' => 202),
		) as $attrname => $extrafieldinfo) {
			$result = $this->ensureProductPhotovoltaicTextExtrafield($extrafields, $attrname, $extrafieldinfo['label'], $extrafieldinfo['position']);
			if ($result < 0) {
				return -1;
			}
		}

		$result = $this->ensureTicketPowerPlantExtrafield($extrafields);
		if ($result < 0) {
			return -1;
		}

		// Create commercial document extrafields storing the calculated total peak power.
		foreach (array('propal', 'commande', 'facture') as $commercialElementType) {
			$result = $this->ensureCommercialPeakPowerExtrafield($extrafields, $commercialElementType);
			if ($result < 0) {
				return -1;
			}
		}
		$result = $this->ensureMaintenanceFoundationExtrafields($extrafields);
		if ($result < 0) {
			return -1;
		}

		// Seed photovoltaic category dictionary.
		$categoryRows = array(
			'MODULE' => 'Module photovoltaïque',
			'ONDULE' => 'Onduleur',
			'ONDACC' => 'Accessoire Onduleur',
			'DATLOG' => 'Datalogger',
			'OPTMIZ' => 'Optimiseur',
			'COFFAC' => 'Coffret AC',
			'COFFDC' => 'Coffret DC',
			'SYSINT' => 'Système d\'intégration',
		);
		foreach ($categoryRows as $code => $label) {
			$sql = "INSERT INTO ".$this->db->prefix()."c_powerplantpv_categorypv(code, label, active)";
			$sql .= " SELECT '".$this->db->escape($code)."', '".$this->db->escape($label)."', 1";
			$sql .= " WHERE NOT EXISTS (SELECT 1 FROM ".$this->db->prefix()."c_powerplantpv_categorypv WHERE code = '".$this->db->escape($code)."')";
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->errors[] = $this->db->lasterror();
				return -1;
			}

			$sql = "UPDATE ".$this->db->prefix()."c_powerplantpv_categorypv";
			$sql .= " SET label = '".$this->db->escape($label)."', active = 1";
			$sql .= " WHERE code = '".$this->db->escape($code)."'";
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->errors[] = $this->db->lasterror();
				return -1;
			}
		}
		$result = $this->seedMaintenanceFoundationData();
		if ($result < 0) {
			return -1;
		}

		// Permissions
		$this->remove($options);

		$sql = array();

		// Migrate legacy single project links to native linked objects.
		$sqlmigrateproject = "INSERT INTO ".$this->db->prefix()."element_element (fk_source, sourcetype, fk_target, targettype)";
		$sqlmigrateproject .= " SELECT p.fk_project, 'project', p.rowid, 'powerplant@powerplantpv'";
		$sqlmigrateproject .= " FROM ".$this->db->prefix()."powerplantpv_powerplant as p";
		$sqlmigrateproject .= " WHERE p.fk_project IS NOT NULL AND p.fk_project > 0";
		$sqlmigrateproject .= " AND NOT EXISTS (";
		$sqlmigrateproject .= " SELECT 1 FROM ".$this->db->prefix()."element_element as ee";
		$sqlmigrateproject .= " WHERE ee.fk_source = p.fk_project";
		$sqlmigrateproject .= " AND ee.sourcetype = 'project'";
		$sqlmigrateproject .= " AND ee.fk_target = p.rowid";
		$sqlmigrateproject .= " AND ee.targettype = 'powerplant@powerplantpv'";
		$sqlmigrateproject .= ")";
		$sql[] = $sqlmigrateproject;

		// Ensure PV product natures are present in dictionary
		$natureTable = $this->db->prefix()."c_product_nature";
		$pvNatures = array(
			array('code' => '50', 'labelkey' => 'ProductNaturePVModules'),
			array('code' => '51', 'labelkey' => 'ProductNaturePVInverters'),
			array('code' => '52', 'labelkey' => 'ProductNaturePVIntegration'),
			array('code' => '53', 'labelkey' => 'ProductNaturePVMonitoring'),
			array('code' => '54', 'labelkey' => 'ProductNaturePVACBox'),
			array('code' => '55', 'labelkey' => 'ProductNaturePVDCBox'),
		);

		foreach ($pvNatures as $nature) {
			$sql[] = "UPDATE ".$natureTable." SET label = '".$this->db->escape($langs->transnoentitiesnoconv($nature['labelkey']))."', active = 1 WHERE code = '".$this->db->escape($nature['code'])."'";
			$sql[] = "INSERT INTO ".$natureTable." (code, label, active) SELECT '".$this->db->escape($nature['code'])."', '".$this->db->escape($langs->transnoentitiesnoconv($nature['labelkey']))."', 1 WHERE NOT EXISTS (SELECT 1 FROM ".$natureTable." WHERE code = '".$this->db->escape($nature['code'])."')";
		}

		$sql = array_merge($sql, $this->getPowerPlantContactTypeSql());

		// Document templates
		$moduledir = dol_sanitizeFileName('powerplantpv');
		$myTmpObjects = array();
		$myTmpObjects['PowerPlant'] = array('includerefgeneration' => 0, 'includedocgeneration' => 1);

		foreach ($myTmpObjects as $myTmpObjectKey => $myTmpObjectArray) {
			if ($myTmpObjectArray['includedocgeneration']) {
				$src = DOL_DOCUMENT_ROOT.'/install/doctemplates/'.$moduledir.'/template_powerplants.odt';
				$dirodt = DOL_DATA_ROOT.($conf->entity > 1 ? '/'.$conf->entity : '').'/doctemplates/'.$moduledir;
				$dest = $dirodt.'/template_powerplants.odt';

				if (file_exists($src) && !file_exists($dest)) {
					require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
					dol_mkdir($dirodt);
					$result = dol_copy($src, $dest, '0', 0);
					if ($result < 0) {
						$langs->load("errors");
						$this->error = $langs->trans('ErrorFailToCopyFile', $src, $dest);
						return 0;
					}
				}

				$documentModelType = $this->db->escape(strtolower($myTmpObjectKey));
				foreach (array(
					'standard_'.strtolower($myTmpObjectKey),
					'centralepv_'.strtolower($myTmpObjectKey),
					'generic_'.strtolower($myTmpObjectKey).'_odt',
				) as $documentModelName) {
					$documentModelNameSql = $this->db->escape($documentModelName);
					$sqlmodel = "INSERT INTO ".$this->db->prefix()."document_model (nom, type, entity)";
					$sqlmodel .= " SELECT '".$documentModelNameSql."', '".$documentModelType."', ".((int) $conf->entity);
					$sqlmodel .= " WHERE NOT EXISTS (";
					$sqlmodel .= " SELECT 1 FROM ".$this->db->prefix()."document_model";
					$sqlmodel .= " WHERE nom = '".$documentModelNameSql."'";
					$sqlmodel .= " AND type = '".$documentModelType."'";
					$sqlmodel .= " AND entity = ".((int) $conf->entity);
					$sqlmodel .= ")";
					$sql[] = $sqlmodel;
				}
			}
		}

		$attestationModels = array(
			'attestation_bridage_dynamique',
			'attestation_bridage_statique',
			'attestation_reglage_max_freq',
			'attestation_installateur_inf100kwc',
		);
		foreach ($attestationModels as $attestationModel) {
			$attestationModelSql = $this->db->escape($attestationModel);
			$sqlmodel = "INSERT INTO ".$this->db->prefix()."document_model (nom, type, entity)";
			$sqlmodel .= " SELECT '".$attestationModelSql."', 'attestation', ".((int) $conf->entity);
			$sqlmodel .= " WHERE NOT EXISTS (";
			$sqlmodel .= " SELECT 1 FROM ".$this->db->prefix()."document_model";
			$sqlmodel .= " WHERE nom = '".$attestationModelSql."'";
			$sqlmodel .= " AND type = 'attestation'";
			$sqlmodel .= " AND entity = ".((int) $conf->entity);
			$sqlmodel .= ")";
			$sql[] = $sqlmodel;
		}

		$sqlreportmodel = "INSERT INTO ".$this->db->prefix()."document_model (nom, type, entity)";
		$sqlreportmodel .= " SELECT 'powerplantpvreport', 'ficheinter', ".((int) $conf->entity);
		$sqlreportmodel .= " WHERE NOT EXISTS (";
		$sqlreportmodel .= " SELECT 1 FROM ".$this->db->prefix()."document_model";
		$sqlreportmodel .= " WHERE nom = 'powerplantpvreport'";
		$sqlreportmodel .= " AND type = 'ficheinter'";
		$sqlreportmodel .= " AND entity = ".((int) $conf->entity);
		$sqlreportmodel .= ")";
		$sql[] = $sqlreportmodel;

		// Migrate legacy agenda links to the canonical Dolibarr element type used by this module.
		$sqlmigrateagenda = "UPDATE ".$this->db->prefix()."actioncomm";
		$sqlmigrateagenda .= " SET elementtype = 'powerplant@powerplantpv'";
		$sqlmigrateagenda .= " WHERE elementtype IN ('powerplant', 'powerplantpv_powerplant')";
		$sqlmigrateagenda .= " AND fk_element IN (SELECT p.rowid FROM ".$this->db->prefix()."powerplantpv_powerplant as p)";
		$sql[] = $sqlmigrateagenda;

		$sql = array_merge($sql, $this->getPowerPlantActionTriggerSql());
		$sql = array_merge($sql, $this->getAttestationActionTriggerSql());
		$sql = array_merge($sql, $this->getAttestationAgendaDuplicateCleanupSql());

		$result = $this->_init($sql, $options);
		if ($result <= 0) {
			return $result;
		}

		$result = $this->registerMulticompanyExternalSharing();
		if ($result < 0) {
			return -1;
		}

		return $result;
	}

	/**
	 * Ensure columns added after the initial table creation exist on upgrades.
	 *
	 * _load_tables() creates missing tables but does not reliably replay ALTER statements
	 * on an already installed module. Keep this guard before any object fetch.
	 *
	 * @return	int		1 if OK, <0 if KO
	 */
	private function ensurePowerPlantSchema()
	{
		$table = $this->db->prefix().'powerplantpv_powerplant';
		$fields = array(
			'access_instructions' => array(
				'type' => 'text',
				'value' => '',
				'null' => '',
			),
			'fk_soc' => array(
				'type' => 'integer',
				'value' => '',
				'null' => '',
			),
			'fk_project' => array(
				'type' => 'integer',
				'value' => '',
				'null' => '',
			),
		);

		foreach ($fields as $field => $fielddesc) {
			$sql = "SHOW COLUMNS FROM ".$this->db->sanitize($table)." LIKE '".$this->db->escape($field)."'";
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->errors[] = $this->db->lasterror();
				return -1;
			}

			$fieldexists = ($this->db->num_rows($resql) > 0);
			$this->db->free($resql);
			if ($fieldexists) {
				continue;
			}

			$result = $this->db->DDLAddField($table, $field, $fielddesc);
			if ($result < 0) {
				$this->errors[] = $this->db->lasterror();
				return -1;
			}
		}

		$indexes = array(
			'idx_powerplantpv_powerplant_fk_soc' => 'fk_soc',
			'idx_powerplantpv_powerplant_fk_project' => 'fk_project',
		);
		foreach ($indexes as $indexname => $fieldname) {
			$sql = "SHOW INDEX FROM ".$this->db->sanitize($table)." WHERE Key_name = '".$this->db->escape($indexname)."'";
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->errors[] = $this->db->lasterror();
				return -1;
			}

			$indexexists = ($this->db->num_rows($resql) > 0);
			$this->db->free($resql);
			if ($indexexists) {
				continue;
			}

			$sql = "ALTER TABLE ".$this->db->sanitize($table)." ADD INDEX ".$this->db->sanitize($indexname)." (".$this->db->sanitize($fieldname).")";
			if (!$this->db->query($sql)) {
				$this->errors[] = $this->db->lasterror();
				return -1;
			}
		}

		$datasourcetable = $this->db->prefix().'powerplantpv_product_datasource';
		$sql = "SHOW TABLES LIKE '".$this->db->escape($datasourcetable)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->errors[] = $this->db->lasterror();
			return -1;
		}

		$tableexists = ($this->db->num_rows($resql) > 0);
		$this->db->free($resql);
		if ($tableexists) {
			$sql = "SHOW COLUMNS FROM ".$this->db->sanitize($datasourcetable)." LIKE 'filename'";
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->errors[] = $this->db->lasterror();
				return -1;
			}

			$fieldexists = ($this->db->num_rows($resql) > 0);
			$this->db->free($resql);
			if (!$fieldexists) {
				$result = $this->db->DDLAddField($datasourcetable, 'filename', array(
					'type' => 'varchar',
					'value' => '255',
					'null' => '',
				));
				if ($result < 0) {
					$this->errors[] = $this->db->lasterror();
					return -1;
				}
			}
		}

		return 1;
	}

	/**
	 * Ensure attestation tables contain fields added by V1.
	 *
	 * @return	int		1 if OK, <0 if KO
	 */
	private function ensureAttestationSchema()
	{
		$tables = array(
			$this->db->prefix().'powerplantpv_attestation' => array(
				'entity' => array('type' => 'integer', 'value' => '', 'null' => 'NOT NULL DEFAULT 1'),
				'ref' => array('type' => 'varchar', 'value' => '128', 'null' => 'NOT NULL'),
				'fk_powerplant' => array('type' => 'integer', 'value' => '', 'null' => ''),
				'fk_soc' => array('type' => 'integer', 'value' => '', 'null' => ''),
				'fk_project' => array('type' => 'integer', 'value' => '', 'null' => ''),
				'type_code' => array('type' => 'varchar', 'value' => '64', 'null' => 'NOT NULL'),
				'model_pdf' => array('type' => 'varchar', 'value' => '128', 'null' => ''),
				'date_attestation' => array('type' => 'date', 'value' => '', 'null' => ''),
				'date_setting' => array('type' => 'date', 'value' => '', 'null' => ''),
				'date_completion' => array('type' => 'date', 'value' => '', 'null' => ''),
				'bta_contract_number' => array('type' => 'varchar', 'value' => '128', 'null' => ''),
				'max_export_power_kw' => array('type' => 'double', 'value' => '24,8', 'null' => ''),
				'max_frequency_hz' => array('type' => 'double', 'value' => '24,8', 'null' => ''),
				'landscape_integration_prime' => array('type' => 'smallint', 'value' => '', 'null' => 'DEFAULT 0'),
				'fk_user_sign' => array('type' => 'integer', 'value' => '', 'null' => ''),
				'date_signature' => array('type' => 'datetime', 'value' => '', 'null' => ''),
				'signature_ip' => array('type' => 'varchar', 'value' => '64', 'null' => ''),
				'signature_user_agent' => array('type' => 'varchar', 'value' => '255', 'null' => ''),
				'online_sign_name' => array('type' => 'varchar', 'value' => '255', 'null' => ''),
				'signature_hash' => array('type' => 'varchar', 'value' => '128', 'null' => ''),
				'signature_file' => array('type' => 'varchar', 'value' => '255', 'null' => ''),
				'signed_pdf_file' => array('type' => 'varchar', 'value' => '255', 'null' => ''),
				'date_valid' => array('type' => 'datetime', 'value' => '', 'null' => ''),
				'last_main_doc' => array('type' => 'varchar', 'value' => '255', 'null' => ''),
				'fk_user_valid' => array('type' => 'integer', 'value' => '', 'null' => ''),
				'status' => array('type' => 'integer', 'value' => '', 'null' => 'NOT NULL DEFAULT 0'),
			),
			$this->db->prefix().'powerplantpv_attestation_equipment' => array(
				'entity' => array('type' => 'integer', 'value' => '', 'null' => 'NOT NULL DEFAULT 1'),
				'fk_attestation' => array('type' => 'integer', 'value' => '', 'null' => 'NOT NULL'),
				'fk_powerplant_line' => array('type' => 'integer', 'value' => '', 'null' => ''),
				'fk_powerplant_serialnumber' => array('type' => 'integer', 'value' => '', 'null' => ''),
				'fk_product' => array('type' => 'integer', 'value' => '', 'null' => ''),
				'fk_categorie' => array('type' => 'integer', 'value' => '', 'null' => ''),
				'rank' => array('type' => 'integer', 'value' => '', 'null' => 'DEFAULT 0'),
			),
		);

		foreach ($tables as $table => $fields) {
			$sql = "SHOW TABLES LIKE '".$this->db->escape($table)."'";
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->errors[] = $this->db->lasterror();
				return -1;
			}
			$tableexists = ($this->db->num_rows($resql) > 0);
			$this->db->free($resql);
			if (!$tableexists) {
				continue;
			}

			foreach ($fields as $field => $fielddesc) {
				$sql = "SHOW COLUMNS FROM ".$this->db->sanitize($table)." LIKE '".$this->db->escape($field)."'";
				$resql = $this->db->query($sql);
				if (!$resql) {
					$this->errors[] = $this->db->lasterror();
					return -1;
				}
				$fieldexists = ($this->db->num_rows($resql) > 0);
				$this->db->free($resql);
				if ($fieldexists) {
					continue;
				}
				$result = $this->db->DDLAddField($table, $field, $fielddesc);
				if ($result < 0) {
					$this->errors[] = $this->db->lasterror();
					return -1;
				}
			}

			if ($table === $this->db->prefix().'powerplantpv_attestation_equipment') {
				$indexes = array(
					'idx_powerplantpv_attestation_equipment_parent' => 'fk_attestation',
					'idx_powerplantpv_attestation_equipment_powerplant_line' => 'fk_powerplant_line',
					'idx_powerplantpv_attestation_equipment_product' => 'fk_product',
					'idx_powerplantpv_attestation_equipment_categorie' => 'fk_categorie',
					'idx_powerplantpv_attestation_equipment_serialnumber' => 'fk_powerplant_serialnumber',
				);
			} elseif ($table === $this->db->prefix().'powerplantpv_attestation') {
				$indexes = array(
					'idx_powerplantpv_attestation_date_valid' => 'date_valid',
					'idx_powerplantpv_attestation_fk_user_valid' => 'fk_user_valid',
				);
			} else {
				$indexes = array();
			}
			if (!empty($indexes)) {
				foreach ($indexes as $indexname => $fieldname) {
					$sql = "SHOW INDEX FROM ".$this->db->sanitize($table)." WHERE Key_name = '".$this->db->escape($indexname)."'";
					$resql = $this->db->query($sql);
					if (!$resql) {
						$this->errors[] = $this->db->lasterror();
						return -1;
					}
					$indexexists = ($this->db->num_rows($resql) > 0);
					$this->db->free($resql);
					if ($indexexists) {
						continue;
					}
					$sql = "ALTER TABLE ".$this->db->sanitize($table)." ADD INDEX ".$this->db->sanitize($indexname)." (".$this->db->sanitize($fieldname).")";
					if (!$this->db->query($sql)) {
						$this->errors[] = $this->db->lasterror();
						return -1;
					}
				}
			}

			if ($table === $this->db->prefix().'powerplantpv_attestation_equipment') {
				$result = $this->relaxLegacyAttestationEquipmentMirrorColumns($table);
				if ($result < 0) {
					return -1;
				}
			}
		}

		return 1;
	}

	/**
	 * Ensure report template engine tables contain fields added after PR1.
	 *
	 * @return	int		1 if OK, <0 if KO
	 */
	private function ensureReportTemplateEngineSchema()
	{
		$tables = array(
			$this->db->prefix().'c_powerplantpv_intervention_nature' => array(
				'fk_report_template' => array('type' => 'integer', 'value' => '', 'null' => ''),
			),
			$this->db->prefix().'powerplantpv_maintenance_service_section' => array(
				'fk_report_template' => array('type' => 'integer', 'value' => '', 'null' => ''),
				'fk_report_template_section' => array('type' => 'integer', 'value' => '', 'null' => ''),
				'is_required' => array('type' => 'smallint', 'value' => '', 'null' => 'DEFAULT 0 NOT NULL'),
				'tms' => array('type' => 'timestamp', 'value' => '', 'null' => 'DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
				'fk_user_modif' => array('type' => 'integer', 'value' => '', 'null' => ''),
			),
			$this->db->prefix().'powerplantpv_report_template_field' => array(
				'fk_report_template' => array('type' => 'integer', 'value' => '', 'null' => ''),
				'fk_report_template_section' => array('type' => 'integer', 'value' => '', 'null' => ''),
				'default_value' => array('type' => 'text', 'value' => '', 'null' => ''),
				'placeholder' => array('type' => 'varchar', 'value' => '255', 'null' => ''),
				'help' => array('type' => 'text', 'value' => '', 'null' => ''),
				'visible_form' => array('type' => 'smallint', 'value' => '', 'null' => 'DEFAULT 1 NOT NULL'),
				'visible_pdf' => array('type' => 'smallint', 'value' => '', 'null' => 'DEFAULT 1 NOT NULL'),
				'readonly' => array('type' => 'smallint', 'value' => '', 'null' => 'DEFAULT 0 NOT NULL'),
				'date_creation' => array('type' => 'datetime', 'value' => '', 'null' => ''),
				'tms' => array('type' => 'timestamp', 'value' => '', 'null' => 'DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
				'fk_user_creat' => array('type' => 'integer', 'value' => '', 'null' => ''),
				'fk_user_modif' => array('type' => 'integer', 'value' => '', 'null' => ''),
			),
		);

		foreach ($tables as $table => $fields) {
			if (!$this->reportTemplateTableExists($table)) {
				continue;
			}
			foreach ($fields as $field => $fielddesc) {
				if ($this->reportTemplateColumnExists($table, $field)) {
					continue;
				}
				$result = $this->db->DDLAddField($table, $field, $fielddesc);
				if ($result < 0) {
					$this->errors[] = $this->db->lasterror();
					return -1;
				}
			}
		}

		$indexes = array(
			$this->db->prefix().'c_powerplantpv_intervention_nature' => array(
				'idx_c_powerplantpv_intervention_nature_fk_template' => 'fk_report_template',
			),
			$this->db->prefix().'powerplantpv_maintenance_service_section' => array(
				'idx_powerplantpv_maintenance_service_section_template' => 'fk_report_template',
				'idx_powerplantpv_maintenance_service_template_section_fk' => 'fk_report_template_section',
			),
			$this->db->prefix().'powerplantpv_report_template_field' => array(
				'idx_powerplantpv_report_template_field_fk_template' => 'fk_report_template',
				'idx_powerplantpv_report_template_field_fk_section' => 'fk_report_template_section',
			),
		);

		foreach ($indexes as $table => $tableindexes) {
			if (!$this->reportTemplateTableExists($table)) {
				continue;
			}
			foreach ($tableindexes as $indexname => $fieldname) {
				if ($this->reportTemplateIndexExists($table, $indexname)) {
					continue;
				}
				$sql = "ALTER TABLE ".$this->db->sanitize($table)." ADD INDEX ".$this->db->sanitize($indexname)." (".$this->db->sanitize($fieldname).")";
				if (!$this->db->query($sql)) {
					$this->errors[] = $this->db->lasterror();
					return -1;
				}
			}
		}

		return 1;
	}

	/**
	 * Ensure generated report snapshot tables contain fields added for PR6 updates.
	 *
	 * @return	int		1 if OK, <0 if KO
	 */
	private function ensureGeneratedReportSchema()
	{
		$createSqls = array(
			"CREATE TABLE IF NOT EXISTS ".$this->db->prefix()."powerplantpv_equipment_mppt(
				rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
				entity integer DEFAULT 1 NOT NULL,
				fk_powerplant integer NOT NULL,
				fk_inverter integer NOT NULL,
				mppt_number integer NOT NULL,
				pv_input_count integer DEFAULT 0 NOT NULL,
				position integer DEFAULT 0 NOT NULL,
				date_creation datetime,
				tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				fk_user_creat integer,
				fk_user_modif integer,
				import_key varchar(14)
			) ENGINE=innodb",
			"CREATE TABLE IF NOT EXISTS ".$this->db->prefix()."powerplantpv_equipment_string(
				rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
				entity integer DEFAULT 1 NOT NULL,
				fk_powerplant integer NOT NULL,
				fk_inverter integer NOT NULL,
				mppt_number integer NOT NULL,
				pv_input_number integer NOT NULL,
				string_ref varchar(128),
				module_count integer,
				module_power double(24,8),
				orientation varchar(64),
				tilt double(24,8),
				is_connected smallint DEFAULT 1 NOT NULL,
				position integer DEFAULT 0 NOT NULL,
				date_creation datetime,
				tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				fk_user_creat integer,
				fk_user_modif integer,
				import_key varchar(14)
			) ENGINE=innodb",
			"CREATE TABLE IF NOT EXISTS ".$this->db->prefix()."powerplantpv_report_dc_measure(
				rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
				entity integer DEFAULT 1 NOT NULL,
				fk_report integer NOT NULL,
				fk_report_section integer NOT NULL,
				fk_report_powerplant integer,
				fk_report_equipment integer,
				fk_powerplant integer,
				fk_inverter integer,
				inverter_ref varchar(128),
				inverter_label varchar(255),
				inverter_serial varchar(128),
				mppt_number integer,
				pv_input_number integer,
				string_ref varchar(128),
				is_connected smallint DEFAULT 1 NOT NULL,
				open_circuit_voltage double(24,8),
				polarity_checked smallint DEFAULT 0 NOT NULL,
				insulation_status varchar(32),
				insulation_positive_to_ground double(24,8),
				insulation_negative_to_ground double(24,8),
				observation text,
				stable_key varchar(255) NOT NULL,
				position integer DEFAULT 0 NOT NULL,
				date_creation datetime,
				tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				fk_user_creat integer,
				fk_user_modif integer,
				import_key varchar(14)
			) ENGINE=innodb",
		);
		foreach ($createSqls as $sql) {
			if (!$this->db->query($sql)) {
				$this->errors[] = $this->db->lasterror();
				return -1;
			}
		}

		$tables = array(
			$this->db->prefix().'powerplantpv_equipment_mppt' => array(
				'fk_powerplant' => array('type' => 'integer', 'value' => '', 'null' => 'NOT NULL'),
				'fk_inverter' => array('type' => 'integer', 'value' => '', 'null' => 'NOT NULL'),
				'mppt_number' => array('type' => 'integer', 'value' => '', 'null' => 'NOT NULL'),
				'pv_input_count' => array('type' => 'integer', 'value' => '', 'null' => 'DEFAULT 0 NOT NULL'),
			),
			$this->db->prefix().'powerplantpv_equipment_string' => array(
				'fk_powerplant' => array('type' => 'integer', 'value' => '', 'null' => 'NOT NULL'),
				'fk_inverter' => array('type' => 'integer', 'value' => '', 'null' => 'NOT NULL'),
				'mppt_number' => array('type' => 'integer', 'value' => '', 'null' => 'NOT NULL'),
				'pv_input_number' => array('type' => 'integer', 'value' => '', 'null' => 'NOT NULL'),
				'is_connected' => array('type' => 'smallint', 'value' => '', 'null' => 'DEFAULT 1 NOT NULL'),
			),
			$this->db->prefix().'powerplantpv_report' => array(
				'fk_fichinter' => array('type' => 'integer', 'value' => '', 'null' => 'NOT NULL'),
				'source_mode' => array('type' => 'varchar', 'value' => '16', 'null' => "DEFAULT 'contract' NOT NULL"),
				'status' => array('type' => 'varchar', 'value' => '16', 'null' => "DEFAULT 'draft' NOT NULL"),
			),
			$this->db->prefix().'powerplantpv_report_powerplant' => array(
				'fk_report' => array('type' => 'integer', 'value' => '', 'null' => 'NOT NULL'),
				'fk_powerplant' => array('type' => 'integer', 'value' => '', 'null' => 'NOT NULL'),
				'position' => array('type' => 'integer', 'value' => '', 'null' => 'DEFAULT 0 NOT NULL'),
			),
			$this->db->prefix().'powerplantpv_report_source_service' => array(
				'fk_report' => array('type' => 'integer', 'value' => '', 'null' => 'NOT NULL'),
				'fk_maintenance_service' => array('type' => 'integer', 'value' => '', 'null' => 'NOT NULL'),
				'source_mode' => array('type' => 'varchar', 'value' => '16', 'null' => "DEFAULT 'contract' NOT NULL"),
			),
			$this->db->prefix().'powerplantpv_report_equipment' => array(
				'fk_report' => array('type' => 'integer', 'value' => '', 'null' => 'NOT NULL'),
				'fk_source_equipment' => array('type' => 'integer', 'value' => '', 'null' => ''),
				'equipment_brand' => array('type' => 'varchar', 'value' => '128', 'null' => ''),
				'equipment_model' => array('type' => 'varchar', 'value' => '128', 'null' => ''),
				'technical_key' => array('type' => 'varchar', 'value' => '255', 'null' => ''),
				'equipment_position' => array('type' => 'varchar', 'value' => '255', 'null' => ''),
				'technical_snapshot' => array('type' => 'mediumtext', 'value' => '', 'null' => ''),
			),
			$this->db->prefix().'powerplantpv_report_section' => array(
				'fk_report' => array('type' => 'integer', 'value' => '', 'null' => 'NOT NULL'),
				'section_code' => array('type' => 'varchar', 'value' => '64', 'null' => 'NOT NULL'),
				'occurrence_key' => array('type' => 'varchar', 'value' => '255', 'null' => 'NOT NULL'),
			),
			$this->db->prefix().'powerplantpv_report_field' => array(
				'fk_report' => array('type' => 'integer', 'value' => '', 'null' => 'NOT NULL'),
				'fk_report_section' => array('type' => 'integer', 'value' => '', 'null' => 'NOT NULL'),
				'stable_key' => array('type' => 'varchar', 'value' => '255', 'null' => 'NOT NULL'),
				'field_code' => array('type' => 'varchar', 'value' => '64', 'null' => 'NOT NULL'),
				'options_snapshot' => array('type' => 'mediumtext', 'value' => '', 'null' => ''),
				'value_text' => array('type' => 'mediumtext', 'value' => '', 'null' => ''),
				'value_number' => array('type' => 'double', 'value' => '24,8', 'null' => ''),
				'value_date' => array('type' => 'datetime', 'value' => '', 'null' => ''),
			),
			$this->db->prefix().'powerplantpv_report_file' => array(
				'fk_report' => array('type' => 'integer', 'value' => '', 'null' => 'NOT NULL'),
				'fk_report_field' => array('type' => 'integer', 'value' => '', 'null' => 'NOT NULL'),
				'filepath' => array('type' => 'varchar', 'value' => '255', 'null' => 'NOT NULL'),
			),
			$this->db->prefix().'powerplantpv_report_dc_measure' => array(
				'fk_report' => array('type' => 'integer', 'value' => '', 'null' => 'NOT NULL'),
				'fk_report_section' => array('type' => 'integer', 'value' => '', 'null' => 'NOT NULL'),
				'stable_key' => array('type' => 'varchar', 'value' => '255', 'null' => 'NOT NULL'),
				'is_connected' => array('type' => 'smallint', 'value' => '', 'null' => 'DEFAULT 1 NOT NULL'),
				'polarity_checked' => array('type' => 'smallint', 'value' => '', 'null' => 'DEFAULT 0 NOT NULL'),
			),
		);

		foreach ($tables as $table => $fields) {
			if (!$this->reportTemplateTableExists($table)) {
				continue;
			}
			foreach ($fields as $field => $fielddesc) {
				if ($this->reportTemplateColumnExists($table, $field)) {
					continue;
				}
				$result = $this->db->DDLAddField($table, $field, $fielddesc);
				if ($result < 0) {
					$this->errors[] = $this->db->lasterror();
					return -1;
				}
			}
		}

		$indexes = array(
			$this->db->prefix().'powerplantpv_equipment_mppt' => array(
				'idx_powerplantpv_equipment_mppt_entity' => 'entity',
				'idx_powerplantpv_equipment_mppt_powerplant' => 'fk_powerplant',
				'idx_powerplantpv_equipment_mppt_inverter' => 'fk_inverter',
				'idx_powerplantpv_equipment_mppt_position' => 'position',
			),
			$this->db->prefix().'powerplantpv_equipment_string' => array(
				'idx_powerplantpv_equipment_string_entity' => 'entity',
				'idx_powerplantpv_equipment_string_powerplant' => 'fk_powerplant',
				'idx_powerplantpv_equipment_string_inverter' => 'fk_inverter',
				'idx_powerplantpv_equipment_string_mppt' => 'mppt_number',
				'idx_powerplantpv_equipment_string_position' => 'position',
			),
			$this->db->prefix().'powerplantpv_report' => array(
				'idx_powerplantpv_report_fichinter_guard' => 'fk_fichinter',
				'idx_powerplantpv_report_status' => 'status',
			),
			$this->db->prefix().'powerplantpv_report_powerplant' => array(
				'idx_powerplantpv_report_powerplant_report' => 'fk_report',
				'idx_powerplantpv_report_powerplant_powerplant' => 'fk_powerplant',
			),
			$this->db->prefix().'powerplantpv_report_source_service' => array(
				'idx_powerplantpv_report_source_service_report' => 'fk_report',
				'idx_powerplantpv_report_source_service_service' => 'fk_maintenance_service',
			),
			$this->db->prefix().'powerplantpv_report_equipment' => array(
				'idx_powerplantpv_report_equipment_report' => 'fk_report',
				'idx_powerplantpv_report_equipment_technical_key' => 'technical_key',
				'idx_powerplantpv_report_equipment_source_equipment' => 'fk_source_equipment',
			),
			$this->db->prefix().'powerplantpv_report_section' => array(
				'idx_powerplantpv_report_section_report' => 'fk_report',
				'idx_powerplantpv_report_section_code' => 'section_code',
			),
			$this->db->prefix().'powerplantpv_report_field' => array(
				'idx_powerplantpv_report_field_report' => 'fk_report',
				'idx_powerplantpv_report_field_section' => 'fk_report_section',
				'idx_powerplantpv_report_field_code' => 'field_code',
			),
			$this->db->prefix().'powerplantpv_report_file' => array(
				'idx_powerplantpv_report_file_report' => 'fk_report',
				'idx_powerplantpv_report_file_field' => 'fk_report_field',
			),
			$this->db->prefix().'powerplantpv_report_dc_measure' => array(
				'idx_powerplantpv_report_dc_measure_entity' => 'entity',
				'idx_powerplantpv_report_dc_measure_report' => 'fk_report',
				'idx_powerplantpv_report_dc_measure_section' => 'fk_report_section',
				'idx_powerplantpv_report_dc_measure_report_powerplant' => 'fk_report_powerplant',
				'idx_powerplantpv_report_dc_measure_report_equipment' => 'fk_report_equipment',
				'idx_powerplantpv_report_dc_measure_powerplant' => 'fk_powerplant',
				'idx_powerplantpv_report_dc_measure_inverter' => 'fk_inverter',
				'idx_powerplantpv_report_dc_measure_position' => 'position',
			),
		);

		foreach ($indexes as $table => $tableindexes) {
			if (!$this->reportTemplateTableExists($table)) {
				continue;
			}
			foreach ($tableindexes as $indexname => $fieldname) {
				if ($this->reportTemplateIndexExists($table, $indexname)) {
					continue;
				}
				$sql = "ALTER TABLE ".$this->db->sanitize($table)." ADD INDEX ".$this->db->sanitize($indexname)." (".$this->db->sanitize($fieldname).")";
				if (!$this->db->query($sql)) {
					$this->errors[] = $this->db->lasterror();
					return -1;
				}
			}
		}

		$customIndexes = array(
			$this->db->prefix().'powerplantpv_equipment_mppt' => array(
				'uk_powerplantpv_equipment_mppt' => 'UNIQUE INDEX uk_powerplantpv_equipment_mppt (entity, fk_powerplant, fk_inverter, mppt_number)',
			),
			$this->db->prefix().'powerplantpv_equipment_string' => array(
				'uk_powerplantpv_equipment_string_input' => 'UNIQUE INDEX uk_powerplantpv_equipment_string_input (entity, fk_powerplant, fk_inverter, mppt_number, pv_input_number)',
			),
			$this->db->prefix().'powerplantpv_report_dc_measure' => array(
				'uk_powerplantpv_report_dc_measure_stable' => 'UNIQUE INDEX uk_powerplantpv_report_dc_measure_stable (entity, fk_report, stable_key)',
			),
		);
		foreach ($customIndexes as $table => $tableindexes) {
			if (!$this->reportTemplateTableExists($table)) {
				continue;
			}
			foreach ($tableindexes as $indexname => $definition) {
				if ($this->reportTemplateIndexExists($table, $indexname)) {
					continue;
				}
				$sql = "ALTER TABLE ".$this->db->sanitize($table)." ADD ".$definition;
				if (!$this->db->query($sql)) {
					$this->errors[] = $this->db->lasterror();
					return -1;
				}
			}
		}

		return 1;
	}

	/**
	 * Ensure production/consumption index reading archive table exists.
	 *
	 * @return	int		1 if OK, <0 if KO
	 */
	private function ensureIndexReadingSchema()
	{
		$table = $this->db->prefix().'powerplantpv_index_reading';
		$sql = "CREATE TABLE IF NOT EXISTS ".$table."(
			rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
			entity integer DEFAULT 1 NOT NULL,
			fk_powerplant integer NOT NULL,
			fk_fichinter_source integer,
			fk_report integer,
			fk_report_powerplant integer DEFAULT 0 NOT NULL,
			fk_report_equipment integer DEFAULT 0 NOT NULL,
			fk_index_type integer,
			reading_type_code varchar(64) NOT NULL,
			reading_date datetime NOT NULL,
			value double(24,8) NOT NULL,
			unit varchar(32) DEFAULT 'kWh' NOT NULL,
			meter_ref varchar(128) DEFAULT '' NOT NULL,
			source_type varchar(32) DEFAULT 'manual' NOT NULL,
			comment text,
			date_creation datetime,
			tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			fk_user_creat integer,
			fk_user_modif integer,
			active smallint DEFAULT 1 NOT NULL
		) ENGINE=innodb";
		if (!$this->db->query($sql)) {
			$this->errors[] = $this->db->lasterror();
			return -1;
		}

		$fields = array(
			'entity' => array('type' => 'integer', 'value' => '', 'null' => 'DEFAULT 1 NOT NULL'),
			'fk_powerplant' => array('type' => 'integer', 'value' => '', 'null' => 'NOT NULL'),
			'fk_fichinter_source' => array('type' => 'integer', 'value' => '', 'null' => ''),
			'fk_report' => array('type' => 'integer', 'value' => '', 'null' => ''),
			'fk_report_powerplant' => array('type' => 'integer', 'value' => '', 'null' => 'DEFAULT 0 NOT NULL'),
			'fk_report_equipment' => array('type' => 'integer', 'value' => '', 'null' => 'DEFAULT 0 NOT NULL'),
			'fk_index_type' => array('type' => 'integer', 'value' => '', 'null' => ''),
			'reading_type_code' => array('type' => 'varchar', 'value' => '64', 'null' => 'NOT NULL'),
			'reading_date' => array('type' => 'datetime', 'value' => '', 'null' => 'NOT NULL'),
			'value' => array('type' => 'double', 'value' => '24,8', 'null' => 'NOT NULL'),
			'unit' => array('type' => 'varchar', 'value' => '32', 'null' => "DEFAULT 'kWh' NOT NULL"),
			'meter_ref' => array('type' => 'varchar', 'value' => '128', 'null' => "DEFAULT '' NOT NULL"),
			'source_type' => array('type' => 'varchar', 'value' => '32', 'null' => "DEFAULT 'manual' NOT NULL"),
			'comment' => array('type' => 'text', 'value' => '', 'null' => ''),
			'date_creation' => array('type' => 'datetime', 'value' => '', 'null' => ''),
			'tms' => array('type' => 'timestamp', 'value' => '', 'null' => 'DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
			'fk_user_creat' => array('type' => 'integer', 'value' => '', 'null' => ''),
			'fk_user_modif' => array('type' => 'integer', 'value' => '', 'null' => ''),
			'active' => array('type' => 'smallint', 'value' => '', 'null' => 'DEFAULT 1 NOT NULL'),
		);

		foreach ($fields as $field => $fielddesc) {
			if ($this->reportTemplateColumnExists($table, $field)) {
				continue;
			}
			$result = $this->db->DDLAddField($table, $field, $fielddesc);
			if ($result < 0) {
				$this->errors[] = $this->db->lasterror();
				return -1;
			}
		}

		$indexes = array(
			'idx_powerplantpv_index_reading_entity' => 'entity',
			'idx_powerplantpv_index_reading_powerplant' => 'fk_powerplant',
			'idx_powerplantpv_index_reading_fichinter' => 'fk_fichinter_source',
			'idx_powerplantpv_index_reading_report' => 'fk_report',
			'idx_powerplantpv_index_reading_report_powerplant' => 'fk_report_powerplant',
			'idx_powerplantpv_index_reading_report_equipment' => 'fk_report_equipment',
			'idx_powerplantpv_index_reading_index_type' => 'fk_index_type',
			'idx_powerplantpv_index_reading_source' => 'source_type',
			'idx_powerplantpv_index_reading_active' => 'active',
			'idx_powerplantpv_index_reading_user_creat' => 'fk_user_creat',
		);
		foreach ($indexes as $indexname => $fieldname) {
			if ($this->reportTemplateIndexExists($table, $indexname)) {
				continue;
			}
			$sql = "ALTER TABLE ".$this->db->sanitize($table)." ADD INDEX ".$this->db->sanitize($indexname)." (".$this->db->sanitize($fieldname).")";
			if (!$this->db->query($sql)) {
				$this->errors[] = $this->db->lasterror();
				return -1;
			}
		}

		$customIndexes = array(
			'uk_powerplantpv_index_reading_report_source' => 'UNIQUE INDEX uk_powerplantpv_index_reading_report_source (entity, fk_powerplant, fk_fichinter_source, fk_report, reading_type_code, meter_ref, fk_report_equipment)',
			'idx_powerplantpv_index_reading_type_date' => 'INDEX idx_powerplantpv_index_reading_type_date (reading_type_code, reading_date)',
		);
		foreach ($customIndexes as $indexname => $definition) {
			if ($this->reportTemplateIndexExists($table, $indexname)) {
				continue;
			}
			$sql = "ALTER TABLE ".$this->db->sanitize($table)." ADD ".$definition;
			if (!$this->db->query($sql)) {
				$this->errors[] = $this->db->lasterror();
				return -1;
			}
		}

		return 1;
	}

	/**
	 * Check if a table exists.
	 *
	 * @param	string	$table	Full table name
	 * @return	bool			True if table exists
	 */
	private function reportTemplateTableExists($table)
	{
		$sql = "SHOW TABLES LIKE '".$this->db->escape($table)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->errors[] = $this->db->lasterror();
			return false;
		}
		$exists = ($this->db->num_rows($resql) > 0);
		$this->db->free($resql);

		return $exists;
	}

	/**
	 * Check if a column exists.
	 *
	 * @param	string	$table	Full table name
	 * @param	string	$field	Field name
	 * @return	bool			True if column exists
	 */
	private function reportTemplateColumnExists($table, $field)
	{
		$sql = "SHOW COLUMNS FROM ".$this->db->sanitize($table)." LIKE '".$this->db->escape($field)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->errors[] = $this->db->lasterror();
			return false;
		}
		$exists = ($this->db->num_rows($resql) > 0);
		$this->db->free($resql);

		return $exists;
	}

	/**
	 * Check if an index exists.
	 *
	 * @param	string	$table		Full table name
	 * @param	string	$indexname	Index name
	 * @return	bool				True if index exists
	 */
	private function reportTemplateIndexExists($table, $indexname)
	{
		$sql = "SHOW INDEX FROM ".$this->db->sanitize($table)." WHERE Key_name = '".$this->db->escape($indexname)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->errors[] = $this->db->lasterror();
			return false;
		}
		$exists = ($this->db->num_rows($resql) > 0);
		$this->db->free($resql);

		return $exists;
	}

	/**
	 * Relax legacy mirror columns kept on existing attestation equipment tables.
	 *
	 * The current model no longer creates or writes those columns. Existing installations
	 * may still have them as NOT NULL without defaults, which breaks INSERT in strict SQL mode.
	 *
	 * @param	string	$table	Full table name
	 * @return	int				1 if OK, <0 if KO
	 */
	private function relaxLegacyAttestationEquipmentMirrorColumns($table)
	{
		$legacycolumns = array(
			'category_code',
			'category_label',
			'equipment_type',
			'designation',
			'brand',
			'model',
			'manufacturer',
			'serial_number',
			'bridage_enabled',
			'bridage_type',
			'max_power_kw',
		);

		foreach ($legacycolumns as $field) {
			$sql = "SHOW COLUMNS FROM ".$this->db->sanitize($table)." LIKE '".$this->db->escape($field)."'";
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->errors[] = $this->db->lasterror();
				return -1;
			}

			$obj = $this->db->fetch_object($resql);
			$this->db->free($resql);
			if (!$obj) {
				continue;
			}

			if (!empty($obj->Null) && strtoupper((string) $obj->Null) === 'YES') {
				continue;
			}

			$fieldtype = !empty($obj->Type) ? (string) $obj->Type : 'varchar(255)';
			$sql = "ALTER TABLE ".$this->db->sanitize($table)." MODIFY ".$this->db->sanitize($field)." ".$fieldtype." NULL";
			if (!$this->db->query($sql)) {
				$this->errors[] = $this->db->lasterror();
				return -1;
			}
			dol_syslog(__METHOD__.' relaxed legacy attestation equipment mirror column '.$field, LOG_INFO);
		}

		return 1;
	}

	/**
	 * Register this module sharing options in Multicompany external module settings.
	 *
	 * @return	int		1 if OK, <0 if KO
	 */
	private function registerMulticompanyExternalSharing()
	{
		$externalmodule = $this->getCurrentMulticompanyExternalSharing();
		if (!is_array($externalmodule)) {
			return -1;
		}

		$externalmodule = array_merge($externalmodule, $this->getMulticompanyExternalSharingConfig());

		return $this->saveMulticompanyExternalSharing($externalmodule);
	}

	/**
	 * Remove this module sharing options from Multicompany external module settings.
	 *
	 * @return	int		1 if OK, <0 if KO
	 */
	private function unregisterMulticompanyExternalSharing()
	{
		// Keep Multicompany sharing settings across disable/reactivate cycles.
		return 1;
	}

	/**
	 * Return current Multicompany external sharing declaration.
	 *
	 * @return	array<string,mixed>|null		Current declaration, null on invalid JSON
	 */
	private function getCurrentMulticompanyExternalSharing()
	{
		global $conf;

		$json = '';
		if (function_exists('getDolGlobalString')) {
			$json = getDolGlobalString('MULTICOMPANY_EXTERNAL_MODULES_SHARING');
		} elseif (!empty($conf->global->MULTICOMPANY_EXTERNAL_MODULES_SHARING)) {
			$json = (string) $conf->global->MULTICOMPANY_EXTERNAL_MODULES_SHARING;
		}

		if ($json === '') {
			return array();
		}

		$externalmodule = json_decode($json, true);
		if (!is_array($externalmodule)) {
			$this->errors[] = 'Invalid MULTICOMPANY_EXTERNAL_MODULES_SHARING JSON value';
			return null;
		}

		return $externalmodule;
	}

	/**
	 * Save Multicompany external sharing declaration.
	 *
	 * @param	array<string,mixed>	$externalmodule		External module sharing declaration
	 * @return	int										1 if OK, <0 if KO
	 */
	private function saveMulticompanyExternalSharing($externalmodule)
	{
		global $conf;

		$jsonformat = json_encode($externalmodule, JSON_UNESCAPED_SLASHES);
		if ($jsonformat === false) {
			$this->errors[] = 'Unable to encode MULTICOMPANY_EXTERNAL_MODULES_SHARING JSON value';
			return -1;
		}

		$result = dolibarr_set_const($this->db, 'MULTICOMPANY_EXTERNAL_MODULES_SHARING', $jsonformat, 'chaine', 0, '', (int) $conf->entity);
		if ($result <= 0) {
			$this->errors[] = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Return Multicompany sharing options added by this module.
	 *
	 * @return	array<string,mixed>		Multicompany external sharing config
	 */
	private function getMulticompanyExternalSharingConfig()
	{
		dol_include_once('/powerplantpv/class/actions_powerplantpv.class.php');

		if (class_exists('ActionsPowerplantpv') && method_exists('ActionsPowerplantpv', 'getMulticompanySharingDefinition')) {
			return ActionsPowerplantpv::getMulticompanySharingDefinition();
		}

		return array();
	}

	/**
	 * Create a product text extrafield used by photovoltaic product data.
	 *
	 * @param	ExtraFields	$extrafields	Extrafields manager
	 * @param	string		$attrname		Technical attribute name
	 * @param	string		$label			Translation key
	 * @param	int			$position		Position
	 * @return	int							1 if OK, <0 if KO
	 */
	private function ensureProductPhotovoltaicTextExtrafield($extrafields, $attrname, $label, $position)
	{
		$elementtype = 'product';

		$extrafields->fetch_name_optionals_label($elementtype);
		if (!empty($extrafields->attributes[$elementtype]['label'][$attrname])) {
			return 1;
		}

		$result = $extrafields->addExtraField(
			$attrname,
			$label,
			'varchar',
			(int) $position,
			128,
			$elementtype,
			0,
			0,
			'',
			'',
			1,
			'',
			-1,
			'',
			'',
			'',
			'powerplantpv@powerplantpv',
			'isModEnabled("powerplantpv")'
		);
		if ($result < 0) {
			$this->errors[] = $extrafields->error;
			return -1;
		}

		return 1;
	}

	/**
	 * Create or update the ticket extrafield used to link a ticket to a power plant.
	 *
	 * @param	ExtraFields	$extrafields	Extrafields manager
	 * @return	int							1 if OK, <0 if KO
	 */
	private function ensureTicketPowerPlantExtrafield($extrafields)
	{
		$elementtype = 'ticket';
		$attrname = 'powerplantpv_powerplant';

		$extrafields->fetch_name_optionals_label($elementtype);

		$powerplantLink = array(
			'options' => array('PowerPlant:powerplantpv/class/powerplant.class.php:0:((entity:IN:__SHARED_ENTITIES__)):ref' => null)
		);

		$method = 'addExtraField';
		if (!empty($extrafields->attributes[$elementtype]['label'][$attrname])) {
			$method = 'updateExtraField';

			if (!empty($extrafields->attributes[$elementtype]['type'][$attrname]) && $extrafields->attributes[$elementtype]['type'][$attrname] != 'link') {
				$result = $this->cleanTicketPowerPlantExtrafieldBeforeTypeChange($attrname);
				if ($result < 0) {
					return -1;
				}
			}
		}

		$result = $extrafields->$method(
			$attrname,
			'PowerPlant',
			'link',
			200,
			'',
			$elementtype,
			0,
			0,
			'',
			$powerplantLink,
			1,
			'',
			-1,
			'',
			'',
			'',
			'powerplantpv@powerplantpv',
			'isModEnabled("powerplantpv")'
		);
		if ($result < 0) {
			$this->errors[] = $extrafields->error;
			return -1;
		}

		return 1;
	}

	/**
	 * Normalize empty values before changing the ticket power plant extrafield from varchar to int.
	 *
	 * @param	string	$attrname	Extrafield attribute name
	 * @return	int					1 if OK, <0 if KO
	 */
	private function cleanTicketPowerPlantExtrafieldBeforeTypeChange($attrname)
	{
		$sql = "UPDATE ".$this->db->prefix()."ticket_extrafields";
		$sql .= " SET ".$this->db->sanitize($attrname)." = NULL";
		$sql .= " WHERE ".$this->db->sanitize($attrname)." = ''";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->errors[] = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Create or update the stored peak-power extrafield for commercial documents.
	 *
	 * @param	ExtraFields	$extrafields	Extrafields manager
	 * @param	string		$elementtype	Element type
	 * @return	int							1 if OK, <0 if KO
	 */
	private function ensureCommercialPeakPowerExtrafield($extrafields, $elementtype)
	{
		$extrafields->fetch_name_optionals_label($elementtype);

		$moreparams = array(
			'css' => 'maxwidth100 right',
			'csslist' => 'right',
			'cssview' => 'right',
		);

		$method = 'addExtraField';
		if (!empty($extrafields->attributes[$elementtype]['label']['powerplantpv_peak_power'])) {
			$method = 'updateExtraField';
		}

		$result = $extrafields->$method(
			'powerplantpv_peak_power',
			'PowerPlantPVPeakPower',
			'double',
			200,
			'24,8',
			$elementtype,
			0,
			0,
			'',
			'',
			0,
			'',
			5,
			'PowerPlantPVPeakPowerHelp',
			'',
			'',
			'powerplantpv@powerplantpv',
			'isModEnabled("powerplantpv")',
			1,
			1,
			$moreparams
		);
		if ($result < 0) {
			$this->errors[] = $extrafields->error;
			return -1;
		}

		return 1;
	}

	/**
	 * Create the extrafields required by the v1.3 maintenance data foundation.
	 *
	 * @param	ExtraFields	$extrafields	Extrafields manager
	 * @return	int							1 if OK, <0 if KO
	 */
	private function ensureMaintenanceFoundationExtrafields($extrafields)
	{
		$enabled = 'isModEnabled("powerplantpv") && getDolGlobalInt("POWERPLANTPV_MAINTENANCE_ENABLE", 1)';
		$recurrenceOptions = array(
			'options' => array(
				'monthly' => 'PowerPlantPVMaintenanceRecurrenceMonthly',
				'quarterly' => 'PowerPlantPVMaintenanceRecurrenceQuarterly',
				'halfyearly' => 'PowerPlantPVMaintenanceRecurrenceHalfyearly',
				'yearly' => 'PowerPlantPVMaintenanceRecurrenceYearly',
				'biennial' => 'PowerPlantPVMaintenanceRecurrenceBiennial',
				'custom' => 'PowerPlantPVMaintenanceRecurrenceCustom',
			),
		);
		$maintenanceServiceOptions = array(
			'options' => array('c_powerplantpv_maintenance_service:label:rowid::((active:=:1) AND (entity:=:$ENTITY$))' => null),
		);
		$interventionNatureOptions = array(
			'options' => array('c_powerplantpv_intervention_nature:label:rowid::((active:=:1) AND (entity:=:$ENTITY$))' => null),
		);

		$definitions = array(
			array('elementtype' => 'contrat', 'attrname' => 'powerplantpv_maintenance_recurrence', 'label' => 'PowerPlantPVMaintenanceRecurrence', 'type' => 'select', 'size' => '', 'position' => 200, 'param' => $recurrenceOptions, 'help' => 'PowerPlantPVMaintenanceRecurrenceHelp'),
			array('elementtype' => 'contrat', 'attrname' => 'powerplantpv_next_maintenance_period_start', 'label' => 'PowerPlantPVNextMaintenancePeriodStart', 'type' => 'date', 'size' => '', 'position' => 201, 'param' => '', 'help' => 'PowerPlantPVNextMaintenancePeriodStartHelp'),
			array('elementtype' => 'contrat', 'attrname' => 'powerplantpv_next_maintenance_period_end', 'label' => 'PowerPlantPVNextMaintenancePeriodEnd', 'type' => 'date', 'size' => '', 'position' => 202, 'param' => '', 'help' => 'PowerPlantPVNextMaintenancePeriodEndHelp'),
			array('elementtype' => 'product', 'attrname' => 'powerplantpv_maintenance_services', 'label' => 'PowerPlantPVMaintenanceServices', 'type' => 'chkbxlst', 'size' => '', 'position' => 220, 'param' => $maintenanceServiceOptions, 'help' => 'PowerPlantPVMaintenanceServicesHelp'),
			array('elementtype' => 'fichinter', 'attrname' => 'powerplantpv_intervention_nature', 'label' => 'PowerPlantPVInterventionNature', 'type' => 'sellist', 'size' => '', 'position' => 220, 'param' => $interventionNatureOptions, 'help' => 'PowerPlantPVInterventionNatureHelp'),
		);

		foreach ($definitions as $definition) {
			$result = $this->ensureMaintenanceExtraField($extrafields, $definition, $enabled);
			if ($result < 0) {
				return -1;
			}
		}

		return 1;
	}

	/**
	 * Create one maintenance extrafield when it does not exist yet.
	 *
	 * Existing extrafields are not updated so administrator custom labels or options are preserved.
	 *
	 * @param	ExtraFields				$extrafields	Extrafields manager
	 * @param	array<string,mixed>		$definition		Extrafield definition
	 * @param	string					$enabled		Enabled expression
	 * @return	int										1 if OK, <0 if KO
	 */
	private function ensureMaintenanceExtraField($extrafields, $definition, $enabled)
	{
		$elementtype = (string) $definition['elementtype'];
		$attrname = (string) $definition['attrname'];

		$extrafields->fetch_name_optionals_label($elementtype);
		if (!empty($extrafields->attributes[$elementtype]['label'][$attrname])) {
			return 1;
		}

		$result = $extrafields->addExtraField(
			$attrname,
			(string) $definition['label'],
			(string) $definition['type'],
			(int) $definition['position'],
			(string) $definition['size'],
			$elementtype,
			0,
			0,
			'',
			$definition['param'],
			1,
			'',
			-1,
			(string) $definition['help'],
			'',
			'',
			'powerplantpv@powerplantpv',
			$enabled
		);
		if ($result < 0) {
			$this->errors[] = $extrafields->error;
			return -1;
		}

		return 1;
	}

	/**
	 * Seed maintenance dictionaries and report template fields for the current entity.
	 *
	 * @return	int		1 if OK, <0 if KO
	 */
	private function seedMaintenanceFoundationData()
	{
		global $conf;

		$entity = (int) $conf->entity;
		$result = $this->seedMaintenanceDictionaryRows($entity);
		if ($result < 0) {
			return -1;
		}

		$result = $this->seedMaintenanceServiceSectionMappings($entity);
		if ($result < 0) {
			return -1;
		}

		$result = $this->seedMaintenanceReportTemplateFields($entity);
		if ($result < 0) {
			return -1;
		}

		return $this->seedMaintenanceReportTemplateEngineData($entity);
	}

	/**
	 * Seed maintenance dictionary rows.
	 *
	 * @param	int	$entity	Current entity
	 * @return	int			1 if OK, <0 if KO
	 */
	private function seedMaintenanceDictionaryRows($entity)
	{
		$natureRows = array(
			array(
				'code' => 'PREVENTIVE_MAINTENANCE',
				'label' => 'Maintenance préventive',
				'label_en' => 'Preventive maintenance',
				'description' => 'Intervention de maintenance préventive d’une centrale photovoltaïque',
				'description_en' => 'Preventive maintenance intervention for a photovoltaic power plant',
				'report_template_code' => 'preventive_maintenance',
				'is_maintenance' => 1,
				'is_preventive' => 1,
				'requires_report' => 1,
				'requires_signature' => 1,
				'active' => 1,
				'position' => 10,
			),
		);
		foreach ($natureRows as $row) {
			$result = $this->insertMaintenanceDefaultRow($this->db->prefix().'c_powerplantpv_intervention_nature', array_merge(array('entity' => $entity), $row), "entity = ".$entity." AND code = '".$this->db->escape($row['code'])."'");
			if ($result < 0) {
				return -1;
			}
		}

		$serviceRows = array(
			array('code' => 'VISUAL_INSPECTION', 'label' => 'Inspection visuelle générale', 'label_en' => 'General visual inspection', 'description' => 'Contrôles visuels de l’installation et des équipements.', 'description_en' => 'Visual checks of the installation and equipment.', 'active' => 1, 'position' => 10),
			array('code' => 'PANEL_CLEANING', 'label' => 'Nettoyage des panneaux', 'label_en' => 'Panel cleaning', 'description' => 'Nettoyage panneaux et contrôles associés.', 'description_en' => 'Panel cleaning and related checks.', 'active' => 1, 'position' => 20),
			array('code' => 'INVERTER_CHECK', 'label' => 'Contrôle onduleur(s)', 'label_en' => 'Inverter check', 'description' => 'Section de contrôle par onduleur.', 'description_en' => 'Check section for each inverter.', 'active' => 1, 'position' => 30),
			array('code' => 'ELECTRICAL_BOX_CHECK', 'label' => 'Contrôle coffrets AC/DC', 'label_en' => 'AC/DC box check', 'description' => 'Section de contrôle par coffret AC/DC.', 'description_en' => 'Check section for each AC/DC box.', 'active' => 1, 'position' => 40),
			array('code' => 'DC_ELECTRICAL_MEASURE', 'label' => 'Mesures électriques DC', 'label_en' => 'DC electrical measurements', 'description' => 'Mesures côté DC selon MPPT et entrées PV.', 'description_en' => 'DC-side measurements by MPPT and PV input.', 'active' => 1, 'position' => 50),
			array('code' => 'PRODUCTION_READING', 'label' => 'Relevés production/consommation', 'label_en' => 'Production/consumption readings', 'description' => 'Relevés N-1/N et archivage dans la centrale.', 'description_en' => 'N-1/N readings and archiving on the power plant.', 'active' => 1, 'position' => 60),
			array('code' => 'ROOF_CHECK', 'label' => 'Contrôle toiture, abergements, fixations et câbles', 'label_en' => 'Roof, flashing, fastening and cable check', 'description' => 'Abergements, fixations, câbles et panneaux.', 'description_en' => 'Flashings, fastenings, cables and panels.', 'active' => 1, 'position' => 70),
			array('code' => 'EARTHING_CHECK', 'label' => 'Contrôle mises à la terre / continuité MALT', 'label_en' => 'Earthing / continuity check', 'description' => 'MALT onduleurs, coffrets et toiture.', 'description_en' => 'Earthing continuity for inverters, boxes and roof equipment.', 'active' => 1, 'position' => 80),
			array('code' => 'SAFETY_CHECK', 'label' => 'Contrôle sécurité électrique', 'label_en' => 'Electrical safety check', 'description' => 'Sectionneur, différentiels, parafoudre, arrêt d’urgence.', 'description_en' => 'Switch disconnector, RCDs, surge protection and emergency stop.', 'active' => 1, 'position' => 90),
			array('code' => 'THERMOGRAPHY', 'label' => 'Thermographie', 'label_en' => 'Thermography', 'description' => 'Photos et données thermographiques.', 'description_en' => 'Thermal photos and thermography data.', 'active' => 1, 'position' => 100),
		);
		foreach ($serviceRows as $row) {
			$result = $this->insertMaintenanceDefaultRow($this->db->prefix().'c_powerplantpv_maintenance_service', array_merge(array('entity' => $entity), $row), "entity = ".$entity." AND code = '".$this->db->escape($row['code'])."'");
			if ($result < 0) {
				return -1;
			}
		}

		$sectionRows = array(
			array('code' => 'GENERAL_INFORMATION', 'label' => 'Informations générales', 'label_en' => 'General information', 'description' => 'Informations générales de l’intervention.', 'description_en' => 'General intervention information.', 'scope_type' => 'intervention', 'equipment_type' => '', 'repeat_mode' => 'once', 'is_base' => 1, 'is_required' => 1, 'active' => 1, 'position' => 10),
			array('code' => 'EQUIPMENT_SUMMARY', 'label' => 'Matériel / installation', 'label_en' => 'Equipment / installation', 'description' => 'Synthèse du matériel et de l’installation.', 'description_en' => 'Equipment and installation summary.', 'scope_type' => 'powerplant', 'equipment_type' => '', 'repeat_mode' => 'per_powerplant', 'is_base' => 1, 'is_required' => 1, 'active' => 1, 'position' => 20),
			array('code' => 'INSTALLATION_DESCRIPTION', 'label' => 'Descriptif installation', 'label_en' => 'Installation description', 'description' => 'Description de l’installation photovoltaïque.', 'description_en' => 'Photovoltaic installation description.', 'scope_type' => 'powerplant', 'equipment_type' => '', 'repeat_mode' => 'per_powerplant', 'is_base' => 1, 'is_required' => 1, 'active' => 1, 'position' => 30),
			array('code' => 'PANEL_CLEANING', 'label' => 'Nettoyage panneaux', 'label_en' => 'Panel cleaning', 'description' => 'Contrôles et nettoyage des panneaux.', 'description_en' => 'Panel cleaning and checks.', 'scope_type' => 'powerplant', 'equipment_type' => 'panel', 'repeat_mode' => 'per_powerplant', 'is_base' => 0, 'is_required' => 0, 'active' => 1, 'position' => 40),
			array('code' => 'INVERTER', 'label' => 'Onduleur', 'label_en' => 'Inverter', 'description' => 'Contrôles par onduleur.', 'description_en' => 'Checks for each inverter.', 'scope_type' => 'inverter', 'equipment_type' => '', 'repeat_mode' => 'per_equipment', 'is_base' => 0, 'is_required' => 0, 'active' => 1, 'position' => 50),
			array('code' => 'ELECTRICAL_BOX', 'label' => 'Vérifications coffrets', 'label_en' => 'Electrical box checks', 'description' => 'Vérifications coffrets AC/DC.', 'description_en' => 'AC/DC box checks.', 'scope_type' => 'electrical_box', 'equipment_type' => '', 'repeat_mode' => 'per_equipment', 'is_base' => 0, 'is_required' => 0, 'active' => 1, 'position' => 60),
			array('code' => 'DC_ELECTRICAL_MEASURE', 'label' => 'Mesures électriques DC', 'label_en' => 'DC electrical measurements', 'description' => 'Mesures DC par onduleur, MPPT et entrée PV.', 'description_en' => 'DC measurements by inverter, MPPT and PV input.', 'scope_type' => 'pv_input', 'equipment_type' => 'INVERTER', 'repeat_mode' => 'once_per_powerplant', 'is_base' => 0, 'is_required' => 0, 'active' => 1, 'position' => 70),
			array('code' => 'PRODUCTION_READING', 'label' => 'Relevés production/consommation', 'label_en' => 'Production/consumption readings', 'description' => 'Relevés de production, injection et consommation.', 'description_en' => 'Production, injection and consumption readings.', 'scope_type' => 'powerplant', 'equipment_type' => '', 'repeat_mode' => 'per_powerplant', 'is_base' => 0, 'is_required' => 0, 'active' => 1, 'position' => 80),
			array('code' => 'ROOF', 'label' => 'Opérations en toiture', 'label_en' => 'Roof operations', 'description' => 'Contrôles et opérations en toiture.', 'description_en' => 'Roof checks and operations.', 'scope_type' => 'roof_area', 'equipment_type' => '', 'repeat_mode' => 'per_powerplant', 'is_base' => 0, 'is_required' => 0, 'active' => 1, 'position' => 90),
			array('code' => 'THERMOGRAPHY', 'label' => 'Thermographie', 'label_en' => 'Thermography', 'description' => 'Données et photos thermographiques.', 'description_en' => 'Thermography data and photos.', 'scope_type' => 'free_line', 'equipment_type' => '', 'repeat_mode' => 'dynamic_rows', 'is_base' => 0, 'is_required' => 0, 'active' => 1, 'position' => 100),
			array('code' => 'GENERAL_OBSERVATIONS', 'label' => 'Observations générales', 'label_en' => 'General observations', 'description' => 'Observations générales de l’intervention.', 'description_en' => 'General intervention observations.', 'scope_type' => 'intervention', 'equipment_type' => '', 'repeat_mode' => 'once', 'is_base' => 1, 'is_required' => 1, 'active' => 1, 'position' => 110),
			array('code' => 'CUSTOMER_SIGNATURE', 'label' => 'Signature client', 'label_en' => 'Customer signature', 'description' => 'Signature du client final.', 'description_en' => 'End customer signature.', 'scope_type' => 'intervention', 'equipment_type' => '', 'repeat_mode' => 'once', 'is_base' => 1, 'is_required' => 1, 'active' => 1, 'position' => 120),
		);
		foreach ($sectionRows as $row) {
			$result = $this->insertMaintenanceDefaultRow($this->db->prefix().'c_powerplantpv_report_section', array_merge(array('entity' => $entity), $row), "entity = ".$entity." AND code = '".$this->db->escape($row['code'])."'");
			if ($result < 0) {
				return -1;
			}
		}

		$indexRows = array(
			array('code' => 'INVERTER_PRODUCTION', 'label' => 'Relevé production onduleur', 'label_en' => 'Inverter production reading', 'description' => 'Relevé de production directement lu sur l’onduleur.', 'description_en' => 'Production reading directly read from the inverter.', 'default_unit' => 'kWh', 'active' => 1, 'position' => 10),
			array('code' => 'PRODUCTION_INDEX', 'label' => 'Index production', 'label_en' => 'Production index', 'description' => 'Index de production.', 'description_en' => 'Production index.', 'default_unit' => 'kWh', 'active' => 1, 'position' => 20),
			array('code' => 'INJECTION_INDEX', 'label' => 'Index injection', 'label_en' => 'Injection index', 'description' => 'Index d’injection réseau.', 'description_en' => 'Grid injection index.', 'default_unit' => 'kWh', 'active' => 1, 'position' => 30),
			array('code' => 'CONSUMPTION_INDEX', 'label' => 'Index consommation', 'label_en' => 'Consumption index', 'description' => 'Index de consommation.', 'description_en' => 'Consumption index.', 'default_unit' => 'kWh', 'active' => 1, 'position' => 40),
			array('code' => 'ANNUAL_PRODUCTION', 'label' => 'Production annuelle', 'label_en' => 'Annual production', 'description' => 'Production annuelle constatée.', 'description_en' => 'Observed annual production.', 'default_unit' => 'kWh', 'active' => 1, 'position' => 50),
			array('code' => 'SELF_CONSUMPTION', 'label' => 'Autoconsommation', 'label_en' => 'Self-consumption', 'description' => 'Énergie autoconsommée.', 'description_en' => 'Self-consumed energy.', 'default_unit' => 'kWh', 'active' => 1, 'position' => 60),
			array('code' => 'OTHER', 'label' => 'Autre', 'label_en' => 'Other', 'description' => 'Autre type de relevé.', 'description_en' => 'Other reading type.', 'default_unit' => '', 'active' => 1, 'position' => 999),
		);
		foreach ($indexRows as $row) {
			$result = $this->insertMaintenanceDefaultRow($this->db->prefix().'c_powerplantpv_index_type', array_merge(array('entity' => $entity), $row), "entity = ".$entity." AND code = '".$this->db->escape($row['code'])."'");
			if ($result < 0) {
				return -1;
			}
		}

		return 1;
	}

	/**
	 * Insert one default row if its unique predicate does not already match.
	 *
	 * @param	string				$table			Table name with prefix
	 * @param	array<string,mixed>	$values			Column values
	 * @param	string				$whereNotExists	Unique predicate
	 * @return	int									1 if OK, <0 if KO
	 */
	private function insertMaintenanceDefaultRow($table, $values, $whereNotExists)
	{
		$columns = array();
		$sqlvalues = array();
		foreach ($values as $column => $value) {
			$columns[] = $this->db->sanitize($column);
			$sqlvalues[] = $this->maintenanceSqlValue($value);
		}

		$sql = "INSERT INTO ".$this->db->sanitize($table)." (".implode(', ', $columns).")";
		$sql .= " SELECT ".implode(', ', $sqlvalues);
		$sql .= " WHERE NOT EXISTS (SELECT 1 FROM ".$this->db->sanitize($table)." WHERE ".$whereNotExists.")";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->errors[] = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Convert a scalar value to SQL literal.
	 *
	 * @param	mixed	$value	Value
	 * @return	string			SQL literal
	 */
	private function maintenanceSqlValue($value)
	{
		if ($value === null) {
			return 'NULL';
		}
		if (is_int($value) || is_float($value)) {
			return (string) $value;
		}

		return "'".$this->db->escape((string) $value)."'";
	}

	/**
	 * Seed maintenance service to report section mappings.
	 *
	 * @param	int	$entity	Current entity
	 * @return	int			1 if OK, <0 if KO
	 */
	private function seedMaintenanceServiceSectionMappings($entity)
	{
		$mappings = array(
			array('service' => 'VISUAL_INSPECTION', 'section' => 'EQUIPMENT_SUMMARY', 'position' => 10),
			array('service' => 'PANEL_CLEANING', 'section' => 'PANEL_CLEANING', 'position' => 20),
			array('service' => 'PANEL_CLEANING', 'section' => 'ROOF', 'position' => 30),
			array('service' => 'INVERTER_CHECK', 'section' => 'INVERTER', 'position' => 40),
			array('service' => 'ELECTRICAL_BOX_CHECK', 'section' => 'ELECTRICAL_BOX', 'position' => 50),
			array('service' => 'DC_ELECTRICAL_MEASURE', 'section' => 'DC_ELECTRICAL_MEASURE', 'position' => 60),
			array('service' => 'PRODUCTION_READING', 'section' => 'PRODUCTION_READING', 'position' => 70),
			array('service' => 'ROOF_CHECK', 'section' => 'ROOF', 'position' => 80),
			array('service' => 'EARTHING_CHECK', 'section' => 'INVERTER', 'position' => 90),
			array('service' => 'EARTHING_CHECK', 'section' => 'ELECTRICAL_BOX', 'position' => 100),
			array('service' => 'EARTHING_CHECK', 'section' => 'ROOF', 'position' => 110),
			array('service' => 'SAFETY_CHECK', 'section' => 'ELECTRICAL_BOX', 'position' => 120),
			array('service' => 'THERMOGRAPHY', 'section' => 'THERMOGRAPHY', 'position' => 130),
		);
		$table = $this->db->prefix().'powerplantpv_maintenance_service_section';
		$serviceTable = $this->db->prefix().'c_powerplantpv_maintenance_service';
		$sectionTable = $this->db->prefix().'c_powerplantpv_report_section';

		foreach ($mappings as $mapping) {
			$service = $this->db->escape($mapping['service']);
			$section = $this->db->escape($mapping['section']);
			$sql = "INSERT INTO ".$table." (entity, fk_maintenance_service, fk_report_section, active, position, date_creation)";
			$sql .= " SELECT ".$entity.", s.rowid, rs.rowid, 1, ".((int) $mapping['position']).", '".$this->db->idate(dol_now())."'";
			$sql .= " FROM ".$serviceTable." as s";
			$sql .= " INNER JOIN ".$sectionTable." as rs ON rs.entity = s.entity AND rs.code = '".$section."'";
			$sql .= " WHERE s.entity = ".$entity." AND s.code = '".$service."'";
			$sql .= " AND NOT EXISTS (SELECT 1 FROM ".$table." as m WHERE m.entity = ".$entity." AND m.fk_maintenance_service = s.rowid AND m.fk_report_section = rs.rowid)";
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->errors[] = $this->db->lasterror();
				return -1;
			}
		}

		return 1;
	}

	/**
	 * Seed default preventive maintenance report template fields.
	 *
	 * @param	int	$entity	Current entity
	 * @return	int			1 if OK, <0 if KO
	 */
	private function seedMaintenanceReportTemplateFields($entity)
	{
		$template = 'preventive_maintenance';
		$rows = array(
			array('section' => 'GENERAL_INFORMATION', 'service' => '', 'code' => 'GENERAL_INTERVENTION_CONTEXT', 'label' => 'Contexte de l’intervention', 'label_en' => 'Intervention context', 'field_type' => 'textarea', 'scope_type' => 'intervention', 'unit' => '', 'position' => 10),
			array('section' => 'EQUIPMENT_SUMMARY', 'service' => '', 'code' => 'EQUIPMENT_SUMMARY_OBSERVATION', 'label' => 'Synthèse matériel / installation', 'label_en' => 'Equipment / installation summary', 'field_type' => 'textarea', 'scope_type' => 'powerplant', 'unit' => '', 'position' => 20),
			array('section' => 'INSTALLATION_DESCRIPTION', 'service' => '', 'code' => 'INSTALLATION_DESCRIPTION_TEXT', 'label' => 'Descriptif installation', 'label_en' => 'Installation description', 'field_type' => 'textarea', 'scope_type' => 'powerplant', 'unit' => '', 'position' => 30),
			array('section' => 'PANEL_CLEANING', 'service' => 'PANEL_CLEANING', 'code' => 'PANEL_CLEANING_OBSERVATION', 'label' => 'Observation nettoyage panneaux', 'label_en' => 'Panel cleaning observation', 'field_type' => 'textarea', 'scope_type' => 'powerplant', 'unit' => '', 'position' => 910),
			array('section' => 'INVERTER', 'service' => 'INVERTER_CHECK', 'code' => 'INVERTER_DC_SWITCH', 'label' => 'Manipulation interrupteur DC', 'label_en' => 'DC switch operation', 'field_type' => 'boolean', 'scope_type' => 'inverter', 'unit' => '', 'position' => 1010),
			array('section' => 'INVERTER', 'service' => 'INVERTER_CHECK', 'code' => 'INVERTER_VENTILATION_CLEANING', 'label' => 'Nettoyage ventilations / dissipateur thermique', 'label_en' => 'Ventilation / heat sink cleaning', 'field_type' => 'boolean', 'scope_type' => 'inverter', 'unit' => '', 'position' => 1020),
			array('section' => 'INVERTER', 'service' => 'EARTHING_CHECK', 'code' => 'INVERTER_EARTHING_CONTINUITY', 'label' => 'Continuité MALT', 'label_en' => 'Earthing continuity', 'field_type' => 'status', 'scope_type' => 'inverter', 'unit' => '', 'position' => 1030),
			array('section' => 'INVERTER', 'service' => 'INVERTER_CHECK', 'code' => 'INVERTER_USB_MPPT_CAPS', 'label' => 'Présence de bouchons USB + MPPT', 'label_en' => 'USB and MPPT cap presence', 'field_type' => 'boolean', 'scope_type' => 'inverter', 'unit' => '', 'position' => 1040),
			array('section' => 'INVERTER', 'service' => 'VISUAL_INSPECTION', 'code' => 'INVERTER_ENVELOPE_LABELS', 'label' => 'Contrôle enveloppe + étiquette onduleur, MPPT, AC', 'label_en' => 'Inverter enclosure + inverter, MPPT and AC label check', 'field_type' => 'status', 'scope_type' => 'inverter', 'unit' => '', 'position' => 1050),
			array('section' => 'INVERTER', 'service' => 'INVERTER_CHECK', 'code' => 'INVERTER_SCREW_TIGHTENING', 'label' => 'Contrôle serrage des vis sur la structure de l’onduleur', 'label_en' => 'Inverter structure screw tightening check', 'field_type' => 'status', 'scope_type' => 'inverter', 'unit' => '', 'position' => 1060),
			array('section' => 'INVERTER', 'service' => 'INVERTER_CHECK', 'code' => 'INVERTER_START_OPERATION', 'label' => 'Contrôle de fonctionnement de l’onduleur au départ', 'label_en' => 'Inverter start operation check', 'field_type' => 'status', 'scope_type' => 'inverter', 'unit' => '', 'position' => 1070),
			array('section' => 'INVERTER', 'service' => 'INVERTER_CHECK', 'code' => 'INVERTER_START_POWER', 'label' => 'Puissance au départ P =', 'label_en' => 'Start power P =', 'field_type' => 'double', 'scope_type' => 'inverter', 'unit' => 'W', 'position' => 1080),
			array('section' => 'ELECTRICAL_BOX', 'service' => 'VISUAL_INSPECTION', 'code' => 'BOX_VISUAL_ENVELOPE', 'label' => 'Contrôle visuel enveloppe : casse, étanchéité, corrosion', 'label_en' => 'Visual enclosure check: breakage, sealing, corrosion', 'field_type' => 'status', 'scope_type' => 'electrical_box', 'unit' => '', 'position' => 2010),
			array('section' => 'ELECTRICAL_BOX', 'service' => 'ELECTRICAL_BOX_CHECK', 'code' => 'BOX_JOINTS_HINGES_CLEANING', 'label' => 'Nettoyage / lubrification des joints, charnières et mécanismes de fermeture', 'label_en' => 'Cleaning / lubrication of seals, hinges and closing mechanisms', 'field_type' => 'boolean', 'scope_type' => 'electrical_box', 'unit' => '', 'position' => 2020),
			array('section' => 'ELECTRICAL_BOX', 'service' => 'ELECTRICAL_BOX_CHECK', 'code' => 'BOX_CONNECTIONS_VISUAL_CHECK', 'label' => 'Contrôle visuel des raccordements et connexions électriques : casse, échauffement', 'label_en' => 'Visual check of electrical wiring and connections: breakage, heating', 'field_type' => 'status', 'scope_type' => 'electrical_box', 'unit' => '', 'position' => 2030),
			array('section' => 'ELECTRICAL_BOX', 'service' => 'EARTHING_CHECK', 'code' => 'BOX_EARTHING_SIGNAGE', 'label' => 'Contrôle présence mise à la terre et signalétique réglementaire', 'label_en' => 'Earthing and regulatory signage presence check', 'field_type' => 'status', 'scope_type' => 'electrical_box', 'unit' => '', 'position' => 2040),
			array('section' => 'ELECTRICAL_BOX', 'service' => 'SAFETY_CHECK', 'code' => 'BOX_SECTION_SWITCH', 'label' => 'Manipulation de l’interrupteur sectionneur', 'label_en' => 'Switch disconnector operation', 'field_type' => 'boolean', 'scope_type' => 'electrical_box', 'unit' => '', 'position' => 2050),
			array('section' => 'ELECTRICAL_BOX', 'service' => 'SAFETY_CHECK', 'code' => 'BOX_DIFFERENTIAL_SWITCH_TEST', 'label' => 'Test des interrupteurs différentiels', 'label_en' => 'RCD switch test', 'field_type' => 'status', 'scope_type' => 'electrical_box', 'unit' => '', 'position' => 2060),
			array('section' => 'ELECTRICAL_BOX', 'service' => 'SAFETY_CHECK', 'code' => 'BOX_SURGE_PROTECTION_CHECK', 'label' => 'Contrôle des cartouches parafoudre', 'label_en' => 'Surge protection cartridge check', 'field_type' => 'status', 'scope_type' => 'electrical_box', 'unit' => '', 'position' => 2070),
			array('section' => 'ELECTRICAL_BOX', 'service' => 'SAFETY_CHECK', 'code' => 'BOX_EMERGENCY_STOP_CHECK', 'label' => 'Vérification fonctionnement arrêt d’urgence', 'label_en' => 'Emergency stop operation check', 'field_type' => 'status', 'scope_type' => 'electrical_box', 'unit' => '', 'position' => 2080),
			array('section' => 'ROOF', 'service' => 'ROOF_CHECK', 'code' => 'ROOF_FLASHING_CHECK_CLEANING', 'label' => 'Vérification et nettoyage des abergements', 'label_en' => 'Flashing check and cleaning', 'field_type' => 'status', 'scope_type' => 'roof', 'unit' => '', 'position' => 3010),
			array('section' => 'ROOF', 'service' => 'VISUAL_INSPECTION', 'code' => 'ROOF_PANEL_VISUAL_CHECK', 'label' => 'Contrôle des panneaux photovoltaïques', 'label_en' => 'Photovoltaic panel check', 'field_type' => 'status', 'scope_type' => 'roof', 'unit' => '', 'position' => 3020),
			array('section' => 'ROOF', 'service' => 'PANEL_CLEANING', 'code' => 'ROOF_PANEL_CLEANING', 'label' => 'Nettoyage des panneaux photovoltaïques', 'label_en' => 'Photovoltaic panel cleaning', 'field_type' => 'boolean', 'scope_type' => 'roof', 'unit' => '', 'position' => 3030),
			array('section' => 'ROOF', 'service' => 'ROOF_CHECK', 'code' => 'ROOF_PANEL_FIXING_CHECK', 'label' => 'Contrôle du système de fixation des panneaux', 'label_en' => 'Panel fastening system check', 'field_type' => 'status', 'scope_type' => 'roof', 'unit' => '', 'position' => 3040),
			array('section' => 'ROOF', 'service' => 'ROOF_CHECK', 'code' => 'ROOF_CABLE_TRAY_CHECK', 'label' => 'Vérification de l’état des chemins de câble et présence du capot', 'label_en' => 'Cable tray condition and cover presence check', 'field_type' => 'status', 'scope_type' => 'roof', 'unit' => '', 'position' => 3050),
			array('section' => 'ROOF', 'service' => 'ROOF_CHECK', 'code' => 'ROOF_CABLE_FIXING_CHECK', 'label' => 'Contrôle de la fixation des câbles sous modules', 'label_en' => 'Cable fastening check under modules', 'field_type' => 'status', 'scope_type' => 'roof', 'unit' => '', 'position' => 3060),
			array('section' => 'ROOF', 'service' => 'EARTHING_CHECK', 'code' => 'ROOF_EARTHING_CHECK', 'label' => 'Contrôle des mises à la terre', 'label_en' => 'Earthing checks', 'field_type' => 'status', 'scope_type' => 'roof', 'unit' => '', 'position' => 3070),
			array('section' => 'DC_ELECTRICAL_MEASURE', 'service' => 'DC_ELECTRICAL_MEASURE', 'code' => 'DC_STRING_REF', 'label' => 'Référence string', 'label_en' => 'String reference', 'field_type' => 'varchar', 'scope_type' => 'dc_measure', 'unit' => '', 'position' => 4010),
			array('section' => 'DC_ELECTRICAL_MEASURE', 'service' => 'DC_ELECTRICAL_MEASURE', 'code' => 'DC_OPEN_CIRCUIT_VOLTAGE', 'label' => 'Tension à vide DC', 'label_en' => 'DC open circuit voltage', 'field_type' => 'double', 'scope_type' => 'dc_measure', 'unit' => 'V', 'position' => 4020),
			array('section' => 'DC_ELECTRICAL_MEASURE', 'service' => 'DC_ELECTRICAL_MEASURE', 'code' => 'DC_POLARITY_CHECKED', 'label' => 'Polarité contrôlée', 'label_en' => 'Polarity checked', 'field_type' => 'boolean', 'scope_type' => 'dc_measure', 'unit' => '', 'position' => 4030),
			array('section' => 'DC_ELECTRICAL_MEASURE', 'service' => 'DC_ELECTRICAL_MEASURE', 'code' => 'DC_INSULATION_STATUS', 'label' => 'Contrôle isolement', 'label_en' => 'Insulation check', 'field_type' => 'status', 'scope_type' => 'dc_measure', 'unit' => '', 'position' => 4040),
			array('section' => 'DC_ELECTRICAL_MEASURE', 'service' => 'DC_ELECTRICAL_MEASURE', 'code' => 'DC_INSULATION_POSITIVE_GROUND', 'label' => 'Isolement + / terre', 'label_en' => 'Positive / ground insulation', 'field_type' => 'double', 'scope_type' => 'dc_measure', 'unit' => 'MOhm', 'position' => 4050),
			array('section' => 'DC_ELECTRICAL_MEASURE', 'service' => 'DC_ELECTRICAL_MEASURE', 'code' => 'DC_INSULATION_NEGATIVE_GROUND', 'label' => 'Isolement - / terre', 'label_en' => 'Negative / ground insulation', 'field_type' => 'double', 'scope_type' => 'dc_measure', 'unit' => 'MOhm', 'position' => 4060),
			array('section' => 'DC_ELECTRICAL_MEASURE', 'service' => 'DC_ELECTRICAL_MEASURE', 'code' => 'DC_MEASURE_OBSERVATION', 'label' => 'Observation', 'label_en' => 'Observation', 'field_type' => 'text', 'scope_type' => 'dc_measure', 'unit' => '', 'position' => 4070),
			array('section' => 'PRODUCTION_READING', 'service' => 'PRODUCTION_READING', 'code' => 'INVERTER_PRODUCTION_N_MINUS_1', 'label' => 'Relevé production onduleur N-1', 'label_en' => 'Inverter production reading N-1', 'field_type' => 'double', 'scope_type' => 'powerplant', 'unit' => 'kWh', 'position' => 5005),
			array('section' => 'PRODUCTION_READING', 'service' => 'PRODUCTION_READING', 'code' => 'INVERTER_PRODUCTION', 'label' => 'Relevé production onduleur N', 'label_en' => 'Inverter production reading N', 'field_type' => 'double', 'scope_type' => 'powerplant', 'unit' => 'kWh', 'position' => 5010),
			array('section' => 'PRODUCTION_READING', 'service' => 'PRODUCTION_READING', 'code' => 'PRODUCTION_INDEX_N_MINUS_1', 'label' => 'Index production N-1', 'label_en' => 'Production index N-1', 'field_type' => 'double', 'scope_type' => 'powerplant', 'unit' => 'kWh', 'position' => 5015),
			array('section' => 'PRODUCTION_READING', 'service' => 'PRODUCTION_READING', 'code' => 'PRODUCTION_INDEX', 'label' => 'Index production N', 'label_en' => 'Production index N', 'field_type' => 'double', 'scope_type' => 'powerplant', 'unit' => 'kWh', 'position' => 5020),
			array('section' => 'PRODUCTION_READING', 'service' => 'PRODUCTION_READING', 'code' => 'INJECTION_INDEX_N_MINUS_1', 'label' => 'Index injection N-1', 'label_en' => 'Injection index N-1', 'field_type' => 'double', 'scope_type' => 'powerplant', 'unit' => 'kWh', 'position' => 5025),
			array('section' => 'PRODUCTION_READING', 'service' => 'PRODUCTION_READING', 'code' => 'INJECTION_INDEX', 'label' => 'Index injection N', 'label_en' => 'Injection index N', 'field_type' => 'double', 'scope_type' => 'powerplant', 'unit' => 'kWh', 'position' => 5030),
			array('section' => 'PRODUCTION_READING', 'service' => 'PRODUCTION_READING', 'code' => 'CONSUMPTION_INDEX_N_MINUS_1', 'label' => 'Index consommation N-1', 'label_en' => 'Consumption index N-1', 'field_type' => 'double', 'scope_type' => 'powerplant', 'unit' => 'kWh', 'position' => 5035),
			array('section' => 'PRODUCTION_READING', 'service' => 'PRODUCTION_READING', 'code' => 'CONSUMPTION_INDEX', 'label' => 'Index consommation N', 'label_en' => 'Consumption index N', 'field_type' => 'double', 'scope_type' => 'powerplant', 'unit' => 'kWh', 'position' => 5040),
			array('section' => 'PRODUCTION_READING', 'service' => 'PRODUCTION_READING', 'code' => 'ANNUAL_PRODUCTION_N_MINUS_1', 'label' => 'Production annuelle N-1', 'label_en' => 'Annual production N-1', 'field_type' => 'double', 'scope_type' => 'powerplant', 'unit' => 'kWh', 'position' => 5045),
			array('section' => 'PRODUCTION_READING', 'service' => 'PRODUCTION_READING', 'code' => 'ANNUAL_PRODUCTION', 'label' => 'Production annuelle N', 'label_en' => 'Annual production N', 'field_type' => 'double', 'scope_type' => 'powerplant', 'unit' => 'kWh', 'position' => 5050),
			array('section' => 'PRODUCTION_READING', 'service' => 'PRODUCTION_READING', 'code' => 'SELF_CONSUMPTION_N_MINUS_1', 'label' => 'Autoconsommation N-1', 'label_en' => 'Self-consumption N-1', 'field_type' => 'double', 'scope_type' => 'powerplant', 'unit' => 'kWh', 'position' => 5055),
			array('section' => 'PRODUCTION_READING', 'service' => 'PRODUCTION_READING', 'code' => 'SELF_CONSUMPTION', 'label' => 'Autoconsommation N', 'label_en' => 'Self-consumption N', 'field_type' => 'double', 'scope_type' => 'powerplant', 'unit' => 'kWh', 'position' => 5058),
			array('section' => 'PRODUCTION_READING', 'service' => 'PRODUCTION_READING', 'code' => 'PRODUCTION_READING_OBSERVATION', 'label' => 'Observation', 'label_en' => 'Observation', 'field_type' => 'text', 'scope_type' => 'powerplant', 'unit' => '', 'position' => 5060),
			array('section' => 'GENERAL_OBSERVATIONS', 'service' => '', 'code' => 'GENERAL_OBSERVATIONS_TEXT', 'label' => 'Observations générales', 'label_en' => 'General observations', 'field_type' => 'textarea', 'scope_type' => 'intervention', 'unit' => '', 'position' => 7010),
			array('section' => 'CUSTOMER_SIGNATURE', 'service' => '', 'code' => 'CUSTOMER_SIGNATORY_NAME', 'label' => 'Nom du signataire client', 'label_en' => 'Customer signatory name', 'field_type' => 'varchar', 'scope_type' => 'intervention', 'unit' => '', 'position' => 8010),
			array('section' => 'CUSTOMER_SIGNATURE', 'service' => '', 'code' => 'CUSTOMER_SIGNATURE_CAPTURE', 'label' => 'Signature client', 'label_en' => 'Customer signature', 'field_type' => 'signature', 'scope_type' => 'intervention', 'unit' => '', 'position' => 8020),
			array('section' => 'THERMOGRAPHY', 'service' => 'THERMOGRAPHY', 'code' => 'THERMO_DONE', 'label' => 'Thermographie réalisée', 'label_en' => 'Thermography performed', 'field_type' => 'boolean', 'scope_type' => 'thermography', 'unit' => '', 'position' => 6010),
			array('section' => 'THERMOGRAPHY', 'service' => 'THERMOGRAPHY', 'code' => 'THERMO_DATETIME', 'label' => 'Date et heure du relevé', 'label_en' => 'Reading date and time', 'field_type' => 'datetime', 'scope_type' => 'thermography', 'unit' => '', 'position' => 6020),
			array('section' => 'THERMOGRAPHY', 'service' => 'THERMOGRAPHY', 'code' => 'THERMO_WEATHER', 'label' => 'Conditions météo', 'label_en' => 'Weather conditions', 'field_type' => 'varchar', 'scope_type' => 'thermography', 'unit' => '', 'position' => 6030),
			array('section' => 'THERMOGRAPHY', 'service' => 'THERMOGRAPHY', 'code' => 'THERMO_OUTSIDE_TEMPERATURE', 'label' => 'Température extérieure', 'label_en' => 'Outside temperature', 'field_type' => 'double', 'scope_type' => 'thermography', 'unit' => 'C', 'position' => 6040),
			array('section' => 'THERMOGRAPHY', 'service' => 'THERMOGRAPHY', 'code' => 'THERMO_IRRADIANCE', 'label' => 'Ensoleillement / irradiance', 'label_en' => 'Sunshine / irradiance', 'field_type' => 'varchar', 'scope_type' => 'thermography', 'unit' => '', 'position' => 6050),
			array('section' => 'THERMOGRAPHY', 'service' => 'THERMOGRAPHY', 'code' => 'THERMO_CAMERA', 'label' => 'Caméra utilisée', 'label_en' => 'Camera used', 'field_type' => 'varchar', 'scope_type' => 'thermography', 'unit' => '', 'position' => 6060),
			array('section' => 'THERMOGRAPHY', 'service' => 'THERMOGRAPHY', 'code' => 'THERMO_AREA_REF', 'label' => 'Zone / panneau / string concerné', 'label_en' => 'Related area / panel / string', 'field_type' => 'varchar', 'scope_type' => 'thermography', 'unit' => '', 'position' => 6070),
			array('section' => 'THERMOGRAPHY', 'service' => 'THERMOGRAPHY', 'code' => 'THERMO_MAX_TEMPERATURE', 'label' => 'Température maximale constatée', 'label_en' => 'Maximum observed temperature', 'field_type' => 'double', 'scope_type' => 'thermography', 'unit' => 'C', 'position' => 6080),
			array('section' => 'THERMOGRAPHY', 'service' => 'THERMOGRAPHY', 'code' => 'THERMO_DELTA_TEMPERATURE', 'label' => 'Écart de température', 'label_en' => 'Temperature delta', 'field_type' => 'double', 'scope_type' => 'thermography', 'unit' => 'C', 'position' => 6090),
			array('section' => 'THERMOGRAPHY', 'service' => 'THERMOGRAPHY', 'code' => 'THERMO_ANOMALY', 'label' => 'Anomalie détectée', 'label_en' => 'Anomaly detected', 'field_type' => 'boolean', 'scope_type' => 'thermography', 'unit' => '', 'position' => 6100),
			array('section' => 'THERMOGRAPHY', 'service' => 'THERMOGRAPHY', 'code' => 'THERMO_ANOMALY_TYPE', 'label' => 'Type d’anomalie', 'label_en' => 'Anomaly type', 'field_type' => 'select', 'scope_type' => 'thermography', 'unit' => '', 'position' => 6110),
			array('section' => 'THERMOGRAPHY', 'service' => 'THERMOGRAPHY', 'code' => 'THERMO_CRITICALITY', 'label' => 'Niveau de criticité', 'label_en' => 'Criticality level', 'field_type' => 'select', 'scope_type' => 'thermography', 'unit' => '', 'position' => 6120),
			array('section' => 'THERMOGRAPHY', 'service' => 'THERMOGRAPHY', 'code' => 'THERMO_RECOMMENDATION', 'label' => 'Préconisation', 'label_en' => 'Recommendation', 'field_type' => 'text', 'scope_type' => 'thermography', 'unit' => '', 'position' => 6130),
			array('section' => 'THERMOGRAPHY', 'service' => 'THERMOGRAPHY', 'code' => 'THERMO_VISIBLE_PHOTO', 'label' => 'Photo visible correspondante', 'label_en' => 'Related visible photo', 'field_type' => 'file', 'scope_type' => 'thermography', 'unit' => '', 'position' => 6140),
			array('section' => 'THERMOGRAPHY', 'service' => 'THERMOGRAPHY', 'code' => 'THERMO_THERMAL_PHOTO', 'label' => 'Photo thermographique', 'label_en' => 'Thermal photo', 'field_type' => 'file', 'scope_type' => 'thermography', 'unit' => '', 'position' => 6150),
		);

		$table = $this->db->prefix().'powerplantpv_report_template_field';
		$sectionTable = $this->db->prefix().'c_powerplantpv_report_section';
		$serviceTable = $this->db->prefix().'c_powerplantpv_maintenance_service';
		foreach ($rows as $row) {
			$section = $this->db->escape($row['section']);
			$serviceCode = isset($row['service']) ? (string) $row['service'] : '';
			$service = $this->db->escape($serviceCode);
			$code = $this->db->escape($row['code']);
			$sql = "INSERT INTO ".$table." (entity, report_template_code, fk_report_section, fk_maintenance_service, code, label, label_en, field_type, scope_type, unit, is_required, active, position)";
			$sql .= " SELECT ".$entity.", '".$this->db->escape($template)."', rs.rowid, ".($serviceCode !== '' ? "s.rowid" : "NULL").", '".$code."', '".$this->db->escape($row['label'])."', '".$this->db->escape($row['label_en'])."', '".$this->db->escape($row['field_type'])."', '".$this->db->escape($row['scope_type'])."', '".$this->db->escape($row['unit'])."', 0, 1, ".((int) $row['position']);
			$sql .= " FROM ".$sectionTable." as rs";
			if ($serviceCode !== '') {
				$sql .= " INNER JOIN ".$serviceTable." as s ON s.entity = rs.entity AND s.code = '".$service."'";
			}
			$sql .= " WHERE rs.entity = ".$entity." AND rs.code = '".$section."'";
			$sql .= " AND NOT EXISTS (SELECT 1 FROM ".$table." as tf WHERE tf.entity = ".$entity." AND tf.report_template_code = '".$this->db->escape($template)."' AND tf.code = '".$code."')";
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->errors[] = $this->db->lasterror();
				return -1;
			}
		}

		return 1;
	}

	/**
	 * Seed and migrate the configurable report template engine.
	 *
	 * @param	int	$entity	Current entity
	 * @return	int			1 if OK, <0 if KO
	 */
	private function seedMaintenanceReportTemplateEngineData($entity)
	{
		dol_include_once('/powerplantpv/lib/powerplantpv_reporttemplate.lib.php');

		$result = $this->insertMaintenanceDefaultRow($this->db->prefix().'powerplantpv_report_template', array(
			'entity' => $entity,
			'code' => 'preventive_maintenance',
			'label' => 'Rapport de maintenance préventive',
			'label_en' => 'Preventive maintenance report',
			'description' => 'Modèle préinstallé pour les rapports d’intervention de maintenance préventive.',
			'description_en' => 'Preinstalled template for preventive maintenance intervention reports.',
			'target_element' => 'fichinter',
			'is_default' => 1,
			'active' => 1,
			'position' => 10,
			'date_creation' => $this->db->idate(dol_now()),
		), "entity = ".$entity." AND code = 'preventive_maintenance'");
		if ($result < 0) {
			return -1;
		}

		$templateId = $this->getReportTemplateId($entity, 'preventive_maintenance');
		if ($templateId <= 0) {
			$this->errors[] = 'Unable to fetch preventive maintenance report template';
			return -1;
		}

		$result = $this->migrateMaintenanceReportSections($entity, $templateId);
		if ($result < 0) {
			return -1;
		}
		$result = $this->migrateMaintenanceInterventionNatures($entity, $templateId);
		if ($result < 0) {
			return -1;
		}
		$result = $this->migrateMaintenanceServiceSectionMappings($entity, $templateId);
		if ($result < 0) {
			return -1;
		}
		$result = $this->migrateMaintenanceReportTemplateFields($entity, $templateId);
		if ($result < 0) {
			return -1;
		}
		$result = $this->applyPreventiveTemplateConservativeUpdates($entity, $templateId);
		if ($result < 0) {
			return -1;
		}

		return $this->seedMaintenanceReportFieldOptions($entity, $templateId);
	}

	/**
	 * Return a report template id by code.
	 *
	 * @param	int		$entity	Entity id
	 * @param	string	$code	Template code
	 * @return	int				Template id, 0 if not found, <0 on error
	 */
	private function getReportTemplateId($entity, $code)
	{
		$sql = "SELECT rowid";
		$sql .= " FROM ".$this->db->prefix()."powerplantpv_report_template";
		$sql .= " WHERE entity = ".((int) $entity);
		$sql .= " AND code = '".$this->db->escape($code)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->errors[] = $this->db->lasterror();
			return -1;
		}

		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($obj)) {
			return 0;
		}

		return (int) $obj->rowid;
	}

	/**
	 * Update only legacy default values that are known to come from previous seeds.
	 *
	 * @param	int	$entity		Entity id
	 * @param	int	$templateId	Template id
	 * @return	int				1 if OK, <0 if KO
	 */
	private function applyPreventiveTemplateConservativeUpdates($entity, $templateId)
	{
		$templateTable = $this->db->prefix().'powerplantpv_report_template';
		$fieldTable = $this->db->prefix().'powerplantpv_report_template_field';
		$sectionTable = $this->db->prefix().'powerplantpv_report_template_section';
		$templateWhere = "entity = ".((int) $entity)." AND code = 'preventive_maintenance'";

		$result = $this->updateMaintenanceDefaultValue($templateTable, 'label', 'Rapport de maintenance préventive', $templateWhere, array('Maintenance préventive'));
		if ($result < 0) {
			return -1;
		}
		$result = $this->updateMaintenanceDefaultValue($templateTable, 'label_en', 'Preventive maintenance report', $templateWhere, array('Preventive maintenance'));
		if ($result < 0) {
			return -1;
		}

		$sectionDefaults = array(
			'INVERTER' => array('scope_type' => array('value' => 'inverter', 'old' => array('equipment')), 'equipment_type' => array('value' => '', 'old' => array('INVERTER'))),
			'ELECTRICAL_BOX' => array('scope_type' => array('value' => 'electrical_box', 'old' => array('equipment')), 'equipment_type' => array('value' => '', 'old' => array('DC_BOX'))),
			'DC_ELECTRICAL_MEASURE' => array('scope_type' => array('value' => 'pv_input', 'old' => array('dc_measure')), 'equipment_type' => array('value' => 'INVERTER', 'old' => array('inverter')), 'repeat_mode' => array('value' => 'once_per_powerplant', 'old' => array('dynamic_rows', 'user_defined_lines'))),
		);
		foreach ($sectionDefaults as $sectionCode => $columns) {
			$where = "entity = ".((int) $entity)." AND fk_report_template = ".((int) $templateId)." AND code = '".$this->db->escape($sectionCode)."'";
			foreach ($columns as $column => $definition) {
				$result = $this->updateMaintenanceDefaultValue($sectionTable, $column, $definition['value'], $where, $definition['old']);
				if ($result < 0) {
					return -1;
				}
			}
		}

		$fieldDefaults = array(
			'INVERTER_PRODUCTION' => array('label' => array('value' => 'Relevé production onduleur N', 'old' => array('Relevé production onduleur')), 'label_en' => array('value' => 'Inverter production reading N', 'old' => array('Inverter production reading')), 'scope_type' => array('value' => 'powerplant', 'old' => array('', 'production_reading'))),
			'PRODUCTION_INDEX' => array('label' => array('value' => 'Index production N', 'old' => array('Index production')), 'label_en' => array('value' => 'Production index N', 'old' => array('Production index')), 'scope_type' => array('value' => 'powerplant', 'old' => array('', 'production_reading'))),
			'INJECTION_INDEX' => array('label' => array('value' => 'Index injection N', 'old' => array('Index injection')), 'label_en' => array('value' => 'Injection index N', 'old' => array('Injection index')), 'scope_type' => array('value' => 'powerplant', 'old' => array('', 'production_reading'))),
			'CONSUMPTION_INDEX' => array('label' => array('value' => 'Index consommation N', 'old' => array('Index consommation')), 'label_en' => array('value' => 'Consumption index N', 'old' => array('Consumption index')), 'scope_type' => array('value' => 'powerplant', 'old' => array('', 'production_reading'))),
			'ANNUAL_PRODUCTION' => array('label' => array('value' => 'Production annuelle N', 'old' => array('Production annuelle')), 'label_en' => array('value' => 'Annual production N', 'old' => array('Annual production')), 'scope_type' => array('value' => 'powerplant', 'old' => array('', 'production_reading'))),
		);
		foreach ($fieldDefaults as $fieldCode => $columns) {
			$where = "entity = ".((int) $entity)." AND fk_report_template = ".((int) $templateId)." AND code = '".$this->db->escape($fieldCode)."'";
			foreach ($columns as $column => $definition) {
				$result = $this->updateMaintenanceDefaultValue($fieldTable, $column, $definition['value'], $where, $definition['old']);
				if ($result < 0) {
					return -1;
				}
			}
		}

		$optionDefaults = array(
			array('field' => 'THERMO_ANOMALY_TYPE', 'option' => 'CELL_DEFECT', 'column' => 'label', 'value' => 'Cellule défectueuse', 'old' => array('Défaut cellule')),
			array('field' => 'THERMO_ANOMALY_TYPE', 'option' => 'CELL_DEFECT', 'column' => 'label_en', 'value' => 'Defective cell', 'old' => array('Cell defect')),
			array('field' => 'THERMO_ANOMALY_TYPE', 'option' => 'CONNECTION_HEATING', 'column' => 'label', 'value' => 'Connectique échauffée', 'old' => array('Échauffement connexion')),
			array('field' => 'THERMO_ANOMALY_TYPE', 'option' => 'CONNECTION_HEATING', 'column' => 'label_en', 'value' => 'Heated connector', 'old' => array('Connection heating')),
		);
		foreach ($optionDefaults as $optionDefault) {
			$result = $this->updatePreventiveFieldOptionDefaultValue(
				(int) $entity,
				(int) $templateId,
				(string) $optionDefault['field'],
				(string) $optionDefault['option'],
				(string) $optionDefault['column'],
				(string) $optionDefault['value'],
				$optionDefault['old']
			);
			if ($result < 0) {
				return -1;
			}
		}

		return 1;
	}

	/**
	 * Update one value only when the current value still matches a known old default.
	 *
	 * @param	string				$table		Table name with prefix
	 * @param	string				$column		Column name
	 * @param	mixed				$newValue	New value
	 * @param	string				$where		SQL predicate
	 * @param	array<int,mixed>	$oldValues	Known old values
	 * @return	int								1 if OK, <0 if KO
	 */
	private function updateMaintenanceDefaultValue($table, $column, $newValue, $where, $oldValues)
	{
		if (empty($oldValues)) {
			return 1;
		}

		$columnSql = $this->db->sanitize($column);
		$oldConditions = array();
		foreach ($oldValues as $oldValue) {
			if ($oldValue === null) {
				$oldConditions[] = $columnSql." IS NULL";
			} else {
				$oldConditions[] = $columnSql." = ".$this->maintenanceSqlValue($oldValue);
			}
		}

		$sql = "UPDATE ".$this->db->sanitize($table);
		$sql .= " SET ".$columnSql." = ".$this->maintenanceSqlValue($newValue);
		$sql .= " WHERE ".$where;
		$sql .= " AND (".implode(' OR ', $oldConditions).")";
		if (!$this->db->query($sql)) {
			$this->errors[] = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Update one option value for a preventive template field.
	 *
	 * @param	int					$entity		Entity id
	 * @param	int					$templateId	Template id
	 * @param	string				$fieldCode	Field code
	 * @param	string				$optionCode	Option code
	 * @param	string				$column		Column name
	 * @param	mixed				$newValue	New value
	 * @param	array<int,mixed>	$oldValues	Known old values
	 * @return	int								1 if OK, <0 if KO
	 */
	private function updatePreventiveFieldOptionDefaultValue($entity, $templateId, $fieldCode, $optionCode, $column, $newValue, $oldValues)
	{
		$fieldTable = $this->db->prefix().'powerplantpv_report_template_field';
		$optionTable = $this->db->prefix().'powerplantpv_report_template_field_option';
		$where = "entity = ".((int) $entity);
		$where .= " AND code = '".$this->db->escape($optionCode)."'";
		$where .= " AND fk_report_template_field IN (";
		$where .= "SELECT rowid FROM ".$fieldTable;
		$where .= " WHERE entity = ".((int) $entity);
		$where .= " AND fk_report_template = ".((int) $templateId);
		$where .= " AND code = '".$this->db->escape($fieldCode)."'";
		$where .= ")";

		return $this->updateMaintenanceDefaultValue($optionTable, $column, $newValue, $where, $oldValues);
	}

	/**
	 * Migrate PR1 dictionary sections into template sections.
	 *
	 * @param	int	$entity		Entity id
	 * @param	int	$templateId	Template id
	 * @return	int				1 if OK, <0 if KO
	 */
	private function migrateMaintenanceReportSections($entity, $templateId)
	{
		$table = $this->db->prefix().'powerplantpv_report_template_section';
		$sourceTable = $this->db->prefix().'c_powerplantpv_report_section';

		$sql = "SELECT rowid, code, label, label_en, description, description_en, scope_type, equipment_type, repeat_mode, is_required, active, position";
		$sql .= " FROM ".$sourceTable;
		$sql .= " WHERE entity = ".((int) $entity);
		$sql .= " ORDER BY position ASC, rowid ASC";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->errors[] = $this->db->lasterror();
			return -1;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$scope = powerplantpvReportTemplateNormalizeScopeType((string) $obj->scope_type);
			if (!array_key_exists($scope, powerplantpvReportTemplateScopeTypes())) {
				$scope = 'free_line';
			}
			$equipment = powerplantpvReportTemplateNormalizeEquipmentType((string) $obj->equipment_type);
			if (!array_key_exists($equipment, powerplantpvReportTemplateEquipmentTypes())) {
				$equipment = '';
			}
			$repeat = powerplantpvReportTemplateNormalizeRepeatMode((string) $obj->repeat_mode);
			if (!array_key_exists($repeat, powerplantpvReportTemplateRepeatModes())) {
				$repeat = 'once';
			}

			$result = $this->insertMaintenanceDefaultRow($table, array(
				'entity' => $entity,
				'fk_report_template' => $templateId,
				'code' => (string) $obj->code,
				'label' => (string) $obj->label,
				'label_en' => (string) $obj->label_en,
				'description' => (string) $obj->description,
				'description_en' => (string) $obj->description_en,
				'scope_type' => $scope,
				'equipment_type' => $equipment,
				'repeat_mode' => $repeat,
				'is_required' => (int) $obj->is_required,
				'visible_form' => 1,
				'visible_pdf' => 1,
				'active' => (int) $obj->active,
				'position' => (int) $obj->position,
				'date_creation' => $this->db->idate(dol_now()),
			), "entity = ".$entity." AND fk_report_template = ".$templateId." AND code = '".$this->db->escape((string) $obj->code)."'");
			if ($result < 0) {
				$this->db->free($resql);
				return -1;
			}
		}
		$this->db->free($resql);

		return 1;
	}

	/**
	 * Link intervention natures to the migrated template.
	 *
	 * @param	int	$entity		Entity id
	 * @param	int	$templateId	Template id
	 * @return	int				1 if OK, <0 if KO
	 */
	private function migrateMaintenanceInterventionNatures($entity, $templateId)
	{
		$sql = "UPDATE ".$this->db->prefix()."c_powerplantpv_intervention_nature";
		$sql .= " SET fk_report_template = ".((int) $templateId);
		$sql .= ", report_template_code = CASE WHEN report_template_code IS NULL OR report_template_code = '' THEN 'preventive_maintenance' ELSE report_template_code END";
		$sql .= " WHERE entity = ".((int) $entity);
		$sql .= " AND code = 'PREVENTIVE_MAINTENANCE'";
		$sql .= " AND (report_template_code = 'preventive_maintenance' OR report_template_code IS NULL OR report_template_code = '')";
		$sql .= " AND (fk_report_template IS NULL OR fk_report_template = 0)";
		if (!$this->db->query($sql)) {
			$this->errors[] = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Migrate service to section mappings to the template section table.
	 *
	 * @param	int	$entity		Entity id
	 * @param	int	$templateId	Template id
	 * @return	int				1 if OK, <0 if KO
	 */
	private function migrateMaintenanceServiceSectionMappings($entity, $templateId)
	{
		$mappingTable = $this->db->prefix().'powerplantpv_maintenance_service_section';
		$sourceSectionTable = $this->db->prefix().'c_powerplantpv_report_section';
		$templateSectionTable = $this->db->prefix().'powerplantpv_report_template_section';

		$sql = "SELECT m.rowid, ts.rowid as fk_report_template_section";
		$sql .= " FROM ".$mappingTable." as m";
		$sql .= " INNER JOIN ".$sourceSectionTable." as rs ON rs.rowid = m.fk_report_section AND rs.entity = m.entity";
		$sql .= " INNER JOIN ".$templateSectionTable." as ts ON ts.entity = m.entity AND ts.fk_report_template = ".((int) $templateId)." AND ts.code = rs.code";
		$sql .= " WHERE m.entity = ".((int) $entity);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->errors[] = $this->db->lasterror();
			return -1;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$update = "UPDATE ".$mappingTable;
			$update .= " SET fk_report_template = ".((int) $templateId);
			$update .= ", fk_report_template_section = ".((int) $obj->fk_report_template_section);
			$update .= " WHERE rowid = ".((int) $obj->rowid);
			$update .= " AND entity = ".((int) $entity);
			if (!$this->db->query($update)) {
				$this->db->free($resql);
				$this->errors[] = $this->db->lasterror();
				return -1;
			}
		}
		$this->db->free($resql);

		return 1;
	}

	/**
	 * Migrate PR1 fields to the normalized template and section references.
	 *
	 * @param	int	$entity		Entity id
	 * @param	int	$templateId	Template id
	 * @return	int				1 if OK, <0 if KO
	 */
	private function migrateMaintenanceReportTemplateFields($entity, $templateId)
	{
		$fieldTable = $this->db->prefix().'powerplantpv_report_template_field';
		$sourceSectionTable = $this->db->prefix().'c_powerplantpv_report_section';
		$templateSectionTable = $this->db->prefix().'powerplantpv_report_template_section';

		$sql = "SELECT f.rowid, f.field_type, f.scope_type, ts.rowid as fk_report_template_section";
		$sql .= " FROM ".$fieldTable." as f";
		$sql .= " INNER JOIN ".$sourceSectionTable." as rs ON rs.rowid = f.fk_report_section AND rs.entity = f.entity";
		$sql .= " INNER JOIN ".$templateSectionTable." as ts ON ts.entity = f.entity AND ts.fk_report_template = ".((int) $templateId)." AND ts.code = rs.code";
		$sql .= " WHERE f.entity = ".((int) $entity);
		$sql .= " AND f.report_template_code = 'preventive_maintenance'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->errors[] = $this->db->lasterror();
			return -1;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$fieldType = powerplantpvReportTemplateNormalizeFieldType((string) $obj->field_type);
			if (!array_key_exists($fieldType, powerplantpvReportTemplateFieldTypes())) {
				$fieldType = 'text';
			}
			$scope = powerplantpvReportTemplateNormalizeScopeType((string) $obj->scope_type);
			if ($scope !== '' && !array_key_exists($scope, powerplantpvReportTemplateScopeTypes())) {
				$scope = '';
			}

			$update = "UPDATE ".$fieldTable;
			$update .= " SET fk_report_template = ".((int) $templateId);
			$update .= ", fk_report_template_section = ".((int) $obj->fk_report_template_section);
			$update .= ", field_type = '".$this->db->escape($fieldType)."'";
			$update .= ", scope_type = '".$this->db->escape($scope)."'";
			$update .= ", date_creation = COALESCE(date_creation, '".$this->db->idate(dol_now())."')";
			$update .= " WHERE rowid = ".((int) $obj->rowid);
			$update .= " AND entity = ".((int) $entity);
			if (!$this->db->query($update)) {
				$this->db->free($resql);
				$this->errors[] = $this->db->lasterror();
				return -1;
			}
		}
		$this->db->free($resql);

		return 1;
	}

	/**
	 * Seed default field options.
	 *
	 * @param	int	$entity		Entity id
	 * @param	int	$templateId	Template id
	 * @return	int				1 if OK, <0 if KO
	 */
	private function seedMaintenanceReportFieldOptions($entity, $templateId)
	{
		$conformityOptions = array(
			array('code' => 'CONFORME', 'label' => 'Conforme', 'label_en' => 'Compliant', 'position' => 10),
			array('code' => 'NON_CONFORME', 'label' => 'Non conforme', 'label_en' => 'Non-compliant', 'position' => 20),
			array('code' => 'SO', 'label' => 'Sans objet', 'label_en' => 'Not applicable', 'position' => 30),
		);
		$yesNoOptions = array(
			array('code' => 'YES', 'label' => 'Oui', 'label_en' => 'Yes', 'position' => 10),
			array('code' => 'NO', 'label' => 'Non', 'label_en' => 'No', 'position' => 20),
		);
		$criticalityOptions = array(
			array('code' => 'LOW', 'label' => 'Faible', 'label_en' => 'Low', 'position' => 10),
			array('code' => 'MEDIUM', 'label' => 'Moyenne', 'label_en' => 'Medium', 'position' => 20),
			array('code' => 'HIGH', 'label' => 'Élevée', 'label_en' => 'High', 'position' => 30),
			array('code' => 'CRITICAL', 'label' => 'Critique', 'label_en' => 'Critical', 'position' => 40),
		);
		$thermographyOptions = array(
			array('code' => 'HOT_SPOT', 'label' => 'Point chaud', 'label_en' => 'Hot spot', 'position' => 10),
			array('code' => 'CELL_DEFECT', 'label' => 'Cellule défectueuse', 'label_en' => 'Defective cell', 'position' => 20),
			array('code' => 'BYPASS_DIODE_SUSPECT', 'label' => 'Diode bypass suspecte', 'label_en' => 'Suspected bypass diode', 'position' => 30),
			array('code' => 'CONNECTION_HEATING', 'label' => 'Connectique échauffée', 'label_en' => 'Heated connector', 'position' => 40),
			array('code' => 'SHADING', 'label' => 'Ombrage', 'label_en' => 'Shading', 'position' => 50),
			array('code' => 'SOILING', 'label' => 'Encrassement', 'label_en' => 'Soiling', 'position' => 60),
			array('code' => 'PERFORMANCE_DIFFERENCE', 'label' => 'Différence de performance', 'label_en' => 'Performance difference', 'position' => 70),
			array('code' => 'OTHER', 'label' => 'Autre', 'label_en' => 'Other', 'position' => 999),
		);

		$fieldTable = $this->db->prefix().'powerplantpv_report_template_field';
		$sql = "SELECT rowid, code, field_type";
		$sql .= " FROM ".$fieldTable;
		$sql .= " WHERE entity = ".((int) $entity);
		$sql .= " AND fk_report_template = ".((int) $templateId);
		$sql .= " AND active = 1";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->errors[] = $this->db->lasterror();
			return -1;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$options = array();
			if ((string) $obj->field_type === 'conformity_so_valid_obs') {
				$options = $conformityOptions;
			} elseif ((string) $obj->field_type === 'yesno') {
				$options = $yesNoOptions;
			} elseif ((string) $obj->code === 'THERMO_CRITICALITY') {
				$options = $criticalityOptions;
			} elseif ((string) $obj->code === 'THERMO_ANOMALY_TYPE') {
				$options = $thermographyOptions;
			}
			if (!empty($options)) {
				$result = $this->seedFieldOptions((int) $entity, (int) $obj->rowid, $options);
				if ($result < 0) {
					$this->db->free($resql);
					return -1;
				}
			}
		}
		$this->db->free($resql);

		return 1;
	}

	/**
	 * Seed options for a field.
	 *
	 * @param	int						$entity	Entity id
	 * @param	int						$fieldId	Field id
	 * @param	array<int,array<string,mixed>>	$options	Options
	 * @return	int								1 if OK, <0 if KO
	 */
	private function seedFieldOptions($entity, $fieldId, $options)
	{
		$table = $this->db->prefix().'powerplantpv_report_template_field_option';
		foreach ($options as $option) {
			$result = $this->insertMaintenanceDefaultRow($table, array(
				'entity' => $entity,
				'fk_report_template_field' => $fieldId,
				'code' => (string) $option['code'],
				'label' => (string) $option['label'],
				'label_en' => (string) $option['label_en'],
				'active' => 1,
				'position' => (int) $option['position'],
				'date_creation' => $this->db->idate(dol_now()),
			), "entity = ".((int) $entity)." AND fk_report_template_field = ".((int) $fieldId)." AND code = '".$this->db->escape((string) $option['code'])."'");
			if ($result < 0) {
				return -1;
			}
		}

		return 1;
	}

	/**
	 * Return PowerPlantPV business triggers handled by Agenda auto events.
	 *
	 * @return	array<int,array<string,int|string>>	Trigger definitions
	 */
	private function getPowerPlantActionTriggers()
	{
		return array(
			array('code' => 'POWERPLANTPV_POWERPLANT_CREATE', 'label' => 'PowerPlantTriggerCreate', 'description' => 'PowerPlantTriggerCreateDesc', 'rang' => 45000400),
			array('code' => 'POWERPLANTPV_POWERPLANT_MODIFY', 'label' => 'PowerPlantTriggerModify', 'description' => 'PowerPlantTriggerModifyDesc', 'rang' => 45000401),
			array('code' => 'POWERPLANTPV_POWERPLANT_DELETE', 'label' => 'PowerPlantTriggerDelete', 'description' => 'PowerPlantTriggerDeleteDesc', 'rang' => 45000402),
			array('code' => 'POWERPLANTPV_POWERPLANT_VALIDATE', 'label' => 'PowerPlantTriggerValidate', 'description' => 'PowerPlantTriggerValidateDesc', 'rang' => 45000403),
			array('code' => 'POWERPLANTPV_POWERPLANT_UNVALIDATE', 'label' => 'PowerPlantTriggerUnvalidate', 'description' => 'PowerPlantTriggerUnvalidateDesc', 'rang' => 45000404),
			array('code' => 'POWERPLANTPV_POWERPLANT_CANCEL', 'label' => 'PowerPlantTriggerCancel', 'description' => 'PowerPlantTriggerCancelDesc', 'rang' => 45000405),
			array('code' => 'POWERPLANTPV_POWERPLANT_REOPEN', 'label' => 'PowerPlantTriggerReopen', 'description' => 'PowerPlantTriggerReopenDesc', 'rang' => 45000406),
			array('code' => 'POWERPLANTPV_POWERPLANT_SENTBYMAIL', 'label' => 'PowerPlantTriggerSentByMail', 'description' => 'PowerPlantTriggerSentByMailDesc', 'rang' => 45000407),
			array('code' => 'POWERPLANTPV_POWERPLANT_INSERVICE', 'label' => 'PowerPlantTriggerInService', 'description' => 'PowerPlantTriggerInServiceDesc', 'rang' => 45000408),
			array('code' => 'POWERPLANTPV_POWERPLANT_OUTOFSERVICE', 'label' => 'PowerPlantTriggerOutOfService', 'description' => 'PowerPlantTriggerOutOfServiceDesc', 'rang' => 45000409),
			array('code' => 'POWERPLANTPV_POWERPLANT_COMP_MODIFY', 'label' => 'PowerPlantCompTriggerModify', 'description' => 'PowerPlantCompTriggerModifyDesc', 'rang' => 45000410),
			array('code' => 'POWERPLANTPV_POWERPLANT_COMP_REPLACE', 'label' => 'PowerPlantCompTriggerReplace', 'description' => 'PowerPlantCompTriggerReplaceDesc', 'rang' => 45000411),
			array('code' => 'POWERPLANTPV_POWERPLANT_COMP_INSERVICE', 'label' => 'PowerPlantCompTriggerInService', 'description' => 'PowerPlantCompTriggerInServiceDesc', 'rang' => 45000412),
			array('code' => 'POWERPLANTPV_POWERPLANT_COMP_OUTOFSERVICE', 'label' => 'PowerPlantCompTriggerOutOfService', 'description' => 'PowerPlantCompTriggerOutOfServiceDesc', 'rang' => 45000413),
			array('code' => 'POWERPLANTPV_POWERPLANT_COMP_SERIAL', 'label' => 'PowerPlantCompTriggerSerial', 'description' => 'PowerPlantCompTriggerSerialDesc', 'rang' => 45000414),
			array('code' => 'POWERPLANTPV_POWERPLANT_COMP_COMMISSIONING', 'label' => 'PowerPlantCompTriggerCommissioning', 'description' => 'PowerPlantCompTriggerCommissioningDesc', 'rang' => 45000415),
			array('code' => 'POWERPLANTPV_POWERPLANT_COMP_SERIAL_IMPORT', 'label' => 'PowerPlantCompTriggerSerialImport', 'description' => 'PowerPlantCompTriggerSerialImportDesc', 'rang' => 45000416),
		);
	}

	/**
	 * Return attestation business triggers handled by Agenda auto events and Notifications.
	 *
	 * @return	array<int,array<string,int|string>>	Trigger definitions
	 */
	private function getAttestationActionTriggers()
	{
		return array(
			array('code' => 'POWERPLANTPV_ATTESTATION_CREATE', 'label' => 'AttestationTriggerCreate', 'description' => 'AttestationTriggerCreateDesc', 'rang' => 45000430),
			array('code' => 'POWERPLANTPV_ATTESTATION_VALIDATE', 'label' => 'AttestationTriggerValidate', 'description' => 'AttestationTriggerValidateDesc', 'rang' => 45000431),
			array('code' => 'POWERPLANTPV_ATTESTATION_GENERATEPDF', 'label' => 'AttestationTriggerGeneratePdf', 'description' => 'AttestationTriggerGeneratePdfDesc', 'rang' => 45000432),
			array('code' => 'POWERPLANTPV_ATTESTATION_SENDSIGN', 'label' => 'AttestationTriggerSendSign', 'description' => 'AttestationTriggerSendSignDesc', 'rang' => 45000433),
			array('code' => 'POWERPLANTPV_ATTESTATION_SIGN', 'label' => 'AttestationTriggerSign', 'description' => 'AttestationTriggerSignDesc', 'rang' => 45000434),
			array('code' => 'POWERPLANTPV_ATTESTATION_CANCEL', 'label' => 'AttestationTriggerCancel', 'description' => 'AttestationTriggerCancelDesc', 'rang' => 45000435),
			array('code' => 'POWERPLANTPV_ATTESTATION_DELETE', 'label' => 'AttestationTriggerDelete', 'description' => 'AttestationTriggerDeleteDesc', 'rang' => 45000436),
		);
	}

	/**
	 * Return SQL statements that register PowerPlantPV business triggers.
	 *
	 * @return	string[]	SQL statements
	 */
	private function getPowerPlantActionTriggerSql()
	{
		global $langs;

		$langs->load('powerplantpv@powerplantpv');

		$sql = array();
		$table = $this->db->prefix().'c_action_trigger';
		$elementtype = $this->db->escape('powerplant@powerplantpv');

		foreach ($this->getPowerPlantActionTriggers() as $trigger) {
			$code = $this->db->escape($trigger['code']);
			$label = $this->db->escape($langs->transnoentitiesnoconv($trigger['label']));
			$description = $this->db->escape($langs->transnoentitiesnoconv($trigger['description']));
			$rang = (int) $trigger['rang'];

			$sql[] = "UPDATE ".$table." SET label = '".$label."', description = '".$description."', elementtype = '".$elementtype."', rang = ".$rang." WHERE code = '".$code."'";
			$sql[] = "INSERT INTO ".$table." (code, label, description, elementtype, rang) SELECT '".$code."', '".$label."', '".$description."', '".$elementtype."', ".$rang." WHERE NOT EXISTS (SELECT 1 FROM ".$table." WHERE code = '".$code."')";
		}

		return $sql;
	}

	/**
	 * Return SQL statements that register attestation business triggers.
	 *
	 * @return	string[]	SQL statements
	 */
	private function getAttestationActionTriggerSql()
	{
		global $langs;

		$langs->load('powerplantpv@powerplantpv');

		$sql = array();
		$table = $this->db->prefix().'c_action_trigger';
		$elementtype = $this->db->escape('attestation@powerplantpv');

		foreach ($this->getAttestationActionTriggers() as $trigger) {
			$code = $this->db->escape($trigger['code']);
			$label = $this->db->escape($langs->transnoentitiesnoconv($trigger['label']));
			$description = $this->db->escape($langs->transnoentitiesnoconv($trigger['description']));
			$rang = (int) $trigger['rang'];

			$sql[] = "UPDATE ".$table." SET label = '".$label."', description = '".$description."', elementtype = '".$elementtype."', rang = ".$rang." WHERE code = '".$code."'";
			$sql[] = "INSERT INTO ".$table." (code, label, description, elementtype, rang) SELECT '".$code."', '".$label."', '".$description."', '".$elementtype."', ".$rang." WHERE NOT EXISTS (SELECT 1 FROM ".$table." WHERE code = '".$code."')";
		}

		return $sql;
	}

	/**
	 * Return SQL statements that remove legacy manual attestation Agenda duplicates.
	 *
	 * @return	string[]	SQL statements
	 */
	private function getAttestationAgendaDuplicateCleanupSql()
	{
		$sql = array();
		$table = $this->db->prefix().'actioncomm';
		$elementtype = $this->db->escape('attestation@powerplantpv');
		$legacytype = $this->db->escape('AC_OTH_AUTO');

		$labelsbytrigger = array(
			'POWERPLANTPV_ATTESTATION_CREATE' => array(
				'POWERPLANTPV_ATTESTATION_CREATE',
				'CREATEInDolibarr',
				'Attestation créée',
				'Attestation created',
			),
			'POWERPLANTPV_ATTESTATION_VALIDATE' => array(
				'POWERPLANTPV_ATTESTATION_VALIDATE',
				'VALIDATEInDolibarr',
				'Attestation validée',
				'Attestation validated',
			),
			'POWERPLANTPV_ATTESTATION_GENERATEPDF' => array(
				'POWERPLANTPV_ATTESTATION_GENERATEPDF',
				'GENERATEPDFInDolibarr',
				'PDF d\'attestation généré',
				'Attestation PDF generated',
			),
			'POWERPLANTPV_ATTESTATION_SENDSIGN' => array(
				'POWERPLANTPV_ATTESTATION_SENDSIGN',
				'SENDSIGNInDolibarr',
				'Attestation envoyée en signature',
				'Attestation sent for signature',
			),
			'POWERPLANTPV_ATTESTATION_SIGN' => array(
				'POWERPLANTPV_ATTESTATION_SIGN',
				'SIGNInDolibarr',
				'Attestation signée',
				'Attestation signed',
			),
			'POWERPLANTPV_ATTESTATION_CANCEL' => array(
				'POWERPLANTPV_ATTESTATION_CANCEL',
				'CANCELInDolibarr',
				'Attestation annulée',
				'Attestation canceled',
			),
			'POWERPLANTPV_ATTESTATION_DELETE' => array(
				'POWERPLANTPV_ATTESTATION_DELETE',
				'DELETEInDolibarr',
				'Attestation supprimée',
				'Attestation deleted',
			),
		);

		foreach ($labelsbytrigger as $triggercode => $labels) {
			$quotedlabels = array();
			foreach (array_unique($labels) as $label) {
				$quotedlabels[] = "'".$this->db->escape($label)."'";
			}

			$nativecode = $this->db->escape('AC_'.$triggercode);
			$delete = "DELETE oldevent FROM ".$table." AS oldevent";
			$delete .= " INNER JOIN ".$table." AS nativeevent";
			$delete .= " ON nativeevent.fk_element = oldevent.fk_element";
			$delete .= " AND nativeevent.elementtype = oldevent.elementtype";
			$delete .= " AND nativeevent.entity = oldevent.entity";
			$delete .= " AND nativeevent.code = '".$nativecode."'";
			$delete .= " WHERE oldevent.elementtype = '".$elementtype."'";
			$delete .= " AND oldevent.code = '".$legacytype."'";
			$delete .= " AND oldevent.fk_element > 0";
			$delete .= " AND oldevent.id <> nativeevent.id";
			$delete .= " AND oldevent.label IN (".implode(', ', $quotedlabels).")";
			$sql[] = $delete;
		}

		return $sql;
	}

	/**
	 * Return SQL statements that register PowerPlant contact types.
	 *
	 * @return	string[]	SQL statements
	 */
	private function getPowerPlantContactTypeSql()
	{
		$sql = array();
		$table = $this->db->prefix().'c_type_contact';
		$element = $this->db->escape('powerplant');
		$module = $this->db->escape('powerplantpv');
		$contacttypes = array(
			array('source' => 'internal', 'code' => 'CENTPV_INTERNAL_SALES', 'label' => 'Responsable commercial', 'position' => 10),
			array('source' => 'internal', 'code' => 'CENTPV_INTERNAL_ENGINEER', 'label' => 'Chargé d’étude', 'position' => 20),
			array('source' => 'internal', 'code' => 'CENTPV_INTERNAL_ADMIN', 'label' => 'Responsable administratif', 'position' => 30),
			array('source' => 'internal', 'code' => 'CENTPV_INTERNAL_WORKS_MANAGER', 'label' => 'Conducteur de travaux', 'position' => 40),
			array('source' => 'internal', 'code' => 'CENTPV_INTERNAL_PURCHASING', 'label' => 'Responsable achats', 'position' => 50),
			array('source' => 'internal', 'code' => 'CENTPV_INTERNAL_COMMISSIONING', 'label' => 'Responsable mise en service', 'position' => 60),
			array('source' => 'internal', 'code' => 'CENTPV_INTERNAL_MAINTENANCE', 'label' => 'Responsable maintenance', 'position' => 70),
			array('source' => 'internal', 'code' => 'CENTPV_INTERNAL_QUALITY', 'label' => 'Référent qualité', 'position' => 80),
			array('source' => 'internal', 'code' => 'CENTPV_INTERNAL_INSTALL_TECH', 'label' => 'Technicien d’installation', 'position' => 90),
			array('source' => 'internal', 'code' => 'CENTPV_INTERNAL_ELECTRICAL_TECH', 'label' => 'Technicien électricien', 'position' => 100),
			array('source' => 'internal', 'code' => 'CENTPV_INTERNAL_COMMISSION_TECH', 'label' => 'Technicien mise en service', 'position' => 110),
			array('source' => 'internal', 'code' => 'CENTPV_INTERNAL_MAINTENANCE_TECH', 'label' => 'Technicien maintenance', 'position' => 120),
			array('source' => 'internal', 'code' => 'CENTPV_INTERNAL_ROOFER', 'label' => 'Technicien toiture', 'position' => 130),
			array('source' => 'external', 'code' => 'CENTPV_EXTERNAL_CLIENT_OWNER', 'label' => 'Maître d’ouvrage', 'position' => 10),
			array('source' => 'external', 'code' => 'CENTPV_EXTERNAL_CLIENT_TECH', 'label' => 'Contact technique client', 'position' => 20),
			array('source' => 'external', 'code' => 'CENTPV_EXTERNAL_CLIENT_ADMIN', 'label' => 'Contact administratif client', 'position' => 30),
			array('source' => 'external', 'code' => 'CENTPV_EXTERNAL_BUILDING_OWNER', 'label' => 'Propriétaire du bâtiment', 'position' => 40),
			array('source' => 'external', 'code' => 'CENTPV_EXTERNAL_SITE_OPERATOR', 'label' => 'Exploitant du site', 'position' => 50),
			array('source' => 'external', 'code' => 'CENTPV_EXTERNAL_INSTALLER', 'label' => 'Installateur', 'position' => 60),
			array('source' => 'external', 'code' => 'CENTPV_EXTERNAL_ELECTRICIAN', 'label' => 'Électricien', 'position' => 70),
			array('source' => 'external', 'code' => 'CENTPV_EXTERNAL_CONTROL_OFFICE', 'label' => 'Bureau de contrôle', 'position' => 80),
			array('source' => 'external', 'code' => 'CENTPV_EXTERNAL_SPS', 'label' => 'Coordinateur SPS', 'position' => 90),
			array('source' => 'external', 'code' => 'CENTPV_EXTERNAL_GRID_OPERATOR', 'label' => 'Gestionnaire de réseau', 'position' => 100),
			array('source' => 'external', 'code' => 'CENTPV_EXTERNAL_ENERGY_BUYER', 'label' => 'Acheteur d’énergie', 'position' => 110),
			array('source' => 'external', 'code' => 'CENTPV_EXTERNAL_MODULE_SUPPLIER', 'label' => 'Fournisseur modules', 'position' => 120),
			array('source' => 'external', 'code' => 'CENTPV_EXTERNAL_INV_SUPPLIER', 'label' => 'Fournisseur onduleurs', 'position' => 130),
			array('source' => 'external', 'code' => 'CENTPV_EXTERNAL_MOUNT_SUPPLIER', 'label' => 'Fournisseur structure', 'position' => 140),
			array('source' => 'external', 'code' => 'CENTPV_EXTERNAL_MONITORING', 'label' => 'Supervision / monitoring', 'position' => 150),
			array('source' => 'external', 'code' => 'CENTPV_EXTERNAL_INSURANCE', 'label' => 'Assureur', 'position' => 160),
			array('source' => 'external', 'code' => 'CENTPV_EXTERNAL_ARCHITECT', 'label' => 'Architecte / maître d’œuvre', 'position' => 170),
			array('source' => 'external', 'code' => 'CENTPV_EXTERNAL_URBANISM', 'label' => 'Service urbanisme', 'position' => 180),
			array('source' => 'external', 'code' => 'CENTPV_EXTERNAL_FIRE_SAFETY', 'label' => 'Sécurité incendie / SDIS', 'position' => 190),
			array('source' => 'external', 'code' => 'CENTPV_EXTERNAL_MAINTAINER', 'label' => 'Mainteneur externe', 'position' => 200),
			array('source' => 'external', 'code' => 'CENTPV_EXTERNAL_INSTALL_TECH', 'label' => 'Technicien d’installation externe', 'position' => 210),
			array('source' => 'external', 'code' => 'CENTPV_EXTERNAL_ELECTRICAL_TECH', 'label' => 'Technicien électricien externe', 'position' => 220),
			array('source' => 'external', 'code' => 'CENTPV_EXTERNAL_COMMISSION_TECH', 'label' => 'Technicien mise en service externe', 'position' => 230),
			array('source' => 'external', 'code' => 'CENTPV_EXTERNAL_MAINTENANCE_TECH', 'label' => 'Technicien maintenance externe', 'position' => 240),
			array('source' => 'external', 'code' => 'CENTPV_EXTERNAL_ROOFER', 'label' => 'Technicien toiture externe', 'position' => 250),
		);

		foreach ($contacttypes as $contacttype) {
			$source = $this->db->escape($contacttype['source']);
			$code = $this->db->escape($contacttype['code']);
			$label = $this->db->escape($contacttype['label']);
			$position = (int) $contacttype['position'];

			$sql[] = "UPDATE ".$table." SET libelle = '".$label."', active = 1, module = '".$module."', position = ".$position." WHERE element = '".$element."' AND source = '".$source."' AND code = '".$code."'";
			$sql[] = "INSERT INTO ".$table." (element, source, code, libelle, active, module, position) SELECT '".$element."', '".$source."', '".$code."', '".$label."', 1, '".$module."', ".$position." WHERE NOT EXISTS (SELECT 1 FROM ".$table." WHERE element = '".$element."' AND source = '".$source."' AND code = '".$code."')";
		}

		return $sql;
	}

	/**
	 *	Function called when module is disabled.
	 *	Remove from database constants, boxes and permissions from Dolibarr database.
	 *	Data directories are not deleted
	 *
	 *	@param	string		$options	Options when enabling module ('', 'noboxes')
	 *	@return	int<-1,1>				1 if OK, <=0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();

		$result = $this->_remove($sql, $options);
		if ($result <= 0) {
			return $result;
		}

		$result = $this->unregisterMulticompanyExternalSharing();
		if ($result < 0) {
			return -1;
		}

		return $result;
	}
}
