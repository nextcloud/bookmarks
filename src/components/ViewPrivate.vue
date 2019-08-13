<template>
	<Content app-name="bookmarks">
		<Navigation />
		<AppContent>
			<Breadcrumbs />
			<BookmarksList :loading="loading.bookmarks" :bookmarks="bookmarks" />
		</AppContent>
		<SidebarBookmark />
	</Content>
</template>

<script>
import { Content, AppContent } from 'nextcloud-vue';
// import Settings from './Settings';
import Navigation from './Navigation';
import BookmarksList from './BookmarksList';
import Breadcrumbs from './Breadcrumbs';
import SidebarBookmark from './SidebarBookmark';
import { actions } from '../store';

export default {
	name: 'App',
	components: {
		Navigation,
		Content,
		AppContent,
		// Settings,
		Breadcrumbs,
		BookmarksList,
		SidebarBookmark
	},
	data: function() {
		return {
			newBookmark: false
		};
	},
	computed: {
		bookmarks() {
			return this.$store.state.bookmarks;
		},
		folders() {
			return this.$store.state.folders;
		},
		tags() {
			return this.$store.state.tags;
		},
		loading() {
			return this.$store.state.loading;
		}
	},

	watch: {
		$route: 'onRoute'
	},

	created() {
		this.reloadTags();
		this.reloadFolders();
		this.onRoute();
		document.addEventListener('scroll', this.onScroll);
	},

	methods: {
		onRoute() {
			const route = this.$route;
			switch (route.name) {
				case 'home':
					this.$store.dispatch(actions.FILTER_BY_FOLDER, route.params.folder);
					break;
				case 'recent':
					this.$store.dispatch(actions.NO_FILTER);
					break;
				case 'untagged':
					this.$store.dispatch(actions.FILTER_BY_UNTAGGED);
					break;
				case 'folder':
					this.$store.dispatch(actions.FILTER_BY_FOLDER, route.params.folder);
					break;
				case 'tags':
					this.$store.dispatch(actions.FILTER_BY_TAGS, route.params.tags);
					break;
				case 'search':
					this.$store.dispatch(actions.FILTER_BY_SEARCH, route.params.search);
					break;
				default:
					throw new Error('Nothing here. Move along.');
			}
		},

		reloadTags() {
			this.$store.dispatch(actions.LOAD_TAGS);
		},
		reloadFolders() {
			this.$store.dispatch(actions.LOAD_FOLDERS);
		},

		onScroll() {
			if (
				document.body.scrollHeight <
				window.scrollY + window.innerHeight + 500
			) {
				this.$store.dispatch(actions.FETCH_PAGE);
			}
		}
	}
};
</script>
