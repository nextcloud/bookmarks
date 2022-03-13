<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<AppSidebar
		v-if="isActive"
		class="sidebar"
		:title="bookmark.title"
		:title-editable="editingTitle"
		:title-placeholder="t('bookmarks', 'Title')"
		:subtitle="addedDate"
		:background="background"
		@update:active="activeTab = $event"
		@update:title="onEditTitleUpdate"
		@submit-title="onEditTitleSubmit"
		@dismiss-editing="onEditTitleCancel"
		@close="onClose">
		<template v-if="!editingTitle" slot="secondary-actions">
			<ActionButton icon="icon-rename" @click="onEditTitle" />
		</template>
		<template v-if="editingTitle" slot="secondary-actions">
			<ActionButton icon="icon-close" @click="onEditTitleCancel" />
		</template>
		<AppSidebarTab
			id="bookmark-details"
			:name="t('bookmarks', 'Details')"
			icon="icon-info"
			:order="0">
			<div>
				<div v-if="!editingUrl" class="details__line">
					<span class="icon-external" :aria-label="t('bookmarks', 'Link')" :title="t('bookmarks', 'Link')" />
					<a class="details__url" :href="bookmark.url">{{ bookmark.url }}</a>
					<Actions v-if="isEditable" class="details__action">
						<ActionButton icon="icon-rename" @click="onEditUrl" />
					</Actions>
				</div>
				<div v-else class="details__line">
					<span class="icon-external" :aria-label="t('bookmarks', 'Link')" :title="t('bookmarks', 'Link')" />
					<input v-model="url" class="details__url">
					<Actions class="details__action">
						<ActionButton icon="icon-confirm" @click="onEditUrlSubmit" />
					</Actions>
					<Actions class="details__action">
						<ActionButton icon="icon-close" @click="onEditUrlCancel" />
					</Actions>
				</div>
				<div class="details__line">
					<div class="folders">
						<span v-for="folderId in bookmark.folders"
							:key="folderId"
							v-tooltip="getFolderPath(folderId)"
							class="folders__folder"
							@click="onOpenFolder(folderId)">
							<FolderIcon :fill-color="colorMainText" /> {{ getFolder(folderId).title || t('bookmarks', 'Untitled folder') }}
						</span>
					</div>
				</div>
				<div class="details__line">
					<span class="icon-tag" :aria-label="t('bookmarks', 'Tags')" :title="t('bookmarks', 'Tags')" />
					<Multiselect
						class="tags"
						:value="tags"
						:auto-limit="false"
						:limit="7"
						:options="allTags"
						:multiple="true"
						:taggable="true"
						open-direction="below"
						:placeholder="t('bookmarks', 'Select tags and create new ones')"
						:disabled="!isEditable"
						@input="onTagsChange"
						@tag="onAddTag" />
				</div>
				<div class="details__line">
					<span class="icon-edit"
						role="figure"
						:aria-label="t('bookmarks', 'Notes')"
						:title="t('bookmarks', 'Notes')" />
					<RichContenteditable
						:value.sync="bookmark.description"
						:contenteditable="isEditable"
						:auto-complete="() => {}"
						:placeholder="t('bookmarks', 'Notes for this bookmark …')"
						:multiline="true"
						class="notes"
						@update:value="onNotesChange" />
				</div>
			</div>
			<div v-if="archivedFile">
				<h3><FileDocumentIcon slot="icon" :size="18" /> {{ t('bookmarks', 'Archived file') }}</h3>
				<a class="button" :href="archivedFileUrl" target="_blank"><FileDocumentIcon :size="18" :fill-color="colorMainText" /> {{ t('bookmarks', 'Open file') }}</a>
				<a class="button" :href="archivedFile" target="_blank"><span class="icon-files-dark" /> {{ t('bookmarks', 'Open file location') }}</a>
			</div>
		</AppSidebarTab>
		<AppSidebarTab v-if="!isPublic"
			id="bookmark-projects"
			:name="t('bookmarks', 'Projects')"
			icon="icon-projects"
			:order="1">
			<CollectionList v-if="bookmark"
				:id="''+bookmark.id"
				:name="bookmark.title"
				type="bookmarks" />
		</AppSidebarTab>
	</AppSidebar>
</template>
<script>
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import RichContenteditable from '@nextcloud/vue/dist/Components/RichContenteditable'
import FileDocumentIcon from 'vue-material-design-icons/FileDocument'
import FolderIcon from 'vue-material-design-icons/Folder'
import { CollectionList } from 'nextcloud-vue-collections'

import { getCurrentUser } from '@nextcloud/auth'
import { generateRemoteUrl, generateUrl } from '@nextcloud/router'
import humanizeDuration from 'humanize-duration'
import { actions, mutations } from '../store/'

const MAX_RELATIVE_DATE = 1000 * 60 * 60 * 24 * 7 // one week

export default {
	name: 'SidebarBookmark',
	components: { AppSidebar, AppSidebarTab, Multiselect, Actions, ActionButton, RichContenteditable, FileDocumentIcon, FolderIcon, CollectionList },
	data() {
		return {
			title: '',
			editingTitle: false,
			url: '',
			editingUrl: false,
			activeTab: '',
			showContentModal: false,
		}
	},
	computed: {
		isActive() {
			if (!this.$store.state.sidebar) return false
			return this.$store.state.sidebar.type === 'bookmark'
		},
		bookmark() {
			if (!this.isActive) return
			return this.$store.getters.getBookmark(this.$store.state.sidebar.id)
		},
		background() {
			return generateUrl(`/apps/bookmarks/bookmark/${this.bookmark.id}/image`)
		},
		addedDate() {
			const date = new Date(Number(this.bookmark.added) * 1000)
			const age = Date.now() - date
			if (age < MAX_RELATIVE_DATE) {
				const duration = humanizeDuration(age, {
					language: OC.getLanguage().split('-')[0],
					units: ['d', 'h', 'm', 's'],
					largest: 1,
					round: true,
				})
				return this.t('bookmarks', 'Created {time} ago', { time: duration })
			} else {
				return this.t('bookmarks', 'Created on {date}', { date: date.toLocaleDateString() })
			}
		},
		tags() {
			return this.bookmark.tags
		},
		allTags() {
			return this.$store.state.tags.map(tag => tag.name)
		},
		isOwner() {
			const currentUser = getCurrentUser()
			return currentUser && this.bookmark.userId === currentUser.uid
		},
		permissions() {
			return this.$store.getters.getPermissionsForBookmark(this.bookmark.id)
		},
		isEditable() {
			return this.isOwner || (!this.isOwner && this.permissions.canWrite)
		},
		archivedFile() {
			if (this.bookmark.archivedFile) {
				return generateUrl(`/apps/files/?fileid=${this.bookmark.archivedFile}`)
			}
			return null
		},
		archivedFileUrl() {
			// remove `/username/files/`
			const barePath = this.bookmark.archivedFilePath.split('/').slice(3).join('/')
			return generateRemoteUrl(`webdav/${barePath}`)
		},
	},
	created() {
	},
	methods: {
		onClose() {
			this.$store.commit(mutations.SET_SIDEBAR, null)
		},
		onNotesChange(e) {
			this.scheduleSave()
		},
		onTagsChange(tags) {
			this.bookmark.tags = tags
			this.scheduleSave()
		},
		onAddTag(tag) {
			this.bookmark.tags.push(tag)
			this.scheduleSave()
		},
		onEditTitle() {
			this.title = this.bookmark.title
			this.editingTitle = true
		},
		onEditTitleUpdate(e) {
			this.title = e
		},
		onEditTitleSubmit() {
			this.editingTitle = false
			this.bookmark.title = this.title
			this.scheduleSave()
		},
		onEditTitleCancel() {
			this.editingTitle = false
			this.title = ''
		},
		onEditUrl() {
			this.url = this.bookmark.url
			this.editingUrl = true
		},
		onEditUrlSubmit() {
			this.editingUrl = false
			this.bookmark.url = this.url
			this.scheduleSave()
		},
		onEditUrlCancel() {
			this.editingUrl = false
			this.url = ''
		},
		scheduleSave() {
			if (this.changeTimeout) clearTimeout(this.changeTimeout)
			this.changeTimeout = setTimeout(async () => {
				await this.$store.dispatch(actions.SAVE_BOOKMARK, this.bookmark.id)
				await this.$store.dispatch(actions.LOAD_TAGS)
			}, 1000)
		},
		onOpenFolder(id) {
			this.$router.push({ name: this.routes.FOLDER, params: { folder: id } })
			this.onClose()
		},
		getFolder(id) {
			const path = this.$store.getters.getFolder(id)
			const folder = path[0]
			return folder
		},
		getFolderPath(id) {
			const path = this.$store.getters.getFolder(id).reverse().map(folder => folder.title)
			return '/' + path.join('/')
		},
	},
}
</script>
<style>
.sidebar span[class^='icon-'],
.sidebar .material-design-icon {
	display: inline-block;
	position: relative;
	top: 3px;
	opacity: 0.5;
}

.sidebar .details__line > span[class^='icon-'],
.sidebar .details__line > .material-design-icon {
	display: inline-block;
	position: relative;
	top: 11px;
	opacity: 0.5;
	margin-right: 10px;
}

.sidebar h3 {
	margin-top: 20px;
}

.sidebar .tags {
	width: 100%;
}

.sidebar .notes {
	flex-grow: 1;
	min-height: 80px;
}

.sidebar .details__line {
	display: flex;
	align-items: flex-start;
	margin-bottom: 10px;
}

.sidebar .details__line > * {
	flex-grow: 0;
}

.sidebar .details__line > :nth-child(2) {
	flex-grow: 1;
}

.sidebar .details__line .notes {
	flex-grow: 1;
}

.sidebar .details__url {
	flex-grow: 1;
	padding: 8px 0;
	text-overflow: ellipsis;
	height: 2em;
	display: inline-block;
	overflow: hidden;
}

.sidebar .details__action {
	flex-grow: 0;
}

.sidebar .folders {
	display: flex;
	align-items: flex-start;
}

.sidebar .folders__folder {
	border: 1px solid var(--color-border);
	padding: 2px 10px;
	border-radius: var(--border-radius-large);
	margin-right: 5px;
	cursor: pointer;
}

.sidebar .folders__folder * {
	cursor: pointer;
}
</style>
