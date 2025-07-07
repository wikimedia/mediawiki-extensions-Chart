<?php

namespace MediaWiki\Extension\Chart;

use JsonConfig\JCContent;
use JsonConfig\JCContentView;
use MediaWiki\Context\RequestContext;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageReference;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;

class JCChartContentView extends JCContentView {

	private ChartRenderer $chartRenderer;

	private LanguageFactory $languageFactory;

	public function __construct( ChartRenderer $chartRenderer, LanguageFactory $languageFactory ) {
		$this->chartRenderer = $chartRenderer;
		$this->languageFactory = $languageFactory;
	}

	/**
	 * @param JCContent $content
	 * @param PageReference $page
	 * @param int|null $revId
	 * @param ParserOptions $options
	 * @param bool $generateHtml
	 * @param ParserOutput &$output
	 * @return string
	 */
	public function valueToHtml(
		JCContent $content,
		PageReference $page,
		$revId,
		ParserOptions $options,
		$generateHtml,
		ParserOutput &$output
	): string {
		$logger = LoggerFactory::getInstance( 'Chart' );
		$chartArgumentsParser = MediaWikiServices::getInstance()->getService( 'Chart.ChartArgumentsParser' );
		$dataPageResolver = MediaWikiServices::getInstance()->getService( 'Chart.DataPageResolver' );

		'@phan-var JCChartContent $content';
		$lang = $options->getUserLangObj();
		$context = new RequestContext();
		$context->setLanguage( $lang );
		$statusFormatter = MediaWikiServices::getInstance()->getFormatterFactory()
			->getStatusFormatter( $context );

		$parserFunction = new ParserFunction(
			$this->chartRenderer,
			$lang,
			$chartArgumentsParser,
			$dataPageResolver,
			$logger,
			$statusFormatter,
			$page
		);

		$status = $parserFunction->renderChartForDefinitionContent( $output, $content );
		if ( $status->isGood() ) {
			return $status->getValue();
		} else {
			return $parserFunction->renderStatus( $status );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getDefault( $modelId ): string {
		return <<<JSON
{
	"@documentation": "https://www.mediawiki.org/wiki/Extension:Chart/Specification",
	"version": 1,

	"license": "",

	"source": "Example.tab",

	"mediawikiCategories": [],

	"title": {
		"en": "Title of chart"
	},

	"type": "line",

	"xAxis": {
		"title": {
			"en": "X axis label"
		}
	},
	"yAxis": {
		"title": {
			"en": "Y axis label"
		}
	}
}
JSON;
	}
}
