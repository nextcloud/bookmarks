<template>
	<div
		:class="{
			bookmark: true,
			active: isOpen || selected,
			'bookmark--gridview': viewMode === 'grid'
		}"
		:style="{
			background:
				viewMode === 'grid'
					? `linear-gradient(0deg, var(--color-main-background) 25%, rgba(0, 212, 255, 0) 50%), url('${imageUrl}')`
					: undefined
		}">
		<a
			:href="url"
			class="bookmark__click-link"
			target="_blank" />
		<template v-if="!renaming">
			<div v-if="isEditable" class="bookmark__checkbox">
				<input v-model="selected" class="checkbox" type="checkbox"><label
					v-tooltip="t('bookmarks', 'Select bookmark')"
					:aria-label="t('bookmarks', 'Select bookmark')"

					@click="clickSelect" />
			</div>
			<div class="bookmark__labels">
				<a :href="url" target="_blank" class="bookmark__title">
					<h3 :title="bookmark.title">
						<figure
							class="bookmark__icon"
							:style="{ backgroundImage: 'url(' + iconUrl + ')' }" />
						{{ bookmark.title }}
					</h3>
				</a>
				<span
					v-if="bookmark.description"
					v-tooltip="bookmark.description"
					class="bookmark__description"><figure class="icon-file" />
					{{ bookmark.description }}</span>
			</div>
			<TagLine :tags="bookmark.tags" />
			<Actions v-if="isEditable" class="bookmark__actions">
				<ActionButton icon="icon-info" @click="onDetails">
					{{ t('bookmarks', 'Details') }}
				</ActionButton>
				<ActionButton icon="icon-rename" @click="onRename">
					{{ t('bookmarks', 'Rename') }}
				</ActionButton>
				<ActionButton @click="onMove">
					{{ t('bookmarks', 'Move') }}
					<FolderMoveIcon #icon />
				</ActionButton>
				<ActionButton icon="icon-delete" @click="onDelete">
					{{ t('bookmarks', 'Delete') }}
				</ActionButton>
			</Actions>
		</template>
		<h3 v-else class="bookmark__title">
			<figure
				class="bookmark__icon"
				:style="{ backgroundImage: 'url(' + iconUrl + ')' }" />
			<input
				ref="input"
				v-model="title"
				type="text"
				:placeholder="t('bookmarks', 'Enter bookmark title')"
				@keyup.enter="onRenameSubmit">
			<Actions>
				<ActionButton icon="icon-checkmark" @click="onRenameSubmit">
					{{ t('bookmarks', 'Save') }}
				</ActionButton>
			</Actions>
		</h3>
	</div>
</template>
<script>
import Vue from 'vue'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import FolderMoveIcon from 'vue-material-design-icons/FolderMove'
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'
import { actions, mutations } from '../store/'
import TagLine from './TagLine'

export default {
	name: 'Bookmark',
	components: {
		Actions,
		ActionButton,
		TagLine,
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
		selected() {
			return this.selectedBookmarks.map(b => b.id).includes(this.bookmark.id)
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
			await Vue.nextTick()
			this.$refs.input.focus()
		},
		async onRenameSubmit() {
			this.bookmark.title = this.title
			await this.$store.dispatch(actions.SAVE_BOOKMARK, this.bookmark.id)
			this.renaming = false
		},
		clickSelect() {
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
.bookmark {
	border-bottom: 1px solid var(--color-border);
	display: flex;
	align-items: center;
	background-position: center !important;
	background-size: cover !important;
	background-color: var(--color-main-background);
	position: relative;
	padding: 0 8px 0 10px;
}

.bookmark.active,
.bookmark:hover,
.bookmark:focus {
	background: var(--color-background-dark);
}

.bookmark__checkbox {
	display: inline-block;
}

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

.bookmark__labels {
	display: flex;
	flex: 1;
	text-overflow: ellipsis;
	overflow: hidden;
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
	padding: 15px 0;
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

.bookmark--gridview.active {
	border-color: var(--color-primary-element);
}

.bookmark--gridview .bookmark__description {
	flex: 0;
}

.bookmark--gridview .bookmark__description figure {
	display: inline-block !important;
	position: relative;
	top: 5px;
}

.bookmark--gridview .bookmark__checkbox {
	position: absolute;
	top: 10px;
	left: 10px;
	background: white;
	border-radius: var(--border-radius);
}

.bookmark__actions {
	flex: 0;
}

.bookmark__title > input {
	width: 100%;
	border-top: none;
	border-left: none;
	border-right: none;
}

.bookmark__title button {
	height: 20px;
}

.bookmark--gridview .tagline {
	position: absolute;
	bottom: 47px;
	left: 10px;
	margin: 0;
}

.bookmark--gridview .bookmark__checkbox input[type='checkbox'].checkbox + label::before {
	margin: 0 3px 3px 3px;
}

.bookmark--gridview .bookmark__icon {
	margin: 0 5px 0 10px;
}

.bookmark__click-link {
	bottom: 0;
	display: none;
	left: 0;
	position: absolute;
	right: 0;
	top: 0;
}

.bookmark--gridview .bookmark__click-link {
	display: block;
}

</style>
