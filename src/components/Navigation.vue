<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<NcAppNavigation class="navigation">
		<template #list>
			<NcAppNavigationItem key="menu-home"
				:to="{ name: routes.HOME }"
				:name="t('bookmarks', 'All bookmarks')"
				:exact="true">
				<HomeIcon slot="icon" />
				<NcCounterBubble slot="counter">
					{{ allBookmarksCount | largeNumbers }}
				</NcCounterBubble>
			</NcAppNavigationItem>
			<NcAppNavigationItem key="menu-recent"
				:to="{ name: routes.RECENT }"
				:name="t('bookmarks', 'Recent')">
				<HistoryIcon slot="icon" />
			</NcAppNavigationItem>
			<NcAppNavigationItem key="menu-shared-folders"
				:to="{ name: routes.SHARED_FOLDERS }"
				:name="t('bookmarks', 'Shared with you')">
				<ShareVariantIcon slot="icon" />
				<NcCounterBubble v-show="Boolean(sharedFoldersCount)" slot="counter">
					{{ sharedFoldersCount | largeNumbers }}
				</NcCounterBubble>
			</NcAppNavigationItem>
			<NcAppNavigationItem key="menu-archived"
				:to="{ name: routes.ARCHIVED }"
				:name="t('bookmarks', 'Files')">
				<FileDocumentMultipleIcon slot="icon" />
				<NcCounterBubble v-show="Boolean(archivedBookmarksCount)" slot="counter">
					{{ archivedBookmarksCount }}
				</NcCounterBubble>
			</NcAppNavigationItem>
			<NcAppNavigationItem key="menu-duplicated"
				:to="{ name: routes.DUPLICATED }"
				:name="t('bookmarks', 'Duplicates')">
				<VectorLinkIcon slot="icon" />
				<NcCounterBubble v-show="Boolean(duplicatedBookmarksCount)" slot="counter">
					{{ duplicatedBookmarksCount | largeNumbers }}
				</NcCounterBubble>
			</NcAppNavigationItem>
			<NcAppNavigationItem key="menu-unavailable"
				:to="{ name: routes.UNAVAILABLE }"
				:name="t('bookmarks', 'Broken links')">
				<LinkVariantOffIcon slot="icon" />
				<NcCounterBubble v-show="Boolean(unavailableBookmarksCount)" slot="counter">
					{{ unavailableBookmarksCount | largeNumbers }}
				</NcCounterBubble>
			</NcAppNavigationItem>
			<NcAppNavigationSpacer />
			<NcAppNavigationNewItem key="menu-new-tag"
				:name="t('bookmarks', 'New tag')"
				@new-item="onNewTag">
				<TagPlusIcon slot="icon" />
			</NcAppNavigationNewItem>
			<template v-if="Boolean(tags.length)">
				<NcAppNavigationItem key="menu-tags"
					:name="t('bookmarks', 'Search tags')"
					@click="onSearchTags">
					<TagMultipleIcon slot="icon" />
				</NcAppNavigationItem>
				<NcAppNavigationItem v-for="tag in tags"
					:key="'tag-'+tag.name"
					v-drop-target="{allow: (e) => allowDropOnTag(tag.name, e), drop: (e) => onDropOnTag(tag.name, e)}"
					:to="tag.route"
					:force-menu="true"
					:edit-label="t('bookmarks', 'Rename')"
					:editable="!isPublic"
					:name="tag.name"
					@update:name="onRenameTag(tag.name, $event)">
					<TagIcon slot="icon" />
					<NcCounterBubble slot="counter">
						{{ tag.count | largeNumbers }}
					</NcCounterBubble>
					<template v-if="!isPublic" slot="actions">
						<NcActionButton :close-after-click="true" @click="onDeleteTag(tag.name)">
							<template #icon>
								<DeleteIcon />
							</template>
							{{ t('bookmarks', 'Delete') }}
						</NcActionButton>
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem key="menu-untagged"
					:to="{ name: routes.UNTAGGED }"
					:name="t('bookmarks', 'Untagged')">
					<TagOffIcon slot="icon" />
				</NcAppNavigationItem>
			</template>
			<template v-if="Number(bookmarksLimit) > 0">
				<NcAppNavigationSpacer />
				<NcAppNavigationItem :pinned="true" :name="t('bookmarks', '{used} bookmarks of {available} available', {used: allBookmarksCount, available: bookmarksLimit})">
					<template #icon>
						<GaugeIcon />
					</template>
					<ProgressBar :val="allBookmarksCount" :max="bookmarksLimit" />
				</NcAppNavigationItem>
			</template>
		</template>
		<template #footer>
			<NcAppNavigationItem :name="t('bookmarks', 'Settings')" @click="settingsOpen = !settingsOpen"><template #icon><CogIcon /></template></NcAppNavigationItem>
			<Settings :settingsOpen.sync="settingsOpen" />
		</template>
	</NcAppNavigation>
</template>

<script>
import { NcActionButton, NcAppNavigation, NcAppNavigationItem, NcAppNavigationNewItem, NcCounterBubble, NcAppNavigationSettings, NcAppNavigationSpacer } from '@nextcloud/vue'
import HomeIcon from 'vue-material-design-icons/Home.vue'
import HistoryIcon from 'vue-material-design-icons/History.vue'
import TagOffIcon from 'vue-material-design-icons/TagOff.vue'
import LinkVariantOffIcon from 'vue-material-design-icons/LinkVariantOff.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'
import FileDocumentMultipleIcon from 'vue-material-design-icons/FileDocumentMultiple.vue'
import TagPlusIcon from 'vue-material-design-icons/TagPlus.vue'
import TagMultipleIcon from 'vue-material-design-icons/TagMultiple.vue'
import VectorLinkIcon from 'vue-material-design-icons/VectorLink.vue'
import TagIcon from 'vue-material-design-icons/Tag.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import GaugeIcon from 'vue-material-design-icons/Gauge.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import ProgressBar from 'vue-simple-progress'
import Settings from './Settings.vue'
import { actions, mutations } from '../store/index.js'

export default {
	name: 'Navigation',
	components: {
		NcAppNavigation,
		NcAppNavigationItem,
		NcAppNavigationNewItem,
		NcCounterBubble,
		NcAppNavigationSpacer,
		NcActionButton,
		Settings,
		ProgressBar,
		HistoryIcon,
		TagOffIcon,
		LinkVariantOffIcon,
		TagPlusIcon,
		TagMultipleIcon,
		FileDocumentMultipleIcon,
		ShareVariantIcon,
		VectorLinkIcon,
		HomeIcon,
		TagIcon,
		DeleteIcon,
		GaugeIcon,
		CogIcon,
	},

	filters: {
		largeNumbers(num) {
			return num >= 1000 ? (Math.round(num / 100) / 10) + 'K' : num
		},
	},
	data() {
		return {
			settingsOpen: false,
		}
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
		sharedFoldersCount() {
			return Object.keys(this.$store.state.sharedFoldersById).length
		},
		archivedBookmarksCount() {
			return this.$store.state.archivedCount
		},
		duplicatedBookmarksCount() {
			return this.$store.state.duplicatedCount
		},
		bookmarksLimit() {
			return this.$store.state.settings.limit
		},
	},

	created() {
	},

	methods: {
		onSearchTags() {
			this.$router.push({ name: this.routes.TAGS })
		},
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
			return !this.$store.state.selection.folders.length && this.$store.state.selection.bookmarks.length
		},
		onDropOnTag(tagName) {
			this.$store.dispatch(actions.TAG_SELECTION, { tags: [tagName], originalTags: [] })
		},
	},
}
</script>
<style>

.navigation .dropTarget--available {
	background: var(--color-primary-element-light);
}

.navigation .dropTarget--active {
	background: var(--color-primary-element-light);
}
</style>
