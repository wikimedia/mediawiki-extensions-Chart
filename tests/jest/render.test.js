const { getFormatterForType, numberFormatter } = require( '../../resources/ext.chart/render.js' );

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
		const format = getFormatterForType( 'integer', 'fa' );
		expect( format( '5' ) ).toBe( '۵' );
		expect( format( 5 ) ).toBe( '۵' );
	} );
	it( 'formats floats correctly', () => {
		const format = getFormatterForType( 'float', 'fa' );
		expect( format( '5.3343444' ) ).toBe( '۵٫۳۳' );
	} );
} );
