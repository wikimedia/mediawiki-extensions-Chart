'use strict';

const { mount, shallowMount, flushPromises } = require( '@vue/test-utils' );
const { createTestingPinia } = require( '@pinia/testing' );
const ChartWizard = require( '../../../resources/ext.chart.wizard/components/ChartWizard.vue' );
const { fixtures, mockMwApiGet } = require( './ChartWizard.setup.js' );
const useChartStore = require( '../../../resources/ext.chart.wizard/stores/chart.js' );

describe( 'ChartWizard', () => {

	beforeEach( () => {
		mw.Title.newFromText = jest.fn().mockImplementation(
			( title ) => ( { getPrefixedText: () => title } )
		);
	} );

	afterEach( () => jest.clearAllMocks() );

	it( 'should populate the store with the given chartDefinition JSON', () => {
		shallowMount( ChartWizard, {
			global: { plugins: [ createTestingPinia( { stubActions: false } ) ] },
			props: {
				chartDefinition: fixtures[ 'Data:Example.Line.chart' ],
				chartIsNew: false
			}
		} );
		mockMwApiGet();
		const store = useChartStore();
		expect( store.chartDefinition ).toStrictEqual( fixtures[ 'Data:Example.Line.chart' ] );
	} );

	it( 'should use schema format values for default axes', () => {
		createTestingPinia( { stubActions: false } );
		const store = useChartStore();

		expect( store.chartDefinition.xAxis.format ).toBe( 'none' );
		expect( store.chartDefinition.yAxis.format ).toBe( 'none' );
	} );

	it( 'should bubble constraint validations to the <form> element', async () => {
		const form = document.createElement( 'form' );
		form.id = 'ext-chart-wizard';
		mount( ChartWizard, {
			global: { plugins: [ createTestingPinia( { stubActions: false } ) ] },
			props: {
				chartDefinition: fixtures[ 'Data:Example.Line.chart' ],
				chartIsNew: false
			},
			attachTo: form
		} );
		document.body.appendChild( form );
		mockMwApiGet();
		const store = useChartStore();
		expect( form.checkValidity() ).toBeTruthy();
		store.source = 'Data:Nonexistent data.tab';
		await flushPromises();
		expect( form.checkValidity() ).toBeFalsy();
		expect( form.reportValidity() ).toBeFalsy();
		expect( store.sourceStatus ).toBe( 'chart-error-data-source-page-not-found' );
		document.body.removeChild( form );
	} );
} );
