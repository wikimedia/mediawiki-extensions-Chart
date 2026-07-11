'use strict';

const { mount, flushPromises } = require( '@vue/test-utils' );
const { createTestingPinia } = require( '@pinia/testing' );
const ChartPreview = require( '../../../resources/ext.chart.wizard/components/ChartPreview.vue' );
const { mockMwApiGet } = require( './ChartWizard.setup.js' );
const useChartStore = require( '../../../resources/ext.chart.wizard/stores/chart.js' );

describe( 'ChartPreview', () => {

	let wrapper, store, hookFire, configValues, $previewContainer;

	async function flushPreviewDebounce() {
		await flushPromises();
		jest.runOnlyPendingTimers();
		await flushPromises();
	}

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
		hookFire = jest.fn();
		mw.hook = jest.fn().mockReturnValue( { fire: hookFire } );
		$previewContainer = null;
		window.jQuery = jest.fn( ( element ) => {
			const collection = {
				0: element,
				length: 1,
				empty: jest.fn( () => {
					element.innerHTML = '';
					return collection;
				} ),
				append: jest.fn( ( content ) => {
					content.forEach( ( node ) => element.appendChild( node ) );
					return collection;
				} )
			};
			$previewContainer = collection;
			return collection;
		} );
		window.jQuery.parseHTML = jest.fn( ( html ) => {
			const template = document.createElement( 'template' );
			template.innerHTML = html;
			return Array.from( template.content.childNodes );
		} );
		mockMwApiGet();

		wrapper = mount( ChartPreview, {
			global: { plugins: [ createTestingPinia( { stubActions: false } ) ] }
		} );
		expect( wrapper.find( '.ext-chart-wizard__preview-placeholder' ).exists() ).toBeTruthy();
		store = useChartStore();
	} );

	afterEach( () => {
		wrapper.unmount();
		jest.useRealTimers();
		jest.clearAllMocks();
	} );

	it( 'should not request a preview without a source', () => {
		expect( mw.Api.prototype.post ).not.toHaveBeenCalled();
		expect( mw.Api.prototype.get ).not.toHaveBeenCalled();
		expect( wrapper.find( '.ext-chart-wizard__preview-placeholder' ).exists() ).toBeTruthy();
	} );

	it( 'should request the initial parsed chart preview without a delay', async () => {
		wrapper.unmount();
		const pinia = createTestingPinia( { stubActions: false } );
		store = useChartStore();
		store.source = 'Data:Chart Example Data.tab';
		mw.Api.prototype.post.mockClear();

		wrapper = mount( ChartPreview, {
			global: { plugins: [ pinia ] }
		} );
		await flushPromises();

		expect( mw.Api.prototype.post ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'should request and inject a parsed chart preview', async () => {
		store.source = 'Data:Chart Example Data.tab';
		await flushPreviewDebounce();

		expect( wrapper.find( '.ext-chart-wizard__preview-placeholder' ).exists() ).toBeFalsy();
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
		expect( mw.hook ).toHaveBeenCalledWith( 'wikipage.content' );
		expect( hookFire ).toHaveBeenCalledWith( $previewContainer );
	} );

	it( 'should wait for parsed chart modules before injecting the preview', async () => {
		let resolveModules;
		mw.loader.using.mockReturnValue( new Promise( ( resolve ) => {
			resolveModules = resolve;
		} ) );

		store.source = 'Data:Chart Example Data.tab';
		await flushPreviewDebounce();

		expect( mw.loader.using ).toHaveBeenCalledWith( [
			'ext.chart.bootstrap',
			'ext.chart.styles'
		] );
		expect( hookFire ).not.toHaveBeenCalled();
		expect( wrapper.find( '[data-testid="chart-preview"]' ).exists() ).toBeFalsy();

		resolveModules();
		await flushPromises();

		expect( wrapper.find( '[data-testid="chart-preview"]' ).exists() ).toBeTruthy();
		expect( hookFire ).toHaveBeenCalledWith( $previewContainer );
	} );

	it( 'should show a preview of the source dataset in the data view', async () => {
		store.source = 'Data:Chart Example Data.tab';
		await flushPromises();
		const dataButton = wrapper.findAll( 'button' ).find(
			( button ) => button.text() === 'chart-wizard-preview-data-view'
		);

		await dataButton.trigger( 'click' );
		await flushPromises();

		expect( mw.Api.prototype.get ).toHaveBeenCalledWith( {
			action: 'query',
			format: 'json',
			prop: 'revisions',
			rvprop: 'content',
			titles: 'Data:Chart Example Data.tab',
			formatversion: 2
		} );
		expect( wrapper.find( '.ext-chart-wizard__preview--chart' ).exists() ).toBeFalsy();
		const json = JSON.parse( wrapper.find( '.ext-chart-wizard__preview--source' ).text() );
		expect( json.description.en ).toBe(
			'Some meaningless example data about Middle-Earth for showcasing wiki charts.'
		);
		expect( json.schema.fields.length ).toBeGreaterThan( 4 );
	} );

	it( 'should not show the empty-source placeholder while the data view loads', async () => {
		let resolveSourcePreview;
		mw.Api.prototype.get.mockReturnValue( new Promise( ( resolve ) => {
			resolveSourcePreview = resolve;
		} ) );
		store.source = 'Data:Chart Example Data.tab';
		await flushPreviewDebounce();
		const dataButton = wrapper.findAll( 'button' ).find(
			( button ) => button.text() === 'chart-wizard-preview-data-view'
		);

		await dataButton.trigger( 'click' );

		expect( wrapper.find( '.ext-chart-wizard__preview-placeholder' ).exists() ).toBeFalsy();
		expect( wrapper.find( '.ext-chart-wizard__preview--data' ).exists() ).toBeTruthy();

		resolveSourcePreview( {
			query: {
				pages: [ {
					revisions: [ {
						content: JSON.stringify( { description: { en: 'Example' } } )
					} ]
				} ]
			}
		} );
		await flushPromises();
		expect( wrapper.find( '.ext-chart-wizard__preview--source' ).exists() ).toBeTruthy();
	} );

	it( 'should clear the previous source preview while loading a new source', async () => {
		mw.Api.prototype.get.mockResolvedValue( sourcePreviewResponse( 'Old data' ) );
		store.source = 'Data:Old.tab';
		await flushPreviewDebounce();
		wrapper.vm.selectPreview( 'data' );
		await flushPromises();
		expect( wrapper.find( '.ext-chart-wizard__preview--source' ).exists() ).toBeTruthy();

		wrapper.vm.selectPreview( 'chart' );
		await flushPromises();
		store.source = 'Data:New.tab';
		await flushPromises();
		mw.Api.prototype.get.mockReturnValue( new Promise( () => {} ) );
		wrapper.vm.selectPreview( 'data' );
		await flushPromises();

		expect( wrapper.find( '.ext-chart-wizard__preview--source' ).exists() ).toBeFalsy();
	} );

	it( 'should clear the previous source error while loading a new source', async () => {
		mw.Api.prototype.get.mockRejectedValue( new Error( 'Source preview failed' ) );
		store.source = 'Data:Old.tab';
		await flushPromises();
		wrapper.vm.selectPreview( 'data' );
		await flushPromises();
		expect( wrapper.find( '.ext-chart-wizard__preview--error' ).exists() ).toBeTruthy();

		wrapper.vm.selectPreview( 'chart' );
		await flushPromises();
		store.source = 'Data:New.tab';
		await flushPromises();
		mw.Api.prototype.get.mockReturnValue( new Promise( () => {} ) );
		wrapper.vm.selectPreview( 'data' );
		await flushPromises();

		expect( wrapper.find( '.ext-chart-wizard__preview--error' ).exists() ).toBeFalsy();
	} );

	it( 'should show the placeholder when the selected source is cleared', async () => {
		mw.Api.prototype.get.mockRejectedValue( new Error( 'Source preview failed' ) );
		store.source = 'Data:Chart Example Data.tab';
		await flushPromises();
		wrapper.vm.selectPreview( 'data' );
		await flushPromises();
		expect( wrapper.find( '.ext-chart-wizard__preview--error' ).exists() ).toBeTruthy();

		store.source = '';
		await flushPromises();

		expect( wrapper.find( '.ext-chart-wizard__preview--error' ).exists() ).toBeFalsy();
		expect( wrapper.find( '.ext-chart-wizard__preview-placeholder' ).exists() ).toBeTruthy();
	} );

	it( 'should ignore source previews from superseded requests', async () => {
		const requests = [];
		mw.Api.prototype.get.mockImplementation( () => new Promise( ( resolve ) => {
			requests.push( resolve );
		} ) );
		store.source = 'Data:Old.tab';
		await flushPromises();
		wrapper.vm.selectPreview( 'data' );
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
		store.source = 'Data:Old.tab';
		await flushPromises();
		wrapper.vm.selectPreview( 'data' );
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

	it( 'should request a new parsed chart preview when the definition changes', async () => {
		store.source = 'Data:Chart Example Data.tab';
		await flushPreviewDebounce();

		store.xAxis.title = { en: 'Date' };
		await flushPreviewDebounce();

		expect( mw.Api.prototype.post ).toHaveBeenCalledTimes( 2 );
		expect( mw.Api.prototype.post.mock.calls[ 1 ][ 0 ].text ).toBe(
			JSON.stringify( store.chartDefinition )
		);
	} );

	it( 'should immediately request a parsed chart preview when the language changes', async () => {
		store.source = 'Data:Chart Example Data.tab';
		await flushPreviewDebounce();

		store.currentLanguage = 'fr';
		await flushPromises();

		expect( mw.Api.prototype.post ).toHaveBeenCalledTimes( 2 );
		expect( mw.Api.prototype.post.mock.calls[ 1 ][ 0 ].uselang ).toBe( 'fr' );
	} );

	it( 'should abort a superseded parsed chart preview request', async () => {
		mw.Api.prototype.post.mockReturnValue( new Promise( () => {} ) );
		store.source = 'Data:Chart Example Data.tab';
		await flushPreviewDebounce();
		mw.Api.prototype.abort.mockClear();

		store.xAxis.title = { en: 'Date' };
		await flushPromises();

		expect( mw.Api.prototype.abort ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'should debounce parsed chart previews when the definition changes rapidly', async () => {
		store.source = 'Data:Chart Example Data.tab';
		await flushPreviewDebounce();

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

		store.source = 'Data:Chart Example Data.tab';
		await flushPreviewDebounce();
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

		store.source = 'Data:Chart Example Data.tab';
		await flushPreviewDebounce();
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
		store.source = 'Data:Chart Example Data.tab';
		await flushPreviewDebounce();
		expect( wrapper.find( '.ext-chart-wizard__preview--error' ).exists() ).toBeTruthy();

		store.title.en = 'Updated';
		await flushPromises();

		expect( wrapper.find( '.ext-chart-wizard__preview--error' ).exists() ).toBeFalsy();
		expect( mw.Api.prototype.post ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'should show an error if the preview failed to load', async () => {
		mw.Api.prototype.post.mockRejectedValue( 'error' );
		store.source = 'Data:Chart Example Data.tab';
		await flushPreviewDebounce();

		expect( wrapper.find( '.ext-chart-wizard__preview-placeholder' ).exists() ).toBeFalsy();
		expect( wrapper.find( '.ext-chart-wizard__preview--error' ).exists() ).toBeTruthy();
	} );
} );
