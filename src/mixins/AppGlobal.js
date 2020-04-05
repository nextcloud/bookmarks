export default {
	methods: {
		t,
		n,
	},
	data: () => ({
		OC,
		OCA,
	}),
	computed: {
		routes() {
			return this.$store.getters.getRoutes()
		},
		isPublic() {
			return this.$store.state.public
		},
	},
}
