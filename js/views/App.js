import _ from 'underscore';
import Backbone from 'backbone';
import SearchController from './SearchController';
import NavigationView from './Navigation';
import ContentView from './Content';

const Marionette = Backbone.Marionette;
const Radio = Backbone.Radio;

export default Marionette.View.extend({
	el: '.app-bookmarks',
	template: _.noop,
	regions: {
		navigation: {
			el: '#navigation-slot',
			replaceElement: true
		},
		content: {
			el: '#app-content',
			replaceElement: true
		}
	},
	initialize: function(options) {
		this.app = options.app;
		this.searchController = new SearchController();

		$(window.document).click(function(e) {
			Radio.channel('documentClicked').trigger('click', e);
		});
	},
	onRender: function() {
		this.showChildView('navigation', new NavigationView({ app: this.app }));
		this.showChildView('content', new ContentView({ app: this.app }));
	}
});
