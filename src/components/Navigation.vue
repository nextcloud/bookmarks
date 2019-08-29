<template>
  <AppNavigation>
    <AppNavigationNew
      :text="t('bookmarks', 'New Bookmark')"
      :disabled="false"
      button-class="icon-add"
      @click="onNewBookmark"
    />
    <ul>
      <AppNavigationItem v-for="item in menu" :key="item.text" :item="item" />
    </ul>
    <AppNavigationSettings><Settings /></AppNavigationSettings>
  </AppNavigation>
</template>

<script>
import {
	AppNavigation,
	AppNavigationNew,
	AppNavigationItem,
	AppNavigationSettings
} from 'nextcloud-vue';
import Settings from './Settings';
import { actions, mutations } from '../store/';

export default {
	name: 'App',
	components: {
		AppNavigation,
		AppNavigationNew,
		AppNavigationItem,
		AppNavigationSettings,
		Settings
	},
	data() {
		return {
			editingTag: false
		};
	},
	computed: {
		tagMenu() {
			return this.$store.state.tags.map(tag => ({
				router: { name: 'tags', params: { tags: tag.name } },
				icon: 'icon-tag',
				classes: this.editingTag === tag.name ? ['editing'] : [],
				text: tag.name,
				edit: {
					action: e => this.onRenameTag(tag.name, e.target.elements[0].value),
					reset: () => this.setEditingTag(tag.name, false)
				},
				utils: {
					counter: tag.count,
					actions: [
						{
							icon: 'icon-rename',
							text: 'Rename',
							action: () => this.setEditingTag(tag.name, true)
						},
						{
							icon: 'icon-delete',
							text: 'Delete',
							action: () => this.onDeleteTag(tag.name)
						}
					]
				}
			}));
		},

		menu() {
			return [
				{
					router: { name: 'home' },
					icon: 'icon-home',
					text: this.t('bookmarks', 'All Bookmarks')
				},
				{
					router: { name: 'recent' },
					icon: 'icon-category-monitoring',
					text: this.t('bookmarks', 'Recent Bookmarks')
				},
				{
					router: { name: 'untagged' },
					icon: 'icon-category-disabled',
					text: this.t('bookmarks', 'Untagged')
				},
				...this.tagMenu
			];
		}
	},

	created() {},

	methods: {
		onNewBookmark() {
			this.$store.commit(
				mutations.DISPLAY_NEW_BOOKMARK,
				!this.$store.state.displayNewBookmark
			);
		},
		onDeleteTag(tag) {
			this.$store.dispatch(actions.DELETE_TAG, tag);
		},
		onRenameTag(e, newName) {
			if (!this.editingTag) return;
			const oldName = this.editingTag;
			this.editingTag = false;
			this.$store.dispatch(actions.RENAME_TAG, { oldName, newName });
		},
		setEditingTag(tag, set) {
			if (set) {
				this.editingTag = tag;
			} else {
				this.editingTag = false;
			}
		}
	}
};
</script>
