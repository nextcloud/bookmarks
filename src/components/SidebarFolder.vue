<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<AppSidebar
		v-if="isActive"
		class="sidebar"
		:title="folder.title"
		@close="onClose">
		<AppSidebarTab id="folder-details" :name="t('bookmarks', 'Details')" icon="icon-info">
			<h3>{{ t('bookmarks', 'Owner') }}</h3>
			<UserBubble :user="folder.userId" :display-name="folder.userId" />
			<h3>{{ t('bookmarks', 'Bookmarks') }}</h3>
			{{ bookmarkCount }}
		</AppSidebarTab>
		<AppSidebarTab v-if="isSharable"
			id="folder-sharing"
			:name="t('bookmarks', 'Sharing')"
			icon="icon-shared">
			<div class="participant-select">
				<figure :class="{'icon-user': true, 'share__avatar': true }" />
				<Multiselect v-model="participant"
					label="displayName"
					track-by="user"
					class="participant-select__selection"
					:user-select="true"
					:options="participantSearchResults"
					:loading="isSearching"
					:placeholder="t('bookmarks', 'Select a user or group')"
					@select="onAddShare"
					@search-change="onParticipantSearch" />
			</div>
			<div class="share">
				<figure :class="{'icon-public': true, 'share__avatar': true, active: publicLink }" />
				<h3 class="share__title">
					{{ t('bookmarks', 'Share link') }}
				</h3>
				<Actions class="share__actions">
					<template v-if="publicLink">
						<ActionButton icon="icon-clippy" @click="onCopyPublicLink">
							{{ t('bookmarks', 'Copy link') }}
						</ActionButton>
						<ActionButton icon="icon-clippy" @click="onCopyRssLink">
							{{ t('bookmarks', 'Copy RSS feed') }}
						</ActionButton>
						<ActionSeparator />
						<ActionButton icon="icon-delete" @click="onDeletePublicLink">
							{{ t('bookmarks', 'Delete link') }}
						</ActionButton>
					</template>
					<ActionButton v-else icon="icon-add" @click="onAddPublicLink">
						{{ t('bookmarks', 'Create public link') }}
					</ActionButton>
				</Actions>
			</div>
			<div v-for="share of shares" :key="share.id">
				<div class="share">
					<Avatar :user="share.participant" class="share__avatar" :size="44" />
					<h3 class="share__title">
						{{ share.participant }}
					</h3>
					<Actions class="share__actions">
						<ActionCheckbox :checked="share.canWrite" @update:checked="onEditShare(share.id, {canWrite: $event, canShare: share.canShare})">
							{{ t('bookmarks', 'Allow editing') }}
						</ActionCheckbox>
						<ActionCheckbox :checked="share.canShare" @update:checked="onEditShare(share.id, {canWrite: share.canWrite, canShare: $event})">
							{{ t('bookmarks', 'Allow sharing') }}
						</ActionCheckbox>
						<ActionButton icon="icon-delete" @click="onDeleteShare(share.id)">
							{{ t('bookmarks', 'Remove share') }}
						</ActionButton>
					</Actions>
				</div>
			</div>
		</AppSidebarTab>
	</AppSidebar>
</template>
<script>
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionCheckbox from '@nextcloud/vue/dist/Components/ActionCheckbox'
import ActionSeparator from '@nextcloud/vue/dist/Components/ActionSeparator'
import UserBubble from '@nextcloud/vue/dist/Components/UserBubble'
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import copy from 'copy-text-to-clipboard'
import { actions, mutations } from '../store/'

export default {
	name: 'SidebarFolder',
	components: { AppSidebar, AppSidebarTab, Avatar, Multiselect, ActionButton, ActionCheckbox, Actions, UserBubble, ActionSeparator },
	data() {
		return {
			participantSearchResults: [],
			participant: null,
			isSearching: false,
		}
	},
	computed: {
		isActive() {
			if (!this.$store.state.sidebar) return false
			return this.$store.state.sidebar.type === 'folder'
		},
		folder() {
			if (!this.isActive) return
			const folders = this.$store.getters.getFolderIncludingDeleted(this.$store.state.sidebar.id)
			const folder = folders[0]
			if (folder.userId === getCurrentUser()) {
				this.$store.dispatch(actions.LOAD_SHARES_OF_FOLDER, folder.id)
				this.$store.dispatch(actions.LOAD_PUBLIC_LINK, folder.id)
			}
			return folder
		},
		isOwner() {
			if (!this.folder) return
			const currentUser = getCurrentUser()
			return currentUser && this.folder.userId === currentUser.uid
		},
		permissions() {
			return this.$store.getters.getPermissionsForFolder(this.folder.id)
		},
		isSharable() {
			if (!this.folder || this.folder.deleted) return
			return this.isOwner || (!this.isOwner && this.permissions.canShare)
		},
		isEditable() {
			if (!this.folder) return
			return this.isOwner || (!this.isOwner && this.permissions.canWrite)
		},
		shares() {
			if (!this.folder) return
			return this.$store.getters.getSharesOfFolder(this.folder.id)
		},
		token() {
			if (!this.folder) return
			return this.$store.getters.getTokenOfFolder(this.folder.id)
		},
		publicLink() {
			if (!this.token) return
			return window.location.origin + generateUrl('/apps/bookmarks/public/' + this.token)
		},
		rssLink() {
			return (
				window.location.origin
					+ generateUrl(
						'/apps/bookmarks/public/rest/v2/bookmark?'
							+ new URLSearchParams(
								Object.assign({}, this.$store.state.fetchState.query, {
									format: 'rss',
									page: -1,
									token: this.token,
								})
							).toString()
					)
			)
		},
		bookmarkCount() {
			return this.$store.state.countsByFolder[this.folder.id]
		},
	},

	methods: {
		onClose() {
			this.$store.commit(mutations.SET_SIDEBAR, null)
		},
		async onAddPublicLink() {
			await this.$store.dispatch(actions.CREATE_PUBLIC_LINK, this.folder.id)
			this.onCopyPublicLink()
		},
		onCopyPublicLink() {
			copy(this.publicLink)
			this.$store.commit(mutations.SET_NOTIFICATION, t('bookmarks', 'Link copied'))
		},
		onCopyRssLink() {
			copy(this.rssLink)
			this.$store.commit(mutations.SET_NOTIFICATION, t('bookmarks', 'RSS feed copied'))
		},
		async onDeletePublicLink() {
			await this.$store.dispatch(actions.DELETE_PUBLIC_LINK, this.folder.id)
		},
		async onParticipantSearch(searchTerm) {
			if (!searchTerm) {
				return
			}
			this.isSearching = true
			const { data: { ocs: { data, meta } } } = await axios.get(generateOcsUrl('apps/files_sharing/api/v1', 1) + `sharees?format=json&itemType=folder&search=${searchTerm}&lookup=false&perPage=200&shareType[]=0&shareType[]=1`)
			if (meta.status !== 'ok') {
				this.participantSearchResults = []
				return
			}
			const users = data.exact.users.concat(data.users)
			const groups = data.exact.groups.concat(data.groups)
			this.participantSearchResults = users.map(result => ({
				user: result.value.shareWith,
				displayName: result.label,
				icon: 'icon-user',
				isNoUser: false,
			})).concat(groups.map(result => ({
				user: result.value.shareWith,
				displayName: result.label,
				icon: 'icon-group',
				isNoUser: true,
			})))
			this.isSearching = false
		},
		async onAddShare(user) {
			await this.$store.dispatch(actions.CREATE_SHARE, { folderId: this.folder.id, participant: user.user, type: user.isNoUser ? 1 : 0 })
		},
		async onEditShare(shareId, { canWrite, canShare }) {
			await this.$store.dispatch(actions.EDIT_SHARE, { shareId, canWrite, canShare })
		},
		async onDeleteShare(shareId) {
			await this.$store.dispatch(actions.DELETE_SHARE, shareId)
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

	.participant-select {
		display: flex;
	}

	.participant-select__selection {
		flex: 1;
		margin-top: 5px !important;
	}

	.participant-select__actions {
		flex-grow: 0;
	}

	.share {
		display: flex;
		margin-top: 10px;
	}

	.share__avatar {
		flex-grow: 0;
		height: 44px;
		width: 44px;
	}

	.share__avatar.active {
		background-color: var(--color-primary-light);
		border-radius: 44px;
	}

	.share__title {
		flex: 1;
		padding-left: 10px;
	}

	.share__actions {
		flex-grow: 0;
	}
</style>
