<template>
	<chart-form
		:chart-is-new="chartIsNew"
		:base-rev-id="chartBaseRevId"
	></chart-form>
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
		chartIsNew: { type: Boolean, required: true },
		chartBaseRevId: { type: Number, default: 0 }
	},
	setup( { chartDefinition } ) {
		// Set refs in the store for each field based on what was passed in from the server.
		const {
			license,
			mediawikiCategories,
			source,
			subtitle,
			title,
			type,
			xAxis,
			yAxis,
			transform
		} = storeToRefs( useChartStore() );
		if ( chartDefinition.source ) {
			source.value = mw.Title.newFromText(
				chartDefinition.source,
				mw.config.get( 'wgNamespaceIds' ).data
			).getPrefixedText();
		}
		license.value = chartDefinition.license;
		mediawikiCategories.value = chartDefinition.mediawikiCategories;
		subtitle.value = chartDefinition.subtitle;
		title.value = chartDefinition.title;
		type.value = chartDefinition.type || 'line';
		xAxis.value = chartDefinition.xAxis;
		yAxis.value = chartDefinition.yAxis;
		transform.value = chartDefinition.transform;
	}
} );
</script>
