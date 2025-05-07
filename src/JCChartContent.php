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

	// Note: doesn't include transform options which are checked separately.
	private const VALIDATION_SCHEMA = [
		[ [ 'version' ], 'required', [ self::class, 'isValidVersion' ], 'raw' ],
		[ [ 'type' ], 'required', [ self::class, 'isValidChartType' ], 'raw' ],
		[ [ 'source' ], 'optional', [ JCValidators::class, 'isString' ], 'raw' ],
		[ [ 'title' ], 'optional', [ self::class, 'isSwitchableString' ], 'localized' ],
		[ [ 'subtitle' ], 'optional', [ JCValidators::class, 'isLocalizedString' ], 'localized' ],
		[ [ 'xAxis', 'title' ], 'optional', [ self::class, 'isSwitchableString' ], 'localized' ],
		[ [ 'yAxis', 'title' ], 'optional', [ self::class, 'isSwitchableString' ], 'localized' ],
		[ [ 'xAxis', 'format' ], 'optional', [ self::class, 'isValidFormatType' ], 'raw' ],
		[ [ 'yAxis', 'format' ], 'optional', [ self::class, 'isValidFormatType' ], 'raw' ],
		[ [ 'legend' ], 'optional', [ self::class, 'isSwitchableString' ], 'localized' ],
		[ [ 'transform', 'module' ], 'optional', [ JCValidators::class, 'isStringLine' ], 'raw' ],
		[ [ 'transform', 'function' ], 'optional', [ JCValidators::class, 'isStringLine' ], 'raw' ],
		[ [ 'transform', 'args' ], 'optional', [ JCValidators::class, 'isDictionary' ], 'raw' ],
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

		foreach ( self::VALIDATION_SCHEMA as [ $path, $presence, $validator ] ) {
			if ( $presence === 'required' ) {
				$this->test( $path, $validator() );
			} else {
				$this->testOptionalAlt( $path, $validator() );
			}
		}

		if ( $this->getField( 'transform' ) ) {
			// *if* we have a transform, module and function must be provided
			// so double up on them with a required check
			$this->test( [ 'transform', 'module' ], JCValidators::isStringLine() );
			$this->test( [ 'transform', 'function' ], JCValidators::isStringLine() );
			$args = $this->getField( [ 'transform', 'args' ] );
			if ( $args ) {
				foreach ( array_keys( get_object_vars( $args->getValue() ) ) as $key ) {
					// Note args are intended to be passable as key=value pairs
					// in parser function arguments and will be strings but may be
					// long or include newlines.
					$this->test( [ 'transform', 'args', $key ], JCValidators::isString() );
				}
			}
		}

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
		return static function ( JCValue $jcv, array $path ) use ( $nullable, $maxlength ) {
			if ( !$jcv->isMissing() ) {
				$v = $jcv->getValue();
				if ( JCUtils::isValidLineString( $v, $maxlength ) ||
					( $nullable && $v === null )
				) {
					return true;
				}
				if ( is_object( $v ) ) {
					$v = (array)$v;
				}
				if ( JCUtils::isLocalizedArray( $v, $maxlength ) ) {
					// Sort array so that the values are sorted alphabetically
					ksort( $v );
					$jcv->setValue( (object)$v );
					return true;
				}
			}
			$jcv->error( 'chart-error-switchable-string', $path, $maxlength );
			return false;
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

	private static function isValidChartType(): callable {
		return self::isValidEnum( self::CHART_TYPES );
	}

	private static function isValidFormatType(): callable {
		return self::isValidEnum( self::FORMAT_TYPES );
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

		foreach ( self::VALIDATION_SCHEMA as [ $path, $presence, $validator, $format ] ) {
			$field = $this->getField( $path );
			if ( $field ) {
				$to = $result;
				$from = $data;
				while ( count( $path ) > 1 ) {
					$segment = array_shift( $path );
					if ( !property_exists( $to, $segment ) ) {
						$to->$segment = (object)[];
					}
					$to = $to->$segment;
					$from = $from->$segment;
				}

				$segment = array_shift( $path );
				$value = $from->$segment;

				if ( $format === 'localized' ) {
					$value = $localize( $value );
				} else {
					if ( is_object( $value ) ) {
						$value = clone $value;
					}
				}

				$to->$segment = $value;
			}
		}
	}
}
