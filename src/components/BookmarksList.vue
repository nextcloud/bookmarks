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
			<Folder
				v-for="folder in folderChildren"
				:key="'f' + folder.id"
				:folder="folder" />
		</template>
		<template v-if="bookmarks.length">
			<Bookmark
				v-for="bookmark in bookmarks"
				:key="'b' + bookmark.id"
				:bookmark="bookmark" />
		</template>
		<div
			v-else-if="!loading && !folderChildren.length"
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
		folderChildren() {
			if (this.$route.name !== this.routes.HOME && this.$route.name !== this.routes.FOLDER) {
				return []
			}
			const folderId = this.$route.params.folder || '-1'
			if (!folderId) return []
			const folder = this.$store.getters.getFolder(folderId)[0]
			if (!folder) return []
			this.$store.dispatch(actions.LOAD_SHARES_OF_FOLDER, folder.id)
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
