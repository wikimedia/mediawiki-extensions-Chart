<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\Chart\Tests\Integration;

use MediaWiki\Extension\Chart\DataPageResolver;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\Chart\DataPageResolver
 */
class DataPageResolverTest extends MediaWikiIntegrationTestCase {

	use ChartIntegrationTestTrait;

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

		$this->assertSame( $expectedResolved, $title?->getDBkey() );
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
