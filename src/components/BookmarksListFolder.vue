<template>
  <div class="Bookmarks__BookmarksList__Folder">
    <figure class="Bookmarks__BookmarksList__Folder__Icon icon-folder" />
    <template v-if="!renaming">
      <h3
        class="Bookmarks__BookmarksList__Folder__Title"
        :title="folder.title"
        @click="onSelect"
      >
        {{ folder.title }}
      </h3>
      <Actions class="Bookmarks__BookmarksList__Folder__Actions">
        <ActionButton icon="icon-rename" @click="onRename">
          {{
            t('bookmarks', 'Rename')
          }}
        </ActionButton>
        <ActionButton icon="icon-category-files" @click="onMove">
          {{
            t('bookmarks', 'Move')
          }}
        </ActionButton>
        <ActionButton icon="icon-delete" @click="onDelete">
          {{
            t('bookmarks', 'Delete')
          }}
        </ActionButton>
      </Actions>
    </template>
    <template v-else>
      <span class="Bookmarks__BookmarksList__Folder__Icon icon-folder" />
      <h3 class="Bookmarks__BookmarksList__Folder__Title">
        <input v-model="title" type="text" @keyup.enter="onRenameSubmit">
        <button type="submit" @click="onRenameSubmit">
          <span class="icon-checkmark" />
          Save
        </button>
      </h3>
    </template>
  </div>
</template>
<script>
import { Actions, ActionButton } from 'nextcloud-vue';
import { actions, mutations } from '../store';

export default {
	name: 'BookmarksListFolder',
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
	computed: {},
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
		onRename() {
			this.renaming = true;
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
.Bookmarks__BookmarksList__Folder {
	border-bottom: 1px solid var(--color-border);
	display: flex;
	align-items: center;
	position: relative;
}
.Bookmarks__BookmarksList__Folder:hover {
	background: var(--color-background-dark);
}
.Bookmarks__BookmarksList__Folder__Icon {
	background-size: cover;
	flex: 0;
	height: 20px;
	width: 20px;
	background-size: cover;
	margin: 15px;
}
.Bookmarks__BookmarksList--GridView .Bookmarks__BookmarksList__Folder__Icon {
	background-size: cover;
	height: 70px;
	width: 70px;
	background-size: cover;
	position: absolute;
	top: 20%;
	left: calc(45% - 35px);
}
.Bookmarks__BookmarksList__Folder__Title {
	display: flex;
	flex: 1;
	text-overflow: ellipsis;
	overflow: hidden;
	white-space: nowrap;
	cursor: pointer;
	margin: 0;
	padding: 15px 0;
}
.Bookmarks__BookmarksList--GridView .Bookmarks__BookmarksList__Folder__Title {
	margin-left: 15px;
}
.Bookmarks__BookmarksList__Folder__Description {
	display: inline-block;
	flex: 0;
	width: 16px;
	height: 16px;
	margin: 0 10px;
}
.Bookmarks__BookmarksList__Folder__Actions {
	flex: 0;
}
.Bookmarks__BookmarksList__Folder__Title input {
	width: 100%;
	border-top: none;
	border-left: none;
	border-right: none;
}
.Bookmarks__BookmarksList__Folder__Title button {
	height: 20px;
}
</style>
