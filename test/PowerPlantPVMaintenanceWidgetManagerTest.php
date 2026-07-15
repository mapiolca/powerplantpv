<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/bootstrap.php';

final class PowerPlantPVMaintenanceWidgetManagerTest extends TestCase
{
	public function testNormalizeLayoutPreservesColumnsAndRemovesDuplicates(): void
	{
		$layout = PowerPlantPVMaintenanceWidgetManager::normalizeLayout(array(
			array('code' => PowerPlantPVMaintenanceWidget::OVERDUE, 'column' => 1, 'position' => 999),
			array('code' => PowerPlantPVMaintenanceWidget::STATUS_SUMMARY, 'column' => 0, 'position' => 1),
			array('code' => PowerPlantPVMaintenanceWidget::OVERDUE, 'column' => 0, 'position' => 1),
			array('code' => 'unknown_widget', 'column' => 0, 'position' => 1),
		));

		$this->assertCount(2, $layout);
		$this->assertSame(array('code' => PowerPlantPVMaintenanceWidget::OVERDUE, 'column' => 1, 'position' => 10), $layout[0]);
		$this->assertSame(array('code' => PowerPlantPVMaintenanceWidget::STATUS_SUMMARY, 'column' => 0, 'position' => 10), $layout[1]);
	}

	public function testDefaultLayoutMatchesLegacyStatistics(): void
	{
		$codes = array_column(PowerPlantPVMaintenanceWidget::getDefaultStatsLayout(), 'code');
		$this->assertSame(array(
			PowerPlantPVMaintenanceWidget::STATUS_SUMMARY,
			PowerPlantPVMaintenanceWidget::BY_NATURE,
			PowerPlantPVMaintenanceWidget::BY_POWERPLANT,
			PowerPlantPVMaintenanceWidget::BY_CUSTOMER,
		), $codes);
	}

	public function testCatalogProvidesHelpForEveryWidget(): void
	{
		$catalog = PowerPlantPVMaintenanceWidget::getCatalog();
		$this->assertCount(14, $catalog);
		foreach ($catalog as $definition) {
			$this->assertNotSame('', $definition['help']);
			$this->assertArrayNotHasKey('help_scope', $definition);
		}
	}

	public function testWidgetHelpKeysExistInEveryDeliveredLanguage(): void
	{
		$helpKeys = array_column(PowerPlantPVMaintenanceWidget::getCatalog(), 'help');
		foreach (array('fr_FR', 'en_US', 'de_DE', 'es_ES', 'it_IT') as $language) {
			$content = file_get_contents(dirname(__DIR__).'/langs/'.$language.'/powerplantpv.lang');
			$this->assertNotFalse($content);
			foreach ($helpKeys as $helpKey) {
				$this->assertMatchesRegularExpression('/^'.preg_quote($helpKey, '/').'\s*=/m', (string) $content, $language.' is missing '.$helpKey);
			}
		}
	}

	public function testNativeWidgetContentsUseDolibarrListTable(): void
	{
		$data = array(
			'counts' => array('to_schedule' => 2, 'scheduled' => 1, 'overdue' => 0, 'covered' => 3, 'not_required' => 4, 'incomplete' => 1),
			'distributions' => array(
				'by_powerplant' => array(array('label' => 'PV-001', 'count' => 2, 'url' => '/powerplant/1')),
			),
		);
		$summary = PowerPlantPVMaintenanceWidget::renderBoxContents(PowerPlantPVMaintenanceWidget::STATUS_SUMMARY, $data);
		$this->assertStringContainsString('<div class="div-table-responsive-no-min">', $summary);
		$this->assertStringContainsString('<table class="noborder centpercent liste">', $summary);
		$this->assertStringContainsString('class="right nowraponall"', $summary);

		$distribution = PowerPlantPVMaintenanceWidget::renderBoxContents(PowerPlantPVMaintenanceWidget::BY_POWERPLANT, $data);
		$this->assertStringNotContainsString('liste_titre', $distribution);
		$this->assertStringContainsString('PV-001', $distribution);
	}

	public function testIndicatorUsesNativeBoxClass(): void
	{
		$output = PowerPlantPVMaintenanceWidget::renderBoxContents(
			PowerPlantPVMaintenanceWidget::TO_SCHEDULE,
			array('counts' => array('to_schedule' => 7))
		);
		$this->assertStringContainsString('boxstatsindicator', $output);
		$this->assertStringNotContainsString('powerplantpv-maintenance-indicator', $output);
	}
}
