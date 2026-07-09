<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\Chart\Tests;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Chart\ChartRenderer;
use MediaWiki\Extension\Chart\JCChartContent;
use MediaWiki\Extension\Chart\SpecialChartWizard;
use MediaWiki\Extension\Chart\Tests\Integration\ChartIntegrationTestTrait;
use MediaWiki\Extension\JsonConfig\JCContentHandler;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebResponse;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;
use MediaWiki\Tests\Specials\SpecialPageTestBase;
use MediaWiki\Title\Title;
use stdclass;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * @covers \MediaWiki\Extension\Chart\SpecialChartWizard
 * @group Database
 */
class SpecialChartWizardTest extends SpecialPageTestBase {

	use ChartIntegrationTestTrait;

	protected function setUp(): void {
		parent::setUp();
		$this->configureChartIntegrationTest();
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

	protected function tearDown(): void {
		parent::tearDown();
	}

	/** @inheritDoc */
	protected function newSpecialPage(): SpecialChartWizard {
		return new SpecialChartWizard(
			$this->getServiceContainer()->getWikiPageFactory(),
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getLanguageNameUtils(),
			$this->getServiceContainer()->get( 'Chart.Logger' ),
		);
	}

	public function testChartDefinitionNotFound(): void {
		[ $html ] = $this->executeSpecialPage( 'NonExistentChart' );
		$this->assertStringContainsString( 'chart-error-chart-definition-not-found', $html );
	}

	public function testJsAndStylesOutput(): void {
		[ $chartDefinition, $title ] = $this->insertChart();
		$chartDefinition = json_decode( $chartDefinition, true );
		$sp = $this->newSpecialPage();
		$context = new RequestContext();
		$context->setTitle( $title );
		$sp->setContext( $context );
		$sp->execute( 'No transform example.chart' );
		$jsVars = json_decode( json_encode( $sp->getOutput()->getJsConfigVars() ), true );
		$this->assertSame( $chartDefinition, $jsVars['chartDefinition'] );
		$this->assertSame( 'Creative Commons Zero', $jsVars['chartLicenseNames']['CC0-1.0'] );
		$this->assertArrayHasKey( 'CC0-1.0', $jsVars['chartCopyrightWarnings'] );
		$this->assertStringContainsString(
			'Creative Commons Zero',
			$jsVars['chartCopyrightWarnings']['CC0-1.0']
		);
		$this->assertFalse( $jsVars['chartIsNew'] );
		$this->assertSame( 'Editing Data:No transform example.chart', $sp->getOutput()->getPageTitle() );
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
		[ , $response ] = $this->executeSpecialPage( subPage: 'No transform example.chart', performer:  $performer );
		$this->assertSame( $response->getHeader( 'LOCATION' ), $title->getEditURL() );
	}

	public function testEditExisting() {
		/** @var Title $definitionTitle */
		[ $definitionContents, $definitionTitle ] = $this->insertChart();
		$definitionData = json_decode( $definitionContents, true );
		// Add a @documentation property (not used by ChartWizard.vue).
		$definitionData[ '@documentation' ] = 'https://example.org';
		// Add categories, which didn't originally exist.
		$definitionData[ 'mediaWikiCategories' ] = [
			[ 'name' => 'Example charts' ],
			[ 'name' => 'Bar charts', 'sort' => 'Example' ],
		];
		// Change source dataset to Data:Chart_input.tab (tests normalization).
		$definitionData[ 'source' ] = 'Data:Chart input.tab';
		$request = new FauxRequest( [
			'chartDefinition' => json_encode( $definitionData ),
			'baseRevId' => $definitionTitle->getLatestRevID(),
		], wasPosted: true );
		$this->executeSpecialPage(
			subPage: 'No transform example.chart',
			request: $request,
			performer: $this->getTestSysop()->getAuthority(),
		);
		// Re-fetch contents.
		$revision = $this->getServiceContainer()->getRevisionLookup()
			->getRevisionByTitle( $definitionTitle, 0, IDBAccessObject::READ_LATEST );
		$content = $revision->getContent( SlotRecord::MAIN );
		$this->assertInstanceOf( JCChartContent::class, $content );
		$newDefinition = json_decode( $content->getText(), associative: true );
		$this->assertSame(
			[
				'license' => 'CC0-1.0',
				'mediawikiCategories' => [
					[ 'name' => 'Example charts' ],
					[ 'name' => 'Bar charts', 'sort' => 'Example' ],
				],
				'version' => 1,
				'type' => 'bar',
				'xAxis' => [
					'title' => [ 'en' => 'Day' ],
				],
				'yAxis' => [
					'title' => [ 'en' => 'Temperature (C)' ],
				],
				'source' => 'Chart input.tab',
				'@documentation' => 'https://example.org',
			],
			$newDefinition
		);
	}

	/**
	 * @return array [ Chart definition page content, Title object ]
	 */
	private function insertChart(): array {
		$this->insertJsonConfigPage(
			pageName: 'Data:Chart input.tab',
			text: file_get_contents( __DIR__ . '/../chart-integration/Chart_input.tab.json' ),
			contentModel: 'Tabular.JsonConfig'
		);
		$chartDefinition = file_get_contents( __DIR__ . '/../chart-integration/No_transform_example.chart.json' );
		$title = $this->insertJsonConfigPage(
			pageName: 'Data:No transform example.chart',
			text: $chartDefinition,
			contentModel: JCChartContent::CONTENT_MODEL,
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
