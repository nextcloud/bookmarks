<template>
	<div class="tagline">
		<div v-for="tag in tags" :key="tag" class="tagline__tag">
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
			required: true,
		},
	},
	data() {
		return {}
	},
	created() {},
	methods: {
	},
}
</script>
<style>
.tagline {
	font-size: 12px;
	height: 24px;
	line-height: 1;
	overflow: hidden;
	display: inline-block;
	margin: 0 15px;
}

.tagline__tag {
	display: inline-block;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-pill);
	padding: 5px 10px;
	margin-right: 3px;
	background-color: var(--color-main-background);
}

.tagline__tag:hover {
	background-color: var(--color-background-dark);
}
</style>
