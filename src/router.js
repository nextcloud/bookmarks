import Vue from 'vue'
import Router from 'vue-router'
import ViewPrivate from './components/ViewPrivate'
import ViewBookmarklet from './components/ViewBookmarklet'
import { generateUrl } from 'nextcloud-router'

Vue.use(Router)

export default new Router({
	mode: 'history',
	base: generateUrl('/apps/bookmarks'),
	linkActiveClass: 'active',
	routes: [
		{
			path: '/',
			name: 'home',
			component: ViewPrivate,
		},
		{
			path: '/recent',
			name: 'recent',
			component: ViewPrivate,
		},
		{
			path: '/search/:search',
			name: 'search',
			component: ViewPrivate,
		},
		{
			path: '/folder/:folder',
			name: 'folder',
			component: ViewPrivate,
		},
		{
			path: '/tags/:tags',
			name: 'tags',
			component: ViewPrivate,
		},
		{
			path: '/untagged',
			name: 'untagged',
			component: ViewPrivate,
		},
		{
			path: '/bookmarklet',
			name: 'bookmarklet',
			component: ViewBookmarklet,
			props: (route) => ({ url: route.query.url, title: route.query.title }),
		},
	],
})
