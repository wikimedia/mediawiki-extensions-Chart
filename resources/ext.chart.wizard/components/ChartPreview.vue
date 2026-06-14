<template>
	<div class="ext-chart-wizard__preview">
		<div v-if="sourcePreview.length">
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
const useChartStore = require( '../stores/chart.js' );
const api = new mw.Api();

module.exports = exports = defineComponent( {
	name: 'ChartPreview',
	setup() {
		const store = useChartStore();
		const { source } = storeToRefs( store );

		/**
		 * Preview of the source content.
		 *
		 * @type {Ref<string>}
		 */
		const sourcePreview = ref( '' );

		watch( source, ( newValue ) => {
			if ( newValue ) {
				previewSource();
			} else {
				sourcePreview.value = '';
			}
		} );

		/**
		 * Fetches the content of the page specified in the source field
		 * and sets it as the source preview.
		 */
		async function previewSource() {
			const response = await api.get( {
				action: 'query',
				format: 'json',
				prop: 'revisions',
				rvprop: 'content',
				titles: source.value,
				formatversion: 2
			} );
			sourcePreview.value = JSON.stringify( JSON.parse(
				response.query.pages[ 0 ].revisions[ 0 ].content
			), null, '    ' );
		}

		return {
			sourcePreview
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-chart-wizard__preview {
	border: @border-subtle;

	&--source {
		background: transparent;
		border: none;
		margin: 0;
		padding: @spacing-200;
	}
}

.ext-chart-wizard__preview-placeholder {
	text-align: center;
	padding: @spacing-200 0;

	&-header {
		font-size: @font-size-large;
		font-weight: @font-weight-bold;
		padding-top: @spacing-200;
		padding-bottom: @spacing-25;
	}

	&-image-wrapper {
		background-color: @background-color-disabled;
		border-radius: @border-radius-circle;
		display: inline-block;
		height: 96px;
		position: relative;
		width: 96px;
	}

	svg {
		height: @size-200;
		left: @size-half;
		position: absolute;
		top: @size-half;
		transform: translate( -50%, -50% );
		width: @size-200;
	}
}

</style>
