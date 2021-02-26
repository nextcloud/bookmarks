<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div class="bookmarkslist__emptyBookmarks">
		<EmptyContent v-if="$route.name === routes.ARCHIVED">
			{{ t('bookmarks', 'No bookmarked files') }}
			<template #desc>
				{{ t('bookmarks', 'Bookmarks to files like photos or PDFs will automatically be saved to your Nextcloud files, so you can still find them even when the link goes offline.') }}
			</template>
		</EmptyContent>
		<EmptyContent v-else icon="icon-favorite">
			{{ t('bookmarks', 'No bookmarks here') }}
			<template #desc>
				<p>{{ t('bookmarks', 'Start by importing bookmarks from a file or synchronizing your browser bookmarks with this app') }}</p>
				<input ref="import"
					type="file"
					class="import"
					size="5"
					@change="onImportSubmit">
				<button @click="onImportOpen">
					<span :class="{'icon-upload': !importing, 'icon-loading-small': importing}" /> {{ t('bookmarks', 'Import bookmarks') }}
				</button>
				<button @click="onSyncOpen">
					<SyncIcon :fill-color="colorMainText" :size="18" :style="{opacity: 0.5}" /> {{ t('bookmarks', 'Sync with your browser') }}
				</button>
			</template>
		</EmptyContent>
	</div>
</template>

<script>
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import { actions } from '../store'
import { privateRoutes } from '../router'
import SyncIcon from 'vue-material-design-icons/Sync'

export default {
	name: 'NoBookmarks',
	components: { EmptyContent, SyncIcon },
	data() {
		return { importing: false }
	},
	computed: {
		routes() {
			return privateRoutes
		},
	},
	methods: {
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

.material-design-icon {
	position: relative;
	top: 4px;
}
</style>
