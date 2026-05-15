const { defineStore } = require( 'pinia' );
const { computed, ref, ComputedRef, Ref } = require( 'vue' );

/**
 * Pinia store for the Chart wizard.
 */
module.exports = exports = defineStore( 'chart', () => {

	/**
	 * Simplifies the Codex status/messages system.
	 * The value is either `null` ('default' status, no message),
	 * or a string which is the error message itself.
	 *
	 * @typedef {string|null} Status
	 */

	// ** State properties (refs) **

	/**
	 * Keep track of all UI-blocking API requests that are currently in flight.
	 *
	 * @type {Ref<Set<Promise|jQuery.Promise>>}
	 */
	const promises = ref( new Set() );
	/**
	 * Used to put the app in an initial loading state, as we will always need
	 * to fire off at least one API request. Without this, the disabled state of
	 * UI elements could flicker depending on how quickly the app loads.
	 *
	 * For use only by the ChartPreview component.
	 *
	 * @type {Ref<boolean>}
	 * @internal
	 */
	const initialLoad = ref( true );
	/**
	 * The source dataset used to create the chart.
	 * This is set only by the SourceField component.
	 *
	 * @type {Ref<string>}
	 */
	const source = ref( '' );
	/**
	 * Status for the source field.
	 *
	 * @type {Ref<Status>}
	 */
	const sourceStatus = ref( null );

	// ** Getters (computed properties) **

	/**
	 * The Chart definition JSON blob.
	 *
	 * @type {ComputedRef<Object>}
	 */
	const chartDefinition = computed( () => ( {
		source: source.value
	} ) );
	/**
	 * Whether the form is disabled due to an in-flight API request.
	 *
	 * @type {ComputedRef<boolean>}
	 */
	const formDisabled = computed( () => !!promises.value.size || initialLoad.value );

	// ** Actions (exported functions) **

	/**
	 * Add a promise to the `Set` of pending promises.
	 * This is used solely to disable the form while waiting for a response,
	 * and should only be used for requests that need to block UI interaction.
	 * The promise will be removed from the Set when it resolves, and
	 * once the Set is empty, the form will be re-enabled.
	 *
	 * @param {Promise|jQuery.Promise} promise
	 * @return {Promise|jQuery.Promise} The same unresolved promise that was passed in.
	 */
	function pushPromise( promise ) {
		promises.value.add( promise );
		// Can't use .finally() because it's not supported in jQuery.
		promise.then(
			() => promises.value.delete( promise ),
			() => promises.value.delete( promise )
		);
		return promise;
	}

	return {
		initialLoad,
		source,
		sourceStatus,
		chartDefinition,
		formDisabled,
		pushPromise
	};
} );
