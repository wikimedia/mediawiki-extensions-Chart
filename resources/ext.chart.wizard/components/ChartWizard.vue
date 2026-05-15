<template>
	<chart-form :chart-is-new="chartIsNew"></chart-form>
	<chart-preview></chart-preview>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { storeToRefs } = require( 'pinia' );
const ChartForm = require( './ChartForm.vue' );
const ChartPreview = require( './ChartPreview.vue' );
const useChartStore = require( '../stores/chart.js' );

module.exports = exports = defineComponent( {
	name: 'ChartWizard',
	components: {
		ChartForm,
		ChartPreview
	},
	props: {
		chartDefinition: { type: Object, required: true },
		chartIsNew: { type: Boolean, required: true }
	},
	setup( { chartDefinition } ) {
		// Set refs in the store for each field based on what was passed in from the server.
		const { source } = storeToRefs( useChartStore() );
		if ( chartDefinition.source ) {
			source.value = mw.Title.newFromText(
				chartDefinition.source,
				mw.config.get( 'wgNamespaceIds' ).data
			).getPrefixedText();
		}
	}
} );
</script>
