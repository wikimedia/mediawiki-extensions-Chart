<template>
	<div class="ext-chart-wizard__form">
		<source-field></source-field>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const SourceField = require( './SourceField.vue' );

module.exports = exports = defineComponent( {
	name: 'ChartForm',
	components: {
		SourceField
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-chart-wizard__form {
	margin-top: @spacing-100;

	.cdx-label {
		font-size: @font-size-base;
		margin-bottom: @spacing-50;

		&__label {
			font-size: @font-size-large;
		}
	}
}
</style>
