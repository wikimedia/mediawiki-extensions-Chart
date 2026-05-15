<?php

namespace MediaWiki\Extension\Chart;

use MediaWiki\Config\Config;
use MediaWiki\Deferred\Hook\LinksUpdateCompleteHook;
use MediaWiki\Deferred\LinksUpdate\LinksUpdate;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser;
use MediaWiki\Skin\Hook\SkinTemplateNavigation__UniversalHook;
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
	protected bool $isChartWizardEnabled;

	public function __construct(
		private readonly Config $config,
	) {
		$this->isChartWizardEnabled = $config->get( 'ChartWizardEnabled' );
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
		if ( $this->shouldSupportWizard( $sktemplate->getTitle() ) ) {
			$this->updateEditLinks( $sktemplate, $links );
		}
	}

	/**
	 * Adds the Chart Wizard JS to the output.
	 *
	 * This is attached to the MediaWiki 'BeforePageDisplay' hook.
	 *
	 * @param OutputPage $output The page view.
	 * @param Skin $skin The skin that's going to build the UI.
	 */
	public function onBeforePageDisplay( $output, $skin ): void {
		if ( $this->shouldSupportWizard( $output->getTitle() ) && $output->getRequest()->getBool( 'visual' ) ) {
			$output->addModules( 'ext.chart.wizard' );
		}
	}

	/**
	 * Update the edit links for the given skin template.
	 *
	 * @param SkinTemplate $sktemplate
	 * @param array &$links
	 */
	private function updateEditLinks( SkinTemplate $sktemplate, array &$links ): void {
		$selected = $sktemplate->getRequest()->getInt( 'visual' );
		// Deselect "edit source" link
		if ( $selected ) {
			$links['views']['edit']['class'] = '';
		}
		$chartWizardTab = [
			'text' => $sktemplate->msg( 'chart-wizard-tab-label' )->text(),
			'icon' => 'edit',
			'class' => $selected ? 'selected' : '',
			'href' => $sktemplate->getRelevantTitle()->getLinkURL( [ 'action' => 'edit', 'visual' => 1 ] )
		];

		$tabs = $links['views'];
		// Attempt to insert before the "View history" tab.
		$newTabs = [];
		foreach ( $tabs as $key => $val ) {
			if ( $key === 'history' ) {
				$newTabs['chart-wizard'] = $chartWizardTab;
			}
			$newTabs[$key] = $val;
		}
		// If no "View history" tab was found, append to the end.
		if ( !isset( $newTabs['chart-wizard'] ) ) {
			$newTabs['chart-wizard'] = $chartWizardTab;
		}

		$links['views'] = $newTabs;
	}

	/**
	 * Determine if Visual mode should be added to the given page title
	 *
	 * @param Title $title Title to render visual mode
	 * @return bool True if chart visual mode flag is enabled and it's a chart page
	 */
	private function shouldSupportWizard( Title $title ): bool {
		return $this->isChartWizardEnabled && $title->getContentModel() === JCChartContent::CONTENT_MODEL;
	}
}
