const { isDateSeries, getFormatter, numberFormatter } = require( '../../resources/ext.chart/render.js' );

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

describe( 'numberFormatter', () => {
	it( 'formats numbers to minimum of 2 decimal places', () => {
		const format = numberFormatter( 'en', 2 );
		expect( format( 5.44444444444 ) ).toBe( '5.44' );
	} );
} );

describe( 'getFormatter', () => {
	it( 'formats integers correctly', () => {
		const format = getFormatter( [ 1, 2, 3 ], 'fa' );
		expect( format( '5' ) ).toBe( '۵' );
		expect( format( 5 ) ).toBe( '۵' );
	} );
} );
