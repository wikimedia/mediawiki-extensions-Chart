/*!
 * VisualEditor ContentEditable MWChartNode class.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * ContentEditable MediaWiki chart node.
 *
 * @class
 * @extends ve.ce.LeafNode
 *
 * @constructor
 * @param {ve.dm.MWChartNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
ve.ce.MWChartNode = function VeCeMWChartNode() {
	// Parent constructor
	ve.ce.MWChartNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ce.MWChartNode, ve.ce.MWTransclusionInlineNode );

/* Static properties */

ve.ce.MWChartNode.static.name = 'mwChart';

ve.ce.MWChartNode.static.primaryCommandName = 'chart';

ve.ce.MWChartNode.static.iconWhenInvisible = 'chart';

ve.ce.MWChartNode.static.tagName = 'span';

/* Methods */

/**
 * @inheritdoc
 */
ve.ce.MWChartNode.prototype.onSetup = function () {
	// Parent method
	ve.ce.MWChartNode.super.prototype.onSetup.call( this );

	// DOM changes
	this.$element.addClass( 've-ce-mwChartNode' );
};

/* Registration */

ve.ce.nodeFactory.register( ve.ce.MWChartNode );
