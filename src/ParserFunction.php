<?php

namespace MediaWiki\Extension\Chart;

use JsonConfig\JCContent;
use JsonConfig\JCSingleton;
use JsonConfig\JCTabularContent;
use MediaWiki\Html\Html;
use MediaWiki\Language\Language;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Page\PageReference;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use MessageLocalizer;
use Psr\Log\LoggerInterface;
use WikiPage;

class ParserFunction implements MessageLocalizer {

	private Language $language;

	/** @var ?PageReference */
	private ?PageReference $page;

	private ChartRenderer $chartRenderer;

	private ChartArgumentsParser $argumentsParser;

	private DataPageResolver $dataPageResolver;

	private LoggerInterface $logger;

	public function __construct(
		ChartRenderer $chartRenderer,
		Language $language,
		ChartArgumentsParser $chartArgumentsParser,
		DataPageResolver $dataPageResolver,
		LoggerInterface $logger,
		?PageReference $page
	) {
		$this->chartRenderer = $chartRenderer;
		$this->language = $language;
		$this->argumentsParser = $chartArgumentsParser;
		$this->dataPageResolver = $dataPageResolver;
		$this->logger = $logger;
		$this->page = $page;
	}

	/**
	 * @inheritDoc
	 */
	public function msg( $key, ...$params ): Message {
		return wfMessage( $key, ...$params )->inLanguage( $this->language )->page( $this->page );
	}

	/**
	 * Static entry point for the `{{#chart:}}` parser function.
	 *
	 * Wrapper for render() that creates an instance of this class based on the Parser's state.
	 * This is needed because these Parser getters don't work yet in the ParserFirstCallInit hook.
	 *
	 * @param Parser $parser
	 * @param string ...$args
	 * @return array
	 */
	public static function funcHook( Parser $parser, ...$args ) {
		$logger = LoggerFactory::getInstance( 'Chart' );
		$chartRenderer = MediaWikiServices::getInstance()->getService( 'Chart.ChartRenderer' );
		$chartArgumentsParser = MediaWikiServices::getInstance()->getService( 'Chart.ChartArgumentsParser' );
		$dataPageResolver = MediaWikiServices::getInstance()->getService( 'Chart.DataPageResolver' );

		$instance = new static(
			$chartRenderer,
			$parser->getTargetLanguage(),
			$chartArgumentsParser,
			$dataPageResolver,
			$logger,
			$parser->getPage()
		);
		return $instance->render( $parser, ...$args );
	}

	/**
	 * Main entry point for the `{{#chart:}}` parser function.
	 *
	 * @param Parser $parser
	 * @param string ...$args
	 * @return array
	 */
	public function render( Parser $parser, ...$args ) {
		try {
			// @todo incrementExpensiveFunctionCount

			$parsedArguments = $this->argumentsParser->parseArguments( $parser, $args );
			$errors = $parsedArguments->getErrors();

			if ( $errors !== [] ) {
				return $this->renderErrors( $errors );
			}

			$html = $this->renderChart(
				$parser->getOutput(),
				$parsedArguments
			);

			// HACK work around a parser bug that inserts <p> tags even though we said not to parse
			$html = str_replace( ">\n", '>', $html );
			return [ $html, 'noparse' => true, 'isHTML' => true ];
		} catch ( \Exception $e ) {
			$this->logger->error(
				'Exception in {method}: {message}',
				[
					'method' => __METHOD__,
					'message' => $e->getMessage()
				]
			);

			return $this->renderErrors( [ 'chart-error-unexpected', [] ] );

		}
	}

	private function renderErrors( array $errors ): array {
		$errorHtml = '';
		foreach ( $errors as $error ) {
			$errorHtml .= $this->renderError( $error['key'], $error['params'] );
		}
		return [ $errorHtml, 'noparse' => true, 'isHTML' => true ];
	}

	/**
	 * @param string $key
	 * @param array $params
	 * @return string
	 */
	private function renderError( string $key, array $params = [] ): string {
		$errorMsg = $this->msg( $key, ...$params )->escaped();
		return Html::errorBox( $errorMsg );
	}

	/**
	 * Render a chart from a definition page and a tabular data page.
	 *
	 * @param ParserOutput $output ParserOutput the chart is being rendered into. Used to record
	 *   dependencies on the chart and data pages.
	 * @param ParsedArguments $parsedArguments
	 * @return string HTML
	 */
	public function renderChart(
		ParserOutput $output,
		ParsedArguments $parsedArguments
	): string {
		$chartDefinitionPageTitle = $parsedArguments->getDefinitionPageTitle();
		$tabularData = $parsedArguments->getDataPageTitle();
		$options = $parsedArguments->getOptions();
		$errors = $parsedArguments->getErrors();

		if ( $errors !== [] ) {
			$errorHtml = '';
			foreach ( $errors as $error ) {
				$errorHtml .= $this->renderError( $error['key'], $error['params'] );
				return $errorHtml;
			}
		}

		if ( !$chartDefinitionPageTitle ) {
			return $this->renderError( 'chart-error-chart-definition-not-found' );
		}

		$definitionPage = new WikiPage( $chartDefinitionPageTitle );
		$definitionContent = JCSingleton::getContent( $chartDefinitionPageTitle->getTitleValue() );
		if ( !$definitionContent ) {
			return $this->renderError( 'chart-error-chart-definition-not-found' );
		}
		if ( !( $definitionContent instanceof JCChartContent ) ) {
			return $this->renderError( 'chart-error-incompatible-chart-definition' );
		}
		if ( $definitionPage->exists() ) {
			// Record a dependency on the chart page, so that the page embedding the chart
			// is reparsed when the chart page is edited
			$output->addTemplate(
				$definitionPage->getTitle(),
				$definitionPage->getId(),
				$definitionPage->getRevisionRecord()->getId()
			);
		}

		// @todo register cross-site dependencies using extended GlobalUsage
		// and allow updates to trigger re-parses of affected pages
		return $this->renderChartForDefinitionContent( $output, $definitionContent, $tabularData, $options );
	}

	/**
	 * Renders a chart from chart definition content and optional tabular data.
	 *
	 * @param ParserOutput $output ParserOutput the chart is being rendered into. Used to record
	 *    dependencies on the chart and data pages.
	 * @param JCContent $definitionContent The chart definition content object.
	 * @param ?Title $tabularData Optional tabular data page title. If not provided, the default
	 *        data source specified in the chart definition will be used.
	 * @param array $options Rendering options (e.g., 'width' and 'height').
	 * @return string HTML string containing the rendered chart or an error message.
	 */
	public function renderChartForDefinitionContent(
		ParserOutput $output,
		JCContent $definitionContent,
		?Title $tabularData = null,
		array $options = []
	): string {
		if ( !$definitionContent instanceof JCChartContent ) {
			throw new \UnexpectedValueException( 'Expected JCChartContent' );
		}

		$definitionObj = $definitionContent->getLocalizedData( $this->language );

		if ( !$tabularData ) {
			if ( !isset( $definitionObj->source ) ) {
				return $this->renderError( 'chart-error-default-source-not-specified' );
			}
			$tabularData = $this->dataPageResolver->resolvePageInDataNamespace( $definitionObj->source );
			if ( !$tabularData ) {
				return $this->renderError( 'chart-error-data-source-page-not-found' );
			}
		}
		$dataPage = new WikiPage( $tabularData );
		$dataContent = JCSingleton::getContent( $tabularData->getTitleValue() );
		if ( !$dataContent ) {
			return $this->renderError( 'chart-error-data-source-page-not-found' );
		}
		if ( !( $dataContent instanceof JCTabularContent ) ) {
			return $this->renderError( 'chart-error-incompatible-data-source' );
		}
		if ( $dataPage->exists() ) {
			// Record a dependency on the data page, so that the page embedding the chart
			// is reparsed when the data page is edited
			$output->addTemplate(
				$dataPage->getTitle(),
				$dataPage->getId(),
				$dataPage->getRevisionRecord()->getId()
			);
		} else {
			// @todo register cross-site dependencies using extended GlobalUsage
			// and allow updates to trigger re-parses of affected pages
		}
		$dataObj = $dataContent->getLocalizedData( $this->language );

		$status = $this->chartRenderer->renderSVG( $definitionObj, $dataObj, $options );
		if ( !$status->isGood() ) {
			return Html::errorBox( $status->getHTML() );
		}
		$svg = $status->getValue();
		$output->addModuleStyles( [ 'ext.chart.styles' ] );

		// Check that Charts progressive enhancement is enabled. If so, modify output.
		$config = MediaWikiServices::getInstance()->getMainConfig();
		if ( $config->get( 'ChartProgressiveEnhancement' ) ) {
			$output->addWrapperDivClass( 'ext-chart-js' );
			$output->addModules( [ 'ext.chart.bootstrap' ] );
		}
		return $svg;
	}
}
