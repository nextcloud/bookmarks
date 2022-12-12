<!--
  - Copyright (c) 2022. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div :class="{treefolder:true, active}">
		<div class="treefolder__title" @click="$emit('select', folder.id)">
			<h3>
				<FolderIcon v-if="!childrenShown"
					class="treefolder__icon-hover"
					:fill-color="colorPrimaryElement"
					@click.stop="folder.children.length && showChildren && (childrenShown = true)" />
				<FolderOpenIcon v-else
					class="treefolder__icon-hover"
					:fill-color="colorPrimaryElement"
					@click.stop="folder.children.length && (childrenShown = false)" />
				{{ folder.title }}
			</h3>
		</div>
		<div v-if="showChildren && childrenShown" class="treefolder__children">
			<TreeFolder v-for="f in folder.children"
				:key="f.id"
				:folder="f"
				@select="$emit('select', $event)" />
		</div>
	</div>
</template>

<script>
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import FolderOpenIcon from 'vue-material-design-icons/FolderOpen.vue'
import { privateRoutes } from '../router.js'
export default {
	name: 'TreeFolder',
	components: { FolderIcon, FolderOpenIcon },
	props: {
		folder: {
			type: Object,
			required: true,
		},
		showChildren: {
			type: Boolean,
			default: true,
		},
	},
	data() {
		return {
			childrenShown: false,
		}
	},
	computed: {
		active() {
			return this.$route.params.folder === this.folder.id
		},
	},
	watch: {
		'$route'() {
			if (this.$route.name === privateRoutes.FOLDER
					&& (this.$route.params.folder === this.folder.id
							|| this.folder.children.find(f => f.id === this.$route.params.folder))
			) {
				this.childrenShown = true
			}
		},
	},
}
</script>

<style>

.treefolder__title .material-design-icon {
	position: relative;
	top: 1px;
	margin: 0 15px;
}

.treefolder__icon-hover:hover {
	opacity: 0.8;
}

.treefolder__title {
	display: flex;
	align-items: center;
	padding: 0 10px;
	margin: 0 -10px;
	cursor: pointer;
}

.treefolder__title * {
	cursor: pointer;
}

.treefolder.active > .treefolder__title,
.treefolder__title:hover,
.treefolder__title:focus {
	background: var(--color-background-dark);
}

.treefolder__title > h3 {
	flex: 1;
	display: flex;
}

.treefolder__children .treefolder__title {
	padding-left: 25px;
}

.treefolder__children .treefolder__children .treefolder__title {
	padding-left: 50px;
}

.treefolder__children  .treefolder__children .treefolder__children .treefolder__title {
	padding-left: 75px;
}

.treefolder__children .treefolder__children  .treefolder__children .treefolder__children .treefolder__title {
	padding-left: 100px;
}

.treefolder__children .treefolder__children .treefolder__children  .treefolder__children .treefolder__children .treefolder__title {
	padding-left: 125px;
}
</style>
