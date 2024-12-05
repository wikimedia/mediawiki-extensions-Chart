/*!
 * VisualEditor user interface MWChartDialog class.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Dialog for editing MediaWiki charts
 *
 * @class
 * @extends ve.ui.MWExtensionDialog
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.MWChartDialog = function VeUiMWChartDialog() {
	// Parent constructor
	ve.ui.MWChartDialog.super.apply( this, arguments );

	this.$element.addClass( 've-ui-mwChartDialog' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWChartDialog, ve.ui.NodeDialog );

/* Static properties */

ve.ui.MWChartDialog.static.name = 'chart';

// ve.ui.MWChartDialog.static.size = 'large';

ve.ui.MWChartDialog.static.title = OO.ui.deferMsg( 'chart-visualeditor-mwchartdialog-title' );

ve.ui.MWChartDialog.static.modelClasses = [ ve.dm.MWChartNode ];

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWChartDialog.prototype.initialize = function () {
	// Parent method
	ve.ui.MWChartDialog.super.prototype.initialize.call( this );

	// Properties
	this.panel = new OO.ui.PanelLayout( {
		padded: true
	} );
	this.chartInput = new OO.ui.TextInputWidget( {
		label: ve.msg( 'chart-visualeditor-mwchartdialog-chart-label' )
	} );
	this.dataInput = new OO.ui.TextInputWidget( {
		label: ve.msg( 'chart-visualeditor-mwchartdialog-data-label' )
	} );

	// Initialization
	this.$element.addClass( 've-ui-mwChartDialog' );
	this.panel.$element.append( this.chartInput.$element );
	this.panel.$element.append( this.dataInput.$element );
	this.$body.append( this.panel.$element );
};

/**
 * @inheritdoc
 */
ve.ui.MWChartDialog.prototype.getSetupProcess = function ( data ) {
	return ve.ui.MWChartDialog.super.prototype.getSetupProcess.call( this, data )
		.next( () => {
			const node = this.getSelectedNode();
			const template = ve.getProp( node, 'element', 'attributes', 'mw', 'parts', 0, 'template' );
			this.chartInput.setValue( ( ve.getProp( template, 'target', 'wt' ) || '' ).replace( /^#chart:/, '' ) );
			this.dataInput.setValue( ve.getProp( template, 'params', 'data', 'wt' ) || '' );
		} );
};

/**
 * @inheritdoc
 */
ve.ui.MWChartDialog.prototype.getReadyProcess = function ( data ) {
	return ve.ui.MWChartDialog.super.prototype.getReadyProcess.call( this, data )
		.next( () => {
			this.chartInput.focus();
		} );
};

/**
 * @inheritdoc
 */
ve.ui.MWChartDialog.prototype.getActionProcess = function ( action ) {
	if ( action === 'done' ) {
		return new OO.ui.Process( () => {
			const chartName = this.chartInput.getValue();
			const dataName = this.dataInput.getValue();
			// Replace
			const mwData = {
				parts: [ { template: {
					i: 0,
					target: {
						function: 'chart',
						wt: '#chart:' + chartName
					},
					params: {}
				} } ]
			};
			if ( dataName ) {
				mwData.parts[ 0 ].template.params.data = { wt: dataName };
			}
			const surfaceModel = this.getFragment().getSurface();
			if ( this.selectedNode ) {
				surfaceModel.change(
					ve.dm.TransactionBuilder.static.newFromAttributeChanges(
						surfaceModel.getDocument(),
						this.selectedNode.getOuterRange().start,
						{ mw: mwData }
					)
				);
			} else {
				this.getFragment().collapseToEnd().insertContent( [
					{
						type: 'mwChart',
						attributes: {
							mw: mwData
						}
					}
				] );
			}
			this.close( { action: 'done' } );
		} );
	}
	// Parent method
	return ve.ui.MWChartDialog.super.prototype.getActionProcess.call( this, action );
};

/**
 * @inheritdoc
 */
ve.ui.MWChartDialog.prototype.getBodyHeight = function () {
	return 150;
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.MWChartDialog );
