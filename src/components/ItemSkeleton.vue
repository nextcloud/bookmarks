<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div :class="{
			item: true,
			'item--gridview': viewMode === 'grid'
		}"
		:style="{ background: 'var(--color-placeholder-dark)', height: viewMode !== 'grid'? '45px' : '' }">
		<div class="item__labels" />
		<div class="item__actions" />
	</div>
</template>
<script>

export default {
	name: 'ItemSkeleton',
	components: {
	},
	computed: {
		viewMode() {
			return this.$store.state.settings.viewMode
		},
	},
}
</script>
