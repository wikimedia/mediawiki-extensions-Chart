/**
 * Debounce work that should also be canceled when its request is superseded.
 *
 * @param {Function} callback
 * @param {number} delay
 * @return {Function}
 */
function createCancelableDebounce( callback, delay ) {
	let timeout;
	const debounced = ( ...args ) => {
		clearTimeout( timeout );
		timeout = setTimeout( () => {
			timeout = undefined;
			callback( ...args );
		}, delay );
	};
	debounced.cancel = () => {
		clearTimeout( timeout );
		timeout = undefined;
	};
	return debounced;
}

/**
 * Track requests so superseded responses can be ignored.
 *
 * @param {mw.Api} api
 * @param {Function} cancelPending Cancel any work scheduled before the API request.
 * @return {{supersede: Function, isLatest: Function}}
 */
function createRequestController( api, cancelPending = () => {} ) {
	let latestRequestId = 0;
	return {
		supersede() {
			cancelPending();
			api.abort();
			return ++latestRequestId;
		},
		isLatest( requestId ) {
			return requestId === latestRequestId;
		}
	};
}

module.exports = {
	createCancelableDebounce,
	createRequestController
};
