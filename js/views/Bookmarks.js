import Backbone from 'backbone';
import BookmarkCardView from './BookmarkCard';
import BookmarksDisplayView from './BookmarksDisplay';

const Marionette = Backbone.Marionette;
const Radio = Backbone.Radio;

export default Marionette.CollectionView.extend({
	className: 'bookmarks',
	initialize: function(opts) {
		this.app = opts.app;
		this.listenTo(Radio.channel('viewMode'), 'change', this.changeViewMode);
		this.listenTo(this.app.settings, 'change:viewMode', this.changeViewMode);
	},
	childViewOptions: function() {
		return { app: this.app };
	},
	childView: function() {
		return BookmarkCardView;
	},
	onRender: function() {
		this.addChildView(new BookmarksDisplayView({ app: this.app }), 0);
		this.addChildView(new EmptySpaceView(), this.collection.length + 1);
		this.addChildView(new EmptySpaceView(), this.collection.length + 1);
		this.addChildView(new EmptySpaceView(), this.collection.length + 1);
		this.addChildView(new EmptySpaceView(), this.collection.length + 1);
		this.addChildView(new EmptySpaceView(), this.collection.length + 1);
	},
	changeViewMode: function(mode) {
		if (typeof mode !== 'string') {
			mode = this.app.settings.get('viewMode');
		}
		if (mode === 'list') {
			this.$el.addClass('list-view');
		} else {
			this.$el.removeClass('list-view');
		}
	}
});

var EmptySpaceView = Marionette.View.extend({
	className: 'empty-space',
	render: function() {}
});
