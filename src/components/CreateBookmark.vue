<template>
	<div class="create-bookmark">
		<span class="create-bookmark__title">
			<figure class="icon-link create-bookmark__icon" />
			<input
				ref="input"
				v-model="url"
				type="text"
				:disabled="creating"
				:placeholder="t('bookmarks', 'Enter a link')"
				@keyup.enter="submit">
		</span>
		<Actions>
			<ActionButton
				:icon="creating ? 'icon-loading' : 'icon-confirm'"
				@click="submit">
				{{ t('bookmarks', 'Create') }}
			</ActionButton>
		</Actions>
		<Actions>
			<ActionButton
				icon="icon-close"
				@click="cancel">
				{{ t('bookmarks', 'Cancel') }}
			</ActionButton>
		</Actions>
	</div>
</template>
<script>
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import { actions, mutations } from '../store/'
export default {
	name: 'CreateBookmark',
	components: { Actions, ActionButton },
	data() {
		return {
			url: '',
		}
	},
	computed: {
		creating() {
			return this.$store.state.loading.createBookmark
		},
	},
	mounted() {
		this.$refs.input.focus()
	},
	methods: {
		submit() {
			this.$store.dispatch(actions.CREATE_BOOKMARK, {
				url: this.url,
				...(this.$route.name === this.$store.getters.getRoutes().FOLDER && { folders: [this.$route.params.folder] }),
			})
		},
		cancel() {
			this.$store.commit(
				mutations.DISPLAY_NEW_BOOKMARK,
				false
			)
		},
	},
}
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
	top: 10px;
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
