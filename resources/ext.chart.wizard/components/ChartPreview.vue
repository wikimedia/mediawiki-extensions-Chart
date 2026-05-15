<template>
	<div class="ext-chart-wizard__preview">
		<div
			v-if="sourcePreview === null"
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
				@click.prevent="doPreviewSource"
			>
				{{ $i18n( 'chart-wizard-preview-error-retry' ) }}
			</cdx-button>
		</div>
		<div v-else-if="sourcePreview.length">
			<pre class="ext-chart-wizard__preview--source">{{ sourcePreview }}</pre>
		</div>
		<div v-else class="ext-chart-wizard__preview-placeholder">
			<div class="ext-chart-wizard__preview-placeholder-image-wrapper skin-invert">
				<!-- eslint-disable-next-line vue/max-attributes-per-line -->
				<svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" fill="none" viewBox="0 0 34 34">
					<!-- eslint-disable-next-line max-len -->
					<path fill="#54595d" d="M3.69049 29.8362H33.2144V33.5658H0V0H3.69049V29.8362Z" />
					<!-- eslint-disable-next-line max-len -->
					<path fill="#54595d" d="M33.2146 7.6922V25.8736H7.61182V24.0555L15.8413 14.0557L21.3276 20.4192L31.3858 7.6922H33.2146Z" />
				</svg>
			</div>
			<div class="ext-chart-wizard__preview-placeholder-header">
				{{ $i18n( 'chart-wizard-preview-placeholder-title' ).text() }}
			</div>
			<div>
				{{ $i18n( 'chart-wizard-preview-placeholder-description' ).text() }}
			</div>
		</div>
	</div>
</template>

<script>
const { defineComponent, ref, watch, Ref } = require( 'vue' );
const { storeToRefs } = require( 'pinia' );
const { CdxButton, CdxMessage } = require( '../../../codex.js' );
const useChartStore = require( '../stores/chart.js' );
const api = new mw.Api();

module.exports = exports = defineComponent( {
	name: 'ChartPreview',
	components: { CdxButton, CdxMessage },
	setup() {
		const store = useChartStore();
		const { initialLoad, source, sourceStatus } = storeToRefs( store );

		/**
		 * Preview of the source content.
		 *
		 * @type {Ref<string>}
		 */
		const sourcePreview = ref( '' );

		/**
		 * Fetches the content of the page specified in the source field
		 * and sets it as the source preview.
		 */
		async function doPreviewSource() {
			let response;
			try {
				response = await store.pushPromise(
					api.get( {
						action: 'query',
						format: 'json',
						prop: 'revisions',
						rvprop: 'content',
						titles: source.value,
						formatversion: 2
					} )
				);
			} catch ( e ) {
				sourcePreview.value = null;
				return;
			} finally {
				initialLoad.value = false;
			}
			const page = response.query.pages[ 0 ];
			if ( page.missing ) {
				sourceStatus.value = mw.msg( 'chart-error-data-source-page-not-found' );
				sourcePreview.value = '';
				return;
			} else if ( response.error ) {
				sourcePreview.value = null;
				return;
			} else {
				sourceStatus.value = null;
			}
			sourcePreview.value = JSON.stringify( JSON.parse(
				page.revisions[ 0 ].content
			), null, '    ' );
		}

		/**
		 * Watch for changes to the source field and update the preview accordingly.
		 * If the source field is empty, clear the preview.
		 */
		watch( source, ( newValue ) => {
			if ( newValue ) {
				doPreviewSource();
			} else {
				sourcePreview.value = '';
				initialLoad.value = false;
			}
		}, { immediate: true } );

		return {
			sourcePreview,
			doPreviewSource
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-chart-wizard__preview {
	position: relative;

	&--error {
		left: @size-half;
		position: absolute;
		text-align: center;
		text-wrap: nowrap;
		top: @size-half;
		transform: translate( -50%, -50% );

		.cdx-message {
			&__icon--vue,
			&__content {
				font-size: @font-size-large;
				margin-bottom: @spacing-100;
			}
		}
	}
}
</style>
