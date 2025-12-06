<?php

namespace MediaWiki\Extension\Chart;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Chart\Validators\ChartRequestValidator;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Language\FormatterFactory;
use MediaWiki\Shell\Shell;
use MediaWiki\Status\Status;
use MWCryptHash;
use Psr\Log\LoggerInterface;
use stdclass;

class ChartRenderer {

	/**
	 * @internal For use by ServiceWiring
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'ChartServiceUrl',
		'ChartCliPath'
	];

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly HttpRequestFactory $httpRequestFactory,
		private readonly FormatterFactory $formatterFactory,
		private readonly ChartRequestValidator $requestValidator,
		private readonly LoggerInterface $logger,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Render a chart from a definition object and a tabular data object.
	 *
	 * @param stdclass $chartDef Chart definition, obtained from JCChartContent::getContent()
	 * @param stdclass $tabularData Tabular data, obtained from JCTabularContent::getContent()
	 * @param array{width?:string,height?:string} $options Additional rendering options:
	 *   'width': Width of the chart, in pixels. Overrides width specified in the chart definition
	 *   'height': Height of the chart, in pixels. Overrides height specified in the chart definition.
	 * @return Status A Status object wrapping an SVG string or an error
	 */
	public function renderSVG( stdclass $chartDef, stdclass $tabularData, array $options = [] ): Status {
		// Prefix for IDs in the SVG. This has to be unique between charts on the same page, to
		// prevent ID collisions (T371558). If the same chart with the same data is displayed twice
		// on the same page, this gives them the same ID prefixes and causes their IDs to collide,
		// but that doesn't seem to cause a problem in practice.
		$definitionForHash = json_encode( [ 'format' => $chartDef, 'source' => $tabularData ] );
		$chartDef = clone $chartDef;
		$chartDef->idPrefix = 'mw-chart-' . MWCryptHash::hash( $definitionForHash, false );

		if ( $this->options->get( 'ChartServiceUrl' ) !== null ) {
			return $this->renderWithService( $chartDef, $tabularData, $options );
		}

		return $this->renderWithCli( $chartDef, $tabularData, $options );
	}

	private function renderWithService( stdclass $chartDef, stdclass $tabularData, array $options ): Status {
		$requestJson = $this->encodeRequestData( $chartDef, $tabularData, $options );

		$status = $this->requestValidator->validateRequestSize( $requestJson );
		if ( !$status->isGood() ) {
			return $status;
		}

		$requestOptions = [
			'method' => 'POST',
			'postData' => $requestJson
		];
		$request = $this->httpRequestFactory->create(
			$this->options->get( 'ChartServiceUrl' ),
			$requestOptions,
			__METHOD__
		);
		$request->setHeader( 'Content-Type', 'application/json' );

		$status = $request->execute();
		if ( !$status->isGood() ) {
			[ $message, $context ] = $this->formatterFactory->getStatusFormatter( RequestContext::getMain() )
				->getPsr3MessageAndContext( $status );
			if ( $request->getContent() ) {
				$error_content = json_decode( $request->getContent() );
				$context['server_error'] = $error_content;
			}
			$this->logger->error(
				'Chart service request returned error: {error}',
				[ 'error' => $message ] + $context
			);
			return Status::newFatal( 'chart-error-rendering-error' );
		}
		$response = $request->getContent();
		return Status::newGood( $response );
	}

	private function renderWithCli( stdclass $chartDef, stdclass $tabularData, array $options ): Status {
		if ( Shell::isDisabled() ) {
			return Status::newFatal( 'chart-error-shell-disabled' );
		}

		$requestData = $this->encodeRequestData( $chartDef, $tabularData, $options );
		$status = $this->requestValidator->validateRequestSize( $requestData );
		if ( !$status->isGood() ) {
			return $status;
		}

		$dataPath = tempnam( \wfTempDir(), 'data-json' );
		file_put_contents( $dataPath, $requestData );

		$result = Shell::command(
			'node',
			$this->options->get( 'ChartCliPath' ),
			$dataPath,
			'-'
		 )
			->execute();

		$error = $result->getStderr();
		if ( $error ) {
			$this->logger->error(
				'Chart shell command returned error: {error}',
				[ 'error' => $error ]
			);

			// @todo tracking category
			$status = Status::newFatal( 'chart-error-rendering-error' );
		} else {
			$svg = $result->getStdout();
			$status = Status::newGood( $svg );
		}

		unlink( $dataPath );
		return $status;
	}

	private function encodeRequestData( stdclass $chartDef, stdclass $tabularData, array $options ): string {
		// JSON_UNESCAPED_UNICODE prevents non-ASCII characters from being escaped as \uXXXX,
		// reducing payload size when chart definitions or data when non-ASCII characters are present.
		return json_encode( [
			'definition' => $chartDef,
			'data' => $tabularData,
			...$options
		], JSON_UNESCAPED_UNICODE );
	}
}
