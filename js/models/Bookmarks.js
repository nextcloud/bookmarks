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
		this.abortCurrentRequest();
	},
	setSortBy: function(sortby) {
		this.sortby = sortby;
		this.loadingState.set({ page: 0, reachedEnd: false });
		this.abortCurrentRequest();
	},
	abortCurrentRequest: function() {
		if (this.currentRequest) {
			this.currentRequest.abort();
		}
		if (this.spinnerTimeout) {
			clearTimeout(this.spinnerTimeout);
		}
		if (this.secondPageTimeout) {
			clearTimeout(this.secondPageTimeout);
		}
	},
	fetchPage: function() {
		var that = this;
		if (this.loadingState.get('reachedEnd')) {
			return;
		}
		const nextPage = this.loadingState.get('page');
		const firstPage = nextPage === 0;
		if (!firstPage && this.loadingState.get('fetching') === true) {
			return;
		}
		var sortby = this.sortby;
		this.loadingState.set({ page: nextPage + 1, fetching: true });

		// Show spinner after 1.5s if we're fetching a new query
		this.abortCurrentRequest();
		this.spinnerTimeout = setTimeout(() => {
			firstPage && this.reset();
		}, 1500);

		const currentQuery = this.loadingState.get('query');

		this.currentRequest = this.fetch({
			data: _.extend({}, this.loadingState.get('query'), {
				page: nextPage,
				limit: BATCH_SIZE,
				sortby: sortby
			}),
			reset: firstPage,
			remove: false,
			success: function(collections, response) {
				clearTimeout(that.spinnerTimeout);
				let reachedEnd = response.data.length < BATCH_SIZE;
				that.loadingState.set({
					fetching: false,
					reachedEnd: reachedEnd
				});
				if (!reachedEnd && nextPage % 2 == 0) {
					that.secondPageTimeout = setTimeout(function() {
						that.fetchPage();
					}, 500);
				}
			}
		});
	}
});
