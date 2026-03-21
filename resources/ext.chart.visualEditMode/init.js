'use strict';

const form = document.querySelector( '.mw-editform' );

if ( form ) {
	const Vue = require( 'vue' );
	const App = require( './ChartVisualEditor.vue' );
	Vue.createMwApp( App )
		.mount( form );
}
