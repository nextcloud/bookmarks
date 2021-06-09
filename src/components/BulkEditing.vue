<!--
  - Copyright (c) 2021. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div class="bulkediting">
		<Actions :primary="true" :menu-title="selectionDescription">
			<ActionButton icon="icon-external" close-after-click @click="onBulkOpen">
				{{ t('bookmarks', 'Open all selected') }}
			</ActionButton>
			<ActionButton close-after-click @click="onBulkMove">
				<template #icon>
					<FolderMoveIcon :fill-color="colorMainText" class="action-button-mdi-icon" />
				</template>
				{{ t('bookmarks', 'Move selection') }}
			</ActionButton>
			<ActionInput
				v-if="!selectedFolders.length"
				:value="selectionTags"
				icon="icon-tag"
				type="multiselect"
				:options="allTags"
				:multiple="true"
				:taggable="true"
				@tag="onBulkTag([...selectionTags, $event])"
				@input="onBulkTag">
				{{ t('bookmarks', 'Edit tags of selection') }}
			</ActionInput>
			<ActionButton icon="icon-delete" close-after-click @click="onBulkDelete">
				{{ t('bookmarks', 'Delete selection') }}
			</ActionButton>
			<ActionSeparator />
			<ActionButton icon="icon-checkmark" @click="onSelectAll">
				{{ t('bookmarks', 'Select all') }}
			</ActionButton>
			<ActionButton icon="icon-close" @click="onCancelSelection">
				{{ t('bookmarks', 'Cancel selection') }}
			</ActionButton>
		</Actions>
	</div>
</template>

<script>

import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionInput from '@nextcloud/vue/dist/Components/ActionInput'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionSeparator from '@nextcloud/vue/dist/Components/ActionSeparator'
import FolderMoveIcon from 'vue-material-design-icons/FolderMove'
import { actions, mutations } from '../store'
import intersection from 'lodash/intersection'

export default {
	name: 'BulkEditing',
	components: { ActionInput, ActionSeparator, FolderMoveIcon, ActionButton, Actions },
	data() {
		return {
			selectionTags: [],
		}
	},
	computed: {
		allTags() {
			return this.$store.state.tags.map(tag => tag.name)
		},
		selectedFolders() {
			return this.$store.state.selection.folders
		},
		selectedBookmarks() {
			return this.$store.state.selection.bookmarks
		},
		selectionDescription() {
			if (this.$store.state.selection.bookmarks.length !== 0 && this.$store.state.selection.folders.length !== 0) {
				return this.t('bookmarks',
					'Selected {folders} folders and {bookmarks} bookmarks',
					{ folders: this.$store.state.selection.folders.length, bookmarks: this.$store.state.selection.bookmarks.length }
				)
			}
			if (this.$store.state.selection.bookmarks.length !== 0) {
				return this.n('bookmarks',
					'Selected %n bookmark',
					'Selected %n bookmarks',
					this.$store.state.selection.bookmarks.length
				)
			}
			if (this.$store.state.selection.folders.length !== 0) {
				return this.n('bookmarks',
					'Selected %n folder',
					'Selected %n folders',
					this.$store.state.selection.folders.length
				)
			}
			return ''
		},
	},
	watch: {
		selectedBookmarks(bookmarks) {
			this.updateSelectionTags()
		},
	},
	methods: {
		async onBulkOpen() {
			for (const { url } of this.$store.state.selection.bookmarks) {
				window.open(url)
				await new Promise(resolve => setTimeout(resolve, 200))
			}
		},
		async onBulkDelete() {
			if (!confirm(t('bookmarks', 'Do you really want to delete these items?'))) {
				return
			}
			await this.$store.dispatch(actions.DELETE_SELECTION, { folder: this.$route.params.folder })
			this.$store.commit(mutations.RESET_SELECTION)
		},
		onBulkMove() {
			this.$store.commit(mutations.DISPLAY_MOVE_DIALOG, true)
		},
		async onBulkTag(tags) {
			const originalTags = this.selectionTags
			this.selectionTags = tags
			await this.$store.dispatch(actions.TAG_SELECTION, { tags, originalTags })
		},
		onCancelSelection() {
			this.$store.commit(mutations.RESET_SELECTION)
		},
		async onSelectAll() {
			await this.$store.dispatch(actions.FETCH_ALL)
			this.$store.state.bookmarks.forEach(bookmark => {
				this.$store.commit(mutations.ADD_SELECTION_BOOKMARK, bookmark)
			})
		},
		updateSelectionTags() {
			this.selectionTags = intersection(...this.selectedBookmarks.map((bm) => bm.tags))
		},
	},
}
</script>
<style>
.bulkediting {
	opacity: 1 !important;
	padding: 0 !important;
	margin-top: 1px;
}
</style>
