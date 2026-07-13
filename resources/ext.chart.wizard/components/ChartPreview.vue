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
		<rendered-chart-preview
			v-if="chartDefinition.source && selectedPreview === 'chart'"
		></rendered-chart-preview>
		<source-data-preview
			v-else-if="chartDefinition.source"
		></source-data-preview>
		<div
			v-else
			class="ext-chart-wizard__preview-placeholder"
		>
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
const { defineComponent, ref, Ref } = require( 'vue' );
const { storeToRefs } = require( 'pinia' );
const { CdxButton } = require( '../../../codex.js' );
const RenderedChartPreview = require( './RenderedChartPreview.vue' );
const SourceDataPreview = require( './SourceDataPreview.vue' );
const useChartStore = require( '../stores/chart.js' );

module.exports = exports = defineComponent( {
	name: 'ChartPreview',
	components: {
		CdxButton,
		RenderedChartPreview,
		SourceDataPreview
	},
	setup() {
		const { chartDefinition, initialLoad } = storeToRefs( useChartStore() );

		/**
		 * The selected preview mode.
		 *
		 * @type {Ref<'chart'|'data'>}
		 */
		const selectedPreview = ref( 'chart' );

		function selectPreview( preview ) {
			if ( preview === selectedPreview.value ) {
				return;
			}
			selectedPreview.value = preview;
		}

		if ( !chartDefinition.value.source ) {
			initialLoad.value = false;
		}

		return {
			chartDefinition,
			selectPreview,
			selectedPreview
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
