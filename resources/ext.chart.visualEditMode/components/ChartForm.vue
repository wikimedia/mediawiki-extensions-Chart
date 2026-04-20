<template>
	<cdx-field
		class="ext-chart-visualmode-form__fieldset"
	>
		<dataset-lookup-field></dataset-lookup-field>
	</cdx-field>
</template>

<script>
const { defineComponent } = require( 'vue' );
const DatasetLookupField = require( './DatasetLookupField.vue' );
const { CdxField } = require( '../../../codex.js' );

module.exports = exports = defineComponent( {
	name: 'ChartForm',
	components: {
		CdxField,
		DatasetLookupField
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-chart-visualmode-form__fieldset {
	margin-top: @spacing-100;
	margin-right: @spacing-100;
}
</style>
