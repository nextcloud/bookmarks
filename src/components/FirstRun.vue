<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div class="bookmarkslist__emptyBookmarks">
		<NcEmptyContent :name="t('bookmarks', 'Welcome to Bookmarks')" :description="t('bookmarks', 'This app allows you to manage links to your favorite places on the web. Sort your bookmarks into folders, label them with tags and share them with others! The app will regularly check all your links for availability and display unavailable links. If you add a link to a file on the web, the file will be automatically downloaded to your Nextcloud Files. You can also import bookmarks exported from other services or directly sync bookmarks from all your browsers with this app.')">
			<template #icon>
				<StarShootingIcon :size="20" />
			</template>
			<template #action>
				<input ref="import"
					type="file"
					class="import"
					size="5"
					@change="onImportSubmit">
				<NcButton @click="onCreateOpen">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
					{{ t('bookmarks', 'Add a bookmark') }}
				</NcButton>
				<NcButton @click="onImportOpen">
					<template #icon>
						<UploadIcon v-if="!importing" :size="20" />
						<NcLoadingIcon v-else />
					</template>
					{{ t('bookmarks', 'Import bookmarks') }}
				</NcButton>
				<NcButton @click="onSyncOpen">
					<template #icon>
						<SyncIcon :size="20" />
					</template>
					{{ t('bookmarks', 'Sync with your browser') }}
				</NcButton>
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import { NcEmptyContent, NcButton, NcLoadingIcon } from '@nextcloud/vue'
import { actions, mutations } from '../store/index.js'
import { privateRoutes } from '../router.js'
import { SyncIcon, PlusIcon, UploadIcon, StarShootingIcon } from './Icons.js'

export default {
	name: 'FirstRun',
	components: { NcEmptyContent, SyncIcon, NcButton, PlusIcon, UploadIcon, NcLoadingIcon, StarShootingIcon },
	data() {
		return { importing: false }
	},
	computed: {
		routes() {
			return privateRoutes
		},
	},
	methods: {
		onCreateOpen() {
			this.$store.commit(mutations.DISPLAY_NEW_BOOKMARK, true)
		},
		onImportOpen() {
			this.$refs.import.click()
		},
		onSyncOpen() {
			window.open('https://floccus.org', '_blank')
		},
		async onImportSubmit(e) {
			this.importing = true
			try {
				await this.$store.dispatch(actions.IMPORT_BOOKMARKS, { file: e.target.files[0], folder: this.$route.params.folder || -1 })
			} catch (e) {
				console.warn(e)
			}
			this.importing = false
		},
	},
}
</script>
<style scoped>
.bookmarkslist__emptyBookmarks {
	width: 500px;
	margin: 0 auto;
}

.import {
	opacity: 0;
	position: absolute;
	top: 0;
	left: -1000px;
}

button {
	margin-bottom: 15px;
}
</style>
