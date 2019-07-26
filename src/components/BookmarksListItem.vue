<template>
	<div>
		{{ bookmark.title }}
		<Action>
			<ActionButton icon="icon-edit" @click="$emit('edit-bookmark')"
				>Edit</ActionButton
			>
			<ActionButton icon="icon-delete" @click="$emit('delete-bookmark')"
				>Delete</ActionButton
			>
		</Action>
	</div>
</template>
<script>
import { Action, ActionButton } from 'nextcloud-vue';

export default {
	name: 'NavigationList',
	components: {
		Action,
		ActionButton
	},
	props: {
		bookmark: {
			type: Object,
			required: true
		}
	},
	created() {}
};
</script>
