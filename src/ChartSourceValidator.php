<?php

namespace MediaWiki\Extension\Chart;

use JsonConfig\JCSingleton;
use JsonConfig\JCTabularContent;
use JsonConfig\JCValue;
use MediaWiki\MediaWikiServices;

/**
 * Service class for validating chart data sources
 */
class ChartSourceValidator {

	/**
	 * Validates that a chart's data source exists and is valid
	 *
	 * @param JCValue $jcv The JsonConfig value to validate
	 * @param array $path The path to the value in the JsonConfig tree
	 * @return bool
	 */
	public function validateSource( JCValue $jcv, array $path ): bool {
		$source = $jcv->getValue();
		if ( !is_string( $source ) ) {
			$jcv->error( 'chart-error-invalid-data-source', $path );
			return false;
		}

		return $this->validateSourcePage( $jcv, $source );
	}

	/**
	 * Validates that a source page exists and has valid content
	 *
	 * @param JCValue $jcv The JsonConfig value to validate
	 * @param string $source The source page name (e.g. "Example.tab")
	 * @return bool
	 */
	public function validateSourcePage( JCValue $jcv, string $source ): bool {
		/**
		 * @var DataPageResolver $dataPageResolver
		 */
		$dataPageResolver = MediaWikiServices::getInstance()->getService( 'Chart.DataPageResolver' );
		$sourceTitle = $dataPageResolver->resolvePageInDataNamespace( $source );

		if ( !$sourceTitle ) {
			$jcv->error( 'chart-error-source-not-found', [ $source ] );
			return false;
		}

		$sourceContent = JCSingleton::getContent( $sourceTitle );
		if ( !$sourceContent || !( $sourceContent instanceof JCTabularContent ) ) {
			$jcv->error( 'chart-error-invalid-source-type', [ $source ] );
			return false;
		}

		if ( !$sourceContent->isValid() ) {
			$jcv->error( 'chart-error-invalid-source-content', [ $source ] );
			return false;
		}

		return true;
	}
}
