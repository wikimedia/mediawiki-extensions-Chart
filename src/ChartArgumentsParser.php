<?php

namespace MediaWiki\Extension\Chart;

use MediaWiki\Parser\Parser;

class ChartArgumentsParser {
	private DataPageResolver $dataPageResolver;

	public function __construct( DataPageResolver $dataPageResolver ) {
		$this->dataPageResolver = $dataPageResolver;
	}

	public function parseArguments( Parser $parser, array $args ): ParsedArguments {
		$magicWords = $parser->getMagicWordFactory()->newArray( [
			'chart_data'
		] );

		$definition = array_shift( $args );
		$dataSource = null;
		$options = [];

		$errors = [];
		foreach ( $args as $arg ) {
			if ( str_contains( $arg, '=' ) ) {
				[ $key, $value ] = array_map( 'trim', explode( '=', $arg, 2 ) );
				switch ( $magicWords->matchStartToEnd( $key ) ) {
					case 'chart_data':
						$dataSource = $value;
						break;
					default:
						// no-op
				}
			}
		}

		$definitionTitle = null;
		if ( $definition === null ) {
			$errors[] = [
				'key' => 'chart-error-chart-definition-not-found',
				'params' => []
			];
		} else {
			$definitionTitle = $this->dataPageResolver->resolvePageInDataNamespace( $definition );

			if ( !$definitionTitle ) {
				$errors[] = [
					'key' => 'chart-error-chart-definition-not-found',
					'params' => []
				];
			}
		}

		$dataTitle = null;
		if ( $dataSource !== null ) {
			$dataTitle = $this->dataPageResolver->resolvePageInDataNamespace( $dataSource );
			if ( !$dataTitle ) {
				$errors[] = [
					'key' => 'chart-error-data-source-page-not-found',
					'params' => []
				];
			}
		}

		return new ParsedArguments( $definitionTitle, $dataTitle, $options, $errors );
	}

}
