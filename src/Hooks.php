<?php

namespace MediaWiki\Extension\Chart;

use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser;

class Hooks implements ParserFirstCallInitHook {

	public static function onRegistration() {
		global $wgChartServiceUrl, $wgChartCliPath;
		if ( $wgChartServiceUrl === null && $wgChartCliPath === null ) {
			// Set the default value for $wgChartCliPath
			$wgChartCliPath = dirname( __DIR__ ) . '/chart-renderer/cli.js';
		}
	}

	/**
	 * @param Parser $parser
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook( 'chart', [ ParserFunction::class, 'funcHook' ] );
	}
}
