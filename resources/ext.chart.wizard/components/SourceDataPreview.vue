<template>
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
			@click.prevent="renderPreview"
		>
			{{ $i18n( 'chart-wizard-preview-error-retry' ) }}
		</cdx-button>
	</div>
	<div
		v-else
		class="ext-chart-wizard__preview--data"
	>
		<pre
			v-if="sourcePreview.length"
			class="ext-chart-wizard__preview--source"
		>{{ sourcePreview }}</pre>
	</div>
</template>

<script>
const {
	defineComponent,
	onBeforeUnmount,
	ref,
	watch,
	Ref
} = require( 'vue' );
const { storeToRefs } = require( 'pinia' );
const { CdxButton, CdxMessage } = require( '../../../codex.js' );
const useChartStore = require( '../stores/chart.js' );
const { createRequestController } = require( '../requestUtils.js' );

module.exports = exports = defineComponent( {
	name: 'SourceDataPreview',
	components: { CdxButton, CdxMessage },
	setup() {
		const api = new mw.Api();
		const { initialLoad, source } = storeToRefs( useChartStore() );

		/**
		 * Whether the source data preview request failed.
		 *
		 * @type {Ref<boolean>}
		 */
		const previewError = ref( false );

		/**
		 * Preview of the source content.
		 *
		 * @type {Ref<string>}
		 */
		const sourcePreview = ref( '' );
		const previewRequests = createRequestController( api );

		async function renderPreview() {
			const requestId = previewRequests.supersede();
			sourcePreview.value = '';
			previewError.value = false;
			if ( !source.value ) {
				initialLoad.value = false;
				return;
			}
			try {
				const response = await api.get( {
					action: 'query',
					format: 'json',
					prop: 'revisions',
					rvprop: 'content',
					titles: source.value,
					formatversion: 2
				} );
				if ( !previewRequests.isLatest( requestId ) ) {
					return;
				}
				const page = response.query.pages[ 0 ];
				if ( page.missing ) {
					previewError.value = true;
					return;
				}
				sourcePreview.value = JSON.stringify( JSON.parse(
					page.revisions[ 0 ].content
				), null, '    ' );
				previewError.value = false;
			} catch ( e ) {
				if ( previewRequests.isLatest( requestId ) ) {
					sourcePreview.value = '';
					previewError.value = true;
				}
			} finally {
				if ( previewRequests.isLatest( requestId ) ) {
					initialLoad.value = false;
				}
			}
		}

		watch( source, renderPreview, { immediate: true } );
		onBeforeUnmount( () => previewRequests.supersede() );

		return {
			previewError,
			renderPreview,
			sourcePreview
		};
	}
} );
</script>
