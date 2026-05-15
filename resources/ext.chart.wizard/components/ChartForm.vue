<template>
	<cdx-field
		:is-fieldset="true"
		:disabled="formDisabled"
		class="ext-chart-wizard__form"
	>
		<source-field></source-field>
		<div class="mw-htmlform-submit-buttons">
			<cdx-button
				action="progressive"
				weight="primary"
			>
				{{ submitText }}
			</cdx-button>
		</div>
	</cdx-field>
</template>

<script>
const { computed, defineComponent } = require( 'vue' );
const { storeToRefs } = require( 'pinia' );
const { CdxButton, CdxField } = require( '../../../codex.js' );
const SourceField = require( './SourceField.vue' );
const useChartStore = require( '../stores/chart.js' );

module.exports = exports = defineComponent( {
	name: 'ChartForm',
	components: {
		CdxButton,
		CdxField,
		SourceField
	},
	props: {
		chartIsNew: { type: Boolean, required: true }
	},
	setup( { chartIsNew } ) {
		const { formDisabled } = storeToRefs( useChartStore() );
		const submitText = computed( () => mw.msg(
			chartIsNew ?
				'chart-wizard-publish' :
				'publishchanges'
		) );

		return {
			formDisabled,
			submitText
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.cdx-field.ext-chart-wizard__form {
	margin-top: @spacing-100;

	> legend {
		display: none;
	}
}
</style>
