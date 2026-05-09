<template>
	<div class="ext-chart-wizard__preview">
		<div class="ext-chart-wizard__preview-placeholder">
			<div class="ext-chart-wizard__preview-placeholder-image"></div>
			<div class="ext-chart-wizard__preview-placeholder-header">
				{{ $i18n( 'chart-wizard-preview-placeholder-title' ).text() }}
			</div>
			<div>
				{{ $i18n( 'chart-wizard-preview-placeholder-description' ).text() }}
			</div>
		</div>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );

module.exports = exports = defineComponent( {
	name: 'ChartPreviewArea',
	components: {},
	setup() {}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-chart-wizard__preview {
	border: @border-subtle;

	pre {
		border: none;
		margin: 0;
	}
}
.ext-chart-wizard__preview-placeholder {
	text-align: center;
	padding: @spacing-200 0;

	&-header {
		font-size: @font-size-large;
		font-weight: @font-weight-bold;
		padding-top: @spacing-200;
		padding-bottom: @spacing-25;
	}

	&-image {
		background-image: url( ../images/PreviewArea_EmptyState.png );
		background-repeat: no-repeat;
		background-size: auto 96px;
		background-position: center;
		height: 96px;
	}
}

</style>
