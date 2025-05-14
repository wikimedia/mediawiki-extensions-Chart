<?php

namespace MediaWiki\Extension\Chart;

use MediaWiki\Deferred\LinksUpdate\LinksUpdate;
use MediaWiki\Hook\LinksUpdateCompleteHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;

class Hooks implements ParserFirstCallInitHook, PageSaveCompleteHook, LinksUpdateCompleteHook {

	public static function onRegistration() {
		global $wgChartServiceUrl, $wgChartCliPath;
		if ( $wgChartServiceUrl === null && $wgChartCliPath === null ) {
			// Set the default value for $wgChartCliPath
			$wgChartCliPath = dirname( __DIR__ ) . '/chart-renderer/cli.js';
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onPageSaveComplete(
		$wikiPage,
		$user,
		$summary,
		$flags,
		$revisionRecord,
		$editResult
	) {
		/** @var ChartMetrics $chartMetrics */
		$chartMetrics = MediaWikiServices::getInstance()->getService( 'Chart.ChartMetrics' );
		$chartMetrics->trackChartDefinitionCreated( $wikiPage, $flags );
	}

	/**
	 * @param Parser $parser
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook( 'chart', [ ParserFunction::class, 'funcHook' ] );
	}

	/**
	 * @param LinksUpdate $linksUpdate
	 * @param mixed $ticket Token returned by {@see IConnectionProvider::getEmptyTransactionTicket()}
	 */
	public function onLinksUpdateComplete( $linksUpdate, $ticket ) {
		/** @var ChartMetrics $chartMetrics */
		$chartMetrics = MediaWikiServices::getInstance()->getService( 'Chart.ChartMetrics' );
		$chartMetrics->trackChartAddedToPage( $linksUpdate );
	}
}
