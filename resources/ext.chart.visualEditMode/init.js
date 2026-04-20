'use strict';

const form = document.querySelector( '.mw-editform' );

if ( form ) {
	const Vue = require( 'vue' );
	const App = require( './components/ChartVisualEditor.vue' );
	const { createPinia } = require( 'pinia' );

	Vue.createMwApp( App )
		.use( createPinia() )
		.mount( form );
}
