<!--
  - Copyright (c) 2021 Artem Lavrukhin <lavryha4590@gmail.com>
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
			return n('bookmarks',
				'Adding %n bookmark to new folder',
				'Adding %n bookmarks to new folder',
				this.selection.bookmarks.length
			)
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
