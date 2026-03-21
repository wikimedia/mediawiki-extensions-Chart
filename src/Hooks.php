<?php

namespace MediaWiki\Extension\Chart;

use MediaWiki\Config\Config;
use MediaWiki\Deferred\LinksUpdate\LinksUpdate;
use MediaWiki\Hook\LinksUpdateCompleteHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\Parser;
use MediaWiki\Skin\Skin;
use MediaWiki\Skin\SkinTemplate;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\Title\Title;

class Hooks implements
	BeforePageDisplayHook,
	ParserFirstCallInitHook,
	PageSaveCompleteHook,
	LinksUpdateCompleteHook,
	SkinTemplateNavigation__UniversalHook
{
	protected bool $isChartVisualModeEnabled;

	public function __construct(
		private readonly Config $config,
	) {
		$this->isChartVisualModeEnabled = $config->get( 'ChartVisualModeEnabled' );
	}

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

	/**
	 * Add an "Visual" tab to the navigation of Data: chart.
	 *
	 *
	 * @param SkinTemplate $sktemplate
	 * @param array &$links
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if ( $this->shouldSupportVisualMode( $sktemplate->getTitle() ) ) {
			$this->updateEditLinks( $sktemplate, $links );
		}
	}

	/**
	 * Adds Chart visual editor JS to the output.
	 *
	 * This is attached to the MediaWiki 'BeforePageDisplay' hook.
	 *
	 * @param OutputPage $output The page view.
	 * @param Skin $skin The skin that's going to build the UI.
	 */
	public function onBeforePageDisplay( $output, $skin ): void {
		if ( $this->shouldSupportVisualMode( $output->getTitle() ) && $output->getRequest()->getBool( 'visual' ) ) {
			$output->addModules( 'ext.chart.visualEditMode' );
		}
	}

	/**
	 * Update the edit links for the given skin template.
	 *
	 * @param SkinTemplate $sktemplate
	 * @param array &$links
	 */
	private function updateEditLinks( SkinTemplate $sktemplate, array &$links ): void {
		$setVisualMode = $sktemplate->getRequest()->getInt( 'visual' );
		// Deselect "edit source" link
		if ( $setVisualMode ) {
			$links['views']['edit']['class'] = '';
		}
		$chartVisualEditTab = [
			'text' => $sktemplate->msg( 'chart-visualedit-tab-label' )->text(),
			'icon' => 'edit',
			'class' => $setVisualMode ? 'selected' : '',
			'href' => $sktemplate->getRelevantTitle()->getLinkURL( [ 'action' => 'edit', 'visual' => 1 ] )
		];

		$tabs = $links['views'];
		// Attempt to insert before the "View history" tab.
		$newTabs = [];
		foreach ( $tabs as $key => $val ) {
			if ( $key === 'history' ) {
				$newTabs['chart-visual-edit'] = $chartVisualEditTab;
			}
			$newTabs[$key] = $val;
		}
		// If no "View history" tab was found, append to the end.
		if ( !isset( $newTabs['chart-visual-edit'] ) ) {
			$newTabs['chart-visual-edit'] = $chartVisualEditTab;
		}

		$links['views'] = $newTabs;
	}

	/**
	 * Determine if Visual mode should be added to the given page title
	 *
	 * @param Title $title Title to render visual mode
	 * @return bool True if chart visual mode flag is enabled and it's a chart page
	 */
	private function shouldSupportVisualMode( Title $title ): bool {
		return $this->isChartVisualModeEnabled && $title->getContentModel() === JCChartContent::CONTENT_MODEL;
	}
}
