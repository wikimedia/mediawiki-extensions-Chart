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
 * Check if the date series looks like a series of dates.
 * Returns false if one of the series cannot be interpreted as a date.
 *
 * @param {string[]} dataSeries
 * @return {boolean}
 */
const isDateSeries = ( dataSeries ) => dataSeries.filter( ( item ) => {
	// If it's not a string and it's not 10 characters long do not interpret.
	// e.g. 2024-02-20 is a date.
	if ( typeof item !== 'string' || item.length !== 10 ) {
		return true;
	}
	const d = new Date( item[ 0 ] );
	return isNaN( d );
} ).length === 0;

/**
 * Creates a number formatter.
 *
 * @param {string} language
 * @return {Function}
 */
const numberFormatter = ( language ) => ( value ) => {
	const isFraction = value < 0;
	const decimals = value < 1000 ? 2 : 0;
	const formatter = new Intl.NumberFormat( language, {
		style: 'decimal',
		notation: value > 1000 ? 'compact' : 'standard',
		compactDisplay: 'short',
		minimumFractionDigits: 0,
		maximumFractionDigits: isFraction ? 3 : decimals
	} );
	return formatter.format( value );
};

/**
 * Check if the data series looks like a series of numbers.
 * Returns false if one of the series cannot be interpreted as a date.
 *
 * @param {any[]} dataSeries
 * @return {boolean}
 */
const isNumberSeries = ( dataSeries ) => dataSeries
	.filter( ( item ) => typeof item !== 'number' ).length === 0;

/**
 * Infers the correct formatter based on the data series.
 *
 * @param {string[]} dataSeries
 * @param {string} language
 * @return {Function}
 */
const getFormatter = ( dataSeries, language ) => {
	const dateFormatter = new Intl.DateTimeFormat( language );
	const formatAsDate = ( value ) => dateFormatter.format( new Date( value ) );
	const formatAsString = ( value ) => value;

	if ( isDateSeries( dataSeries ) ) {
		return formatAsDate;
	} else if ( isNumberSeries( dataSeries ) ) {
		return numberFormatter(
			language
		);
	} else {
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
 */
const renderInNode = ( wikiChartElement, spec, theme ) => {
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
		yFormatter = getFormatter( spec.series.length ? spec.series[ 0 ].data || [] : [], language );
		spec.yAxis.axisLabel = {
			formatter: yFormatter
		};
	}

	if ( spec.xAxis ) {
		xFormatter = getFormatter( spec.xAxis.data || [], language );
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
 */
const render = ( wikiChartElement, spec ) => {
	const theme = wikiChartElement.dataset.theme;
	if ( !theme ) {
		return;
	}
	renderInNode(
		wikiChartElement,
		spec,
		JSON.parse( decodeURIComponent( theme ) )
	);
};

module.exports = {
	numberFormatter,
	isDateSeries,
	getFormatter,
	render
};
