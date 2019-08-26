<template>
  <div
    :class="{
      Bookmarks__BookmarksList: true,
      'Bookmarks__BookmarksList--GridView': viewMode === 'grid'
    }"
  >
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
			if (this.$route.name !== 'home' && this.$route.name !== 'folder') {
				return [];
			}
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
		},
		viewMode() {
			return this.$store.state.viewMode;
		}
	}
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

.Bookmarks__BookmarksList--GridView {
	display: flex;
	flex-flow: wrap;
}
.Bookmarks__BookmarksList--GridView > .Bookmarks__BookmarksList__Folder,
.Bookmarks__BookmarksList--GridView > .Bookmarks__BookmarksList__Bookmark,
.Bookmarks__BookmarksList--GridView > .Bookmarks__CreateFolder,
.Bookmarks__BookmarksList--GridView > .Bookmarks__CreateBookmark {
	width: 200px;
	max-width: 300px;
	flex: 1;
	height: 200px;
	align-items: flex-end;
	background: rgb(255, 255, 255);
	margin: 10px 0 0 10px;
	border: 1px solid var(--color-border);
	box-shadow: #efefef7d 0px 0 13px 0px inset;
}
</style>
