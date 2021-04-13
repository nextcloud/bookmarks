<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<Content app-name="bookmarks">
		<AppContent>
			<Controls />
			<BookmarksList :loading="!!loading.bookmarks" :bookmarks="bookmarks" />
		</AppContent>
		<BookmarkContent />
	</Content>
</template>

<script>
import Content from '@nextcloud/vue/dist/Components/Content'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import BookmarksList from './BookmarksList'
import Controls from './Controls'
import { actions, mutations } from '../store/'
import BookmarkContent from './BookmarkContent'

export default {
	name: 'ViewPublic',
	components: {
		BookmarkContent,
		Content,
		AppContent,
		Controls,
		BookmarksList,
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
		loading() {
			return this.$store.state.loading
		},
	},

	watch: {
		$route: 'onRoute',
	},

	async created() {
		// this.search = new OCA.Search(this.onSearch, this.onResetSearch)
		this.$store.commit(mutations.SET_AUTH_TOKEN, this.$route.params.token)
		// set loading indicator
		this.$store.commit(mutations.FETCH_START, { type: 'bookmarks' })
		await Promise.all([
			// this.reloadTags(),
			this.reloadFolders(),
		])
		this.onRoute()
	},

	methods: {
		async onRoute() {
			const route = this.$route
			switch (route.name) {
			case this.routes.HOME:
				return this.$store.dispatch(actions.FILTER_BY_FOLDER, '-1')
			case this.routes.RECENT:
				return this.$store.dispatch(actions.FILTER_BY_RECENT)
			case this.routes.UNTAGGED:
				return this.$store.dispatch(actions.FILTER_BY_UNTAGGED)
			case this.routes.FOLDER:
				return this.$store.dispatch(actions.FILTER_BY_FOLDER, route.params.folder)
			case this.routes.TAGS:
				return this.$store.dispatch(
					actions.FILTER_BY_TAGS,
					route.params.tags.split(',')
				)
			case this.routes.SEARCH:
				return this.$store.dispatch(actions.FILTER_BY_SEARCH, route.params.search)
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

		onSearch(search) {
			this.$router.push({ name: this.routes.SEARCH, params: { search } })
		},

		onResetSearch() {
			this.$router.push({ name: this.routes.HOME })
		},
	},
}
</script>
<style>
	#app-content {
		max-width: 100%;
	}
</style>
