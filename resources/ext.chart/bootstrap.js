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
						mw.loader.using( 'ext.chart.render' ).then( ( req ) => {
							req( 'ext.chart.render' ).render( this );
						} );
						intersectionObserver.disconnect();
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
