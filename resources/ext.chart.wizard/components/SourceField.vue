<template>
	<cdx-field
		class="ext-chart-wizard__source"
		:is-fieldset="true"
		:status="sourceStatus ? 'error' : 'default'"
		:messages="sourceStatus ? { error: sourceStatus } : {}"
	>
		<cdx-lookup
			ref="sourceLookup"
			v-model:selected="selection"
			v-model:input-value="currentSearchTerm"
			name="source"
			required
			:menu-items="menuItems"
			:menu-config="menuConfig"
			:placeholder="$i18n( 'chart-wizard-form-source-placeholder' ).text()"
			:clearable="true"
			@input="onInput"
			@change="onChange"
			@clear="onChange"
			@update:selected="onSelect"
		>
		</cdx-lookup>
		<template #label>
			{{ $i18n( 'chart-wizard-form-source-label' ).text() }}
		</template>
		<template #description>
			{{ $i18n( 'chart-wizard-form-source-description' ).text() }}
		</template>
	</cdx-field>
</template>

<script>
const { defineComponent, ref, useTemplateRef, watch } = require( 'vue' );
const { CdxField, CdxLookup } = require( '../../../codex.js' );
const { storeToRefs } = require( 'pinia' );
const useChartStore = require( '../stores/chart.js' );
const api = new mw.Api();

module.exports = exports = defineComponent( {
	name: 'SourceField',
	components: { CdxField, CdxLookup },
	setup() {
		const store = useChartStore();
		const { source, sourceStatus } = storeToRefs( store );
		const tabularNs = mw.config.get( 'wgNamespaceIds' ).data;
		const sourceLookup = useTemplateRef( 'sourceLookup' );

		// Selected item, defaulting to null.
		const selection = ref( source.value );
		// Current input value. This is helpful to track so we can fetch results for the current
		// search term, and is bound to the Lookup via v-model.
		// Note that, on selection, the input updates to match the selected item.
		const currentSearchTerm = ref( source.value );
		// Menu items to show. On input, results will be fetched and provided as menu items. When
		// the input is cleared, the menu items will be reset to an empty array.
		const menuItems = ref( [] );
		// Limit the height of the menu and enable scrolling.
		const menuConfig = {
			visibleItemLimit: 6
		};

		// Sync validity to the HTMLInputElement so it bubbles up to the <form>.
		watch( sourceStatus, ( newStatus ) => {
			sourceLookup.value.textInput.setCustomValidity( newStatus || '' );
		} );

		/**
		 * Get search results.
		 *
		 * @param {string} searchTerm
		 * @return {Promise}
		 */
		async function fetchSourcePages( searchTerm ) {
			const params = {
				action: 'query',
				generator: 'prefixsearch',
				gpssearch: searchTerm,
				gpsnamespace: tabularNs,
				gpslimit: 10,
				prop: 'info',
				formatversion: 2
			};
			const response = await api.get( params );
			const pages = response.query && response.query.pages || [];
			return pages.filter( ( p ) => p.contentmodel === 'Tabular.JsonConfig' );
		}

		/**
		 * Handle lookup input.
		 *
		 * @param {string} value
		 * @return {Promise}
		 */
		async function onInput( value ) {
			// Abort any existing requests.
			api.abort();

			// Internally track the current search term.
			currentSearchTerm.value = value;

			// Do nothing if we have no input.
			if ( !value ) {
				menuItems.value = [];
				return;
			}

			try {
				const data = await fetchSourcePages( value );

				// Make sure this data is still relevant first.
				if ( currentSearchTerm.value !== value ) {
					return;
				}

				// Reset the menu items if there are no results.
				if ( !data || data.length === 0 ) {
					menuItems.value = [];
					return;
				}

				// Build an array of menu items.
				menuItems.value = data.map( ( result ) => ( {
					label: result.title,
					value: result.title
				} ) );
			} catch ( e ) {
				// On error, set results to empty.
				if ( currentSearchTerm.value === value ) {
					menuItems.value = [];
				}
			}
		}

		/**
		 * Handle lookup change.
		 */
		function onChange() {
			// Use the currentSearchTerm value instead of the event target value,
			// since the event can be fired before the watcher updates the value.
			setSource( currentSearchTerm.value );
		}

		/**
		 * Handle lookup selection.
		 */
		function onSelect() {
			if ( selection.value !== null ) {
				currentSearchTerm.value = selection.value;
				setSource( selection.value );
			}
		}

		/**
		 * Set the source dataset.
		 *
		 * @param {string} value
		 */
		function setSource( value ) {
			source.value = value;
			onInput( value );
		}

		return {
			currentSearchTerm,
			selection,
			menuItems,
			menuConfig,
			sourceStatus,
			onInput,
			onChange,
			onSelect
		};
	}
} );
</script>
