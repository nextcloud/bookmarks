import Backbone from 'backbone';
import Tags from '../models/Tags';
import BookmarkletView from '../views/Bookmarklet';

const Marionette = Backbone.Marionette;

export default Marionette.Application.extend({
	region: '#bookmarklet_form',
	onBeforeStart: function() {
		var that = this;
		this.tags = new Tags;
		this.tags.fetch({
			data: {count: true},
		});
	},
	onStart: function() {
		this.showView(new BookmarkletView({app: this}));
	},
});
