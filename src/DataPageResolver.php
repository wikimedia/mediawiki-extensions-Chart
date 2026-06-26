<?php

namespace MediaWiki\Extension\Chart;

use MediaWiki\Extension\JsonConfig\JCSingleton;
use MediaWiki\Extension\JsonConfig\JCTitle;
use MediaWiki\Title\Title;
use UnexpectedValueException;

class DataPageResolver {
	/**
	 * Look up a page in the Data: namespace. This takes a string like "Foo.tab" and returns a
	 * JCTitle object corresponding to Data:Foo.tab; this is a TitleValue subclass which has
	 * the attached JsonConfig configuration blob for the underlying data type.
	 *
	 * All data pages are assumed to live in the NS_DATA namespace, although JsonConfig
	 * seems to allow more complex configs that are not fully supported.
	 *
	 * @param string $pageName Name of a Data page, with or without the namespace prefix
	 * @return ?JCTitle JCTitle object for Data namespace page (or null if invalid)
	 * @throws UnexpectedValueException
	 */
	public function resolvePageInDataNamespace( string $pageName ): ?JCTitle {
		$title = Title::newFromText( $pageName );
		if ( $title && $title->getNamespace() === NS_DATA ) {
			$pageName = $title->getDBkey();
		}

		// Note: parseTitle can return false when given an empty string
		return JCSingleton::parseTitle( $pageName, NS_DATA ) ?: null;
	}

	/**
	 * Normalize Data namespace page names for storing in chart definitions.
	 *
	 * @param string $pageName Name of a Data page, with or without the namespace prefix
	 * @return ?string Page name without the namespace prefix, or null if invalid
	 */
	public function normalizePageNameInDataNamespace( string $pageName ): ?string {
		if ( !$this->resolvePageInDataNamespace( $pageName ) ) {
			return null;
		}

		$title = Title::newFromText( $pageName );
		if ( $title && $title->getNamespace() === NS_DATA ) {
			return $title->getText();
		}
		return $pageName;
	}

}
