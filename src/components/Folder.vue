<template>
  <div :class="{folder: true, 'folder--gridview': viewMode === 'grid'}">
    <figure class="folder__icon icon-folder" />
    <template v-if="!renaming">
      <h3
        class="folder__title"
        :title="folder.title"
        @click="onSelect"
      >
        {{ folder.title }}
      </h3>
      <Actions class="folder__actions">
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
        <input ref="input" v-model="title" type="text"
               @keyup.enter="onRenameSubmit"
        >
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
import Vue from 'vue';
import { Actions, ActionButton } from 'nextcloud-vue';
import { actions, mutations } from '../store';

export default {
	name: 'Folder',
	components: {
		Actions,
		ActionButton
	},
	props: {
		folder: {
			type: Object,
			required: true
		}
	},
	data() {
		return { renaming: false, title: this.folder.title };
	},
	computed: {
		viewMode() {
			return this.$store.state.viewMode;
		}
	},
	created() {},
	methods: {
		onDelete() {
			this.$store.dispatch(actions.DELETE_FOLDER, this.folder.id);
		},
		onMove() {
			this.$store.commit(mutations.RESET_SELECTION);
			this.$store.commit(mutations.ADD_SELECTION_FOLDER, this.folder);
			this.$store.commit(mutations.DISPLAY_MOVE_DIALOG, true);
		},
		onSelect() {
			this.$router.push({ name: 'folder', params: { folder: this.folder.id } });
		},
		async onRename() {
			this.renaming = true;
			await Vue.nextTick();
			this.$refs['input'].focus();
		},
		onRenameSubmit() {
			this.folder.title = this.title;
			this.$store.dispatch(actions.SAVE_FOLDER, this.folder.id);
			this.renaming = false;
		}
	}
};
</script>
<style>
.folder {
	border-bottom: 1px solid var(--color-border);
	display: flex;
	align-items: center;
	position: relative;
}

.folder:hover {
	background: var(--color-background-dark);
}

.folder__icon {
	flex: 0;
	height: 20px;
	width: 20px;
	background-size: cover;
	margin: 15px;
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
	display: flex;
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
