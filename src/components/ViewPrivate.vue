<template>
	<Content app-name="bookmarks">
		<Navigation />
		<AppContent>
			<Breadcrumbs />
			<BookmarksList
				:loading="loading.bookmarks"
				:creating="loading.createBookmark"
				:bookmarks="bookmarks"
				:new-bookmark="newBookmark"
				@create-bookmark="onCreateBookmark"
			/>
		</AppContent>
	</Content>
</template>

<script>
import { Content, AppContent } from 'nextcloud-vue';
// import Settings from './Settings';
import Navigation from './Navigation';
import BookmarksList from './BookmarksList';
import Breadcrumbs from './Breadcrumbs';
import { actions } from '../store';

export default {
	name: 'App',
	components: {
		Navigation,
		Content,
		AppContent,
		// Settings,
		BookmarksList,
		Breadcrumbs
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
		},
		menu() {
			let defaultItems = [
				{
					router: { name: 'home' },
					icon: 'icon-home',
					text: this.t('bookmarks', 'All Bookmarks')
				},
				{
					router: { name: 'folder', params: { folder: '-1' } },
					icon: 'icon-category-files',
					text: this.t('bookmarks', 'Folders')
				},
				{
					router: { name: 'untagged' },
					icon: 'icon-category-disabled',
					text: this.t('bookmarks', 'Untagged')
				}
			];
			return defaultItems.concat(
				this.$store.state.tags.map(tag => ({
					action: () => this.onSelectTag(tag.name),
					icon: 'icon-tag',
					text: tag.name,
					edit: {
						action: newName => this.onRenameTag(tag.name, newName)
					},
					utils: {
						counter: tag.count,
						actions: [
							{
								icon: 'icon-rename',
								text: 'rename',
								action: () => this.setEditingTag(tag.name)
							}
						]
					}
				}))
			);
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

		onNewBookmark() {
			this.newBookmark = true;
		},
		onCreateBookmark(url) {
			// todo: create bookmark inside current folder
			this.$store.dispatch(actions.CREATE_BOOKMARK, url).then(() => {
				this.newBookmark = false;
			});
		},

		onRenameTag(oldName, newName) {
			this.$store.dispatch(actions.RENAME_TAG, { oldName, newName });
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
