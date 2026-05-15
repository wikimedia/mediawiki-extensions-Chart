<?php

namespace MediaWiki\Extension\Chart;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\Chart\Validators\ChartRequestValidator;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\WikiMap\WikiMap;
use Psr\Log\LoggerInterface;

/**
 * @codeCoverageIgnore
 */

/** @phpcs-require-sorted-array */
return [
	'Chart.ChartArgumentsParser' => static function ( MediaWikiServices $services ): ChartArgumentsParser {
		return new ChartArgumentsParser( $services->get( 'Chart.DataPageResolver' ) );
	},
	'Chart.ChartMetrics' => static function ( MediaWikiServices $services ): ChartMetrics {
		return new ChartMetrics(
			$services->getStatsFactory(),
			WikiMap::getCurrentWikiId()
		);
	},
	'Chart.ChartRenderer' => static function ( MediaWikiServices $services ): ChartRenderer {
		return new ChartRenderer(
			new ServiceOptions(
				ChartRenderer::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getHttpRequestFactory(),
			$services->getFormatterFactory(),
			$services->get( 'Chart.ChartRequestValidator' ),
			$services->get( 'Chart.Logger' ),
		);
	},
	'Chart.ChartRequestValidator' => static function ( MediaWikiServices $services ): ChartRequestValidator {
		return new ChartRequestValidator(
			new ServiceOptions(
				ChartRequestValidator::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->get( 'Chart.Logger' ),
		);
	},
	'Chart.ChartSourceValidator' => static function ( MediaWikiServices $services ): ChartSourceValidator {
		return new ChartSourceValidator();
	},
	'Chart.DataPageResolver' => static function ( MediaWikiServices $services ): DataPageResolver {
		return new DataPageResolver();
	},
	'Chart.Logger' => static function (): LoggerInterface {
		return LoggerFactory::getInstance( 'Chart' );
	}
];
