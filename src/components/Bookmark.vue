<template>
  <div
    :class="{
      bookmark: true,
      active: isOpen,
      'bookmark--gridview': viewMode === 'grid'
    }"
    :style="{
      background:
        viewMode === 'grid'
          ? `linear-gradient(0deg,	var(--color-main-background) 25%, rgba(0, 212, 255, 0) 50%), url('${imageUrl}')`
          : undefined
    }"
  >
    <template v-if="!renaming">
      <a :href="url" target="_blank" class="bookmark__title">
        <h3 :title="bookmark.title">
          <figure
            class="bookmark__icon"
            :style="{ backgroundImage: 'url(' + iconUrl + ')' }"
          />
          {{ bookmark.title }}
        </h3>
      </a>
      <TagLine :tags="bookmark.tags" />
      <span
        v-if="bookmark.description"
        v-tooltip="bookmark.description"
        class="icon-file bookmark__description"
      />
      <Actions class="bookmark__actions">
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
    <h3 v-else class="bookmark__title">
      <figure
        class="bookmark__icon"
        :style="{ backgroundImage: 'url(' + iconUrl + ')' }"
      />
      <input ref="input" v-model="title" type="text"
             :placeholder="t('bookmarks', 'Enter bookmark title')"
             @keyup.enter="onRenameSubmit"
      >
      <Actions>
        <ActionButton icon="icon-checkmark" @click="onRenameSubmit">
          {{ t('bookmarks', 'Save') }}
        </ActionButton>
      </Actions>
    </h3>
  </div>
</template>
<script>
import Vue from 'vue';
import Actions from 'nextcloud-vue/dist/Components/Actions';
import ActionButton from 'nextcloud-vue/dist/Components/ActionButton';
import { generateUrl } from 'nextcloud-router';
import { actions, mutations } from '../store/';
import TagLine from './TagLine';

export default {
	name: 'Bookmark',
	components: {
		Actions,
		ActionButton,
		TagLine
	},
	props: {
		bookmark: {
			type: Object,
			required: true
		}
	},
	data() {
		return { title: this.bookmark.title, renaming: false };
	},
	computed: {
		iconUrl() {
			return generateUrl(
				'/apps/bookmarks/bookmark/' + this.bookmark.id + '/favicon'
			);
		},
		imageUrl() {
			return generateUrl(
				'/apps/bookmarks/bookmark/' + this.bookmark.id + '/image'
			);
		},
		url() {
			return this.bookmark.url;
		},
		isOpen() {
			return this.$store.state.sidebar
				&& this.$store.state.sidebar.type === 'bookmark'
				? this.$store.state.sidebar.id === this.bookmark.id
				: false;
		},
		viewMode() {
			return this.$store.state.viewMode;
		}
	},
	created() {},
	methods: {
		onDelete() {
			this.$store.dispatch(actions.DELETE_BOOKMARK, {
				id: this.bookmark.id,
				folder: this.$store.state.fetchState.query.folder
			});
		},
		onDetails() {
			this.$store.dispatch(actions.OPEN_BOOKMARK, this.bookmark.id);
		},
		onMove() {
			this.$store.commit(mutations.RESET_SELECTION);
			this.$store.commit(mutations.ADD_SELECTION_BOOKMARK, this.bookmark);
			this.$store.commit(mutations.DISPLAY_MOVE_DIALOG, true);
		},
		async onRename() {
			this.renaming = true;
			await Vue.nextTick();
			this.$refs['input'].focus();
		},
		async onRenameSubmit() {
			this.bookmark.title = this.title;
			await this.$store.dispatch(actions.SAVE_BOOKMARK, this.bookmark.id);
			this.renaming = false;
		}
	}
};
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
}

.bookmark.active,
.bookmark:hover {
	background: var(--color-background-dark);
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

.bookmark__title {
	display: flex;
	flex: 1;
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
	flex: 0;
	width: 20px;
	height: 47px;
	margin: 0 10px;
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

.bookmark--gridview .bookmark__icon {
	margin: 0 5px 0 10px;
}
</style>
