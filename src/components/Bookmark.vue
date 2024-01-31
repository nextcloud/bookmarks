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
		:draggable="isEditable"
		:renaming="renaming"
		:background="background"
		:url="url"
		:selectable="selectable"
		@select="onSelect"
		@rename="onRenameSubmit"
		@rename-cancel="renaming = false"
		@click="onClick">
		<template #title>
			<div class="bookmark__title">
				<h3 :title="bookmark.title">
					<span v-if="bookmark.preliminary" class="icon-loading-small bookmark__icon" />
					<figure v-else
						class="bookmark__icon"
						:style="{ backgroundImage: 'url(' + iconUrl + ')' }" />
					{{ bookmark.title }}
				</h3>
				<span v-if="bookmark.description"
					v-tooltip="bookmark.description"
					class="bookmark__description"><figure class="icon-file" />
					{{ bookmark.description }}</span>
			</div>
		</template>
		<template #actions>
			<NcActionButton :close-after-click="true"
				@click="onDetails">
				<template #icon>
					<InformationVariantIcon :size="20" />
				</template>
				{{ t('bookmarks', 'Details') }}
			</NcActionButton>
			<NcActionCheckbox @change="onSelect">
				{{ t('bookmarks', 'Select bookmark') }}
			</NcActionCheckbox>
			<NcActionButton :close-after-click="true"
				@click="onRename">
				<template #icon>
					<PencilIcon :size="20" />
				</template>
				{{ t('bookmarks', 'Rename') }}
			</NcActionButton>
			<NcActionButton :close-after-click="true"
				@click="onCopyUrl">
				<template #icon>
					<ContentCopyIcon :size="20" />
				</template>
				{{ t('bookmarks', 'Copy link') }}
			</NcActionButton>
			<NcActionButton :close-after-click="true" @click="onMove">
				<template #icon>
					<FolderMoveIcon :size="20" />
				</template>
				{{ t('bookmarks', 'Move') }}
			</NcActionButton>
			<NcActionButton :close-after-click="true" @click="onCopy">
				<template #icon>
					<FolderPlusIcon :size="20" />
				</template>
				{{ t('bookmarks', 'Add to folders') }}
			</NcActionButton>
			<NcActionButton :close-after-click="true"
				@click="onDelete">
				<template #icon>
					<DeleteIcon :size="20" />
				</template>
				{{ t('bookmarks', 'Delete') }}
			</NcActionButton>
		</template>
	</Item>
</template>
<script>
import Item from './Item.vue'
import { NcActionButton, NcActionCheckbox } from '@nextcloud/vue'
import { FolderPlusIcon } from './Icons.js'
import { FolderMoveIcon } from './Icons.js'
import { ContentCopyIcon } from './Icons.js'
import { PencilIcon } from './Icons.js'
import { InformationVariantIcon } from './Icons.js'
import { DeleteIcon } from './Icons.js'
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'
import { actions, mutations } from '../store/index.js'
import axios from '@nextcloud/axios'

export default {
	name: 'Bookmark',
	components: {
		Item,
		NcActionButton,
		NcActionCheckbox,
		FolderPlusIcon,
		FolderMoveIcon,
		ContentCopyIcon,
		PencilIcon,
		InformationVariantIcon,
		DeleteIcon,
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
			backgroundImage: undefined,
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
			return (
				this.apiUrl
				+ '/bookmark/'
				+ this.bookmark.id
				+ '/favicon'
				+ (this.$store.state.public
					? '?token=' + this.$store.state.authToken
					: '')
			)
		},
		imageUrl() {
			return (
				this.apiUrl
				+ '/bookmark/'
				+ this.bookmark.id
				+ '/image'
				+ (this.$store.state.public
					? '?token=' + this.$store.state.authToken
					: '')
			)
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
			return this.$store.getters.getPermissionsForBookmark(
				this.bookmark.id
			)
		},
		isEditable() {
			return this.isOwner || (!this.isOwner && this.permissions.canWrite)
		},
		selectedBookmarks() {
			return this.$store.state.selection.bookmarks
		},
		selectable() {
			return Boolean(
				this.$store.state.selection.bookmarks.length
					|| this.$store.state.selection.folders.length
			)
		},
		selected() {
			return this.selectedBookmarks
				.map(b => b.id)
				.includes(this.bookmark.id)
		},
		isActive() {
			return this.isOpen || this.selected
		},
		background() {
			return this.viewMode === 'grid' ? this.backgroundImage : undefined
		},
	},
	mounted() {
		this.fetchBackgroundImage()
	},
	methods: {
		onDelete() {
			if (
				!confirm(
					t(
						'bookmarks',
						'Do you really want to delete this bookmark?'
					)
				)
			) {
				return
			}
			this.$store.dispatch(actions.DELETE_BOOKMARK, {
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
		onCopy() {
			this.$store.commit(mutations.RESET_SELECTION)
			this.$store.commit(mutations.ADD_SELECTION_BOOKMARK, this.bookmark)
			this.$store.commit(mutations.DISPLAY_COPY_DIALOG, true)
		},
		async onRename() {
			this.renaming = true
		},
		async onRenameSubmit(title) {
			// eslint-disable-next-line vue/no-mutating-props
			this.bookmark.title = title
			await this.$store.dispatch(actions.SAVE_BOOKMARK, this.bookmark.id)
			this.renaming = false
		},
		onSelect() {
			if (!this.selected) {
				this.$store.commit(
					mutations.ADD_SELECTION_BOOKMARK,
					this.bookmark
				)
			} else {
				this.$store.commit(
					mutations.REMOVE_SELECTION_BOOKMARK,
					this.bookmark
				)
			}
		},
		onClick() {
			if (this.bookmark.url.startsWith('file:')) {
				this.$store.commit(mutations.SET_ERROR, 'Most browsers will not allow clicking on file links. Try copying the URL')
			}
			this.$store.dispatch(actions.CLICK_BOOKMARK, this.bookmark)
		},
		onCopyUrl() {
			navigator.clipboard.writeText(this.bookmark.target)
			this.$store.commit(mutations.SET_NOTIFICATION, this.t('bookmarks', 'Link copied to clipboard'))
		},
		async fetchBackgroundImage() {
			if (this.colorMainBackground === '#ffffff') {
				this.backgroundImage = 'var(--icon-link-000) no-repeat center 25% / 50% !important'
			} else {
				this.backgroundImage = 'var(--icon-link-fff) no-repeat center 25% / 50% !important'
			}
			try {
				const response = await axios.get(this.imageUrl, { responseType: 'blob' })
				const url = URL.createObjectURL(response.data)
				this.backgroundImage = `linear-gradient(0deg, var(--color-main-background) 25%, rgba(0, 212, 255, 0) 50%), url('${url}')`
			} catch (e) {
				if (this.colorMainBackground === '#ffffff') {
					this.backgroundImage = 'var(--icon-link-000) no-repeat center 25% / 50% !important'
				} else {
					this.backgroundImage = 'var(--icon-link-fff) no-repeat center 25% / 50% !important'
				}
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
	min-width: calc(50px + 40%);
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
	height: 24px;
	color: var(--color-text-lighter);
	text-overflow: ellipsis;
	overflow: hidden;
	min-width: 15px;
}

.bookmark__description figure {
	display: none !important;
}

.item--gridview .bookmark__title {
	min-width: auto;
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
