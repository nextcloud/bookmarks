import Vue from 'vue';
import Router from 'vue-router';
import ViewPrivate from './components/ViewPrivate';

Vue.use(Router);

export default new Router({
	mode: 'hash',
	base: OC.generateUrl('/apps/bookmarks'),
	linkActiveClass: 'active',
	routes: [
		{
			path: '/',
			name: 'home',
			component: ViewPrivate
		},
		{
			path: '/recent',
			name: 'recent',
			component: ViewPrivate
		},
		{
			path: '/search/:search',
			name: 'search',
			component: ViewPrivate
		},
		{
			path: '/folder/:folder',
			name: 'folder',
			component: ViewPrivate
		},
		{
			path: '/tags/:tags',
			name: 'tags',
			component: ViewPrivate
		},
		{
			path: '/untagged',
			name: 'untagged',
			component: ViewPrivate
		}
	]
});
