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
				<div v-if="!editingUrl" class="bookmark-details__line">
					<span class="bookmark-details__url">{{ bookmark.url }}</span>
					<Actions v-if="isEditable" class="bookmark-details__action">
						<ActionButton icon="icon-rename" @click="onEditUrl" />
					</Actions>
				</div>
				<div v-else class="bookmark-details__line">
					<input v-model="url" class="bookmark-details__url">
					<Actions class="bookmark-details__action">
						<ActionButton icon="icon-confirm" @click="onEditUrlSubmit" />
					</Actions>
					<Actions class="bookmark-details__action">
						<ActionButton icon="icon-close" @click="onEditUrlCancel" />
					</Actions>
				</div>
			</div>
			<div v-if="archivedFile">
				<h3><ArchiveArrowDownIcon slot="icon" :size="18" /> {{ t('bookmarks', 'Archived file') }}</h3>
				<a :href="archivedFile" class="button">{{ t('bookmarks', 'Open archived file') }}</a>
			</div>
			<div v-else-if="bookmark.textContent">
				<h3><ArchiveArrowDownIcon slot="icon" :size="18" /> {{ t('bookmarks', 'Archived content') }}</h3>
				<blockquote v-text="bookmark.textContent.substr(0, 250)+'...'" />
				<a href="javascript:void(0)" class="button" @click="showContentModal = true">{{ t('bookmarks', 'Read more') }}</a>
				<ContentModal v-if="showContentModal" :bookmark="bookmark" @close="showContentModal = false" />
			</div>
			<div>
				<h3><span class="icon-tag" /> {{ t('bookmarks', 'Tags') }}</h3>
				<Multiselect
					class="sidebar__tags"
					:value="tags"
					:auto-limit="false"
					:limit="7"
					:options="allTags"
					:multiple="true"
					:taggable="true"
					:placeholder="t('bookmarks', 'Select tags and create new ones')"
					:disabled="!isEditable"
					@input="onTagsChange"
					@tag="onAddTag" />
			</div>
			<div>
				<h3><span class="icon-edit" /> {{ t('bookmarks', 'Notes') }}</h3>
				<RichContenteditable
					:value.sync="bookmark.description"
					:contenteditable="isEditable"
					:auto-complete="() => {}"
					:placeholder="t('bookmarks', 'Notes for this bookmark …')"
					:multiline="true"
					@update:value="onNotesChange" />
			</div>
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
import ArchiveArrowDownIcon from 'vue-material-design-icons/ArchiveArrowDown'

import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'
import humanizeDuration from 'humanize-duration'
import { actions, mutations } from '../store/'
import ContentModal from './ContentModal'

const MAX_RELATIVE_DATE = 1000 * 60 * 60 * 24 * 7 // one week

export default {
	name: 'SidebarBookmark',
	components: { ContentModal, AppSidebar, AppSidebarTab, Multiselect, Actions, ActionButton, RichContenteditable, ArchiveArrowDownIcon },
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
			this.changeTimeout = setTimeout(async() => {
				await this.$store.dispatch(actions.SAVE_BOOKMARK, this.bookmark.id)
				await this.$store.dispatch(actions.LOAD_TAGS)
			}, 1000)
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

.sidebar h3 {
	margin-top: 20px;
}

.sidebar__tags {
	width: 100%;
}

.sidebar__notes {
	min-height: 200px !important;
	width: auto !important;
}

.bookmark-details__line {
	display: flex;
}

.bookmark-details__url {
	flex-grow: 1;
	padding: 8px 0;
}

.bookmark-details__action {
	flex-grow: 0;
}

.sidebar blockquote {
	border-left: var(--color-placeholder-dark) 3px solid;
	padding-left: 10px;
	color: var(--color-text-lighter);
	margin: 10px 0;
}
</style>
