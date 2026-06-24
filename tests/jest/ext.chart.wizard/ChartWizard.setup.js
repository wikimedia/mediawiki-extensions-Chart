'use strict';

const fixtures = {
	'Data:Example.Line.chart': require( './fixtures/Data:Example.Line.chart.json' ),
	'Data:Chart Example Data.tab': require( './fixtures/Data:Chart Example Data.tab.json' )
};

/**
 * Mock calls to mw.Api.prototype.get() based on given parameters and response.
 * The default implementation mocks API GET requests used across the test suite.
 *
 * @param {Array<Object>} [additionalMocks] Additional mocks to add to the default list.
 *   Each object should contain the keys `params` and `response`.
 *   `params` is an Object with sufficient set of parameters to identify the request
 *   (e.g. `{ list: 'logevents', letype: 'block' }`). The `response` is an Object
 *   with the expected response data (e.g. `{ query: { logevents: [ ... ] } }`).
 */
function mockMwApiGet( additionalMocks = [] ) {
	/**
	 * This is intended to encapsulate any API requests that
	 * consistently need to be mocked across the test suite.
	 *
	 * @type {Object}
	 */
	const mocks = [
		{
			params: {
				prop: 'revisions',
				rvprop: 'content',
				titles: 'Data:Chart Example Data.tab'
			},
			response: {
				query: {
					pages: [ {
						revisions: [ {
							content: JSON.stringify(
								fixtures[ 'Data:Chart Example Data.tab' ]
							)
						} ]
					} ]
				}
			}
		},
		{
			params: {
				prop: 'revisions',
				rvprop: 'content',
				titles: 'Data:Nonexistent data.tab'
			},
			response: {
				query: {
					pages: [ {
						missing: true
					} ]
				}
			}
		},
		{
			params: {
				prop: 'revisions',
				rvprop: 'content',
				titles: 'Data:Erroneous data.tab'
			},
			reject: true
		},
		{
			params: {
				prop: 'revisions',
				rvprop: 'content',
				titles: 'Chart Example Data.tab'
			},
			response: {
				query: {
					pages: [ {
						revisions: [ {
							content: JSON.stringify(
								fixtures[ 'Data:Chart Example Data.tab' ]
							)
						} ]
					} ]
				}
			}
		},
		// Add more shared mocks as needed above this line.
		...additionalMocks
	];
	mw.Api.prototype.get.mockImplementation( ( params ) => {
		if ( !params ) {
			// eslint-disable-next-line no-console
			console.warn( 'No params provided to mw.Api.get()' );
			return Promise.resolve( jest.fn() );
		}
		// Find the appropriate mock from the list based on the expected parameters.
		const mock = mocks.find( ( m ) => Object.entries( m.params )
			.every( ( [ key, value ] ) => params[ key ] === value )
		);
		if ( !mock ) {
			// eslint-disable-next-line no-console
			console.warn( 'No mock found for:', params );
			return Promise.resolve( jest.fn() );
		}
		if ( mock.response ) {
			return Promise.resolve( mock.response );
		}
		return Promise.reject( mock.reject );
	} );
}

module.exports = {
	fixtures,
	mockMwApiGet
};
