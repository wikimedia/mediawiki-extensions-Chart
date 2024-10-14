const echarts = require( '../../lib/echarts/echarts.common.js' );

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
	});
	// https://github.com/apache/echarts/blob/release/src/i18n/langAR.ts
	// update spec
	spec.tooltip = {
		valueFormatter: ( value ) => formatter.format(value),
		trigger: 'axis'
	};
	if ( spec.yAxis ) {
		spec.yAxis.axisLabel = {
			formatter: ( value ) => formatter.format( value )
		};
	}
	if ( spec.legend ) {
		Object.assign( spec.legend, {
			[isRTL ? 'right' : 'left']: 0,
			type: 'scroll',
			align: isRTL ? 'right' : 'left'
		} );
	}
	chart.setOption( spec );
	originalSVG.parentNode.removeChild( originalSVG );
	window.addEventListener( 'resize', chart.resize );
};

const render = ( wikiChartElement ) => {
	const theme = wikiChartElement.dataset.theme;
	const spec = wikiChartElement.dataset.spec;
	if ( !spec || !theme ) {
		return;
	}
	renderInNode(
		wikiChartElement,
		JSON.parse( decodeURIComponent( spec ) ),
		JSON.parse( decodeURIComponent( theme ) )
	);
};

module.exports = {
	render
};
