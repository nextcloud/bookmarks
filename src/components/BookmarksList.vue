<!--
  - Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div :class="{
		bookmarkslist: true,
		'bookmarkslist--gridview': viewMode === 'grid',
		'bookmarkslist--with-description': descriptionShown,
	}">
		<div v-if="$route.name === routes.TRASHBIN && (bookmarks.length || subFolders.length)" class="bookmarkslist__description">
			<NcNoteCard type="info">
				{{
					t('bookmarks', 'These are your deleted items. Items will be deleted permanently after two months.')
				}}
			</NcNoteCard>
		</div>
		<div v-if="$route.name === routes.ARCHIVED && bookmarks.length" class="bookmarkslist__description">
			<NcNoteCard type="info">
				{{
					t('bookmarks', 'Bookmarks to files on the web like photos or PDFs will automatically be saved to your Nextcloud files, so you can still find them even when the link goes offline.')
				}}
			</NcNoteCard>
		</div>
		<div v-if="$route.name === routes.SEARCH && (bookmarks.length || subFolders.length) && Number($route.params.folder) !== -1" class="bookmarkslist__description">
			<NcNoteCard type="info">
				{{
					t('bookmarks', 'Searching in the current folder only.')
				}}
				<NcButton @click="onSearchGlobally">
					<template #icon>
						<MagnifyIcon :size="20" />
					</template>
					{{ t('bookmarks', 'Repeat search in all folders') }}
				</NcButton>
			</NcNoteCard>
		</div>
		<div v-if="$route.name === routes.UNAVAILABLE && bookmarks.length" class="bookmarkslist__description">
			<NcNoteCard type="info">
				{{
					t('bookmarks', 'Bookmarked links are checked regularly and the ones that cannot be reached are listed here.')
				}}
			</NcNoteCard>
		</div>
		<div v-if="$route.name === routes.SHARED_FOLDERS && bookmarks.length" class="bookmarkslist__description">
			<NcNoteCard type="info">
				{{
					t('bookmarks', 'You can share bookmark folders with others. All folders shared with you are listed here.')
				}}
			</NcNoteCard>
		</div>
		<div v-if="$route.name === routes.DUPLICATED && bookmarks.length" class="bookmarkslist__description">
			<NcNoteCard type="info">
				{{
					t('bookmarks', 'One bookmark can be in multiple folders at once. Updating it will update all copies. All duplicated bookmarks are listed here for convenience.')
				}}
			</NcNoteCard>
		</div>
		<VirtualScroll :reached-end="reachedEnd" @load-more="loadMore">
			<CreateBookmark v-if="newBookmark" />
			<CreateFolder v-if="newFolder" />
			<template v-if="$route.name === routes.FOLDER || $route.name === routes.HOME">
				<!-- FOLDER VIEW WITH CUSTOM SORTING -->
				<template v-if="sortOrder === 'index' && children.length">
					<template v-for="item in children">
						<Folder v-if="item.type === 'folder' && getFolder(item.id)"
							:key="item.type + item.id"
							:folder="getFolder(item.id)" />
						<Bookmark v-if="item.type === 'bookmark' && getBookmark(item.id)"
							:key="item.type + item.id"
							:bookmark="getBookmark(item.id)" />
					</template>
				</template>
				<!-- FOLDER VIEW WITH NORMAL SORTING -->
				<template v-else-if="(subFolders.length || bookmarks.length) && !loading">
					<Folder v-for="folder in subFolders"
						:key="'folder' + folder.id"
						:folder="folder" />
					<template v-if="bookmarks.length">
						<Bookmark v-for="bookmark in bookmarks"
							:key="'bookmark' + bookmark.id"
							:bookmark="bookmark" />
					</template>
				</template>
				<NoBookmarks v-else-if="!loading && (allBookmarksCount > 0 || isPublic)" />
				<FirstRun v-else-if="!loading" />
			</template>
			<!-- NON-FOLDER VIEW -->
			<template v-else-if="subFolders.length || bookmarks.length">
				<Folder v-for="folder in subFolders"
					:key="'folder' + folder.id"
					:folder="folder" />
				<Bookmark v-for="bookmark in bookmarks"
					:key="'bookmark' + bookmark.id"
					:bookmark="bookmark" />
			</template>
			<NoBookmarks v-else-if="!loading && (allBookmarksCount > 0 || isPublic)" />
			<FirstRun v-else-if="!loading" />
		</VirtualScroll>
	</div>
</template>

<script>
import { NcButton, NcNoteCard } from '@nextcloud/vue'
import Bookmark from './Bookmark.vue'
import Folder from './Folder.vue'
import CreateBookmark from './CreateBookmark.vue'
import CreateFolder from './CreateFolder.vue'
import { actions, mutations } from '../store/index.js'
import NoBookmarks from './NoBookmarks.vue'
import FirstRun from './FirstRun.vue'
import VirtualScroll from './VirtualScroll.vue'
import { privateRoutes } from '../router.js'
import { MagnifyIcon } from './Icons.js'

export default {
	name: 'BookmarksList',
	components: {
		NcButton,
		CreateFolder,
		CreateBookmark,
		VirtualScroll,
		FirstRun,
		NoBookmarks,
		Bookmark,
		Folder,
		NcNoteCard,
		MagnifyIcon,
	},
	computed: {
		bookmarks() {
			return this.$store.state.bookmarks
		},
		reachedEnd() {
			return this.$store.state.fetchState.reachedEnd
		},
		descriptionShown() {
			return this.$route.name === this.routes.ARCHIVED || (this.$route.name === this.routes.SEARCH && Number(this.$route.params.folder) !== -1) || this.$route.name === this.routes.UNAVAILABLE || this.$route.name === this.routes.SHARED_FOLDERS || this.$route.name === this.routes.DUPLICATED
		},
		allBookmarksCount() {
			return this.$store.state.countsByFolder[-1]
		},
		children() {
			if (this.$route.name !== this.routes.HOME && this.$route.name !== this.routes.FOLDER) {
				return []
			}
			const folderId = this.$route.params.folder || '-1'
			if (!folderId) return []
			return this.$store.getters.getFolderChildren(folderId)
		},
		subFolders() {
			if (this.$route.name === this.routes.SHARED_FOLDERS) {
				// Show shared folders
				return Object.keys(this.$store.state.sharedFoldersById)
					.map(folderId => this.$store.getters.getFolder(folderId)[0])
			}
			if (this.$route.name === this.routes.TRASHBIN) {
				// Show deleted folders
				return this.$store.state.deletedFolders
			}
			if (this.$route.name === this.routes.SEARCH) {
				// Search folders
				const searchFolder = (folder) => {
					const results = folder.children.flatMap(searchFolder)
					if (folder.title && this.$store.state.fetchState.query.search && this.$store.state.fetchState.query.search.every(term => term.trim() && folder.title.toLowerCase().includes(term.toLowerCase()))) {
						results.push(folder)
					}
					return results
				}
				return this.$store.getters.getFolder(this.$store.state.fetchState.query.folder || -1)[0].children.flatMap(searchFolder)
			}
			if (this.$route.name !== this.routes.HOME && this.$route.name !== this.routes.FOLDER) {
				return []
			}
			const folderId = this.$route.params.folder || '-1'
			if (!folderId) return []
			const folder = this.$store.getters.getFolder(folderId)[0]
			if (!folder) return []
			this.$store.dispatch(actions.LOAD_SHARES_OF_FOLDER, folderId)
			return folder.children
		},
		newBookmark() {
			return this.$store.state.displayNewBookmark
		},
		newFolder() {
			return this.$store.state.displayNewFolder
		},
		viewMode() {
			return this.$store.state.viewMode
		},
		sortOrder() {
			return this.$store.state.settings.sorting
		},
		loading() {
			return this.$store.state.loading.bookmarks || this.$store.state.loading.folders
		},
	},
	methods: {
		loadMore() {
			if (this.$route.name === privateRoutes.SHARED_FOLDERS) {
				this.$store.commit(mutations.REACHED_END)
				return
			}
			this.$store.dispatch(actions.FETCH_PAGE)
		},
		getFolder(id) {
			return this.$store.getters.getFolder(id)[0]
		},
		getBookmark(id) {
			return this.$store.getters.getBookmark(id)
		},
		onSearchGlobally() {
			this.$router.push({ name: this.routes.SEARCH, params: { search: this.$route.params.search, folder: '-1' } })
		},
	},
}
</script>
<style>
.folder--gridview,
.bookmark--gridview,
.bookmarkslist--gridview .create-folder,
.bookmarkslist--gridview .create-bookmark {
	width: 250px;
	height: 200px;
	align-items: flex-end;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	box-shadow: #efefef7d 0px 0 13px 0px inset;
	border-radius: var(--border-radius);
}
</style>
