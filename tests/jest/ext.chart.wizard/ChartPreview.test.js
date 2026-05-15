'use strict';

const { mount, flushPromises } = require( '@vue/test-utils' );

const { createTestingPinia } = require( '@pinia/testing' );
const ChartPreview = require( '../../../resources/ext.chart.wizard/components/ChartPreview.vue' );
const { mockMwApiGet } = require( './ChartWizard.setup.js' );
const useChartStore = require( '../../../resources/ext.chart.wizard/stores/chart.js' );

describe( 'ChartPreview', () => {

	it( 'should show a preview of the source dataset', async () => {
		const wrapper = mount( ChartPreview, {
			global: { plugins: [ createTestingPinia( { stubActions: false } ) ] }
		} );
		expect( wrapper.find( '.ext-chart-wizard__preview-placeholder' ).exists() ).toBeTruthy();
		mockMwApiGet();
		const store = useChartStore();
		store.source = 'Data:Chart Example Data.tab';
		await flushPromises();
		expect( wrapper.find( '.ext-chart-wizard__preview-placeholder' ).exists() ).toBeFalsy();
		const json = JSON.parse( wrapper.find( '.ext-chart-wizard__preview--source' ).text() );
		expect( json.description.en ).toBe( 'Description (en)' );
		expect( json.schema.fields.length ).toBe( 2 );
	} );

	it( 'should show an error if the source dataset is missing', async () => {
		const wrapper = mount( ChartPreview, {
			global: { plugins: [ createTestingPinia( { stubActions: false } ) ] }
		} );
		expect( wrapper.find( '.ext-chart-wizard__preview-placeholder' ).exists() ).toBeTruthy();
		mockMwApiGet();
		const store = useChartStore();
		store.source = 'Data:Nonexistent data.tab';
		await flushPromises();
		expect( wrapper.find( '.ext-chart-wizard__preview-placeholder' ).exists() ).toBeTruthy();
		expect( store.sourceStatus ).toBe( 'chart-error-data-source-page-not-found' );
	} );

	it( 'should show an error if the preview failed to load', async () => {
		const wrapper = mount( ChartPreview, {
			global: { plugins: [ createTestingPinia( { stubActions: false } ) ] }
		} );
		expect( wrapper.find( '.ext-chart-wizard__preview-placeholder' ).exists() ).toBeTruthy();
		mockMwApiGet();
		const store = useChartStore();
		store.source = 'Data:Erroneous data.tab';
		await flushPromises();
		expect( wrapper.find( '.ext-chart-wizard__preview-placeholder' ).exists() ).toBeFalsy();
		expect( wrapper.find( '.ext-chart-wizard__preview--error' ).exists() ).toBeTruthy();
	} );
} );
