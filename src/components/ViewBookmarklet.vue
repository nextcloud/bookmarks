<template>
  <Content app-name="bookmarks">
    <div class="bookmarklet">
      <h2><figure :class="loading? 'icon-loading' : 'icon-link'" /> {{ t('bookmarks', 'Add a bookmark') }}</h2>
      <div v-if="exists" class="bookmarklet__exists">
        {{ t('bookmarks', 'This URL is already bookmarked! Overwrite?') }}
      </div>
      <label>{{ t('bookmarks', 'Title') }}
        <input v-model="bookmark.title" type="text" :placeholder="t('bookmarks', 'Enter bookmark title')">
      </label>
      <label>{{ t('bookmarks', 'Link') }}
        <input v-model="bookmark.url" type="text" :placeholder="t('bookmarks', 'Enter bookmark url')">
      </label>
      <label><span class="icon-tag" /> {{ t('bookmarks', 'Tags') }}
        <Multiselect
          class="sidebar__tags"
          :value="bookmark.tags"
          :auto-limit="false"
          :limit="7"
          :options="allTags"
          :multiple="true"
          :taggable="true"
          @input="onTagsChange"
          @tag="onAddTag"
        />
      </label>
      <label>{{ t('bookmarks', 'Notes') }}</label>
      <div
        class="bookmarklet__notes"
        contenteditable
        @input="onNotesChange"
      >
        {{ bookmark.description }}
      </div>
      <button class="primary" @click="submit">
        <span class="icon-confirm-white" /> Save
      </button>
    </div>
  </Content>
</template>

<script>
import { Content, Multiselect } from 'nextcloud-vue';
import { actions } from '../store/';

export default {
	name: 'ViewBookmarklet',
	components: {
		Content,
		Multiselect
	},
	props: {
		title: {
			type: String,
			default: ''
		},
		url: {
			type: String,
			default: ''
		}
	},
	data: function() {
		return {
			bookmark: {
				title: this.title,
				url: this.url,
				tags: [],
				description: ''
			},
			exists: false
		};
	},
	computed: {
		allTags() {
			return this.$store.state.tags.map(tag => tag.name);
		},
		folders() {
			return this.$store.state.folders;
		}
	},

	created() {
		this.reloadTags();
		this.reloadFolders();
	},

	mounted() {
		this.findBookmark(this.bookmark.url);
	},

	methods: {
		reloadTags() {
			this.$store.dispatch(actions.LOAD_TAGS);
		},
		reloadFolders() {
			this.$store.dispatch(actions.LOAD_FOLDERS);
		},
		reloadSettings() {
			this.$store.dispatch(actions.LOAD_SETTINGS);
		},
		onNotesChange(e) {
			this.bookmark.description = e.target.textContent;
		},
		onTagsChange(tags) {
			this.bookmark.tags = tags;
		},
		onAddTag(tag) {
			this.bookmark.tags.push(tag);
		},
		async findBookmark(url) {
			const bookmark = await this.$store.dispatch(actions.FIND_BOOKMARK, url);
			if (bookmark) {
				this.exists = true;
				this.bookmark = bookmark;
			}
		},

		async submit() {
			this.loading = true;
			if (this.exists) {
				await this.$store.dispatch(actions.SAVE_BOOKMARK, this.bookmark.id);
			} else {
				await this.$store.dispatch(actions.CREATE_BOOKMARK, this.bookmark);
			}
			window.close();
		}
	}
};
</script>
<style>
.bookmarklet {
margin: 30px auto;
width: 70%;
}
.bookmarklet__exists {
  background: var(--color-background-dark);
  border-radius: var(--border-radius-pill);
  font-weight: bold;
  padding: 10px 20px;
}
figure[class^=icon-] {
  display: inline-block;
}
.bookmarklet label {
  margin-top: 10px;
  display: block;
}
.bookmarklet input {
  width: 100%;
  display: block;
}
.bookmarklet__notes {
	min-height: 100px !important;
	width: auto !important;
}
</style>
