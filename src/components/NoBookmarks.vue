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
		<EmptyContent v-else-if="$route.name === routes.UNAVAILABLE">
			{{ t('bookmarks', 'No broken links') }}
			<template #desc>
				{{ t('bookmarks', 'Bookmarked links are checked regularly and the ones that cannot be reached are listed here.') }}
			</template>
		</EmptyContent>
		<EmptyContent v-else-if="$route.name === routes.SHARED_FOLDERS">
			{{ t('bookmarks', 'No shared folders') }}
			<template #desc>
				{{ t('bookmarks', 'You can share bookmark folders with others. All folders shared with you are listed here.') }}
			</template>
		</EmptyContent>
		<EmptyContent v-else icon="icon-favorite">
			{{ t('bookmarks', 'No bookmarks here') }}
			<template v-if="!isPublic" #desc>
				<p>{{ t('bookmarks', 'Add bookmarks manually or import bookmarks from a HTML file.') }}</p>
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
			</template>
		</EmptyContent>
	</div>
</template>

<script>
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import { actions, mutations } from '../store'
import { privateRoutes } from '../router'
export default {
	name: 'NoBookmarks',
	components: { EmptyContent },
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
