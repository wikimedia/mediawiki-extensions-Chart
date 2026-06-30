<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\Chart\Tests\Integration;

use MediaWiki\Extension\Chart\ChartSourceValidator;
use MediaWiki\Extension\Chart\JCChartContent;
use MediaWiki\Extension\JsonConfig\JCContentHandler;
use MediaWiki\Json\FormatJson;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\Chart\JCChartContent
 */
class JCChartContentTest extends MediaWikiIntegrationTestCase {

	use ChartIntegrationTestTrait;

	public function setUp(): void {
		parent::setUp();
		$this->configureChartIntegrationTest();
		$chartSourceValidator = $this->createPartialMock( ChartSourceValidator::class, [ 'validateSourcePage' ] );
		$chartSourceValidator->method( 'validateSourcePage' )->willReturn( true );
		$this->setService( 'Chart.ChartSourceValidator', $chartSourceValidator );
	}

	public function testUnserializeContent() {
		$file = __DIR__ . '/../chart-integration/1993 Canadian federal election-chart.json';
		$content = $this->getRawJCChartContentFromFile( $file );

		$data = $content->getData();

		// test the data structure is correct
		$expectedStructure = [
			'title' => [
				'en' => '1993 Canadian federal election',
			],
			'type' => 'line',
			'source' => '1993 Canadian federal election.tab',
			'xAxis' => [
				'title' => [
					'en' => 'Year',
				],
				'format' => 'auto'
			],
			'yAxis' => [
				'title' => [
					'en' => '%support',
					'fr' => '%soutien'
				]
			]
		];

		$this->assertObjectStructure( $expectedStructure, $data );
	}

	public function testGetLocalizedData() {
		$file = __DIR__ . '/../chart-integration/1993 Canadian federal election-chart.json';
		$content = $this->getRawJCChartContentFromFile( $file );

		$lang = $this->getServiceContainer()->getLanguageFactory()->getLanguage( code: 'en' );

		$localizedData = $content->getLocalizedData( $lang );

		$expectedStructure = [
			'title' => '1993 Canadian federal election',
			'type' => 'line',
			'source' => '1993 Canadian federal election.tab',
			'xAxis' => [
				'title' => 'Year',
				'format' => 'auto'
			],
			'yAxis' => [
				'title' => '%support'
			]
		];

		$this->assertObjectStructure( $expectedStructure, $localizedData );
	}

	public function testSerializeContentNormalizesPrefixedSource() {
		$contentHandler = new JCContentHandler( JCChartContent::CONTENT_MODEL );
		$content = $contentHandler->unserializeContent( FormatJson::encode( [
			'license' => 'CC0-1.0',
			'version' => 1,
			'type' => 'line',
			'source' => 'Data:2022 US energy consumption.tab',
		], false, FormatJson::ALL_OK ) );

		$serialized = FormatJson::parse( $contentHandler->serializeContent( $content ) );

		$this->assertTrue( $serialized->isOK() );
		$this->assertSame( '2022 US energy consumption.tab', $serialized->getValue()->source );
	}

	private function getRawJCChartContentFromFile( $filePath ): JCChartContent {
		$rawData = file_get_contents( $filePath );
		if ( $rawData === false ) {
			$this->fail( "Can't read file $filePath" );
		}
		$contentHandler = new JCContentHandler( JCChartContent::CONTENT_MODEL );

		$content = $contentHandler->unserializeContent( $rawData );

		if ( !( $content instanceof JCChartContent ) ) {
			$this->fail( "Expected JCChartContent but got " . get_class( $content ) );
		}

		return $content;
	}

	/**
	 * @dataProvider provideTestCases
	 * @param string $file
	 * @param bool $thorough
	 */
	public function testValidateContent( $file, $thorough ) {
		$content = file_get_contents( $file );
		if ( $content === false ) {
			$this->fail( "Can't read file $file" );
		}
		$content = FormatJson::parse( $content );
		if ( !$content->isGood() ) {
			$this->fail( $content->getMessage()->plain() );
		}

		$content = $content->getValue();
		$testData = FormatJson::encode( $content->raw, false, FormatJson::ALL_OK );

		$c = new JCChartContent( $testData, 'Chart.JsonConfig', $thorough );
		if ( $c->isValid() ) {
			$this->assertTrue( true );
			$languageFactory = $this->getServiceContainer()->getLanguageFactory();
			foreach ( $content as $langCode => $expected ) {
				if ( $langCode === 'raw' ) {
					continue;
				} elseif ( $langCode === '_' ) {
					$actual = $c->getData();
				} else {
					$actual = $c->getLocalizedData( $languageFactory->getLanguage( $langCode ) );
					unset( $actual->license->text );
					unset( $actual->license->url );
				}
				$this->assertEquals( $expected, $actual, "langCode='$langCode'" );
			}
		} else {
			$this->fail( $c->getStatus()->getMessage()->plain() );
		}
	}

	public static function provideTestCases() {
		foreach ( glob( __DIR__ . "/../chart-good/*.json" ) as $file ) {
			yield [ $file, false ];
			yield [ $file, true ];
		}
	}

	/**
	 * @dataProvider provideBadTestCases
	 * @param string $file
	 */
	public function testValidateBadContent( $file ) {
		$content = file_get_contents( $file );
		if ( $content === false ) {
			$this->fail( "Can't read file $file" );
		}

		$c = new JCChartContent( $content, 'Chart.JsonConfig', true );
		$this->assertFalse( $c->isValid(), 'Validation unexpectedly succeeded on ' . basename( $file ) );
	}

	public static function provideBadTestCases() {
		foreach ( glob( __DIR__ . "/../chart-bad/*.json" ) as $file ) {
			yield [ $file ];
		}
	}

	/**
	 * @param array $expectedStructure
	 * @param \stdClass $actual
	 * @param string $path
	 */
	private function assertObjectStructure( array $expectedStructure, \stdClass $actual, string $path = '' ) {
		foreach ( $expectedStructure as $property => $value ) {
			$fullPath = $path ? "$path.$property" : $property;
			$this->assertObjectHasProperty( $property, $actual, "Missing property: $fullPath" );

			if ( is_array( $value ) ) {
				$this->assertIsObject( $actual->$property, "Property $fullPath should be an object" );
				$this->assertObjectStructure( $value, $actual->$property, $fullPath );
			} else {
				// assert value matches expected value from expectedStructure
				$this->assertSame( $value, $actual->$property, "Property $fullPath should be $value" );
			}
		}
	}

}
