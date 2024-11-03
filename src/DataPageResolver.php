<?php

namespace MediaWiki\Extension\Chart;

use JsonConfig\JCSingleton;
use JsonConfig\JCTitle;
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
	 * @param string $pageName Name of a Data page, without the namespace prefix
	 * @return ?JCTitle JCTitle object for Data namespace page (or null if invalid)
	 * @throws UnexpectedValueException
	 */
	public function resolvePageInDataNamespace( string $pageName ): ?JCTitle {
		// Note: parseTitle cannot return false when given a string
		return JCSingleton::parseTitle( $pageName, NS_DATA );
	}

}
