<template>
	<div>
		<input v-show="show"
			ref="searchInput"
			type="text"
			:placeholder="t('bookmarks', 'Search')"
			@keyup="onSearch($event.target.value)">
		<Actions>
			<ActionButton icon="icon-search" @click="toggleSearchInput">
				{{ t('bookmarks', 'Search') }}
			</ActionButton>
		</Actions>
	</div>
</template>

<script>
import { privateRoutes, publicRoutes } from '../router'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'

export default {
	name: 'Search',
	components: { ActionButton, Actions },
	data() {
	  return { show: false }
	},
	computed: {
	  routes() {
	    return this.$store.state.public ? privateRoutes : publicRoutes
		},
	},
	methods: {
	  toggleSearchInput() {
	    this.show = !this.show
			setTimeout(() => {
				this.$refs.searchInput.focus()
			}, 100)
		},
		onSearch(search) {
			this.$router.push({ name: this.routes.SEARCH, params: { search } })
		},
		onResetSearch() {
			this.$router.push({ name: this.routes.HOME })
		},
	},
}
</script>

<style scoped>
input[type=text] {
  position: relative;
  top: 5px;
  margin: 0;
  left: 42px;
  padding-right: 40px;
  border-left: none;
  border-right: none;
  border-top: none;
}
</style>
