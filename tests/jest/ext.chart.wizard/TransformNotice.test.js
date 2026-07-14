'use strict';

const { mount } = require( '@vue/test-utils' );
const { createTestingPinia } = require( '@pinia/testing' );
const { CdxInfoChip, CdxMessage } = require( '@wikimedia/codex' );
const TransformNotice = require(
	'../../../resources/ext.chart.wizard/components/TransformNotice.vue'
);

describe( 'TransformNotice', () => {
	beforeEach( () => {
		mw.message = jest.fn( ( key, moduleTitle, functionName ) => ( {
			parse: () => `Data is transformed by <a href="/wiki/${ moduleTitle }">${ moduleTitle }</a>, function ${ functionName.outerHTML }.`
		} ) );
	} );

	afterEach( () => jest.clearAllMocks() );

	it( 'displays the transform module, function, and arguments', () => {
		const wrapper = mount( TransformNotice, {
			global: {
				directives: {
					'i18n-html': ( el, binding ) => {
						el.innerHTML = binding.value.parse();
					}
				},
				plugins: [ createTestingPinia( {
					initialState: {
						chart: {
							transform: {
								module: 'Foo',
								function: 'bar',
								args: {
									baz: 5,
									qux: 'quux'
								}
							}
						}
					}
				} ) ]
			}
		} );

		expect( wrapper.findComponent( CdxMessage ).exists() ).toBe( true );
		expect( wrapper.vm.transform.function ).toBe( 'bar' );
		expect( wrapper.get( 'a' ).text() ).toBe( 'Module:Foo' );
		expect( wrapper.get( 'code' ).text() ).toBe( 'bar' );
		expect( wrapper.findAllComponents( CdxInfoChip ).map( ( chip ) => chip.text() ) )
			.toStrictEqual( [ 'baz=5', 'qux=quux' ] );
	} );

	it( 'does not display a notice without a transform', () => {
		const wrapper = mount( TransformNotice, {
			global: { plugins: [ createTestingPinia() ] }
		} );

		expect( wrapper.findComponent( CdxMessage ).exists() ).toBe( false );
	} );
} );
