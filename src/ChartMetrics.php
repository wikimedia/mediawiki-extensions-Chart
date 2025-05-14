<?php

namespace MediaWiki\Extension\Chart;

use MediaWiki\Deferred\LinksUpdate\LinksTable;
use MediaWiki\Deferred\LinksUpdate\LinksUpdate;
use MediaWiki\Page\WikiPage;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Stats\StatsFactory;

class ChartMetrics {
	public const STATS_COMPONENT = 'Chart';
	public const CHART_DEFINITION_CREATE = 'definitions_created_total';
	public const CHART_ADDED_TO_PAGE = 'added_to_pages_total';

	/** @var StatsFactory */
	private StatsFactory $statsFactory;

	/** @var string */
	private $wikiId;

	public function __construct(
		StatsFactory $statsFactory,
		?string $wikiId = null
	) {
		$this->statsFactory = $statsFactory;
		$this->wikiId = $wikiId ?? WikiMap::getCurrentWikiId();
	}

	/**
	 * Track when a new chart definition page is created
	 *
	 * @param WikiPage $wikiPage
	 * @param int $flags
	 * @return void
	 */
	public function trackChartDefinitionCreated( WikiPage $wikiPage, int $flags ) {
		$isNewPage = ( $flags & EDIT_NEW ) !== 0;

		if ( !$isNewPage ) {
			return;
		}

		$title = $wikiPage->getTitle();
		if ( $title->getNamespace() !== NS_DATA || $title->getContentModel() !== JCChartContent::CONTENT_MODEL ) {
			return;
		}

		$this->incrementStat( self::CHART_DEFINITION_CREATE );
	}

	/**
	 * Track when a chart parser function is added to a page.
	 * Called in the onLinksUpdateComplete hook, which has access
	 * to what was added and removed.
	 *
	 * @param LinksUpdate $linksUpdate
	 * @return void
	 */
	public function trackChartAddedToPage( LinksUpdate $linksUpdate ) {
		if ( $linksUpdate->getTitle()->getContentModel() !== CONTENT_MODEL_WIKITEXT ) {
			return;
		}

		$addedCategoryLinks = $linksUpdate->getPageReferenceIterator(
			'categorylinks',
			LinksTable::INSERTED
		);

		$categoryMsgText = wfMessage( 'chart-render-category' )->inContentLanguage()->text();

		$categoryTitle = Title::makeTitleSafe( NS_CATEGORY, $categoryMsgText );
		$chartCategoryDbKey = $categoryTitle ? $categoryTitle->getDBkey() : '';

		foreach ( $addedCategoryLinks as $addedCategoryLink ) {
			if ( $addedCategoryLink->getDBkey() === $chartCategoryDbKey ) {
				$this->incrementStat( self::CHART_ADDED_TO_PAGE );
				break;
			}
		}
	}

	/**
	 * Increment a statistics counter with wiki ID label.
	 *
	 * @param string $statName Name of the statistic to increment
	 * @return void
	 */
	private function incrementStat( string $statName ) {
		$this->statsFactory->withComponent( self::STATS_COMPONENT )
			->getCounter( $statName )
			->setLabel( 'wiki', $this->wikiId )
			->increment();
	}
}
