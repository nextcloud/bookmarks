<template>
	<AppNavigation>
		<AppNavigationNew
			:text="t('bookmarks', 'New Bookmark')"
			:disabled="false"
			button-class="icon-add"
			@click="onNewBookmark" />
		<ul>
			<AppNavigationItem v-for="item in menu" :key="item.text" :item="item" />
		</ul>
		<AppNavigationSettings><Settings /></AppNavigationSettings>
	</AppNavigation>
</template>

<script>
import AppNavigation from 'nextcloud-vue/dist/Components/AppNavigation'
import AppNavigationNew from 'nextcloud-vue/dist/Components/AppNavigationNew'
import AppNavigationItem from 'nextcloud-vue/dist/Components/AppNavigationItem'
import AppNavigationSettings from 'nextcloud-vue/dist/Components/AppNavigationSettings'
import Settings from './Settings'
import { actions, mutations } from '../store/'

export default {
	name: 'Navigation',
	components: {
		AppNavigation,
		AppNavigationNew,
		AppNavigationItem,
		AppNavigationSettings,
		Settings,
	},
	data() {
		return {
			editingTag: false,
		}
	},
	computed: {
		tagMenu() {
			return this.$store.state.tags.map(tag => ({
				router: { name: 'tags', params: { tags: tag.tag } },
				icon: 'icon-tag',
				classes: this.editingTag === tag.tag ? ['editing'] : [],
				text: tag.tag,
				edit: {
					action: e => this.onRenameTag(tag.tag, e.target.elements[0].value),
					reset: () => this.setEditingTag(tag.tag, false),
				},
				utils: {
					counter: tag.nbr,
					actions: [
						{
							icon: 'icon-rename',
							text: 'Rename',
							action: () => this.setEditingTag(tag.tag, true),
						},
						{
							icon: 'icon-delete',
							text: 'Delete',
							action: () => this.onDeleteTag(tag.tag),
						},
					],
				},
			}))
		},

		menu() {
			return [
				{
					router: { name: 'home' },
					icon: 'icon-home',
					text: this.t('bookmarks', 'All Bookmarks'),
				},
				{
					router: { name: 'recent' },
					icon: 'icon-category-monitoring',
					text: this.t('bookmarks', 'Recent Bookmarks'),
				},
				{
					router: { name: 'untagged' },
					icon: 'icon-category-disabled',
					text: this.t('bookmarks', 'Untagged'),
				},
				...this.tagMenu,
			]
		},
	},

	created() {},

	methods: {
		onNewBookmark() {
			this.$store.commit(
				mutations.DISPLAY_NEW_BOOKMARK,
				!this.$store.state.displayNewBookmark
			)
		},
		onDeleteTag(tag) {
			this.$store.dispatch(actions.DELETE_TAG, tag)
		},
		onRenameTag(e, newName) {
			if (!this.editingTag) return
			const oldName = this.editingTag
			this.editingTag = false
			this.$store.dispatch(actions.RENAME_TAG, { oldName, newName })
		},
		setEditingTag(tag, set) {
			if (set) {
				this.editingTag = tag
			} else {
				this.editingTag = false
			}
		},
	},
}
</script>
