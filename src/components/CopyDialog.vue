<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<Modal v-if="showModal" :title="title" @close="onClose">
		<div class="copy-dialog">
			<FolderPicker :title="title" :filter="filterFolders" @submit="onSubmit" />
		</div>
	</Modal>
</template>
<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'
import { actions, mutations } from '../store/'
import FolderPicker from './FolderPicker'

export default {
	name: 'CopyDialog',
	components: {
		FolderPicker,
		Modal,
	},
	computed: {
		showModal() {
			return this.$store.state.displayCopyDialog
		},
		selection() {
			return this.$store.state.selection
		},
		title() {
			if (this.selection.folders.length) {
				if (this.selection.bookmarks.length) {
					return n('bookmarks',
						'Copyng %n folder and some bookmarks',
						'Copyng %n folders and some bookmarks',
						this.selection.folders.length
					)
				} else {
					return n('bookmarks',
						'Copyng %n folder',
						'Copyng %n folders',
						this.selection.folders.length
					)
				}
			} else {
				return n('bookmarks',
					'Copyng %n bookmark',
					'Copyng %n bookmarks',
					this.selection.bookmarks.length
				)
			}
		},
	},
	methods: {
		async onSubmit(folderId) {
			this.$store.commit(mutations.DISPLAY_COPY_DIALOG, false)
			await this.$store.dispatch(actions.COPY_SELECTION, folderId)
			this.$store.commit(mutations.RESET_SELECTION)
			this.$store.dispatch(actions.RELOAD_VIEW)
		},
		onClose() {
			this.$store.commit(mutations.DISPLAY_COPY_DIALOG, false)
		},
		filterFolders(child) {
			return !this.selection.folders.some(folder => folder.id === child.id)
		},
	},
}
</script>
<style>
.copy-dialog {
	min-width: 300px;
	height: 300px;
	overflow-y: scroll;
	padding: 20px;
}
</style>
