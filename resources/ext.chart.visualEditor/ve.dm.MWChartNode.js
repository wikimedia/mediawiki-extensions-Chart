/*!
 * VisualEditor DataModel MWChartNode class.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * DataModel MediaWiki chart node.
 *
 * @class
 * @extends ve.dm.MWTransclusionInlineNode
 *
 * @constructor
 * @param {Object} [element]
 */
ve.dm.MWChartNode = function VeDmMWChartNode() {
	// Parent constructor
	ve.dm.MWChartNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.MWChartNode, ve.dm.MWTransclusionInlineNode );

/* Static members */

ve.dm.MWChartNode.static.name = 'mwChart';

ve.dm.MWChartNode.static.inlineType = 'mwChart';

ve.dm.MWChartNode.static.isContent = true;

ve.dm.MWChartNode.static.matchTagNames = null;

ve.dm.MWChartNode.static.matchRdfaTypes = [ 'mw:Transclusion' ];

// Allow TemplateStyles in the matching element
ve.dm.MWChartNode.static.allowedRdfaTypes = [ 'mw:Extension/templatestyles' ];

ve.dm.MWChartNode.static.enableAboutGrouping = true;

// This node has the same specificity as ve.dm.MWTranslcusionNode and only matches
// ahead of it because it is registered later (via a dependency in ResourceLoader)
// TODO: Make this less fragile.
ve.dm.MWChartNode.static.matchFunction = function ( domElement ) {
	const mwDataJSON = domElement.getAttribute( 'data-mw' );
	const mwData = mwDataJSON ? JSON.parse( mwDataJSON ) : {};
	const mwPart = ( mwData.parts || [] )[ 0 ];
	if ( !mwPart ) {
		return false;
	}
	return ve.getProp( mwPart, 'template', 'target', 'function' ) === 'chart';
};

/* Registration */

ve.dm.modelRegistry.register( ve.dm.MWChartNode );
