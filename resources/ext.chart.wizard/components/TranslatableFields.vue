<template>
	<cdx-field class="ext-chart-wizard__language">
		<cdx-select
			v-model:selected="currentLanguage"
			:menu-items="languageItems"
			@update:selected="onSelectLangauge"
		></cdx-select>
		<template #label>
			{{ $i18n( 'chart-wizard-form-language-label' ).text() }}
		</template>
		<template #description>
			{{ $i18n( 'chart-wizard-form-language-description' ).text() }}
		</template>
	</cdx-field>
	<cdx-field
		class="ext-chart-wizard__title"
		:optional="true"
	>
		<cdx-text-input
			v-model="titleModel"
			:clearable="true"
		></cdx-text-input>
		<template #label>
			{{ $i18n( 'chart-wizard-form-title-label' ).text() }}
		</template>
	</cdx-field>
	<cdx-field
		class="ext-chart-wizard__subtitle"
		:optional="true"
	>
		<cdx-text-input
			v-model="subtitleModel"
			:clearable="true"
		></cdx-text-input>
		<template #label>
			{{ $i18n( 'chart-wizard-form-subtitle-label' ).text() }}
		</template>
		<template #description>
			{{ $i18n( 'chart-wizard-form-subtitle-description' ).text() }}
		</template>
	</cdx-field>
	<cdx-field
		v-if="type !== 'pie'"
		class="ext-chart-wizard__x-axis"
		:optional="true"
	>
		<cdx-text-input
			v-model="xAxisTitleModel"
			:clearable="true"
		></cdx-text-input>
		<template #label>
			{{ $i18n( 'chart-wizard-form-x-axis-label' ) }}
		</template>
		<cdx-checkbox v-model="xAxisFormatModel">
			{{ $i18n( 'chart-wizard-form-axis-format-label' ) }}
		</cdx-checkbox>
	</cdx-field>
	<cdx-field
		v-if="type !== 'pie'"
		class="ext-chart-wizard__y-axis"
		:optional="true"
	>
		<cdx-text-input
			v-model="yAxisTitleModel"
			:clearable="true"
		></cdx-text-input>
		<template #label>
			{{ $i18n( 'chart-wizard-form-y-axis-label' ) }}
		</template>
		<cdx-checkbox v-model="yAxisFormatModel">
			{{ $i18n( 'chart-wizard-form-axis-format-label' ) }}
		</cdx-checkbox>
	</cdx-field>
</template>

<script>
const { computed, defineComponent, watch, ComputedRef, WritableComputedRef } = require( 'vue' );
const { storeToRefs } = require( 'pinia' );
const { CdxCheckbox, CdxField, CdxSelect, CdxTextInput } = require( '../../../codex.js' );
const useChartStore = require( '../stores/chart.js' );

module.exports = exports = defineComponent( {
	name: 'TranslatableFields',
	components: { CdxCheckbox, CdxField, CdxSelect, CdxTextInput },
	setup() {
		const {
			currentLanguage,
			type,
			title,
			subtitle,
			xAxis,
			yAxis
		} = storeToRefs( useChartStore() );
		const availableLanguages = mw.config.get( 'chartLanguages' );

		/**
		 * Computed model for the title field.
		 *
		 * @type {WritableComputedRef<string>}
		 */
		const titleModel = computed( {
			get() {
				return title.value ? title.value[ currentLanguage.value ] : '';
			},
			set( value ) {
				if ( value ) {
					title.value[ currentLanguage.value ] = value;
				} else {
					delete title.value[ currentLanguage.value ];
				}
			}
		} );

		/**
		 * Computed model for the subtitle field.
		 *
		 * @type {WritableComputedRef<string>}
		 */
		const subtitleModel = computed( {
			get() {
				return subtitle.value ? subtitle.value[ currentLanguage.value ] : '';
			},
			set( value ) {
				if ( value ) {
					subtitle.value[ currentLanguage.value ] = value;
				} else {
					delete subtitle.value[ currentLanguage.value ];
				}
			}
		} );

		/**
		 * Computed model for the x-axis title field.
		 *
		 * @type {WritableComputedRef<string>}
		 */
		const xAxisTitleModel = computed( {
			get: () => xAxis.value.title ? xAxis.value.title[ currentLanguage.value ] : '',
			set( value ) {
				if ( value ) {
					if ( !xAxis.value.title ) {
						xAxis.value.title = {};
					}
					xAxis.value.title[ currentLanguage.value ] = value;
				} else if ( xAxis.value.title ) {
					delete xAxis.value.title[ currentLanguage.value ];
					if ( !Object.keys( xAxis.value.title ).length ) {
						delete xAxis.value.title;
					}
				}
			}
		} );

		/**
		 * Computed model for the x-axis format field.
		 *
		 * @type {WritableComputedRef<boolean>}
		 */
		const xAxisFormatModel = computed( {
			get: () => xAxis.value.format === 'auto',
			set( value ) {
				if ( value ) {
					xAxis.value.format = 'auto';
				} else {
					delete xAxis.value.format;
				}
			}
		} );

		/**
		 * Computed model for the y-axis title field.
		 *
		 * @type {WritableComputedRef<string>}
		 */
		const yAxisTitleModel = computed( {
			get: () => yAxis.value.title ? yAxis.value.title[ currentLanguage.value ] : '',
			set( value ) {
				if ( value ) {
					if ( !yAxis.value.title ) {
						yAxis.value.title = {};
					}
					yAxis.value.title[ currentLanguage.value ] = value;
				} else {
					if ( yAxis.value.title ) {
						delete yAxis.value.title[ currentLanguage.value ];
						if ( !Object.keys( yAxis.value.title ).length ) {
							delete yAxis.value.title;
						}
					}
				}
			}
		} );

		/**
		 * Computed model for the y-axis format field.
		 *
		 * @type {WritableComputedRef<boolean>}
		 */
		const yAxisFormatModel = computed( {
			get: () => yAxis.value.format === 'auto',
			set( value ) {
				if ( value ) {
					yAxis.value.format = 'auto';
				} else {
					delete yAxis.value.format;
				}
			}
		} );

		/**
		 * The list of languages available for translation.
		 * The languages are organized into two groups: "Translated" and "Untranslated".
		 *
		 * @type {ComputedRef<MenuGroupData[]>}
		 */
		const languageItems = computed( () => {
			const translatedLangs = [
				...new Set( [
					...Object.keys( title.value ),
					...Object.keys( subtitle.value ),
					...Object.keys( xAxis.value.title || {} ),
					...Object.keys( yAxis.value.title || {} )
				] )
			];
			const untranslatedLangs = Object.keys( availableLanguages ).filter(
				( lang ) => !translatedLangs.includes( lang )
			);

			const menuItems = [];
			if ( translatedLangs.length ) {
				menuItems.push( {
					label: mw.msg( 'chart-wizard-form-language-translated' ),
					items: translatedLangs.map( ( lang ) => ( {
						label: `${ lang } – ${ availableLanguages[ lang ] }`,
						value: lang
					} ) )
				} );
			}
			if ( untranslatedLangs.length ) {
				menuItems.push( {
					label: mw.msg( 'chart-wizard-form-language-untranslated' ),
					items: untranslatedLangs.map( ( lang ) => ( {
						label: `${ lang } – ${ availableLanguages[ lang ] }`,
						value: lang
					} ) )
				} );
			}
			return menuItems;
		} );

		/**
		 * Handler for when the user selects a language from the dropdown.
		 *
		 * @param {string} language
		 */
		function onSelectLangauge( language ) {
			currentLanguage.value = language;
		}

		// Blank the axes fields if we're using a pie chart.
		// If we change back, try to restore any preexisting axes data.
		let cachedXAxis = {},
			cachedYAxis = {};
		watch( type, ( newType, oldType ) => {
			if ( newType === 'pie' && oldType !== 'pie' ) {
				cachedXAxis = xAxis.value;
				cachedYAxis = yAxis.value;
				xAxis.value = {};
				yAxis.value = {};
			} else if ( newType !== 'pie' && oldType === 'pie' ) {
				xAxis.value = cachedXAxis;
				yAxis.value = cachedYAxis;
				cachedXAxis = {};
				cachedYAxis = {};
			}
		} );

		return {
			currentLanguage,
			type,
			languageItems,
			titleModel,
			subtitleModel,
			xAxisTitleModel,
			xAxisFormatModel,
			yAxisTitleModel,
			yAxisFormatModel,
			onSelectLangauge
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.cdx-checkbox__wrapper {
	margin-top: @spacing-50;
}
</style>
