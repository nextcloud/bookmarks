<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div class="bookmarkslist__emptyBookmarks">
		<EmptyContent icon="icon-favorite">
			{{ t('bookmarks', 'Welcome to Bookmarks') }}
			<template #desc>
				<p>{{ t('bookmarks', 'This app allows you to manage links to your favorite places on the web.') }}</p>
				<p />
				<p>{{ t('bookmarks', 'Sort your bookmarks into folders, label them with tags and share them with others! The app will regularly check all your links for availability and display unavailable links. If you add a link to a file on the web, the file will be automatically downloaded to your Nextcloud Files. You can also import bookmarks exported from other services or directly sync bookmarks from all your browsers with this app.') }}</p>
				<p />
				<input ref="import"
					type="file"
					class="import"
					size="5"
					@change="onImportSubmit">
				<button @click="onCreateOpen">
					{{ t('bookmarks', 'Add a bookmark') }}
				</button>
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
import { actions, mutations } from '../store'
import { privateRoutes } from '../router'
import SyncIcon from 'vue-material-design-icons/Sync'

export default {
	name: 'FirstRun',
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

.material-design-icon {
	position: relative;
	top: 4px;
	display: inline-flex;
}

p {
	margin-bottom: 15px;
}
</style>
