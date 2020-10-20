<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<Item :active="selected"
		:editable="isEditable"
		:selected="selected"
		:title="folder.title"
		:renaming="renaming"
		:select-label="t('bookmarks', 'Select folder')"
		:rename-placeholder="t('bookmarks', 'Enter folder title')"
		:selectable="selectable"
		@select="clickSelect"
		@rename="onRenameSubmit"
		@rename-cancel="renaming = false"
		@click="onSelect">
		<template #icon>
			<FolderIcon :fill-color="colorPrimaryElement" :class="'folder__icon'" @click="onSelect" />
			<ShareVariantIcon v-if="(isShared || !isOwner) || isSharedPublicly"
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
					{{ t('bookmarks', 'Shared by {user}', {user: folder.userId}) }}
				</div>
			</div>
		</template>
		<template #actions>
			<ActionButton icon="icon-info" :close-after-click="true" @click="onDetails">
				{{ t('bookmarks', 'Details') }}
			</ActionButton>
			<ActionCheckbox @change="clickSelect">
				{{ t('bookmarks', 'Select folder') }}
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
			<ActionButton icon="icon-delete" :close-after-click="true" @click="onDelete">
				{{ t('bookmarks', 'Delete') }}
			</ActionButton>
		</template>
	</Item>
</template>
<script>
import { getCurrentUser } from '@nextcloud/auth'
import FolderMoveIcon from 'vue-material-design-icons/FolderMove'
import FolderIcon from 'vue-material-design-icons/Folder'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionCheckbox from '@nextcloud/vue/dist/Components/ActionCheckbox'
import { actions, mutations } from '../store/'
import Item from './Item'

export default {
	name: 'Folder',
	components: {
		Item,
		ActionButton,
		ActionCheckbox,
		FolderIcon,
		FolderMoveIcon,
		ShareVariantIcon,
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
		permissions() {
			return this.$store.getters.getPermissionsForFolder(this.folder.id)
		},
		isEditable() {
			return this.isOwner || (!this.isOwner && this.permissions.canWrite)
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
	created() {
		this.$store.dispatch(actions.LOAD_SHARES_OF_FOLDER, this.folder.id)
		this.$store.dispatch(actions.LOAD_PUBLIC_LINK, this.folder.id)
		this.$store.dispatch(actions.COUNT_BOOKMARKS, this.folder.id)
	},
	methods: {
		onDetails() {
			this.$store.dispatch(actions.OPEN_FOLDER_DETAILS, this.folder.id)
		},
		onDelete() {
			this.$store.dispatch(actions.DELETE_FOLDER, { id: this.folder.id })
		},
		onMove() {
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
			this.folder.title = title
			this.$store.dispatch(actions.SAVE_FOLDER, this.folder.id)
			this.renaming = false
		},
		clickSelect(e) {
			if (!this.selected) {
				this.$store.commit(mutations.ADD_SELECTION_FOLDER, this.folder)
			} else {
				this.$store.commit(mutations.REMOVE_SELECTION_FOLDER, this.folder)
			}
		},
		onEnter(e) {
			if (e.key === 'Enter') {
				this.onSelect(e)
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
	transform: scale(0.5);
	position: absolute;
	top: 11px;
	height:auto;
	width:auto;
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
	transform: translate(100%, 90%) scale(2);
}

.folder__title {
	flex: 1;
	text-overflow: ellipsis;
	overflow: hidden;
	white-space: nowrap;
	cursor: pointer;
	margin: 0;
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
	background-color: var(--color-primary-light);
}

.action-button-mdi-icon {
	margin: 10px;
	margin-top: 6px;
	height: 21px;
}
</style>
