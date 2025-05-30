<?php

namespace MediaWiki\Extension\Chart\Tests\Unit;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\Chart\Validators\ChartRequestValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \MediaWiki\Extension\Chart\Validators\ChartRequestValidator
 */
class ChartRequestValidatorTest extends TestCase {
	private const SMALL_SIZE_LIMIT = 50;
	private const LARGE_SIZE_LIMIT = 5000;

	public function testRequestSizeExceedsLimit() {
		$sampleJson = $this->getSampleJson();

		$logger = $this->getMockLogger();
		$logger->method( 'error' )
			->with(
				$this->stringContains( 'Chart request size' ),
				$this->callback( static function ( $params ) {
					return is_array( $params ) &&
						   isset( $params['size'] ) &&
						   isset( $params['limit'] );
				} )
			);

		$validator = new ChartRequestValidator(
			$this->getMockServiceOptions( self::SMALL_SIZE_LIMIT ),
			$logger
		);

		$result = $validator->validateRequestSize( $sampleJson );

		$this->assertFalse( $result->isGood() );
	}

	public function testRequestWithinSizeLimit() {
		$validator = new ChartRequestValidator(
			$this->getMockServiceOptions( self::LARGE_SIZE_LIMIT ),
			$this->getMockLogger()
		);
		$requestJson = $this->getSampleJson();

		$result = $validator->validateRequestSize( $requestJson );

		$this->assertTrue( $result->isGood() );
	}

	/** @return MockObject&LoggerInterface */
	private function getMockLogger() {
		return $this->createMock( LoggerInterface::class );
	}

	/** @return MockObject&ServiceOptions */
	private function getMockServiceOptions( int $sizeLimit ) {
		$options = $this->createMock( ServiceOptions::class );
		$options->method( 'get' )
			->with( 'ChartRequestSizeLimit' )
			->willReturn( $sizeLimit );
		return $options;
	}

	private function getSampleJson(): string {
		$filePath = __DIR__ . '/../../sample-requests/exchange-rate-JPYUSD.json';
		return file_get_contents( $filePath );
	}
}
