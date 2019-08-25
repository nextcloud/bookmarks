<template>
	<div
		:class="{ Bookmarks__BookmarksList__Bookmark: true, active: isOpen }"
		:style="{
			background: `linear-gradient(0deg,	var(--color-main-background) 25%, rgba(0, 212, 255, 0) 50%), url('${imageUrl}')`
		}"
	>
		<template v-if="!renaming">
			<a
				:href="url"
				target="_blank"
				class="Bookmarks__BookmarksList__Bookmark__Title"
			>
				<h3 :title="bookmark.title">
					<figure
						class="Bookmarks__BookmarksList__Bookmark__Icon"
						:style="{ backgroundImage: 'url(' + iconUrl + ')' }"
					/>
					{{ bookmark.title }}
				</h3>
			</a>
			<TagLine :tags="bookmark.tags" />
			<span
				v-if="bookmark.description"
				v-tooltip="bookmark.description"
				class="icon-file Bookmarks__BookmarksList__Bookmark__Description"
			/>
			<Actions class="Bookmarks__BookmarksList__Bookmark__Actions">
				<ActionButton icon="icon-info" @click="onDetails">{{
					t('bookmarks', 'Details')
				}}</ActionButton>
				<ActionButton icon="icon-rename" @click="onRename">{{
					t('bookmarks', 'Rename')
				}}</ActionButton>
				<ActionButton icon="icon-category-files" @click="onMove">{{
					t('bookmarks', 'Move')
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
import { actions, mutations } from '../store';
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
		imageUrl() {
			return OC.generateUrl(
				'/apps/bookmarks/bookmark/' + this.bookmark.id + '/image'
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
	background-position: center !important;
	background-size: cover !important;
	position: relative;
}
*:not(.Bookmarks__BookmarksList--GridView)
	> .Bookmarks__BookmarksList__Bookmark {
	background: var(--color-main-background) !important;
}

*:not(.Bookmarks__BookmarksList--GridView)
	> .Bookmarks__BookmarksList__Bookmark.active,
*:not(.Bookmarks__BookmarksList--GridView)
	> .Bookmarks__BookmarksList__Bookmark:hover {
	background: var(--color-background-dark) !important;
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
	width: 20px;
	height: 47px;
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
.Bookmarks__BookmarksList--GridView .Bookmarks__TagLine {
	position: absolute;
	top: 10px;
}
.Bookmarks__BookmarksList--GridView .Bookmarks__BookmarksList__Bookmark__Icon {
	margin: 0 5px 0 10px;
}
</style>
