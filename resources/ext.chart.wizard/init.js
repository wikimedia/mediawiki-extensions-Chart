'use strict';

const form = document.getElementById( 'ext-chart-wizard' );

if ( form ) {
	const Vue = require( 'vue' );
	const App = require( './components/ChartWizard.vue' );
	const { createPinia } = require( 'pinia' );

	Vue.createMwApp( App, {
		chartDefinition: mw.config.get( 'chartDefinition' ),
		chartIsNew: mw.config.get( 'chartIsNew' )
	} )
		.use( createPinia() )
		.mount( form );
}
