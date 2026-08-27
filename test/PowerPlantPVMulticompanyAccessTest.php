<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/bootstrap.php';

final class PowerPlantPVMulticompanyAccessTest extends TestCase
{
	public function testCanonicalTableAndShareUsesBusinessElement(): void
	{
		$powerplant = new stdClass();
		$powerplant->table_element = 'powerplantpv_powerplant';
		$powerplant->element = 'powerplant';
		$this->assertSame('powerplantpv_powerplant&powerplant', powerplantpvGetSharedObjectTableAndShare($powerplant));

		$attestation = new stdClass();
		$attestation->table_element = 'powerplantpv_attestation';
		$attestation->element = 'attestation';
		$this->assertSame('powerplantpv_attestation&attestation', powerplantpvGetSharedObjectTableAndShare($attestation));

		$this->assertSame('', powerplantpvGetSharedObjectTableAndShare(new stdClass()));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function readRouteProvider(): array
	{
		return array(
			'powerplant card' => array('powerplant_card.php'),
			'powerplant composition' => array('powerplant_composition.php'),
			'powerplant location' => array('powerplant_location.php'),
			'powerplant production' => array('powerplant_production_consumption.php'),
			'powerplant maintenance' => array('powerplant_maintenance.php'),
			'powerplant contacts' => array('powerplant_contact.php'),
			'powerplant notes' => array('powerplant_note.php'),
			'powerplant documents' => array('powerplant_document.php'),
			'powerplant serial import' => array('serialimport.php'),
			'powerplant serial list' => array('serialnumber_list.php'),
			'powerplant agenda' => array('powerplant_agenda.php'),
			'attestation card' => array('attestation_card.php'),
			'attestation notes' => array('attestation_note.php'),
			'attestation documents' => array('attestation_document.php'),
			'attestation agenda' => array('attestation_agenda.php'),
		);
	}

	/**
	 * @dataProvider readRouteProvider
	 */
	public function testEveryObjectReadRouteUsesSharedAccessGuard(string $relativePath): void
	{
		$content = file_get_contents(dirname(__DIR__).'/'.$relativePath);
		$this->assertNotFalse($content);
		$this->assertStringContainsString('powerplantpvRequireSharedObjectReadAccess(', (string) $content, $relativePath);
		$this->assertStringNotContainsString('restrictedArea($user, $object->module, $object, $object->table_element,', (string) $content, $relativePath);
	}

	public function testMaintenanceBoxesAreDefinitionsOnlyAndUserSelectionsArePreserved(): void
	{
		$content = file_get_contents(dirname(__DIR__).'/core/modules/modPowerPlantPV.class.php');
		$this->assertNotFalse($content);
		$this->assertSame(3, substr_count((string) $content, "'enabledbydefaulton' => 'Home'"));
		$this->assertSame(14, substr_count((string) $content, "array('file' => 'powerplantpv_box_maintenance_"));
		$this->assertStringContainsString("insert_boxes('newboxdefonly')", (string) $content);
		$this->assertStringContainsString('WHERE fk_user = 0 AND position = 0', (string) $content);
		$this->assertStringContainsString("trim(\$options.' noboxes')", (string) $content);
	}

	public function testPowerPlantRoutesNoLongerBypassDeclaredPermissions(): void
	{
		$routes = array_merge(
			array_column(self::readRouteProvider(), 0),
			array('powerplant_list.php', 'powerplantpvindex.php')
		);
		foreach ($routes as $relativePath) {
			$content = file_get_contents(dirname(__DIR__).'/'.$relativePath);
			$this->assertNotFalse($content);
			$this->assertStringNotContainsString('POWERPLANTPV_ENABLE_PERMISSION_CHECK', (string) $content, $relativePath);
		}
	}
}
