<template>
	<cdx-field
		:is-fieldset="true"
		:disabled="formDisabled"
		class="ext-chart-wizard__form"
	>
		<source-field></source-field>
		<transform-notice></transform-notice>
		<chart-configure></chart-configure>
		<license-field></license-field>
		<!-- eslint-disable-next-line vue/no-v-html -->
		<div
			class="ext-chart-wizard__copywarn"
			v-html="copyrightWarning"
		></div>
		<input
			type="hidden"
			name="chartDefinition"
			:value="JSON.stringify( chartDefinition )"
		>
		<input
			id="wpEditToken"
			type="hidden"
			name="wpEditToken"
			:value="editToken"
		>
		<input
			type="hidden"
			name="baseRevId"
			:value="baseRevId"
		>
		<div class="mw-htmlform-submit-buttons">
			<cdx-button
				action="progressive"
				type="submit"
				weight="primary"
				@click="onFormSubmission"
			>
				{{ submitText }}
			</cdx-button>
		</div>
	</cdx-field>
</template>

<script>
const { computed, defineComponent, nextTick } = require( 'vue' );
const { storeToRefs } = require( 'pinia' );
const { CdxButton, CdxField } = require( '../../../codex.js' );
const SourceField = require( './SourceField.vue' );
const TransformNotice = require( './TransformNotice.vue' );
const ChartConfigure = require( './ChartConfigure.vue' );
const LicenseField = require( './LicenseField.vue' );
const useChartStore = require( '../stores/chart.js' );

module.exports = exports = defineComponent( {
	name: 'ChartForm',
	components: {
		CdxButton,
		CdxField,
		ChartConfigure,
		SourceField,
		TransformNotice,
		LicenseField
	},
	props: {
		chartIsNew: { type: Boolean, required: true },
		baseRevId: { type: Number, default: 0 }
	},
	setup( { chartIsNew } ) {
		const { formDisabled, chartDefinition, license } = storeToRefs( useChartStore() );
		const submitText = computed( () => mw.msg(
			chartIsNew ?
				'chart-wizard-publish' :
				'publishchanges'
		) );
		const editToken = mw.config.get( 'chartEditToken' );
		const copyrightWarnings = mw.config.get( 'chartCopyrightWarnings' );
		const copyrightWarning = computed( () => copyrightWarnings[ license.value ] || '' );

		/**
		 * Handle form submission. If the form is invalid, scroll to the
		 * first error message to ensure the user sees it.
		 *
		 * If the form is valid, submit the form to the server.
		 *
		 * @param {Event} event
		 */
		async function onFormSubmission( event ) {
			if ( !event.target.form.checkValidity() ) {
				await nextTick();
				// Scrolling to `cdx-message--error` is merely future-proofing to
				// ensure the user sees the error message, wherever it may be.
				const firstError = document.querySelector( '.cdx-message--error' );
				if ( firstError ) {
					// Guard against there not being any parent fieldset.
					const firstErrorFieldset = firstError.closest( '.cdx-field' );
					( firstErrorFieldset || firstError ).scrollIntoView( { behavior: 'smooth' } );
				}
			}
		}

		return {
			formDisabled,
			submitText,
			chartDefinition,
			copyrightWarning,
			editToken,
			onFormSubmission
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

	.cdx-select-vue {
		width: 100%;
	}

	// Codex bug; See T430881.
	.cdx-field__help-text:empty {
		margin: 0;
	}

	.ext-chart-wizard__copywarn {
		margin: @spacing-75 0;
	}
}
</style>
