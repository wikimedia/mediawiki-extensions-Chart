<template>
	<cdx-field
		v-if="allowedLicenses.length > 0"
		class="ext-chart-wizard__confirm"
		:is-fieldset="true"
	>
		<cdx-field class="ext-chart-wizard__license">
			<cdx-select
				v-model:selected="license"
				:menu-items="allowedLicenses"
			></cdx-select>
			<template #label>
				{{ $i18n( 'chart-wizard-form-license-label' ).text() }}
			</template>
		</cdx-field>
		<template #label>
			{{ $i18n( 'chart-wizard-form-publish-label' ).text() }}
		</template>
		<template #description>
			{{ $i18n( 'chart-wizard-form-publish-description' ).text() }}
		</template>
	</cdx-field>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxField, CdxSelect } = require( '../../../codex.js' );
const { storeToRefs } = require( 'pinia' );
const useChartStore = require( '../stores/chart.js' );

module.exports = exports = defineComponent( {
	name: 'LicenseField',
	components: { CdxField, CdxSelect },
	setup() {
		const store = useChartStore();
		const { license } = storeToRefs( store );
		const allowedLicenses = mw.config.get( 'chartAllowedLicenses', [] )
			.map( ( val ) => ( { value: val, label: val } ) );

		return {
			license,
			allowedLicenses
		};
	}
} );
</script>
