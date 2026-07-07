<template>
	<cdx-field
		class="ext-chart-wizard__configure"
		:is-fieldset="true"
	>
		<chart-type-field></chart-type-field>
		<categories-field></categories-field>
		<translatable-fields></translatable-fields>
		<template #label>
			{{ $i18n( 'chart-wizard-form-configure-label' ).text() }}
		</template>
		<template #description>
			{{ $i18n( 'chart-wizard-form-configure-description' ).text() }}
		</template>
	</cdx-field>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxField } = require( '../../../codex.js' );
const ChartTypeField = require( './ChartTypeField.vue' );
const CategoriesField = require( './CategoriesField.vue' );
const TranslatableFields = require( './TranslatableFields.vue' );

module.exports = exports = defineComponent( {
	name: 'ChartConfigure',
	components: { CdxField, ChartTypeField, CategoriesField, TranslatableFields }
} );
</script>
