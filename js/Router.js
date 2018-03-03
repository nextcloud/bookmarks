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
			var tags = tagString? tagString.split(',').map(decodeURIComponent) : []; 
			this.app.bookmarks.setFetchQuery({tags: tags, conjunction: 'and'});
			this.app.bookmarks.fetchPage();
			Radio.channel('nav').trigger('navigate', 'tags', tags);
		},
		search: function(query) {
			this.app.bookmarks.setFetchQuery({search: decodeURIComponent(query).split(' ')});
			this.app.bookmarks.fetchPage();
			Radio.channel('nav').trigger('navigate', 'search', query);
		}
	},
	appRoutes: {
		'': 'index',
		'all': 'all',
		'favorites': 'favorites',
		'shared': 'shared',
		'tags(/*tags)': 'tags',
		'search/:query': 'search'
	},
	initialize: function(options) {
		this.controller.app = options.app;
	}
});
