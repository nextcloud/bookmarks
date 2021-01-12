<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<Item :title="bookmark.title"
		:tags="bookmark.tags"
		:rename-placeholder="t('bookmarks', 'Enter new title')"
		:select-label="t('bookmarks', 'Select bookmark')"
		:active="isActive"
		:editable="isEditable"
		:selected="selected"
		:renaming="renaming"
		:background="background"
		:url="url"
		:selectable="selectable"
		@select="onSelect"
		@rename="onRenameSubmit"
		@rename-cancel="renaming = false">
		<template #title>
			<div class="bookmark__title">
				<h3 :title="bookmark.title">
					<figure
						class="bookmark__icon"
						:style="{ backgroundImage: 'url(' + iconUrl + ')' }" />
					{{ bookmark.title }}
				</h3>
				<span
					v-if="bookmark.description"
					v-tooltip="bookmark.description"
					class="bookmark__description"><figure class="icon-file" />
					{{ bookmark.description }}</span>
			</div>
		</template>
		<template #actions>
			<ActionButton icon="icon-info" :close-after-click="true" @click="onDetails">
				{{ t('bookmarks', 'Details') }}
			</ActionButton>
			<ActionCheckbox @change="onSelect">
				{{ t('bookmarks', 'Select bookmark') }}
			</ActionCheckbox>
			<ActionButton icon="icon-rename" :close-after-click="true" @click="onRename">
				{{ t('bookmarks', 'Rename') }}
			</ActionButton>
			<ActionButton :close-after-click="true" @click="onMove">
				<template #icon>
					<FolderMoveIcon :fill-color="colorMainText" class="action-button-mdi-icon" />
				</template>
				{{ t('bookmarks', 'Move') }}
			</ActionButton>
			<ActionButton
				v-if="!bookmark.deleted"
				icon="icon-delete"
				:close-after-click="true"
				@click="onDelete">
				{{ t('bookmarks', 'Delete') }}
			</ActionButton>
			<ActionButton
				v-if="bookmark.deleted"
				icon="icon-history"
				:close-after-click="true"
				@click="onRestore">
				{{ t('bookmarks', 'Restore') }}
			</ActionButton>
			<ActionButton
				v-if="bookmark.deleted"
				icon="icon-delete"
				:close-after-click="true"
				@click="onPermanentlyDelete">
				{{ t('bookmarks', 'Permanently delete') }}
			</ActionButton>
		</template>
	</Item>
</template>
<script>
import Item from './Item'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionCheckbox from '@nextcloud/vue/dist/Components/ActionCheckbox'
import FolderMoveIcon from 'vue-material-design-icons/FolderMove'
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'
import { actions, mutations } from '../store/'

export default {
	name: 'Bookmark',
	components: {
		Item,
		ActionButton,
		ActionCheckbox,
		FolderMoveIcon,
	},
	props: {
		bookmark: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			title: this.bookmark.title,
			renaming: false,
		}
	},
	computed: {
		apiUrl() {
			if (this.isPublic) {
				return generateUrl('/apps/bookmarks/public/rest/v2')
			}
			return generateUrl('/apps/bookmarks')
		},
		iconUrl() {
			return this.apiUrl + '/bookmark/' + this.bookmark.id + '/favicon' + (this.$store.state.public ? '?token=' + this.$store.state.authToken : '')
		},
		imageUrl() {
			return this.apiUrl + '/bookmark/' + this.bookmark.id + '/image' + (this.$store.state.public ? '?token=' + this.$store.state.authToken : '')
		},
		background() {
			return this.viewMode === 'grid' ? `linear-gradient(0deg, var(--color-main-background) 25%, rgba(0, 212, 255, 0) 50%), url('${this.imageUrl}')` : undefined
		},
		url() {
			return this.bookmark.url
		},
		isOpen() {
			return this.$store.state.sidebar
				&& this.$store.state.sidebar.type === 'bookmark'
				? this.$store.state.sidebar.id === this.bookmark.id
				: false
		},
		viewMode() {
			return this.$store.state.viewMode
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
		selectedBookmarks() {
			return this.$store.state.selection.bookmarks
		},
		selectable() {
			return Boolean(this.$store.state.selection.bookmarks.length || this.$store.state.selection.folders.length)
		},
		selected() {
			return this.selectedBookmarks.map(b => b.id).includes(this.bookmark.id)
		},
		isActive() {
			return this.isOpen || this.selected
		},
	},
	created() {},
	methods: {
		onDelete() {
			this.$store.dispatch(actions.DELETE_BOOKMARK, {
				id: this.bookmark.id,
				folder: this.$store.state.fetchState.query.folder,
			})
		},
		onPermanentlyDelete() {
			this.$store.dispatch(actions.PERMANENTLY_DELETE_BOOKMARK, {
				id: this.bookmark.id,
				folder: this.$store.state.fetchState.query.folder,
			})
		},
		onRestore() {
			this.$store.dispatch(actions.RESTORE_BOOKMARK, {
				id: this.bookmark.id,
				folder: this.$store.state.fetchState.query.folder,
			})
		},
		onDetails() {
			this.$store.dispatch(actions.OPEN_BOOKMARK, this.bookmark.id)
		},
		onMove() {
			this.$store.commit(mutations.RESET_SELECTION)
			this.$store.commit(mutations.ADD_SELECTION_BOOKMARK, this.bookmark)
			this.$store.commit(mutations.DISPLAY_MOVE_DIALOG, true)
		},
		async onRename() {
			this.renaming = true
		},
		async onRenameSubmit(title) {
			this.bookmark.title = title
			await this.$store.dispatch(actions.SAVE_BOOKMARK, this.bookmark.id)
			this.renaming = false
		},
		onSelect() {
			if (!this.selected) {
				this.$store.commit(mutations.ADD_SELECTION_BOOKMARK, this.bookmark)
			} else {
				this.$store.commit(mutations.REMOVE_SELECTION_BOOKMARK, this.bookmark)
			}
		},
	},
}
</script>
<style>

.bookmark__icon {
	display: inline-block;
	flex: 0;
	height: 20px;
	width: 20px;
	background-size: cover;
	margin: 0 15px;
	position: relative;
	top: 3px;
}

.bookmark__title {
	display: flex;
}

.bookmark__title,
.bookmark__title > h3 {
	text-overflow: ellipsis;
	overflow: hidden;
	white-space: nowrap;
}

.bookmark__title > h3 {
	margin: 0;
}

.bookmark__description {
	display: inline-block;
	flex: 1;
	margin: auto 10px;
	height: 20px;
	color: var(--color-text-lighter);
	text-overflow: ellipsis;
	overflow: hidden;
	min-width: 20px;
}

.bookmark__description figure {
	display: none !important;
}

.item--gridview .bookmark__description {
	flex: 0;
}

.item--gridview .bookmark__description figure {
	display: inline-block !important;
	position: relative;
	top: 5px;
}

.item--gridview .bookmark__icon {
	margin: 0 5px 0 8px;
}

</style>
