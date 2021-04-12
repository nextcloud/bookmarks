<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<Content app-name="bookmarks">
		<Navigation />
		<AppContent>
			<Controls />
			<BookmarksList :bookmarks="bookmarks" />
			<BookmarkContent />
		</AppContent>
		<SidebarBookmark />
		<SidebarFolder />
		<MoveDialog />
		<LoadingModal />
	</Content>
</template>

<script>
import Content from '@nextcloud/vue/dist/Components/Content'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import Navigation from './Navigation'
import BookmarksList from './BookmarksList'
import Controls from './Controls'
import SidebarBookmark from './SidebarBookmark'
import SidebarFolder from './SidebarFolder'
import MoveDialog from './MoveDialog'
import { privateRoutes } from '../router'
import { actions, mutations } from '../store/'
import LoadingModal from './LoadingModal'
import BookmarkContent from './BookmarkContent'
import { getCurrentUser } from '@nextcloud/auth'

export default {
	name: 'ViewPrivate',
	components: {
		BookmarkContent,
		LoadingModal,
		Navigation,
		Content,
		AppContent,
		Controls,
		BookmarksList,
		SidebarBookmark,
		SidebarFolder,
		MoveDialog,
	},
	data() {
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
	},

	watch: {
		$route: 'onRoute',
	},

	async created() {
	  if (OCA.Search) {
	    // legacy search pre nc v20
			this.search = new window.OCA.Search(this.onSearch, this.onResetSearch)
		}
		// set loading indicator
		this.$store.commit(mutations.FETCH_START, { type: 'bookmarks' })

		this.reloadTags()
		this.reloadCount()
		await Promise.all([
			this.reloadSettings(),
			this.reloadFolders(),
		])
		this.onRoute()

		const currentUser = getCurrentUser()
		if (currentUser.isAdmin) {
			const scrapingEnabled = await this.getSettingValue('privacy.enableScraping')
			if (scrapingEnabled !== 'true') {
				this.$store.commit(mutations.SET_NOTIFICATION, t('bookmarks', 'Network access is disabled by default. Go to administrator settings for the bookmarks app to allow fetching previews and favicons.'))
			}
		}
	},

	methods: {
		async onRoute() {
			const route = this.$route
			this.$store.commit(mutations.RESET_SELECTION)
			switch (route.name) {
			case privateRoutes.HOME:
				this.$store.dispatch(actions.FILTER_BY_FOLDER, '-1')
				break
			case privateRoutes.RECENT:
				this.$store.dispatch(actions.FILTER_BY_RECENT)
				break
			case privateRoutes.UNTAGGED:
				this.$store.dispatch(actions.FILTER_BY_UNTAGGED)
				break
			case privateRoutes.UNAVAILABLE:
				this.$store.dispatch(actions.FILTER_BY_UNAVAILABLE)
				break
			case privateRoutes.ARCHIVED:
				this.$store.dispatch(actions.FILTER_BY_ARCHIVED)
				break
			case privateRoutes.BOOKMARK:
				await this.$store.dispatch(actions.LOAD_BOOKMARK, route.params.bookmark)
				this.$store.dispatch(actions.OPEN_BOOKMARK, route.params.bookmark)
				this.$store.commit(mutations.FETCH_END, 'bookmarks')
				break
			case privateRoutes.FOLDER:
				this.$store.dispatch(actions.FILTER_BY_FOLDER, route.params.folder)
				break
			case privateRoutes.TAGS:
				this.$store.dispatch(
					actions.FILTER_BY_TAGS,
					route.params.tags.split(',')
				)
				break
			case privateRoutes.SEARCH:
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
		async reloadCount() {
			return Promise.all([
				this.$store.dispatch(actions.COUNT_BOOKMARKS, -1),
				this.$store.dispatch(actions.COUNT_UNAVAILABLE),
				this.$store.dispatch(actions.COUNT_ARCHIVED),
			])
		},

		onSearch(search) {
			this.$router.push({ name: privateRoutes.SEARCH, params: { search } })
		},

		onResetSearch() {
			this.$router.push({ name: privateRoutes.HOME })
		},

		async getSettingValue(setting) {
			const resDocument = await new Promise((resolve, reject) =>
				OCP.AppConfig.getValue('bookmarks', setting, null, {
					success: resolve,
					error: reject,
				})
			)
			if (resDocument.querySelector('status').textContent !== 'ok') {
				console.error('Failed request', resDocument)
				return
			}
			const dataEl = resDocument.querySelector('data')
			return dataEl.firstElementChild.textContent
		},
	},
}
</script>
<style>
#app-content {
	max-width: calc(100vw - 300px);
	min-width: 0;
}

@media only screen and (max-width: 768px) {
	#app-content {
		max-width: 100%;
	}
}
</style>
