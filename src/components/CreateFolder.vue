<template>
  <div class="create-folder">
    <h3 class="create-folder__title">
      <span class="icon-folder create-folder__icon" />
      <input
        v-model="title"
        type="text"
        :placeholder="t('bookmarks', 'Enter folder title')"
        :disabled="loading"
        @keyup.enter="submit"
      >
    </h3>
    <button type="button" class="button" @click="submit">
      <span :class="loading ? 'icon-loading' : 'icon-checkmark'" />
      Create
    </button>
  </div>
</template>
<script>
import { actions } from '../store';

export default {
	name: 'CreateFolder',
	components: {},
	data() {
		return {
			title: ''
		};
	},
	computed: {
		loading() {
			return this.$store.state.loading.createFolder;
		}
	},
	created() {},
	methods: {
		submit() {
			const parentFolder = this.$route.params.folder;
			this.$store.dispatch(actions.CREATE_FOLDER, {
				parentFolder,
				title: this.title
			});
		}
	}
};
</script>
<style>
.create-folder {
	border-bottom: 1px solid var(--color-border);
	padding: 5px;
	display: flex;
	align-items: center;
}
.create-folder__icon {
	display: inline-block;
	flex-shrink: 0;
	height: 20px;
	width: 20px;
	background-size: cover;
	margin: 0 10px;
	position: relative;
	top: 8px;
}
.create-folder__title {
	display: flex;
	flex-grow: 1;
}
.create-folder__title > input {
	width: 100%;
}
.create-folder button {
	height: 20px;
}
.create-folder input {
	border-top: none;
	border-left: none;
	border-right: none;
}
</style>
