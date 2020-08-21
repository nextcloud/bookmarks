<template>
	<AppNavigation class="navigation">
		<AppNavigationNew
			v-if="!isPublic"
			:text="t('bookmarks', 'New Bookmark')"
			:disabled="false"
			button-class="icon-add"
			@click="onNewBookmark" />
		<template #list>
			<AppNavigationItem key="menu-home"
				:to="{ name: routes.HOME }"
				icon="icon-home"
				:title="t('bookmarks', 'All Bookmarks')"
				:exact="true">
				<AppNavigationCounter slot="counter">
					{{ allBookmarksCount }}
				</AppNavigationCounter>
			</AppNavigationItem>
			<AppNavigationItem key="menu-recent"
				:to="{ name: routes.RECENT }"
				:title="t('bookmarks', 'Recent Bookmarks')">
				<HistoryIcon slot="icon" :size="18" />
			</AppNavigationItem>
			<AppNavigationItem key="menu-unavailable"
				:to="{ name: routes.UNAVAILABLE }"
				:title="t('bookmarks', 'Broken links')">
				<LinkVariantOffIcon slot="icon" :size="18" />
			</AppNavigationItem>
			<AppNavigationSpacer />
			<AppNavigationItem key="menu-tags"
				:to="{ name: routes.TAGS }"
				:exact="true"
				:title="t('bookmarks', 'Filter tags')">
				<TagMultipleIcon slot="icon" :size="18" />
			</AppNavigationItem>
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
			<AppNavigationItem key="menu-untagged"
				:to="{ name: routes.UNTAGGED }"
				:title="t('bookmarks', 'Untagged')">
				<TagOffIcon slot="icon" :size="18" />
			</AppNavigationItem>
			<template v-if="Number(bookmarksLimit) > 0">
				<AppNavigationSpacer />
				<AppNavigationItem :pinned="true" icon="icon-quota" :title="t('bookmarks', '{used} bookmarks of {available} available', {used: allBookmarksCount, available: bookmarksLimit})">
					<ProgressBar :val="allBookmarksCount" :max="bookmarksLimit" />
				</AppNavigationItem>
			</template>
		</template>
		<template #footer>
			<AppNavigationSettings v-if="!isPublic">
				<Settings />
			</AppNavigationSettings>
		</template>
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
import HistoryIcon from 'vue-material-design-icons/History'
import TagOffIcon from 'vue-material-design-icons/TagOff'
import LinkVariantOffIcon from 'vue-material-design-icons/LinkVariantOff'
import TagMultipleIcon from 'vue-material-design-icons/TagMultiple'
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
		HistoryIcon,
		TagOffIcon,
		LinkVariantOffIcon,
		TagMultipleIcon,
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
<style>
.navigation .material-design-icon {
	position: relative;
	top: 2px;
}
</style>
