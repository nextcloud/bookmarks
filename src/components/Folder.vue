<template>
	<div :class="{folder: true, 'folder--gridview': viewMode === 'grid', active: selected,}"
		tabindex="0"
		@click="onSelect"
		@keypress="onEnter">
		<div v-if="isEditable" class="folder__checkbox">
			<input :id="'select'+folder.id"
				v-model="selected"
				class="checkbox"
				type="checkbox"><label
					v-tooltip="t('bookmarks', 'Select folder')"
					:aria-label="t('bookmarks', 'Select folder')"
					:for="'select'+folder.id"
					@click="clickSelect" />
		</div>
		<FolderIcon fill-color="#0082c9" :class="'folder__icon'" @click="onSelect" />
		<ShareVariantIcon v-if="(isShared || !isOwner) || isSharedPublicly"
			fill-color="#ffffff"
			:class="['folder__icon', 'shared']" />
		<template v-if="!renaming">
			<h3
				class="folder__title"
				:title="folder.title">
				{{ folder.title }}
			</h3>

			<div class="folder__tags">
				<div v-if="!isOwner && !isSharedPublicly" class="folder__tag">
					{{ t('bookmarks', 'Shared by {user}', {user: folder.userId}) }}
				</div>
			</div>
			<div v-if="count" class="folder__count folder__tag" v-text="count" />
			<Actions v-if="isEditable" ref="actions" class="folder__actions">
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
import FolderIcon from 'vue-material-design-icons/Folder'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import { actions, mutations } from '../store/'

export default {
	name: 'Folder',
	components: {
		Actions,
		ActionButton,
		FolderIcon,
		ShareVariantIcon,
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
		count() {
			return this.$store.state.countsByFolder[this.folder.id] > 99 ? '99+' : this.$store.state.countsByFolder[this.folder.id] || ''
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
			this.$store.dispatch(actions.DELETE_FOLDER, this.folder.id)
		},
		onMove() {
			this.$store.commit(mutations.RESET_SELECTION)
			this.$store.commit(mutations.ADD_SELECTION_FOLDER, this.folder)
			this.$store.commit(mutations.DISPLAY_MOVE_DIALOG, true)
		},
		onSelect(e) {
			if (this.$refs.actions.$el.contains(e.target)) return
			this.$router.push({ name: this.routes.FOLDER, params: { folder: this.folder.id } })
			e.preventDefault()
		},
		async onRename() {
			this.renaming = true
			await Vue.nextTick()
			this.$refs.input.focus()
		},
		onRenameSubmit() {
			this.folder.title = this.title
			this.$store.dispatch(actions.SAVE_FOLDER, this.folder.id)
			this.renaming = false
		},
		clickSelect(e) {
			if (!this.selected) {
				this.$store.commit(mutations.ADD_SELECTION_FOLDER, this.folder)
			} else {
				this.$store.commit(mutations.REMOVE_SELECTION_FOLDER, this.folder)
			}
			e.stopPropagation()
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
.folder {
	border-bottom: 1px solid var(--color-border);
	display: flex;
	align-items: center;
	position: relative;
	padding: 0 8px 0 10px;
}

.folder.active,
.folder:hover,
.folder:focus {
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
}

.folder__icon.shared {
	transform: scale(0.5);
	position: absolute;
	left: 35px;
	top: 2px;
	height:auto;
	width:auto;
}

.folder--gridview .folder__icon {
	background-size: cover;
	position: absolute;
	top: 20%;
	left: calc(45% - 35px);
	transform: scale(3);
	transform-origin: top left;
}

.folder--gridview .folder__icon.shared {
	transform: translate(100%, 90%);
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

.folder__count {
	font-size: 12px;
	height: 24px;
	line-height: 1;
	overflow: hidden;
}

.folder--gridview .folder__count {
	position: absolute;
	top: 10px;
	right: 10px;
}

.folder__actions {
	flex: 0;
	padding: 4px 0;
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
