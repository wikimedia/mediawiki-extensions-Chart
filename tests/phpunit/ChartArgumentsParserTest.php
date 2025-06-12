<?php

namespace MediaWiki\Extension\Chart;

use JsonConfig\JCSingleton;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWikiIntegrationTestCase;

class ChartArgumentsParserTest extends MediaWikiIntegrationTestCase {

	/**
	 * Load up the sample filter lua state
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValues( [
			'LanguageCode' => 'en',
			'JsonConfigs' => [
				'Tabular.JsonConfig' => [
					'namespace' => 486,
					'nsName' => 'Data',
					'pattern' => '/.\.tab$/',
					'license' => 'CC0-1.0',
					'isLocal' => true,
					'store' => true,
				],
				'Chart.JsonConfig' => [
					'namespace' => 486,
					'nsName' => 'Data',
					'pattern' => '/.\.chart$/',
					'license' => 'CC0-1.0',
					'isLocal' => true,
					'store' => true,
				]
			],
			'JsonConfigModels' => [
				'Tabular.JsonConfig' => 'JsonConfig\JCTabularContent',
				'Chart.JsonConfig' => 'MediaWiki\Extension\Chart\JCChartContent',
			],
		] );
		JCSingleton::init( true );
	}

	protected function tearDown(): void {
		parent::tearDown();
		JCSingleton::init( true );
	}

	/**
	 * @covers MediaWiki\Extension\Chart\ChartArgumentsParser
	 * @dataProvider chartArgumentsProvider
	 */
	public function testChartArgumentsParser( mixed $source, array $args,
		mixed $expectedDefinition, mixed $expectedData,
		array $expectedOptions, array $expectedArgs, array $expectedErrors
	) {
		$source = $source ? JCSingleton::parseTitle( $source, NS_DATA ) : null;
		$pageResolver = new DataPageResolver();

		$magicWordFactory = MediaWikiServices::getInstance()->getMagicWordFactory();
		$parser = $this->createPartialMock( Parser::class, [ 'getMagicWordFactory' ] );
		$parser->method( 'getMagicWordFactory' )->willReturn( $magicWordFactory );

		$argumentsParser = new ChartArgumentsParser( $pageResolver );
		$parsed = $argumentsParser->parseArguments( $parser, $args );

		$this->assertSame( $expectedDefinition, $parsed->getDefinitionPageTitle()
			? $parsed->getDefinitionPageTitle()->getDBKey() : null, 'definition page' );

		$this->assertSame( $expectedData, $parsed->getDataPageTitle()
			? $parsed->getDataPageTitle()->getDBKey() : null, 'data page' );

		$this->assertSame( $expectedOptions, $parsed->getOptions(), 'options' );
		$this->assertSame( $expectedArgs, $parsed->getTransformArgs(), 'transform args' );
		$this->assertSame( $expectedErrors, array_map( static function ( $val ) {
			return $val['key'];
		}, $parsed->getErrors() ), 'errors' );
	}

	public function chartArgumentsProvider(): array {
		return [
			[
				'Definition page.chart',
				[ 'Definition page.chart' ],
				// definition page
				'Definition_page.chart',
				// data page
				null,
				// options
				[],
				// transform args
				[],
				// errors
				[]
			],
			[
				null,
				[ '' ],
				// definition page is not a valid title
				null,
				// data page
				null,
				// options
				[],
				// transform args
				[],
				// errors
				[ 'chart-error-chart-definition-not-found' ]
			],
			[
				'Definition page.chart',
				[ 'Definition page.chart', 'data=Data page.tab' ],
				// Definition page
				'Definition_page.chart',
				// data page
				'Data_page.tab',
				// options
				[],
				// transform args
				[],
				// errors
				[]
			],
			[
				'Transform sample.chart',
				[ 'Transform sample.chart', 'arg:entity=Q84', 'arg:a=b', 'arg:c=d=e', 'f=g' ],
				// definition page
				'Transform_sample.chart',
				// data page
				null,
				// options
				[],
				// transform args
				[
					'entity' => 'Q84',
					'a' => 'b',
					'c' => 'd=e',
				],
				// errors
				[]
			],
		];
	}
}
