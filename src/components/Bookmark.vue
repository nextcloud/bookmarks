<!--
  - Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<NcPopover popup-role="dialog" :shown="popoverShown">
		<template #trigger>
			<div @mouseenter="(![routes.FOLDER, routes.HOME].includes($route.name)) && (popoverShown = true)" @mouseleave="popoverShown = false">
				<Item :title="bookmark.title"
					:tags="bookmark.tags"
					:rename-placeholder="t('bookmarks', 'Enter new title')"
					:select-label="t('bookmarks', 'Select bookmark')"
					:active="isActive"
					:editable="isEditable"
					:selected="selected"
					:draggable="isDraggable"
					:renaming="renaming"
					:background="background"
					:url="url"
					:selectable="selectable"
					@select="onSelect"
					@rename="onRenameSubmit"
					@rename-cancel="renaming = false"
					@click="onClick">
					<template #icon>
						<span v-if="bookmark.preliminary" class="icon-loading-small bookmark__icon" />
						<BookmarksIcon v-else-if="!iconLoaded" :size="20" class="bookmark__icon" />
						<figure v-else
							class="bookmark__icon"
							:style="{ backgroundImage: iconImage }" />
					</template>
					<template #title>
						<div class="bookmark__title">
							<h3 :title="displayTitle">
								{{ displayTitle }}
							</h3>
							<span v-if="bookmark.description"
								v-tooltip="bookmark.description"
								class="bookmark__description"><figure class="icon-file" />
								{{ bookmark.description }}</span>
						</div>
					</template>
					<template #rating>
						<template v-if="hotness === 0">
							<div :title="t('bookmarks', 'You have never clicked this link')"><HotnessZero :size="20" /></div>
						</template>
						<template v-if="hotness === 1">
							<div :title="t('bookmarks', 'You have clicked this link {count} times', {count: largeNumbers(bookmark.clickcount)})"><Hotness :size="20" /></div>
						</template>
						<template v-if="hotness === 2">
							<div :title="t('bookmarks', 'You have clicked this link {count} times', {count: largeNumbers(bookmark.clickcount)})"><Hotness :size="20" /><Hotness :size="20" /></div>
						</template>
						<template v-if="hotness === 3">
							<div :title="t('bookmarks', 'You have clicked this link {count} times', {count: largeNumbers(bookmark.clickcount)})"><Hotness :size="20" /><Hotness :size="20" /><Hotness :size="20" /></div>
						</template>
					</template>
					<template #actions>
						<template v-if="!isTrashbin">
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
							<NcActionButton :close-after-click="true" @click="onDelete(false)">
								<template #icon>
									<DeleteIcon :size="20" />
								</template>
								{{ t('bookmarks', 'Put bookmark into trash bin') }}
							</NcActionButton>
						</template>
						<template v-else>
							<NcActionButton :close-after-click="true" @click="onUndelete">
								<template #icon>
									<UndeleteIcon :size="20" />
								</template>
								{{ t('bookmarks', 'Restore bookmark') }}
							</NcActionButton>
							<NcActionButton :close-after-click="true" @click="onDelete(true)">
								<template #icon>
									<DeleteForeverIcon :size="20" />
								</template>
								{{ t('bookmarks', 'Delete bookmark permanently') }}
							</NcActionButton>
						</template>
					</template>
				</Item>
			</div>
		</template>
		<ul class="bookmark__folder-tooltip">
			<li v-for="folderId in bookmark.folders"
				:key="folderId">
				<FolderIcon :size="20" /> {{ getFolderPath(folderId) }}
			</li>
		</ul>
	</NcPopover>
</template>
<script>
import Item from './Item.vue'
import { NcActionButton, NcActionCheckbox, NcPopover } from '@nextcloud/vue'
import { Hotness, HotnessZero, FolderIcon, UndeleteIcon, DeleteForeverIcon, FolderPlusIcon, FolderMoveIcon, ContentCopyIcon, PencilIcon, InformationVariantIcon, DeleteIcon, BookmarksIcon } from './Icons.js'
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
		FolderIcon,
		ContentCopyIcon,
		PencilIcon,
		InformationVariantIcon,
		DeleteIcon,
		DeleteForeverIcon,
		UndeleteIcon,
		BookmarksIcon,
		NcPopover,
		Hotness,
		HotnessZero,
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
			iconImage: undefined,
			iconLoaded: false,
			popoverShown: false,
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
		displayTitle() {
			return this.bookmark.title || this.bookmark.url
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
		isDraggable() {
			return this.isEditable && !this.isTrashbin
		},
		isEditable() {
			return (this.isOwner || (!this.isOwner && this.permissions.canWrite))
		},
		selectedBookmarks() {
			return this.$store.state.selection.bookmarks
		},
		selectable() {
			return Boolean(
				(this.$store.state.selection.bookmarks.length
					|| this.$store.state.selection.folders.length) && !this.isTrashbin
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
		folder() {
			return this.$store.getters.getFolder(this.$store.state.fetchState.query.folder)[0]
		},
		isTrashbin() {
			return (this.folder && this.folder.softDeleted) || this.$route.name === this.routes.TRASHBIN
		},
		hotness() {
			const avgClickCount = this.$store.state.allClicksCount / this.$store.state.withClicksCount
			if (this.$store.state.allClicksCount < 100) {
				const totalClicks = this.$store.state.allClicksCount
				return this.bookmark.clickcount <= 0 ? 0 : this.bookmark.clickcount < totalClicks * 0.25 ? 1 : this.bookmark.clickcount < totalClicks * 0.5 ? 2 : 3
			}
			const avgClicksLog = Math.log10(avgClickCount)
			return this.bookmark.clickcount <= 0 ? 0 : Math.log10(this.bookmark.clickcount) < avgClicksLog * 0.5 ? 1 : Math.log10(this.bookmark.clickcount) < avgClicksLog * 1.5 ? 2 : 3
		},
	},
	mounted() {
		this.fetchBackgroundImage()
		this.fetchIcon()
	},
	methods: {
		largeNumbers(num) {
			return num > 10000 ? Math.round(num / 1000) + 'K' : num >= 1000 ? (Math.round(num / 100) / 10) + 'K' : num
		},
		getFolderPath(id) {
			const path = this.$store.getters.getFolder(id).reverse().map(folder => folder.title)
			return '/' + path.join('/')
		},
		onDelete(hard) {
			if (
				hard && !confirm(
					t(
						'bookmarks',
						'Do you really want to delete this bookmark?'
					)
				)
			) {
				return
			}
			if ((this.$route.name === this.routes.FOLDER || this.$route.name === this.routes.HOME) && this.$store.state.fetchState.query.folder) {
				this.$store.dispatch(actions.DELETE_BOOKMARK, {
					id: this.bookmark.id,
					folder: this.$store.state.fetchState.query.folder,
					hard,
				})
			} else {
				this.bookmark.folders.forEach((folder) => this.$store.dispatch(actions.DELETE_BOOKMARK, {
					id: this.bookmark.id,
					folder,
					hard,
				}))
			}
		},
		onUndelete() {
			if (this.$route.name === this.routes.FOLDER && this.$store.state.fetchState.query.folder) {
				this.$store.dispatch(actions.UNDELETE_BOOKMARK, {
					id: this.bookmark.id,
					folder: this.$store.state.fetchState.query.folder,
				})
			} else {
				this.bookmark.folders.forEach((folder) => this.$store.dispatch(actions.UNDELETE_BOOKMARK, {
					id: this.bookmark.id,
					folder,
				}))
			}
		},
		onDetails() {
			this.$store.dispatch(actions.OPEN_BOOKMARK, this.bookmark.id)
		},
		onMove() {
			if (this.isTrashbin) {
				return
			}
			this.$store.commit(mutations.RESET_SELECTION)
			this.$store.commit(mutations.ADD_SELECTION_BOOKMARK, this.bookmark)
			this.$store.commit(mutations.DISPLAY_MOVE_DIALOG, true)
		},
		onCopy() {
			if (this.isTrashbin) {
				return
			}
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
			if (this.isTrashbin) {
				return
			}
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
			if (this.bookmark.lastPreview === 0) {
				return
			}
			try {
				const response = await axios.get(this.imageUrl, { responseType: 'blob' })
				const url = URL.createObjectURL(response.data)
				this.backgroundImage = `linear-gradient(0deg, var(--color-main-background) 25%, rgba(0, 212, 255, 0) 50%), url('${url}')`
			} catch (e) {
			}
		},
		async fetchIcon() {
			this.iconLoaded = false
			if (this.bookmark.lastPreview === 0) {
				return
			}
			try {
				const response = await axios.get(this.iconUrl, { responseType: 'blob' })
				const url = URL.createObjectURL(response.data)
				this.iconImage = `url('${url}')`
				this.iconLoaded = true
			} catch (e) {
				this.iconLoaded = false
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
	margin-left: 15px;
}

.item--gridview .bookmark__icon {
	background-size: cover;
	position: absolute;
	top: 20%;
	left: calc(45% - 20px);
	transform: scale(2);
	transform-origin: top left;
	margin: 0 5px 0 8px;
}

.item--gridview .bookmark__description {
	flex: 0;
}

.item--gridview .bookmark__description figure {
	display: inline-block !important;
	position: relative;
	top: 5px;
}

.bookmark__folder-tooltip {
	padding: 10px;
	min-width: 150px;
}

.bookmark__folder-tooltip li {
	display: flex;
}
</style>
