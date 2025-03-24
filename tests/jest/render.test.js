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
	it( 'formats integers correctly with formatMode none', () => {
		const format = getFormatterForType( 'integer', 'none', 'fa' );
		expect( format( '5' ) ).toBe( '۵' );
		expect( format( 5 ) ).toBe( '۵' );
	} );
	it( 'formats floats correctly with formatMode none', () => {
		const format = getFormatterForType( 'float', 'none', 'fa' );
		expect( format( '5.3343444' ) ).toBe( '۵٫۳۳۴۳۴۴۴' );
	} );
	it( 'formats integers correctly with formatMode none and no comma separator', () => {
		const format = getFormatterForType( 'integer', 'none', 'en' );
		expect( format( 2025 ) ).toBe( '2025' );
	} );

	it( 'formats integers correctly with formatMode auto', () => {
		const format = getFormatterForType( 'integer', 'auto', 'fa' );
		expect( format( '5' ) ).toBe( '۵' );
		expect( format( 5 ) ).toBe( '۵' );
	} );
	it( 'formats floats correctly with formatMode auto', () => {
		const format = getFormatterForType( 'float', 'auto', 'fa' );
		expect( format( '5.3343444' ) ).toBe( '۵٫۳۳' );
	} );
	it( 'formats integers correctly with formatMode auto and comma separator', () => {
		const format = getFormatterForType( 'integer', 'auto', 'en' );
		expect( format( 2025 ) ).toBe( '2K' );
	} );

} );
