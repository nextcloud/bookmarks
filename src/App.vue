<template>
	<router-view />
</template>

<script>
import { mutations } from './store';
export default {
	name: 'App',
	computed: {
		error() {
			return this.$store.state.error;
		}
	},
	watch: {
		error(error) {
			if (!error) return;
			OC.Notification.showTemporary(error);
			this.$store.commit(mutations.SET_ERROR, null);
		}
	}
};
</script>
