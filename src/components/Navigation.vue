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
				<HomeIcon :size="20" slot="icon" />
				<NcCounterBubble slot="counter">
					{{ allBookmarksCount | largeNumbers }}
				</NcCounterBubble>
			</NcAppNavigationItem>
			<NcAppNavigationItem key="menu-recent"
				:to="{ name: routes.RECENT }"
				:name="t('bookmarks', 'Recent')">
				<HistoryIcon :size="20" slot="icon" />
			</NcAppNavigationItem>
			<NcAppNavigationItem key="menu-shared-folders"
				:to="{ name: routes.SHARED_FOLDERS }"
				:name="t('bookmarks', 'Shared with you')">
				<ShareVariantIcon :size="20" slot="icon" />
				<NcCounterBubble v-show="Boolean(sharedFoldersCount)" slot="counter">
					{{ sharedFoldersCount | largeNumbers }}
				</NcCounterBubble>
			</NcAppNavigationItem>
			<NcAppNavigationItem key="menu-archived"
				:to="{ name: routes.ARCHIVED }"
				:name="t('bookmarks', 'Files')">
				<FileDocumentMultipleIcon :size="20" slot="icon" />
				<NcCounterBubble v-show="Boolean(archivedBookmarksCount)" slot="counter">
					{{ archivedBookmarksCount }}
				</NcCounterBubble>
			</NcAppNavigationItem>
			<NcAppNavigationItem key="menu-duplicated"
				:to="{ name: routes.DUPLICATED }"
				:name="t('bookmarks', 'Duplicates')">
				<VectorLinkIcon :size="20" slot="icon" />
				<NcCounterBubble v-show="Boolean(duplicatedBookmarksCount)" slot="counter">
					{{ duplicatedBookmarksCount | largeNumbers }}
				</NcCounterBubble>
			</NcAppNavigationItem>
			<NcAppNavigationItem key="menu-unavailable"
				:to="{ name: routes.UNAVAILABLE }"
				:name="t('bookmarks', 'Broken links')">
				<LinkVariantOffIcon :size="20" slot="icon" />
				<NcCounterBubble v-show="Boolean(unavailableBookmarksCount)" slot="counter">
					{{ unavailableBookmarksCount | largeNumbers }}
				</NcCounterBubble>
			</NcAppNavigationItem>
			<NcAppNavigationSpacer />
			<NcAppNavigationNewItem key="menu-new-tag"
				:name="t('bookmarks', 'New tag')"
				@new-item="onNewTag">
				<TagPlusIcon :size="20" slot="icon" />
			</NcAppNavigationNewItem>
			<template v-if="Boolean(tags.length)">
				<NcAppNavigationItem key="menu-tags"
					:name="t('bookmarks', 'Search tags')"
					@click="onSearchTags">
					<TagMultipleIcon :size="20" slot="icon" />
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
					<TagIcon :size="20" slot="icon" />
					<NcCounterBubble slot="counter">
						{{ tag.count | largeNumbers }}
					</NcCounterBubble>
					<template v-if="!isPublic" slot="actions">
						<NcActionButton :close-after-click="true" @click="onDeleteTag(tag.name)">
							<template #icon>
								<DeleteIcon :size="20" />
							</template>
							{{ t('bookmarks', 'Delete') }}
						</NcActionButton>
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem key="menu-untagged"
					:to="{ name: routes.UNTAGGED }"
					:name="t('bookmarks', 'Untagged')">
					<TagOffIcon :size="20" slot="icon" />
				</NcAppNavigationItem>
			</template>
			<template v-if="Number(bookmarksLimit) > 0">
				<NcAppNavigationSpacer />
				<NcAppNavigationItem :pinned="true" :name="t('bookmarks', '{used} bookmarks of {available} available', {used: allBookmarksCount, available: bookmarksLimit})">
					<template #icon>
						<GaugeIcon :size="20" />
					</template>
					<ProgressBar :val="allBookmarksCount" :max="bookmarksLimit" />
				</NcAppNavigationItem>
			</template>
		</template>
		<template #footer>
			<NcAppNavigationItem :name="t('bookmarks', 'Settings')" @click="settingsOpen = !settingsOpen"><template #icon><CogIcon :size="20" /></template></NcAppNavigationItem>
			<Settings :settingsOpen.sync="settingsOpen" />
		</template>
	</NcAppNavigation>
</template>

<script>
import { NcActionButton, NcAppNavigation, NcAppNavigationItem, NcAppNavigationNewItem, NcCounterBubble, NcAppNavigationSettings, NcAppNavigationSpacer } from '@nextcloud/vue'
import { HomeIcon } from './Icons.js'
import { HistoryIcon } from './Icons.js'
import { TagOffIcon } from './Icons.js'
import { LinkVariantOffIcon } from './Icons.js'
import { ShareVariantIcon } from './Icons.js'
import { FileDocumentMultipleIcon } from './Icons.js'
import { TagPlusIcon } from './Icons.js'
import { TagMultipleIcon } from './Icons.js'
import { VectorLinkIcon } from './Icons.js'
import { TagIcon } from './Icons.js'
import { DeleteIcon } from './Icons.js'
import { GaugeIcon } from './Icons.js'
import { CogIcon } from './Icons.js'
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
