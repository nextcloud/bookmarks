<!--
  - Copyright (c) 2022. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->
<script>
const GRID_ITEM_HEIGHT = 198 + 2 + 10
const GRID_ITEM_WIDTH = 248 + 2 + 10
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
			itemHeight: 0,
			itemWidth: 0,
			startIndex: 0,
			visibleItems: 80,
			scrollTop: 0,
			scrollHeight: 500,
			initialLoadingSkeleton: false,
			initialLoadingTimeout: null,
			timeout: null,
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
		fetching() {
			return this.$store.state.loading.bookmarks
		},
		sidebar() {
			return this.$store.state.sidebar
		},
	},
	watch: {
		newBookmark() {
			this.$el.scrollTop = 0
		},
		newFolder() {
			this.$el.scrollTop = 0
		},
		viewMode() {
			this.onViewModeChange()
		},
		itemHeight() {
			this.recalculateVisibleItems()
		},
		itemWidth() {
			this.recalculateVisibleItems()
		},
		sidebar() {
			this.$nextTick(() => {
				this.onScroll()
			})
		},
	},
	mounted() {
		this.$nextTick(() => {
			this.onViewModeChange()
			this.onScroll()
		})
		window.addEventListener('resize', this.onScroll)
	},
	destroyed() {
		window.removeEventListener('resize', this.onScroll)
	},
	methods: {
		onViewModeChange() {
			this.viewport.width = this.$el.clientWidth
			this.viewport.height = this.$el.clientHeight
			if (this.viewMode === 'grid') {
				this.itemHeight = GRID_ITEM_HEIGHT
				this.itemWidth = GRID_ITEM_WIDTH
			} else {
				this.itemHeight = LIST_ITEM_HEIGHT
				this.itemWidth = this.viewport.width
			}
		},
		recalculateVisibleItems() {
			this.visibleItems
				= (Math.ceil(this.viewport.height / this.itemHeight) + 2)
				* Math.floor(this.viewport.width / this.itemWidth)
		},
		onScroll() {
			this.viewport.width = this.$el.clientWidth
			this.viewport.height = this.$el.clientHeight
			const scrollTop = this.$el.scrollTop
			this.startIndex
				= Math.floor(scrollTop / this.itemHeight)
				* Math.floor(this.viewport.width / this.itemWidth)
			const childComponents = this.$slots.default.filter(
				(child) => !!child.componentOptions,
			)
			if (
				scrollTop + this.viewport.height
				>= Math.ceil(childComponents.length / this.itemWidth)
					* this.itemHeight
					- 100
			) {
				if (!this.fetching) {
					this.$emit('load-more')
				}
			}
		},
	},
	render(h) {
		if (!this.$slots.default || !this.$el) {
			return h('div', {
				class: 'virtual-scroll',
			})
		}

		const childComponents = this.$slots.default.filter(
			(child) => !!child.componentOptions,
		)

		const endIndex = Math.min(
			this.startIndex + this.visibleItems,
			childComponents.length,
		)
		const visibleData = Array.from(
			{ length: endIndex - this.startIndex },
			(_, i) => this.startIndex + i,
		)
		const itemsPerRow = Math.floor(this.viewport.width / this.itemWidth)
		return h(
			'div',
			{
				class: 'virtual-scroll',
				on: { scroll: () => this.onScroll() },
			},
			[
				h(
					'div',
					{
						class: 'container-window',
						style: {
							height: `${
								Math.ceil(
									childComponents.length / itemsPerRow,
								) * this.itemHeight
							}px`,
							position: 'relative',
						},
					},
					visibleData.map((index) => {
						const x = Math.floor(index / itemsPerRow)
						const y = index - x * itemsPerRow
						return h(
							'div',
							{
								key: index,
								style: {
									position: 'absolute',
									top: `${x * this.itemHeight}px`,
									left: `${y * this.itemWidth + (itemsPerRow === 1 ? 0 : 10)}px`,
									height: `${this.itemHeight}px`,
									width: `${this.itemWidth}px`,
								},
							},
							[childComponents[index]],
						)
					}),
				),
			],
		)
	},
}
</script>

<style scoped>
.virtual-scroll {
	height: calc(100vh - 50px - 50px - 10px);
	position: relative;
	overflow-y: scroll;
}

.bookmarkslist--with-description .virtual-scroll {
	height: calc(100vh - 50px - 50px - 130px);
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
