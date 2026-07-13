<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\Chart;

use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\Hook\LinksUpdateCompleteHook;
use MediaWiki\Deferred\LinksUpdate\LinksUpdate;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser;
use MediaWiki\RecentChanges\Hook\RecentChange_saveHook;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Skin\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Skin\SkinTemplate;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;

class Hooks implements
	ParserFirstCallInitHook,
	PageSaveCompleteHook,
	LinksUpdateCompleteHook,
	SkinTemplateNavigation__UniversalHook,
	SpecialPage_initListHook,
	RecentChange_saveHook
{
	public const string SESSION_KEY = 'ext-chart';
	public const string CHANGE_TAG = 'chart-wizard';

	/**
	 * @param SpecialPageFactory $specialPageFactory
	 * @param Config $config
	 * @param ?FauxRequest $request Ignore; For unit testing only.
	 */
	public function __construct(
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly Config $config,
		private readonly ?FauxRequest $request = null,
	) {
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
		if ( !$this->config->get( 'ChartWizardEnabled' ) ) {
			return;
		}

		$chartWizard = $this->specialPageFactory->getPage( 'ChartWizard' );
		if ( !$chartWizard ) {
			return;
		}

		$title = $sktemplate->getTitle();
		$isChartWizardSpecialPage = $title->isSpecial( 'ChartWizard' );

		// Do not show "Edit with form" on the create chart page.
		if ( $isChartWizardSpecialPage && $sktemplate->getRelevantTitle()->isSpecialPage() ) {
			return;
		}

		if ( !$isChartWizardSpecialPage && (
				$title->getContentModel() !== JCChartContent::CONTENT_MODEL ||
				!$sktemplate->getUser()->probablyCan( 'edit', $title )
			)
		) {
			return;
		}

		$chartWizardTab = [
			'text' => $sktemplate->msg( 'chart-wizard-tab-label' )->text(),
			'icon' => 'edit',
			'class' => $isChartWizardSpecialPage ? 'selected' : '',
			'href' => $chartWizard->getPageTitle( $sktemplate->getRelevantTitle() )
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

	/** @inheritDoc */
	public function onSpecialPage_initList( &$list ): void {
		if ( !$this->config->get( 'ChartWizardEnabled' ) ) {
			unset( $list['ChartWizard'] );
			return;
		}

		$jsonConfigs = $this->config->get( 'JsonConfigs' );
		$chartConfig = $jsonConfigs[JCChartContent::CONTENT_MODEL] ?? null;
		$storesChartDefinitionsLocally = is_array( $chartConfig ) &&
			( ( $chartConfig['isLocal'] ?? true ) || array_key_exists( 'store', $chartConfig ) );
		if ( !$storesChartDefinitionsLocally ) {
			unset( $list['ChartWizard'] );
		}
	}

	/**
	 * Registers self::CHANGE_TAG as an active change tag.
	 */
	public static function onRegisterTags( array &$tags ): true {
		$tags[] = self::CHANGE_TAG;
		return true;
	}

	/**
	 * Adds the self::CHANGE_TAG tag to recent changes
	 * if the request was made using Special:ChartWizard.
	 *
	 * @param RecentChange $recentChange
	 */
	public function onRecentChange_save( $recentChange ): void {
		$request = $this->request ?? RequestContext::getMain()->getRequest();
		if ( $request->getSession()->get( self::SESSION_KEY ) ) {
			$recentChange->addTags( self::CHANGE_TAG );
			$request->getSession()->remove( self::SESSION_KEY );
		}
	}
}
