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
	/**
	 * Translations of a string in form of an object, keyed by language code.
	 *
	 * @typedef {Object} LocalizableString
	 */
	/**
	 * Axis format mode.
	 *
	 * @typedef {'auto'|'none'} AxisFormat
	 */
	/**
	 * A MediaWiki category with optional `sort` property.
	 *
	 * @typedef {Object} Category
	 * @property {string} name
	 * @property {string} [sort]
	 */
	/**
	 * An axis configuration for a chart.
	 *
	 * @typedef {Object} Axis
	 * @property {LocalizableString} title
	 * @property {AxisFormat} [format]
	 */
	/**
	 * Lua transformation to apply to the chart data.
	 *
	 * @typedef {Object} LuaTransform
	 * @property {string} module Page name of the Lua module, without the namespace.
	 * @property {string} function The name of Lua function to call
	 * @property {Object} args Key-value pairs of arguments to pass to the Lua function.
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

	// Chart definition fields.

	/**
	 * The license for the chart.
	 *
	 * @type {Ref<string>}
	 */
	const license = ref( '' );
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
	/**
	 * Categories the chart should be added to.
	 *
	 * @type {Ref<Array<Category>>}
	 */
	const mediawikiCategories = ref( [] );
	/**
	 * The title of the chart as a localizable string.
	 *
	 * @type {Ref<LocalizableString>}
	 */
	const title = ref( {} );
	/**
	 * The subtitle of the chart as a localizable string.
	 *
	 * @type {Ref<LocalizableString>}
	 */
	const subtitle = ref( {} );
	/**
	 * The chart type. Possible values are hard-coded in the TypeField component.
	 *
	 * @type {Ref<'line'|'pie'|'bar'|'area'>}
	 */
	const type = ref( '' );
	/**
	 * X-axis configuration.
	 *
	 * @type {Ref<Axis>}
	 */
	const xAxis = ref( {
		title: '',
		format: 'none'
	} );
	/**
	 * Y-axis configuration.
	 *
	 * @type {Ref<Axis>}
	 */
	const yAxis = ref( {
		title: '',
		format: 'none'
	} );
	/**
	 * The transform configuration for the chart.
	 *
	 * @type {Ref<LuaTransform>}
	 */
	const transform = ref( {
		module: '',
		function: '',
		args: {}
	} );

	// ** Getters (computed properties) **

	/**
	 * The Chart definition JSON blob.
	 *
	 * @type {ComputedRef<Object>}
	 */
	const chartDefinition = computed( () => ( {
		version: 1,
		license: license.value,
		source: source.value,
		mediawikiCategories: mediawikiCategories.value,
		title: title.value,
		subtitle: subtitle.value,
		type: type.value,
		xAxis: xAxis.value,
		yAxis: yAxis.value,
		transform: transform.value
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
		license,
		source,
		mediawikiCategories,
		title,
		subtitle,
		type,
		xAxis,
		yAxis,
		transform,
		sourceStatus,
		chartDefinition,
		formDisabled,
		pushPromise
	};
} );
