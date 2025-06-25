<?php
namespace MediaWiki\Extension\Chart;

use JsonConfig\GlobalJsonLinks;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Get some useful statistics of Chart usage
 */
class ApiChartInfo extends ApiBase {

	private WANObjectCache $cache;
	private GlobalJsonLinks $globalJsonLinks;

	public function __construct(
		ApiMain $main,
		string $action,
		WANObjectCache $cache,
		GlobalJsonLinks $globalJsonLinks
	) {
		parent::__construct( $main, $action );
		$this->cache = $cache;
		$this->globalJsonLinks = $globalJsonLinks;
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$global = $params['global'];
		$data = [
			'pagesWithCharts' => $this->pagesWithCharts( $global ),
			'global' => $global,
		];
		$this->getResult()->addValue( null, $this->getModuleName(), $data );
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'global' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_DEFAULT => false,
			],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=chartinfo&formatversion=2&format=jsonfm'
				=> 'apihelp-chartinfo-example-1',
			'action=chartinfo&global=0&formatversion=2&format=jsonfm'
				=> 'apihelp-chartinfo-example-2',
		];
	}

	/** @inheritDoc */
	public function isInternal() {
		return true;
	}

	private function pagesWithCharts( bool $global ): int {
		if ( $global ) {
			$key = $this->cache->makeGlobalKey( 'chartinfo', 'global' );
		} else {
			$key = $this->cache->makeKey( 'chartinfo', 'local' );
		}
		return $this->cache->getWithSetCallback(
			$key,
			WANObjectCache::TTL_HOUR,
			function ( $oldValue, &$ttl, &$setOpts ) use ( $global ) {
				return $this->globalJsonLinks
					->countLinksMatchingSuffix( '.chart', $global );
			}
		);
	}
}
