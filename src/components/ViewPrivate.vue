<template>
	<Content app-name="bookmarks">
		<Navigation />
		<AppContent>
			<Breadcrumbs />
			<BookmarksList :loading="!!loading.bookmarks" :bookmarks="bookmarks" />
		</AppContent>
		<SidebarBookmark />
		<SidebarFolder />
		<MoveDialog />
	</Content>
</template>

<script>
	import Content from '@nextcloud/vue/dist/Components/Content'
	import AppContent from '@nextcloud/vue/dist/Components/AppContent'
	import Navigation from './Navigation'
	import BookmarksList from './BookmarksList'
	import Breadcrumbs from './Breadcrumbs'
	import SidebarBookmark from './SidebarBookmark'
	import SidebarFolder from './SidebarFolder'
	import MoveDialog from './MoveDialog'
	import { actions, mutations } from '../store/'

	export default {
		name: 'ViewPrivate',
		components: {
			Navigation,
			Content,
			AppContent,
			Breadcrumbs,
			BookmarksList,
			SidebarBookmark,
			SidebarFolder,
			MoveDialog,
	},
	data: function() {
		return {
			newBookmark: false,
		}
	},
	computed: {
		bookmarks() {
			return this.$store.state.bookmarks
		},
		folders() {
			return this.$store.state.folders
		},
		tags() {
			return this.$store.state.tags
		},
		loading() {
			return this.$store.state.loading
		},
	},

	watch: {
		$route: 'onRoute',
	},

	async created() {
		document.addEventListener('scroll', this.onScroll)
		this.search = new OCA.Search(this.onSearch, this.onResetSearch)
		// set loading indicator
		this.$store.commit(mutations.FETCH_START, { type: 'bookmarks' })
		await Promise.all([
			this.reloadSettings(),
			this.reloadTags(),
			this.reloadFolders(),
		])
		this.onRoute()
	},

	methods: {
		async onRoute() {
			const route = this.$route
			switch (route.name) {
			case 'home':
				this.$store.dispatch(actions.FILTER_BY_FOLDER, '-1')
				break
			case 'recent':
				this.$store.dispatch(actions.FILTER_BY_RECENT)
				break
			case 'untagged':
				this.$store.dispatch(actions.FILTER_BY_UNTAGGED)
				break
			case 'folder':
				this.$store.dispatch(actions.FILTER_BY_FOLDER, route.params.folder)
				break
			case 'tags':
				this.$store.dispatch(
					actions.FILTER_BY_TAGS,
					route.params.tags.split(',')
				)
				break
			case 'search':
				this.$store.dispatch(actions.FILTER_BY_SEARCH, route.params.search)
				break
			default:
				throw new Error('Nothing here. Move along.')
			}
		},

		async reloadTags() {
			return this.$store.dispatch(actions.LOAD_TAGS)
		},
		async reloadFolders() {
			return this.$store.dispatch(actions.LOAD_FOLDERS)
		},
		async reloadSettings() {
			return this.$store.dispatch(actions.LOAD_SETTINGS)
		},

		onSearch(search) {
			this.$router.push({ name: 'search', params: { search } })
		},

		onResetSearch() {
			this.$router.push({ name: 'home' })
		},

		onScroll() {
			if (
				document.body.scrollHeight
				< window.scrollY + window.innerHeight + 500
			) {
				this.$store.dispatch(actions.FETCH_PAGE)
			}
		},
	},
}
</script>
<style>
#app-content {
	max-width: calc(100vw - 300px);
}

@media only screen and (max-width: 768px) {
	#app-content {
		max-width: 100%;
	}
}
</style>
