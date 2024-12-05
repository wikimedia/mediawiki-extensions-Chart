/*!
 * VisualEditor MWChartContextItem class.
 *
 * @copyright See AUTHORS.txt
 */

/**
 * Context item for a chart node.
 *
 * @class
 * @extends ve.ui.LinearContextItem
 *
 * @param {ve.ui.LinearContext} context Context the item is in
 * @param {ve.dm.Model} model Model the item is related to
 * @param {Object} config Configuration options
 */
ve.ui.MWChartContextItem = function VeUiMWChartContextItem() {
	// Parent constructor
	ve.ui.MWChartContextItem.super.apply( this, arguments );

	this.$element.addClass( 've-ui-mwChartContextItem' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWChartContextItem, ve.ui.LinearContextItem );

/* Static Properties */

ve.ui.MWChartContextItem.static.name = 'chart';

ve.ui.MWChartContextItem.static.icon = 'chart';

ve.ui.MWChartContextItem.static.label = OO.ui.deferMsg( 'chart-visualeditor-mwchartdialog-title' );

ve.ui.MWChartContextItem.static.modelClasses = [ ve.dm.MWChartNode ];

ve.ui.MWChartContextItem.static.embeddable = false;

ve.ui.MWChartContextItem.static.commandName = 'chart';

ve.ui.MWChartContextItem.static.suppresses = [ 'transclusion' ];

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWChartContextItem.prototype.getDescription = function () {
	return ve.ce.nodeFactory.getDescription( this.model );
};

/* Registration */

ve.ui.contextItemFactory.register( ve.ui.MWChartContextItem );
