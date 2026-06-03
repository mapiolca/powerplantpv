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
 *	\file		core/modules/powerplantpv/doc/pdf_centralepv_powerplant.modules.php
 *	\ingroup	powerplantpv
 *	\brief		PDF document model for a photovoltaic power plant synthesis
 */

dol_include_once('/powerplantpv/core/modules/powerplantpv/modules_powerplant.php');
dol_include_once('/powerplantpv/lib/powerplantpv_powerplant.lib.php');
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';


/**
 *	Class to manage PDF template centralepv_powerplant
 */
class pdf_centralepv_powerplant extends ModelePDFPowerPlant
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var int The environment ID when using a multicompany module
	 */
	public $entity;

	/**
	 * @var string model display name
	 */
	public $name;

	/**
	 * @var string model description
	 */
	public $description;

	/**
	 * @var int Save the generated file as the main doc when using this template
	 */
	public $update_main_doc_field;

	/**
	 * @var string document type
	 */
	public $type;

	/**
	 * @var array{0:int,1:int} Minimum version of PHP required by module
	 */
	public $phpmin = array(8, 0);

	/**
	 * @var string Dolibarr version of the loaded document
	 */
	public $version = 'dolibarr';

	/**
	 * @var Societe Object that emits
	 */
	public $emetteur;

	/**
	 * @var int Support company logo option
	 */
	public $option_logo = 1;

	/**
	 * @var int Support multilanguage option
	 */
	public $option_multilang = 1;

	/**
	 * @var int Support draft watermark option
	 */
	public $option_draft_watermark = 1;

	/**
	 * @var int Default font size
	 */
	protected $defaultFontSize = 9;

	/**
	 * @var float Top Y position for content
	 */
	protected $contentTop = 30;

	/**
	 * @var float Bottom Y position for content
	 */
	protected $contentBottom = 272;

	/**
	 * @var array<string,array<int,int>> PDF color palette
	 */
	protected $colors = array(
		'blue' => array(0, 126, 159),
		'dark' => array(35, 45, 60),
		'muted' => array(105, 115, 125),
		'line' => array(210, 216, 222),
		'soft' => array(245, 248, 250),
	);

	/**
	 * @var PowerPlant|null Current object during generation
	 */
	protected $currentObject;

	/**
	 * @var Translate|null Current output language during generation
	 */
	protected $currentOutputLangs;

	/**
	 * @var int|null Current background template id
	 */
	protected $currentTplidx;

	/**
	 * Constructor
	 *
	 * @param	DoliDB	$db	Database handler
	 */
	public function __construct($db)
	{
		global $langs, $mysoc;

		$langs->loadLangs(array('main', 'companies', 'products', 'powerplantpv@powerplantpv'));

		$this->db = $db;
		$this->name = $langs->trans('PowerPlantPDFCentralePVName');
		$this->description = $langs->trans('PowerPlantPDFCentralePVDescription');
		$this->update_main_doc_field = 1;

		$this->type = 'pdf';
		$formatarray = pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);

		$this->contentTop = max(28, $this->marge_haute + 18);
		$this->contentBottom = $this->page_hauteur - max(22, $this->marge_basse + 16);

		if ($mysoc === null) {
			dol_syslog(get_class($this).'::__construct() Global $mysoc should not be null.'.getCallerInfoString(), LOG_ERR);
			return;
		}

		$this->emetteur = $mysoc;
		if (empty($this->emetteur->country_code)) {
			$this->emetteur->country_code = substr($langs->defaultlang, -2);
		}
	}

	/**
	 * Return model information
	 *
	 * @param	Translate	$langs	Lang output object
	 * @return	string				Description
	 */
	public function info($langs)
	{
		return $langs->trans('PowerPlantPDFCentralePVDescription');
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 * Function to build and write pdf to disk
	 *
	 * @param	PowerPlant	$object				Source object to generate document from
	 * @param	Translate	$outputlangs		Lang output object
	 * @param	string		$srctemplatepath	Full path of source filename for generator using a template file
	 * @param	int<0,1>	$hidedetails		Do not show line details
	 * @param	int<0,1>	$hidedesc			Do not show desc
	 * @param	int<0,1>	$hideref			Do not show ref
	 * @return	int<-1,1>						1 if OK, <=0 if KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		// phpcs:enable
		global $user, $langs, $conf, $hookmanager, $action;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		if (getDolGlobalInt('MAIN_USE_FPDF')) {
			$outputlangs->charset_output = 'ISO-8859-1';
		}

		$langfiles = array('main', 'companies', 'products', 'dict', 'powerplantpv@powerplantpv');
		$outputlangs->loadLangs($langfiles);

		if (getDolGlobalString('POWERPLANTPV_DRAFT_WATERMARK') && $object->status == $object::STATUS_DRAFT) {
			$this->watermark = getDolGlobalString('POWERPLANTPV_DRAFT_WATERMARK');
		}

		if (empty($conf->powerplantpv->dir_output) && empty($conf->powerplantpv->multidir_output[$object->entity ?? $conf->entity])) {
			$this->error = $langs->transnoentities('ErrorConstantNotDefined', 'POWERPLANTPV_OUTPUTDIR');
			return 0;
		}

		$object->fetch_thirdparty();

		$dir = powerplantGetDocumentUploadDir($object);
		$file = $dir.'/'.($object->specimen ? 'SPECIMEN' : dol_sanitizeFileName($object->ref)).'_centrale_pv.pdf';

		if (!file_exists($dir) && dol_mkdir($dir) < 0) {
			$this->error = $langs->transnoentities('ErrorCanNotCreateDir', $dir);
			return 0;
		}

		if (!file_exists($dir)) {
			$this->error = $langs->transnoentities('ErrorCanNotCreateDir', $dir);
			return 0;
		}

		if (!is_object($hookmanager)) {
			include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
			$hookmanager = new HookManager($this->db);
		}
		$hookmanager->initHooks(array('pdfgeneration'));
		$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
		$reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action);
		if ($reshook < 0) {
			$this->error = $hookmanager->error;
			$this->errors = $hookmanager->errors;
			return -1;
		}

		$pdf = pdf_getInstance($this->format);
		'@phan-var-force TCPDI|TCPDF $pdf';
		$default_font_size = pdf_getPDFFontSize($outputlangs);
		$this->defaultFontSize = max(8, $default_font_size - 1);
		$pdf->setAutoPageBreak(false, 0);
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetFont(pdf_getPDFFont($outputlangs));
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
		$pdf->SetSubject($outputlangs->transnoentities('PowerPlantPDFCentralePVTitle'));
		$pdf->SetCreator('Dolibarr '.DOL_VERSION);
		$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
		$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref.' '.$outputlangs->transnoentities('PowerPlantPDFCentralePVTitle')));
		if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
			$pdf->SetCompression(false);
		}

		$tplidx = null;
		if (getDolGlobalString('MAIN_ADD_PDF_BACKGROUND')) {
			$logodir = $conf->mycompany->dir_output;
			if (!empty($conf->mycompany->multidir_output[$object->entity ?? $conf->entity])) {
				$logodir = $conf->mycompany->multidir_output[$object->entity ?? $conf->entity];
			}
			$pagecount = $pdf->setSourceFile($logodir.'/'.getDolGlobalString('MAIN_ADD_PDF_BACKGROUND'));
			if ($pagecount > 0) {
				$tplidx = $pdf->importPage(1);
			}
		}

		$pdf->Open();
		$this->addPage($pdf, $object, $outputlangs, $tplidx);
		$this->currentObject = $object;
		$this->currentOutputLangs = $outputlangs;
		$this->currentTplidx = $tplidx;
		$this->renderDocument($pdf, $object, $outputlangs, $tplidx);
		$this->renderFooter($pdf, $object, $outputlangs);

		$hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action);

		$pdf->Close();
		$pdf->Output($file, 'F');
		if (!empty($conf->global->MAIN_UMASK)) {
			dolChmod($file);
		}

		$this->result = array('fullpath' => $file);
		return 1;
	}

	/**
	 * Render all PDF sections
	 *
	 * @param	TCPDF|TCPDI	$pdf			PDF handler
	 * @param	PowerPlant	$object			PowerPlant
	 * @param	Translate	$outputlangs	Output language
	 * @param	int|null	$tplidx			Background template id
	 * @return	void
	 */
	protected function renderDocument(&$pdf, $object, $outputlangs, $tplidx = null)
	{
		$data = $this->buildDataset($object, $outputlangs);

		$this->renderMainTitle($pdf, $object, $outputlangs);

		$siteRows = $this->filterRows(array(
			array($outputlangs->transnoentities('PowerPlantPDFReference'), $object->ref),
			array($outputlangs->transnoentities('PowerPlantPDFLabel'), $object->label),
			array($outputlangs->transnoentities('PowerPlantPDFThirdParty'), $data['thirdparty']),
			array($outputlangs->transnoentities('PowerPlantPDFAddress'), $data['address']),
			array($outputlangs->transnoentities('PowerPlantPDFStatusPowerPlant'), $data['status']),
			array($outputlangs->transnoentities('PowerPlantPDFCommissioningDate'), $data['commissioning_date']),
			array($outputlangs->transnoentities('PowerPlantPDFDescription'), $object->description),
		));
		$this->renderSection($pdf, $object, $outputlangs, $tplidx, 'PowerPlantPDFSectionSite', $siteRows);

		$accessRows = $this->filterRows(array(
			array($outputlangs->transnoentities('PowerPlantPDFAccessInstructions'), $object->access_instructions),
		));
		$this->renderSection($pdf, $object, $outputlangs, $tplidx, 'PowerPlantPDFSectionAccess', $accessRows);

		$connectionRows = $this->filterRows(array(
			array($outputlangs->transnoentities('PowerPlantPDFPrmPdl'), $object->prm_pdl_number),
			array($outputlangs->transnoentities('PowerPlantPDFConnectionContractPower'), $this->formatNumber($object->connection_contract_power, 2, $outputlangs)),
			array($outputlangs->transnoentities('PowerPlantPDFConnectionType'), $object->connection_type),
			array($outputlangs->transnoentities('PowerPlantPDFEnedisCommissioningDate'), $this->formatDate($object->enedis_commissioning_date, $outputlangs)),
			array($outputlangs->transnoentities('PowerPlantPDFConnectionRequestNumber'), $object->connection_request_number),
			array($outputlangs->transnoentities('PowerPlantPDFT0ObtentionDate'), $this->formatDate($object->t0_obtention_date, $outputlangs)),
		));
		$this->renderSection($pdf, $object, $outputlangs, $tplidx, 'PowerPlantPDFSectionConnection', $connectionRows);

		$operationRows = $this->filterRows(array(
			array($outputlangs->transnoentities('PowerPlantPDFInstalledPower'), $this->formatNumber($object->installed_power, 2, $outputlangs)),
			array($outputlangs->transnoentities('PowerPlantPDFBuybackContractNumber'), $object->buyback_contract_number),
			array($outputlangs->transnoentities('PowerPlantPDFBuybackTariff'), $object->buyback_tariff),
		));
		$this->renderSection($pdf, $object, $outputlangs, $tplidx, 'PowerPlantPDFSectionOperation', $operationRows);

		if (!empty($data['contacts'])) {
			$this->renderSectionTitle($pdf, $outputlangs->transnoentities('PowerPlantPDFSectionContacts'));
			$this->renderSimpleTable($pdf, $data['contacts'], array(
				$outputlangs->transnoentities('PowerPlantPDFRole') => 42,
				$outputlangs->transnoentities('PowerPlantPDFName') => 58,
				$outputlangs->transnoentities('PowerPlantPDFPhone') => 35,
				$outputlangs->transnoentities('PowerPlantPDFEmail') => 0,
			));
		}

		if (!empty($data['modules']) || !empty($data['inverters'])) {
			$this->renderSectionTitle($pdf, $outputlangs->transnoentities('PowerPlantPDFSectionMaterial'));
			if (!empty($data['modules'])) {
				$this->renderSubTitle($pdf, $outputlangs->transnoentities('PowerPlantPDFModules'));
				$this->renderSimpleTable($pdf, $data['modules'], array(
					$outputlangs->transnoentities('PowerPlantPDFProduct') => 70,
					$outputlangs->transnoentities('PowerPlantPDFQuantity') => 18,
					$outputlangs->transnoentities('PowerPlantPDFPower') => 28,
					$outputlangs->transnoentities('PowerPlantPDFSerialNumber') => 42,
					$outputlangs->transnoentities('PowerPlantPDFStatus') => 0,
				));
			}
			if (!empty($data['inverters'])) {
				$this->renderSubTitle($pdf, $outputlangs->transnoentities('PowerPlantPDFInverters'));
				$this->renderSimpleTable($pdf, $data['inverters'], array(
					$outputlangs->transnoentities('PowerPlantPDFProduct') => 70,
					$outputlangs->transnoentities('PowerPlantPDFQuantity') => 18,
					$outputlangs->transnoentities('PowerPlantPDFPower') => 38,
					$outputlangs->transnoentities('PowerPlantPDFSerialNumber') => 42,
					$outputlangs->transnoentities('PowerPlantPDFStatus') => 0,
				));
			}
		}

		if (!empty($data['composition'])) {
			$this->renderSectionTitle($pdf, $outputlangs->transnoentities('PowerPlantPDFSectionComposition'));
			$this->renderSimpleTable($pdf, $data['composition'], array(
				$outputlangs->transnoentities('PowerPlantPDFCategory') => 34,
				$outputlangs->transnoentities('PowerPlantPDFProduct') => 62,
				$outputlangs->transnoentities('PowerPlantPDFQuantity') => 18,
				$outputlangs->transnoentities('PowerPlantPDFSerialNumber') => 38,
				$outputlangs->transnoentities('PowerPlantPDFDate') => 22,
				$outputlangs->transnoentities('PowerPlantPDFStatus') => 0,
			));
		}

		if (!empty($data['image'])) {
			$this->renderSectionTitle($pdf, $outputlangs->transnoentities('PowerPlantPDFSectionPhoto'));
			$this->renderImage($pdf, $data['image'], $object, $outputlangs, $tplidx);
		}
	}

	/**
	 * Render a simple key-value section
	 *
	 * @param	TCPDF|TCPDI	$pdf			PDF handler
	 * @param	PowerPlant	$object			PowerPlant
	 * @param	Translate	$outputlangs	Output language
	 * @param	int|null	$tplidx			Background template id
	 * @param	string		$titlekey		Section title translation key
	 * @param	array<int,array{0:string,1:mixed}>	$rows	Rows
	 * @return	void
	 */
	protected function renderSection(&$pdf, $object, $outputlangs, $tplidx, $titlekey, $rows)
	{
		if (empty($rows)) {
			return;
		}

		$this->renderSectionTitle($pdf, $outputlangs->transnoentities($titlekey));
		$this->renderKeyValueTable($pdf, $rows);
	}

	/**
	 * Build the dataset used by the PDF
	 *
	 * @param	PowerPlant	$object			PowerPlant
	 * @param	Translate	$outputlangs	Output language
	 * @return	array<string,mixed>			Dataset
	 */
	protected function buildDataset($object, $outputlangs)
	{
		$components = $this->fetchComponents($object, $outputlangs);

		return array(
			'thirdparty' => (!empty($object->thirdparty->name) ? $object->thirdparty->name : ''),
			'address' => $this->formatPowerPlantAddress($object, $outputlangs),
			'status' => $this->statusText($object, $outputlangs),
			'commissioning_date' => $this->formatDate($object->commissioning_date, $outputlangs),
			'contacts' => $this->fetchContacts($object),
			'modules' => $this->buildMaterialRows($components, array('MODULE'), $outputlangs, 'module'),
			'inverters' => $this->buildMaterialRows($components, array('ONDULE'), $outputlangs, 'inverter'),
			'composition' => $this->buildCompositionRows($components, $outputlangs),
			'image' => $this->findFirstImage($object),
		);
	}

	/**
	 * Fetch material composition rows
	 *
	 * @param	PowerPlant	$object			PowerPlant
	 * @param	Translate	$outputlangs	Output language
	 * @return	array<int,array<string,mixed>>	Rows
	 */
	protected function fetchComponents($object, $outputlangs)
	{
		$rows = array();
		$powerplantid = (int) $object->id;
		if ($powerplantid <= 0) {
			return $rows;
		}

		$entity = (!empty($object->entity) ? (int) $object->entity : 1);
		$productEntities = $this->sanitizeEntityList(getEntity('product'));

		$sql = "SELECT c.rowid, c.fk_product, c.fk_status, c.qty, c.serial_number, c.commissioning_date,";
		$sql .= " p.ref as product_ref, p.label as product_label,";
		$sql .= " pe.categorie_photovoltaique as fk_categorypv, pvcat.code as category_code, pvcat.label as category_label";
		$sql .= " FROM ".$this->db->prefix()."powerplantpv_powerplantcomp as c";
		$sql .= " LEFT JOIN ".$this->db->prefix()."product as p ON p.rowid = c.fk_product";
		$sql .= " LEFT JOIN ".$this->db->prefix()."product_extrafields as pe ON pe.fk_object = c.fk_product";
		$sql .= " LEFT JOIN ".$this->db->prefix()."c_powerplantpv_categorypv as pvcat ON pvcat.rowid = pe.categorie_photovoltaique";
		$sql .= " WHERE c.fk_powerplant = ".$powerplantid;
		$sql .= " AND c.entity = ".$entity;
		$sql .= " AND (p.rowid IS NULL OR p.entity IN (".$productEntities."))";
		$sql .= " ORDER BY pvcat.code ASC, p.ref ASC, c.rowid ASC";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return $rows;
		}

		$powercache = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$productid = (int) $obj->fk_product;
			if ($productid > 0 && !isset($powercache[$productid])) {
				$powercache[$productid] = $this->fetchProductPowerData($productid);
			}
			$productpower = ($productid > 0 ? $powercache[$productid] : array());
			$rows[] = array(
				'id' => (int) $obj->rowid,
				'fk_product' => $productid,
				'product_ref' => (string) $obj->product_ref,
				'product_label' => (string) $obj->product_label,
				'category_code' => (string) $obj->category_code,
				'category_label' => (string) $obj->category_label,
				'qty' => $obj->qty,
				'serial_number' => (string) $obj->serial_number,
				'commissioning_date' => $obj->commissioning_date,
				'status' => $this->componentStatusText((int) $obj->fk_status, $outputlangs),
				'pmax' => (isset($productpower['pmax']) ? $productpower['pmax'] : null),
				'ac_nominal_power' => (isset($productpower['ac_nominal_power']) ? $productpower['ac_nominal_power'] : null),
				'ac_max_power' => (isset($productpower['ac_max_power']) ? $productpower['ac_max_power'] : null),
			);
		}
		$this->db->free($resql);

		return $rows;
	}

	/**
	 * Fetch photovoltaic product power data
	 *
	 * @param	int	$productid	Product id
	 * @return	array<string,mixed>	Data
	 */
	protected function fetchProductPowerData($productid)
	{
		$data = array();
		$productEntities = $this->sanitizeEntityList(getEntity('product'));

		$sql = "SELECT pmax";
		$sql .= " FROM ".$this->db->prefix()."powerplantpv_product_pvpanel";
		$sql .= " WHERE fk_product = ".((int) $productid);
		$sql .= " AND entity IN (".$productEntities.")";
		$sql .= " ORDER BY entity DESC";
		$sql .= " LIMIT 1";
		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			$obj = $this->db->fetch_object($resql);
			$data['pmax'] = $obj->pmax;
		}
		if ($resql) {
			$this->db->free($resql);
		}

		$sql = "SELECT ac_nominal_power, ac_max_power";
		$sql .= " FROM ".$this->db->prefix()."powerplantpv_product_inverter";
		$sql .= " WHERE fk_product = ".((int) $productid);
		$sql .= " AND entity IN (".$productEntities.")";
		$sql .= " ORDER BY entity DESC";
		$sql .= " LIMIT 1";
		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			$obj = $this->db->fetch_object($resql);
			$data['ac_nominal_power'] = $obj->ac_nominal_power;
			$data['ac_max_power'] = $obj->ac_max_power;
		}
		if ($resql) {
			$this->db->free($resql);
		}

		return $data;
	}

	/**
	 * Fetch contacts linked to the power plant
	 *
	 * @param	PowerPlant	$object	PowerPlant
	 * @return	array<int,array<string,string>>	Rows
	 */
	protected function fetchContacts($object)
	{
		$rows = array();
		$contacts = array_merge((array) $object->liste_contact(-1, 'internal'), (array) $object->liste_contact(-1, 'external'));

		foreach ($contacts as $contact) {
			$source = (string) $contact['source'];
			$id = (int) $contact['id'];
			$name = trim((string) $contact['firstname'].' '.(string) $contact['lastname']);
			$email = (string) $contact['email'];
			$phone = '';

			if ($source === 'internal') {
				$tmpuser = new User($this->db);
				if ($id > 0 && $tmpuser->fetch($id) > 0) {
					$name = $tmpuser->getFullName($this->currentOutputLangs);
					$email = (string) $tmpuser->email;
					$phone = $this->firstNonEmpty(array($tmpuser->office_phone, $tmpuser->user_mobile));
				}
			} elseif ($source === 'external') {
				$tmpcontact = new Contact($this->db);
				if ($id > 0 && $tmpcontact->fetch($id) > 0) {
					$name = $tmpcontact->getFullName($this->currentOutputLangs);
					$email = (string) $tmpcontact->email;
					$phone = $this->firstNonEmpty(array($tmpcontact->phone_pro, $tmpcontact->phone_mobile, $tmpcontact->phone_perso));
				}
			}

			$rows[] = array(
				'role' => (string) $contact['libelle'],
				'name' => $name,
				'phone' => $phone,
				'email' => $email,
			);
		}

		return $rows;
	}

	/**
	 * Build material rows from composition
	 *
	 * @param	array<int,array<string,mixed>>	$components	Components
	 * @param	string[]	$codes		Category codes
	 * @param	Translate	$outputlangs	Output language
	 * @param	string		$type		Material type
	 * @return	array<int,array<string,string>>	Rows
	 */
	protected function buildMaterialRows($components, $codes, $outputlangs, $type)
	{
		$rows = array();
		foreach ($components as $component) {
			if (!in_array($component['category_code'], $codes, true)) {
				continue;
			}
			$power = '';
			if ($type === 'module' && $this->isFilled($component['pmax'])) {
				$power = $this->formatNumber($component['pmax'], 0, $outputlangs).' W';
			}
			if ($type === 'inverter') {
				$values = array();
				if ($this->isFilled($component['ac_nominal_power'])) {
					$values[] = $outputlangs->transnoentities('PowerPlantPDFACNominalPowerShort').' '.$this->formatNumber($component['ac_nominal_power'], 2, $outputlangs);
				}
				if ($this->isFilled($component['ac_max_power'])) {
					$values[] = $outputlangs->transnoentities('PowerPlantPDFACMaxPowerShort').' '.$this->formatNumber($component['ac_max_power'], 2, $outputlangs);
				}
				$power = implode(' / ', $values);
			}
			$rows[] = array(
				'product' => $this->productLabel($component),
				'qty' => $this->formatNumber($component['qty'], 2, $outputlangs),
				'power' => $power,
				'serial' => $component['serial_number'],
				'status' => $component['status'],
			);
		}

		return $rows;
	}

	/**
	 * Build complete composition rows
	 *
	 * @param	array<int,array<string,mixed>>	$components	Components
	 * @param	Translate	$outputlangs	Output language
	 * @return	array<int,array<string,string>>	Rows
	 */
	protected function buildCompositionRows($components, $outputlangs)
	{
		$rows = array();
		foreach ($components as $component) {
			$rows[] = array(
				'category' => $this->firstNonEmpty(array($component['category_label'], $component['category_code'])),
				'product' => $this->productLabel($component),
				'qty' => $this->formatNumber($component['qty'], 2, $outputlangs),
				'serial' => $component['serial_number'],
				'date' => $this->formatDate($component['commissioning_date'], $outputlangs),
				'status' => $component['status'],
			);
		}

		return $rows;
	}

	/**
	 * Find the first image attached to the power plant
	 *
	 * @param	PowerPlant	$object	PowerPlant
	 * @return	string				Image full path
	 */
	protected function findFirstImage($object)
	{
		$upload_dir = powerplantGetDocumentUploadDir($object);
		if (!is_dir($upload_dir)) {
			return '';
		}

		$filearray = dol_dir_list($upload_dir, 'files', 1, '(?i)\.(jpe?g|png|gif|webp)$', '(?i)(\.meta|_preview.*\.png|thumbs)', 'name', SORT_ASC, 1);
		foreach ($filearray as $file) {
			$fullname = (string) $file['fullname'];
			if (is_readable($fullname) && @getimagesize($fullname) !== false) {
				return $fullname;
			}
		}

		return '';
	}

	/**
	 * Add a PDF page with header
	 *
	 * @param	TCPDF|TCPDI	$pdf			PDF handler
	 * @param	PowerPlant	$object			PowerPlant
	 * @param	Translate	$outputlangs	Output language
	 * @param	int|null	$tplidx			Background template id
	 * @return	void
	 */
	protected function addPage(&$pdf, $object, $outputlangs, $tplidx = null)
	{
		if ($pdf->getPage() > 0) {
			$this->renderFooter($pdf, $object, $outputlangs);
		}

		$pdf->AddPage();
		if (!empty($tplidx)) {
			$pdf->useTemplate($tplidx);
		}

		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);
		if (!empty($this->watermark)) {
			pdf_watermark($pdf, $outputlangs, $this->page_hauteur, $this->page_largeur, 'mm', dol_escape_htmltag($this->watermark));
		}
		$this->renderHeader($pdf, $object, $outputlangs);
		$pdf->SetY($this->contentTop);
	}

	/**
	 * Render compact document header
	 *
	 * @param	TCPDF|TCPDI	$pdf			PDF handler
	 * @param	PowerPlant	$object			PowerPlant
	 * @param	Translate	$outputlangs	Output language
	 * @return	void
	 */
	protected function renderHeader(&$pdf, $object, $outputlangs)
	{
		global $conf;

		$posy = $this->marge_haute;
		$pdf->SetTextColor($this->colors['dark'][0], $this->colors['dark'][1], $this->colors['dark'][2]);

		if (!getDolGlobalInt('PDF_DISABLE_MYCOMPANY_LOGO') && !empty($this->emetteur->logo)) {
			$logodir = $conf->mycompany->dir_output;
			if (!empty(getMultidirOutput($object, 'mycompany'))) {
				$logodir = getMultidirOutput($object, 'mycompany');
			}
			$logo = $logodir.'/logos/'.(getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO') ? $this->emetteur->logo : $this->emetteur->logo_small);
			if (is_readable($logo)) {
				$pdf->Image($logo, $this->marge_gauche, $posy, 0, min(12, pdf_getHeightForLogo($logo)));
			}
		}

		$pdf->SetFont('', 'B', $this->defaultFontSize + 4);
		$pdf->SetXY($this->marge_gauche + 50, $posy);
		$pdf->Cell($this->contentWidth() - 50, 6, $outputlangs->convToOutputCharset($outputlangs->transnoentities('PowerPlantPDFCentralePVTitle')), 0, 1, 'R');
		$pdf->SetFont('', '', $this->defaultFontSize - 1);
		$pdf->SetX($this->marge_gauche + 50);
		$pdf->Cell($this->contentWidth() - 50, 4, $outputlangs->convToOutputCharset($object->ref), 0, 1, 'R');
		$pdf->SetDrawColor($this->colors['line'][0], $this->colors['line'][1], $this->colors['line'][2]);
		$pdf->Line($this->marge_gauche, $this->contentTop - 3, $this->page_largeur - $this->marge_droite, $this->contentTop - 3);
	}

	/**
	 * Render the document title block
	 *
	 * @param	TCPDF|TCPDI	$pdf			PDF handler
	 * @param	PowerPlant	$object			PowerPlant
	 * @param	Translate	$outputlangs	Output language
	 * @return	void
	 */
	protected function renderMainTitle(&$pdf, $object, $outputlangs)
	{
		$title = $object->ref;
		if (!empty($object->label)) {
			$title .= ' - '.$object->label;
		}

		$this->ensureSpace($pdf, 24, $object, $outputlangs);
		$pdf->SetTextColor($this->colors['blue'][0], $this->colors['blue'][1], $this->colors['blue'][2]);
		$pdf->SetFont('', 'B', $this->defaultFontSize + 7);
		$pdf->MultiCell($this->contentWidth(), 8, $outputlangs->convToOutputCharset($title), 0, 'L');
		$pdf->Ln(2);
	}

	/**
	 * Render a section title
	 *
	 * @param	TCPDF|TCPDI	$pdf	PDF handler
	 * @param	string		$title	Title
	 * @return	void
	 */
	protected function renderSectionTitle(&$pdf, $title)
	{
		$outputlangs = $this->currentOutputLangs;

		$this->ensureSpace($pdf, 14, null, $outputlangs);
		$pdf->Ln(1);
		$pdf->SetTextColor($this->colors['blue'][0], $this->colors['blue'][1], $this->colors['blue'][2]);
		$pdf->SetFont('', 'B', $this->defaultFontSize + 2);
		$pdf->Cell($this->contentWidth(), 6, $this->safeText($title, $outputlangs), 0, 1, 'L');
		$pdf->SetDrawColor($this->colors['blue'][0], $this->colors['blue'][1], $this->colors['blue'][2]);
		$pdf->Line($this->marge_gauche, $pdf->GetY(), $this->page_largeur - $this->marge_droite, $pdf->GetY());
		$pdf->Ln(2);
	}

	/**
	 * Render a subtitle
	 *
	 * @param	TCPDF|TCPDI	$pdf	PDF handler
	 * @param	string		$title	Title
	 * @return	void
	 */
	protected function renderSubTitle(&$pdf, $title)
	{
		$outputlangs = $this->currentOutputLangs;

		$this->ensureSpace($pdf, 10, null, $outputlangs);
		$pdf->SetTextColor($this->colors['dark'][0], $this->colors['dark'][1], $this->colors['dark'][2]);
		$pdf->SetFont('', 'B', $this->defaultFontSize);
		$pdf->Cell($this->contentWidth(), 5, $this->safeText($title, $outputlangs), 0, 1, 'L');
		$pdf->Ln(1);
	}

	/**
	 * Render key-value rows
	 *
	 * @param	TCPDF|TCPDI	$pdf	PDF handler
	 * @param	array<int,array{0:string,1:mixed}>	$rows	Rows
	 * @return	void
	 */
	protected function renderKeyValueTable(&$pdf, $rows)
	{
		$outputlangs = $this->currentOutputLangs;

		$labelw = 52;
		$valuew = $this->contentWidth() - $labelw;
		foreach ($rows as $row) {
			$label = (string) $row[0];
			$value = $this->normalizeValue($row[1]);
			$height = max(6, $this->textHeight($pdf, $value, $valuew, $this->defaultFontSize) + 2);
			$this->ensureSpace($pdf, $height + 1, null, $outputlangs);

			$y = $pdf->GetY();
			$this->drawRect($pdf, $this->marge_gauche, $y, $labelw, $height, $this->colors['soft'], $this->colors['line']);
			$this->drawRect($pdf, $this->marge_gauche + $labelw, $y, $valuew, $height, array(255, 255, 255), $this->colors['line']);
			$pdf->SetTextColor($this->colors['muted'][0], $this->colors['muted'][1], $this->colors['muted'][2]);
			$pdf->SetFont('', 'B', $this->defaultFontSize - 1);
			$pdf->SetXY($this->marge_gauche + 2, $y + 1.5);
			$pdf->MultiCell($labelw - 4, 4, $this->safeText($label, $outputlangs), 0, 'L');
			$pdf->SetTextColor($this->colors['dark'][0], $this->colors['dark'][1], $this->colors['dark'][2]);
			$pdf->SetFont('', '', $this->defaultFontSize);
			$pdf->SetXY($this->marge_gauche + $labelw + 2, $y + 1.5);
			$pdf->MultiCell($valuew - 4, 4, $this->safeText($value, $outputlangs), 0, 'L');
			$pdf->SetY($y + $height);
		}
		$pdf->Ln(1);
	}

	/**
	 * Render a table
	 *
	 * @param	TCPDF|TCPDI	$pdf	PDF handler
	 * @param	array<int,array<string,string>>	$rows	Rows
	 * @param	array<string,float|int>	$columns	Column title => width, 0 means remaining width
	 * @return	void
	 */
	protected function renderSimpleTable(&$pdf, $rows, $columns)
	{
		$outputlangs = $this->currentOutputLangs;

		if (empty($rows)) {
			return;
		}

		$columns = $this->normalizeColumns($columns);
		$this->ensureSpace($pdf, 12, null, $outputlangs);

		$y = $pdf->GetY();
		$x = $this->marge_gauche;
		$pdf->SetFont('', 'B', $this->defaultFontSize - 1);
		foreach ($columns as $title => $width) {
			$this->drawRect($pdf, $x, $y, $width, 7, $this->colors['soft'], $this->colors['line']);
			$pdf->SetXY($x + 1.5, $y + 1.5);
			$pdf->MultiCell($width - 3, 4, $this->safeText($title, $outputlangs), 0, 'L');
			$x += $width;
		}
		$pdf->SetY($y + 7);

		foreach ($rows as $row) {
			$cells = array_values($row);
			$rowheight = 6;
			$i = 0;
			foreach ($columns as $width) {
				$value = (isset($cells[$i]) ? (string) $cells[$i] : '');
				$rowheight = max($rowheight, $this->textHeight($pdf, $value, $width - 3, $this->defaultFontSize - 1) + 2);
				$i++;
			}
			$this->ensureSpace($pdf, $rowheight, null, $outputlangs);

			$y = $pdf->GetY();
			$x = $this->marge_gauche;
			$pdf->SetFont('', '', $this->defaultFontSize - 1);
			$i = 0;
			foreach ($columns as $width) {
				$value = (isset($cells[$i]) ? (string) $cells[$i] : '');
				$this->drawRect($pdf, $x, $y, $width, $rowheight, array(255, 255, 255), $this->colors['line']);
				$pdf->SetXY($x + 1.5, $y + 1.3);
				$pdf->MultiCell($width - 3, 4, $this->safeText($value, $outputlangs), 0, 'L');
				$x += $width;
				$i++;
			}
			$pdf->SetY($y + $rowheight);
		}
		$pdf->Ln(2);
	}

	/**
	 * Render an image
	 *
	 * @param	TCPDF|TCPDI	$pdf			PDF handler
	 * @param	string		$image			Image path
	 * @param	PowerPlant	$object			PowerPlant
	 * @param	Translate	$outputlangs	Output language
	 * @param	int|null	$tplidx			Background template id
	 * @return	void
	 */
	protected function renderImage(&$pdf, $image, $object, $outputlangs, $tplidx = null)
	{
		$maxw = $this->contentWidth();
		$maxh = 105;
		$size = @getimagesize($image);
		if (empty($size[0]) || empty($size[1])) {
			return;
		}

		$ratio = min($maxw / $size[0], $maxh / $size[1]);
		$w = $size[0] * $ratio;
		$h = $size[1] * $ratio;
		$this->ensureSpace($pdf, $h + 4, $object, $outputlangs, $tplidx);

		$x = $this->marge_gauche + (($maxw - $w) / 2);
		$y = $pdf->GetY();
		$pdf->Image($image, $x, $y, $w, $h);
		$pdf->SetY($y + $h + 2);
	}

	/**
	 * Render footer
	 *
	 * @param	TCPDF|TCPDI	$pdf			PDF handler
	 * @param	PowerPlant	$object			PowerPlant
	 * @param	Translate	$outputlangs	Output language
	 * @return	int<0,1>					Footer height
	 */
	protected function renderFooter(&$pdf, $object, $outputlangs)
	{
		$showdetails = !getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS') ? 0 : getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS');
		return pdf_pagefoot($pdf, $outputlangs, 'POWERPLANTPV_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, 0);
	}

	/**
	 * Ensure there is enough vertical space on page
	 *
	 * @param	TCPDF|TCPDI	$pdf			PDF handler
	 * @param	float		$needed			Needed height
	 * @param	PowerPlant|null	$object		PowerPlant
	 * @param	Translate|null	$outputlangs	Output language
	 * @param	int|null	$tplidx			Background template id
	 * @return	void
	 */
	protected function ensureSpace(&$pdf, $needed, $object = null, $outputlangs = null, $tplidx = null)
	{
		if ($pdf->GetY() + $needed <= $this->contentBottom) {
			return;
		}
		if (!is_object($object)) {
			$object = $this->currentObject;
		}
		if (!is_object($outputlangs)) {
			$outputlangs = $this->currentOutputLangs;
		}
		if ($tplidx === null) {
			$tplidx = $this->currentTplidx;
		}
		if (is_object($object) && is_object($outputlangs)) {
			$this->addPage($pdf, $object, $outputlangs, $tplidx);
		} else {
			$pdf->AddPage();
			$pdf->SetY($this->contentTop);
		}
	}

	/**
	 * Draw a rectangle
	 *
	 * @param	TCPDF|TCPDI	$pdf	PDF handler
	 * @param	float		$x		X
	 * @param	float		$y		Y
	 * @param	float		$w		Width
	 * @param	float		$h		Height
	 * @param	int[]		$fill	Fill color
	 * @param	int[]		$border	Border color
	 * @return	void
	 */
	protected function drawRect(&$pdf, $x, $y, $w, $h, $fill, $border)
	{
		$pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
		$pdf->SetDrawColor($border[0], $border[1], $border[2]);
		$pdf->Rect($x, $y, $w, $h, 'DF');
	}

	/**
	 * Return text height
	 *
	 * @param	TCPDF|TCPDI	$pdf	PDF handler
	 * @param	string		$text	Text
	 * @param	float		$width	Width
	 * @param	float		$size	Font size
	 * @return	float				Height
	 */
	protected function textHeight(&$pdf, $text, $width, $size)
	{
		$pdf->SetFont('', '', $size);
		if (!method_exists($pdf, 'getStringHeight')) {
			$lineheight = max(3.5, $size * 0.45);
			$charsperline = max(20, (int) floor($width / max(1, $size * 0.45)));
			$lines = 0;
			foreach (explode("\n", (string) $text) as $line) {
				$lines += max(1, (int) ceil(dol_strlen($line) / $charsperline));
			}

			return max(4, $lines * $lineheight);
		}

		return max(4, (float) $pdf->getStringHeight($width, $text));
	}

	/**
	 * Normalize columns width
	 *
	 * @param	array<string,float|int>	$columns	Columns
	 * @return	array<string,float>				Columns
	 */
	protected function normalizeColumns($columns)
	{
		$fixed = 0;
		$freecount = 0;
		foreach ($columns as $width) {
			if ((float) $width > 0) {
				$fixed += (float) $width;
			} else {
				$freecount++;
			}
		}

		$remaining = max(20, $this->contentWidth() - $fixed);
		$result = array();
		foreach ($columns as $title => $width) {
			$result[$title] = ((float) $width > 0 ? (float) $width : $remaining / max(1, $freecount));
		}

		return $result;
	}

	/**
	 * Return content width
	 *
	 * @return	float	Width
	 */
	protected function contentWidth()
	{
		return $this->page_largeur - $this->marge_gauche - $this->marge_droite;
	}

	/**
	 * Return a safe output text
	 *
	 * @param	string		$text			Text
	 * @param	Translate	$outputlangs	Output language
	 * @return	string						Output text
	 */
	protected function safeText($text, $outputlangs)
	{
		global $langs;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}

		return $outputlangs->convToOutputCharset((string) $text);
	}

	/**
	 * Filter empty key-value rows
	 *
	 * @param	array<int,array{0:string,1:mixed}>	$rows	Rows
	 * @return	array<int,array{0:string,1:mixed}>			Rows
	 */
	protected function filterRows($rows)
	{
		$filtered = array();
		foreach ($rows as $row) {
			if ($this->isFilled($row[1])) {
				$filtered[] = $row;
			}
		}

		return $filtered;
	}

	/**
	 * Normalize a value for display
	 *
	 * @param	mixed	$value	Value
	 * @return	string			Text
	 */
	protected function normalizeValue($value)
	{
		if (is_array($value)) {
			$value = implode("\n", array_filter($value));
		}

		return trim((string) dol_string_nohtmltag((string) $value, 0));
	}

	/**
	 * Test if a value is filled
	 *
	 * @param	mixed	$value	Value
	 * @return	bool			True if filled
	 */
	protected function isFilled($value)
	{
		return !(is_null($value) || trim((string) $value) === '');
	}

	/**
	 * Return the first non-empty value
	 *
	 * @param	array<int,mixed>	$values	Values
	 * @return	string					Value
	 */
	protected function firstNonEmpty($values)
	{
		foreach ($values as $value) {
			if ($this->isFilled($value)) {
				return (string) $value;
			}
		}

		return '';
	}

	/**
	 * Format a number with Dolibarr rules
	 *
	 * @param	mixed		$value			Value
	 * @param	int			$decimals		Decimals
	 * @param	Translate	$outputlangs	Output language
	 * @return	string						Formatted value
	 */
	protected function formatNumber($value, $decimals, $outputlangs)
	{
		if (!$this->isFilled($value)) {
			return '';
		}

		return price($value, 0, $outputlangs, 0, 0, $decimals);
	}

	/**
	 * Format a date
	 *
	 * @param	mixed		$value			Date value
	 * @param	Translate	$outputlangs	Output language
	 * @return	string						Formatted date
	 */
	protected function formatDate($value, $outputlangs)
	{
		if (!$this->isFilled($value)) {
			return '';
		}
		if (is_numeric($value)) {
			return dol_print_date((int) $value, 'day', false, $outputlangs, true);
		}

		return dol_print_date($this->db->jdate($value), 'day', false, $outputlangs, true);
	}

	/**
	 * Format the power plant address
	 *
	 * @param	PowerPlant	$object			PowerPlant
	 * @param	Translate	$outputlangs	Output language
	 * @return	string						Address
	 */
	protected function formatPowerPlantAddress($object, $outputlangs)
	{
		if (empty($object->address) && empty($object->zip) && empty($object->town) && empty($object->fk_country)) {
			return '';
		}

		$addressobject = new stdClass();
		$addressobject->address = $object->address;
		$addressobject->zip = $object->zip;
		$addressobject->town = $object->town;
		$addressobject->country_id = $object->fk_country;
		$addressobject->country_code = '';

		if (!empty($object->fk_country)) {
			$country = getCountry((int) $object->fk_country, 'all', null, $outputlangs, 0);
			if (is_array($country)) {
				$addressobject->country_code = $country['code'];
				$addressobject->country = $country['label'];
			}
		}

		return dol_format_address($addressobject, 1, ', ', $outputlangs);
	}

	/**
	 * Return status text
	 *
	 * @param	PowerPlant	$object			PowerPlant
	 * @param	Translate	$outputlangs	Output language
	 * @return	string						Status
	 */
	protected function statusText($object, $outputlangs)
	{
		if (method_exists($object, 'getLibStatut')) {
			return dol_string_nohtmltag($object->getLibStatut(0), 1);
		}

		return (string) $object->status;
	}

	/**
	 * Return component status text
	 *
	 * @param	int			$status			Status id
	 * @param	Translate	$outputlangs	Output language
	 * @return	string						Status
	 */
	protected function componentStatusText($status, $outputlangs)
	{
		$map = array(
			0 => 'PowerPlantCompStatusInactive',
			4 => 'PowerPlantCompStatusActive',
			6 => 'PowerPlantCompStatusReplaced',
			8 => 'PowerPlantCompStatusOutOfService',
		);
		if (isset($map[$status])) {
			return $outputlangs->transnoentities($map[$status]);
		}

		return (string) $status;
	}

	/**
	 * Return product display label
	 *
	 * @param	array<string,mixed>	$component	Component
	 * @return	string							Label
	 */
	protected function productLabel($component)
	{
		$product = $this->firstNonEmpty(array($component['product_ref'], $component['product_label']));
		if ($this->isFilled($component['product_ref']) && $this->isFilled($component['product_label'])) {
			$product = $component['product_ref'].' - '.$component['product_label'];
		}

		return $product;
	}

	/**
	 * Return a sanitized entity list for SQL IN
	 *
	 * @param	string	$list	Entity list
	 * @return	string			Sanitized list
	 */
	protected function sanitizeEntityList($list)
	{
		global $conf;

		$entities = array();
		foreach (explode(',', (string) $list) as $entity) {
			$entity = trim($entity);
			if ($entity !== '' && preg_match('/^\d+$/', $entity)) {
				$entities[(int) $entity] = (int) $entity;
			}
		}
		if (empty($entities)) {
			$entities[(int) $conf->entity] = (int) $conf->entity;
		}
		ksort($entities, SORT_NUMERIC);

		return implode(',', $entities);
	}
}
