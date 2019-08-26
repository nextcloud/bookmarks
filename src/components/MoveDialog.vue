<template>
	<Modal v-if="showModal" :title="title" @close="onClose">
		<div class="move-dialog">
			<TreeFolder
				:folder="{
					title: t('bookmarks', 'Root folder'),
					id: '-1',
					children: allFolders
				}"
				:show-children="true"
				@select="onSelect"
			/>
		</div>
	</Modal>
</template>
<script>
import { Modal } from 'nextcloud-vue';
import { actions, mutations } from '../store';
import TreeFolder from './TreeFolder';

export default {
	name: 'MoveDialog',
	components: {
		Modal,
		TreeFolder
	},
	computed: {
		showModal() {
			return this.$store.state.displayMoveDialog;
		},
		selection() {
			return this.$store.state.selection;
		},
		allFolders() {
			return this.filterFolders(this.$store.state.folders);
		},
		title() {
			if (this.selection.folders.length) {
				if (this.selection.bookmarks.length) {
					return n(
						'bookmarks',
						'Moving %n folder and some bookmarks',
						'Moving %n folders and some bookmarks',
						this.selection.folders.length
					);
				} else {
					return n(
						'bookmarks',
						'Moving %n folder',
						'Moving %n folders',
						this.selection.folders.length
					);
				}
			} else {
				return n(
					'bookmarks',
					'Moving %n bookmark',
					'Moving %n bookmarks',
					this.selection.bookmarks.length
				);
			}
		}
	},
	created() {},
	methods: {
		async onSelect(folderId) {
			await this.$store.dispatch(actions.MOVE_SELECTION, folderId);
			this.$store.commit(mutations.RESET_SELECTION);
			this.$store.commit(mutations.DISPLAY_MOVE_DIALOG, false);
			this.$store.dispatch(actions.RELOAD_VIEW);
		},
		onClose() {
			this.$store.commit(mutations.DISPLAY_MOVE_DIALOG, false);
		},
		filterFolders(children) {
			return children
				.filter(
					child =>
						!this.selection.folders.some(folder => folder.id === child.id)
				)
				.map(child => ({
					...child,
					children: this.filterFolders(child.children)
				}));
		}
	}
};
</script>
<style>
.move-dialog {
	min-width: 300px;
	height: 300px;
	overflow-y: scroll;
	padding: 10px;
}
</style>
