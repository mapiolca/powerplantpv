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
		$this->version = '1.0.1';
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
					'ticketcard',
					'category',
					'elementproperties',
					'notification',
				),
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
		$this->phpmin = array(7, 2); // Minimum version of PHP required by module
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
		foreach ($this->getPowerPlantActionTriggers() as $trigger) {
			$this->const[] = array('MAIN_AGENDA_ACTIONAUTO_'.$trigger['code'], 'chaine', '1', $trigger['description'], 0, 'current');
		}

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
			'product:+pvpanel:PVPanelTabTitle:powerplantpv@powerplantpv:$user->hasRight(\'produit\', \'lire\'):/powerplantpv/product_detailedcaracteristics.php?id=__ID__'
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
			'tabname' => array('c_powerplantpv_categorypv'),
			'tablib' => array('PhotovoltaicCategoryDictionary'),
			'tabsql' => array('SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.$this->db->prefix().'c_powerplantpv_categorypv as f'),
			'tabsqlsort' => array('f.label ASC'),
			'tabfield' => array('code,label'),
			'tabfieldvalue' => array('code,label'),
			'tabfieldinsert' => array('code,label'),
			'tabrowid' => array('rowid'),
			'tabcond' => array(isModEnabled('powerplantpv')),
			'tabhelp' => array(array('code' => $langs->trans('CodeTooltipHelp'))),
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
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (0 * 10) + 0 + 1);
		$this->rights[$r][1] = 'PowerPlantPermissionRead';
		$this->rights[$r][4] = 'powerplant';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (0 * 10) + 1 + 1);
		$this->rights[$r][1] = 'PowerPlantPermissionWrite';
		$this->rights[$r][4] = 'powerplant';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (0 * 10) + 2 + 1);
		$this->rights[$r][1] = 'PowerPlantPermissionDelete';
		$this->rights[$r][4] = 'powerplant';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (0 * 10) + 3 + 1);
		$this->rights[$r][1] = 'PowerPlantPermissionInService';
		$this->rights[$r][4] = 'powerplant';
		$this->rights[$r][5] = 'inservice';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (0 * 10) + 4 + 1);
		$this->rights[$r][1] = 'PowerPlantPermissionOutOfService';
		$this->rights[$r][4] = 'powerplant';
		$this->rights[$r][5] = 'outofservice';
		$r++;

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

				$sql = array_merge($sql, array(
					"DELETE FROM ".$this->db->prefix()."document_model WHERE nom = 'standard_".strtolower($myTmpObjectKey)."' AND type = '".$this->db->escape(strtolower($myTmpObjectKey))."' AND entity = ".((int) $conf->entity),
					"INSERT INTO ".$this->db->prefix()."document_model (nom, type, entity) VALUES('standard_".strtolower($myTmpObjectKey)."', '".$this->db->escape(strtolower($myTmpObjectKey))."', ".((int) $conf->entity).")",
					"DELETE FROM ".$this->db->prefix()."document_model WHERE nom = 'generic_".strtolower($myTmpObjectKey)."_odt' AND type = '".$this->db->escape(strtolower($myTmpObjectKey))."' AND entity = ".((int) $conf->entity),
					"INSERT INTO ".$this->db->prefix()."document_model (nom, type, entity) VALUES('generic_".strtolower($myTmpObjectKey)."_odt', '".$this->db->escape(strtolower($myTmpObjectKey))."', ".((int) $conf->entity).")"
				));
			}
		}

		// Migrate legacy agenda links to the canonical Dolibarr element type used by this module.
		$sqlmigrateagenda = "UPDATE ".$this->db->prefix()."actioncomm";
		$sqlmigrateagenda .= " SET elementtype = 'powerplant@powerplantpv'";
		$sqlmigrateagenda .= " WHERE elementtype IN ('powerplant', 'powerplantpv_powerplant')";
		$sqlmigrateagenda .= " AND fk_element IN (SELECT p.rowid FROM ".$this->db->prefix()."powerplantpv_powerplant as p)";
		$sql[] = $sqlmigrateagenda;

		$sql = array_merge($sql, $this->getPowerPlantActionTriggerSql());

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
		$externalmodule = $this->getCurrentMulticompanyExternalSharing();
		if (!is_array($externalmodule)) {
			return -1;
		}

		if (empty($externalmodule['powerplantpv'])) {
			return 1;
		}

		unset($externalmodule['powerplantpv']);

		return $this->saveMulticompanyExternalSharing($externalmodule);
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
		$jsonformat = json_encode($externalmodule, JSON_UNESCAPED_SLASHES);
		if ($jsonformat === false) {
			$this->errors[] = 'Unable to encode MULTICOMPANY_EXTERNAL_MODULES_SHARING JSON value';
			return -1;
		}

		$result = dolibarr_set_const($this->db, 'MULTICOMPANY_EXTERNAL_MODULES_SHARING', $jsonformat, 'chaine', 0, '');
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
		return array(
			'powerplantpv' => array(
				'sharingelements' => array(
					'powerplant' => array(
						'type' => 'element',
						'icon' => 'sun-o',
						'lang' => 'powerplantpv@powerplantpv',
						'enable' => '! empty($conf->powerplantpv->enabled)',
						'input' => array(
							'global' => array(
								'showhide' => true,
								'hide' => true,
								'del' => true,
							),
						),
					),
				),
				'sharingmodulename' => array(
					'powerplant' => 'powerplantpv',
				),
				'dictionary' => array(
					'c_powerplantpv_categorypv' => array(
						'type' => 'dictionary',
						'icon' => 'tags',
						'transkey' => 'PhotovoltaicCategoryDictionary',
						'lang' => 'powerplantpv@powerplantpv',
						'filepath' => '/powerplantpv/sql/llx_c_powerplantpv_categorypv.sql',
					),
				),
			),
		);
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
