<?php

namespace MediaWiki\Extension\Chart;

use MediaWiki\Json\FormatJson;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\Chart\JCChartContent
 */
class JCChartContentTest extends MediaWikiIntegrationTestCase {

	public function setUp(): void {
		parent::setUp();

		$chartSourceValidator = $this->createPartialMock( ChartSourceValidator::class, [ 'validateSourcePage' ] );
		$chartSourceValidator->method( 'validateSourcePage' )->willReturn( true );
		$this->setService( 'Chart.ChartSourceValidator', $chartSourceValidator );
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
		foreach ( glob( __DIR__ . "/chart-good/*.json" ) as $file ) {
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
		foreach ( glob( __DIR__ . "/chart-bad/*.json" ) as $file ) {
			yield [ $file ];
		}
	}

}
