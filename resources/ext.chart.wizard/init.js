'use strict';

const container = document.querySelector( '.ext-chart-wizard-container' );

if ( container ) {
	const Vue = require( 'vue' );
	const App = require( './components/ChartWizard.vue' );
	const { createPinia } = require( 'pinia' );

	Vue.createMwApp( App, {
		chartDefinition: mw.config.get( 'chartDefinition' ),
		chartIsNew: mw.config.get( 'chartIsNew' ),
		chartBaseRevId: mw.config.get( 'chartBaseRevId' )
	} )
		.use( createPinia() )
		.mount( container );
}
