<template>
	<div
		:class="{
			bookmarkslist: true,
			'bookmarkslist--gridview': viewMode === 'grid'
		}"
		@scroll="onScroll">
		<CreateBookmark v-if="newBookmark" />
		<CreateFolder v-if="newFolder" />
		<template v-if="$route.name === routes.FOLDER || $route.name === routes.HOME">
			<!-- FOLDER VIEW WITH CUSTOM SORTING -->
			<template v-if="sortOrder === 'index'">
				<template v-for="item in children">
					<Folder
						v-if="item.type === 'folder'"
						:key="item.type + item.id"
						:folder="getFolder(item.id)" />
					<Bookmark
						v-if="item.type === 'bookmark' && getBookmark(item.id)"
						:key="item.type + item.id"
						:bookmark="getBookmark(item.id)" />
				</template>
			</template>
			<!-- FOLDER VIEW WITH NORMAL SORTING -->
			<template v-else>
				<Folder
					v-for="folder in subFolders"
					:key="'folder' + folder.id"
					:folder="folder" />
				<template v-if="bookmarks.length">
					<Bookmark
						v-for="bookmark in bookmarks"
						:key="'bookmark' + bookmark.id"
						:bookmark="bookmark" />
				</template>
			</template>
		</template>
		<!-- NON-FOLDER VIEW -->
		<template v-else-if="bookmarks.length">
			<Bookmark
				v-for="bookmark in bookmarks"
				:key="'bookmark' + bookmark.id"
				:bookmark="bookmark" />
		</template>
		<div
			v-else-if="!loading && !bookmarks.length && !subFolders.length"
			class="bookmarkslist__empty">
			<h2>{{ t('bookmarks', 'No bookmarks here') }}</h2>
			<p>{{ t('bookmarks', 'Try changing your query or add some using the button on the left.') }}</p>
		</div>
		<div v-if="loading" class="bookmarkslist__loading">
			<figure class="icon-loading" />
		</div>
	</div>
</template>

<script>
import Bookmark from './Bookmark'
import Folder from './Folder'
import CreateBookmark from './CreateBookmark'
import CreateFolder from './CreateFolder'
import { actions } from '../store'

export default {
	name: 'BookmarksList',
	components: {
		Bookmark,
		Folder,
		CreateBookmark,
		CreateFolder,
	},
	props: {
		bookmarks: {
			type: Array,
			required: true,
		},
		loading: {
			type: Boolean,
			required: true,
		},
	},
	computed: {
		children() {
			if (this.$route.name !== this.routes.HOME && this.$route.name !== this.routes.FOLDER) {
				return []
			}
			const folderId = this.$route.params.folder || '-1'
			if (!folderId) return []
			return this.$store.getters.getFolderChildren(folderId)
		},
		subFolders() {
			if (this.$route.name !== this.routes.HOME && this.$route.name !== this.routes.FOLDER) {
				return []
			}
			const folderId = this.$route.params.folder || '-1'
			if (!folderId) return []
			const folder = this.$store.getters.getFolder(folderId)[0]
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
	},
	methods: {
		onScroll() {
			if (
				this.$el.scrollHeight
					< this.$el.scrollTop + this.$el.clientHeight + 500
			) {
				this.$store.dispatch(actions.FETCH_PAGE)
			}
		},
		getFolder(id) {
			return this.$store.getters.getFolder(id)[0]
		},
		getBookmark(id) {
			return this.$store.getters.getBookmark(id)
		},
	},
}
</script>
<style>
.bookmarkslist {
	/* 50px header; 50px breadcrumbs */
	height: calc(100vh - 50px - 50px);
	overflow-y: scroll;
	position: relative;
}

.bookmarkslist
> *:first-child:not(.bookmarkslist__loading):not(.bookmarkslist__empty) {
	border-top: 1px solid var(--color-border);
}

.bookmarkslist__loading,
.bookmarkslist__empty {
	width: 200px;
	margin: 200px auto;
}

.bookmarkslist__loading {
	text-align: center;
}

.bookmarkslist--gridview {
	display: flex;
	flex-flow: wrap;
	align-content: start;
	gap: 10px;
	padding: 0 10px;
}

.folder--gridview,
.bookmark--gridview,
.bookmarkslist--gridview > .create-folder,
.bookmarkslist--gridview > .create-bookmark {
	min-width: 200px;
	max-width: 300px;
	flex: 1;
	height: 200px;
	align-items: flex-end;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	box-shadow: #efefef7d 0px 0 13px 0px inset;
	border-radius: var(--border-radius);
}
</style>
