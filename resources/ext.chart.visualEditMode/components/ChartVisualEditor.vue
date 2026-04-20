<template>
	<div class="ext-chart-visualmode">
		<chart-form></chart-form>
		<chart-preview-area></chart-preview-area>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const ChartForm = require( './ChartForm.vue' );
const ChartPreviewArea = require( './ChartPreviewArea.vue' );

module.exports = exports = defineComponent( {
	name: 'ChartVisualEditor',
	components: {
		ChartForm,
		ChartPreviewArea
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-chart-visualmode {
	margin-top: @spacing-100;
	display: flex;
	flex-wrap: wrap;
	column-gap: @spacing-12;

	& > * {
		flex-basis: 0;
		flex-grow: 1;
	}
}
</style>
