<template>
	<AppSidebar
		v-if="isActive"
		class="sidebar"
		:title="bookmark.title"
		:background="background"
		@close="onClose">
		<AppSidebarTab id="bookmark-details" :name="t('bookmarks', 'Details')" icon="icon-info">
			<div>
				<h3>
					<span class="icon-link" />
					{{ t('bookmarks', 'Link') }}
				</h3>
				<div v-if="!editingUrl" class="bookmark-details__line">
					<span class="bookmark-details__url">{{ bookmark.url }}</span>
					<Actions class="bookmark-details__action">
						<ActionButton icon="icon-rename" @click="editingUrl = true" />
					</Actions>
				</div>
				<div v-else class="bookmark-details__line">
					<input v-model="bookmark.url" class="bookmark-details__url">
					<Actions class="bookmark-details__action">
						<ActionButton icon="icon-confirm" @click="onEditUrl" />
					</Actions>
				</div>
			</div>
			<div>
				<h3>
					<span class="icon-calendar-dark" />
					{{ t('bookmarks', 'Creation date') }}
				</h3>
				{{ addedDate }}
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
				<div class="sidebar__notes"
					:contenteditable="isEditable"
					:placeholder="t('bookmarks', 'Notes for this bookmark...')"
					@input="onNotesChange">
					{{ description }}
				</div>
			</div>
		</AppSidebarTab>
		<!--<AppSidebarTab :name="t('bookmarks', 'Sharing')" icon="icon-sharing" />-->
	</AppSidebar>
</template>
<script>
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'
import humanizeDuration from 'humanize-duration'
import { actions, mutations } from '../store/'

const MAX_RELATIVE_DATE = 1000 * 60 * 60 * 24 * 7 // one week

export default {
	name: 'SidebarBookmark',
	components: { AppSidebar, AppSidebarTab, Multiselect, Actions, ActionButton },
	data() {
		return {
			descripting: '',
			url: '',
			editingUrl: false,
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
				return this.t('bookmarks', '{time} ago', { time: duration })
			} else {
				return date.toLocaleDateString()
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
	},
	watch: {
		bookmark(newBookmark) {
			if (!this.isActive) return
			this.description = newBookmark.description
		},
	},
	created() {},
	methods: {
		onClose() {
			this.$store.commit(mutations.SET_SIDEBAR, null)
		},
		onNotesChange(e) {
			this.bookmark.description = e.target.textContent
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
		onEditUrl() {
			this.editingUrl = false
			this.scheduleSave()
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
.sidebar span[class^='icon-'] {
	display: inline-block;
	position: relative;
	top: 3px;
	opacity: 0.5;
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
</style>
