'use strict';

const { mount, flushPromises } = require( '@vue/test-utils' );
const { createTestingPinia } = require( '@pinia/testing' );
const SourceDataPreview = require(
	'../../../resources/ext.chart.wizard/components/SourceDataPreview.vue'
);
const { mockMwApiGet } = require( './ChartWizard.setup.js' );
const useChartStore = require( '../../../resources/ext.chart.wizard/stores/chart.js' );

describe( 'SourceDataPreview', () => {

	let wrapper, store;

	function sourcePreviewResponse( description ) {
		return {
			query: {
				pages: [ {
					revisions: [ {
						content: JSON.stringify( { description: { en: description } } )
					} ]
				} ]
			}
		};
	}

	function mountPreview( source ) {
		const pinia = createTestingPinia( { stubActions: false } );
		store = useChartStore();
		store.source = source;
		wrapper = mount( SourceDataPreview, {
			global: { plugins: [ pinia ] }
		} );
	}

	beforeEach( () => {
		mockMwApiGet();
	} );

	afterEach( () => {
		if ( wrapper ) {
			wrapper.unmount();
		}
		jest.clearAllMocks();
	} );

	it( 'should request and show a preview of the source dataset', async () => {
		mountPreview( 'Data:Chart Example Data.tab' );
		await flushPromises();

		expect( mw.Api.prototype.get ).toHaveBeenCalledWith( {
			action: 'query',
			format: 'json',
			prop: 'revisions',
			rvprop: 'content',
			titles: 'Data:Chart Example Data.tab',
			formatversion: 2
		} );
		const json = JSON.parse( wrapper.find( '.ext-chart-wizard__preview--source' ).text() );
		expect( json.description.en ).toBe(
			'Some meaningless example data about Middle-Earth for showcasing wiki charts.'
		);
		expect( json.schema.fields.length ).toBeGreaterThan( 4 );
	} );

	it( 'should show an empty data view while the source loads', async () => {
		mw.Api.prototype.get.mockReturnValue( new Promise( () => {} ) );
		mountPreview( 'Data:Chart Example Data.tab' );
		await flushPromises();

		expect( wrapper.find( '.ext-chart-wizard__preview--data' ).exists() ).toBeTruthy();
		expect( wrapper.find( '.ext-chart-wizard__preview--source' ).exists() ).toBeFalsy();
		expect( wrapper.find( '.ext-chart-wizard__preview--error' ).exists() ).toBeFalsy();
	} );

	it( 'should clear the previous source preview while loading a new source', async () => {
		mw.Api.prototype.get.mockResolvedValue( sourcePreviewResponse( 'Old data' ) );
		mountPreview( 'Data:Old.tab' );
		await flushPromises();
		expect( wrapper.find( '.ext-chart-wizard__preview--source' ).exists() ).toBeTruthy();

		mw.Api.prototype.get.mockReturnValue( new Promise( () => {} ) );
		store.source = 'Data:New.tab';
		await flushPromises();

		expect( wrapper.find( '.ext-chart-wizard__preview--source' ).exists() ).toBeFalsy();
	} );

	it( 'should clear the previous source error while loading a new source', async () => {
		mw.Api.prototype.get.mockRejectedValue( new Error( 'Source preview failed' ) );
		mountPreview( 'Data:Old.tab' );
		await flushPromises();
		expect( wrapper.find( '.ext-chart-wizard__preview--error' ).exists() ).toBeTruthy();

		mw.Api.prototype.get.mockReturnValue( new Promise( () => {} ) );
		store.source = 'Data:New.tab';
		await flushPromises();

		expect( wrapper.find( '.ext-chart-wizard__preview--error' ).exists() ).toBeFalsy();
	} );

	it( 'should show an error when the source is missing', async () => {
		mw.Api.prototype.get.mockResolvedValue( {
			query: {
				pages: [ { missing: true } ]
			}
		} );
		mountPreview( 'Data:Missing.tab' );
		await flushPromises();

		expect( wrapper.find( '.ext-chart-wizard__preview--error' ).exists() ).toBeTruthy();
	} );

	it( 'should show an error when the source request fails', async () => {
		mw.Api.prototype.get.mockRejectedValue( new Error( 'Source preview failed' ) );
		mountPreview( 'Data:Erroneous.tab' );
		await flushPromises();

		expect( wrapper.find( '.ext-chart-wizard__preview--error' ).exists() ).toBeTruthy();
	} );

	it( 'should ignore source previews from superseded requests', async () => {
		const requests = [];
		mw.Api.prototype.get.mockImplementation( () => new Promise( ( resolve ) => {
			requests.push( resolve );
		} ) );
		mountPreview( 'Data:Old.tab' );
		await flushPromises();
		store.source = 'Data:New.tab';
		await flushPromises();

		requests[ 1 ]( sourcePreviewResponse( 'New data' ) );
		await flushPromises();
		requests[ 0 ]( sourcePreviewResponse( 'Old data' ) );
		await flushPromises();

		expect( wrapper.find( '.ext-chart-wizard__preview--source' ).text() )
			.toContain( 'New data' );
		expect( wrapper.find( '.ext-chart-wizard__preview--source' ).text() )
			.not.toContain( 'Old data' );
	} );

	it( 'should ignore errors from superseded source preview requests', async () => {
		const requests = [];
		mw.Api.prototype.get.mockImplementation( () => new Promise( ( resolve, reject ) => {
			requests.push( { resolve, reject } );
		} ) );
		mountPreview( 'Data:Old.tab' );
		await flushPromises();
		store.source = 'Data:New.tab';
		await flushPromises();

		requests[ 1 ].resolve( sourcePreviewResponse( 'New data' ) );
		await flushPromises();
		requests[ 0 ].reject( new Error( 'Old request failed' ) );
		await flushPromises();

		expect( wrapper.find( '.ext-chart-wizard__preview--source' ).text() )
			.toContain( 'New data' );
		expect( wrapper.find( '.ext-chart-wizard__preview--error' ).exists() ).toBeFalsy();
	} );

	it( 'should abort the source request when unmounted', async () => {
		mw.Api.prototype.get.mockReturnValue( new Promise( () => {} ) );
		mountPreview( 'Data:Chart Example Data.tab' );
		await flushPromises();
		mw.Api.prototype.abort.mockClear();

		wrapper.unmount();

		expect( mw.Api.prototype.abort ).toHaveBeenCalledTimes( 1 );
	} );
} );
