import _ from 'underscore';
import Backbone from 'backbone';
import Bookmark from './Bookmark';

const BATCH_SIZE = 34; // roughly two screens

export default Backbone.Collection.extend({
	model: Bookmark,
	url: 'bookmark',
	parse: function(json) {
		return json.data;
	},
	//override Backbone#comparator
	comparator: function(m) {
		if (this.sortby == 'title') {
			return m.get('title').toLowerCase(); //for case insensitive sorting
		} else if (this.sortby == 'added') {
			return -1 * m.get('added'); //for descending sorting
		} else if (this.sortby == 'lastmodified') {
			return -1 * m.get('lastmodified');
		} else if (this.sortby == 'clickcount') {
			return -1 * m.get('clickcount');
		}
	},
	initialize: function() {
		this.loadingState = new Backbone.Model({
			page: 0,
			query: {},
			fetching: false,
			reachedEnd: false
		});
	},
	setFetchQuery: function(data) {
		this.loadingState.set({
			page: 0,
			query: data,
			fetching: false,
			reachedEnd: false
		});
	},
	setSortBy: function(sortby) {
		this.sortby = sortby;
		this.loadingState.set({ page: 0, reachedEnd: false });
	},
	fetchPage: function() {
		var that = this;
		if (
			this.loadingState.get('fetching') ||
			this.loadingState.get('reachedEnd')
		) {
			return;
		}
		const nextPage = this.loadingState.get('page');
		this.loadingState.set({ page: nextPage + 1, fetching: true });
		var sortby = this.sortby;
		// Show spinner after 1.5s if we're fetching a new query
		const spinnerTimeout = setTimeout(
			() => nextPage === 0 && this.reset(),
			1500
		);

		return this.fetch({
			data: _.extend({}, this.loadingState.get('query'), {
				page: nextPage,
				limit: BATCH_SIZE,
				sortby: sortby
			}),
			reset: nextPage === 0,
			remove: false,
			success: function(collections, response) {
				clearTimeout(spinnerTimeout);
				let reachedEnd = response.data.length < BATCH_SIZE;
				that.loadingState.set({
					fetching: false,
					reachedEnd: reachedEnd
				});
				if (!reachedEnd && (nextPage <= 1 || nextPage % 2 == 0)) {
					setTimeout(function() {
						that.fetchPage();
					}, 500);
				}
			}
		});
	}
});
