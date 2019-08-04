<template>
	<div class="Bookmarks__BookmarksList">
		<CreateBookmark
			v-if="newBookmark"
			:loading="creating"
			@create-bookmark="onCreateBookmark"
		/>
		<template v-if="bookmarks.length">
			<BookmarksListItem
				v-for="bookmark in bookmarks"
				:key="bookmark.id"
				:bookmark="bookmark"
			/>
		</template>
		<div v-else-if="!loading" class="Bookmarks__BookmarksList_Empty">
			<h2>No bookmarks here</h2>
			<p>Try changing your query or add some using the button on the left.</p>
		</div>
		<div v-if="loading" class="Bookmarks__BookmarksList_Loading">
			<span class="icon-loading" />
		</div>
	</div>
</template>

<script>
import BookmarksListItem from './BookmarksListItem';
import CreateBookmark from './CreateBookmark';

export default {
	name: 'NavigationList',
	components: {
		BookmarksListItem,
		CreateBookmark
	},
	props: {
		bookmarks: {
			type: Array,
			required: true
		},
		newBookmark: {
			type: Boolean,
			required: true
		},
		creating: {
			type: Boolean,
			required: true
		},
		loading: {
			type: Boolean,
			required: true
		}
	},
	created() {},
	methods: {
		onCreateBookmark(url) {
			this.$emit('create-bookmark', url);
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
</style>
