<template>
	<div class="Bookmarks__TreeFolder">
		<div class="Bookmarks__TreeFolder__Title">
			<h4 @click="showChildren = !showChildren">
				<figure class="icon-folder"></figure>
				{{ folder.title }}
			</h4>
			<button @click="$emit('select', folder.id)">
				{{ t('bookmarks', 'Select') }}
			</button>
		</div>
		<div class="Bookmarks__TreeFolder__Children" v-if="showChildren">
			<TreeFolder
				v-for="folder in folder.children"
				:folder="folder"
				@select="$emit('select', $event)"
			/>
		</div>
	</div>
</template>
<script>
import { Modal } from 'nextcloud-vue';
import { actions, mutations } from '../store';
import TreeFolder from './TreeFolder';

export default {
	name: 'TreeFolder',
	components: {
		Modal,
		TreeFolder
	},
	props: {
		folder: {
			type: Object,
			required: true
		}
	},
	data() {
		return { showChildren: false };
	}
};
</script>
<style>
.Bookmarks__TreeFolder__Title {
	display: flex;
	align-items: center;
}
.Bookmarks__TreeFolder__Title > h4 {
	flex: 1;
	display: flex;
	cursor: pointer;
}
.Bookmarks__TreeFolder__Title > h4 > figure {
	margin: 0 5px;
}
.Bookmarks__TreeFolder__Title > button {
	flex: 0;
}
.Bookmarks__TreeFolder__Children {
	padding-left: 20px;
}
</style>
