<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\Chart;

use MediaWiki\Config\Config;
use MediaWiki\Deferred\Hook\LinksUpdateCompleteHook;
use MediaWiki\Deferred\LinksUpdate\LinksUpdate;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser;
use MediaWiki\Skin\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Skin\SkinTemplate;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;

class Hooks implements
	ParserFirstCallInitHook,
	PageSaveCompleteHook,
	LinksUpdateCompleteHook,
	SkinTemplateNavigation__UniversalHook
{
	protected bool $isChartWizardEnabled;

	public function __construct(
		private readonly SpecialPageFactory $specialPageFactory,
		Config $config
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
	 * Add an "Edit with form" tab to Chart definition pages.
	 *
	 * @param SkinTemplate $sktemplate
	 * @param array &$links
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		$title = $sktemplate->getTitle();
		$isSpecial = $title->isSpecial( 'ChartWizard' );
		if ( !$this->isChartWizardEnabled ||
			(
				!$isSpecial && (
					$title->getContentModel() !== JCChartContent::CONTENT_MODEL ||
					!$sktemplate->getUser()->probablyCan( 'edit', $title )
				)
			)
		) {
			return;
		}

		$chartWizardTab = [
			'text' => $sktemplate->msg( 'chart-wizard-tab-label' )->text(),
			'icon' => 'edit',
			'class' => $isSpecial ? 'selected' : '',
			'href' => $this->specialPageFactory->getPage( 'ChartWizard' )
				->getPageTitle( $sktemplate->getRelevantTitle() )
				->getLocalURL()
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
}
