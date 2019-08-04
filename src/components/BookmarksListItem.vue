<template>
	<div class="Bookmarks__BookmarksList_Item">
		<h3 class="Bookmarks__BookmarksList_Item__Title">
			<a :href="url" target="_blank">
				<figure
					class="Bookmarks__BookmarksList_Item__Icon"
					:style="{ backgroundImage: 'url(' + iconUrl + ')' }"
				/>
				{{ bookmark.title }}</a
			>
		</h3>
		<span
			v-if="bookmark.description"
			v-tooltip="bookmark.description"
			class="icon-file Bookmarks__BookmarksList_Item__Description"
		/>
		<TagLine :tags="bookmark.tags" />
		<Actions class="Bookmarks__BookmarksList_Item__Actions">
			<ActionButton icon="icon-edit" @click="onEditBookmark">Edit</ActionButton>
			<ActionButton icon="icon-delete" @click="onDeleteBookmark"
				>Delete</ActionButton
			>
		</Actions>
	</div>
</template>
<script>
import { Actions, ActionButton } from 'nextcloud-vue';
import TagLine from './TagLine';

export default {
	name: 'BookmarksListItem',
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
	created() {},
	computed: {
		iconUrl() {
			return OC.generateUrl(
				'/apps/bookmarks/bookmark/' + this.bookmark.id + '/favicon'
			);
		},
		url() {
			return this.bookmark.url;
		}
	},
	methods: {
		onDeleteBookmark() {},
		onEditBookmark() {}
	}
};
</script>
<style>
.Bookmarks__BookmarksList_Item {
	border-bottom: 1px solid var(--color-border);
	display: flex;
	align-items: center;
}
.Bookmarks__BookmarksList_Item__Icon {
	display: inline-block;
	flex-shrink: 0;
	height: 20px;
	width: 20px;
	background-size: cover;
	margin: 0 10px;
	position: relative;
	top: 3px;
}
.Bookmarks__BookmarksList_Item__Title {
	display: flex;
	flex-grow: 1;
}
.Bookmarks__BookmarksList_Item__Title,
.Bookmarks__BookmarksList_Item__Title > a {
	text-overflow: ellipsis;
	overflow: hidden;
	white-space: nowrap;
}
.Bookmarks__BookmarksList_Item__Description {
	display: inline-block;
	flex-shrink: 0;
	width: 16px;
	height: 16px;
	margin: 0 10px;
}
.Bookmarks__BookmarksList_Item__Actions {
	flex-shrink: 0;
}
</style>
