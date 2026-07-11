'use strict';

const vueConfig = require( '@vue/test-utils' ).config;
global.mw = require( '@wikimedia/mw-node-qunit/src/mockMediaWiki.js' )();

/**
 * Mock for the calls to Core's $i18n plugin which returns a mw.Message object.
 *
 * @param {string} key The key of the message to parse.
 * @param {...*} args Arbitrary number of arguments to be parsed.
 * @return {Object} mw.Message-like object with .text() and .parse() methods.
 */
function $i18nMock( key, ...args ) {
	function serializeArgs() {
		return args.length ? `${ key }:[${ args.join( ',' ) }]` : key;
	}
	return {
		text: () => serializeArgs(),
		parse: () => serializeArgs()
	};
}
// Mock Vue plugins in test suites.
vueConfig.global.provide = {
	i18n: $i18nMock
};
vueConfig.global.mocks = {
	$i18n: $i18nMock
};
vueConfig.global.directives = {
	'i18n-html': ( el, binding ) => {
		el.innerHTML = `${ binding.arg } (${ binding.value })`;
	}
};

global.mw.Api.prototype.get = jest.fn().mockReturnValue( Promise.resolve( {} ) );
global.mw.Api.prototype.abort = jest.fn();
