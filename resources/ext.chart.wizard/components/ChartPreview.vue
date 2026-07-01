<template>
	<div class="ext-chart-wizard__preview">
		<div
			v-if="chartDefinition.source"
			class="ext-chart-wizard__preview-switch"
		>
			<cdx-button
				weight="quiet"
				:action="selectedPreview === 'chart' ? 'progressive' : 'default'"
				:aria-pressed="selectedPreview === 'chart'"
				@click.prevent="selectPreview( 'chart' )"
			>
				{{ $i18n( 'chart-wizard-preview-chart-view' ).text() }}
			</cdx-button>
			<cdx-button
				weight="quiet"
				:action="selectedPreview === 'data' ? 'progressive' : 'default'"
				:aria-pressed="selectedPreview === 'data'"
				@click.prevent="selectPreview( 'data' )"
			>
				{{ $i18n( 'chart-wizard-preview-data-view' ).text() }}
			</cdx-button>
		</div>
		<div
			v-if="activePreviewError"
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
				@click.prevent="renderSelectedPreview"
			>
				{{ $i18n( 'chart-wizard-preview-error-retry' ) }}
			</cdx-button>
		</div>
		<div
			v-else-if="chartDefinition.source && selectedPreview === 'chart'"
			ref="previewContainer"
			class="ext-chart-wizard__preview--chart"
		>
		</div>
		<div
			v-else-if="chartDefinition.source && selectedPreview === 'data'"
			class="ext-chart-wizard__preview--data"
		>
			<pre
				v-if="sourcePreview.length"
				class="ext-chart-wizard__preview--source"
			>{{ sourcePreview }}</pre>
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
const { computed, defineComponent, nextTick, ref, useTemplateRef, watch, ComputedRef, Ref } = require( 'vue' );
const { storeToRefs } = require( 'pinia' );
const { CdxButton, CdxMessage } = require( '../../../codex.js' );
const useChartStore = require( '../stores/chart.js' );
const api = new mw.Api();

module.exports = exports = defineComponent( {
	name: 'ChartPreview',
	components: { CdxButton, CdxMessage },
	setup() {
		const store = useChartStore();
		const { chartDefinition, initialLoad, currentLanguage } = storeToRefs( store );

		/**
		 * The selected preview mode.
		 *
		 * @type {Ref<'chart'|'data'>}
		 */
		const selectedPreview = ref( 'chart' );

		/**
		 * Whether the rendered chart preview request failed.
		 *
		 * @type {Ref<boolean>}
		 */
		const chartPreviewError = ref( false );

		/**
		 * Whether the source data preview request failed.
		 *
		 * @type {Ref<boolean>}
		 */
		const sourcePreviewError = ref( false );

		/**
		 * Preview of the source content.
		 *
		 * @type {Ref<string>}
		 */
		const sourcePreview = ref( '' );

		/**
		 * Container for rendered chart preview HTML.
		 *
		 * @type {Ref<HTMLElement|null>}
		 */
		const previewContainer = useTemplateRef( 'previewContainer' );

		function prepareChartPreviewContent( content ) {
			content.forEach( ( node ) => {
				if ( node.nodeType !== Node.ELEMENT_NODE ) {
					return;
				}
				const charts = node.matches( 'wiki-chart' ) ?
					[ node ] :
					Array.from( node.querySelectorAll( 'wiki-chart' ) );
				charts.forEach( ( chart ) => {
					const svg = chart.querySelector( 'svg' );
					const height = svg && svg.getAttribute( 'height' );
					if ( height ) {
						chart.style.minHeight = `${ height }px`;
					}
				} );
			} );
			return content;
		}

		async function injectPreview( response ) {
			const parse = response.parse;
			if ( parse.jsconfigvars ) {
				mw.config.set( parse.jsconfigvars );
			}
			const modules = ( parse.modules || [] ).concat( parse.modulestyles || [] );
			if ( modules.length ) {
				await mw.loader.using( modules );
			}
			chartPreviewError.value = false;
			const $previewContainer = window.jQuery( previewContainer.value );
			$previewContainer.empty().append(
				prepareChartPreviewContent( window.jQuery.parseHTML( parse.text ) )
			);
			mw.hook( 'wikipage.content' ).fire( $previewContainer );
		}

		async function renderChartPreview() {
			if ( !chartDefinition.value.source ) {
				chartPreviewError.value = false;
				initialLoad.value = false;
				return;
			}
			chartPreviewError.value = false;
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
				await injectPreview( response );
			} catch ( e ) {
				chartPreviewError.value = true;
			} finally {
				initialLoad.value = false;
			}
		}

		async function renderSourcePreview() {
			if ( !chartDefinition.value.source ) {
				sourcePreview.value = '';
				sourcePreviewError.value = false;
				initialLoad.value = false;
				return;
			}
			try {
				const response = await api.get( {
					action: 'query',
					format: 'json',
					prop: 'revisions',
					rvprop: 'content',
					titles: chartDefinition.value.source,
					formatversion: 2
				} );
				const page = response.query.pages[ 0 ];
				if ( page.missing || response.error ) {
					sourcePreview.value = '';
					sourcePreviewError.value = true;
					return;
				}
				sourcePreview.value = JSON.stringify( JSON.parse(
					page.revisions[ 0 ].content
				), null, '    ' );
				sourcePreviewError.value = false;
			} catch ( e ) {
				sourcePreview.value = '';
				sourcePreviewError.value = true;
			} finally {
				initialLoad.value = false;
			}
		}

		function renderSelectedPreview() {
			if ( selectedPreview.value === 'data' ) {
				return renderSourcePreview();
			}
			return renderChartPreview();
		}

		function selectPreview( preview ) {
			selectedPreview.value = preview;
			return renderSelectedPreview();
		}

		/**
		 * Whether the currently visible preview request failed.
		 *
		 * @type {ComputedRef<boolean>}
		 */
		const activePreviewError = computed( () => selectedPreview.value === 'data' ?
			sourcePreviewError.value :
			chartPreviewError.value
		);

		watch( chartDefinition, () => {
			if ( selectedPreview.value === 'chart' ) {
				renderChartPreview();
			}
		}, { deep: true, immediate: true } );
		watch( () => chartDefinition.value.source, () => {
			if ( selectedPreview.value === 'data' ) {
				renderSourcePreview();
			}
		} );
		watch( currentLanguage, () => {
			if ( selectedPreview.value === 'chart' ) {
				renderChartPreview();
			}
		} );

		return {
			activePreviewError,
			chartDefinition,
			previewContainer,
			renderSelectedPreview,
			selectPreview,
			selectedPreview,
			sourcePreview
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-chart-wizard__preview {
	position: relative;

	&-switch {
		margin-bottom: @spacing-100;
	}

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
