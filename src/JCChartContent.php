<?php

namespace MediaWiki\Extension\Chart;

use JsonConfig\JCDataContent;
use JsonConfig\JCUtils;
use Language;
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
		$result->width = $data->width;
		$result->height = $data->height;
		if ( isset( $data->type ) ) {
			$result->type = $data->type;
		}
		if ( isset( $data->interpolate ) ) {
			$result->interpolate = $data->interpolate;
		}
		if ( isset( $data->colors ) ) {
			$result->colors = $data->colors;
		}

		$axis = static function ( $src ) use ( $localize ) {
			$dst = (object)[];
			if ( isset( $src->title ) ) {
				$dst->title = $localize( $src->title );
			}
			if ( isset( $src->min ) ) {
				$dst->min = $src->min;
			}
			if ( isset( $src->max ) ) {
				$dst->max = $src->max;
			}
			if ( isset( $src->format ) ) {
				$dst->format = $localize( $src->format );
			}
			if ( isset( $src->angle ) ) {
				$dst->angle = $src->angle;
			}
			if ( isset( $src->type ) ) {
				$dst->type = $src->type;
			}
			if ( isset( $src->grid ) ) {
				$dst->grid = $src->grid;
			}
			return $dst;
		};
		if ( isset( $data->xAxis ) ) {
			$result->xAxis = $axis( $data->xAxis );
		}
		if ( isset( $data->yAxis ) ) {
			$result->yAxis = $axis( $data->yAxis );
		}

		if ( isset( $data->legend ) ) {
			$result->legend = $localize( $data->legend );
		}
		if ( isset( $data->linewidth ) ) {
			$result->linewidth = $data->linewidth;
		}
		if ( isset( $data->showValues ) ) {
			$src = $data->showValues;
			$dst = (object)[];
			if ( isset( $src->format ) ) {
				$dst->format = $localize( $src->format );
			}
			if ( isset( $src->fontcolor ) ) {
				$dst->fontcolor = $src->fontcolor;
			}
			if ( isset( $src->fontsize ) ) {
				$dst->fontsize = $src->fontsize;
			}
			if ( isset( $src->offset ) ) {
				$dst->offset = $src->offset;
			}
			if ( isset( $src->angle ) ) {
				$dst->angle = $src->angle;
			}
			$result->showValues = $dst;
		}

		if ( isset( $data->showSymbols ) ) {
			$result->showSymbols = $data->showSymbols;
		}
		if ( isset( $data->innerRadius ) ) {
			$result->innerRadius = $data->innerRadius;
		}
		if ( isset( $data->source ) ) {
			$result->source = $data->source;
		}
	}
}
