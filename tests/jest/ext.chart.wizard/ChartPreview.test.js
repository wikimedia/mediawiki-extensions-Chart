'use strict';

const { mount, flushPromises } = require( '@vue/test-utils' );
const { createTestingPinia } = require( '@pinia/testing' );
const ChartPreview = require( '../../../resources/ext.chart.wizard/components/ChartPreview.vue' );
const useChartStore = require( '../../../resources/ext.chart.wizard/stores/chart.js' );

describe( 'ChartPreview', () => {

	let wrapper, store, renderedPreviewMounts;

	function mountPreview( source = '' ) {
		const pinia = createTestingPinia( { stubActions: false } );
		store = useChartStore();
		store.source = source;
		wrapper = mount( ChartPreview, {
			global: {
				plugins: [ pinia ],
				stubs: {
					RenderedChartPreview: {
						template: '<div data-testid="rendered-chart-preview"></div>',
						setup() {
							renderedPreviewMounts++;
						}
					},
					SourceDataPreview: {
						template: '<div data-testid="source-data-preview"></div>'
					}
				}
			}
		} );
	}

	beforeEach( () => {
		renderedPreviewMounts = 0;
		mw.config.get = jest.fn().mockReturnValue( 'en' );
	} );

	afterEach( () => {
		wrapper.unmount();
		jest.clearAllMocks();
	} );

	it( 'should show the placeholder without a source', () => {
		mountPreview();

		expect( wrapper.find( '.ext-chart-wizard__preview-placeholder' ).exists() ).toBeTruthy();
		expect( wrapper.find( '[data-testid="rendered-chart-preview"]' ).exists() ).toBeFalsy();
		expect( wrapper.find( '[data-testid="source-data-preview"]' ).exists() ).toBeFalsy();
		expect( store.initialLoad ).toBeFalsy();
	} );

	it( 'should switch between the rendered chart and source data previews', async () => {
		mountPreview( 'Data:Chart Example Data.tab' );
		expect( wrapper.find( '[data-testid="rendered-chart-preview"]' ).exists() ).toBeTruthy();

		const buttons = wrapper.findAll( 'button' );
		const chartButton = buttons.find(
			( button ) => button.text() === 'chart-wizard-preview-chart-view'
		);
		const dataButton = buttons.find(
			( button ) => button.text() === 'chart-wizard-preview-data-view'
		);

		await dataButton.trigger( 'click' );
		expect( wrapper.find( '[data-testid="rendered-chart-preview"]' ).exists() ).toBeFalsy();
		expect( wrapper.find( '[data-testid="source-data-preview"]' ).exists() ).toBeTruthy();

		await chartButton.trigger( 'click' );
		expect( wrapper.find( '[data-testid="rendered-chart-preview"]' ).exists() ).toBeTruthy();
		expect( wrapper.find( '[data-testid="source-data-preview"]' ).exists() ).toBeFalsy();
	} );

	it( 'should not reload the already selected preview', async () => {
		mountPreview( 'Data:Chart Example Data.tab' );
		const chartButton = wrapper.findAll( 'button' ).find(
			( button ) => button.text() === 'chart-wizard-preview-chart-view'
		);

		await chartButton.trigger( 'click' );

		expect( renderedPreviewMounts ).toBe( 1 );
	} );

	it( 'should show the placeholder when the source is cleared', async () => {
		mountPreview( 'Data:Chart Example Data.tab' );

		store.source = '';
		await flushPromises();

		expect( wrapper.find( '.ext-chart-wizard__preview-placeholder' ).exists() ).toBeTruthy();
		expect( wrapper.find( '[data-testid="rendered-chart-preview"]' ).exists() ).toBeFalsy();
	} );
} );
