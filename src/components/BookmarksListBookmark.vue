<template>
	<div :class="{ Bookmarks__BookmarksList__Bookmark: true, active: isOpen }">
		<template v-if="!renaming">
			<a
				:href="url"
				target="_blank"
				class="Bookmarks__BookmarksList__Bookmark__Title"
			>
				<h3>
					<figure
						class="Bookmarks__BookmarksList__Bookmark__Icon"
						:style="{ backgroundImage: 'url(' + iconUrl + ')' }"
					/>
					{{ bookmark.title }}
				</h3>
			</a>
			<span
				v-if="bookmark.description"
				v-tooltip="bookmark.description"
				class="icon-file Bookmarks__BookmarksList__Bookmark__Description"
			/>
			<TagLine :tags="bookmark.tags" />
			<Actions class="Bookmarks__BookmarksList__Bookmark__Actions">
				<ActionButton icon="icon-info" @click="onDetails">{{
					t('bookmarks', 'Details')
				}}</ActionButton>
				<ActionButton icon="icon-rename" @click="onRename">{{
					t('bookmarks', 'Rename')
				}}</ActionButton>
				<ActionButton icon="icon-delete" @click="onDelete">{{
					t('bookmarks', 'Delete')
				}}</ActionButton>
			</Actions>
		</template>
		<h3 class="Bookmarks__BookmarksList__Bookmark__Title" v-else>
			<figure
				class="Bookmarks__BookmarksList__Bookmark__Icon"
				:style="{ backgroundImage: 'url(' + iconUrl + ')' }"
			/>
			<input type="text" v-model="title" @keyup.enter="onRenameSubmit" />
			<button type="submit" @click="onRenameSubmit">
				<span class="icon-checkmark"></span>
				Save
			</button>
		</h3>
	</div>
</template>
<script>
import { Actions, ActionButton } from 'nextcloud-vue';
import { actions } from '../store';
import TagLine from './TagLine';

export default {
	name: 'BookmarksListBookmark',
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
	created() {},
	computed: {
		iconUrl() {
			return OC.generateUrl(
				'/apps/bookmarks/bookmark/' + this.bookmark.id + '/favicon'
			);
		},
		url() {
			return this.bookmark.url;
		},
		isOpen() {
			return this.$store.state.sidebar &&
				this.$store.state.sidebar.type === 'bookmark'
				? this.$store.state.sidebar.id === this.bookmark.id
				: false;
		}
	},
	methods: {
		onDelete() {
			this.$store.dispatch(actions.DELETE_BOOKMARK, this.bookmark.id);
		},
		onDetails() {
			this.$store.dispatch(actions.OPEN_BOOKMARK, this.bookmark.id);
		},
		onRename() {
			this.renaming = true;
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
.Bookmarks__BookmarksList__Bookmark {
	border-bottom: 1px solid var(--color-border);
	display: flex;
	align-items: center;
}
.Bookmarks__BookmarksList__Bookmark.active,
.Bookmarks__BookmarksList__Bookmark:hover {
	background: var(--color-background-dark);
}
.Bookmarks__BookmarksList__Bookmark__Icon {
	display: inline-block;
	flex: 0;
	height: 20px;
	width: 20px;
	background-size: cover;
	margin: 0 15px;
	position: relative;
	top: 3px;
}
.Bookmarks__BookmarksList__Bookmark__Title {
	display: flex;
	flex: 1;
}
.Bookmarks__BookmarksList__Bookmark__Title,
.Bookmarks__BookmarksList__Bookmark__Title > h3 {
	text-overflow: ellipsis;
	overflow: hidden;
	white-space: nowrap;
}
.Bookmarks__BookmarksList__Bookmark__Title > h3 {
	margin: 0;
	padding: 15px 0;
}
.Bookmarks__BookmarksList__Bookmark__Description {
	display: inline-block;
	flex: 0;
	width: 16px;
	height: 16px;
	margin: 0 10px;
}
.Bookmarks__BookmarksList__Bookmark__Actions {
	flex: 0;
}
.Bookmarks__BookmarksList__Bookmark__Title > input {
	width: 100%;
	border-top: none;
	border-left: none;
	border-right: none;
}
.Bookmarks__BookmarksList__Bookmark__Title button {
	height: 20px;
}
</style>
