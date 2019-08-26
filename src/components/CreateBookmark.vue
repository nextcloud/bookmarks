<template>
  <div class="create-bookmark">
    <h3 class="create-bookmark__title">
      <span class="icon-add create-bookmark__icon" />
      <input
        v-model="url"
        type="text"
        :placeholder="t('bookmarks', 'Enter a Link...')"
        :disabled="creating"
        @keyup.enter="submit"
      >
    </h3>
    <button type="button" class="button" @click="submit">
      <span :class="creating ? 'icon-loading' : 'icon-checkmark'" />
      Create
    </button>
  </div>
</template>
<script>
import { actions } from '../store';
export default {
	name: 'CreateBookmark',
	components: {},
	data() {
		return {
			url: ''
		};
	},
	computed: {
		creating() {
			return this.$store.state.loading.createBookmark;
		}
	},
	created() {},
	methods: {
		submit() {
			this.$store.dispatch(actions.CREATE_BOOKMARK, this.url);
		}
	}
};
</script>
<style>
.create-bookmark {
	border-bottom: 1px solid var(--color-border);
	padding: 5px;
	display: flex;
	align-items: center;
}
.create-bookmark__icon {
	display: inline-block;
	flex-shrink: 0;
	height: 20px;
	width: 20px;
	background-size: cover;
	margin: 0 10px;
	position: relative;
	top: 8px;
}
.create-bookmark__title {
	display: flex;
	flex-grow: 1;
}
.create-bookmark__title > input {
	width: 100%;
}
.create-bookmark button {
	height: 20px;
}
.create-bookmark input {
	border-top: none;
	border-left: none;
	border-right: none;
}
</style>
