'use strict';

const { defineConfig, globalIgnores } = require( 'eslint/config' );
const wikimedia = require( 'eslint-config-wikimedia/flat' );

// Directories with their own config, which other configs don't apply to.
// These were directories with `"root": true` in .eslintrc.json.
const rootDirs = {
	resources: [ 'resources/**' ],
	tests: [ 'tests/jest/**' ]
};

// Shared by resources and tests (was resources/.eslintrc.json)
const resourcesConfig = [
	wikimedia( 'client', 'mediawiki', 'vue3/es2017' ),
	{
		languageOptions: {
			sourceType: 'module',
			globals: {
				exports: 'writable'
			}
		},
		settings: {
			jsdoc: {
				preferredTypes: {
					ChipInputItem: 'ChipInputItem',
					MenuGroupData: 'MenuGroupData',
					MenuItemData: 'MenuItemData',
					MenuItemValue: 'MenuItemValue'
				}
			}
		}
	}
];

module.exports = defineConfig( [
	globalIgnores( [
		// Upstream code
		'chart-renderer/',
		'cli/',
		'lib/',

		// Build
		'coverage/',
		'vendor/',
		'doc/',

		// Language files written automatically by TranslateWiki
		'**/i18n/**/*.json',
		'!**/i18n/**/en.json',
		'!**/i18n/**/qqq.json'
	] ),

	// jest.config.js, etc.
	{
		ignores: Object.values( rootDirs ).flat(),
		extends: [ wikimedia( 'server' ) ],
		languageOptions: {
			globals: {
				jest: 'readonly'
			}
		}
	},

	// Code which runs in MediaWiki
	{
		files: rootDirs.resources,
		extends: resourcesConfig
	},
	{
		files: [ 'resources/ext.chart.visualEditor/**' ],
		languageOptions: {
			globals: {
				ve: 'readonly'
			}
		}
	},
	{
		files: [ 'resources/ext.chart.wizard/**' ],
		// In this order, 'mediawiki' overrides some of the Vue rules from 'vue3/es2017'
		extends: [ wikimedia( 'client', 'vue3/es2017', 'mediawiki' ) ],
		languageOptions: {
			sourceType: 'module'
		}
	},

	// Jest tests
	{
		files: rootDirs.tests,
		extends: [ resourcesConfig, wikimedia( 'jest' ) ],
		languageOptions: {
			sourceType: 'module'
		}
	}
] );
