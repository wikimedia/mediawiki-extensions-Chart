// @ts-ignore
const echarts = require( '../../lib/echarts/echarts.common.js' );

const adjustTitleWidth = ( chart ) => {
	const option = chart.getOption();
	if ( option.title && option.title[ 0 ].textStyle ) {
		option.title[ 0 ].textStyle.width = chart.getWidth();
		chart.setOption( option );
	}
};

/**
 * Creates a number formatter.
 *
 * @param {string} language
 * @return {Function}
 */
const numberFormatter = ( language ) => ( value ) => {
	const isFraction = value < 0;
	const decimals = value < 100 ? 2 : 0;
	const formatter = new Intl.NumberFormat( language, {
		style: 'decimal',
		notation: value >= 1000 ? 'compact' : 'standard',
		compactDisplay: 'short',
		minimumFractionDigits: 0,
		maximumFractionDigits: isFraction ? 3 : decimals
	} );
	return formatter.format( value );
};

/**
 * @typedef {'float'|'integer'|'date'|'string'|string} ChartAxisType
 */

/**
 * Infers the correct formatter based on the data series.
 *
 * @param {string} type
 * @param {string} language
 * @return {Function}
 */
const getFormatterForType = ( type, language ) => {
	const dateFormatter = new Intl.DateTimeFormat( language );
	const formatAsDate = ( /** @type {string} */ value ) => dateFormatter.format( new Date( value ) );
	const formatAsString = ( /** @type {string} */ value ) => value;

	switch ( type ) {
		case 'number':
		case 'integer':
		case 'float':
			return numberFormatter(
				language
			);
		case 'date':
			return formatAsDate;
		default:
			return formatAsString;
	}
};

/**
 * Adds tooltip functionality to chart.
 *
 * @param {Object} spec
 * @param {Function} [xFormatter]
 * @param {Function} [yFormatter]
 * @return {Object}
 */
const specWithTooltip = ( spec, xFormatter, yFormatter ) => Object.assign( {}, spec, {
	tooltip: {
		axisPointer: {
			label: {
				formatter: ( axis ) => xFormatter( axis.value )
			}
		},
		valueFormatter: yFormatter,
		trigger: 'axis'
	}
} );

/**
 * @param {HTMLElement} wikiChartElement
 * @param {Object} spec for rendering the chart
 * @param {Object} theme the theme to use.
 * @param {ChartAxisType} xAxisType
 * @param {ChartAxisType} yAxisType
 */
const renderInNode = ( wikiChartElement, spec, theme, xAxisType, yAxisType ) => {
	const language = mw.config.get( 'wgUserLanguage' );
	const height = wikiChartElement.clientHeight;
	const locale = new Intl.Locale( language );
	// Note: Only available in modern browsers, older browsers will fall back to LTR.
	// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Intl/Locale/getTextInfo
	const isRTL = locale && locale.textInfo && locale.textInfo.direction === 'rtl';
	const originalSVG = wikiChartElement.querySelector( 'svg' );
	const chart = echarts.init( wikiChartElement, theme, {
		renderer: 'svg',
		height
	} );
	let xFormatter, yFormatter;

	if ( spec.title && spec.title.textStyle ) {
		spec.title.textStyle.width = chart.getWidth();
	}

	if ( spec.yAxis ) {
		yFormatter = getFormatterForType( yAxisType, language );
		spec.yAxis.axisLabel = {
			formatter: yFormatter
		};
	}

	if ( spec.xAxis ) {
		xFormatter = getFormatterForType( xAxisType, language );
		spec.xAxis.axisLabel = {
			formatter: xFormatter
		};
	}
	spec = specWithTooltip( spec, xFormatter, yFormatter );

	if ( spec.legend ) {
		Object.assign( spec.legend, {
			[ isRTL ? 'right' : 'left' ]: 0,
			type: 'scroll',
			align: isRTL ? 'right' : 'left'
		} );
	}
	chart.setOption( spec );
	originalSVG.parentNode.removeChild( originalSVG );
	window.addEventListener( 'resize', () => {
		chart.resize();
		adjustTitleWidth( chart );
	} );
};

/**
 * @param {HTMLElement} wikiChartElement
 * @param {Object} spec
 * @param {Object} theme the theme to use.
 * @param {ChartAxisType} xAxisType
 * @param {ChartAxisType} yAxisType
 */
const render = ( wikiChartElement, spec, theme, xAxisType, yAxisType ) => {
	renderInNode(
		wikiChartElement,
		spec,
		theme,
		xAxisType,
		yAxisType
	);
};

module.exports = {
	numberFormatter,
	getFormatterForType,
	render
};
