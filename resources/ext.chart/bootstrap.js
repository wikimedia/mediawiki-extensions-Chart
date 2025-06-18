const trustedCharts = Array.from( document.querySelectorAll( 'wiki-chart' ) );

class WikiChart extends HTMLElement {
	constructor() {
		super();
		this.trusted = trustedCharts.includes( this ) && (
			// FIXME: `data-mw` is for cached HTML (T395462)
			this.hasAttribute( 'data-mw-chart' ) || this.hasAttribute( 'data-mw' )
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
							if ( this.dataset.mwChart !== undefined && this.dataset.mwChart !== 'true' ) {
								chartData = JSON.parse( this.dataset.mwChart );
							} else {
								// Backward compatibility: older content uses data-chart
								// rather than data-mw-chart
								chartData = JSON.parse( this.dataset.chart );
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
						const renderStartTime = mw.now();
						mw.loader.using( 'ext.chart.render' ).then( ( req ) => {
							const eChartsStartTime = mw.now();
							req( 'ext.chart.render' ).render( this, chartData );
							mw.track( 'stats.mediawiki_Chart_render_end_total', 1, { type } );
							mw.track( 'stats.mediawiki_Chart_render_time_seconds', mw.now() - renderStartTime );
							mw.track( 'stats.mediawiki_Chart_echarts_time_seconds', mw.now() - eChartsStartTime );
						} ).catch( ( error ) => {
							this.classList.add( 'ext-chart-fallback' );
							mw.errorLogger.logError( error, 'error.charts' );
							mw.track( 'stats.mediawiki_Chart_render_failure_total', 1, { type } );
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
