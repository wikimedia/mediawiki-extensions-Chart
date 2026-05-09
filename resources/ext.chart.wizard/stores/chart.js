const { defineStore } = require( 'pinia' );
const { ref, Ref } = require( 'vue' );

/**
 * Pinia store for the Chart visual mode.
 */
module.exports = exports = defineStore( 'chart', () => {
	/**
	 * The source dataset used to create the chart.
	 * This is set only by the SourceField component.
	 *
	 * @type {Ref<string>}
	 */
	const source = ref( '' );

	return {
		source
	};
} );
