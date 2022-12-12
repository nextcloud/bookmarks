<!--
  - Copyright (c) 2022. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<NcAppContentList :show-details="showDetails" @update:show-details="$emit('update:show-details', $event)">
		<TreeFolder v-for="folder in rootFolder.children"
			:key="folder.id"
			:folder="folder"
			@select="onSelect($event)" />
	</NcAppContentList>
</template>

<script>
import { NcAppContentList } from '@nextcloud/vue'
import TreeFolder from './TreeFolder.vue'
import { privateRoutes } from '../router.js'

export default {
	name: 'FolderOverview',
	components: {
		TreeFolder,
		NcAppContentList,
	},
	props: {
		showDetails: {
			type: Boolean,
			required: true,
		},
	},
	computed: {
		rootFolder() {
			return this.$store.getters.getFolder(-1)[0]
		},
	},
	methods: {
		onSelect(folder) {
			this.$router.push({ name: privateRoutes.FOLDER, params: { folder } })
			this.$emit('update:show-details', true)
		},
	},
}
</script>

<style>
.app-content-list {
	padding: 5px;
	padding-top: 45px;
	overflow-y: scroll;
	height: calc(100vh - 50px);
}
</style>
