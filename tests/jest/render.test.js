const mockChart = {
	dispose: jest.fn(),
	getOption: jest.fn( () => ( {} ) ),
	getWidth: jest.fn( () => 100 ),
	resize: jest.fn(),
	setOption: jest.fn()
};

jest.mock( '../../lib/echarts/echarts.common.js', () => ( {
	init: jest.fn( () => mockChart )
} ) );

const echarts = require( '../../lib/echarts/echarts.common.js' );
const { getFormatterForType, numberFormatter, render } = require( '../../resources/ext.chart/render.js' );

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

describe( 'render with resize listener', () => {
	let chartElement;

	beforeEach( () => {
		jest.clearAllMocks();
		chartElement = document.createElement( 'wiki-chart' );
		chartElement.lang = 'en';
		chartElement.innerHTML = '<svg height="100"></svg>';
		Object.defineProperty( chartElement, 'clientWidth', {
			configurable: true,
			value: 100
		} );
		Object.defineProperty( chartElement, 'clientHeight', {
			configurable: true,
			value: 100
		} );
		document.body.appendChild( chartElement );
	} );

	afterEach( () => {
		chartElement.remove();
	} );

	it( 'removes resize listeners for detached charts', () => {
		render( chartElement, {
			spec: {
				series: []
			}
		} );

		expect( echarts.init ).toHaveBeenCalled();
		chartElement.remove();
		window.dispatchEvent( new Event( 'resize' ) );

		expect( mockChart.resize ).not.toHaveBeenCalled();
		expect( mockChart.dispose ).toHaveBeenCalled();
	} );

	it( 'does not resize connected charts with zero dimensions', () => {
		render( chartElement, {
			spec: {
				series: []
			}
		} );

		Object.defineProperty( chartElement, 'clientWidth', {
			configurable: true,
			value: 0
		} );
		window.dispatchEvent( new Event( 'resize' ) );

		expect( mockChart.resize ).not.toHaveBeenCalled();
		expect( mockChart.dispose ).not.toHaveBeenCalled();
	} );
} );
