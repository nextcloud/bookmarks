<template>
	<div class="Bookmarks__Breadcrumbs">
		<a class="icon-home" @click="onSelectHome" />
		<span class="icon-breadcrumb" />
		<template v-if="$route.name === 'folder'">
			<template v-for="folder in folderPath">
				<a
					href="#"
					:key="'a' + folder.id"
					@click.prevent="onSelectFolder(folder.id)"
					>{{ folder.title }}</a
				>
				<span :key="'b' + folder.id" class="icon-breadcrumb" />
			</template>
		</template>
		<template v-if="$route.name === 'tags'">
			<span class="icon-tag" />
			<Multiselect
				class="Bookmarks__Breadcrumbs__Tags"
				:value="tags"
				:autoLimit="false"
				:limit="7"
				:options="allTags"
				:multiple="true"
				@input="onTagsChange"
			/>
		</template>
		<button
			class="button icon-add Bookmarks__Breadcrumbs__AddFolder"
			@click="onAddFolder"
		></button>
	</div>
</template>
<script>
import { Multiselect } from 'nextcloud-vue';
import { actions, mutations } from '../store';

export default {
	name: 'Breadcrumbs',
	components: { Multiselect },
	props: {},
	data() {
		return {
			url: ''
		};
	},
	computed: {
		allTags() {
			return this.$store.state.tags.map(tag => tag.name);
		},
		tags() {
			const tags = this.$route.params.tags;
			if (!tags) return [];
			return tags.split(',');
		},
		folderPath() {
			const folder = this.$route.params.folder;
			if (!folder) return [];
			return this.$store.getters.getFolder(folder).reverse();
		}
	},
	created() {},
	methods: {
		onSelectHome() {
			this.$router.push({ name: 'home' });
		},
		onTagsChange(tags) {
			this.$router.push({ name: 'tags', params: { tags } });
		},

		onSelectFolder(folder) {
			this.$router.push({ name: 'folder', params: { folder } });
		},

		onAddFolder() {
			this.$store.commit(mutations.DISPLAY_NEW_FOLDER, true);
		}
	}
};
</script>
<style>
.Bookmarks__Breadcrumbs {
	padding: 8px;
	display: flex;
	align-items: center;
	position: fixed;
	width: 100%;
	z-index: 100;
	background: var(--color-main-background-translucent);
}
.Bookmarks__Breadcrumbs + * {
	margin-top: 50px;
}
.Bookmarks__Breadcrumbs > * {
	display: inline-block;
	height: 30px;
	padding: 7px;
}
.Bookmarks__Breadcrumbs > *:not(.icon-breadcrumb) {
	opacity: 0.6;
	min-width: 30px;
}
.Bookmarks__Breadcrumbs > a:hover {
	opacity: 1;
}
.Bookmarks__Breadcrumbs__Tags {
	width: 300px;
}
.Bookmarks__Breadcrumbs__Tags .multiselect__tags {
	border-top: none !important;
	border-left: none !important;
	border-right: none !important;
}
.Bookmarks__Breadcrumbs__AddFolder {
	margin-left: 5px;
}
</style>
