<template>
	<div class="Bookmarks__TagLine">
		<div v-for="tag in tags" :key="tag" class="Bookmarks__TagLine_Tag">
			{{ tag }}
		</div>
	</div>
</template>
<script>
export default {
	name: 'TagLine',
	components: {},
	props: {
		tags: {
			type: Array,
			required: true
		}
	},
	data() {
		return {};
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
.Bookmarks__TagLine {
	font-size: 12px;
	height: 24px;
	line-height: 1;
	overflow: hidden;
	display: inline-block;
	margin: 0 15px;
}
.Bookmarks__TagLine_Tag {
	display: inline-block;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-pill);
	padding: 5px 10px;
	margin: 0 3px;
}
.Bookmarks__TagLine_Tag:hover {
	background-color: var(--color-background-dark);
	cursor: pointer;
}
</style>
