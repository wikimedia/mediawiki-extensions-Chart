<template>
	<cdx-accordion
		v-if="isMobile"
		class="ext-chart-wizard__preview-accordion"
	>
		<template #title>
			{{ $i18n( 'chart-wizard-preview-accordion' ) }}
		</template>
		<chart-preview></chart-preview>
	</cdx-accordion>
	<chart-form
		:chart-is-new="chartIsNew"
		:base-rev-id="chartBaseRevId"
	></chart-form>
	<chart-preview v-if="!isMobile"></chart-preview>
</template>

<script>
const { onBeforeMount, onBeforeUnmount, onMounted, defineComponent, ref, Ref } = require( 'vue' );
const { storeToRefs } = require( 'pinia' );
const { CdxAccordion } = require( '../../../codex.js' );
const ChartForm = require( './ChartForm.vue' );
const ChartPreview = require( './ChartPreview.vue' );
const useChartStore = require( '../stores/chart.js' );

module.exports = exports = defineComponent( {
	name: 'ChartWizard',
	components: {
		CdxAccordion,
		ChartForm,
		ChartPreview
	},
	props: {
		chartDefinition: { type: Object, required: true },
		chartIsNew: { type: Boolean, required: true },
		chartBaseRevId: { type: Number, default: 0 }
	},
	setup( { chartDefinition } ) {
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

		const mediaQuery = window.matchMedia( '(max-width: 1119px)' );

		/**
		 * Whether the window width is less than the max desktop breakpoint.
		 *
		 * @type {Ref<boolean>}
		 */
		const isMobile = ref( mediaQuery.matches );

		/**
		 * Handle changes to the media query for mobile vs desktop.
		 *
		 * @param {MediaQueryListEvent} event
		 */
		function onMatchMediaChange( event ) {
			isMobile.value = event.matches;
		}

		onBeforeMount( () => {
			// Set refs in the store for each field based on what was passed in from the server.
			if ( chartDefinition.source ) {
				source.value = mw.Title.newFromText(
					chartDefinition.source,
					mw.config.get( 'wgNamespaceIds' ).data
				).getPrefixedText();
			}
			license.value = chartDefinition.license || mw.config.get( 'chartAllowedLicenses' )[ 0 ];
			mediawikiCategories.value = chartDefinition.mediawikiCategories || [];
			title.value = chartDefinition.title || {};
			subtitle.value = chartDefinition.subtitle || {};
			type.value = ( chartDefinition.type === 'rect' ? 'bar' : chartDefinition.type ) || 'line';
			xAxis.value = chartDefinition.xAxis || {};
			yAxis.value = chartDefinition.yAxis || {};
			transform.value = chartDefinition.transform || {};
		} );

		onMounted( () => mediaQuery.addEventListener( 'change', onMatchMediaChange ) );

		onBeforeUnmount( () => mediaQuery.removeEventListener( 'change', onMatchMediaChange ) );

		return { isMobile };
	}
} );
</script>
