'use strict';

const { mount, flushPromises } = require( '@vue/test-utils' );
const { createTestingPinia } = require( '@pinia/testing' );
const RenderedChartPreview = require(
	'../../../resources/ext.chart.wizard/components/RenderedChartPreview.vue'
);
const useChartStore = require( '../../../resources/ext.chart.wizard/stores/chart.js' );

describe( 'RenderedChartPreview', () => {

	let wrapper, store, configValues;

	async function flushPreviewDebounce() {
		await flushPromises();
		jest.runOnlyPendingTimers();
		await flushPromises();
	}

	function mountPreview( source = '' ) {
		const pinia = createTestingPinia( { stubActions: false } );
		store = useChartStore();
		store.source = source;
		wrapper = mount( RenderedChartPreview, {
			global: { plugins: [ pinia ] }
		} );
	}

	beforeEach( () => {
		jest.useFakeTimers();
		configValues = {};
		mw.config = {
			get: jest.fn( ( key ) => configValues[ key ] ),
			set: jest.fn( ( values ) => Object.assign( configValues, values ) )
		};
		mw.config.set( {
			chartPageName: 'Data:Example.Line.chart',
			skin: 'vector-2022',
			wgUserLanguage: 'en'
		} );
		mw.Api.prototype.post = jest.fn().mockResolvedValue( {
			parse: {
				text: '<wiki-chart data-testid="chart-preview"><svg height="320"></svg></wiki-chart>',
				modules: [ 'ext.chart.bootstrap' ],
				modulestyles: [ 'ext.chart.styles' ],
				jsconfigvars: {
					chartPreviewToken: 'test'
				}
			}
		} );
		mw.loader.using = jest.fn().mockResolvedValue();
	} );

	afterEach( () => {
		if ( wrapper ) {
			wrapper.unmount();
		}
		jest.useRealTimers();
		jest.clearAllMocks();
	} );

	it( 'should not request a preview without a source', async () => {
		mountPreview();
		await flushPromises();

		expect( mw.Api.prototype.post ).not.toHaveBeenCalled();
		expect( store.initialLoad ).toBeFalsy();
	} );

	it( 'should immediately request and inject the initial parsed chart preview', async () => {
		mountPreview( 'Data:Chart Example Data.tab' );
		await flushPromises();

		expect( mw.Api.prototype.post ).toHaveBeenCalledWith( {
			action: 'parse',
			formatversion: 2,
			title: 'Data:Example.Line.chart',
			text: JSON.stringify( store.chartDefinition ),
			contentmodel: 'Chart.JsonConfig',
			prop: 'text|modules|modulestyles|jsconfigvars',
			preview: true,
			disableeditsection: true,
			disablelimitreport: 1,
			useskin: 'vector-2022',
			uselang: 'en'
		}, {
			headers: { 'Promise-Non-Write-API-Action': 'true' }
		} );
		expect( wrapper.find( '[data-testid="chart-preview"]' ).exists() ).toBeTruthy();
		expect( wrapper.find( '[data-testid="chart-preview"]' ).element.style.minHeight ).toBe( '320px' );
		expect( mw.config.get( 'chartPreviewToken' ) ).toBe( 'test' );
		expect( mw.loader.using ).toHaveBeenCalledWith( [
			'ext.chart.bootstrap',
			'ext.chart.styles'
		] );
	} );

	it( 'should wait for parsed chart modules before injecting the preview', async () => {
		let resolveModules;
		mw.loader.using.mockReturnValue( new Promise( ( resolve ) => {
			resolveModules = resolve;
		} ) );
		mountPreview( 'Data:Chart Example Data.tab' );
		await flushPromises();

		expect( wrapper.find( '[data-testid="chart-preview"]' ).exists() ).toBeFalsy();

		resolveModules();
		await flushPromises();

		expect( wrapper.find( '[data-testid="chart-preview"]' ).exists() ).toBeTruthy();
	} );

	it( 'should request a new parsed chart preview when the definition changes', async () => {
		mountPreview( 'Data:Chart Example Data.tab' );
		await flushPromises();

		store.xAxis.title = { en: 'Date' };
		await flushPreviewDebounce();

		expect( mw.Api.prototype.post ).toHaveBeenCalledTimes( 2 );
		expect( mw.Api.prototype.post.mock.calls[ 1 ][ 0 ].text ).toBe(
			JSON.stringify( store.chartDefinition )
		);
	} );

	it( 'should immediately request a parsed chart preview when the language changes', async () => {
		mountPreview( 'Data:Chart Example Data.tab' );
		await flushPromises();

		store.currentLanguage = 'fr';
		await flushPromises();

		expect( mw.Api.prototype.post ).toHaveBeenCalledTimes( 2 );
		expect( mw.Api.prototype.post.mock.calls[ 1 ][ 0 ].uselang ).toBe( 'fr' );
	} );

	it( 'should abort a superseded parsed chart preview request', async () => {
		mw.Api.prototype.post.mockReturnValue( new Promise( () => {} ) );
		mountPreview( 'Data:Chart Example Data.tab' );
		await flushPromises();
		mw.Api.prototype.abort.mockClear();

		store.xAxis.title = { en: 'Date' };
		await flushPromises();

		expect( mw.Api.prototype.abort ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'should debounce parsed chart previews when the definition changes rapidly', async () => {
		mountPreview( 'Data:Chart Example Data.tab' );
		await flushPromises();

		store.title.en = 'E';
		store.title.en = 'Ex';
		store.title.en = 'Example';
		await flushPromises();

		expect( mw.Api.prototype.post ).toHaveBeenCalledTimes( 1 );

		await flushPreviewDebounce();

		expect( mw.Api.prototype.post ).toHaveBeenCalledTimes( 2 );
		expect( mw.Api.prototype.post.mock.calls[ 1 ][ 0 ].text ).toBe(
			JSON.stringify( store.chartDefinition )
		);
	} );

	it( 'should ignore parsed chart previews from superseded requests', async () => {
		const requests = [];
		mw.Api.prototype.post.mockImplementation( () => new Promise( ( resolve ) => {
			requests.push( resolve );
		} ) );
		mountPreview( 'Data:Chart Example Data.tab' );
		await flushPromises();
		store.title.en = 'Updated';
		await flushPreviewDebounce();

		requests[ 1 ]( {
			parse: {
				text: '<wiki-chart data-testid="new-preview"></wiki-chart>'
			}
		} );
		await flushPromises();
		requests[ 0 ]( {
			parse: {
				text: '<wiki-chart data-testid="old-preview"></wiki-chart>'
			}
		} );
		await flushPromises();

		expect( wrapper.find( '[data-testid="new-preview"]' ).exists() ).toBeTruthy();
		expect( wrapper.find( '[data-testid="old-preview"]' ).exists() ).toBeFalsy();
	} );

	it( 'should ignore errors from superseded parsed chart preview requests', async () => {
		const requests = [];
		mw.Api.prototype.post.mockImplementation( () => new Promise( ( resolve, reject ) => {
			requests.push( { resolve, reject } );
		} ) );
		mountPreview( 'Data:Chart Example Data.tab' );
		await flushPromises();
		store.title.en = 'Updated';
		await flushPreviewDebounce();

		requests[ 1 ].resolve( {
			parse: {
				text: '<wiki-chart data-testid="new-preview"></wiki-chart>'
			}
		} );
		await flushPromises();
		requests[ 0 ].reject( new Error( 'abort' ) );
		await flushPromises();

		expect( wrapper.find( '[data-testid="new-preview"]' ).exists() ).toBeTruthy();
		expect( wrapper.find( '.ext-chart-wizard__preview--error' ).exists() ).toBeFalsy();
	} );

	it( 'should clear a preview error while scheduling a new preview', async () => {
		mw.Api.prototype.post.mockRejectedValue( 'error' );
		mountPreview( 'Data:Chart Example Data.tab' );
		await flushPromises();
		expect( wrapper.find( '.ext-chart-wizard__preview--error' ).exists() ).toBeTruthy();

		store.title.en = 'Updated';
		await flushPromises();

		expect( wrapper.find( '.ext-chart-wizard__preview--error' ).exists() ).toBeFalsy();
		expect( mw.Api.prototype.post ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'should show an error if the preview failed to load', async () => {
		mw.Api.prototype.post.mockRejectedValue( 'error' );
		mountPreview( 'Data:Chart Example Data.tab' );
		await flushPromises();

		expect( wrapper.find( '.ext-chart-wizard__preview--error' ).exists() ).toBeTruthy();
	} );
} );
