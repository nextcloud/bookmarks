<template>
	<div class="Bookmarks__CreateFolder">
		<h3 class="Bookmarks__CreateFolder__Title">
			<span class="icon-folder Bookmarks__CreateFolder__Icon" />
			<input
				type="text"
				:placeholder="t('bookmarks', 'Enter folder title')"
				:disabled="loading"
				v-model="title"
				@keyup.enter="submit"
			/>
		</h3>
		<button type="button" class="button" @click="submit">
			<span :class="loading ? 'icon-loading' : 'icon-checkmark'"></span>
			Create
		</button>
	</div>
</template>
<script>
import { actions, mutations } from '../store';

export default {
	name: 'CreateFolder',
	components: {},
	data() {
		return {
			title: ''
		};
	},
	created() {},
	computed: {
		loading() {
			return this.$store.state.loading.createFolder;
		}
	},
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
.Bookmarks__CreateFolder {
	border-bottom: 1px solid var(--color-border);
	padding: 5px;
	display: flex;
	align-items: center;
}
.Bookmarks__CreateFolder__Icon {
	display: inline-block;
	flex-shrink: 0;
	height: 20px;
	width: 20px;
	background-size: cover;
	margin: 0 10px;
	position: relative;
	top: 8px;
}
.Bookmarks__CreateFolder__Title {
	display: flex;
	flex-grow: 1;
}
.Bookmarks__CreateFolder__Title > input {
	width: 100%;
}
.Bookmarks__CreateFolder button {
	height: 20px;
}
.Bookmarks__CreateFolder input {
	border-top: none;
	border-left: none;
	border-right: none;
}
</style>
