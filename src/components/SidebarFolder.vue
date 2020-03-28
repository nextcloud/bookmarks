<template>
	<AppSidebar
			v-if="isActive"
			class="sidebar"
			:title="folder.title"
			@close="onClose">
		<AppSidebarTab :name="t('bookmarks', 'Details')" icon="icon-info">
			<h3>{{ t('bookmarks', 'Owner') }}</h3>
			<UserBubble :user="folder.userId" :display-name="folder.userId"/>
		</AppSidebarTab>
		<AppSidebarTab :name="t('bookmarks', 'Sharing')" icon="icon-shared">
			<!-- <Multiselect :user-select="true" /> -->
			<div class="public-link">
				<h3 class="public-link__description">
					<figure class="icon-public"/>
					{{ t('bookmarks', 'Share link') }}
				</h3>
				<Actions class="public-link__actions">
					<template v-if="publicLink">
						<ActionButton icon="icon-clippy" @click="onCopyPublicLink">
							{{ t('bookmarks', 'Copy link') }}
						</ActionButton>
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
				<UserBubble :user="share.participant" :display-name="share.participant"/>
			</div>
		</AppSidebarTab>
	</AppSidebar>
</template>
<script>
	import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
	import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
	import UserBubble from '@nextcloud/vue/dist/Components/UserBubble'
	import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
	import Actions from '@nextcloud/vue/dist/Components/Actions'
	import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
	import { generateUrl } from '@nextcloud/router'
	import copy from 'copy-text-to-clipboard'
	import { actions, mutations } from '../store/'

	export default {
		name: 'SidebarFolder',
		components: { AppSidebar, AppSidebarTab, UserBubble, Multiselect, ActionButton, Actions },
		data() {
			return {}
		},
		computed: {
			isActive() {
				if (!this.$store.state.sidebar) return false
				return this.$store.state.sidebar.type === 'folder'
			},
			folder() {
				if (!this.isActive) return
				const folders = this.$store.getters.getFolder(this.$store.state.sidebar.id)
				const folder = folders[0]
				this.$store.dispatch(actions.LOAD_SHARES_OF_FOLDER, folder.id)
				this.$store.dispatch(actions.LOAD_PUBLIC_LINK, folder.id)
				return folder
			},
			shares() {
				return this.$store.getters.getSharesOfFolder(this.folder.id)
			},
			token() {
				return this.$store.getters.getTokenOfFolder(this.folder.id)
			},
			publicLink() {
				if (!this.token) return
				return window.location.protocol + '//' + window.location.host + generateUrl('/apps/bookmarks/public/' + this.token)
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
			async onDeletePublicLink() {
				await this.$store.dispatch(actions.DELETE_PUBLIC_LINK, this.folder.id)
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

	.public-link {
		display: flex;
	}

	.public-link__description {
		flex: 1;
	}

	.public-link__actions {
		flex-grow: 0;
	}
</style>
