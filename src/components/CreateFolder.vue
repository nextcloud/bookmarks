<template>
  <div class="create-folder">
    <span class="create-folder__title">
      <figure class="icon-folder create-folder__icon" />
      <input
        ref="input"
        v-model="title"
        type="text"
        :disabled="loading"
        :placeholder="t('bookmarks', 'Enter folder title')"
        @keyup.enter="submit"
      >
    </span>
    <Actions>
      <ActionButton
        :icon="loading ? 'icon-loading' : 'icon-confirm'"
        @click="submit"
      >
        Create
      </ActionButton>
    </Actions>
  </div>
</template>
<script>
import { Actions, ActionButton } from 'nextcloud-vue';
import { actions } from '../store';

export default {
	name: 'CreateFolder',
	components: { Actions, ActionButton },
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
	mounted() {
		this.$refs['input'].focus();
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
