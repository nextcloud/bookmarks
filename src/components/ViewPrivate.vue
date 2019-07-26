<template>
	<Content app-name="bookmarks">
		<AppNavigation>
			<AppNavigationNew
				:text="t('bookmarks', 'New Bookmark')"
				:disabled="false"
				button-id="bookmarks-new"
				button-class="['icon-add', {loading: loading.create}]"
				@click="onNewBookmark"
			/>
			<NavigationList
				v-show="!loading.folders && !loading.tags"
				:folders="folders"
				:tags="tags"
				@select-folder="onSelectFolder"
				@select-tag="onSelectTag"
			/>
			<Settings @reload="reload" />
		</AppNavigation>
		<BookmarksList
			:loading="loading.bookmarks"
			:bookmarks="bookmarks"
			@load-next="onNextPage"
			@delete-bookmark="onDeleteBookmark"
		/>
	</Content>
</template>

<script>
import { Content, AppNavigation, AppNavigationNew } from 'nextcloud-vue';
// import Settings from './Settings';
// import NavigationList from './NavigationList';
import BookmarksList from './BookmarksList';
import { actions } from '../store';

export default {
	name: 'App',
	components: {
		Content,
		AppNavigation,
		AppNavigationNew,
		// NavigationList,
		// Settings,
		BookmarksList
	},
	data: function() {
		return {
			loading: {
				folders: true,
				tags: true,
				bookmarks: true,
				create: false
			},
			page: {
				type: Number,
				default: 0
			}
		};
	},
	computed: {
		bookmarks() {
			return this.$store.bookmarks;
		},
		folders() {
			return this.$store.folders;
		},
		tags() {
			return this.$store.tags;
		}
	},

	watch: {
		search(from, to) {
			this.$store.dispatch(actions.FILTER_BY_SEARCH, to);
		},

		tags(from, to) {
			this.$store.dispatch(actions.FILTER_BY_TAGS, to);
		},

		folderId(from, to) {
			this.$store.dispatch(actions.FILTER_BY_FOLDER, to);
		}
	},

	methods: {
		onNextPage() {
			this.$store.dispatch(actions.FETCH_PAGE);
		},

		onNewBookmark(e) {
			console.debug(e);
		},

		onSelectTag(tag) {
			this.$router.push({ name: 'tag', tags: tag });
		},

		onSelectFolder(folderId) {
			this.$router.push({ name: 'folder', folderId });
		}
	}
};
</script>
