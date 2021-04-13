<template>
	<Modal v-if="showModal" :can-close="false">
		<div class="loading icon-loading">
			<h3>{{ title }}</h3>
		</div>
	</Modal>
</template>
<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'

export default {
	name: 'LoadingModal',
	components: {
		Modal,
	},
	data() {
		return {
			states: {
				deleteBookmarks: this.t('bookmarks', 'Deleting bookmarks'),
				deleteSelection: this.t('bookmarks', 'Deleting selection'),
				importBookmarks: this.t('bookmarks', 'Importing bookmarks'),
				moveSelection: this.t('bookmkarks', 'Moving selection'),
			},
			showModal: false,
			showTimeout: null,
		}
	},
	computed: {
		state() {
			return Object.keys(this.states).find(state => this.$store.state.loading[state])
		},
		title() {
			const state = this.state
			if (state) {
				return this.states[state]
			} else {
				return ''
			}
		},
	},
	watch: {
		state(newState, previous) {
			if (this.state && !previous) {
				this.showTimeout = setTimeout(() => {
					this.showModal = true
				}, 500)
			} else if (!this.state && previous) {
				clearTimeout(this.showTimeout)
				this.showModal = false
			}
		},
	},
}
</script>
<style scoped>
.loading {
	min-width: 300px;
	height: 200px;
	overflow-y: scroll;
	padding: 10px;
	text-align: center;
}
</style>
