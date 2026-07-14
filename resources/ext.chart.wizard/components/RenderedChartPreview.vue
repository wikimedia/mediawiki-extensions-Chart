<template>
	<cdx-message
		v-if="chartDefinition.source && previewUsesLanguageFallback"
		class="ext-chart-wizard__preview-language-fallback"
		type="notice"
		inline
	>
		{{ $i18n(
			'chart-wizard-preview-language-fallback',
			currentLanguageName
		).text() }}
	</cdx-message>
	<div
		v-if="previewError"
		class="ext-chart-wizard__preview--error"
	>
		<cdx-message
			type="error"
			inline
		>
			{{ $i18n( 'chart-wizard-preview-error' ) }}
		</cdx-message>
		<cdx-button
			weight="primary"
			@click.prevent="renderPreviewImmediately"
		>
			{{ $i18n( 'chart-wizard-preview-error-retry' ) }}
		</cdx-button>
	</div>
	<div
		v-else
		ref="previewContainer"
		class="ext-chart-wizard__preview--chart"
	>
	</div>
</template>

<script>
const {
	computed,
	defineComponent,
	nextTick,
	onBeforeUnmount,
	ref,
	useTemplateRef,
	watch,
	Ref
} = require( 'vue' );
const { storeToRefs } = require( 'pinia' );
const { CdxButton, CdxMessage } = require( '../../../codex.js' );
const useChartStore = require( '../stores/chart.js' );
const {
	createCancelableDebounce,
	createRequestController
} = require( '../requestUtils.js' );
const previewDebounceDelay = 300;

module.exports = exports = defineComponent( {
	name: 'RenderedChartPreview',
	components: { CdxButton, CdxMessage },
	setup() {
		const api = new mw.Api();
		const { chartDefinition, initialLoad, currentLanguage } = storeToRefs( useChartStore() );
		const currentLanguageName = computed( () => {
			const languageNames = mw.config.get( 'chartLanguages' ) || {};
			return languageNames[ currentLanguage.value ] || currentLanguage.value;
		} );
		const previewUsesLanguageFallback = computed( () => {
			const definition = chartDefinition.value;
			return [
				definition.title,
				definition.subtitle,
				definition.xAxis && definition.xAxis.title,
				definition.yAxis && definition.yAxis.title
			].some( ( translations ) => translations &&
				Object.keys( translations ).length &&
				!Object.prototype.hasOwnProperty.call( translations, currentLanguage.value )
			);
		} );

		/**
		 * Whether the rendered chart preview request failed.
		 *
		 * @type {Ref<boolean>}
		 */
		const previewError = ref( false );

		/**
		 * Container for rendered chart preview HTML.
		 *
		 * @type {Ref<HTMLElement|null>}
		 */
		const previewContainer = useTemplateRef( 'previewContainer' );

		const debouncedRenderPreview = createCancelableDebounce(
			( requestId ) => renderPreview( requestId ),
			previewDebounceDelay
		);
		const previewRequests = createRequestController( api, debouncedRenderPreview.cancel );

		function preparePreviewContent( container ) {
			Array.from( container.querySelectorAll( 'wiki-chart' ) ).forEach( ( chart ) => {
				const svg = chart.querySelector( 'svg' );
				const height = svg && svg.getAttribute( 'height' );
				if ( height ) {
					chart.style.minHeight = `${ height }px`;
				}
			} );
		}

		async function injectPreview( response, requestId ) {
			// Ignore responses superseded before resource loading begins.
			if ( !previewRequests.isLatest( requestId ) ) {
				return;
			}
			const parse = response.parse;
			if ( parse.jsconfigvars ) {
				mw.config.set( parse.jsconfigvars );
			}
			const modules = ( parse.modules || [] ).concat( parse.modulestyles || [] );
			if ( modules.length ) {
				await mw.loader.using( modules );
			}
			// The request may become stale while modules are loading.
			if ( !previewRequests.isLatest( requestId ) ) {
				return;
			}
			previewError.value = false;
			previewContainer.value.innerHTML = parse.text;
			preparePreviewContent( previewContainer.value );
		}

		async function renderPreview( requestId ) {
			if ( !chartDefinition.value.source ) {
				previewError.value = false;
				initialLoad.value = false;
				return;
			}
			previewError.value = false;
			await nextTick();
			try {
				const response = await api.post( {
					action: 'parse',
					formatversion: 2,
					title: mw.config.get( 'chartPageName' ),
					text: JSON.stringify( chartDefinition.value ),
					contentmodel: 'Chart.JsonConfig',
					prop: 'text|modules|modulestyles|jsconfigvars',
					preview: true,
					disableeditsection: true,
					disablelimitreport: 1,
					useskin: mw.config.get( 'skin' ),
					uselang: currentLanguage.value
				}, {
					headers: { 'Promise-Non-Write-API-Action': 'true' }
				} );
				await injectPreview( response, requestId );
			} catch ( e ) {
				if ( previewRequests.isLatest( requestId ) ) {
					previewError.value = true;
				}
			} finally {
				if ( previewRequests.isLatest( requestId ) ) {
					initialLoad.value = false;
				}
			}
		}

		function renderPreviewImmediately() {
			return renderPreview( previewRequests.supersede() );
		}

		function schedulePreview() {
			const requestId = previewRequests.supersede();
			previewError.value = false;
			if ( !chartDefinition.value.source ) {
				renderPreview( requestId );
				return;
			}
			debouncedRenderPreview( requestId );
		}

		watch( chartDefinition, ( definition, previousDefinition ) => {
			if ( previousDefinition === undefined ) {
				renderPreviewImmediately();
			} else {
				schedulePreview();
			}
		}, { deep: true, immediate: true } );
		watch( currentLanguage, renderPreviewImmediately );
		onBeforeUnmount( () => previewRequests.supersede() );

		return {
			chartDefinition,
			currentLanguageName,
			previewContainer,
			previewError,
			previewUsesLanguageFallback,
			renderPreviewImmediately
		};
	}
} );
</script>
