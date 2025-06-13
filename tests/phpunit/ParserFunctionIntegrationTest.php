<?php

namespace MediaWiki\Extension\Chart;

use Article;
use JsonConfig\JCSingleton;
use JsonConfig\Tests\JCTransformTestCase;
use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use stdclass;

/**
 * @covers \MediaWiki\Extension\Chart\ParserFunction
 * @group Database
 */
class ParserFunctionIntegrationTest extends JCTransformTestCase {

	public function setUp(): void {
		parent::setUp();

		// Enable but turn off renderer.
		// This should let us validate other things about
		// the setup and error handling behavior.
		$this->overrideConfigValues( [
			'ChartTransformsEnabled' => true,
			'ChartServiceUrl' => null,
			'ChartCliPath' => null,
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
				'Chart.JsonConfig' => 'MediaWiki\Extension\Chart\JCChartContent'
			],
		] );
		JCSingleton::init( true );

		$tableName = 'Temperature_conversion';
		$fileName = __DIR__ . "/chart-integration/$tableName.lua";
		$content = file_get_contents( $fileName );
		$title = Title::makeTitle( NS_MODULE, $tableName );
		$this->editPage( $title, $content );

		$tableName = 'Chart_input.tab';
		$fileName = __DIR__ . "/chart-integration/$tableName.json";
		$content = file_get_contents( $fileName );
		$title = Title::makeTitle( NS_DATA, $tableName );
		$this->editPage( $title, $content );

		$tableName = 'No_transform_example.chart';
		$fileName = __DIR__ . "/chart-integration/$tableName.json";
		$content = file_get_contents( $fileName );
		$title = Title::makeTitle( NS_DATA, $tableName );
		$this->editPage( $title, $content );

		$tableName = 'Transform_example.chart';
		$fileName = __DIR__ . "/chart-integration/$tableName.json";
		$content = file_get_contents( $fileName );
		$title = Title::makeTitle( NS_DATA, $tableName );
		$this->editPage( $title, $content );

		$tableName = 'No_args_example.chart';
		$fileName = __DIR__ . "/chart-integration/$tableName.json";
		$content = file_get_contents( $fileName );
		$title = Title::makeTitle( NS_DATA, $tableName );
		$this->editPage( $title, $content );
	}

	/**
	 * Ensuring that various data input doesn't cause failures.
	 * @dataProvider provideBadTestCases
	 * @param string $input wikitext input
	 */
	public function testExceptionPrevention( $input ) {
		$rawtitle = Title::makeTitle( NS_MAIN, 'ParserFunctionTest' );
		$context = RequestContext::getMain();
		$article = new Article( $rawtitle );
		$article->setContext( $context );
		$options = ParserOptions::newFromContext( $context );
		$parser = MediaWikiServices::getInstance()->getParserFactory()->getInstance();
		$parser->startExternalParse( $rawtitle, $options, Parser::OT_HTML );
		$linestart = true;
		$revId = 0;
		$parser->parse( $input, $rawtitle, $options, $linestart, true, $revId );
		$this->assertTrue( true, 'Successfully parsed without throwing exception or fatal' );
	}

	public static function provideBadTestCases() {
		return [
			[ '{{#chart:No transform example.chart}}' ],
			[ '{{#chart:No transform example.chart|arg:foo=bar}}' ],
			[ '{{#chart:Transform example.chart}}' ],
			[ '{{#chart:Transform example.chart|arg:foo=bar}}' ],
			[ '{{#chart:No args example.chart}}' ],
			[ '{{#chart:No args example.chart|arg:foo=bar}}' ],
		];
	}

	/**
	 * Confirm the parser function sees the given language code with any variant.
	 *
	 * @dataProvider provideLanguageCases
	 * @param string $contentCode
	 * @param string $langCode
	 */
	public function testLanguageVariant( $contentCode, $langCode ) {
		$this->overrideConfigValue( MainConfigNames::LanguageCode, $contentCode );
		$this->setUserLang( $langCode );

		$mock = $this->createMock( ChartRenderer::class );
		$mock->method( 'renderSVG' )
			->willReturnCallback( static function (
				stdclass $chartDef,
				stdclass $tabularData,
				array $options = []
			): Status {
				return Status::newGood( '<chart-lang>' . $options['locale'] . '</chart-lang>' );
			} );
		$this->setService( 'Chart.ChartRenderer', $mock );

		$services = MediaWikiServices::getInstance();

		$context = RequestContext::getMain();
		$context->setRequest( new FauxRequest( [
			'uselang' => $langCode,
		] ) );

		$rawtitle = Title::makeTitle( NS_MAIN, 'ParserFunctionVariantTest' );
		$article = new Article( $rawtitle );
		$article->setContext( $context );

		$input = '{{#chart:No transform example.chart}}';

		$options = ParserOptions::newFromContext( $context );
		$parser = $services->getParserFactory()->getInstance();
		$parser->startExternalParse( $rawtitle, $options, Parser::OT_HTML );

		$linestart = true;
		$revId = 0;
		$parser->parse( $input, $rawtitle, $options, $linestart, true, $revId );

		$output = $parser
			->getOutput()
			->runOutputPipeline( $options, [] )
			->getContentHolderText();

		$extracted = '';
		if ( preg_match( '/<chart-lang>(.*?)<\/chart-lang>/', $output, $matches ) ) {
			$extracted = $matches[1];
		}

		$this->assertEquals( $langCode, $extracted, 'Returned expected language code' );
	}

	public static function provideLanguageCases() {
		return [
			[ 'en', 'en' ],
			[ 'fr', 'fr' ],
			[ 'zh', 'zh' ],
			[ 'zh', 'zh-hans' ],
			[ 'zh', 'zh-hant' ],
		];
	}
}
