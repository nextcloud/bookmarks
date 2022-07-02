<!--
  - Copyright (c) 2022. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->
<script>
import ItemSkeleton from './ItemSkeleton'

const GRID_ITEM_HEIGHT = 200 + 10
const GRID_ITEM_WIDTH = 250 + 10
const LIST_ITEM_HEIGHT = 45 + 1

export default {
	name: 'VirtualScroll',
	props: {
		reachedEnd: {
			type: Boolean,
			required: true,
		},
	},
	data() {
		return {
			viewport: { width: 0, height: 0 },
			scrollTop: 0,
			scrollHeight: 500,
		}
	},
	computed: {
		viewMode() {
			return this.$store.state.viewMode
		},
		newBookmark() {
			return this.$store.state.displayNewBookmark
		},
		newFolder() {
			return this.$store.state.displayNewFolder
		},
	},
	watch: {
		newBookmark() {
			this.$el.scrollTop = 0
		},
		newFolder() {
			this.$el.scrollTop = 0
		},
	},
	mounted() {
		this.onScroll()
	},
	methods: {
		onScroll() {
			this.scrollTop = this.$el.scrollTop
			this.scrollHeight = this.$el.scrollHeight
		},
	},
	render(h) {
		let children = []
		let itemsPerRow = 1
		let renderedItems = 40
		let upperPaddingItems = 0
		let lowerPaddingItems = 0
		let itemHeight = 1
		if (this.$slots.default) {
			const viewport = this.$el.getBoundingClientRect()
			itemHeight = this.viewMode === 'grid' ? GRID_ITEM_HEIGHT : LIST_ITEM_HEIGHT
			itemsPerRow = this.viewMode === 'grid' ? Math.floor(viewport.width / GRID_ITEM_WIDTH) : 1
			renderedItems = itemsPerRow * Math.floor((viewport.height + 2 * 500) / itemHeight)
			upperPaddingItems = itemsPerRow * Math.floor(Math.max(this.scrollTop - 500, 0) / itemHeight)
			lowerPaddingItems = Math.max(this.$slots.default.length - renderedItems - upperPaddingItems, 0)
			children = this.$slots.default.slice(upperPaddingItems, upperPaddingItems + renderedItems)
			renderedItems = children.length

			setImmediate(() => { this.$el.scrollTop = this.scrollTop })
		}

		if (!this.reachedEnd && upperPaddingItems + renderedItems > (this.$slots.default ? this.$slots.default.length : 0)) {
			this.$emit('load-more')
			children = [...children, ...Array(upperPaddingItems + renderedItems - (this.$slots.default ? this.$slots.default.length : 0)).fill(0).map(() =>
				h(ItemSkeleton)
			)]
		}

		return h('div', {
			class: 'virtual-scroll',
			on: { scroll: () => this.onScroll() },
		},
		[
			h('div', { class: 'upper-padding', style: { height: Math.max((upperPaddingItems / itemsPerRow) * itemHeight, 0) + 'px' } }),
			h('div', { class: 'container-window', style: { height: Math.max((renderedItems / itemsPerRow) * itemHeight, 0) + 'px' } }, children),
			h('div', { class: 'lower-padding', style: { height: Math.max((lowerPaddingItems / itemsPerRow) * itemHeight, 0) + 'px' } }),
		])
	},
}
</script>

<style scoped>
.virtual-scroll {
	height: calc(100vh - 50px - 50px - 10px);
	position: relative;
	overflow-y: scroll;
}

.bookmarkslist--gridview .container-window {
	display: flex;
	flex-direction: row;
	flex-wrap: wrap;
	align-content: start;
	gap: 10px;
	padding: 0 10px;
	padding-top: 10px;
}
</style>
