const { isDateSeries } = require( '../../resources/ext.chart/render.js' );

describe( 'isDateSeries', () => {
	it( 'does not convert numbers to dates', () => {
		expect( isDateSeries( [ 0, 1, 2, 4 ] ) ).toBe( false );
	} );
	it( 'does not convert incomplete dates', () => {
		expect( isDateSeries( [ '2024', '2023', '2022', '2021' ] ) ).toBe( false );
	} );
	it( 'does convert dates in supported ISO format', () => {
		expect( isDateSeries( [
			'2024-02-20',
			'2023-02-20',
			'2022-02-20',
			'2021-02-20'
		] ) ).toBe( true );
	} );
	it( 'does convert dates if one bad date', () => {
		expect( isDateSeries( [ '2024-02-20', '2023-02', '2022-02-20', '2021-02-20' ] ) ).toBe( false );
	} );
} );
