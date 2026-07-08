<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\Chart;

use MediaWiki\Config\HashConfig;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Session\Session;
use MediaWiki\Skin\SkinTemplate;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Tests\Unit\FakeQqxMessageLocalizer;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;
use MockTitleTrait;

/**
 * @covers \MediaWiki\Extension\Chart\Hooks
 */
class HooksTest extends MediaWikiUnitTestCase {

	use MockTitleTrait;

	/**
	 * @dataProvider provideOnSkinTemplateNavigation__Universal
	 */
	public function testOnSkinTemplateNavigation__Universal(
		array $opts = [],
		array $expectedTabs = [],
	): void {
		$opts = array_merge(
			[
				'chartWizardEnabled' => true,
				'canEdit' => true,
				'contentModel' => JCChartContent::CONTENT_MODEL,
				'tabs' => [
					'view' => [],
					'edit' => [],
					'history' => [],
				],
			],
			$opts
		);
		$title = $opts['title'] ?? function ( self $testCase ) use ( $opts ) {
			$ret = $testCase->makeMockTitle(
				isset( $opts['title'] ) ? $opts['title']( $this ) : 'Data:Example.Line.chart',
				[ 'contentModel' => $opts['contentModel'] ]
			);
			$ret->expects( $this->atMost( 1 ) )
				->method( 'isSpecial' )
				->willReturn( $ret->getNamespace() === NS_SPECIAL );
			return $ret;
		};

		$user = $this->createNoOpMock( User::class, [ 'probablyCan' ] );
		$user->expects( $this->atMost( 1 ) )
			->method( 'probablyCan' )
			->willReturn( $opts['canEdit'] );
		$skinTemplate = $this->createNoOpMock( SkinTemplate::class,
			[ 'getUser', 'getTitle', 'getRelevantTitle', 'msg' ]
		);
		$skinTemplate->expects( $this->atMost( 1 ) )
			->method( 'getUser' )
			->willReturn( $user );
		$skinTemplate->expects( $opts['chartWizardEnabled'] ? $this->once() : $this->never() )
			->method( 'getTitle' )
			->willReturn( $title( $this ) );
		$relevantTitle = ( $opts['relevantTitle'] ?? $title )( $this );
		$skinTemplate->expects( $this->atMost( 2 ) )
			->method( 'getRelevantTitle' )
			->willReturn( $relevantTitle );
		$skinTemplate->expects( $this->atMost( 1 ) )
			->method( 'msg' )
			->willReturnCallback( [ new FakeQqxMessageLocalizer(), 'msg' ] );

		$handler = $this->getHandler( $opts['chartWizardEnabled'], $relevantTitle );
		$links = [ 'views' => $opts['tabs'] ];
		$handler->onSkinTemplateNavigation__Universal( $skinTemplate, $links );

		$this->assertSame( $expectedTabs, array_keys( $links['views'] ) );
	}

	public static function provideOnSkinTemplateNavigation__Universal(): array {
		return [
			'chart definition page, wizard enabled, can edit' => [
				'opts' => [],
				'expectedTabs' => [ 'view', 'edit', 'chart-wizard', 'history' ],
			],
			'chart definition page, wizard enabled, cannot edit' => [
				'opts' => [
					'canEdit' => false,
					'tabs' => [
						'view' => [],
						// Appears as 'View source'
						'edit' => [],
						'history' => [],
					],
				],
				'expectedTabs' => [ 'view', 'edit', 'history' ],
			],
			'not a chart definition page, wizard enabled, can edit' => [
				'opts' => [
					'title' => static fn ( self $testCase ) => $testCase->makeMockTitle( 'Data:Example.tab', [
						'contentModel' => 'Metallica.JsonConfig',
					] ),
					'relevantTitle' => static fn ( self $testCase ) => $testCase->makeMockTitle( 'Data:Example.tab', [
						'contentModel' => 'Metallica.JsonConfig',
					] ),
					'tabs' => [
						'view' => [],
						'edit' => [],
						'history' => [],
					],
				],
				'expectedTabs' => [ 'view', 'edit', 'history' ],
			],
			'special page, wizard enabled, can edit' => [
				'opts' => [
					'title' => static function ( self $testCase ) {
						$ret = $testCase->makeMockTitle(
							'ChartWizard/Data:Example.Line.chart',
							[ 'namespace' => NS_SPECIAL ],
						);
						$ret->method( 'isSpecial' )
							->willReturn( true );
						return $ret;
					},
					'relevantTitle' => static fn ( self $testCase ) => $testCase
						->makeMockTitle( 'Data:Example.Line.chart' ),
					'tabs' => [
						'view' => [],
						'edit' => [],
						'history' => [],
					],
				],
				'expectedTabs' => [ 'view', 'edit', 'chart-wizard', 'history' ],
			],
			'special page, wizard disabled, can edit' => [
				'opts' => [
					'title' => static fn ( self $testCase ) => $testCase->makeMockTitle(
						'ChartWizard/Data:Example.Line.chart',
						[ 'namespace' => NS_SPECIAL ],
					),
					'relevantTitle' => static fn ( self $testCase ) => $testCase
						->makeMockTitle( 'Data:Example.Line.chart' ),
					'chartWizardEnabled' => false,
					'canEdit' => true,
					'tabs' => [
						'view' => [],
						'edit' => [],
						'history' => [],
					],
				],
				'expectedTabs' => [ 'view', 'edit', 'history' ],
			],
			'only view tab beforehand' => [
				[ 'tabs' => [ 'view' => [] ] ],
				[ 'view', 'chart-wizard' ],
			],
			'no applicable tabs beforehand' => [
				[ 'tabs' => [ 'foo' => [], 'bar' => [] ] ],
				[ 'foo', 'bar', 'chart-wizard' ],
			],
		];
	}

	public function testChangeTags(): void {
		$session = $this->createNoOpMock( Session::class, [ 'get', 'remove' ] );
		$session->expects( $this->once() )
			->method( 'get' )
			->with( Hooks::SESSION_KEY )
			->willReturn( true );
		$session->expects( $this->once() )
			->method( 'remove' )
			->with( Hooks::SESSION_KEY );
		$request = $this->createConfiguredMock( FauxRequest::class, [ 'getSession' => $session ] );
		$handler = $this->getHandler(
			chartWizardEnabled: true,
			relevantTitle: $this->makeMockTitle( 'Data:Example.Line.chart' ),
			request: $request,
		);
		$tags = [];
		$this->assertTrue( $handler::onRegisterTags( $tags ) );
		$this->assertContains( Hooks::CHANGE_TAG, $tags );
		$recentChange = $this->createNoOpMock( RecentChange::class, [ 'addTags' ] );
		$recentChange->expects( $this->once() )
			->method( 'addTags' )
			->with( Hooks::CHANGE_TAG );
		$handler->onRecentChange_save( $recentChange );
	}

	private function getHandler(
		bool $chartWizardEnabled,
		Title $relevantTitle,
		?FauxRequest $request = null
	): Hooks {
		$specialPageFactory = $this->createNoOpMock( SpecialPageFactory::class, [ 'getPage' ] );
		$specialPageFactory->expects( $this->atMost( 1 ) )
			->method( 'getPage' )
			->with( 'ChartWizard' )
			->willReturnCallback( function ( $name ) use ( $relevantTitle ) {
				$specialPage = $this->createNoOpMock( SpecialPage::class, [ 'getPageTitle' ] );
				$specialPage->expects( $this->atMost( 1 ) )
					->method( 'getPageTitle' )
					->with( $relevantTitle )
					->willReturn( $relevantTitle );
				return $specialPage;
			} );
		$config = new HashConfig( [ 'ChartWizardEnabled' => $chartWizardEnabled ] );
		return new Hooks( $specialPageFactory, $config, $request );
	}
}
