<!--
  - Copyright (c) 2021. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div class="bulkediting">
		<NcActions :primary="true" :menu-name="selectionDescription">
			<NcActionButton close-after-click @click="onBulkOpen">
				<template #icon>
					<OpenInNewIcon :size="20" />
				</template>
				{{ t('bookmarks', 'Open all selected') }}
			</NcActionButton>
			<NcActionButton close-after-click @click="onBulkMove">
				<template #icon>
					<FolderMoveIcon :size="20" />
				</template>
				{{ t('bookmarks', 'Move selection') }}
			</NcActionButton>
			<NcActionButton v-if="!selectedFolders.length" close-after-click @click="onBulkCopy">
				<template #icon>
					<FolderPlusIcon :size="20" />
				</template>
				{{ t('bookmarks', 'Add to folders') }}
			</NcActionButton>
			<NcActionInput v-if="!selectedFolders.length"
				:value="selectionTags"
				type="multiselect"
				:options="allTags"
				:multiple="true"
				:taggable="true"
				@tag="onBulkTag([...selectionTags, $event])"
				@input="onBulkTag">
				<template #icon>
					<TagIcon :size="20" />
				</template>
				{{ t('bookmarks', 'Edit tags of selection') }}
			</NcActionInput>
			<NcActionButton close-after-click @click="onBulkDelete">
				<template #icon>
					<DeleteIcon :size="20" />
				</template>
				{{ t('bookmarks', 'Delete selection') }}
			</NcActionButton>
			<NcActionSeparator />
			<NcActionButton @click="onSelectAll">
				<template #icon>
					<SelectAllIcon :size="20" />
				</template>
				{{ t('bookmarks', 'Select all') }}
			</NcActionButton>
			<NcActionButton @click="onCancelSelection">
				<template #icon>
					<SelectOffIcon :size="20" />
				</template>
				{{ t('bookmarks', 'Cancel selection') }}
			</NcActionButton>
		</NcActions>
	</div>
</template>

<script>

import { NcActions, NcActionSeparator, NcActionButton, NcActionInput } from '@nextcloud/vue'
import { FolderPlusIcon, FolderMoveIcon, OpenInNewIcon, TagIcon, SelectAllIcon, SelectOffIcon, DeleteIcon } from './Icons.js'
import { actions, mutations } from '../store/index.js'
import intersection from 'lodash/intersection.js'

export default {
	name: 'BulkEditing',
	components: { NcActionInput, NcActionSeparator, FolderPlusIcon, FolderMoveIcon, NcActionButton, NcActions, OpenInNewIcon, TagIcon, SelectAllIcon, SelectOffIcon, DeleteIcon },
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
		onBulkCopy() {
			this.$store.commit(mutations.DISPLAY_COPY_DIALOG, true)
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
	min-width: 500px;
}
</style>
