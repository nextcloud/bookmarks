<!--
  - Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
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
		<SupportThisProjectModal />
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
import SupportThisProjectModal from './SupportThisProjectModal.vue'
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
		SupportThisProjectModal,
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
			return (
				this.$route.name === privateRoutes.FOLDER
				|| this.$route.name === privateRoutes.HOME
			)
		},
		isSharedWithYou() {
			return this.$route.name === privateRoutes.SHARED_FOLDERS
		},
		isTrashbin() {
			const folder = this.$store.getters.getFolder(
				this.$route.params.folder || '-1',
			)[0]
			return (
				this.$route.name === privateRoutes.TRASHBIN
				|| (this.$route.name === privateRoutes.FOLDER
					&& folder.softDeleted)
			)
		},
		showFolderOverview() {
			return (
				this.isFolderView
				&& !this.smallScreen
				&& this.folders.length
				&& !this.isTrashbin
				&& !this.isSharedWithYou
			)
		},
	},
	watch: {
		$route: 'onRoute',
		async showFolderOverview(value) {
			if (!this.initialLoad && value) {
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

		// set loading indicator
		this.$store.commit(mutations.FETCH_START, { type: 'bookmarks' })

		await this.reloadSettings()

		this.reloadSharedFolders()
		this.reloadCount()
		this.reloadTags()

		await Promise.all([this.reloadFolders(), this.reloadDeletedFolders()])

		this.onRoute()

		const currentUser = getCurrentUser()
		if (currentUser.isAdmin) {
			const scrapingEnabled = await this.getSettingValue(
				'privacy.enableScraping',
			)
			const alreadyShown
				= window.localStorage
				&& window.localStorage.getItem('bookmarks.scrapingNoteShown')
			if (scrapingEnabled !== 'true' && alreadyShown !== 'true') {
				window.localStorage
					&& window.localStorage.setItem(
						'bookmarks.scrapingNoteShown',
						'true',
					)
				this.$store.commit(
					mutations.SET_NOTIFICATION,
					t(
						'bookmarks',
						'Network access is disabled by default. Go to administrator settings for the bookmarks app to allow fetching previews and favicons.',
					),
				)
			}
		}
		this.initialLoad = false
	},

	methods: {
		async onRoute() {
			const route = this.$route
			this.$store.commit(mutations.RESET_SELECTION)
			if (typeof this.$store.state.loading.bookmarks === 'function') {
				this.$store.state.loading.bookmarks()
			}
			switch (route.name) {
			case privateRoutes.HOME:
				this.$store.dispatch(actions.FILTER_BY_FOLDER, {
					folder: '-1',
				})
				break
			case privateRoutes.RECENT:
				this.$store.dispatch(actions.FILTER_BY_RECENT)
				break
			case privateRoutes.FREQUENT:
				this.$store.dispatch(actions.FILTER_BY_FREQUENT)
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
			case privateRoutes.TRASHBIN:
				this.$store.commit(mutations.FETCH_END, 'bookmarks')
				await this.$store.dispatch(actions.LOAD_DELETED_BOOKMARKS)
				await this.$store.dispatch(actions.LOAD_DELETED_FOLDERS)
				break
			case privateRoutes.SHARED_FOLDERS:
				this.$store.dispatch(actions.LOAD_SHARED_FOLDERS)
				await this.$store.dispatch(actions.RELOAD_VIEW)
				await this.$store.commit(mutations.REMOVE_ALL_BOOKMARKS)
				break
			case privateRoutes.BOOKMARK:
				await this.$store.dispatch(
					actions.LOAD_BOOKMARK,
					route.params.bookmark,
				)
				this.$store.dispatch(
					actions.OPEN_BOOKMARK,
					route.params.bookmark,
				)
				this.$store.commit(mutations.FETCH_END, 'bookmarks')
				break
			case privateRoutes.FOLDER:
				// eslint-disable-next-line no-case-declarations
				const folder = this.$store.getters.getFolder(
					route.params.folder,
				)[0]
				this.$store.dispatch(actions.FILTER_BY_FOLDER, {
					folder: route.params.folder,
					softDeleted: folder.softDeleted,
				})
				break
			case privateRoutes.TAGS:
				this.$store.dispatch(
					actions.FILTER_BY_TAGS,
					route.params.tags.split(','),
				)
				break
			case privateRoutes.SEARCH:
				this.$store.dispatch(actions.FILTER_BY_SEARCH, {
					search: route.params.search,
					folder: route.params.folder || -1,
				})
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
		async reloadDeletedFolders() {
			return this.$store.dispatch(actions.LOAD_DELETED_FOLDERS)
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
				this.$store.dispatch(actions.COUNT_DELETED),
				this.$store.dispatch(actions.COUNT_DUPLICATED),
				this.$store.dispatch(actions.COUNT_ALL_CLICKS),
				this.$store.dispatch(actions.COUNT_WITH_CLICKS),
			])
		},

		onSearch(search) {
			this.$router.push({
				name: privateRoutes.SEARCH,
				params: { search },
			})
		},

		onResetSearch() {
			this.$router.push({ name: privateRoutes.HOME })
		},

		async getSettingValue(setting) {
			const resDocument = await new Promise((resolve, reject) =>
				OCP.AppConfig.getValue('bookmarks', setting, null, {
					success: resolve,
					error: reject,
				}),
			)
			if (typeof resDocument.data !== 'undefined') {
				return resDocument.data
			}
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
