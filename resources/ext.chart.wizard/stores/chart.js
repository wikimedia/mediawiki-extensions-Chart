const { defineStore } = require( 'pinia' );
const { computed, ref, ComputedRef, Ref } = require( 'vue' );

/**
 * Pinia store for the Chart wizard.
 */
module.exports = exports = defineStore( 'chart', () => {

	// ** State properties (refs) **

	/**
	 * The source dataset used to create the chart.
	 * This is set only by the SourceField component.
	 *
	 * @type {Ref<string>}
	 */
	const source = ref( '' );

	// ** Getters (computed properties) **

	/**
	 * The Chart definition JSON blob.
	 *
	 * @type {ComputedRef<Object>}
	 */
	const chartDefinition = computed( () => ( {
		source: source.value
	} ) );

	return {
		chartDefinition,
		source
	};
} );
