<template>
	<div class="ext-chart-visualmode--area__preview">
		<div class="ext-chart-visualmode--area__preview-placeholder">
			<span class="ext-chart-visualmode--area__preview-placeholder-image"></span>
			<span class="ext-chart-visualmode--area__preview-placeholder-header">
				{{ $i18n( 'chart-visualedit-tab-preview-placeholder-title' ).text() }}
			</span>
			<span>
				{{ $i18n( 'chart-visualedit-tab-preview-placeholder-description' ).text() }}
			</span>
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

.ext-chart-visualmode--area__preview {
	border: @border-base;

	pre {
		border: none;
		margin: 0;
	}
}
.ext-chart-visualmode--area__preview-placeholder {
	text-align: center;
	padding-top: 30px;

	&-image {
		background-image: url( ../images/PreviewArea_EmptyState.png );
		background-repeat: no-repeat;
		background-size: auto 96px;
		background-position: center;
		height: 96px;
		display: block;
	}

	&-header {
		font-weight: @font-weight-bold;
		display: block;
	}
}

</style>
