<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\Chart\Tests;

use MediaWiki\Extension\Chart\SpecialChartWizard;
use MediaWiki\Extension\JsonConfig\JCSingleton;
use MediaWiki\Request\WebResponse;
use MediaWiki\Tests\Specials\SpecialPageTestBase;
use MediaWiki\Title\Title;

/**
 * @covers \MediaWiki\Extension\Chart\SpecialChartWizard
 * @group Database
 */
class SpecialChartWizardTest extends SpecialPageTestBase {

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValues( [
			'ChartWizardEnabled' => true,
			'LanguageCode' => 'en',
			'JsonConfigEnableLuaSupport' => true,
			'JsonConfigTransformsEnabled' => true,
			'JsonConfigs' => [
				'Tabular.JsonConfig' => [
					'namespace' => 486,
					'nsName' => 'Data',
					'pattern' => '/.\.tab$/',
					'license' => 'CC0-1.0',
					'isLocal' => true,
					'store' => true,
				],
				'Chart.JsonConfig' => [
					'namespace' => 486,
					'nsName' => 'Data',
					'pattern' => '/.\.chart$/',
					'license' => 'CC0-1.0',
					'isLocal' => true,
					'store' => true,
				]
			],
			'JsonConfigModels' => [
				'Chart.JsonConfig' => 'MediaWiki\Extension\Chart\JCChartContent',
				'Tabular.JsonConfig' => 'JsonConfig\JCTabularContent'
			],
		] );
		JCSingleton::init( true );
	}

	/** @inheritDoc */
	protected function newSpecialPage(): SpecialChartWizard {
		return new SpecialChartWizard(
			$this->getServiceContainer()->getWikiPageFactory(),
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->get( 'Chart.Logger' ),
		);
	}

	public function testChartDefinitionNotFound(): void {
		[ $html ] = $this->executeSpecialPage( 'NonExistentChart' );
		$this->assertStringContainsString( 'chart-error-chart-definition-not-found', $html );
	}

	public function testJsAndStylesOutput(): void {
		[ $chartDefinition ] = $this->insertChart();
		$chartDefinition = json_decode( $chartDefinition, true );
		$sp = $this->newSpecialPage();
		$sp->execute( 'No transform example.chart' );
		$jsVars = json_decode( json_encode( $sp->getOutput()->getJsConfigVars() ), true );
		$this->assertSame( $chartDefinition, $jsVars['chartDefinition'] );
		$this->assertFalse( $jsVars['chartIsNew'] );
		$this->assertContains( 'ext.chart.wizard', $sp->getOutput()->getModules() );
		$this->assertContains( 'ext.chart.wizard.styles', $sp->getOutput()->getModuleStyles() );
	}

	public function testExecuteOnProtectedChart(): void {
		/** @var Title $title */
		[ , $title ] = $this->insertChart();
		$cascade = false;
		$this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( $title )
			->doUpdateRestrictions(
				[ 'edit' => 'sysop' ],
				[ 'edit' => 'infinity' ],
				$cascade,
				'',
				$this->getTestSysop()->getUserIdentity()
			);
		$performer = $this->getTestUser()->getAuthority();
		/** @var WebResponse $response */
		[ , $response ] = $this->executeSpecialPage( 'No transform example.chart', null, null, $performer );
		$this->assertSame( $response->getHeader( 'LOCATION' ), $title->getEditURL() );
	}

	/**
	 * @return array [ Chart definition page content, Title object ]
	 */
	private function insertChart(): array {
		$this->insertPage(
			'Data:Chart input.tab',
			file_get_contents( __DIR__ . '/chart-integration/Chart_input.tab.json' )
		);
		$chartDefinition = file_get_contents( __DIR__ . '/chart-integration/No_transform_example.chart.json' );
		$title = $this->insertPage( 'Data:No transform example.chart', $chartDefinition )['title'];
		return [ $chartDefinition, $title ];
	}
}
