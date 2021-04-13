<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<AppNavigation class="navigation">
		<template #list>
			<AppNavigationItem key="menu-home"
				:to="{ name: routes.HOME }"
				icon="icon-home"
				:title="t('bookmarks', 'All bookmarks')"
				:exact="true">
				<AppNavigationCounter slot="counter">
					{{ allBookmarksCount }}
				</AppNavigationCounter>
			</AppNavigationItem>
			<AppNavigationItem key="menu-recent"
				:to="{ name: routes.RECENT }"
				:title="t('bookmarks', 'Recent')">
				<HistoryIcon slot="icon" :size="18" :fill-color="colorMainText" />
			</AppNavigationItem>
			<AppNavigationItem
				key="menu-archived"
				:to="{ name: routes.ARCHIVED }"
				:title="t('bookmarks', 'Archived')">
				<ArchiveArrowDownIcon slot="icon" :size="18" :fill-color="colorMainText" />
				<AppNavigationCounter v-show="Boolean(archivedBookmarksCount)" slot="counter">
					{{ archivedBookmarksCount }}
				</AppNavigationCounter>
			</AppNavigationItem>
			<AppNavigationItem v-if="unavailableBookmarksCount > 0"
				key="menu-unavailable"
				:to="{ name: routes.UNAVAILABLE }"
				:title="t('bookmarks', 'Broken links')">
				<LinkVariantOffIcon slot="icon" :size="18" :fill-color="colorMainText" />
				<AppNavigationCounter slot="counter">
					{{ unavailableBookmarksCount }}
				</AppNavigationCounter>
			</AppNavigationItem>
			<AppNavigationSpacer />
			<AppNavigationNewItem key="menu-new-tag"
				:title="t('bookmarks', 'New tag')"
				@new-item="onNewTag">
				<TagPlusIcon slot="icon" :size="18" :fill-color="colorMainText" />
			</AppNavigationNewItem>
			<template v-if="Boolean(tags.length)">
				<AppNavigationItem v-for="tag in tags"
					:key="'tag-'+tag.name"
					v-drop-target="{allow: (e) => allowDropOnTag(tag.name, e), drop: (e) => onDropOnTag(tag.name, e)}"
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
						<ActionButton icon="icon-delete" :close-after-click="true" @click="onDeleteTag(tag.name)">
							{{ t('bookmarks', 'Delete') }}
						</ActionButton>
					</template>
				</AppNavigationItem>
				<AppNavigationItem key="menu-untagged"
					:to="{ name: routes.UNTAGGED }"
					:title="t('bookmarks', 'Untagged')">
					<TagOffIcon slot="icon" :size="18" :fill-color="colorMainText" />
				</AppNavigationItem>
			</template>
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
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import AppNavigationNewItem from '@nextcloud/vue/dist/Components/AppNavigationNewItem'
import AppNavigationCounter from '@nextcloud/vue/dist/Components/AppNavigationCounter'
import AppNavigationSettings from '@nextcloud/vue/dist/Components/AppNavigationSettings'
import AppNavigationSpacer from '@nextcloud/vue/dist/Components/AppNavigationSpacer'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import HistoryIcon from 'vue-material-design-icons/History'
import TagOffIcon from 'vue-material-design-icons/TagOff'
import LinkVariantOffIcon from 'vue-material-design-icons/LinkVariantOff'
import TagPlusIcon from 'vue-material-design-icons/TagPlus'
import ArchiveArrowDownIcon from 'vue-material-design-icons/ArchiveArrowDown'
import ProgressBar from 'vue-simple-progress'
import Settings from './Settings'
import { actions, mutations } from '../store/'

export default {
	name: 'Navigation',
	components: {
		AppNavigation,
		AppNavigationItem,
		AppNavigationNewItem,
		AppNavigationCounter,
		AppNavigationSettings,
		AppNavigationSpacer,
		ActionButton,
		Settings,
		ProgressBar,
		HistoryIcon,
		TagOffIcon,
		LinkVariantOffIcon,
		TagPlusIcon,
		ArchiveArrowDownIcon,
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
		unavailableBookmarksCount() {
			return this.$store.state.unavailableCount
		},
		archivedBookmarksCount() {
			return this.$store.state.archivedCount
		},
		bookmarksLimit() {
			return this.$store.state.settings.limit
		},
	},

	created() {
	},

	methods: {
		onDeleteTag(tag) {
			this.$store.dispatch(actions.DELETE_TAG, tag)
		},
		onRenameTag(oldName, newName) {
			this.$store.dispatch(actions.RENAME_TAG, { oldName, newName })
		},
		onNewTag(tagName) {
			this.$store.commit(mutations.ADD_TAG, tagName)
		},
		allowDropOnTag(tagName) {
			return !this.$store.state.selection.folders.length
		},
		onDropOnTag(tagName) {
			this.$store.dispatch(actions.TAG_SELECTION, { tags: [tagName], originalTags: [] })
		},
	},
}
</script>
<style>
.navigation .material-design-icon {
	position: relative;
	top: 4px;
}

.navigation .dropTarget {
	background: var(--color-primary-element-light);
}
</style>
