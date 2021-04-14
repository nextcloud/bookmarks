<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div :class="['controls', $store.state.public && 'wide']">
		<div class="controls__left">
			<Actions v-if="$route.name !== routes.HOME">
				<ActionButton @click="onClickBack">
					<ArrowLeftIcon slot="icon" :size="18" :fill-color="colorMainText" />
					{{ t('bookmarks', 'Go back') }}
				</ActionButton>
			</Actions>
			<template v-if="$route.name === routes.FOLDER">
				<h2><FolderIcon :size="18" :fill-color="colorMainText" /> <span>{{ folder.title }}</span></h2>
				<Actions>
					<ActionButton icon="icon-share" :close-after-click="true" @click="onOpenFolderShare">
						{{ t('bookmarks', 'Share folder') }}
					</ActionButton>
				</Actions>
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
				v-tooltip="t('bookmarks', 'New')"
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
			<Actions v-tooltip="sortingOptions[sorting].description">
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
			<Actions force-menu>
				<template #icon>
					<RssIcon :fill-color="colorMainText" :size="20" class="action-button-mdi-icon" />
				</template>
				<ActionButton
					:title="t('bookmarks', 'Copy RSS Feed of current view')"
					:close-after-click="true"
					@click="copyRssUrl">
					<template #icon>
						<RssIcon :fill-color="colorMainText" :size="20" class="action-button-mdi-icon" />
					</template>
					{{ !this.$store.state.public? t('bookmarks', 'The RSS feed requires authentication with your Nextcloud credentials') : '' }}
				</ActionButton>
			</Actions>
		</div>
	</div>
</template>
<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionInput from '@nextcloud/vue/dist/Components/ActionInput'
import ActionRouter from '@nextcloud/vue/dist/Components/ActionRouter'
import FolderIcon from 'vue-material-design-icons/Folder'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft'
import RssIcon from 'vue-material-design-icons/Rss'
import SortAlphabeticalAscendingIcon from 'vue-material-design-icons/SortAlphabeticalAscending'
import SortBoolAscendingIcon from 'vue-material-design-icons/SortBoolAscending'
import SortClockAscendingOutlineIcon from 'vue-material-design-icons/SortClockAscendingOutline'
import SortCalendarAscendingIcon from 'vue-material-design-icons/SortCalendarAscending'
import SortAscendingIcon from 'vue-material-design-icons/SortAscending'
import { actions, mutations } from '../store/'
import { generateUrl } from '@nextcloud/router'
import BulkEditing from './BulkEditing'
import copy from 'copy-text-to-clipboard'

export default {
	name: 'Controls',
	components: {
		BulkEditing,
		Multiselect,
		Actions,
		ActionButton,
		ActionInput,
		ActionRouter,
		RssIcon,
		SortAscendingIcon,
		SortCalendarAscendingIcon,
		SortAlphabeticalAscendingIcon,
		SortClockAscendingOutlineIcon,
		SortBoolAscendingIcon,
		FolderIcon,
		ArrowLeftIcon,
	},
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
		backLink() {
			if (this.folder && this.folderPath.length > 1) {
				return { name: this.routes.FOLDER, params: { folder: this.folder.parent_folder } }
			}

			return { name: this.routes.HOME }
		},
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
		folder() {
			const folder = this.$route.params.folder
			if (!folder) return
			return this.$store.getters.getFolder(folder)[0]
		},
		viewMode() {
			return this.$store.state.viewMode
		},
		hasSelection() {
			return this.$store.state.selection.bookmarks.length || this.$store.state.selection.folders.length
		},
		rssURL() {
			const params = new URLSearchParams()
			for (const field in this.$store.state.fetchState.query) {
				if (Array.isArray(this.$store.state.fetchState.query[field])) {
					this.$store.state.fetchState.query[field].forEach(value => {
						params.append(field + '[]', value)
					})
				} else {
					params.append(field, this.$store.state.fetchState.query[field])
				}
			}
			params.set('format', 'rss')
			params.set('page', '-1')
			if (this.$store.state.public) {
				params.set('token', this.$store.state.authToken)
			}
			return (
				window.location.origin
					+ generateUrl(
						'/apps/bookmarks/public/rest/v2/bookmark?'
							+ params.toString()
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

		onClickBack() {
			this.$router.push(this.backLink)
		},

		onTagsChange(tags) {
			this.$router.push({ name: this.routes.TAGS, params: { tags: tags.join(',') } })
		},

		onSelectFolder(folder) {
			this.$router.push({ name: this.routes.FOLDER, params: { folder } })
		},

		onOpenFolderShare() {
			this.$store.dispatch(actions.OPEN_FOLDER_SHARING, this.folder.id)
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

		copyRssUrl() {
			copy(this.rssURL)
			this.$store.commit(mutations.SET_NOTIFICATION, t('bookmarks', 'RSS feed copied'))
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

.controls h2 {
	margin: 0;
	margin-left: 10px;
	display: flex;
	position: relative;
	top: -2px;
}

.controls h2 :nth-child(2) {
	margin-left: 5px;
}

.controls h2 > .material-design-icon {
	position: relative;
	top: 4px;
}

.controls.wide {
	padding: 0 8px;
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

.controls__tags {
	width: 300px;
	flex: 1;
}

.controls__tags .multiselect__tags {
	border-top: none !important;
	border-left: none !important;
	border-right: none !important;
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
