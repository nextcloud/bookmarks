<template>
	<div :class="{folder: true, 'folder--gridview': viewMode === 'grid', active: selected,}">
		<div v-if="isEditable" class="folder__checkbox">
			<input v-model="selected" class="checkbox" type="checkbox"><label
				:aria-label="t('bookmarks', 'Select folder')"
				@click="clickSelect" />
		</div>
		<figure :class="{'folder__icon': true, 'shared': !isOwner && !isPublic || isSharedPublicly || isShared }" @click="onSelect" />
		<template v-if="!renaming">
			<h3
				class="folder__title"
				:title="folder.title"
				@click="onSelect">
				{{ folder.title }}
			</h3>

			<div class="folder__tags">
				<div v-if="!isOwner && !isPublic" class="folder__tag">
					{{ t('bookmarks', 'Shared by {user}', {user: folder.userId}) }}
				</div>
				<div v-if="isOwner && isShared" class="folder__tag">
					{{ t('bookmarks','Shared privately') }}
				</div>
				<div v-if="isSharedPublicly" class="folder__tag">
					{{ t('bookmarks', 'Public') }}
				</div>
			</div>
			<Actions v-if="isEditable" class="folder__actions">
				<ActionButton icon="icon-info" @click="onDetails">
					{{ t('bookmarks', 'Details') }}
				</ActionButton>
				<ActionButton icon="icon-rename" @click="onRename">
					{{ t('bookmarks', 'Rename') }}
				</ActionButton>
				<ActionButton icon="icon-category-files" @click="onMove">
					{{ t('bookmarks', 'Move') }}
				</ActionButton>
				<ActionButton icon="icon-delete" @click="onDelete">
					{{ t('bookmarks', 'Delete') }}
				</ActionButton>
			</Actions>
		</template>
		<template v-else>
			<span class="folder__title">
				<input ref="input"
					v-model="title"
					type="text"
					:placeholder="t('bookmarks', 'Enter folder title')"
					@keyup.enter="onRenameSubmit">
				<Actions>
					<ActionButton icon="icon-checkmark" @click="onRenameSubmit">
						{{ t('bookmarks', 'Save') }}
					</ActionButton>
				</Actions>
			</span>
		</template>
	</div>
</template>
<script>
import Vue from 'vue'
import { getCurrentUser } from '@nextcloud/auth'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import { actions, mutations } from '../store/'

export default {
	name: 'Folder',
	components: {
		Actions,
		ActionButton,
	},
	props: {
		folder: {
			type: Object,
			required: true,
		},
	},
	data() {
		return { renaming: false, title: this.folder.title }
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
		selected() {
			return this.selectedFolders.map(f => f.id).includes(this.folder.id)
		},
	},
	created() {
		this.$store.dispatch(actions.LOAD_SHARES_OF_FOLDER, this.folder.id)
		this.$store.dispatch(actions.LOAD_PUBLIC_LINK, this.folder.id)
	},
	methods: {
		onDetails() {
			this.$store.dispatch(actions.OPEN_FOLDER_DETAILS, this.folder.id)
		},
		onDelete() {
			this.$store.dispatch(actions.DELETE_FOLDER, this.folder.id)
		},
		onMove() {
			this.$store.commit(mutations.RESET_SELECTION)
			this.$store.commit(mutations.ADD_SELECTION_FOLDER, this.folder)
			this.$store.commit(mutations.DISPLAY_MOVE_DIALOG, true)
		},
		onSelect() {
			this.$router.push({ name: this.routes.FOLDER, params: { folder: this.folder.id } })
		},
		async onRename() {
			this.renaming = true
			await Vue.nextTick()
			this.$refs['input'].focus()
		},
		onRenameSubmit() {
			this.folder.title = this.title
			this.$store.dispatch(actions.SAVE_FOLDER, this.folder.id)
			this.renaming = false
		},
		clickSelect() {
			if (!this.selected) {
				this.$store.commit(mutations.ADD_SELECTION_FOLDER, this.folder)
			} else {
				this.$store.commit(mutations.REMOVE_SELECTION_FOLDER, this.folder)
			}
		},
	},
}
</script>
<style>
.folder {
	border-bottom: 1px solid var(--color-border);
	display: flex;
	align-items: center;
	position: relative;
}

.folder.active,
.folder:hover {
	background: var(--color-background-dark);
}

.folder--gridview.active {
	border-color: var(--color-primary-element);
}

.folder__checkbox {
	display: inline-block;
}

.folder--gridview .folder__checkbox {
	position: absolute;
	top: 10px;
	left: 10px;
	background: white;
	border-radius: var(--border-radius);
}

.folder__icon {
	flex-grow: 0;
	height: 20px;
	width: 20px;
	background-size: cover;
	margin: 15px;
	cursor: pointer;
	background-image: url('/svg/core/filetypes/folder?color=0082c9'), var(--icon-folder-000);
	background-repeat: no-repeat;
	background-position: center;
}

.folder__icon.shared {
	background-image: var(--icon-share-fff), url('/svg/core/filetypes/folder?color=0082c9'), var(--icon-folder-000);
	background-size: 9px, cover, cover !important;
}

.folder--gridview .folder__icon.shared {
	background-size: 30px, cover, cover !important;
}

.folder--gridview .folder__icon {
	height: 70px;
	width: 70px;
	background-size: cover;
	position: absolute;
	top: 20%;
	left: calc(45% - 35px);
}

.folder__title {
	flex: 1;
	text-overflow: ellipsis;
	overflow: hidden;
	white-space: nowrap;
	cursor: pointer;
	margin: 0;
	padding: 15px 0;
}

.folder--gridview .folder__title {
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

.folder--gridview .folder__tags {
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

.folder__actions {
	flex: 0;
}

.folder__title input {
	width: 100%;
	border-top: none;
	border-left: none;
	border-right: none;
}

.folder__title button {
	height: 20px;
}
</style>
