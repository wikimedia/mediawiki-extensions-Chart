<?php

namespace MediaWiki\Extension\Chart;

use JsonConfig\JCContent;
use JsonConfig\JCSingleton;
use JsonConfig\JCTabularContent;
use JsonConfig\JCTitle;
use JsonConfig\JCTransform;
use MediaWiki\Context\RequestContext;
use MediaWiki\Html\Html;
use MediaWiki\Language\Language;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Page\PageReference;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Status\Status;
use MediaWiki\Status\StatusFormatter;
use MessageLocalizer;
use Psr\Log\LoggerInterface;

class ParserFunction implements MessageLocalizer {

	private Language $language;

	/** @var ?PageReference */
	private ?PageReference $page;

	private ChartRenderer $chartRenderer;

	private ChartArgumentsParser $argumentsParser;

	private DataPageResolver $dataPageResolver;

	private LoggerInterface $logger;

	private StatusFormatter $statusFormatter;

	public function __construct(
		ChartRenderer $chartRenderer,
		Language $language,
		ChartArgumentsParser $chartArgumentsParser,
		DataPageResolver $dataPageResolver,
		LoggerInterface $logger,
		StatusFormatter $statusFormatter,
		?PageReference $page
	) {
		$this->chartRenderer = $chartRenderer;
		$this->language = $language;
		$this->argumentsParser = $chartArgumentsParser;
		$this->dataPageResolver = $dataPageResolver;
		$this->logger = $logger;
		$this->statusFormatter = $statusFormatter;
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
		$services = MediaWikiServices::getInstance();
		$chartRenderer = $services->getService( 'Chart.ChartRenderer' );
		$languageFactory = $services->getService( 'LanguageFactory' );
		$chartArgumentsParser = $services->getService( 'Chart.ChartArgumentsParser' );
		$dataPageResolver = $services->getService( 'Chart.DataPageResolver' );

		$converter = $parser->getTargetLanguageConverter();
		if ( $converter->getVariants() ) {
			// The user language sets the preferred variant to use on languages
			// that use the language converter. We must inform the parser that
			// cache bifurcation needs to take it into account.
			$parser->getOptions()->getUserLangObj();
			$language = $languageFactory->getLanguage(
				$converter->getPreferredVariant()
			);
		} else {
			// Otherwise simply use the page's target language.
			$language = $parser->getTargetLanguage();
		}
		$context = new RequestContext();
		$context->setLanguage( $language );

		$statusFormatter = MediaWikiServices::getInstance()->getFormatterFactory()
			->getStatusFormatter( $context );
		$instance = new static(
			$chartRenderer,
			$language,
			$chartArgumentsParser,
			$dataPageResolver,
			$logger,
			$statusFormatter,
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
		$parser->addTrackingCategory( 'chart-render-category' );

		$status = Status::newGood();
		try {
			// @todo incrementExpensiveFunctionCount

			$parsedArguments = $this->argumentsParser->parseArguments( $parser, $args );
			$errors = $parsedArguments->getErrors();

			if ( $errors !== [] ) {
				foreach ( $errors as $error ) {
					$status->fatal( $error['key'], ...$error['params'] );
				}
			}

			if ( $status->isOK() ) {
				$status = $this->renderChart(
					$parser->getOutput(),
					$parsedArguments
				);
			}
		} catch ( \Exception $e ) {
			$this->logger->error(
				'Exception in {method}: {message}',
				[
					'method' => __METHOD__,
					'message' => $e->getMessage()
				]
			);

			$status->fatal( 'chart-error-unexpected' );
		}

		if ( $status->isOK() ) {
			$html = $status->getValue();
		} else {
			$parser->addTrackingCategory( 'chart-error-category' );
			$html = $this->renderStatus( $status );
		}

		return [ $html, 'noparse' => true, 'isRawHTML' => true ];
	}

	/**
	 * @return string
	 */
	public function renderStatus( Status $status ) {
		return Html::errorBox( $this->statusFormatter->getHTML( $status ) );
	}

	/**
	 * Render a chart from a definition page and a tabular data page.
	 *
	 * @param ParserOutput $output Parser the chart is being rendered into. Used to record
	 *   dependencies on the chart and data pages.
	 * @param ParsedArguments $parsedArguments
	 * @return Status wrapped HTML
	 */
	public function renderChart(
		ParserOutput $output,
		ParsedArguments $parsedArguments
	): Status {
		$chartDefinitionPageTitle = $parsedArguments->getDefinitionPageTitle();
		$tabularData = $parsedArguments->getDataPageTitle();
		$options = $parsedArguments->getOptions();
		$errors = $parsedArguments->getErrors();
		$transformArgs = $parsedArguments->getTransformArgs();
		$status = Status::newGood();

		if ( $errors !== [] ) {
			foreach ( $errors as $error ) {
				$status->fatal( $error['key'], ...$error['params'] );
			}
			return $status;
		}

		if ( !$chartDefinitionPageTitle ) {
			$status->fatal( 'chart-error-chart-definition-not-found' );
			return $status;
		}

		$definitionTitleValue = $chartDefinitionPageTitle;
		JCSingleton::recordJsonLink(
			$output,
			$definitionTitleValue
		);
		$definitionContent = JCSingleton::getContent( $definitionTitleValue );
		if ( !$definitionContent ) {
			$status->fatal( 'chart-error-chart-definition-not-found' );
		} elseif ( !( $definitionContent instanceof JCChartContent ) ) {
			$status->fatal( 'chart-error-incompatible-chart-definition' );
		} else {
			$status = $this->renderChartForDefinitionContent(
				$output,
				$definitionContent,
				$tabularData,
				$options,
				$transformArgs
			);
		}

		return $status;
	}

	/**
	 * Renders a chart from chart definition content and optional tabular data.
	 *
	 * @param ParserOutput $output ParserOutput the chart is being rendered into. Used to record
	 *    dependencies on the chart and data pages.
	 * @param JCContent $definitionContent The chart definition content object.
	 * @param ?JCTitle $tabularData Optional tabular data page title. If not provided, the default
	 *        data source specified in the chart definition will be used.
	 * @param array $options Rendering options (e.g., 'width' and 'height').
	 * @param array $transformArgs arguments passed on to the transform
	 * @return Status<string> wrapped HTML string containing the rendered chart or an error message.
	 */
	public function renderChartForDefinitionContent(
		ParserOutput $output,
		JCContent $definitionContent,
		?JCTitle $tabularData = null,
		array $options = [],
		array $transformArgs = []
	): Status {
		if ( !$definitionContent instanceof JCChartContent ) {
			return Status::newFatal( 'chart-error-chart-definition-invalid' );
		}

		$definitionObj = $definitionContent->getLocalizedData( $this->language );
		if ( !$definitionObj ) {
			return Status::newFatal( 'chart-error-chart-definition-invalid' );
		}

		$tabularDataTitleValue = null;
		if ( $tabularData ) {
			$tabularDataTitleValue = $tabularData;
		} else {
			if ( !isset( $definitionObj->source ) ) {
				return Status::newFatal( 'chart-error-default-source-not-specified' );
			}
			$tabularDataTitleValue = $this->dataPageResolver->resolvePageInDataNamespace(
				$definitionObj->source
			);
		}
		if ( !$tabularDataTitleValue ) {
			return Status::newFatal( 'chart-error-data-source-page-not-found' );
		}

		JCSingleton::recordJsonLink(
			$output,
			$tabularDataTitleValue
		);

		$config = MediaWikiServices::getInstance()->getMainConfig();
		$loader = JCSingleton::getContentLoader( $tabularDataTitleValue );
		if ( $definitionObj->transform ?? null ) {
			if ( $config->get( 'ChartTransformsEnabled' ) ) {
				$transformDef = $definitionObj->transform;
				// Apply any options as transform argumemnts,
				// overriding those in the format definition;
				if ( !property_exists( $transformDef, 'args' ) ) {
					$transformDef->args = (object)[];
				}
				foreach ( $transformArgs as $key => $val ) {
					$transformDef->args->$key = $val;
				}
				$transform = JCTransform::newFromJson( $transformDef );
				$loader->transform( $transform );
			} else {
				return Status::newFatal( 'chart-error-transforms-disabled' );
			}
		}
		$status = $loader->load();
		if ( !$status->isOk() ) {
			// Phan doesn't seem to like changing container generic types here?
			// There's no contained value, so we're only reporting the error.
			// @phan-suppress-next-line PhanTypeMismatchReturn
			return $status;
		}

		$wrapper = $status->getValue();
		$dataContent = $wrapper->getContent();
		if ( !( $dataContent instanceof JCTabularContent ) ) {
			return Status::newFatal( 'chart-error-incompatible-data-source' );
		}
		$wrapper->addToParserOutput( $output );

		$dataObj = $dataContent->getLocalizedData( $this->language );
		if ( !$dataObj ) {
			return Status::newFatal( 'chart-error-invalid-data-source' );
		}
		$options['locale'] = $this->language->getCode();

		$status = $this->chartRenderer->renderSVG(
			$definitionObj,
			$dataObj,
			$options
		);

		if ( $status->isOk() ) {
			$output->addModuleStyles( [ 'ext.chart.styles' ] );

			// Check that Charts progressive enhancement is enabled. If so, modify output.
			if ( $config->get( 'ChartProgressiveEnhancement' ) ) {
				$output->addWrapperDivClass( 'ext-chart-js' );
				$output->addModules( [ 'ext.chart.bootstrap' ] );
			}
		}

		return $status;
	}
}
