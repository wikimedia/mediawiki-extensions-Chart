<?php

namespace MediaWiki\Extension\Chart\Tests;

use MediaWiki\Deferred\LinksUpdate\LinksTable;
use MediaWiki\Deferred\LinksUpdate\LinksUpdate;
use MediaWiki\Extension\Chart\ChartMetrics;
use MediaWiki\Extension\Chart\JCChartContent;
use MediaWiki\Message\Message;
use MediaWiki\Page\WikiPage;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\Stats\StatsFactory;

/**
 * @covers \MediaWiki\Extension\Chart\ChartMetrics
 */
class ChartMetricsTest extends MediaWikiIntegrationTestCase {
	/** @var StatsFactory&MockObject */
	private StatsFactory $statsFactory;

	private ChartMetrics $chartMetrics;

	protected function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( 'wgNamespaceIds', [ 'data' => 486 ] );
		$this->setMwGlobals( 'wgLanguageCode', 'qqx' );

		$this->mockStatsFactory();

		$this->chartMetrics = new ChartMetrics(
			$this->statsFactory,
			'testwiki'
		);
	}

	public function testTrackChartDefinitionCreated() {
		$title = $this->createMock( Title::class );
		$title->method( 'getNamespace' )->willReturn( 486 );
		$title->method( 'getContentModel' )->willReturn( JCChartContent::CONTENT_MODEL );

		/**
		 * @var WikiPage&MockObject
		 */
		$wikiPage = $this->createMock( WikiPage::class );
		$wikiPage->method( 'getTitle' )->willReturn( $title );

		$statsHelper = StatsFactory::newUnitTestingHelper();
		$this->chartMetrics = new ChartMetrics(
			$statsHelper->getStatsFactory(),
			'testwiki'
		);

		$this->chartMetrics->trackChartDefinitionCreated( $wikiPage, EDIT_NEW );

		$metrics = $statsHelper->consumeAllFormatted();

		$this->assertSame(
			[ 'mediawiki.Chart.definitions_created_total:1|c|#wiki:testwiki' ],
			$metrics
		);
	}

	public function testTrackChartAddedToPage() {
		$title = $this->createMock( Title::class );
		$title->method( 'getContentModel' )->willReturn( CONTENT_MODEL_WIKITEXT );

		$categoryMsgText = Message::newFromSpecifier( 'chart-render-category' )->inContentLanguage()->text();
		$categoryTitle = $this->createMock( Title::class );
		$categoryTitle->method( 'getDBkey' )->willReturn( $categoryMsgText );

		/**
		 * @var LinksUpdate&MockObject
		 */
		$linksUpdate = $this->createMock( LinksUpdate::class );
		$linksUpdate->method( 'getTitle' )->willReturn( $title );
		$linksUpdate->method( 'getPageReferenceIterator' )
			->with( 'categorylinks', LinksTable::INSERTED )
			->willReturn( [ $categoryTitle ] );

		$statsHelper = StatsFactory::newUnitTestingHelper();
		$this->chartMetrics = new ChartMetrics(
			$statsHelper->getStatsFactory(),
			'testwiki'
		);

		$this->chartMetrics->trackChartAddedToPage( $linksUpdate );

		$metrics = $statsHelper->consumeAllFormatted();
		$this->assertSame(
			[ 'mediawiki.Chart.added_to_pages_total:1|c|#wiki:testwiki' ],
			$metrics
		);
	}

	private function mockStatsFactory(): void {
		/** @var StatsFactory&MockObject $statsFactory */
		$statsFactory = $this->createMock( StatsFactory::class );
		$this->statsFactory = $statsFactory;
	}
}
