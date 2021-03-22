<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div :class="['controls', $store.state.public && 'wide']">
		<div class="controls__left">
			<template v-if="$route.name === routes.FOLDER || $route.name === routes.HOME || $store.state.public">
				<a :class="!isPublic? 'icon-home' : 'icon-public'" @click="onSelectHome" />
				<span class="icon-breadcrumb" />
			</template>
			<template v-if="$route.name === routes.FOLDER">
				<template v-for="folder in folderPath">
					<a
						:key="'a' + folder.id"
						href="#"
						tabindex="0"
						@click.prevent="onSelectFolder(folder.id)">{{ folder.title }}</a>
					<span :key="'b' + folder.id" class="icon-breadcrumb" />
				</template>
			</template>
			<template v-if="$route.name === routes.TAGS">
				<span class="icon-tag" />
				<Multiselect
					class="controls__tags"
					:value="tags"
					:auto-limit="false"
					:limit="7"
					:options="allTags"
					:multiple="true"
					:placeholder="t('bookmarks', 'Select one or more tags')"
					@input="onTagsChange" />
			</template>
			<Actions
				v-if="!isPublic"
				class="controls__AddFolder"
				:title="t('bookmarks', 'New')"
				:default-icon="'icon-add'">
				<ActionButton
					icon="icon-link"
					:close-after-click="true"
					@click="onAddBookmark">
					{{
						t('bookmarks', 'New bookmark')
					}}
				</ActionButton>
				<ActionButton
					icon="icon-folder"
					:close-after-click="true"
					@click="onAddFolder">
					{{ t('bookmarks', 'New folder') }}
				</ActionButton>
			</Actions>
			<BulkEditing v-if="hasSelection" />
		</div>
		<div class="controls__right">
			<Actions>
				<ActionButton
					:icon="
						viewMode === 'list'
							? 'icon-toggle-pictures'
							: 'icon-toggle-filelist'
					"
					@click="onToggleViewMode">
					{{ viewMode === 'list' ? t('bookmarks', 'Change to grid view') : t('bookmarks', 'Change to list view') }}
				</ActionButton>
			</Actions>
			<Actions :title="sortingOptions[sorting].description">
				<template #icon>
					<component :is="sortingOptions[sorting].icon" :size="20" :fill-color="colorMainText" />
				</template>
				<ActionButton v-for="(option, key) in sortingOptions"
					:key="key"
					:close-after-click="true"
					@click="onChangeSorting(key)">
					<template #icon>
						<component :is="option.icon"
							:size="20"
							:fill-color="key === sorting? colorPrimaryElement : colorMainText" />
					</template>
					{{ option.description }}
				</ActionButton>
			</Actions>
			<button v-tooltip="t('bookmarks', 'RSS Feed of current view')"
				class="custom-button"
				:title="t('bookmarks', 'RSS Feed of current view')"
				@click="openRssUrl">
				<RssIcon :fill-color="colorMainText" class="action-button-mdi-icon" />
			</button>
		</div>
	</div>
</template>
<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import RssIcon from 'vue-material-design-icons/Rss'
import SortAlphabeticalAscendingIcon from 'vue-material-design-icons/SortAlphabeticalAscending'
import SortBoolAscendingIcon from 'vue-material-design-icons/SortBoolAscending'
import SortClockAscendingOutlineIcon from 'vue-material-design-icons/SortClockAscendingOutline'
import SortCalendarAscendingIcon from 'vue-material-design-icons/SortCalendarAscending'
import SortAscendingIcon from 'vue-material-design-icons/SortAscending'
import { actions, mutations } from '../store/'
import { generateUrl } from '@nextcloud/router'
import BulkEditing from './BulkEditing'

export default {
	name: 'Controls',
	components: { BulkEditing, Multiselect, Actions, ActionButton, RssIcon, SortAscendingIcon, SortCalendarAscendingIcon, SortAlphabeticalAscendingIcon, SortClockAscendingOutlineIcon, SortBoolAscendingIcon },
	props: {},
	data() {
		return {
			url: '',
			search: this.$route.params.search || '',
			sortingOptions: {
				added: { icon: 'SortCalendarAscendingIcon', description: this.t('bookmarks', 'Sort by creation date') },
				lastmodified: { icon: 'SortClockAscendingOutlineIcon', description: this.t('bookmarks', 'Sort by last modified') },
				title: { icon: 'SortAlphabeticalAscendingIcon', description: this.t('bookmarks', 'Sort by title') },
				clickcount: { icon: 'SortBoolAscendingIcon', description: this.t('bookmarks', 'Sort by click count') },
				index: { icon: 'SortAscendingIcon', description: this.t('bookmarks', 'Sort by manual order') },
			},
		}
	},
	computed: {
		allTags() {
			return this.$store.state.tags.map(tag => tag.name)
		},
		tags() {
			const tags = this.$route.params.tags
			if (!tags) return []
			return tags.split(',')
		},
		folderPath() {
			const folder = this.$route.params.folder
			if (!folder) return []
			return this.$store.getters.getFolder(folder).reverse()
		},
		viewMode() {
			return this.$store.state.viewMode
		},
		hasSelection() {
			return this.$store.state.selection.bookmarks.length || this.$store.state.selection.folders.length
		},
		rssURL() {
			return (
				window.location.origin
					+ generateUrl(
						'/apps/bookmarks/public/rest/v2/bookmark?'
							+ new URLSearchParams(
								Object.assign({}, this.$store.state.fetchState.query, {
									format: 'rss',
									page: -1,
									...(this.$store.state.public && { token: this.$store.state.authToken }),
								})
							).toString()
					)
			)
		},
		sorting() {
			return this.$store.state.settings.sorting
		},
	},
	created() {},
	methods: {
		onSelectHome() {
			this.$router.push({ name: this.routes.HOME })
		},
		onTagsChange(tags) {
			this.$router.push({ name: this.routes.TAGS, params: { tags: tags.join(',') } })
		},

		onSelectFolder(folder) {
			this.$router.push({ name: this.routes.FOLDER, params: { folder } })
		},

		onAddFolder() {
			this.$store.commit(
				mutations.DISPLAY_NEW_FOLDER,
				!this.$store.state.displayNewFolder
			)
		},
		onAddBookmark() {
			this.$store.commit(
				mutations.DISPLAY_NEW_BOOKMARK,
				!this.$store.state.displayNewBookmark
			)
		},

		onToggleViewMode() {
			this.$store.dispatch(actions.SET_SETTING, {
				key: 'viewMode',
				value: this.$store.state.viewMode === 'grid' ? 'list' : 'grid',
			})
		},

		openRssUrl() {
			window.open(this.rssURL)
		},

		async onChangeSorting(value) {
			await this.$store.dispatch(actions.SET_SETTING, {
				key: 'sorting',
				value,
			})
			await this.$store.dispatch(actions.FETCH_PAGE)
		},
	},
}
</script>
<style>
.controls {
	padding: 0 8px 0 44px;
	display: flex;
	position: absolute;
	z-index: 100;
	background: var(--color-main-background-translucent);
	left: 0;
	right: 0;
	top: 0;
}

.controls.wide {
	padding: 0 8px;
}

.controls .custom-button {
	background: none;
	padding: 0;
	border: none;
}

.controls .custom-button:hover,
.controls .custom-button:active {
	background-color: var(--color-background-hover);
}

.controls + * {
	margin-top: 50px;
}

.controls__left {
	display: flex;
	align-items: center;
	flex: 0;
}

.controls__left > * {
	display: inline-block;
	height: 30px;
	padding: 5px 7px;
	flex-shrink: 0;
}

.controls__left > *:not(.icon-breadcrumb) {
	min-width: 30px;
	opacity: 0.7;
}

.controls__left > *:hover,
.controls__left > *:focus {
	opacity: 1;
}

.controls__tags {
	width: 300px;
	flex: 1;
}

.controls__tags .multiselect__tags {
	border-top: none !important;
	border-left: none !important;
	border-right: none !important;
}

.controls__AddFolder {
	margin-left: 5px;
	padding: 0;
	margin-top: -10px;
}

.controls__right {
	flex: 2;
	display: flex;
	flex-direction: row-reverse;
	padding: 0;
}

.controls__right > * {
	min-width: 30px;
}
</style>
