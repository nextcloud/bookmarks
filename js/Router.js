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
			this.app.bookmarks.fetch({ 
				data: {page: -1}
			});
			Radio.channel('nav').trigger('navigate', 'all');
		},
		favorites: function() {
			Radio.channel('nav').trigger('navigate', 'favorites');
		},
		shared: function() {
			this.app.bookmarks.fetch({ 
				data: {page: -1}
			});
			Radio.channel('nav').trigger('navigate', 'shared');
		},
		tags: function(tagString) {
			var tags = tagString? tagString.split(',').map(decodeURIComponent) : []; 
			this.app.bookmarks.fetch({
				data: {tags: tags, page: -1, conjunction: 'and'}
			});
			Radio.channel('nav').trigger('navigate', 'tags', tags);
		},
		search: function(query) {
			this.app.bookmarks.fetch({
				data: {search: decodeURIComponent(query).split(' '), page: -1}
			});
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
