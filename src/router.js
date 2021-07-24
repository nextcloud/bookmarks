/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

import Vue from 'vue'
import Router from 'vue-router'
import { generateUrl } from '@nextcloud/router'

const ViewPrivate = () => import(/* webpackPreload: true */ './components/ViewPrivate')
const ViewPublic = () => import(/* webpackPreload: true */'./components/ViewPublic')
const ViewBookmarklet = () => import(/* webpackPreload: true */'./components/ViewBookmarklet')

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
	ARCHIVED: 'ARCHIVED',
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
			path: '/archived',
			name: privateRoutes.ARCHIVED,
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
