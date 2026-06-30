<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\Chart\Tests\Integration;

use MediaWiki\Extension\JsonConfig\JCSingleton;
use MediaWiki\Title\Title;

trait ChartIntegrationTestTrait {

	protected function setUp(): void {
		parent::setUp();
		$this->configureChartIntegrationTest();
	}

	private function configureChartIntegrationTest(): void {
		// Enable but turn off renderer.
		// This should let us validate other things about
		// the setup and error handling behavior.
		$this->overrideConfigValues( [
			'LanguageCode' => 'en',
			'ChartTransformsEnabled' => true,
			'ChartServiceUrl' => null,
			'ChartCliPath' => null,
			'ChartWizardEnabled' => true,
			'JsonConfigEnableLuaSupport' => true,
			'JsonConfigTransformsEnabled' => true,
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
				],
			],
			'JsonConfigModels' => [
				'Chart.JsonConfig' => 'MediaWiki\Extension\Chart\JCChartContent',
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

	/**
	 * @return array [ Chart definition page content, Title object ]
	 */
	private function editChartIntegrationPage(
		string $pageName,
		string $extension = 'json',
		int $namespace = NS_DATA,
	): array {
		$fileName = __DIR__ . "/../chart-integration/$pageName.$extension";
		$content = file_get_contents( $fileName );
		$title = Title::makeTitle( $namespace, $pageName );
		$this->editPage( $title, $content );
		return [ $content, $title ];
	}

	protected function tearDown(): void {
		parent::tearDown();
		JCSingleton::init( true );
	}
}
