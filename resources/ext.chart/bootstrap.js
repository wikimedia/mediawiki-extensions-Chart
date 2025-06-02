const trustedCharts = Array.from( document.querySelectorAll( 'wiki-chart' ) );

class WikiChart extends HTMLElement {
	constructor() {
		super();
		this.trusted = trustedCharts.includes( this ) && (
			// FIXME: `data-mw` is for cached HTML (T395462)
			this.hasAttribute( 'data-mw-charts' ) || this.hasAttribute( 'data-mw' )
		);
		this.visible = false;
	}

	connectedCallback() {
		if ( this.trusted && !this.visible ) {
			const intersectionObserver = new IntersectionObserver(
				( entries ) => {
					if ( entries.length && entries[ 0 ].isIntersecting ) {
						this.visible = true;
						intersectionObserver.disconnect();

						let chartData = {};
						let type = 'unknown';
						try {
							if ( this.dataset.chart !== undefined ) {
								chartData = JSON.parse( this.dataset.chart );
							} else {
								// Backward compatibility: older content contains separate data
								// attributes instead of a single data-chart attribute, and these
								// are URL-encoded. This code can be removed once this older content
								// has expired from the parser cache.
								chartData = {
									spec: this.dataset.spec === undefined ? undefined :
										JSON.parse( decodeURIComponent( this.dataset.spec ) ),
									theme: this.dataset.theme === undefined ? undefined :
										JSON.parse( decodeURIComponent( this.dataset.theme ) ),
									xAxisType: this.dataset.xAxisType,
									yAxisType: this.dataset.yAxisType
								};
							}
							type = chartData.spec.series[ 0 ].type;
						} catch ( e ) {
							// ignore.
							mw.errorLogger.logError(
								new Error( 'Unable to read data from data-chart or data-spec attribute' ),
								'error.charts'
							);
							return;
						}
						mw.track( `counter.MediaWiki.extensions.Chart.${ type }.renderStart`, 1 );
						mw.track( 'stats.mediawiki_Chart_render_start_total', 1, { type } );
						mw.loader.using( 'ext.chart.render' ).then( ( req ) => {
							req( 'ext.chart.render' ).render( this, chartData );
							mw.track( `counter.MediaWiki.extensions.Chart.${ type }.renderEnd`, 1 );
							mw.track( 'stats.mediawiki_Chart_render_end_total', 1, { type } );
						} );
					}
				}
			);
			intersectionObserver.observe( this );
		}
	}
}

if ( !customElements.get( 'wiki-chart' ) ) {
	customElements.define( 'wiki-chart', WikiChart );
}
