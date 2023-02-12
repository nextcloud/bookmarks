<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div :class="['controls', $store.state.public && 'wide']">
		<div class="controls__left">
			<NcActions v-if="$route.name === routes.FOLDER">
				<NcActionButton @click="onClickBack">
					<template #icon>
						<ArrowLeftIcon />
					</template>
					{{ t('bookmarks', 'Go back') }}
				</NcActionButton>
			</NcActions>
			<template v-if="$route.name === routes.FOLDER">
				<h2><FolderIcon /> <span>{{ folder.title }}</span></h2>
				<NcActions v-if="permissions.canShare">
					<NcActionButton :close-after-click="true" @click="onOpenFolderShare">
						<template #icon>
							<ShareVariantIcon />
						</template>
						{{ t('bookmarks', 'Share folder') }}
					</NcActionButton>
				</NcActions>
			</template>
			<template v-if="$route.name === routes.TAGS">
				<TagIcon />
				<NcMultiselect class="controls__tags"
					:value="tags"
					:auto-limit="false"
					:limit="7"
					:options="allTags"
					:multiple="true"
					:placeholder="t('bookmarks', 'Select one or more tags')"
					@input="onTagsChange" />
			</template>
			<NcActions v-if="!isPublic"
				v-tooltip="t('bookmarks', 'New')"
				:title="t('bookmarks', 'New')">
				<template #icon>
					<PlusIcon />
				</template>
				<NcActionButton :close-after-click="true"
					@click="onAddBookmark">
					<template #icon>
						<EarthIcon />
					</template>
					{{
						t('bookmarks', 'New bookmark')
					}}
				</NcActionButton>
				<NcActionButton :close-after-click="true"
					@click="onAddFolder">
					<template #icon>
						<FolderIcon />
					</template>
					{{ t('bookmarks', 'New folder') }}
				</NcActionButton>
			</NcActions>
			<BulkEditing v-if="hasSelection" />
		</div>
		<div class="controls__right">
			<NcActions>
				<NcActionButton @click="onToggleViewMode">
					<template #icon>
						<ViewListIcon v-if="viewMode !== 'list'" />
						<ViewGridIcon v-else />
					</template>
					{{ viewMode === 'list' ? t('bookmarks', 'Change to grid view') : t('bookmarks', 'Change to list view') }}
				</NcActionButton>
			</NcActions>
			<NcActions v-tooltip="sortingOptions[sorting].description">
				<template #icon>
					<component :is="sortingOptions[sorting].icon" :size="20" :fill-color="colorMainText" />
				</template>
				<NcActionButton v-for="(option, key) in sortingOptions"
					:key="key"
					:close-after-click="true"
					@click="onChangeSorting(key)">
					<template #icon>
						<component :is="option.icon"
							:fill-color="key === sorting? colorPrimaryElement : colorMainText" />
					</template>
					{{ option.description }}
				</NcActionButton>
			</NcActions>
			<NcActions force-menu>
				<template #icon>
					<RssIcon />
				</template>
				<NcActionButton :title="t('bookmarks', 'Copy RSS Feed of current view')"
					:close-after-click="true"
					@click="copyRssUrl">
					<template #icon>
						<RssIcon />
					</template>
					{{ !$store.state.public? t('bookmarks', 'The RSS feed requires authentication with your Nextcloud credentials') : '' }}
				</NcActionButton>
			</NcActions>
			<NcTextField v-if="isPublic"
				:value.sync="search"
				:label="t('bookmarks','Search')"
				:placeholder="t('bookmarks','Search')"
				class="inline-search"
				@update:value="onSearch($event)">
				<MagnifyIcon />
			</NcTextField>
		</div>
	</div>
</template>
<script>
import { NcMultiselect, NcActions, NcActionButton, NcActionInput, NcActionRouter, NcTextField } from '@nextcloud/vue'
import MagnifyIcon from 'vue-material-design-icons/Magnify.vue'
import EarthIcon from 'vue-material-design-icons/Earth.vue'
import ViewGridIcon from 'vue-material-design-icons/ViewGrid.vue'
import ViewListIcon from 'vue-material-design-icons/ViewList.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import RssIcon from 'vue-material-design-icons/Rss.vue'
import SortAlphabeticalAscendingIcon from 'vue-material-design-icons/SortAlphabeticalAscending.vue'
import SortBoolAscendingIcon from 'vue-material-design-icons/SortBoolAscending.vue'
import SortClockAscendingOutlineIcon from 'vue-material-design-icons/SortClockAscendingOutline.vue'
import SortCalendarAscendingIcon from 'vue-material-design-icons/SortCalendarAscending.vue'
import SortNumericAscendingIcon from 'vue-material-design-icons/SortNumericAscending.vue'
import SortAscendingIcon from 'vue-material-design-icons/SortAscending.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'
import TagIcon from 'vue-material-design-icons/Tag.vue'
import { actions, mutations } from '../store/index.js'
import { generateUrl } from '@nextcloud/router'
import BulkEditing from './BulkEditing.vue'

export default {
	name: 'Controls',
	components: {
		BulkEditing,
		NcMultiselect,
		NcActions,
		NcActionButton,
		NcActionInput,
		NcActionRouter,
		RssIcon,
		SortAscendingIcon,
		SortCalendarAscendingIcon,
		SortAlphabeticalAscendingIcon,
		SortClockAscendingOutlineIcon,
		SortBoolAscendingIcon,
		SortNumericAscendingIcon,
		FolderIcon,
		ArrowLeftIcon,
		PlusIcon,
		ViewGridIcon,
		ViewListIcon,
		EarthIcon,
		ShareVariantIcon,
		TagIcon,
		NcTextField,
		MagnifyIcon,
	},
	props: {},
	data() {
		return {
			url: '',
			search: this.$route.params.search || '',
			sortingOptions: {
				added: { icon: 'SortCalendarAscendingIcon', description: this.t('bookmarks', 'Sort by created date') },
				lastmodified: { icon: 'SortClockAscendingOutlineIcon', description: this.t('bookmarks', 'Sort by last modified') },
				title: { icon: 'SortAlphabeticalAscendingIcon', description: this.t('bookmarks', 'Sort by title') },
				clickcount: { icon: 'SortBoolAscendingIcon', description: this.t('bookmarks', 'Sort by click count') },
				index: { icon: 'SortAscendingIcon', description: this.t('bookmarks', 'Sort by manual order') },
				url: { icon: 'SortNumericAscendingIcon', description: this.t('bookmarks', 'Sort by URL') },
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
		permissions() {
			const folder = this.folder
			if (!folder) {
				return {}
			}
			return this.$store.getters.getPermissionsForFolder(folder.id)
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

		onSearch(query) {
			this.$router.push({ name: this.routes.SEARCH, params: { search: query } })
		},

		copyRssUrl() {
			navigator.clipboard.writeText(this.rssURL)
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
	padding: 4px 8px 0 44px;
	display: flex;
	position: absolute;
	z-index: 100;
	background: var(--color-main-background-translucent);
	left: 0;
	right: 0;
	top: 0;
	border-bottom: var(--color-border) 1px solid;
}

.controls h2 {
	margin: 0;
	margin-left: 10px;
	margin-right: 10px;
	display: flex;
	flex-shrink: 0;
}

.controls h2 :nth-child(2) {
	margin-left: 5px;
}

.controls .action-item {
	height: 45px;
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

.controls__right {
	flex: 2;
	display: flex;
	flex-direction: row-reverse;
	padding: 0;
}

.controls__right > * {
	min-width: 30px;
}

.controls__right .inline-search {
	max-width: 150px !important;
	position: relative;
	top: 4px;
}
</style>
