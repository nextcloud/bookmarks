<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<Modal v-if="show" :title="t('bookmarks', 'Select folder')" @close="onClose">
		<div class="folderpicker-dialog">
			<TreeFolder
				:folder="{
					title: t('bookmarks', 'Root folder'),
					id: -1,
					children: allFolders
				}"
				:show-children-default="true"
				@select="onSelect" />
		</div>
	</Modal>
</template>
<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'
import TreeFolder from './TreeFolder'

export default {
	name: 'FolderPickerDialog',
	components: {
		Modal,
		TreeFolder,
	},
	props: {
		value: {
			type: Number,
			default: -1,
		},
		show: {
			type: Boolean,
			required: true,
		},
	},
	computed: {
		allFolders() {
			return this.$store.state.folders
		},
	},
	created() {},
	methods: {
		onSelect(folderId) {
			this.$emit('input', folderId)
			this.$emit('close')
		},
		onClose() {
			this.$emit('close')
		},
	},
}
</script>
<style>
.folderpicker-dialog {
	min-width: 300px;
	height: 300px;
	overflow-y: scroll;
	padding: 10px;
}
</style>
