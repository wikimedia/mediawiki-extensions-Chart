'use strict';

const { nextTick } = require( 'vue' );
const { shallowMount, flushPromises } = require( '@vue/test-utils' );
const { createTestingPinia } = require( '@pinia/testing' );
const { CdxAccordion } = require( '@wikimedia/codex' );
const ChartWizard = require( '../../../resources/ext.chart.wizard/components/ChartWizard.vue' );
const { fixtures, mockMwApiGet } = require( './ChartWizard.setup.js' );
const useChartStore = require( '../../../resources/ext.chart.wizard/stores/chart.js' );

describe( 'ChartWizard', () => {
	let matchMediaChangeListener = null;

	beforeEach( () => {
		const matchMediaMock = {
			matches: false,
			addEventListener: jest.fn( ( event, callback ) => {
				if ( event === 'change' ) {
					matchMediaChangeListener = callback;
				}
			} ),
			removeEventListener: jest.fn()
		};
		window.matchMedia = jest.fn().mockReturnValue( matchMediaMock );
		mw.Title.newFromText = jest.fn().mockImplementation(
			( title ) => ( { getPrefixedText: () => title } )
		);
		mw.Api.prototype.post = jest.fn().mockResolvedValue( {
			parse: {
				text: '<wiki-chart></wiki-chart>'
			}
		} );
		mw.loader.using = jest.fn().mockResolvedValue();
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

	it( 'should normalize rect chart type to bar', () => {
		shallowMount( ChartWizard, {
			global: { plugins: [ createTestingPinia( { stubActions: false } ) ] },
			props: {
				chartDefinition: Object.assign(
					{},
					fixtures[ 'Data:Example.Line.chart' ],
					{
						type: 'rect'
					}
				),
				chartIsNew: false
			}
		} );
		mockMwApiGet();
		const store = useChartStore();
		expect( store.type ).toBe( 'bar' );
		expect( store.chartDefinition.type ).toBe( 'bar' );
	} );

	it( 'should omit transform when the chart definition has none', () => {
		const chartDefinition = Object.assign( {}, fixtures[ 'Data:Example.Line.chart' ] );
		delete chartDefinition.transform;
		shallowMount( ChartWizard, {
			global: { plugins: [ createTestingPinia( { stubActions: false } ) ] },
			props: {
				chartDefinition,
				chartIsNew: false
			}
		} );
		const store = useChartStore();
		expect( store.transform ).toStrictEqual( {} );
		expect( store.chartDefinition ).not.toHaveProperty( 'transform' );
	} );

	it( 'should keep preview errors out of form constraints', async () => {
		const form = document.createElement( 'form' );
		form.id = 'ext-chart-wizard';
		shallowMount( ChartWizard, {
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
		expect( form.checkValidity() ).toBeTruthy();
		expect( form.reportValidity() ).toBeTruthy();
		expect( store.sourceStatus ).toBeNull();
		document.body.removeChild( form );
	} );

	it( 'should wrap the preview area in an accordion on small viewports', async () => {
		const wrapper = shallowMount( ChartWizard, {
			global: { plugins: [ createTestingPinia() ] },
			props: {
				chartDefinition: fixtures[ 'Data:Example.Line.chart' ],
				chartIsNew: false
			}
		} );
		expect( wrapper.findComponent( CdxAccordion ).exists() ).toBe( false );
		matchMediaChangeListener( { matches: true } );
		await nextTick();
		expect( wrapper.findComponent( CdxAccordion ).exists() ).toBe( true );
	} );
} );
