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
					:size="20"
					class="treefolder__icon-hover"
					:fill-color="colorPrimaryElement"
					@click.stop="folder.children.length && showChildren && (childrenShown = true)" />
				<FolderOpenIcon v-else
					:size="20"
					class="treefolder__icon-hover"
					:fill-color="colorPrimaryElement"
					@click.stop="folder.children.length && (childrenShown = false)" />
				{{ folder.title }}
			</h3>
			<NcCounterBubble v-if="typeof bookmarksCount !== 'undefined'">
				{{ bookmarksCount | largeNumbers }}
			</NcCounterBubble>
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
import { NcCounterBubble } from '@nextcloud/vue'
import { FolderIcon, FolderOpenIcon } from './Icons.js'
import { privateRoutes } from '../router.js'
export default {
	name: 'TreeFolder',
	components: { FolderIcon, FolderOpenIcon, NcCounterBubble },
	filters: {
		largeNumbers(num) {
			return num >= 1000 ? (Math.round(num / 100) / 10) + 'K' : num
		},
	},
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
		bookmarksCount() {
			return this.$store.state.countsByFolder[this.folder.id]
		},
	},
	watch: {
		'$route'() {
			if (this.$route.name === privateRoutes.FOLDER
					&& (this.$route.params.folder === this.folder.id
							|| this.folder.children.find(f => f.id === this.$route.params.folder)) && this.folder.children.length
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
	justify-content: space-between;
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
	font-weight: normal;
	font-size: 1em;
	margin: 10px 0;
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

.treefolder__children .treefolder__children .treefolder__children  .treefolder__children .treefolder__children .treefolder__children .treefolder__title {
	padding-left: 150px;
}

.treefolder__children .treefolder__children .treefolder__children  .treefolder__children .treefolder__children .treefolder__children .treefolder__children .treefolder__title {
	padding-left: 175px;
}

.treefolder__children .treefolder__children .treefolder__children  .treefolder__children .treefolder__children .treefolder__children .treefolder__children .treefolder__children .treefolder__title {
	padding-left: 200px;
}

.treefolder__children .treefolder__children .treefolder__children  .treefolder__children .treefolder__children .treefolder__children .treefolder__children .treefolder__children .treefolder__children .treefolder__title {
	padding-left: 225px;
}

.treefolder__children .treefolder__children .treefolder__children  .treefolder__children .treefolder__children .treefolder__children .treefolder__children .treefolder__children .treefolder__children .treefolder__children .treefolder__title {
	padding-left: 250px;
}
</style>
