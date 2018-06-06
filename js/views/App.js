import _ from 'underscore';
import Backbone from 'backbone';
import SearchController from './SearchController';
import AddBookmarkView from './AddBookmark';
import NavigationView from './Navigation';
import TagsManagementView from './TagsManagement';
import ContentView from './Content';
import SettingsView from './Settings';
import Settings from '../models/Settings';
import templateString from '../templates/App.html';

const Marionette = Backbone.Marionette;
const Radio = Backbone.Radio;

export default Marionette.View.extend({
	template: _.template(templateString),
	regions: {
		'addBookmarks':  {
			el: '#add-bookmark-slot',
			replaceElement: true
		},
		'navigation': {
			el: '#navigation-slot',
			replaceElement: true
		},
		'content': {
			el: '#app-content',
			replaceElement: true
		},
		'tags': {
			el: '#favorite-tags-slot',
			replaceElement: true
		},
		'settings': {
			el: '#settings-slot',
			replaceElement: true
		}
	},
	initialize: function(options) {
		this.app = options.app;
		this.searchController = new SearchController;
    
		$(window.document).click(function(e) {
			Radio.channel('documentClicked').trigger('click', e);
		});
	},
	onRender: function() {
		this.showChildView('addBookmarks', new AddBookmarkView());
		this.showChildView('navigation', new NavigationView);
		this.showChildView('settings', new SettingsView({model: this.app.settings}));
		this.showChildView('content', new ContentView({app: this.app})); 
		this.showChildView('tags', new TagsManagementView({collection: this.app.tags}));
	}
});
