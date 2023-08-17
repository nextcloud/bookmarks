<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<NcContent app-name="bookmarks">
		<Navigation />
		<NcAppContent :show-details.sync="showDetails">
			<template v-if="showFolderOverview" #list>
				<FolderOverview :show-details.sync="showDetails" />
			</template>
			<template #default>
				<Controls />
				<BookmarksList />
			</template>
		</NcAppContent>
		<SidebarBookmark />
		<SidebarFolder />
		<MoveDialog />
		<CopyDialog />
		<LoadingModal />
		<BookmarkContent />
		<WhatsnewModal />
	</NcContent>
</template>

<script>
import { NcContent, NcAppContent } from '@nextcloud/vue'
import Navigation from './Navigation.vue'
import FolderOverview from './FolderOverview.vue'
import BookmarksList from './BookmarksList.vue'
import Controls from './Controls.vue'
import SidebarBookmark from './SidebarBookmark.vue'
import SidebarFolder from './SidebarFolder.vue'
import MoveDialog from './MoveDialog.vue'
import CopyDialog from './CopyDialog.vue'
import { privateRoutes } from '../router.js'
import { actions, mutations } from '../store/index.js'
import LoadingModal from './LoadingModal.vue'
import BookmarkContent from './BookmarkContent.vue'
import WhatsnewModal from './WhatsnewModal.vue'
import { getCurrentUser } from '@nextcloud/auth'

export default {
	name: 'ViewPrivate',
	components: {
		BookmarkContent,
		LoadingModal,
		Navigation,
		NcContent,
		NcAppContent,
		FolderOverview,
		Controls,
		BookmarksList,
		SidebarBookmark,
		SidebarFolder,
		MoveDialog,
		CopyDialog,
		WhatsnewModal,
	},
	data() {
		return {
			newBookmark: false,
			showDetails: false,
			smallScreen: false,
			showWhatsnew: false,
			initialLoad: false,
		}
	},
	computed: {
		folders() {
			return this.$store.state.folders
		},
		tags() {
			return this.$store.state.tags
		},
		isFolderView() {
			return this.$route.name === privateRoutes.FOLDER || this.$route.name === privateRoutes.HOME
		},
		showFolderOverview() {
			return this.isFolderView && !this.smallScreen && this.folders.length
		},
	},
	watch: {
		$route: 'onRoute',
		async showFolderOverview(value) {
			if (!this.initialLoad) {
				// hack to make bookmarkslist rerender
				await this.$store.dispatch(actions.RELOAD_VIEW)
			}
		},
	},
	async created() {
		this.initialLoad = true
		const mediaQuery = window.matchMedia('(max-width: 1024px)')
		this.smallScreen = mediaQuery.matches
		mediaQuery.addEventListener('change', this.onWindowFormatChange)

	  if (OCA.Search) {
	    // legacy search pre nc v20
			this.search = new window.OCA.Search(this.onSearch, this.onResetSearch)
		}

		// set loading indicator
		this.$store.commit(mutations.FETCH_START, { type: 'bookmarks' })

		await this.reloadSettings()

		this.onRoute()
		this.reloadFolders()
		this.reloadSharedFolders()
		this.reloadCount()
		this.reloadTags()

		const currentUser = getCurrentUser()
		if (currentUser.isAdmin) {
			const scrapingEnabled = await this.getSettingValue('privacy.enableScraping')
			const alreadyShown = window.localStorage && window.localStorage.getItem('bookmarks.scrapingNoteShown')
			if (scrapingEnabled !== 'true' && alreadyShown !== 'true') {
				window.localStorage && window.localStorage.setItem('bookmarks.scrapingNoteShown', 'true')
				this.$store.commit(mutations.SET_NOTIFICATION, t('bookmarks', 'Network access is disabled by default. Go to administrator settings for the bookmarks app to allow fetching previews and favicons.'))
			}
		}
		this.initialLoad = false
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
			case privateRoutes.DUPLICATED:
				this.$store.dispatch(actions.FILTER_BY_DUPLICATED)
				break
			case privateRoutes.SHARED_FOLDERS:
				await this.$store.dispatch(actions.LOAD_SHARED_FOLDERS)
				this.$store.commit(mutations.REMOVE_ALL_BOOKMARKS)
				this.$store.commit(mutations.FETCH_END, 'bookmarks')
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
		async reloadSharedFolders() {
			return this.$store.dispatch(actions.LOAD_SHARED_FOLDERS)
		},
		async reloadSettings() {
			return this.$store.dispatch(actions.LOAD_SETTINGS)
		},
		async reloadCount() {
			return Promise.all([
				this.$store.dispatch(actions.COUNT_BOOKMARKS, -1),
				this.$store.dispatch(actions.COUNT_UNAVAILABLE),
				this.$store.dispatch(actions.COUNT_ARCHIVED),
				this.$store.dispatch(actions.COUNT_DUPLICATED),
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

		onWindowFormatChange(mediaQuery) {
			this.smallScreen = mediaQuery.matches
		},
	},
}
</script>
<style>
.app-content {
	position: relative !important;
}
</style>
