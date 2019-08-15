<template>
	<div class="Bookmarks__BookmarksList">
		<CreateBookmark v-if="newBookmark" />
		<CreateFolder v-if="newFolder" />
		<template v-if="$route.name === 'folder' || $route.name === 'home'">
			<BookmarksListFolder
				v-for="folder in folderChildren"
				:key="'f' + folder.id"
				:folder="folder"
			/>
		</template>
		<template v-if="bookmarks.length">
			<BookmarksListBookmark
				v-for="bookmark in bookmarks"
				:key="'b' + bookmark.id"
				:bookmark="bookmark"
			/>
		</template>
		<div
			v-else-if="!loading && !folderChildren.length"
			class="Bookmarks__BookmarksList_Empty"
		>
			<h2>No bookmarks here</h2>
			<p>Try changing your query or add some using the button on the left.</p>
		</div>
		<div v-if="loading" class="Bookmarks__BookmarksList_Loading">
			<span class="icon-loading" />
		</div>
	</div>
</template>

<script>
import BookmarksListBookmark from './BookmarksListBookmark';
import BookmarksListFolder from './BookmarksListFolder';
import CreateBookmark from './CreateBookmark';
import CreateFolder from './CreateFolder';
import { actions } from '../store';

export default {
	name: 'BookmarksList',
	components: {
		BookmarksListBookmark,
		BookmarksListFolder,
		CreateBookmark,
		CreateFolder
	},
	props: {
		bookmarks: {
			type: Array,
			required: true
		},
		loading: {
			type: Boolean,
			required: true
		}
	},
	computed: {
		folderChildren() {
			const folderId = this.$route.params.folder || '-1';
			if (!folderId) return [];
			const folder = this.$store.getters.getFolder(folderId)[0];
			if (!folder) return [];
			return folder.children;
		},
		newBookmark() {
			return this.$store.state.displayNewBookmark;
		},
		newFolder() {
			return this.$store.state.displayNewFolder;
		}
	},
	created() {},
	methods: {}
};
</script>
<style>
.Bookmarks__BookmarksList
	> *:first-child:not(.Bookmarks__BookmarksList_Loading):not(.Bookmarks__BookmarksList_Empty) {
	border-top: 1px solid var(--color-border);
}
.Bookmarks__BookmarksList_Loading,
.Bookmarks__BookmarksList_Empty {
	width: 200px;
	margin: 200px auto;
}
.Bookmarks__BookmarksList_Loading {
	text-align: center;
}
</style>
