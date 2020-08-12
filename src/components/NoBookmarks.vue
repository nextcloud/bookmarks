<template>
	<div
		class="bookmarkslist__empty">
		<h2>{{ t('bookmarks', 'No bookmarks here') }}</h2>
		<p>{{ t('bookmarks', 'Try changing your query or add some using the button on the left.') }}</p>
	</div>
</template>

<script>
export default {
	name: 'NoBookmarks',
}
</script>

<style scoped>

</style>
