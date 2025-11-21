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
 * Check if value has data
 *
 * @param {number|string} value
 * @return {boolean}
 */
const hasData = ( value ) => {
	if ( typeof value === 'undefined' || value === null ) {
		return false;
	}
	return true;
};

/**
 * In RTL languages, titles that begin with a number can be reordered by the
 * browser's bidi algorithm so that the number appears on the opposite side
 * of the text (i.e. "… 15" rendered as "15 …"). See T401719.
 *
 * To keep the visual order matching the logical one, these titles can be
 * wrapped in an explicit RTL embedding using Unicode bidi control characters.
 *
 * Note this is a client-side only. The static SVG rendered on the
 * server will continue to use the unmodified title text.
 *
 * @param {Object} spec
 * @param {boolean} isRTL
 */
const fixBidiTitle = ( spec, isRTL ) => {
	if ( !isRTL || !spec || !spec.title ) {
		return;
	}

	/**
	 * @param {string} text
	 * @return {string}
	 */
	const wrapIfNeeded = ( text ) => {
		if ( typeof text !== 'string' ) {
			return text;
		}

		// Only adjust titles that start with an ASCII digit.
		if ( !/^[0-9]/.test( text ) ) {
			return text;
		}

		// Right-to-Left Embedding
		const RLE = '\u202B';
		// Pop directional formatting
		const PDF = '\u202C';

		// Avoid double wrapping if someone has already added control chars.
		if ( text.charAt( 0 ) === RLE ) {
			return text;
		}

		return RLE + text + PDF;
	};

	if ( Object.prototype.hasOwnProperty.call( spec.title, 'text' ) ) {
		spec.title.text = wrapIfNeeded( spec.title.text );
	}

	if ( Object.prototype.hasOwnProperty.call( spec.title, 'subtext' ) ) {
		spec.title.subtext = wrapIfNeeded( spec.title.subtext );
	}
};

/**
 * Creates a number formatter.
 * Uses basic formatting for the 'none' format option.
 * (no thousands separator, no compact notation)
 *
 * @param {string} language
 * @param {string} formatMode
 * @return {Function}
 */
const numberFormatter = ( language, formatMode ) => ( value ) => {
	if ( !hasData( value ) ) {
		return mw.message( 'chart-render-tooltip-nodata' );
	}
	let formatter;
	if ( formatMode === 'none' ) {
		formatter = new Intl.NumberFormat( language, {
			// Display grouping separators when there are at least 2 digits in a group.
			useGrouping: 'min2',
			maximumFractionDigits: 13
		} );
	} else {
		const isFraction = value < 0;
		const decimals = value < 100 ? 2 : 0;
		formatter = new Intl.NumberFormat( language, {
			style: 'decimal',
			notation: value >= 1000 ? 'compact' : 'standard',
			compactDisplay: 'short',
			minimumFractionDigits: 0,
			maximumFractionDigits: isFraction ? 3 : decimals
		} );
	}
	return formatter.format( value );
};

const getPercentFormatter = ( language ) => ( value ) => {
	if ( !hasData( value ) ) {
		return mw.message( 'chart-render-tooltip-nodata' );
	}
	const formatter = new Intl.NumberFormat( language, {
		style: 'percent',
		minimumFractionDigits: 0,
		maximumFractionDigits: 2
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
 * @param {string} formatMode
 * @param {string} language
 * @return {Function}
 */
const getFormatterForType = ( type, formatMode, language ) => {
	const dateFormatter = new Intl.DateTimeFormat( language );
	const formatAsDate = ( /** @type {string} */ value ) => {
		if ( !hasData( value ) ) {
			return mw.message( 'chart-render-tooltip-nodata' );
		}
		return dateFormatter.format( new Date( value ) );
	};
	const formatAsString = ( /** @type {string} */ value ) => {
		if ( !hasData( value ) ) {
			return mw.message( 'chart-render-tooltip-nodata' );
		}
		return value;
	};

	switch ( type ) {
		case 'number':
		case 'integer':
		case 'float':
			return numberFormatter( language, formatMode );
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
 * @param {Function} xFormatter
 * @param {Function} yFormatter
 * @param {Object} locale
 * @param {string} language
 * @param {boolean} isRTL
 */
const addTooltip = ( spec, xFormatter, yFormatter, language ) => {
	if ( !spec.series || !Array.isArray( spec.series ) || !spec.series.length ) {
		return;
	}
	const tooltipSpec = {
		valueFormatter: yFormatter
	};

	if ( spec.series[ 0 ].type === 'pie' ) {
		const formatPercent = getPercentFormatter( language );
		tooltipSpec.trigger = 'item';
		tooltipSpec.formatter = ( params ) => {
			const value = yFormatter( params.value );
			const percentage = params.percent !== null ?
				` (${ formatPercent( params.percent / 100 ) })` :
				'';
			return ` ${ params.marker } ${ params.name }: ${ value }${ percentage }`;
		};
	} else {
		tooltipSpec.trigger = 'axis';
		tooltipSpec.axisPointer = {
			label: {
				formatter: ( axis ) => xFormatter( axis.value )
			}
		};
	}
	Object.assign( spec, {
		tooltip: tooltipSpec
	} );
};

/**
 * @param {HTMLElement} wikiChartElement
 * @param {Object} chartData
 */
const render = ( wikiChartElement, chartData ) => {
	// WARNING: Be careful when changing this code to handle new or changed fields in chartData
	// generated by the chart-renderer service. This code will encounter cached pages with the old
	// version of this interface for a few weeks after deployment, so it must be backwards
	// compatible (handle both the old and the new version of this interface correctly).
	// For example, when a new field is added, this code should check whether that new field is
	// present before using it.
	const {
		spec,
		theme,
		xAxisType,
		xAxisFormatMode = 'none',
		yAxisType,
		yAxisFormatMode = 'none'
	} = chartData;

	const language = wikiChartElement.lang ? wikiChartElement.lang : mw.config.get( 'wgPageViewLanguage' );
	const locale = Intl.Locale ? new Intl.Locale( language ) : null;
	// Note: Only available in modern browsers, older browsers will fall back to LTR.
	// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Intl/Locale/getTextInfo
	const isRTL =
    locale && locale.textInfo && locale.textInfo.direction === 'rtl';

	const height = wikiChartElement.clientHeight;
	const originalSVG = wikiChartElement.querySelector( 'svg' );
	const chart = echarts.init( wikiChartElement, theme, {
		renderer: 'svg',
		height
	} );

	if ( spec.title && spec.title.textStyle ) {
		spec.title.textStyle.width = chart.getWidth();
	}

	if ( spec.title && spec.title.subtextStyle ) {
		spec.title.subtextStyle.width = chart.getWidth();
	}

	const xFormatter = getFormatterForType( xAxisType, xAxisFormatMode, language );
	const yFormatter = getFormatterForType( yAxisType, yAxisFormatMode, language );

	addTooltip( spec, xFormatter, yFormatter, language );

	if ( spec.xAxis ) {
		spec.xAxis.axisLabel = {
			formatter: xFormatter
		};
	}

	if ( spec.yAxis ) {
		spec.yAxis.axisLabel = {
			formatter: yFormatter
		};
	}

	if ( spec.legend ) {
		Object.assign( spec.legend, {
			[ isRTL ? 'right' : 'left' ]: 0,
			type: 'scroll',
			align: isRTL ? 'right' : 'left'
		} );
	}

	// Ensure that on RTL pages titles that begin with a number preserve
	// their logical order. See T401719.
	fixBidiTitle( spec, isRTL );

	chart.setOption( spec );
	originalSVG.parentNode.removeChild( originalSVG );
	window.addEventListener( 'resize', () => {
		chart.resize();
		adjustTitleWidth( chart );
	} );
};

module.exports = {
	numberFormatter,
	getFormatterForType,
	render,
	fixBidiTitle
};
