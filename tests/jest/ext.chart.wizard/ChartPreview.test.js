'use strict';

const { mount, flushPromises } = require( '@vue/test-utils' );
const { createTestingPinia } = require( '@pinia/testing' );
const ChartPreview = require( '../../../resources/ext.chart.wizard/components/ChartPreview.vue' );
const { mockMwApiGet } = require( './ChartWizard.setup.js' );
const useChartStore = require( '../../../resources/ext.chart.wizard/stores/chart.js' );

describe( 'ChartPreview', () => {

	let wrapper, store, hookFire, configValues, $previewContainer;

	beforeEach( () => {
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

	afterEach( () => jest.clearAllMocks() );

	it( 'should not request a preview without a source', () => {
		expect( mw.Api.prototype.post ).not.toHaveBeenCalled();
		expect( mw.Api.prototype.get ).not.toHaveBeenCalled();
		expect( wrapper.find( '.ext-chart-wizard__preview-placeholder' ).exists() ).toBeTruthy();
	} );

	it( 'should request and inject a parsed chart preview', async () => {
		store.source = 'Data:Chart Example Data.tab';
		await flushPromises();

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
		await flushPromises();

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
		await flushPromises();
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

	it( 'should request a new parsed chart preview when the definition changes', async () => {
		store.source = 'Data:Chart Example Data.tab';
		await flushPromises();

		store.xAxis.title = { en: 'Date' };
		await flushPromises();

		expect( mw.Api.prototype.post ).toHaveBeenCalledTimes( 2 );
		expect( mw.Api.prototype.post.mock.calls[ 1 ][ 0 ].text ).toBe(
			JSON.stringify( store.chartDefinition )
		);
	} );

	it( 'should show an error if the preview failed to load', async () => {
		mw.Api.prototype.post.mockRejectedValue( 'error' );
		store.source = 'Data:Chart Example Data.tab';
		await flushPromises();

		expect( wrapper.find( '.ext-chart-wizard__preview-placeholder' ).exists() ).toBeFalsy();
		expect( wrapper.find( '.ext-chart-wizard__preview--error' ).exists() ).toBeTruthy();
	} );
} );
