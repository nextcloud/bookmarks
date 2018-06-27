import Backbone from 'backbone';

const Marionette = Backbone.Marionette;
const Radio = Backbone.Radio;

export default Marionette.AppRouter.extend({
	controller: {
		index: function() {
			setTimeout(function(){
				Backbone.history.navigate('all', {trigger: true});
			}, 1);
		},
		all: function() {
			this.app.bookmarks.setFetchQuery({})
			this.app.bookmarks.fetchPage();
			Radio.channel('nav').trigger('navigate', 'all');
		},
		favorites: function() {
			Radio.channel('nav').trigger('navigate', 'favorites');
		},
		shared: function() {
			this.app.bookmarks.setFetchQuery({});
			this.app.bookmarks.fetchPage();
			Radio.channel('nav').trigger('navigate', 'shared');
		},
		tags: function(tagString) {
			var tags = tagString? tagString.split(',') : [];
			this.app.bookmarks.setFetchQuery({tags: tags, conjunction: 'and'});
			this.app.bookmarks.fetchPage();
			Radio.channel('nav').trigger('navigate', 'tags', tags);
		},
		search: function(query) {
			this.app.bookmarks.setFetchQuery({search: decodeURIComponent(query).split(' '), conjunction: 'and'});
			this.app.bookmarks.fetchPage();
			Radio.channel('nav').trigger('navigate', 'search', query);
		},
		untagged: function() {
			this.app.bookmarks.setFetchQuery({untagged: true});
			this.app.bookmarks.fetchPage();
			Radio.channel('nav').trigger('navigate', 'untagged');
		}
	},
	appRoutes: {
		'': 'index',
		'all': 'all',
		'favorites': 'favorites',
		'shared': 'shared',
		'tags(/*tags)': 'tags',
		'search/:query': 'search',
		'untagged': 'untagged'
	},
	initialize: function(options) {
		this.controller.app = options.app;
	}
});
