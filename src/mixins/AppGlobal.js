export default {
	methods: {
		t,
		n,
	},
	computed: {
		routes() {
			return this.$store.getters.getRoutes()
		},
		isPublic() {
			return this.$store.state.public
		},
		colorPrimaryElement() {
			return getComputedStyle(document.documentElement).getPropertyValue('--color-primary-element')
		},
		colorPrimaryText() {
			return getComputedStyle(document.documentElement).getPropertyValue('--color-primary-text')
		},
		colorMainText() {
			return getComputedStyle(document.documentElement).getPropertyValue('--color-main-text')
		},
		colorMainBackground() {
			return getComputedStyle(document.documentElement).getPropertyValue('--color-main-background')
		},
	},
}
