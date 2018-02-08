import Backbone from 'backbone';
import Bookmarks from './models/Bookmarks';
import Tag from './models/Tag';
import Tags from './models/Tags';
import Router from './Router';
import AppView from './views/App';

const Marionette = Backbone.Marionette;

export default Marionette.Application.extend({
	region: '#content',
	onBeforeStart: function() {
		var that = this;
		this.bookmarks = new Bookmarks;
		this.tags = new Tags;
		this.tags.fetch({
			data: {count: true},
			success: function() {
				// we sadly cannot listen ot 'sync', which would fire after fetching, so we have to listen to these and add some timeout
				that.listenTo(that.tags, 'sync', that.onTagChanged);
				that.listenTo(that.tags, 'add', that.onTagChanged);
				that.listenTo(that.tags, 'remove', that.onTagChanged);
			}
		});
		this.listenTo(this.bookmarks, 'sync', this.onBookmarkTagsChanged);

		this.router = new Router({app: this});
	},
	onStart: function() {
		this.showView(new AppView({app: this}));
		Backbone.history.start();
	},
	onTagChanged: function(tag) {
		var that = this;
		if (!(tag instanceof Tag)) return; // we can also receive 'sync' events from the collection, which we don't want here
		if (this.bookmarkChanged) return this.bookmarkChanged = false; // set to true by onBookmarkTagsChanged
		this.tagChanged = true;

		// we need to wait 'till the tag change has been acknowledged by the server
		setTimeout(function() {
			that.bookmarks
				.filter(function(bm) {
					return bm.get('tags').some(function(t) {
						return t === tag.get('name') || t === tag.previous('name');
					});
				})
				.forEach(function(bm) {
					bm.fetch();
				});
		}, 100);
	},
	onBookmarkTagsChanged: function() {
		var that = this;
		if (this.tagChanged === true) return this.tagChanged = false;
		this.bokmarkChanged = true;
		that.tags.fetch({data: {count: true}}); // we listen to 'sync', so we can fetch immediately
	}
});
