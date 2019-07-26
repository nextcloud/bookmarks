import Vue from 'vue';
import Router from 'vue-router';
import ViewPrivate from './components/ViewPrivate';

Vue.use(Router);

export default new Router({
	mode: 'history',
	base: OC.generateUrl('/apps/bookmarks'),
	linkActiveClass: 'active',
	routes: [
		{
			path: '/',
			name: 'home',
			component: ViewPrivate
		},
		{
			path: '/search/:search',
			name: 'home',
			component: ViewPrivate,
			props: true
		},
		{
			path: '/folder/:folderId',
			name: 'folder',
			component: ViewPrivate
		},
		{
			path: '/tags/:tags',
			name: 'tags',
			components: ViewPrivate,
			props: true
		}
	]
});
