import Backbone from 'backbone';
import templateString from '../templates/BookmarksDisplay.html';

const Marionette = Backbone.Marionette;
const Radio = Backbone.Radio;

export default Marionette.View.extend({
	template: _.template(templateString),
	className: 'bookmarks-display',
	initialize: function(opts) {
		this.app = opts.app;
	},
	events: {
		'click .action-listview': 'activateListView',
		'click .action-gridview': 'activateGridView'
	},
	activateGridView: function() {
		Radio.channel('viewMode').trigger('change', 'grid');
	},
	activateListView: function() {
		Radio.channel('viewMode').trigger('change', 'list');
	}
});
