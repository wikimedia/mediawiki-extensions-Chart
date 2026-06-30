'use strict';

const { mount } = require( '@vue/test-utils' );
const { createTestingPinia } = require( '@pinia/testing' );
const CategoriesField = require( '../../../resources/ext.chart.wizard/components/CategoriesField.vue' );
const useChartStore = require( '../../../resources/ext.chart.wizard/stores/chart.js' );

describe( 'CategoriesField', () => {

	let wrapper, store;

	beforeEach( () => {
		wrapper = mount( CategoriesField, {
			global: { plugins: [ createTestingPinia( {
				stubActions: false,
				initialState: {
					chart: {
						mediawikiCategories: [
							{ name: 'Test category A', sort: 'Test sort key' },
							{ name: 'Test category B' }
						]
					}
				}
			} ) ] }
		} );
		store = useChartStore();
	} );

	it( 'should show an array of input chips for each category', () => {
		expect( wrapper.vm.inputChips ).toStrictEqual( [
			{ label: 'Test category A', value: 'Test category A' },
			{ label: 'Test category B', value: 'Test category B' }
		] );
		expect( wrapper.findAll( '.cdx-input-chip' ) ).toHaveLength( 2 );
	} );

	it( 'should retain sort keys already defined on categories', () => {
		wrapper.vm.onUpdateSelected( [
			'Test category A',
			'Test category B',
			'Test category C'
		] );
		expect( store.mediawikiCategories ).toStrictEqual( [
			{ name: 'Test category A', sort: 'Test sort key' },
			{ name: 'Test category B' },
			{ name: 'Test category C' }
		] );
	} );
} );
