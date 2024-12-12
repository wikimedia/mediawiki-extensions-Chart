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
		const format = numberFormatter( 'en' );
		expect( format( 5.44444444444 ) ).toBe( '5.44' );
	} );
	it( 'formats 1000 as 1K', () => {
		const format = numberFormatter( 'en' );
		expect( format( 1000 ) ).toBe( '1K' );
	} );

	it( 'formats to four figures between 100 and 1000 as expected', () => {
		const format = numberFormatter( 'en' );
		expect( format( 999.4555555 ) ).toBe( '999' );
	} );

	it( 'formats large numbers on axis to nearest integers', () => {
		const format = numberFormatter( 'en' );
		expect( format( 99.4555555 ) ).toBe( '99.46' );
	} );
} );

describe( 'getFormatter', () => {
	it( 'formats integers correctly', () => {
		const format = getFormatter( [ 1, 2, 3 ], 'fa' );
		expect( format( '5' ) ).toBe( '۵' );
		expect( format( 5 ) ).toBe( '۵' );
	} );
} );
