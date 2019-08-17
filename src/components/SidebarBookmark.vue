<template>
	<AppSidebar
		class="Bookmarks__Sidebar"
		v-if="isActive"
		:title="bookmark.title"
		:subtitle="bookmark.url"
		:background="background"
		@close="onClose"
	>
		<AppSidebarTab :name="t('bookmarks', 'Details')" icon="icon-info">
			<div>
				<h3>
					<span class="icon-calendar-dark" />
					{{ t('bookmarks', 'Creation date') }}
				</h3>
				{{ addedDate }}
			</div>
			<div>
				<h3><span class="icon-tag" /> {{ t('bookmarks', 'Tags') }}</h3>
				<Multiselect
					class="Bookmarks__Sidebar__Tags"
					:value="tags"
					:autoLimit="false"
					:limit="7"
					:options="allTags"
					:multiple="true"
					:taggable="true"
					@input="onTagsChange"
					@tag="onAddTag"
				>
					<template #option="{ option }">
						<span>{{ option | tagLabel }}</span>
					</template>
				</Multiselect>
			</div>
			<div>
				<h3><span class="icon-edit" /> {{ t('bookmarks', 'Notes') }}</h3>
				<div
					class="Bookmarks__Sidebar__Notes"
					contenteditable
					@input="onNotesChange"
				>
					{{ description }}
				</div>
			</div>
		</AppSidebarTab>
		<AppSidebarTab :name="t('bookmarks', 'Sharing')" icon="icon-sharing" />
	</AppSidebar>
</template>
<script>
import { AppSidebar, AppSidebarTab, Multiselect } from 'nextcloud-vue';
import humanizeDuration from 'humanize-duration';
import { actions, mutations } from '../store';

const MAX_RELATIVE_DATE = 1000 * 60 * 60 * 24 * 7; // one week

export default {
	name: 'SidebarBookmark',
	components: { AppSidebar, AppSidebarTab, Multiselect },
	created() {},
	data() {
		return {
			description: ''
		};
	},
	computed: {
		isActive() {
			if (!this.$store.state.sidebar) return false;
			return this.$store.state.sidebar.type === 'bookmark';
		},
		bookmark() {
			if (!this.isActive) return;
			return this.$store.getters.getBookmark(this.$store.state.sidebar.id);
		},
		background() {
			return OC.generateUrl(
				`/apps/bookmarks/bookmark/${this.bookmark.id}/image`
			);
		},
		addedDate() {
			const date = new Date(Number(this.bookmark.added) * 1000);
			const age = Date.now() - date;
			if (age < MAX_RELATIVE_DATE) {
				const duration = humanizeDuration(age, {
					language: OC.getLanguage(),
					units: ['d', 'h', 'm', 's'],
					largest: 1
				});
				return duration + ' ' + this.t('bookmarks', 'ago');
			} else {
				return date.toLocaleDateString();
			}
		},
		tags() {
			return this.bookmark.tags;
		},
		allTags() {
			return this.$store.state.tags.map(tag => tag.name);
		}
	},
	watch: {
		bookmark(newBookmark) {
			this.description = newBookmark.description;
		}
	},
	methods: {
		onClose() {
			this.$store.commit(mutations.SET_SIDEBAR, null);
		},
		onNotesChange(e) {
			this.bookmark.description = e.target.textContent;
			this.scheduleSave();
		},
		onTagsChange(tags) {
			this.bookmark.tags = tags;
			this.scheduleSave();
		},
		onAddTag(tag) {
			this.bookmark.tags.push(tag);
			this.scheduleSave();
		},
		scheduleSave() {
			if (this.changeTimeout) clearTimeout(this.changeTimeout);
			this.changeTimeout = setTimeout(async () => {
				await this.$store.dispatch(actions.SAVE_BOOKMARK, this.bookmark.id);
				await this.$store.dispatch(actions.LOAD_TAGS);
			}, 1000);
		}
	},
	filters: {
		tagLabel(tag) {
			return tag.isTag ? tag.label : tag;
		}
	}
};
</script>
<style>
.Bookmarks__Sidebar span[class^='icon-'] {
	display: inline-block;
	position: relative;
	top: 3px;
	opacity: 0.5;
}
.Bookmarks__Sidebar__Tags {
	width: 100%;
}
.Bookmarks__Sidebar__Notes {
	min-height: 400px !important;
	width: auto !important;
}
</style>
