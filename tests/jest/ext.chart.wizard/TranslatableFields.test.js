'use strict';

const { mount } = require( '@vue/test-utils' );
const { createTestingPinia } = require( '@pinia/testing' );
const TranslatableFields = require( '../../../resources/ext.chart.wizard/components/TranslatableFields.vue' );
const { mockMwConfigGet } = require( './ChartWizard.setup.js' );
const useChartStore = require( '../../../resources/ext.chart.wizard/stores/chart.js' );

describe( 'TranslatableFields', () => {

	let wrapper, store;

	beforeEach( () => {
		mockMwConfigGet( {
			chartLanguages: {
				ar: 'العربية',
				en: 'English',
				de: 'Deutsch'
			},
			wgUserLanguage: 'ar'
		} );
		wrapper = mount( TranslatableFields, {
			global: { plugins: [ createTestingPinia( {
				stubActions: false,
				initialState: {
					chart: {
						title: {
							en: 'Example chart',
							de: 'Beispiel-Diagramm'
						},
						subtitle: { en: 'Example subtitle' },
						xAxis: {
							title: {
								en: 'Example x-axis title',
								de: 'Beispiel x-Achsentitel'
							},
							format: 'auto'
						},
						yAxis: {
							title: {
								en: 'Example y-axis title',
								de: 'Beispiel y-Achsentitel'
							},
							format: 'none'
						}
					}
				}
			} ) ] }
		} );
		store = useChartStore();
	} );

	it( 'should group together translated and untranslated languages', () => {
		// Initial state.
		expect( wrapper.vm.languageItems ).toStrictEqual( [
			{
				items: [
					{ label: 'en – English', value: 'en' },
					{ label: 'de – Deutsch', value: 'de' }
				],
				label: 'chart-wizard-form-language-translated'
			}, {
				items: [
					{ label: 'ar – العربية', value: 'ar' }
				],
				label: 'chart-wizard-form-language-untranslated'
			}
		] );
		// Add Arabic translation.
		store.title.ar = 'مثال على الرسم البياني';
		// Expect Arabic to be moved to the translated group.
		expect( wrapper.vm.languageItems ).toStrictEqual( [
			{
				items: [
					{ label: 'en – English', value: 'en' },
					{ label: 'de – Deutsch', value: 'de' },
					{ label: 'ar – العربية', value: 'ar' }
				],
				label: 'chart-wizard-form-language-translated'
			}
		] );
	} );

	it( 'should set the language field to the user\'s language', () => {
		expect( store.currentLanguage ).toBe( 'ar' );
		expect( wrapper.vm.currentLanguage ).toBe( 'ar' );
	} );

	it( 'should set the format option on the axis unless it matches the default', () => {
		expect( store.xAxis.format ).toBe( 'auto' );
		expect( store.yAxis.format ).toBe( 'none' );
		expect( wrapper.vm.xAxisFormatModel ).toBe( true );
		expect( wrapper.vm.yAxisFormatModel ).toBe( false );
		wrapper.vm.xAxisFormatModel = false;
		wrapper.vm.yAxisFormatModel = true;
		expect( store.xAxis.format ).toBeUndefined();
		expect( store.yAxis.format ).toBe( 'auto' );
	} );

	it( 'should not include axis titles in the definition if they are empty', () => {
		store.currentLanguage = 'en';

		expect( wrapper.vm.xAxisTitleModel ).toBe( 'Example x-axis title' );

		wrapper.vm.xAxisTitleModel = '';

		expect( store.chartDefinition.xAxis ).toStrictEqual( {
			title: {
				de: 'Beispiel x-Achsentitel'
			},
			format: 'auto'
		} );

		store.currentLanguage = 'de';
		wrapper.vm.xAxisTitleModel = '';

		expect( store.chartDefinition.xAxis ).toStrictEqual( {
			format: 'auto'
		} );
	} );

	it( 'should not include fields in the definition if they are empty (titles)', () => {
		// Change language to English.
		store.currentLanguage = 'en';
		// Sanity checks.
		expect( store.chartDefinition.title ).toBeDefined();
		expect( wrapper.vm.titleModel ).toBe( 'Example chart' );
		// Blank the title.
		wrapper.vm.titleModel = '';
		expect( wrapper.find( '.ext-chart-wizard__subtitle' ).exists() ).toBeTruthy();
		// Should now only include German in the store.
		expect( store.chartDefinition.title ).toStrictEqual( {
			de: 'Beispiel-Diagramm'
		} );
		// Remove German, too.
		store.currentLanguage = 'de';
		wrapper.vm.titleModel = '';
		// And assert emptiness.
		expect( store.chartDefinition.title ).toBeUndefined();
	} );

	it( 'should not include fields in the definition if they are empty (subtitles)', () => {
		store.currentLanguage = 'en';

		expect( wrapper.vm.titleModel ).toBe( 'Example chart' );
		expect( wrapper.vm.subtitleModel ).toBe( 'Example subtitle' );

		wrapper.vm.subtitleModel = '';

		expect( store.chartDefinition.subtitle ).toBeUndefined();
		expect( store.chartDefinition.title.en ).toBe( 'Example chart' );
		expect( wrapper.find( '.ext-chart-wizard__subtitle' ).exists() ).toBeTruthy();
	} );
} );
