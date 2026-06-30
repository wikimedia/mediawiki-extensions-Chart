<template>
	<cdx-field
		class="ext-chart-wizard__categories"
	>
		<cdx-multiselect-lookup
			v-model:input-chips="inputChips"
			v-model:selected="selection"
			v-model:input-value="inputValue"
			:menu-items="menuItems"
			:menu-config="menuConfig"
			:placeholder="$i18n( 'chart-wizard-form-categories-placeholder' ).text()"
			:aria-label="$i18n( 'chart-wizard-form-categories-placeholder' ).text()"
			@input="onInput"
			@blur="onBlur"
			@update:selected="onUpdateSelected"
		>
			<template #no-results>
				{{ $i18n( 'chart-wizard-form-categories-no-results' ).text() }}
			</template>
		</cdx-multiselect-lookup>
		<template #label>
			{{ $i18n( 'chart-wizard-form-categories' ).text() }}
		</template>
	</cdx-field>
</template>

<script>
const { defineComponent, ref, Ref } = require( 'vue' );
const { storeToRefs } = require( 'pinia' );
const { CdxField, CdxMultiselectLookup } = require( '../../../codex.js' );
const useChartStore = require( '../stores/chart.js' );
const api = new mw.Api();
const categoryNsId = mw.config.get( 'wgNamespaceIds' ).category;

module.exports = exports = defineComponent( {
	name: 'CategoriesField',
	components: { CdxField, CdxMultiselectLookup },
	setup() {
		const { mediawikiCategories } = storeToRefs( useChartStore() );

		/**
		 * Array of chips to display in the input field.
		 *
		 * @type {Ref<ChipInputItem[]>}
		 */
		const inputChips = ref( mediawikiCategories.value.map(
			( category ) => ( {
				label: category.name,
				value: category.name
			} ) )
		);

		/**
		 * Array of selected values.
		 *
		 * @type {Ref<string[]>}
		 */
		const selection = ref( mediawikiCategories.value.map( ( category ) => category.name ) );

		/**
		 * Current input value.
		 *
		 * @type {Ref<string>}
		 */
		const inputValue = ref( '' );

		/**
		 * Menu items to display in the dropdown.
		 *
		 * @type {Ref<MenuItemData[]>}
		 */
		const menuItems = ref( [] );

		const menuConfig = { visibleItemLimit: 10 };

		/**
		 * Get search results.
		 *
		 * @param {string} searchTerm
		 * @return {Promise}
		 */
		async function fetchResults( searchTerm ) {
			const response = await api.get( {
				action: 'query',
				list: 'allpages',
				apprefix: searchTerm,
				apnamespace: categoryNsId,
				apfilterredir: 'nonredirects',
				aplimit: '10',
				format: 'json',
				formatversion: 2
			} );
			return ( response.query && response.query.allpages || [] ).map(
				( { title } ) => mw.Title.newFromText( title, categoryNsId ).getMainText()
			);
		}

		/**
		 * Handle lookup input.
		 *
		 * @param {string} value
		 */
		async function onInput( value ) {
			// Abort any existing requests.
			api.abort();

			// Clear menu items if the input was cleared.
			if ( !value ) {
				menuItems.value = [];
				return;
			}

			try {
				const data = await fetchResults( value );

				// Make sure this data is still relevant first.
				if ( inputValue.value !== value ) {
					return;
				}

				// Reset the menu items if there are no results.
				if ( !data || data.length === 0 ) {
					menuItems.value = [];
					return;
				}

				// Build an array of menu items.
				menuItems.value = data.map( ( result ) => ( {
					label: result,
					value: result
				} ) );
			} catch ( e ) {
				// On error, set results to empty.
				if ( inputValue.value === value ) {
					menuItems.value = [];
				}
			}
		}

		/**
		 * Clear the input value when the field loses focus.
		 */
		async function onBlur() {
			inputValue.value = '';
		}

		/**
		 * Update the categories in the store when the selection changes.
		 *
		 * @param {MenuItemValue[]} items
		 */
		function onUpdateSelected( items ) {
			mediawikiCategories.value = items.map( ( item ) => {
				const category = { name: item };
				// Retain sort keys if already present.
				const prevCat = mediawikiCategories.value.find( ( cat ) => cat.name === item );
				if ( prevCat && prevCat.sort ) {
					category.sort = prevCat.sort;
				}
				return category;
			} );
		}

		return {
			inputChips,
			inputValue,
			menuItems,
			menuConfig,
			selection,
			onInput,
			onBlur,
			onUpdateSelected
		};
	}
} );
</script>
