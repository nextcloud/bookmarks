<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<DashboardWidget :items="items" :loading="loading">
		<template v-slot:empty-content>
			{{ t('bookmarks', 'No bookmarks here') }}
		</template>
	</DashboardWidget>
</template>

<script>
import { DashboardWidget } from '@nextcloud/vue-dashboard'
import { generateUrl } from '@nextcloud/router'
import { actions } from '../store'
export default {
	name: 'Dashboard',
	components: { DashboardWidget },
	computed: {
		loading() {
			return this.$store.state.loading.bookmarks
		},
		items() {
			return this.$store.state.bookmarks.map(bookmark => ({
				id: bookmark.id,
				targetUrl: bookmark.url,
				avatarUrl: generateUrl(`/apps/bookmarks/bookmark/${bookmark.id}/favicon`),
				mainText: bookmark.title,
				subText: bookmark.url,
			}))
		},
	},
	async mounted() {
		await this.$store.dispatch(actions.FILTER_BY_RECENT)
		await this.$store.dispatch(actions.FETCH_PAGE)
	},
}
</script>
