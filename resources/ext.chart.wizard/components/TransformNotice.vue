<template>
	<cdx-message
		v-if="transform.module && transform.function"
		class="ext-chart-wizard__transform-notice"
	>
		<div v-i18n-html="transformDescription"></div>
		<div
			v-if="transformArgs.length"
			class="ext-chart-wizard__transform-args"
		>
			<span>{{ $i18n( 'chart-wizard-form-transform-arguments' ) }}</span>
			<cdx-info-chip
				v-for="arg in transformArgs"
				:key="arg"
				status="subtle"
			>
				{{ arg }}
			</cdx-info-chip>
		</div>
	</cdx-message>
</template>

<script>
const { computed, defineComponent } = require( 'vue' );
const { storeToRefs } = require( 'pinia' );
const { CdxInfoChip, CdxMessage } = require( '../../../codex.js' );
const useChartStore = require( '../stores/chart.js' );

module.exports = exports = defineComponent( {
	name: 'TransformNotice',
	components: {
		CdxInfoChip,
		CdxMessage
	},
	setup() {
		const { transform } = storeToRefs( useChartStore() );
		const moduleTitle = computed( () => `Module:${ transform.value.module }` );
		const transformDescription = computed( () => {
			const functionName = document.createElement( 'code' );
			functionName.textContent = transform.value.function;
			return mw.message(
				'chart-wizard-form-transform-description',
				moduleTitle.value,
				functionName
			);
		} );
		const transformArgs = computed( () => Object.entries( transform.value.args || {} )
			.map( ( [ key, value ] ) => {
				const displayValue = typeof value === 'string' ? value : JSON.stringify( value );
				return `${ key }=${ displayValue }`;
			} )
		);

		return {
			transform,
			transformDescription,
			transformArgs
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-chart-wizard__transform-notice {
	margin: @spacing-100 0;
}

.ext-chart-wizard__transform-args {
	align-items: center;
	display: flex;
	flex-wrap: wrap;
	gap: @spacing-25;
	margin-top: @spacing-50;
}
</style>
