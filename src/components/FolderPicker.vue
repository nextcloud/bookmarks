<!--
  - Copyright (c) 2021. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div class="folderpicker">
		<h2>{{ title }}</h2>
		<div class="currentfolder">
			<Actions v-if="currentFolder.id !== -1">
				<ActionButton @click="onSelect(currentFolder.parent_folder)">
					<ArrowLeftIcon slot="icon" :size="18" :fill-color="colorMainText" />
					{{ t('bookmarks', 'Go back') }}
				</ActionButton>
			</Actions>
			<h2 v-if="currentFolder.id !== -1">
				<FolderIcon :fill-color="colorMainText" /><span>{{ currentFolder.title }}</span>
			</h2>
			<h2 v-else>
				<HomeIcon :fill-color="colorMainText" />
			</h2>
		</div>
		<TreeFolder v-for="folder of items"
			:key="folder.id"
			:folder="folder"
			:show-children="false"
			@select="folder.children && onSelect(folder.id)" />
		<div class="actions">
			<button class="button" @click="onSubmit">
				{{ t('bookmarks', 'Choose folder') }}
			</button>
		</div>
	</div>
</template>

<script>
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import FolderIcon from 'vue-material-design-icons/Folder'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft'
import HomeIcon from 'vue-material-design-icons/Home'
import TreeFolder from './TreeFolder'

export default {
	name: 'FolderPicker',
	components: {
		TreeFolder,
		Actions,
		ActionButton,
		FolderIcon,
		ArrowLeftIcon,
		HomeIcon,
	},
	props: {
		title: {
			type: String,
			required: true,
		},
		filter: {
			type: Function,
			required: false,
			default: () => true,
		},
	},
	data() {
		return {
			selectedFolderId: -1,
		}
	},
	computed: {
		currentFolderPath() {
			return this.$store.getters.getFolder(this.selectedFolderId).reverse()
		},
		currentFolder() {
			return this.$store.getters.getFolder(this.selectedFolderId)[0]
		},
		items() {
			return this.currentFolder.children.filter(this.filter)
		},
	},
	methods: {
		async onSelect(folderId) {
			if (!this.$store.getters.getFolder(folderId)[0]) {
				this.selectedFolderId = -1
			} else {
				this.selectedFolderId = folderId
			}
		},
		async onSubmit() {
			this.$emit('submit', this.selectedFolderId)
		},
	},
}
</script>
<style>
.folderpicker {
	min-height: 300px;
	display: flex;
	flex-direction: column;
}

.folderpicker .currentfolder {
	display: flex;
	align-items: center;
	height: 45px;
}

.currentfolder h2 {
	margin: 0;
	display: flex;
}

.folderpicker .actions {
	flex-grow: 1;
	display: flex;
	justify-content: end;
	align-items: end;
}
</style>
