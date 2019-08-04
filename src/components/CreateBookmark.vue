<template>
	<div class="Bookmarks__CreateBookmark">
		<h3 class="Bookmarks__CreateBookmark__Title">
			<span class="icon-add Bookmarks__CreateBookmark__Icon" />
			<input
				type="text"
				:placeholder="t('bookmarks', 'Enter a Link...')"
				:disabled="loading"
				v-model="url"
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
export default {
	name: 'CreateBookmark',
	components: {},
	props: {
		loading: {
			type: Boolean,
			required: true
		}
	},
	data() {
		return {
			url: ''
		};
	},
	created() {},
	methods: {
		submit() {
			this.$emit('create-bookmark', this.url);
		}
	}
};
</script>
<style>
.Bookmarks__CreateBookmark {
	border-bottom: 1px solid var(--color-border);
	padding: 5px;
	display: flex;
	align-items: center;
}
.Bookmarks__CreateBookmark__Icon {
	display: inline-block;
	flex-shrink: 0;
	height: 20px;
	width: 20px;
	background-size: cover;
	margin: 0 10px;
	position: relative;
	top: 8px;
}
.Bookmarks__CreateBookmark__Title {
	display: flex;
	flex-grow: 1;
}
.Bookmarks__CreateBookmark__Title > input {
	width: 100%;
}
.Bookmarks__CreateBookmark button {
	height: 20px;
}
.Bookmarks__CreateBookmark input {
	border-top: none;
	border-left: none;
	border-right: none;
}
</style>
