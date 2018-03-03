import _ from 'underscore';
import Backbone from 'backbone';
import Bookmark from './Bookmark';

const BATCH_SIZE = 30;

export default Backbone.Collection.extend({
	model: Bookmark,
	url: 'bookmark',
	parse: function(json) {
		return json.data;
	},
	setFetchQuery: function(data) {
		this.fetchQuery = data;
		this.page = 0;
		this.fetching = false;
		this.reachedEnd = false;
	},
	fetchPage: function() {
		if (this.fetching || this.reachedEnd) return;
		this.fetching = true;
	    var that = this;
		const nextPage = this.page++;
		return this.fetch({
			data: _.extend({}, this.fetchQuery, {page: nextPage, limit: BATCH_SIZE}),
			reset: nextPage === 0,
			remove: false,
			success: function(collections, response) {
				that.fetching = false;
				that.reachedEnd = response.data.length < BATCH_SIZE;
				that.trigger('fetchPage');
			}
		});
	}
});
