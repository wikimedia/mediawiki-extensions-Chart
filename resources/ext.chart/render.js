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
	const formatter = new Intl.NumberFormat( language, {
		style: 'decimal',
		minimumFractionDigits: 2,
		maximumFractionDigits: 2
	} );
	// https://github.com/apache/echarts/blob/release/src/i18n/langAR.ts
	// update spec
	spec.tooltip = {
		valueFormatter: ( value ) => formatter.format( value ),
		trigger: 'axis'
	};

	if ( spec.title && spec.title.textStyle ) {
		spec.title.textStyle.width = chart.getWidth();
	}

	const dateFormatter = new Intl.DateTimeFormat( language );
	const formatAsDate = ( value ) => dateFormatter.format( new Date( value ) );
	const formatAsStringOrNumber = ( value ) => typeof value === 'string' ? value : formatter.format( value );

	if ( spec.yAxis ) {
		spec.yAxis.axisLabel = {
			formatter: formatAsStringOrNumber
		};
	}

	if ( spec.xAxis ) {
		spec.xAxis.axisLabel = {
			formatter: isDateSeries( spec.xAxis.data || [] ) ?
				formatAsDate : formatAsStringOrNumber
		};
	}
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
	isDateSeries,
	render
};
