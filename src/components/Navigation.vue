<template>
	<AppNavigation>
		<AppNavigationNew
			v-if="!isPublic"
			:text="t('bookmarks', 'New Bookmark')"
			:disabled="false"
			button-class="icon-add"
			@click="onNewBookmark" />
		<ul>
			<AppNavigationItem key="menu-home"
				:to="{ name: routes.HOME }"
				icon="icon-home"
				:title="t('bookmarks', 'All Bookmarks')">
				<AppNavigationCounter>{{ allBookmarksCount }}</AppNavigationCounter>
			</AppNavigationItem>
			<AppNavigationItem key="menu-recent"
				:to="{ name: routes.RECENT }"
				icon="icon-category-monitoring"
				:title="t('bookmarks', 'Recent Bookmarks')" />
			<AppNavigationItem key="menu-untagged"
				:to="{ name: routes.UNTAGGED }"
				icon="icon-category-disabled"
				:title="t('bookmarks', 'Untagged')" />
			<AppNavigationSpacer />
			<AppNavigationItem v-for="tag in tags"
				:key="'tag-'+tag.name"
				icon="icon-tag"
				:to="tag.route"
				:force-menu="true"
				:edit-label="t('bookmarks', 'Rename')"
				:editable="!isPublic"
				:title="tag.name"
				@update:title="onRenameTag(tag.name, $event)">
				<AppNavigationCounter slot="counter">
					{{ tag.count }}
				</AppNavigationCounter>
				<template v-if="!isPublic" slot="actions">
					<ActionButton icon="icon-delete" @click="onDeleteTag(tag.name)">
						{{ t('bookmarks', 'Delete') }}
					</ActionButton>
				</template>
			</AppNavigationItem>
			<template v-if="Number(allBookmarksCount) > 0">
				<AppNavigationSpacer />
				<AppNavigationItem :pinned="true" icon="icon-quota" :title="t('bookmarks', '{used} bookmarks of {available} available', {used: allBookmarksCount, available: bookmarksLimit})">
					<ProgressBar :val="allBookmarksCount" :max="bookmarksLimit" />
				</AppNavigationItem>
			</template>
		</ul>
		<AppNavigationSettings v-if="!isPublic">
			<Settings />
		</AppNavigationSettings>
	</AppNavigation>
</template>

<script>
	import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
	import AppNavigationNew from '@nextcloud/vue/dist/Components/AppNavigationNew'
	import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
	import AppNavigationCounter from '@nextcloud/vue/dist/Components/AppNavigationCounter'
	import AppNavigationSettings from '@nextcloud/vue/dist/Components/AppNavigationSettings'
	import AppNavigationSpacer from '@nextcloud/vue/dist/Components/AppNavigationSpacer'
	import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
	import ProgressBar from 'vue-simple-progress'
	import Settings from './Settings'
	import { actions, mutations } from '../store/'

	export default {
	name: 'Navigation',
	components: {
		AppNavigation,
		AppNavigationNew,
		AppNavigationItem,
		AppNavigationCounter,
		AppNavigationSettings,
		AppNavigationSpacer,
		ActionButton,
		Settings,
		ProgressBar,
	},
	data() {
		return {}
	},
	computed: {
		tags() {
			return this.$store.state.tags.map(tag => ({
				route: { name: this.routes.TAGS, params: { tags: tag.name } },
				name: tag.name,
				count: tag.count,
			}))
		},
		allBookmarksCount() {
			return this.$store.state.countsByFolder[-1]
		},
		bookmarksLimit() {
			return this.$store.state.settings.limit
		},
	},

	created() {
	},

	methods: {
		onNewBookmark() {
			this.$store.commit(
				mutations.DISPLAY_NEW_BOOKMARK,
				!this.$store.state.displayNewBookmark
			)
		},
		onDeleteTag(tag) {
			this.$store.dispatch(actions.DELETE_TAG, tag)
		},
		onRenameTag(oldName, newName) {
			this.$store.dispatch(actions.RENAME_TAG, { oldName, newName })
		},
	},
}
</script>
