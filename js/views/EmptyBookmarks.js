import _ from 'underscore';
import Backbone from 'backbone';
import templateStringEmpty from '../templates/EmptyBookmarks.html';
import templateStringLoading from '../templates/LoadingBookmarks.html';

const Marionette = Backbone.Marionette;
const Radio = Backbone.Radio;

export default Marionette.View.extend({
	getTemplate: function() {
		if (this.app.bookmarks.loadingState.get('fetching')) {
			return _.template(templateStringLoading);
		} else if (this.app.bookmarks.length === 0) {
			return _.template(templateStringEmpty);
		} else {
			return _.template('');
		}
	},
	className: 'bookmarks-empty',
	initialize: function(options) {
		this.app = options.app;
		this.listenTo(this.app.bookmarks.loadingState, 'change:fetching', this.render);
	}
});
