<template>
	<AppContentList>
		<BookmarksListItem
			v-for="bookmark in bookmarks"
			:key="bookmark.id"
			:bookmark="bookmark"
			@delete-bookmark="$emit('delete-bookmark')"
		/>
	</AppContentList>
</template>

<script>
import { AppContentList } from 'nextcloud-vue';
import BookmarksListItem from './BookmarksListItem';

export default {
	name: 'NavigationList',
	components: {
		BookmarksListItem,
		AppContentList
	},
	props: {
		bookmarks: {
			type: Array,
			required: true
		}
	},
	created() {}
};
</script>
