<?php

namespace MediaWiki\Extension\Chart;

use JsonConfig\JCTitle;

class ParsedArguments {
	private ?JCTitle $definitionPageTitle;

	private ?JCTitle $dataPageTitle;

	private array $options;

	/**
	 * @var array[] List of errors, with 'key' and 'params'
	 */
	private array $errors;

	/**
	 * @param ?JCTitle $definitionPageTitle Chart definition page title.
	 * @param ?JCTitle $dataPageTitle Tabular data page.
	 * @param array{width?:string,height?:string} $options Additional rendering options:
	 *    'width': Width of the chart, in pixels. Overrides width specified in the chart definition
	 *    'height': Height of the chart, in pixels. Overrides height specified in the chart definition.
	 * @param array<array{key:string, params:array}> $errors An array of errors with key and params
	 */
	public function __construct(
		?JCTitle $definitionPageTitle,
		?JCTitle $dataPageTitle,
		array $options,
		array $errors
	) {
		$this->definitionPageTitle = $definitionPageTitle;
		$this->dataPageTitle = $dataPageTitle;
		$this->options = $options;
		$this->errors = $errors;
	}

	public function getDefinitionPageTitle(): ?JCTitle {
		return $this->definitionPageTitle;
	}

	public function getDataPageTitle(): ?JCTitle {
		return $this->dataPageTitle;
	}

	public function getOptions(): array {
		return $this->options;
	}

	public function getErrors(): array {
		return $this->errors;
	}
}
