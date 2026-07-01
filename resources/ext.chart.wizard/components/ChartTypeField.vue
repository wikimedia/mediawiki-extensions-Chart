<template>
	<cdx-field
		class="ext-chart-wizard__type"
	>
		<cdx-select
			v-model:selected="type"
			:menu-items="menuItems"
			:default-label="$i18n( 'chart-wizard-form-type-placeholder' ).text()"
		>
		</cdx-select>
		<template #label>
			{{ $i18n( 'chart-wizard-form-type-label' ).text() }}
		</template>
	</cdx-field>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { storeToRefs } = require( 'pinia' );
const { CdxField, CdxSelect } = require( '../../../codex.js' );
const {
	cdxIconChart,
	cdxIconChartBar,
	cdxIconChartLine,
	cdxIconChartPie
} = require( '../icons.json' );
const useChartStore = require( '../stores/chart.js' );

const menuItems = [
	{
		value: 'line',
		label: mw.msg( 'chart-wizard-form-type-line' ),
		description: mw.msg( 'chart-wizard-form-type-line-description' ),
		icon: cdxIconChartLine
	},
	{
		value: 'bar',
		label: mw.msg( 'chart-wizard-form-type-bar' ),
		description: mw.msg( 'chart-wizard-form-type-bar-description' ),
		icon: cdxIconChartBar
	},
	{
		value: 'area',
		label: mw.msg( 'chart-wizard-form-type-area' ),
		description: mw.msg( 'chart-wizard-form-type-area-description' ),
		icon: cdxIconChart
	},
	{
		value: 'pie',
		label: mw.msg( 'chart-wizard-form-type-pie' ),
		description: mw.msg( 'chart-wizard-form-type-pie-description' ),
		icon: cdxIconChartPie
	}
];

module.exports = exports = defineComponent( {
	name: 'ChartType',
	components: { CdxField, CdxSelect },
	setup() {
		const { type } = storeToRefs( useChartStore() );

		return {
			type,
			menuItems
		};
	}
} );
</script>
