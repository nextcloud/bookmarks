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
		<div v-for="folder of items" :key="folder.id" class="treefolder">
			<div class="treefolder__title" @click="folder.children && onSelect(folder.id)">
				<h3>
					<FolderIcon :fill-color="colorPrimaryElement" />
					{{ folder.title }}
				</h3>
			</div>
		</div>
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

export default {
	name: 'FolderPicker',
	components: {
		Actions, ActionButton, FolderIcon, ArrowLeftIcon, HomeIcon,
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
}

.currentfolder h2 .material-design-icon {
	position: relative;
	top: 5px;
	margin: 0 15px;
}

.treefolder__title .material-design-icon {
	position: relative;
	top: 1px;
	margin: 0 15px;
}

.treefolder__title {
	display: flex;
	align-items: center;
	padding: 0 10px;
	margin: 0 -10px;
	cursor: pointer;
}

.treefolder__title * {
	cursor: pointer;
}

{
	position: relative;
	top: 4px;
}

.treefolder__title:hover,
.treefolder__title:focus {
	background: var(--color-background-dark);
}

.treefolder__title > h3 {
	flex: 1;
	display: flex;
}

.folderpicker .actions {
	flex-grow: 1;
	display: flex;
	justify-content: end;
	align-items: end;
}
</style>
