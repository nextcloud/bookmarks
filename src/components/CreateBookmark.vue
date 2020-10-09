<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<Item :selectable="false"
		:renaming="true"
		title=""
		:editable="true"
		:rename-placeholder="t('bookmarks', 'Enter a link')"
		select-label=""
		@rename="submit"
		@rename-cancel="cancel">
		<template #icon>
			<EarthIcon :fill-color="colorMainText" class="icon" />
		</template>
	</Item>
</template>
<script>
import EarthIcon from 'vue-material-design-icons/Earth'
import Item from './Item'
import { actions, mutations } from '../store/'

export default {
	name: 'CreateBookmark',
	components: { Item, EarthIcon },
	computed: {
		creating() {
			return this.$store.state.loading.createBookmark
		},
		isFolderView() {
			return this.$route.name === this.$store.getters.getRoutes().FOLDER
		},
		isTagView() {
			return this.$route.name === this.$store.getters.getRoutes().TAGS
		},
	},
	mounted() {
		this.$refs.input.focus()
	},
	methods: {
		submit(url) {
			this.$store.dispatch(actions.CREATE_BOOKMARK, {
				url,
				...(this.isFolderView && { folders: [this.$route.params.folder] }),
				...(this.isTagView && { tags: this.$route.params.tags.split(',') }),
			})
		},
		cancel() {
			this.$store.commit(
				mutations.DISPLAY_NEW_BOOKMARK,
				false
			)
		},
	},
}
</script>
<style scoped>
.icon {
	flex-grow: 0;
	height: 20px;
	width: 20px;
	background-size: cover;
	margin: 0 15px;
	cursor: pointer;
}

.item--gridview .icon {
	background-size: cover;
	position: absolute;
	top: 20%;
	left: calc(45% - 50px);
	transform: scale(4);
	transform-origin: top left;
}
</style>
