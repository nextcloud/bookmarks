<template>
	<div class="bookmarkslist__emptyBookmarks">
		<EmptyContent v-if="$route.name === routes.ARCHIVED">
			{{ t('bookmarks', 'No archived bookmarks') }}
			<template #desc>
				{{ t('bookmarks', 'Bookmarks to files like photos or PDFs will automatically be saved to your nextcloud files, so you can still find them even when the link goes offline.') }}
			</template>
		</EmptyContent>
		<EmptyContent v-else>
			{{ t('bookmarks', 'No bookmarks here') }}
			<template #desc>
				<button @click="onAddBookmark">
					{{ t('bookmarks', 'Add a bookmark') }}
				</button>
				<input ref="import"
					type="file"
					class="import"
					size="5"
					@change="onImportSubmit">
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
		onAddBookmark() {
			this.$store.commit(
				mutations.DISPLAY_NEW_BOOKMARK,
				!this.$store.state.displayNewBookmark
			)
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
<style>
.bookmarkslist__emptyBookmarks {
	width: 450px;
	margin: 0 auto;
}

.import {
	opacity: 0;
	position: absolute;
	top: 0;
	left: -1000px;
}
</style>
