<?php

namespace MediaWiki\Extension\Chart;

use JsonConfig\JCDataContent;
use JsonConfig\JCUtils;
use MediaWiki\Language\Language;
use MediaWiki\MediaWikiServices;

class JCChartContent extends JCDataContent {

	protected function createDefaultView() {
		$services = MediaWikiServices::getInstance();
		$chartRenderer = $services->getService( 'Chart.ChartRenderer' );
		$languageFactory = $services->getLanguageFactory();

		return new JCChartContentView( $chartRenderer, $languageFactory );
	}

	/**
	 * Returns wikitext representation of the data on transclusion.
	 *
	 * @return string|bool The raw text, or false if the conversion failed.
	 */
	public function getWikitextForTransclusion() {
		// @todo consider wrapping {{Data:Foo.chart}} into
		// {{#chart:Foo.chart}}, or a pretty source rep for copy-paste?
		return parent::getWikitextForTransclusion();
	}

	/**
	 * Derived classes must implement this method to perform custom validation
	 * using the check(...) calls.
	 *
	 * This should be kept compatible with mw.JsonConfig.JsonEditDialog validation
	 */
	public function validateContent() {
		// @todo implement validation of the custom schema
		parent::validateContent();
	}

	/**
	 * Resolve any override-specific localizations, and add it to $result
	 * @param \stdClass $result
	 * @param Language $lang
	 */
	protected function localizeData( $result, Language $lang ) {
		parent::localizeData( $result, $lang );

		$data = $this->getData();
		$localize = static function ( $value ) use ( $lang ) {
			if ( is_object( $value ) ) {
				return JCUtils::pickLocalizedString( $value, $lang );
			}
			return $value;
		};

		$result->version = $data->version;
		if ( isset( $data->type ) ) {
			$result->type = $data->type;
		}

		if ( isset( $data->title ) ) {
			$result->title = $localize( $data->title );
		}

		$axis = static function ( $src ) use ( $localize ) {
			$dst = (object)[];
			if ( isset( $src->title ) ) {
				$dst->title = $localize( $src->title );
			}
			return $dst;
		};
		if ( isset( $data->xAxis ) ) {
			$result->xAxis = $axis( $data->xAxis );
		}
		if ( isset( $data->yAxis ) ) {
			$result->yAxis = $axis( $data->yAxis );
		}
		if ( isset( $data->source ) ) {
			$result->source = $data->source;
		}
	}
}
