<template>
  <div class="breadcrumbs">
    <div class="breadcrumbs__path">
      <a class="icon-home" @click="onSelectHome" />
      <span class="icon-breadcrumb" />
      <template v-if="$route.name === 'folder'">
        <template v-for="folder in folderPath">
          <a
            :key="'a' + folder.id"
            href="#"
            @click.prevent="onSelectFolder(folder.id)"
          >{{ folder.title }}</a>
          <span :key="'b' + folder.id" class="icon-breadcrumb" />
        </template>
      </template>
      <template v-if="$route.name === 'tags'">
        <span class="icon-tag" />
        <Multiselect
          class="breadcrumbs__tags"
          :value="tags"
          :auto-limit="false"
          :limit="7"
          :options="allTags"
          :multiple="true"
          :placeholder="t('bookmarks', 'Select one or more tags')"
          @input="onTagsChange"
        />
      </template>
      <Actions>
        <ActionButton
          v-if="$route.name === 'folder' || $route.name === 'home'"
          v-tooltip="t('bookmarks', 'New folder')"
          icon="icon-add"
          class="breadcrumbs__AddFolder"
          @click="onAddFolder"
        />
      </Actions>
    </div>
    <div class="breadcrumbs__viewmode">
      <Actions>
        <ActionButton
          :icon="
            viewMode === 'list'
              ? 'icon-toggle-pictures'
              : 'icon-toggle-filelist'
          "
          @click="onToggleViewMode"
        >
          {{ t('bookmarks', viewMode === 'list' ? 'Grid view' : 'List view') }}
        </ActionButton>
      </Actions>
    </div>
  </div>
</template>
<script>
import Multiselect from 'nextcloud-vue/dist/Components/Multiselect';
import Actions from 'nextcloud-vue/dist/Components/Actions';
import ActionButton from 'nextcloud-vue/dist/Components/ActionButton';
import { mutations, actions } from '../store/';

export default {
	name: 'Breadcrumbs',
	components: { Multiselect, Actions, ActionButton },
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
		},
		viewMode() {
			return this.$store.state.viewMode;
		}
	},
	created() {},
	methods: {
		onSelectHome() {
			this.$router.push({ name: 'home' });
		},
		onTagsChange(tags) {
			this.$router.push({ name: 'tags', params: { tags: tags.join(',') } });
		},

		onSelectFolder(folder) {
			this.$router.push({ name: 'folder', params: { folder } });
		},

		onAddFolder() {
			this.$store.commit(
				mutations.DISPLAY_NEW_FOLDER,
				!this.$store.state.displayNewFolder
			);
		},

		onToggleViewMode() {
			this.$store.dispatch(actions.SET_SETTING, {
				key: 'viewMode',
				value: this.$store.state.viewMode === 'grid' ? 'list' : 'grid'
			});
		}
	}
};
</script>
<style>
.breadcrumbs {
	padding: 2px 8px;
	display: flex;
	position: fixed;
	z-index: 100;
	background: var(--color-main-background-translucent);
	right: 0;
	left: 300px;
}
@media only screen and (max-width: 768px) {
	.breadcrumbs {
		padding-left: 52px;
		left: 0;
	}
}

.breadcrumbs + * {
	margin-top: 50px;
}

.breadcrumbs__path {
	display: flex;
	align-items: center;
	flex: 0;
}

.breadcrumbs__path > * {
	display: inline-block;
	height: 30px;
	padding: 7px;
}

.breadcrumbs__path > *:not(.icon-breadcrumb) {
	min-width: 30px;
	opacity: 0.7;
}

.breadcrumbs__path > *:hover {
	opacity: 1;
}

.breadcrumbs__tags {
	width: 300px;
	flex: 1;
}

.breadcrumbs__tags .multiselect__tags {
	border-top: none !important;
	border-left: none !important;
	border-right: none !important;
}

.breadcrumbs__AddFolder {
	margin-left: 5px;
}

.breadcrumbs__viewmode {
	flex: 2;
	display: flex;
	flex-direction: row-reverse;
	padding: 0;
}

.breadcrumbs__viewmode > * {
	min-width: 30px;
}
</style>
