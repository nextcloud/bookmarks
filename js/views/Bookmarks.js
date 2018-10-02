import Backbone from 'backbone';
import BookmarkCardView from './BookmarkCard';

const Marionette = Backbone.Marionette;
const Radio = Backbone.Radio;

export default Marionette.CollectionView.extend({
	className: 'bookmarks',
	initialize: function(opts) {
		this.app = opts.app;
	},
	childViewOptions: function() {
		return { app: this.app };
	},
	childView: function() {
		return BookmarkCardView;
	},
	onRender: function() {
		this.addChildView(new EmptySpaceView(), this.collection.length);
		this.addChildView(new EmptySpaceView(), this.collection.length);
		this.addChildView(new EmptySpaceView(), this.collection.length);
		this.addChildView(new EmptySpaceView(), this.collection.length);
		this.addChildView(new EmptySpaceView(), this.collection.length);
	}
});

var EmptySpaceView = Marionette.View.extend({
	className: 'empty-space',
	render: function() {}
});
