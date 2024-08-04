<!--
  - Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<Item :active="selected"
		:editable="isEditable"
		:draggable="isDraggable"
		:selected="selected"
		:title="folder.title"
		:renaming="renaming"
		:select-label="t('bookmarks', 'Select folder')"
		:rename-placeholder="t('bookmarks', 'Enter folder title')"
		:selectable="selectable"
		:allow-drop="allowDrop"
		@drop="onDrop"
		@select="clickSelect"
		@rename="onRenameSubmit"
		@rename-cancel="renaming = false"
		@click="onSelect">
		<template #icon>
			<FolderIcon :size="20"
				:fill-color="colorPrimaryElement"
				:class="'folder__icon'"
				@click="onSelect" />
			<ShareVariantIcon v-if="(isShared || !isOwner) || isSharedPublicly"
				:size="20"
				:fill-color="colorPrimaryText"
				:class="['folder__icon', 'shared']" />
		</template>
		<template #title>
			<h3 class="folder__title">
				{{ folder.title }}
			</h3>
		</template>
		<template #tags>
			<div class="folder__tags">
				<div v-if="!isOwner && !isSharedPublicly" class="folder__tag">
					{{ t('bookmarks', 'Shared by {user}', {user: folder.userDisplayName}) }}
				</div>
			</div>
		</template>
		<template #actions>
			<template v-if="!isTrashbin">
				<NcActionButton :close-after-click="true" @click="onDetails">
					<template #icon>
						<InformationVariantIcon :size="20" />
					</template>
					{{ t('bookmarks', 'Details') }}
				</NcActionButton>
				<NcActionCheckbox @change="clickSelect">
					{{ t('bookmarks', 'Select folder') }}
				</NcActionCheckbox>
				<NcActionButton v-if="permissions.canShare"
					icon="icon-share"
					:close-after-click="true"
					@click="onShare">
					<template #icon>
						<ShareVariantIcon :size="20" />
					</template>
					{{ t('bookmarks', 'Share folder') }}
				</NcActionButton>
				<NcActionButton :close-after-click="true" @click="onRename">
					<template #icon>
						<PencilIcon :size="20" />
					</template>
					{{ t('bookmarks', 'Rename folder') }}
				</NcActionButton>
				<NcActionButton :close-after-click="true" @click="onMove">
					<template #icon>
						<FolderMoveIcon :size="20" :fill-color="colorMainText" />
					</template>
					{{ t('bookmarks', 'Move folder') }}
				</NcActionButton>
				<NcActionButton :close-after-click="true" @click="onDelete(false)">
					<template #icon>
						<DeleteIcon :size="20" />
					</template>
					{{ t('bookmarks', 'Put folder into trashbin') }}
				</NcActionButton>
			</template>
			<template v-else>
				<NcActionButton :close-after-click="true" @click="onUndelete">
					<template #icon>
						<UndeleteIcon :size="20" />
					</template>
					{{ t('bookmarks', 'Restore folder') }}
				</NcActionButton>
				<NcActionButton :close-after-click="true" @click="onDelete(true)">
					<template #icon>
						<DeleteForeverIcon :size="20" />
					</template>
					{{ t('bookmarks', 'Delete folder permanently') }}
				</NcActionButton>
			</template>
		</template>
	</Item>
</template>
<script>
import { getCurrentUser } from '@nextcloud/auth'
import { UndeleteIcon, DeleteForeverIcon, FolderMoveIcon, FolderIcon, ShareVariantIcon, DeleteIcon, PencilIcon, InformationVariantIcon } from './Icons.js'
import { NcActionButton, NcActionCheckbox } from '@nextcloud/vue'
import { actions, mutations } from '../store/index.js'
import Item from './Item.vue'

export default {
	name: 'Folder',
	components: {
		Item,
		NcActionButton,
		NcActionCheckbox,
		FolderIcon,
		FolderMoveIcon,
		ShareVariantIcon,
		DeleteIcon,
		DeleteForeverIcon,
		UndeleteIcon,
		PencilIcon,
		InformationVariantIcon,
	},
	props: {
		folder: {
			type: Object,
			required: true,
		},
	},
	data() {
		return { renaming: false }
	},
	computed: {
		viewMode() {
			return this.$store.state.viewMode
		},
		currentUser() {
			return getCurrentUser().uid
		},
		isOwner() {
			const currentUser = getCurrentUser()
			return currentUser && this.folder.userId === currentUser.uid
		},
		containingFolder() {
			return this.$store.getters.getFolder(this.$store.state.fetchState.query.folder)[0]
		},
		isTrashbin() {
			return (this.containingFolder && this.containingFolder.softDeleted) || this.$route.name === this.routes.TRASHBIN
		},
		permissions() {
			return this.$store.getters.getPermissionsForFolder(this.folder.id)
		},
		isDirectShare() {
			return this.$store.state.sharedFoldersById[this.folder.id] !== undefined
		},
		isEditable() {
			return this.isOwner || this.isDirectShare || this.permissions.canWrite
		},
		isDraggable() {
			return this.isEditable && !this.isTrashbin
		},
		shares() {
			return this.$store.getters.getSharesOfFolder(this.folder.id)
		},
		publicToken() {
			return this.$store.getters.getTokenOfFolder(this.folder.id)
		},
		isShared() {
			return Boolean(this.shares.length)
		},
		isSharedPublicly() {
			return Boolean(this.publicToken)
		},
		selectedFolders() {
			return this.$store.state.selection.folders
		},
		selectable() {
			return Boolean(this.$store.state.selection.bookmarks.length || this.$store.state.selection.folders.length)
		},
		selected() {
			return this.selectedFolders.map(f => f.id).includes(this.folder.id)
		},
	},
	mounted() {
		// This slows down initial load otherwise and it's not directly necessary
		setTimeout(() => {
			this.$store.dispatch(actions.LOAD_SHARES_OF_FOLDER, this.folder.id)
			this.$store.dispatch(actions.LOAD_PUBLIC_LINK, this.folder.id)
			this.$store.dispatch(actions.COUNT_BOOKMARKS, this.folder.id)
		}, 2000)
	},
	methods: {
		onDetails() {
			this.$store.dispatch(actions.OPEN_FOLDER_DETAILS, this.folder.id)
		},
		onShare() {
			this.$store.dispatch(actions.OPEN_FOLDER_SHARING, this.folder.id)
		},
		onDelete(hard) {
			if (hard && !confirm(t('bookmarks', 'Do you really want to permanently delete this folder?'))) {
				return
			}
			this.$store.dispatch(actions.DELETE_FOLDER, { id: this.folder.id, hard })
		},
		onUndelete() {
			this.$store.dispatch(actions.UNDELETE_FOLDER, { id: this.folder.id })
		},
		onMove() {
			if (this.isTrashbin) {
				return
			}
			this.$store.commit(mutations.RESET_SELECTION)
			this.$store.commit(mutations.ADD_SELECTION_FOLDER, this.folder)
			this.$store.commit(mutations.DISPLAY_MOVE_DIALOG, true)
		},
		onSelect(e) {
			this.$router.push({ name: this.routes.FOLDER, params: { folder: this.folder.id } })
			e.preventDefault()
		},
		async onRename() {
			this.renaming = true
		},
		onRenameSubmit(title) {
			// eslint-disable-next-line vue/no-mutating-props
			this.folder.title = title
			this.$store.dispatch(actions.SAVE_FOLDER, this.folder.id)
			this.renaming = false
		},
		clickSelect(e) {
			if (this.isTrashbin) {
				return
			}
			if (!this.selected) {
				this.$store.commit(mutations.ADD_SELECTION_FOLDER, this.folder)
			} else {
				this.$store.commit(mutations.REMOVE_SELECTION_FOLDER, this.folder)
			}
		},
		allowDrop() {
			return !this.isTrashbin && !this.$store.state.selection.folders.includes(this.folder) && (this.$store.state.selection.folders.length || this.$store.state.selection.bookmarks.length)
		},
		async onDrop(e) {
			e.preventDefault()
			try {
				await this.$store.dispatch(actions.MOVE_SELECTION, this.folder.id)
			} finally {
				this.$store.commit(mutations.RESET_SELECTION)
			}
		},
	},
}
</script>
<style>
.folder__icon {
	flex-grow: 0;
	height: 20px;
	width: 20px;
	background-size: cover;
	margin: 0 15px;
	cursor: pointer;
}

.folder__icon.shared {
	transform: scale(0.4);
	position: absolute;
	top: 0;
	height: auto;
	width: auto;
	left: -1px;
}

.item--gridview .folder__icon {
	background-size: cover;
	position: absolute;
	top: 20%;
	left: calc(45% - 50px);
	transform: scale(4);
	transform-origin: top left;
}

.item--gridview .folder__icon.shared {
	transform: translate(100%, 130%) scale(1.5);
}

.folder__title {
	flex: 1;
	text-overflow: ellipsis;
	overflow: hidden;
	white-space: nowrap;
	cursor: pointer;
	margin: 0;
	font-size: 1em;
	font-weight: normal;
}

.item--gridview .folder__title {
	margin-left: 15px;
}

.folder__tags {
	font-size: 12px;
	height: 24px;
	line-height: 1;
	overflow: hidden;
	display: inline-block;
	margin: 0 15px;
}

.item--gridview .folder__tags {
	position: absolute;
	bottom: 47px;
	left: 10px;
	margin: 0;
}

.folder__tag {
	display: inline-block;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-pill);
	padding: 5px 10px;
	margin-right: 3px;
	background-color: var(--color-primary-element-light);
}
</style>
