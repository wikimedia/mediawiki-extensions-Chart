<template>
	<div class="ext-chart-wizard">
		<chart-form></chart-form>
		<chart-preview></chart-preview>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const ChartForm = require( './ChartForm.vue' );
const ChartPreview = require( './ChartPreview.vue' );

module.exports = exports = defineComponent( {
	name: 'ChartWizard',
	components: {
		ChartForm,
		ChartPreview
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-chart-wizard {
	display: flex;
	flex-wrap: wrap;
	column-gap: @spacing-200;
	margin-top: @spacing-100;

	& > * {
		flex-basis: 0;
		flex-grow: 1;
	}
}
</style>
