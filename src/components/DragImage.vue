<!--
  - Copyright (c) 2021. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div class="dragImage">
		<FolderIcon v-if="this.$store.state.selection.folders.length" :fill-color="colorPrimaryElement" />
		<EarthIcon v-else :fill-color="colorPrimaryElement" /><span class="description">{{ selectionDescription }}</span>
	</div>
</template>

<script>
import FolderIcon from 'vue-material-design-icons/Folder'
import EarthIcon from 'vue-material-design-icons/Earth'
export default {
	name: 'DragImage',
	components: { FolderIcon, EarthIcon },
	computed: {
		selectionDescription() {
			if (this.$store.state.selection.bookmarks.length !== 0 && this.$store.state.selection.folders.length !== 0) {
				return this.t('bookmarks',
					'{folders} folders and {bookmarks} bookmarks',
					{ folders: this.$store.state.selection.folders.length, bookmarks: this.$store.state.selection.bookmarks.length }
				)
			}
			if (this.$store.state.selection.bookmarks.length !== 0) {
				if (this.$store.state.selection.bookmarks.length === 1) {
					return this.$store.state.selection.bookmarks[0].title
				}
				return this.n('bookmarks',
					'%n bookmark',
					'%n bookmarks',
					this.$store.state.selection.bookmarks.length
				)
			}
			if (this.$store.state.selection.folders.length !== 0) {
				if (this.$store.state.selection.folders.length === 1) {
					return this.$store.state.selection.folders[0].title
				}
				return this.n('bookmarks',
					'%n folder',
					'%n folders',
					this.$store.state.selection.folders.length
				)
			}
			return ''
		},
	},
}
</script>

<style scoped>
.dragImage {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	box-shadow: #efefef7d 0px 0 13px 0px;
	border-radius: var(--border-radius-large);
	width: 250px;
	height: 1.5em;
	padding: 10px;
	display: flex;
	flex-direction: row;
}

.dragImage .description {
	text-overflow: ellipsis;
	overflow: hidden;
	flex-grow: 1;
	height: 1.5em;
	display: inline-block;
	margin-left: 3px;
}
</style>
