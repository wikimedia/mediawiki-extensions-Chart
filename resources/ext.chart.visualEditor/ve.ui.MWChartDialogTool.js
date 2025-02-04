/*!
 * VisualEditor UserInterface MWChartDialogTool class.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki UserInterface dialog tool.
 *
 * @class
 * @extends ve.ui.FragmentDialogTool
 * @constructor
 * @param {OO.ui.ToolGroup} toolGroup
 * @param {Object} [config] Configuration options
 */

ve.ui.MWChartDialogTool = function VeUiMWChartDialogTool( toolGroup, config ) {
	ve.ui.MWChartDialogTool.super.call( this, toolGroup, config );
};
OO.inheritClass( ve.ui.MWChartDialogTool, ve.ui.FragmentWindowTool );
ve.ui.MWChartDialogTool.static.name = 'chart';
ve.ui.MWChartDialogTool.static.group = 'object';
ve.ui.MWChartDialogTool.static.icon = 'chart';
ve.ui.MWChartDialogTool.static.title = OO.ui.deferMsg(
	'chart-visualeditor-mwchartdialog-title'
);
ve.ui.MWChartDialogTool.static.modelClasses = [ ve.dm.MWChartNode ];
ve.ui.MWChartDialogTool.static.commandName = 'chart';
ve.ui.toolFactory.register( ve.ui.MWChartDialogTool );

ve.ui.commandRegistry.register(
	new ve.ui.Command(
		'chart', 'window', 'open',
		{ args: [ 'chart' ], supportedSelections: [ 'linear' ] }
	)
);

ve.ui.sequenceRegistry.register(
	new ve.ui.Sequence( 'wikitextChart', 'chart', '{#chart:', 6 )
);

ve.ui.commandHelpRegistry.register( 'insert', 'chart', {
	sequences: [ 'wikitextChart' ],
	label: OO.ui.deferMsg( 'chart-visualeditor-mwchartdialog-title' )
} );
