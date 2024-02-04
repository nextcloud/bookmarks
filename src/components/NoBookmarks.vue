<!--
  - Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div class="bookmarkslist__emptyBookmarks">
		<NcEmptyContent v-if="$route.name === routes.ARCHIVED"
			:name="t('bookmarks', 'No bookmarked files')"
			:description="t('bookmarks', 'Bookmarks to files like photos or PDFs will automatically be saved to your Nextcloud files, so you can still find them even when the link goes offline.')">
			<template #icon>
				<FileDocumentMultipleIcon :size="20" />
			</template>
		</NcEmptyContent>
		<NcEmptyContent v-else-if="$route.name === routes.SEARCH"
			:name="t('bookmarks', 'Nothing found')"
			:description="t('bookmarks', 'Your search yielded no results in the current folder.')">
			<template #icon>
				<StarShootingIcon :size="20" />
			</template>
		</NcEmptyContent>
		<NcEmptyContent v-else-if="$route.name === routes.UNAVAILABLE"
			:name="t('bookmarks', 'No broken links')"
			:description="t('bookmarks', 'Bookmarked links are checked regularly and the ones that cannot be reached are listed here.')">
			<template #icon>
				<LinkVariantOffIcon :size="20" />
			</template>
		</NcEmptyContent>
		<NcEmptyContent v-else-if="$route.name === routes.SHARED_FOLDERS"
			:name="t('bookmarks', 'No shared folders')"
			:description="t('bookmarks', 'You can share bookmark folders with others. All folders shared with you are listed here.')">
			<template #icon>
				<ShareVariantIcon :size="20" />
			</template>
		</NcEmptyContent>
		<NcEmptyContent v-else-if="$route.name === routes.DUPLICATED"
			:name="t('bookmarks', 'No duplicated bookmarks')"
			:description="t('bookmarks', 'One bookmark can be in multiple folders at once. Updating it will update all copies. All duplicated bookmarks are listed here for convenience.')">
			<template #icon>
				<VectorLinkIcon :size="20" />
			</template>
		</NcEmptyContent>
		<NcEmptyContent v-else
			:name="t('bookmarks', 'No bookmarks here')"
			:description="t('bookmarks', 'Add bookmarks manually or import bookmarks from a HTML file.')">
			<template #icon>
				<StarShootingIcon :size="20" />
			</template>
			<template v-if="!isPublic" #action>
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
						<NcLoadingIcon v-else :size="20" />
					</template>
					{{ t('bookmarks', 'Import bookmarks') }}
				</NcButton>
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import { NcEmptyContent, NcButton, NcLoadingIcon } from '@nextcloud/vue'
import { actions, mutations } from '../store/index.js'
import { privateRoutes } from '../router.js'
import { StarShootingIcon, UploadIcon, PlusIcon, ShareVariantIcon, VectorLinkIcon, LinkVariantOffIcon, FileDocumentMultipleIcon } from './Icons.js'

export default {
	name: 'NoBookmarks',
	components: { NcEmptyContent, StarShootingIcon, NcButton, NcLoadingIcon, UploadIcon, PlusIcon, ShareVariantIcon, VectorLinkIcon, LinkVariantOffIcon, FileDocumentMultipleIcon },
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
	margin-top: 100px;
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

.empty-content {
	display: flex;
	height: 100%;
}
</style>
