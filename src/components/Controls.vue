<template>
	<div :class="['controls', isPublic && 'wide']">
		<div class="controls__left">
			<template v-if="$route.name === routes.FOLDER || $route.name === routes.HOME || $route.name === routes.TAGS">
				<template v-if="$route.name === routes.FOLDER || $route.name === routes.HOME">
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
					{{ viewMode === 'list' ? t('bookmarks', 'Grid view') : t('bookmarks', 'List view') }}
				</ActionButton>
			</Actions>
			<button v-tooltip="t('bookmarks', 'Private RSS Feed of current view')"
				class="custom-button"
				:title="t('bookmarks', 'Private RSS Feed of current view')"
				@click="openRssUrl">
				<RssIcon :fill-color="colorMainText" class="action-button-mdi-icon" />
			</button>
			<div v-if="hasSelection" class="breadcrumbs__bulkediting">
				<Actions :primary="true" :menu-title="selectionDescription">
					<ActionButton @click="onBulkMove">
						<template #icon>
							<FolderMoveIcon :fill-color="colorMainText" class="action-button-mdi-icon" />
						</template>
						{{ t('bookmarks', 'Move selection') }}
					</ActionButton>
					<ActionButton icon="icon-delete" @click="onBulkDelete">
						{{ t('bookmarks', 'Delete selection') }}
					</ActionButton>
					<ActionButton icon="icon-external" @click="onBulkOpen">
						{{ t('bookmarks', 'Open all selected') }}
					</ActionButton>
					<ActionSeparator />
					<ActionButton icon="icon-checkmark" @click="onSelectAll">
						{{ t('bookmarks', 'Select all') }}
					</ActionButton>
					<ActionButton icon="icon-close" @click="onCancelSelection">
						{{ t('bookmarks', 'Cancel selection') }}
					</ActionButton>
				</Actions>
			</div>
		</div>
	</div>
</template>
<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionSeparator from '@nextcloud/vue/dist/Components/ActionSeparator'
import RssIcon from 'vue-material-design-icons/Rss'
import FolderMoveIcon from 'vue-material-design-icons/FolderMove'
import { actions, mutations } from '../store/'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'Controls',
	components: { Multiselect, Actions, ActionButton, ActionSeparator, FolderMoveIcon, RssIcon },
	props: {},
	data() {
		return {
			url: '',
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
		selectionDescription() {
			if (this.$store.state.selection.bookmarks.length !== 0 && this.$store.state.selection.folders.length !== 0) {
				return this.t('bookmarks',
					'Selected {folders} folders and {bookmarks} bookmarks',
					{ folders: this.$store.state.selection.folders.length, bookmarks: this.$store.state.selection.bookmarks.length }
				)
			}
			if (this.$store.state.selection.bookmarks.length !== 0) {
				return this.n('bookmarks',
					'Selected %n bookmark',
					'Selected %n bookmarks',
					this.$store.state.selection.bookmarks.length
				)
			}
			if (this.$store.state.selection.folders.length !== 0) {
				return this.n('bookmarks',
					'Selected %n folder',
					'Selected %n folders',
					this.$store.state.selection.folders.length
				)
			}
			return ''
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

		async onBulkOpen() {
			for (const { url } of this.$store.state.selection.bookmarks) {
				window.open(url)
				await new Promise(resolve => setTimeout(resolve, 200))
			}
		},
		async onBulkDelete() {
			await this.$store.dispatch(actions.DELETE_SELECTION, { folder: this.$route.params.folder })
			this.$store.commit(mutations.RESET_SELECTION)
		},
		onBulkMove() {
			this.$store.commit(mutations.DISPLAY_MOVE_DIALOG, true)
		},
		onCancelSelection() {
			this.$store.commit(mutations.RESET_SELECTION)
		},
		async onSelectAll() {
			await this.$store.dispatch(actions.FETCH_ALL)
			this.$store.state.bookmarks.forEach(bookmark => {
				this.$store.commit(mutations.ADD_SELECTION_BOOKMARK, bookmark)
			})
		},

		openRssUrl() {
			window.open(this.rssURL)
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
