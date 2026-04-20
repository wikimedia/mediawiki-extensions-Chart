const { defineStore } = require( 'pinia' );
const { ref, Ref } = require( 'vue' );

/**
 * Pinia store for the Chart visual mode.
 */
module.exports = exports = defineStore( 'chart', () => {
	/**
	 * The dataset used to create the chart.
	 * This is set only by the DatasetLookupField component.
	 *
	 * @type {Ref<string>}
	 */
	const dataset = ref( '' );

	return {
		dataset
	};
} );
