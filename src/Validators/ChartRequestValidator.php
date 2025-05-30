<?php

namespace MediaWiki\Extension\Chart\Validators;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Status\Status;
use Psr\Log\LoggerInterface;

class ChartRequestValidator {
	private ServiceOptions $options;
	private LoggerInterface $logger;

	/**
	 * @internal For use by ServiceWiring
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'ChartRequestSizeLimit'
	];

	/**
	 * @param ServiceOptions $options
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		ServiceOptions $options,
		LoggerInterface $logger
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->logger = $logger;
	}

	/**
	 * Validate that the chart rendering request does not exceed the configured size limits.
	 * The rendering request consists of the chart definition and tabular data combined.
	 *
	 * @param string $requestJson
	 * @return Status
	 */
	public function validateRequestSize( string $requestJson ): Status {
		// use 8bit encoding to count bytes, not characters
		$requestSize = mb_strlen( $requestJson, '8bit' );
		$sizeLimit = $this->options->get( 'ChartRequestSizeLimit' );

		if ( $requestSize > $sizeLimit ) {
			$this->logger->warning(
				'Chart request size {size} exceeds limit of {limit} bytes',
				[
					'size' => $requestSize,
					'limit' => $sizeLimit
				]
			);

			return Status::newFatal( 'chart-error-request-too-large', $sizeLimit );
		}

		return Status::newGood();
	}
}
