const trustedCharts = Array.from( document.querySelectorAll( 'wiki-chart' ) );
class WikiChart extends HTMLElement {
	constructor() {
		super();
		this.trusted = trustedCharts.includes( this ) && this.hasAttribute( 'data-mw' );
		this.visible = false;
	}

	connectedCallback() {
		if ( this.trusted && !this.visible ) {
			const intersectionObserver = new IntersectionObserver(
				( entries ) => {
					if ( entries.length && entries[ 0 ].isIntersecting ) {
						this.visible = true;
						const spec = this.dataset.spec;
						let type = 'unknown';
						let specJSON = {};
						intersectionObserver.disconnect();

						let theme = this.dataset.theme;
						const xAxisType = this.dataset.xAxisType;
						const yAxisType = this.dataset.yAxisType;
						if ( spec && theme && xAxisType && yAxisType ) {
							try {
								specJSON = JSON.parse( decodeURIComponent( spec ) );
								theme = JSON.parse( decodeURIComponent( theme ) );
								type = specJSON.series[ 0 ].type;
							} catch ( e ) {
								// ignore.
								mw.errorLogger.logError(
									new Error( 'Unable to read data from spec data attribute' ),
									'error.charts'
								);
								return;
							}
							mw.track( `counter.MediaWiki.extensions.Chart.${ type }.renderStart`, 1 );
							mw.loader.using( 'ext.chart.render' ).then( ( req ) => {
								req( 'ext.chart.render' ).render(
									this,
									specJSON,
									theme,
									xAxisType,
									yAxisType
								);
								mw.track( `counter.MediaWiki.extensions.Chart.${ type }.renderEnd`, 1 );
							} );
						}
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
