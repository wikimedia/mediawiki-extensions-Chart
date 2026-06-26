<?php

namespace MediaWiki\Extension\Chart;

use MediaWiki\Extension\JsonConfig\JCSingleton;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\Chart\DataPageResolver
 */
class DataPageResolverTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValues( [
			'JsonConfigs' => [
				'Tabular.JsonConfig' => [
					'namespace' => 486,
					'nsName' => 'Data',
					'pattern' => '/.\.tab$/',
					'license' => 'CC0-1.0',
					'isLocal' => true,
					'store' => true,
				],
			],
			'JsonConfigModels' => [
				'Tabular.JsonConfig' => 'JsonConfig\JCTabularContent',
			],
		] );
		JCSingleton::init( true );
		$namespaces = $this->getServiceContainer()->getContentLanguage()->getNamespaces();
		if ( !array_key_exists( NS_DATA, $namespaces ) ) {
			$this->overrideConfigValue( 'ExtraNamespaces', [
				NS_DATA => 'Data',
				NS_DATA_TALK => 'Data_talk',
			] );
		}
	}

	protected function tearDown(): void {
		parent::tearDown();
		JCSingleton::init( true );
	}

	/**
	 * @dataProvider providePageNames
	 */
	public function testNormalizePageNameInDataNamespace(
		string $pageName,
		?string $expected,
		?string $expectedResolved
	): void {
		$resolver = new DataPageResolver();

		$this->assertSame(
			$expected,
			$resolver->normalizePageNameInDataNamespace( $pageName )
		);
	}

	/**
	 * @dataProvider providePageNames
	 */
	public function testResolvePageInDataNamespace(
		string $pageName,
		?string $expected,
		?string $expectedResolved
	): void {
		$resolver = new DataPageResolver();
		$title = $resolver->resolvePageInDataNamespace( $pageName );

		$this->assertSame( $expectedResolved, $title ? $title->getDBkey() : null );
	}

	public static function providePageNames(): array {
		return [
			'unprefixed' => [ 'Example.tab', 'Example.tab', 'Example.tab' ],
			'prefixed' => [ 'Data:Example.tab', 'Example.tab', 'Example.tab' ],
			'prefixed with spaces' => [ 'Data:Example data.tab', 'Example data.tab', 'Example_data.tab' ],
			'unprefixed with colon' => [ 'Example:2026.tab', 'Example:2026.tab', 'Example:2026.tab' ],
			'invalid' => [ 'Example#section.tab', null, null ],
		];
	}
}
