<template>
	<div class="ext-chart-wizard__preview">
		<div v-if="sourcePreview.length">
			<pre class="ext-chart-wizard__preview--source">{{ sourcePreview }}</pre>
		</div>
		<div v-else class="ext-chart-wizard__preview-placeholder">
			<div class="ext-chart-wizard__preview-placeholder-image"></div>
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

	&-image {
		background-image: url( ../images/PreviewArea_EmptyState.png );
		background-repeat: no-repeat;
		background-size: auto 96px;
		background-position: center;
		height: 96px;
	}
}

</style>
