<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\Chart\Tests;

use MediaWiki\Extension\Chart\ChartRenderer;
use MediaWiki\Extension\Chart\JCChartContent;
use MediaWiki\Extension\Chart\SpecialChartWizard;
use MediaWiki\Extension\JsonConfig\JCContentHandler;
use MediaWiki\Extension\JsonConfig\JCSingleton;
use MediaWiki\Request\WebResponse;
use MediaWiki\Status\Status;
use MediaWiki\Tests\Specials\SpecialPageTestBase;
use MediaWiki\Title\Title;
use stdclass;

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
		$namespaces = $this->getServiceContainer()->getContentLanguage()->getNamespaces();
		if ( !array_key_exists( NS_DATA, $namespaces ) ) {
			$this->overrideConfigValue( 'ExtraNamespaces', [
				NS_DATA => 'Data',
				NS_DATA_TALK => 'Data_talk',
			] );
		}
		$this->overrideConfigValue(
			'ContentHandlers',
			$this->getServiceContainer()->getMainConfig()->get( 'ContentHandlers' ) + [
				'Chart.JsonConfig' => JCContentHandler::class,
				'Tabular.JsonConfig' => JCContentHandler::class,
			]
		);
		$mock = $this->createMock( ChartRenderer::class );
		$mock->method( 'renderSVG' )
			->willReturnCallback( static function (
				stdclass $chartDef,
				stdclass $tabularData,
				array $options = []
			): Status {
				return Status::newGood( '<svg></svg>' );
			} );
		$this->setService( 'Chart.ChartRenderer', $mock );
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
		$this->insertJsonConfigPage(
			'Data:Chart input.tab',
			file_get_contents( __DIR__ . '/chart-integration/Chart_input.tab.json' ),
			'Tabular.JsonConfig'
		);
		$chartDefinition = file_get_contents( __DIR__ . '/chart-integration/No_transform_example.chart.json' );
		$title = $this->insertJsonConfigPage(
			'Data:No transform example.chart',
			$chartDefinition,
			JCChartContent::CONTENT_MODEL
		);
		return [ $chartDefinition, $title ];
	}

	private function insertJsonConfigPage( string $pageName, string $text, string $contentModel ): Title {
		$title = Title::newFromText( $pageName );
		$status = $this->editPage(
			$title,
			( new JCContentHandler( $contentModel ) )->unserializeContent( $text ),
			'',
			NS_MAIN,
			$this->getTestSysop()->getAuthority()
		);
		if ( !$status->isOK() ) {
			$this->fail( $status->getWikiText() );
		}
		return $title;
	}
}
