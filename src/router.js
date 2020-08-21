import Vue from 'vue'
import Router from 'vue-router'
import ViewPrivate from './components/ViewPrivate'
import ViewPublic from './components/ViewPublic'
import ViewBookmarklet from './components/ViewBookmarklet'
import { generateUrl } from '@nextcloud/router'

Vue.use(Router)

export const privateRoutes = {
	HOME: 'home',
	RECENT: 'recent',
	SEARCH: 'search',
	FOLDER: 'folder',
	BOOKMARK: 'bookmark',
	TAGS: 'tags',
	UNTAGGED: 'untagged',
	UNAVAILABLE: 'UNAVAILABLE',
	BOOKMARKLET: 'bookmarklet',
}

export const publicRoutes = {
	HOME: 'public.home',
	RECENT: 'public.recent',
	SEARCH: 'public.search',
	FOLDER: 'public.folder',
	TAGS: 'public.tags',
	UNTAGGED: 'public.untagged',
	BOOKMARKLET: 'bookmarklet',
}

export default new Router({
	mode: 'history',
	base: generateUrl('/apps/bookmarks'),
	linkActiveClass: 'active',
	routes: [
		{
			path: '/',
			name: privateRoutes.HOME,
			component: ViewPrivate,
		},
		{
			path: '/recent',
			name: privateRoutes.RECENT,
			component: ViewPrivate,
		},
		{
			path: '/search/:search',
			name: privateRoutes.SEARCH,
			component: ViewPrivate,
		},
		{
			path: '/folders/:folder',
			name: privateRoutes.FOLDER,
			component: ViewPrivate,
		},
		{
			path: '/bookmarks/:bookmark',
			name: privateRoutes.BOOKMARK,
			component: ViewPrivate,
		},
		{
			path: '/tags/:tags?',
			name: privateRoutes.TAGS,
			component: ViewPrivate,
		},
		{
			path: '/untagged',
			name: privateRoutes.UNTAGGED,
			component: ViewPrivate,
		},
		{
			path: '/unavailable',
			name: privateRoutes.UNAVAILABLE,
			component: ViewPrivate,
		},
		{
			path: '/bookmarklet',
			name: privateRoutes.BOOKMARKLET,
			component: ViewBookmarklet,
			props: (route) => ({ url: route.query.url, title: route.query.title }),
		},
		{
			path: '/public/:token',
			name: publicRoutes.HOME,
			component: ViewPublic,
		},
		{
			path: '/public/:token/recent',
			name: publicRoutes.RECENT,
			component: ViewPublic,
		},
		{
			path: '/public/:token/search/:search',
			name: publicRoutes.SEARCH,
			component: ViewPublic,
		},
		{
			path: '/public/:token/folder/:folder',
			name: publicRoutes.FOLDER,
			component: ViewPublic,
		},
		{
			path: '/public/:token/tags/:tags',
			name: publicRoutes.TAGS,
			component: ViewPublic,
		},
		{
			path: '/public/:token/untagged',
			name: publicRoutes.UNTAGGED,
			component: ViewPublic,
		},
	],
})
