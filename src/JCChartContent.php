<?php

namespace MediaWiki\Extension\Chart;

use JsonConfig\JCDataContent;
use JsonConfig\JCUtils;
use JsonConfig\JCValidators;
use JsonConfig\JCValue;
use MediaWiki\Language\Language;
use MediaWiki\MediaWikiServices;

class JCChartContent extends JCDataContent {

	public const CONTENT_MODEL = 'Chart.JsonConfig';

	public const MIN_CHART_VERSION = 1;
	public const MAX_CHART_VERSION = 1;

	public const CHART_TYPES = [
		'bar',
		'rect',
		'line',
		'area',
		'pie',
	];

	public const FORMAT_TYPES = [
		'auto',
		'none'
	];

	/** @inheritDoc */
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
		parent::validateContent();

		$this->test( 'version', self::isValidVersion() );
		$this->test( 'type', self::isValidEnum( self::CHART_TYPES ) );

		$this->testOptionalAlt( [ 'xAxis', 'title' ], self::isSwitchableString() );
		$this->testOptionalAlt( [ 'yAxis', 'title' ], self::isSwitchableString() );
		$this->testOptionalAlt( [ 'xAxis', 'format' ], self::isValidEnum( self::FORMAT_TYPES ) );
		$this->testOptionalAlt( [ 'yAxis', 'format' ], self::isValidEnum( self::FORMAT_TYPES ) );

		$this->testOptionalAlt( 'legend', self::isSwitchableString() );

		$this->test( 'source', self::isValidSource() );
	}

	/**
	 * There are some checks on the data source used in rendering
	 * either from chart definition or parser function override
	 * but that does not prevent the chart definition from
	 * being saved when referencing an invalid source.
	 */
	private static function isValidSource(): \Closure {
		return static function ( JCValue $jcv, array $path ) {
			/**
			 * @var ChartSourceValidator $chartSourceValidator
			 */
			$chartSourceValidator = MediaWikiServices::getInstance()->getService( 'Chart.ChartSourceValidator' );
			return $chartSourceValidator->validateSource( $jcv, $path );
		};
	}

	/**
	 * Test for an optional parameter without fiddling around with
	 * defaults that don't exist.
	 * @param string|array $path
	 * @param callable|array $validators
	 */
	private function testOptionalAlt( $path, $validators ) {
		$field = $this->getField( $path );
		if ( $field ) {
			$this->test( $path, $validators );
		}
	}

	/**
	 * JsonConfig validator for Chart's optional localized strings
	 * @param bool $nullable
	 * @param int $maxlength
	 * @return callable
	 */
	private static function isSwitchableString( $nullable = false, $maxlength = 400 ) {
		$localizableString = JCValidators::isLocalizedString( $nullable, $maxlength );
		$stringLine = JCValidators::isStringLine( $nullable, $maxlength );
		return static function ( JCValue $jcv, array $path ) use ( $localizableString, $stringLine ) {
			if ( !$jcv->isMissing() ) {
				$v = $jcv->getValue();
				if ( is_object( $v ) ) {
					return $localizableString( $jcv, $path );
				}
			}
			return $stringLine( $jcv, $path );
		};
	}

	/**
	 * JsonConfig validator for 'enums' of a set of specific values
	 * @param array $validValues
	 * @return callable
	 */
	private static function isValidEnum( $validValues ) {
		return static function ( JCValue $jcv, array $path ) use ( $validValues ) {
			if ( !$jcv->isMissing() ) {
				$v = $jcv->getValue();
				if ( in_array( $v, $validValues ) ) {
					return true;
				}
			}
			$jcv->error( 'chartjson-error-enum', $path, implode( ', ', $validValues ) );
			return false;
		};
	}

	/**
	 * JsonConfig validator for Chart JSON version
	 * @return callable
	 */
	private static function isValidVersion() {
		$isint = JCValidators::isInt();
		return static function ( JCValue $v, array $path ) use ( $isint ) {
			if ( $isint( $v, $path ) ) {
				$value = $v->getValue();
				if ( $value < self::MIN_CHART_VERSION
					|| $value > self::MAX_CHART_VERSION ) {
					$v->error( 'chartjson-error-version', $path, self::MIN_CHART_VERSION, self::MAX_CHART_VERSION );
				}
			}
			return false;
		};
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
			if ( isset( $src->format ) ) {
				$dst->format = $src->format;
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
		if ( isset( $data->source ) ) {
			$result->source = $data->source;
		}
	}
}
