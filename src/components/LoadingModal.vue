<template>
	<NcModal v-if="showNcModal" :can-close="false">
		<div class="loading icon-loading">
			<h3>{{ title }}</h3>
		</div>
	</NcModal>
</template>
<script>
import { NcModal } from '@nextcloud/vue'

export default {
	name: 'LoadingModal',
	components: {
		NcModal,
	},
	data() {
		return {
			states: {
				deleteBookmarks: this.t('bookmarks', 'Deleting bookmarks'),
				deleteSelection: this.t('bookmarks', 'Deleting selection'),
				importBookmarks: this.t('bookmarks', 'Importing bookmarks'),
				moveSelection: this.t('bookmkarks', 'Moving selection'),
				copySelection: this.t('bookmkarks', 'Adding selection to folders'),
			},
			showNcModal: false,
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
					this.showNcModal = true
				}, 500)
			} else if (!this.state && previous) {
				clearTimeout(this.showTimeout)
				this.showNcModal = false
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
